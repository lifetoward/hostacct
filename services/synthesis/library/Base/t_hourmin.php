<?php
/**
* This type represents a span of time in hours and minutes. It is not a time of day, but rather an interval sizing in hours and minutes
* Acceptable formats are described by [HHH]:MM[:SS]; SS portion is not used in form submission or input controls
* Normally H{,3}:MM is expected. If there's no colon, we interpret the value based on the setting of the numeric attribute.
*
* Canonical is (string)[H]HH:MM:SS
*
* Relevant optional field attributes:
*	- range ('00:00-24:00') - The inclusive lower and exclusive upper limits on allowed values; you can use hh:mm or just a number of minutes. Min min is 00:00. (As an interval, we don't do negative values.)
*		Max max is 840:00 (1 week "hence"); if you exceed this we clip it there.
*	- required (false) - When set, a value is required during accept and during form validation.
*	- initial ('01:00') - Initial value to use when a value is required and none is set - note that selectors input implies initialization via the input rendering
*	- format ('hh:mm') - We can produce 'hh:mm', 'hours' (with up to 2 decimal places), and 'minutes' (integer). Units are included when formatted as hours or minutes.
*	- numeric ('hours') - How to interpret and return a simple numeric value, either as whole 'minutes' or decimal-allowed 'hours' (rounded to the nearest minute and 2 decimal places)
*	- input ('selectors') - Determines the style used for rendering the input field.
*		"selectors" means using 2 selector fields containing Hour and Minute.
*		"text" means taking text input with typing limited to digits and one optional colon, or a single decimal if numeric is hours. See below for allowed formats
*	- minuterval (5) - The number of minutes in each selectable step, ie. preceision of minute selector - applies to input selectors rendering only.
*
* All original code.
* @package Synthesis/Base
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
abstract class t_hourmin extends Type
{
	public static function format( /* Instance */ $d, $fname = null, $format = null )
	{
	}

	public static function render( Instance $d, $fname, HTMLRendering $R )
	{
		list($h,$m,$s) = explode(':', $d->$fname, 3);
		if ($R::INPUT != $R->mode)
			return htmlentities("$h:$m");

		$R->addStyles("select.hourmin { }", "t_hourmin");
		$R->addScript("var hourmin = new Array();
function set_hourmin(f) {
hourmin[f][0].value=hourmin[f][1].value+':'+hourmin[f][2].value+':00';
capcel_validated(hourmin[f][0],f,1);
}
");
		$f = $d->getFieldDef($fname);
		if (!($minuterval = $f['minuterval'] * 1))
			$minuterval = 5;
		if ($h == 0 && $m == 0 && ($f['initial'] || $f['range'])) {
			$h = $f['initial'];
			if ($h < 1 && $f['range'])
				list($h,$max) = explode('-', $f['range']);
			$h = sprintf("%02d", $h);
		}
		for ($x = 0; $x < 24; $x++)
			$hrsopts .= '<option value="'. sprintf("%02d", $x) .'"'. ($x==$h ? ' selected=""' : null) .'>'. sprintf("%02d", $x) .'</option>';
		for ($x = 0; $x < 60; $x+=$minuterval)
			$minopts .= '<option value="'. sprintf("%02d", $x) .'"'. ($x==$m ? ' selected=""' : null) .'>'. sprintf("%02d", $x) .'</option>';
		return <<<end
<input type="hidden" name="$fname" id="$R->idprefix$fname" value="{$d->$fname}"/>
<select $R->tabindex id="$R->idprefix{$fname}_hrs" onchange="set_hourmin('$fname')" class="hourmin">$hrsopts</select>:<select id="$R->idprefix{$fname}_min" class="hourmin" onchange="set_hourmin('$fname')">$minopts</select>
<script>hourmin['$fname']=[document.getElementById('$R->idprefix$fname'),document.getElementById('$R->idprefix{$fname}_hrs'),document.getElementById('$R->idprefix{$fname}_min')];set_hourmin('$fname');</script>
end;
		}
	}

	public static function accept( $value, array $fd )
	{
		if ($fd['format'] == 'hours') {
			if (!preg_match('/^(\d{1,3})(\.\d{,2})$/', $value, $a))
				throw new BadFieldValueX($fd, "Not a valid time value (hours, [HH]H[.hh]).");
			$h = 1*$a[1];
			$m = 60*$a[2];
		} else if ($fd['format'] == 'minutes') {
			if (!preg_match('/^\d{1,3}$/', $value, $a))
				throw new BadFieldValueX($fd, "Not a valid time value (minutes, [MM]M).");
			$h = floor($a[0]/60);
			$m = $a[0]%60;
		} else {
			if (!preg_match('/^(\d{,3}):([0-5]\d)(:([0-5]\d))?$/', $value, $a))
				throw new BadFieldValueX($fd, "Not a valid time value ([HHH]:MM[:SS])");
			list($x, $h, $m, $y, $s) = $a;
		}
		return sprintf("%02d:%02d:%02d", $h*1, $m*1, $s*1);
	}

	/**
	* We are numeric as hours, dropping seconds.
	*/
	public static function numeric( Instance $d, $fname )
	{
		list($h,$m,$s) = explode(':', $d->$fname, 3);
		return $h+($m/60);
	}

	public static function mysql_ddl( array $fd, Database $db = null )
	{
		if (!$db)
			$db = $GLOBALS['root'];
		$comment = "COMMENT 'hourmin: ". $db->dbEscapeString($fd['label']) ."'";
		if ($fd['notnull'] || $fd['required'])
			$notnull = "NOT NULL";
		$default = $fd['initial'] ? $db->dbEscapeString($fd['initial']) : '12:00';
		return "`$def[name]` TIME $notnull DEFAULT $default $comment";
	}
}

?>
