<?php
/**
* This represents a host system or "smart" device, like a computer, smart phone, cloud server or cluster, or other similar physical object or operating system instance
*	which runs a specific operating system with the ability to run applications.
*
* All original code.
* @package Synthesis/Identities
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
class e_hostdev extends e_facility
{
	public static $table = "ids_hostdev", $singular = "Host system or device", $plural = "Host systems and devices", $descriptive = "System info",
		$fielddefs = array(
			 'facility'=>array('name'=>'facility','class'=>'e_facility','type'=>'include','identifying'=>true,'sort'=>true)
			,'hostname'=>array('name'=>'hostname','class'=>'t_string','label'=>"Host name",'help'=>"This is the hostname published according to connectivity protocols, like LAN, DNS, Bluetooth, etc.")
			,'category'=>array('name'=>'category','class'=>'t_select','label'=>"Device type",'sort'=>true,
				'options'=>array('pocket'=>"Mobile (pocket) device",'tablet'=>"Touch tablet",'laptop'=>"Laptop computer",'console'=>"Traditional computer", 'server'=>"Server or mainframe"))
			,'systype'=>array('name'=>'systype','class'=>'t_select','label'=>"Runtime system",'sort'=>true,
				'options'=>array('ios'=>"iOS (Apple)", 'osx'=>"Mac OS X (Apple)", 'windows'=>"Windows (Microsoft)", 'android'=>"Android (open/Google)", 'linux'=>"Linux (open)"))
			,'owner'=>array('name'=>'owner','class'=>'e_entity','type'=>'refer','label'=>"Legal owner")
			,'user'=>array('name'=>'user','class'=>'e_person','type'=>'refer','label'=>"Primary user",'help'=>"This person maintains possession of the device.")
			,'ipaddr'=>array('name'=>'ipaddr','class'=>'t_string','label'=>"Internet address")
//			,'location'=>array('name'=>'location','class'=>'e_location','type'=>'refer','label'=>"Deployed location")
//			,'asset'=>array('name'=>'asset','type'=>'refer','class'=>'e_asset','label'=>"Asset tracking")
		), $hints = array( // hints are provided for general purpose actions to be able to customize rendering of this particular element class
			 'a_Browse'=>array('role'=>'Staff'
				,'include'=>array('facname','systype','user','location')
				,'triggers'=>array('banner'=>"create", 'row'=>array('update','display'), 'multi'=>"delete") )
			,'a_Edit'=>array('role'=>'Staff','triggers'=>array('banner'=>'delete'))
			,'a_Display'=>array('role'=>'Staff', 'tiles'=>array(
				 array('method'=>'a_Display::fields','title'=>"Facility information", 'include'=>array('facname','provider','admin','owner','user','description'),
					'operations'=>array('head'=>'update'))
				,array('method'=>'a_Display::fields','title'=>"Host information", 'include'=>array('hostname','category','systype','location','ipaddr'),
					'operations'=>array('head'=>'update'))
				,array('method'=>'e_facility::listIdentities','title'=>"My Keys",'width'=>12)
				) )
		), $operations = array('display'=>array(),'update'=>array(),'delete'=>array(),'create'=>array(),'list'=>array()
		);

	public function formatted()
	{
		return "$this->°facname ($this->°systype)";
	}
}
