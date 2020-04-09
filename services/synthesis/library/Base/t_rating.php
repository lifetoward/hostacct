<?php
/**
* t_rating
* Allows for the specification of a number of stars (1-5) to qualitatively assess something.
* There is no 0 value, so at least one star must be lit to indicate that an assessment has been made and applied.
* Null is a legal value meaning 'unrated'.
*
* Created: 1/28/15 for Lifetoward LLC
*
* All original code.
* @package Synthesis/Base
* @author Biz Wiz <bizwiz@SyntheticWebApps.com>
* @copyright (c) 2015 Lifetoward LLC; All rights reserved.
* @license proprietary
*/
abstract class t_rating extends t_select
{
	/**
	 * Represent a field's value in a string form suitable for use in human-readable applications such as in emails, in printed documents, etc.
	 * This default implementation uses a single conversion to string for scalar values; for arrays it just implodes the values into a string.
	 * @param mixed $d The first parameter may be an instance object by which to obtain the value to format. Otherwise it's the raw value itself.
	 * @param string $fname Either the Field name, ie. the index into the instance object passed as the first parameter; OR a formatting hint to help process a scalar.
	 * @return string A canonical representation of the field value in string form.
	 */
	public static function format( /* Instance */ $d , $fname = null )
	{
		if (!($val = ($d instanceof Instance ? $d->$fname : $d) * 1))
			return "not rated";
		return "$val of 5 stars";
	}

	/**
	 * Represent in conventional HTML a data field.
	 * The field may be rendered in a variety of conventional "modes" such as for input, concise output, verbose output, etc.
	 * Guidelines:
	 *	- Every field should be rendered within an HTML element which carries an id so scripts can access its contents as needed.
	 *		For example, rather than just return the value, return it wrapped like this: <span id="$R->idprefix$fname">$value</span>
	 * @param Instance $d The instance object containing the field to render.
	 * @param string $fname The field name within the instance.
	 * @param HTMLRendering $R The rendering object
	 * @return string The rendered field.
	 * @api
	 */
	public static function render( Instance $d, $fname, HTMLRendering $R )
	{
		$val = $d->$fname;
		if ($R->mode != $R::INPUT) {
			return "<span id=\"$R->idprefix$fname\">". self::renderStarGroup($val) ."</span>";
		}
		$fd = $d->getFieldDef($fname);
		if (!$fd['required'])
			$options = '<option value="">&nbsp;not&nbsp;rated&nbsp;</option>';
		for ($x = 1; $x <= 5; $x++)
			$options .= '<option '. ($x==$val ? 'selected="" ' : null) .'value="'. $x .'">'. static::format($x) .'</option>';
		return "<select size=\"1\" name=\"$fname\" id=\"$R->idprefix$name\" $R->tabindex class=\"form-control input\">$options</select>";
	}

	public static function renderStarGroup( $rating )
	{
		if (!$rating)
			return "&nbsp;not&nbsp;rated";
		for ($x = 1; $x <= 5; $x++)
			$out .= '<span style="color:#CCCC00" class="t_rating glyphicon glyphicon-star'. ($rating >= $x ? null : '-empty') .'"></span>';
		return $out;
	}

	/**
	 * Process-in a single data value received from a user, cleansing and validating it as necessary.
	 * This default implementation filters out any dangerous HTML tags from the input.
	 * @param string $value Data value received from an untrusted source.
	 * @param array $field (optional) Provide the field definition array if it is available. The only reason it wouldn't be is if internally validating data of a known type (rare if ever?)
	 * @return mixed Returns the runtime canonical form of the field value for direct storage in a Instance->updated[] array.
	 */
	public static function accept( $value, $field = null )
	{
		return $value >= 1 && $value <= 6 ? $value * 1 : null;
	}

	/**
	* Convert a field value as loaded from the database via an SQL query into its native form as should be stored in the instance's loaded[] array.
	* Important: It is possible for a single field to be stored in its database table as multiple columns. This helps with allowing the database to do fast sorting and indexing
	* on portions of the field's value. acceptInstanceData() has logic to combine all aspects of a fieldset of this type into an associative array, and if this is what's happening for this
	* field type, then you'll get an array as a value. In all other cases, the value is a string and we suggest type conversion to canonical form during the accept_db operation.
	* @param mixed $value Value as it is obtained from the database query and fetch operation.
	* @return mixed Value as it should be placed in Instance->loaded[]
	*/
	public static function accept_db( $value )
	{
		return $value * 1;
	}

	/**
	* Convert a field value from its native form to something that can be included in an SQL statement to UPDATE or INSERT a record.
	* In complement with accept_db, this operation can return an array, and if it does, the array's keys will become "subfields" as follows:
	* `{$fname}_{$key1}` = $value1, `{$fname}_{$key2}` = $value2, ...
	* We only allow one level deep of this. All values in an array returned here must be strings which will become column values in a database INSERT or UPDATE query.
	* @param mixed $value Value as it exists in Instance->updated[]
	* @return mixed Value as it be stored in the database query (UPDATE/INSERT) operation.
	*/
	public static function put_db( $value, Database $db = null )
	{
		if (!$db)
			$db = $GLOBALS['root'];
		return $value ? $value * 1 : 'NULL';
	}

	/**
	* Obtain a numeric representation of the field value for use in mathematical operations.
	* Note that the default implementation is just to take the PHP cast to type integer. This may not be what you want if you are string-like.
	* @param Instance $d The object in which the field value resides.
	* @param string $fn The field name you want to get as numeric
	* @return numeric A number type representation of the field value.
	*/
	public static function numeric( Instance $d, $fn )
	{
		return $d->$fn * 1;
	}

	/**
	 * Provides the MySQL DDL snippet for a field of this type.
	 * This default implementation should almost never be used.
	 * Override it to specify how a field of this type should look in a CREATE TABLE syntax.
	 * @param array $def field definition
	 * @return string Entire MySQL DDL snippet for a single row's data definition.
	 */
	public static function mysql_ddl( array $def, Database $db = null )
	{
		return "`$def[name]` TINYINT(1) DEFAULT NULL COMMENT 't_rating: 1-5 stars'";
	}

}
