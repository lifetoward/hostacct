# Establishes system-specific PHP configuration

export PHP55_MODLIB=mod_php5.so
export PHP55_MODNAME=php5_module
export PHPRC=$ADMIN_HOME/etc/php.ini
# On MacOSX dev we have built our own PHP with modules and extensions compiled in.
export PHP_INI_SCAN_DIR=
export PHP_EXT_DIR=
export FIREPHP="$ADMIN_HOME"/services/synthesis/packages/FirePHPCore
