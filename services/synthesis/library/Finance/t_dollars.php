<?php
/**
* Stored in 2-place decimal format and rendered as a 2-decimal place value with a leading dollar sign.
* Usable for up to $100Million.
* This is an unsigned quantity. If you want a signed dollar amount, considering using t_balance instead.
*
* The methods in this abstract subclass are all described in the base class Type, so don't expect a lot of comments about them here.
*
* All original code.
* @package Synthesis/Finance
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
abstract class t_dollars extends t_decimal
{
	const MIN=0, FORMAT='8.2';

	// To prevent rounding problems you must use integers to do the math on the monetary amounts.
	// Use Number.asDollars to convert integer numeric cents (as we handle all amount and balance values) back into a string formatted dollars-and-cents.
	// Use String.asCents to convert a string (ie. the value of an input control) of formatted dollars-and-cents to integer numeric cents.
	const jsDollarsAndCents = "String.prototype.asCents = function(){ return Math.round(100*this.replace(/,/g,'')); }
Number.prototype.asDollars = function(){ return (this/100).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}); }";

	protected static function get_range( )
	{
		return parent::get_range('0:struct', self::FORMAT);
	}

	public static function format( /* Instance */ $d, $fn = null )
	{
		return parent::format($d, $fn, static::FORMAT, $GLOBALS['root']->moneysign, ' USD');
	}

	public static function render( Instance $d, $fn, HTMLRendering $R, $format = null )
	{
		$R->addReadyScript(self::jsDollarsAndCents, 't_dollars');
		return parent::render($d, $fn, $R, $format, $GLOBALS['root']->moneysign, $R->mode == $R::INPUT ? 'USD' : null);
	}
}
