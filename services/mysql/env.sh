# env.sh configures an environment for Apache to run.
# This file is service-specific but most services have very similar stuff here.
. /etc/php.env.sh

HANDLE=mysql
SUBPORT=01

# Unlike some ignored env.sh files, this service-specific file is invariant for all accounts and systems.
# This is achieved by relying on account.env.sh settings and port settings in ~/etc/sites .

[ "$ACCOUNT_HOME" ] || { getHostAcct && popd &>/dev/null ; }

PORTBASE=
while read handle DOMAIN portbase services
do
    [ "$handle" ] || break
    case "$services" in *$HANDLE*) PORTBASE=$portbase ; break ; esac
    # IMPORTANT NOTE: Only one phpMyAdmin apache instance will run for the account.
    # The first site defined which elects mysql will define the port ("{$PORTBASE}01") where phpMyAdmin can be found.
    # Meanwhile for the proxy, every site which elects mysql will produce a gateway to that one port.
done < $ACCOUNT_HOME/etc/sites

[ -z "$PORTBASE" -o "$PORTBASE" -gt 640 -o "$PORTBASE" -lt 11 ] && {
	PORTBASE=650
	echo "WARNING!! PORTBASE is not defined... default to $PORTBASE"
}

export SERVICE=phpMyAdmin
export APACHE_PORT="$PORTBASE$SUBPORT"
export APACHE_DOCROOT="$PHPMYADMIN_HOME"
export APACHE_HOME="$ACCOUNT_HOME/$HANDLE"
export APACHE_CONF="$APACHE_HOME/httpd.conf"
export APACHE_HOSTNAME=localhost
export APACHE_GROUP=$ADMIN_GROUP
export VHOST_LOGPREFIX=

if [ $SYSTEM_TYPE = dev ]
then
	export APPURL="http://localhost:${APACHE_PORT}"
	export APACHE_USER=$ADMIN_USER
else
	export APPURL="https://$DOMAIN/$SERVICE/"
	export APACHE_USER=$ACCOUNT
fi

export LogErrorFile="$APACHE_HOME/apache2.log";
export LogDebugFile="$APACHE_HOME/apache2.log";

