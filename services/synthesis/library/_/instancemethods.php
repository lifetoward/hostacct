<?php
/**
* This exception is thrown by Instance->acceptValues() if any of the values passed in failed to be accepted by type or field-specific validation.
* (Compare to exception BadFieldValueX which is thrown by Type->accept() or $class->acceptFieldValue().)
* A developer can process the exception via $ex->problems to see those fieldnames and messages.
*/
class BadFieldValuesX extends Exception
{
	public $problems;
	function __construct( $problems )
	{
		parent::__construct();
		$this->problems = $problems;
	}
	function __toString()
	{
		foreach ($this->problems as $f=>$prob)
			$problemList .= "Field '$f': $prob\n";
		return "Bad Field Values list: \n$problemList\n". parent::__toString();
	}
}

/**
* Used exclusively by Instance
* This has a bunch of methods for manipulating whole instances.
* This is where transaction management methods are.
*
* All original code.
 * @package Synthesis
 * @author Guy Johnson <Guy@SyntheticWebApps.com>
 * @copyright 2007-2014 Lifetoward LLC
 * @license proprietary
*/
trait InstanceMethods
{
	/**
	* Call this function to determine whether a given object or class encapsulates another data class or to get a complete list of all data classes it encapsulates.
	* @param string $qclass The data class to check for.
	* @return boolean True if the subject class encapsulates the passed classname
	*/
	public static function is( $qclass = null )
	{
		if ($qclass == get_called_class())
			return true;
		foreach (static::$fielddefs as $fd)
			if ($fd['type'] == 'include')
				if ($fd['class']::is($qclass))
					return true;
		return false;
	}

	/**
	* Does the complement of the is() method, identifying classes which eventually include this one.
	* @return array A list of instance subclass names which include this class somewhere along the chain.
	*/
	public static function supports( )
	{
		return array();
	}

	public static function getJoins( )
	{
		return isset(static::$joins) && is_array(static::$joins) ? static::$joins : [];
	}

	/**
	* Get the number of objects of this class currently in the database.
	* @param string $where (optional) Filter the count according to this SQL where clause
	* @return integer Count of all objects in the database of this class, and optionally matching the provided where clause.
	*/
	public static function cardinality( $where = null )
	{
		global $root;
		if ($where)
			$where = static::get_joins() ." WHERE ". str_replace('{}', static::$table, $where);
		return 1 * $root->dbGetScalar("SELECT COUNT(*) FROM `". static::$table ."` $where");
	}

	// Helps the preceding and is recursive.
	private static function get_joins( )
	{
		foreach (static::$fielddefs as $f)
			if (in_array($f['type'], array('include','fieldset'))) {
				$inname = $f['class']::$table;
				$j .= " LEFT JOIN `$inname` ON(`$inname`._id=`$name`.`$f[name]`)". $f['class']::get_joins();
			}
		return $j;
	}

	/**
	* When creating an instance, initialization must occur to full depth.
	* Note that we leave it to getInstanceFromLoadedData() to do the equivalent data-inclusive initialization to full depth during loads.
	* @param mixed[] $initial Set of initial values to accept into the instance once it is created.
	* @return Instance Returns an initialized instance of the class created.
	*/
	protected static function create( $initial = null )
	{
		$class = get_called_class();
		$instance = new $class; // just get an empty one... we will make it whole through initialization

		if (!$initial || !is_array($initial))
			$initial = array();

		// Initialize the values of every native field, diving into included fields along the way.
		foreach ($fds = static::$fielddefs as $fn=>$fd) {
			if ($fd['type'] == 'fieldset') {
				if (!is_subclass_of($fd['class'], 'Fieldset'))
					throw new ErrorException("Field '$fn' of type 'fieldset' in class '$class' references class '$fd[class]' which is not a Fieldset.");
				$instance->included[$fn] = $fd['class']::create($class, $fn, $initial[$fn]);
			} else if ($fd['type'] == 'include') {
				if (!is_subclass_of($fd['class'], 'Element'))
					throw new ErrorException("Field '$fn' of type 'include' in class '$class' references class '$fd[class]' which is not an Element.");
				$instance->included[$fn] = $fd['class']::create($initial);
			} else if (isset($fd['initial']))
				$instance->$fn = $fd['initial'];
		}

		if (count($initial))
			$instance->acceptValues($initial);

		return $instance;
	}

	/**
	* Makes a new exact replica of an existing data object from top to bottom.
	* This does not affect the original object or its persistence in the database in any way; it produces a newly created object with deep pre-initialized state.
	* @return Instance The new object which is unstored; Returns null if the object you wanted to duplicate is not the encapcelating object. In such cases, you should do $this->_capcel->duplicate();
	*/
	public function duplicate( )
	{
		$this->load();
		$dupe = clone $this;
		unset($dupe->loaded['_loadtime']);
		$dupe->updated = array_merge($dupe->loaded, $dupe->updated);
		foreach ($fds = $this::$fielddefs as $n=>$f)
			if (in_array($f['type'], array('fieldset','include'))) {
				$dupe->included[$n] = $this->included[$n]->duplicate();
				if ($f['type'] == 'include' || !$this->$n)
					unset($dupe->updated[$n]);
				else
					$dupe->updated[$n] = '*'; // the new fieldset is guaranteed new here... and we know we consider it set
			}
		$dupe->loaded = array();
		$dupe->key = null;
		return $dupe;
	}

	/**
	* The singular destructor of the database persistent aspect of an Instance.
	* After this has been called on an existing instance, the object remains in the hands of the program, unstored and with latent values representing its last known state;
	*		but it is removed from the database.
	* Just let it go from all references to cause it to cease to exist entirely.
	* @return void
	*/
	public function delete( )
	{
		static::trackChanges("Delete $this->_handle");
		$this->registerChanges();

		$this->load();
		unset($this->loaded['_loadtime']);
		$this->updated = array_merge($this->loaded, $this->updated);
		$this->loaded = array();

		if (is_string($this::$keys))
			$keyExprs = array("`_{$this::$keys}` = $this->key");
		else
			foreach ($this::$keys as $key=>$kClass)
				if ($this->key[$key])
					$keyExprs[] = "`_$key` = {$this->key[$key]}";

		if (count($keyExprs) == count((array)$this::$keys)) {
			try {
				foreach ($fds = $this::$fielddefs as $n=>$f)
					if (in_array($f['type'], array('include','fieldset'))) {
						$this->included[$n]->delete();
						unset($this->updated[$n]);
					}
				$GLOBALS['root']->dbQuery("DELETE FROM `{$this::$table}` WHERE ". implode(" AND ", $keyExprs));
			} catch (Exception $ex) {
				static::abortChanges($ex);
			}
		}
		$this->key = null;
		static::commitChanges();
	}

	/**
	* Call this to force loading the database values for this object into the program space. You can do this even if the object has latent updates.
	* @param boolean $force (optional) If the record is already loaded, we don't load it again unless you pass true.
	* @return integer The element ID of this instance.
	*/
	public function load( $force = false )
	{
		if (!$this->_stored)
			return false;
		if ($this->loaded() && !$force)
			return $this->key;
		$q = $this::loadInstanceData($this->key);
		return $this->acceptInstanceData($q->next, $q->queryTime);
	}

	/**
	* This method provides a way for subclasses to provide their own meta properties, ie. which might be answered functionally rather than just drawn from the aux[] array.
	* Meta properties are requested in the form $instance->{_.$name}
	* Instance provides the following meta properties:
	*	_class = (string) The name of the class for this instance
	*	_key = (array) The canonical key value (not a stringified form)
	*	_loaded = (boolean) indication of whether the object is loaded
	*	_latent = (boolean) indication of whether the object has any unstored changes
	*	_meta = (mixed[]) an array of meta properties indexed by their _-prefixed names, including class, key, loaded, latent, stored, and handle
	*	_fields = (string[]) a simple list of all the field names available in the instance
	*	_aux = (mixed[]) all of the auxiliary (nonstorable but session-persistent) properties associated with the instance
	*	_ = (mixed[])
	*   _{static_class_property} = Any static class variable that is defined and not named like a defined meta property can be returned in this way
	* @param string $name The name of the requested meta property, without the preceding underscore character which signifies its being a metaProperty
	* @return mixed Whatever value makes sense for the meta property.
	*/
	public function getMetaProperty( $name )
	{
		if ($name === $this::$keys) // when $this::$keys is a string, the key is an integer (unless the instance is unstored)
			return $this->key ? $this->key : '*'; // '*' is the system-wide "unstored but present" key
		if (is_array($this::$keys) && in_array($name, array_keys($this::$keys)))
			return $this->key[$name] ? $this->key[$name] : '*'; // '*' is the system-wide "unstored but present" key
		if (isset($this->$name)) // esp for instance sysvals
			return $this->$name;

		switch ($name) {
			case 'class': 		return get_class($this);
			case 'key':    		return $this->key;
			case 'loaded':	return $this->loaded['_loadtime'] && true;
			case 'latent':		return $this->latent(true);
			case 'fields': 		return $this->listFields();
			case 'aux':		return ($this->aux);
			case '':				return array_merge($this->_meta, $this->getFieldValues($GLOBALS['root'], array(), array(), true), (array)$this->aux);
			case 'meta':
				foreach (array('class','key','loaded','latent','stored','handle') as $meta)
					$result['_'.$meta] = $this->getMetaProperty($meta);
				return $result;
		}
		return isset($this::$$name) ? $this::$$name : null;
	}

	/**
	* Call this method to set many values within an instance object at one time.
	* Notably used whenever accepting externally obtained (untrusted) user input data for an object. It uses instance- and field-level safety checking on values and then assigns them into the object as updates.
	* We just loop on the submitted values and set them within the object.
	* That means that some values may not be for fields, but rather are being set as transient instance properties instead. We treat that as OK.
	* In the case of real fields, the set operation takes care of all the validity checking, etc.
	* For our part we only confirm that the user is allowed to do "update" on this object (or that such permissions are not defined or that there's no authentication context)
	* @param mixed[] $values Associative array of fieldname=>value pairs to set within the subject instance.
	* @param Context $c (optional) Allows for authorization checking and perhaps other features.
	* @return integer Count of values successfully assigned.
	* @throws BadFieldValuesX if values are illegal, or if the "completeness" flag is true and any required fields are missing.
	*/
	public function acceptValues( array $values, Context $c = null )
	{
		$problems = array(); $assigned = 0;
		foreach ($values as $vn=>$value) {
			try {
				$this->__set($vn, $value);
			} catch (BadFieldValueX $ex) {
				$problems[$vn] = $ex->getMessage();
				continue;
			}
			$assigned++;
		}
		if (count($problems))
			throw new BadFieldValuesX($problems, "There were errors attempting to accept values into $this");
		return $assigned;
	}

	/**
	* Check an object's field(s) to see if they hold unstored updates, ie. "Needs Updating in Persistent Storage"
	* @param mixed $args (optional) Interpreted as follows:
	*	- If you pass a single string argument we check whether the field with that name is latent.
	*	- If you pass a single boolean true, we return a boolean indicating whether any part of the object is latent. This is the sense obtained via shorthand $instance->_latent.
	* 	- If you pass no argument it returns a simple array listing all latent field names.
	*	- If you pass an array or many arguments, each is taken as a field name and if any of them is latent, you'll get true back, otherwise false
	* @return boolean|array See notes for the parameter above.
	*/
	public function latent( $args = null )
	{
		if ($args === true) {
			foreach ((array)$this->included as $i)
				if ($i->latent(true))
					return true;
			return count($this->updated) || false;
		}
		if (!$args) {
			$result = array_keys($this->updated);
			foreach ($this->included as $obj)
				$result = array_merge($result, $obj->latent());
			return $result;
		}
		if (is_string($args) && func_num_args() == 1) {
			if (!is_array($f = $this::$fielddefs[$args])) {
				foreach ($this->included as $subel) {
					try { return $subel->latent($args); }
					catch (NotMyFieldX $ex) { } // fine, we'll just try our other included elements
				}
				throw new NotMyFieldX($args, $this);
			}
			return array_key_exists($args, $this->updated);
		}

		$args = is_array($args) ? $args : func_get_args();
		foreach ($args as $fname)
			if ($this->latent($fname))
				return true;
		return false;
	}

	/**
	* Object instances may or may not be fully populated from the database. In many cases they can be partially or not at all loaded from the database.
	* For example, when they first wake up from serialization they are not loaded. Various attempts to access the fields or methods of an instance will
	* automatically trigger loading as needed. This check allows you to know about that aspect of the instance's state.
	* This is an important method for some instance classes to override because they may have internal properties which themselves need to be loaded
	* and this standard implementation couldn't be aware of that.
	* We provide a shorthand approach to testing via magic boolean property $instance->_loaded .
	* @return boolean Returns true if this instance has been loaded from the database. (It could rather be just a skeleton instead.)
	*/
	public function loaded( )
	{
		return $this->loaded['_loadtime'];
	}

	// This is for debugging and testing.
	public function dump_basics( $note = "DUMP", $level = 0 )
	{
		$horse = clone $this;
		$incls = $horse->included;
		$refd = $horse->referenced;
		$horse->referenced = "{". implode(',',array_keys((array)$horse->referenced)) ."}";
		$horse->restore = "{". implode(',',array_keys((array)$horse->restore)) ."}";
		$horse->included = "{". implode(',',array_keys((array)$horse->included)) ."}";
		logDebug(array("$note level $level"=>$horse));
		if (count($incls))
			foreach ($incls as $i)
				$i->dump_basics($note, $level + 1);
	}

	/*
	* TRANSACTION MANAGEMENT
	* The key concern with transactions in the object model is that if there's an abort during a multi-object database write operation, changes which are specific to the database writes are rolled back from the objects themselves.
	* However, this is a generally useful facility and because during a store operation other object field values may have been updated, we are concerned with all detectable object state changes.
	* We define and handle "detectable" changes like this: Throughout the core instance code we have placed calls to "registerChanges()" which simply means
	* "If we are in a transaction, make sure a restore-point exists for this object because we are about to change it's important state."
	* Note that these static variables do not persist across requests. Transactions only happen within the context of a single request.
	*/

	private static $transactions = null, $transObjects = null;
	private $restore; // This saves a clone of myself (or a subset of one) during transactions so that if the transaction aborts, I can be restored.

	/**
	* Meant to be called only by Synthesis library classes, this method is called to make an object state checkpoint for potential rollback when
	* a transaction is being tracked.
	*/
	protected final function registerChanges()
	{
		if (count(self::$transactions) && !$this->restore) {
			$this->restore = clone $this;
			self::$transObjects[] = $this;
		}
	}

	/** new in v4
	* Call this method to query whether you are operating within a transaction.
	* It's interesting because if you are inside a transaction, then the changes you are making could be rolled back if there's an abort.
	* @param boolean $objects Pass true if you'd rather get a list of ORIGINAL objects which are subject to abort, rather than the description of the controlling transaction.
	* @return boolean|string|Instance[] If there is no transaction in progress, we return false.
	*	Otherwise we normally return the description of the controlling transaction. (Transactions nested inside that one are functionally irrelevant.)
	*	If you pass true, then we return the list of instance objects subject to rollback in their ORIGINAL (premodified) state.
	*/
	public final static function trackingChanges( $objects = false )
	{
		if (!count(self::$transactions))
			return false;
		if ($objects)
			return self::$transObjects;
		return reset(self::$transactions);
	}

	/**
	* Automatically called as the exception handler when you're tracking a transaction.
	* You don't have to do try/catch as this is the effective catch-all block after track() is called until commit() is called.
	* You can also just call us directly (without an exception) for your own reasons.
	* When used we will:
	* 1. Abort the database transaction using rollback.
	* 2. Deregister ourselves as the exception handler.
	* 3. Undo changes in objects that have been registered since the outermost transaction was begun.
	* 4. Clear the transaction stack and the registered objects list.
	* 5. Rethrow the exception we received (if any).
	* THIS FUNCTION MUST APPEAR BEFORE track() IN THE PROGRAM FILE!
	* @param Exception $ex (optional) If you pass an exception, we will throw it for you, saving an extra line of code.
	* @return void
	*/
	public final static function abortChanges( Exception $ex = null )
	{
		global $root;
		restore_exception_handler();
		$root->dbQuery("ROLLBACK");
		if (is_array(self::$transObjects))
			foreach (self::$transObjects as $obj) {
				logDebug("ABORT: Restoring object $obj->_handle to pre-transaction state.");
				foreach (array('key','loaded','updated','included','referenced','formatted','aux') as $restore)
					if ($obj->restore)
						$obj->$restore = $obj->restore->$restore;
				$obj->restore = null;
			}
		if (is_array(self::$transactions))
			while ($trx = array_pop(self::$transactions))
				logDebug("Instance::abortChanges($trx)");
		self::$transactions = null;
		self::$transObjects = null;
		if ($ex)
			throw $ex; // we throw it back out into the handler that existed before we did.
	}

	/**
	* To begin a transaction, call Instance::track(). We will:
	* 1. Push the track request onto our transaction stack.
	* 2. IF we are the first transaction on the stack:
	* 2.a. Begin the transaction in the database.
	* 2.b. Ensure that Instance::abortChanges() is set up as the temporary exception handler
	* 2.c. Initialize Instance::$transObjects which will keep track of any updates made in objects during the transaction span.
	*/
	public final static function trackChanges( $note )
	{
		global $root;
		if (!self::$transactions) {
			$root->dbQuery("START TRANSACTION");
			set_exception_handler( array('Instance', 'abortChanges') );
			self::$transObjects = array();
			self::$transactions = array($note);
		} else
			self::$transactions[] = $note;
		logDebug("Instance::track($note): Begin transaction");
	}

	/**
	* To close a transaction as good, call this. We will:
	* 1. Pop the transaction stack.
	* 2. If the transaction stack is now empty:
	* 2.a. Clear the updated, loaded, and referenced arrays within every instance in the include tree including the top. This is because the "known good" copy of the instance data is now in the database.
	* 2.b. Clear the tracked changes.
	* 2.c. Deregister abortChanges() as the exception handler.
	*/
	public final static function commitChanges( )
	{
		global $root;
		if (!is_array(self::$transactions) || !count(self::$transactions))
			return logWarn("Attempt to commit a transaction when none are active.");
		$note = array_pop(self::$transactions);
		logDebug("Transaction for '$note' is now complete.");
		if (!count(self::$transactions)) {
			$root->dbQuery("COMMIT");
			restore_exception_handler();
			foreach (self::$transObjects as $obj)
				$obj->restore = logDebug("Changes in object $obj->_handle are now committed.",null);
			self::$transObjects = null;
			self::$transactions = null;
		}
	}

	/**
	* @return array Returns an array which specifies this instance in the form of args, ie. with keys 'class' and one for each named key.
	*		Where key values are unknown because the instance is unstored, the value will be empty (null)
	*/
	public function asArgs()
	{
		$result = array('class'=>($class = get_class($this)));
		if (is_array($class::$keys))
			foreach (array_keys($class::$keys) as $key)
				$result[$key] = $this->key[$key];
		else
			$result[$class::$keys] = $this->key;
		return $result;
	}

	// The following is just a shared helper method for the 2 get[Class]Operation(...) methods in Element and Relation.
	// It assembles the Operation spec with its standard defaults and renders some aggregated values based on the content of the operation spec.
	protected static function getOpSpec( $operation, array $standard = array() )
	{
		if (!isset(static::$operations) || !is_array(static::$operations) || !is_array(static::$operations[$operation]))
			return null;
		$opspec = array_merge(isset($standard[$operation]) ? $standard[$operation] : array(), static::$operations[$operation]);
		if ($opspec['reqrole'] && !$GLOBALS['root']->isAuthorized($opspec['reqrole']))
			return logDebug("Operation '$operation' requires role '$operation[reqrole]'; the current user is not authorized.", null);
		$opspec = static::extendOpSpec($opspec);
		$opspec['args']['operation'] = $operation;
		return array_merge($opspec, compact('operation','glyphicon','label'));
	}

	// The following extends rendering hints for any opspec.
	// Specifically icon -> glyphicon and verb/noun -> label
	public static function extendOpSpec( array $opspec )
	{
		if ($opspec['icon'])
			$opspec['glyphicon'] = "<span class=\"glyphicon glyphicon-$opspec[icon]\"></span>";
		if ($opspec['verb'] || $opspec['noun'])
			$opspec['label'] = "$opspec[verb] ". (isset(static::${$opspec['noun']}) ? htmlentities(static::${$opspec['noun']}) : null);
		if (!$opspec['vmsg'])
			$opspec['vmsg'] = $opspec['label'];
		return $opspec;
	}
}
