<?php
/**
* String is one of the most commonly used and straightforward types.
* We allow field-defined PCRE pattern validation and size specification (an input format hint)
*
* The methods in this abstract subclass are all described in the base class Type, so don't expect a lot of comments about them here.
*
* All original code.
* @package Synthesis/Base
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
abstract class t_string extends Type
{
	public static $defhints = array(
		 'pattern'=>"(regex) Text entered for this field will be required to conform to this expression."
	);

	const SIZE = 32, PATTERN = ".*"; // assumed values for pattern and size

	public static function render( Instance $d, $fn, HTMLRendering $R, $format = null )
	{
		$fd = $d->{¶.$fn};
		$value = htmlentities($d->$fn);
		if ($fd['identifying'])
			$identifying = ' identifying';

		if ($R->mode != $R::INPUT || $R->readonly || $format == '*readonly' || $fd['readonly'])
			return "<span class=\"fn-$fn$identifying\" id=\"$R->idprefix$fn\">$value</span>";

		$pattern = $fd['pattern'] ? "$fd[pattern]" : static::PATTERN;
		$size = is_numeric($fd['size']) ? $fd['size'] : static::SIZE;
		$autofill = isset($fd['autofill']) ? 'on' : ($fd['autofill'] ? 'on' : 'off');
		if (is_null($value))
			$value = htmlentities($f['initial']);
		$cid = "$R->idprefix$fn";
		$ifreq = ($fd['identifying'] || $fd['required']) * 1;
		if ($pattern != ".*" || $ifreq) {
			// If a value is provided it must always match the pattern. If not required, it may be empty.
			$R->addScript(<<<js
function checkstring(c,p,r){
	fg=$(c).parents('.form-group');
	(c.value&&null!=c.value.match(p))||(!c.value&&!r) ?
		fg.removeClass('has-error') :
		fg.addClass('has-error')
}
js
				, "t_string validator");
			$R->addReadyScript(<<<js
(c=(\$('#$cid')).on('change',function(e){checkstring(this,/$pattern/,$ifreq)}));
\$(c.prop('form')).on('submit',function(e){if(\$('#$cid').parents('.form-group').hasClass('has-error'))e.preventDefault()});
checkstring(c[0],/$pattern/,$ifreq);
js
				);
		}
		return "<input class=\"form-control t_string fn-$fn$identifying\" autocomplete=\"$autofill\" $R->tabindex id=\"$cid\" type=\"text\" size=\"$size\" name=\"$fn\" value=\"$value\" placeholder=\"{$d->{¬.$fn}}\"/>";
	}

	public static function accept( $value, $format = null )
	{
		$pattern = is_array($format) ? $format['pattern'] : ($format ? $format : static::PATTERN);
		if ($value && !preg_match("/$pattern/", $value))
			throw new BadFieldValueX($format, "The value provided was not in an appropriate format");
		return parent::accept($value, $format); // check for evil in strings
	}

	public static function mysql_ddl( array $fd, Database $db = null )
	{
		if (!$db)
			$db = $GLOBALS['root'];
		$comment = "COMMENT '". preg_replace("/^t_/", "", get_called_class()) .": ". $db->dbEscapeString($fd['label']) ."'";
		$nuller = $fd['identifying'] || $fd['required'] || $fd['notnull'] ? 'NOT NULL' : null;
		$default = $fd['initial'] ? "DEFAULT '". $db->dbEscapeString($fd['initial']) ."'" : null;
		return "`$fd[name]` VARCHAR(255) $nuller $default $comment";
	}
}
