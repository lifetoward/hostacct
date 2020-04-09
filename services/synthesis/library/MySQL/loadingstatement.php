<?php
/**
* You're gonna use one of these per table loaded and merge them together (typically using $q->incorporate($q->joinedStatement(...))) to build the overall query
* By design, this query builder class is almost unaware of all inter-instance reference semantics.
* However, if you provide Instance subclasses to the static constructors and joinTable method, you get the helpful benefit of enabling automatic creation of missing tables.
* This class's lack of awareness of the reference semantics allows it to be more generally useful for queries which might fall outside of the scope of the usual Synthesis data model.
*
* All original code.
* @package Synthesis/MySQL
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2014 Lifetoward LLC
* @license proprietary
*/
class LoadingStatement
{
	protected $fields = array(), $order = array(), $tables = array(), $where = array(), $groupby = array(), $having = array(); // the accumulators
	protected $db, $sortfield, $reverse, $tablias, $prefix, $class, $sorting, $limit; // the parameters
	public $queryTime;
	protected $result = null, $records = null;

	/**
	* The principal API for all but the instance classes consists of operations to retrieve pseudo properties of the executed query, as in $record = $dbls->next . See below:
	* Note: After fetching "records", "result", or one more than the last "next" record, it contains no reference to the dataset.
	* @property int	$rowCount - returns the number of result records obtained
	* @property array[] $records - returns a list of associative arrays (records) - The keys of the records are the fully prefixed field names as determined by the query building logic.
	* @property array $next - returns a single associative array (record) from the query, ie. the next in the iteration; this is the recommended way to retrieve all records in a loaded dataset
	*		After the last record is returned, the next returns null and the query result in the LoadingStatement object is freed. That is, you can only read the records out this way once. This is to save memory.
	* @property resource $result - returns the low-level database connection's query result resource. Once we provide this to you, me release our reference to it and the result becomes entirely yours to manage.
	*/
	public function &__get( $name )
	{
		if ($name == 'rowCount')
			return $this->result ? $this->result->num_rows : count($this->records);

		if ($name == 'records') {
			if (!$this->records && $this->result) {
				$this->records = array();
				while ($this->records[] = $r->fetch_assoc()); // assignment intended
			}
			if ($this->result) {
				$this->result->free();
				$this->result = null;
			}
			return $this->records;
		}
		if ($name == 'next') {
			if ($this->result) {
				if (null === ($record = $this->result->fetch_assoc())) { // assignment intended
					$this->result->free();
					$this->result = null;
				}
				return $record;
			}
			return null;
		}
		if ($name == 'result') { // if you request the result resource, you own it and we disown it... free it yourself
			$r = $this->result;
			$this->result = null;
			return $r;
		}
	}

	protected function __construct( $class, Database $db, $sortfield = null, $reverse = false, $prefix = null, $sorting = true )
	{
		$this->class = is_subclass_of($class, 'Instance') ? $class : null;
		foreach (array('db','sortfield','reverse','prefix','sorting','class') as $prop)
			$this->$prop = $$prop;
		$this->queryTime = date('Y-m-d H:i:s');
	}

	/**
	* Call this to get a top-level statement object to build with
	*/
	public static function newStatement( $class, Database $db, $sortfield = null, $reverse = false, $sorting = true )
	{
		$q = new LoadingStatement($class, $db, $sortfield, $reverse, null, $sorting);
		$q->tablias = class_exists($class) && isset($class::$table) ? $class::$table : (is_string($class) ? $class : null);
		if (!$q->tablias)
			throw new ErrorException("We require a class with static property \$table defined, or a string to use for the table name.");
		$q->tables[$q->tablias] = "FROM `$q->tablias` ";
		return $q;
	}

	/**
	* Call this to get a substatement object to build with and later incorporate
	*/
	public function joinedInstance( $class, $addTablias, $addPrefix, $joinOn, $sorting = null )
	{
		$sub = new LoadingStatement($class, $this->db, $this->sortfield, $this->reverse, "$this->prefix$addPrefix", isset($sorting) ? $sorting : $this->sorting);
		$subtable = class_exists($class) && isset($class::$table) ? $class::$table : (is_string($class) ? $class : null);
		if (!$subtable)
			throw new ErrorException("We require a class with static property \$table defined, or a string to use for the table name.");
		$sub->prefix = "$this->prefix$addPrefix";
		$sub->tablias = "{$this->tablias}_$addTablias";
		$sub->tables[$sub->tablias] = " LEFT JOIN `$subtable` AS `$sub->tablias` ON(".
			(is_array($joinOn) ? "`$this->tablias`.`$joinOn[0]` = `$sub->tablias`.`$joinOn[1]`" : str_replace('{}', $this->tablias, $joinOn)) .")";
		return $sub;
	}

	/**
	* Merges the query components from another LoadingStatement into this one. Typically the other was created using $this->joinedInstance().
	* We don't incorporate grouping clauses from substatements
	* @param LoadingStatement $sub The statement builder to merge in
	* @param mixed $asField (optional) If you pass false here, we will ignore sorting clauses from the sub.
	*		If you provide a string, then we will check it against the sortfield specification, and if they match, we will place the sub's ordering clauses in front of our own rather than behind.
	*/
	public function incorporate( LoadingStatement $sub, $asField = null )
	{
		foreach (array('fields', 'tables', 'where', 'having') as $a)
			$this->$a = array_merge($this->$a, $sub->$a);
		if ($asField !== false && $this->sorting)
			$this->order = $asField == $this->sortfield ? array_merge($sub->order, $this->order) : array_merge($this->order, $sub->order);
	}

	/**
	* This method allows you to join an additional table to the query without setting up a substatement to handle fields and such. It has several ways to interpret the parameters.
	* @param string $class Either the name of a class with a static property called "$table" or the name of a table to join. Identifies the table to join.
			If you don't provide a value here, we will assume the $on value you provide is a complete SQL JOIN clause, possibly containing table name substitution points.
	* @param string|string[] $on Specifies the criteria on which to join. We recognize a list array or a string:
	*		If a list array, then the criteria will be a direct match between a field in the established table (the first array value) and a field in the joined table (second array value); no punctuation on these field names, please.
	*		If a string, then we'll substitute the table alias for {} in the string and then use it directly as an ON clause.
	* @param string $join (optional) We use LEFT JOIN to join the tables unless you provide something else here or provide no $class parameter.
	* @return string Returns the table's alias according to how it will appear in the query. Meanwhile the table join clause will have been added to the statement.
	*/
	public function joinTable( $class, $on, $join = "LEFT JOIN" )
	{
		if (is_string($on))
			$on = str_replace('{}', $this->tablias, $on);
		if (is_subclass_of($class, 'Instance')) {
			$tablias = "{$this->tablias}_{$class::$table}_";
			if (is_array($on)) {
				list($from, $to, $jtype) = $on;
				$on = "`$this->tablias`.`$from` = `$tablias`.`$to`";
				if ($jtype)
					$join = $jtype;
			}
			$this->tables[$tablias] = $on ? " $join `{$class::$table}` AS `$tablias` ON($on) " : " ". str_replace('{}', $this->tablias, $join) ." ";
		} else {
			if (!is_string($on))
				throw new ErrorException("Invalid parameters to LoadingStatement->joinTable(\$table, \$clause [, \$join]): When the first parameter is not an Instance class, the clause must be an SQL snippet.");
			if ($tablias = is_string($class) ? "{$this->tablias}_{$class}_" : null) { // assignment intended
				$this->tables[$tablias] = "$join `$class` AS `$tablias` ON($on)";
			} else
				$this->tables[] = " $on ";
		}
		return $tablias;
	}

	/**
	* @param string $name The actual column name as defined in the database table ddl
	* @param string $derivation If supplied, this SQL expression will be used to describe the column's value rather than the default "`$tablias`.`$name`". Note that we perform {} => tablias substitution for you.
	* @return void
	*/
	public function addColumn( $name, $derivation = null )
	{
		$this->fields["{$this->prefix}$name"] = ($derivation ? str_replace('{}', $this->tablias, $derivation) : "`$this->tablias`.`$name`") ." AS `{$this->prefix}$name`";
	}

	/**
	* @param string $clause The logical clause in SQL which will be AND-related to others in the top-level where clause
	* @param boolean $aggregate If you set aggregate to TRUE, then we'll put this clause in the HAVING section rather than the WHERE section
	*/
	public function addSQLSelector( $clause, $aggregate = false )
	{
		if (is_string($clause))
			$clause = [$clause];
		if (!is_array($clause))
			throw new ErrorException("Filtering clauses must be strings or arrays.");
		foreach ($clause as $c) {
			$x = '('. str_replace('{}', $this->tablias, $c) .')';
			if ($aggregate)
				$this->having[] = $x;
			else
				$this->where[] = $x;
		}
	}

	/**
	* This is a partially abstract way to do field-based selection. We still require an SQL operator as part of the constant predicate. But the subject we handle for you.
	* @param string $fieldName Field name valid within this class. (included fields are allowed, but chained fields should be selected within the joined instance substatement)
	* @param string $predicate The predicate must consist of a valid SQL operator followed by a constant or properly constructed SQL expression.
	* @param boolean $aggregate If you set aggregate to TRUE, then we'll put this clause in the HAVING section rather than the WHERE section
	* @return void
	*/
	public function addFieldSelector( $fieldName, $predicate, $aggregate = false )
	{
		$this->addSQLSelector("{}.$fieldName $predicate", $aggregate);
	}

	/**
	* @param string $name The column to sort by, usually the field name, unless overridden (below)
	* @param boolean $descending Pass a true value if this field is configured to normally sort descending.
	* @param string $override (optional) if you pass this we will sort by a column other than the name provided. This let's you CHOOSE whether to sort first based on the field name while actually sorting on the real column.
	*/
	public function addSorting( $name, $descending = false, $column = null )
	{
		if (!$this->sorting)
			return;
		$sorter = "`$this->prefix". ($column ? $column : $name) ."` ". ($this->reverse ? ($descending ? 'ASC' : 'DESC') : ($descending ? 'DESC' : 'ASC'));
		if ($this->sortfield == $name)
			array_unshift($this->order, $sorter);
		else
			array_push($this->order, $sorter);
	}

	/**
	* Turn sorting on or off for this statement or substatement
	* @param boolean $bool Provide true to enable sorting, false to disable it.
	*/
	public function setSorting( $bool )
	{
		$this->sorting = $bool && true;
	}

	/**
	* Call this to limit the result row count or begin returning records from somewhere beyond the first in the selected set.
	* @param int $count A limit on the number of rows to obtain.
	* @param int $start An ordinal specifying which row in the selected result set to return first... ie. skip past the first N-1 rows.
	* @return void The appropriate row selection criteria are set in the query.
	*/
	public function setLimits( $count, $start = 0 )
	{
		$start+=0; $count+=0;
		if ($count || $start) {
			if ($count <= 0)
				$count = 1000000;
			if ($start < 0)
				$start = 0;
			$this->limit = "LIMIT $start,$count";
		} else
			$this->limit = null;
	}

	public function addGrouping( $name )
	{
		$this->groupby[] = "`$this->tablias`.`$name`";
	}

	/**
	* @return string Call this to obtain the SQL query, ready to run.
	*/
	public function getQuery( )
	{
		return "SELECT \n   ".
			implode("\n  ,", $this->fields) ."\n".
			implode("\n", $this->tables) ."\n".
			(count($this->where) ? "WHERE ". implode(' AND ', $this->where) ."\n" : null) .
			(count($this->groupby) ? "GROUP BY ". implode(',', $this->groupby) ."\n" : null) .
			(count($this->having) ? "  HAVING ". implode(' AND ', $this->having) ."\n" : null) .
			(count($this->order) && $this->sorting ? "ORDER BY ". implode(', ', $this->order) ."\n" : null) .
			"$this->limit;\n";
	}

	/**
	* This recursive method just builds an array of table=>class array elements, including every table that can possibly be depended upon by the passed class.
	* @param string $class The name of a Instance subclass.
	* @return array Associative array with tablenames as keys and classes as values.
	*/
	private static function getRequiredTablesList( $class, array &$result = array() )
	{
		if (!is_subclass_of($class, 'Instance'))
			return array();
		$result[$class::$table] = $class;
		if (is_array($kds = $class::$keys))
			foreach ($kds as $key=>$kclass)
				if (!$result[$kclass::$table])
					self::getRequiredTablesList($kclass, $result);
		if (is_array($jds = $class::getJoins()))
			foreach ($jds as $jclass=>$jd)
				if (!$result[$jclass::$table])
					self::getRequiredTablesList($jclass, $result);
		foreach ($fds = $class::$fielddefs as $fd)
			if (in_array($fd['type'], array('include','fieldset','require','belong','refer')))
				if (!$result[$fd['class']::$table])
					self::getRequiredTablesList($fd['class'], $result);
		return $result;
	}

	/**
	* Call this method to execute the query you've been building.
	* If the query was built for subclasses of Instance, if we find that the query fails due to a missing table, we will resolve the matter automatically and try the query again.
	* @param boolean $disableLogging If you want to prevent logging of this query (for security reasons) pass true here.
	* @return integer Returns the number of rows in the result set.
	*/
	public function execute( $disableLogging = false )
	{
		$fkeyAddQs = array();
		try {
			for ($x = 0; $x < 2; $x++) { // for single retry in case of a missing table; one retry is enough because we now have an exhaustive and reliable approach to resolving a missing table
				try {

					$this->result = $this->db->dbQuery($this->getQuery(), $disableLogging ? '*nolog' : null);
					break; // success exits the retry-on-error loop

				} catch (dbMissingTableX $ex) {
					// If we have a missing table, we will determine a list of ALL POSSIBLE dependencies this whole query could have and then make sure every table is out there.
					// This approach does NOT require tracking the tables in use during query building... instead we build it on demand here and now
					$tabledeps = self::getRequiredTablesList($this->class);
					if (!in_array($ex->tablename, array_keys($tabledeps))) {
						logDebug(array("This is the table dep list we obtained."=>$tabledeps));
						throw new ErrorException("We got a missing table exception, but our list of table dependencies does not contain table '$ex->tablename'. If you didn't use Instance classes, that would make sense.", $ex);
					}
					foreach ($tabledeps as $table=>$class) {
						// We do this "create with deps" then if fail create without approach because we want to do nothing at all when the table already exists, which looks like success on the first call.
						// This way we know that if we have to create it without deps, the table really was missing and the addition of constraints later will be appropriate.
						logDebug("Creating table $table for class $class");
						$ddl = new InstanceDDL($class);
						try { $this->db->dbQuery($ddl->getCreateDDL(true), "Creating table for class '$class' with foreign key dependencies."); }
						catch (dbFailedDependencyX $x) {
							$this->db->dbQuery($ddl->getCreateDDL(false), "Creating table for class '$class' DEFERRING foreign key dependencies.");
							$fkeyAddQs[$class] = $ddl->getAddConstraintsDDL();
						}
					}
					// So at this point every table has been created, while some are lacking constraints, but now that all are created, we know the constraints can be added without fail.
					foreach ($fkeyAddQs as $fkclass=>$qs) {
						if ($qs)
							$this->db->dbQuery($qs, "Adding foreign key constraints for class '$fkclass'");
						$fkeyAddQs[$fkclass] = null; // we unset each as we complete them so if there's a problem with any, only those we haven't completed remain
					}

				} catch (dbMissingColumnX $ex) {
					// If we have a missing column, we can fix that too, because adding a column is not dangerous to existing data.
					// We just haven't implemented this yet... it requires the column-specific differentiation for DDL updates in InstanceDDL
					throw $ex;
				}
			} // closes the retry loop

		} catch (Exception $ex) {
			// Any failed dependencies which could not be handled internally or general errors other than a missing table or column error are caught here.
			// We're going to rethrow, but we want to dump some info first to facilitate recovery
			foreach ($fkeyAddQs as $fkclass=>$qs)
				if ($qs)
					$fkeyList[] = "Class '$fkclass': [ $qs ]";
			if (count($fkeyList))
				throw new Exception("ATTENTION: THE FOLLOWING CONSTRAINTS ARE STILL REQUIRED FOR NEWLY CREATED TABLES:\n  ". implode("\n  ", $fkeyList), 1000, $ex);
			throw $ex;
		}

		return $this->result->num_rows;
	}

	public function __destruct( )
	{
		if ($this->result)
			$this->result->free();
	}

}
