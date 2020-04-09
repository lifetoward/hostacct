#!/bin/bash
#
#  hostacct.cron.sh
# Common cron task execution script for a hostacct user.
# Generally linked-to from the hostacct's scripts directory, ie:
#   ln -s $ADMIN_HOME/scripts/hostacct.cron.sh $ACCOUNT_HOME/scripts/cron.sh
#
# THE JOB IS TO USE SERVICE-SPECIFIC LOGIC TO PROCESS $ACCOUNT_HOME/etc/sites 
# and handle this service as defined there.
#
# Original code.
# Copyright © 2015 Lifetoward LLC
# Created by Biz Wiz on 1/24/15.
#

{ # We run as the top script under sudo or cron, so we establish the env for the cron period
  . /etc/host.env.sh 
  . apache.sh
  . mysql.sh
} &>/dev/null

getHostAcct 2>/dev/null

# More specific scripts should override this variable:
export AlertSubject="ALERT! Exception @ $ACCOUNT"

# Shorthands for getting alert messages out
sendAlert () {
    sendEmail -subject "$AlertSubject" -to "$ACCOUNT.monitor@syntheticwebapps.com" "$@"
}
throwAlert () {
    sendAlert "$@"
    eval exit "\${$#:--1}" # works in a subshell only!
}
declare -fx throwAlert sendAlert

for service in `ls "$ADMIN_HOME"/services/`
do
    SERVCRON="$ADMIN_HOME/services/$service/scripts/cron.sh"
    if [ -x "$SERVCRON" ]
    then echo -e "\n$ACCOUNT:${service^^}: Executing $SERVCRON $1:"
        eval "$SERVCRON" "$1" 2>&1 | awk '{print "    " $0}'
    else echo -e "\n$ACCOUNT:${service^^}: (No $SERVCRON for service '$service'.)"
    fi
done 

case "$1" in
  hourly)
    ;;
  daily)
    # Update all SSL certificates as needed
    while read handle domain other 
      do  case "$handle" in '#'*) continue;; '') break;; esac
          echo -e "\nChecking currency for $handle site ($domain)...\n"
          acmephp.phar request "$domain"
      done < ${ACCOUNT_HOME}/etc/sites
    ;;
  weekly)
    ;;
  monthly)
    ;;
esac

