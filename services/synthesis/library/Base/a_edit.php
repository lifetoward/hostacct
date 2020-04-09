<?php
/**
* The create/update UI workhorse.
*
* All original code.
* @package Synthesis
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2014 Lifetoward LLC
* @license proprietary
*/
class a_Edit extends Action
{
	protected $instance, $include = null, $exclude = null, $title = null; // the item under edit
	private static $savedProperties = ['instance','include','exclude','title'];
	use SerializationHelper;

	/**
	* Our constructor is large and complex because we facilitate the identification of a focal instance in a variety of ways.
	* @param Context $c The embedding context in which the Action will run.
	* @param mixed $x This is where it gets interesting. There are several things you could pass here:
	*	Instance object - Straightforward: We'll edit this instance.
	*	Instance class name as string - We'll create a new Instance of the passed class.
	*	Array of args - Conventional args are those which are typically provided through triggers or as parameters in URLs. We care about these args:
	*		class - The name of the class of Instance to edit.
	*		for Element classes:
	*			id - If you pass this, it uniquely identifies an Element and that makes your Instance. Otherwise we create a new instance of the class.
	*		for Relation classes we don't yet have args-based identification. (Sorry!)
	*		initial - You can provide an initialization array for the instance fields.
	*		include/exclude - You can specify field inclusion or exclusion hints.
	* 		title - If you pass a title, we'll use that for the header rather than the normal title derived from the class and/or identifying content of the Instance.
	*	... consider:  Passing include, exclude, or title ought to be possible when passing an object too!
	*/
	public function __construct( Context $c, $x = null )
	{
		parent::__construct($c);

		// We accept an overloaded main parameter...
		if ($x instanceof Instance)
			$this->instance = $x;

		else if (is_string($x) && is_subclass_of($x, 'Instance'))
			$this->instance = $x::create();

		else if ($x['instance'] instanceof Instance) // The instance is inside the passed array of args
			$this->instance = $x['instance'];

		else if (is_array($x)) {  // The instance is described in an array of instance descriptive arguments (class, id) - We'll need to obtain an instance object on our own here.

			// Get a valid class name
			if (!is_subclass_of($x['class'], 'Instance') || !class_exists($x['class']))
				throw new ErrorException("a_Edit::__construct(...): Can't find the focal class '$class'; need args[class] (or args[focus])");

			// Create a new instance or get an existing one depending on provision of 'id' arg
			if (is_subclass_of($x['class'], 'Element'))
				$this->instance = ( $x['id'] ? $x['class']::get($x['id']) : $x['class']::create() );

			else if (is_subclass_of($x['class'], 'Relation')) {
				throw new Exception("a_Edit is not yet capable of identifying Relation classes via conventional arguments.");
			}

			// Convert reference field initializers to objects
			if (!$this->instance->_stored && $x['initial'])
				$this->instance->acceptValues($x['initial'], $this->context);

			foreach (['include','exclude'] as $attr)
				if (is_array($x[$attr]))
					$this->$attr = $x[$attr];

			if (is_string($x['title']))
				$this->title = $x['title'];

		} else
			throw new ErrorException("No parameters provided to construct ". __CLASS__);

		if (!$this->instance instanceof Instance)
			throw new ErrorException("Failed to identify an instance");
	}

	protected function render_me( Result $returning = null )
	{
		$c = $this->context;
		extract($c->request, EXTR_PREFIX_INVALID, 'r');
		$class = get_class($this->instance);
		$hints = isset($class::$hints) ? (array)$class::$hints['a_Edit'] : array();
		$R = new InputRendering($c, $returning, $this->instance);

		if ($returning) {
			if ($returning instanceof Notice && $returning->reason == 'success' && $this->instance->reffield_adding) {
				$this->instance->acceptValues([$this->instance->reffield_adding=>$returning->focus], $c);
				unset($this->instance->reffield_adding);
			}

		} else if ($args['post']) {
			// Here's where we accept input from the form and update the focal instance. Note that we may do this without validation if we're triggering a subAction.
			// When accepting input we normally need to worry about bad values and duplicate data.
			try {
				if (!$this->instance->acceptValues($post, $c) && !$this->instance->_latent)
					return new Notice("No changes were made to '$this->instance'.", 'complete', $returning, $this->instance);
				$verbed = $this->instance->_stored ? 'Updated' : 'Added';
				$this->instance->store();
				return new Notice("$verbed ". htmlentities($class::$descriptive) ." for '$this->instance'.", 'success', $returning, $this->instance);
			} catch (BadFieldValuesX $ex) {
				$R->addResult(new Notice(["Some values were not acceptable as submitted."=>$ex->problems], 'failure'));
				// fall thru to re-rendering the input form
			} catch (dbDuplicateDataX $ex) {
				foreach ($this->instance->getFieldDefs($c, (array)$hints['exclude'], (array)$hints['include']) as $fd)
					if (!$fd['derived'] && $fd[$ex->failedKey == '_ident' ? 'identifying' : 'unique'] && ($ex->failedKey != '_ident' ? $fd['unique'] == $ex->failedKey : true))
						$fieldproblems[$fd['label']] = "({$this->instance->{$fd['name']}}) is part of the ".
							($fd['identifying'] ? 'primary identifying' : "'$fd[unique]'") ." combo which must be unique.";
				$R->addResult(new Notice([
					 "The submitted information would create duplicate data in the system."=>"You must vary at least one of the values involved in order to submit this new information."
					,'Values involved'=>$fieldproblems], 'failure'));
			}

		}

		// Here we'll handle "Create new referenced Element" requests, the only subAction triggers we're concerned with
		else if ($args['operation'] == 'create' && $args['reffield']) { // this is the telltale signature of a "Create new referenced Element" request as generated in _/FieldOps (Instance)
			try { $this->instance->acceptValues($post, $this->context); } // accept whatever data is set from the UI so far so it's there when we come back
			catch (BadFieldValuesX $ex) { } // we don't care about bad values right now... we take what we can get and proceed to allow further editing later.
			$this->instance->reffield_adding = $args['reffield']; // We save this information to know what got added when the subaction returns
			return $this->setSubOperation($args); // returns null, allowing caller (Action::render()) to perform subaction rendering
		}

		// Triggers, notices, and headings
		if (!$c->nocancel)
			$R->cancel = ['label'=>"Abandon changes"];
		$classLabel = htmlentities($this->instance->_singular);
		if ($this->instance->_stored) {
			$instanceLabel = $this->instance->_rendered;
		} else
			$instanceLabel = "New $classLabel";
		$subtitle = "<small>&nbsp;: ". ($this->title ? $this->title : htmlentities($class::$descriptive)) ."</small>";
		$R->header = $c->noheading ? null : "<h1>$instanceLabel$subtitle</h1><hr/>";

		// FIELD ROWS
		$R->mode = $R::INPUT;
		$R->tabindex = 5;
		$this->instance->_rendering = $R;
		// Someone might want to set include/exclude arrays on our rendering (can't remember)
		$exclude = array_merge((array)$hints['exclude'], (array)$this->exclude);
		$include = array_merge((array)$hints['include'], (array)$this->include);

		// Primary rendering loop
		foreach ($this->instance->getFieldDefs($R, $exclude, $include) as $fn=>$fd)
			$fieldRows .= $R->renderFieldWithRowBreaks($this->instance, $fd);

		$R->addStyles("div#actions { text-align:center }", 'a_Edit');

		$R->content = <<<HTML
<fieldset>
<div class="row">$fieldRows</div>
</fieldset>
<hr/>
<div id="actions">
	<button type="submit" class="btn btn-success btn-lg" tabindex="10"><span class="glyphicon glyphicon-ok-sign"></span> Commit </button>
</div>
HTML;
		return $R;
	}
}
