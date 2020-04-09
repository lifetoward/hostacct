<?php
/**
* A Fieldset provides a subrecord of a particular complex type (via an instance definition rather than field definition) for use in other instances.
* It's a type of instance which has no intended autonomous existence. It's a lot like an Element, but the only way it gets constructed is within the constructor of another instance.
* Fieldset's are something like Type's in that they have some of the same methods like accept(), render(), and format().
* While a Type consists of static methods operating on scalars in the nesting instance, a Fieldset is an Instance with its own fields and storage, etc.
*
* All original code.
 * @package Synthesis
 * @author Guy Johnson <Guy@SyntheticWebApps.com>
 * @copyright 2007-2014 Lifetoward LLC
 * @license proprietary
*/
abstract class Fieldset extends Instance
{
	const InputWidth=6;
	static $keys = 'id', $sysvals = array('refdef'); // the refdef is the instanceClassName.fieldName designation of the possessing instance.
	protected $refdef = null; // This keeps track of which instance class and field references this Fieldset instance. There can be only 1.
	use SerializationHelper;
	private static $savedProperties = array('refdef');

	/**
	* The following static fielddef-like properties are used when representing the entire fieldset as a field.
	* @property string $help  HTML-ready string for input help
	* @property string $label Standard string labeling the entire fieldset for rendering purposes.
	*/
	static $help = "Default fieldset help", $label = "Default fieldset label";

	/**
	* Like a Type, a Fieldset must render as an atomic unit.
	* So our render() method has the same signature as Type::render().
	* As an adapter to a whole-instance rendering method, it must locate the nesting field definition 
	*	and obtain the Fieldset instance, and for INPUT situations, even if the nominal field value is null.
	*/
	public static final function render( Instance $d, $fn, HTMLRendering $R, $format = null )
	{
		if (!($fs = $d->$fn)) {
			if ($R->mode != $R::INPUT)
				return "( not set )"; // a null fieldset value is renderable without effort except when rendering for input
			// The null value we obtain through the accessor syntax cannot obtain the Fieldset instance itself, so we must go digging for it.
			if (!is_array($fd = $d::$fielddefs[$fn])) {
				// We need to get our hands on the Fieldset object itself, and to do this we need the native instance which references the Fieldset.
				$class = get_called_class();
				foreach ($d->included as $ifn=>$incobj) {
					try { return $class::render($incobj, $fn, $R, $format); }
					catch (NotMyFieldX $ex) { }
				}
				throw new NotMyFieldX($fn, $d);
			} // preceding 'if' block never gets here
			$fs = $d->included[$fn]; // ie. get the fieldset object for the native field of this name
		} else {
			list($nest, $fsfn) = explode('.', $fs->refdef);
			$fd = $nest::$fielddefs[$fn];
		}
		// To call renderFieldset we need the Fieldset instance AND the field definition that references it.
		return $fs->renderFieldset($fd, $R, $format);
	}
	
	/**
	* This method embodies the main purpose of a fieldset: To render an instance as a single complex aggregate "field" within the scope of a referring instance.
	* When the rendering mode is INPUT, the names of the input controls must produce any value that can be accepted by the same class's accept() method.
	* To do this, renderFieldset must be fully aware of the field definition which references it in the same way a Type needs to be so aware.
	* @param array $fd The field definition array for the field referencing this instance. This should be used for scoping the naming of rendered local fields.
	* @param HTMLRendering $R The rendering object.
	* @param mixed $format (optional) As for Type's, we allow a formatting hint to be passed through to the rendering implementation.
	* @return string An HTML rendering of the entire fieldset according to the mode specified in the context.
	*/
	public function renderFieldset( array $fd, HTMLRendering $R, $format = null )
	{
		if ($R->mode != $R::INPUT)
			return str_replace("\n", "<br/>", htmlentities($this->format()));
		return "<button>An input rendering is not implemented.</button>";
	}

	/*
	* Fieldsets have an "accept" method which is different than the Instance's acceptValues(); it returns something different and throws as a single field.
	* Fieldsets would do well to provide "smart" interpretations for how to accept values.
	*	For example, imagine a postal address which could be accepted as a string... if the string can be parsed into a meaningful address, then great, do it!
	* For this common default case we accept an array as would be POST'd by default from a standard rendering of the Fieldset.
	* The other important thing about this method is that it must return a value appropriate to indicate whether the Fieldset as a whole is null or worth storing.
	* @param mixed $value A value to use to set the entire fieldset object's state. 
	*		Any implementation of a Fieldset MUST accept an array of values as would be POST'ed from a user interaction INPUT rendering.
	*		Most implementations should also accept sparse arrays of the same type.
	* @return null|*|integer Returns null when the resulting fieldset in aggregate is empty or undefined. 
	*		Otherwise returns the ID of the fieldset record, with * for an unstored record.
	*/
	public function accept( $value, array $fd )
	{
		if (!is_array($value)) // Overriding implementations would do well to be more flexible and clever.
			throw new BadFieldValueX($fd, "By default, a Fieldset only accepts a set of values.");
		try { $this->acceptValues($value); } // call the Instance array acceptance method
		catch (BadFieldValuesX $ex) { throw new BadFieldValueX($fd, $ex->getMessage()); }
		foreach ($this->getFieldValues() as $val)
			if ($val) // This is a simple null checking logic... if no set values, bomb out. Not suitable for real Fieldset's.
				return $this->_id; // * is appropriate here when the object is unstored
		return null;
	}
	
	/**
	* Unlike for elements, its more important for fieldsets to have well-implemented formatting methods. The entire content of the fieldset should be considered important.
	* @return string A complete representation of the fieldset as one big "value". Should be a natural string, no markup or escaped characters. Line breaks should be signified with \n newlines.
	*/
	public function format( $hint = null )
	{
		foreach ($this->getFieldValues() as $fn=>$fv)
			$result .= "{$this->{¬.$fn}}: $fv";
		return implode("\n", $result);
	}

	/**
	* When you use a fieldset as a string you will get it formatted.
	*/
	public function __toString()
	{
		return $this->format();
	}
	
	/**
	* Create is called when the referring instance is being created from scratch.
	* It's not public because no one is allowed to create a fieldset without a referring context.
	* @param string|object $class The referring instance's class name.
	* @param string|array $f The name of the field which refers to this fieldset.
	* @param mixed[] $initial (optional) Initialization values to use overlaying initializations from the referring field definition if they exist.
	* @return Fieldset A new object which is a subclass of Fieldset
	*/
	protected static function create( $nest, $f, $initial = null )
	{
		if (is_object($nest))
			$nest = get_class($nest);
		$nfd = $nest::getFieldDef(is_array($f) ? $f['name'] : $f);
		if (!is_array($nfd))
			throw new ErrorException("Unable to resolve field name ($f) when creating a new Fieldset instance under class '$nest'.");

		$obj = parent::create(array_merge(is_array($nfd['initial']) ? $nfd['initial'] : array(), is_array($initial) ? $initial : array()));
		
		$obj->refdef = "$nest.$nfd[name]"; // essential sysval initialization
		
		return $obj;
	}
	
	/**
	* As a Fieldset you can get the metaproperties _id (simple numeric key, or '*' when unstored), _handle (system-unique instance identifier), _formatted (string form of a field)
	*/
	public function getMetaProperty( $name )
	{
		switch ($name) {
			case 'handle':   	return get_class($this) ."=$this->key";
			case 'formatted':	return $this->formatted();
			case 'stored':		return $this->key && true;
		}
		return parent::getMetaProperty($name);
	}

}
