<?php
/**
* Specifies how a remote identity's owner has chosen to share use of the identity with other authenticated users in the local system.
* These associate system logins, not higher-level entities, with remote identities.
*
* All original code.
* @package Synthesis/Contacts
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
abstract class IDSharing extends Relation
{
	static $table = 'ids_sharing', $descriptive = "Sharing",
		$fielddefs = array(
			 'access'=>array('name'=>'access','class'=>'t_boolset','label'=>"Allowed access",'options'=>array('use'=>"Use within System",'view'=>"View and download"))
			,'begin'=>array('name'=>'begin','class'=>'t_date','label'=>"Date this access begins",'sort'=>'ASC')
			,'end'=>array('name'=>'end','class'=>'t_date','label'=>"Date this access ends",'sort'=>'ASC')
		);
}

class r_iduser extends IDSharing
{
	static $keys = array('identity'=>'e_identity', 'login'=>'e_login'), $complement = 'r_sharedid'
		, $singular = "Allowed user", $plural = "Allowed users"
		,$hints = array(
		);
}

class r_sharedid extends IDSharing
{
	static $keys = array('login'=>'e_login', 'identity'=>'e_identity'), $complement = 'r_iduser'
		, $singular = "Shared identity", $plural = "Shared identity"
		,$hints = array(
		);
}
