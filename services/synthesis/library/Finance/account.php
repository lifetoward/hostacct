<?php
/**
* An account is an aggregator for accounting journal entries trackable against a single purpose or designation.
* It provides a history of entries (and their transactions) and balance calculation.
*
* All original code.
* @package Synthesis/Finance
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
class Account extends Element
{
	const ActiveFilter = "FIND_IN_SET('inactive',{}.flags)=0";
	public static $table = 'actg_account', $singular = "Ledger account", $plural = "Ledger accounts", $descriptive = "Account details",
		$description = "Contains journal entries made in the common general ledger. Each entry is either a credit or a debit and is associated with a balanced Transaction. The sum of all the entries is the account balance.",
		$joins = array('AcctEntry'=>"«account"),
		$fielddefs = array(
			 'name'=>array('class'=>'t_string', 'name'=>'name', 'label'=>"Account name",'identifying'=>true, 'sort'=>'ASC')
			,'class'=>array('class'=>'AcctClass', 'type'=>'require', 'name'=>'class', 'label'=>"Account type", 'sort'=>true)
			,'balance'=>array('class'=>'t_dollars', 'name'=>'balance', 'label'=>'Balance', 'width'=>4,
				'derived'=>"IF(`{}_class`.positive,1,-1)*(SUM(IF(`{}_actg_entry_`.type,`{}_actg_entry_`.amount,0))-SUM(IF(`{}_actg_entry_`.type,0,`{}_actg_entry_`.amount)))")
			,'cleardate'=>array('class'=>'t_date', 'name'=>'cleardate', 'label'=>"Date last reconciled", 'readonly'=>true, 'width'=>4)
			,'cleared'=>array('class'=>'t_balance', 'name'=>'cleared', 'label'=>"Reconciled balance", 'readonly'=>true, 'initial'=>0, 'width'=>4)
			,'flags'=>array('class'=>'t_boolset', 'name'=>'flags', 'label'=>"Capabilities", 'width'=>12,
				'options'=>array('inactive'=>"Inactive", 'funding'=>"Funding", 'interest'=>"Interest bearing"),
				'help'=>"Inactive means not available for creating new transactions. Funding means able disburse funds. Interest bearing means we earn or pay interest on balance.")
			,'notes'=>array('class'=>'t_richtext', 'name'=>'notes', 'label'=>"Description and notes", 'width'=>12)
		), $hints = array(
			 'a_Browse'=>array(
				 'include'=>array('name','class','balance')
				,'triggers'=>[ 'banner'=>"create", 'row'=>['update','display'] ] )
			,'a_Edit'=>array(
				 'exclude'=>array('cleardate','cleared') )
		), $operations = array(
			 'display'=>array('action'=>'AcctRegister', 'role'=>'Finance', 'icon'=>'list-alt', 'label'=>"List transactions", 'verb'=>"List")
			,'update'=>array('action'=>'a_Edit', 'role'=>'Finance')
			,'create'=>array('action'=>'a_Edit', 'role'=>'Finance')
			,'delete'=>array('action'=>'a_Delete','role'=>'*super')
			,'list'=>array('action'=>'a_Browse', 'role'=>'Staff', 'args'=>['sortfield'=>'class'])
		);

	public function formatted()
	{
		return "$this->°name";
	}

}
