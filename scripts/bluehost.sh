#!/bin/bash
# Facilitates operations against our bluehost server (control panel)

bhEmail_configHostAcct_="
Usage: bhEmail_configHostAcct
Ensures the existence of email account(s) and alias(es) to match the site configuration of the current hostacct.
We get our information from ~/etc/sites and from $ADMIN_HOME/services/*/service.sh
The resulting side-effect are configurations on the mail server to handle email for the following:
 - For the hostacct itself there is a single email account called $ACCOUNT@SyntheticWebApps.com
 - For each site-service combination there is an alias called $ACCOUNT.$SERVICE@$DOMAIN which forwards to the above.
"
function bhEmail_configHostAcct {
    getHostAcct "$*"
    local emacct="$ACCOUNT@$WEBHOST_DOMAIN" site= sitedomain= portbase= services= service=
    echo "Setting up main account '$emacct'..."
    bhEmail_addAccount "$emacct" && echo "OK." || echo "Failed."
    bhEmail_addForwarder "$ACCOUNT.monitor@$WEBHOST_DOMAIN" "$emacct" || echo "Create monitor alias failed."
    while read site sitedomain portbase services
    do
        [ -z "${site%%#}" ] && break
        echo "Processing site '$site' @ '$sitedomain'..."
        for service in $services
        do
            echo "Processing service '$service'... "
            bhEmail_addForwarder "-a$site" "$ACCOUNT.$service@$sitedomain" "$emacct" && echo "OK." || echo "Failed."
        done | awk '{print "    " $0 }'
        echo "Done with site '$site'."
    done < $ACCOUNT_HOME/etc/sites | awk '{print "  " $0}'
    echo "Done for '$ACCOUNT'"
}

bluehostJson_="
Usage: bluehostJson [ -a{accountHandle} ] { module } { function } [ \"name=value\" [ ... ] ]
This will output a JSON API response to stdout.
The module and function must be defined in the BlueHost APIs.
The -a option selects credentials from the database text file found in ~/etc/BlueHost. 
	If you don't provide this option, the first in the file will be used.
"
function bluehostJson {
	local account= handle= creds=
	while [ "${1#-}" != "$1" ] 
	do 
		optval="${1#-a}" ; [ "$optval" = "$1" ] || account="$optval"
		shift
	done
	[ -z "$1" -o -z "$2" ] &&
		{ echo "You must supply the JSON API module name and function name, and then any name=value parameters."; return 1 ; }
	DATAARGS="--data-urlencode 'cpanel_jsonapi_module=$1' --data-urlencode 'cpanel_jsonapi_func=$2'"
	shift 2
	while [ "$1" ]
	do
		DATAARGS="$DATAARGS --data-urlencode '$1'"
		shift
	done
	# Now we need to select the appropriate creds
	while read handle creds
	do 
		[ "$handle" -a "$account" ] || break
		[ "$handle" = "$account" ] && break
	done < ~/etc/BlueHost
	[ "$creds" ] ||
		{ echo "Unable to obtain credentials to authenticate at BlueHost. Use -a option. $bluehostJson_" ; return 2 ; }
	eval curl --insecure --silent -u \"\$creds\" --url \"https://server.lifetoward.com:2083/json-api/cpanel\" $DATAARGS
}
echo added function bluehostJson

bhEmail_addAccount_="
Usage: bhEmail_addAccount [ -a{accountHandle} ] { emailAddress }  [ password ]  [ quota ]

The assumed password will be taken from ~/etc/emailPassDefault
The quota is taken as MB; the assumed quota is 250MB

The -a option selects credentials from the database text file found in ~/etc/BlueHost.
	If you don't provide this option, the first in the file will be used.
"
function bhEmail_addAccount {
	local options= ; while [ "$1" != "${1#-}" ] ; do options="$options $1" ; shift ; done
	validateEmailAddress "$1" $FUNCNAME || return 1
	[ -n "$2" ] && PASSWORD="$2" || read PASSWORD < ~/etc/emailPassDefault
	[ -z "$PASSWORD" ] && {
		echo "A password is required and we could not obtain a default. $bhEmail_addAccount_"
		return 3
	}
	[ "$3" ] && [ "$3" -gt 0 ] && QUOTA="$3" || QUOTA=250
	TMPFILE=`mktemp`
	bluehostJson $options Email addpop "domain=$DOMAIN" "email=$EMAIL" "password=$PASSWORD" "quota=$QUOTA" >| "$TMPFILE"
	[ "`jshon -e cpanelresult -e data -e 0 -e result < "$TMPFILE" 2>/dev/null`" = 1 ] || {
	   echo "The request failed with the following message:"
	   jshon -e cpanelresult -e data -e 0 -e reason < "$TMPFILE" 2>/dev/null
	   return 10
	}
	rm "$TMPFILE"
	echo "Success! A new email account was created for $1."
}
echo added function bhEmail_addAccount

bhEmail_dropAccount_="
Usage: bhEmail_dropAccount [ -a{accountHandle} ] { emailAddress }
You'll be required to confirm your deadly action by providing specific input on stdin.
The -a option selects credentials from the database text file found in ~/etc/BlueHost.
	If you don't provide this option, the first in the file will be used.
"
function bhEmail_dropAccount {
	local options= ; while [ "$1" != "${1#-}" ] ; do options="$options $1" ; shift ; done
	validateEmailAddress "$1" $FUNCNAME || return 1
	echo ; echo "Are you sure you want to completely destroy the email account $1?"
	local sure=
	until [ "$sure" = 'yes' ]
	do read -p " (Enter 'yes' to proceed): " sure ; done
	TMPFILE=`mktemp`
	bluehostJson $options Email delpop "domain=$DOMAIN" "email=$EMAIL" >| "$TMPFILE"
	[ "`jshon -e cpanelresult -e data -e 0 -e result < "$TMPFILE" 2>/dev/null`" = 1 ] || {
	   echo "The request failed with the following message:"
	   jshon -e cpanelresult -e data -e 0 -e reason < "$TMPFILE" 2>/dev/null
	   return 10
	}
	rm "$TMPFILE"
	echo "Success! Email account $1 was dropped."
}
echo added function bhEmail_dropAccount

bhEmail_listAccounts_="
Usage: bhEmail_listAccounts [ -a{accountHandle} ] [ domain ] [ regex ]

If you provide a domain, only accounts in that domain will be included.
If you provide a regex, only accounts matching the regex will be included.

The -a option selects credentials from the database text file found in ~/etc/BlueHost.
	If you don't provide this option, the first in the file will be used.
"
function bhEmail_listAccounts {
	local options= ; while [ "$1" != "${1#-}" ] ; do options="$options $1" ; shift ; done
	bluehostJson $options Email listpopswithdisk "domain=$1" "regex=$2" |
	jshon -e cpanelresult -e data -a -e email -u |
	sort
}
echo added function bhEmail_listAccounts

bhEmail_listForwarders_="
Usage: bhEmail_listForwarders [ -a{accountHandle} ] [ domain ] [ regex ]

Output is one mapping per line in the form:
{alias} {target}
Note that a single alias can appear multiple times, as can a single target.

If you provide a domain, only aliases in that domain will be included.
If you provide a regex, only aliases matching the regex will be included.

The -a option selects credentials from the database text file found in ~/etc/BlueHost.
	If you don't provide this option, the first in the file will be used.
"
function bhEmail_listForwarders {
	local options= ; while [ "$1" != "${1#-}" ] ; do options="$options $1" ; shift ; done
	bluehostJson $options Email listforwards "domain=$1" "regex=$2" |
	jshon -e cpanelresult -e data -a -e dest -u -p -e forward -u |
	paste -sd ' \n' -
}
echo added function bhEmail_listForwarders

bhEmail_dropForwarder_="
usage: bhEmail_dropForwarder [ -a{accountHandle} ] { address } { target }

Both parameters must be complete and valid email addresses.

The exit status will indicate success or failure, but except for API access errors, the result should be no forwarder.

The -a option selects credentials from the database text file found in ~/etc/BlueHost.
	If you don't provide this option, the first in the file will be used.
"
function bhEmail_dropForwarder {
	local options= ; while [ "$1" != "${1#-}" ] ; do options="$options $1" ; shift ; done
	validateEmailAddress "$1" $FUNCNAME || return 1
	validateEmailAddress "$2" $FUNCNAME || return 2
	bluehostJson $options Email delforward "email=$1" "emaildest=$2" |
	jshon -e cpanelresult -e data -e 0 -e statusmsg -u
}
echo added function bhEmail_dropForwarder

bhEmail_addForwarder_="
usage: bhEmail_addForwarder [ -a{accountHandle} ] { address } { target }

Both parameters must be complete and valid email addresses.

The result is a dump of the response data which should be an object containing domain, email, and fwdemail.
 On failure the result will be a string describing something about what happened.

The -a option selects credentials from the database text file found in ~/etc/BlueHost.
	If you don't provide this option, the first in the file will be used.
"
function bhEmail_addForwarder {
	local options= ; while [ "$1" != "${1#-}" ] ; do options="$options $1" ; shift ; done
	validateEmailAddress "$2" $FUNCNAME || return 2
	validateEmailAddress "$1" $FUNCNAME || return 1
	bluehostJson $options Email addforward "fwdopt=fwd" "email=$EMAIL" "domain=$DOMAIN" "fwdemail=$2" |
	jshon -e cpanelresult -e data -e 0
}
echo added function bhEmail_addForwarder
