<?php
/**
* A statement is a container which collects all known un-assembled client transactions into a stand-alone renderable document.
*
*/
class Statement extends Container
{
	public static $table = 'biz_statement',  $singular = "Statement", $plural = "Statements", $descriptive = "Statement info",
		$containDef = [ 'fname'=>'trxs', 'class'=>'ClientTrx', 'refBy'=>'statement' ],
		$fielddefs = [
			'client'=>[ 'name'=>'client', 'type'=>"belong", 'class'=>'Client', 'label'=>"Client", 'identifying'=>true, 'readonly'=>true, ],
			'closedate'=>[ 'name'=>'closedate', 'class'=>'t_date', 'label'=>"Month", 'sort'=>"DESC", 'identifying'=>true, 'readonly'=>true, 'notnull'=>true ],
			'prevbal'=>[ 'name'=>'prevbal', 'class'=>'t_balance', 'label'=>"Previous", 'readonly'=>true, 'notnull'=>true ],
			'receipts'=>[ 'name'=>'receipts', 'class'=>'t_dollars', 'label'=>"Receipts", 'readonly'=>true, 'notnull'=>true ],
			'charges'=>[ 'name'=>'charges', 'class'=>'t_dollars', 'label'=>"Charges", 'readonly'=>true, 'notnull'=>true ],
			'due'=>[ 'name'=>'due', 'class'=>'t_balance', 'label'=>"Current", 'readonly'=>true, 'notnull'=>true ],
			'url'=>[ 'name'=>'url', 'class'=>'t_url', 'label'=>"URL", 'derived'=>"'dynamic'", 'readonly'=>true ],
			'message'=>[ 'name'=>'message', 'class'=>'t_text', 'label'=>"Message" ],
			'prepared'=>[ 'name'=>'prepared', 'class'=>'t_timestamp', 'label'=>"Date prepared" ],
			'rendered'=>[ 'name'=>'rendered', 'class'=>'t_richtext', 'label'=>"Finalized document", 'readonly'=>true, ],
			],
		$operations = [ // All operations associated with a statement are specialized
			'create'=>[ ],
			'display'=>[ ],
			'delete'=>[ ],
			],
		$hints = [
			'DataDirect'=>[ 'asDocument'=>true ],
			];

	public function formatted()
	{
		return "$this->client $this->closedate";
	}

	/**
	* You create a Statement for a Client for a particular closing date.
	* All existing client transactions which are for the intended client, unassociated with any other statement, and no later than the closing date are allocated to the statement.
	* From these the statement's aggregate values are determined. But until the statement is stored, those client transactions are not locked in.
	* When the creation is attempted, the other automatic processing we do is create charges for any monthly subscriptions which are not yet charged.
	* We have a specialized signature:
	* @param Client $client Any existing client, regardless of their activity status, may be used.
	* @param string $closedate The latest date of existing transactions to include within the statement. Constraints:
	*		This date must be later than the latest closedate associated with any statement in the system associated with the same Client.
	*		The date may not be later than today.
	*		You MAY pass a date without a DAY portion. When you do this, you incur the special behavior which works like this:
	*			- The closing date of the statement is officially the last day of the month in question, and yet...
	*			- The subscription charges for the first of the following month are charged and included in the statement.
	*		You may skip passing the closedate. In that case we will determine the closedate to be the last day of last month.
	* @return Statement
	*/
	public static function create( Client $client, $closedate = null )
	{
		$s = new Statement;
		$s->client = $client;
		if ($previous = Statement::get($client)) // assignment intended
			$pclose = $previous->closedate;
		else
			$pclose = '0000-00-00';
		list($pyear, $pmon, $pmday) = explode('-', $pclose);

		// Part One is exactly determining and validating the intended closedate
		if (!$closedate)
			extract(getdate(strtotime('last day of last month')));
		else
			list($year,$mon,$mday) = explode('-', $closedate);
		if (!$mday*1) // No day specified...
			extract(getdate(strtotime("last day of $year-$mon")));
		$this->closedate = "$year-$mon-$mday";
		if ($this->closedate <= $pclose)
			throw new Exception("The $this->¬closedate you request ($closedate) must be later than the $this->¬closedate of the next previous statement ($pclose) for this Client.");

		// Part Two is assembling all the relevant client transactions
		// First step here is to create new transactions dictated by monthly subscriptions.
		$newSubs = [];
//		foreach (Subscription::collection(['client'=>$client, 'where'=>""]) as $sub)
//			if (!ClientTrx::get())
//				$newSubs[] = ClientTrx::create($client, [ $sub->_ ]);
		$this->trxs = array_merge($newSubs,
			ClientTrx::collection(['client'=>$client, 'where'=>"{}.statement IS NULL AND {}_trx.trxdate <= '$this->closedate'"]));

		// Part Three is populating the Statement object with discovered information, some of which would be derived.
		$this->prevbal = $previous ? $previous->due : 0;
		$this->credits = 0;
		$this->charges = 0;
		$this->due = $this->prevbal;
		foreach ($this->trxs as $trx) {
			if ($trx->type) {
				$this->credits += $trx->extprice;
				$this->due += $trx->extprice;
			} else {
				$this->charges += $trx->extprice;
				$this->due -= $trx->extprice;
			}
		}

		return $s;
	}

	/**
	* We accept several possible selection modes:
	* 	- solo id as numeric, which is the ID of a Statement
	*	- array with id=numeric and class either unset or 'Statement'
	*	- array with client=numeric|Client AND closedate='yyyy-mm-dd'
	*	- Client and (string)closedate; if a Client is passed first and no second arg is passed, then we give you the latest stored statement.
	*/
	public static function get( $first )
	{
		$argc = func_num_args();
		$argv = func_get_args();
		if ($first instanceof Client) {
			if ($argc == 1)
				return static::get(['client'=>$first, 'limit'=>1]);
//			if (is_string($closedate = $argv[1]))
		}
	}

	public function asDocument( )
	{
	}

function bc_stmt( $focus, $id, &$data )
{
	extract($data);
	foreach (array_keys($data) as $key)
		if ($key != 'message')
			unset($data[$key]); // burn out the boat, but keep the hull for filling
	logDebug("A statement is needed for $client->_formatted for $end");
	if (count($subs = capcel_focus_data('biz:element:subscription', null,"startdate<='$end' AND client=$client->_id"))) {
		logDebug("For $end there are ". count($subs) ." active subscriptions to process for client $client->_formatted.");
		foreach ($subs as $charge) {
			$charge['trxdate'] = ($charge['startdate'] > $start ? $charge['startdate'] : $start);
			$charge['subscription'] = $charge['_id'];
			$subcharges = capcel_focus_data($chg_ = 'biz:element:charge', null,
				"subscription=${charge['subscription']} AND trxdate>='". mb_substr($end,0,8) ."01' AND trxdate<='$end'");
			if (count($subcharges)) {
				if (count($subcharges) > 1)
					capcel_error("The database already has multiple charges for ${charge['_rendered']} for ${charge['client__rendered']} for the month of $end.");
				$existing = array_pop($subcharges);
				if (($existing['quantity'] != $charge['quantity'] && $charge['item_unit'] != 'VPlnPAR')
						|| $existing['item'] != $charge['item'])
					capcel_error("Charge in database (qty=$existing[quantity],item=$existing[item]) for ${charge['_rendered']} for ${client['_rendered']} for $end doesn't match current charge (qty=$charge[quantity],item=$charge[item])!");
				logDebug("Valid charge already exists for ${charge['_rendered']} for $end. Continuing....");
				continue;
			}
			capcel_create_element($chg_, $charge);
		}
	} // if we have active subscriptions
	$data['client'] = $client['_id'];
	$data['prevbal'] = $prevbal;
	$data['month'] = $end;
	$data['receipts'] = capcel_query_scalar("SELECT SUM(received) FROM duercpt_ ".
		"WHERE trxdate>='$start' AND trxdate<='$end' AND client=${client['_id']}");
	$data['charges'] = capcel_query_scalar("SELECT SUM(total) FROM charge_ ".
		"WHERE trxdate>='$start' AND trxdate<='$end' AND client=${client['_id']}");
	$data['due'] = $prevbal + $data['charges'] - $data['receipts'];
	global $biz;
	$data['url'] = "$biz[stmtaccess]/$client[handle]/". ($client['stmtfile'] = "Stmt_$end.html");
	return $client;
}

function ac_stmt( $focus, $id, &$data, $client )
{
	global $biz, $notices;
	file_put_contents("${biz['stmtfolder']}/${client['handle']}/${client['stmtfile']}", render_statement($id));
}

}

?>
