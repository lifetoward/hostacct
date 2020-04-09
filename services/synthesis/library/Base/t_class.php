<?php
/**
* t_class
* The class type is for saving the name of an Instance class. Combined with t_id, for example, it's a way to have a more virtual but still managed variability to references.
*
* Created: 3/21/15 for Lifetoward LLC
*
* All original code.
* @package Synthesis/Base
* @author Biz Wiz <bizwiz@SyntheticWebApps.com>
* @copyright (c) 2015 Lifetoward LLC; All rights reserved.
* @license proprietary
*/
abstract class t_class extends t_string
{
	const PATTERN = '^\w*$';

	public static function format( Instance $d, $fn )
	{
		$base = $d->{¶.$fn}['base'];
		$value = $d->$fn;
		return $value && class_exists($value) && is_subclass_of(parent::accept($value, $base), $base ? $base : 'Instance') ? "[ $value ]" : null;
	}

	public static function accept( $value, array $fd )
	{
		if (!$value)
			return null;
		if (!class_exists($value) || !is_subclass_of(parent::accept($value, $fd), $base = $fd['base'] ? $fd['base'] : 'Instance'))
			throw new BadFieldValueX($fd, "Class '$value' is not known to the system or is not a subclass of '$fd[base]'.");
		return $value;
	}

	public static function numeric( Instance $d, $fn )
	{
		return null;
	}

}
