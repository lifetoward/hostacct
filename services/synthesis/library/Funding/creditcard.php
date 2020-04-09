<?php
/**
* A credit card facilitates purchase payments. It's not always hooked to a credit line... it could just as well be hooked to a current asset bank account.
* If the card is associated with a bank account then it's a card owned by the focal entity and that's the account that will register the charges made, ie. what we'll use to reconcile its purchases.
* Without a bank account, the card is owned by another entity. Such cards are used for accepting payment from clients rather than making payments to vendors.
*
* All original code.
* @package Synthesis/Finance
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
class CreditCard extends TrxInstrument
{
	public static $table = 'fund_credcard', $singular = "Credit card", $plural = "Credit cards", $descriptive = "Credit card info",
		$description = "A credit card facilitates purchases from a funding account. Multiple cards may be tied to a single account.",
		$fielddefs = array(
			 'label'=>array('name'=>'label', 'class'=>'t_string', 'label'=>"Common name", 'identifying'=>true, 'help'=>"Common identification of this card within this system.")
			,'holder'=>array('name'=>'holder', 'class'=>'e_person', 'type'=>'belong', 'label'=>"Card holder",
				'help'=>"This is the entity which is financially responsible for the debt on the card.")
			,'bankacct'=>array('name'=>'bankacct', 'type'=>'belong', 'class'=>'BankAccount', 'label'=>"Bank account",
				'help'=>"When the bank account is specified, it means we own it. Otherwise it's a client's means of payment.")
			,'cardnum'=>array('name'=>'cardnum', 'class'=>'t_cardnum'
			,'system'=>array('name'=>'system', 'class'=>'t_select', 'label'=>"Credit card system",
				'options'=>array('visa'=>"Visa", 'mc'=>"MasterCard", 'discover'=>"Discover", 'amex'=>"American Express", 'dc'=>"Diner's Club"))
			,'expdate'=>array()
			,'cid'=>array()
			,'billaddr'=>array()
		), $hints = array( // hints are provided for general purpose actions to be able to customize rendering of this particular element class
		), $operations = array( // operations allow general purpose actions to know how to interact with this element class in various situations
		);

	public function formatted( )
	{
		return "$this->°label (-". substr($this->acctnum, -4) .")";
	}

}