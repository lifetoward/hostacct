<?php
/**
* Context is tbe base class for the context information which underpins each request and runtime context.
* There are two flavors of context: a root context (Request) and an action subcontext (SubContext)
*
* All original code.
* @package Synthesis
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2014 Lifetoward LLC
* @license proprietary
*/
class Context
	implements Serializable
{
	protected $export = array(); // stores arbitrary app-defined attributes of the context itself

	public function serialize()
	{
		global $_serializedObjects;
		// Because Context is not abstract, we must handle the case when we are the leaf class.
		if (!isset($_serializedObjects[$hash = spl_object_hash($this)]))
			$_serializedObjects[$hash] = ++$_serializedObjects['counter'];
		// Now add on each of the appropriate properties
		$props['export'] = $this->export;
		if ($this instanceof RootContext) // A context's action is saved only if the context is a root action, ie. a subclass of Request.
			$props['action'] = $this->action;
		$z = mb_strlen($p = "{$_serializedObjects[$hash]}:". serialize($props));
		return __CLASS__.":$z:$p"; // parsable by pullSerialString($rep)
	}

	public function unserialize($rep)
	{
		list($OR,$propRep) = explode(':', pullSerialString($rep, __CLASS__), 2);
		$GLOBALS['_renewedObjects'][$OR*1] = $this;
		$props = unserialize($propRep);
		array_walk_recursive($props, 'renewObjects');
		$this->export = $props['export'];
		$this->action = $props['action'];
	}

	/**
	* Call this to get a new instance of this class. We protect the __construct() method in root context subclasses.
	* @param array $export (optional) You can provide a a set of attribute name-value pairs to initialize the Context.
	* @return Context A newly constructed Context instance.
	*/
	public static function create( array $export = null )
	{
		$class = get_called_class();
		return new $class($export);
	}

	/**
	* Many subclasses may make this public, but because some do not, we have it protected here.
	* @param array $export (optional) You can provide a a set of attribute name-value pairs to initialize the Context.
	* @return Context A newly constructed Context instance.
	*/
	protected function __construct( array $export = null )
	{
		foreach ((array)$export as $name=>$value)
			$this->$name = $value; // this takes us through the root context filtering
	}

	public function __get( $name )
	{
		return $this->export[$name];
	}

	public function __set( $name, $value )
	{
		$this->export[$name] = $value;
	}

	public function __unset( $name )
	{
		unset($this->export[$name]);
	}

	/**
	* This method's job is to produce a string which provides hints to chain from the current runtime context to another new one after termination.
	*	It must duly pass all the appropriate calling parameters to a targeted action on the other side of valley of death.
	* This dummy should be re-implemented by a root or sub context.
	* @param mixed[] $args Name-value pairs which serve as the primary data transferred to the targeted action.
	* @param int $accept Set to true or > 0 to permit posted data, and set > 1 to permit File upload
	* @param string $dynarg The name of the single dynamic argument.
	* @param mixed $dynval The optional dynamic argument value, which must be some sort of scalar.
	*		If you don't pass one, you are allowed to append one to the end of the target you get back from this, as long as it's duly stringified and url encoded.
	* @return string A string which encodes the hints required to invoke a follow-on runtime under the included parameters.
	*/
	public function target( array $args = null, $accept = false, $dynarg = null, $dynval = null )
	{
		foreach ($args as $n=>$v)
			$result .= "$n=$v ";
		$result .= "_accept=$accept ";
		if ($dynarg)
			$result .= "$dynarg=$dynval";
		return $result;
	}
}
