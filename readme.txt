=== WP Admin Hide Other's Posts ===
Contributors: webbtj
Donate link: http://webb.tj/
Tags: admin, posts
Requires at least: 3.5
Tested up to: 3.5
Stable tag: 1.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Hides posts by other authors in the admin area, manageable with a "view_others_posts" permission

== Description ==

This plugin creates a new permission, "view_others_posts". When viewing the posts lists in the admin area, users
without the "view_other_posts" permission will only see there own posts, not the posts of other users. Additionally
the counts at the top of the post list (All, Published, Drafts, Trash, etc.) will only count posts which belong to the
current author.

== Installation ==

This section describes how to install the plugin and get it working.

1. Extract wp-admin-hide-others-posts.zip
2. Upload `wp-admin-hide-others-posts/` to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= Can I allow certain users to view others posts? =

Yes, the plugin creates a new permission `view_others_posts` which can be enabled for any user group.

= Does this prevent authors from editing each others posts? =

No, WordPress has it's own mechanisms for determining who can edit which posts which can be managed with permissions.

= Does this affect who can view which posts on the front of the website? =

No, this only alters the list of posts as viewed in the admin area.

== Changelog ==

= 1.0 =
* Initial Release

= 1.1 =
* Added settings interface to allow an admin to decide which post types will be filtered.