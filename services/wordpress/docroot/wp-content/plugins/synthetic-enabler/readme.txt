=== Synthetic Enabler ===
Contributors: Guy J
Requires at least: 4.2
Stable tag: 4.2
Tested up to: 4.2

Tweaks for WordPress as hosted at Synthetic.Cloud.
1. Fix secure image URLs on non-secure pages when running FORCE_ADMIN_SSL with private SSL certs.

== Description ==

If you are running WordPress with FORCE_ADMIN_SSL and behind a gateway revproxy, the situation can arise that images from the media library end up with URLs which are secured even when viewing a non-secure page. When the SSL cert is private (not blessed by a root authority known to all browsers), then those images won't load with the non-secure page. RIGHT NOW THIS PLUGIN ONLY CORRECTS THIS PROBLEM by simply filtering out the protocal and the host name from URLs from inside the media library, ie. /wp-content/uploads/.

