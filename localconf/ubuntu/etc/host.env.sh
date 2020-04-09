# The host configuration must configure:
# variant commands, hostadmin account, bzr environment, timezone adjustment, hosting domain and system type, and hostacct model
# To avoid a chicken-and-egg problem, host.env.sh also sources common.sh.

. /etc/system.env.sh
. $ADMIN_HOME/scripts/common.sh 2>&1
addpath $ADMIN_HOME/scripts

# variant commands
# ssmtp provides sendmail as sendmail

netstat () {
    [ "$1" ] && local opts="$@" || local opts="-tanp"
    command netstat $opts
}
declare -fx netstat

ps () {
    [ "$1" ] && local opts="$@" || local opts="-Heo user,pid,ppid,vsize,command"
    command ps $opts | cat
}
declare -fx ps

# hostacct model

FNAME=getHostAcct
eval "export usage_$FNAME=\"usage:  $FNAME  [ {hostacct} ]

$FNAME determines the appropriate active hostacct for hosting logins.
If this function succeeds, you will be moved to the HostAcct's top directory and have already sourced the account's environment configuration script.
It will fail if the HostAcct cannot be determined and validated and configured.

On the server, it's important to ensure that the user logged in is a valid registered HostAcct.
If the current user is ubuntu, a hostacct can be selected via command line option; the current $ACCOUNT variable can also suffice.
The normal case is we're already logged in as a hostacct user. Here, validate that it's a registered account.

Note that the admin account is NOT a valid hosting account. If you are logged in as the admin user, you MUST provide the desired account as \$1
\""
getHostAcct () {
    local HostAcct=`whoami` MSG=
    if [ $HostAcct = $ADMIN_USER -o $HostAcct = root ]
    then
        HostAcct=$ACCOUNT
        [ "$1" ] && HostAcct="$1"
        MSG="Neither \$ACCOUNT nor the argument provided is a valid account."
    else
        MSG="Current user is not WebAdmin and is not registered as a valid account."
    fi
    [ "$HostAcct" -a -d "$ADMIN_HOME/accounts/$HostAcct" ] ||
        { echo "Can't identify hostacct. $MSG" ; return 1 ; }
    pushd $ADMIN_HOME/accounts/$HostAcct &>/dev/null && cd -P . && source etc/account.env.sh
}
export -f $FNAME && echo added function $FNAME
