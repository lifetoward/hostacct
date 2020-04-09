<?php
/**
* This type has a powerful input processor which uses javascript expression evaluation to get its input values.
* It enforces ranges and decimal place formats.
* Your subclass should carefully configure the various configuring constants:
*	- MIN - The minimum allowed value. If you specify "struct" it means that you can accept the minimum possible value depending on the FORMAT
*	- MAX - The maximum " 	" 	"
*	- FORMAT - Indicate the number of digits accepted to the left and right of the decimal. For example, 9.3 means a value with thousandths precision and up to (not including) 1 billion.
*	- PlaceShift - This is a concept only relevant in human-readable contexts, including input controls, formatted strings, and rendering.
*		If you want to shift the decimal place to the right for conventional readability, set the number of places to shift. For example, you could use this to render dollars as cents or numbers as percents.
*
* All original code.
* @package Synthesis/Contacts
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
abstract class t_decimal extends Type
{
	const MIN='struct', MAX='struct', FORMAT='9.3', PlaceShift = 0,
		RX_FORMAT = '/^[0-9]+(.[0-9]+)?$/', RX_RANGE = '/^([0-9]+)?:([0-9]+)?$/';

	/**
	* Get range is where the number's format and limits are determined.
	* For this reason, it is an important place for subclasses which have more restrictive ranges or specialized formats to exert their authority. For example, they might prevent the use of field overrides of format.
	* @param string $range In the format "min:max". Typically the field definition range is passed here. If it's invalid or incomplete, values are drawn from class constants MIN and MAX.
	* @param string $format In the format "left.right" indicating the number of allowed places to the left and right of the decimal point.
	*		Typically the field definition format is passed here. If it's invalid we use class constant FORMAT.
	*		Note that the format should specify how the number will be used internally, ie. if a percentage, then 1.5 or 0.2 might be appropriate formats.
	* @return array Returns an array of hint values: min, max, precision, scale, decimals.
	*/
	protected static function get_range( $range, $format )
	{
		list($min, $max) = preg_match(self::RX_RANGE, $range) ? explode(':', $range) : array(static::MIN, static::MAX);
		if (!$format || !preg_match(self::RX_FORMAT, $format))
			$format = static::FORMAT;
		list($scale, $decimals) = explode('.', $format);
		$absmax = pow(10, $scale) - ($precision = 1/pow(10, $decimals));
		if (!is_numeric($min) || $min <= -$absmax)
			$min = -$absmax;
		if (!is_numeric($max) || $max >= $absmax)
			$max = $absmax;
		return array('min'=>$min*pow(10,static::PlaceShift), 'max'=>$max*pow(10,static::PlaceShift),
			'precision'=>$precision, 'scale'=>(int)$scale+static::PlaceShift, 'decimals'=>(int)$decimals-static::PlaceShift);
	}

	/**
	* We accept $value polymorphically:
	* If you pass a genuine number, we accept the value as-is.
	* If you pass a string (ie. from a form), then we shift the value by the PlaceShift
	*/
	public static function accept( $value, $fd )
	{
		if (!is_scalar($value))
			throw new BadFieldValueX($fd, 'Value must be a number or numeric string.');
		if (is_string($value)) {
			if (!is_numeric($v = str_replace(',','',$value))) // we do this to allow comma-delimited thousands as might come in from an input field in the UI
				throw new BadFieldValueX($fd, 'Value must be numeric');
			$value = pow(10,-static::PlaceShift) * $v;
		}
		extract(static::get_range($fd['range'], $fd['format']));
		if ($value < $min || $value >= $max)
			throw new BadFieldValueX($fd, 'Value must be >= $min and < $max');
		return round($value, $decimals);
	}

	public static function format( /* Instance */ $d, $field = null, $format = null, $before = null, $after = null )
	{
		if ($d instanceof Instance)
			$value = $d->$field;
		else {
			$value = $d;
			$format = $field;
		}
		if ($value === null || empty($value))
			return "( no value )";
		if (!$format || !preg_match(self::RX_FORMAT, $format))
			if (!($format = $fd['format']))
				$format = static::FORMAT;
		list($scale, $decimals) = explode('.', $format);
		return $before . number_format($value*pow(10,static::PlaceShift), (int)$decimals-static::PlaceShift) . $after;
	}

	public static function render( Instance $d, $fn, HTMLRendering $R, $format = null, $before = null, $after = null )
	{
		$value = $d->$fn*pow(10,static::PlaceShift);
		$fd = $d->getFieldDef($fn);
		if (!$format || !preg_match(self::RX_FORMAT, $format))
			$format = $fd['format'];
		extract(static::get_range($fd['range'], $format));
		if (!$before)
			$before = $fd['before'];
		if (!$after)
			$after = $fd['after'];

		if ($R->mode != $R::INPUT) {
			$R->addStyles("div.t_decimal { background-color:transparent; border:1px solid transparent; text-align:justify; width:auto }".
				"\ndiv.t_decimal>div { text-align:right; display:inline-block; width:". floor(($decimals + $scale) * 3 / 5) ."em }", 't_decimal output');
			return "<div class=\"t_decimal fn-$fn\">$before<div id=\"$R->idprefix$fn\">". number_format($value, $decimals) ."$after</div></div>";
		}

		$R->addScript(<<<jscript
function validate_decimal(c){
	var expr='NaN'*1
		,\$c=$(c)
		,p=1*\$c.prop('decimalPlaces')
		,max=1*\$c.prop('maxValue')
		,min=1*\$c.prop('minValue')
		,rpref=\$c.prop('roundingPref');
	if(c.value.match(/^[-() 0-9*\/+\.,]+$/))
		{try{expr=eval(c.value.replace(/[, ]/g,''));}catch(ev){}}
	if(isNaN(expr)||expr>max||expr<min)
		{alert("Invalid value! Provide a decimal expression which evaluates within range "+min+" - "+max+".");return false;}
	var rmeth=rpref=='up'?Math.ceil:(rpref=='down'?Math.floor:Math.round)
		,shift=Math.pow(10,p);
	expr=rmeth(expr*shift)/shift;
	c.value=expr.toLocaleString('en-US',{minimumFractionDigits:p,maximumFractionDigits:p});
	return true;
}
jscript
			, "t_decimal input");
		$R->addStyles("input.t_decimal { font-size:inherit; height:auto; text-align:right; }", "t_decimal input"); // most of these styles are designed to override bootstrap's form-control class
		$R->addReadyScript(<<<jscript
$('#$R->idprefix$fn')
	.prop('decimalPlaces',$decimals)
	.prop('roundingPref','$fd[round]')
	.prop('maxValue',$max)
	.prop('minValue',$min)
	.on('change',function(e){validate_decimal(this)});
jscript
			);
		$before = $before ? "<div class=\"input-group-addon\">$before</div>" : null;
		$after = $after ? "<div class=\"input-group-addon\">$after</div>" : null;
		$value = is_null($d->$fn) && !$fd['required'] ? null : number_format($value, 1*$decimals);
		$disabled = ($fd['derived'] || $R->readonly || $fd['readonly'] || $format == '*readonly') ? ' disabled="1"' : null;
		$size = $scale+$decimals;
		return <<<html
<div class="input-group">$before
<input type="text" class="form-control t_decimal fn-$fn" id="$R->idprefix$fn" name="$fn" placeholder="Amount" value="$value"
	$R->tabindex $disabled title="Enter a valid decimal expression, e.g. (12.50+7.99)*1.0825/2" size="$size" min="$min" max="$max" data-places="$decimals" />$after</div>
html;
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
		extract(static::get_range($fd['range'], $fd['format']));
		$comment = "COMMENT '". get_called_class() ."($scale.$decimals): ". $db->dbEscapeString($fd['label']) ."'";
		$nullmode = $fd['required'] ? "DEFAULT 0 NOT" : "DEFAULT";
		$format = sprintf("%d,%d", $scale+$decimals, $decimals);
		$unsigned = $min >= 0 ? "UNSIGNED" : null;
		return "`$fd[name]` DECIMAL($format) $unsigned $nullmode NULL $comment";
	}
}
