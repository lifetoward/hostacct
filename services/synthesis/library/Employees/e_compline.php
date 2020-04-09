<?php

class e_compline extends Element
{
	public static $table = "compline",$singular = "Compensation line item",$plural = "Compensation line items",$descriptive = "Compensation line detail",
		$fielddefs = array(
			 'compcard'=>array('name'=>"compcard", 'type'=>"belong", 'label'=>"Comp card", 'class'=>"e_compcard", 'ondelete'=>"CASCADE", 'notnull'=>true, 'readonly'=>true) // <contexts normal="*render"
			,'earndate'=>array('name'=>"earndate", 'class'=>"t_date", 'label'=>"Date earned", 'notnull'=>true, 'required'=>true, 'sort'=>'ASC') // <contexts normal="*render"/>
			,'comptype'=>array('name'=>"comptype", 'type'=>"require", 'class'=>"e_comptype", 'label'=>"Compensation category", 'readonly'=>true, 'sort'=>true) // <contexts normal="*render"/>
			,'quantity'=>array('name'=>"quantity", 'label'=>"Quantity of units", 'class'=>"t_string", 'initial'=>1, 'range'=>"1-999", 'notnull'=>true, 'required'=>true) // <contexts, 'normal'=>"*render"/>
			,'rate'=>array('name'=>"rate", 'label'=>"Applied rate", 'class'=>"t_string", 'notnull'=>true, 'readonly'=>true) // <contexts, 'normal'=>"*render"/>
			,'amount'=>array('name'=>"amount", 'label'=>"Amount", 'class'=>"t_cents", 'notnull'=>true, 'readonly'=>true) //  <contexts normal="*render"/>
			,'memo'=>array('name'=>"memo", 'label'=>"Memo / explanation", 'class'=>"t_string", 'notnull'=>true, 'required'=>true) // <contexts normal="*render"/>
			);

	public static function create( e_compcard $card, e_comptype $type )
	{
		$me = parent::create();
		$me->compcard = $card;
		$me->comptype = $type;
		return $me;
	}

	public static function collection( $arg )
	{
		if ($arg instanceof e_compcard)
			$arg = array('where'=>"{}.compcard=$arg->_id");
		return parent::collection($arg);
	}

	public function renderField( $fn, Context $c )
	{
		if ($fn == 'quantity') {
			$unitclass = "t_{$this->comptype->unittype}";
			if ($c->mode == $c::COLUMNAR)
				$c->mode = $c::INLINE;
			return $unitclass::render($this, 'quantity', $c) ."&nbsp;". $this->comptype->renderField('unit', $c) . ($this->quantity == 1 ? null : 's');
		}
		if ($fn == 'rate')
			return $this->rate == 1 ? null : number_format(round($this->rate / 100, 2), 2) ." / {$this->comptype->»unit}";
		return parent::renderField($fn, $c);
	}

}
