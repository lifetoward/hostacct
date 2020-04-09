<?php
/**
* This is how we associate a person with a home location. 
* A place where any person lives is called a household. It permits specification of 
*
* All original code.
* @package Synthesis/Contacts
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2014 Lifetoward LLC
* @license proprietary
*/
abstract class Home extends Relation
{
	static $table = 'com_home',
		$fielddefs = array(
			 'phone'=>array('name'=>'phone', 'class'=>'t_phone', 'label'=>"Personal phone line",
				'help'=>"If a person within the household has his or her own personal phone line, specify it here. Otherwise, leave it blank.")
		), $hints = array(
			 'a_Browse'=>array('include'=>array('phone','fax'))
			,'a_Display'=>array('operations'=>array('head'=>array('select','view')),'include'=>array())
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

class r_Home extends Home
{
	static $keys = array('person'=>'e_person', 'household'=>'e_location'),	$singular = "Home", $plural = "Residences";
}

class r_Resident extends Home
{
	static $keys = array('household'=>'e_location', 'person'=>'e_person'), $singular = "Resident",	$plural = "Residents";
}
