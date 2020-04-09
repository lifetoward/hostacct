<?php
/**
* t_id
* When you want to store a reference to an Element, but the class is variable, use this type.
* We store the ID of the Element you want to reference. It's up to your instance class to let us know what
* class that Element is supposed to be. Whenever we want to know what class to use, we call $d->getIDClass($fn).
* If we get a working class back from this call, we will treat the field value as an Element instance of the proper class,
* and working with a field of this type will be very much like working with a field of type 'instance'.
* If we can't resolve the class, we just return the Element's id as a number and leave you to puzzle it out.
*
* Created: 3/21/15 for Lifetoward LLC
*
* All original code.
* @package Synthesis/Base
* @author Biz Wiz <bizwiz@SyntheticWebApps.com>
* @copyright (c) 2015 Lifetoward LLC; All rights reserved.
* @license proprietary
*/
abstract class t_id extends Type
{
	public static function format( Instance $d , $fn )
	{
		$val = $d->$fn;
		if (!$val)
			return $d->_rendering && $d->_rendering->mode == HTMLRendering::COLUMNAR ? '' : '( not set )';
		$class = $d->getIDClass($fn);
		$obj = $class::get($val);
		return "$obj";
	}

	// Note that we cannot do validation of the value's class here because we don't have access to the Instance.
	public static function accept( $value, array $fd )
	{
		if (!$value)
			return null;
		if (is_object($value))
			$value = $value->_id;
		if (!is_int($value) || $value < 1)
			throw new BadFieldValueX($fd, "You must provide a stored Element instance or the ID of one.");
		return $value;
	}

	public static function accept_db( $value )
	{
		return $value ? $value * 1 : null;
	}

	public static function put_db( $value, Database $db )
	{
		return $value ? $value : 'NULL';
	}

	public static function mysql_ddl( array $fd )
	{
		return "`$fd[name]` INT(10) UNSIGNED DEFAULT NULL COMMENT 'id: $fd[label]'";
	}

}
