<?php

class e_basetypes extends Element
{
	public static $table = "basetypes",$singular = 'Base type test element',$plural = 'Test elements',
		$fielddefs = array(
			 'boolean'=>array('name'=>'boolean', 'class'=>'t_boolean', 'label'=>'boolean', 'format'=>'No way!|Oh Yeah!', 'required'=>true)
	/*		,'boolset'=>array('name'=>'boolset', 'class'=>'t_boolset', 'label'=>'boolset', )
			,'string'=>array('name'=>'string', 'class'=>'t_string', 'label'=>'string', 'pattern'=>'[-a-zA-Z0-9_!@#$%^&*()+=~]')
			,'text'=>array('name'=>'text', 'class'=>'t_text', 'label'=>'text', 'format'=>'Oh Yeah!|No way!')
			,'richtext'=>array('name'=>'richtext', 'class'=>'t_richtext', 'label'=>'richtext', 'format'=>'Oh Yeah!|No way!')
			,'integer'=>array('name'=>'integer', 'class'=>'t_integer', 'label'=>'integer', 'format'=>'Oh Yeah!|No way!')
			,'float'=>array('name'=>'float', 'class'=>'t_float', 'label'=>'float', 'format'=>'Oh Yeah!|No way!')
			,'decimal'=>array('name'=>'decimal', 'class'=>'t_decimal', 'label'=>'decimal', 'format'=>'Oh Yeah!|No way!')
			,'select'=>array('name'=>'select', 'class'=>'t_select', 'label'=>'select', 'format'=>'Oh Yeah!|No way!')
	*/		,'date1'=>array('name'=>'date1', 'class'=>'t_date', 'label'=>'date via picker', 'format'=>null, 'required'=>false)
			,'date2'=>array('name'=>'date2', 'class'=>'t_date', 'label'=>'date as text', 'format'=>null, 'input'=>'text')
			,'date3'=>array('name'=>'date3', 'class'=>'t_date', 'label'=>'date as selectors', 'format'=>null, 'input'=>'selectors')
	/*		,'hourmin'=>array('name'=>'hourmin', 'class'=>'t_hourmin', 'label'=>'hourmin', 'format'=>'Oh Yeah!|No way!')
			,'timeofday'=>array('name'=>'timeofday', 'class'=>'t_timeofday', 'label'=>'timeofday', 'format'=>'Oh Yeah!|No way!')
			,'timestamp'=>array('name'=>'timestamp', 'class'=>'t_timestamp', 'label'=>'timestamp', 'format'=>'Oh Yeah!|No way!')
	*/	);
}
