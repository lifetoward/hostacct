<?php
/**
* A Receipt is a funding transaction in which a client or customer pays us for products or services, i.e. charges incurred.
* We need to short-circuit the transactions in each of these included subelements.
*/
class ReceiptTrx extends Element
{
	public static $table = 'biz_receipt',  $singular = "Receipt", $plural = "Receipts", $descriptive = "Receipt details",
		$fielddefs = [
			'funding'=>[ 'name'=>'funding', 'type'=>'include', 'class'=>'FundsXfer', 'label'=>"Funding info", 'identifying'=>true,
				'override'=>[ 'entity'=>[ 'filter'=>"{}._id IN (SELECT entity FROM biz_client)", ], ], ],
			'trx'=>[ 'name'=>'trx', 'type'=>'include', 'class'=>'Transaction', 'label'=>"Receipt (A/R) transaction", ],
			'method'=>[ 'name'=>'method', 'class'=>'t_string', 'label'=>"Method received",
				'help'=>"Merely a mnemonic in case of disputes. Common examples might be 'by mail', or 'in person in a meeting", ],
			'statement'=>[ 'name'=>'statement', 'class'=>'Statement', 'label'=>"Statement", 'type'=>'refer', 'readonly'=>true, ],
			],
		$operations = [ // actions allow general purpose actions to know how to interact with this element class in various situations
			'display'=>[ 'role'=>'Staff','action'=>'a_Trx' ],
			'create'=>[ 'role'=>'Finance','action'=>'a_Trx'],
			'list'=>[ 'role'=>'Staff' ],
			],
		$hints = [ // hints are provided for general purpose actions to be able to customize rendering of this particular element class
			'a_Browse'=>[ 'include'=>[ 'payer','rcvdate','amount','type','xid' ], 'triggers'=>[ 'banner'=>'create', 'row'=>[ 'display' ], ], ],
			];

	public function formatted()
	{
		return "RECEIVED $this->°funding";
	}

	public function renderTrx( TrxRendering $R )
	{
        $R->mode = $R::INPUT;
        $this->_rendering = $R;
		$R->idprefix = null;

		// It's important that we render the standard fields first because we override some of their configuration in our custom scripts below.
        foreach (['payer','rcvdate','amount','type','xid','method'] as $fn)
			$result .= $R->renderFieldWithRowBreaks($this, $this->getFieldDef($fn));

		$R->addScript("function validate_trx() { return true; }", "Validate ReceiptTrx");
		$R->addReadyScript("$('#trigger-store').prop('disabled',false);", "No validate required");
		$R->content = "<div class=\"row\">$result</div>";
		$R->header = "<h1>". ($this->_stored ? null : "New ") . htmlentities(static::$singular) ."</h1>";
		return $R;
	}

	// We must map certain values into substructures based on input received.
	public function populateTrx( array $values, Context $c = null )
	{
		$disp = count($this->entries) ? 'change' : 'new';
		$xid = $values['xid'] ? $values['xid'] : $this->°xid;
		$type = $values['type'] ? $this::getFieldDef('type')['options'][$values['type']] : $this->°type;
		$method = $values['method'] ? $values['method'] : $this->°method;
		$memo =  "$type $xid - $method";
		$payer = $values['payer'] ? e_entity::get($values['payer']) : $this->payer;
		if ($payer instanceof e_entity)
			$client = Client::get(['where'=>"{}.entity = $payer->_id"]);

		return parent::acceptValues(array_merge($values, [
			'payer'=>$payer,
			'receiver' => e_entity::get($c->OurEntity*1),
			'trxdate'=> $values['rcvdate'] ? $values['rcvdate'] : $this->rcvdate,
			'method'=>$method,
			'entries'=>[ 0=>
				[ 	'_id'=>$this->entries[0]->_id,
					'type' =>AcctEntry::Credit,
					'account'=>$this->entries[0]->account ? $this->entries[0]->account : // Earned receivables
							Account::get(['where'=>"`{}`.`class`= 130 AND ". Account::ActiveFilter, 'filter'=>['class'=>130, 'flags'=>'!inactive']]),
					'amount'=>$values['amount']*1,
					'memo'=>$memo,
					'tag'=>$client,
					'disposition' =>$disp,
					],
				[	'_id'=>$this->entries[1]->_id,
					'type' => AcctEntry::Debit,
					'account' =>$this->entries[1]->account ? $this->entries[1]->account : // Receipts pending
							Account::get(['where'=>"`{}`.`class`= 120 AND ". Account::ActiveFilter, 'filter'=>['class'=>120, 'flags'=>'!inactive']]),
					'amount' =>$values['amount']*1,
					'memo' =>$memo,
					'tag'=>$this->funding->_stored ? $this->funding : null, // FundsXfer object... will have a Trx when deposited
					'disposition' => $disp,
					],
				], // entries
			]), $c);
	}

	// In a create scenario, we need to let the basic object get stored, then stuff our FundsXfer object into the transaction debit entry.
	protected function storeInstanceData( Database $db )
	{
		$this->label = $this->formatted();
		parent::storeInstanceData($db);
		$this->entries[1]->tag = $this->funding->_id;
		$this->entries[1]->storeInstanceData($db);
		return $this->key;
	}
}
