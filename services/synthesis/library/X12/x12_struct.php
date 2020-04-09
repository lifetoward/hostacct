<?php

abstract class x12_struct // a struct is a conceptual model by which segments are organized in the EDI format. It is a commonly type-referenced base class for the more specific implementations
{
	protected $label, $usage = 'R';
	protected $repeatable = 0, $repetition = null; // we can build a daisy chain of repeated instances of a structure as long as its repeat value is not 0

	public function __construct( $label, $usage, $repeat )
	{
		$this->label = $label;
		$this->repeatable = $repeat == 1 ? 0 : $repeat;
		switch ($usage) { case 'R': case 'S': case 'N': $this->usage = $usage ; }
	}

	public function initialize( ) // put intrisically defined values into an already-constructed structure... useful and necessary when building an interchange from scratch.
	{
		// There's no general case for initialization, but we want the method to be here for all structure types because for many it is a no-op
	}

	public function __get( $name ) // allow read-only access to the defining properties
	{
		if ('_label'==$name)
			return $this->label;
		if ('_usage'==$name)
			return $this->usage;
		if ('_repeat'==$name)
			return $this->repeatable;
		if ('assigned'==$name)
			return $this->assigned;
	}

	abstract public function assigned( ); // returns boolean to indicate whether any data within this structure has been assigned; if a structure is unassigned and not required, it will not be rendered

	abstract public function render( ); // return the x12-compliant flat serialized output stream appropriate to this structure if its status is "assigned"

	abstract public function parse( $content ); // read an x12-compliant flat file to populate an already-constructed structure

	abstract public function count_segments( ); // return the count of all >>RENDERABLE<< segments embodied by this structure; renderable implies assigned() || required

	public final function repeat( )
	{
		if (!$this->assigned())
			return $this;
		if (!$this->repeatable)
			throw new Exception("Attempt to repeat a structure which cannot be repeated.");
		if ($this->repetition)
			return $this->repetition->repeat(); // we can only ever add to the end of the chain
		$x = clone $this;
		$x->repeatable--;
		$x->usage = 'S'; // we force usage to S for any repeated instances because there's no spec rule that sets a minimum repeat count
		return $this->repetition = $x;
	}

	public final function count( ) // count just tells you how many of repetitions of the exact segment type are in the transaction but it does not tell you if they are ready, only existing
	{
		return 1 + ($this->repetition ? $this->repetition->count() : 0);
	}
}

?>
