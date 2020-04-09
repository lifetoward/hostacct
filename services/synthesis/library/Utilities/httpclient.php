<?php
namespace Lifetoward\Utilities;
/**
* Our HTTPClient class makes it simpler to use cURL for our favorite purposes like Automating tedious interactions with foreign websites.
* The usage concept is that you get an HTTPClient object when you're ready to implement a cURL session, ie. typically a sequence of requests
* which together accomplish a task. You'd let go of this object when that multi-request task is done.
*
* We have the notion of a session configuration consisting of the set of CURL options which are persistent, but overridable, for all
* requests made. You set these using the configure method.
*
* Then on top of that we have the request-specific overlays. We have overlays that are programmed in to certain types of requests.
* These override the standard common configuration of the session. But then of course we overlay your request-call-specific options
* over those.
*/
class HTTPException
{
	public function __construct( array $curlopts )
	{
		// log as needed?
	}

	public function __toString()
	{
	}
}

class HTTPClient
{
	protected $res = null, $baseURL = null, $config = [
		CURLOPT_RETURNTRANSFER=>true,
		CURLOPT_HEADER=>true,
		];
	public $result = null, $info = [], $headers = [];

	public function __construct( $baseURL = null )
	{
		$this->res = curl_init();
		$this->baseURL = $baseURL;
		// Here we only set options which we provide no facility to change, even programmatically by method.
		// These options define how we've chosen this service to operate.
		curl_setopt_array($this->res, [
			CURLOPT_RETURNTRANSFER=>true,
			CURLOPT_HEADER=>true,
			CURLOPT_VERBOSE=>true,
			CURLOPT_STDERR=>fopen("$tmpdir/log", "w"),
			CURLOPT_COOKIEFILE=>$cookies, CURLOPT_COOKIEJAR=>$cookies,
			]);
	}

	/**
	* Changes you make to the options you pass here are persistent in the session,  ie. for the life of this object or until changed again.
	* All of these are overridable on a per-request basis.
	* @param array $options You can use friendly (strings) or CURLOPT_ names for the values you want to set.
	*/
	public function configure( array $options = [] )
	{
		$this->config = array_merge($this->config, $options, $this->convertOptions($options));
	}

	public function request( array $curlopts = [], )
	{
		curl_setopt_array($this->res, $options = array_merge($this->config, $curlopts));
		if (!($result = curl_exec($this->res))) // assignment intended
			throw new HTTPException($options);
		$this->info = curl_getinfo($this->res);
		list($headers, $this->result) = explode("\n\n", $result); // Watch out for CRLF issues... read up and choose an approach!
		$this->headers = [];
		foreach (explode("\n", $headers) as $header) {
			list($key, $value) = explode(': ', $header);
			$this->headers[trim($key)] = trim($value);
		}
		// Perform any sort of logging that's desired based on configuration
	}

	public function assembleURL( $uri )
	{
		return preg_match('|^https?://|', strtolower($uri)) ? "$uri" : ($this->baseURL .($uri[0] == '/' ? null : '/'). $uri)
	}

	public function urlencodeArgs( array $data )
	{
		// Convert the form or query data to urlencoded format; for POST, cURL automatically detects this string format and posts with that type
		foreach ($data as $key=>$value)
			$encData[] = urlencode($key)."=".urlencode($value);
		return implode('&', $encData);
	}

	/**
	* Use this method to locate and extract specific string data from the current request result.
	* @param string $regex Provide the interesting part (no delimiters) of a regular expression which includes at least one identified subportion.
	* @return mixed - If your regular expression contains only one subportion, you'll get just that string back.
	*		Otherwise you'll get the entire array of matching information as set by preg_match(), including the "whole match" as $result[0].
	* @throws Exception if there's no match.
	*/
	public function extract( $regex )
	{
		if (!preg_match($regex, $this->result, $matches))
			throw new Exception("Failed to locate extraction target ($regex) in result.");
		return count($matches) > 2 ? $matches : (count($matches) > 1 ? $matches[1] : $matches[0]);
	}

	public function convertOptions( array $options )
	{
		foreach ($options as $key=>$value) {
			if (is_numeric($key))
				$result[$key] = $value;
			else if ('FollowRedirects'==$key) {
				$result[CURLOPT_FOLLOWLOCATION] = $value;
			} else if ('ValidateSSLCert'==$key) {
			} else if ('TimeOut'==$key) {
			} else if ('Async'==$key) {
			} else if ('VerboseLogFile'==$key) {
				if (!$value) {
					CURLOPT_VERBOSE=>false,
				} else {
					CURLOPT_VERBOSE=>true,
				}
			} else if ('CheckStatus'==$key) {
			} else if ('PostQStyle'==$key) {
			} else if ('CookieJar'==$key) {
			}
		}
		return $result;
	}

	/**
	* Certain assumptions specific to GET requests
	*	FollowRedirects = false
	*	Post = false, PostFields = []
	*	Get = true
	*	URL = "$baseURL$uri"
	* Any of these can be overridden in the general options.
	*/
	public function GET( $uri, $qargs = [], $options = [] )
	{
		return $this->request(array_merge([
			CURLOPT_URL=>$this->assembleURL($uri),
			CURLOPT_POST=>false,
			CURLOPT_HTTPGET=>true
			CURLOPT_POSTFIELDS=>null,
			CURLOPT_FOLLOWLOCATION=>false,
			], $this->convertOptions($options)));
	}

	/**
	* Assumptions specific to POST requests (overlay session configuration)
	*	FollowRedirects = true
	* 	Post = true, PostFields = $data
	*	Get = false
	*	URL = "$baseURL$uri"
	* @param string $uri Portion of the URL which is appended to the baseURL to form the requested location.
	* @param mixed $data Usually passed as an associative array implying that we post it as data within the request body as key-value pairs per line.
	*		If you pass a string, it's assumed to be an already urlencoded query parameter list.
	* @param array $options You can override ANY comprehended options here.
	*		The class and method establish assumptions, but you can completely run around those if you want.
	* @param array $qargs Key-value pairs you pass here will end up in the QUERY_STRING portion of the request, ie. above the headers. These are always URL-encoded.
	*/
	public function POST( $uri, $data = [], $options = [], $qargs = [] )
	{
		return $this->request(array_merge([
			CURLOPT_URL=>$this->assembleURL($uri),
			CURLOPT_POST=>true,
			CURLOPT_POSTFIELDS=>$this->config['PostQStyle'] && is_array($data) ? $this->urlencodeArgs($data) : $data,
			CURLOPT_FOLLOWLOCATION=>true,
			], $this->convertOptions($options)));
	}
}
