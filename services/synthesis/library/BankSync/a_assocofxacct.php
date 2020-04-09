<?php

class a_AssocOFXAcct extends Action
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

function AssocOFXAcct_action( &$frame )
{
	$stmtdata = $frame['arg*'];
	$focus = "accounting:element:account";
	if ($stmtdata['account']) {
		capcel_query("UPDATE account SET bankid='$stmtdata[bankid]', acctid='$stmtdata[acctid]' WHERE _id = $stmtdata[account]", 'Associate OFX identification');
		return;
	}

	// Instead of the approach below...
	// Categorize by class
	// List in columns with account names as links to select (maybe later: each with an "info" button beside it which takes you to the account details edit page)

	$accounts = capcel_element_data($focus, null, "IF(bankid,0,1) AND IF(acctid,0,1) AND class IN ('current','liability','security')", 'class');
	capcel_include_stylesheet('common','base');
	capcel_include_stylesheet('browse','base');
	capcel_load_ext('base/browse');
	$fc = array('*fqn'=>'finance:acctselect:record', 'render'=>'column_field', 'columnar'=>1, 'class'=>'concise');
	$fields = capcel_get_fields($fc, $focus);
	unset($fields['balance']);
	foreach ($fields as $field)
		$labels[] = column_field($field, "<b>${field['*label']}</b>");
	$accttable = '<table class="browse"><tr class="browse">'. implode('', $labels) ."<td>&nbsp;</td></tr>\n";
	$ac = array('*fqn'=>'base:browse:record', 'render'=>'base/browse:record_trigger');
	foreach ($accounts as &$record) {
		$ac['jspass']['id'] = $record['_id'];
		$accttable .= '<tr class="alt'. (++$alt % 2) .'">'. capcel_render_fields($fc, $focus, $record, $fields) .
			"<td><button onclick=\"location.replace('". capcel_expect_trigger(array('account'=>$record['_id'])) ."')\">Select</button></td></tr>\n";
	}
	$accttable .= "</table>\n";
	$ac = array('*fqn'=>'finance:acctselect:banner', 'render'=>'capcel_standard_trigger');
	$banneractions = implode(" ", capcel_triggers($ac, $focus)) ." ". capcel_exit_action();
	return <<<end
<span id="banneractions">$banneractions</span><h2>Select the Account matching</h2><h2>Bank ID $stmtdata[bankid], Acct ID $stmtdata[acctid]</h2>
$accttable
end;
}

}
