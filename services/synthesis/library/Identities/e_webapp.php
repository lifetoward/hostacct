<?php
/**
* A web application or online service is a resource accessed over the internet providing some sort of interactive or data service.
*
* All original code.
* @package Synthesis/Identities
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
class e_webapp extends e_facility
{
	public static $table = "ids_webapp", $singular = "Online service", $plural = "Online services", $descriptive = "Web service info",
		$fielddefs = array(
			 'facility'=>array('name'=>'facility','type'=>'include','class'=>'e_facility','identifying'=>true,'sort'=>true)
			,'appuri'=>array('name'=>'appuri','class'=>'t_url','label'=>"Application address",'unique'=>'appuri','help'=>"The service's home page.")
		), $hints = array( // hints are provided for general purpose actions to be able to customize rendering of this particular element class
			 'a_Browse'=>array(
				'triggers'=>array('banner'=>"create", 'row'=>array('update','display'), 'multi'=>"delete")
				,'include'=>array('facname','provider','appuri'))
			,'a_Edit'=>array('triggers'=>array('banner'=>'delete'))
			,'a_Display'=>array('role'=>'Staff', 'tiles'=>array(
				 array('method'=>'a_Display::fields','title'=>"Security context", 'include'=>array('facname','provider','admin','description'),
					'operations'=>array('head'=>'update'))
				,array('method'=>'a_Display::fields','title'=>"Access", 'include'=>array('appuri','loginuri'),
					'operations'=>array('head'=>'update'))
				,array('method'=>'e_facility::listIdentities','title'=>"My Keys")
				) )
		), $operations = array('display'=>array(),'update'=>array(),'delete'=>array(),'create'=>array(),'list'=>array()
		);

	public function formatted()
	{
		return "$this->°facname";
	}
}
