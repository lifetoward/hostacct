<?php
/**
* Implements a collection of named boolean flags. The names represent the various values you can turn on or off or check for state as described below.
*
* Native format: associative array in which the keys are the value handles and they point to true when they are turned on; if a key is missing or points to an empty value it's off.
* 	We don't guarantee whether all options are included in the array if unset, though they may appear with empty values (0, false, null, etc.)
*
* To test for a specific value within the set use (bool)($instance->$fname[$value]) (the cast here is illustrative and not needed if used in a boolean context)
*	We only guarantee that set values will evaluate to a boolean equivalent of true. Don't rely on it actually being boolean or exactly equal to true.
* By contrast you must always use truly boolean values when assigning values within the set. We don't guarantee that boolean equivalents will be handled as you'd expect.
*
* To set (turn on) a value do $instance->$fname[$value] = true;
*
* To reset (turn off) a value do $instance->$fname[$value] = false;
* 	Note that if you did unset($instance->$fname[$value]) it would mean "revert to stored state of this specific value" during updates, analogous to what unset($instance->$fname) would do for all values.
*
* To bulk-set all named values without checking, you can do $instance->$fname = array($val1=>true, $val2=true); because this is the native format.
* Pass such an array to accept() to obtain the native format with checking: $instance->$fname = t_boolset::accept(array($val1=>true, $val2=true), $this->getFieldDef($fname));
* You can also provide a string with the set value names separated by commas if you use the accept method: $instance->$fname = t_boolset::accept("val2,val4,val21", $this->getFieldDef($fname));
*
* Sorting is not usually meaningful against this type and is not recommended.
*
* To select records during a database load based on the on-state of a certain value use a where fragment like
*	$where = "0<FIND_IN_SET($setval, $fname)"
* The above example would select records wherein the sought value was set. Change the < operator to = in order to select records where the value is unset.
*
* The methods in this abstract subclass are all described in the base class Type, so don't expect a lot of comments about them here.
*
* All original code.
* @package Synthesis/Base
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
abstract class t_boolset extends Type
{
	const InputWidth = 12;

	public static function accept( $value, array $fd )
	{
		if (is_string($value))
			$value = array_fill_keys(explode(',',$value), true);
		if (!is_array($value))
			throw new BadFieldValueX($fd, "The value provided is not of an acceptable format.");
		foreach ($value as $vn=>$vl)
			if (!$fd['lenient'] && !$fd['options'][$vn])
				throw new BadFieldValueX($fd, "The value provided contains unrecognized components.");
		$result = array();
		foreach ($fd['options'] as $on=>$ol)
			$result[$on] = $value[$on] ? true : false;
		return $result;
	}

	public static function put_db( array $native, Database $db = null )
	{
		foreach ($native as $n=>$v)
			$v && $trues[] = $n;
		return parent::put_db(implode(',', $trues), $db);
	}

	public static function accept_db( $dbformat )
	{
		$result = array();
		if ($dbformat)
			foreach (explode(',', $dbformat) as $on)
				$result[$on] = true;
		return $result;
	}

	public static function render( Instance $d, $fname, HTMLRendering $R, $format = null )
	{
		$f = $d->getFieldDef($fname);

		// We render checkboxes for INPUT and VERBOSE scenarios.
		if (in_array($R->mode, array($R::VERBOSE,$R::INPUT)) || $format == 'checkboxes') {
			$R->addStyles(<<<css
div.boolset { height:auto; margin-left:0; margin-right:0;}
div.boolset_opt input.boolset { margin-right:1em; height:18px; width:18px; }
css
				);
			$roattr =  $R->mode != $R::INPUT || $format == '*readonly' || $R->readonly ? 'disabled="1"' : 'name="'. $fname .'[%OPTION%]"';
			foreach ((array)$f['options'] as $opt=>$readable) {
				$cid = "$R->idprefix{$fname}_$opt";
				$altattr = str_replace('%OPTION%', htmlentities($opt), $roattr);
				$checked = ($d->{$fname}[$opt] ? ' checked="true"' : null);
				$readable = htmlentities($readable);
				$result .= <<<html
	<div class="boolset_opt col-md-4">
		<input type="checkbox" class="boolset" $R->tabindex $altattr value="1" id="$cid"$checked/><label for="$cid">$readable</label>
	</div>\n
html;
			}
			return '<div class="form-control boolset row">'. $result ."</div>";
		}
		$list = array();
		foreach ($d->$fname as $on=>$val)
			if ($val)
				$list[] = str_replace(' ', '&nbsp;', htmlentities($f['options'][$on] ? $f['options'][$on] : $on));
		return implode(', ', $list);
	}

	public static function mysql_ddl( array $fd, Database $db = null )
	{
		if (!$db)
			$db = $GLOBALS['root'];
		$comment = "COMMENT 'boolset: ". $db->dbEscapeString($fd['label']) ."'";
		$default = "'". ($fd['initial'] ? $db->dbEscapeString($fd['initial']) : null) ."'";
		if (count($fd['options'])) {
			foreach ($fd['options'] as $value=>$label)
				$values[] = "'". $db->dbEscapeString($value) ."'";
			$valuelist = implode(',', $values);
		}
		return "`$fd[name]` SET($valuelist) CHARACTER SET binary NOT NULL DEFAULT $default $comment";
	}
}
