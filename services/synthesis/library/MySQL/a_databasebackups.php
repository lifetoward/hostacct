<?php

class a_DatabaseBackups extends Action
{
	public function render( Context $c )
	{
		$c->action = $this;

		// ...

		return $this->getResult(static::PROCEED);
	}

	public static function legacy( )
	{
		require_once('init.php');
		capcel_init('sysadmin', CAPCEL_DBSUPER, 'admin');
		$appconfig = $_SESSION['appconfig'];
		if ($_GET['action'] == 'dlbackup') {
			mysql_close();
			// $this->mydb = new mysqli(null, null, null, $name = "synthesis_$_ENV[PHASE]", null, $socket = "$_ENV[ACCOUNT_HOME]/mysql/mysqld.sock");
			@include("$sysroot/privdb.php");
			capcel_connect($privdb);
			$views = array(); $tables = array();
			$q1 = capcel_query('show tables');
			while ($a = mysql_fetch_row($q1)) {
				$table = $a[0];
				$c = mysql_fetch_assoc(capcel_query("show create table `$table`"));
				if ($c['View'] == $table) {
					$views[$table] = preg_replace('|CREATE .* VIEW|', 'CREATE VIEW', $c['Create View']);
					continue;
				} else {
					$tables[$table]['create'] = $c['Create Table'];
					$info =& $tables[$table];
					$matches = array();
					if ($fkeys = preg_match_all("|,.* FOREIGN KEY .* REFERENCES `(.*)` .*[,)]|isU", $info['create'], $matches, PREG_PATTERN_ORDER))
						for ($phrase = 0; $phrase < $fkeys; $phrase++) {
							$reftable = $matches[1][$phrase];
							$info['requires'][] = $reftable;
						}
					$q = capcel_query("show fields in `$table`");
					while ($b = mysql_fetch_row($q))
						$info['fields'][] = $b[0];
				}
				unset($info);
			}
			debug_message("ORIGINAL: ". implode(array_keys($tables), ', '));
			$ordered = array();
			while (count($tables)) // reset to the remaining tables and look through them for one that can go
				foreach ($tables as $table=>$info)  { // look through each table not yet approved and see if it can go now
					if (is_array($info['requires']))
						foreach ($info['requires'] as $req)  // if the table requires any table which is not already ordered, then we have to leave it here.
							if (!array_key_exists($req, $ordered))
								continue 2;			// The table requires something not yet ordered, so we must wait and proceed to the next candidate
					$ordered[$table] = $info; // we have no unordered pre-reqs, so we add this table to the ordered list...
					unset($tables[$table]); // and drop it from the original list for the next go-round
				}
			debug_message("ORDERED: ". implode(array_keys($ordered), ', '));
			debug_dump_array($ordered);

			$result = "-- Lifetoward information management system backup\n--\n".
				"-- THE INFORMATION IN THIS FILE IS CONFIDENTIAL AND THE PROPERTY OF INNERACTIVE WELLNESS, INC\n".
				"-- UNAUTHORIZED USE IS PROHIBITED.\n--\n".
				"-- Application database for InnerActive Wellness (lifetowa_inneractive)\n".
				"-- This backup should be restored into a blank database.\n".
				"-- Timestamp: ". date("Y-m-d H:i:s") ." (server timezone, i.e. Utah)\n\nSET SQL_MODE=\"NO_AUTO_VALUE_ON_ZERO\";\n\n";

		//	capcel_query("FLUSH TABLES WITH READ LOCK");
			foreach ($ordered as $table => $info) {
				$result .= "\n". $info['create'] .";\n";
				if ($q = capcel_query("SELECT `". implode($info['fields'], "`, `") ."` FROM `$table`", "Failure on data fetch for $table")) {
					if (mysql_num_rows($q)) {
						$recsep = ' ';
						$result .= "\nINSERT INTO `$table` (`". implode($info['fields'], "`, `") ."`) VALUES";
						while ($a = mysql_fetch_row($q)) {
							$conv = array();
							foreach ($a as $f)
								$conv[] = (null === $f || !isset($f) ? "NULL" : "'". mysql_escape_string($f) ."'");
							$result .= "\n\t$recsep( ". implode($conv, ",") ." )";
							$recsep = ',';
						}
						$result .= ";\n";
					} else
						$result .= "\n-- NO RECORDS IN THIS TABLE\n";
					mysql_free_result($q);
				}
			}
		//	capcel_query("UNLOCK TABLES");
			mysql_close();

			$result .= "\n\n-- VIEWS (DERIVED TABLES)\n";
			if (is_array($views))
				foreach ($views as $create)
					$result .= "\n$create;\n";

		//	header("Content-type: text/plain");
		//	header('Content-disposition: attachment; filename=inneractive-'. date('YmdHis') .'.sql');
		//	echo $result;
			header("Content-type: application/x-bzip2");
			header('Content-disposition: attachment; filename='. $appconfig['appname'] .'-'. date('YmdHis') .'.sql.bz2');
			echo bzcompress($result);
			exit;
	} // legacy

}
