<?php

class x12_semantic extends x12_segment
{
	protected $index = 1; // Identifies which element in the segment matters to semantically differentiate otherwise identical segment types. Normally it's the first element.
	protected $semCodes = array(); // This array holds the effective set of semantic qualifier codes; that is, it's the complete set (code=>value) filtered and augmented by the list which applies to this instance

	public static function stream( $semcode /*, ... variable args */ )
	{
		$class = get_called_class();
		$args = func_get_args();
		$seg = new $class(array_shift($args));
		call_user_func_array(array($seg, 'initialize'), $args);
		return $seg->render();
	}

	// $elements[0] = array(  "{SEGMENT IDENTIFIER CODE}", "{SPECIFIC QUALIFIER}" | array("{SPECIFIC QUALIFIER}", ...), array("{RECOGNIZED QUALIFIER}"=>"{DESCRIPTION}", ...) [, {QUALIFER ELEMENT INDEX} ] )
	public function __construct( $label, array $elements, $usage = 'R', $repeatable = 0 )
	{
		if (!is_array($elements) || !is_array($elements[0]))
			throw new Exception("SEMANTIC '$label' requires a properly formatted elements list.");
		$semDef = array_shift($elements); // semDef is the first element, which for semantics includes a bunch of info
		array_unshift($elements, array_shift($semDef)); // semDef now contains the list of relevant semantic codes
		parent::__construct($label, $elements, $usage, $repeatable);
		list($specifics, $recognized, $index) = $semDef;
		foreach ((array)$specifics as $specific)
			$this->semCodes[$specific] = isset($recognized[$specific]) ? $recognized[$specific] : "$label $specific";
		if (is_numeric($index) && $this->elements[$index] instanceof x12el_code)
			$this->index = $index;
		$this->elements[$this->index]->options = $this->semCodes;
		$this->elements[$this->index]->strict = true;
		if (1 == count($this->semCodes))
			$this->elements[$this->index]->_ = array_shift(array_keys($this->semCodes)); // initialize this because it is structurally relevant and fully known
	}

	public function is_next( $content )
	{
		list($ours, $remainder) = explode(x12seg_ISA::$delimiters['segment'], $content, 2);
		$elements = explode(x12seg_ISA::$delimiters['element'], $ours);
		if ($elements[0] != $this->elements[0])
			return false;
		return isset($this->semCodes[$elements[$this->index]]);
	}

	public function assigned( )
	{
		foreach ($this->elements as $e)
			if ($e->assigned && $e !== $this->elements[$this->index]) // we must exclude the semantic qualifier code when considering whether we're conveying information
				return true;
		return false;
	}

}

?>
