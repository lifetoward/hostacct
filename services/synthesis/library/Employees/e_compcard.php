<?php

class e_compcard extends Container
{
	protected $lines = null;

	public static $table = "compcard",$singular = "Compensation card",$plural = "Compensation cards",$descriptive = "Compensation record",
		$containDef = [ 'fname'=>'lines', 'class'=>'CompensationLine', 'refBy'=>'compcard' ],
		$joins = array("LEFT JOIN compline AS `{}_compline` ON(`{}_compline`.`compcard`=`{}`._id) LEFT JOIN comptype AS `{}_compline_comptype` ON(`{}_compline`.comptype=`{}_compline_comptype`._id)"),
		$fielddefs = array(
			 'employee'=>array('name'=>"employee", 'type'=>"belong", 'class'=>'e_employee', 'label'=>"Employee", 'sort'=>true, 'ondelete'=>"RESTRICT", 'readonly'=>true, 'notnull'=>true, 'identifying'=>true)
			,'bizunit'=>array('name'=>"bizunit", 'class'=>"t_select", 'label'=>"Biz unit", 'sort'=>'ASC' ,'notnull'=>true,
				'options'=>array("screening"=>'Screenings', "coaching"=>'Coaching', "management"=>'Management', 'general'=>'General'))
			,'description'=>array('name'=>"description", 'class'=>"t_string", 'label'=>"Description", 'required'=>true)
			,'initdate'=>array('name'=>"initdate", 'class'=>"t_date", 'label'=>"Submitted", 'sort'=>"ASC")
			,'lockdate'=>array('name'=>"lockdate", 'class'=>"t_date", 'label'=>"Committed", 'sort'=>"ASC")
			,'comppaid'=>array('name'=>"comppaid", 'type'=>"refer", 'label'=>"Paycheck / status", 'class'=>"e_comppaid", 'sort'=>"ASC", 'ondelete'=>"RESTRICT")
			,'initiation'=>array('name'=>"initiation", 'class'=>"t_select", 'label'=>"Initiated by", 'sort'=>"ASC",
				'options'=>array("mgmt"=>'management', "system"=>'the system', "empl"=>'the employee'))
			,'explanation'=>array('name'=>"explanation", 'class'=>"t_richtext", 'label'=>"Explanations",
				'help'=>"This text is provided by the initiator of the card and explains any history or reasons for its creation.")
			,'response'=>array('name'=>"response", 'class'=>"t_richtext", 'label'=>"Response",
				'help'=>"This text is provided by the non-initiating party (among employee or management) in response to the explanation and generally accompanies committing the card.")
			,'adjusted'=>array('name'=>"adjusted", 'type'=>"refer", 'label'=>"Referenced card", 'class'=>"e_compcard", 'ondelete'=>"RESTRICT")
			,'taxable'=>array('name'=>"taxable", 'class'=>"t_cents", 'label'=>"Taxable", 'derived'=>"SUM(IF(FIND_IN_SET('taxable', {}_compline_comptype.flags),{}_compline.amount,0))")
			,'taxfree'=>array('name'=>"taxfree", 'class'=>"t_cents", 'label'=>"Untaxed", 'derived'=>"SUM(IF(FIND_IN_SET('taxable', {}_compline_comptype.flags),0,{}_compline.amount))")
			,'mindate'=>array('name'=>"mindate", 'class'=>"t_date", 'label'=>"Earliest date", 'derived'=>"MIN({}_compline.earndate)")
			,'maxdate'=>array('name'=>"maxdate", 'class'=>"t_date", 'label'=>"Latest date", 'derived'=>"MAX({}_compline.earndate)")
			,'linecount'=>array('name'=>"linecount", 'class'=>"t_integer", 'label'=>"Lines", 'derived'=>"COUNT(DISTINCT {}_compline._id)")
			,'daterange'=>array('name'=>'daterange', 'label'=>'Dates',
				'help'=>"Because a compensation card is really just a collection of purpose-related line items, this provides the range of earning dates for the line items in the card.")
			);

	public static function create( e_employee $emp )
	{
		$me = parent::create();
		$me->employee = $emp;
		return $me;
	}

	public function formatted( )
	{
		return $this->employee .($this->description ? ": $this->°description" : null) .($this->mindate ? " [$this->°daterange]" : null);
	}

	public function &getFieldValue( $fn )
	{
		if ('daterange' == $fn)
				return "$this->mindate,$this->maxdate";
		return parent::getFieldValue($fn);
	}

	public function numericizeField( $fn )
	{
		if ($name == 'daterange') {
			$min = new DateTime($this->mindate);
			$interval = $min->diff(new DateTime($this->maxdate), true);
			return $interval->format('%a') * 1;
		}
		return parent::numericizeField($fn);
	}

	public function formatField( $name )
	{
		if ($name == 'daterange')
			return t_datespan::format_span($this->mindate, $this->maxdate);
		return parent::formatField($name);
	}

	public function renderField( /* ... */ )
	{
		$args = func_get_args();
		if ($args[0] == 'daterange')
			return htmlentities($this->formatField('daterange'));
		return call_user_func_array('parent::renderField', $args);
	}
}
