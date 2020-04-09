<?php

class ClientItem extends Element
{
	const ActiveFilter = "FIND_IN_SET('retired',`{}`.flags)=0 && `{}`.replacement IS NULL";

	public static $table = 'biz_item',  $singular = "Client exchange item", $plural = "Client exchange items", $descriptive = "Item details",
		$fielddefs = [
			'which'=>[ 'name'=>'which', 'class'=>'t_boolean', 'format'=>"Credit|Charge", 'label'=>"Type", 'identifying'=>true, 'sort'=>'DESC',
				'initial'=>1, 'help'=>"A credit reduces the A/R asset account, while a charge increases it.", 'required'=>true ],
			'name'=>[ 'name'=>'name', 'class'=>'t_string', 'label'=>"Item name", 'sort'=>"ASC", 'identifying'=>true, 'required'=>true ],
			'rate'=>[ 'name'=>'rate', 'class'=>'t_dollars', 'label'=>"Rate or List price", 'sort'=>"DESC", 'help'=>"When the unit is undefined, this rate becomes the assumed price." ],
			'unit'=>[ 'name'=>'unit', 'class'=>'t_select', 'label'=>"Rate unit", 'sort'=>"ASC", 'options'=>[ 'hour'=>"Hour", 'unit'=>"Unit", 'mile'=>"Mile", 'month'=>"Month" ],
				'help'=>"Leaving this unset means the price is fixed, or 'per dollar'.", ],
			'rounding'=>[ 'name'=>'rounding', 'class'=>'t_select', 'label'=>"Rounding method", 'help'=>"Native is an actual method name in ClientItem.",
				'options'=>[ 'nearest'=>"Off to the nearest", 'up'=>"Up to the next", 'down'=>"Down to the whole" ], 'initial'=>'nearest' ],
			'dplaces'=>[ 'name'=>'dplaces', 'class'=>'t_integer' ,'label'=>"Decimal places", 'initial'=>0, 'range'=>'-3:6',
				'help'=>"How precisely to interpret quantities: positive numbers are right of the decimal, negative to the left. Examples: -1 means round to the nearest 10; 2 means round to the nearest cent." ],
			'description'=>[ 'name'=>'description', 'class'=>'t_richtext', 'label'=>"Description" ],
			'flags'=>[ 'name'=>'flags', 'class'=>'t_boolset', 'label'=>"Marker attributes", 'options'=>[ 'retired'=>"Retired" ], 'width'=>8],
			'replacement'=>[ 'name'=>'replacement', 'class'=>'ClientItem', 'identifying'=>true, 'type'=>'refer', 'label'=>"Updated and Superceded by", 'readonly'=>true,
				'help'=>"When an item has already been used, it cannot be updated, so changes are actually made in new items copied from the old. This refers to the version of the item which replaced this one." ],
			],
		 $operations = [  // actions allow general purpose actions to know how to interact with this element class in various situations
			'update'=>[ 'role'=>'Manager' ],
			'create'=>[ 'role'=>'Manager' ],
			'delete'=>[ 'role'=>'*super' ],
			'list'=>[ ],
			],
		$hints = [  // hints are provided for general purpose actions to be able to customize rendering of this particular element class
			'a_Browse'=>[ 'include'=>[ 'name','which','rate', 'unit' ],	'triggers'=>[ 'banner'=>"create", 'row'=>[ 'display','update' ], ], ],
			'AJAXAction'=>['asJSON'=>'Staff'],
			];

	public function formatted()
	{
		return "$this->name";
	}

/*
// BELOW INHERITED FROM LEGACY BILLABLE ELEMENT

	// The following function is a helper for extending accepted charged quantities by the billable item's units. It's called during the preparation of a charge (or subscription, etc) for storage.
	public static function extend_quantity( Element $line )
	{
		if ( ! $line instanceof e_charge && ! $line instanceof Subscription )
			throw new ErrorException("biz_interpret_quantity() is for extending charges.");

		// Sets a validated quantity string (quantity) and a line total (total) in the passed transaction
		$quantity = trim($line->quantity);
		if ($line->item->unit == 'each' || $line->item->unit == 'permile' || $item->unit == 'qtrhr') {
			if (!preg_match('/^[0-9]*$/', $quantity))
				throw new DataEntryX(array('quantity'=>'An whole number is required for per-unit and per-mile items'));
			if (($this->quantity = $quantity + 0) < 1)
				throw new DataEntryX(array('quantity'=>'A quantity of at least 1 is required'));
			$this->total = $this->quantity * $line->item->rate;
		} else if ($line->item->unit == 'hourly') {
			if (!preg_match('/^[0-9]*:[0-5][0-9]$/', $quantity))
				throw new DataEntryX(array('item'=>'This item is billed by the hour', 'quantity'=>'A time specification is required: [H]:MM'));
			list($hours, $mins) = explode(":", $quantity, 2);
			if ($hours < 1 && $mins < 1)
				throw new DataEntryX(array('quantity'=>'A quantity of at least 1 minute is required'));
			$this->quantity = ($hours + 0) .":". sprintf("%02d", $mins); // normalizes the quantity for storage
			$this->total = ($hours * $line->item->rate) + ($mins * $line->item->rate / 60);
		} else if ($line->item->unit == 'fixed') {
			if (!preg_match('/^[0-9]*(\.[0-9][0-9])?$/', $quantity))
				throw new DataEntryX(array('quantity'=>'For this item, a price in dollars or dollars-and-cents is required: D[.CC]'));
			list($dollars, $cents) = explode(".", $quantity, 2);
			logDebug("quantity=$quantity, dollars=$dollars, cents=$cents");
			if ($dollars < 1 && $cents < 1)
				throw new DataEntryX(array('quantity'=>'A price of at least 1 cent is required'));
			$this->quantity = ($dollars += 0) .".". sprintf("%02d", $cents += 0);
			$this->total = $dollars * 100 + $cents;
		} else
			throw new DataEntryX(array('item'=>'The selected item does not have a unit defined. Please correct the item or use a different one.'));
		return;
	}


// BELOW INHERITED FROM LEGACY CHARGE ELEMENT
//	public function store( )
	{
		if ($this->trxdate < capcel_query_scalar("SELECT MAX(month) FROM statement WHERE client={$this->client->_id} GROUP BY _id") && !$force)
			throw new VerifyPostX("The date of this charge ($this->Â»trxdate) is older than the latest statement for $this->client. If you continue, you may end up with an orphaned charge that won't be reflected in a published statement.");
		e_billable::extend_quantity($this);
		$this->itemRate = $this->billable->rate;
		$this->itemName = $this->billable->name;
		// transaction-level details
		$this->uniqid = uniqid();
		$this->notes = "CHARGE $this->client $". sprintf("%0.2f", $data['total']/100) . ": $this->details";
		// acctentry details
		$mode = $this->_stored ? 'update' : 'create';
		$this->entries->perform = array($mode, $mode); // not a real field, but rather hints for the acctentries.
		$this->entries->type = array('credit','debit');
		$this->entries->amount = array($data['total'], $data['total']);
		$this->entries->account = array(capcel_get_parm('Revenues Account'), capcel_get_parm('Receivables Account'));
		$this->entries->memo = array($client->_formatted, $client->_formatted);
		global $notices;
		if (!$this->_stored) {
			if (count($r = capcel_get_records("SELECT _id FROM acctentry WHERE trx=$this->_id ORDER BY type")) != 2)
				capcel_error("a charge is meant to be exactly 2 acctentries from database");
			$this->entries->_id = array($r[0]['_id'], $r[1]['_id']);
			$notices[] = array('level'=>'charges',
				'mgr'=>"Charge to $this->client modified; now $". sprintf("%0.2f", $this->total/100) ." for $this->billable on $this->Â»trxdate.",
				'client'=>"An as yet unbilled charge against your account has been modified.\n");
		} else
			$notices[] = array('level'=>'charges',
				'mgr'=>"Charged $this->client $". sprintf("%0.2f", $this->total/100) ." for $this->billable as rendered on $this->Â»trxdate.",
				'client'=>"A new charge on $this->Â»trxdate for $". sprintf("%0.2f", $this->total/100) ." has been made against your account for $this->billable. ".
					($this->details ? "A description of the work performed follows:\n$this->details" : null));

		return parent::store();
	}
	public static function getFieldDefs( $c = null, array $exclude = array(), array $include = array() )
	{
		if (!$c && !$c->mode)
			return parent::getFieldDefs();

		if ($c->mode == $c::INPUT) // various fields are simply not editable
			$exclude = array_merge((array)$exclude, array('notes','state','uniqid','total','itemName','itemRate','subscription'));

		return parent::getFieldDefs($c, $exclude, $include);
	}
*/

}
