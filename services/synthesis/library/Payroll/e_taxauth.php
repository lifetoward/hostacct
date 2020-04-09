<?php
/**
* e_taxauth
* A taxing authority demands payroll taxes.
*
* Created: 11/30/14 for Lifetoward LLC
*
* All original code.
* @package Synthesis/Payroll
* @author Biz Wiz <bizwiz@SyntheticWebApps.com>
* @copyright (c) 2014 Lifetoward LLC; All rights reserved.
* @license proprietary
*/
class e_taxauth extends Element
{
	public static $table = "pay_taxauth", $singular = "Tax authority", $plural = "Tax authorities", $descriptive = "Tax responsibility",
		$help = "A taxing authority demands payroll taxes.",
		$fielddefs = array(
			 'entity'=>array('name'=>'entity','class'=>'e_entity','type'=>'include','label'=>"Taxing authority",'identifying'=>true,'sort'=>true,
				'help'=>"A taxing authority is an entity with facilities and contact information.")
			,'shortname'=>array('name'=>'shortname','class'=>'t_string','label'=>"Short name",'required'=>true,'pattern'=>"\w{2,}",
				'help'=>"For use in the labeling of specific taxes.")
			,'minsched'=>array('name'=>'minsched','class'=>'t_select','label'=>"Payment schedule under minimum",'required'=>true,
				'options'=>array('annual'=>"Yearly",'quarter'=>"Quarterly",'month'=>"Monthly",'week'=>"Weekly"),
				'help'=>"Designate the payment schedule that applies when the amount due is below the standard floor.")
			,'mingrace'=>array('name'=>'mingrace','class'=>'t_integer','label'=>"Payment grace period under minimum",'required'=>true,
				'help'=>"Designate the grace period in days that applies for payments under minimal terms.")
			,'stdfloor'=>array('name'=>'stdfloor','class'=>'t_dollars','label'=>"Tax due floor for standard terms",'required'=>true,
				'help'=>"The standard payment schedule applies once the amount due per period exceeds this amount.")
			,'stdsched'=>array('name'=>'stdsched','class'=>'t_select','label'=>"Standard payment schedule",'required'=>true,
				'options'=>array('annual'=>"Yearly",'quarter'=>"Quarterly",'month'=>"Monthly",'week'=>"Weekly"),
				'help'=>"Designate the payment schedule that applies under standard terms.")
			,'stdgrace'=>array('name'=>'stdgrace','class'=>'t_integer','label'=>"Standard grace period",'required'=>true,
				'help'=>"Designate the grace period in days under standard terms.")
			,'maxfloor'=>array('name'=>'maxfloor','class'=>'t_dollars','label'=>"Tax due floor for maximum terms",'required'=>true,
				'help'=>"Immediate payments are due when the amount due exceeds this amount.")
			,'maxgrace'=>array('name'=>'maxgrace','class'=>'t_integer','label'=>"Payment grace period under maximum terms",'required'=>true,
				'help'=>"Designate the grace period in days for payments made under immediate terms.")
			,'reportsched'=>array('name'=>'reportsched','class'=>'t_select','label'=>"Report filing schedule",'required'=>true,
				'options'=>array('annual'=>"Yearly",'quarter'=>"Quarterly",'month'=>"Monthly",'week'=>"Weekly"),
				'help'=>"Designate the schedule for filing reports.")
//			,'report'=>array('name'=>'report','class'=>'e_document','type'=>'refer','label'=>"Blank report to file")
		), $hints = array( // hints are provided for general purpose actions to be able to customize rendering of this particular element class
			 '*all'=>array()
			,'a_Browse'=>array(
				 'include'=>array('name','reportsched')
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
