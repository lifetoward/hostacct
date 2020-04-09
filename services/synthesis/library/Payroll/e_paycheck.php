<?php
/**
* e_paycheck
* A paycheck is a transaction by which an employee gets paid. It includes entries for all taxes and other forms of compensation and withholding.
*
* Created: 11/30/14 for Lifetoward LLC
*
* All original code.
* @package Synthesis/Payroll
* @author Biz Wiz <bizwiz@SyntheticWebApps.com>
* @copyright (c) 2014 Lifetoward LLC; All rights reserved.
* @license proprietary
*/
class e_paycheck extends Element
{
	public static $table = "pay_check", $singular = "Paycheck", $plural = "Paychecks", $descriptive = "Paycheck details",
		$help = "A paycheck is a transaction by which an employee gets paid. It includes entries for all taxes and other forms of compensation and withholding.",
		$fielddefs = array(
			 'example'=>array('name'=>'example','class'=>'e_example','label'=>"Label here",'type'=>'include','identifying'=>true,'sort'=>true
				,'options'=>array('value'=>"Label")
				,'help'=>"Field help")
		), $hints = array( // hints are provided for general purpose actions to be able to customize rendering of this particular element class
			 '*all'=>array()
			,'a_Browse'=>array(
				 'include'=>array('example')
				,'triggers'=>array('banner'=>"create", 'row'=>array('update','display'), 'multi'=>"delete") )
			,'a_Edit'=>array('triggers'=>array('banner'=>'delete'))
			,'a_Display'=>array('title'=>'', 'subtitle'=>'', 'headdesc'=>'', 'tiles'=>array(
//				 array('method'=>'fields','title'=>"",'fields'=>array('example'))
//				,array('method'=>'relations','title'=>"",'class'=>'')
//				,array('method'=>'relatives','title'=>"",'class'=>'')
				))
		), $operations = array( // actions allow general purpose actions to know how to interact with this element class in various situations
			 'list'=>array('action'=>'a_Browse', 'role'=>'Staff')
			,'display'=>array('action'=>'a_Display', 'role'=>array('*owner','Staff'))
			,'update'=>array('action'=>'a_Edit', 'role'=>array('*owner','Staff'))
			,'delete'=>array('action'=>'a_Delete','role'=>'Administrator')
			,'create'=>array('action'=>'a_Edit', 'role'=>'Staff')
		);

	public function formatted()
	{
		return "$this->°name";
	}
}
