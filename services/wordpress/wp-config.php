<?php
/**
 * The base configurations of the WordPress.
 * Common for ALL SITEs within a shared HostAcct.
 *
 * {@link https://codex.wordpress.org/Editing_wp-config.php Editing wp-config.php}
 *
 * @package WordPress
 */

/** 
 * Begin Synthetic Web Apps multi-site, SSL, and proxy handling logic.
 * We do multisite by sharing the WordPress directory but differentiating the database prefix.
 * We learn the database prefix (site name) from $_SERVER[HTTP_GATEWAY_SITENAME] which is a header set by the proxy.
 * From this it follows that the URL, locations, and media paths are all determined separately
 * While themes and plugins are shared. Note that the user databases remain separate, and that's
 * the most likely thing to be developed-around someday.
 */
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_X_FORWARDED_HOST'];
if ($_SERVER['HTTP_SECURE_REQUEST'] == 'true') {
	$_SERVER['REQUEST_SCHEME'] = "https";
	$_SERVER['HTTP_X_FORWARDED_PORT'] = 443;
	$_SERVER['HTTPS'] = 'on';
} else {
	$_SERVER['REQUEST_SCHEME'] = "http";
	$_SERVER['HTTP_X_FORWARDED_PORT'] = 80;
	$_SERVER['HTTPS'] = null;
}
define('WP_HOME', "$_SERVER[REQUEST_SCHEME]://$_SERVER[HTTP_HOST]");
define('WP_SITEURL', WP_HOME);
$SiteName = "$_SERVER[HTTP_GATEWAY_SITENAME]";

define('UPLOADS', "static/$SiteName/wp-uploads"); // see http://codex.wordpress.org/Function_Reference/wp_upload_dir

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
$table_prefix = "${SiteName}_";

define('DB_NAME', 'wordpress');

/** MySQL database username */
define('DB_USER', 'wordpress');

/** MySQL database password */
define('DB_PASSWORD', '');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

require_once("$_ENV[ACCOUNT_HOME]/wordpress/salts.php");

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 */
define('WP_DEBUG', false);

define('RELOCATE', true);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
