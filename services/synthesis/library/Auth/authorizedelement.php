<?php
/**
* AuthorizedElement, ie. an Element which implements AuthorizedInstance
* We add a "creator" sysval which:
*	- Is set to the creating user automatically as it's created.
*	- Is configured to reference a login id.
*	- Enforces that the creator is set whenever storing an instance.
*	-
*
* Created: 2/6/15 for Lifetoward LLC
*
* All original code.
* @package Synthesis/Authentication
* @author Biz Wiz <bizwiz@SyntheticWebApps.com>
* @copyright (c) 2015 Lifetoward LLC; All rights reserved.
* @license proprietary
*/
class AuthorizedElement extends Element
{
	public static $sysvals = array('capcel','creator');
	protected $creator; // This is the creator sysval attribute
	protected static
		$roleAuthField = null, // If you specify this, then access to a particular instance requires possession of a Role matching the value of this field.
		$reqRole = null; // If you specify this, then access to any instance requires possession of this Role.
	private static $savedProperties = array('creator');
	use SerializationHelper;
	
	public static function getSysvalDDL( $sysval )
	{
		if ($sysval != 'creator')
			return null;
		return "_creator INT(10) UNSIGNED NOT NULL COMMENT 'Login ID of creator'".
			",CONSTRAINT `". static::$table ."__creator_auth_login` FOREIGN KEY (_creator) REFERENCES auth_login(_id) ON DELETE RESTRICT";
	}
	
	public static function create( array $initial = array() )
	{
		$creator = $GLOBALS['root']->getAuthId();
		if (!$creator)
			throw new ErrorException("Can't create elements of class e_identity without an authorized context.");
		$obj = parent::create($initial);
		$obj->creator = $creator;
		return $obj;
	}
	
	protected function storeInstanceData( Database $db )
	{
		if (!$this->creator)
			throw new ErrorException("Can't store elements of class e_identity without creator sysval set.");
		return parent::storeInstanceData($db);
	}
}
