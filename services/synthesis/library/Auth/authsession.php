<?php
/**
* AuthSession provides a standard authenticated persistent session context for the usual privileged application UI handlers and for any
* privileged access handlers which rely on cookie-based sessions.
*
* Note that we authenticate, but don't check for proper authorization. We leave that to the caller. We just fetch the current authenticated context, if any,
* and answer general questions about credentials in terms of login and role.
*
* All original code.
*
* @package Synthesis/Authentication
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2015 Lifetoward LLC
* @license proprietary
*/
class AuthSession extends SecureRequest
{
	use Authorization; // Overrides the dummy authorization methods defined in RootContext
	use SerializationHelper; // For some reason this can't be inside the Authorization trait... when it is, the private properties don't serialize.

	public static function start( )
	{
		global $root;
		session_start(); // must have a session before we can log out of it (if requested)
		if ($_SESSION['_root'] instanceof AuthSession)
			$root = $_SESSION['_root'];
		else {
			static::setRequestDetails(); // of all abort scenarios, only this one has never instantiated a Request object
			static::abort("You need to log in.");
		}
		if ($root->request[0]['_'] == 'logout')
			static::abort("You have been logged out as requested.");
		if ($_SESSION['clientIPAddr'] != $_SERVER['clientIPAddr'])
			static::abort("Client IP Address mismatch appeared nefarious so we nixed the session. (had=$_SESSION[clientIPAddr], now=$_SERVER[clientIPAddr])");
		logInfo("Authenticated session recovered for $_SERVER[script]; client @ '$_SERVER[clientIPAddr]'; user '". $root->getAuthUser() ."'.");
		return $root;
	}

	/**
	* The constructor for an authenticated session is only ever called during an authentication request.
	* It must have a functioning request context with database connectivity in order to obtain the authentication credentials and check them respectively.
	* It never returns. It redirects either to the "abort" target (typically the login.php handler) OR to the app target ($_GET[target])
	*/
	public function __construct( Context $c, $asserted )
	{
		logDebug("Authenticating...");
		// Here we look up the user to see whether it's valid. Note that at this stage, there is no session since the login action runs in a session-free context
		try {
			$login = e_login::get("username='$asserted->username' && password=". t_password::put_db($asserted->password));
		} catch (dbNotSingularX $x) {
			$this::relogin($c, 'There is a configuration error in the system regarding your credentials. Please contact the system administrator.');
		} catch (dbQueryX $x) {
			$this::relogin($c, 'Our login database query failed. Is your database configured?');
		}

		if (!$login) { // generally a simple authentication failure, but there are some special cases...
			if ($asserted->username == 'thoth') { // the thoth login is an attempt to initialize the authentication database
				if (!e_login::cardinality()) { // if there are no login records at all then we will initialize the login database
					// We interpret the password field as a username|password|repeat set and we configure that login record and give it the "*super" role
					list($asserted->username, $pass1, $pass2) = explode('|', $asserted->password, 3);
					if ($pass1 !== $pass2)
						$this::relogin($c, 'Your passwords need to match.');
					$asserted->password = $pass1;
					if (!($super = e_role::get("label='*super'"))) {
						$super = e_role::create();
						$super->label = '*super';
						$super->description = "This role normally permits access to all data and functionalities.";
					}
					$map = r_rolemap::get($asserted, $super);
					$map->store(); // this will effect the storage of the login and role as well
					$this::relogin($c, "Thoth, god of records and administration, has performed his joyful duty.");
				}
				$this::relogin($c, 'Invalid login attempt. Authentication is already configured.');
			}
			$this::relogin($c, 'Your login name and password combination were not correct.');
		} // if login failed...

		// If we are here then we know that the authentication request succeeded... we have a known user with a matching password. Note that authorization is not established here.
		$this->initialize($login);

		// Now we ensure that any possible previously existing session is destroyed.
		static::destroySession("Ensuring clean slate...");
		// Now we establish a new session with an appropriate cookie definition
		session_start();
		session_unset();
		logInfo(['Session ID'=>session_id(), '_SESSION'=>$_SESSION]);
		// Obtain a new root Context and place our session in it as _root.
		parent::__construct();
		$_SESSION['_root'] = $this;
		$_SESSION['clientIPAddr'] = $_SERVER['clientIPAddr'];

		if (!($target = $c->request['args']['target']))
			$target = $c->urlbase; // if target is not requested explicitly, assume it just means the DocumentRoot of the app
		logDebug("Successful authentication; redirecting to application target: $target");
		session_write_close();
		header("Location: $target"); // Now that we have an authenticated session, redirect back to the originally requested app handler
		exit;
	}

	/**
	* This method sets whatever values are appropriate within this new RootContext object.
	* For our part we handle finding and associating the roles in a conveniently tested manner.
	* If there's ever any problem encountered, you should call static::relogin($c, $message) to get another go at the login attempt.
	* @param e_login $login The login info record (complete) which is now authenticated.
	*/
	protected function initialize( e_login $login )
	{
		$this->authInfo = $login;
		foreach (r_rolemap::getRelations($login) as $role)
			$this->authRoles[$role->_relative->label] = $role->_relative->_id;
	}

	/**
	* Thoroughly wipe out an existing and active session (if there is one)
	* @param $message Logged reason for the destruction
	* @return The same message you passed in... so you can use it for something more.
	*/
	protected static function destroySession( $message )
	{
		if ($sid = session_id()) {
			// We include an explicit delete for the old session cookie according to INI settings.
			session_unset();
			session_destroy();
			session_write_close();
			setcookie($name = session_name(), '', 1, ini_get('session.cookie_path'), ini_get('session.cookie_domain'), ini_get('session.cookie_secure'), ini_get('session.cookie_httponly'));
			logInfo("Client session '$name=$sid' destroyed. $message");
		}
		return $message;
	}

	/**
	* This method is called as an exit from the Authentication process only. (self::__construct())
	* It ensures there's no session, and redirects back to the login screen (usually ourself) passing through the intended app target, root context class, and a login reason message.
	* @param Context $c This is the non-secure basic request context which is started by a login form submission. It gives us our args to pass thru.
	* @param string $msg This is the reason that a login attempt is required. (The message passed in to the request is moot at this point, and we require a new one.)
	* @return DOES NOT RETURN. The script exits here.
	*/
	protected static function relogin( Context $c, $msg )
	{
		static::destroySession($msg);
		$target = $c->urlbase; // presumed value
		$class = get_called_class(); // presumed value
		extract($c->request['args'], EXTR_IF_EXISTS); // overwrites target and class if they are args
		static::setHTTPHeaders(); // ensures cache prevention
		header("Location: $_SERVER[location]/login.php?msg=". urlencode($msg) ."&class=". urlencode($class) ."&target=". urlencode($target));
		exit;
	}

	/**
	* Call this method when you need to kill the current session and require a login.
	* Whatever REQUEST_URI was current when you called this will become the new target to redirect to after successful authentication.
	* @param msg The message which explains the reason for the return to the login page.
	* @return DOES NOT RETURN and redirects the browser to the login.php handler
	*/
	public function abort( $msg, $class = null )
	{
		static::destroySession($msg);
		if (!$class)
			$class = get_called_class();
		static::setHTTPHeaders(); // ensures cache prevention
		header("Location: $_SERVER[location]/login.php?class=$class&target=". urlencode($_SERVER['script']) ."&msg=". urlencode($msg));
		exit;
	}

}
