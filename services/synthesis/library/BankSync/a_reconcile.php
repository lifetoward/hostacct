<?php

class a_Reconcile extends Action
{
	public function render_me( $notice = null )
	{

	// Input processing

		list($args, $post) = $c->request;
		if ($args['*exit'])
			return $this->getResult(static::CANCEL);

	// Rendering

		$c->addStyles(self::styles, 'Transaction form');
		$c->addScript(self::scripting, 'Transaction form');

		return $this->getResult(static::PROCEED);
	}
}

function reconcile_action( &$frame )
{
	if ($frame['*hint'] == 'initial' || $frame['*hint'] == 'return') {
		$frame['account'] = capcel_element_data('accounting:element:account', $frame['arg*']['id']);
	}
	$account = $frame['account'];
	if ($account['bankid'] && $account['acctid'] && !$frame['extstmt']) { // when rendering this page, always check to see if we have some statement data we can use.
		$stmtrecord = "extacctstmt WHERE bankid = '$account[bankid]' AND acctid = '$account[acctid]' AND status = 'open' ORDER BY dtstart ASC LIMIT 1";
		if ($account['cleardate'] == capcel_query_scalar("SELECT DATE_SUB(dtstart, INTERVAL 1 DAY) FROM $stmtrecord")) // ensure the clear date is one before the statement date to validate it
			$frame['extstmt'] = array_shift(capcel_get_records("SELECT * FROM $stmtrecord", 'Fetch the oldest open statement for this account, if any.'));
	}
	return $frame['extstmt'] ? reconcile_imported($frame) : reconcile_manual($frame);
}

function reconcile_imported( &$frame )
{
	// We are here because there exists an unreconciled externally downloaded statement to reconcile against our paired local account
	$extstmt =& $frame['extstmt'];

	// First check to see if there are any unreconciled entries in the statement. If there aren't, the goal is to get the statement put to bed by marking it closed at a certain balance and date.
	if (!$extstmt['entries']) { // First look at the external statement's contents
		$extstmt['entries']['open'] = capcel_get_records("SELECT * FROM extstmtent WHERE extacctstmt = $extstmt[_id] AND entry IS NULL ORDER BY dtposted ASC, _id ASC");
		$extstmt['entries']['assoc'] = capcel_get_records("SELECT * FROM extstmtent WHERE extacctstmt = $extstmt[_id] AND entry > 0 ORDER BY dtposted ASC, _id ASC");
	}

	if (($subaction = $frame['arg*']['subaction']) && ($entry = $frame['arg*']['entry'])) {
		switch ($subaction) {
			case 'syncmark':
				// TO-DO: update the transaction date to match the external entry.
				// fall thru
			case 'markentry':
				$extentry = array_shift($extstmt['entries']['open']);
				capcel_begin_transaction("mark entry vs extstmt");
				capcel_query("UPDATE extstmtent SET entry = $entry WHERE _id = $extentry[_id]");
				capcel_query("UPDATE acctentry SET reconcile = 'mark' WHERE _id = $entry");
				$extentry['entry'] = $entry;
				$extstmt['entries']['assoc'][] = $extentry;
				capcel_commit_transaction();
				unset($frame['arg*']['subaction']);
				break;
			case 'editastype':
				break;
		}
		// fall thru to whatever's next
	}

	$assoccount = count($extassoc =& $extstmt['entries']['assoc']);
	if (!($opencount = count($extopen =& $extstmt['entries']['open']))) {
		if ($subaction == 'reconciled') {
			capcel_begin_transaction("Reconcile account ". $frame['account']['_id'] ." vs statement data");
			capcel_query("UPDATE acctentry SET reconcile = 'close' WHERE _id IN ((SELECT entry FROM extstmtent WHERE extacctstmt = $extstmt[_id]))");
			capcel_query("UPDATE acctentry SET reconcile = 'open' WHERE account = $extstmt[account] AND reconcile = 'mark'");
			capcel_query("UPDATE extacctstmt SET status = 'reconciled' WHERE _id = $extstmt[_id]");
			capcel_query("UPDATE account SET cleared = $frame[endbal], cleardate = '$extstmt[dtend]' WHERE _id = $extstmt[account]");
			capcel_commit_transaction();
			return null;
		}
		$debits = array_pop(capcel_get_records(
			"SELECT COUNT(*) AS count, SUM(amount) AS total FROM extstmtent JOIN acctentry ON entry=acctentry._id WHERE extacctstmt=$extstmt[_id] AND type='debit'"));
		$credits = array_pop(capcel_get_records(
			"SELECT COUNT(*) AS count, SUM(amount) AS total FROM extstmtent JOIN acctentry ON entry=acctentry._id WHERE extacctstmt=$extstmt[_id] AND type='credit'"));
		$frame['startbal'] = capcel_query_scalar("SELECT cleared FROM account WHERE _id = $extstmt[account]");
		$frame['endbal'] = $frame['startbal'] + ($frame['debittotal'] = $debits['total']) - ($frame['credittotal'] = $credits['total']);
		$frame['class'] = $frame['account']['class'];
		$fc = array('columnar'=>true);
		capcel_load_ext('accounting/render');
		capcel_load_ext('base/types');
		$rendered = array(
			 'startbal' => render_type_balance($fc, array('name'=>'startbal'), $frame)
			,'startdate' => base_render_date($extstmt['dtstart'], "D m 'y")
			,'credittotal' => render_type_balance($fc, array('name'=>'credittotal'), $frame)
			,'debittotal' => render_type_balance($fc, array('name'=>'debittotal'), $frame)
			,'endbal' => render_type_balance($fc, array('name'=>'endbal'), $frame)
			,'enddate' => base_render_date($extstmt['dtend'], "D m 'y")
			,'acctname' => $frame['account']->_rendered
			);
		if ($extstmt['dtasof'] == $extstmt['dtend']) {
			if ($extstmt['balamt'] == $frame['endbal'])
				$rendered['balancenote'] = "<p>The computed ending balance is <b>CONFIRMED</b> according to the statement data.</p>";
			else
				$rendered['balancenote'] = "<p>The balance provided in the statement does NOT match the balance we arrived at here. You should NOT reconcile this statement until this discrepancy is resolved.</p>";
		} else
			$rendered['balancenote'] = "<p>The statement does not include a balance matching the end date of the statement. This means it is up to you to confirm that the ending balance is accurate as of the end date of the statement according to the external account holder. Typically this means you'll need to check against a paper statement for this period received from them.</p>";

		$frame['unassocmarked'] = capcel_get_records("SELECT * FROM acctentry WHERE reconcile = 'mark' AND account = $extstmt[account] AND _id NOT IN ".
			"((SELECT entry FROM extstmtent WHERE extacctstmt=$extstmt[_id]))");
		if ($unassoc = count($frame['unassocmarked']))
			$rendered['unassocnote'] = "<p>There are $unassoc local entries which are marked for reconciliation but which are not associated with this statement. If you choose to proceed, these entries will be reclassified as 'open' or unmarked.</p>";
		$reloadbutton = '<button onclick="location.replace(\''. capcel_expect_trigger(array()) .'\')">Reload</button>';
		$reconcilebutton = '<button onclick="location.replace(\''. capcel_expect_trigger(array('subaction'=>'reconciled')) .'\')"><img src="images/check.png"/> Reconciled!</button>';
		$returnbutton = capcel_exit_action();
return <<< end
Reconciliation Summary for
<h2>$rendered[acctname]</h2>
<table>
<tr><td>Starting balance</td><td>$rendered[startbal]</td><td>$rendered[startdate]</td></tr>
<tr><td>Total of $credits[count] credits</td><td>$rendered[credittotal]</td></tr>
<tr><td>Total of $debits[count] debits</td><td>$rendered[debittotal]</td></tr>
<tr><td>Ending balance</td><td>$rendered[endbal]</td><td>$rendered[enddate]</td></tr>
</table>
$rendered[balancenote]$rendered[unassocnote]
<p>$reloadbutton $reconcilebutton $returnbutton</p>
end;
		// Optional: List all marked entries which are associated with this statement's ext entries showing the balance changes over time.
	}

	// So, we have unreconciled entries in the external statement.
	capcel_load_ext('base/types');
	capcel_include_stylesheet('common','base');
	capcel_include_stylesheet('browse','base');
	capcel_include_stylesheet('common', 'accounting');
	capcel_include_stylesheet('reconcile', 'finance');

	// First render the goal external entry which we intend to meet with a local account entry
	$curopen =& $extopen[0];
	$fc = array('*fqn'=>'finance:reconcile:entryrow', 'columnar'=>1, 'class'=>'concise');
	$extentdef = $_SESSION['fqn*']['finance:element:extstmtent'];
	$colstyles = array('date','type','amount','memo','actions');
	foreach (array('dtposted','trntype','trnamt','memo') as $x => $col)
		$focusrow .= '<td class="'. $colstyles[$x] .'">'. capcel_render_field($fc, $extentdef, $curopen, $col) ."</td>";
	$rendered['curopen'] = "<tr class=\"extentry\">$focusrow<td>&nbsp;</td></tr>";

	// Now find candidate local entries to associate. There are various forms of candidacy each of which can be acted on in an appropriate way.
	$account =& $frame['account'];
	$acctent = $_SESSION['fqn*']['accounting:element:acctentry'];
	$s = <<<end
SELECT acctentry.*,trxdate,notes,state
	,IF(`type`='debit',NULL,amount) AS `credit`
	,IF(`type`='credit',NULL,amount) AS `debit`
FROM acctentry
	LEFT JOIN trx ON trx=trx._id
WHERE acctentry.account = $account[_id] AND reconcile = 'open'
end;
	$s2 = "ORDER BY trxdate ASC";

	$groups['probable'] = array('label' => 'Highly Probable Matches'
		,'description' => 'Candidate by Matching Type, Date, and Amount (high probability)'
		,'entries' => capcel_get_records("$s AND type = '$curopen[localtype]' AND amount = $curopen[trnamt] AND trxdate = '$curopen[dtposted]' $s2")
		,'matching' => array('date'=>true,'amount'=>true,'type'=>true)
		,'action' => array('label'=>'<img title="reconcile as-is" src="images/check.png"/> Reconcile', 'args'=>array('subaction'=>'markentry'), 'dynarg'=>'entry', 'dynfield'=>'_id')
		);
	$groups['possible'] = array('label' => 'Possible matches (date miss)'
		,'description'=>'Candidate by Matching Type and Amount and having an Older Date'
		,'entries'=>capcel_get_records("$s AND type = '$curopen[localtype]' AND amount = $curopen[trnamt] AND trxdate < '$curopen[dtposted]' $s2")
		,'matching' => array('amount'=>true,'type'=>true)
		,'action' => array('label'=>'Sync Date &amp; Reconcile', 'args'=>array('subaction'=>'syncmark'), 'dynarg'=>'entry', 'dynfield'=>'_id')
		);
	$groups['other'] = array('label'=>'Local Transactions from Around that Time'
		,'description'=>'A few entries which are from around the same time as the statement entry. An incorrect entry may be found in this way.'
		,'entries'=>capcel_get_records("$s AND trxdate < DATE_ADD('$curopen[dtposted]', INTERVAL 7 DAY) $s2")
		,'action'=>array('label'=>'Edit Transaction', 'args'=>array('action'=>'accounting:action:trxuse', 'focus'=>'accounting:element:trx'), 'dynarg'=>'id', 'dynfield'=>'trx')
		);

	// Render these grouped candidate entries along with their actions
	foreach ($groups as $group => $info) {
		if (count($info['entries'])) {
			$accum = null;
			foreach ($info['entries'] as $candidate)
				$accum .= render_entry_row($group, $colstyles, $candidate, $info['action'], $info['matching']);
			$rendered['candidates'] .= '<tr><td colspan="5" class="grouplabel">'. $info['label'] .":</td></tr>\n".
				"<tbody>\n$accum\n</tbody>\n<tr><td colspan=\"5\">&nbsp;</td></tr>\n";
		} else
			$rendered['candidates'] .= "<tr><td colspan=\"5\" class=\"grouplabel\">$info[label]: None found</td></tr><tr><td colspan=\"5\">&nbsp;</td></tr>\n";
	}

	// Render the page
	$acctname = htmlentities($account['name']);
	$cancel = capcel_exit_action();
	$dtstart = base_render_date($extstmt['dtstart'], 'D M Y');
	$dtend = base_render_date($extstmt['dtend'], 'D M Y');
	$ac = array('*fqn'=>'finance:reconcile:create', 'arg*'=>array('focus'=>'accounting:element:trx','extentry'=>$curopen['_id']));
	$picktemplate = capcel_trigger('finance:action:trxmodel', $ac);
	$fqn = $_SESSION['fqn*'];
	$addtrx = '<select size="1" onchange="location.replace(value)" name="purpose"><option>Add new...</option>'.
		'<option value="'. _capcel_trigger_args($fqn['finance:action:purchase'], null) .'">Purchase</option><option value="'. _capcel_trigger_args($fqn['finance:action:receive'], null) .'">Receipt</option>'.
		'<option value="'. _capcel_trigger_args($fqn['finance:action:deposit'], null) .'">Deposit</option><option value="'. _capcel_trigger_args($fqn['accounting:action:trxnew'], null) .'">Transaction</option>'.
		'</select>';
	$allcount = $opencount + $assoccount;
	return <<< end
<span id="banneractions">$cancel</span>
<h1>Reconcile $acctname vs Statement Data</h1>
<p>Current statement covers <b>$dtstart - $dtend</b>. Of $allcount entries, $opencount remain to reconcile and $assoccount have been reconciled.</p>
<table class="entries"><thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Memo</th><th>Actions</th></tr></thead>
<tr><td colspan="5" class="grouplabel">External entry to reconcile:</td></tr>
<tbody>$rendered[curopen]</tbody><tr><td colspan="5">&nbsp;</td></tr>
$rendered[candidates]</table>
$picktemplate $addtrx
end;
}

function render_entry_row( $class, $cols, $candidate, $action, $matching = array() )
{
	capcel_load_ext('base/types');
	$expect = capcel_expect_trigger($action['args'], $action['dynarg']);
	$candidate['memo'] = "$candidate[notes]: $candidate[memo]";
	$fc = array('*fqn'=>'finance:reconcile:entryrow', 'columnar'=>1, 'class'=>'concise');
	foreach ($cols as $col) {
		switch ($col) {
			case 'date': $value = base_render_date($candidate['trxdate'], "D m 'y"); break;
			case 'actions': $value = "<button onclick=\"location.replace('$expect&$action[dynarg]=". $candidate[$action['dynfield']] ."')\">$action[label]</button>"; break;
			default: $value = capcel_render_field($fc, 'accounting:element:acctentry', $candidate, $col); break;
		}
		$row .= '<td class="'. $col . ($matching[$col] ? " matching" : null) ."\">$value</td>";
	}
	return "<tr class=\"$class\">$row</tr>\n";
}

// Lists recent reconciled entries matching the passed extentry in account and (local)type, allowing selection which execs an 'iterate' on the transaction while applying the setdate value.
function trxmodel_action( &$frame )
{
	if ($model = $frame['arg*']['entry']) { // assignment
		$trx = array_pop(capcel_get_records("SELECT trx.*,amount,type FROM acctentry LEFT JOIN trx ON trx=trx._id WHERE acctentry._id = $model"));
		if (!count($entries = capcel_get_records("SELECT * FROM acctentry WHERE trx = $trx[_id]")))
			return "Something is wrong. We were unable to fetch any entries from the transaction selected. Aborting. ". capcel_exit_action();
		$trx['trxdate'] = $frame['extentry']['dtposted'];
		unset($trx['_id']);
		foreach ($entries as &$entry) {
			if ($entry['amount'] == $trx['amount'])
				$entry['amount'] = $frame['extentry']['trnamt'];
			unset($entry['_id']);
			$entry['reconcile'] = 'open';
		}
		capcel_load_ext('accounting/trx');
		$frame['action'] = 'accounting:action:trxnew';
		$frame['arg*'] = array('focus'=>'accounting:element:trx');
		return render_trx_form($trx, $entries, "Finish new transaction");
	}
	$frame['extentry'] = $extentry = array_pop(capcel_get_records("SELECT * FROM extstmtent WHERE _id = {$frame['arg*']['extentry']} ORDER BY dtposted ASC, _id ASC"));
	$extstmt = capcel_element_data('finance:element:extacctstmt', $extentry['extacctstmt']);
	$acctname = $extstmt->»account;
	$exittrigger = capcel_exit_action();
	$ac = array('fqn*'=>'finance:trxmodel:banner', 'arg*'=>array('focus'=>'accounting:element:trx'));
	$banneractions = $exittrigger;
	$fqn = $_SESSION['fqn*'];
	$addtrx = '<select size="1" onchange="location.replace(value)" name="purpose"><option>Add new transaction...</option>'.
		'<option value="'. _capcel_trigger_args($fqn['finance:action:purchase'], null) .'">Purchase</option><option value="'. _capcel_trigger_args($fqn['finance:action:receive'], null) .'">Receipt</option>'.
		'<option value="'. _capcel_trigger_args($fqn['finance:action:deposit'], null) .'">Deposit</option><option value="'. _capcel_trigger_args($fqn['accounting:action:trxnew'], null) .'">Generic</option>'.
		'</select>';
	$fc = array('*fqn'=>'finance:trxmodel:entryrow', 'columnar'=>1, 'class'=>'concise');
	$extentdef = $_SESSION['fqn*']['finance:element:extstmtent'];
	$colstyles = array('date','type','amount','memo','actions');
	foreach (array('dtposted','trntype','trnamt','memo') as $x => $col)
		$focusrow .= '<td class="'. $colstyles[$x] .'">'. capcel_render_field($fc, $extentdef, $extentry, $col) ."</td>";
	$rendered['extentry'] = "<tr class=\"extentry\">$focusrow<td>&nbsp;</td></tr>";
	$s = <<<end
SELECT acctentry.*,trxdate,notes,state
	,IF(`type`='debit',NULL,amount) AS `credit`
	,IF(`type`='credit',NULL,amount) AS `debit`
FROM acctentry
	LEFT JOIN trx ON trx=trx._id
WHERE acctentry.account = $extstmt[account]
	AND reconcile != 'open'
	AND trxdate > DATE_SUB('$extentry[dtposted]', INTERVAL 120 DAY)
	AND type = '$extentry[localtype]'
ORDER BY trxdate DESC
end;
	if (!count($frame['entries'] = $entries = capcel_get_records($s)))
		return "\n<p>No relevant pre-existing entries could be found.</p>\n<p>$newtrxtrigger $exittrigger</p>\n";
	$action = array('label'=>'Use as model', 'args'=>array(), 'dynarg'=>'entry', 'dynfield'=>'_id');
	foreach ($entries as $candidate)
		$rendered['candidates'] .= render_entry_row(null, $colstyles, $candidate, $action);
	capcel_include_stylesheet('common','base');
	capcel_include_stylesheet('browse','base');
	capcel_include_stylesheet('common', 'accounting');
	capcel_include_stylesheet('reconcile', 'finance');
	return <<<end
<span id="banneractions">$banneractions</span>
Account: <b>$acctname</b>
<h1>Select a Transaction Model</h1>
<table class="entries"><thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Memo</th><th>Actions</th></tr></thead>
<tr><td colspan="5" class="grouplabel">External entry to reconcile:</td></tr>
$rendered[extentry]
<tr><td colspan="5" class="grouplabel">&nbsp;</td></tr>
<tr><td colspan="5" class="grouplabel">Previously marked local entries for this account:</td></tr>
$rendered[candidates]</table>
<p>If none of the listed entries would make an appropriate model transaction, you can create a new transaction from scratch:</p>
$addtrx
end;
}

function reconcile_manual( &$frame )
{
	$entry_ = $_SESSION['fqn*']['accounting:element:acctentry'];
	$acct_ = $_SESSION['fqn*']['accounting:element:account'];
	extract($frame, EXTR_REFS);
	if ($frame['post*']) {
// Here we need to save the posted data somewhere in the frame because we may not use it immediately if we still require the cleardate.
// Generally we see that actions need to operate in the frame using the director's status variables as fleeting inputs to adjust state.
// The director maybe should pass the context information (not as a reference) as the second arg to the action. For backward compat it needs to continue to provide the old style too.
		$post = $frame['post*'];
		unset($frame['post*']);
		$frame['marklist'] = $post['marked'] ? $post['marked'] : array();
		switch ($post['purpose']) {
// We're not handling these parameters correctly. Are we posting or passing args? What's best?
			case 'close':
				if (!$post['cleardate']||$post['cleardate']=='*NULL')
					break;
				$cdqfrag = ",cleardate='$post[cleardate]'";
			case 'mark':
				capcel_begin_transaction("$post[purpose] entries for account $account->_formatted");
				capcel_query("UPDATE acctentry SET reconcile='open' WHERE account=$account[_id] AND reconcile!='close'");
				capcel_query("UPDATE acctentry SET reconcile='$post[purpose]' WHERE _id IN (". implode(',', $frame['marklist']) .")");
				capcel_query("UPDATE account SET cleared=".
					"(SELECT IFNULL(SUM(IF(type='credit',-amount,amount)),0) FROM acctentry WHERE account=$account[_id] AND reconcile='close') $cdqfrag ".
					"WHERE _id=$account[_id]");
				capcel_commit_transaction();
				return null;
			default:
				if (mb_strstr($post['purpose'], ':action:'))
					return capcel_call_action($post['purpose']);
		}
	}
	$cancel = capcel_exit_action();
	$ac = array('*fqn'=>'finance:reconcile:main','render'=>'capcel_standard_trigger','arg*'=>array('id'=>$frame['arg*']['id']));
	$otheractions = implode("", capcel_triggers($ac, $acct_));
	$savexit = '<button type="submit" name="purpose" value="mark">Save &amp; exit</button>';
	$reconcile = '<button type="submit" name="purpose" id="reconciler" value="close" disabled="">Reconciled as of:</button>'.
		capcel_render_field(array('class'=>'create'), $acct_, null, $acct_['field*']['cleardate']);
	$addtrx = '<select size="1" onchange="form.submit()" name="purpose"><option>Add new...</option>'.
		'<option value="finance:action:purchase">Purchase</option><option value="finance:action:receive">Receipt</option>'.
		'<option value="finance:action:deposit">Deposit</option><option value="accounting:action:trxnew">Transaction</option>'.
		'</select>';
	$amtfield = array('name'=>'amt', 'type'=>'accounting:balance', 'contexts'=>array('normal'=>'*render'));
	$fc = array('*fqn'=>'reconcile:row:field', 'columnar'=>true);
	$trxac = array('render'=>null, 'arg*'=>array('focus'=>'accounting:element:trx'));
	$factor = ($account['convention'] == 'credit' ? -1 : 1);
	$marked = $account['cleared'];
	$markcount=0;
	$query = "SELECT acctentry.*,trxdate,notes FROM acctentry LEFT JOIN trx ON trx=trx._id WHERE reconcile!='close' AND account=$account[_id] ORDER BY reconcile ASC,trxdate ASC";
	foreach (capcel_get_records($query) as $entry) {
		// the amount of the entry we store in the form as the nextSibling of the checkbox is signed to take into account the account sign convention
		// and the (credit or debit) type of the entry.
		$entry['amt'] = $entry['amount'] * ($entry['type']=='credit'? -1 : 1);
		if ($frame['*hint'] != 'initial')
			$entry['reconcile'] = (array_search($entry['_id'], $frame['marklist']) === false ? 'open' : 'mark');
		if ($entry['reconcile']=='mark') {
			$marked += $entry['amt'];
			$markcount++;
		}
		$trxac['jspass']['id'] = $entry['trx'];
		$rows .= "\n".'<tr class="entry alt'. (++$alt % 2) .'"><td style="text-align:left"><a href="javascript:'. capcel_trigger('accounting:action:trxuse',$trxac) ."\">$entry[notes]</a><br/>$entry[memo]</td>".
			"<td>$entry[trxdate]</td>".
			'<td class="balance">'. capcel_render_field($fc, $entry_, $entry, $amtfield) .'</td>'.
			'<td><input type="checkbox" value="'. $entry['_id'] .'" name="marked[]"'. ($entry['reconcile']=='mark'?' checked=""':null) .
				' onchange="mark(this)"/><input type="hidden" value="'. $entry['amt'] .'"/></td></tr>';
	}
	$posttarget = capcel_expect_trigger(array('*post'=>1));
	global $tailscript;
	capcel_include_stylesheet('common', 'base');
	capcel_include_stylesheet('browse', 'base');
	capcel_include_stylesheet('common', 'accounting');
	capcel_include_jscript("helpers", '.');
	capcel_include_jscript("input", 'accounting');
	return <<<end
<script>
var markbal,markbalout,markcount,rowset,cleardate,reconciler;
function mark(c){
	i=(c.checked?1:-1);
	markbal+=i*c.nextSibling.value;
	markbalout.innerHTML=cents_to_dollars(markbal*($factor));
	markcount.innerHTML=markcount.innerHTML*1+i;
	for(var x=0;x<rowset.rows.length;x++)
		if(rowset.rows[x]==c.parentNode.parentNode)
			break;
	var row=rowset.removeChild(rowset.rows[x]);
	for(x=0;x<rowset.rows.length;x++) {
		if(!c.checked&&rowset.rows[x].cells[3].firstChild.checked)
			break;
		if((c.checked==rowset.rows[x].cells[3].firstChild.checked)&&rowset.rows[x].cells[1].innerHTML>row.cells[1].innerHTML)
			break;
	}
	if(x==rowset.rows.length)
		rowset.appendChild(row);
	else
		rowset.insertBefore(row,rowset.rows[x]);
}
function capcel_validated() { switch(cleardate.value){case null:case '*NULL':reconciler.disabled=true;break;default:reconciler.disabled=false;}}
</script>
<span id="banneractions">$otheractions $cancel</span>
<h1>Reconcile $account->_rendered</h1>
<form enctype="multipart/form-data" method="post" action="$posttarget" name="inputform" class="input">
<table width="100%"><tr><td rowspan="2">$addtrx $savexit $reconcile </td><th>Marked balance</th><th>Marked entries</th></tr>
<tr><th>$<span id="markbal"></span></th><th><span id="markcount">$markcount</span></th></tr></table>
<table width="100%">
<thead><th align="left">Description</th><th>Date</th><th>Amount</th><th>Mark</th></thead>
<tbody id="rowset">$rows</tbody>
</table>
</form>
<script>
markbal = $marked;
markbalout = document.getElementById('markbal');
markcount = document.getElementById('markcount');
reconciler=document.getElementById('reconciler');
cleardate=document.getElementById('cleardate');
rowset = document.getElementById('rowset');
markbalout.innerHTML = cents_to_dollars(markbal*($factor));
$tailscript
</script>
end;
}

/* finance/purchase.css

div.editform { width:90% }
th { text-align:center }
td.editvalue { width:auto }

table.entries td { text-align:center; }
table.entries td.date {  }
table.entries td.type { }
table.entries td.amount { }
table.entries td.memo { text-align:left }
table.entries td.actions { }
table.entries td.matching { font-weight:bold }
table.entries td.grouplabel { font-weight:bold; text-align:left; color:#888888 }
table.entries tr.extentry { font-size:larger; font-weight:bold; border:1px solid blue; }
table.entries tr.probable { background-color:#88FF88; border:1px solid green; }
table.entries tr.possible { background-color:#FFFF88; border:1px solid darkyellow; }
table.entries tr.other { }

*/
