# Establishes system-specific Apache2 configuration

export APACHE_EXEC="/opt/local/sbin/httpd"
export APACHE_DEFINES="-D LACK_BUILTINS"
export APACHE_MIME=/opt/local/etc/apache2/mime.types
export APACHE_MODDIR=/opt/local/lib/apache2/modules
export APACHE_PRODUSER=_www
export APACHE_PRODGROUP=_www

# Supporting utilities
BROWSER=${BROWSER:-lynx -dump -width=120}


