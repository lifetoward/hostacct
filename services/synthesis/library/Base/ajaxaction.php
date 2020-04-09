<?php
/**
* AJAXAction is a root action which provides access to Instance data directly.
* Usually these are session-authenticated, but that's up to the portal script to decide.
* It loads a requested data object by its class and key(s), and then
* Returns the output of a requested method (via 'method' arg) or "asJSON" to the caller.
* Not just any method can be invoked this way. It must be listed as an array key in the appropriate hint, ie. $focus::$hints['AJAX'][$method]
* The method's static role requirement can be provided as that array key's value. (If empty, it means, just be authenticated.)
* We return all the headers and such presuming JSON data as the sole body.
*
* All original code.
* @author Created 2015-02-11 by Guy Johnson (Austin Biz Wiz)
* @copyright Copyright © 2015 Lifetoward LLC; All rights reserved.
* @license proprietary
*/

class HttpException extends Exception
{
	protected $responseCode;

	function __construct( $responseCode, $message )
	{
		if ($responseCode < 100 || $responseCode > 999)
			$responseCode = 500;
		$this->responseCode = $responseCode;
		parent::__construct($message);
	}

	function getResponseCode()
	{
		return $this->responseCode;
	}
}

class AJAXAction extends Action
{
	public static function start( $contextClass = 'AuthSession' )
	{
		$handler = get_called_class();
		$root = $contextClass::start();

		try {
			$args = $root->request['args'];
			if (!($class = $args['class']) || !class_exists($class))
				throw new HttpException(400, "instance class is required and must exist");
			if (!($method = $args['method'])) // assignment intended
				$method = 'asJSON';
			if (!isset($class::$hints[__CLASS__]) || !isset($class::$hints[__CLASS__][$method]) || !method_exists($class, $method))
				throw new HttpException(405, "instance rendering method ($method) not available");
			if (!$root->isAuthorized($class::$hints[__CLASS__][$method]))
				throw new HttpException(401, "not authorized");
			foreach ((array)$class::$keys as $key)
				if (!($keys[$key] = $args[$key])) // assignment intended
					throw new HttpException(400, "missing key '$key' for class '$class'");
			if (!($focus = $class::get($keys)))
				throw new HttpException(404, "instance not found");
			$data = $focus->$method($root);
			// We must execute the method before we close off headers and print data.
			header("MIME-Version: 1.0");
			header("Content-type: text/json; charset=utf8");
			print $data;
			exit;

		} catch (Exception $ex) {
			if ($ex instanceof HttpException) {
				$response = $ex->getResponseCode();
			} else
				$response = 500;
			logError(array("Exception caught by $handler"=>$ex));
			$queuedOutput = ob_get_clean();
			$rendex = HTMLLogger::renderException($ex);
			print <<<html
<!DOCTYPE html>
<html><body>
<p>An exception was caught by $handler::start($contextClass) during processing of Action '$handler'. The operation did not complete.</p>
$rendex
</body></html>
html;
			logExit("Exception caught by $handler");
			exit;
		}
	}

const OLD_CODE = <<<'end'
This older approach to an AJAX action took a more complete and purpose-specific approach which I don't just want to delete yet.
The implementation above is pretty general purpose, but also not informative and perhaps even quite unmanageable under error conditions.

		// The initialization case happens below. We instantiate the requested action and return the INITIAL status.
		if (!class_exists($actionClass = $args['_action']) || !$actionClass instanceof Action)
			exit print json_encode(array('status'=>static::FAIL, 'content'=>"Could not locate the requested action class '$actionClass'."));
		$token = uniqid();
		if (($c->$handler[$token] = new $actionClass($args)) instanceof Action) // Here's the normal case
			exit print json_encode(array_merge($c->$handler[$token]->getResult(), array('token'=>$token)));
		unset($c->$handler[$token]);
		exit print json_encode(array('status'=>static::FAIL, 'content'=>"Unable to construct the requested action '$actionClass'.", 'request'=>$c->request));
	}

	/**
	* Call this to obtain a jscript trigger which will invoke a new AjaxActionDialog.
	* @parm
	* @return string Javascript snippet to execute when someone triggers an AjaxActionDialog
	*/
	public static function triggerDialog()
	{
	}

	/**
	* If you're rendering some content that can trigger an Ajax action dialog, then call this.
	* It will load in your client-side javascript library with your rendering normal action. That library is what's gonna take care
	* of rendering and managing your Ajax action in the browser document.
	* @return void
	*/
	public static function addAjaxActionDialogScript( )
	{
		Frame::addScript(<<<js
var actionStatus = { INITIAL:0, SUCCEED:1, PROCEED:2, CANCEL:3, FAIL:4, RESET:5, COMPLETE:6, AUTH:7 };
function newAjaxActionDialog() {
	// Insert a dialog wrapper into the end of the document body; it should include a "please wait... loading" kind of message at this point.
	// Craft and send an AJAX request to construct the action passing the trigger arguments; receive the result synchronously.
	// If the result is a failure, report that in the dialog and allow for closing the dialog.
	// Otherwise send off the first render request, passing the args again(?)
	// As long as the STATUS is PROCEED, take the CONTENT property and replace it into the dialog.
	// Once the dialog is rendered, we're done. The triggers within the rendered action will call into the "triggerAjaxAction()"
}
function proceedAjaxAction() {
	// When the ajax action is rendered (as a dialog), its own triggers must be handled through here.
	// We ship the request to the ajax request handler (rather than the portal handler) and act on the response.
	// The response will either be PROCEED, in which case we're just re-rendering and returning,
	// or if it's SUCCEED we take returned information (we'll need id and rendering of the new action pref as an option... something a_Edit will need to learn to do) and plug it into the control that triggered all this.
	// Any other response on the action results in rendering a message in the dialog before the dialog cancels out by user click.
}
js
			, "AjaxActionDialog client script");
end;
}
