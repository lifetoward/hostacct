<?php
/**
* A bank is a vendor responsible for administering financial accounts on our behalf.
*
* All original code.
* @package Synthesis/Finance
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
class Bank extends Element
{
	public static $table = 'bank_bank', $singular = "Financial institution", $plural = "Financial institutions", $descriptive = "Bank details",
		$help = "",
		$fielddefs = array(
			 'vendor'=>array('name'=>'vendor', 'type'=>"include", 'class'=>"Vendor", 'sort'=>true, 'identifying'=>true, 'initial'=>array('category'=>'financial'))
			,'achnum'=>array('name'=>'achnum', 'class'=>"t_string", 'label'=>"Routing number", 'required'=>true, 'pattern'=>"\d{9}",
				'help'=>"Provide the ACH rounting number as it appears on checks.")
		), $hints = array( // hints are provided for general purpose actions to be able to customize rendering of this particular element class
			 '*all'=>array()
			,'a_Browse'=>array(
				 'include'=>array('name','achnum')
				,'triggers'=>array('banner'=>'create','record'=>array('update','display'),'multi'=>'delete'))
		), $operations = array( // actions allow general purpose actions to know how to interact with this element class in various situations
			 'display'=>array('action'=>'a_Display', 'role'=>'Staff')
			,'update'=>array('action'=>'a_Edit', 'role'=>'Accounting')
			,'create'=>array('action'=>'a_Edit', 'role'=>'Accounting')
			,'delete'=>array('action'=>'a_Delete','role'=>'*super')
			,'list'=>array('action'=>'a_Browse', 'role'=>'Staff', 'args'=>array('sortfield'=>'name'))
		);

	public function formatted( )
	{
		return "$this->°name";
	}
}
