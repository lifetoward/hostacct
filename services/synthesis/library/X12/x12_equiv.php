<?php

/* The equiv structure is implied in the X12 spec.
It is used to group "equivalent structures" such that any of its members may appear in the data stream in any order.
Equivalent structures include loops of the same level such as "2010AA" and "2010AB" or segments of the same type which appear contiguously in the spec.
	Usually segments that require grouping in this way are semantic and such that some qualifier codes are required while others are not.
Equiv structures are required if and only if any of their members are required. They do not themselves hold such a distinction.
	Their usage flag is set during construction by examining the members that are constructed within it.
Equiv structures cannot repeat, but their members certainly may repeat.
	However, during parsing, it has an implied indefinite repetition.
	When parsing, an equiv structure will consume content and parse any of its members until it finds a structure which is not among its members in the stream.
*/
class x12_equiv extends x12_struct
{
	protected $members;

	public function report($indent = 0)
	{
		if (!$this->assigned())
			return null;
		for ($x = 0; $x < $indent; $x++)
			$tabs .= "\t";
		foreach ($this->members as $mbr)
			if ($mbr->assigned())
				$mbrs[] = $mbr->report($indent+1);
		return "Section $this->label:\n\t$tabs". implode("\n\t$tabs", $mbrs) . ($this->repetition ? "\n$tabs". $this->repetition->report($indent) : null);
	}

	public function __construct( $label, $members )
	{
		$this->members = array();
		$usage = 'S';
		foreach ($members as $member) {
			if ( ! $member instanceof x12_struct || $member instanceof x12_equiv )
				throw new Exception("Members of an x12_equiv structure '$label' must be x12_struct but not x12_equiv.");
			$this->members[] = $member;
			if ($member->usage == 'R')
				$usage = 'R';
		}
		parent::__construct($label, $usage, 0);
	}

	public function __clone( )
	{
		foreach ($this->members as &$m)
			$m = clone $m;
		$this->repetition = null;
	}

	public function assigned( )
	{
		foreach ($this->members as $member)
			if ($member->assigned())
				return true;
		return false;
	}

	public function render( ) // return the x12-compliant flat serialized output stream appropriate to this structure
	{
		if (!$this->assigned())
			return null;
		foreach ($this->members as $member)
			$result .= $member->render();
		return $result;
	}

	public function parse( $content ) // read an x12-compliant flat file to populate an already-constructed structure
	{
		logDebug("EQUIV '$this->label' is being explored...");
		for ($foundOne = true; $foundOne; $foundOne = false) // we have an outer loop because the order and frequency of appearance of any of our members is undefined at this level
			foreach ($this->members as $member)
				if ($member->is_next($content)) {
					$content = $member->parse($content);
					$foundOne = true;
				}
		logDebug("EQUIV '$this->label' ends here.");
		return $content;
	}

	public function count_segments( ) // return the count of all >>renderable<< segments embodied by this structure
	{
		$total = 0;
		foreach ($this->members as $m)
			$total += $m->count_segments();
		return $total;
	}
}

?>
