<?php
/**
* A secure request is one which makes sure that the client connected to the server or its gateway using secure communications.
*
	* NEW APPROACH NOTES
	* Both the open and secure request classes (Request and SecureRequest) will possibly need to perform redirection, so this method will be implemented in both.
	* The secure form would check the host and the proxy's secure request authentication (a special header containing a secret shared by the proxy and synthesis server).
	* That secure authentication stands in for "this is a connection from an allowed agent which itself ensured a secure comm channel to the client",
	*	which means the scheme can be assumed to be https for generated URLs and we can do secret communications.
	* Without this token in place, we request a redirect to the matching secure URL which must include a port; if our request is the result of such a redirection, we halt. 
	* For any given agent which can assure security, we would need to know the appropriate URL and base directory. This information is best provided by that agent itself. 
	*	We would only use such info if it is trusted.

* All original code.
* @package Synthesis
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2014 Lifetoward LLC
* @license proprietary
*/
class SecureRequest extends Request
{
	/**
	* Particularly to ensure a secure connection, we check to make sure that the URL used to access this handler is fully as we'd expect and require.
	* If it fails to match, we redirect the user agent to our preferred location.
	* 
	*
	* @return The canonical URLBASE (scheme://
	*/
	protected static function redirectToCanonicalURL( )
	{
		if ($_ENV['SYSTEM_TYPE'] == 'dev')
			$scheme = "http";
		else {
			// Here we must authenticate the gateway as ensuring a secure communication with the client requestor
			$scheme = "https"; // This is only correct when the gateway is known to be a standard gateway HTTPS-enforcing proxy. There may be other techniques.
		}
		return parent::redirectToCanonicalURL($scheme);
	}
}
