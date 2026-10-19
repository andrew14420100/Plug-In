=== Bracelli Club ===
Contributors: bracelliclub
Tags: sports club, members area, attendance, courses, staff
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 0.4.0

Management tools for the Bracelli Club sports centre: modular admin dashboard, private members area, course timetable and staff attendance.

== Description ==

Bracelli Club is the management plugin of the Bracelli Club sports centre. The user interface is in Italian.

Features available in this version:

* **Modular core**: module catalogue with dependencies, cascading deactivation only after confirmation, versioned database migrations, safe mode (`define( 'BRACELLI_CLUB_SAFE_MODE', true );`) and a recovery page that works without JavaScript.
* **Admin dashboard** (React, built with the WordPress packages): light/dark themes, colour palettes, per-user widgets and favourites, optional hiding of the WordPress menu with a button that always stays visible.
* **Roles and permissions**: club roles (front office, instructor, employee, member) with atomic capabilities checked on the server for every page, action and REST request.
* **Activity log** with masked personal data, filters and REST API.
* **Diagnostics** and settings export/import with a preview of the differences.
* **Private area** on the front end (shortcode `[bracelli_area_riservata]` for the header button): login with username or email, no public registration; front-office staff, instructors, employees and members never see the WordPress dashboard.
* **Courses**: course catalogue (create, edit, archive, duplicate, with history), rooms with server-side overlap checks, weekly timetable imported from a CSV file with preview, conflict detection and report (admin page or `wp bracelli-corsi import`); members see the timetable, instructors mark each of their lessons as present or absent, the front office assigns instructors and rooms and changes or cancels timetable entries with full history (nothing is deleted).
* **Staff shifts** (prototype): the front office plans employee shifts, each employee sees only their own shifts and records attendance.
* WP-CLI commands: `wp bracelli status|migrate|module|diagnose`.

Not included yet: membership records, membership cards, bookings, payments, documents and synchronisation with external management software.

= Privacy =

The plugin does not contact external services and does not collect usage data. It stores in the site database: the activity log (personal data masked), course timetables, instructor attendance and staff shifts, linked to WordPress user accounts. The only outgoing request is a check, from the admin page "Area riservata", that the site itself answers on the short private-area address.

= Source code =

The JavaScript and CSS in `assets/dist` are compiled from the sources published at https://github.com/andrew14420100/Plug-In (folder `frontend`). Build with Node.js 22: `npm ci && npm run build`.

== Installation ==

1. Back up the site.
2. Upload the ZIP from Plugins → Add New Plugin → Upload Plugin and activate it.
3. Open the Bracelli Club dashboard once as an administrator: the database tables are created by the migrations.
4. Optional: add the `[bracelli_area_riservata]` shortcode to the site header (for example with the Elementor Shortcode widget).

Deactivating or uninstalling the plugin keeps all data, unless the option "Elimina tutti i dati alla disinstallazione" is enabled in the settings.

== Frequently Asked Questions ==

= Does it work with WordPress Playground? =

Yes for evaluation. Playground uses SQLite instead of MySQL/MariaDB, so it is not representative of performance on a real host.

= Which database is supported? =

MySQL 8.0 and MariaDB 10.11 are tested.

== Changelog ==

= 0.4.0 =
* Courses, phase 1: create, edit, archive and duplicate courses from the private area, with history; rooms managed by the front office, room per timetable entry or per course, server-side blocking of overlapping lessons in the same room.
* CSV import never overwrites courses edited or archived by staff.
* The legacy "Gestore corsi" role is no longer created or assignable; existing users keep their permissions until an administrator converts them.

= 0.3.1 =
* Security: an instructor can no longer record lessons from before their assignment, nor overwrite a colleague's record; staff can record shifts up to 30 days back; only administrators can enable data deletion on uninstall; only administrators trigger database migrations from admin requests.
* Readme in English; translations are loaded by WordPress.
* Migrations also run on WordPress Playground (SQLite has no GET_LOCK).

= 0.3.0 =
* Private area, courses and instructor attendance.

= 0.2.0 =
* Staff shifts and attendance prototype.

= 0.1.0 =
* First version: modular core and dashboard.
