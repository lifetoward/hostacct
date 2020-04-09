<?php
/**
* This exception should be thrown by an implementation of Type::accept(...) when a field value is rejected.
* You must provide a field definition structure and an optional message.
*/
class BadFieldValueX extends Exception
{
	public $field;
	public function __construct( array $fd, $msg = null )
	{
		$this->field = $fd;
		parent::__construct("Invalid field value rejected for field '$fd[name]' of type '$fd[class]': \n$msg");
	}
}
/**
* This exception is thrown when an attempt to access a field by name fails because that field is not part of the instance.
* It's how a search down through a hierarchy of included elements for a field terminates.
*/
class NotMyFieldX extends Exception
{
	/**
	* @param string $fn Name of the field sought.
	* @param Instance|string Instance object or name of the class in which the field was sought.
	*/
	public function __construct( $fn, $d )
	{
		$class = $d instanceof Instance ? get_class($d) : $d;
		parent::__construct("Instance class '$class' doesn't contain a field named '$fn'.");
	}
}

/**
* For use exclusively by Instance!
* Useful because this is where we make the entire encapcelated data class look like one big flat set of fields in useful ways.
* Remember that any of these methods are overridable in order to perform custom magic on values.
*
* All original code.
 * @package Synthesis
 * @author Guy Johnson <Guy@SyntheticWebApps.com>
 * @copyright 2007-2014 Lifetoward LLC
 * @license proprietary
*/
trait FieldOps
{
	/**
	* Rendering shortcut facilitators.
	* @property HTMLRendering $_rendering Registers the last context used for rendering values from this object. You can also set it yourself. Facilitates using rendering shortcuts for fields.
	*/
	public $_rendering; // not persistent... request lifetime only

	// * * * * * * * * * * * * * * * * * SINGULAR * * * * * * * * * * * * * * * * * * * * * * * * * * *

	public static function getFieldDef( $name )
	{
		if (is_array($fd = static::$fielddefs[$name]))
			return $fd;
		foreach ($fds = static::$fielddefs as $fd)
			if (in_array($fd['type'], array('include','fieldset'))) {
				try {
					$found = $fd['class']::getFieldDef($name);
					return $fd['type'] == 'include' && is_array($fd['override'][$name]) ? array_merge($found, $fd['override'][$name]) : $found;
				} catch (NotMyFieldX $ex) { }
			}
		throw new NotMyFieldX($name, get_called_class());
	}

	public static function getFieldLabel( $name )
	{
		$fd = static::getFieldDef($name);
		if (!$fd['label'] && $fd['type'])
			$fd['label'] = is_subclass_of($fd['class'], 'Fieldset') ? $fd['class']::$label : (is_subclass_of($fd['class'], 'Element') ? $fd['class']::$singular : $fd['class']);
		return htmlentities($fd['label']);
	}

	// The help is presumend HTML-ready because it's user-interactive-specific
	public static function getFieldHelp( $name )
	{
		$fd = static::getFieldDef($name);
		return $fd['help'];
	}

	/**
	* Obtain the canonical (programmatic) value of a field within an instance.
	* @param string $fn The name of the field sought. It could be any native or included field.
	* @param boolean $flat (optional) If passed as true, get the ID of referenced objects rather than the object itself.
	* @return mixed The canonical (or _id) value of the requested field.
	*/
	public function getFieldValue( $fn, $flat = false )
	{
		// We don't use getFieldDef because we're responsible for actually pulling the value from the fieldset object itself
		// We need that object, not just metadata associated with it.
		if (!is_array($f = static::$fielddefs[$fn])) {
			foreach ($this->included as $subel) {
				try { return $subel->getFieldValue($fn); }
				catch (NotMyFieldX $ex) { } // fine, we'll just try our other included elements
			}
			throw new NotMyFieldX($fn, $this);
		}

		if (array_key_exists($fn, (array)$this->updated))
			$value = $this->updated[$fn];

		else {
			if (!array_key_exists($fn, (array)$this->loaded))
				$this->load();
			$value = $this->loaded[$fn];
		}

		if ($f['type']) {

			if ($f['type'] == 'include') // included objects are special... they are guaranteed to exist from birth and their index cannot be changed without updating the object
				return $flat ? $this->included[$fn]->_handle : $this->included[$fn];

			if ($f['type'] == 'fieldset') // fieldsets may be null in aggregate, and if not, they are objects
				return $value && !$flat ? $this->included[$fn] : $value;

			if ($value == '*') { // special case for NEW (unstored) objects assigned to reffields
				if (!$this->referenced[$fn] instanceof $f['class']) // the referenced object must exist and be of the correct class
					throw new ErrorException("An unstored referenced object is found in $this->_handle.$fn which is not of the correct class ($f[class])");
				if ($this->referenced[$fn]->key) {// it was stored since we last accessed it... we need to allow this and gracefully handle the update.
					$this->registerChanges();
					$this->updated[$fn] = $this->referenced[$fn]->key;
				}
				return $flat ? $this->referenced[$fn]->_handle : $this->referenced[$fn];
			}

			if (!$value) {
				if (!$this->referenced[$fn])
					return null;
				throw new ErrorException("A referenced object exists for $this->_handle.$fn but its key value is null. This is a core problem.");
			}

			// Before we can return a referenced object we must ensure that it is current with the latest value set for its index.
			$handle = $f['type']=='instance' ? $value : "$f[class]=$value";
			if ($flat)
				return $handle;
			if (!$this->referenced[$fn] || $this->referenced[$fn]->_handle != $handle)
				$this->referenced[$fn] = Element::get($handle);
			return $this->referenced[$fn];
		}
		return $value;
	}

	public function formatField( $fname, $format = null )
	{
		$f = $this->getFieldDef($fname);
		if ($f['type'])
			return $this->$fname ? $this->$fname->formatted() : null;
		return $f['class'] ? $f['class']::format($this, $fname, $format) : "{$this->$fname}";
	}

	public function numericizeField( $fname )
	{
		$f = $this->getFieldDef($fname);
		if ($f['type'])
			return $this->$fname instanceof Element ? $this->$fname->_id : null;
		return $f['class'] ? $f['class']::numeric($this, $fname) : $this->$fname * 1;
	}

	/**
	* Return a rendering of the field as it contains its value.
	* Includes handling identifying fields such that they appear as triggers if a display operation has been defined in the instance subclass.
	* 	The idea here is that wherever we might render the label of an instance, we could enable that label with a trigger which takes you to the instance's chosen display operation rendering.
	*/
	public function renderField( $fname, HTMLRendering $R, $format = null )
	{
		$this->_rendering = $R;
		$f = $this->getFieldDef($fname);

		if ($f['type'] && $f['type'] != 'fieldset') { // fieldsets render like Types

			$referenced = $this->$fname;

			if ($R->mode == $R::INPUT && !$f['readonly'] && in_array($f['type'], ['belong','require','refer'])) {

				$cid = "$R->idprefix$f[name]";
				$choices = t_select::nullOptionWithValidation($R, $referenced, $f['required'] || in_array($f['type'], ['require','belong']), $cid) .
					$f['class']::as_options($referenced->_id, $f['filter']);

				// "Create immediate" feature:
				//	When a field is presenting the ability to select a referenced element, in many cases the element may not exist yet.
				// 	When that happens it sure is easiest to allow it to be created while remaining working within the edit action.
				// When we trigger this we assume the operation must be handled within current Action's render_me() (note the need to save reffield!)
				//	and that being in INPUT mode, we should submit POST'd data.
				if (($op = $f['class']::getClassOperation('create', $R->context, null, ['reffield'=>$fname], true))) { // assignment intended
					$R->addReadyScript("\$('select#$cid').on('change',function(e){if(this.value=='*new')". $R->getJSTrigger($op['target']) .'});', "new ref element $cid");
					$choices .= '<option disabled="true">&mdash;</option><option value="*new">( '. $op['label'] .'... )</option>';
				}

				$result = t_select::renderWithOptions($R, $f, $choices); // Here we presume a nefarious knowledge of the content of a higher-level module. Oh well.

			} else
				$result = $referenced ? $referenced->render($R) : null;

		} else // standard case
			$result = $f['class'] ? $f['class']::render($this, $fname, $R, $format) : null;

		return $result;
	}

	/**
	* This function returns the value which has been assigned to $this->updated[$f] as a side-effect.
	* Note that if the field you provide is not native to $this, it will throw an Exception, suggesting that you must locate the field elsewhere in your aggregated instance.
	* We CANNOT handle auxiliary values here. That's done higher up with $instance->$f = $value;
	* This is the method that's called when one assigns a field value using $instance->$f notation when the field is defined in the instance's include/fieldset chain.
	* We check for required, identifying, type, etc. leaving the rest to the type handler.
	* @param string $f Field name
	* @param mixed $value Value to assign to the field. The formats of these values can vary even for a single type if that type's accept method allows for them.
	* @return mixed Returns the value of the field after acceptance. (This differs from how Fieldset implements this method!)
	* @throws BadFieldValueX if the value did not pass validation
	*/
	public function acceptFieldValue( $f, $value )
	{
		// Native fields only! If it's not native find it in the included objects
		// We don't use getFieldDef because we're responsible for actually pulling the value from the fieldset object itself
		// We need that object, not just metadata associated with it.
		if (!is_array($fd = $this::$fielddefs[$f])) {
			foreach ($this->included as $fn=>$subel)
				if ($this::$fielddefs[$fn]['type'] == 'include') { // or namespacing reasons we don't allow this kind of flat access to fieldsets' fields
					try { return $subel->acceptFieldValue($f, $value); }
					catch (NotMyFieldX $ex) { } // fine, we'll just try our other included elements
				}
			throw new NotMyFieldX($f, $this);
		}

		if ($fd['derived'])
			$this->updated[$f] = $value;

		if (!$fd['type']) { // for non-system fields
			if ($fd['required'] && ($value === '*NULL' || (!is_bool($value) && !is_numeric($value) && !$value)))
				throw new BadFieldValueX($fd, "A value is required.");
			$this->registerChanges();
			return $this->updated[$f] = $fd['class'] ? $fd['class']::accept($value, $fd, $more) : $value;
		}

		// Below we handle assignments of any system types

		if ($value == '*contextObject' && $_SESSION['_root']->{$fd['name']} instanceof $fd['class'])
			$value = $_SESSION['_root']->{$fd['name']};

		if (!$value && ($fd['required'] || in_array($fd['type'], array('belong','require'))))
			throw new BadFieldValueX($fd, "Cannot be empty.");

		if (($fd['type'] == 'instance' && is_string($value) && $value == $this->referenced[$f]->_handle) ||
				(is_numeric($value) && $value > 0 && $value == $this->referenced[$f]->key))
			return $value; // for reference fields, we tolerate the no-op situation where we attempt to numerically set the value which already exists

		if ($fd['type'] == 'include') // the preceding is the only valid attempt to set an included field's value... it must ALWAYS match what it had been.
			throw new BadFieldValueX($fd, "Cannot accept updates."); // Changing the id value of an included subrecord is not allowed

		$this->registerChanges(); // Now for the side-effect of updating the object's field value

		if ($fd['type'] == 'fieldset') {
			$result = $this->included[$f]->accept($value, $fd);
			if ($fd['required'] && !$result)
				throw new BadFieldValueX($fd, "Cannot be empty.");
			return $this->updated[$f] = $result; // If the fieldset is unstored and containing non-null, the return value will be '*'.
		}

		// No-ops, invalid nulls and empties, and include types are already dealt with above,
		if (!$value) {
			unset($this->referenced[$f]);
			return $this->updated[$f] = null; // assignment intended
		}

		// So now we are dealing with attempts to make an actual change to a reffield value
		if ($fd['type'] == 'instance') {
			$this->referenced[$f] = Element::get($value);
			return $this->updated[$f] = $this->referenced[$f]->_handle;
		}

		// We are willing to convert scalar values (numeric key or handle) to appropriate element instance objects as a convenience when dealing with whole records for legacy reasons.
		if (is_numeric($value) && $value > 0)
			$value = $fd['class']::get($value);

		if (!$value instanceof $fd['class'])
			throw new BadFieldValueX($fd, "To set the value of reference field \"$this->_handle.$f\" you must supply an Element of class $fd[class].");

		// At this point we either have an appropriately classed element or we have an invalid value
		$this->referenced[$f] = $value;
		return $this->updated[$f] = ($value->key ? $value->key : '*'); // new in v4 we allow assigning unstored elements to reffields. We don't store them for you though, and storing their referrers will throw if they're not.
	}

	/**
	* This method does NOT return objects for reference type fields! It only returns their IDs. This is for various reasons... just deal with it.
	* @return mixed The last loaded (stored, unmodified) value of a field ignoring updates made in the temporary program space.
	*/
	public function original( $fname )
	{
		$this->load(); // checks for stored and loaded itself
		if (!($fd = $this::$fielddefs[$fname])) {
			foreach ($this->included as $subel) {
				try { return $subel->original($fname); }
				catch (NotMyFieldX $ex) { }
			}
			throw new NotMyFieldX($fname, $this);
		}
		return in_array($fd['type'], array('require','belong','refer')) ? $fd['class']::get($this->loaded[$fname]) : $this->loaded[$fname];
	}

	// * * * * * * * * * * * * * * * * * PLURAL * * * * * * * * * * * * * * * * * * * * ** * * * * *

/* NOTES ABOUT THE PLURAL FIELD HANDLERS IN GENERAL

One of the most important aspects of the plural handling of fields is that whole elements which are included into an element will be represented as a native part of the element.
That means the fields of all the included elements and top-level instance are:
	1. Loaded together in a single query (though only selectively in appropriate situations)
	2. Accssible using one level of indirection: $instance->includedFieldName works, no matter how many levels of inclusion may exist to arrive at includedFieldName.
	3. Stored together

One consequence of this approach is that all the field names in an entire inclusion chain must be unique. (There are possible ways around this which we have not accepted as appropriate yet.)

Because the inclusion is signified with a field which itself has a value and a meaning (as the included object in its autonomy), there needs to be clarity about how this field of type include is handled.

The necessary rule is that all plural field handling methods must behave the same as regards the inclusion of the include-type fields as dictated by STATIC instance definitions.
That is, no matter which of the methods below you call for a given instance using the same set of input parameters, the number, sequence, and identity of the fields in the returned array must be the same.

For these implementations in the base class, this is fairly simple to effect, because all our implementations use getFieldDefs to determine the list. Subclasses should take care to maintain the same
convention, even if they don't do so by the same means.

getFieldDefs() then will make its decision about the inclusion of the include-type fields as follows:


*/
	/**
	* This workhorse static function is responsible for assembling a complete field definition list for any element and should be used instead of accessing the native Instance::$fielddefs property in almost all cases.
	* Most notably we consolidate into the single flat array all fields *include'd from additional elements as defined in an instance's field definitions.
	* Because this method is called almost every time an instance of this class is accessed and because there can be a lot of figgering involved in this process even though the information it's based on is all static,
	* we cache the finished result and return the cached value once it's been set. This of course only applies per runtime, ie. per request.
	*
	* It's called a workhorse because all the other plural field methods use it to obtain the set of relevant field definitions on which they operate and you, app programmer, should too. In addition the _fieldlist magic attribute of the instance
	*	uses getFieldDefs (free of attributes) to capture the list of field names. In all these cases, the filtering described below occurs.
	*
	* The second major value we add here (after consolidating *include'd fields) is the filtering by exclude and include lists, an ability which we expect will be used readily by specific subclasses even if they do their own implementations.
	* Not only do we process the include and exclude lists that are passed in for applied reasons at call time, we also use field definitions to apply filtering over included field lists.
	*	Specifically we handle "exclude" and "include" field attributes (for fields with type=*include) and apply them accordingly.
	*
	* After applying the exclude and include filters, we then handle the "override" attribute which can be used to override any included field attribute's definition. You need to provideBe careful with this because if you take
	*	over the specification of a complex attribute (ie. an array or similar) then you need to be sure everything is in there that needs to be, not just your changes. This is PER ATTRIBUTE.
	*	Extensive use of an override attribute may suggest that your data model is not quite right, because it raises the question of why you're *including an element so improperly configured.
	*
	* Keep in mind that these overrides and filters are wholesale modifications which don't care about the rendering context or mode, so be sure that's what you want when using them in your *include field definitions.
	* Also note that because of the power and importance of these filtering and overriding procedures, an instance class which overrides getFieldDefs() should almost always call this parent implemention
	* 	eventually because it would be very rare that you would not want to engage these techniques.
	*
	* Note: This standard implementation does NOT currently effect authorization filtering or modification. Perhaps it should in the future. Currently authorization attributes are not defined.
	*
	* The signature of this function is shared by all the static plural field methods. The signature is intended to provide the ability for subclasses to override not just inclusion in the list but also
	* 	attributes present in the field definitions based on the context and mode. For example, when listing element records, it's typical to include only the most interesting fields for the purpose and to relabel some.
	* All arguments to this function are optional but they are still required in order.
	* @param Context|HTMLRendering $c The context under which an action is rendering the instance fields. The context can contain authorization info and other arbitrary values that subclass implementations can honor.
	* @param array $exclude A simple list of field names which should be excluded from the set. If it's in this list, it won't come back in the result.
	* @param array $include A simple list of field names which are the only ones allowed to be included in the set. Note that both exclude and include effect reductions in the resulting list.
	*	That is, being in the include list does not override being in the exclude list. Because of this, the include list is less often used.
	* @return array The result is a consolidated associative array of field definition structures indexed by the field names. All included elements' fields are included in the list unless their exclusion follows some purpose.
	*/
	public static function getFieldDefs( $c = null, $exclude = null, $include = null )
	{
		// The cache can only apply for null contexts and include/exclude settings for a given mode
		if (!$exclude)
			$exclude = array();
		$result = array();
		foreach ($fds = static::$fielddefs as $fn=>$fd)
			if (($keep = !in_array($fn, (array)$exclude) && (!$include || in_array($fn, $include))) || $fd['type']=='include') {
				if ($fd['derived'] && $c instanceof HTMLRendering && $c->mode == $c::INPUT)
					continue;
				// Although fieldset type allows referenced fields to appear alongside this instance's fields, we don't expand them when listing fields in aggregate.
				// That's why ONLY include type fields are dove-into here.
				if ($fd['type'] == 'include') {
					// We support a number of field list filtering and definition hack approaches to customize the way included elements present in the *include'ing element.
					// These settings are wholesale modifications which don't care about the rendering context or mode, so be careful using them in your field definitions.
					if (is_array($fd['exclude']))
						$exclude = array_merge((array)$exclude, $fd['exclude']);
					if (is_array($fd['include']))
						$include = count($include) ? array_filter($include, $fd['include']) : $fd['include'];
					$infields = $fd['class']::getFieldDefs($c, (array)$exclude, (array)$include);
					if (is_array($fd['override']))
						foreach ($fd['override'] as $ifn=>$override)
							if (is_array($override) && is_array($infields[$ifn]))
								$infields[$ifn] = array_merge($infields[$ifn], $override);
					if (!$c && $keep) // This says we only include the include-type field itself in the list if no context is passed in.
						$result[$fn] = static::getFieldDef($fn);
					$result = array_merge($result, $infields);
				} else if ($keep) // only false if we're here to handle included fields and the included field is not included
					$result[$fn] = static::getFieldDef($fn);
			}
		return $result;
	}

	/**
	* The signature of this method is the same as for getFieldDefs() and the set of fields will be managed according to its filtering. Reference that method's description for details.
	* @return array A simple enumerated list of relevant field names for a given context, etc.
	*/
	public static function getFieldList( $c = null, array $exclude = null, array $include = null )
	{
		return array_keys(static::getFieldDefs($c, $exclude, $include));
	}

	/**
	* The signature of this method is the same as for getFieldDefs() and the set of fields will be managed according to its filtering. Reference that method's description for details.
	* @return array An associative array with field names as indexes and html-compatible field labels  simple enumerated list of relevant field names for a given context, etc.
	*/
	public static function getFieldLabels( $c = null, array $exclude = null, array $include = null )
	{
		$results = array();
		foreach (static::getFieldDefs($c, $exclude, $include) as $n=>$fd)
			if ($fd['type'] != 'include') // include fields are themselves never rendered
				$results[$n] = htmlentities($fd['label'] ? $fd['label'] : ($fd['type'] == 'fieldset' && isset($fd['class']::$singular) ? $fd['class']::$singular : $fd['class']));
		return $results;
	}

	/**
	* The signature of this method is the same as for getFieldDefs() and the set of fields will be managed according to its filtering. Reference that method's description for details.
	* @return array An associative array with field names as indexes and field help text as the values. Help text is suppsoed to be HTML compatible as defined in the native $fielddefs static property, so this result is HTML-ready.
	*/
	public static function getFieldHelps( $c = null, array $exclude = null, array $include = null )
	{
		$results = array();
		foreach (static::getFieldDefs($c, $exclude, $include) as $n=>$fd)
			if ($fd['type'] != 'include') // include fields are themselves never rendered
				$results[$n] = $fd['help'] ? $fd['help'] : ($fd['type'] == 'fieldset' && isset($fd['class']::$help) ? $fd['class']::$help : null);
		return $results;
	}

	/**
	* The signature of this method is the same as for getFieldDefs() and the set of fields will be managed according to its filtering. Reference that method's description for details.
	* @param boolean $flat The extra "flat" parameter allows you to request handles for referenced objects rather than the objects themselves.
	* @return array An associative array with field names as indexes referencing their native programmatic (PHP) values.
	*/
	public function getFieldValues( $c = null, array $exclude = null, array $include = null, $flat = false )
	{
		$results = array();
		foreach ($this->getFieldDefs($c, $exclude, $include) as $n=>$fd)
			$results[$n] = $this->getFieldValue($n, $flat);
		return $results;
	}

	/**
	* The signature of this method is the same as for getFieldDefs() and the set of fields will be managed according to its filtering. Reference that method's description for details.
	* @return array An associative array with field names as indexes referencing string-formatted values, ie. from formatField().
	*/
	public function formatFields( $c = null, array $include = null, array $exclude = null )
	{
		$results = array();
		foreach ($this->getFieldDefs($c, $exclude, $include) as $n=>$fd)
			if ($fd['type'] != 'include') // include fields are themselves never rendered
				$results[$n] = $this->formatField($n);
		return $results;
	}

	/**
	* The signature of this method is the same as for getFieldDefs() and the set of fields will be managed according to its filtering. Reference that method's description for details.
	* @return array An associative array with field names as indexes referencing html-ready string values as from renderField().
	*/
	public function renderFields( HTMLRendering $R, array $exclude = null, array $include = null, $format = null )
	{
		$results = array();
		foreach ($this->getFieldDefs($R, $exclude, $include) as $n=>$fd)
			if ($fd['type'] != 'include' && ($R->mode != $R::INPUT || !$fd['readonly'])) // include fields are themselves never rendered; we exclude readonly fields for INPUT
				$results[$n] = $this->renderField($n, $R, $format);
		return $results;
	}
}
