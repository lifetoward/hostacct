<?php
/**
* This action renders accounting journal entries for a given account in chronological order.
* Account balances are maintained in the records.
*
* All original code.
* @package Synthesis/Finance
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2015 Lifetoward LLC
* @license proprietary
*/
class AcctRegister extends Action
{
	protected $acct, $mode = [ 'from'=>null, 'thru'=>null ];
	private static $savedProperties = [ 'acct', 'mode' ];
	use SerializationHelper;

	public function __construct( Context $c, array $args = [ ] )
	{
		parent::__construct($c);
		$this->acct = static::getFocusFromArgs($args, 'Account', 'account');
		extract($args); // by using this extract/compact approach we control which are allowed.
		$this->mode = compact(array_keys($this->mode));
	}

	public function render_me( Result $returned = null )
	{
		$c = $this->context;
		extract($c->request, EXTR_PREFIX_INVALID, 'r');
		// bring in any valid adjustments to the rendering mode
		$this->mode = array_merge($this->mode, array_intersect_key((array)$args, $this->mode));

	// SETUP
		$R = new HTMLRendering($c, $returned, $this->acct);
		$R->mode = $R::COLUMNAR;
		$R->addStyles(static::styles, 'AcctRegister styles');

	// Rendering
		// soon: allows filtering by date (is this really just filtering by date range?)
		// eventually: allow changing the account while staying within the action
		// eventually: view filtering by other criteria like transaction class
		// eventually: provides transaction finding tools
		$R->dataheadrow = static::renderEntryRow();
		foreach (AcctEntry::getAccountEntries($this->acct, $this->mode['from'], $this->mode['thru']) as $entry) {
			$entry->_rendering = $R;
			$entry->trx->_rendering = $R;
			$R->databodyrows[] = static::renderEntryRow($entry);
		}
		$R->classes = ['table'=>'AcctRegisterData'];
		$R->header = "<h1>$this->acct<small> : Transaction history</small></h1>";
		return $R;
	}

	const styles = <<<'css'
tr.AcctEntry table .fn-tag { width:11em }
tr.AcctEntry table .t_dollars { width:10em }
tr.AcctEntry > .fn-trxdate { width: 10em }
tr.AcctEntry > .fn-balance { width: 11em }
div#container-body { position:relative; }
tr .t_dollars { text-align:center; }
tr .t_balance { text-align:center; }
div.t_decimal { text-align:center !IMPORTANT; }
div.t_balance { text-align:center !IMPORTANT; }
css;

	protected static function renderEntryRow( AcctEntry $entry = null )
	{
		list($el, $date, $type, $label, $balance, $tag, $memo, $credit, $debit) = $entry ?
			[ 'td', $entry->trx->»trxdate, $entry->trx->»class, $entry->trx->»label, $entry->»balance, $entry->»tag, $entry->»memo, $entry->»credit, $entry->»debit ] :
			[ 'th', "Date", "Type", "Transaction label", "Balance", "Specifier", "Memo", "Credit", "Debit" ] ;
		return <<<html
<tr class="AcctEntry">
	<$el class="fn-trxdate t_date">$date</$el>
	<$el class="entry_detail">
		<table width="100%"><tr class="transaction">
			<$el class="fn-class">$type</$el>
			<$el class="fn-label t_string" colspan=3>$label</$el>
		</tr><tr class="entry">
			<$el class="fn-tag t_id">$tag</$el>
			<$el class="fn-memo t_string">$memo</$el>
			<$el class="fn-credit t_dollars">$credit</$el>
			<$el class="fn-debit t_dollars">$debit</$el>
		</tr></table>
	</$el>
	<$el class="fn-balance t_balance">$balance</$el>
</tr>
html;
	}
}
