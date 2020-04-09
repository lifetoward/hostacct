<?php
/**
* A physical or geographic place, for example where entities might be found.
* As a general rule, it's one "address" per location, by which we mean subunits are not specified here.
*
* All original code.
* @package Synthesis/Contacts
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2014 Lifetoward LLC
* @license proprietary
*/
class _e_location extends Element
{
	public static $table = 'com_location', $singular = "Location", $plural = "Locations", $descriptive = "Location details",
		$fielddefs = array(
			 'label'=>array('name'=>'label', 'class'=>'t_string', 'label'=>"Location name", 'pattern'=>'[\\w ]+', 'identifying'=>true, 'sort'=>'ASC',
				'help'=>"Name the location as it makes sense within context keeping sortability in mind. Use printable characters only.")
			,'addressee'=>array('name'=>'addressee', 'class'=>'t_string', 'label'=>"To Address All Occupants Together",
				'help'=>"When addressing correspondence to this location, show this as the intended recipient. For example, for a family residence this might be 'The Jones Family', or for a workplace this might be 'All Team Members'. If otherwise unspecified, the Location name will be used.")
			,'function'=>array('name'=>'function', 'class'=>'t_select', 'label'=>"Type or function", 'sort'=>'ASC', 'options'=>array(
				'industry'=>"Industrial", 'residence'=>"Residential", 'commerce'=>"Commercial", 'office'=>"Offices", 'recreation'=>"Recreation area"))
			,'address'=>array('name'=>'address', 'label'=>"Geographic address", 'class'=>'f_postaddr_USA', 'type'=>'fieldset', 'identifying'=> true, 'sort'=>true)
//			,'geoloc'=>array('name'=>'geoloc', 'class'=>'t_geoloc', 'label'=>"Geographic location")
			,'phone'=>array('name'=>'phone', 'label'=>"Site phone", 'class'=>'t_telecom')
			,'fax'=>array('name'=>'fax', 'label'=>"Site fax", 'class'=>'t_telecom')
			,'details'=>array('name'=>'details', 'label'=>"Locating details", 'class'=>'t_richtext',
				'help'=>"Provide details to aid navigation or access, or provide context for the location as a whole.")
		), $hints = array( // hints are provided for general purpose actions to be able to customize rendering of this particular element class
			 '*all'=>array('exclude'=>array('geoloc'))
			,'a_Browse'=>array(
				 'include'=>array('label','function','phone')
				,'triggers'=>array('banner'=>"create", 'row'=>array('update','display'), 'multi'=>"delete") )
		), $operations = array( // actions allow general purpose actions to know how to interact with this element class in various situations
			 'display'=>array('action'=>'a_Display', 'role'=>array('*owner','Staff'))
			,'update'=>array('action'=>'a_Edit', 'role'=>array('*owner','Staff'))
			,'delete'=>array('action'=>'a_Delete','role'=>'Administrator')
			,'create'=>array('action'=>'a_Edit', 'role'=>'Staff')
			,'list'=>array('action'=>'a_Browse', 'role'=>'Staff')
		);

	public function formatted()
	{
		return "$this->°label";
	}
}
