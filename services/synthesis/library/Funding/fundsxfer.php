<?php
//namespace Lifetoward\Synthesis\Funding;
/**
* paydate and rcvdate should match for all reputable transaction models except "escrowed" models where the escrow agent is bona-fide.
*/
class FundsXfer extends Element
{
    public static $table = 'fund_xfer', $singular = "Funding transfer", $plural = "Funds transfers", $descriptive = "Funding details",
		$fielddefs = [
			'type' => [ 'name'=>'type', 'class'=>'t_select', 'label'=>"Type", 'identifying'=>true, 'width'=>4, 'initial'=>'cheque',
				'options'=>[ 'cheque'=>"Check", 'acheft'=>"ACH e-check", 'ccx'=>"Credit card", 'paypal'=>"PayPal", 'wire'=>"Wire transfer", ], ],
			'amount' => [ 'name'=>'amount', 'class'=>'t_dollars', 'label'=>"Total amount", 'required'=>true, 'width'=>4,
	 			'help'=>"This is the 'outer' amount of the transfer, as taken from the payer before any fees are applied.", ],
			'xid'=>[ 'name'=>'xid', 'class'=>'t_string', 'label'=>"Specific identifier", 'identifying'=>true, 'width'=>4,
				'help'=>"This may be a unique ID for the transaction as per the processor, or a specifier used in conjunction with the paying account (like a Check #)", ],
			'payer' => [ 'name'=>'payer', 'class'=>'e_entity', 'type'=>'belong', 'label'=>"Payer", 'help'=>"The source of the funds being transfered.", 'identifying'=>true, 'width'=>6, ],
			'acctid' => [ 'name'=>'acctnum', 'class'=>'t_string', 'label'=>"Paying account identifier", 'help'=>"The identifier for the account from which funds are sourced.", ],
			'receiver'=>[ 'name'=>'receiver', 'class'=>'e_entity', 'type'=>'belong', 'label'=>"Receiver", 'help'=>"The destination of the funds being transfered.", 'required'=>true, ],
			'broker'=>[ 'name'=>'broker', 'class'=>'e_entity', 'type'=>'refer', 'label'=>"Brokering agency",
				'help'=>"This is the processing agent for the funding transfer. Could be a bank, ACH service, Credit card processor, etc.", ],
			'viafee' => [ 'name'=>'viafee', 'class'=>'t_dollars', 'label'=>"Transaction fee",
				'help'=>"The amount of any fee deducted by the transaction broker service, ie. the difference between the amount paid and the net to the receiver.", ],
			'paydate'=>[ 'name'=>'paydate', 'class'=>'t_date', 'input'=>'*picker', 'label'=>"Payment date",
				'help'=>"This UNofficial date marks when the payer released the funds in principle. Example: Signed purchase date for CC or cheque date for cheques.", ],
			'rcvdate'=>[ 'name'=>'rcvdate', 'class'=>'t_date', 'input'=>'*picker', 'label'=>"Receipt date", 'initial'=>'today',
				'help'=>"This UNofficial date marks the receipt of the funds in principle. It will match or precede the reconciliation date. Example: Check received in mail.", ],
			'trx'=>[ 'name'=>'trx', 'class'=>"Transaction", 'type'=>'refer', 'label'=>"Closing transaction", 'readonly'=>true,
				'help'=>"The transaction associated with a funding transfer reflects the arrival of net funds in the receiver's account. Thus it may be pending while the process object exists.", ],
			],
		$operations = [
			'display'=>[ 'role'=>'Staff','action'=>'a_Display' ],
			'create'=>[ 'role'=>'Finance','action'=>'a_Edit'],
			'list'=>[ 'role'=>'Staff' ],
			],
		$hints = [ // hints are provided for general purpose actions to be able to customize rendering of this particular element class
			'a_Browse'=>[ 'include'=>[ 'trxdate','type','xid','amount','payer','receiver' ], 'triggers'=>[ 'banner'=>"create", 'row'=>[ 'display' ], ], ],
			];

	public function formatted()
	{
		return "$this->°type $this->°xid for $this->°amount ". ($this->receiver->_id == $GLOBALS['root']->OurEntity ? "from $this->°payer" : "to $this->°receiver");
	}

	public function renderField( $fn, HTMLRendering $R, $format = null )
	{
		if ('trxdate'!=$fn || $this->trxdate)
			return parent::renderField($fn, $R, $format);
		if ($this->rcvdate)
			return "(Rcvd $this->»rcvdate)";
		if ($this->paydate)
			return "(Paid $this->»paydate)";
		return "(pending)";
	}
}
