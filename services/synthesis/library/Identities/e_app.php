<?php
/**
* An application runs on a set of devices and may have its own authentication and authorization scheme.
*
* All original code.
* @package Synthesis/Identities
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
class e_app extends e_facility
{
	public static $table = "ids_app", $singular = "Software application", $plural = "Software applications", $descriptive = "Application details",
		 $help = "A software application is self-contained and runs on a host system or device. It may require authentication to use it."
		, $fielddefs = array(
			 'facility'=>array('name'=>'facility','class'=>'e_facility','type'=>'include','identifying'=>true,'sort'=>true,
				'override'=>array('facname'=>array('label'=>"Application name")))
			,'system'=>array('name'=>'system','class'=>'e_hostdev','type'=>'belong','identifying'=>true,'label'=>"Host system or device",'sort'=>true)
		), $hints = array( // hints are provided for general purpose actions to be able to customize rendering of this particular element class
			 '*all'=>array()
			,'a_Browse'=>array(
				 'triggers'=>array('banner'=>"create", 'row'=>array('update','display'), 'multi'=>"delete")
				,'include'=>array('facname','admin','appname','system'))
			,'a_Edit'=>array('triggers'=>array('banner'=>'delete'))
			,'a_Display'=>array('role'=>'Staff', 'tiles'=>array(
				 array('method'=>'a_Display::fields','title'=>"Application security info",'operations'=>array('head'=>'update'))
				,array('method'=>'e_facility::listIdentities','title'=>"My Keys",'width'=>12)
				) )
		), $operations = array('display'=>array(),'update'=>array(),'delete'=>array(),'create'=>array(),'list'=>array()
		);

	public function formatted()
	{
		return "$this->°facname";
	}
}
