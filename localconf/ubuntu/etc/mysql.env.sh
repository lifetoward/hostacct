# source me with bash

# System-specific environment settings which configure MySQL

export MYSQL_HOME=/usr
export MYSQL_PLUGINS=$MYSQL_HOME/lib/plugin
export MYSQL_BINDIR=$MYSQL_HOME/bin
export MYSQLD_EXEC=$MYSQL_HOME/sbin/mysqld
export MYSQL_MSGDIR=$MYSQL_HOME/share/mysql
addpath "$MYSQL_BINDIR" > /dev/null

export PHPMYADMIN_HOME="$ADMIN_HOME/services/mysql/phpMyAdmin"
export TZINFO=/usr/share/zoneinfo
