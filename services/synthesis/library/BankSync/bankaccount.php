<?php
/**
* A bank account is special in that the account as held by the vendor exactly mirrors what we call an account.
* It includes a current asset or liability administered by a vendor whose job is exactly that.
* That means this could be a money market, checking, savings, credit line, loan, or trading account..
*
* All original code.
* @package Synthesis/Finance
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
class BankAccount extends Element
{
	public static $table = 'bank_account', $singular = "Bank account", $plural = "Bank accounts", $descriptive = "Bank account",
		$help = "",
		$fielddefs = array(
			 'account'=>array('name'=>'account', 'type'=>"include", 'class'=>"Account", 'sort'=>true, 'identifying'=>true)
			,'bank'=>array('name'=>'bank','class'=>'Bank','type'=>'require','sort'=>true,'identifying'=>true,'label'=>"Bank")
			,'acctnum'=>array('name'=>'acctnum', 'class'=>"t_string", 'label'=>"Account number", 'required'=>true, 'pattern'=>'^\d[- \d]{4,20}\d$', 'trim'=>true,
				'help'=>"Specify the account number as will appear in electronic statements, identifying the account online, or on checks, cards, or other instruments.")
			,'acctabbrev'=>array('name'=>'acctabbrev', 'class'=>'t_string', 'label'=>"Account # hint", 'derived'=>"CONCAT('-',SUBSTRING(`{}`.acctnum,-4))")
			,'termsnotes'=>array('name'=>'termsnotes', 'class'=>"t_richtext", 'label'=>"Notes about terms",
				'help'=>"Use this field to describe the account's main terms, such as what kinds of fees are involved, whether it bears interest, etc.")
		), $hints = array( // hints are provided for general purpose actions to be able to customize rendering of this particular element class
			 '*all'=>array()
			,'a_Browse'=>array(
				 'include'=>array('name','bank','variety','acctabbrev')
				,'triggers'=>array('banner'=>'create','record'=>array('update','display'),'multi'=>'delete'))
		), $operations = array( // actions allow general purpose actions to know how to interact with this element class in various situations
			 'display'=>array('action'=>'a_Display', 'role'=>'Staff')
			,'update'=>array('action'=>'a_Edit', 'role'=>'Finance')
			,'create'=>array('action'=>'a_Edit', 'role'=>'Finance')
			,'delete'=>array('action'=>'a_Delete','role'=>'*super')
			,'list'=>array('action'=>'a_Browse', 'role'=>'Staff', 'args'=>array('sortfield'=>'name'))
		);

	public function formatted( )
	{
		return "$this->°name @ {$this->bank->°shortname} (-". mb_substr("$this->acctnum",-4) .")";
	}

	
}
