# Source this file after pushing changes up from the dev branch
# The push makes all updated files ubuntu ubuntu and some of them can't stay that way
set -x
cd ~/localconf/ubuntu/etc
chgrp hostacct *.env.sh ssmtp/ssmtp.conf
cd ~/scripts
chgrp hostacct apache.sh bluehost.sh common.sh hostacct.cron.sh installSiteIdentity mysql.sh pki.sh
set +x

