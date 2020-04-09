<?php
/**
* t_passhint
* Password hints are a way to employ mnemonics in a systematic way to effectively obfuscate passwords without making them cumbersome to reference.
* We systematize using hashlike memory techniques while retaining all the value of strong passwords.
*
* Features:
* 1. We add an extra layer of owner-checking on format (and thereby render). 
* 2. You can't just view it passively. You must hover over the rendering in output modes to view the title.
*
* Created: 1/9/15 for Lifetoward LLC
*
* All original code.
* @package Synthesis/Identities
* @author Biz Wiz <bizwiz@SyntheticWebApps.com>
* @copyright (c) 2015 Lifetoward LLC; All rights reserved.
* @license proprietary
*/
abstract class t_passhint extends t_string
{
	public static function format( /* Instance */ $d , $fname = null )
	{
        if ($GLOBALS['root']->getAuthId())
            $val = $d instanceof Instance ? $d->$fname : $d;
        else
            $val = "( private )";
		return is_array($val) ? implode(' ', $val) : "$val";
	}

	public static function render( Instance $d, $fname, HTMLRendering $R )
	{
		if ($R->mode == $R::INPUT)
			return parent::render($d, $fname, $R);
		return $d->$fname ? '<span title="'. htmlentities(static::format($d, $fname)) ."\">(&nbsp;Hover&nbsp;to&nbsp;view&nbsp;)</span>" : "";
	}

	public static function numeric( Instance $d, $fn )
	{
		return count((array)$d->$fn) * 1;
	}

}
