<?php
/**
* A persistent session is an unauthenticated session which is able to last a long time.
* It's useful for supporting the application process, whereby an unregistered user seeks to request access to the system or a business.
*/
class OpenSession extends Request
{
	/**
	* Root contexts must provide a static start method which handlers can call to bootstrap themselves.
	*/
	public static function start( )
	{
		global $root;
		static::sessionStart();
		if ($_SESSION['_root'] instanceof OpenSession)
			$root = $_SESSION['_root'];
		else
			$root = new OpenSession;
		return $root;
	}

	protected static function sessionStart( )
	{
		session_start();
		logInfo("Started session named ". session_name());
		return $name;
	}

	protected function __construct( )
	{
		if (!session_id())
			throw new ErrorException("One may only construct a new session object within an established session context.");
		session_unset();
		parent::__construct();
		$_SESSION['_root'] = $this;
	}

	/**
	 * cancels out any existing session. Note that it destroys the cookie, too, so to ensure predictable results,
	 *      return a response to the user agent after destroying the session and before creating a new one to use.
	 * @param string $message (Optional) the notice message about why the session is reset.
	 * @return string The message passed in is returned back so that this function can be called "on the way out"
	 */
	public static function sessionDestroy( $message = null )
	{
		if ($sid = session_id()) {
			$name = session_name();
			session_unset();
			session_destroy();
			session_write_close();
			logInfo("Client session '$name=$sid' destroyed. $message");
		}
		return $message;
	}
}

