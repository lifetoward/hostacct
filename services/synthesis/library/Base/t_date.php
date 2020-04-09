<?php
/**
* Date type; here are the relevant optional field attributes (with defaults):
*	- format ("D j M 'y") - The "readable" formatted or rendered appearance of the date value; refer to http://us1.php.net/manual/en/function.date.php for codes
*	- input ("picker") - Input rendering approach. Currently there's "picker" which pops up a calendar, and there's "selectors" which uses 3 selectors for year, month, and day,
*		and there's "text" which takes text input and is only validated on the server.
*	- yearrange ('1900-2050') - Input rendering contraint which applies only to selectors style.
*		If you supply only a beginning or end ("1960-" or "-2020") the other limit will be taken from the default or the year given if necessary (-1899 yields 1899-1899).
*	- required (null) - If set, then a date is required for accept and input operations.
*	- initial ('today') - You can provide the initial value in any parsable form, as with accept() and format(). See strtotime(): http://us1.php.net/manual/en/datetime.formats.php
*
* The methods in this abstract subclass are all described in the base class Type, so don't expect a lot of comments about the methods in general here.
*
* All original code.
* @package Synthesis/Base
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
abstract class t_date extends Type
{
	const InputWidth = 4;
	const STANDARD ="D j M 'y";
	const VERBOSE = "l j F Y";
	const NATIVE = 'Y-m-d'; // this must not change
	const DEFAULTMAXYEAR = 2050;
	const DEFAULTMINYEAR = 1900;

	/**
	* We can accept most English interpretable date formats if you're just wanting to reformat an existing string.
	* We use strtotime() which you can read about here: http://us1.php.net/manual/en/datetime.formats.php
	* v4 OK
	*/
	public static function format( /* Instance */ $d, $fname = null, $format = null )
	{
		if ($d instanceof Instance) {
			$value = $d->$fname;
			$fd = $d->getFieldDef($fname);
			if (is_null($value) && $fd['required'] && isset($fd['initial']))
				$value = $fd['initial'];
			if (!$format)
				$format = $fd['format'];
		} else {
			$value = $d;
			$format = $fname ? $fname : self::STANDARD;
		}
		if (!$format)
			$format = self::STANDARD;
		if (!$value || $value == '0000-00-00' || $value == '*NULL')
			return null;
		return date($format, strtotime($value));
	}

	/**
	* Because several attibutes can apply during rendering we accept $format as an overriding array. We also accept it as a simple string, in which case it just overrides the format attribute.
	* v4 OK
	*/
	public static function render( Instance $d, $fname, HTMLRendering $R, $format = 0 )
	{
		$fd = $d->getFieldDef($fname);
		if (is_array($format))
			$fd = array_merge($fd, $format);
		else if ($format)
			$fd['format'] = $format;
		if (!$fd['format'])
			$fd['format'] = self::STANDARD;

		if ($R::VERBOSE == $R->mode)
			return htmlentities(self::format($d, $fname, $fd['format'] ? $fd['format'] : self::VERBOSE));

		if ($R->mode != $R::INPUT || $R->readonly || $fd['readonly'] || $format == '*readonly')
			return htmlentities(self::format($d, $fname, $fd['format'] ? ($R->mode == $R::COLUMNAR ? self::STANDARD : $fd['format']) : self::STANDARD));

		if ($fd['input'] == 'selectors')
			return self::input_selectors($R, $d->$fname, $fd);
		else if ($fd['input'] == 'text')
			return self::input_text($R, $d->$fname, $fd);
		else
			return self::input_picker($R, $d->$fname, $fd);
	}

	/**
	* For input rendering only.
	* Note that this is not where we accept a variety of input formats. We only recognize and accept values in NATIVE format.
	* Does not modify value if it comes in correctly formatted. It may be outside the year range, for example, and we allow that if it's a properly formatted value.
	* 	We also allow null to pass thru if a value is not required. If it is required, null gets changed to initial value, or lacking that, today.
	* Returns an array with DETERMINED yrmin, yrmax, and value (in native string format) to use extractable.
	* v4 OK
	*/
	private function get_values( $value, array $fd )
	{
		if (preg_match("/([0-9]{4})?-([0-9]{4})?/", $fd['yearrange'], $yrs))
			list($junk, $yrmin, $yrmax) = $yrs;
		if (!$yrmin)
			$yrmin = $yrmax && $yrmax < self::DEFAULTMINYEAR ? $yrmax : self::DEFAULTMINYEAR;
		$yrmin *= 1;
		if (!$yrmax)
			$yrmax = $yrmin > self::DEFAULTMAXYEAR ? $yrmin : self::DEFAULTMAXYEAR;
		$yrmax *= 1;

		if (!$value || $value == '0000-00-00')
			$value = $fd['required'] ? date(self::NATIVE, strtotime($fd['initial'] ? $fd['initial'] : 'today')) : null;

		return compact('yrmin','yrmax','value');
	}

	/**
	* When you use "simple" input, there's no validation done in the browser except for ensuring something has been typed if we require a value. We use strtotime() to parse it during acceptance.
	* v4 OK
	*/
	public function input_text( HTMLRendering $R, $value, array $fd )
	{
		extract(self::get_values($value, $fd)); // gets $yrmin, $yrmax, $value
		$cid = "$R->idprefix$fd[name]";
		if ($fd['required']) {
			$invalid = ' onchange="if(this.value.length)$(this).removeClass(\'invalid\');else $(this).addClass(\'invalid\')"'. ($value ? null : ' class="invalid"');
			$script = "<script>$('#$cid').prop('form').on('submit',function(e){if(!this.value.length)e.preventDefault()});</script>";
		}
		return '<input type="text" size="12" id="'. "$cid\" class=\"t_date fn-$fd[name]\" name=\"$fd[name]\" value=\"". htmlentities(self::format($value, $fd['format'])) ."\"$invalid".
			' title="'. htmlentities('Enter a reasonable date description such as "5/10/2014" or "today" or "next thursday" or "1 week from now", etc.') ."\"/>$script";
	}

	/**
	* We decided there's no reason to keep this hidden from the public, but we expect all parameters to appropriately specified.
	* v4 OK
	*/
	public function input_selectors( HTMLRendering $R, $value, array $fd )
	{
		extract(self::get_values($value, $fd)); // gets $yrmin, $yrmax, $value, and $format
		list($year, $month, $day) = explode('-', $value ? $value : date(self::NATIVE));
		for ($x = 1; $x <= 31; $x++)
			$days .= sprintf('<option value="%02d"'. ($day==$x?' selected=""':null) .'>%02d&nbsp;</option>', $x, $x);
		$monthlist = array(1=>'January','February','March','April','May','June','July','August','September','October','November','December');
		for ($x = 1; $x <= 12; $x++)
			$months .= sprintf('<option value="%02d"'. ($month==$x?' selected=""':null) .'>'. $monthlist[$x] .'&nbsp;</option>', $x);
		for ($x = $yrmax; $x >= $yrmin; $x--)
			$years .= '<option value="'. $x .'"'. ($year==$x?' selected=""':null) .">$x&nbsp;</option>";
		$id = "$R->idprefix$fd[name]";
		$R->addScript(<<<jscript
var dpm=[0,31,29,31,30,31,30,31,31,30,31,30,31];
function sync_datesel(p){
	var mv=\$('#{$id}_month',p).val();
	var yv=\$('#{$id}_year',p).val();
	var d=\$('#{$id}_day',p);
	var dv=d.val();
	if(dv>dpm[mv])
		d.val(dpm[mv]);
	if(mv==2&&dv==29&&(yv%4||(!yv%100&&yv%400))) // not leap year
		d.val(dv-1);
	\$('#{$id}',p).val(yv+'-'+mv+'-'+d.val())
}
jscript
			, 't_date selectors');
		$required = $fd['required'] || $fd['identifying'] || $fd['notnull'];
		if (!$required || $fd['nullok']) {
			$nullButton = '	<button '. $tabindex .' type="button" id="'. $id .'_nuller" title="Click to clear or activate a value" onclick="toggleDateSel($(this))"><span class="glyphicon glyphicon-remove"></span></button>'."\n";
			$R->addScript(<<<jscript
function toggleDateSel(\$b){
	btxt=' No date; Click to specify a value ';
	if (\$b.text()==btxt) {
		\$b.siblings('.input').show();
		\$b.html('<span class="glyphicon glyphicon-remove"></span>');
		sync_datesel(\$b.parent());
		\$('#{$id}_day',\$b.parent()).focus() }
	else {
		\$b.siblings('.input').hide();
		\$b.html(btxt);
		\$('#$id',\$b.parent()).val("") }
}
jscript
			,'t_date selector null');
			if (!$value)
				$R->addReadyScript("\$('#${id}_nuller').click();");
		}
		return <<<html
<div class="form-control fn-$fd[name] t_date"><input type="hidden" value="$value" id="$id" name="$fd[name]"/>
	<select class="input" size="1" $tabindex id="{$id}_day" onchange="sync_datesel($(this).parent())">$days</select>
	<select class="input" size="1" $tabindex id="{$id}_month" onblur="sync_datesel($(this).parent())">$months</select>
	<select class="input" size="1" $tabindex id="{$id}_year" onchange="sync_datesel($(this).parent())">$years</select>
$nullButton</div>
html;
	}

	/**
	* We decided there's no reason to keep this hidden from the public, but we expect all parameters to appropriately specified.
	* v4 OK
	*/
	public function input_picker( HTMLRendering $R, $value, array $fd )
	{
		extract(self::get_values($value, $fd)); // gets $yrmin, $yrmax, $value
		$cid = "$R->idprefix$fd[name]";
		$readable = htmlentities(self::format($value, $fd['format']));
		$outFormat = self::php2jqdate($fd['format']);
		if ($fd['identifying'])
			$identifying = " identifying";
		if ($fd['required'] || $fd['identifying']) {
			if (!$value) {
				$placeholder = ' Required !';
				$invalid = ' class="hasError"';
				$onscript = ".one('change',function(e){\$(this).removeClass('hasError')})".
					";\$(c.prop('form')).on('submit',function(e){if(!\$('#$cid').val().length)e.preventDefault()})";
			} else {
				// If a value is required but we have a value, all will be fine, nothing special to do.
			}
		} else { // if not required
			// We must allow them to unset it and we don't care if there's no value
			$placeholder = " No date";
			$unset = '<div class="input-group-addon" title="Click to unset the date" onclick="$'."('#view_$cid').datepicker('setDate','');\$('#$cid').val('')\">".
				'<span class="glyphicon glyphicon-remove"></span></div>';
		}
/*
	,maxDate:new Date($maxParms2015,12-1,31)
	,minDate:new Date(1991,1-1,1)
*/
		$R->addReadyScript(<<<js
(c=\$('#view_$cid')).datepicker({
	 dateFormat:"$outFormat"
	,altField:$('#$cid')
	,altFormat:'yy-mm-dd'
	,autoSize:true
	,constrainInput:true
	,showOn:'focus'
	,showOtherMonths:true
	,selectOtherMonths:true
	,changeMonth:true
	,changeYear:true
	,hideIfNoPrevNext:true
	,gotoCurrent:true
	,defaultDate:0
//	,showButtonPanel:true // enabling this option manifests a rendering bug in the calendar picker... you lose the top pane in favor of the bottom pane.
	$max$min}).on('keydown',function(e){if(e.which!=9)e.preventDefault();})$onscript;
js
			);
		$R->addStyles(<<<css
div.t_date { cursor:pointer; }
.t_date input { cursor:inherit; }
#ui-datepicker-div { z-index:65000 !important; border:none; }
css
			, 't_date picker');
		return <<<html
<div class="input-group t_date fn-$fd[name]$identifying">
<input id="view_$cid" class="form-control" title="Click or tab here to choose a date" $R->tabindex value="$readable" type="text" placeholder="$placeholder"$invalid/>$unset
</div><input id="$cid" value="$value" name="$fd[name]" type="hidden"/>
html;
	}

	/**
	* We can accept most English interpretable date formats if you're getting your date string from somewhere exotic like free human entry.
	* We use strtotime() which you can read about here: http://us1.php.net/manual/en/datetime.formats.php
	* v4 OK
	*/
	public static function accept( $value, array $fd )
	{
		if (!$value)
			return null;
		if (!($native = date(self::NATIVE, strtotime($value))))
			throw new BadFieldValueX($fd, "Date was unrecognizable");
		list($y, $md) = explode('-', $native, 2);
		if ($fd['yearrange']) {
			list($a,$z) = explode('-', $fd['yearrange']);
			if (($a && $y < $a) || ($z && $y > $z))
				throw new BadFieldValueX($fd, "Year is out of range ($fd[yearrange]).");
		}
		return $native;
	}

	// v4 OK
	public static function mysql_ddl( array $fd, Database $db = null )
	{
		if (!$db)
			$db = $GLOBALS['root'];
		$comment = "COMMENT 'date: ". $db->dbEscapeString($fd['label']) ."'";
		// All initialization actually occurs in code, sometimes UI, sometimes PHP. Anyway, we have no reason to do it in the database, so no default
		return "`$fd[name]` DATE $comment";
	}

	private function php2jqdate( $format )
	{
		static $map = array(
		// Day
			 'd'=>'dd' // day of month w/ lead 0
			,'D'=>'D' // A textual representation of a day, three letters	Mon through Sun
			,'j'=>'d' // Day of the month without leading zeros	1 to 31
			,'l'=>'DD' // (lowercase 'L') A full textual representation of the day of the week	Sunday through Saturday
			,'N'=>'' // ISO-8601 numeric representation of the day of the week (added in PHP 5.1.0)	1 (for Monday) through 7 (for Sunday)
			,'S'=>'' // English ordinal suffix for the day of the month, 2 characters	st, nd, rd or th. Works well with j
			,'w'=>'' // Numeric representation of the day of the week	0 (for Sunday) through 6 (for Saturday)
			,'z'=>'o' // The day of the year (starting from 0)	0 through 365
		//Week	---	---
			,'W'=>'' // ISO-8601 week number of year, weeks starting on Monday (added in PHP 4.1.0)	Example: 42 (the 42nd week in the year)
		//Month	---	---
			,'F'=>'MM' // A full textual representation of a month, such as January or March	January through December
			,'m'=>'mm' // Numeric representation of a month, with leading zeros	01 through 12
			,'M'=>'M' // A short textual representation of a month, three letters	Jan through Dec
			,'n'=>'m' // Numeric representation of a month, without leading zeros	1 through 12
			,'t'=>'' // (Fake... no such thing in JSC) Number of days in the given month	28 through 31
		//Year	---	---
			,'L'=>'' // Whether it's a leap year	1 if it is a leap year, 0 otherwise.
			,'o'=>'' // Approx, no such JSC; ISO-8601 year number. This has the same value as Y, except that if the ISO week number (W) belongs to the previous or next year, that year is used instead. (added in PHP 5.1.0)	Examples: 1999 or 2003
			,'Y'=>'yy' // A full numeric representation of a year, 4 digits	Examples: 1999 or 2003
			,'y'=>'y' // A two digit representation of a year	Examples: 99 or 03
		//Other
			,'U'=>'@' // Seconds since the Unix Epoch (January 1 1970 00:00:00 GMT)	See also time()
			,"'"=>"''"
		);
		$recurProof = str_replace(array_keys($map), $delimd = array_map(function ($val){return "<[<$val>]>";}, array_keys($map)), $format);
		return str_replace($delimd, array_values($map), $recurProof);
	}

	/**
	* This converts PHP's datetime rendering codes to JSCalendar's. It also makes a quick reference for the PHP codes used in accept, initial, etc.
	* See http://us1.php.net/manual/en/datetime.formats.php and http://lifetoward.com/weblib/jscalendar/doc/html/reference.html#node_sec_4.3.5
	*/
	private function php2jscal( $format )
	{
		static $map = array(
		// Day
			 'd'=>'%d' // day of month w/ lead 0
			,'D'=>'%a' // A textual representation of a day, three letters	Mon through Sun
			,'j'=>'%e' // Day of the month without leading zeros	1 to 31
			,'l'=>'%A' // (lowercase 'L') A full textual representation of the day of the week	Sunday through Saturday
			,'N'=>'%u' // ISO-8601 numeric representation of the day of the week (added in PHP 5.1.0)	1 (for Monday) through 7 (for Sunday)
			,'S'=>'th' // APPROXIMATE - doesn't work for 1,2,3! English ordinal suffix for the day of the month, 2 characters	st, nd, rd or th. Works well with j
			,'w'=>'%w' // Numeric representation of the day of the week	0 (for Sunday) through 6 (for Saturday)
			,'z'=>'%j' // The day of the year (starting from 0)	0 through 365
		//Week	---	---
			,'W'=>'%W' // ISO-8601 week number of year, weeks starting on Monday (added in PHP 4.1.0)	Example: 42 (the 42nd week in the year)
		//Month	---	---
			,'F'=>'%B' // A full textual representation of a month, such as January or March	January through December
			,'m'=>'%m' // Numeric representation of a month, with leading zeros	01 through 12
			,'M'=>'%b' // A short textual representation of a month, three letters	Jan through Dec
			,'n'=>'%m' // (Approx... JSC has no non-lead) Numeric representation of a month, without leading zeros	1 through 12
			,'t'=>'(28-31)' // (Fake... no such thing in JSC) Number of days in the given month	28 through 31
		//Year	---	---
			,'L'=>'' // Hack, no such JSC; Whether it's a leap year	1 if it is a leap year, 0 otherwise.
			,'o'=>'%Y' // Approx, no such JSC; ISO-8601 year number. This has the same value as Y, except that if the ISO week number (W) belongs to the previous or next year, that year is used instead. (added in PHP 5.1.0)	Examples: 1999 or 2003
			,'Y'=>'%Y' // A full numeric representation of a year, 4 digits	Examples: 1999 or 2003
			,'y'=>'%y' // A two digit representation of a year	Examples: 99 or 03
		//Other
			,'U'=>'%s' // Seconds since the Unix Epoch (January 1 1970 00:00:00 GMT)	See also time()
			,'%'=>'%%',"\n"=>'%n',"\t"=>'%t'
		);
		$recurProof = str_replace(array_keys($map), $delimd = array_map(function ($val){return "<[<$val>]>";}, array_keys($map)), $format);
		return str_replace($delimd, array_values($map), $recurProof);
	}

}

?>
