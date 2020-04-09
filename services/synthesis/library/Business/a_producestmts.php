<?php

class a_ProduceStmts extends Action
{
	public function render_me( $notice = null )
	{
	// Input processing

		if (!$this->subAction) {
			list($args, $post) = $c->request;
			if ($args['*exit'])
				return self::CANCEL;
		}

	// Rendering

		$c->addStyles(self::styles, 'Transaction form');
		$c->addScript(self::scripting, 'Transaction form');

	}

function produce_statements( &$frame )
{
	capcel_load_lib('data');
	if (is_array($frame['post*'])) {
		if (!count($frame['goodies']) || !count($frame['clients']))
			capcel_error("Clients and Goodies not initialized on message posting");
		$data = array_merge($frame['goodies'], $frame['post*']); // $data must not be a reference to $goodies!
		try { capcel_create_element('biz:element:statement', $data); }
		catch ( Exception $ex ) { throw $ex; }
		$d = getdate(strtotime($data['month']));
		$frame['clientmsgs'][] = 'A statement of your account for '. $d['month'] .' '. $d['year'] .' is now available. '.
			'You can view it online at <a target="_blank" href="'. $data['url'] .'">'. $data['url'] .'</a>.';
		$frame['actionreport'][] = 'A statement for '. $frame['goodies']['client']['company'] .' for '. $d['month'] .' '. $d['year'] .' has been generated. '.
			'It\'s available at <a target="_blank" href="'. $data['url'] .'">'. $data['url'] .'</a>.';
		// prepare for the next statement for this client
		$frame['goodies']['start'] = $frame['goodies']['next'];
		$frame['goodies']['prevbal'] = $data['due'];
		unset($frame['post*']);
	}
	if (!is_array($frame['clients'])) { // initialize the client list if needed
		if ($frame['arg*']['client'])
			$frame['clients'][] = capcel_focus_data('biz:element:client', $frame['arg*']);
		else
			$frame['clients'] = capcel_focus_data('biz:element:client');
		$frame['goodies']['today'] = date('Y-m-d', mktime());
	}
	$goodies =& $frame['goodies'];
	foreach ($frame['clients'] as $client) {
		$goodies['client'] = $client;
		logDebug("Processing for ". $client['_rendered']);
		if (!$goodies['start']) {
			if ($laststmt = capcel_get_records(
					"SELECT month,due FROM statement WHERE client=${client['_id']} ORDER BY month DESC LIMIT 1")) {
				extract(array_pop($laststmt));
				logDebug("Start will be based on the existence of a statement ending ". $month);
				list($yr, $mo, $dy) = explode('-', $month, 3);
				$goodies['start'] = date('Y-m-d', mktime(0, 0, 0, ++$mo, 1, $yr));
				$goodies['prevbal'] = $due;
			} else {
				$charge1 = capcel_query_scalar(
					"SELECT trxdate FROM charge LEFT JOIN trx ON(trx._id=trx) WHERE client=${client['_id']} ORDER BY trxdate ASC LIMIT 1");
				$rcpt1 = capcel_query_scalar(
					"SELECT trxdate FROM duercpt LEFT JOIN receipt ON(receipt._id=receipt) LEFT JOIN trx ON(trx._id=trx) WHERE client=${client['_id']} ORDER BY trxdate ASC LIMIT 1");
				if (!$charge1 && !$rcpt1) {
					logDebug("No charges or receipts for ${client['_rendered']}");
					array_shift($frame['clients']); // necessary because we need our looping position to be persistent across invocations
					continue; // because no statements are necessary for a client with no charges or receipts
				}
				logDebug("Start will be first of the earliest month containing receipts ($rcpt1) or charges ($charge1)");
				$earliest = $charge1 ? ($rcpt1 ? ($charge1 < $rcpt1 ? $charge1 : $rcpt1) : $charge1) : $rcpt1;
				$goodies['start'] = mb_substr($earliest, 0, 8) .'01';
				$goodies['prevbal'] = 0;
			}
			logDebug("Earliest statement would begin ${goodies['start']}");
		}
		list($yr, $mo, $dy) = explode('-', $goodies['start'], 3);
		if ($goodies['today'] > ($goodies['end'] = date('Y-m-d', mktime(0, 0, 0, ++$mo, 0, $yr)))) {
			$goodies['next'] = date('Y-m-d', mktime(0, 0, 0, $mo, 1, $yr));
			return "<h2>Creating a statement for ${client['_rendered']} for ". mb_substr($goodies['start'], 0, 7) ."</h2>".
				'<p>You can supply a special message for this invoice or choose to leave it blank:</p>'.
				'<form action="'. capcel_expect_trigger(array('*post'=>1)) .'" method="post" >'
				.'<input type="text" name="message" length="100"/><br/>'
				.'<button type="submit">Create statement</button> '. capcel_exit_action() .'</form>';
		}
		capcel_load_ext('biz/etc');
		if (count($frame['clientmsgs']))
			if (notify_client($client, $frame['clientmsgs'], "Your account statement is now available"))
				$frame['actionreport'][] = $client['company'] ." has been notified at ${client['email']}.";
		array_shift($frame['clients']); // necessary because we need our looping position to be persistent across invocations
		unset($frame['clientmsgs']);
		logDebug("Statements are current to the present for ${client['_rendered']}");
		$frame['goodies'] = array('today'=>$goodies['today']);
	} // for each client
	$result = "<h1>Statement generation report</h1>\n<p>". (count($frame['actionreport']) ? implode("</p>\n<p>", $frame['actionreport']) : "There were no statements to create at this time.") ."</p>\n";
	return $result . capcel_exit_action('OK');
}


}

?>
