#!/bin/bash
#  upgrade.sh
#
# As we move an instance up to a new version, we want to manage the database changes and other things.
#
#  Lifetoward LLC
#
#  Created by Biz Wiz on 2/8/15.
#

[ "$ACCOUNT" ] || { echo "No ACCOUNT configured. Can't proceed. Try getHostAcct."; exit 1; }

PHASE=test
[ "$1" = prod ] && PHASE=prod

mysqlEnv || exit 7
cd ~/synthesis/$PHASE
apacheStop || exit 2
rm run/sessions/*
LIBLEVEL=`bzr revno --tree lib`
APPLEVEL=`bzr revno --tree .`
cd lib && bzr pull || { echo "Not a clean pull of lib branch... app branch remains untouched... Check out the status." ; exit 3 ; }
NEWLIB=`bzr revno`
cd .. && bzr pull || { echo "Not a clean pull of app branch... no database updates attempted... Check the status." ; exit 4 ; }
NEWAPP=`bzr revno`
bzr update 
bzr update lib

echo -e "\n-- DBUP assembled from revisions LIB=$LIBLEVEL APP=$APPLEVEL `date`\nSTART TRANSACTION;\n" > ~/tmp/dbup.sql

dbups=0
for dbup in `ls lib/dbup` ; do
    [ "${dbup%.sql}" -ge $LIBLEVEL ] &&
        { echo -e "\n-- LIB dbup $dbup follows:" ; cat lib/dbup/$dbup ; echo ; } >> ~/tmp/dbup.sql &&
        echo "LIB $dbup included..." && ((dbups+=1))
done

for dbup in `ls app/dbup` ; do
    [ "${dbup%.sql}" -ge $APPLEVEL ] &&
        { echo -e "\n-- APP dbup $dbup follows:\n" ; cat app/dbup/$dbup ; echo ; } >> ~/tmp/dbup.sql &&
        echo "APP $dbup included..." && ((dbups+=1))
done

echo -e "\nCOMMIT;\n-- DBUP ENDS\n" >> ~/tmp/dbup.sql

if [ $dbups -gt 0 ] ; then
    echo -e "\nNOTE: Apache Server remains DOWN at this time."
    echo -en "\nPress Enter to see the dbup.sql file... If you terminate it normally, we'll run it."
    read j
    if less -K ~/tmp/dbup.sql ; then
        $myclient --line-numbers -o synthesis_$PHASE < ~/tmp/dbup.sql && apacheStart || { echo "FAILED TO UPDATE DATABASE" ; exit 5 ; }
    else
        echo "Aborting the upgrade's DBUP step... do what you need to do!"
        exit 6
    fi
else
    echo -e "\nNo DBUP modifications found. We're just restarting apache now...\n"
    apacheStart
fi
apacheStatus
