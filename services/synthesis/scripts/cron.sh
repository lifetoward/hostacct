#!/bin/bash
#
# services/synthesis/scripts/cron.sh
#
# By calling setServiceSite, we establish the first site configured for synthesis. This is singleton-style processing.
#
# This script is responsible for multiplexing by PHASE. 
# It looks for instance directories named prod, test, or dev andi, if configured with env.sh and cron.sh, runs the instance-specific cron.sh inside each.
#
#  Created by Biz Wiz on 2015-02-03.
#

setServiceSite synthesis &>/dev/null || { echo "Synthesis is not configured for any site in account '$ACCOUNT'" ; exit 0 ; }

export AlertSubject="ALERT! Exception running ${1^^} in Synthesis for ${ACCOUNT^^}"

cd "$ACCOUNT_HOME"/synthesis ||
    echo "Account $ACCOUNT has synthesis configured for site='$SITE' with portbase='$PORTBASE' but does not have a synthesis service directory; cannot proceed." | throwAlert 1

echo -e "\nSynthesis ${1^^} cron script for ${ACCOUNT^^} running"

for phase in prod test dev
do if [ -r $phase/env.sh -a -x $phase/cron.sh ] ; then
    if [ $phase = prod ] ; then
        # The rule is that if production is configured, then it should be up or that's an alert.
        apacheEnv $phase
    else
        # test and dev instances must not only be configured but also running to warrant handling under cron.
        apacheStatus $phase &>/dev/null || 
            { echo "${phase^^} instance is configured but not running... skipped." ; continue ; }
    fi
    cd "$SYNTH_HOME" ; echo -e "\n${phase^^} instance is configured in `pwd`"
    ./cron.sh $1 | awk '{ print "    " $0 }'
    cd ..
else
    echo -e "\n${phase^^} instance not configured... skipped."
fi ; done

echo -e "\nSynthesis ${1^^} cron script for ${ACCOUNT^^} ends"

