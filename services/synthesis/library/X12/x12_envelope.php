<?php

class x12_envelope extends x12_struct // an envelope is a structure of segments which has a trailer, header, and payload.
{
	public $header, $payload, $trailer;

	public function report( $indent = 0 )
	{
		if (!$this->assigned())
			return null;
		for ($x = 0; $x < $indent; $x++)
			$tabs .= "\t";
		return $this->header->report($indent) ."\n$tabs\t".
			$this->payload->report($indent+1) ."\n$tabs".
			$this->trailer->report($indent) . ($this->repetition ? "\n$tabs". $this->repetition->report($indent) : null);
	}

	public function __construct( $label, x12_env_header $header, x12_segment $trailer, $usage, $repeat, x12_struct $payload = null )
	{
		$this->label = $label;
		$this->header = $header;
		$this->trailer = $trailer;
		$this->usage = $usage;
		$this->repeat = $repeat;
		if ($payload)
			$this->payload = $payload;
	}

	public function __set( $name, $value )
	{
		if ('payload' == $name)
			$this->payload = $value;
	}

	public function __clone( )
	{
		$this->header = clone $this->header;
		$this->trailer = clone $this->trailer;
		$this->payload = clone $this->payload;
		$this->repetition = null;
	}

	public function assigned( )
	{
		return $this->payload && ($this->header->assigned() || $this->payload->assigned() || $this->trailer->assigned());
	}

	public function render( )
	{
		if ($this->assigned() || $this->usage == 'R')
			return $this->header->render() . $this->payload->render() . $this->trailer->render() . ($this->repetition ? $this->repetition->render() : null);
		return null;
	}

	public function count_segments( )
	{
		return $this->assigned() ? 2 + $this->payload->count_segments() + ($this->repetition ? $this->repetition->count_segments() : 0) : 0;
	}

	public function parse( $content )
	{
		logDebug("ENVELOPE '$this->label' is being parsed...");
		$content = $this->header->parse($content);
		// The header should tell me how to parse the payload
		$payloadClass = $this->header->payload_class();
		$this->payload = new $payloadClass();
		$content = $this->payload->parse($content);
		$content = $this->trailer->parse($content);
		logDebug("ENVELOPE '$this->label' ends here.");

		// Finally check to see if I'm repeating in the content, and if so, create and parse the repetition.
		if ($this->is_next($content)) {
			$repetition = $this->repeat();
			$content = $repetition->parse($content);
		}
		return $content;
	}

	public function is_next( $content )
	{
		return $this->header->is_next($content);
	}

	public function initialize( )
	{
		$this->header->initialize();
		if ($this->payload)
			$this->payload->initialize();
		$this->trailer->initialize();
	}

}

interface x12_env_header
{
	public function payload_class(); // returns the name of the class of the payload of the envelope.
}

?>
