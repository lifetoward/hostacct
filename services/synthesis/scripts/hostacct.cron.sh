#!/bin/bash
#
# Synthesis service
#  hostacct.cron.sh - Found as $ACCOUNT_HOME/synthesis/$PHASE/cron.sh
#
# Can assume that env.sh via apacheEnv have been sourced and that pwd=SYNTH_HOME
#
#  Created by Biz Wiz on 2014-02-03
#

echo "Running ${1^^} synthesis ${PHASE^^} instance work for '${ACCOUNT^^}'"
export MyDB="synthesis_$PHASE"

# Here we take care of the shell-scripted instance-global actions

case "$1" in
  hourly)
    mysqlEnv && popd &>/dev/null
    $myclient -e "SHOW TABLES" $MyDB >/dev/null ||
        echo -e "Attempt to list tables for '$MyDB' failed! Here's the command:\n$myclient -e 'SHOW TABLES' $MyDB 2>&1\n
Here's the output:\n\n`$myclient -e 'SHOW TABLES' $MyDB 2>&1`" | sendAlert
    echo -e "\nDatabase $MyDB is answering queries.\n"
    apacheStatus ||
        apacheStatus | sendAlert
    ;;
  daily)
    apacheStop || echo "WARNING: Failed stopping Apache"
    mysqlEnv && popd &>/dev/null
    $mydump $MyDB | zip "bak/$MyDB-`date +%Y%m%d`.sql.zip" - ||
        echo -e "MySQL backup (dump) of database '$MyDB' failed. Log in and check out the results.\nThe command we used is:\n  $mydump $MyDB" | sendAlert
    echo -n "Rotating log files... "
    rm run/prior.log
    mv run/now.log run/prior.log
    echo -e "\nLog initialized `date`\n" | tee run/${DayOfWeek}log > run/now.log
    echo "OK. Here are the new log files:"
    ls -l run/${DayOfWeek}log run/now.log
    apacheStart || echo "Failed to start Apache! Log in and find out what's going on!" | sendAlert
    ;;
esac

# Here we let PHP handle the app-specific actions

if [ -x app/cron.$1.php ]
then app/cron.$1.php
else echo -e "\nNo $1 script found within Synthesis instance.\n"
fi

echo "Completed ${1^^} synthesis ${PHASE^^} instance work for ${ACCOUNT^^}"

