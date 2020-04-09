<?php
/**
* Integer with range limitation. Relevant optional field attributes:
*	- range ('0:10000') - Allowed values during accept() and form input. You can use negative numbers, so separator is :. Limits are inclusive.
* 		Note that because we optimize storage, if you set the value directly and outside the allowed range and then store it, it may overflow the storage and yield a different number when loaded back.
* Signed values are possible if you set the range minimum less than 0 or non-numeric (latter case meaning, negative structural minimum).
*
* We accept comma-delimited input for the initial value, in forms, and during accept. Commas are always just removed, not checked.
*
* All original code.
* @package Synthesis/Base
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
abstract class t_integer extends t_decimal
{
	const MIN=0, MAX=10000, FORMAT='9';

	protected static function get_range( $range, $format )
	{
		return parent::get_range($range, strstr($format, '.', true)); // we prevent right-side decimal places
	}

	public static function render( Instance $d, $fn, HTMLRendering $R )
	{
		$fd = $d->getFieldDef($fn);
        if (null === $d->$fn)
            return null;
		if ($R->mode != $R::INPUT || $R->readonly || $fd['derived'] || $fd['readonly'])
			return "<span id=\"$R->idprefix$fn\">{$d->{°.$fn}}</span>";
		return parent::render($d, $fn, $R);
	}

	public static function mysql_ddl( array $fd, Database $db = null )
	{
		if (!$db)
			$db = $GLOBALS['root'];
		extract(static::get_range($fd['range'], $fd['format']));
		$comment = "COMMENT '". get_called_class() ."($scale.): ". $db->dbEscapeString($fd['label']) ."'";
		$unsigned = $min >= 0 ? "UNSIGNED" : null;
		if (($unsigned && $max > 4294967295) || (!$unsigned && $max > 2147483647) || (is_numeric($min) && $min < -2147483648))
			$intsize = 'BIGINT';
		else if (($unsigned && $max > 16777215) || (!$unsigned && $max > 8388607) || (is_numeric($min) && $min < -8388608))
			$intsize = 'INT';
		else if (($unsigned && $max > 65535) || (!$unsigned && $max > 32767) || (is_numeric($min) && $min < -32768))
			$intsize = 'MEDIUMINT';
		else if (($unsigned && $max > 255) || (!$unsigned && $max > 127) || (is_numeric($min) && $min < -128))
			$intsize = 'SMALLINT';
		else
			$intsize = 'TINYINT';
		$nullmode = $fd['required'] ? "DEFAULT 0 NOT" : "DEFAULT";
		return "`$fd[name]` $intsize($scale) $unsigned $nullmode NULL $comment";
	}
}
