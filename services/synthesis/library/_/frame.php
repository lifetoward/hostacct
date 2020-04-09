<?php
/**
* This class is a root action and suitable for rendering from a handler.
* Provides a standard template for a window frame.
* Override any portion to tweak the rendering.
*
* All original code.
* @package Synthesis/Authentication
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2014 Lifetoward LLC
* @license proprietary
*/
class Frame extends Action
{
	/**
	* This static method simplifies what would otherwise be required in a handler file.
	* Just call this via the appropriate top-level action class and it will establish and render itself.
	*/
	public static function start( $handler = null, $contextClass = 'AuthSession' )
	{
		if (!$handler)
			$handler = get_called_class();
		$root = $contextClass::start();

		if (!$root->action)
			if (! ($root->action = new $handler($root)) instanceof Frame)
				$root::abort($_SERVER['script'], "Unable to launch the $handler interface.");

		try {
			$R = $root->action->render($root);
		} catch (Exception $ex) {
			logError(array("Exception caught by Frame"=>$ex));
			$queuedOutput = ob_get_clean();
			$rendex = HTMLLogger::renderException($ex);
			print <<<html
<!DOCTYPE html>
<html><body>
<p>An exception was caught by Frame::start($handler, $contextClass) during processing of Action '$handler'. The operation did not complete.</p>
$rendex
<p>If you are unsure about the meaning of this error, please pass this information on to your service provider so they can help you understand or correct the problem.</p>
<button type="button" onclick="location.reload(true)">OK</button>
</body></html>
html;
			logExit("Exception caught by Frame");
			exit;
		}
		if ($R instanceof HTMLRendering) {
			$root->setHTTPHeaders(); // we can do this because we expect to be the root action
			$R->outputDocument();
			return;
		}
		$R->log();
		$root::abort($_SERVER['script'], "$R");
	}

	/**
	* Constructor requires the navigation structure to lay out the way actions are accessed.
	* We construct but do not render all top-level actions during frame construction.
	* @param Context $root Known-authenticated Root context
	* @param array $hint Navigation hints
	*/
	public function __construct( Context $root, $hint = null )
	{
		$this->context = $root;
		if ($hint instanceof Action)
			$this->subAction = $hint; // It's fairly common for Frame to be used directly by simple wizards or actions like login.

		// Many times, Frame is subclassed by a template and in these cases we don't do any special constructing.
	}

	/**
	* If this class gets the render call (and a subclass is not overriding) then we're just rendering a known focal action within a standard but overridable template.
	* In these cases we always just pass-thru the context, and there is no action-specific context.
	*/
	public function render( Context $c )
	{
		if (!$this->subAction instanceof Action)
			return new Notice(__CLASS__ .": No content defined.", 'complete');

		$R = $this->subAction->render($c);
		if ($R instanceof HTMLRendering) // wrap the content trivially
			$R->content = '<div id="focal" class="container"><h1>'. htmlentities($_ENV['ACCOUNT_NAME']) ."</h1>\n$R->content</div>";
		return $R;
	}
}
