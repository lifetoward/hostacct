# System-specific environment settings which configure MySQL

export MYSQL_HOME=/opt/local/lib/mysql55
export MYSQL_PLUGINS=$MYSQL_HOME/lib/plugin
export MYSQL_BINDIR=$MYSQL_HOME/bin
export MYSQLD_EXEC=$MYSQL_HOME/bin/mysqld
export MYSQL_MSGDIR=/opt/local/share/mysql55
addpath "$MYSQL_BINDIR" > /dev/null

export PHPMYADMIN_HOME=/Users/bizwiz/WebAdmin/services/mysql/phpMyAdmin
export TZINFO=/usr/share/zoneinfo
