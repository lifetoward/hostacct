<?php
/**
* When there's a database connectivity error, then load this handler and it will help you attempt to resolve the problem, perhaps even
* eventually by facilitating the installation or setup of a new database.
*
* All original code.
* @package Synthesis/Authentication
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2014 Lifetoward LLC
* @license proprietary
*/
require "../../lib/_/boot.php";
$c = Request::start();
$a = new Frame($c, new a_NoDB($c));
$a->render($c);
print $a->rendered;
