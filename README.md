# Events Made Easy REST API

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPLv2%20or%20later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/github/v/release/gserafini/eme-rest-api)](https://github.com/gserafini/eme-rest-api/releases)
[![GitHub stars](https://img.shields.io/github/stars/gserafini/eme-rest-api?style=social)](https://github.com/gserafini/eme-rest-api)

REST API endpoints for Events Made Easy plugin - CRUD operations for events, locations, categories, and recurring events.

Version 1.10.0 uses EME's native recurrence lifecycle. Qualify the EME engine
and database upgrade before enabling recurrence creation on a live calendar.

## Description

A WordPress plugin that adds REST API endpoints to the [Events Made Easy](https://github.com/liedekef/events-made-easy) plugin.

Events Made Easy (EME) uses custom database tables instead of WordPress custom
post types, so it doesn't expose events via the WordPress REST API by default.
This plugin bridges that gap by providing a clean REST API interface to EME's
functionality.

## Features

* ✅ Full CRUD operations for events (Create, Read, Update, Delete)
* 📍 Location management
* 🏷️ Category management
* 🔄 Recurring events support
* 🔐 WordPress application password authentication
* 📝 Standard REST API conventions
* 🌐 WordPress Multisite compatible

## Requirements

* WordPress 6.0 or higher
* PHP 8.1 or higher
* Events Made Easy 3.2.26 with its matching database schema for recurrence creation

Older EME engines retain read/event endpoints but recurrence creation returns
HTTP 409 before any recurrence write. Existing event and read endpoints remain available.

Event and recurrence creation honor EME's configured add or edit-all capability.
Publishing requires its configured publish capability; other writers create drafts.
Event changes and recurrence deletion require the configured edit-all capability,
or the configured author capability with matching event ownership.

Public recurrence metadata contains its ID and duration only. Pattern bounds,
specific dates, exclusions and generation rules are available to staff readers;
anonymous clients use the instances endpoint for visible occurrence dates.

Run PHP contracts only in an isolated staging database. Use its configured
`/bBLOG/` fixture URL rather than a live domain alias. Invoke the actual test
file with `wp eval-file tests/test-upstream-compat.php`; piping that test through
stdin loses the adjacent `.upstream` metadata directory.

## Installation

### Method 1: From GitHub Release

1. Download the latest `eme-rest-api.zip` from [Releases](https://github.com/gserafini/eme-rest-api/releases)
2. In WordPress admin, go to Plugins → Add New → Upload Plugin
3. Choose the ZIP file and click "Install Now"
4. Activate the plugin
5. Ensure Events Made Easy is also installed and activated

### Method 2: Manual Installation

1. Clone this repository or download the source code
2. Upload the `eme-rest-api` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Ensure Events Made Easy is installed and activated

### Multisite Installation

The plugin works seamlessly with WordPress Multisite:

* **Network Activate:** Activates for all sites in the network
* **Per-Site Activate:** Can be activated individually per site
* **Table Prefixes:** Automatically handles site-specific table prefixes
* **REST API:** Each site has its own `/wp-json/eme/v1/` endpoints
* **Credentials:** Use application passwords from each site's admin

Example multisite usage:

```bash
# Site 1
curl https://site1.example.com/wp-json/eme/v1/eme_events \
  -u "site1admin:site1_app_password"

# Site 2
curl https://site2.example.com/wp-json/eme/v1/eme_events \
  -u "site2admin:site2_app_password"
```

## API Endpoints

Base URL: `https://yoursite.com/wp-json/eme/v1`

### Events

#### List Events

```http
GET /eme/v1/eme_events
```

Query parameters:

* `per_page` (int): Events per page (default: 10)
* `page` (int): Page number (default: 1)
* `scope` (string): Event scope - `future`, `past`, `today`, `month`, etc. (default: `future`)
* `start_date` (string): Filter events starting from this date (YYYY-MM-DD)
* `end_date` (string): Filter events ending before this date (YYYY-MM-DD)

**Note:** If `start_date` or `end_date` are provided, they take precedence over `scope`.

Examples:

```bash
# Get future events
curl https://yoursite.com/wp-json/eme/v1/eme_events?per_page=5&scope=future

# Get events in a specific date range
curl https://yoursite.com/wp-json/eme/v1/eme_events?start_date=2025-11-01&end_date=2025-11-30

# Get all events from a start date onwards
curl https://yoursite.com/wp-json/eme/v1/eme_events?start_date=2025-11-01

# Get all events before an end date
curl https://yoursite.com/wp-json/eme/v1/eme_events?end_date=2025-12-31
```

#### Get Single Event

```http
GET /eme/v1/eme_events/{id}
```

Example:

```bash
curl https://yoursite.com/wp-json/eme/v1/eme_events/123
```

#### Create Event

```http
POST /eme/v1/eme_events
```

Required authentication: Yes (application password or OAuth)

Request body:

```json
{
  "title": "Annual Meeting",
  "description": "Everyone is welcome to attend",
  "start_date": "2025-11-08 13:00:00",
  "end_date": "2025-11-08 16:00:00",
  "status": "published",
  "location_id": 5,
  "category_ids": [1, 3],
  "rsvp": true,
  "seats": 100,
  "price": "25.00",
  "currency": "USD"
}
```

Required fields:

* `title` (string)
* `start_date` (datetime: `YYYY-MM-DD HH:MM:SS`)

Optional fields:

* `description` (string)
* `end_date` (datetime, defaults to start_date)
* `status` (string: `published` or `draft`, default: `published`)
* `location_id` (int)
* `category_ids` (array of ints or comma-separated string)
* `rsvp` (boolean)
* `seats` (int)
* `price` (string)
* `currency` (string)

#### Update Event

```http
POST /eme/v1/eme_events/{id}
PUT /eme/v1/eme_events/{id}
PATCH /eme/v1/eme_events/{id}
```

Required authentication: Yes

Request body: Same as create, all fields optional

Example:

```bash
curl -X POST https://yoursite.com/wp-json/eme/v1/eme_events/123 \
  -u "username:application_password" \
  -H "Content-Type: application/json" \
  -d '{"title": "Updated Event Title", "seats": 150}'
```

#### Delete Event

```http
DELETE /eme/v1/eme_events/{id}
```

Required authentication: Yes

### Locations

#### List Locations

```http
GET /eme/v1/eme_locations
```

#### Get Single Location

```http
GET /eme/v1/eme_locations/{id}
```

#### Create Location

```http
POST /eme/v1/eme_locations
```

Request body:

```json
{
  "name": "Conference Center",
  "address": "123 Main St",
  "city": "San Francisco",
  "state": "CA",
  "zip": "94102",
  "country": "USA"
}
```

### Categories

#### List Categories

```http
GET /eme/v1/eme_categories
```

#### Create Category

```http
POST /eme/v1/eme_categories
```

Request body:

```json
{
  "name": "Workshops",
  "slug": "workshops"
}
```

### Recurring Events

#### List Recurring Events

```http
GET /eme/v1/eme_recurrences
```

Returns one real event for each recurrence pattern visible to the caller. Anonymous lists contain published events; editors can also inspect private patterns.

Example:

```bash
curl https://yoursite.com/wp-json/eme/v1/eme_recurrences
```

**Response:** Array of representative event objects. `id` is the stored event ID; `recurrence_id` is its nonzero pattern ID. Use the pattern ID for recurrence detail and instance endpoints.

#### Create Recurring Event

```http
POST /eme/v1/eme_recurrences
```

Request body for weekly recurrence:

```json
{
  "title": "Weekly Team Meeting",
  "status": "draft",
  "recurrence": {
    "frequency": "weekly",
    "days_of_week": [1, 3, 5],
    "start_date": "2030-01-01",
    "end_date": "2030-03-31",
    "start_time": "09:00:00",
    "duration": 3600,
    "interval": 1,
    "exclude_days": ["2030-01-02"]
  }
}
```

Request body for monthly recurrence:

```json
{
  "title": "Monthly Board Meeting",
  "status": "draft",
  "recurrence": {
    "frequency": "monthly",
    "day_of_week": "monday",
    "week_of_month": 2,
    "start_date": "2030-01-01",
    "end_date": "2030-03-31",
    "start_time": "14:00:00",
    "duration": 7200
  }
}
```

The legacy flat request form is also accepted. Weekdays accept names or numbers
1–7 (Monday–Sunday); 0 also means Sunday. Monthly patterns use a weekday and
week 1–5, or -1 for the last matching weekday. Specific patterns use
`frequency: "specific"` and a `dates` array. Interval is an integer from 1 to 127.

Duration is elapsed seconds. The response adds `duration_days` for EME's native
inclusive day count. Nonexistent local times and series whose fixed local times
cannot preserve that duration across daylight saving return 400 before writing;
split those series or choose different times. Native EME omits past occurrences
for weekly/monthly patterns and retains explicitly requested specific dates.

Success requires every planned occurrence to be read back with its requested
date and times. A failed save returns 500 and invokes native recurrence cleanup;
saved events move to trash. Native hooks may already have run: this does not
promise transactional rollback of email or third-party effects. Recurrence
deletion likewise follows native EME's event, booking and task cleanup policy.

#### Get Recurrence Instances

```http
GET /eme/v1/eme_recurrences/{id}/instances
```

Query parameters:

* `start_date` (optional): Start date for instances
* `end_date` (optional): End date for instances

## Authentication

Use WordPress Application Passwords for authentication:

1. In WordPress admin, go to Users → Profile
2. Scroll to "Application Passwords"
3. Create a new application password
4. Use it with your username in API requests

Example:

```bash
curl https://yoursite.com/wp-json/eme/v1/eme_events \
  -u "username:application_password"
```

## Why `eme_` Prefix?

Endpoints use the `eme_` prefix to avoid conflicts with Events Made Easy's custom post types. This ensures the REST API routes take precedence over WordPress's default post type routing.

## Development

### File Structure

```text
eme-rest-api/
├── eme-rest-api.php    # Main plugin file
├── readme.txt          # WordPress.org format
└── README.md           # This file
```

### Building

To create a distribution ZIP:

```bash
./create-zip.sh
```

This creates `eme-rest-api.zip` with only the essential plugin files.

## Contributing

Contributions are welcome! Please feel free to:

* Report issues on [GitHub Issues](https://github.com/gserafini/eme-rest-api/issues)
* Submit pull requests
* Suggest new features
* Improve documentation

## Support

* **Plugin Issues:** [GitHub Issues](https://github.com/gserafini/eme-rest-api/issues)
* **Events Made Easy:** [EME GitHub](https://github.com/liedekef/events-made-easy)

## Changelog

### v1.9.2 - 2026-10-22

* Anonymous reads conceal draft, private, trash and front-end-submitted draft events and patterns. Published events and direct unlisted links retain their existing visibility.
* Recurrence lists identify native patterns correctly. Instance reads use stored occurrences and the requested date window, preserving native intervals, exclusions and actual times.
* Recurrence creation remains unchanged in this security maintenance release; its native-engine rewrite is qualified separately.
* Real WordPress API tests cover both active legacy EME sites and the isolated 3.2.26 upgrade candidate. `tests/test-visibility.php` creates and cleans fixtures only in the guarded socket-only staging environment; never run it against production.

### v1.9.0 - 2025-11-12

* **MAJOR FIX:** Complete rewrite of `eme_get_events()` call to use positional parameters
* API now returns ALL events matching criteria (like calendar frontend does)
* Fixed default `per_page` from 10 to 0 (unlimited) to match calendar behavior
* Added `show_ongoing=1` by default for multi-day events
* Removed `array` parameter which wasn't recognized by EME
* API now properly returns recurring event instances
* This matches how EME's calendar frontend queries events

### v1.8.2 - 2025-11-12

* **CRITICAL FIX:** Date range separator changed from comma to double-dash
* Fixed date range queries to use EME's expected format (`YYYY-MM-DD--YYYY-MM-DD`)
* This fix resolves the issue where API was only returning 1 event for date ranges

### v1.8.1 - 2025-11-12

* Added passthrough for EME query parameters (`recurrences`, `recurring`, `month`, `year`, etc.)
* Added boolean conversion for `recurring`/`recurrences` parameters
* Added debug response headers (`X-EME-Query-Args`, `X-EME-Event-Count`)

### v1.8.0 - 2025-11-12

* **NEW:** Date range query parameters for events endpoint
  * Added `start_date` and `end_date` parameters to `GET /eme_events`
  * Supports filtering events by date range (YYYY-MM-DD format)
  * Date parameters take precedence over `scope` parameter
* **NEW:** List recurring events endpoint
  * Added `GET /eme_recurrences` to list all recurring event templates
  * Returns parent events that have recurring instances
* **FIXED:** Events API now properly returns all events including recurring instances
  * Previously only returned limited results due to parameter handling
  * Now correctly passes date range to EME's query functions

### v1.6.2 - 2025-10-30

* Fixed markdownlint issues in documentation
* Improved code formatting and documentation
* Clean repository structure

### v1.6.0 - 2025-10-30

* **BREAKING:** Renamed all endpoints to use `eme_` prefix to avoid conflicts
  * `/events` → `/eme_events`
  * `/locations` → `/eme_locations`
  * `/categories` → `/eme_categories`
  * `/recurrences` → `/eme_recurrences`
* Fixed WordPress routing conflicts with EME custom post types
* Switched to using EME's built-in functions for better reliability

### v1.1.0 - 2025-10-29

* **NEW:** Recurring events support
  * Create weekly, monthly, or specific-date recurring events
  * Get recurrence instances
  * Update and delete recurrences
* Enhanced event formatting with proper URL generation
* Added multisite compatibility
* Improved error handling

### v1.0.0 - 2025-10-29

* Initial release
* Full CRUD operations for events
* Location management
* Category management
* WordPress application password authentication

## License

This plugin is licensed under the [GNU General Public License v2.0 or later](https://www.gnu.org/licenses/gpl-2.0.html).

## Credits

Created by [Gabriel Serafini](https://gabrielserafini.com) to enable REST API access to Events Made Easy.
