=== Plugin Name ===
Contributors: boonebgorges, rmccue, sennza
Donate link: http://teleogistic.net/donate
Tags: buddypress, ning, import
Requires at least: WP 2.8, BuddyPress 1.2
Tested up to: WP 3.5.1, BuddyPress 1.7
Stable tag: 2.0.7

Imports the contents of a Ning Network Archive into BuddyPress

== Description ==

This plugin imports the contents of your Ning Network Archive into BuddyPress.

== Installation ==

* Install in your plugins folder and activate.
* Download your Ning network export, using the Archiver tool as described here: http://help.ning.com/cgi-bin/ning.cfg/php/enduser/std_adp.php?p_faqid=3796
* Create a directory called `ning-files` in your wp-content directory.
* Upload the content of your unzipped Ning export (a group of .json files, as well as several directories) to /wp-content/ning-files.
* Visit the plugin panel at Dashboard > BuddyPress > Import From Ning


== Frequently Asked Questions ==

= What if I'm not running BuddyPress? =

Version 2.0+ of Import From Ning does not support WordPress standalone. Use version 1.1: http://wordpress.org/extend/plugins/import-from-ning/download/

= What content will Import From Ning import? =

Import From Ning currently imports the following items from a Ning export: members, member profiles, member avatars, members comments (the "wall"), groups, discussions, and blogs. The plugin attempts to recognize inline images and copy them to the BuddyPress installation, so that you don't lose the images you've put in your blog posts.

= What about my images, movies, and music? =

BuddyPress by itself does not currently support photo, movie, or music galleries. The best plugin available for images right now is BuddyPress Album+ http://wordpress.org/extend/plugins/bp-album/, which is in the process of being adapted to support video and audio galleries as well. In the future, I hope to expand this plugin to import content for display with Album+, but in the meantime you can import your multimedia content manually.

= What do I do if I have a gargantuan network? =

The plugin is most reliable when working with relatively small sets of data, though I have tested it with a network import of over 1300 users. There are various safeguards built into Import From Ning, so that if a particular step fails to complete because your hosting environment runs out of memory, you can simply refresh the page to pick up from where you've left off.

= What's with all these new groups? =

In BuddyPress, each forum must be associated with a group. In cases where your Ning discussion thread was not part of a group, Import From Ning creates a group corresponding to the discussion category and places the discussion topic there.

= You rule! =

That's not really a question, but thanks. You are pretty cool yourself. 

== Changelog ==

= 2.1 =
* Major refactor for better stability and performance on large imports. Huge thanks to rmccue and sennza.
* Better support for screwed-up JSON export formats from Ning
* Improved PHP 5.4 support
* Adds support for Events import (using The Events Calendar)
* Better username and email address sanitization
* Improved image importing

= 2.0.7 =
* Improved compatibility with WP Network Admin
* Better error messages

= 2.0.6 =
* Fixed email problem. Props Karen Chun

= 2.0.5 =
* Fixed some debug notices

= 2.0.3 =
* Small bug fixes
* Updated help text on main screen

= 2.0.2 =
* Small bug fixes

= 2.0.1 =
* Addressed another member looping bug related to avatar import
* Smoothed out plugin behavior after groups with no forum_ids

= 2.0.1 =
* Addressed member looping bug

= 2.0 =
* Added support for content of Ning network

= 1.1 =
* Switched from copy-and-paste to direct .csv upload
* Added BuddyPress profile field import functionality

= 1.0 =
* Initial release
