<?php
/**
* The CLI context facilitates manual testing of Synthesis components.
*	- Turn off logging to browsers
* 	- Establish and configure a working connection to the database
*	- Publish the root context as $GLOBALS['root']
*
* A root context should provide all of the following things:
*	- Database access (based on application level configuration)
*	- Application configuration data
*
* All original code.
* @package Synthesis
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
class CLI extends RootContext
{
	/**
	* Root contexts must provide a static start method which handlers can call to bootstrap themselves.
	*/
	public static function start( Action $action = null )
	{
		global $console, $loggers;
		$console = true;
		foreach (array('FireBugLogger') as $unwanted)
			unset($loggers[$unwanted]);
		logInfo("Starting Command Line Session");
		return parent::start($action, get_called_class());
	}

	protected function __construct( )
	{
		parent::__construct();
		print "$this\n";
	}

	public function __toString( )
	{
		try {
			$dbTables = $this->dbQuery("SHOW TABLES");
			$dbStat = "Connected to database with ". count($dbTables) ." tables.";
		} catch (dbQueryX $ex) {
			$dbStat = "Unable to get Database Status";
		}
		return "Command Line Root Context with database status:\n$dbStat\n";
	}
}
