<?php
/**
* A transaction is an enforced-balanced set of accounting journal entries.
* We have a very flexible model, one which allows any number of credit or debit entries as long as the total of all debits matches the total of all credits.
* Transactions happen on a given date. We don't allow specifying a time of day.
*
* All original code.
* @package Synthesis/Finance
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright © 2014-2015 Lifetoward LLC
* @license proprietary
*/
class Transaction extends Container
{
	public static $table = 'actg_trx', $singular = "General transaction", $plural = "Accounting transactions", $descriptive = "Transaction details",
		$description = "A transaction is an enforced-balanced collection of debits and credits which happen as one event in time and are represented by an associated set of accounting journal entries.",
		$containDef = ['fname'=>'entries', 'class'=>'AcctEntry', 'refBy'=>'trx'],
		$fielddefs = array(
			 'trxdate'=>array('name'=>'trxdate', 'class'=>"t_date", 'input'=>"*picker", 'format'=>"D, j F Y", 'label'=>"Date", 'sort'=>"DESC", 'identifying'=>true, 'width'=>4)
			,'class'=>[ 'name'=>'class', 'derived'=>'{}._capcel', 'label'=>"Type" ]
			,'label'=>array('name'=>'label', 'class'=>'t_string', 'label'=>"Description", 'identifying'=>true, 'width'=>8)
			,'details'=>array('name'=>'details', 'class'=>'t_richtext', 'label'=>"Notes", 'width'=>12)
//			,'attachpoint'=>array('name'=>'attachpoint', 'class'=>'f_attachpoint', 'type'=>'fieldset')
			,'creditcount'=>array('name'=>'creditcount', 'derived'=>"SUM(1-`{}_actg_entry_`.`type`)", 'class'=>"t_integer", 'label'=>"Credits")
			,'debitcount'=>array('name'=>'debitcount', 'derived'=>"SUM(`{}_actg_entry_`.`type`)", 'class'=>"t_integer", 'label'=>"Debits")
			,'creditsum'=>array('name'=>'creditsum', 'derived'=>"SUM((1-`{}_actg_entry_`.`type`)*`{}_actg_entry_`.`amount`)", 'class'=>"t_dollars", 'label'=>"Volume")
			,'debitsum'=>array('name'=>'debitsum', 'derived'=>"SUM(`{}_actg_entry_`.`type`*`{}_actg_entry_`.`amount`)", 'class'=>"t_dollars", 'label'=>"Volume")
			),
		$operations = [ // actions allow general purpose actions to know how to interact with this element class in various situations
			'display'=>[ 'action'=>'a_Trx', 'role'=>'Finance' ],
			'update'=>[ 'action'=>'a_Trx', 'role'=>'Finance' ],
			'create'=>[ 'action'=>'a_Trx', 'role'=>'Finance' ],
			'list'=>[ 'action'=>'a_Browse', 'role'=>'Staff' ],
			'delete'=>[ 'role'=>'Finance' ],
			],
		$hints = array( // hints are provided for general purpose actions to be able to customize rendering of this particular element class
			'a_Browse'=>[ 'include'=>[ 'trxdate','label','creditcount','debitcount','debitsum' ], 'triggers'=>[ 'banner'=>"create", 'row'=>"display" ], ],
			'a_Trx'=>[ 'iterable'=>true ],
		);

	/**
	* Accepting a transaction can include accepting its entries.
	* If the entries are included, then they must be provided as an additional pseudo-field in the values array named 'entries'.
	* Accepting entries does not require them to be in balance, and we accept the entries we can while rejecting those we can't. Some are ignored. See AcctEntry->acceptValues() for details.
	* We do throw BadFieldValuesX for the entries we cannot accept (each with its own reasons) but the caller can eat that exception as they wish or could wrap
	* 	the entire acceptValues() call inside of an instance transaction which can be rolled back.
	*
	* When entries is supplied, it must be an array of assoc arrays, each of which represents an acceptable AcctEntry record in array format.
	* Every entry known to exist in the transaction in its current state must be represented in the array of entries provided, in the same order and with matching type and _id fields.
	* 	Existing entries are required to include a pseudo-field named "disposition" which contains "keep", "delete", or "change". These are recognized and processed by AcctEntry->acceptValues(); see its rules for details.
	* New entries may appear after those that already existed.
	*/
	final public function acceptValues( array $values, Context $c = null )
	{
		$accepted = parent::acceptValues($values, $c);

		// Here we compress and order the entries and recalculate our derived field values because we've just made changes vs the database and these values are useful.
		foreach (array('state','creditsum','debitsum','creditcount','debitcount') as $derived)
			$this->updated[$derived] = 0;
		$compressed = array(AcctEntry::Credit=>array(), AcctEntry::Debit=>array());
		foreach ($this->contained as $entry) {
			if ($this->_stored) // if the thing has never been stored, its only possible state is New (0)
				$this->updated['state'] |= $entry->reconcile;
			$entry->type == AcctEntry::Credit && ++$this->updated['creditcount'] && $this->updated['creditsum'] += $entry->amount;
			$entry->type == AcctEntry::Debit && ++$this->updated['debitcount'] && $this->updated['debitsum'] += $entry->amount;
			$compressed[$entry->type][] = $entry;
		}
		$this->contained = array_merge($compressed[AcctEntry::Credit], $compressed[AcctEntry::Debit]);
		return $accepted;
	}

	public function acceptFieldValue( $fn, $value, $more = null )
	{
		if ($fn == 'trxdate' && $this->trxdate != $value && $this->state > AcctEntry::Open)
			throw new BadFieldValueX($this->¶trxdate, "The transaction date cannot be changed because at least one of the entries has been confirmed.");
		return parent::acceptFieldValue($fn, $value, $more);
	}

	/**
	* We override the latent method because we're latent if any of our entries are latent or if our entries are out of balance.
	* For the purposes of this method signature, we treat our internal entries as if they were named 'entries' in aggregate.
	* @param mixed $args (optional) Interpreted as follows:
	*	- If you pass a single string argument we check whether the field with that name is latent.
	*	- If you pass a single boolean true, we return a boolean indicating whether any part of the object is latent. This is the sense obtained via shorthand $instance->_latent.
	* 	- If you pass no argument it returns a simple array listing all latent field names.
	*	- If you pass an array or many arguments, each is taken as a field name and if any of them is latent, you'll get true back, otherwise false
	* @return boolean|array See notes for the parameter above.
	*/
	final public function latent( $args = null )
	{
		if (!is_string($args) || ($efld = $args == 'entries')) {
			foreach ((array)$this->entries as $entry) {
				if ($entries = ($entries || $entry->latent(true)))
					break;
				$balance += $entry->type ? $entry->amount : -1 * $entry->amount;
			}
			$entries = $balance != 0 || $entries || count($this->toDelete);
		}
		if (!is_string($args) || !$efld)
			$trx = parent::latent($args); // gets my usual fields and handles all cases in which an array is passed in. (We don't handle the array case below.)
		if (is_string($args))
			return $efld ? $entries : $trx;
		if ($args === true)
			return $trx || $entries;
		if (!$args) {
			$entries && ($trx[] = 'entries');
			return $trx;
		}
		return false;
	}

	/**
	* A valid transaction should always have a balance of 0.
	* If you call this on a transaction and it returns something other than 0, you can assume the transaction is both latent and in need of updating before it can be stored.
	* @return numeric The balance method returns the difference between the totals of debits and credits.
	*	Excess credits will appear negative, while excess debits will appear positive.
	*/
	final public function balance( )
	{
		$balance = 0;
		foreach ((array)$this->contained as $entry)
			if ($entry->disposition != 'delete')
				$balance += round($entry->amount * ($entry->type ? 100 : -100));
		return $balance / 100;
	}

	public function validate( )
	{
		if (self::balance() != 0)
			throw new Exception("Transaction is not in balance.");
	}

	public function duplicate()
	{
		$dupe = parent::duplicate();
		$dupe->state = 0; // because we have just made this populated transaction artificially new
		return $dupe;
	}

	/**
	* This method is intended to be reimplemented by subclasses.
	* Its purpose is to convert the parameterized inputs defined for the subclass into canonical Transaction values.
	* Typically this is used in 2 cases:
	*	1. The result of the UI's posted form is in specific format, and this converts it to canonical format.
	*	2. When someone constructs one of these with initial values to effect a "live birth" transaction.
	* @param mixed[] The values accepted here should be specific to the transaction class. In this base implementation, they're canonical already.
	* @return void This method should through an exception if it has a problem, typically BadFieldValuesX.
	*/
	public function populateTrx( array $values, Context $c = null )
	{
		$this->acceptValues($values, $c);
	}

	/**
	* This method is intended to be implemented by subclasses.
	* Its purpose is to return a UI rendering which represents the current state of the transaction and conditionally allows modification.
	* We do NOT include the rendering of the standard button bar at the bottom of the transaction UI.
	* We let the caller do the transaction-level operation buttons, and moderate which are appropriate through subclass static variables.
	* @param TrxRendering $R Provide the TrxRendering in which to accumulate the rendering. Assume some content came before, and some will come after.
	* @return string Provide the portion of the rendering within the form and not including the transaction triggers. Begin with the heading, etc.
	*/
	public function renderTrx( TrxRendering $R )
	{
		$R->mode = $R::INPUT;
		$R->idprefix = null;
		$this->_rendering = $R;
		$R->header = "<h1>". static::$singular ."</h1>";

		// First render all the entries, but categorize them by type
		foreach ((array)$this->entries as $x=>$entry)
			$entryRows[$entry->type] .= $entry->render($R, "entries", $x);

		// Second: Insert the rendered entries into the form, Credits first, then the Debits
		foreach (AcctEntry::$hints['TypeRoots'] as $type => $root) {
			$caproot = AcctEntry::$hints['TypeCapRoots'][$type];
			$addRowTrigger = $this->status ? null :
				'<button onclick="addEntry('. $type .',1);validate_trx();" type="button" class="btn btn-primary add-entry-button" '. $R->tabindex .' title="Add a new '. $caproot .'it entry">'.
					'<span class="glyphicon glyphicon-plus"></span> Add a new '. $caproot .'it entry</button>';
			$entriesOut[$type] = <<<html
<fieldset id="set$type" class="form-group">
<label for="{$root}itstable" style="font-size:larger">{$caproot}its ({$this->{».$root.itcount}})</label>
<table class="entries" rules="groups" id="{$root}itstable">
<thead><tr><th class="account">Account</th><th class="amount">Amount</th><th class="memo">Memo</th><td class="triggers">&nbsp;</td></tr></thead>
<tbody id="{$root}itsbody">
{$entryRows[$type]}
</tbody>
<tfoot><tr>
	<th class="account" style="text-align:right">Total of {$caproot}its:</th><th class="amount">{$this->{».$root.itsum}}</th><th colspan="2" style="text-align:right">$addRowTrigger</th>
</tr></tfoot>
</table>
</fieldset>
html;
		}

		// Render the template used as a clone base for new entries
		$emptyEntry = AcctEntry::create($this, array('disposition'=>'new', 'amount'=>0));
		$R->templates = '<form class="templates" action="" method="get" name="templateForm" id="templateForm">'.
			"\n<table>". $emptyEntry->render($R) ."</table>\n</form>\n";

		// Now set hooks to validate entire transaction whenever an account or amount field changes or if
		$R->addReadyScript(<<<jscript
var entrows=$('tr.acctentry');
$('.fn-account,.fn-amount',entrows).on('change',validate_trx);
$('td.triggers button',entrows).on('click',validate_trx);
validate_trx();
jscript
			, 'a_trx entry hooks');

		// Render the transaction element fields
		$R->idprefix = null; // template entry rendering sets this(?)
		foreach ([100=>['trxdate','label'], 300=>['details']] as $R->tabindex=>$fields)
			foreach ($fields as $fn)
				$trxFields[$fn] = $R->renderFieldInForm($this, $this->getFieldDef($fn));

		// Include the functional scripting and styles
		$R->addScript("var a_trx = { entries:". count($this->entries) ." };\n". self::UIScript, "Transaction scripting");
		$R->addStyles(self::styles, 'Transaction styles');

		// Here we put it all together
		if (!$this->creditcount)
			$R->addReadyScript("addEntry(0);");
		if (!$this->debitcount)
			$R->addReadyScript("addEntry(1);");

		$R->content = "<fieldset id=\"trxset\" class=\"row\"><div class=\"col-md-3\">$trxFields[trxdate]</div>\n<div class=\"col-md-9\">$trxFields[label]</div>\n</fieldset>\n".
			"$entriesOut[0]\n$entriesOut[1]\n<fieldset id=\"trxdetails\">\n$trxFields[details]\n</fieldset>\n";
		return $R;
	} // Transaction::renderTrx

	const UIScript = <<< 'jscript'
function validate_trx() {
	var adjrow=[null,null], count=[0,0], sum=[0,0];
	$('tr.acctentry:not(#templaterow)')
		.each(function(x){
			if($('.fn-disposition',this).val()=='delete')
				{ $('select,input',this).prop('disabled',true); return; }
			var t=1*$('.fn-type',this).val(),
				inc=$('.fn-account',this).val()=='',
				a=$('.fn-amount',this).val().asCents();
			count[t]++; sum[t]+=a;
			if(inc) {
				adjrow[t] = this; $(this).addClass('incomplete');
			} else
				$(this).removeClass('incomplete');
			$('button.delentry',adjrow[t]).prop('disabled',inc&&a);
		});
	var bal=sum[0]-sum[1];
	if(bal!=0) {
		var d = Math.abs(bal), lt=1*(bal>0), ht=1*(!lt); // light and heavy types
		if (adjrow[ht]) { // the heavy side is adjustable... let's reduce it before adding a new entry.
			var hc = $('.fn-amount',adjrow[ht]), ha = hc.val().asCents();
			if(ha>d) { // the difference can be completely taken up by reducing the heavy side without wiping it out
				hc.val((ha-d).asDollars()); sum[ht]-=d; d=0;
			} else if(count[ht]>1){ // we can kill the heavy adjustable row, and we still have difference remaining to add
				adjrow[ht].remove(); adjrow[ht]=null;
				count[ht]--; sum[ht]-=ha; d-=ha; // gap narrowed
		}}
		if(d){ // If we must still make up the difference on the light side
			if(!adjrow[lt]) {
				adjrow[lt]=addEntry(lt); count[lt]++; }
			var lc=$('.fn-amount',adjrow[lt]);
			lc.val((lc.val().asCents()+d).asDollars());
			sum[lt]+=d;
	}}
	// Disable the "add entry" button for sets with incomplete rows
	$('button.add-entry-button').each(function(x){$(this).prop('disabled',adjrow[x]!=null)});
	$('#creditcount').html(count[0]);
	$('#debitcount').html(count[1]);
	$('#creditsum').val(sum[0].asDollars());
	$('#debitsum').val(sum[1].asDollars());
	var complete = sum[0]>0&&sum[1]>0&&!adjrow[0]&&!adjrow[1];
	$('button#trigger-store').prop('disabled',!complete);
	return complete;
}
function addEntry(type,focus) { // returns the jquery of the new entry row
	var newrow=$('#templaterow').clone(true); // clone the templateEntry
	$(':input:not("button")', newrow
			.attr('id','acctentry_'+a_trx.entries) // change its id so it can't be selected as the template again and so we can use it
			.appendTo('#set'+type+' tbody') 	// append to the appropriate table
		) // here we have all input and select elements within the new row
		.attr('name',function(x,val){
			return 'entries['+a_trx.entries+']['+val+']'} // prefix and bracket its name to participate in the entry list as the next element
		);
	a_trx.entries++;
	$('input.fn-type',newrow).attr('value',type);
	if(focus)$('select.fn-account',newrow).focus();
	return newrow;
}
function deleteEntry(row) {
	if ($('input.fn-disposition',row).val()!='new') { // when the entry is stored, we mark it deleted but keep it visible in place
		$('input.fn-disposition',row).val('delete');
		$(':input:not(button)',row).prop('disabled',true);
		$('button.delentry#delete',row).hide();
		$('button.delentry#restore',row).show();
		$(row).addClass('deleted');
	} else
		row.remove();
	validate_trx();
	return row;
}
function restoreEntry(row) {
	$('input.fn-disposition',row).val('change');
	$(':input',row).prop('disabled',false);
	$(row).removeClass('deleted');
	$('button.delentry#delete',row).show();
	$('button.delentry#restore',row).hide();
	validate_trx();
	return row;
}
jscript;

	const styles = <<<'css'
table.entries {
	margin:0 auto 1em;
	width:100%;
	border:1px solid black;
	border-radius:5px;
}
#creditsum { background-color:transparent; }
#debitsum { background-color:transparent; }
.memo { width:35%; }
.amount { width:25%; }
.account { width:30%; }
.triggers { width:5%; }
table.entries th, table.entries td { padding:2pt; }
tr.acctentry.deleted { background-color:darkgray; }
tr.acctentry.deleted input, tr.acctentry.deleted select { text-decoration:line-through; }
tr.acctentry.incomplete { background-color:yellow; }
table.entries th, table.entries td {
	white-space:nowrap;
	vertical-align:middle;
	text-align:center;
}
css;

}
