# Establishes system-specific Apache2 configuration

export APACHE_EXEC="/usr/sbin/apache2"
export APACHE_DEFINES="-D LACK_MPM"
export APACHE_MIME=/etc/mime.types
export APACHE_MODDIR=/usr/lib/apache2/modules
export APACHE_PRODUSER=www-data
export APACHE_PRODGROUP=www-data
export APACHE_CACERTS_PATH=/etc/ssl/certs

# Supporting utilities
BROWSER=${BROWSER:-lynx -dump -width=120}

