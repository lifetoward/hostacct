<?php

abstract class t_percent extends t_decimal
{
	const MAX=10, MIN=0, FORMAT='1.5', PlaceShift = 2;

	public static function format( /* Instance */ $d, $fn = null )
	{
		return parent::format($d, $fn, null, null, '%');
	}

	public static function render( Instance $d, $fn, HTMLRendering $R )
	{
		return parent::render($d, $fn, $R, null, null, '%');
	}

}
