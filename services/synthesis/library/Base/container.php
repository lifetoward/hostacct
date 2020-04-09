<?php
/**
* A Container is an Element which contains a specific and dedicated set of elements of another class which in aggregate constitute a whole.
*
* This scheme is NOT appropriate for use with large or unbounded (growing over time) sets of contained elements.
*	So for example, it is not appropriate to make a ledger account a containing element of accounting entries because those entries will continue to grow over time.
*	However it WOULD be appropriate to have that relationship for an accounting transaction as containing its accounting entries.
*	In the first case, the set of all contained elements would be expected to grow without bound over time, whereas in the latter case the set is finite and manageably limited.
* Other examples of good uses for this containing element relationship:
* 	A compensation card in an HR scenario, in which several compensation claims are aggregated into a meaningful set.
*	Dedicated attachments, ie. where the attached document cannot possibly be meaningfully shared for other uses. (Are such cases possible?)
*
* Contained elements must uniquely and permanently belong to one containing element. They are not transferrable among containing elements.
* Any such contained element should always be created with an express reference to the containing element. The element cannot be valid without such a reference.
* Contained elements are joined in full during the containing element's load query, however they are "grouped", which means you can define derived fields based on their
*	contents during each containing element load.
* These restrictions are placed upon any contained element class in order to participate in this scheme:
*	- It must NOT contain a defined field called "disposition". We reserve this name as an auxiliary value for use in maintaining the latent contained elements.
*	- They must refer to the Container using a type=belong reference; note that both these reference types disallow null values [and cannot be updated]?.
*	- The signature of the contained element's static create() method must be: public static function create( {ContainingClass} $element, array $initialValues = null )
*
* All original code.
* @package Synthesis/Finance
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
abstract class Container extends Element
{
	/* Subclasses must override $containDef as described below. */
	protected static $containDef = [ // this is just an example... it must be overridden completely by the subclass of Container
		'fname'=>'contained', // name of the pseudo-field in this Instance containing the array of contained elements
		'class'=>'ContainedClass', // the class of Elements which we're containing. Instances of that class "belong" to this Instance.
		'refBy'=>'ReferringField', // contained element's fieldname which refers here i.e. via a belong reference
		];
	protected $contained = []; // this is the instance property in which we keep the actual contained instances
	private static $savedProperties = ['contained'];
	use SerializationHelper;

	/* - - - - - - - - - - FIELD-LEVEL STUFF - - - - - - - - - - - - */

	/**
	* We override getFieldDef() for 2 reasons:
	* 	1. because we must make the contained Element array appear to be a field.
	*	2. because we are introducing the idea of a field which is an alias for another field.
	* 		This allows you to perform most field operations against this alias field, but it won't ever be stored or loaded.
	*/
	public static function getFieldDef( $fn )
	{
		extract(static::$containDef);
		if ($fn == $fname)
			return [ 'name'=>$fname, 'derived'=>"COUNT(`{}_{$class::$table}_`._id)", 'label'=>$class::$plural, 'class'=>'t_integer', 'range'=>'0:' ];
		$fd = parent::getFieldDef($fn);
		if (is_string($fd['alias'])) {
			list($class, $field) = explode('.', $fd['alias']);
			return array_merge($class::getFieldDef($field), $fd);
		}
		return $fd;
	}

	/**
	* We must override getFieldValue because it is the base "getter" method for all field operations
	* and we need to hack-in the pseudo-field which holds the array of contained Elements.
	*/
	public function &getFieldValue( $fn )
	{
		extract($this::$containDef);
		if ($fn != $fname)
			return parent::getFieldValue($fn);
		if (!count($this->contained) && $this->_stored)
			$this->load();
		return $this->contained;
	}

	/**
	* We must override acceptFieldValue because it is the base "setter" method for all field operations
	* and we need to hack-in the pseudo-field which holds the array of contained Elements.
	* When accepting a value for a Container, the contained elements may be included in their entirety, but are not required.
	* If they are included, they must be in a multi-level array format, with the name of the array matching the $containDef name.
	* The array effectively looks like this: $values[$containedSetName][$index][$containedFieldName] = $submittedFieldValue;
	* We require strict matching between the contained elements accepted and those we are aware of in memory as follows:
	*	The indexes must match the known contained elements list; this means the submitter must be aware of the original set and respond with appropriately matching updates.
	*	If the elements are stored, their ids must match.
	*	The disposition must be included among the contained elements field values and be set to "keep", "delete", "change", or "new".
	*	Depending on the known and newly asserted dispositions, other logical constraints apply.
	*/
	public function acceptFieldValue( $fn, $value )
	{
		extract($this::$containDef);
		if ($fn != $fname)
			return parent::acceptFieldValue($fn, $value);

		if (!is_array($value))
			throw new BadFieldValueX($fn, "The ". $class::$plural ." must be submitted together.");

		foreach ($this->contained as $x=>$was) {
			if (!is_array($value[$x]))
				$problems["{$fname}[$x]"] = "Missing ". $class::$singular ." in the submitted data at position $x.";
			else try {
				$was->acceptValues($value[$x], $this);
				$accepted++;
			} catch (BadFieldValuesX $ex) {
				foreach ($ex->problems as $fn=>$problem)
					$problems["{$fname}[$x][$fn]"] = $problem;
			} catch (BadFieldValueX $ex) {
				$problems["{$fname}[$x][{$ex->field['name']}"] = $ex->description;
			}
			unset($value[$x]);
		}
		// Here we have consumed all previously existing entries from the input and confirmed they're all present.
		// If there are any input entries remaining they should have disposition = new and they can be added.
		foreach ($value as $x=>$in) {
			if ($in['disposition'] == 'new')
				$this->contained[] = $class::create($this, $in);
			else
				$problems["{$fname}[$x]"] = "The ". $class::$singular ." received at position $x is unrecognized and is not intended as new.";
		}
		if (count($problems))
			throw new BadFieldValuesX($problems, "Not all data could be accepted as submitted.");
		return $accepted;
	}

	/* - - - - - - - INSTANCE-LEVEL STUFF - - - - - - - - - */

	/**
	* getJoins provides an overridable method for accessing the static property which had fully defined the joins in the past.
	* Now we can hack in fake joins like for this container!
	*/
	public static function getJoins( )
	{
		$explicit = isset(static::$joins) && is_array(static::$joins) ? static::$joins : [] ;
		extract(static::$containDef);
		return array_merge($explicit, [ $class=>"«$refBy" ]);
	}

	public function load( )
	{
		if (!($this->_id*1))
			return null;
		parent::load();
		extract($this::$containDef);
		$elements = $class::collection(array('where'=>$this->_stored ? "`{}`.`$refBy`=$this->_id" : null));
		if (!count($this->contained) && count($elements))
			$this->contained = array_merge($elements); // in our contained array, we need ordinal keys to do input matching
		return $this->_id;
	}

	public function delete()
	{
		if (!$this->_stored)
			return;
		static::trackChanges("Delete $this->_handle: $this");
		$this->registerChanges();
		try {
			foreach ($this->contained as $sub)
				if ($sub->_stored)
					$sub->delete();
			parent::delete();
		} catch (Exception $ex) { static::abortChanges($ex); }
		static::commitChanges();
	}

	public function duplicate()
	{
		$dupe = parent::duplicate();
		$dupe->contained = array();
		$refField = static::$containDef[static::ContainedRefField];
		foreach ($this->contained as $sub) {
			$subdupe = $sub->duplicate();
			$subdupe->$refField = $dupe;
			$subdupe->disposition = $sub->disposition == 'change' ? 'keep' : $sub->disposition;
			$dupe->contained[] = $subdupe;
		}
		return $dupe;
	}

	public function loaded( )
	{
		if (!parent::loaded())
			return false;
		if (!count($this->contained))
			return false;
		return true;
	}

	/**
	* We override the latent method because we're latent if any of our contained elements are latent or if our overall aggregate state is invalid.
	* For the purposes of this method signature, we treat our contained elements as if they were named in aggregate according to $containDef
	* The signature is the same as the parent classes, but it's worth remembering as we consider this "fake" field, our contained elements.
	* @param mixed $args (optional) Interpreted as follows:
	*	- If you pass a single string argument we check whether the field with that name is latent.
	*	- If you pass a single boolean true, we return a boolean indicating whether any part of the object is latent. This is the sense obtained via shorthand $instance->_latent.
	* 	- If you pass no argument it returns a simple array listing all latent field names.
	*	- If you pass an array or many arguments, each is taken as a field name and if any of them is latent, you'll get true back, otherwise false
	* @return boolean|array See notes for the parameter above.
	*/
	public function latent( $args = null )
	{
		list($name, $class, $field) = $this::$containDef;
		if (!is_string($args) || ($cfld = ($args == $name))) { // assignment to boolean intended
			foreach ((array)$this->contained as $sub)
				if ($contained = ($contained || $sub->latent(true)))
					break;
			$contained = $contained || $this->validate();
		}
		if (!is_string($args) || !$cfld)
			$owner = parent::latent($args); // gets my usual fields and handles all cases in which an array is passed in. (We don't handle the array case below.)
		if (is_string($args))
			return $cfld ? $contained : $owner;
		if ($args === true)
			return $owner || $contained;
		if (!$args) {
			$contained && ($owner[] = $field);
			return $owner;
		}
		return false;
	}

	/**
	* As we write a Container's fieldset we must also write out its contained elements.
	*/
	public function storeInstanceData( Database $db )
	{
		$this->validate();
		$this->registerChanges();
		$id = parent::storeInstanceData($db);
		extract($this::$containDef);
		foreach ($this->contained as $sub) {
			if ($sub->disposition == 'delete') {
				if ($sub->_stored)
					$sub->delete();
			} else {
				$sub->updated[$refBy] = $id; // this only matters for new instances, but it's easier than logicalizing.
				$sub->store();
				$compressed[] = $sub;
			}
		}
		$this->contained = $compressed;
		return $id;
	}

	/**
	* This method is added by Container to allow one to validate the entire containing element, considering the state of its contained elements
	* in addition to the Container's state.
	* We call this at the end of acceptValues() and at the top of storeInstanceData(). You may want to call it other times too.
	* It takes and returns no parameters. It should throw an exception if it fails to validate. BadFieldValuesX would be an appropriate exception, but there could be others.
	*/
	public function validate()
	{
	}

	/**
	* Support a_Display with a standard tile for rendering contained elements.
	*/
	public static function ContainedElementsTile( Instance $focus, array $hints, HTMLRendering $R )
	{
		$R->mode = $R::COLUMNAR;
		$out = '';
		extract($focus::$containDef);
	//	foreach ($class::getUltimateClasses() as $uclass) {
		$uclass = $class;
			$coll = $uclass::collection(['where'=>"`{}`.$refBy = $focus->_id"]);
			$labels = ''; $rows = '';
			$exclude = $hints['exclude'] ? (array)$hints['exclude'] : $uclass::$hints['a_Browse']['exclude'];
			$include = $hints['include'] ? (array)$hints['include'] : $uclass::$hints['a_Browse']['include'];
			$fields = (array)$uclass::getFieldDefs($R, $exclude, $include);
			foreach ($fields as $fn=>$fd)
				$labels .= "	<th>". (htmlentities($hints['relabel'][$fn] ? $hints['relabel'][$fn] : $uclass::getFieldLabel($fn))) ."</th>\n";
			if (count($coll))
				foreach ($coll as $d)
					$rows .= '<tr><td>'. implode("</td><td>", $d->renderFields($R, $exclude, $include)) ."</td></tr>\n";
			else
				$rows = "<tr><td colspan=\"". count($fields) ."\">$rows</td></tr>\n";
			$heading = htmlentities($uclass::$plural);
			if ($hints['classOps'])
				foreach ((array)$hints['classOps'] as $opName)
					if ($op = $uclass::getClassOperation($opName, $c, '_action', [ $refBy=>$focus->_id ])) // assignment intended
						$bannerTriggers[] = $R->renderOperationAsButton($op);
			if (count($bannerTriggers))
				$out .= "<span style=\"float:right\">". implode(' ', $bannerTriggers) ."</span>\n";
			$out .= "<h3>$heading</h3>\n<table class=\"table table-striped table-hover table-responsive\">\n".
				"<thead><tr class=\"browse\">$labels</tr></thead>\n<tbody>$rows\n</tbody></table>\n\n";
	//	}
		return $out;
	}

}

