<?php

// a loop is an ordered, type-specific collection of structs which may or may not be repeatable
class x12_loop extends x12_struct
{
	protected $members /* = array(x12_struct, x12_struct, ...) */ ;

	public function report($indent = 0)
	{
		if (!$this->assigned())
			return null;
		for ($x = 0; $x < $indent; $x++)
			$tabs .= "\t";
		foreach ($this->members as $mbr)
			if ($mbr->assigned())
				$mbrs[] = $mbr->report($indent+1);
		return "Loop $this->label:\n\t$tabs". implode("\n\t$tabs", $mbrs) . ($this->repetition ? "\n$tabs". $this->repetition->report($indent) : null);
	}

	public function __construct( $label, array $members, $usage = 'R', $repeat = 0 )
	{
		parent::__construct($label, $usage, $repeat);
		if (! $members[0] instanceof x12_segment || $members[0]->usage != 'R')
			throw new Exception("LOOP '$label' must be constructed with the first member being a required segment.");
		foreach ($members as $member)
			if ($member instanceof x12_struct)
				$this->members[] = $member;
			else
				throw new Exception("LOOP '$label' could not be constructed because the members were not all x12_struct.");
	}

	public function __clone( )
	{
		// The members of a loop should each be cloned in their unassigned state.
		foreach ($this->members as &$member)
			$member = clone $member;
		$this->repetition = null;
	}

	public function assigned( )
	{
		foreach ($this->members as $m)
			if ($m->assigned())
				return true;
		return false;
	}

	public function render( )
	{
		if (!$this->assigned())
			return null;
//		if (!$this->ready() && $this->usage == 'R')
//			logDebug("WARNING: Rendering structure '$this->label' when it is not ready.");
		foreach ($this->members as $instance)
			$result .= $instance->render();
		if ($this->repetition)
			$result .= $this->repetition->render();
		return $result;
	}

	public function parse( $content )
	{
		if (!$this->is_next($content)) {
			if ('R' == $this->usage)
				logDebug("LOOP '$this->label' is required but not present in the content stream! (ERROR)");
			else
				logDebug("LOOP '$this->label' is neither present nor required.");
			return $content; // Loop is optional and its first segment is not present, so we skip the loop
		}
		logDebug("LOOP '$this->label' found in stream... parsing it.");
		foreach ($this->members as $member)
			$content = $member->parse($content);
		logDebug("LOOP '$this->label' ends here.");

		// Finally check to see if I'm repeating in the content, and if so, create and parse the repetition.
		if ($this->repeatable && $this->members[0]->is_next($content) && $this->repeat())
			$content = $this->repetition->parse($content);
		return $content;
	}

	public function is_next( $content )
	{
		return $this->members[0]->is_next($content);
	}

	public function count_segments( )
	{
		foreach ($this->members as $member)
			$count += $member->count_segments();
		if ($this->repetition)
			$count += $this->repetition->count_segments();
		return $count;
	}

	public function initialize( )
	{
		foreach ($members as $member)
			$member->initialize();
	}

}

?>

