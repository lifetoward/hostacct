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
abstract class t_email extends t_string
{
	const PATTERN = '^([-_!"#$%&\'*+\/0-9=?@a-zA-Z`\{\|\}~\.^]+)(@([-a-z0-9A-Z_]*\.)+[a-zA-Z]+)+$';

	public static function render( Instance $d , $fname, HTMLRendering $R = null )
	{
		if ($R->mode == $R::INPUT)
			return parent::render($d, $fname, $R);

		$addr = htmlentities($d->$fname);
		return $addr ? '<a href="mailto:'. $addr .'">'. $addr .'</a>' : null;
	}

}
