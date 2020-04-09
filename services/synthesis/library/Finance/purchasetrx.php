<?php
/**
* A purchase credits a funding account (or few) and does any number of these:
*	- debits a security or expense account - or
*	- creates and debits an fixed asset account (which must then be capitalized under a separate operation)
*
* All original code.
* @package Synthesis/Finance
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
class PurchaseTrx extends Trx
{
	public static $properties = array('name'=>"actg_purchase", 'singular'=>"Purchase", 'plural'=>"Purchases", 'descriptive'=>"Purchase details");

	public static $fielddefs = array(
		 'trx'=>array('name'=>"trx", 'type'=>"include", 'class'=>'t_trx', 'sort'=>"*yes", 'renderonly'=>"trxdate")
		,'vendor'=>array('name'=>"vendor", 'type'=>"refer", 'class'=>'t_vendor', 'label'=>"Vendor", 'help'=>"The store, restaurant, person, or other institution which sold you the items.")
		,'salestaxrate'=>array('name'=>"salestaxrate", 'class'=>"t_percent", 'label'=>"Sales tax rate", 'initial'=>"8.25",
			'help'=><<<html
Specify in %. This rate and the \"tax\" flags for each line item are only helpers for correctly allocating sales tax across separate line items.
Typically receipts show the before tax amount for each line, and only a total sales tax for the entire purchase. By specifying the rate and entering pre-tax costs per line item, the form can do the rest.
html
			)
		);

	public function formatted()
	{
		return "$this->trx";
	}

function prep_purchase( $focus, $id, &$data )
{
	$data['notes'] = "PURCHASE from $data[vendor_txt]: $data[purpose]";
	foreach($data['memo'] as $x=>$memo)
		if ($data['type'][$x] == 'credit') {
			$data['memo'][$x] .= " @ $data[vendor_txt]";
			$data['_creditcount']++;
		} else {
			$data['_debitcount']++;
			if (!$memo)
				$data['memo'][$x] = $data['purpose'];
		}
}

}

?>
<?php

class a_Purchase extends Action
{
	public function render_me( $notice = null )
	{
	// Input processing

		list($args, $post) = $c->request;
		if ($args['*exit'])
			return $this->getResult(static::CANCEL);

	// Rendering

		$c->add_styles(self::styles, 'Transaction form');
		$c->add_script(self::scripting, 'Transaction form');

		return $this->getResult(static::PROCEED);
	}

function input_field( $field, $value )
{
	return '<table class="editfield">'.
		'<tr><td class="editprompt" colspan="2">'. $field['*label'] .'</td></tr>'. ($value ?
		'<tr><td class="editvalue">'. $value .'</td><td class="edithelp help">'. $field['help'] ."</td></tr></table>\n" : null);
}

	function purchase_form( &$frame )
	{
		$focus = $_SESSION['fqn*']['finance:element:purchase'];
		capcel_load_lib('data');
		if ($frame['post*']) {
			capcel_create_element($focus['*fqn'], $frame['post*']);
			return null;
		}
		global $tailscript, $headscript;
		$fc = array('*fqn'=>'purchase:input', 'input'=>true, 'class'=>'create', 'dateformat'=>'d M Y');
		$backbutton = capcel_exit_action();
		$initialdata = array('trxdate'=>date('Y-m-d'));
		foreach (capcel_get_fields($fc, $focus) as $field)
			$rendered[$field['name']] = '<td class="editvalue">'. capcel_render_field($fc, $focus, $initialdata, $field) .'</td>';
		$uniqid = '<input type="hidden" name="uniqid" value="'. uniqid() .'"/>';
		$posttarget = capcel_expect_trigger(array('*post'=>1));
		$credacctopts = capcel_element_options('accounting:element:account', null, "FIND_IN_SET('purchase',flags)>0");
		$debacctopts = capcel_element_options('accounting:element:account', null, "class IN('expense','fixed')");
		capcel_include_stylesheet('common', 'base');
		capcel_include_stylesheet('edit', 'base');
		capcel_include_stylesheet('purchase','finance');
		capcel_include_jscript("helpers", '.');
		capcel_include_jscript("input", 'accounting');
		$headscript .= <<<end
var okflags={'creds':false,'debs':false};
var credits,debits,expenses,total,postbutton=0;
function addcreditentry()
{
	for(x=0;x<credits.rows.length;x++)
		if(credits.rows[x].cells[1].lastChild.value*1==0)
			return;
	var newrow=credits.rows[0].cloneNode(true);
	newrow.cells[0].lastChild.value='';
	newrow.cells[1].lastChild.value=0;
	newrow.cells[1].lastChild.previousSibling.value='0.00';
	newrow.cells[2].firstChild.value='(method memo)';
	credits.appendChild(newrow);
	compute_credits();
	newrow.cells[0].lastChild.focus();
}
function compute_debits(taxrate)
{
	// This form hacks all debit postable values to contain the post-tax value despite what the input field reads!
	// In other words the type validator is overridden for all "add tax" cases.
	var remains=total,debtotal=0;
	okflags['debs']=true;
	for(var x=0;x<debits.rows.length;x++){
		var cells=debits.rows[x].cells;
		if(!remains){
			if(!x)break;
			debits.removeChild(debits.rows[x--]);
			continue;
		}
		if(cells[0].firstChild.value*1<1)
			okflags['debs']=false;
		var rowamt=cells[1].lastChild.value*1;
		if(rowamt>=remains||rowamt==0||!cells[0].lastChild.value)
			cells[1].lastChild.value=rowamt=remains;
		cells[1].lastChild.previousSibling.value=cents_to_dollars(1*(cells[3].firstChild.checked?(rowamt/(1+taxrate/100)).toFixed(0):rowamt));
		remains-=rowamt;
		debtotal+=rowamt;
	}
	if(remains){
		var nr=debits.rows[0].cloneNode(true);
		nr.cells[0].firstChild.value='';
		nr.cells[1].lastChild.value=remains;
		nr.cells[1].lastChild.previousSibling.value=cents_to_dollars(1*(cells[3].firstChild.checked?(remains/(1+taxrate/100)).toFixed(0):remains));
		nr.cells[2].firstChild.value='';
		nr.cells[3].firstChild.checked=false;
		debits.appendChild(nr);
		debtotal+=remains;
		okflags['debs']=false;
	}
}
function compute_credits()
{
	total=0;okflags['creds']=true;
	for(var x=0;x<credits.rows.length;x++){
		var cells=credits.rows[x].cells;
		if(cells[0].lastChild.value==''||cells[1].lastChild.value*1==0)
			okflags['creds']=false;
		total+=cells[1].lastChild.value*1;
	}
	expenses.display=okflags['creds']?'block':'none';
	document.getElementById('creditadder').style.display=okflags['creds']?'table-row':'none';
}
function capcel_validated(c,n,ok)
{
	var taxrate=document.getElementById('salestaxrate').value*1;
	switch(n){
		case 'purpose':
		case 'trxdate':
		case 'vendor':
		case 'memo':
			return;
		case 'credamt':
			if(!c.value*1&&credits.rows.length>1)
				credits.removeChild(c.parentNode.parentNode);
			compute_credits();
			compute_debits(taxrate);
			break;
		case 'debitamt':
			if(c.parentNode.parentNode.cells[3].firstChild.checked)
				c.value=(c.value*(1+taxrate/100)).toFixed(0);
			compute_debits(taxrate);
			break;
		case 'taxflag':
			if(!c.checked)
				return accounting_validate_dollars(c.parentNode.parentNode.cells[1].lastChild.previousSibling,'debitamt',0);
			var x=c.parentNode.parentNode.cells[1].lastChild;
			x.value=(x.value*(1+taxrate/100)).toFixed(0);
		case 'debitacct':
		case 'salestaxrate':
			compute_debits(taxrate);
			break;
		case 'credacct':
			compute_credits();
			break;
	}
	if(n)okflags[n]=ok;
	for(n in okflags)if(!(ok=ok&&okflags[n]))break;
	postbutton.disabled=!ok;postbutton.style.display=ok?'inline':'none';
}
end;
		return <<<end
<span id="banneractions">$backbutton</span>
<h1>Record a Purchase</h1>
<form enctype="multipart/form-data" method="post" action="$posttarget" name="inputform" class="input">
$uniqid<div class="editform">
<table class="editfield">
	<tr><td class="editprompt">Date</td><td class="editprompt">Vendor</td></tr>
		<tr>$rendered[trxdate]$rendered[vendor]</tr>
	<tr><td>&nbsp;</td><td class="editprompt">Purpose and description</td></tr>
	<tr><td>&nbsp;</td>$rendered[purpose]</tr>
</table><table class="editfield">
	<tr><td class="editprompt">Method(s) and amount(s) paid</td></tr>
	<tr><td class="editprompt">
<table width="90%" style="padding:0 1em;margin:0 auto"><thead><tr><th>Purchased with</th><th>Amount</th><th>Memo</th></tr></thead>
	<tbody id="credbody"><tr class="entry">
	<td class="account"><select class="account" size=1 name="account[]" onchange="capcel_validated(this,'credacct',value*1)">
		<option value=""> -- Select -- </option>$credacctopts</select></td>
	<td> &nbsp;$<input class="alignright" type="text" size="10" value="0.00" onchange="accounting_validate_dollars(this,'credamt',1);"
		/><input type="hidden" name="amount[]" value="0"/></td>
	<td><input type="text" size="16" name="memo[]"/>
		<input type="hidden" name="perform[]" value="create"/><input type="hidden" name="type[]" value="credit"/></td>
</tr></tbody><tfoot><tr id="creditadder" style="display:none"><td><img src="images/new.png" onclick="addcreditentry()"/></td></tr></tfoot></table>
	</td></tr>
</table><table class="editfield" id="expenses" style="display:none">
	<tr><td class="editprompt">Expense categorization</td></tr>
	<tr><td class="editprompt">
<table width="95%" style="padding:0 1em;margin:0 auto"><thead><tr><th>Expense account</th><th>Amount spent</th><th>Description</th><th>+Tax</th></tr></thead>
	<tbody id="debbody"><tr class="entry">
	<td class="account"><select class="account" size=1 name="account[]" onchange="capcel_validated(this,'debitacct',value*1)">
		<option value=""> -- Select -- </option>$debacctopts</select></td>
	<td> &nbsp;$<input class="alignright" type="text" size="10" value="0.00" onchange="accounting_validate_dollars(this,'debitamt',1);"
		/><input type="hidden" name="amount[]" value="0"/></td>
	<td><input type="text" size="20" value="" name="memo[]"/></td>
	<td style="text-align:center"><input type="checkbox" onchange="capcel_validated(this,'taxflag',1)"/>
		<input type="hidden" name="perform[]" value="create"/><input type="hidden" name="type[]" value="debit"/></td>
	</tr></tbody>
	<tfoot><tr><td>&nbsp;</td><td align="right">Sales tax rate (%):</td>$rendered[salestaxrate]<td>&nbsp;</td></tfoot>
</table>
	</td></tr>
</table>
<center><button id="submitbutton" type="submit" style="display:none" disabled="yup">
	Record this purchase <img src="images/check.png"/> </button></center>
</div>
</form>
<script>
credits=document.getElementById('credbody');
debits=document.getElementById('debbody');
postbutton=document.getElementById('submitbutton');
expenses=document.getElementById('expenses').style;
document.getElementById('trxdate_').focus();
$tailscript
</script>
end;
	}

}
