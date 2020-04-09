<?php
/**
* A subscription specifies a ClientTrx which should be iterated on a monthly basis.
* Generally these iterations are created during statement creation.
* In the future this could be generalized to handle other periods like weeks or years, but months are by far the most popular.
*/
class Subscription extends Element
{
	public static $table = 'biz_subscription', $singular = "Subscription", $plural = "Subscriptions", $descriptive = "Subscription details",
		$fielddefs = [
			'client'=>[ 'name'=>'client', 'type'=>"belong", 'class'=>'Client', 'label'=>"Client", 'width'=>6, ],
			'item'=>[ 'name'=>'item', 'type'=>"require", 'class'=>'ClientItem', 'label'=>"Item", 'width'=>6, ],
			'began'=>[ 'name'=>'began', 'class'=>'t_date', 'label'=>"First day", 'sort'=>"ASC", 'input'=>"*picker", 'width'=>4, ],
			'proratestart'=>[ 'name'=>'proratestart', 'class'=>'t_boolean', 'format'=>"No|Yes", 'label'=>"Prorate FIRST month?", 'width'=>2, 'initial'=>0, 'required'=>true, ],
			'ended'=>[ 'name'=>'ended', 'class'=>'t_date', 'label'=>"Last day", 'sort'=>"DESC", 'input'=>"*picker", 'width'=>4 ],
			'prorateend'=>[ 'name'=>'prorateend', 'class'=>'t_boolean', 'format'=>"No|Yes", 'label'=>"Prorate LAST month?", 'width'=>2, 'initial'=>0, 'required'=>true, ],
			'quantity'=>[ 'name'=>'quantity', 'class'=>'t_integer', 'range'=>"1-9999", 'label'=>"Quantity", 'initial'=>1 ],
			'extprice'=>[ 'name'=>'extprice', 'class'=>'t_dollars', 'label'=>"Line total", 'required'=>true, 'readonly'=>true ],
			'details'=>[ 'name'=>'details', 'class'=>'t_richtext', 'label'=>"Details" ],
			],
		$operations = [
			'create'=>[],
			'display'=>[],
			'update'=>[],
			],
		$hints = [
			'a_Browse'=>['include'=>['client','item','began','ended'],'triggers'=>['banner'=>'create','row'=>['display','update']]],
			];

	public function formatted()
	{
		return "$this->client / $this->item";
	}
/*
	public function storeInstanceData( Database $db )
	{
		if ($this->item->unit != 'fixed' && $this->item->unit != 'each' && $this->item->unit != 'qtrhr')
			throw new DataEntryX([ 'item'=>'Only per-unit, quarter-hour, and fixed-price items can be subscribed']);
		e_billable::extend_quantity($this); // modifies values within $data
		return parent::storeInstanceData($db);
	}
*/
}
