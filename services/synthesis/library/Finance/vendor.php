<?php
/**
* A vendor is an entity from whom resources can be procured.
*
* All original code.
* @package Synthesis/Finance
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
class Vendor extends Element
{
	public static $table = "actg_vendor", $singular = "Vendor", $descriptive = "Contact info", $plural = "Vendors",
		$fielddefs = array(
			 'org'=>array('name'=>'org', 'type'=>'include', 'class'=>'e_organization', 'identifying'=>true)
			,'shortname'=>array('name'=>"shortname", 'class'=>'t_string', 'label'=>"Short name", 'help'=>"For use in other abbreviated or aggregate names of other things")
			,'contact'=>array('name'=>'contact', 'class'=>'e_person', 'type'=>"refer", 'sort'=>true, 'label'=>"Primary contact",
				'help'=>"This is the person who should be contacted for billing and other vendor relations issues.")
			,'emailias'=>array('name'=>"emailias", 'class'=>"t_email", 'class'=>"t_email", 'label'=>"Custom email they retain")
			,'expense'=>array('name'=>'expense', 'class'=>'Account', 'type'=>'refer', 'label'=>"Expense account", 'filter'=>"class='expense'",
				'help'=>"If you specify this, we can assume that charges incurred through this vendor can be expensed to this account.")
			,'notes'=>array('name'=>"notes", 'class'=>"t_richtext", 'label'=>"Notes")
		), $hints = array( // hints are provided for general purpose actions to be able to customize rendering of this particular element class
			 'a_Browse'=>array(
				 'include'=>array('name','class','contact')
				,'triggers'=>array('banner'=>'create', 'row'=>array('display','edit'), 'multi'=>'addresses') )
		), $operations = array( // actions allow general purpose actions to know how to interact with this element class in various situations
			 'display'=>array('action'=>'a_Display', 'role'=>'Manager')
			,'update'=>array('action'=>'a_Edit', 'role'=>'Accounting')
			,'create'=>array('action'=>'a_Edit', 'role'=>'Accounting')
			,'delete'=>array('action'=>'a_Delete','role'=>'*super')
			,'list'=>array('action'=>'a_Browse', 'role'=>'Staff')
			,'addresses'=>array('action'=>'a_ProduceAddressesPDF', 'icon'=>'envelope')
		);

	public function formatted()
	{
		return $this->°name;
	}
}
