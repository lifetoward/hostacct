<?php

class x12_segment extends x12_struct // the segment is the basic record unit of the EDI/X12 model and is an ordered collection of elements
{
	public static $segmentCount = 0;
	protected $elements; // an array of instances of the x12el_ element classes

	public function report( $indent = 0 )
	{
		if (!$this->assigned())
			return null;
		for ($x = 0; $x < $indent; $x++)
			$tabs .= "\t";
		foreach ($this->elements as $el)
			if (is_object($el) && $el->assigned())
				$els[] = $el->report();
		return "Segment {$this->elements[0]} \"$this->label\":{ ". implode('; ', $els) ." }{$this->elements[0]}". ($this->repetition ? "\n$tabs". $this->repetition->report($indent) : null);
	}

	public static function stream( /* variable args as expected by specific initialization methods */ )
	{
		$class = get_called_class();
		$seg = new $class();
		call_user_func_array(array($seg, 'initialize'), func_get_args());
		return $seg->render();
	}

	public function initialize( array $basic )
	{
		$this->assign($basic);
	}

	public function __construct( $label, array $elements, $usage = 'R', $repeat = 0 )
	{
		parent::__construct($label, $usage, $repeat);
		if (count($elements) < 1)
			throw new Exception("Segment '$label' must be constructed with at least a handle.");
		if (!preg_match('/^[A-Z][A-Z0-9]*$/', $handle = array_shift($elements)))
			throw new Exception("SEGMENT '$label' has an invalid handle '$handle'");
		$this->elements = array($handle);
		while ($element = array_shift($elements)) {
			if ($element instanceof x12_element)
				$this->elements[] = $element;
			else
				throw new Exception("SEGMENT '$label' contained a non-element.");
		}
	}

	public function __clone( )
	{
		foreach ($this->elements as &$element)
			if (is_object($element)) // skip the string identifier at position 0
				$element = clone $element;
		$this->repetition = null;
	}

	public function assign( /* args will be interpreted in any of several ways ... */ )
	{
		$args = func_get_args();
		$values = is_scalar($args[0]) ? $args : $args[0];
		if (!isset($values[0])) {
			foreach ($values as $x => $value)
				if (is_object($e = $this->elements[$x]))
						$e->assign($value);
		} else {
			$x = 1;
			foreach ($values as $value)
				if (is_object($e = $this->elements[$x++]))
						$e->assign($value);
		}
	}

	public function assigned( )
	{
		foreach ($this->elements as $e)
			if ($e->assigned)
				return true;
		return false;
	}

	public function parse( $content )
	{
		$delimiters = x12seg_ISA::$delimiters;
		if (!$this->is_next($content)) {
			if ($this->usage == 'R')
				throw new Exception("Unexpected segment identifier! Expected '{$this->elements[0]}' but got '". strstr($content, $delimiters['element'], true). "'.");
			logDebug("SEGMENT '$this->label ({$this->elements[0]})' is neither required nor present.");
			return $content;
		}
		list($ours, $remainder) = explode($delimiters['segment'], $content, 2);
		$elements = explode($delimiters['element'], $ours);
		array_shift($elements); // remove the segment identifier, which is not considered an element value and has already been confirmed present
		foreach ($this->elements as $element)
			if (is_object($element)) // skip the segment identifier
				$element->assign(array_shift($elements));
		logDebug("SEGMENT '$this->label ({$this->elements[0]})' has been parsed and assigned. (". ++static::$segmentCount .")");
		if ($this->is_next($content = ltrim($remainder)) && $this->repeat())
			$content = $this->repetition->parse($content);
		return $content;
	}

	public function is_next( $content )
	{
		return 0==strncmp($content, $this->elements[0] . x12seg_ISA::$delimiters['element'], strlen($this->elements[0])+1);
	}

	public function __get( $name )
	{
		if ('_handle'==$name)
			return $this->elements[0];
		if ('element'==$name)
			return $this->elements;
		return parent::__get($name);
	}

	public function count_segments( )
	{
		return $this->assigned() ? 1 + ($this->repetition ? $this->repetition->count_segments() : 0) : 0;
	}

	public function render( )
	{
		$delimiters = x12seg_ISA::$delimiters;
		if (!$this->assigned())
			return null;
		foreach ($this->elements as $e) {
			if (is_string($e)) // fixed segment indicator, ie. element 0
				$rendered[] = $e;
			else if (!$e->assigned) // empty elements
				$rendered[] = null;
			else
				$rendered[] = $e->render(); // must do it this way rather than "$e" to ensure delimiters are sub'd out of the stream
		}
		self::$segmentCount++;
		return preg_replace("/\\$delimiters[element]*$/", null, implode($delimiters['element'], $rendered)) ."$delimiters[segment]". ($delimiters['segment'] == "\n" ? null : "\n");
	}

}

?>
