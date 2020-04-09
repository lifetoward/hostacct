
# hostadmin account
export ADMIN_HOME=/home/ubuntu
. $ADMIN_HOME/scripts/common.sh
addpath $ADMIN_HOME/scripts
export ADMIN_USER=ubuntu
export ADMIN_GROUP=ubuntu # set this to the primary group of the host administrator account

[ `whoami` = $ADMIN_USER ] && 
	. fixperms.sh # ensure that after files are exchanged with bzr the owner and group are restored

# bzr environment
# bzr is duly configured by Ubuntu Server LTS

# timezone adjustment
export NEWDAYHOUR=6 # server is UTC: day boundaries at midnight or 1am depending on daylight savings

# hosting domain and system type
export SYSTEM_TYPE=server
export WEBHOST_DOMAIN=syntheticwebapps.com
