# for hosting acounts
# Provides a set of functions used to manage a microcosmic PKI infrastructure
#
# Original code
# @copyright 2014 Lifetoward LLC / SyntheticWebApps
# @author Guy Johnson (Guy@SyntheticWebApps.com)
# @license proprietary
#

# The bootstrap of this whole system is obtaining the master top level CA self-signed certificate.
# That's done with the following command(s), run from $ADMIN_HOME/etc/:
# openssl req -x509 -newkey rsa:2048 -keyout master.key.pem -out master.ca.pem -days 7305
# With that done, it becomes possible to use pkiNewAccount, pkiNewSite, and pkiNewClient to do the rest.


FNAME=pkiEnv
eval "export usage_$FNAME=\"usage:  $FNAME  [ -p{profile} ] [ account ]

Sets up a reference to the appropriate openssl.conf file and user account name.
If you don't supply an account name, then we check to see if the logged-in user has been configured as a CA.
	If so, then that user becomes the CA; otherwise the host administrator role is assumed.
Only the host administrator can create and sign an account CA.
Account CAs can and should create and sign their server and client certificates.
The -p option allows specification of a specific configuration for an account CA op.
	'site' and 'client' are the possibilities.
\""
pkiEnv () {
	local Profile=
	while [ "$1" != "${1#-}" ]
	do
		local optval="${1#-p}" ; [ "$optval" -a "$1" != "$optval" ] && Profile="$optval."
		shift
	done
	fixReturn
	if getHostAcct $1
	then
		PKI_CONF="$ADMIN_HOME/accounts/$ACCOUNT/etc/openssl.${Profile}conf"
		echo "Acting as account $ACCOUNT"
	else
		[ "$1" ] && { usage "Invalid account name ($1)." ; PKI_CONF= ; return 91 ; }
		PKI_CONF="$ADMIN_HOME/etc/openssl.master.conf"
		echo "Acting as host administrator $ADMIN_USER"
	fi
	[ -r "$PKI_CONF" ] 2>/dev/null || 
		{ usage "Can't read $PKI_CONF." ; PKI_CONF= ; return 92 ; }
}
export -f $FNAME && echo added function $FNAME >&2


FNAME=pkiNewReq
eval "export usage_$FNAME=\"usage:  $FNAME  [ -a{account} ] [ -P ] [ -p{profile} ] { name }

Creates a pair of matching PEM files, a key and a certificate request.
	The resulting request file can be submitted for signature by a CA.
The required name parameter sets the name of both files;
	both are created in the current directory with different extensions: .req.pem and .key.pem;
	The name must be simple alphanumeric and must not include spaces.
The -a option allows you to specify the account under which to perform the operation. Without it the host admin is assumed.
If -P is provided, then encrypt the private key with des3 and therefore require a password to access the key.
	This is what you want for creating a CA, but usually not for server or client keys.
Moving and securing the resulting key file is up to you.
\""
pkiNewReq () {
	[ "$1" ] || { usage ; return 1 ; }
	local encrypt= account=$ACCOUNT profile=
	while [ "$1" != "${1#-}" ]
	do
		local optval="${1#-a}" ; [ "$1" != "$optval" ] && account="$optval"
		local optval="${1#-p}" ; [ "$1" != "$optval" ] && profile="$optval"
		[ "$1" = '-P' ] && encrypt=des3
		shift
	done
	[ "$1" -a "${1# }" = "$1" ] ||
		{ usage "Missing or invalid required name argument." ; return 2 ; }
	pkiEnv -p$profile "$account" 2>/dev/null || return $?
	if [ "$encrypt" ]
	then
		openssl req -config "$PKI_CONF" -newkey rsa -keyout $1.key.pem -out $1.req.pem &&
			echo "Generated new private key in $1.key.pem and certificate request in $1.req.pem"
	else # generate the private key separately to do so without encryption and password
		openssl genrsa -out $1.key.pem 2048 &&
		openssl req -config "$PKI_CONF" -new -key $1.key.pem -out $1.req.pem &&
			echo "Generated new private key in $1.key.pem and certificate request in $1.req.pem"
	fi
}
export -f $FNAME && echo added function $FNAME >&2


FNAME=pkiSign
eval "export usage_$FNAME=\"usage:  $FNAME  [ -a{account} ] [ -p{profile} ] { name } [ additional openssl ca options... ]

Signs a certificate request named {name}.req.pem producing a new certificate with name {name}.crt.pem .
The -a option allows you to specify the account CA to perform the operation.
	Without it the system root CA is assumed.
You'll be prompted to provide the CA password and verify the contents of the request.
The CA database will be fully updated.
\""
pkiSign () {
	[ "$1" ] || { eval echo \"\$${FUNCNAME}_\" ; return 1 ; }
	local ca= profile=
	while [ "$1" != "${1#-}" ]
	do
		local optval="${1#-a}" ; [ "$1" != "$optval" ] && ca="$optval"
		local optval="${1#-p}" ; [ "$1" != "$optval" ] && profile="$optval"
		shift
	done
	[ -z "$ca" ] && ACCOUNT=$ADMIN_USER
	pkiEnv -p$profile "$ca" || return $?
	[ "$1" -a "${1# }" = "$1" ] ||
        { usage "Missing or invalid required name argument." ; return 2 ; }
	local NAME=$1
	shift
	openssl ca -config "$PKI_CONF" -md sha256 -in $NAME.req.pem -out $NAME.crt.pem "$@"
}
export -f $FNAME && echo added function $FNAME >&2

# Above this point are relatively primitive wrappers for openssl subcommands.
# They are used by the functions below which implement host administration operations within our semantics.


FNAME=pkiNewAccount
eval "export usage_$FNAME=\"usage:  $FNAME  { account }
Creates a new Account CA for the given account including the appropriate directories and signing key and cert.
You must be the host administrator (with sudo powers) to complete this operation.
The resulting {account}.ca.pem and {account}.key.pem files will be located in $ACCOUNT_HOME/etc/ while the CA data
	directory will be $ACCOUNT_HOME/etc/CA/.
The files will be duly secured as accessible only by the account user.
The account CA is signed by the master CA found as $ADMIN_HOME/etc/.
You'll be prompted for the Account CA's key password and certificate information, and then the master CA's key password.
\""
pkiNewAccount () {
	pkiEnv "$1" || [ $? -eq 92 ] || return -1
	[ "`who -m | awk '{print $1}'`" = "$ADMIN_USER" ] ||
	    { usage "You must be $ADMIN_USER to execute this function." ; return 1 ; }
	echo ; echo "SETTING UP..."
	[ "$ACCOUNT" -a "$ACCOUNT_HOME" -a "$ACCOUNT_NAME" ] ||
		{ usage "Unable to obtain required account settings ($PWD/etc/account.env.sh)." ; popd ; return 2 ; }
	fixReturn
	cd -P "$ACCOUNT_HOME/etc" ||
		{ usage "Can't change to $ACCOUNT_HOME/etc/." ; return 9 ; }
	echo "Working in $PWD..."
	[ -d CA ] &&
		{ usage "Account already has a configured CA... aborting." ; return 3 ; }
	[ -f account.ca.pem ] &&
		{ usage "Account already has an account.ca.pem cert... aborting." ; return 4 ; }
	touch $FUNCNAME-test && rm $FUNCNAME-test || {
		sudo chgrp $ADMIN_GROUP . && sudo chmod g+w . && echo "Granted write permission on $PWD to group $ADMIN_GROUP" || 
			{ usage "Unable to operate in $PWD." ; return 5 ; }
	}

	{   mkdir CA &&
		touch CA/data &&
		echo 0001 > CA/serial &&
		ln -s "$ADMIN_HOME/etc/openssl.account.conf" openssl.conf &&
		ln -s "$ADMIN_HOME/etc/openssl.client.conf" . &&
		ln -s "$ADMIN_HOME/etc/openssl.site.conf" . &&
		chmod 660 CA/data &&
		ls -lA CA &&
		chmod 700 CA &&
		sudo chown -R $1 CA &&
		sudo chgrp -R $1 CA
	} || { usage "CA setup ops failed." ; return 8 ; }

	echo ; echo "Initialized account CA data directory and openssl.[*.]conf:"
	/bin/ls -lA

	echo ; echo "CREATE NEW PRIVATE KEY AND CERT REQUEST for account $ACCOUNT's CA:"
	pkiNewReq -p account ||
		{ usage "Failed to create new CA key and cert request." ; return 6 ; }
	echo ; echo "VERIFY AND SIGN THE NEW CA CERTIFICATE:"
	pkiSign account ||
		{ usage "Failed to sign the new CA request with the root CA key." ; return 7 ; }
	rm account.req.pem
	mv account.crt.pem account.ca.pem
	echo ; echo "Key created and new CA cert produced."

	{   chmod 444 account.ca.pem &&
		chmod 440 account.key.pem &&
		sudo chown $1 account.ca.pem account.key.pem
	} || { usage "Securing PEM files failed." ; return 10 ; }
}
export -f $FNAME && echo added function $FNAME >&2


FNAME=pkiNewSite
eval "export usage_$FNAME=\"usage:  $FNAME  { handle }
Creates a web server SSL identity certificate in the appropriate location for an account's site identified by handle.
You must be the owner of the CA in order to run this. That is, you must be logged in as the hosting account.
The certificate will be ~/proxy/certs/{handle}.pem and the key will be ~/proxy/keys/{handle}.pem .
This certificate's key does NOT have a password, but will be secured for access only by the proxy web server.
	That means it will be owned by the production proxy webserver run-as user (www-data) and have $ADMIN_GROUP for group.
The handle provided must be found in the $ACCOUNT_HOME/etc/sites file along with its domain name.
\""
pkiNewSite () {
	[ "$1" ] || { usage "Missing site handle." ; return 1 ; }
	pkiEnv || return $? # By not passing an account we effectively require NON-ADMIN_USER
	local Etc="`dirname "$PKI_CONF"`" Handle Domain other
	while read Handle Domain other
	do
		[ "$Handle" ] ||
			{ eval echo \"Unable to locate the provided handle '$1' in $Etc/sites. \$${FUNCNAME}_\" ; return 2 ; }
		[ "$Handle" = "$1" ] && break
	done < "$Etc/sites"
	export Domain

	echo ; echo "Creating key and certificate for site '$Handle' with domain '$Domain' for account '$USER':"
	pkiNewReq -a$ACCOUNT -psite "$Handle" ||
		{ usage "Failed to create the new key and request for site $Handle." ; return 4 ; }

	echo ; echo "Signing the cert request for site '$Handle' using the account CA:"
	pkiSign -psite "$Handle" ||
		{ usage "Failed to sign the cert request for site $Handle." ; return 5 ; }

	sudo $ADMIN_HOME/scripts/installSiteIdentity $Handle $ACCOUNT ||
		{ usage "Failed to install the new identity files for site $Handle." ; return 6 ; }
}
export -f $FNAME && echo added function $FNAME >&2


FNAME=pkiNewClient
eval "export usage_$FNAME=\"usage:  $FNAME  { name }
Creates a client identity key-cert-ca bundle (PKCS#12, *.pfx) for a user or device belonging to the account.
The name parameter should identify the user primarily, and optionally the client device where the key is managed;
	it should be be in the form:
	Full Name [@ ClientDeviceName]
		- example: -
	Guy Johnson @ melody
You must be logged in as the hosting account to run this.
You will need to provide (in duplicate) a password to create the key. This password must be conveyed securely to the
    end user whose identity this represents. For example, they could provide it to you to employ here, or you could inform
    them of the password over the phone. The password is needed only to crack the file to insert it into its home keystore.
This operation will produce 4 files with names identity.[[req|crt|key].pem|pfx] in the current directory.
	WARNING: Existing files by these names will be clobbered. All but the .pfx file will be removed.
	The resulting identity.pfx file should be conveyed to the end user who will install it into their client device's keystore.
The certificate will be usable for a variety of purposes including SSL authentication and email security (S/MIME).
When the key and certificate are properly installed, its authenticated presence can be taken as proof of identity for
	devices which provide it. For this reason, a revocation policy is important to deal with the situation of lost or
	compromised devices.
\""
pkiNewClient () {
	[ "$1" ] || { usage "Missing client name." ; return 1 ; }
	pkiEnv || return $? # By not passing an account we effectively require NON-ADMIN_USER
	export NewClientName="$*" # A generous way to glom arguments together and not require quoting
	echo ; echo "Creating private key for $NewClientName:"
	pkiNewReq -pclient identity ||
		{ usage "Failed to create the new key and request for $NewClientName." ; return 4 ; }

	echo ; echo "Validating the certificate request for $NewClientName:"
	pkiSign -pclient identity ||
		{ usage "Failed to sign the certificate for $NewClientName." ; return 5 ; }

	echo ; echo "Producing the exportable identity file for $NewClientName:"
	openssl pkcs12 -export -certfile "$ACCOUNT_HOME"/etc/account.ca.pem -inkey identity.key.pem -in identity.crt.pem \
		 -out identity.pfx -name "$NewClientName" ||
		{ usage "Failed to produce the PKCS#12 file for $NewClientName." ; return 6 ; }
	rm identity.key.pem identity.req.pem identity.crt.pem
}
export -f $FNAME && echo added function $FNAME >&2
