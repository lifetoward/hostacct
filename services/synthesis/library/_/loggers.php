<?php
/*
* A Logger is capable of dispatching logging information to a particular destination.
* The facilities we have now include FILE (always on), CONSOLE (on for CLI testing), FIREBUG (works in Firefox and maybe Chrome)
* We've experimented with ChromePHP and PHPConsole in the past but they are out of date. We've got skeletons for them below.
*/
interface Logger
{
	const TRACE='TRACE' // like LOG but include a stack trace
		,LOG='LOG' // for debugging purposes only. not interesting in production unless there's an error
		,INFO='INFO' // useful runtime marker points for major context changes, etc.
		,WARN='WARN' // a problem may be developing because something out of the ordinary happened
		,ERROR='ERROR' // a problem has occurred, and something that we don't want to happen did happen
		,BEGIN='BEGIN' // open a new nestable logging section (push)
		,END='END' // close the current logging section (pop)
		,TERM='TERM' // The script is dying, probably because of an exception. Do your last rights.
		;

	/* log
	* Logs a set of items of various types under a given log level and runtime context.
	* @param string $level One of the string constants above, indicating how severe and where to log the information.
	*		Note that some of these levels imply generated logged content or a shift in logging behavior.
	* @param string $context Describes where in the code and runtime we were as the log message was generated.
	* @param mixed[] $items Any number of items to be logged. Non-string items are assumed to be described by the string following them in the list.
	* @return void
	*/
	function log( $level, $context, array $items );
}

class FileLogger implements Logger
{
	protected $groups, $requestId, $debugFile, $errorFile;

	public function __construct( )
	{
		$this->debugFile = fopen("$_ENV[LogDebugFile]", 'a');
		$this->errorFile = fopen("$_ENV[LogErrorFile]", 'a');
		$this->groups = array(); // The depth of the stack provides the number of indents in a text log. We may also track the function and stack depth so we can autopop.
		$this->requestId = $GLOBALS['requestId']; // allows grepping a log file for a specific request and getting the whole set of messages.
	}

	public static function backtrace( )
	{
		ob_start();
		debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		return strstr(ob_get_clean(), "\n#3 ");
	}

	public static function complex( $item )
	{
		ob_start();
		var_dump($item);
		return mb_substr(preg_replace("|\n *}\n|", " }\n", preg_replace("|=>\n *|", "=> ", ob_get_clean())), 0, -1);
	}

	public static function logAsString( array &$groups, $level, $status, array $items, $reqprefix = null )
	{
		$simple = !count($items) || (count($items) == 1 && is_string($items[0]));
		if ($level == 'END')
			$message = "} $level (closes \"". array_pop($groups) ."\") { $status }";
		switch ($level) {
			case 'TRACE':
				$level = 'LOG';
				// fall thru
			case 'ERROR':
				$items["Stack trace"] = self::backtrace();
				break;
			case 'BEGIN':
				$message = "$level: $items[0] ($status) {";
				array_push($groups, $items[0]);
				break;
		}
		if (!$message) {
			if (!count($items) || (count($items) == 1 && is_string($items[0])))
				$message = "$level: $items[0] { $status }";
			else {
				$message = "$level ($status) : ";
				foreach ($items as $label=>$item) {
					$prefix = is_numeric($label) ? null : "$label: ";
					$item = is_scalar($item) ? "$item" : self::complex($item);
					$message .= "\n. $prefix". str_replace("\n", "\n. ", rtrim($item));
				}
			}
		}
		return $reqprefix . str_replace("\n", "\n$reqprefix", trim($message)) ."\n";
	}

	// Every time we log
	public function log( $level, $status, array $items )
	{
		$reqprefix = sprintf("SyRq=%08d ", $this->requestId) . str_repeat('| ', count($this->groups));
		$msg = $this::logAsString($this->groups, $level, $status, $items, $reqprefix);
		fwrite($this->debugFile, $msg);
		if ($level != 'LOG')
			fwrite($this->errorFile, $msg);
	}
}

class ConsoleLogger implements Logger
{
	protected $stderr, $groups = array();

	public function __construct( )
	{
		if (!$GLOBALS['CONSOLE'])
			throw new Exception("Console logging is not enabled.");
		$this->stderr = fopen("php://stderr", 'a');
	}

	public function log( $level, $context, array $items, $rc = null )
	{
		fwrite($this->stderr, FileLogger::logAsString($this->groups, $level, $context, $items));
	}
}

class FireBugLogger implements Logger
{
	protected $fb;

	public function __construct( )
	{
		if ($CONSOLE || $_ENV['PHASE'] == 'prod')
			throw new Exception("Browser logging is disabled because we are in Console mode or Production phase.");
		require_once("$_ENV[SYNTH_HOME]/lib/contrib/FirePHP/lib/FirePHPCore/FirePHP.class.php");
		$this->fb = new FirePHP;
	}

	public function log( $level, $context, array $items )
	{
		switch ($level) {
			case 'BEGIN':
				$this->fb->group($items[0], ['Collapsed'=>true]);
				break;
			case 'END':
				$this->fb->groupEnd();
				break;
			case 'ERROR':
			default:
				if (($multi = count($items) > 1 || $level == 'ERROR'))
					$this->fb->group(($level == 'ERROR' ? 'ERROR: ' : '') .$context, array('Collapsed'=>$level != 'ERROR'));
				foreach ($items as $label=>$item) {
					if (is_scalar($item) && !$multi)
						$this->fb->fb("$item { $context }", is_string($label) ? $label : null, $level);
					else
						$this->fb->fb($item, is_string($label) ? $label : null, $level);
				}
				if ($level == 'ERROR')
					$this->fb->fb("The exception was caught here:", $context, FirePHP::TRACE);
				if ($multi)
					$this->fb->groupEnd();
		}
	}
}

/*
* This logger is only to be used for development and is capable of dumping and entire requests' log entries in HTML.
*/
class HTMLLogger implements Logger
{
	public function __construct()
	{
		throw new Exception(__CLASS__ ." not yet implemented.");
	}

	public static function renderException( Exception $ex )
	{
		return '<div style="border:2px solid red;font-size:9pt;background-color:#FED;width:100%;margin:auto;padding:1em;white-space:pre-wrap">'.
			str_replace("$_ENV[SYNTH_HOME]/", "", htmlentities("$ex")) ."</div>\n<p>The file logging request ID is $GLOBALS[requestId]</p>\n";
	}

	public function log( $level, $context, array $items )
	{
		// This code is merely drawn from the old exception handler... it needs a good reworking before it could run.
		if ($level == 'TERM') {
			$rendex = htmlentities($ex);
			if ($root instanceof Database && $root->roles['developer']) {
				if (is_array($trace_log))
					foreach ($trace_log as $msg)
						$traces .= "\n<p>". str_replace(" ", "&nbsp;", str_replace("\n", "<br/>\n", htmlentities($msg))) ."</p>";
				$datetime = htmlentities(date('M-d@H:i:s'));
				$content = htmlentities(ob_get_clean());
				print <<<html
<!DOCTYPE html>
<html><body>
<h1>Synthesis Exception Report, $datetime</h1><p><b>Request: $_SERVER[REQUEST_URI]</b></p>
<div><h2>Exception</h2><pre style="white-space:wrap">$rendex</pre></div>
<div><h2>Trace Log</h2>$traces</div>
<div><h2>Content output at exit</h2></div><pre style="white-space:wrap">$content</pre>
</body></html>
html;

			} else {
				print <<<html
<!DOCTYPE html>
<html><body>
<p>An error was encountered during processing of $_SERVER[REQUEST_URI]. The operation did not complete.</p>
<div style="border:2px solid darkgreen;background-color:#DFD;width:90%;margin:auto;padding:1em"><pre style="white-space:wrap">$rendex</pre></div>
<p>If you are unsure about the meaning of this error, please pass this information on to your service provider so they can help you understand or correct the problem</p>
<button type="button" onclick="location.reload(true)">OK</button>
</body></html>
html;
				// send the grand rendering to the developer as an email but protect sensitive data first (password zip?)
			}
		}
		return;
	}
}

/*
* The EmailLogger sends information about important conditions encountered in production phase runtimes.
* It queues up a possible email message, but only sends it if there is a runtime trap or an ERROR condition is logged.
*/
class EmailLogger implements Logger
{
	public function __construct()
	{
		throw new Exception(__CLASS__ ." not yet implemented.");
	}

	public function log( $level, $context, array $items )
	{
	}
}

abstract class ChromeLogger implements Logger
{
	public function __construct()
	{
		throw new Exception("ChromePHP logging is not implemented.");

		if (file_exists($_ENV['chromePhp']) && !$CONSOLE) { // && Chrome is the browser
			/*
			@include_once($_ENV['chromePhp']);
			ChromePhp::useFile('/tmp/chromelogs', "$urlbase/chromelogs");
			parent::$loggers[] = __CLASS__;
			*/
		}
	}

	public function log( $level, $context, array $items )
	{
	}
}

abstract class PHPConsoleLogger implements Logger
{
	public function __construct()
	{
		throw new Exception("PHPConsole logging is not implemented.");

		if (file_exists($_ENV['phpConsole']) && !$GLOBALS['CONSOLE']) { // && The browser supports this package
			/*
			@include_once($_ENV['phpConsole']);
			PhpConsole::start();
			parent::$loggers[] = __CLASS__;
			*/
		}
	}

	public function log( $level, $context, array $items )
	{
	}
}
