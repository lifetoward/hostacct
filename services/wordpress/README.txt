
To set up WordPress as a service for a host account, the steps go something like this:

1. Create the service directory, with the run and docroot subdirs.
2. Branch the WordPress application from services/wordpress/docroot/ to $ACCOUNT_HOME/wordpress/docroot/.
3. Symlink *.conf, wp-config.php, and env.sh into $ACCOUNT_HOME/wordpress/.
4. Bring up the Apache server.
5. Hit the service at localhost:xx30 and follow the wizard to set up the database.

