<?php
/**
* This is how we associate a person with a location. It includes location specifiers and contact specifiers for some appropriate contact modes.
*
* All original code.
* @package Synthesis/Contacts
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2014 Lifetoward LLC
* @license proprietary
*/
abstract class Workplace extends Relation
{
	static $table = 'com_workplace', $descriptive = "Workplace information",
		$fielddefs = array(
			 'unit'=>array('name'=>'unit', 'class'=>'t_string', 'label'=>"Office, Station, etc.", 'pattern'=>'^[[:alnum:] ]{0,39}$',
				'help'=>"Provide the office number, mailstop, or other similar description of how this person can be found or mail addressed to them at this location.")
			,'phone'=>array('name'=>'phone', 'class'=>'t_phone', 'label'=>"Phone",
				'help'=>"Direct line for this person at this place. If it's not a personal or at least semi-private line, leave it blank and the location's general number will be used.")
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

class r_Workplace extends Workplace
{
	static $keys = array('person'=>'e_person', 'location'=>'e_location'), $singular = "Workplace", $plural = "Work locations";
}

class r_LocWorkers extends Workplace
{
	static $keys = array('location'=>'e_location', 'person'=>'e_person'), $singular = "Worker", $plural = "Workers";
}
