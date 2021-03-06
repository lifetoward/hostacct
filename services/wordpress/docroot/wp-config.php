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
$table_prefix = "${SiteName}_";

/** The name of the database for WordPress */
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

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'rr<F+fBGCi}fZf5SsC)1@A8JUp,8p|;=k:+)>Ruer3A=#VE!; _([P@V]_OaJycx');
define('SECURE_AUTH_KEY',  '+B]n8*0u@!Lv>SKZ%.|y/DZS2wb}EfoI4HtRN|]Ti,(eM*z8n9LLk|%cCm6z(bL{');
define('LOGGED_IN_KEY',    '$UH/+K:U L#XA)y$cEI~TS;Js6W>)0,=C{^0~`r+lgw1Dz)+|GO+v-HbS$eF6ATI');
define('NONCE_KEY',        'R[Y/zILcHje*w$xx{k@^|9=b!.B;Z.-Nu`G&j0Z{EPiTz:K+U%|Rnqa%QumIXpq~');
define('AUTH_SALT',        '88-O|_:um^.v+sAM:UticYn8uJ1SE#g1M72{HSV$Rvg1}0dt;*$,)ABQA|iZ>RdU');
define('SECURE_AUTH_SALT', 'Q}4]F(qFn-N)#Hq@v&>!LIt,&T$UT66eIc0kT-pb;O-rGe(/o!X97FlQc7Fu!_cf');
define('LOGGED_IN_SALT',   'OT@I?H?tkig8<+<iq3^H[|SH.x]+6U>;,+38%6DvqQ_1Fc. Sp;<,,r~OkB~:pz4');
define('NONCE_SALT',       ';5_N-)O-vgBu[Q]<8G}UD:yT~Ky$v*`e)}3uap)W!+BD$4,#WsYs+p{Pnc|h?a,q');
/** Guy replaced these crypt keys 2015-05-23 */
/**#@-*/


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
