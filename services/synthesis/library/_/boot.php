<?php
/**
* Contains a hodgepodge of the basic and widely or always used procedural functions which are so basic that even the core library depends on these functions being readily available at all times.
* It notably includes error handling, logging, and debugging configuration and functions.
*/

// Scripts written to run in the console should set Global variable $CONSOLE = true.
if ($CAPCELTEST) // We interpret this global as the deprecated way to enable CONSOLE mode.
	$CONSOLE = true;

$inboundHeaders = getallheaders();

######### Liberal INI Settings for error reporting in DEV or TEST mode ##############################

if ($_ENV['PHASE'] != 'prod' || $CONSOLE)  {
	ini_set('error_reporting', E_ALL&~E_NOTICE&~E_STRICT);
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
}

#########  PATHS, esp. Class Loading ##################################

// Consider which lib and app module directories contain class-filenamed programs and be sure they are included in the arrays below:
$paths = [ "$_ENV[SYNTH_HOME]/app/web", "$_ENV[SYNTH_HOME]/app", "$_ENV[SYNTH_HOME]/lib" ];
foreach (explode(' ', $_ENV['SYNTH_LIBMODULES']) as $libmod)
	$paths[] = "$_ENV[SYNTH_HOME]/lib/$libmod";
$paths[] = "$_ENV[SYNTH_HOME]/lib/_";
$paths[] = get_include_path(); // pick up the configured include path on the end
set_include_path(implode(PATH_SEPARATOR, $paths));
spl_autoload_register(); // set up the standard include-path and class-name based loader
spl_autoload_register(function(){}); // for an unknown reason, this prevents LogicExceptions when autoload fails for a missing class.

#########  Multi-byte UTF-8 character handling setup ##################################

mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_http_input('auto');
mb_language('uni');
mb_regex_encoding('UTF-8');

######### LOGGING FUNCTIONS #################################

require_once("loggers.php");
$requestId = ((int)($_SERVER['REQUEST_TIME_FLOAT']*1000))%100000000;

$loggers = array();
foreach ([ 'FileLogger','FireBugLogger','HTMLLogger','ConsoleLogger'/*,'EmailLogger'*/ ] as $logger) {
	try {
		$loggers[$logger] = new $logger;
		logDebug("$logger enabled.");
	} catch (Exception $ex) { // An exception merely means a logger cannot be enabled; reasons could be valid. Just inform and proceed.
		logDebug("$logger NOT enabled: ". $ex->getMessage());
	}
}

/*
* Any of these logging functions will include basic runtime location information along with the member consumption and time elapsed.
* The signatures are all the same:
* @param mixed $item The first argument will be cast to array, and then each element in it will be logged as appropriate to its type. Keys will label their values.
*	The idea here is that passing a simple string will log it as itself and unlabeled.
*	Passing something other than an array will similarly produce a single unlabeled item rendering.
*	If you pass an array, you need to make sure it is intended for logging, ie. with items labeled by keys.
* @param mixed $rc The call returns this value; this is helpful for simplifying log operations as you return.
* @return Passed value.
*/

// Status means to dump a stack trace along with any other items submitted. Always at level debug.
function logStatus( $item, $rc = null )
{
	return logToAll('TRACE', (array)$item, $rc);
}
// Debug means it's not interesting except for development reasons. These won't appear in the week-persistent daily logs (ErrorFile)
function logDebug( $item, $rc = null )
{
	return logToAll('LOG', (array)$item, $rc);
}
// Info means something normal but of note happened. This is like a loose auditing mode.
function logInfo( $item, $rc = null )
{
	return logToAll('INFO', (array)$item, $rc);
}
// Warn means something abnormal is going on, but it doesn't in itself imply there's a problem.
function logWarn( $item, $rc = null )
{
	return logToAll('WARN', (array)$item, $rc);
}
// Error means something did not work out as expected and the program will need to take evasive action. This will automatically include dumping a stack frame at error level.
function logError( $item, $rc = null )
{
	return logToAll('ERROR', (array)$item, $rc);
}
// Begin a logging group, ie. a logical unit of messages which could be theoretically collapsed on review.
function logBegin( $item, $rc = null )
{
	return logToAll('BEGIN', (array)$item, $rc);
}
// End a logging group as described above.
function logEnd( $item = '', $rc = null )
{
	return logToAll('END', (array)$item, $rc);
}
// The runtime is about to exit and this is your chance to dump something or whatever. If $items[0] is an Exception, you can assume that's the cause of death.
function logExit( $item = '', $rc = null )
{
	return logToAll('TERM', (array)$item, $rc);
}

// The following function is not meant to be called from the rest of the code. It's a consolidator for all the logging functions described above.
function logToAll( $level, array $items, $rc )
{
	list($top, $atCall, $caller) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
	$status = date('H:i:s') ." in ".
			($caller['function'] ?
				($caller['class'] ?	"$caller[class]$caller[type]" : '') ."$caller[function](...)" :
				"{global code}") .
			" @ ". str_replace("$_ENV[SYNTH_HOME]/", '', $atCall['file']) .":$atCall[line]; Mem=". floor(memory_get_usage()/1024) ."kB; Elapsed=".
				(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000 ."ms";
	foreach ($GLOBALS['loggers'] as $logger)
		$logger->log($level, $status, $items);
	return $rc;
}

######## MAILING FUNCTION : Here mainly because email is considered an important logging channel ##################

function sendEmail( $to, $subject, $message, $headers = array() )
{
	$monitor = "Synthesis Monitor <$_ENV[EMAIL_MONITOR]>";
	$headers = array_merge(
		[	 'From'=>"$_ENV[ACCOUNT_NAME] System <$_ENV[EMAIL_FROM]>"
			,'Content-type'=>"text/html; charset=utf-8"
			,'X-Mailer'=>"Business Operations Synthesis Software (BOSS) by Synthetic Web Apps, a Lifetoward LLC venture"
		], $headers);

	// This is the new way, designed to work in conjunction with the new EmailMessage element class.
	if ($_ENV['PHASE'] == 'prod') {
		if (!$to)
			$to = $monitor;
		else
			$headers['Bcc'] .= ", $monitor";

	} else {
		// For non-prod instances, we send mail to the monitor address only
		$to = $monitor;
		$headers['X-Would-Bcc'] = $headers['Bcc'];
		$headers['X-Would-Cc'] = $headers['Cc'];
		unset($headers['Bcc'], $headers['Cc']);
	}

	require_once('Mail.php');
	list($smtpscheme, $smtphost, $smtpport) = explode(':', $_ENV['EMAIL_SMTP_SERVER'], 3);
	$mailer = Mail::factory('smtp',
		array('host'=>($smtpscheme=='smtps'?"tls":$smtpscheme).":$smtphost", 'port'=>$smtpport, 'auth'=>'LOGIN', 'timeout'=>15,
			'username'=>"$_ENV[EMAIL_ACCT]", 'password'=>trim(file_get_contents("$_ENV[SYNTH_HOME]/.emailpass"))));
	if (true !== ($result = $mailer->send($to, $headers, $message))) {
		logError(array("Error encountered sending mail"=>$result));
		return false;
	}
	return true;

	// We're keeping the old code to dump the minimally assembled message into the log.
	foreach (array_merge($defaultHeaders, $headers) as $tag => $value)
		$ready[] = "$tag: $value";

	logInfo("\n\n\$\$Message submitted ". date('Y-m-d H:i:s') ."\nTo: $to\nSubject: $subject\n$ready\r\n\r\n$message\n\$\$end\n");
	return true;
}

############## SERIALIZATION BUG WORKAROUND #####################################################
# Because of https://bugs.php.net/bug.php?id=65591 we explicitly handle core object references ourselves rather than let the buggy serializer mess them up.

function preserializeObjectRefs( &$v, $x )
{
	if (!is_object($v))
		return $v;
	global $_serializedObjects;
	$hash = spl_object_hash($v);
	if ($ref = $_serializedObjects[$hash])
		$v = "*OR.$ref";
	else
		$_serializedObjects[$hash] = ++$_serializedObjects['counter'];
	return $v;
}

function renewObjects( &$v, $x )
{
	if (is_string($v) && 0===strpos($v, '*OR.'))
		$v = $GLOBALS['_renewedObjects'][mb_substr($v,4)];
	return $v;
}

/**
* This function aids unserializing classes within objects.
* You pass it (our proprietary) serial representation of your class's property list, and it:
*	1. Checks that the range's name matches the $check value and throws an Exception if not.
*	2. Chops the focal serialized chunk off the front of $rep, changing the value of $rep in place by reference.
*  3. Extracts and returns the serialized value of the property.
*/
function pullSerialString( &$rep, $check = null )
{
	list($t, $z, $x) = explode(':', $rep, 3);
	if ($check && $t !== $check)
		throw new Exception("Unserialization failure. Expected range label '$check' but found '$t'");
	$rep = mb_substr($x, $z);
	return mb_substr($x, 0, $z);
}

######## EXCEPTION AND ERROR HANDLERS #######################

// Following function is the final arbitor of all Exceptions, and thanks to our error handler, all errors too.
set_exception_handler(
	function( $ex )
	{
		restore_error_handler();
		logError($ex);
		exit(logExit($ex));
	}
);

set_error_handler(
	function( $errno, $errstr=null, $errfile=null, $errline=null )
	{ // http://us2.php.net/manual/en/function.set-error-handler.php
		throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
	}
, E_ALL&~E_NOTICE&~E_STRICT);

class NoMethodX extends Exception
{
	function __construct( $methodName )
	{
		$this->message = "Method Not Found: $methodName";
	}
}

logInfo("* * * NEW INVOCATION: Boot complete for $_ENV[PHASE] phase");
