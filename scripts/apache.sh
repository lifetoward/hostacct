#!/bin/bash
# Web Administration
# Provides a set of functions used to manage Apache web server instances.
#
# Original code
# @copyright 2014 Lifetoward LLC / SyntheticWebApps
# @author Guy Johnson (Guy@SyntheticWebApps.com)
# @license proprietary
#
# Works for any Apache instance.
# To determine the Apache instance, the location of the environment configuration for the instance (env.sh) is required.
# This location can be the current working directory or passed in as the first non-option argument.
# Its configuration identifies the runtime home directory for Apache, which may differ from its own location.

# host-specific apache configuration
. /etc/apache.env.sh &>/dev/null

FNAME=apacheEnv
eval "export usage_$FNAME=\"usage:  $FNAME  [ {configLocation} ]

First $FNAME validates the environment is configured.
    If these checks fail it's because of lacking /etc/host.env.sh or /etc/apache.env.sh .
If no configLocation is provided, we will use the current directory.
$FNAME looks in configLocation for env.sh which provides final configuration for the Apache instance.
We'll output the calculated APACHE_HOME and APACHE_CONF environment variables on stdout.
\""
apacheEnv () {
    # Check environment
	[ "$ADMIN_HOME" -a -d "$ADMIN_HOME" -a -r "$ADMIN_HOME" ] ||
		{ usage "Can't establish \$ADMIN_HOME ($ADMIN_HOME) as a readable directory." ; return 11 ; }
	[ -n "$APACHE_MIME" -a -f "$APACHE_MIME" ] ||
		{ usage "Can't proceed without \$APACHE_MIME!" ; return 14 ; }
	[ -x "$APACHE_EXEC" ] ||
		{ usage "Can't proceed without \$APACHE_EXEC!" ; return 15 ; }
	[ -f "$APACHE_MODDIR/mod_alias.so" ] ||
		{ usage "Can't proceed without \$APACHE_MODDIR!" ; return 16 ; }

    # Set tentative APACHE_HOME, processing alternate location if provided
	while [ "${1#-}" != "$1" ] ; do shift ; done # shift off any options which we don't care about
	[ "$1" ] &&
		{ pushd "$1" &>/dev/null || { usage "Can't change to provided directory ($1)." ; return 2 ; } }
    APACHE_HOME="`pwd -P`" # this may be temporary if env.sh overrides it; for now it's just where we find env.sh
    [ "$1" ] && popd &>/dev/null

    # Source official runtime environment
	. "$APACHE_HOME/env.sh" ||
		{ usage "Environment script file env.sh not found in $APACHE_HOME." ; return 3 ; }
	[ -r "$APACHE_CONF" ] ||
		{ usage "Apache configuration file $APACHE_CONF not readable." ; return 4 ; }
	echo "Runtime location: $APACHE_HOME ; Configuration file: $APACHE_CONF"
	export APACHE_ARGS="-d \"$APACHE_HOME\" -f \"$APACHE_CONF\" $APACHE_DEFINES"
	export PIDFILE="$APACHE_HOME/apache.pid.log"
}
export -f $FNAME && echo added function $FNAME >&2


function apacheExec {
	echo "$APACHE_EXEC" $APACHE_ARGS "$@"
	eval "$APACHE_EXEC" $APACHE_ARGS "$@"
}
export -f apacheExec && echo added function apacheExec >&2


FNAME=apacheStatus
eval "export usage_$FNAME=\"usage:  $FNAME  [ -v ] [ -c ]

Display information about the Apache2 instance, its configuration, and its runtime status.
Pass the -v option for a verbose output employing a server-status query against the server @ localhost
Pass the -c option for a verbose check of the configuration.
returns true (0) if the instance is up and running with a registered PID, etc.
returns 1 if a ps listing cannot identify the process, normally by PID, but possibly by run dir.
\""
apacheStatus () {
	apacheEnv "$@" || return $?
	local Verbose= Config=
	while [ "$1" != "${1#-}" ] 
	do case "$1" in
		-v) Verbose=true ; shift ;;
		-c) Config="-D DUMP_VHOSTS -D DUMP_RUN_CFG -D DUMP_MODULES" ; shift ;;
	esac ; done
    fixReturn
	cd "$APACHE_HOME"
	echo
	eval "$APACHE_EXEC" -v 
	echo ; echo "Configuration check: "
	apacheExec -t $Config 2>&1
	echo ; PID=
	[ -r $PIDFILE ] && read PID < $PIDFILE &>/dev/null && [ "$PID" -gt 0 ] &>/dev/null && {
		echo "Here's a netstat report for the listening server:"
		netstat | egrep ":($APACHE_PORT|$APACHE_SECPORT)\s"
		echo ; echo "PID=$PID; Here's the running process listing:"
		ps | egrep "\s$PID\s"
	} || {
		echo "Could not determine PID. Just in case, here's a ps listing for APACHE_HOME:"
		ps | egrep "\s$APACHE_EXEC\s.*\s$APACHE_HOME\s"
	}
	STATUS=$? # The status we care about is obtained here... we don't care about the direct query
	[ $STATUS -eq 0 -a "$1" = '-v' ] && {
		echo
		STATUSURL="http://localhost:$APACHE_PORT/server-status"
		eval $BROWSER $STATUSURL || echo "'$BROWSER $STATUSURL' failed." >&2
	}
	return $STATUS
}
export -f $FNAME && echo added function $FNAME >&2


FNAME=apacheStart
eval "export usage_$FNAME=\"usage:  $FNAME  [ {configLocation} ]

Starts the Apache server configured as from the current directory or provided configLocation.
\""
apacheStart () {
	apacheEnv "$@" || return $?
	rm -f *ssl_scache*
	apacheExec -k start
}
export -f $FNAME && echo added function $FNAME >&2


FNAME=apacheStop
eval "export usage_$FNAME=\"usage:  $FNAME  [ {configLocation} ] [ -f ]

Gracefully Stop the Apache server instance identified by the established configLocation.
To force  immediate termination and close current connections, use the -f arg.
Either way we will wait up to 5 seconds for it to stop. After that we consider it a failure.
We return success (0) when the server is DOWN, and failure (1) if we could not stop it.
\""
apacheStop () {
	apacheEnv "$@" || return $?
	METHOD=graceful-stop
	[ "$1" = '-f' ] && METHOD=stop
	local pid=
	[ -f $PIDFILE ] && read pid < $PIDFILE
	apacheExec -k $METHOD
	echo -n "Waiting for $pid to stop... "
	[ "$pid" ] &&
        for tries in 5 4 3 2 1 0
        do ps -p $pid | grep $pid &>/dev/null || break;
            [ $tries = 0 ] && { echo "Failed to stop server." ; return 1 ; }
            echo -n '. ' ; sleep 1
        done
	echo "OK."
}
export -f $FNAME && echo added function $FNAME >&2


FNAME=apacheRestart
eval "export usage_$FNAME=\"usage:  $FNAME  [ {configLocation} ] [ -f ]
Gracefully stop and then start the Apache server instance.
Because we do a full stop and start, changes in the environment will be recognized.
To quick-stop the server, force-terminating connections as necessary, use the -f arg.
\""
apacheRestart () {
    apacheStop "$@" && apacheStart "$@"
}
export -f $FNAME && echo added function $FNAME >&2

