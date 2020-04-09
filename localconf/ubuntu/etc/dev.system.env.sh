
# hostadmin account
export ADMIN_HOME=/home/ubuntu
export ADMIN_USER=ubuntu
export ADMIN_GROUP=ubuntu # set this to the primary group of the host administrator account

# [ `whoami` = $ADMIN_USER ] && 
# 	. fixperms.sh # ensure that after files are exchanged with bzr the owner and group are restored

# bzr environment
# bzr is duly configured by Ubuntu 

# timezone adjustment
export NEWDAYHOUR=1 # We're focal: day boundaries at midnight or 1am depending on daylight savings

# hosting domain and system type
export SYSTEM_TYPE=dev
export WEBHOST_DOMAIN=local

