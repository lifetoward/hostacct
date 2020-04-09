<?php
/**
* The methods in this abstract subclass are all described in the base class Type, so don't expect a lot of comments about them here.
*
* All original code.
* @package Synthesis/Contacts
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
abstract class t_url extends t_string
{
	const PATTERN = '^([a-zA-Z]{2,12}:\/\/)(([-\w]+\.)+[a-zA-Z]+)+(:\d+)?(\/.*)?$';

	public static function render( Instance $d , $fname, HTMLRendering $R = null )
	{
		if ($R->mode == $R::INPUT)
			return parent::render($d, $fname, $R);

		$addr = htmlentities($d->$fname);
		return $addr ? '<a href="'. $addr .'" title="This will launch in a new window." target="_blank">( Click to launch )</a>' : null;
	}

}
