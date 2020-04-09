<?php
/**
* A secure area is a physical place with controlled access required authentication credentials and appropriate authorization to enter.
*
* All original code.
* @package Synthesis/Identities
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
class e_secarea extends Element
{
	public static $table = "ids_secarea", $singular = "Secure area", $plural = "Secure areas", $descriptive = "Area security",
		$fielddefs = array(
			 'facility'=>array('name'=>'facility','class'=>'e_facility','type'=>'include','identifying'=>true,'label'=>"Secure area",'sort'=>true)
			,'location'=>array('name'=>'location','class'=>'e_location','type'=>'require','label'=>"Location",'sort'=>true)
			,'subunit'=>array('name'=>'subunit','class'=>'t_string','label'=>"Area label",'help'=>"This is the locating hint for where exactly to access this area within the general location.")
		), $hints = array( // hints are provided for general purpose actions to be able to customize rendering of this particular element class
			 '*all'=>array()
			,'a_Browse'=>array(
				 'triggers'=>array('banner'=>"create", 'row'=>array('update','display'), 'multi'=>"delete")
				,'include'=>array('facname','admin','location','subunit'))
			,'a_Edit'=>array('triggers'=>array('banner'=>'delete'))
			,'a_Display'=>array('role'=>'Staff', 'tiles'=>array(
				,array('method'=>'a_Display::fields','width'=>12)
				,array('method'=>'e_facility::listIdentities','title'=>"My Keys",'width'=>12)
				) )
		), $operations = array('display'=>array(),'update'=>array(),'delete'=>array(),'create'=>array(),'list'=>array()
		);

	public function formatted()
	{
		return "$this->°facname @ $this->°location";
	}
}
