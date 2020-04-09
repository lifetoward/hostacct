<?php
/**
* This is how we associate a person with a temporary location.
*
* All original code.
* @package Synthesis/Contacts
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2014 Lifetoward LLC
* @license proprietary
*/
abstract class TempStay extends Relation
{
	static $table = 'com_visit', $descriptive = "Temporary stay details",
		$fielddefs = array(
			 'phone'=>array('name'=>'phone', 'class'=>'t_phone', 'label'=>"Phone",
				'help'=>"Direct line for this person at this Destination. If it's not a personal or at least semi-private line, leave it blank and the location's general number will be used.")
			,'unit'=>array('name'=>'unit', 'class'=>'t_string', 'label'=>"Room, Suite, etc.", 'pattern'=>'^[[:alnum:] ]{0,39}$',
				'help'=>"Specify the particular unit, room, apartment, or office this person will be using while at this location. Example: 'Room 204'")
			,'locnotes'=>array('name'=>'locnotes', 'class'=>'t_richtext', 'label'=>"Notes",
				'help'=>"Provide any important additional information about this person's stay in this place.")
			,'begin'=>array('name'=>'begin', 'class'=>'t_date', 'label'=>"From date", 'help'=>"Date from which this person is located here.")
			,'until'=>array('name'=>'until', 'class'=>'t_date', 'label'=>"Until date", 'help'=>"Date when this person leaves here.")
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

class r_Destination extends TempStay
{
	static $keys = array('person'=>'e_person', 'location'=>'e_location'), $singular = "Destination", $plural = "Destinations";
}

class r_Visitor extends TempStay
{
	static $keys = array('location'=>'e_location', 'person'=>'e_person'), $singular = "Visitor", $plural = "Visitors";
}
