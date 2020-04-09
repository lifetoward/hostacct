
# hostadmin user can source me to manage HostAcct's

FNAME=newHostAcct
eval "export usage_$FNAME=\"usage:  $FNAME  { account }  { name }

The account must conform with system user account constraints, ie. [a-z_][a-z0-9_-]* .
The name can be any string of printable characters, typically the company or client who will own the account.
\""
newHostAcct () {
	[ "$1" -a "$2" ] || { usage ; return 1 ; }
	echo "$1 | $2" | grep '^[a-z_][a-z0-9_-]*\ | [[:print:]]*$' > /dev/null ||
		{ usage "Invalid arguments." ; return 2 ; }
	fixReturn
	echo "Creating hosting account '$1' with name \"$2\"..."
	ACCTHOME=/home/hostacct/$1
	sudo useradd -m -b /home/hostacct -c "$2" -k $ADMIN_HOME/template -s /bin/bash -U $1 &&
	sudo usermod -aG hostacct $1 &&
	ln -s $ACCTHOME $ADMIN_HOME/accounts/$1 &&
	cat $ADMIN_HOME/template/etc/account.env.sh | 
		sed 's/AccountHandle/'"$1/" |
		sed 's/AccountEntityName/'"$2/" >| $ADMIN_HOME/tmp/account.env.sh && 
	sudo install -m 644 -o $1 -g $1 $ADMIN_HOME/tmp/account.env.sh $ACCTHOME/etc/account.env.sh &&
	sudo chgrp $ADMIN_GROUP $ACCTHOME/mysql $ACCTHOME/proxy/test $ACCTHOME/proxy &&
	echo "Include /home/hostacct/$1/proxy/proxy.conf" >> $ADMIN_HOME/proxy/local.conf &&
	source pki.sh &>/dev/null &&
	pkiNewAccount $1 &&
	echo "
All initialization steps were completed. The account:
1. exists as a user on this host belonging to groups '$1' and 'hostacct'.
2. has a home directory: /home/hostacct/$1/.
3. has a link under $ADMIN_HOME/accounts/.
4. has been configured as a CA for its own purposes
5. can be accessed via SSH by the usual approved entities

Next you should access the system as the account and take these steps:
1. Edit ~/etc/sites to include the initially requested services and domain(s).
2. Set up DNS for the domain(s) to point here. 
3. Source $ADMIN_HOME/scripts/apache.sh and run apacheSites to configure Apache in the general purpose way.
4. Create email accounts and aliases on the mail server.
5. Enable services, usually mysql and static web services first.
"
}
export -f $FNAME && echo added function $FNAME >&2

FNAME=dropHostAcct
eval "export usage_$FNAME=\"usage:  $FNAME  { account }

This function totally wipes out a hosting account, notably nuking its entire home directoy with
all its backups and everything else. Only backups made off the system can be considered intact 
after this operation completes.
\""
dropHostAcct () {
	[ "$1" -a -d "/home/hostacct/$1" ] || { usage ; return 1 ; }
	read -p "Are you sure you want to proceed with wiping out the '$1' hosting account?" resp
	case "$resp" in y*|Y*) ;; *) return ;; esac
	echo "Removing hosting account '$1' and all its files... "
	set -x
	sudo userdel -r "$1" && 
	rm $ADMIN_HOME/accounts/$1 &&
	set +x && 
	echo "Done."
}
export -f $FNAME && echo added function $FNAME >&2

