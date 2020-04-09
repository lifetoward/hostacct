<?php
/**
* This context is specially designed to be created by actions which need to call subactions.
* An action must create one of these subcontexts for itself during construction setting it as $action->context. It is a saved property so this context is persistent for the life of the action.
* Action contexts provide these things to allow actions to work in multiple UI scenarios:
* 	- Request chaining, target building, request presentation, subchaining (may require nesting context)
*	- Providing rendering hints (like idprefix and mode) to rendering delegates (like subclasses of Type)
*	- Persistent Data export to subactions; if you want your subactions to be aware of information you have set for them, be sure to
*		1) set the variable in the context (like $this->context->ExportedObject = myObject;) and
*		2) Make sure you always pass your own context when calling the subaction's render() method. NOTE this is always done for you in Action->render()... you only need to worry about this if override that.
*	- Provide access to all environmental information from all nesting contexts, including application configuration,
*	- Provide access to methods in nesting contexts, including all database access methods!
*
* All original code.
* @package Synthesis
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
class SubContext extends Context
{
	protected $parmCache = array(), // A subcontext maintains its own "inner" request data on behalf of its action...
		$request = null; // it will assemble and unpack chaining parameters working with nesting contexts
	public $nest = null; // These must be public because both are set from outside the context (by the action) rather than being serialized.

	use SerializationHelper;
	private static $savedProperties = array('parmCache');

	// This is here only to make the constructor public and limited to a single required parameter.
	public function __construct( Context $parent )
	{
		$this->nest = $parent;
	}

	/**
	* We allow read-only access to the request parameters, and if we haven't initialized them yet (unpacked from a selected target), then we do that first.
	* We also fetch requested stored values from nesting contexts if they're not set locally, effecting the export-to-subactions functionality.
	*/
	public function &__get( $name )
	{
		if ($name == 'request') {
			if (is_null($this->request))
				$this->set_request();
			return $this->request;
		}
		return array_key_exists($name, $this->export) ? $this->export[$name] : ($this->nest instanceof Context ? $this->nest->$name : null);
	}

	/**
	* As a handy convenience for all rendering and processing operations, we make any methods provided by the supported action or by any nesting contexts and their actions
	* callable through this object. Note that this is not particularly safe except for static methods, but it sure is handy for getting database functionality and other such things
	* wherever it needs to be.
	*/
	public function __call( $name, $args )
	{
		// This makes a context appear to be unified with its lineage
		if ($this->nest instanceof Context)
			return call_user_func_array(array($this->nest, $name), $args);
	}

	/**
	* An action target must embed its parameters into a target that can be passed down from any nesting action contexts.
	* We use parameter caching with the "single dynamic" model.
	* @param mixed[] $args Name-value pairs which serve as the primary data transferred to the targeted action.
	* @param int $accept Set to true or > 0 to permit posted data, and set > 1 to permit File upload
	* @param string $dynarg The name of the single dynamic argument.
	* @param mixed $dynval The optional dynamic argument value, which must be some sort of scalar.
	*		If you don't pass one, you are allowed to append one to the end of the target you get back from this, as long as it's duly stringified and url encoded.
	* @return A URL-encoded not HTML-ready request target which is blessed and known to work within the full nesting context.
	*/
	public function target( array $args = null, $accept = false, $dynarg = null, $dynval = null )
	{
		$new = compact('args','accept','dynarg');
		foreach ($this->parmCache as $hash=>$parms)
			if ($parms == $new)
				break;
		if ($parms != $new)
			$this->parmCache[$hash = substr(uniqid(), -6)] = $new;
		return $this->nest->target(array(), $accept, '_sub', "${hash}_$dynval");
	}

	/**
	* The complement to the target() method above, this method unpacks the triggered request parameters that were set up prior and actually selected in the current request.
	* We set the request property as the central side effect, allowing subsequent access to $c->request to return all the submitted request data which might affect processing.
	* @return void
	*/
	private function set_request()
	{
		// expand the nest's request into a request at our level
		list($nestargs, $data, $cookies, $files) = $this->nest->request;
		if ($nestargs['_sub']) {
			list($x, $dynval) = explode('_', $nestargs['_sub'], 2);
			if (is_array($parms = $this->parmCache[$x])) { // assignment intended
				extract($parms); // accept, args, dynarg
				if ($dynarg)
					$args[$dynarg] = $dynval; // This is a standard function of every request conversion.
			} else
				$args = [];
		}
		$this->request = [ $args, $accept ? $data : null, $cookies, $accept > 1 ? $files : null ];
		$x = 0;
		foreach (['args','post','cookies','files'] as $label)
			$this->request[$label] =& $this->request[$x++];
		$this->parmCache = []; // we clear this once we've consumed it
		logDebug(['Unpacked Request Parameters'=>$this->request]);
	}

}
