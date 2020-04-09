<?php
/**
* A Recovery is money received from a Vendor for any purpose. That is, it's not Income, but rather a refund on a former Expense.
* It is an actual transfer of funds, not just a "credit" on our account (that's what a Reversal is).
* In other words, this is a general type of receipt of funding operating on the Expense side of the business rather than the Income side.
* When we are a tracked client, typically this operation is linked with a Reversal, ie. by which we mean a Vendors payable account is involved.
* However, when we're just a customer, this receipt can go against an expense line (or few) directly, and can work much like a Payment in reverse.
*
* Note that Recovery is the inverse of Payment just as Reversal is the inverse of Purchase.
* Recoverys and Payments are external funds transfers, whereas Reversals and Purchases are accounting operations against Vendors payable.
* In both sides, the pair of operations can be collapsed into one.
* That is, as described above, a Recovery from a vendor with which we do not have an account, is effectively the combination of a Reversal and Recovery.
* Similarly a Purchase from a vendor with which we have no account is also going to include a Payment.
* In both "collapsed" cases, we are skipping the Vendors payable step, and bridging directly from Expense(s) to Funding accounts or Pending receipts.
* Note also that for any given RECEIPT (in the general sense, to include Receipts and Recoverys specifically), the nature of the receipt dictates whether
*	it lands in a Pending receipts account or directly in a Bank account. That is to say whether it is "directly deposited". Note that it's the nature of the
*	funding method which dictates whether the Pending receipts account is involved; this is NEVER just about whether they are done in "one step".
*	This is because the transactions are likely to post on different dates. We typically intend to book a receipt when we get the instrument, but it won't
*	post until the check (or other process) clears the banks.
*
* REQUIREMENTS:
*	- trxdate (t_date): On what day is the funding instrument received or did the far-side allocation happen?
*	- vendor (Vendor): Allow a null setting (not the initial value!) of "No specific Vendor (Retail)", ie. 0
*	- method (Account): This is a pseudo-field for the UI only. It would allow selection of any Pending receipts account or the option "Direct deposit".
*		Any account with class (Pending receipts) may be selected.
*	- instrument (t_string): This text should be required and is to describe the means by which the funds are arriving, like "Check 12231" or "via Paypal trx 21351925123"
*	- directTo (Account): In fact, this account and the one of method (above) are the same to the transaction. It's just that we want to use the UI to make a clear
*		distinction between direct deposits and funding instruments. If they select direct deposit for method, then the account should be taken from here.
*	- amount (t_dollars): The amount of the funding in total as received.
*	- details: (t_richtext): Not required.

* REVERSAL COLLAPSED
* When there's no Vendor to identify, we must collect a series of expense lines to credit. This code should be common with the Reversal code.
*	- expenseBack[][account]: The expense account against which we deem this portion of the recovery to be applied.
*	- expenseBack[][project]: The project this portion of the recovery affects. All subsequent expense lines added should take their initial project value from the first line.
*	- expenseBack[][amount]: The amount of the split. All these must add up to the total amount above.
*	- expenseBack[][memo]: Description of this part of the recovery, ie. what expense(s) this reimburses
*
* ENTRIES:
*	0: Account = method ? method : directTo, Type = Debit, Amount = amount, Memo = instrument, Tag = null
* Option A: Vendor->account non-null
*	1: Account = Vendor->account, Type = Credit, Amount = amount, Memo = method, Tag = vendor
* Option B: No vendor->account (direct deposit) (aka Reversal Collapsed)
*	n: Account = expenseBack[n][account], Type = Credit, Amount = expenseBack[n][amount], Memo = expenseBack[n][memo], Tag = expenseBack[n][project]
*
* OTHER COMPLETIONS:
*	class: get_class($this)
*	label: "Recovery [from $vendor] [for $expenseBack[][project][,...]]" (keep it pretty, punctuate only with purpose)
*
* UI AND FUNCTIONAL HINTS
*	- The reversal-collapsed code must manage the expense entries to always sum to the total amount. Add empty rows as needed. Follow the Transaction approach.
*	- Don't allow posting if the amount is 0.
*	- Based on whether the selected Vendor has specified a Vendors payable account, the entire Reversal section may or may not be displayed and used.
*		If the selected Vendor has an account, then no reversal section. Otherwise it needs to do as REVERSAL COLLAPSED
*		we must display an additional portion of the UI, the part common with a "reversal" which allows allocation of the returned funds to expense lines. (Below)
*
* All original code.
* @package Synthesis/Finance
* @copyright © 2015 Lifetoward LLC
* @license proprietary
*/
class RecoveryTrx extends Transaction
{
	public static
		$fielddefs = array(
			 'trxdate'=>array('name'=>'trxdate', 'class'=>"t_date", 'input'=>"*picker", 'format'=>"D, j F Y", 'label'=>"Date", 'sort'=>"DESC", 'identifying'=>true)
			,'label'=>array('name'=>'label', 'class'=>'t_string', 'label'=>"Description", 'identifying'=>true)
			,'details'=>array('name'=>'details', 'class'=>'t_richtext', 'label'=>"Notes")
			,'vendor'=>array('name'=>'vendor', 'alias'=>true, 'class'=>'Vendor', 'type'=>'refer', 'label'=>"Vendor")
			,'amount'=>array('name'=>'amount', 'alias'=>'AcctEntry.amount', 'label'=>"Amount received")
			,'directTo'=>array('name'=>'directTo', 'alias'=>'AcctEntry.account', 'label'=>"Direct deposit to")
			,'method'=>array('name'=>'method', 'alias'=>'AcctEntry.account', 'type'=>'refer', 'label'=>"Means of transfer")
			,'instrument'=>array('name'=>'instrument', 'alias'=>'AcctEntry.memo', 'label'=>"Document or instrument")
		),
		$operations = [ 'delete'=>[ 'role'=>'*super' ], ],
		$hints = [ 'a_Trx'=>[ 'iterable'=>true ], ];

	public function populateTrx( array $values )
	{
	}

	public function renderTrx( TrxRendering $R )
	{
	// DELETE THE CODE HERE AND REPLACE IT WITH SOMETHING REAL... THESE ARE TEST EXAMPLES ONLY
		$creditEntry = array('type'=>AcctEntry::Credit, 'account'=>Account::get(array('where'=>"{}.class=270"))->_id, 'amount'=>10, 'disposition'=>'new');
		$debitEntry = array('type'=>AcctEntry::Debit, 'account'=>Account::get(array('where'=>"{}.class=190"))->_id, 'amount'=>10, 'disposition'=>'new');
		$recovery = Transaction::create(array('class'=>'RecoveryTrx'));
		logDebug(array('blank new recovery class from Transaction::create'=>$recovery));
		$recovery->acceptValues(array('entries'=>array($creditEntry, $debitEntry)));
		logDebug(array('recovery created via Transaction with entries'=>$recovery));
		$recovery->store();
		logDebug(array('stored recovery'=>$recovery));
		$recovery = Transaction::get($recovery->_id);
		logDebug(array('loaded recovery'=>$recovery));
		foreach ($recovery->entries as $entry)
			$result .= "$entry";

		$R->content = $content;
		return $R;
	}
}
