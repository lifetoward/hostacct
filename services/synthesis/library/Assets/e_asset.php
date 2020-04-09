<?php
/**
* Here we represent a fixed asset, something which can be possessed and employed long term to generate value.
* We track some details like dates acquired, deployed, retured, disposed. We record depreciation method specifications. We identify related accounts.
* We also have a number of derived fields which can yield quick reporting on asset value in a number of ways.
*
* All original code.
* @package Synthesis/Finance
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
class e_asset extends Element
{
	public static $table = 'actg_asset', $singular = "Fixed asset", $plural = "Fixed assets", $descriptive = "Asset information",
		$description = "A fixed asset is an account in that it can hold book value, but it also provides a focus for depreciation, maintenance, and investment tracking.",
		$fielddefs = array(
			 'account'=>array('name'=>'account', 'class'=>'Account', 'type'=>'include', 'sort'=>true, 'identifying'=>true)
			,'slug'=>array('name'=>'slug', 'class'=>'t_string', 'label'=>'Filing label', 'pattern'=>"[\\w]*", 'sort'=>true, 'required'=>true, 'unique'=>'slug',
				'help'=>"This value is used like the label of a file folder. It will be used in links, directory names, etc. It should be short and easily recognized as representing this asset. Only letters, digits, and underscores may be used.")
			,'acquired'=>array('name'=>'acquired', 'class'=>'t_date', 'label'=>"Date acquired", 'sort'=>'ASC')
			,'deployed'=>array('name'=>'deployed', 'class'=>'t_date', 'label'=>"Date deployed", 'sort'=>'ASC')
			,'retired'=>array('name'=>'retired', 'class'=>'t_date', 'label'=>"Date retired", 'sort'=>'ASC')
			,'disposed'=>array('name'=>'disposed', 'class'=>'t_date', 'label'=>"Date disposed", 'sort'=>'ASC')
			,'depmethod'=>array('name'=>'depmethod', 'class'=>'t_select', 'label'=>"Depreciation method", 'initial'=>'manual', 'required'=>true,
				'options'=>array('manual'=>"Manual (no automatic calculation)", 'linear'=>"Linear (straight slope from acquisition to disposition)"))
			,'depperiod'=>array('name'=>'depperiod', 'class'=>'t_select', 'label'=>"Depreciation period", 'initial'=>'year',
				'options'=>array('month'=>'Monthly', 'quarter'=>'Quarterly', 'year'=>'Annual'))
			,'deprate'=>array('name'=>'deprate', 'class'=>'t_percent', 'label'=>"Depreciation rate", 'help'=>"Rate used for calculating depreciation. How's it's used depends on the method and period.")
			,'depacct'=>array('name'=>'depacct', 'class'=>'Account', 'type'=>'refer', 'label'=>"Depreciation expense account")
			,'investacct'=>array('name'=>'investacct', 'class'=>'Account', 'type'=>'refer', 'label'=>"Investment asset account")
			,'maintacct'=>array('name'=>'maintacct', 'class'=>'Account', 'type'=>'refer', 'label'=>"Maintenance expense account")
			,'productacct'=>array('name'=>'productacct', 'class'=>'Account', 'type'=>'refer', 'label'=>"Production credit account")
			,'mortgage'=>array('name'=>'mortgage', 'class'=>'Account', 'type'=>'refer', 'label'=>"Loan outstanding")
		// Here we have a bunch of derived fields for doing continuous reporting on asset value
			,'basis'=>array('name'=>"basis", 'class'=>'t_balance', 'label'=>'Basis', 'derived'=>"FLOOR(0)",
				'help'=>"(Not implemented) Sum of all debit entries to the asset account")
			,'depreciation'=>array('name'=>"depreciation", 'class'=>'t_balance', 'label'=>'Depreciation', 'derived'=>"FLOOR(0)",
				'help'=>"(Not implemented) Sum of all credit entries (except the sale) to the asset account")
			,'maintenance'=>array('name'=>"maintenance", 'class'=>'t_balance', 'label'=>'Maintenance', 'derived'=>"FLOOR(0)",
				'help'=>"(Not implemented) Sum of all non-depreciation expenses tagged to this asset")
			,'tcownership'=>array('name'=>"tcownership", 'class'=>'t_balance', 'label'=>'Total Cost of Ownership', 'derived'=>"FLOOR(0)",
				'help'=>"(Not implemented) Total cost of ownership (basis + maintenance)")
			,'costrate'=>array('name'=>"costrate", 'class'=>'t_balance', 'label'=>'Cost rate', 'derived'=>"FLOOR(0)",
				'help'=>"(Not implemented) Cost of ownership per unit time (tco/(disposed-acquired)*timeunits)")
			,'productivity'=>array('name'=>"productivity", 'class'=>'t_balance', 'label'=>'Productivity', 'derived'=>"FLOOR(0)",
				'help'=>"(Not implemented) Revenue enabled by this asset")
			,'prodrate'=>array('name'=>"prodrate", 'class'=>'t_balance', 'label'=>'Productivity rate', 'derived'=>"FLOOR(0)",
				'help'=>"(Not implemented) Productivity per unit time")
			,'profit'=>array('name'=>"value", 'class'=>'t_balance', 'label'=>'Total value', 'derived'=>"FLOOR(0)",
				'help'=>"(Not implemented) Marginal benefit in total (productivity-tco)")
			,'profitability'=>array('name'=>"valuerate", 'class'=>'t_balance', 'label'=>'Profitability', 'derived'=>"FLOOR(0)",
				'help'=>"(Not implemented) Marginal benefit per unit time (value / timeunit)")
		), $hints = array( // hints are provided for general purpose actions to be able to customize rendering of this particular element class
			 'a_Browse'=>array(
				 'include'=>array('name','deployed','productivity')
				,'triggers'=>array('banner'=>"create", 'row'=>"display") )
		), $operations = array( // actions allow general purpose actions to know how to interact with this element class in various situations
			 'display'=>array('action'=>'a_Display', 'role'=>'Manager')
			,'update'=>array('action'=>'a_Edit', 'role'=>'Accounting')
			,'create'=>array('action'=>'a_Edit', 'role'=>'Accounting')
//			,'delete'=>array('action'=>'a_Delete','role'=>'*super')
			,'list'=>array('action'=>'a_Browse', 'role'=>'Staff')
		);

	public function formatted( )
	{
		return $this->°name;
	}
}
