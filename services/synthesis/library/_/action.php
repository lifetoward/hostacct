<?php
/**
* Application developers should extend Action to create Interactive and Functional procedures as part of the application.
*
* All original code.
* @package Synthesis
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright © 2007-2015 Lifetoward LLC
* @license proprietary
*/
abstract class Action
	implements Serializable
{
	protected $subAction = null, $context = null;

	public function serialize()
	{
		global $_serializedObjects;
		if (!isset($_serializedObjects[$hash = spl_object_hash($this)]))
			$_serializedObjects[$hash] = ++$_serializedObjects['counter'];
		foreach (array('subAction','context') as $prop)
			$props[$prop] = $this->$prop;
		array_walk_recursive($props, 'preserializeObjectRefs');
		$z = mb_strlen($p = "{$_serializedObjects[$hash]}:". serialize($props)); // This is where we stash the Object Ref ID
		return __CLASS__.":$z:$p"; // parsable by pullSerialString($rep)
	}

	public function unserialize($rep)
	{
		list($OR, $propRep) = explode(':',pullSerialString($rep, __CLASS__), 2);
		$GLOBALS['_renewedObjects'][$OR] = $this;
		$props = unserialize($propRep);
		array_walk_recursive($props, 'renewObjects'); // This conveniently operates on our own properties first and all their array descendents too
		foreach (array('subAction','context') as $prop)
			$this->$prop = $props[$prop];
	}

	/**
	* Every new action needs its own subcontext. If you write your own action constructor, be sure to get this done.
	*/
	public function __construct( Context $super )
	{
		$this->context = new SubContext($super);
	}

	/**
	* If you want your action run or render while also displaying its subaction, implement this method. It will be called AFTER the subAction runs
	* Use your own context as you wish and run or render whatever you want, but keep in mind that your result object has already been populated by your subAction.
	* Your job is to make modifications to the current result, and you must not ignore or clobber its contents unless that's your real intent.
	* @param Result $R The Result returned by the SubAction your enveloping.
	* @return Result You must return the final Result you want the world to have.
	*/
	protected function envelope( Result $R )
	{
		return $R;
	}

	/**
	* A subclass of Action which implements this method can receive posted form data from the browser's UI even as a new subaction is triggered.
	* When the action is triggered, the new subAction will be constructed and set to execute, but then before it is rendered this method will be called.
	* Implementing this method may be a nice idea even if you don't need this post-n-call functionality. It assembles form data handling into one neat spot.
	* @param string[] $posted Only the posted form data is considered relevant because the args will contain subaction information.
	*	Besides, if args are needed for some reason, the context is already known and can be queried to obtain the request data ($this->context->request[...])
	* @return void Any information you need from this method's execution needs to be placed in instance variables if rendering depends on it; we may not be back here for a while.
	*/
	protected function acceptFormData( array $posted )
	{
	}

	/**
	* This method is used to produce a result appropriate to an HTML based user-driven scenario.
	* If you want to produce a different type of result (like AJAX or REST), use a different method.
	* Our implementation of render() in this base class provides for an automagic "nesting" facility among actions.
	* Under this scheme, a running action can trigger a subAction which then gets rendered instead of its parent until the subAction exits, etc.
	* We provide this facility by calling the related render_me() and optional envelope() methods.
	* Of course you can override this method if you don't want that kind of behavior.
	* Root actions, for example, typically override this to allow for more than one Action stack or to implement other forms of interaction.
	* For a typical Action which might be useful in many contexts, you would not worry about this stuff, and you would implement render_me() instead of render().
	* @param Context $super The context of the action invoker. We use this to inform our own context where to get exported and general system information which is request-specific.
	* @return Result Like any public method in the Action class, you must return a Result object. The usual goal is to return an HTMLRendering object because only that can
	*	result in a visible HTML panel in a browser as this method is intended to facilitate. However, whenever an Action is done interacting, it returns another Result, typically a Notice, instead.
	*	So as a general rule, you should either return an HTMLRendering (if you action continues to require the attention of the user), or a Notice (if you're done executing).
	*/
	public function render( Context $super = null )
	{
		if (($c = $this->context) instanceof SubContext)
			$c->nest = $super;
		$args = $c->request['args'];
		$myClass = get_class($this);

		// We have a convention to check for subAction triggers identified by the Action class name in the _action parameter.
		if (!$this->subAction && ($actclass = $args['_action'])) { // assignment intended

			if ($actclass == 'CANCEL')
				return logInfo("Cancel triggered in Action $myClass", new Notice("Action '$myClass' was canceled.", 'abort'));

			if (is_subclass_of($actclass, 'Action')) {
				logInfo("New subaction '$actclass' triggered");
				$this->subAction = new $actclass($c, $args);
				if ($args['_accept'])
					$this->acceptFormData($c->request['post']);
			} else
				logWarn("_action arg value '$actclass' is not an Action. Ignored.");
		}

		// If there's already a subAction active under this action, we pass-thru to it spanning many requests if need be until it returns something other than an HTMLRendering result.

		do { // This loop allows render_me to produce subactions in its own way... if it created a subaction, we need to go back around and render it.

			if ($this->subAction) {
				$subClass = get_class($this->subAction);
				try {
					$R = $this->subAction->render($c);
					if ($R instanceof HTMLRendering)
						return $this->envelope($R); // As long as the subAction still has things to do, we envelope it and return its Result as our own.

					// If we continue below here, it means the subAction is terminating one way or another and we will capture that Result and proceed with whatever's next.
					logBegin("*** SubAction '$subClass' exits:");

				} catch (Exception $ex) {
					$R = new Notice($ex, 'error');
					logBegin("*** Subaction '$subClass' threw an exception caught by Action::render()");

				}
				$R->log(); // An action result notice should know how to log itself.
				logEnd('***');
				$this->subAction = null; // if the subaction is not proceeding, we're done with it because we've already obtained its results as $R

			} else
				$R = null; // This just sets up passing the non-existant returning subAction's result as null (just below)

			logInfo("*** Rendering Action '$myClass' ***");
			$R = static::render_me($R); // i.e. turn any subResult now exited into our own Result now active

		} while ($this->subAction);

		return $R;
	}

	/**
	* Subclasses should override this function to keep things as simple as possible. Just render your stuff and don't worry about when subactions are triggered.
	* @param ActionResult $returned If you are getting control immediately after a subaction returned, you will receive its result object as your only parameter.
	*	The standard and appropriate thing to do with it is if ($returned->status != ActionResult::CANCEL) then render $returned->content as a notice in an appropriate place on your panel.
	* @return void ; render_me only needs to prep its result object which will have already been set when it is called.
	*/
	protected function render_me( Result $returned = null )
	{
		return new Notice("<p>This interaction is undefined. The system developer should implement render_me() in the action.</p>", $returned);
	}

	/**
	* If an Operation (as rendered by get[Class]Operation(...)) is triggered with a null $actionArg, the action can still be instantiated and invoked from within the render_me() method like so:
	* if ($args['operation']) {
	*	// process any posted data or do other pre-invocation preparation
	*	return setSubOperation($args);
	* }
	* @param mixed[] $args The args received through the request, ie. $c->request['args']
	* @return null So you can just return the value of this call to the caller of render_me(), ie. Action::render() which will then invoke the instantiated Action.
	*/
	protected function setSubOperation( array $args )
	{
		if (!($op = $args['class']::getClassOperation($args))) // can use getClassOperation here because it has special handling for args arrays which may be instance specific.
			throw new Exception("Args not appropriate for an operation.");
		$actionClass = $op['action'];
		unset($args['operation']);
		$this->subAction = new $actionClass($this->context, $args);
		return null;
	}

	/**
	* As a shortcut for Actions which need to process standard arguments to obtain a focal object, you can call this method.
	* This is usually useful in the constructor for a specific Action implementation.
	* It converts arguments received into an Instance using standard conventions for arg passing among actions.
	* Specifically that means arg 'focus' can be an actual Instance object or can be an Element handle like "classname=id#";
	*	Lacking 'focus' we check 'class' and 'id', combining them to create a handle we use to obtain an Element.
	* @param array $args The args values received.
	* @param string $class (optional, assumed "Instance") If you provide a class name, we'll validate that the object received is of this class or throw an ErrorException.
	* @param string $argname (optional) If you provide an argname, it will be accepted as the source of the desired focal object before what we find in "focus"
	*/
	protected static function getFocusFromArgs( array $args, $reqClass = 'Instance', $argname = null )
	{
		$actClass = get_called_class();
		if ($argname && $args[$argname])
			$focus = $args[$argname];
		else if ($args['focus'])
			$focus = $args['focus'];
		else if (is_string($args['class']) && is_numeric($args['id']))
			$focus = "$args[class]=$args[id]";
		else
			throw new ErrorException("$actClass requires an Instance specification in the args: ". ($argname ? "$argname, " : null) ."focus, or class and id");

		if (is_string($focus)) {
			list($class, $id) = explode('=', $focus, 2);
			if (!$class || ($class != $reqClass && !is_subclass_of($class, $reqClass)))
				throw new ErrorException("$actClass requires an Instance specification of class $reqClass.");
			$focus = $class::get($id);
		}

		if (! $focus instanceof $reqClass)
			throw new ErrorException("$actClass requires an Instance of class $reqClass, but such an object could not be obtained.");
		return $focus;
	}
}
