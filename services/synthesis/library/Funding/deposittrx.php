<?php
//namespace Lifetoward\Synthesis\Funding;
/**
* A deposit assembles an employee's cash and selected items (entries) from a pending receipts account and places them into an asset bank account.
* It finds eligible transactions by looking for FundsXfer objects (of type 'cheque' for now) with 'trx' unset.
* The user selects which of the eligible transactions are to be included in the single deposit transaction, adds any cash to include, and that's it.
*
* CREDITS:
*	- Pending receipts (12) employee
*	- Cash in hand (14) employee
* DEBITS:
*	- Bank account (16) null (extacctent)
* REQUIREMENTS:
*	- employee: Acting employee (presume from login unless delegation rules apply; may be null when employees not implemented)
*	- pendacct: Pending receipts account (presume if singular and not authorized to create)
*	- bankacct: Target bank account (presume if singular and not authorized to create)
*	- receipts: List of debit entries in selected pending receipts account with tag is null
*	- cashamt: Cash amount deposited
*
* All original code.
* @package Synthesis/Finance
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright © 2015 Lifetoward LLC
* @license proprietary
*/
class DepositTrx extends Transaction
{
	public static
		$operations = [ 'delete'=>[ 'role'=>'*super' ], ],
		$hints = [ 'a_Trx'=>[ 'iterable'=>true ], ];


	public function populateTrx( array $values )
	{
	}

	public function renderTrx( TrxRendering $R )
	{

	// Input processing

		list($args, $post) = $c->request;
		if ($args['*exit'])
			return $this->getResult(static::CANCEL);

	// Rendering

		$c->add_styles(self::styles, 'Transaction form');
		$c->add_script(self::scripting, 'Transaction form');

		$R->content = $content;
		return $R;
	}
// begin old code (not very useful)
/*
		$focus = 'finance:element:deposit';
		capcel_load_lib('data');
		if ($frame['post*']) {
			capcel_create_element($focus, $frame['post*']);
			return null;
		}
		capcel_include_stylesheet('common', 'base');
		capcel_include_stylesheet('edit', 'base');
		capcel_include_jscript("helpers", '.');
		capcel_include_jscript("input", 'accounting');
		$fc = array('*fqn'=>'deposit:input', 'render'=>'input_field', 'class'=>$frame['arg*']['_id']?'update':'create', 'inputclass'=>'input', 'dateformat'=>'d M Y');
		global $headscript;
		$headscript .=<<<js
	var total=0,cash=0;
	function capcel_validated(c,fn){
		if (c.type=='checkbox'){if(c.checked)total+=(c.value*1);else total-=(1*c.value);}
		else if(fn=='cash'){total-=cash;cash=c.value*1;total+=cash;}
		document.getElementById('total').innerHTML='$ '+cents_to_dollars(total); }
	js;
		$uniqid = '<input type="hidden" name="uniqid" value="'. uniqid() .'"/>';
		$initial = array('trxdate'=>date('Y-m-d'));
		$body = '<span id="banneractions">'. capcel_exit_action() ."</span>\n".
			"<h1>Assemble a Deposit</h1>\n".
			'<form enctype="multipart/form-data" method="post" action="'. capcel_expect_trigger(array('*post'=>1)) .
				'" name="inputform" class="input">'."\n$uniqid".
			'<div class="editform">'."\n". capcel_render_fields($fc, $focus, $initial);
		$undeps = capcel_focus_data('finance:element:receipt', null, "{}.deposit IS NULL");
		if (!count($undeps))
			$body .= "<center> There are no undeposited receipts. </center>\n";
		else {
			$body .= "<table width=\"100%\">\n".
				input_field(array('*label'=>'Deposit total'),
					'<span id="total" style="font-weight:bold;font-size:larger" class="input">$ 0.00</span>') .
				input_field(array('*label'=>'Receipts to include'), null) .
				"<table width=\"90%\" style=\"margin-left:1em\">\n";
			foreach ($undeps as $receipt)
				$body .= '<tr><td colspan="2">'.
					'<input type="checkbox" class="input" onchange="capcel_validated(this,\'receipt\')" name="receipt['. $receipt['_id'] .']" value="'. $receipt['received'] .'"/> '.
					'&nbsp;'. base_render_date($receipt['trxdate'], "m d,'y") .': $'.
					sprintf("%0.2f", $receipt['received']/100) ." from $receipt->»client as $receipt->»docid</td></tr>\n";
			$body .= "</table>\n";
		}

		// semi-new code this stmt only:
		return $result->prep(static::PROCEED, "$body<p class=\"help\">&nbsp;</p></div>\n".
			'<div id="formactions"><button type="submit" id="submit_button"> Make deposit </button></div>'."\n</form>");

	}

function input_field( $field, $value )
{
	return '<table class="editfield">'.
		'<tr><td class="editprompt" colspan="2">'. $field['*label'] .'</td></tr>'. ($value ?
		'<tr><td class="editvalue">'. $value .'</td><td class="edithelp help">'. $field['help'] ."</td></tr></table>\n" : null);
}

function prep_deposit( $focus, $id, &$data )
{
	foreach ($data['receipt'] as $amount)
		$itemtotal += $amount;
	$debitacct = capcel_element_data("accounting:element:account", $data['debitacct']);
	$description = count($data['receipt']) ." items ". ($data['cash']>0 ? "+ $".sprintf("%0.2f", $data['cash']/100)." " : null) .
		"to $debitacct[_formatted]";
	$creditmemo = "deposited into $debitacct[_formatted]";
	$data['perform'] = array('create', 'create');
	$data['type'] = array('credit','debit');
	$data['amount'] = array($itemtotal, $itemtotal + $data['cash']);
	$data['account'] = array(capcel_get_parm('Undeposited Receipts Account'), $data['debitacct']);
	$data['memo'] = array($creditmemo, $description);
	$data['notes'] = "DEPOSIT: $description";
	if ($data['cash'] > 0) {
		// have to add an additional credit for cash on hand and adjust the debit to include it
		$data['perform'][] = 'create';
		$data['type'][] = 'credit';
		$data['amount'][] = $data['cash'];
		$data['account'][] = capcel_get_parm('Cash on Hand Account');
		$data['memo'][] = $creditmemo;
	}
	// The transaction processor expects the only arrays in the data are the journal entries,
	// so we pull out the receipts list and pass it along to the after function
	$receipts = $data['receipt'];
	unset($data['receipt']);
	return $receipts;
}

function mark_deposited_receipts( $focus, $id, $data, $receipts )
{
	foreach ($receipts as $rcpt => $amount)
		capcel_query("UPDATE receipt SET deposit=$id WHERE _id = $rcpt");
}

*/// end old code
}
