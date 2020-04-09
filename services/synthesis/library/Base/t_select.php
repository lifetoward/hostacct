<?php
/**
* The methods in this abstract subclass are all described in the base class Type, so don't expect a lot of comments about them here.
*
* All original code.
* @package Synthesis/Base
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
abstract class t_select extends Type
{
	/**
	* This method handles null logic for any select-rendered input control.
	* It has the side effect of setting up validation logic on the control as needed by adding scripts to the context.
	* About Null values, the Null Option and validation:
	*	When a reference field is not initialized and has no explicit initializer, it must never pre-select a value because accidental selection of a reference is hard to notice and usually important.
	*	Thus if the type is *require or it's *belong and notnull, then it must still present a null option, but enforce having a value at dynamic validation and accept time.
	*	Therefore a null option is needed in any of the following conditions:
	*		1. Field permits null values (not *require and lacking notnull)
	*		2. Field is uninitialized (unset with no initializer)
	* Validation - Based on the preceding rules, validity checking is required only when Null is not allowed but the field is uninitialized
	* Also, once a value has been selected, if we remove the null option then it's no longer possible for it to be invalid. Thus it's a one-way thing and simpler than most.
	*
	* @param HTMLRendering $R The current rendering context.
	* @param mixed $value The current value of the field as will be rendered. We only check this for whether it is empty or not.
	* @param boolean $required Pass whether or not this field requires a value.
	* @param string $cid The complete (context-specific) id attribute of the select control.
	* @return An HTML-rendered string which should be concatenated with the select control's rendered non-null values.
	*/
	public static function nullOptionWithValidation( HTMLRendering $R, $value, $required, $cid )
	{
		if (!$required || !$value)
			$result = '<option value=""'. ($value ? null : ' selected="true"') .'> - not specified - </option>';

		if ($required && !$value) {
			$R->addScript('function nonNullSelected(){
	$("option[value=\'\']",
		$(this).parents(".form-group")
			.removeClass("has-error")
			.attr("title",null)
	).remove()}
', "t_select::nonNullSelected");
			$R->addReadyScript(<<<jscript
\$c=\$('#$cid');
\$c.one('change',nonNullSelected)
	.parents(".form-group")
		.addClass('has-error')
		.prop('title','You must choose a value.');
\$(\$c.prop('form')).on('submit',function(e){if(\$('#$cid').val()=='')e.preventDefault()});
jscript
			);
		}
		return $result;
	}

	/**
	* The overall structure of how to render any select control with a provided set of choices is handled here so that other uses for a select controls
	* can share the same proper rendering technique. (Notably FieldOps)
    * @param HTMLRendering $R Rendering object for hints like tabindex and idprefix.
	* @param array|string $f The name of the field as it should post in the form
	* @param string $options The entire options list already rendered as an HTML string
	* @return The completed rendering of the entire select control... aka the $options parameter fully wrapped to become a functioning control.
	*/
	public static function renderWithOptions( HTMLRendering $R, $f, $options )
	{
		if (is_array($f)) {
			$fn = $f['name'];
			if ($f['identifying'])
				$idc = ' identifying';
		} else
			$fn = $f;
		return "	<select size=\"1\" name=\"$fn\" $R->tabindex id=\"$R->idprefix$fn\" class=\"form-control fn-$fn$idc\">$options</select>";
	}

	public static function format( Instance $d, $fn )
	{
		$options = $d->{¶.$fn}['options'];
		return $options[$d->$fn] ? $options[$d->$fn] : $d->$fn; // raw value is the fall back, formatted value is preferred
	}

	public static function render( Instance $d, $fn, HTMLRendering $R, $format = null )
	{
		$fd = $d->getFieldDef($fn);
		if ($R->mode != $R::INPUT || $format == '*readonly' || $fd['readonly'])
			return htmlentities(static::format($d, $fn));

		$value = $d->$fn;
		foreach ($fd['options'] as $opt=>$label)
			if ($opt)
				$opts .= '<option value="'. htmlentities($opt) .'"'. ($opt == $value ? ' selected="1"' : null) .'>'. htmlentities($label) .'&nbsp;</option>';
		return static::renderWithOptions($R, $fn, static::nullOptionWithValidation($R, $value, $fd['required'], "$R->idprefix$fn") . $opts);
	}

	public static function accept( $value, array $fd )
	{
		if ($value && !array_key_exists($value, $fd['options']))
			throw new BadFieldValueX($fd, "Value not an option.");
		return $value ? $value : null; // make sure it's actually null-type if unset
	}

	public static function mysql_ddl( array $fd, Database $db = null )
	{
		if (!$db)
			$db = $GLOBALS['root'];
		$comment = "COMMENT 'select: ". $db->dbEscapeString($fd['label']) ."'";
		if ($fd['required'] || $fd['notnull'])
			$notnull = "NOT NULL";
		$default = $fd['initial'] ? "'". $db->dbEscapeString($fd['initial']) ."'" : ($notnull ? "''" : 'NULL');
		return "`$fd[name]` VARCHAR(31) $notnull DEFAULT $default $comment";
	}
}

