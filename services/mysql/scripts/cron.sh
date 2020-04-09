#!/bin/bash
#
# services/mysql/scripts/cron.sh
#
# We find the first occurrence of the mysql service in the site list for the hostacct and act on that for the whole account.
# Because it's one mysql per account, there is no $ACCSERV/cron.sh - > hostacct.cron.sh script to implement.
#
# Created by Biz Wiz on 2015-01-24.
#

setServiceSite mysql 2>&1 || exit 0

export AlertSubject="ALERT! Exception for MySQL @ $ACCOUNT"

[ -n "$ACCOUNT_HOME" -a -d "$ACCOUNT_HOME" ] && cd "$ACCOUNT_HOME"/mysql ||
    echo "Account $ACCOUNT has mysql selected for site='$SITE' with portbase='$PORTBASE' but does not have a mysql service directory; cannot proceed." | throwAlert 1

echo MySQL cron script running $1

case "$1" in

  hourly)
    mysqlStatus || echo -e "MySQL status reports down at beginning of cron script. Status output:\n`mysqlStatus`" | sendAlert
    ;;

  weekly)
    mysqlStop || echo "WARNING: Unable to stop mysql."
    DateForm=`date +%Y%m%d`

    # Do a direct-file backup of the mysql data directory. This captures the kaboodle in binary form
    zip -rp backups/mysql-$DateForm.zip data

    # rotate log files (zip archive)
    zip backups/logs-$DateForm.zip mysqld.log apache2.log
    echo "Log backups made:"
    ls -L backups/*-$DateForm.zip
    { echo -n "Log file initialized by mysql/scripts/cron.sh " ; date ; } | tee mysqld.log > apache2.log
    echo "New log files:"
    ls -l *.log

    mysqlStart || echo -e "Failed to start MySQL service. Status output:\n`mysqlStatus`" | sendAlert
    ;;

  *)    echo "(Nothing to do $1)" ;;

esac

echo MySQL cron script ends for $1
