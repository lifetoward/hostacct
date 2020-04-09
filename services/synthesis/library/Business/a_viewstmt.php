<?php

class a_ViewStmt extends Action
{
	public function render_me( $notice = null )
	{
	// Input processing

		list($args, $post) = $c->request;
		if ($args['*exit'])
			return self::CANCEL;

	// Rendering

		$c->addStyles(self::styles, 'Transaction form');
		$c->addScript(self::scripting, 'Transaction form');

		return $this->getResult(static::PROCEED);
	}


function view_statement( &$frame )
{
	capcel_load_lib('data');
	$stmt = capcel_focus_data('biz:element:statement', $frame['arg*']);
	return capcel_exit_action() .'<iframe style="margin:0;width:100%;height:11in" src="'. $stmt['url'] .
		'">Your browser does not support frames so you can\'t view the statement in this window. '.
		'Click this link to launch the statement in a new window: <a target="_blank" href="'. $stmt['url'] .'">'.
		$stmt['url'] .'</a></iframe>'."\n";
}

function render_statement( $id )
{
	capcel_load_lib('data');
	$stmt = capcel_focus_data('biz:element:statement', array('id'=>$id));
	$endstruct = getdate(strtotime($stmt['month']));
	$shortmonth = mb_substr($endstruct['month'], 0, 3);
	$prep = getdate(strtotime($stmt['prepared']));
	$result = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Style-Type" content="text/css"/>
<style>
	body { font-family:sanserif; font-size:12pt; padding:6pt; width:auto; }
	@media print { body { font-size:8pt; width:7in;} }
	@page { margin:0.5in }
	h1,h2,h3 { margin:0; padding:0; }
	hr { width:auto; }
	.smallprint { font-style:italic; font-size:smaller; white-space:wrap; }
	div.table { width:auto; margin:0; padding:0.5em 2em;  }
	div.header { width:auto; margin-bottom:2em; padding:0;  }
	table { width:100%; table-layout:auto; }
	th,td { padding:0.3em; vertical-align:top; white-space:nowrap; }
	th { background-color:#DDDDDD; font-size:smaller; vertical-align:bottom;}
	.header td { padding:0; white-space:nowrap; }
	#summary td { text-align:center; }
	td.l { text-align:left; border-top:0.1em solid black; }
	td.c { text-align:center; border-top:0.1em solid black; }
	td.r { text-align:right; border-top:0.1em solid black; }
	div#payments { width:60%; margin:0.5em auto; }
	span.qty {  }
	span.unit { font-size:smaller; color:gray }
	#thanks { font-style:italic;margin:auto;font-weight:bold;color:darkgreen;text-align:center; }
	.wrapok { white-space:normal; }
</style>
</head>
<body>
<div class="header"><table>
<tr><td>'. capcel_get_parm('Statement Logo Block') .'</td>
	<td><span class="smallprint">Please direct payments and billing inquiries to:</span>
		<br/>Phone: '. capcel_get_parm('Statement Phone Number') .'
		<br/>Email: <a href="mailto:'. ($email = capcel_get_parm('Statement Email Address')) .'">'. $email .'</a></td>
	<td>'. capcel_get_parm('Statement Address') .'</td></tr>
</table></div>
<div class="header"><table>
<tr><td><h3>Your <u>'. "${endstruct['month']} ". mb_substr($stmt['month'],0,4) .'</u> statement</h3></td>
	<td colspan="2" style="text-align:right">Prepared: '. "${prep['month']} ${prep['mday']}, ${prep['year']}" .'</td></tr>
</table></div>
<hr/><h3>Summary</h3>
<div class="table"><table id="summary">
<tr><td>Previous balance<br/>$ '. sprintf("%0.2f", $stmt['prevbal'] / 100) .'</td>
	<td>Payments received<br/>$ '. sprintf("%0.2f", $stmt['receipts'] / 100) .'</td>
	<td>New charges<br/>$ '. sprintf("%0.2f", $stmt['charges'] / 100) .'</td>
	<td style="border:1px solid black">'. ($stmt['due']>0 ? 'Payment due' : 'Account balance') .
		'<br/><b>$ '. sprintf("%0.2f", $stmt['due'] / 100) .'</b></td>
	<td>'. ($stmt['due']>0 ? 'Payment due date<br/>( due now )' : 'No payment is<br/>due at this time') .'</td>
</tr></table></div>
<hr/><h3>Current charges</h3>
<div class="table"><table id="charges">
<thead><tr><th class="c">Date</th>
	<th class="l">Description</th>
	<th class="c">Quantity<br/><span class="unit">price per unit</span></th>
	<th class="r">Amount</th></tr></thead>
<tbody>';
	$unitopts = $_SESSION['fqn*']['biz:element:billable']['field*']['unit']['option*'];
	if (count($chgs = capcel_focus_data('biz:element:charge', null,
			"client=${stmt['client']} AND trxdate>='". mb_substr($stmt['month'],0,8) ."01' AND trxdate<='${stmt['month']}'",
			'trxdate', true)))
		foreach ($chgs as $chg) {
			$rate = sprintf('%0.2f', ($rawrate = $chg['itemRate']) / 100);
			$qty = $chg['quantity'];
			$result .= '<tr><td class="c">'. $shortmonth .' '. (0+mb_substr($chg['trxdate'],8)) .'</td>'.
				'<td class="l"><b>'. $chg['itemName'] .'</b><br/><span class="wrapok">'. $chg['details'] .'</span></td>'.
				'<td class="c"><span class="qty">';
			eval('$result .= "'. $qty .'";');
			$result .= '</span><br/><span class="unit">';
			eval('$result .= "'. (($runit = e_billable::$stmtunits[$chg['item_unit']]) ? $runit : "$rate ".$chg['item_unit']) .'";');
			$result .= '</span></td><td class="r">$ '. sprintf("%0.2f", $chg['total']/100) .'</td></tr>';
		}
	$result .= '</tbody>
<tfoot><tr><td colspan="3" class="r">Total charges:</td><td class="r">$ <b>'.
		sprintf("%0.2f", $stmt['charges'] / 100) .'</b></td></tr></tfoot>
</table></div>
<hr/><h3>Payments received</h3>
<div id="payments" class="table"><table>
<thead><tr>
	<th class="l">Received</th>
	<th class="l">Identified as</th>
	<th class="r">Amount</th>
	</tr></thead>
<tbody>';
	if (count($rcpts = capcel_focus_data('biz:element:duercpt', null,
			"client=${stmt['client']} AND trxdate>='". mb_substr($stmt['month'],0,8) ."01' AND trxdate<='${stmt['month']}'",
			'trxdate', true)))
		foreach ($rcpts as $rcpt)
			$result .= '<tr><td class="l">'. $shortmonth .' '. (0+mb_substr($rcpt['trxdate'],8)) .'</td>'.
				'<td class="l">'. ($rcpt['docid'] ? $rcpt['docid'] : '(not recorded)') .'</td>'.
				'<td class="r">$ '. sprintf('%0.2f', $rcpt['received']/100) .'</td></tr>';
	$result .= '</tbody>
<tfoot><tr><td colspan="2" class="r">Total payments:</td><td class="r">$ <b>'.
		sprintf("%0.2f", $stmt['receipts'] / 100) .'</b></td></tr></tfoot>
</table></div>'. ($stmt['receipts'] > 0 ? '<div id="thanks">Thank you for your payment!</div>' : null) .'
<hr/><h3>Account information</h3>
'. ($stmt['message'] ? '<p><b>Special message:</b> '. $stmt['message'] .'</p>' : '<!-- no message -->') .'
<div class="table"><table id="information">
<tr><td><u>Account identifier</u><br/>'. $stmt['client_handle'] .'</td>
	<td><u>Mailing address</u><br/>'. $stmt->»client .'<br/>'. $stmt['client_address'] .'</td>
	<td><u>Contact information</u><br/>'. $stmt['client_contact'] .'<br/>Phone: '. $stmt['client_phone'] .
		'<br/>Email: <a href="'. $stmt['client_email'] .'">'. $stmt['client_email'] .'</a></td></tr>
</table></div>
'. (($closer = capcel_get_parm('Statement Closing Comments')) ? "<p>$closer</p>" : "<!-- no closing comments -->") .
"<p>This statement can be found online at <a target=\"_blank\" href=\"${stmt['url']}\">${stmt['url']}</a>.</p>";
	return "$result\n<hr/></body></html>\n";
}


}

?>
