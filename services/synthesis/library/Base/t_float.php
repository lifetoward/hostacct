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
abstract class t_float extends Type
{
	public static function accept( $value, $fd )
	{
		if (!is_numeric($value))
			throw new BadFieldValueX('Value must be numeric');
		$value *= 1;
		list($min, $max, $step, $left, $right) = static::get_range($fd['range'], $fd['format']);
		if ($value < $min || $value > $max)
			throw new BadFieldValueX('Value must be > $min and < $max');
		return round($value, $right);
	}

	protected static function get_range($range, $format)
	{
		if (!preg_match('/([0-9]+)?:([0-9]+)?/', $range))
			$range = null;
		$range = explode(':', $range);
		if (!preg_match('/[0-9]+(.[0-9]+)?/', $format))
			$format = "10.2";
		list($left, $right) = explode('.', $format);

		$absmax = pow(10, $left) - ($step = 1/pow(10,$right));
		return array(
			(!is_numeric($range[0]) || $range[0] <= -$absmax) ? -$absmax : $range[0],
			(!is_numeric($range[1]) || $range[1] >= $absmax) ? $absmax : $range[1],
			$step, $left, $right);
	}

	/**
	* This method is useful for making the number pretty, ie. with comma separators if the format is set to "delim"
	*/
	public static function format( /* Instance */ $d, $field = null, $format = null )
	{
		if ($d instanceof Instance) {
			$value = $d->$field;
			$format = $format ? $format : $d->{¶.$field}['format'];
		} else {
			$value = $d;
			$format = $field;
		}
		if ($value === null)
			return "(no value)";
		$value *= 1;
		if ($format == 'delim')
			return number_format($value);
		return "$value";
	}

	public static function render( Instance $d, $fn, HTMLRendering $R )
	{
		if ($R->mode != $R::INPUT)
			return $d->$fn;

		$fd = $d->getFieldDef($fn);
		if (!$d->_stored && $d->$fn === null && $fd['initial'])
			$d->$fn = $fd['initial'];
		$required = $fd['required'] ? '1' : '0';
		list($min, $max, $step, $left, $right) = static::get_range($fd['range'], $fd['format']);

		return "<input title=\"$min - $max\" class=\"input form-control\" $R->tabindex id=\"$R->idprefix$fn\" type=\"text\" size=\"". ($left+$right+1) ."\" name=\"$fn\" value=\"{$d->$fn}\"".
			"$R->readonly onchange=\"valid_decimal(this,'$fn',$left,$right,$min,$max,$required)\"/>$fd[units] <img src=\"images/invalid.png\"/>".
			"<script>valid_decimal(document.getElementById('$R->idprefix$fn'),'$fn',$left,$right,$min,$max,$required);</script>";
	}

	public static function accept_db( $value )
	{
		return $value * 1;
	}

	public static function put_db( $value, Database $db = null )
	{
		return is_null($value) ? 'NULL' : $value * 1;
	}

	public static function numeric( Instance $d, $fname )
	{
		return $d->$fname * 1;
	}

	public static function mysql_ddl( array $fd, Database $db = null )
	{
		if (!$db)
			$db = $GLOBALS['root'];
		$comment = "COMMENT 'float: ". $db->dbEscapeString($fd['label']) ."'";
		if ($fd['notnull'] || $fd['required'])
			$notnull = "NOT NULL";
		if ($fd['initial'])
			$default = "DEFAULT $fd[initial]";
		list($min, $max, $step, $left, $right) = self::get_range($fd['range'], $fd['format']);
		$format = sprintf("%d.%d", $left+$right, $right);
		if ($min >= 0)
			$unsigned = "UNSIGNED";

		return "`$fd[name]` FLOAT $unsigned $notnull $default $comment";
	}

}

?>
