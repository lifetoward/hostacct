<?php

class ReceiptTrx extends Trx
{
	public function render_me( TrxRendering $R )
	{
	// Input processing

		list($args, $post) = $c->request;
		if ($args['*exit'])
			return $this->getResult(static::CANCEL);

	// Rendering

		$c->addStyles(self::styles, 'Transaction form');
		$c->addScript(self::scripting, 'Transaction form');


		$R->content = $content;
		return $R;
	}

function input_field( $field, $value )
{
	return '<table class="editfield">'.
		'<tr><td class="editprompt" colspan="2">'. $field['*label'] .'</td></tr>'. ($value ?
		'<tr><td class="editvalue">'. $value .'</td><td class="edithelp help">'. $field['help'] ."</td></tr></table>\n" : null);
}
function receipt_form( &$frame )
{
	$focus = $_SESSION['fqn*'][$frame['arg*']['focus']];
	capcel_load_lib('data');
	if ($frame['post*']) {
		capcel_create_element($focus['*fqn'], $frame['post*']);
		return null;
	}
	capcel_include_stylesheet('common', 'base');
	capcel_include_stylesheet('edit', 'base');
	capcel_include_jscript("helpers", '.');
	capcel_include_jscript("input", 'accounting');
	$fc = array('*fqn'=>'receipt:input', 'input'=>true, 'class'=>'create', 'render'=>'input_field', 'dateformat'=>'d M Y');
	$backbutton = capcel_exit_action();
	$mainfields = capcel_render_fields($fc, $focus, array('uniqid'=>uniqid(), 'trxdate'=>date('Y-m-d')));
	$uniqid = '<input type="hidden" name="uniqid" value="'. uniqid() .'"/>';
	$posttarget = capcel_expect_trigger(array('*post'=>1));
	$acctoptions = capcel_element_options('accounting:element:account', null, "class in ('expense','income','current','asset')");
	global $tailscript, $headscript;
	$headscript .= <<<end
var okflags={'rows':false,'received':false};
function capcel_validated(c,n,ok)
{
	if(n=='received'&&(c.value*1)<1)
		ok=false;
	else if(n=='received'||n=='amount'||n=='account'){
		okflags['rows']=true;
		var context=document.getElementById('credbody');
		var remaining=document.getElementById('received').value*1;
		for(x=0; x<context.rows.length; x++){
			var cells=context.rows[x].cells;
			if(!remaining){
				if(!x)break;
				context.removeChild(context.rows[x--]);
				continue;
			}
			if(cells[0].firstChild.value*1<1)
				okflags['rows']=false;
			var rowamt=cells[1].lastChild.value*1;
			if(rowamt>=remaining||rowamt==0){
				rowamt=cells[1].lastChild.value=remaining;
				cells[1].lastChild.previousSibling.value=cents_to_dollars(remaining);
			}
			remaining-=rowamt;
		}
		if(remaining){
			newrow=context.rows[0].cloneNode(true);
			newrow.cells[1].lastChild.value=remaining;
			newrow.cells[1].lastChild.previousSibling.value=cents_to_dollars(remaining);
			newrow.cells[0].firstChild.value='';
			context.appendChild(newrow);
			okflags['rows']=false;
		}
	}
	if(n)okflags[n]=ok;
	for(n in okflags)if(!(ok=ok&&okflags[n]))break;
	document.getElementById('submitbutton').disabled=!ok;
}
end;
	if ($focus['*fqn'] == 'finance:element:receipt') {
		$creditrows = <<<end
<table><tbody id="credbody"><tr id="initialrow" class="entry">
	<td class="account"><select class="account" size="1" name="account[]" onchange="capcel_validated(this,'account',value*1)">
		<option value=""> -- Select -- </option>$acctoptions</select></td>
	<td> &nbsp;$<input class="alignright" type="text" size="10" value="0.00" onchange="accounting_validate_dollars(this,'amount',1);"
		/><input type="hidden" name="amount[]" value="0"/></td>
</tr></tbody></table>
end;
		$creditacctfields = input_field(array('*label'=>'Account(s) to credit',
				'help'=>'Specify here which account(s) in the ledger should be credited with these funds. Additional accounts will be added as long as they total less than the amount received.'),
			$creditrows);
	}
	return <<<end
<span id="banneractions">$backbutton</span>
<h1>Record a Receipt</h1>
<form enctype="multipart/form-data" method="post" action="$posttarget" name="inputform" class="input">
$uniqid<div class="editform">
$mainfields
$creditacctfields
</div>
<center><button id="submitbutton" type="submit" disabled="yup"> OK </button></center>
</form>
<script>
$tailscript
</script>
end;
}

}
