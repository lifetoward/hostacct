<?php
/**
 * AuthContext provides a standard root context for handlers which do not have persistent state but still need to authenticate
 * the caller. Typically this root context is used for things like REST API handlers or privileged resource fetchers which make use of
 * authentication tokens which are passed with each request.
 * @package Synthesis/Authentication
 * @author Guy Johnson <Guy@SyntheticWebApps.com>
 * @copyright 2007-2014 Lifetoward LLC
 * @license proprietary
 */
class AuthContext extends SecureRequest
{
	use Authorization; // Overrides the dummy authorization methods defined in RootContext

	// Always authenticate the user based on creds obtained from the request parameters passed in. There's no persistence beyond the request.
}
