<?php
/**
* e_taxtable
* Each tax table record defines a single range of taxable funds and the tax rate that applies.
* The records are grouped into mutually exclusive bands which make up a tax table, ie. a particular tax to be calculated.
* The bands can be prorated (defined by a current amount only, like in income tax) or period-cumulative (defined by the total taxable to date for the tax period)
*
* Created: 11/30/14 for Lifetoward LLC
*
* All original code.
* @package Synthesis/Payroll
* @author Biz Wiz <bizwiz@SyntheticWebApps.com>
* @copyright (c) 2014 Lifetoward LLC; All rights reserved.
* @license proprietary
*/
class e_taxtable extends Element
{
	public static $table = "pay_taxtable", $singular = "Tax band", $plural = "Tax tables", $descriptive = "Tax band definition",
		$help = "A tax table defines a single range of taxable funds and the tax rate that applies to them. They can be prorated or period-cumulative.",
		$fielddefs = array(
			 'paytax'=>array('name'=>'paytax','class'=>'e_paytax','label'=>"Payroll tax",'type'=>'belong','identifying'=>true,'sort'=>true
				,'help'=>"Field help")
			,'floor'=>array('name'=>'base','class'=>'t_dollars','label'=>"Base of band",'identifying'=>true,'sort'=>true,
				'help'=>"The floor of the band; this limit is treated exclusively, meaning the amount must EXCEED this value to be in this band.")
			,'employer'=>array('name'=>'employer','class'=>'t_percent','label'=>"Employer's tax rate",'help'=>"The rate at which the employer's own responsibility is calculated.")
			,'employee'=>array('name'=>'employee','class'=>'t_percent','label'=>"Employee's tax rate",'help'=>"The rate at which taxes are due from employees and withheld from their pay.")
		), $hints = array( // hints are provided for general purpose actions to be able to customize rendering of this particular element class
			 '*all'=>array()
			,'a_Browse'=>array(
				 'triggers'=>array('banner'=>"create", 'row'=>array('update','display'), 'multi'=>"delete") )
			,'a_Edit'=>array('triggers'=>array('banner'=>'delete'))
			,'a_Display'=>array('title'=>'', 'subtitle'=>'', 'headdesc'=>'', 'tiles'=>array(
//				 array('method'=>'fields','title'=>"",'fields'=>array('example'))
//				,array('method'=>'relations','title'=>"",'class'=>'')
//				,array('method'=>'relatives','title'=>"",'class'=>'')
				))
		), $operations = array( // actions allow general purpose actions to know how to interact with this element class in various situations
			 'list'=>array('action'=>'a_Browse', 'role'=>'Staff')
			,'display'=>array('action'=>'a_Display', 'role'=>'Staff')
			,'update'=>array('action'=>'a_Edit') // only a *super can change a tax definition
			,'delete'=>array('action'=>'a_Delete') // only a *super can change a tax definition
			,'create'=>array('action'=>'a_Edit', 'role'=>'*system')
		);

	public static function create( e_paytax $paytax, array $initial = null )
	{
	}

	public function formatted()
	{
		return "$this->°paytax @ $this->°floor";
	}
}
