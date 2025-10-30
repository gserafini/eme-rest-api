=== Events Made Easy REST API ===
Contributors: gabrielserafini
Tags: events, rest-api, events-made-easy, api, calendar
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.6.4
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

REST API endpoints for Events Made Easy plugin - CRUD operations for events, locations, categories, and recurring events.

== Description ==

A WordPress plugin that adds REST API endpoints to the Events Made Easy plugin.

Events Made Easy (EME) uses custom database tables instead of WordPress custom post types, so it doesn't expose events via the WordPress REST API by default. This plugin bridges that gap by providing a clean REST API interface to EME's functionality.

= Features =

* Full CRUD operations for events (Create, Read, Update, Delete)
* Location management
* Category management
* Recurring events support
* WordPress application password authentication
* Standard REST API conventions
* WordPress Multisite compatible

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Navigate to Plugins → Add New
3. Search for "Events Made Easy REST API"
4. Click "Install Now" and then "Activate"
5. Ensure Events Made Easy plugin is also installed and activated

= Manual Installation =

1. Download the plugin ZIP file
2. Log in to your WordPress admin panel
3. Navigate to Plugins → Add New → Upload Plugin
4. Choose the ZIP file and click "Install Now"
5. Click "Activate Plugin"
6. Ensure Events Made Easy plugin is also installed and activated

= From Source =

1. Clone or download from GitHub: https://github.com/gserafini/eme-rest-api
2. Upload the `eme-rest-api` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Ensure Events Made Easy is installed and activated

= Multisite =

The plugin works seamlessly with WordPress Multisite:

* **Network Activate:** Activates for all sites in the network
* **Per-Site Activate:** Can be activated individually per site
* **Table Prefixes:** Automatically handles site-specific table prefixes
* **REST API:** Each site has its own `/wp-json/eme/v1/` endpoints
* **Credentials:** Use application passwords from each site's admin

== Frequently Asked Questions ==

= What is the base URL for the API? =

The base URL is: `https://yoursite.com/wp-json/eme/v1`

Replace `yoursite.com` with your actual WordPress site URL.

= How do I authenticate API requests? =

Use WordPress Application Passwords for authentication. You can create an application password in WordPress admin under Users → Profile → Application Passwords.

Example:
`curl https://yoursite.com/wp-json/eme/v1/eme_events -u "username:application_password"`

= What endpoints are available? =

Main endpoints include:

* `/eme_events` - List and create events
* `/eme_events/{id}` - Get, update, or delete a specific event
* `/eme_locations` - List and create locations
* `/eme_locations/{id}` - Get a specific location
* `/eme_categories` - List and create categories
* `/eme_recurrences` - Create recurring events
* `/eme_recurrences/{id}` - Get or delete a recurrence
* `/eme_recurrences/{id}/instances` - Get instances of a recurring event

See the full documentation at: https://github.com/gserafini/eme-rest-api

= Why are endpoints named eme_events instead of events? =

To avoid conflicts with Events Made Easy's custom post types, all endpoints use the `eme_` prefix. This ensures the REST API routes take precedence over WordPress's default post type routing.

= Does this work with WordPress Multisite? =

Yes! The plugin fully supports WordPress Multisite. Each site in the network gets its own REST API endpoints and authentication.

= What version of Events Made Easy is required? =

Events Made Easy version 2.0 or higher is recommended. The plugin uses EME's built-in functions for reliability and compatibility.

== Screenshots ==

Screenshots coming soon.

== Changelog ==

= 1.6.4 - 2025-10-30 =
* Fixed DELETE endpoint to properly handle eme_db_delete_event void return
* Added WordPress object cache clearing before deletion verification
* Now correctly returns 200 success when events are deleted

= 1.6.3 - 2025-10-30 =
* Fixed DELETE endpoint to properly verify deletion and return correct status
* Improved error handling with defensive verification approach

= 1.6.2 - 2025-10-30 =
* Fixed markdownlint issues in documentation
* Improved code formatting and documentation
* Added .markdownlint.json configuration

= 1.6.1 - 2025-10-30 =
* Removed test/debug endpoints
* Updated all documentation with new endpoint names

= 1.6.0 - 2025-10-30 =
* **BREAKING:** Renamed all endpoints to use eme_ prefix to avoid conflicts
  * `/events` → `/eme_events`
  * `/locations` → `/eme_locations`
  * `/categories` → `/eme_categories`
  * `/recurrences` → `/eme_recurrences`
* Fixed WordPress routing conflicts with EME custom post types
* Switched to using EME's built-in functions for better reliability

= 1.1.0 - 2025-10-29 =
* **NEW:** Recurring events support
  * Create weekly, monthly, or specific-date recurring events
  * Get recurrence instances
  * Update and delete recurrences
* **NEW:** Direct database queries for improved performance
* Enhanced event formatting with proper URL generation
* Added multisite compatibility
* Improved error handling

= 1.0.0 - 2025-10-29 =
* Initial release
* Full CRUD operations for events
* Location management
* Category management
* WordPress application password authentication

== Upgrade Notice ==

= 1.6.0 =
BREAKING CHANGE: All endpoint URLs have changed to use eme_ prefix. Update your API calls from /events to /eme_events, /locations to /eme_locations, etc.

= 1.1.0 =
Major update with recurring events support and performance improvements.

== Additional Information ==

= GitHub Repository =

https://github.com/gserafini/eme-rest-api

= Support =

For issues, questions, or contributions:
* GitHub Issues: https://github.com/gserafini/eme-rest-api/issues
* Events Made Easy: https://github.com/liedekef/events-made-easy

= Credits =

Created by Gabriel Serafini to enable REST API access to Events Made Easy.
