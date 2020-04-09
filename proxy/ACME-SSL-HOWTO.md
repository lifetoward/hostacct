AcmePHP is a tool that works to get LetsEncrypt certs configured for websites.

The main docs are here:
https://acmephp.github.io/documentation/getting-started/1-installation.html

I recommend using wget instead of php's copy to pull the 2 files, and importantly you must download the latest version as in:
wget https://github.com/acmephp/acmephp/releases/download/1.0.0-beta3/acmephp.phar
wget https://github.com/acmephp/acmephp/releases/download/1.0.0-beta3/acmephp.phar.pubkey

On the AWS server, we pulled these (they must remain together) into $ADMIN_HOME/scripts and that seems to work well and need not be repeated for each hostacct.

For each domain which needs SSL, you need to do an initial proof of ownership and registration to get things started.
This procedure should be made part of domain creation, but it's not clear how well we've got that scripted/documented.
Keep in mind that this must be done from a particular hostacct's ssh session, and for each domain within that hostacct that needs to use SSL. It's also for each site, so we use $HANDLE and $DOMAIN respectively from ~/etc/sites .

# First we'll configure our master email address at LetsEncrypt to be accessed from this identity on our server:
acmephp.phar register webadmin@syntheticwebapps.com

# Next we make LetsEncrypt aware of our domain and receive our proof instructions (on the tty)
acmephp.phar authorize $DOMAIN

# Now we'll create a place to put the proof file of the provided name and content. These tidbits pop out on the tty as output from the previous command.
# It's important to note that the proxy server is already specially configured to permit access to such locations for this purpose.
cd ~/proxy
mkdir -p acmeproofs/$DOMAIN
echo "$PROOFCONTENT" > acmeproofs/$DOMAIN/$PROOFFILENAME

# Now we ask the certifier to check our proof and then request a cert for the domain
acmephp.phar check $DOMAIN
acmephp.phar request $DOMAIN

# Finally, newly configured domains like this need to find their certs and keys from the existing configuration:
ln -sf ~/.acmephp/master/certs/$DOMAIN/cert.pem certs/$HANDLE.pem
ln -sf ~/.acmephp/master/certs/$DOMAIN/chain.pem certs/CA-chain.pem
ln -sf ~/.acmephp/master/private/lifetoward.com/private.pem keys/llc.pem

# Now the hostadmin must reboot the proxy server to reload the new keys. However, that happens nightly, so you can also just wait for that to take care of itself.

We have configured the standard cron scripts to automatically run the "request" function on a daily basis. This in conjunction with the symlinked certs and keys and the fact that the proxy always reboots with daily cron runs, cert renewal should be self-managed for any site that's been through this process above. But we'll see.
