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
class a_NoDB extends Action
{
	public function render( Context $c )
	{
		$c->action = $this;

		// set happy content... to be overridden with problems
		$rc = static::SUCCEED;
		$content = <<<html
<h3>Congratulations!</h3>
<p>Your database appears to be reachable now as configured.</p>
html;

		try {
			$this->mydb = new mysqli(null, null, null, $name = "synthesis_$_ENV[PHASE]", null, $socket = "$_ENV[ACCOUNT_HOME]/mysql/mysqld.sock");
		} catch (ErrorException $ex) {

			switch ($errno = mysqli_connect_errno()) {

				case 1049: // No such database
					$content = <<<html
<p>You need to create the database '$name' using a privileged user account on the database server, or specify a different database name in the configuration.</p>
html;
					break;

				case 1045: // Access denied: Bad password
				case 1044: // Access denied
					$content = <<<html
<p>You must do one of the following to authorize access the database:</p>
<ul>
<li>Provide the correct password for user '$user' in the configuration.</li>
<li>Specify a different user in the configuration which has appropriate access to database '$name'.</li>
<li>Grant the user '$user' access authority to the database '$name'.</li>
</ul>
<p>We recommend that you correct these problems manually by administering the database using the mysql client.</p>
html;

				default:
					$errstring = mysqli_connect_error();
					$content = <<<html
<h3>Connection failed!</h3>
<p>$errno: <strong><i>$errstring</i></strong>.</p>
$content
html;
					$rc = static::PROCEED;

				case 0: // no problems
			}
		}

		if ($mydb instanceof mysqli) {
			$r = $mydb->query("show tables");
			$tables = $r->fetch_all();
			$r->free();
			array_walk($tables, function(&$row, $index){ $row = $row[0]; });
			if (!in_array('_user', $tables)) {
				$content = <<<html
<h3>Missing authentication table</h3>
<p>Without an authentication table, no user can be authenticated against the database.
This may be OK if your database and application are open, but that's failry unusual. You need to populate the database with the basic schema!</p>
html;
				$rc = static::PROCEED;
			}
		}

		$this->rendered = <<<html
<div id="a_NoDB">
<h2>Database connection and setup</h2>
<p>Your database connection is currently configured as follows:</p>
<table>
<tr><td>Host</td><td>$host</td></tr>
<tr><td>User</td><td>$user</td></tr>
<tr><td>Password</td><td>(hidden)</td></tr>
<tr><td>Database</td><td>$name</td></tr>
<tr><td>Port</td><td>$port</td></tr>
<tr><td>Socket</td><td>$socket</td></tr>
</table>
$content
</div>
html;
		return $this->getResult($rc);
	}
}
