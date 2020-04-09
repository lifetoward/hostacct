# env.sh configures an environment for Apache to run.
# This file is service-specific but most services have very similar stuff here.

export SERVICE=wiki SUBPORT=40

# Customizations usually drawn from ~/etc/account.env.sh:
# WikiTitle, WikiRoot

# Customizations drawn from the proxy's site recognition:
# SiteName

# We run a single server for the Wikka Wiki (wiki) service on subport 40.
# Each unique site handle gets its own database table prefix and uploaded content directory.

[ -z "$PORTBASE" -o "$PORTBASE" -gt 640 -o "$PORTBASE" -lt 11 ] && {
    PORTBASE=650
    echo "WARNING!! PORTBASE is not defined... default to $PORTBASE"
}

export SERVICE_HOME="$ACCOUNT_HOME/$SERVICE" APACHE_PORT=${PORTBASE}$SUBPORT 
export PHP_REVAL_FREQ=0 APACHE_USER=$ACCOUNT APACHE_GROUP=$ACCOUNT 
export APACHE_HOME="$SERVICE_HOME/run" APACHE_CONF="$SERVICE_HOME/httpd.conf" 
export APACHE_DOCROOT="$ADMIN_HOME/services/$SERVICE/docroot" APACHE_HOSTNAME=localhost 
export LogErrorFile="$APACHE_HOME/${DayOfWeek}log" 
export LogDebugFile="$LogErrorFile"

