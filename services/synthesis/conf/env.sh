# env.sh configures an environment for Apache to run.
# This file is service-specific but most services have very similar stuff here.

# Unlike some ignored env.sh files, this service-specific file is invariant for all accounts and systems.
# This is achieved by relying on ACCOUNT_HOME/etc/(account.env.sh|sites)

. /etc/php.env.sh

# You can set a custom home page for the account by setting SYNTH_HOMEPAGE in account.env.sh. This is really just a default.
export SYNTH_HOMEPAGE="${SYNTH_HOMEPAGE:-modules.php}"

setServiceSite synthesis

[ -z "$PORTBASE" -o "$PORTBASE" -gt 640 -o "$PORTBASE" -lt 11 ] && {
    PORTBASE=650
    echo "WARNING!! PORTBASE is not defined... default to $PORTBASE"
}

export SYNTH_HOME="${APACHE_HOME?-`pwd -P`}"
export PHASE=${SYNTH_HOME##*/}
case $PHASE in
  dev) export APACHE_PORT=${PORTBASE}11 PHP_REVAL_FREQ=300 APACHE_USER=$ADMIN_USER APACHE_GROUP=$ADMIN_GROUP ;;
  test) export APACHE_PORT=${PORTBASE}19 PHP_REVAL_FREQ=60 URL_HOST=$DOMAIN URL_LOC=/synthtest APACHE_USER=$ACCOUNT APACHE_GROUP=synthesis ;;
  prod) export APACHE_PORT=${PORTBASE}10 PHP_REVAL_FREQ=5 URL_HOST=$DOMAIN URL_LOC=/synthesis APACHE_USER=$ACCOUNT APACHE_GROUP=synthesis ;;
  *)	echo "Unable to determine the phase for this directory! Halted!" ; return -1 ;;
esac

export APACHE_HOME="$SYNTH_HOME/run"
export APACHE_CONF="$SYNTH_HOME/httpd.conf"
export APACHE_DOCROOT="$SYNTH_HOME/app/web"
export APACHE_HOSTNAME=localhost

export WEBLIB_LOC=weblib

export EMAIL_MONITOR="$ACCOUNT.monitor@SyntheticWebApps.com"
export EMAIL_ACCT="$ACCOUNT.synthesis@SyntheticWebApps.com"
export EMAIL_FROM="$ACCOUNT.System@SyntheticWebApps.com"
export EMAIL_SMTP_SERVER="smtps://server.lifetoward.com:465"
export EMAIL_IMAP_SERVER="imaps://server.lifetoward.com:993"

export LogErrorFile="$APACHE_HOME/${DayOfWeek}log";
export LogDebugFile="$APACHE_HOME/now.log";

echo "SYNTH_HOME=$SYNTH_HOME APACHE_HOME=$APACHE_HOME PHASE=$PHASE"

