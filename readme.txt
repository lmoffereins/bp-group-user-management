=== BP Group User Management ===
Contributors: Offereins
Tags: buddypress, groups, member, user, management
Requires at least: 3.8, BP 2.0
Tested up to: 4.0, BP 2.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Integrate BP group member management with WP user management.

== Description ==

Manage BuddyPress group members within the WordPress User Management screen:
* Filter users by groups
* Add users to groups
* Remove users from groups

Requires at least BP 2.0.

Also adds additional group management functionalities:
* Group creation shortcut in Add New admin menu

Supports the following BuddyPress group plugins:
* BP Group Hierarchy
* BP Group Organizer

== Installation ==

1. Place the 'bp-group-user-management' folder in your '/wp-content/plugins/' directory.
2. Activate BP Group User Management.
3. Visit the User Management screen to manage BuddyPress group members.

== Changelog ==

= 1.0.0 =
* Plugin requires now at least BP 2.0
* Remove search unblocking logic
* Fix plugin singleton pattern
* Fix bug where bulk user edit logic frustrated user deletion
* Fix bulk user edit admin notices

= 0.0.3 =
* Fix group member user list links

= 0.0.2 =
* Fix group member query for hierarchy
* Fix group dropdown for hidden groups and no hierarchy

= 0.0.1 =
* Initial release
