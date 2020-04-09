<?php

class e_comppaid extends Element
{
	public static $table = 'comppaid',$singular = "Payroll record",$plural = "Payroll records",$descriptive = "Payroll details",
		$joins = array(
			 "LEFT JOIN compcard AS `{}_compcard` ON(`{}_compcard`.comppaid=`{}`._id)"
			,"LEFT JOIN compline AS `{}_compcard_compline` ON(`{}_compcard_compline`.`compcard`=`{}_compcard`._id)"
			,"LEFT JOIN comptype AS `{}_compcard_compline_comptype` ON(`{}_compcard_compline`.comptype=`{}_compcard_compline_comptype`._id)"
			),
		$fielddefs = array(
			 'label'=>array('name'=>'label', 'label'=>"Purpose description", 'class'=>'t_string', 'initial'=>"Bi-weekly paychecks", 'sort'=>"ASC", 'required'=>true)
			,'paydate'=>array('name'=>"paydate", 'label'=>"Pay date", 'class'=>"t_date", 'sort'=>"DESC", 'required'=>true)
			,'employees'=>array('name'=>"employees", 'label'=>"Employees", 'class'=>'t_integer', 'derived'=>"COUNT(DISTINCT {}_compcard.employee)")
			,'cards'=>array('name'=>"cards", 'label'=>"Cards", 'class'=>'t_integer', 'derived'=>"COUNT(DISTINCT {}_compcard._id)")
			,'taxable'=>array('name'=>"taxable", 'label'=>"Total taxed", 'class'=>'t_cents', 'derived'=>"SUM(IF(FIND_IN_SET('taxable', {}_compcard_compline_comptype.flags),{}_compcard_compline.amount,0))")
			,'taxfree'=>array('name'=>"taxfree", 'label'=>"Total untaxed", 'class'=>'t_cents', 'derived'=>"SUM(IF(FIND_IN_SET('taxable', {}_compcard_compline_comptype.flags),0,{}_compcard_compline.amount))")
			,'notes'=>array('name'=>"notes", 'label'=>"Notes", 'class'=>'t_richtext')
			);

	public function getFieldDefs( $c = null, array $exclude = array(), array $include = array() )
	{
		if (!$c)
			return parent::getFieldDefs();
		if ($c->mode != $c::INPUT && $c->mode != $c::VERBOSE) // various fields are simply not editable
			$exclude = array('notes');
		return parent::getFieldDefs($c, $exclude);
	}

}

?>
