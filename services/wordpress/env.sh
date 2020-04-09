# env.sh configures an environment for Apache to run.
# This file is service-specific but most services have very similar stuff here.

# We run a single server for the wordpress service on subport 20.
# Each unique site handle gets its own database and uploaded content directory.

[ -z "$PORTBASE" -o "$PORTBASE" -gt 640 -o "$PORTBASE" -lt 11 ] && {
    PORTBASE=650
    echo "WARNING!! PORTBASE is not defined... default to $PORTBASE"
}

export WP_HOME="$ACCOUNT_HOME/wordpress"
export APACHE_PORT=${PORTBASE}20 PHP_REVAL_FREQ=300 APACHE_USER=$ACCOUNT APACHE_GROUP=$ACCOUNT 
export APACHE_HOME="$WP_HOME/run"
export APACHE_CONF="$WP_HOME/httpd.conf"
export APACHE_DOCROOT="$WP_HOME/docroot"
export APACHE_HOSTNAME=localhost

export LogErrorFile="$APACHE_HOME/${DayOfWeek}log";
export LogDebugFile="$APACHE_HOME/now.log";

