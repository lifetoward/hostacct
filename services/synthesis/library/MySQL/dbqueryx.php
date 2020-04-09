<?php

class dbQueryX extends Exception
{
	public static $ErrorCodes = array(
		 dbMissingTableX::ErrorCode=>"dbMissingTableX"
		,dbMissingColumnX::ErrorCode=>"dbMissingColumnX"
		,dbFailedDependencyX::ErrorCode=>"dbFailedDependencyX"
		,dbDuplicateDataX::ErrorCode=>"dbDuplicateDataX"
	);

	public $mysql_errno, $mysql_msg, $query;

	public function __construct($mydb, $query, $msg = null)
	{
		$this->mysql_msg = $mydb->error;
		$this->mysql_errno = $mydb->errno;
		$this->query = $query;
		parent::__construct("Query ($query) failed:\n $this->mysql_msg [$this->mysql_errno]\n" .($msg ? " ; '$msg'" : null), 20000+$this->mysql_errno);
	}
}

class dbMissingColumnX extends dbQueryX
{
	const ErrorCode = 1054;

	public $tablename, $column;

	public function __construct( $mydb, $query, $msg = null )
	{
		parent::__construct($mydb, $query, $msg);
		if (preg_match("/Unknown column '([a-zA-Z0-9_]*_)([a-zA-Z0-9]*)\.([a-zA-Z0-9_]*)' in/", $this->mysql_msg, $m))
			list($junk, $this->tablename, $this->column) = $m;
	}
}

class dbMissingTableX extends dbQueryX
{
	const ErrorCode = 1146;

	public $tablename;

	public function __construct( $mydb, $query, $msg = null )
	{
		parent::__construct($mydb, $query, $msg);
		if (preg_match("/Table '[^']*\.([^']*)'/", $this->mysql_msg, $m))
			$this->tablename = $m[1];
	}
}

/**
* Thrown when a table cannot be created because something it references (using a foreign key, typically) is not available
*/
class dbFailedDependencyX extends dbQueryX
{
	const ErrorCode = 1005;
}

/**
* Thrown when a table cannot be created because something it references (using a foreign key, typically) is not available
*/
class dbDuplicateDataX extends dbQueryX
{
	const ErrorCode = 1062;
	public $failedKey;
	public function __construct( $mydb, $query, $msg = null )
	{
		parent::__construct($mydb, $query, $msg);
		if (preg_match("/for key '([^']*)'/", $this->mysql_msg, $m))
			$this->failedKey = $m[1];
	}
}

class dbUndeletableX extends dbQueryX
{
}

