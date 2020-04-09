# The host configuration must configure:
# variant commands, hostadmin account, bzr environment, timezone adjustment, hosting domain and system type, and hostacct model
# To avoid a chicken-and-egg problem, host.env.sh also sources common.sh.

# variant commands
sendmail () {
    { echo -e "\n\n@----------------------------------------------\nMessage for: $@" ; cat ; } >> $ADMIN_HOME/maildump.txt
}
declare -fx sendmail

netstat () {
    [ "$1" ] && local opts="$@" || local opts="-anp tcp -f inet"
    command netstat $opts | grep -v CLOSE | sed -E 's|\.([0-9]+\s)|:\1|g'
}
declare -fx netstat

ps () {
    [ "$1" ] && local opts="$@" || local opts="-eo user,pid,ppid,vsize,command"
    command ps $opts | sort -g -k3 -k1
}
declare -fx ps

# hostadmin account
export ADMIN_HOME=/Users/bizwiz/WebAdmin
. $ADMIN_HOME/scripts/common.sh
addpath $ADMIN_HOME/scripts
export ADMIN_USER=bizwiz
export ADMIN_GROUP=staff # set this to the primary group of the host administrator account

# bzr environment
export PYTHONPATH=/Library/Python/2.6/site-packages # This gets bzrlib

# timezone adjustment
export NEWDAYHOUR=0

# hosting domain and system type
export WEBHOST_DOMAIN=local
export SYSTEM_TYPE=dev

# hostacct model

FNAME=getHostAcct
eval "export usage_$FNAME=\"usage:  $FNAME  [ {hostacct} ]

$FNAME determines the appropriate active HostAcct for hosting logins.
If this function succeeds, you will be moved to the HostAcct's top directory and have already sourced the account's environment configuration script.
It will fail if the HostAcct cannot be determined and validated and configured.

On a dev system it's important to figure out which HostAcct we are interested in
    given that all work is done within the local user account, ie. bizwiz which is itself not a hosting login at all.

The order of precedence for determining the correct HostAcct:
    1. Value passed as the first arg to the function.
    2. The current working directory resides under a registered HostAcct (as per $ADMIN_HOME/accounts/$ACCOUNT)
    3. The current setting of the $ACCOUNT environment variable.
\""
getHostAcct () {
	[ "$1" -a -d "$ADMIN_HOME/accounts/$1" ] && ACCOUNT="$1" || {
		here="`pwd -P`"
		for reg in `ls "$ADMIN_HOME/accounts"`
		do
			pushd "$ADMIN_HOME/accounts/$reg"
			[ "${here##`pwd -P`}" = "$here" ] ||
                { ACCOUNT=$reg ; popd ; break ; }
			popd
		done
	} &> /dev/null
	[ "$ACCOUNT" -a -d "$ADMIN_HOME/accounts/$ACCOUNT" ] && 
	pushd . &>/dev/null && cd -P "$ADMIN_HOME/accounts/$ACCOUNT" && 
	source etc/account.env.sh 
}
export -f $FNAME && echo added function $FNAME >&2
