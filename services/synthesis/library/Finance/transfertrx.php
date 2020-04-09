<?php
/**
 * A transfer moves money between any two (different) accounts (subject to class criteria) via a mechanism triggered from one of the banks or another.
 *
 * INPUTS TO populateTrx
 *	Render all and in this sequence unless otherwise noted
 *	- trxdate: On what day does the transfer happen?
 *	- amount: How much money moves - required.
 *	- fromacct: Where the money is taken from. Not inactive && class IN Credit line, Bank account, Owners equity
 *	- destacct: Where the money goes to. Not inactive && Bank account, Owners equity
 *	- method: A brief text description of how the transfer was invoked - required, always rendered.
 *	- purpose: Brief text; Not required unless one of the accounts has class (Owners equity) - Always rendered.
 *	- details: Not required, but always available.
 *	- fromstmtentry, deststmtentry: You'll get either 1 or none of these on initialization, or they may be available in an existing transaction. Never rendered.
 *
 * ENTRIES:
 *	0: Account = fromacct, Type = Credit, Amount = amount, Memo = purpose, Tag = (NULL or derived from existing data or fromstmtentry)
 *	1: Account = destacct, Type = Debit, Amount = amount, Memo = method, Tag = (NULL or derived from existing data or deststmtentry)
 *
 * OTHER COMPLETIONS:
 *	trxdate: trxdate
 *	label: "TRANSFER: $method for $purpose" (keep it pretty, if no purpose, no ';')
 *	details: As provided
 *
 * UI BEHAVIOR AND FUNCTIONAL HINTS
 *	- Most information which is fed in on initialization should be readonly on the UI; exceptions are details and purpose, which can remain updatable.
 *	- Don't allow posting if the selected accounts are the same.
 *	- Don't allow posting if the amount is 0.
 *	- Require a method, but only require a purpose if one of the accounts has class = (Owners equity)
 *	- Never render [from|dest]stmtentry, but always be sure they pass through and get stored if set
 *	- The sequence of the fields (important because of 2-col responsive) should be 'trxdate','amount','fromacct','destacct','method','purpose','details'
 *	- Render details last and full-width, whereas the others may go to 2 cols.
 *	- trxdate must be readonly if either entry's tag is set.
 *
 * TO DOs:
 *	- a_Trx must identify the entering status of values so any transaction can know what's updatable.
 *	- Implement the UI constraints logic
 *
 * All original code.
 * @package Synthesis/Finance
 * @copyright © 2015 Lifetoward LLC
 * @license proprietary
 */
class TransferTrx extends Transaction
{
    public static $singular = 'Internal transfer', $plural = 'Internal transfers', $descriptive = "Transfer details",
		$fielddefs = [
			// Common Transaction fields relevant here too
			 'trxdate' => ['name'=>'trxdate', 'class'=>"t_date", 'input'=>"*picker", 'format'=>"D, j F Y", 'label'=>"Date", 'sort'=>"DESC", 'identifying'=>true, 'width'=>3]
			,'label'=>['name'=>'label', 'class'=>'t_string', 'label'=>"Description", 'identifying'=>true, 'readonly'=>true, 'width'=>5 ]
			// Transaction-specific derived fields (not used in this subclass, but needed when with transactions of other classes]
			,'creditcount'=>['name'=>'creditcount', 'derived'=>"SUM(1-`{}_actg_entry_`.`type`)", 'class'=>"t_integer", 'label'=>"Credits"]
			,'debitcount'=>['name'=>'debitcount', 'derived'=>"SUM(`{}_actg_entry_`.`type`)", 'class'=>"t_integer", 'label'=>"Debits"]
			,'creditsum'=>['name'=>'creditsum', 'derived'=>"SUM((1-`{}_actg_entry_`.`type`)*`{}_actg_entry_`.`amount`)", 'class'=>"t_dollars", 'label'=>"Volume"]
			,'debitsum'=>['name'=>'debitsum', 'derived'=>"SUM(`{}_actg_entry_`.`type`*`{}_actg_entry_`.`amount`)", 'class'=>"t_dollars", 'label'=>"Volume"]
			// TransferTrx-specific rendering fields
			,'amount'=>['name'=>'amount', 'alias'=>'AcctEntry.amount', 'label'=>"Amount transferred"]
			,'fromacct'=>['name'=>'fromacct', 'alias'=>'AcctEntry.account', 'label'=>"Transfer FROM this account", 'width'=>4,
				'filter'=>"FIND_IN_SET('inactive',{}.flags)=0 AND {}.class IN (310,190)"]
			,'destacct'=>['name'=>'destacct', 'alias'=>'AcctEntry.account', 'label'=>"Transfer TO this account", 'width'=>4,
				'filter'=>"FIND_IN_SET('inactive',{}.flags)=0 AND {}.class IN (310,190,210)"]
			,'method'=>['name'=>'method', 'alias'=>'AcctEntry.memo', 'label'=>"Method of transfer"]
			,'purpose'=>['name'=>'purpose', 'alias'=>'AcctEntry.memo', 'label'=>"Reason for transfer"]
			,'fromstmtentry'=>['name'=>'fromstmtentry', 'alias'=>'AcctEntry.tag', 'label'=>"Bank statement match", 'readonly'=>true]
			,'deststmtentry'=>['name'=>'deststmtentry', 'alias'=>'AcctEntry.tag', 'label'=>"Bank statement match", 'readonly'=>true]
			// Common caboose field
			,'details'=>['name'=>'details', 'class'=>'t_richtext', 'label'=>"Notes", 'width'=>12]
			];

    public function populateTrx(array $values, Context $c = null)
    {
		$disp = count($this->entries) ? 'change' : 'new';
		// Save off submitted data as itself in case we need to re-render it.
		// i.e. if data is submitted that cannot be accepted, we'll still keep it around to render it as much as we can to allow editing it further.
		foreach ($values as $fn=>$value)
			if (is_array(static::$fielddefs[$fn]))
				$this->$fn = $value;
		return $this->acceptValues(
			[	'trxdate' => $values['trxdate'],
				'details' => $values['details'],
				'label' => "TRANSFER: $values[method]". ($values['purpose'] ? " for: $values[purpose]" : null),
				'entries' => [
					 0=>[
						 '_id'=>$this->entries[0]->_id
						,'type' => AcctEntry::Credit
						,'account' => $values['fromacct']
						,'amount' => $values['amount']
						,'memo' => $values['purpose']
						,'reconcile'=>$this->entries[0]->reconcile
						,'tag'=>$this->values->fromstmtentry instanceof BankStmtEntry ? $this->values->fromstmtentry->_id : $this->entries[0]->tag
						,'disposition' => $disp	]
					,1=>[
						 '_id'=>$this->entries[1]->_id
						,'type' => AcctEntry::Debit
						,'account' => $values['destacct']
						,'amount' => $values['amount']
						,'memo' => $values['method']
						,'reconcile'=>$this->entries[1]->reconcile
						,'tag'=>$this->values->deststmtentry instanceof BankStmtEntry ? $this->values->deststmtentry->_id : $this->entries[1]->tag
						,'disposition' => $disp	]
					]
				], $c);
    }

    public function renderTrx( TrxRendering $R )
    {
        $R->mode = $R::INPUT;
        $this->_rendering = $R;

		// PART 1: Translate the native Transaction into the UI field structure so past state is rendered.
		// Note that sorting rules for contained AcctEntry's ensure Credits appear at index 0 and Debits at index 1 in the entries array.
		if (!$this->unvalidated) { // unvalidated is only true if data was submitted that failed validation.
			$this->fromacct = $this->entries[0]->account;
			$this->amount = $this->entries[0]->amount;
			$this->purpose = $this->entries[0]->memo;
			$this->destacct = $this->entries[1]->account;
			$this->method = $this->entries[1]->memo;
		}

		// PART 2: Render the data fields
        foreach ($this::getFieldDefs() as $fn=>$fd)
			if (!$fd['readonly'] && !$fd['derived'])
				$result .= $R->renderFieldWithRowBreaks($this, $fd);

		// PART 3: Assemble the supporting code and components and return it all.
		$R->addScript(<<<jscript
function validate_trx() {
	// this is a dummy implementation right now
	$('button#trigger-store').prop('disabled',false);
	return true;
}
jscript
			,'TransferTrx');

		$R->addReadyScript("$('.fn-fromacct,.fn-destacct.fn-amount').on('change',validate_trx);\nvalidate_trx();", 'TransferTrx');
		$R->header = "<h1>". htmlentities(static::$singular) ."</h1>";
        $R->content = "<div class=\"row\">$result</div>";
		return $R;
    }
}
