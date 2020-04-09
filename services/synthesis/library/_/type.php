<?php
/**
* Type is the base class for any field. By itself it contains no canonical value, but all its subclasses should.
* Each type defines a set of behavior hints which can be specified in the field definition array by which a field is associated with an instance.
* Some of these types are standardized for all types and are needed for processing above the field level: "name", "label", "class", "type", "unique", "help", "identifying", and "required".
* Others can be defined arbitrarily as makes sense in the semantic of the datum.
*
* A type should define whether sorting is possible and if so, how that is put in SQL queries.
*
* A type should define what kinds of filtering can be done with a field of this kind.
*
* Type outlines what the many types in a typical web app must each be capable of doing.
* For example, its methods facilitate type-specific data validation, query generation, and rendering.
* All child type classes are static (abstract) because we don't actually instantiate fields individually, but rather store them and allow access to them only via their Instances.
* This base class for all field types handles any non-reference types defined in the Legacy "datatree" model and in the case of accept() also provides a suitable base class implementation even for modern usage.
* @package \Synthesis\Core
*
* Early considerations of Cap v3 had this stuff about type filters
*	A filter describes how to limit a result set in 3 ways:
*	1. In the form of a database query "WHERE" or perhaps "LIMIT" clause
*	2. In the form of an interactive filter selection form
*	3. In the form of a rendered description of the filter's state, i.e. what's currently being filtered
*	public function filterRendering( SynthesisRenderingContext $c ) { return null; }
*	public function filterFormRendering( SynthesisRenderingContext $c ) { return null; }
*	public function filterFragment( $dbtype = 'mysql' ) { return null; }
*
* All original code.
* @package Synthesis
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2014 Lifetoward LLC
* @license proprietary
*/
abstract class Type
{
	const InputWidth = 6;

	static $sortable = true, $defhints = array(
		 'name'=>"REQUIRED. Uniquely identifies this field among all other fields in the extended instance"
		,'class'=>"REQUIRED UNLESS derived is set. Must be the name of a Type subclass, or if the type hint is set to a system reference field, then it must be the name of a Element subclass"
		,'label'=>"A formatted (but not HTML) user-friendly name for the field"
		,'type'=>"Usually left unspecified, but set it to specify a system-defined field from among: belong, require, include, refer, instance, or fieldset"
		,'help'=>"An HTML rendered description of how to provide an appropriate value for this field"
		,'unique'=>"Set to the name of a possibly-shared index which will be enforced within the database to ensure records are appropriately unique"
		,'required'=>"Set to true if the value for this field is not allowed to be null or blank and it must be set to pass user input validation for the instance"
		,'identifying'=>"Fields with this hint set to true will always be loaded even when the load is merely for identification purposes. Also, all fields so marked will be part of an implied unique key named _ident. Also, when these fields are rendered, if there is an authorized display operation for the instance, then it will be rendered as a trigger which will take the user to that display operation."
		,'sort'=>"Setting to true is the same as setting to 'ASC' which means 'sort in ascending order'. To sort in descending order, set to 'DESC'."
	);

	/**
	 * Represent a field's value in a string form suitable for use in human-readable applications such as in emails, in printed documents, etc.
	 * This default implementation uses a single conversion to string for scalar values; for arrays it just implodes the values into a string.
	 * @param mixed $d The first parameter may be an instance object by which to obtain the value to format. Otherwise it's the raw value itself.
	 * @param string $fname Either the Field name, ie. the index into the instance object passed as the first parameter; OR a formatting hint to help process a scalar.
	 * @return string A canonical representation of the field value in string form.
	 */
	public static function format( /* Instance */ $d , $fname = null )
	{
		if ($d instanceof Instance)
			return is_array($d->$fname) ? implode(', ', $d->$fname) : "{$d->$fname}";
		else
			return "$d";
	}

	/**
	 * Represent in conventional HTML a data field.
	 * The field may be rendered in a variety of conventional "modes" such as for input, concise output, verbose output, etc.
	 * Guidelines:
	 *	- Every field should be rendered within an HTML element which carries an id so scripts can access its contents as needed.
	 *		For example, rather than just return the value, return it wrapped like this: <span id="$c->idprefix$fname">$value</span>
	 * @param Instance $d The instance object containing the field to render.
	 * @param string $fname The field name within the instance.
	 * @param HTMLRendering $R The rendering context
	 * @return string The rendered field.
	 * @api
	 */
	public static function render( Instance $d, $fname, HTMLRendering $R )
	{
		return htmlentities(static::format($d, $fname));
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
		// see http://msdn.microsoft.com/en-us/library/ff649310.aspx for some cross-site scripting prevention tips... our list of evil here came from that page
		foreach ((array)$value as $scalar)
			foreach (array('<script','<object','<iframe','<frame','<applet','<body','<embed','<html','<img','<style','<layer','<link','<ilayer','<meta') as $evil)
				if (false !== mb_strpos($scalar, $evil))
					throw new BadFieldValueX($field, "Dangerous HTML snippets were found in the input");
		return $value;
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
		return $value;
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
		return isset($value) ? "'". $db->dbEscapeString("$value") ."'" : 'NULL';
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
		return isset($d->$fn) ? $d->$fn * 1 : null;
	}

	/**
	 * Provides the MySQL DDL snippet for a field of this type.
	 * This default implementation should almost never be used.
	 * Override it to specify how a field of this type should look in a CREATE TABLE syntax.
	 * @param array $def field definition
	 * @return string Entire MySQL DDL snippet for a single row's data definition.
	 */
	public static function mysql_ddl( array $fd, Database $db = null )
	{
		return "`$fd[name]` VARCHAR(255) DEFAULT NULL COMMENT 'Default Type: $fd[label]'";
	}

}
