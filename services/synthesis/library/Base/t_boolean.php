<?php
/**
* Boolean type: optional field attributes (with defaults):
*	format ("False|True") - The False and True indication strings (for formatting and rendering) separated by a |.
*	required (false) - Set to indicate that a non-answer is unacceptable. Affects input rendering and value acceptance.
*	initial (null) - Determines the initial value to be used in the database record and in the runtime and renderings. Use true, 1, false, 0, or null. If you use null but the required attribute is set then false will be used instead.
*
* Native format is 0, 1, or null.
*
* The methods in this abstract subclass are all described in the base class Type, so don't expect a lot of comments about them here.
*
* All original code.
* @package Synthesis/Base
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
abstract class t_boolean extends Type
{
	/**
	* Pass an instance, a field name, and an optional format
	* 	-or-
	* Pass a value and an optional format.
	*/
	// v4 OK
	public static function format( /* Instance */ $d, $fname = null, $format = null )
	{
		$values = self::get_values($d, $fname, $format);
		return is_null($values[3]) ? $values[2] : $values[$values[3]];
	}

	// v4 OK
	private static function get_values($d, $field, $format)
	{
		if ($d instanceof Instance) {
			$raw = $d->$field;
			$field = $d->getFieldDef($field);
			if (is_null($raw) && isset($field['initial']))
				$raw = $field['initial'];
			$format = $field['format'];
		} else {
			$raw = $d;
			$format = $field;
			$field = array('format'=>$format);
		}
		$value = is_null($raw) ? null : $raw * 1;

		if (!mb_strpos($format, '|'))
			$format = "False|True";

		return array_merge(explode('|', $format, 2), array('( ? )', $value));
	}

	// v4 OK
	public static function render( Instance $d, $fn, HTMLRendering $R, $format = null )
	{
		$fd = $d->getFieldDef($fn);
		if ($R->mode != $R::INPUT || $R->readonly || $fd['readonly'] || $format == '*readonly')
			return htmlentities(self::format($d, $fn, $format));

		$values = self::get_values($d, $fn, $format);
		$opts = '<option value="1"'. ($values[3] ? ' selected="1"' : null) .">$values[1]</option>".
			'<option value="0"'. ($values[3] !== null && !$values[3] ? ' selected="1"' : null) .">$values[0]</option>";
		$nullopt = $fd['required'] && $values[3] !== null ? null : "<option value=\"\">$values[2]</option>";
		$cid = "$R->idprefix$fd[name]";
		if ($fd['required'] && $values[3] === null) {
			$R->addReadyScript("(c=\$('#$cid'))".
				".one('change',function(){\$('option[value=\"\"]',this).remove();\$(this).removeClass('invalid').attr('title',null)})".
				";\$(c.prop('form')).on('submit',function(e){if(!\$('#$cid').val().length)e.preventDefault()});");
			$invalid = ' class="invalid" title="You must choose a value"';
		}
		return "<select class=\"form-control\" $R->tabindex id=\"$cid\" name=\"$fd[name]\" size=\"1\"$invalid>$nullopt$opts</select>$script";
	}

	// v4 OK
	public static function accept( $value, array $fd )
	{
		if (null === ($test = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE))) // this covers all reasonable boolean indicators, though we always expect "1" and "0" from our own forms
			throw new BadFieldValueX($fd, "Not a recognizable boolean value");
		return $test * 1;
	}

	// v4 OK
	public static function numeric( Instance $d, $fname )
	{
		if (is_null($x = $d->$fname))
			return null;
		return $x * 1;
	}

	// v4 OK
	public static function put_db( $value )
	{
		return $value === null ? 'NULL' : $value * 1;
	}

	public static function accept_db( $value )
	{
		return isset($value) ? ($value * 1) : null;
	}

	// v4 OK
	public static function mysql_ddl( array $fd, Database $db = null )
	{
		if (!$db)
			$db = $GLOBALS['root'];
		$comment = "COMMENT 'boolean: ". $db->dbEscapeString($fd['label']) ."'";
		$nuller = $fd['identifying'] || $fd['required'] || $fd['notnull'] ? 'NOT NULL' : null;
		$default = "DEFAULT ". (is_null($fd['initial']) && !$nuller ? 'NULL' : ($fd['initial'] ? 1 : 0));
		return "`$fd[name]` TINYINT(1) $nuller $default $comment";
	}

}
