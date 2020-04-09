<?php
/**
* Canonical is (string)HH:MM:SS LOCAL 24-hour
* Here are the field definition hints:
* format (12hr) - 24hr or 12hr, with optional "+s" to specify you want the seconds to appear. With 24hr, the leading hour 0 will be included, and with 12hr it won't.
*
* We accept values like [H]H:MM[:SS][am|pm].
* We're considering also accepting values like "5 minutes from now" etc. but we've not implemented that yet.
*
* The methods in this abstract subclass are all described in the base class Type, so don't expect a lot of comments about them here.
*
* All original code.
* @package Synthesis/Contacts
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
abstract class t_timeofday extends Type
{
	// BEGIN PORTED FORWARD TO v4

	public static function accept( $value, array $fd )
	{
		if (is_array($value)) {
			$h = $value['hour'] * 1;
			$m = $value['minute'] * 1;
			$mer = $value['ampm'];
			$s = $value['second'] * 1;
		} else if (Is_string($value)) {
			if (!preg_match('/^(([012])?\d):([0-5]\d)(:([0-5]\d))? ?([aApP][mM])?$/', $value, $a))
				throw new BadFieldValueX($fd, "Not a recognizable time of day ([H]H:MM[:SS][am|pm]).");
			list($h, $x, $m, $y, $s, $mer) = $a;
		}
		if ($mer = mb_strtolower($mer))
			if ($mer == 'pm')
				$h += 12;
		if ($h > 23)
			throw new BadFieldValueX($fd, "Hour must be 0-23H or 1-12am or pm");
		return sprintf("%02d:%02d:%02d", $h*1, $m*1, $s*1);
	}

	public static function mysql_ddl( array $fd, Database $db = null )
	{
		if (!$db)
			$db = $GLOBALS['root'];
		$comment = "COMMENT 'timeofday: ". $db->dbEscapeString($fd['label']) ."'";
		$notnull = $fd['required'] ? "NOT" : "DEFAULT";
		return "`$fd[name]` TIME $notnull NULL $comment";
	}

	public static function minute_of_day( /* Instance */ $d, $fname = null )
	{
		list($hours, $mins, $secs) = explode(':', $d instanceof Instance ? $d->$fname : $d);
		return $hours * 60 + $mins;
	}

	/**
	* Format=>Produces: 24=>23:45H, 24+s=>23:45:00H, 12=>9:45pm, 12+s=>9:45:00pm
	* If you pass a plain value as first arg, the second arg can be a format.
	* Otherwise format is taken from the field definition.
	* Format "12" is presumed.
	*/
	public static function format( /* Instance */ $d, $fn = null )
	{
		if ($d instanceof Instance) {
			$time = $d->$fn;
			$format = $d->{¶.$fn}['format'];
		} else {
			$time = $d;
			$format = $fn;
		}
		if (!$time)
			return null; // I'd rather set time to "NOW" here...
		list($hrs, $wsecs) = explode('+', $format);
		list($h,$m,$s) = explode(':', $time);
		if ($hrs*1 != 24) { // this means we're using 12-hour format
			if (!($hour = $h % 12))
				$hour = 12;
			$suffix = $h < 12 ? 'am' : 'pm';
			$hfmt = "%d";
		} else { // this is for 24-hour format
			$hour = $h;
			$suffix = 'H';
			$hfmt = "%02d";
		}
		$secs = $wsecs[0] == 's' ? "%02d" : null;
		return sprintf("$hfmt:%02d$secs", $hour, $m, $s) ."$suffix";
	}

	public static function render( Instance $d, $fname, HTMLRendering $R )
	{
		if ($R->mode != $R::INPUT)
			return htmlentities(static::format($d->$fname));

		list($hour, $minute, $second) = explode(':', $d->$fname);
		$fd = $d->getFieldDef($fname);
		$mindex = floor($fd['minuterval'] * 1);
		if ($mindex > 30 || $mindex < 1)
			$mindex = 1;
		$r = "<div class=\"form-control\" style=\"border:none\" id=\"$R->idprefix$fname\" tabindex=\"-1\"><select $R->tabindex size=\"1\" id=\"$R->idprefix$fname-hour\" name=\"{$fname}[hour]\">";
		for ($x = 1; $x <= 12; $x++)
			$r .= '<option value="'. ($x%12) .'"'. ( ($x%12)==($hour%12) ? ' selected=""' : null ) .">$x&nbsp;</option>";
		$r .= "</select><select $R->tabindex size=\"1\" id=\"$R->idprefix$fname-minute\" name=\"${fname}[minute]\">";
		for ($x = 0; $x < 60; $x += $mindex)
			$r .= '<option value="'. sprintf('%02d',$x) .'"'. ($x==$minute ? ' selected=""' : null) .'>'. sprintf("%02d", $x) .'&nbsp;</option>';
		return $r ."</select><select $R->tabindex size=\"1\" id=\"$R->idprefix$fname-ampm\" name=\"${fname}[ampm]\">".
			'<option value="am"'. ($hour < 12 ? ' selected="yes"' : null) .'>am&nbsp;</option><option value="pm"'. ($hour > 11 ? ' selected="yes"' : null) .'>pm&nbsp;</option></select></div>';
	}

}
