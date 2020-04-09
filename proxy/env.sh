# APACHE_HOME - The working directory of the running server
# APACHE_DOCROOT - The document root of the general server
# APACHE_PORT - The http listening port
# APACHE_SECPORT - The https listening port
# APACHE_USER - The user under which to run worker processes
# APACHE_GROUP - The group under which to run worker processes
# APACHE_HOSTNAME - The canonical hostname of the general server

export APACHE_HOME="$ADMIN_HOME/proxy"
export APACHE_CONF="$APACHE_HOME/httpd.conf"
export APACHE_DOCROOT="$ADMIN_HOME/proxy/docroot"
export APACHE_PORT=80
export APACHE_SECPORT=443
export APACHE_USER=$APACHE_PRODUSER
export APACHE_GROUP=$APACHE_PRODGROUP
export APACHE_HOSTNAME=proxy.$WEBHOST_DOMAIN
export VHOST_LOGPREFIX=
export DOMAIN=$APACHE_HOSTNAME

DayOfWeek="`date +%a | tr [A-Z] [a-z]`."
export LogDebugFile="$APACHE_HOME/${DayOfWeek}proxy.log"
export LogErrorFile="$APACHE_HOME/${DayOfWeek}proxy.log"
