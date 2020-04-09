<?php
/**
* This is how we associate an organization with a location. It includes location specifiers and contact specifiers for some appropriate contact modes.
*
* All original code.
* @package Synthesis/Contacts
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2014 Lifetoward LLC
* @license proprietary
*/
abstract class Locator extends Relation
{
	static $table = 'com_locator', $descriptive = "Location details",
		$fielddefs = array(
		), $hints = array(
			 'a_Browse'=>array('include'=>array('purpose','locspec','phone','fax','locnotes'),'orderby'=>'pref')
			,'a_Display'=>array('operations'=>array('head'=>array('select','view')),'include'=>array('purpose','locspec','phone'))
			,'a_Edit'=>array()
			,'a_Relate'=>array()
			,'a_Delete'=>array()
		), $operations = array(
			 'view'=>array()
			,'select'=>array()
			,'edit'=>array()
			,'remove'=>array()
		);
}

class r_Location extends Locator
{
	static $keys = array('organization'=>'e_organization', 'location'=>'e_location'), $singular = "Location", $plural = "Locations";
}

class r_Occupant extends Locator
{
	static $keys = array('location'=>'e_location', 'organization'=>'e_organization'), $singular = "Occupant", $plural = "Occupants";
}
