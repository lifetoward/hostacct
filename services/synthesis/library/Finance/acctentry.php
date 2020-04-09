<?php
/**
* These are accounting journal entries, one of the three fundamental elements of the accounting system.
* Each entry references one account and one transaction.
* It contributes to the account's balance based on its sign as a credit or debit.
* It combines with other entries to make an enforced-balanced transaction.
* It has a reconciliation status flag which can be set to any of 3 states:
*	- Open means the entry is asserted by the originating accounting, but has not been confirmed.
*	- Confirmed means that another data source or process corroborates the entry... this is usually a transitional state which equates with "marking" a statement line until the total can be validated.
*
* All original code.
* @package Synthesis/Finance
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
class AcctEntry extends Element
{
	const Credit = 0, Debit = 1,
		Open = 0, Confirmed = 1; // reconciliation status

	public static $table = 'actg_entry', $singular = "Journal entry", $plural = "Journal entries", $descriptive = "Journal entry",
		$description = "Contains journal entries made in the common general ledger. Each entry is either a credit or a debit and is associated with a balanced Transaction. The sum of all the entries is the account balance.",
		$fielddefs = array(
			 'trx'=>array('name'=>'trx', 'type'=>'belong', 'class'=>"Transaction", 'label'=>"Transaction", 'sort'=>true, 'noupdate'=>true)
			,'account'=>array('name'=>'account', 'type'=>"belong", 'class'=>"Account", 'label'=>"Account", 'filter'=>Account::ActiveFilter)
			,'type'=>array('name'=>'type', 'class'=>"t_boolean", 'label'=>"Debit or Credit", 'format'=>"Credit|Debit", 'sort'=>"ASC", 'initial'=>self::Credit, 'required'=>true)
			,'amount'=>array('name'=>'amount', 'class'=>"t_dollars", 'label'=>"Amount", 'required'=>true)
			,'memo'=>array('name'=>'memo', 'class'=>"t_string", 'label'=>"Memo")
			,'tag'=>array('name'=>'tag', 'label'=>"Tagged element", 'class'=>'t_id')
			,'credit'=>array('name'=>'credit', 'class'=>"t_dollars", 'label'=>"Credit", 'derived'=>"IF(`{}`.`type`,NULL,`{}`.amount)")
			,'debit'=>array('name'=>'debit', 'class'=>"t_dollars", 'label'=>"Debit", 'derived'=>"IF(!`{}`.`type`,NULL,`{}`.amount)")
			,'balance'=>array('name'=>'balance', 'class'=>'t_balance', 'derived'=>"NULL", 'label'=>"Balance",
                'help'=>"This is a temporary value which presents the balance achieved by this entry in a running set of entries within an account, ie. in a Register setting.")
		), $hints = array( // hints are provided for general purpose actions to be able to customize rendering of this particular element class
			 'TypeRoots' => array( AcctEntry::Credit => 'cred', AcctEntry::Debit => 'deb' )
			,'TypeCapRoots' => array( AcctEntry::Credit => 'Cred', AcctEntry::Debit => 'Deb' )
		), $operations = array( // actions allow general purpose actions to know how to interact with this element class in various situations
		);

	public function renderField( $fn, HTMLRendering $R, $hints = null )
	{
		if ($fn == 'balance') {
			$positive = $this->account->class->positive;
			$sign = $positive * 2 - 1;
			$value = $this->$fn * $sign;
			if ($value < 0) {
				$negative = 'negative';
				$value = -$value;
			}
			$R->addStyles("div.t_balance { background-color:transparent; border:1px solid transparent; text-align:justify; width:auto; color:darkgreen; }".
				"\ndiv.t_balance>div { text-align:right; display:inline-block; width:6em; }".
				"\ndiv.t_balance>span { color:transparent; display:inline-block; width:1pc; text-align:center; }".
				"\ndiv.t_balance.negative { color:darkred; } div.t_balance.negative>span { color:inherit }", 'acctentry balance');
			return "<div class=\"t_balance $negative fn-$fn\"><span>". ($negative ? '(' : '&nbsp;') ."</span>". $GLOBALS['root']->moneysign .
				"<div id=\"$R->idprefix$fn\">". number_format($value, 2) ."</div><span>". ($negative ? ')' : '&nbsp;') ."</span></div>";
		}
		if (in_array($fn, ['credit','debit']) && $this->$fn === null)
			return '&nbsp;';
		return parent::renderField($fn, $R, $hints);
	}

	public function getFieldValue( $fn )
	{
		$val = parent::getFieldValue($fn);
		if ($fn != 'tag' || !$val)
			return $val;
		$class = $this->getIDClass($fn);
		return $class::get($val);
	}

	public function getIDClass( $fn )
	{
		if ($fn != 'tag')
			return null;
		$tagcf = $this->type == self::Debit ? 'debittag' : 'credittag';
		return $this->account->class->$tagcf;
	}

	public function formatted( )
	{
		return ($this->trx instanceof Transaction ? $this->trx->°trxdate : "?? Missing trx!!") .": $this->°type $this->°amount @ $this->°account";
	}

	/**
	* We differ form the usual in that we are final and we accept and pass-thru a transaction initializer.
	*/
	final public static function create( Transaction $trx, array $initial = array() )
	{
		$entry = new AcctEntry;
		$entry->acceptValues($initial, $trx);
		return $entry;
	}

	/**
	* Values are accepted from within a container.
	* Here are the rules we enforce:
	*	Any entry must have a defined account, type, and transaction, and its amount must be numeric and non-negative. (We enforce the latter, the rest are in the standard acceptValues())
	*	You can't change an entry's transaction, but you can set it when it's new. (Standard flagged as 'noupdate')
	*	It's not legal to delete a Confirmed entry. (We enforce.)
	*	It's not legal to delete or change anything but the memo in a Confirmed entry. (We enforce.)
	*	Reconciliation status may only be changed if nothing else changes. (We enforce.)
	* 	The disposition is one of 4 defined strings which indicate how to accept the proposed changes to the record.
	* 	'keep' means that there's no change actually submitted for the entry, but we're accepting it anyway because we verify it's unchanged for transactional integrity. (We actively check for a complete match)
	* 	'change' means some aspects of the entry are changing. Depending on the reconciliation status, these changes may be limited. (See below.) Only certain fields may change under certain situations.
	* 	'delete' means the entry should be removed from the transaction. We care about that here because we only allow it depending on reconciliation status.
	* 	'new' means we're populating a blank acctentry instance and appropriate requirements must be met.
	* @param mixed[] $values An augmented associative array representation of an account entry object's fields. it's augmented to include _id and disposition (described above)
	* @param Transaction $container Because we are contained, we use the container information as part of our internal validation or in creating new entries.
	* @return integer We return the number of fields which have been changed. We return -1 when the entry is to be deleted from its transaction. (Caller must perform this.)
	*/
	final public function acceptValues( array $values, Transaction $container )
	{
		$_id = 0; $trx = 0; $account = 0; $amount = 0; $memo = 0; $disposition = 0;
		extract($values, EXTR_IF_EXISTS); // so we have a white-listed set of values
		global $root;
		$amount = str_replace($root->mon_decimal_point, '.', str_replace($root->mon_thousands_sep,'',$amount)) * 1;

		if ($trx && (($trx instanceof Transaction && $trx != $container) || (is_scalar($trx) && $trx != $container->_id)))
			$problems['trx'] = "Reference to container Transaction ($container->_id) must match the value we're receiving for the container ($trx).";
		$values['trx'] = $container; // we want the actual object going forward esp. in parent::acceptValues().

		if ($_id != $this->_id && $disposition != 'new')
			$problems['_id'] = "Accepted _id ($_id) must match current _id ($this->_id).";
		unset($values['_id']); // prevents attempt to set this in parent::acceptValues()

		if ('keep' == $disposition) {
			if ($this->account->_id != $account)
				$problems['account'] = "Changes detected ('{$this->account->_id}' becomes '$account') in entry field '$fn' while the entry is asserted as unchanged.";
			foreach (['amount', 'memo'] as $fn)
				if ($this->$fn != $$fn)
					$problems[$fn] = "Changes detected ('{$this->$fn}' becomes '${$fn}') in entry field '$fn' while the entry is asserted as unchanged.";

		} else if ('delete' == $disposition) {
			if ($this->tag && 'BankStmtEntry' == $this->account->class->credittag)
				$problems['tag'] = "Cannot delete this entry because it's already reconciled with an entry from an external account.";

		} else if ('change' == $disposition) {
			if ($this->tag && 'BankStmtEntry' == $this->account->class->credittag) {
				if ($this->account->_id != $account)
					$problems['account'] = "Can't change accounts when the associated reconcilable account entry has been identified.";
				if ($this->amount != $amount)
					$problems['amount'] = "Can't change amounts when the associated reconcilable account entry has been identified.";
			}
		} else if ($disposition != 'new')
			$problems['disposition'] = "Invalid disposition for a submitted entry.";

		try {
			$count = parent::acceptValues($values);
		} catch (BadFieldValuesX $ex) {
			$problems = array_merge((array)$problems, $ex->problems);
		}

		if (count($problems))
			throw new BadFieldValuesX($problems, "Unable to accept the submitted ". static::$singular);
		return $count;
	}

	/**
	* Our customization of loadInstanceData ensures that only the interesting (non-derived) parts of transactions are loaded along with the entries.
	* @param mixed $keysel We narrowly interpret keysel, always including _id and _capcel in the load and optionally selecting on the id value(s) provided as $keysel.
	*		We also accept either an Account or Transaction instance as $keysel, in which case we override whatever matching trx or account arg we get with the _id of the passed instance.
	* @param array $args In the $args:
	*		- we accept special keys endDate and beginDate which bracket (inclusively) the entries based on their transaction dates
	*		- we accept key 'account' and 'trx' as objects or IDs - It only makes sense to specify one of these.
	*		- sortfield is ignored because there's only 1 correct way to sort entries: trxdate and trx->_id descending then type and _id ascending.
	*		- reverse is allowed
	*		- we ignore limit and start
	*		- we allow where clauses
	* @return LoadingStatement The executed statement.
	*/
	public static function loadInstanceData( $keysel = null, array $args = [] )
	{
		extract($args); // this is an internal call, so we trust the args
		$q = LoadingStatement::newStatement(__CLASS__, $db = $GLOBALS['root'], null, $args['reverse'], true); // always sort, but say by nothing so trx ends up in front
		if ($keysel instanceof Account)
			$account = $keysel;
		else if ($keysel instanceof Transaction)
			$trx = $keysel;
		else if (is_array($keysel) && $keysel['_id'])
			$keysel = $keysel['_id'];
		if ($keysel) {
			if (is_numeric($keysel))
				$q->addSQLSelector("{}._id = $keysel");
			else if (is_array($keysel))
				$q->addSQLSelector("{}._id IN (". implode(',', $keysel) .")");
		}
		$q->addColumn('_id');
		$q->addColumn('_capcel');
		$q->addColumn('_full', 1);
		$q->addGrouping('_id');
		$q->addSorting('type');
		$q->addSorting('_id');
		foreach (static::getFieldDefs() as $efd)
			$q->addColumn($efd['name'], $efd['derived']);
		if ($where)
			$q->addSQLSelector($where);
		$account = $account instanceof Account ? $account->_id : (is_numeric($account) ? $account : null);
		if ($account)
			$q->addFieldSelector('account', "= $account");
		$trx = $trx instanceof Transaction ? $trx->_id : (is_numeric($trx) ? $trx : null);
		if ($trx)
			$q->addFieldSelector('trx', "= $trx");

		$tq = $q->joinedInstance('Transaction', 'trx', 'trx_', array('trx','_id'));
		$tq->addColumn('_id');
		$tq->addColumn('_capcel');
		$tq->addColumn('_full', 1);
		$fds = Transaction::$fielddefs;
		foreach ($fds as $tfd)
			if (!$tfd['derived'] || $trx)
				$tq->addColumn($tfd['name'], $tfd['derived']);
		if ($endDate) // assignment intended
			$tq->addFieldSelector('trxdate', "<= '$fromDate'");
		if ($beginDate)
			$tq->addFieldSelector('trxdate', ">= '$thruDate'");
		if (!$args['sortfield'] && !$args['reverse']) {
			$tq->addSorting('trxdate', true);
			$tq->addSorting('_id', true);
		}
		$q->incorporate($tq);
		$q->execute();
		return $q;
	}

	/**
	* Use this method to get a fully assembled, sorted, correlated set of accounting entries:
	*  for a single account, with running balances, sorted by date future-to-past.
	* @param Account $acct The account which all the returned entries must belong to.
	* @param string $dateA (optional) A date in native 'Y-m-d' format.
	* @param string $dateB (optional) A second date in native 'Y-m-d' format.
	* If no date is passed, all transactions which touch the account will be included.
	* If one date is passed, it represents the earliest date to include among the transactions.
	* If two dates are passed, transactions from the earlier date thru the later date will be included.
	* @return AcctEntry[] The array returned will be a simple list of AcctEntry instances
	*		They will reference completely loaded Transaction objects and include a derived value for the AllEntries balance as $entry->balance.
	*		Remember that balances as stored and used within the system are always credit-negative. Only on rendering do we flip their sign.
	*/
	public static function getAccountEntries( Account $acct, $dateA = null, $dateB = null )
	{
		$fromDate = null; $thruDate = null;
		if ($dateA && $dateB) {
			$thruDate = $dateA > $dateB ? $dateA : $dateB;
			$fromDate = $dateA > $dateB ? $dateB : $dateA;
		} else if ($dateA)
			$fromDate = $dateA;

		$q = static::loadInstanceData($acct, compact('thruDate','fromDate'));
		if (!$q->rowCount)
			return [];

		// Gotta get the real computed balance from the point we are ending at.
		$trxTable = Transaction::$table;
		$entryTable = self::$table;
		$balance = 1 * $GLOBALS['root']->dbGetScalar("SELECT SUM(IF(type,1,-1)*amount) FROM `$entryTable` " .($endDate ? "LEFT JOIN `$trxTable` ON (`$entryTable`.trx=`$trxTable`._id) " : null).
				"WHERE account = $acct->_id". ($thruDate ? " AND trxdate <= '$thruDate'" : null), "Get balance for Account $acct". ($thruDate ? " thru $thruDate" : null));

		// Now we can process the result into a list of entries and some transactions
		while ($r = $q->next) {
			Transaction::getInstanceFromLoadedData($r, false, 'trx_'); // this ensures the trx's are current in cache
			$e = AcctEntry::getInstanceFromLoadedData($r, true);
			$e->balance = $balance; // an aux value which represents the account balance this entry achieves
			$balance -= $e->amount * ($e->type ? 1 : -1);
			$result[] = $e;
		}
		return $result;
	}

	/**
	* Render an entry as a table row for INPUT
	* @param HTMLRendering $R The context in which we are rendering. We create a local transient subcontext from it to set various items which affect rendering the entry fields.
	*		Without naming hints (additional parms below), will render entry with name="$fieldname"
	* @param string $entriesName An array name to contain the field names for "name" attributes for posting. ie. will render with name="$entriesName[$entryIndex][$fieldname]"
	* @param integer $entryIndex A further numeric index within the names array which further specifies the posting name of the entry's fields. ie. will render with name="$entriesName[$entryIndex][$fieldname]"
	*		Without an entryIndex, the id of the row is set to "templaterow", but with one it's set to "acctentry_$entryIndex"
	* @return string Rendered tablerow
	*/
	public function render( HTMLRendering $R, $entriesName = null, $entryIndex = null )
	{
		if ($R->mode != $R::INPUT)
			return htmlentities($this->formatted());
		$namePrefix = $entriesName ? ($entriesName .(isset($entryIndex) ? "[". ($entryIndex*1) ."]" : null)) : null;
		$R->formControlNamePrefix = $namePrefix;
		$R->idprefix = isset($entryIndex) ? sprintf("%02d-", $entryIndex * 1) : null;
		$this->_rendering = $R;
		if ($this->tag && $this->account->class->credittag == 'BankStmtentry') {
			$disabled['amount'] = true;
			$disabled['account'] = true;
			$trigger = '<span class="glyphicon glyphicon-th-list" title="Entry is Confirmed" style="color:blue"></span>';
		} else
			$trigger =
				'<button '. ($this->disposition == 'delete' ? null : 'style="display:none" ') . $R->tabindex .' type="button" class="btn btn-warning delentry" id="restore" disabled="1" onclick="restoreEntry($(this).parents(\'tr.acctentry\'))" title="Restore this entry">'.
					'<span class="glyphicon glyphicon-repeat"></span></button>'.
				'<button '. ($this->disposition == 'delete' ? 'style="display:none" ' : null) . $R->tabindex .' type="button" class="btn btn-warning delentry" id="delete" disabled="1" onclick="deleteEntry($(this).parents(\'tr.acctentry\'))" title="Delete this entry">'.
					'<span class="glyphicon glyphicon-remove"></span></button>';
		$this->amount *= 1;
		foreach (['account','amount','memo'] as $fn)
			$visibleFields .= "\n<td class=\"$fn\">". str_replace("name=\"$fn\"", 'name="'. ($namePrefix ? "{$namePrefix}[$fn]" : $fn) .'"', $this->{».$fn}) ."</td>";
		$hiddenFields = "<input type=\"hidden\" class=\"fn-trx\" name=\"". ($namePrefix ? "{$namePrefix}[trx]" : 'trx') ."\" id=\"{$R->idprefix}trx\" value=\"{$this->trx->_id}\"/>";
		foreach (['_id', 'type', 'disposition'] as $hfn)
			$hiddenFields .= "<input type=\"hidden\" class=\"fn-$hfn\" id=\"{$R->idprefix}$hfn\" name=\"". ($namePrefix ? "{$namePrefix}[$hfn]" : $hfn) ."\" value=\"{$this->$hfn}\"/>";
		$R->addReadyScript('$(".fn-amount,.fn-account,.fn-memo","tr.acctentry:not(#templaterow)").on("change",'.
			'function(e){var d=$("input.fn-disposition",$(e.target).parents("tr.acctentry"));if(d.val()=="keep")d.val("change")});', 'disposition updater');
		$id = $namePrefix ? "acctentry_$entryIndex" : 'templaterow';
		return "<tr class=\"acctentry". ($this->account ? null : " incomplete") . ($this->disposition == 'delete' ? ' deleted' : null) ."\" id=\"$id\">$visibleFields<td class=\"triggers\">$trigger$hiddenFields</td></tr>\n";
	}
}
