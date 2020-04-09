#!/bin/bash
#  cron.sh
#  cron executes this script directly on behalf of the WebAdmin (ubuntu).
#
# First we partition hour hourly dispatches into these periods:
#       hourly, daily, weekly, monthly
# We execute the hourly tasks to completion before the daily, then the weekly, etc.
# Thus the entire process is synchronous and sequenced from most common to least common tasks.
#
# Within that periodic framework we:
#  - take care of proxy maintenance jobs
#  - dispatch `$ACCOUNT_HOME/scripts/cron.sh { period }` for each registered hostacct under $ADMIN_HOME/accounts/.
#
# Thus the entire set of procedures across all accounts is synchronized in a predictable sequence.
# This is deemed to be safer than having separate cron registration for each hostacct, but perhaps that's not important.
#
# This approach has the benefit of accumulating a single outcome of all operations dispatched each hour under a single trace log.
# Because support for all hostaccts is managed centrally, there's no good reason to log this stuff per account.
#
# Under this scheme the following rules should be followed by all scripts involved across all accounts:
#   - Processing must not hang (read data from STDIN) or require minutes to execute. We need to complete whole cycles well within an hour even at full load.
#   - All information that might be useful for monitoring status or task completion can simply go to STDERR and STDOUT, but cannot be interactive.
#   - Any problem with reporting to the administrator should be immediately sent as an email and processing should continue as best it can.
#
# All original code.
# Created by Biz Wiz on 1/23/15.
# Copyright © 2015 Lifetoward LLC

echo ; echo "WebAdmin MASTER cron job commenced `date`"

{ # This shell comes blank, so it needs a full initialization
  . /etc/host.env.sh
  . apache.sh
  . mysql.sh
} &>/dev/null

function eachAcct {
    pushd "$ADMIN_HOME"/accounts &>/dev/null
    echo "Executing $1 scripts for all accounts..." ; echo
    for acct in `ls`
    do
        echo "HOSTACCT = $acct"
        HOSTACCTCRON="$ADMIN_HOME"/accounts/$acct/scripts/cron.sh
        if [ -x "$HOSTACCTCRON" ]
        then sudo su -c "\"$HOSTACCTCRON\" $1" $acct
        else echo "(No $HOSTACCTCRON to execute.)"
        fi
        echo
    done | awk '{print "    " $0}'
    popd &>/dev/null
}

echo ; echo "WebAdmin HOURLY cron tasks began `date`"
    eachAcct hourly
echo "WebAdmin HOURLY cron tasks ended `date`" ; echo

[ `date +%H` -eq "$NEWDAYHOUR" -o "$1" != "${1%ly}" ] && {

echo "WebAdmin DAILY cron tasks began `date`"
    sudo "$ADMIN_HOME"/scripts/manageProxy stop #    sudo ~/scripts/manageProxy maintain
    logfile="$ADMIN_HOME"/proxy/${DayOfWeek}proxy.log
    echo -e "\nLog file initialized `date`\n" > "$logfile"
    sudo chmod 660 "$logfile" 
    sudo chgrp $ADMIN_GROUP "$logfile"
    eachAcct daily
    sudo "$ADMIN_HOME"/scripts/manageProxy start #    sudo ~/scripts/manageProxy restore
    echo "New log file for proxy:" ; ls -l "$logfile" ; head "$logfile" | awk '{print "    " $0}'
    sudo "$ADMIN_HOME"/scripts/manageProxy check
echo "WebAdmin DAILY cron tasks ended `date`" ; echo

    [ $DayOfWeek = sun. ] && {
echo "WebAdmin WEEKLY cron tasks began `date`"
    eachAcct weekly
echo "WebAdmin WEEKLY cron tasks ended `date`" ; echo
    }

    [ `date +%d` -eq 1 ] && {
echo "WebAdmin MONTHLY cron tasks began `date`"
    eachAcct monthly
echo "WebAdmin MONTHLY cron tasks ended `date`" ; echo
    }
}

echo "WebAdmin MASTER cron job completed `date`" ; echo

