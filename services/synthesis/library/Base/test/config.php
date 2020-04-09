<?php

$appconfig = array(

	// dbinfo = { Array of necessary details to connect to the database server and select the appropriate database. }
	// Required array keys are name, host, user, password. Optional: port, socket
	 'dbinfo'=>array( 'name'=>"capceltest", 'host'=>"127.0.0.1", 'user'=>"capceltest", 'password'=>"testPassword" )

	// handle = { Alpha_Numeric slug for the application for use in cookies, etc. }
	,'handle' => 'testApp'

	// phase = { dev | test | prod } indicating which development phase this app instance represents
	,'phase' => 'dev'

	// timezone = { timezone in Region/City format }
	// obviously this is the preferred timezone from the app's perspective - can be overridden by browser clients
	,'timezone' => 'America/Chicago'

	// sysroot = { Fully qualified absolute server local filesystem path to the application directory (where the lib and app branch directories reside)
	,'sysroot' => '/Users/guy/Projects/business'

	// secure = true | false : Whether or not to secure the transmission channel between browser and server
	,'secure' => false

	// host = { resolvable host name to reach web server }
	,'host' => '127.0.0.1'

	// urlbasedir = { locates the URL root of the application relative to the root within the host name's docroot }
	// no terminating / please.... leave empty if there's no subdir under document root
	,'urlbasedir' => 'business'

	// weblib = { URL to the root of the standard Synthetic Web Apps web library, typically javascript and stylesheet libraries organized by package}
	,'weblib' => "http://localhost/weblib"

	// firebug = { absolute path to the firephp include file, fb.php }; if empty or phase==prod, firephp will not be enabled in the requests
//	,'firebug' => '/usr/lib/php/FirePHPCore/fb.php'

	// modules = { Array of modules which are needed to enable the application. List them in the order of class loading precedence. Modules not listed here will not be found for schema and class loading. }
	// Each directory at branch top level (app,lib) has its own array of modules in order of precedence. App always takes precedence over lib.
	,'modules' => array('lib'=>array('Base/test','Base','MySQL'), 'app'=>array())
);

require_once("$appconfig[sysroot]/lib/_/boot.php");
