=== BMRE Domain Monitor ===
Contributors: dvwallin
Tags: domain, monitor, seo
Requires at least: 3.0.1
Tested up to: 3.4
Stable tag: 0.8.9
License: WTFPL
License URI: http://en.wikipedia.org/wiki/WTFPL

This plugin is meant to monitor your domainnames and warn you before they expire.

== Description ==

BMRE Domain Monitor is built to keep an eye on the domains you add.

It checks the expiration date and warns you if a domain will expire in 30 days or less (both through email and in the admin interface)

It also has the feature (optional) to suggest domains that are similar to the ones you've added and that are still available.

DISCLAIMER: Do not ONLY trust this plugin for your domains. It works well but I can not be held responsible if something should go wrong and your domain does actually expire.

LICENSE:
           DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
                   Version 2, December 2004

Copyright (C) 2012 David V. Wallin <david@dwall.in>

Everyone is permitted to copy and distribute verbatim or modified
copies of this license document, and changing it is allowed as long
as the name is changed.

           DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
  TERMS AND CONDITIONS FOR COPYING, DISTRIBUTION AND MODIFICATION

 0. You just DO WHAT THE FUCK YOU WANT TO.

== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Upload `bmre-domain-monitor` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings -> BMRE Domain Monitor and add your domains.

== Frequently Asked Questions ==

== Screenshots ==

1. This is the admin page to add domains and monitor them.

== Changelog ==

= 0.8.9 =
Added the very missing license-information
= 0.8.8 =
Final stable version
= 0.8.7 =
PHP ending bug
= 0.8.5 =
Simple cleanup and updated readme and descriptions
= 0.8.4 =
The formatting of the lock-files were wrong and made the plug search for similar domains on every load
= 0.8.3 =
The check-files are now written to /tmp for security and to avoid permission-errors
= 0.8.2 =
0.8.1 caused a crashed due to a small simple misstake
= 0.8.1 =
Fixed bug that prevented files from being written
= 0.8 =
It is now possible to get suggestions on available domains that are similar to yours.
= 0.7 =
Fixed version-crash
= 0.6 =
Code-cleaning
Reference-adding
Update of version-tags and readme -file
= 0.4 =
It is now possible to import multiple domain names at once
= 0.2 =
Fixed bug with message on 0000-00-00 expiration dates
= 0.1 =
Initial import with stable functionality
