<?php
/**
* A RootContext must:
* 	- Establish and configure a working connection to the database, implementing the Database interface.
*	- Publish the root context to the runtime as $GLOBALS['root']
*  - Establish and provide access to runtime configuration information, ie. here as magic properties.
*  - Provide standard methods to facilitate Authorization checking, even if they always fail due to being unauthenticated.
*
* All original code.
* @package Synthesis
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
class RootContext extends Context
	implements Database
{
    // The SelectedDatabase is found through the autoloader. The first path in the include_path which has this trait will load as the database handler.
	use SelectedDatabase;

	/**
	* Root contexts must provide a static start method which handlers can call to bootstrap themselves.
	*/
	public static function start( Action $action = null, $class = null )
	{
		global $root;
		if (!$class)
			$class = get_called_class();
		$root = new $class;
		if ($action)
			$root->action = $action;
		return $root;
	}

	/**
	* Root contexts must establish a database connection for whenever they are active.
	*/
	protected function __construct( )
	{
		$this->dbConnect();
	}
	
	/**
	* Runtime configuration (environment variables) provided as magic properties.
	*/
	public function __get( $var )
	{
		if (isset($_ENV[$var]))
			return $_ENV[$var];
		return parent::__get($var);
	}

	/* * * * * * * * * AUTHORIZATION SECTION * * * * * * * */

	/**
	* This ultrasimple authorization checker just checks whether the authenticated user has the passed role (by label)
	* @param string $role The label of a role element.
	* @return boolean False always because this implementation cannot presume authentication.
	*/
	public function hasAuthRole( $role )
	{
		return false;
	}

	/**
	* Primary method for role-based authorization.
	* @return boolean False - because this implementation cannot presume authentication.
	*/
	public function isAuthorized( )
	{
		return false;
	}

	/** 
	* Identifies the current authenticated user by login ID
	* @return null Null because this implementation cannot presume authentication.
	*/
	public function getAuthId( )
	{
		return null;
	}

	/**
	* Identifies the current authenticated user by username.
	* @return null Null because this implementation cannot presume authentication.
	*/
	public function getAuthUser( )
	{
		return null;
	}

	/**
	* Returns a simple list array of Role names possessed by the currently authenticated user.
	* @return array Empty array always returned here because this implementation cannot presume authentication.
	*/
	public function listAuthRoles( )
	{
		return array();
	}

 	/**
	* Returns a comma-separated list of Role IDs possessed by the currently authenticated user.
	* @return string We return the string 'null' because this implementation cannot presume authentication and this string can be used in SQL queries directly with null results.
	*/
	public function getAuthRoleIds( )
	{
		return 'null';
	}
}
