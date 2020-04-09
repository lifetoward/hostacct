<?php
/**
* The backbone of the Synthesis data model, this class handles all the fields and common object nature of Synthesis data instances.
* It implements all features related to reference type fields.
* It provides a loose form of multiple inheritance.
*
* All original code.
 * @package Synthesis
 * @author Guy Johnson <Guy@SyntheticWebApps.com>
 * @copyright 2007-2014 Lifetoward LLC
 * @license proprietary
 */
abstract class Instance
	implements Serializable
{
	/**
	* The following static properties must be provided by any instance, so we place dummies of them here to prevent syntax exceptions using $class::$staticProperty .
	* Subclasses must override these.
	*/
	static $table = "{database_tablename}", $singular = "{singular_name}", $descriptive = "{descriptive_name}", $plural = "{plural_name}",
		$fielddefs = array(/* fieldName=>array( field definitions ) */), $operations = array(/* opName=>array( op defs )*/),
		$keys = array( /* keyName=>className, ... */ ), $joins = array(/* joinedClass=>onClause */), $sysvals = array(/* colName */);

	// We have extracted some of this large class into a few traits merely for code management reasons.
	use FieldOps, InstanceMethods, LoadAndStore;
	// What remains here are the core properties and the magic methods.

	protected $key;						// The full key information which uniquely identifies one record in the table
	protected $keyed = array(); // This is like referenced, but for key-referenced instances other than the first key (which is $this)
	protected $included = array(); // This is an array of Element or Fieldset objects indexed by referencing fields' names and constructed by the same data load as the parent
	protected $referenced = array(); // This is an array of Element objects indexed by referencing fields' names and constructed by the same data load as the parent
	protected $updated = array(); 	// This is an overlay of $loaded which represents updates made from the application but not yet written to the database
	protected $loaded = array(); 	// This is the data record, without key or system values, as was last read from the database; it includes extended key-values obtained from encapulating field types in old _{capcelField} format
	protected $aux = array();			// This is instance-specific, transient, "app" data which is made from persistent values but not itself persistent in the database. It WILL persist in the session
	protected $formatted; 	// When a formatted value is obtained from the database we store it here for use later as requested. It could also be populated as part of some data class method.

	private static $savedProperties = array('key','aux','updated','included','formatted'); // these are important to remember, whereas loaded data should be retrieved anew

	// Because of https://bugs.php.net/bug.php?id=65591 we explicitly handle core object references ourselves rather than let the buggy serializer mess up object references.

	public function serialize()
	{
		global $_serializedObjects;
		if (!isset($_serializedObjects[$hash = spl_object_hash($this)]))
			$_serializedObjects[$hash] = ++$_serializedObjects['counter'];
		$propList = self::$savedProperties;
		$props = array();
		foreach ($propList as $prop)
			$props[$prop] = $this->$prop;

		// Here we handle unstored referenced objects (our sleep procedure)
		foreach (array('key'=>'keyed', 'updated'=>'referenced') as $ids=>$objs)
			foreach ($this->$objs as $name=>$obj)
				if (!$obj->_stored)
					$props[$objs][$name] = $obj;

		array_walk_recursive($props, 'preserializeObjectRefs');
		$z = mb_strlen($p = "{$_serializedObjects[$hash]}:". serialize($props)); // This is where we stash the Object Ref ID
		return __CLASS__.":$z:$p"; // parsable by pullSerialString($rep)
	}

	public function unserialize($rep)
	{
		list($OR, $propRep) = explode(':',pullSerialString($rep, __CLASS__),2);
		$GLOBALS['_renewedObjects'][$OR] = $this;
		$props = unserialize($propRep);
		array_walk_recursive($props, 'renewObjects'); // This conveniently operates on our own properties first and all their array descendents too
		$propList = self::$savedProperties;
		foreach ($propList as $prop)
			$this->$prop = $props[$prop];

		// Here we handle unstored referenced objects (our wakeup procedure)
		foreach (array('key'=>'keyed', 'updated'=>'referenced') as $ids=>$objs)
			if (count($props[$objs])) {
				$this->$objs = $props[$objs];
				foreach ($this->$objs as $name=>$obj)
					$this->{$ids}[$name] = '*';
			}
	}

	/**
	* You can obtain any registered object field value just by getting it in the usual classy way, $instance->$fieldname
	* You can also get any such value in a special format using a fieldname with a prefix as seen below. All these map to actual named methods which you can call instead.
	* 	Note: If you're going to ask for a rendered value, the _rendering and its mode matter for what you'll get, so be sure that one of the following is true:
	*		1. You've previously rendered something from this object during this request using a method call
	*		2. You've set the _rendering directly using $instance->_rendering = $R
	*		3. You're content to have it render with mode "INLINE" and with a new context based on the root context.
	* You can also obtain any of the "special" properties defined by Synthesis which tell you about the class or object itself. See those below.
	* You can also obtain any session-persistent application property that's been set on the object earlier during the session.
	*/
	public function __get( $name )
	{
		$main = mb_substr($name, 1);
		switch (mb_substr($name, 0, 1)) {
			case '_':	return $this->getMetaProperty($main);
			case '¶': 	return $this->getFieldDef($main); // To get this ¶ character (00B6 "pilcrow"): Mac = Alt-7; Linux = Compose ! p;
			case '¬': 	return $this->getFieldLabel($main); // To get this ¬ character (00AC "not"): Mac = Alt-L; Linux = Compose - , ;
			case '¿':	return $this->getFieldHelp($main); // To get this ¿ character (00BF "invert question"): Mac = Shift-Alt-?; Linux = Compose ? ?
			case '°': 	return $this->formatField($main); // To get this ° character (00B0 "degree"): Mac = Shift-Alt-8; Linux = Compose 0 *
			case '»': 	return $this->renderField($main, $this->_rendering); // To get this » character (00BB "double rt angle quote"): Mac = Shift-Alt-\; Linux = Compose > >
			case '•': 	return $this->numericizeField($main); // To get this • character (2022 "bullet"): Mac = Alt-8; Linux = Compose . =
			case '√':	return $this->getFieldValue($main, true); // If it's a reference, get the handle rather than the object. To get this On Mac this is Alt-V; Linux = Compose v /
			// For help with special characters on Linux see http://fsymbols.com/keyboard/linux/compose/ or the more complete http://www.x.org/releases/X11R7.7/doc/libX11/i18n/compose/en_US.UTF-8.html
		}
		try {
			return $this->getFieldValue($name);
		} catch (NotMyFieldX $ex) {
			return $this->aux[$name];
		}
	}

	/**
	* An app developer can set field values and arbitrary semi-persistent properties on an instance by just assigning to the field or property name:
	* $instance->fieldname = $fieldvalue or $instance->propertyname = $propertyValue;
	* Properties of this kind persist as long as the PHP object, ie. up to the extent of a session, but not in the database.
	* Obviously you can't set properties with the name of a defined field.
	*/
	public final function __set( $name, $value )
	{
		// If they are passing the entire object's set of data (as "_"), just accept the whole object's values as provided.
		if ($name == '_' && is_array($value)) // $element->_ = array(field1=>value1, ... )
			return $this->acceptValues($value);

		// Disallow setting any value that begins with a reserved prefix. (Remember that public real properties are always accessible.)
		if (in_array(mb_substr($name, 0, 1), array('_','¶','°','•','¿','»','¬','√')))
			return logWarn("Can't set a pseudo/system value on an instance.");

		try {
			$this->acceptFieldValue($name, $value);
		} catch (NotMyFieldX $ex) {
			$this->registerChanges();
			$this->aux[$name] = $value;
		}
	}

	/**
	* You can unset any auxiliary (non-database) property; it need not have existed prior.
	* Use unset() to UNDO a latent change to a field (aka persistent property). ie: $element->name = "New name"; unset($element->name); It's like "revert".
	* You can unset the special attribute '_' (whole record) to revert the entire instance to its loaded values. Note that this does not itself invoke a load.
	* It's not possible to literally unset (ie. cause to not exist) a defined field because that definition is encoded in the class (duh).
	* If you want to change the persistent value of a property simply assign that property.
	* If allowed by the field design, you can assign a property to be NULL, for example,
	*	and when the instance is stored, that null WILL be stored in the database, clobbering a former value.
	*/
	public function __unset( $name )
	{
		if ($name == '_') {
			foreach ($this->referenced as $fn=>$obj)
				if (array_key_exists($fn, $this->updated))
					unset($this->referenced[$fn]);
			foreach ($this->included as $obj)
				unset($obj->_); // this is effectively recursive
			$this->registerChanges();
			$this->updated = array();
		}

		if ($name[0] == '_')
			return; // one cannot unset a pseudo/reserved/meta property

		if (!is_array($f = $this::$fielddefs[$name])) {
			if (isset($this->aux[$name])) {
				$this->registerChanges();
				unset($this->aux[$name]);
				return;
			}
			foreach ($this->included as $subel) {
				try { return $subel->__unset($name); }
				catch (NotMyFieldX $ex) { } // fine, we'll just try our other included elements
			}
		}

		if ($f['type'] == 'include')
			return logWarn("$this.$name: Unsetting a field of type include is not valid."); // Changing the id value of an included subrecord or superrecord is not allowed

		$this->registerChanges();
		$this->load();

		if ($f['type'] == 'fieldset')
			// Unsetting a fieldset means reverting its value en masse to how it was loaded.
			unset($this->included[$name]->_); // we don't remove the thing, but rather revert it because we always need a proxy there
		else

			if ($f['type'] && $this->loaded[$name] != $this->referenced[$name]->key && in_array($f['type'], array('refer','instance','require','belong')))
				unset($this->referenced[$name]);

		unset($this->updated[$name]);
	}

	/**
	* You can call methods on a data object which are actually provided by an encapsulated data class.
	* In such cases, the operation is always performed against the encapsulated data object which provides the method.
	* For example, this means that if a method returns the key of an instance, it will return the key of the encapsulated subobject called and not of the heir.
	* Attempts to resolve such a method call are made in fielddef order, depth first.
	*/
	public function __call( $func, $args )
	{
		$this->load(); // must make sure I'm fully loaded, because I must call actual objects
		foreach ($this->included as $capcel) {
			try { return call_user_func_array(array($capcel, $func), $args); }
			catch (NoMethodX $ex) { }
		}
		throw new NoMethodX("Method call attempt on ". get_called_class() ." failed. No such method here.");
	}

} // class Instance
