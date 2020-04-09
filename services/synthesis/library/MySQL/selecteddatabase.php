<?php
/**
* MySQL implementation of Database as a trait to be used by a root context.
*
* All original code.
* @package Synthesis/MySQL
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2014 Lifetoward LLC
* @license proprietary
*/
trait SelectedDatabase
{
	/**
	* @var object $mydb MySQLi connection object
	*/
	private $mydb;

	/**
	* Root contexts are empowered to establish and broker the connection to the database.
	* Because we use only a single privileged database authentication for the entire app, establishing the connection once at the beginning of the request is appropriate and sufficient.
	* @return void
	* @throws ErrorException A connection or configuration failure will throw an error.
	*/
	public function dbConnect( )
	{
		if (!$this->mydb instanceof mysqli) {
		
			try {
				$this->mydb = new mysqli(null, null, null, $name = "synthesis_$_ENV[PHASE]", null, $socket = "$_ENV[ACCOUNT_HOME]/mysql/mysqld.sock");
				// Note, contrary to the PHP documentation, the mysqli constructor is actually generating hard errors before it returns.
				// That means we catch an exception (converted from an error) rather than just check $this->mydb->connect_errno as the docs suggest.
			} catch (ErrorException $ex) {
				print <<<html
<html><body><h3>Failed database connection!</h3><p>We cannot proceed without the database. Perhaps it's not configured yet.</p>
<p>We're using the following database connection info:</p>
<dl><dt>Database name</dt><dd>$name</dd><dt>Unix socket</dt><dd>$socket</dd></dl>
</pre></body></html>
html;
				//logError("Failed to connect to the database!\n$ex\nRedirecting to the resolution handler!");
				//header("Location: $urlbase/nodb.php");
				exit;
			}

			// Configure the timezone and character set
			if (!$this->mydb->query("SET SQL_BIG_SELECTS=1") || !$this->dbSetTimezone() ||
					!$this->mydb->set_charset("utf8") || !$this->mydb->query("SET CHARACTER SET 'utf8'") || !$this->mydb->query("SET NAMES 'utf8'"))
				throw new ErrorException("Unable to configure the database connection (charset, timezone, big selects): ". $this->mydb->error);
		}
		$GLOBALS['root'] = $this;
	}

	public function dbSetTimezone( $timezone = null )
	{
		$tz = $timezone ? $timezone : ($this->timezone ? $this->timezone : 'America/Chicago');
		if ($this->mydb instanceof mysqli)
			return $this->mydb->query("SET SESSION time_zone='$tz'");
	}

	/**
	* Obtain the ID of the newest created db record
	* @return integer numeric primary ID of db record last inserted
	*/
	public function dbNewId( )
	{
		return $this->mydb->insert_id;
	}

	/** dbEscapeString()
	* Performs special character conversion on a string which needs to pass through db query syntax unharmed.
	* @param string Text to convert
	* @return string converted text
	*/
	public function dbEscapeString( $text )
	{
		return $this->mydb->escape_string($text);
	}

	/**
	* Performs a raw SQL query against the database. Note that in some cases you'd need to know which database you're using in order to get the query just right, but solid SQL
	* should work almost always.
	* The value-add here includes database query time tracking, conversion of certain error conditions to specific exception types, and logging of the query for debug/audit purposes.
	* @param string SQL query string
	* @param string (optional) A description of the purpose of the query for audit/logging purposes.
	* @return resource A mysqli request response object.
	* @throws dbDuplicateDataX When the operation failed due to failing a Unique key spec
	* @throws dbUndeletableX When the operation can't proceed because foreign key constraints prevent it
	* @throws dbQueryX For all other failures of the database request itself
	* @throws ErrorException When there's no connection established to the database
	*/
	public function dbQuery( $query, $intent = NULL )
	{
		$logquery = $intent=='*nolog' && $GLOBALS['appconfig']['phase'] != 'dev' ? "(query logging blocked)" : $query;

		if (! $this->mydb instanceof mysqli) {
			logError("Unable to invoke query \"$logquery\" because we have no connection to the database.");
			return;
		}
		$start = microtime(true);
		if (!($r = $this->mydb->query($query, MYSQLI_STORE_RESULT))) {
			if ($dbxClass = dbQueryX::$ErrorCodes[$this->mydb->errno]) // assignment intended
				throw new $dbxClass($this->mydb, $logquery, $intent);
			throw new dbQueryX($this->mydb, $logquery, $intent);
		}
		global $profile;
		$profile['dbtotal'] += ($elapsed = microtime(true) - $start);
		if ($profile['dbmax'] < $elapsed)
			$profile['dbmax'] = $elapsed;
		$profile['dbqueries']++;
		logDebug("Success: [$logquery] in $elapsed sec");
		return $r;
	}

	/** dbGetScalar()
	* Takes an unchecked SQL query which is designed to return a single column of a single row.
	* We handle the manipulations to return just that value and release the result.
	* @param string SQL query which generates a single column in a single row.
	* @return string The value from that column/row.
	* @throws NotSingularX When the query generates more than one row or more than one column.
	*/
	public function dbGetScalar( $query, $msg = null )
	{
		$r = $this->dbQuery($query, $msg);
		if ($r->num_rows > 1 || $r->field_count > 1)
			throw new dbNotSingularX($query, "dbGetScalar: $msg");
		$x = $r->fetch_row();
		$r->free();
		return $x[0];
	}

	/** dbGetRecords()
	* Returns a database query result as a sequential array of associative arrays.
	* No checking is done on the query, including reasonable size limitation. Beware.
	* @param string SQL query
	* @return array Query result as a sequential array of associative arrays
	*/
	// returns an array of associative arrays (field=>value) generated by the given query
	public function dbGetRecords( $qstring, $errormsg = null )
	{
		$result = array();
		if ($r = $this->dbQuery($qstring, $errormsg)) {  // assignment intended
			while ($a = $r->fetch_assoc()) // assignment intended
				$result[] = $a;
			$r->free();
		}
		return $result;
	}

	public function __get( $var )
	{
		if (in_array($var, array('error','errno','connect_errno','connect_error','error_list','server_info','client_info')))
			return $this->mydb->$var;
		if (count(class_parents($this)))
			return parent::__get($var);
		return null;
	}

	/**
	* We could pass-thru calls to mysqli as a convenience for programmers, and as a possible lead-in to database independence.
	public function __call( $name, $args )
	{
		if (method_exists('mysqli', $name))
			return call_user_func_array(array($this->mydb, $name), $args);
		logError("No known method: $name");
	}
	*/

	/**
	* Here begins the section for handling database transactions
	*/
	private $transactions; // a stack of open transactions

	/**
	* Opens a database transaction. Note that you can nest calls to this. The nested calls don't do anything in the database, but they do keep
	* track of when something does need to be done in the database. So you can call this for any operation you need to ensure is atomic.
	* @param string (optional) tag the tag is just a unique code location or purpose identifier for debugging purposes
	* @return void
	*/
	public function dbBegin( $tag = NULL )
	{
		if (!is_array($this->transactions))
			$this->transactions = array();
		$tag = ($tag ? "$tag #" : "(no tag) #") . (count($this->transactions) + 1);
		array_push($this->transactions, $tag);
		logDebug("Beginning transaction: $tag");
		if (count($this->transactions) == 1)
			$this->dbQuery('START TRANSACTION', $tag);
	}

	/**
	* If you begin a transaction you must close it. Call this to close a successful transaction.
	* @return void
	*/
	public function dbCommit( )
	{
		if (!($tag = array_pop($this->transactions)))
			throw new ErrorException(__CLASS__ ."->". __FUNCTION__ .": no pending transaction");
		logDebug("Committing transaction: $tag");
		if (!count($this->transactions))
			$this->dbQuery('COMMIT', $tag);
	}

	/**
	* If you begin a transaction you must close it. Call this to abort a transaction. It ensures that its entire nested chain is aborted too.
	* @return void
	*/
	public function dbAbort( $msg = null )
	{
		$tag = null;
		if (!count($this->transactions))
			logDebug("No pending transaction");
		else while ($tag = array_pop($this->transactions))
			logDebug("Rolling back transaction: $tag");
		$this->dbQuery('ROLLBACK', $tag);
		return $msg;
	}

}
