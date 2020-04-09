<?php
/**
* This action, intended to be useful for many different elements and relations, lists objects of a particular data class.
* It allows grouping the lists, subsetting them into tabgroups, filtering them through defined filters, etc. to the extent
* the data objects define the appropriate methods and properties to configure those abilities.
* It also allows triggering actions against individual objects or sets of selected objects from the list.
*
* ACTION TRIGGERS
* As a generic action, we need hints found in the instance specification in order to determine the appropriate actions to render.
* There are 3 contexts in which we render action triggers:
* 	* Banner (banner) triggers which act against the class, ie. with Create New or similar operations.
*	* Record (row) triggers which act against the object within its row.
*	* Multi-record (multi) triggers which act against a set of objects selected through something like checkboxes.
*
* All original code.
* @package Synthesis/Base
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2004-2014 Lifetoward LLC
* @license proprietary
*/
class a_Browse extends Action
{
	protected static $modeOptions = ['sortfield','reverse','limit','start','tabgroups','groupby'];

	protected $mode; // Persistent rendering mode settings. Initially configured from passed args during construction, then modified through triggers.
	private static $savedProperties = ['mode'];
	use SerializationHelper;

	public function __construct( Context $c, $args = null )
	{
		parent::__construct($c);

		if (!$args['class'] || !class_exists($args['class'])) {
			if (is_string($args['focus']) && class_exists($args['focus']))
				$args['class'] = $args['focus'];
			else
				throw new ErrorException("a_Browse requires an argument 'class' which specifies the type of instance to list.");
		}
		extract($args); // by using this extract/compact approach we control which are allowed.
		$this->mode = compact('class', self::$modeOptions);
	}

	protected function render_me( Result $returned = null )
	{
		$c = $this->context;
		list($args, $post) = $this->context->request;

		// bring in any adjustments to the rendering mode
		if (count($args))
			foreach (self::$modeOptions as $arg)
				if (array_key_exists($arg, (array)$args))
					$this->mode[$arg] = $args[$arg];

		// SETUP
		$R = new InputRendering($c, $returned); // We use input rendering to make multi-select-and-act functionality possible with checkboxes
		$focus = $this->mode['class'];
		if (isset($focus::$hints))
			$hints = $focus::$hints[__CLASS__];
		$R->mode = $R::COLUMNAR;
		$fds = (array)$focus::getFieldDefs($R, (array)$hints['exclude'], (array)$hints['include']);

		// Multi-select Operations
		$multiOperator = $R->renderOperationSelector($focus, (array)$hints['triggers']['multi'], 'multi-');

		// Class Operations for the Banner
		$R->idprefix = 'banner-';
		foreach ((array)$hints['triggers']['banner'] as $opname)
			if ($op = $focus::getClassOperation($opname, $c, $multiOperator ? null : '_action')) // assignment intended
				$R->triggers[] = $op;
		$R->cancel = null; // turn off the Cancel button

		// LABELS ROW
		// Includes triggers to effect sorting
		$R->datahead[0] = $multiOperator ? '<input type="checkbox" onchange="$(\':checkbox\').attr(\'checked\',checked)"/>&nbsp;'. $multiOperator : '&nbsp;';
		foreach ($fds as $fn=>$fd) {
			$sortTrigger = htmlentities($this->mode['sortfield'] == $fn ?
				$c->target([], false, 'reverse', $this->mode['reverse'] ? 0 : 1) :
				$c->target(['reverse'=>0], false, 'sortfield', $fn) );
			$sortIndicator = $fn == $this->mode['sortfield'] ? ($this->mode['reverse'] ? '&nbsp;&uarr;' : '&nbsp;&darr;') : null;
			$label = htmlentities($hints['relabel'][$fn] ? $hints['relabel'][$fn] : $focus::getFieldLabel($fn));
			$R->datahead[] = ($fd['sort'] ? "<a href=\"$sortTrigger\">" : null) . $label . ($fd['sort'] ? "</a>$sortIndicator" : null);
		}
		$R->classes = [ 'actions', 'table'=>'browse-data', 'row'=>'browse', 'action'=>__CLASS__ ];

		// RECORD ROWS
		$i = 0;
		foreach ($focus::collection($this->mode) as $d) {
			$rowTriggers = null;
			$R->idprefix = $i++."-";
			$row = $d->renderFields($R, (array)$hints['exclude'], (array)$hints['include']);
			// Now put the actions column on the front:
			foreach ((array)$hints['triggers']['row'] as $opname)
				if ($op = $d->getOperation($opname, $c, $multiOperator ? null : '_action')) // assignment intended
					$rowTriggers .= $R->renderOperationAsIcon($op);
			array_unshift($row, ($multiOperator ? "<input class=\"recsel\" type=\"checkbox\" name=\"id[]\" value=\"$d->_id\" id=\"el.$d->_id\"/>&nbsp;" : null) ."$rowTriggers");
			$R->databody[] = $row;
		}
		$R->header = "<h1>". htmlentities($focus::$plural) ." <small>(". count($R->databody) .")</small></h1>";
		return $R;
	}
}
