=== Google+ Importer+Comment ===
Contributors: mithro
Tags: google plus, import, social, google
Requires at least: 3.0
Tested up to: 3.2.1
Stable tag: 0.1

Automatically create new WordPress posts from your activity on Google+ using Google+ Importer

== Description ==

Import your latest Google+ activity into your site as posts. Currently supports most types of activity on Google+ with more options coming soon.

This is an early version so use wisely. Please let me know about any bugs or requests.

== Installation ==

1. Upload the `/google-importer/` directory to your `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Visit the Google+ WordPress settings page and follow the directions under "Getting Started"

== Frequently Asked Questions ==

= Can I use CSS to differentiate my Google+ activity from other posts? =

Yes! The div container for posts from Google+ has "google-plus" as an additional class for your CSS convenience. If you set "via Google+" text to be shown at the end of posts, you can use span.via-google-plus to style it.

== Changelog ==

= 1.1 =
* Added option to import hashtags as WordPress tags

= 1.0.3 =
* Fixed a bug related to SSL

= 1.0.2 =
* Better error reporting for problems with wp_remote_get()

= 1.0.1 =
* Fixed broken link to the plugin's page

= 1.0 =
* Added option to selectively import posts by using a tag on Google+

= 0.9 =
* Added option to import as custom post types
* Added option to import as drafts so activity can be manually approved
* Added error checking

= 0.7 =
* Added option to set tags for posts from Google+
* Added option to show "via Google+" or other custom text at the end of posts from Google+
* Small improvement to titles regarding ellipses

= 0.6 =
* Fixed an issue with hourly checking
* Improved the "Check now" functionality
* Don't include video titles above embedded videos
* If no user-written content is included in a post, set the title to the display name of the first attachment
* Better titles for reshares and posts with line breaks
* Titles no longer are clipped in the middle of a word
* Better alignment of icons for users being reshared
* Map images are slightly smaller and more zoomed out

= 0.5.2 =
* Added better support for resharing
* Posts now have a title

= 0.5 =
* Initial release

== Roadmap ==

This plugin is currently in its early stages, so send your feedback or ideas to sutherland.boswell@gmail.com and I'll keep them in mind as development continues. Look for frequent updates, new features, and bugfixes.
