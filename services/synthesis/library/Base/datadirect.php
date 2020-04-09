<?php
/**
* DataDirect is a root action (handler) which provides access to Instances directly.
* It loads a requested data object by its class and key(s), and then renders it back using the requested formatting method.
* Not just any method can be invoked this way. It must be listed as an array key in $focus::$directMethods.
* The method's static role requirement can be provided as that array key's value. (If empty, it means, just be authenticated.)
* Available formatting methods depend on the Instance subclass, but some are universal (like JSON).
* Unless you pass a context class to the start() method, we're going to assume you want us to use an AuthSession.
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

class DataDirect extends Action
{
	public static function start( $contextClass = 'AuthSession' )
	{
		$handler = get_called_class();
		$root = $contextClass::start();

		try {

		// TO-DO: This approach cannot handle MULTIPLE instances via multiplexed keys (like ref or id).
		// We'd need that as well as a different/new directVCard to dump out the whole VCard troop.

			$args = $root->request['args'];
			if (!($class = $args['class']))
				throw new HttpException(400, "instance class is required");
			if (!($method = $args['method'])) // assignment intended
				throw new HttpException(400, "instance rendering method is required");
			$hints = isset($class::$hints) && isset($class::$hints[__CLASS__]) ? $class::$hints[__CLASS__] : [] ;
			if (!$hints[$method] || !method_exists($class, $method))
				throw new HttpException(405, "instance rendering method not available");
			if (!$root->isAuthorized($hints[$method]))
				throw new HttpException(401, "not authorized");
			foreach ((array)$class::$keys as $key)
				if (!($keys[$key] = $args[$key])) // assignment intended
					throw new HttpException(400, "missing key '$key' for class '$class'");
			if (!($focus = $class::get($keys)))
				throw new HttpException(404, "instance not found");
			$focus->$method($c); // does not return
			// never reached
			exit -1;

		} catch (Exception $ex) {
			if ($ex instanceof HttpException) {
				$response = $ex->getResponseCode();
			} else
				$response = 500;
			logError(array("Exception caught by $handler"=>$ex));
			$queuedOutput = ob_get_clean();
			$rendex = HTMLLogger::renderException($ex);
			logExit("Exception caught by $handler");
			print <<<html
<!DOCTYPE html>
<html><body>
<p>An exception was caught by $handler::start($contextClass) during processing of Action '$handler'. The operation did not complete.</p>
$rendex
</body></html>
html;
			exit;
		}
	}
}
