<?php
/**
* Here we map a user to his or her roles.
*
* All original code.
* @package Synthesis/Authentication
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2014 Lifetoward LLC
* @license proprietary
*/

class sysroles extends Relation
{
    static $table = 'auth_roles', $descriptive = 'Role map',
        $fielddefs = array(
		), $hints = array(
			 'a_Display'=>array('operations'=>array('head'=>array('select')),'include'=>array())
			,'a_Relate'=>array()
			,'a_Delete'=>array()
		), $operations = array(
			 'view'=>array()
			,'select'=>array()
			,'edit'=>array()
			,'remove'=>array()
		);
}

class r_rolemap extends sysroles
{
	static $keys = array('login'=>'e_login','role'=>'e_role'), $singular = 'Role', $plural = 'Recognized roles';
}

class r_roleplayer extends sysroles
{
	static $keys = array('role'=>'e_role','login'=>'e_login'), $singular = 'Role holder', $plural = 'Role holders';
}