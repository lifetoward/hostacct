<?php
/**
 * The idea of a Relation is that we have in hand, know about, or are focused on a particular data element, which we refer to throughout as the "REFERENT" element,
 *	and then there are relationships which exist between that referent and other elements in the system. Those other elements to which the referent is related are
 *	known as the "RELATIVE" element(s) throughout.
 *
 * In this semantic, a relationship itself can carry its own data providing further details about the relationship.
 *
 * A key point of a relationship is that there is usually a reciprocal conception of it, ie. any of the related elements can be the referent while the others are then seen as the relatives.
 * 	Thus the referent role is distinct from the relative role only as a matter of purpose.
 * This distinction of purpose is to be embodied by defining different subclasses of Relation which reference the same database table but do so treating different elements as referent.
 * Now that the system is committed to PHP 5.5+, our recommended way of achieving this is:
 *	 - Create a subclass of Relation which is used for one of the two "directions" of relating, ie. with the referent as element A and the relative as B.
 *	 - Create a subclass of Relation which is used for the reciprocal relationship from that above, ie. with the referent as B and the relative as A.
 *	 - Both relations extend an abstract Relation class which itself extends Instance and defines the fields and methods for the relationship record.
 * 	 - Place both classes and the trait into a single PHP file named for the two shared Relation class, then create a symlink to for each pole. This way the autoloader will
 *		find the same file when looking for either class, and they'll all be loaded.
 * With this approach you can access the appropriate relationship depending on your purpose and referent while also knowing that the shared database table is engaged accordingly.
 * The key to making this work is not difficult, but important, and it has to do with having reciprocal relations share the same database table
 *   and how Relation understands the definition it finds in relation::$keys as follows:
 * 	- The keys property must be defined as an associative array in which the keys are the commonly used names of the key fields and the values are the class names of the elements to which they refer.
 *		Obviously the keys must be different from each other, however the values (element classes involved) may match. In this way relationships can be made among elements of the same type.
 *		While it is possible to (mis?)use this ability to create loops, the library functions are not susceptible to such problems. It is even possible for a relation instance to relate one element instance to itself.
 *		Example: r_employment::$keys = array('worker'=>'e_employee', 'company'=>'e_company');
 *		In the database the key fields in the database for this relation record would be _worker and _company.
 *		Each would have foreign key associations with the appropriate tables which contain the data for the e_employee element class and the e_company element class respectively
 *	- The keys properties found in reciprocal relations must match in every way except for the order of the entries. Thus in our example, the reciprocal relation must have keys defined like this:
 *		Example: r_employee::$keys = array('company'=>'e_company', 'worker'=>'e_employee'); This is exactly as it must be, given the reciprocal defined as above.
 *	- Properties common to both poles and central to the database existence of the record should reside in the abstract shared Relation subclass. These include $table, $descriptive, $fielddefs.
 *	- Other properties like $singular, $plural, and necessarily $keys MUST be pole-specific and reside in the instantiable classes.
 *	- Properties $operations and $hints may be sharable or may need to be specific to the pole.
 *
 * In the implementation sense, relations are instances which do not have their own simple primary key record identifiers. Instead the information is multiply
 *	keyed to two (perhaps more in the future) elements. You'll note then that for a given combination of element keys, there can only be one relation. So if you find yourself
 *	wanting to associate multiple instances of information among the same pair of elements, then we see this as going outside the purpose of Relation's semantic
 *	and we recommend that you simply create an Element which includes appropriate references to the related elements.
 * 	Accordingly, the standard unique or primary key for all relations consists of the aggregate of both keys.
*
* All original code.
 * @package Synthesis
 * @author Guy Johnson <Guy@SyntheticWebApps.com>
 * @copyright 2007-2014 Lifetoward LLC
 * @license proprietary
 */
abstract class Relation extends Instance
{
	static $keys = array( /* refKeyName=>refClassName, relKeyName=>relClassName [, ...] */ );

	/**
	* Use this to obtain a dynamically filled-out Operation specification.
	* If an array is passed for $operation, then our job is to return the simple result of getClassOperation or getOperation based on the args in the array ignoring remaining method parameters.
	* Otherwise, when a context is provided we include a target generated by the passed context for requesting the Operation UI.
	* When generating a target, we override (if present in $args) and pass arguments 'operation','class','ref'. The action argument is treated as the dynamic arg in the target.
	* @param Element $referent Because this is a class operation but a Relation, a referent Element (anchor) is required to specify the relation group of interest. 
	*	It must of course match the definition of the called Relation class.
	* @param mixed $operation If passed as an array, it's assumed to be an array of args as if requested by the request of a target generated here previously, and all other args will be ignored.
	*	If passed as a string, then it must be any operation specification found in the called class's operations list. If the operation is not found there, we return null.
	* @param Context $c (optional) The context from which to obtain the target rendering. Without this no target is generated and none of the following args are used.
	* @param string $actionArg (optional) The name of the argument to pass containing the operation's action value as its value.
	*	The assumed value is '_action' which would be automatically handled by Action::render().
	*	If you pass null here, then the target produced will not include the action at all, leaving the caller to invoke the new Action internally on its reinvocation.
	* @param mixed[] $args (optional) Any args you'd like passed through in the target. Perhaps initializers or other contextual clues which will be recognized by the targeted Action.
	* @param boolean $accept (optional) If you need data to be posted along with the request, set this to true. 
	*	This is most often done in conjunction with an $actionArg value which will be processed by the triggering InputAction, allowing it to save some input data before jumping to the new Action.
	* @return string[] An associative array defining the Operation with key 'target' set to a raw URL, requesting which will invoke the operation.
	*/
	public static function getClassOperation( Element $referent, $operation, Context $c = null, $actionArg = '_action', $args = array(), $accept = false )
	{
		if (is_array($operation)) {
			// In this case we look at this array as args from a request derived from an earlier call to this method or the instance-based equivalent to get a target.
			// Our goal is to simply return a finished operation specification array.
			extract($operation, EXTR_OVERWRITE);
			if (!is_subclass_of($class, 'Relation'))
				return null;
			return $rel ? $class::get($referent,$rel)->getOperation($operation) : $class::getClassOperation($referent,$operation);
		}
		
		static $standard = array(
			// View is a full-featured explorer for Relations of a particular class for a given referent. Analogous to a_Display and a_Browse combined.
			 'view'=>array('icon'=>'th-list', 'noun'=>'plural', 'verb'=>"Work with", 'action'=>'a_Browse')
			// Select allows for managing which relatives are related to a given referent by a given Relation class. It's centered around an include/exclude binary selection interface.
			,'select'=>array('icon'=>'list-alt', 'noun'=>'plural', 'verb'=>"Select or Add", 'action'=>'a_Relate')
		);
		if (!isset(static::$operations) || !is_array(static::$operations) || !is_array(static::$operations[$operation]))
			return logWarn("Operation '$operation' not defined for class '". get_called_class() ."'.", null);
		$opspec = static::getOpSpec($operation, $standard);
		if ($c instanceof Context)
			$opspec['target'] = $c->target(array_merge($args, array('operation'=>$operation, 'class'=>get_called_class(), 'ref'=>$referent->_id)), $accept, $actionArg, $actionArg ? $opspec['action'] : null);
		return $opspec;
	}

	/**
	* Use this to obtain a dynamically filled-out Operation specification which includes a target generated by the passed context.
	* We override (if present in $args) and pass arguments 'operation','class','ref', and 'rel'. The rel argument is treated as the dynamic arg in the target.
	* @param string $operation Any operation specification found in the called class's operations list. If the operation is not found there, we return null.
	* @param Context $c (optional) The rendering context from which to obtain the target rendering. Without this no target is generated and none of the following args are used.
	* @param string $actionArg (optional) The name of the argument to pass containing the operation's action value as its value.
	*	The assumed value is '_action' which would be automatically handled by Action::render().
	*	If you pass null here, then the target produced will not include the action at all, leaving the caller to invoke the new Action internally on its reinvocation.
	* @param mixed[] $args (optional) Any args you'd like passed through in the target. Perhaps initializers or other contextual clues which will be recognized by the targeted Action.
	* @param boolean $accept (optional) If you need data to be posted along with the request, set this to true. 
	*	This is most often done in conjunction with an $actionArg value which will be processed by the triggering InputAction, allowing it to save some input data before jumping to the new Action.
	* @return string[] An associative array defining the Operation with key 'target' set to a raw URL, requesting which will invoke the operation.
	*/
	public function getOperation( $operation, Context $c = null, $actionArg = '_action', $args = array(), $accept = false )
	{
		static $standard = array(
			// Edit allows setting the contents of a Relation record, be it new or existing.
			 'edit'=>array('icon'=>'edit', 'noun'=>'descriptive', 'verb'=>"Edit", 'action'=>'a_Edit')
			// Remove effects the unassignment of a relation, including destroying the data in its fields.
			,'remove'=>array('icon'=>'remove-circle', 'noun'=>'singular', 'verb'=>"Remove", 'action'=>'a_Delete')
		);
		if (!$this->_ref || !$this->_rel)
			return null;
		$opspec = static::getOpSpec($operation, $standard);

		if (is_string($opspec['target'])) { // If opspec['target'] is already specified as a string, it's the name of a different handler script
			$args = array_merge($args, $opspec['args'], $this->asArgs());
			foreach ($args as $arg=>$val)
				$argpairs[] = urlencode($arg) .'='. urlencode($val);
			$opspec['target'] = "$c->urlbase/$opspec[target]?". implode('&', $argpairs);

		} else if ($c instanceof Context) {
			if ($actionArg)
				$args[$actionArg] = $opspec['action'];
			$opspec['target'] = $c->target(array_merge($args, $opspec['args'], array('class'=>get_class($this), 'ref'=>$this->_ref)), $accept, 'rel', $this->_rel);
		}
		return $opspec;
	}
	
	/**
	* This is a convenience function for actions rendering a Relation. 
	* It returns an assoc array of commonly used variables associated with the static definition of the Relation. 
	* Wherever relevant, the values are ready to render as HTML... no further conversion is required.
	* Obviously the names of these variables is conventional. You'll want to ensure your local namespace is OK with these variables if you extract() the return value.
	* @param mixed[] $hints The hints array may contain conventional modifiers suggesting how this relation should be rendered. Some of these might affect the results returned.
	* @return string[] See the return statement below to observe which values are returned.
	*/
	public static function obtainRenderingVars( array $hints = array() )
	{
		$fields = static::getFieldDefs(null, $hints['exclude'], $hints['include']);
		$dataless = count($fields) == 0 || $hints['dataless'];
		$relatives = htmlentities(static::$plural);
		$relative = htmlentities(static::$singular);
		$descriptive = htmlentities(static::$descriptive);
		$verb = isset(static::$verb) ? htmlentities(static::$verb) : 'assign';
		$verbed = isset(static::$verbed) ? htmlentities(static::$verbed) : 'assigned';
		$unverb = isset(static::$unverb) ? htmlentities(static::$unverb) : 'unassign';
		$unverbed = isset(static::$unverbed) ? htmlentities(static::$unverbed) : 'unassigned';
		$ks = static::$keys; // to get a fresh pointer
		list($refkey,$refclass) = each($ks);
		list($relkey,$relclass) = each($ks);
		return compact('fields','dataless','relatives','relative','descriptive','verb','verbed','unverb','unverbed','refkey','refclass','relkey','relclass');
	}
	
	/**
	 * Obtains a list of identifying-only Relatives, IGNORING all relation data, indexed by their Element ids.
	 * This is an Element list, indistinguishable in from the return value of Element::getList() except that the result is always filtered to include only those duly related to the referent.
	 * Because you get (likely space) Element objects, you can render each in full with $result[$rel]->render($c).
	 * @param Element $referent The "referent" is the "anchor" or starting place of a relational setting. It's the element you have in hand as you seek relationships to others.
	 * @param array $parms (Optional) can contain any of the standard multi-result specifiers: sortfield, reverse, where, limit, start.
	 * @return Element[] Unless there's an error we ALWAYS return an ARRAY of objects, even if there's only 0 or 1 objects in it.
	 */
	public static function getRelativesList( Element $referent, array $parms = array() )
	{
		extract($vars = static::obtainRenderingVars());
		$parms['identifying'] = true;
		$parms['where'] .= "`{}`._id ". ($parms['inverse'] ? 'NOT ' : null) ."IN (SELECT _$relkey FROM `". static::$table ."` WHERE _$refkey = $referent->_id)";
		return $relclass::collection($parms);
	}
	
	/**
	 * The PLURAL CONSTRUCTOR for EXISTING instances
	 * Every relation object includes its full relative object as well, so you have a full set of relation-as-mask data fields available.
	 * @param Element $referent The "referent" is the "anchor" or starting place of a relational setting. It's the element you have in hand as you seek relationships to others.
	 * @param array $parms (Optional) can contain any of the standard multi-result specifiers: sortfield, reverse, where, limit, start.
	 * 	The presumed sorting is by the relative first, but if sortfield is specified, then it will outweigh that rule.
	 * @return Relation[] Unless there's an error (missing or bad focus or missing id/rel), we ALWAYS return an ARRAY of objects, even if there's only 0 or 1 objects in it.
	 */
	public static function getRelations( Element $referent, array $parms = array() )
	{
		$class = get_called_class();
		$keys = array_keys(static::$keys);
		$classes = array_values(static::$keys);
		if ($classes[0] != get_class($referent)) // we should be smart enough to locate the matching class if in an *include'd hierarchy
			throw new ErrorException("Referent object does not match relation referent class.");
		$result = array();
		$q = $class::loadInstanceData($referent->_id, $parms);
		while ($record = $q->next) { // assignment intended
			$x = $class::getInstanceFromLoadedData($record, $q->queryTime);
			$result[$x->key[$keys[1]]] = $x;
		}
		return $result;
	}

	/**
	 * The SINGULAR CONSTRUCTOR for NEW AND EXISTING instances
	 * Loads a single relation object or creates it.
	 * To create a new relation provide the referent and relative elements. If the relation already exists, its information will be loaded from the database and you'll have the object as it already existed.
	 * If it does not already exist, it will be created when you first store it.
	 * @param Element $referent The referent element, ie. the element you already have from which you seek its relationships.
	 * @param mixed $relative You can provide the relative specification as any of the following:<ul><li>Element instance</li><li>A MySQL WHERE clause</li></ul>
	 * @param array $initial (Optional) Associative array of field values with which to set the fields of the instance. These values will be applied to the instance regardless of whether it was pre-existing.
	 * @return Relation Returns a single instance object or returns NULL if the object was not found
	 */
	public static function get( Element $referent, $relative, $initial = null ) // it is possible to look for a single instance via a where clause, but it must return 1 result or it's an error.
	{
		$class = get_called_class();
		$keys = array_keys(static::$keys);
		$classes = array_values(static::$keys);
		if ($classes[0] != get_class($referent)) // we should be smart enough to locate the matching class if in an *include'd hierarchy
			throw new ErrorException("Referent object does not match relation referent class.");

		if ($relative instanceof Element) { // there is only one record out there if any...
			if ($classes[1] != get_class($relative)) // we should be smart enough to locate the matching class if in an *include'd hierarchy
				throw new ErrorException("Relative object does not match relation relative class.");
			$key = array($keys[0]=>$referent->_stored ? $referent->_id : '*', $keys[1]=>$relative->_stored ? $relative->_id : '*');
			if ($relative->_stored && $referent->_stored) {
				$q = $class::loadInstanceData($key);
				if ($record = $q->next) // assignment intended
					return $class::getInstanceFromLoadedData($record, $q->queryTime);
			}
			$instance = new $class;
			$instance->key = $key;
			$instance->keyed = array($keys[0]=>$referent, $keys[1]=>$relative);
			if (is_array($initial) && count($initial))
				$instance->acceptValues($initial);
			return $instance;

		} else if (is_string($relative)) {
			$q = $class::loadInstanceData($referent->_id, array('where'=>$relative));
			if ($q->rowCount > 1)
				throw new dbNotSingularX("Use get to obtain a single record only. You where clause obtained multiple records.");
			return $class::getInstanceFromLoadedData($q->next, $q->queryTime);
		}

		throw new ErrorException("To get a relation, supply the referent element and either a relative object or a where clause for selection of the relation or relative record.");
	}

	public function getMetaProperty( $name )
	{
		$keys = array_keys(static::$keys);
		switch ($name) {
			case "$keys[0]":
			case "$keys[1]":	return $this->key[$name];
			case "$keys[0]_":
			case "$keys[1]_":	return $this->keyed[$name];
			case 'referent':		return $this->keyed[$keys[0]];
			case 'ref':				return $this->key[$keys[0]];
			case 'relative':		return $this->keyed[$keys[1]];
			case 'rel':				return $this->key[$keys[1]];
			case 'cacheHash':	// We still have the problem that we can't save the classname; can object casting work?
				sort($keys);
				foreach ($keys as $key)
					$handles[] = $this->key[$key];
				return $this::$table ."[". implode(',', $handles) ."]";
			case "handle":
				return get_class($this) .'='. $this->key[$keys[0]] .':'. $this->key[$keys[1]];
			case "stored": // tough question... our key's definition only means the elements have been stored... to see if the relation actually exists in the database, we may actually need to ask the database
				if ($this->loaded['_loadtime'])
					return true; // at least we know loaded implies stored
				foreach ($keys as $key)
					if (!$this->keyed[$key]->_stored)
						return false;
				try {
					return 0 < $GLOBALS['root']->dbGetScalar("SELECT COUNT(*) FROM `". $this::$table ."` WHERE `_$keys[0]`={$this->key[$keys[0]]} && `_$keys[1]`={$this->key[$keys[1]]}",
							"Check stored status of relation");
				} catch (dbMissingTableX $ex) {
					return false;
				}
		}
		return parent::getMetaProperty($name);
	}

	function __toString()
	{
		return static::$descriptive .": $this->_referent -> $this->_relative";
	}

}
