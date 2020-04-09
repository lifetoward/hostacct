<?php

class a_ImportOFX extends Action
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

function importofx_action( Database $c, &$frame )
{
	if (!$frame['ofxfile']) {
		if (!is_array($frame['post*'])) {
			$actiontarget = capcel_expect_trigger(array('*post'=>1));
			$cancel = capcel_exit_action();
			return <<< end
<h1>Import OFX statement data</h1>
<form action="$actiontarget" method="post" enctype="multipart/form-data">
<p>Select the file to upload: <input type="file" name="ofxfile" onchange="getElementById('submitbutton').disabled=value.length?false:true"/></p>
<p><button type="submit" disabled="" id="submitbutton"> Import </button> $cancel</p>
</form>
end;
		}
		$frame['ofxfile'] = file_get_contents($_FILES['ofxfile']['tmp_name']);
	}
	if (!$frame['ofxdata']) {
		$raw = parseofx($frame['ofxfile']);
		$frame['ofxdata'] = convertofx($raw);
	}
	if ($frame['arg*']['import'] == 'GO!') {
		$c->dbBegin("writing imported OFX data to database");
		foreach ($frame['ofxdata']['statement*'] as $acctstmt) {
			$qs = "INSERT INTO extacctstmt (account, bankid,acctid,accttype,dtstart,dtend,balamt,dtasof,status) VALUES ( $acctstmt[account] ".
				",'$acctstmt[bankid]' ,'$acctstmt[acctid]' ,'$acctstmt[accttype]' ,'$acctstmt[dtstart]' ,'$acctstmt[dtend]' ,$acctstmt[balamt] ,'$acctstmt[dtasof]','open')";
			$c->dbQuery($qs);
			$eas = $c->dbNewId();
			foreach ($acctstmt['entry*'] as $trx)
				capcel_query("INSERT INTO extstmtent (extacctstmt, fitid, trntype, dtposted, trnamt, memo, localtype) VALUES ( ".
					"$eas ,'". $c->dbEscapeString($trx['fitid']) ."' ,'$trx[trntype]' ,'$trx[dtposted]' ,$trx[trnamt] ,'".
					$c->dbEscapeString($trx['memo']) ."' ,'". ($trx['negative'] ? 'credit' : 'debit') ."' )");
		}
		capcel_commit_transaction();
		return "<p>The statement data has been imported as previously described.</p>". capcel_exit_action('OK');
	}
	foreach ($frame['ofxdata']['statement*'] as &$acctstmt) {
		$stmtresult .= "<p><b>Statement for Bank ID $acctstmt[bankid], Account ID $acctstmt[acctid]:</b><br/>".
			"Date range: $acctstmt[dtstart] thru $acctstmt[dtend] ; Transactions: ". count($acctstmt['entry*']);
		$account = array_pop(capcel_element_data('accounting:element:account', null, "bankid='$acctstmt[bankid]' AND acctid='$acctstmt[acctid]'"));
		if ($account['_id']) {
			$acctstmt['account'] = $account['_id'];
			$stmtresult .= "<br/>Local account: <b>$account->_rendered</b>";
			if (!($nextdate = capcel_query_scalar(
					"SELECT DATE_ADD(dtend, INTERVAL 1 DAY) FROM extacctstmt WHERE account = $account[_id] ORDER BY dtend DESC LIMIT 1")))
				$nextdate = capcel_query_scalar("SELECT DATE_ADD('$account[cleardate]', INTERVAL 1 DAY)");
			if ($acctstmt['dtstart'] == $nextdate) {
				$stmtresult .= "; The statement has an appropriate start date of $nextdate.\n";
			} else {
				$problems++;
				$stmtresult .= "; The statement begins at $acctstmt[dtstart] but the expected start (based on the last cleared or imported date) is ". ($nextdate ? $nextdate : 'undefined') .".\n".
					"<br/>To correct this you must either reconcile the account or import statement data up to the day before $acctstmt[dtstart].\n";
			}
		} else {
			$problems++;
			$args = $acctstmt;
			unset($args['entry*']);
			$args['action'] = 'finance:action:AssocOFXAcct';
			$stmtresult .= '<br/>This vendor account is not associated with a local account. Click <a href="'. capcel_expect_trigger($args) .
				'">here</a> to correct this before proceeding.';
		}
		$stmtresult .= "</p>";
	}
	if (!$problems)
		$OK = "<button onclick=\"location.replace('". capcel_expect_trigger(array('import'=>'GO!')) ."')\">OK, Import to Database</button>";
	return "<p>The vendor  data you've uploaded contains ". count($frame['ofxdata']['statement*']) ." $stmtcount ".
		($stmtcount > 1 ? 'different statements' : 'statement') .":</p>$stmtresult <br/>$OK ". capcel_exit_action();
}

function parseofx( $ofx )
{
	$DTD = file_get_contents("$GLOBALS[sysroot]/lib/finance/ofxbank.dtd");
	$result = array();
	$indexes = array();
	// Process the header (colon delimited name value pairs)
	$ofxlines = explode("\n", $ofx);
	while (mb_strlen($line = trim(array_shift($ofxlines)))) {
		list($name,$val) = explode(':', $line);
		$result['header*'][$name] = $val;
	}
	$ofxbody = implode("", $ofxlines);
	// Process the body (SGML)
	$tag = trim(strtok($ofxbody, '<'));
	do  { // we'll process by tag, whether it be an opener, closer, or value marker (unended)
		$count++;
		list($key, $value) = explode('>', $tag);
		if (mb_substr($key, 0, 1) == '/') { // if this is a closer
			while (mb_substr($key, 1) != array_pop($indexes));
			continue; // this tag has been processed... go get the next
		}
		if (!mb_strlen($value)) { // Tags either hold a value or contain other tags... this logic is for containers
			array_push($indexes, $key); // save the containing aspect
			if (preg_match("|[^a-zA-Z]${key}[+*]|", $DTD) || preg_match("|\([^(]*[^a-zA-Z]${key}[^a-zA-Z][^(]*\)[+*]|", $DTD)) // The DTD defines this key as having many instances
				array_push($indexes, $count); // This pushes a unique (ever increasing count) name into the indexing tree merely to differentiate mult-entry keys
			continue;
		}
		$target = '$result["' . implode('"]["', $indexes) .'"]["'. $key .'"] = "'. trim($value) .'";';
		eval($target);
	} while ($tag = trim(strtok('<')));
	return $result;
}

function convertofx( $raw )
{
	if (is_array($raw['OFX']['BANKMSGSRSV1'])) {
		$statements = $raw["OFX"]["BANKMSGSRSV1"]["STMTTRNRS"];
		$stmtlevel = 'STMTRS';
		$acctinfo = 'BANKACCTFROM';
	} else if (is_array($raw['OFX']['CREDITCARDMSGSRSV1'])) {
		$statements = $raw["OFX"]["CREDITCARDMSGSRSV1"]["CCSTMTTRNRS"];
		$stmtlevel = 'CCSTMTRS';
		$acctinfo = 'CCACCTFROM';
	}
	foreach ($statements as &$stmt) {
		$entries = array();
		foreach ($stmt[$stmtlevel]['BANKTRANLIST']['STMTTRN'] as $entry) {
			if ($entry['MEMO']) {
				if ($entry['NAME']) {
					if (mb_strstr($entry['MEMO'],$entry['NAME']) !== false)  // name is a subset of memo
						$memo = $entry['MEMO']; // then use the superset
					else if (mb_strstr($entry['NAME'], $entry['MEMO']) !== false) // memo is a subset of name
						$memo = $entry['NAME']; // then use the superset
					else
						$memo = "$entry[NAME]; $entry[MEMO]"; // name and memo are both interesting
				} else
					$memo = $entry['MEMO'];
			} else
				$memo = $entry['NAME'];

			$entries[] = array(
				 'fitid'=>$entry['FITID']
				,'trntype'=>$entry['TRNTYPE']
				,'dtposted'=>($entry['DTUSER'] ? ofxdateconv($entry['DTUSER']) : ofxdateconv($entry['DTPOSTED']))
				,'trnamt'=>str_replace('-', '', $entry['TRNAMT']) * 100
				,'memo'=> // Different banks report memos and names differently. Some see memo as a long version of name. Some see them as co-requisite. Some don't provide memo.
					$entry['MEMO'] ?
						($entry['NAME'] ?
							(mb_strstr($entry['MEMO'],$entry['NAME']) !== false ?  // name is a subset of memo
								$entry['MEMO'] : // then use the superset
								(mb_strstr($entry['NAME'], $entry['MEMO']) !== false ? // memo is a subset of name
									$entry['NAME'] : // then use the superset
									"$entry[NAME]; $entry[MEMO]")) : // name and memo are both interesting
							$entry['MEMO']) :
						$entry['NAME']
				,'negative'=> $entry['TRNAMT'][0]=='-'
				);
		}
		$data['statement*'][] = array(
			 'bankid'=>($stmt[$stmtlevel][$acctinfo]['BANKID'] ? $stmt[$stmtlevel][$acctinfo]['BANKID'] : 'undef')
			,'acctid'=>$stmt[$stmtlevel][$acctinfo]['ACCTID']
			,'accttype'=>$stmt[$stmtlevel][$acctinfo]['ACCTTYPE']
			,'dtstart'=>ofxdateconv($stmt[$stmtlevel]['BANKTRANLIST']['DTSTART'])
			,'dtend'=>ofxdateconv($stmt[$stmtlevel]['BANKTRANLIST']['DTEND'])
			,'balamt'=>$stmt[$stmtlevel]['LEDGERBAL']['BALAMT'] * 100
			,'dtasof'=>ofxdateconv($stmt[$stmtlevel]['LEDGERBAL']['DTASOF'])
			,'entry*'=>$entries
			);
	}
	return $data;
}

function ofxdateconv($ofxdate)
	{ return mb_substr($ofxdate,0,4).'-'.mb_substr($ofxdate,4,2).'-'.mb_substr($ofxdate,6,2); }



}
