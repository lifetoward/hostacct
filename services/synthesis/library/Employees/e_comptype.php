<?php

class e_comptype extends Element
{
	public static $table = "comptype",$singular = "Compensation type",$plural = "Compensation types",$descriptive = "Compensation type detail",
		$fielddefs = array(
			 'label'=>array('name'=>"label", 'label'=>"Compensation type", 'class'=>'t_string')
			,'rate'=>array('name'=>"rate", 'label'=>"Rate specifier", 'class'=>'t_string')
			,'flags'=>array('name'=>"flags", 'label'=>"Flags", 'class'=>'t_boolset', 'options'=>array("taxable"=>'Taxable', "employee"=>'Employee-allowed', "manager"=>'Manager allowed'))
			,'unittype'=>array('name'=>"unittype", 'label'=>"Type of units", 'class'=>'t_select', 'options'=>array("integer"=>"Integer", "hourmin"=>'Hours:Minutes', "cents"=>'Currency'))
			,'unit'=>array('name'=>"unit", 'label'=>"Units", 'class'=>'t_string', 'access'=>"admin")
			,'factor'=>array('name'=>"factor", 'label'=>"Multiplier", 'class'=>'t_float', 'initial'=>1, 'access'=>"admin")
			,'help'=>array('name'=>"help", 'label'=>"Usage information", 'class'=>'t_richtext', 'access'=>"admin")
			);

	public function formatted()
	{
		return $this->label;
	}

}

?>
