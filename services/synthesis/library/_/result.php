<?php
//namespace Lifetoward\Synthesis\_
/**
* An action's execution method (typically render() or render_me()) assembles and then returns one of these result objects.
* Nothing about this kind of object persists across multiple requests... it's entirely for assembling the result of a single request; it is not persistent in the session.
* A result in its most common form may be rendered as HTML, formatted as plain text, or logged to the established log streams.
* Any result can nest an array of results generated from actions directly nested by the action which constructed this result.
* Assembly of a result for output is presumed to include nested results as well. How they are all assembled depends on the nesting result.
*
* All original code.
* @package Synthesis
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright © 2007-2015 Lifetoward LLC
* @license proprietary
*/
class Result
{
	protected $subResults = [] // Results presumably but not necessarily from nested actions can be assembled into this result here.
		, $content = null // This is the main content of the result, ie. typically some textual description or rendering, etc.
		, $focus = null // The focus object is the Instance that is obviously central to the action's purpose; not required.
		, $custom = [ ] // Here we allow setting additional values which might be unique to the action and known only to its specific callers.
		;

	/**
	* We render as a mere HTML-compatible string of whatever was passed to us.
	*/
	public function render() { return htmlentities("$this->content"); }

	/**
	* We format as the language stringification of the provided content
	*/
	public function format() { return "$this->content"; }

	/**
	* Pass an option return value which will be passed through the logging routines and then returned back to you.
	*/
	public function log( $rc = null ) { return logInfo("$this->content", $rc); }

	/**
	* @param mixed $message Pass the message you'd like to convey. We will stringify it, no matter what it is.
	* @param Result $nested (optional) Set this Result as the first subResult.
	* @param mixed[] $properties (optional) An array of custom properties, indexed by their names.
	*/
	public function __construct( $message = null, Result $nested = null, array $properties = [] )
	{
		$this->content = "$message";
		if ($nested)
			$this->subResults[] = $nested;
		$this->custom = array_merge($this->custom, $properties);
	}

	/**
	* @param Result $nested Add this result to the set of nested sub results.
	*/
	public function addResult( Result $nested )
	{
		$this->subResults[] = $nested;
	}

	/**
	* We allow setting arbitrary properties in the result. However, we do NOT permit setting standard properties like content, focus, or subResults this way.
	*/
	public function __set( $name, $value )
	{
		if ($name == 'content')
			$this->content = "$value";
		else if ($name == 'focus' && $value instanceof Instance)
			$this->focus = $value;
		else
			$this->custom[$name] = $value;
	}

	public function __get( $name )
	{
		if (in_array($name, ['content','focus','subResults']))
			return $this->$name;
		return $this->custom[$name];
	}

	public function __toString()
	{
		return $this->format();
	}
}
