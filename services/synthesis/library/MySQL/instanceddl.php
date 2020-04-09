<?php
/**
* One of these classes should be instantiated to work with the instance (table) DDL for any instance.
*
* All original code.
* @package Synthesis
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2014 Lifetoward LLC
* @license proprietary
*/
class InstanceDDL
{
	protected $class = null, $columns = array(), $indexes = array(), $foreign = array(), $comment = null, $db = null;

	public function __construct( $class , Database $db = null )
	{
		if (!($this->db = $db))
			$this->db = $GLOBALS['root'];
		if (!is_subclass_of($class, 'Instance'))
			throw new ErrorException("You must provide the name of a subclass of Instance.");
		$this->class = $class;
		$this->comment = $this->db->dbEscapeString($class::$descriptive) ." ($class)";

		$unikeys = array();

		if (!is_array($class::$keys)) {
			$key = $class::$keys;
			$this->columns["_$key"] = "`_$key` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '$class Primary Key'";
			$this->indexes["_$key"] = "PRIMARY KEY (`_$key`)";
		} else
			foreach ($class::$keys as $key=>$kClass) {
				$this->columns["_$key"] = "`_$key` INT(10) UNSIGNED NOT NULL COMMENT '$class Relational Key'";
				$this->indexes["_$key"] = "KEY (`_$key`)";
				$this->foreign["_$key"] = "CONSTRAINT `{$class::$table}__{$key}_{$kClass::$table}` FOREIGN KEY (`_$key`) REFERENCES `{$kClass::$table}` (`_id`)"; // ON DELETE RESTRICT
				$unikeys["{$class::$table}__primary"][] = "_$key";
			}

		if (isset($class::$sysvals))
			foreach ((array)$class::$sysvals as $col)
				if (!($this->columns["_$col"] = is_callable("$class::getSysvalDDL") ? $class::getSysvalDDL($col) : null))
					$this->columns["_$col"] = "`_$col` VARCHAR(255) DEFAULT NULL COMMENT '$col, System Value'";

		foreach ($class::$fielddefs as $fd) {
			if (!$fd['derived']) {
				if ($fd['unique'])
					$unikeys[$fd['unique']][] = $fd['name'];
				if ($fd['identifying'])
					$unikeys['_ident'][] = $fd['name'];
			}
			if (in_array($fd['type'], array('include','require','belong','refer','fieldset'))) {
				// ON UPDATE constraints as RESTRICT are necessary because the system may have handles stored anywhere (like instance-type fields).
				// With those present in the system, not all keys to tables that might update their keys could be duly informed with the change. For this reason, we use the
				// constraints available to us to prevent keys CHANGING. Meanwhile if keys are removed from the system, that's easier to handle. A stale id or handle can readily
				// deal with the situation of a data record missing. It can't know what to do if the data record is no longer what it had been relied upon to contain.
				// Note that we do NOT expressly include those restrictions in the SQL snippets below, but that is only because the output of
				// SHOW CREATE TABLE ... does not include them and we'd like to retain string comparison ability with those lines.
				// The official MySQL docs say that if unspecified, ON UPDATE defaults to RESTRICT.
				$reftable = $fd['class']::$table;
				if ($fd['type'] == 'fieldset') {
					$nuller = 'DEFAULT'; $ondel = 'ON DELETE SET NULL'; // a fieldset is an optional value... it must not be destructive of its referring instance
				} else if ($fd['type'] == 'include') {
					$nuller = 'NOT'; $ondel = 'ON DELETE CASCADE'; // ripple deletion of deepest ancestor throughout the entire encapcelation instance
					$unikeys["_inckey_$fd[name]"] = array($fd['name']);
				} else if ($fd['type'] == 'require') {
					$nuller = 'NOT'; $ondel = ($fd['ondelete'] ? 'ON DELETE '. $fd['ondelete'] : null); // RESTRICT is the default on-delete mode and won't appear in the canonical form
				} else if ($fd['type'] == 'belong') {
					$nuller = 'NOT'; $ondel = 'ON DELETE '. ($fd['ondelete'] ? $fd['ondelete'] : 'CASCADE');
				} else if ($fd['type'] == 'refer') {
					$nuller = 'DEFAULT'; $ondel = 'ON DELETE '. ($fd['ondelete'] ? $fd['ondelete'] : 'SET NULL');
				} else
					throw new ErrorException("Invalid system type found for fielddef with name '$fd[name]'");
				$this->foreign[$fd['name']] = "CONSTRAINT `{$class::$table}_$fd[name]_$reftable` FOREIGN KEY (`$fd[name]`) REFERENCES `$reftable` (`_id`) $ondel";
				$this->columns[$fd['name']] = "`$fd[name]` int(10) unsigned $nuller NULL COMMENT '$fd[type]: ". $this->db->dbEscapeString($fd['class']::$singular ." ($fd[class])")  ."'";

			} else if (!$fd['derived'] && is_subclass_of($fd['class'], 'Type')) {
				$this->columns[$fd['name']] = $fd['mysql_ddl'] ? call_user_func("$class::$fd[mysql_ddl]", $fd, $this->db) : $fd['class']::mysql_ddl($fd, $this->db);
			}
		} // foreach fielddef

		foreach ($unikeys as $name=>$fs)
			$this->indexes[$name] = "UNIQUE KEY `$name` (`". implode("`,`", $fs) ."`)";
	}

	public function getCreateDDL( $foreign = false )
	{
		if (!count($this->columns))
			return null;
		$class = $this->class;
		// There's a specific reason we do "IF NOT EXISTS" only when foreign keys are included: If we create a table intentionally without dependencies and it fails, one reason could be that the table already exists.
		// We'd want to be aware of that, but we don't care if the table exists when the deps are included because there we want to highlight dependency failures.
		return "CREATE TABLE ". ($foreign ? "IF NOT EXISTS" : null) ." `{$class::$table}` (\n   ".
			implode("\n  ,", $this->columns) .
			(count($this->indexes) ? "\n  ,". implode("\n  ,", $this->indexes) : null) .
			(count($this->foreign) && $foreign ? "\n  ,". implode("\n  ,", $this->foreign) : null) .
			"\n) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='$this->comment'";
	}

	public function getAddConstraintsDDL( )
	{
		if (!count($this->foreign))
			return null;
		$class = $this->class;
		return "ALTER TABLE `{$class::$table}` ADD ". implode(", ADD ", $this->foreign);
	}

	/**
	* @return string The query to execute to update the database to a modern DDL for this table. Will return null if there are no updates to perform. If a CREATE is required, it will return create DDL.
	*/
	public function getUpdateDDL( )
	{
	}

/* * * * * * * * * * * BELOW HAS NOT BEEN PROVEN OR INTEGRATED INTO A CALLING MODEL * * * * * * * * * * * * * */

	private function discoverDeployedDDL( )
	{
		throw new ErrorException("discoverDeployedDDL is not ready to use yet.");

		$ddl = array('instype'=>'element', 'table'=>static::$table);
		$m = array();

		$r = capcel_get_record("SHOW CREATE TABLE `$ddl[table]`");
		if (!preg_match("/CREATE TABLE `$table` \((.*)\)([^)]*)$/", $r['Create Table'], $m))
			throw new ErrorException("Failed to comprehend the SHOW CREATE TABLE feedback for $ddl[table].");
		if (count($lines = explode(",\n   ", trim($m[0]))) < 2)
			throw new ErrorException("Got less than 2 lines in the SHOW CREATE TABLE feedback for $ddl[table].");
		$ddl['tail'] = $m[1];

		foreach ($lines as $line) {
			if (preg_match("/^`_id` int(10) unsigned not null auto_increment/iu", $line))  // _id column definition
				$elementy |= 1;
			else if (preg_match("/^PRIMARY KEY \(`_id`\)/iu", $line)) // _id primary key
				$elementy |= 2;
			else if (preg_match("/^KEY `([:alnum:]*_[:alnum:]*_[:alnum:]*)` \(`([:alnum:]*`\)/iu", $line)) // local key declaration supporting foreign key
				continue; // we ignore these because we don't formally require nor recognize them
			else
				parent::mysql_ddl_fields_discover($line, $ddl);
		}

		if ($elementy != 3)
			throw new ErrorException("Unable to interpret a proper element in the database-derived schema for `$ddl[table]`");

		return $ddl;
	}

	private function mysql_ddl_fields_discover( $line, array &$ddl )
	{
		if (preg_match("/^CONSTRAINT `([:alnum:]*_[:alnum:]*_[:alnum:]*)` FOREIGN KEY \(`([:alnum:]*)`\) REFERENCES `([:alnum:]*)` \(`_id`\)/iu", $line, $m)) // foreign key constraint
			$ddl['refkeys'][$m[1]] = $line;
		 else if (preg_match("/^UNIQUE (KEY )? `([:alnum:]_]*)` \(`([`,[:alnum:]_]*)`\)/iu", $line, $m))  // unique key declaration
			$ddl['unikeys'][$m[1]] = explode('`,`', $m[2]);
		 else if (preg_match("/^`([:alpha:][:alnum:]*)` /iu", $line, $m))  // simple field definition
			$ddl['columns'][$m[0]] = $line;
		 else if (preg_match("/^`([:alpha:][:alnum:]*))_([:alnum:]*)` /iu", $line, $m))  // multi-part field definition
			$ddl['columns'][$m[0]][$m[1]] = $line;
		 else
			$ddl['unknowns'][] = $line;
	}

}
