<?php // coding: utf-8
/**
* The Database interface is intended to be implemented by root contexts by using RDBMS-specific traits.
* The root context would implement this interface and then "use SelectedDatabase;"
* That trait would be drawn from the one RDBMS module found in the path.
*
* All original code.
* @package Synthesis
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2014 Lifetoward LLC
* @license proprietary
*/
interface Database
{
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
	public function dbQuery( $query, $intent = NULL );

	/**
	* Obtain the ID of the newest created db record
	* @return integer numeric primary ID of db record last inserted
	*/
	public function dbNewId( );

	/** dbEscapeString()
	* Performs special character conversion on a string which needs to pass through db query syntax unharmed.
	* @param string Text to convert
	* @return string converted text
	*/
	public function dbEscapeString( $text );

	/** dbGetScalar()
	* Takes an unchecked SQL query which is designed to return a single column of a single row.
	* We handle the manipulations to return just that value and release the result.
	* @param string SQL query which generates a single column in a single row.
	* @return string The value from that column/row.
	* @throws dbQueryX When the query fails.
	* @throws NotSingularX When the query generates more than one row or more than one column.
	*/
	public function dbGetScalar( $query, $msg = null );

	/** dbGetRecords()
	* Returns a database query result as a sequential array of associative arrays.
	* No checking is done on the query, including reasonable size limitation. Beware.
	* @param string SQL query
	* @return array Query result as a sequential array of associative arrays
	*/
	public function dbGetRecords( $qstring, $errormsg = null );

	/**
	* Database transactions ensure data integrity and rollback for operations which need to be atomic.
	* @param string $tag A label identifying this transaction frame for logging purposes.
	* @return void
	*/
	public function dbBegin( $tag = NULL );

	/**
	* If you begin a transaction you must close it. Call this to close a successful transaction.
	*/
	public function dbCommit( );

	/**
	* If you begin a transaction you must close it. Call this to abort a transaction. It ensures that its entire nested chain is aborted too.
	* @param string $tag
	* @return string The string passed in, unmodified. This is done only to facilitate simplified coding.
	*/
	public function dbAbort( $msg = null );

}

/**
* Here three of the exception types associated with this interface.
* dbQueryX is missing because its implementation is assumed to be entirely RDBMS specific, so you'll find that in lib/{DBTYPE}/dbQueryX.php
*/
// This is thrown when there's an attempt to load a record which is supposed to be solo, but isn't. There's no sense catching this anywhere. If it happens, there's a problem in the system design.
class dbNotSingularX extends Exception
{
	function __toString()
	{
		return parent::__toString() ."\nIn this context exactly one record or value is expected.";
	}
}
