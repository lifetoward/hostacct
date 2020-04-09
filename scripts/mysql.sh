# Provides shell functions to facilitate managing a MySQL database server instance.
# Our host management model allocates a single MySQL server instance to each hosted account.
# PREREQUISITES:
#   Run as a hostacct ACCOUNT, or as ADMIN_USER with the account provided with option -a
#
# Client access is provided only through a Unix socket within the mysql service directory for the account.
# Database access is configured for any user able to use the socket to have full access to all databases (without GRANT).
# Said another way: Authorization for mysql instances relies on filesystem access control to the socket.
#   If you can access the socket you can use any databases to which you've been granted access as an ANONYMOUS MySQL user.
#   As long as you use mysqlCreateDB to create new databases, this access is automatically granted for each database.

. /etc/mysql.env.sh &>/dev/null
. /etc/php.env.sh &>/dev/null # for phpMyAdmin

FNAME=mysqlEnv
eval "export usage_$FNAME=\"usage:  $FNAME  [ -a {account} ]
Establishes all the necessary configuration to manage an account's MySQL server.
Includes changing to the mysql runtime directory as a side effect.
You must be logged in as a registered host account or be ADMIN_USER and supply the account name via the -a option.

Provides these aliases for interactive shells:
  myclient - Run the CLI database client against the account's instance
  mydump - Run mysqldump (database SQL data export utility)
  myroot - Run the CLI database client as the database root user
  myadmin = Run the mysqladmin CLI utility as the database root user

Any hostacct member can use these CLI functions to access the database as the root user, with authority to create
	databases, grant privileges, etc. This kind of root access to the database is only prevented from users coming
	from outside the host. That is, all database-using web services will NOT authenticate to mysql when they run.
\""
mysqlEnv () {
	fixReturn
	while [ "$1" ]
	do [ "$1" = '-a' ] && { ACCOUNT="$2"; shift ; }
		shift
	done
	getHostAcct $ACCOUNT || { usage "Unable to establish account." ; return 92 ; }

	SERVICE=mysql
	cd -P "$SERVICE" || { usage "Missing service directory ($SERVICE) in account home directory ($ACCOUNT_HOME)." ; return 93 ; 	}
	ACCTSERV_HOME="$PWD"
	MYPIDFILE="$ACCTSERV_HOME/mysqld.pid.log"
	trap RETURN # No more directory changes, and what happens below we want to remain as side-effects

	export MYSQL_OPTIONS="--defaults-file=$ACCTSERV_HOME/mysqld.cnf --socket=$ACCTSERV_HOME/mysqld.sock"
	export MYSQL_ROOTOPTS='-u root'
	export myclient="mysql $MYSQL_OPTIONS"
	export mydump="mysqldump $MYSQL_OPTIONS"
	export myroot="mysql $MYSQL_OPTIONS $MYSQL_ROOTOPTS"
	export myadmin="mysqladmin $MYSQL_OPTIONS $MYSQL_ROOTOPTS"
	alias myclient="$myclient"
	alias mydump="$mydump"
	alias myroot="$myroot"
	alias myadmin="$myadmin"
}
export -f $FNAME && echo added function $FNAME >&2


FNAME=mysqlStatus
eval "export usage_$FNAME=\"usage:  $FNAME   [ -a {account} ]

Checks the status of a specific MySQL database server instance
Provide the -a option and the account name unless you are current logged in as the intended account.
Affirmed side-effects if returning true:
   $PID will be set to a valid number, though perhaps not a confirmed running process.
   MySQL's runtime data directory is valid at $ACCTSERV_HOME/data
 true (0) exit status means the server is running
 1 means there's no pid file (or we can't access it)
 2 means the pid file does not contain a number
 3 means despite there being a pid file, we can't see the process (perhaps we lack access)
 4 means the data directory could not be validated (or we don't have access)
\""
mysqlStatus () {
	fixReturn
	mysqlEnv "$@" &>/dev/null || { stat=$? ; usage "Unable to establish environment." ; return $stat ; }
	echo ; echo "phpMyAdmin server status:"
	apacheStatus "$ACCTSERV_HOME"
	echo ; echo "MySQL server status with runtime directory: $ACCTSERV_HOME"
	[ -d "$ACCTSERV_HOME/data/mysql" ] || { usage "Unable to locate the mysql core database. (Not initialized? Bad \$ACCTSERV_HOME?)" ; return 4 ; }
	eval $myadmin ping
	[ -r "$MYPIDFILE" ] || { usage "No PID file. Server is NOT running." ; return 1 ; }
	read PID < "$MYPIDFILE"
	[ "$PID" -gt 0 ] || { usage "Invalid PID value ($PID) obtained from $MYPIDFILE file. Server is NOT running." ; return 2 ; }
	ps -p "$PID" -So pid,etime,cputime,vsz,%mem,user,command | cat ||
        { usage "Could not list the process with ID from pid file ($PID). Server is NOT running." ; return 3 ; }
	eval $myclient -e status
}
export -f $FNAME && echo added function $FNAME >&2


FNAME=mysqlStart
eval "export usage_$FNAME=\"usage:  $FNAME   [ -a {account} ]

Starts the account's MySQL database server instance. Return values:
    true (0) if the server is already running or if we start it successfully
    false 21 if there's a problem with getting to the runtime directory
    the result of mysqlStatus otherwise
\""
mysqlStart () {
	fixReturn
	mysqlEnv "$@" &>/dev/null || { stat=$? ; usage "Unable to establish environment." ; return $stat ; }
	mysqlStatus "$@" &>/dev/null &&
		{ functionUsage "" "mysqld is already running with PID $PID; not restarting" ; return 0 ; }
	[ -d data ] || return 22
    # The following commands were in mysqld_safe, but we're not sure if we need them:
	# trap '' 1 2 3 13 15 ; ulimit -c $core_file_size ; ulimit -n $open_files
	{ echo ; echo -n "mysqld starting " ; date ; } >> "mysqld.log"
	nohup nice -n 0 $MYSQLD_EXEC $MYSQL_OPTIONS \
		--basedir="$MYSQL_HOME" \
		--lc-messages-dir="$MYSQL_MSGDIR" \
		--plugin-dir="$MYSQL_PLUGINS" \
		--datadir="$ACCTSERV_HOME/data" \
		--log-error="$ACCTSERV_HOME/mysqld.log" \
		--pid-file="$MYPIDFILE" \
		&>>"$ACCTSERV_HOME/mysqld.log" &
	echo -n "Waiting for server to initialize..."
	for (( x = 5 ; x ; x-- ))
	do sleep 1 ; echo -n " ."
		[ -S mysqld.sock ] && break
	done
	[ -S mysqld.sock ] && echo " OK." ||
        { usage "Still can't see the socket... assuming start failed." ; return 23 ; }
	chmod 660 mysqld.sock
	chmod 640 mysqld.log "$MYPIDFILE"
	echo "Secured socket and log files."
	ls -l *.log *.sock
	mysqlStatus "$@" && apacheStart
}
export -f $FNAME && echo added function $FNAME >&2


FNAME=mysqlStop
eval "export usage_$FNAME=\"usage:  $FNAME   [ -a {account} ]

Terminates a running MySQL server instance.
\""
mysqlStop () {
	fixReturn
	mysqlEnv "$@" &>/dev/null || { stat=$? ; usage "Unable to establish environment." ; return $stat ; }
	apacheStop $ACCTSERV_HOME
	$myadmin shutdown && echo "Shutdown request succeeded."
}
export -f $FNAME && echo added function $FNAME >&2


FNAME=mysqlRestart
eval "export usage_$FNAME=\"usage:  $FNAME   [ -a {account} ]

Stop, pause a second, and then restart the MySQL server for the account.
Returns the result of the status check done after the start.
\""
mysqlRestart () {
	fixReturn
	mysqlStop "$@" &>/dev/null || return $?
	mysqlStatus "$@" &>/dev/null ||
	mysqlStart "$@"
}
export -f $FNAME && echo added function $FNAME >&2


FNAME=mysqlInit
eval "export usage_$FNAME=\"usage:  $FNAME

Initialize a MySQL database server instance on behalf of an account.
You must be the account owner to perform this operation.
    returns true (0) if initialization and security enhancement succeeded
    returns 1 if the server is running
    returns 2 if the server's data directory already has a mysql database directory
    returns 3 if mysql_install_db failed
    returns 4 if unable to start the virgin server
    returns 5 if the root password could not be set
\""
mysqlInit () {
	fixReturn
	mysqlEnv &>/dev/null || { stat=$? ; usage "Unable to establish environment." ; return $stat ; }
	[ "$SYSTEM_TYPE" != dev -a `who -m | awk '{print $1}'` = $ADMIN_USER ] &&
		{ usage "You must be the account owner to initialize MySQL." ; return -2 ; }
	cd "$ACCOUNT_HOME"
	[ -d mysql ] || mkdir mysql || { echo "Can't establish $PWD/mysql." ; return -4 ; }
	chmod 770 mysql ||
		{ usage "Failed to establish proper permissions on the mysql service directory." ; return -3; }
	cd mysql || { echo "Can't change into mysql service directory ($PWD/mysql)" ; return -5 ; }
	for x in "$ADMIN_HOME"/services/mysql/* ; do ln -s "$x" ; done
	mkdir sessions backups

	mysqlStatus &>/dev/null &&
        { usage "Can't initialize a server that's already running!!" ; return 1 ; }
	[ -d "$ACCTSERV_HOME/data/mysql" ] &&
        { usage "Can't initialize because the mysql database directory already exists. ($ACCTSERV_HOME/data/mysql)" ; return 2 ; }
	[ -d "$ACCTSERV_HOME/data" ] || mkdir "$ACCTSERV_HOME/data"
	cd -P "$ACCTSERV_HOME"
	chmod 750 data

	local logfile=`tempfile -p myIn- -s .log`
	mysql_install_db $MYSQL_OPTIONS --basedir=$MYSQL_HOME --datadir=$ACCTSERV_HOME/data &>"$logfile" ||
        { functionUsage "" "Data directory initialization failed." ; echo -e "\nHere's the log ($logfile):\n" ; cat "$logfile" ; return 3 ; }
	echo "Data directory initialized"
	rm "$logfile"

	mysqlStart &>/dev/null
	echo -n "Waiting for the start... "
	for ((x=5 ; x >= 0 ; x--))
	do
		[ -S mysqld.sock ] && break
		echo -n .
		sleep 1
	done
	[ -S mysqld.sock ] || { functionUsage "" "Failed to get the server running for initial configuration." ; return 4; }
	echo " Up!"

	# Get rid of all accounts which are not @ localhost
	mysql $MYSQL_OPTIONS -u root -e "DELETE FROM mysql.user WHERE Host != 'localhost'" &&
		echo "Extra users removed."

	# Install the phpMyAdmin advanced feature support database
	mysql $MYSQL_OPTIONS -u root < "$ACCTSERV_HOME/pma_tables.sql" &&
	rm "$ACCTSERV_HOME/pma_tables.sql" &&
	echo "Installed phpMyAdmin advanced features support database"

	# Now drop the test database and remove its grant records
	mysql $MYSQL_OPTIONS -u root -e "GRANT ALL PRIVILEGES ON *.* TO ''@'localhost'" &&
		echo "All privileges on hypothetically existing databases granted to any user from localhost."

	# Populate the timezone database from the system zoneinfo
	mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql $MYSQL_OPTIONS -u root mysql || true # we allow this to fail as good enough
}
export -f $FNAME && echo added function $FNAME >&2


FNAME=mysqlCreateDB
eval "export usage_$FNAME=\"usage:  $FNAME   {databaseName}

Add a database. You must be the hostacct user.
Establishes all privileges to the database for the anonymous user (authorized by filesystem access to socket)
\""
mysqlCreateDB () {
	fixReturn
	mysqlEnv &>/dev/null || { stat=$? ; usage "Unable to establish environment." ; return $stat ; }
	[ "$1" -a "$1" = "${1##-}" ] ||
		{ usage "Provide the database name as the only arg" ; return 2 ; }
	mysqlStatus &>/dev/null ||
		{ functionUsage "" "The server is not running. Can't proceed." ; return 1 ; }
	$myadmin create "$1" ||
		{ functionUsage "" "Failed to create the database. Operation stopped." ; return 3 ; }
	$myclient $MYSQL_ROOTOPTS -e "GRANT ALL PRIVILEGES ON $1.* TO ''@localhost" ||
		{ echo "Failed to grant privileges on $1 to localhost users." ; return 4 ; }
	return 0
}
export -f $FNAME && echo added function $FNAME >&2


FNAME=mysqlBackupDB
eval "export usage_$FNAME=\"usage:  $FNAME   {databaseName}

Dump a database. You must be the hostacct user.
We name the backup ~/backups/mysqldb-{dbname}-{date}{time}.sql.bz2
\""
mysqlBackupDB () {
	fixReturn
	mysqlEnv &>/dev/null || { stat=$? ; usage "Unable to establish environment." ; return $stat ; }
	[ "$1" -a "$1" = "${1##-}" ] ||
		{ usage "Provide the database name as the only arg" ; return 2 ; }
	mysqlStatus &>/dev/null ||
		{ usage "The server is not running. Can't proceed." ; return 1 ; }
	local bakfile="$ACCOUNT_HOME/backups/mysqldb-$1-`date -u '+%Y%m%d%H%M%S'`Z.sql.bz2"
	{ echo ; echo "New Backup: $bakfile" ; } >> dump.log
	$mydump --comments --complete-insert --create-options --extended-insert --lock-tables --log-error="dump.log" "$1" |
		bzip2 >| "$bakfile"
	ls -l "$bakfile"
}
export -f $FNAME && echo added function $FNAME >&2
