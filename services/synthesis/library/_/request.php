<?php
/**
* This is the standard request-handling root context. So in the constructor and the unserializer we:
* 	- Ensure that the canonical URL to the script is in fact being used. If not, we immediately dispatch a redirect.
*	- Moderate the differences between proxied and direct requests.
* 	- Establish and configure a working connection to the database
*	- Publish the root context as $GLOBALS['root']
* 	- Establish the parameters which moderate connections between client, application, and database (timezone, locale, etc.)
*	- Set authoritative Timezone, locale, and other request-related environment
*
* All original code EXCEPT for the contents of function userAgentIsMobile() which was obtained under license from:
* @package Synthesis
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2014 Lifetoward LLC
* @license proprietary
*/
class Request extends RootContext
{
	/**
	* Root contexts must provide a static start method which handlers can call to bootstrap themselves.
	*/
	public static function start( Action $action = null )
	{
		logInfo("Starting request: $_SERVER[REQUEST_URI]");
		return parent::start($action, get_called_class());
	}

	protected function __construct( $timezone = null, $language = null )
	{
		$this->timezone = $timezone ? $timezone : isset($_ENV['TIMEZONE']) ? $_ENV['TIMEZONE'] : 'America/Chicago';
		$this->language = $language ? $language : isset($_ENV['LANG']) ? $_ENV['LANG'] : 'en_US';
		$this->wakeup();
	}

	protected $request = array(), $timezone, $language;

	use SerializationHelper;
	private static $savedProperties = array(array('wakeup'), 'timezone', 'language');
	private function wakeup(  )
	{
		$this->urlbase = static::redirectToCanonicalURL();
		$this->__set('timezone', $this->timezone); // To ensure PHP and MySQL are aware of our timezone.
		$this->__set('language', $this->language); // Ensures locale settings are current.
		$this->weblib = "$this->urlbase/$_ENV[WEBLIB_LOC]";
		$this->set_request();
		$this->dbConnect();
	}

	// We need to allow access to our request, timezone, and locale properties.
	public function __get( $var )
	{
		if (isset($this->$var))
			return $this->$var;
		if ('locale' == $var)
			return "$this->language.UTF-8";
		$locinfo = localeconv();
		if ('currency' == $var)
			return $locinfo['int_curr_symbol'] ? $locinfo['int_curr_symbol'] : 'USD';
		if ('moneysign' == $var)
			return $locinfo['currency_symbol'] ? $locinfo['currency_symbol'] : '$';
		if (array_key_exists($var, $locinfo))
			return $locinfo[$var];
		return parent::__get($var);
	}

	public function __set( $name, $value )
	{
		if ('timezone' == $name) {
			// We use "named" timezones because they seem to be the most standardized
			$m = array();
			if (preg_match('/^([-+]?)(\d\d?):(\d\d)/', $value, $m)) {
				$offset = ($m[2]*3600+$m[3]*60)*($m[1] ? "$m[1]1" * 1 : 1);
				$tzname = timezone_name_from_abbr("", $offset, 0);
			} else
				$tzname = $value;
			$this->timezone = $tzname;
			ini_set('date.timezone',  $tzname);
			method_exists($this, 'dbSetTimezone') && $this->dbSetTimezone();

		// Setting any of the following is tricky... it really depends on more than the scope of runtime.
		} else if ('language' == $name) {
			if ($locale = setlocale(LC_ALL, "$value"))  // assignment intended
				$this->language = $value;
			else if (!	setlocale(LC_ALL, "$this->language"))
				setlocale(LC_ALL, $this->language = 'en_US');
			setlocale(LC_MONETARY, $_ENV['CURRENCY_LOCALE'] ? $_ENV['CURRENCY_LOCALE'] : 'en_US'); // currency overrides the client's locale preferences... it's money-based system.

		} else if ('locale'== $name) {
			list($lang, $x) = explode('.', $value, 2);
			$this->__set('language', $lang);

		} else
			parent::__set($name, $value);
	}

	/**
	* This routine works out all the differentiations between gateway-proxied and direct-to-server-docroot request scenarios.
	* It sets values in the $_SERVER superglobal complementing or overriding what the request naturally puts there.
	*/
	protected static function setRequestDetails( )
	{	/* In v4, we run as a Request or as a CLI root context. Here we handle the Request scenarios which include:
			A. A reverse-proxied request from the internet.
			B. A direct query to the server on localhost, ie. typically via SSH tunneling from a development machine.
		Depending on the situation, we have the following things to set:
			1. The canonical address of requests including scheme, host, port, and location, which assembled become urlbase.
			2. The session cookie properties to match the canonical address, notably session.cookie_path (to match location) and session.cookie_secure (to match scheme)
		Note the following:
			- We've learned never to set session.cookie_domain because Google does not process it properly.
			- The port worth using should be calculated to be null (if standard vs. the scheme) or colon-prefixed for easy inclusion in host:port concatenations.
			- We cram these important request settings into a superglobal, namely _SERVER
			- SECURITY in FUTURE:
				Before we can open up any Synthesis server to use by clients or vendors, we must implement request source authentication. We must enforce that the proxy or the
				developer client accessing our server via localhost on the server is not just someone else on the box. However, until we open up such access to the server, the proxy
				and ssh are the only means to put a request like that on localhost, so we are safe as long as those are not compromised.
		*/
		global $inboundHeaders;
		// Here we add some useful settings to the $_SERVER array for use esp. here but also throughout.
		if ($inboundHeaders['X-Forwarded-Host']) {
			$_SERVER['gateway'] = true;
			$_SERVER['request'] = $inboundHeaders['Gateway-Request']; // set by the proxy configuration (mod_rewrite)
			$_SERVER['reqPort'] = $inboundHeaders['Gateway-Port']; // set by the proxy configuration (mod_rewrite)
			$_SERVER['clientIPAddr'] = $inboundHeaders['X-Forwarded-For']; // set by the proxy automatically
			$_SERVER['secureReq'] = $inboundHeaders['Secure-Request'] == 'true'; // set by the proxy configuration (mod_rewrite)
			$_SERVER['host'] = $_ENV['URL_HOST']; // derived from the environment because these are the canonical values
			$_SERVER['location'] = $_ENV['URL_LOC']; // derived from the environment because these are the canonical values
			$_SERVER['scheme'] = $_SERVER['secureReq'] ? 'https' : $scheme; // allow the requestor to promote any connection to secure if desired.
			// We have no need or information by which to override the port which was actually used to reach the gateway, so our only decisions is whether to drop it as standard.
			$_SERVER['port'] = ($_SERVER['scheme'] == 'https' && $_SERVER['reqPort'] == 443) || ($_SERVER['scheme'] == 'http' && $_SERVER['reqPort'] == 80) ?
				'' : ":$_SERVER[reqPort]";
		} else {
			$_SERVER['gateway'] = false;
			$_SERVER['request'] = "$_SERVER[REQUEST_SCHEME]://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]";
			$_SERVER['reqPort'] = $_SERVER['SERVER_PORT'];
			$_SERVER['clientIPAddr'] = $_SERVER['REMOTE_ADDR']; // this will be the ssh server's client port in tunnel scenarios. Should be fine.
			$_SERVER['secureReq'] = $_SERVER['REQUEST_SCHEME'] == 'https';
			$_SERVER['host'] = $_SERVER['SERVER_NAME'];
			$_SERVER['location'] = ''; // this is defined, meaning the synthesis instance is always the root of the synthesis server
			$_SERVER['scheme'] = 'http'; // we force this because we never use SSL in local
			$_SERVER['port'] = ":$_ENV[APACHE_PORT]";
		}
		// The following are derived in common from the scenario-specific settings established above.
		$_SERVER['canonical'] = "$_SERVER[scheme]://$_SERVER[host]$_SERVER[port]$_SERVER[location]";
		// Note that canonical is the invariant portion of the correct request URL for the entire app instance.
		$_SERVER['script'] = "$_SERVER[location]$_SERVER[SCRIPT_NAME]";

		ini_set('session.cookie_secure', $_SERVER['scheme'] == 'https' ? 1 : 0);
		ini_set('session.cookie_path', "$_SERVER[location]");
	}

	/**
	* Particularly to ensure a secure connection, we check to make sure that the URL used to access this handler is fully as we'd expect and require.
	* If it fails to match, we redirect the user agent to our preferred location.
	* @param string $scheme Pass the desired URL scheme/protocol string as determined by the type of root context. This is almost always either 'http' or 'https'.
	* @return The canonical application URL base location, ie. "scheme://hostname[:port]/[basedir]"
	*/
	protected static function redirectToCanonicalURL( $scheme = 'http' )
	{
		static::setRequestDetails();

		if (strcmp("$_SERVER[canonical]$_SERVER[SCRIPT_NAME]", $_SERVER['request'])) {
			if ($_GET['__canonical']) {
				print "Unable to achieve a canonical target. Please check your configuration. (want \"$_SERVER[canonical]\"; getting \"$_SERVER[request]\")";
				exit;
			}
			exit(header("Location: $_SERVER[canonical]$_SERVER[SCRIPT_NAME]?__canonical=1&$_SERVER[QUERY_STRING]"));
		}
		return $_SERVER['canonical'];
	}

	// http_headers() generates direct and immediate side effects which affect the document.
	// You must generate these headers before producing any content to the output stream, BUT you must also know for sure that this is what you're generating, ie. a frame doc.
	public function setHTTPHeaders( $language = 'en_US' )
	{
		header("Cache-Control: no-cache, must-revalidate");
		header("Pragma: no-cache");
		header("Expires: 0");
		header("Content-Type: text/html; charset=utf-8");
		header("Content-Language: $language");
		header("Content-Style-Type: text/css");
		header("Content-Script-Type: text/javascript");
	}

	/**
	 * @return boolean indicating whether the requesting User Agent is a mobile device (smartphone or tablet)
	 */
	public static function userAgentIsMobile()
	{
		static $answer = null;
		is_null($answer) &&
			$answer = preg_match('/android.+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|'.
				'opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i',
				$_SERVER['HTTP_USER_AGENT'])
			|| preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|'.
				'as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|'.
				'craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|'.
				'gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|'.
				'ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|e\-|e\/|\-[a-w])|'.
				'libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(di|rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|'.
				'mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|'.
				'pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|'.
				'rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|'.
				'so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|'.
				'utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|'.
				'x700|xda(\-|2|g)|yas\-|your|zeto|zte\-/i', mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 4));
		return $answer;
	}

	/* * * * * * * * * * * * * * * SECTION: BASIC REQUEST CHAINING via _GET * * * * * * * * * * * * * * * */

	private function set_request( )
	{
		if (!count($this->request)) {
			$post = $_GET['_accept'] ? $_POST : null;
			$files = $_GET['_accept'] > 1 ? $_FILES : null;
			unset(/*$_GET['_action'],*/ $_GET['_accept']);
			$this->request = [ $_GET, $post, $_COOKIE, $files ];
			$x = 0;
			foreach ([ 'args','post','cookies','files' ] as $label)
				$this->request[$label] =& $this->request[$x++];
			logDebug(['Raw Request Parameters'=>$this->request]);
		}
		unset($_GET, $_POST, $_FILES);
	}

	/**
	* Actions should call this method to obtain appropriate target URLs containing the request-level (GET-style) arguments.
	* Because there's a dynamic argument, a single target like this can be used for a whole class of nearly-same action triggers.
	* If this target is requested, it will be "unpacked" by the context and so this method and set_request() are complementary functions.
	* @param mixed[] $args (optional) Associative array of name-value pairs of request arguments. Does not include posted data.
	* @param boolean $accept (optional) If set to true, you're enabling the acceptance of posted data. If set to > 1, then you're also accepting file uploads. Without it, any posted or file data sent in with the request would be discarded.
	* @param string $dynarg (optional) Name of the single allowed variant argument for the request
	* @param mixed $dynval (optional) Value of the single allowed variant argument for the request
	* @return string A URL-encoded but NOT HTML-escaped complete relative URL target configured to pass through the requested arguments and conditions.
	*/
	public function target( array $args = null, $accept = false, $dynarg = null, $dynval = null )
	{
		if ($accept)
			$clauses[] = "_accept=". ($accept > 1 ? 2 : 1);
		foreach ((array)$args as $name=>$value)
			$clauses[] = urlencode($name) .'='. urlencode($value);
		if ($dynarg)
			$clauses[] = urlencode($dynarg) .'='. urlencode($dynval);
		$target = "$_SERVER[canonical]$_SERVER[SCRIPT_NAME]". (count($clauses) ? "?". implode('&', $clauses) : null);
		return $target;
	}

	/**
	* For a standard Request context, the abort function is trivial. We have nothing to destroy or forget.
	* @param $target Provide the URL to which to redirect after successful authentication
	* @param msg The message which explains the reason for the return to the login page.
	* @return DOES NOT RETURN and redirects the browser to the login.php handler
	*/
	public function abort( $msg )
	{
		logInfo("ABORT: $msg");
		print "$message\n";
		exit;
	}
}
