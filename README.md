=== Events Made Easy REST API ===
Contributors: gabrielserafini
Tags: events, rest-api, events-made-easy, api, calendar
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.6.2
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

REST API endpoints for Events Made Easy plugin - CRUD operations for events, locations, categories, and recurring events.

== Description ==

A WordPress plugin that adds REST API endpoints to the [Events Made Easy](https://github.com/liedekef/events-made-easy) plugin.

Events Made Easy (EME) uses custom database tables instead of WordPress custom post types, so it doesn't expose events via the WordPress REST API by default. This plugin bridges that gap by providing a clean REST API interface to EME's functionality.

== Features ==

* Full CRUD operations for events (Create, Read, Update, Delete)
* Location management
* Category management
* Recurring events support
* WordPress application password authentication
* Standard REST API conventions
* WordPress Multisite compatible

== Requirements ==

* WordPress 5.0 or higher
* PHP 7.2 or higher
* Events Made Easy plugin 2.0+ installed and activated

== Installation ==

### Multisite Notes

The plugin works seamlessly with WordPress Multisite:

- **Network Activate:** Activates for all sites in the network
- **Per-Site Activate:** Can be activated individually per site
- **Table Prefixes:** Automatically handles site-specific table prefixes
- **REST API:** Each site has its own `/wp-json/eme/v1/` endpoints
- **Credentials:** Use application passwords from each site's admin

**Example multisite usage:**
```bash
# Site 1
curl https://site1.example.com/wp-json/eme/v1/eme_events \
  -u "site1admin:site1_app_password"

# Site 2
curl https://site2.example.com/wp-json/eme/v1/eme_events \
  -u "site2admin:site2_app_password"
```

## Installation

### Method 1: Manual Upload

1. Download or clone this repository
2. Upload the `eme-rest-api` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Ensure Events Made Easy is installed and activated

### Method 2: ZIP Upload

1. Create a ZIP file of the `eme-rest-api` folder
2. In WordPress admin, go to Plugins → Add New → Upload Plugin
3. Choose the ZIP file and click "Install Now"
4. Activate the plugin

## API Endpoints

Base URL: `https://yoursite.com/wp-json/eme/v1`

**NEW in v1.1.0:** Recurring events support!

### Events

#### List Events
```http
GET /eme/v1/eme_events
```

Query parameters:
- `per_page` (int): Events per page (default: 10)
- `page` (int): Page number (default: 1)
- `scope` (string): Event scope - `future`, `past`, `all` (default: `future`)

Example:
```bash
curl https://yoursite.com/wp-json/eme/v1/eme_events?per_page=5&scope=future
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
  "currency": "USD",
  "image_id": 456,
  "url": "https://example.com/event-info"
}
```

Required fields:
- `title` (string)
- `start_date` (datetime: `YYYY-MM-DD HH:MM:SS`)

Optional fields:
- `description` (string)
- `end_date` (datetime, defaults to start_date)
- `status` (string: `published` or `draft`, default: `published`)
- `location_id` (int)
- `category_ids` (array of ints or comma-separated string)
- `rsvp` (boolean)
- `seats` (int)
- `price` (string)
- `currency` (string)
- `image_id` (int - WordPress media library attachment ID)
- `contact_person_id` (int)
- `url` (string)

Example:
```bash
curl -X POST https://yoursite.com/wp-json/eme/v1/eme_events \
  -u "username:application_password" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Annual Meeting",
    "description": "Everyone welcome",
    "start_date": "2025-11-08 13:00:00",
    "end_date": "2025-11-08 16:00:00",
    "location_id": 5
  }'
```

Response:
```json
{
  "success": true,
  "event_id": 123,
  "event": {
    "id": 123,
    "title": "Annual Meeting",
    "description": "Everyone welcome",
    "start_date": "2025-11-08 13:00:00",
    "end_date": "2025-11-08 16:00:00",
    "status": "published",
    "slug": "annual-meeting",
    "url": "https://yoursite.com/events/annual-meeting",
    "location_id": 5,
    "location": {
      "id": 5,
      "name": "Third Church",
      "address": "11914 Rustic Lane",
      "city": "San Antonio",
      "state": "Texas",
      "zip": "78230",
      "country": ""
    },
    "category_ids": [],
    "rsvp_enabled": false,
    "seats": "",
    "price": "",
    "currency": "",
    "image_id": 0,
    "image_url": "",
    "contact_person_id": 0,
    "created_date": "2025-10-29 13:30:00",
    "modified_date": "2025-10-29 13:30:00"
  },
  "message": "Event created successfully"
}
```

#### Update Event
```http
PUT /eme/v1/eme_events/{id}
```

Required authentication: Yes

Request body: Same as create, all fields optional

Example:
```bash
curl -X PUT https://yoursite.com/wp-json/eme/v1/eme_events/123 \
  -u "username:application_password" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Updated Event Title",
    "seats": 150
  }'
```

#### Delete Event
```http
DELETE /eme/v1/eme_events/{id}
```

Required authentication: Yes

Example:
```bash
curl -X DELETE https://yoursite.com/wp-json/eme/v1/eme_events/123 \
  -u "username:application_password"
```

### Locations

#### List Locations
```http
GET /eme/v1/eme_locations
```

Example:
```bash
curl https://yoursite.com/wp-json/eme/v1/eme_locations
```

#### Get Single Location
```http
GET /eme/v1/eme_locations/{id}
```

Example:
```bash
curl https://yoursite.com/wp-json/eme/v1/eme_locations/5
```

#### Create Location
```http
POST /eme/v1/eme_locations
```

Required authentication: Yes

Request body:
```json
{
  "name": "Third Church of Christ, Scientist",
  "address": "11914 Rustic Lane",
  "city": "San Antonio",
  "state": "Texas",
  "zip": "78230",
  "country": "USA"
}
```

Required fields:
- `name` (string)

Optional fields:
- `address` (string)
- `city` (string)
- `state` (string)
- `zip` (string)
- `country` (string)

Example:
```bash
curl -X POST https://yoursite.com/wp-json/eme/v1/eme_locations \
  -u "username:application_password" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Third Church",
    "address": "11914 Rustic Lane",
    "city": "San Antonio",
    "state": "Texas",
    "zip": "78230"
  }'
```

### Categories

#### List Categories
```http
GET /eme/v1/eme_categories
```

Example:
```bash
curl https://yoursite.com/wp-json/eme/v1/eme_categories
```

#### Create Category
```http
POST /eme/v1/eme_categories
```

Required authentication: Yes

Request body:
```json
{
  "name": "Mobile Reading Room",
  "slug": "mobile-reading-room"
}
```

Required fields:
- `name` (string)

Optional fields:
- `slug` (string, auto-generated from name if not provided)

Example:
```bash
curl -X POST https://yoursite.com/wp-json/eme/v1/eme_categories \
  -u "username:application_password" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Institutional Committee"
  }'
```

### Recurrences (NEW in v1.1.0)

Create recurring events with flexible patterns.

#### Create Recurring Event
```http
POST /eme/v1/eme_recurrences
```

Required authentication: Yes

**Weekly Recurrence Example:**
```bash
curl -X POST https://yoursite.com/wp-json/eme/v1/eme_recurrences \
  -u "username:application_password" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Weekly Team Meeting",
    "description": "Recurring team sync every Mon/Wed/Fri",
    "location_id": 5,
    "recurrence": {
      "frequency": "weekly",
      "interval": 1,
      "start_date": "2025-11-01",
      "end_date": "2025-12-31",
      "days_of_week": ["monday", "wednesday", "friday"],
      "duration": 3600
    }
  }'
```

**Monthly Recurrence Example (2nd Tuesday of every month):**
```bash
curl -X POST https://yoursite.com/wp-json/eme/v1/eme_recurrences \
  -u "username:application_password" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Monthly Board Meeting",
    "description": "Second Tuesday of each month",
    "location_id": 5,
    "recurrence": {
      "frequency": "monthly",
      "interval": 1,
      "start_date": "2025-11-01",
      "end_date": "2026-11-01",
      "week_of_month": 2,
      "day_of_week": "tuesday",
      "duration": 7200
    }
  }'
```

**Specific Dates Example:**
```bash
curl -X POST https://yoursite.com/wp-json/eme/v1/eme_recurrences \
  -u "username:application_password" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Special Events",
    "description": "Events on specific dates",
    "recurrence": {
      "frequency": "specific",
      "dates": ["2025-11-01", "2025-11-15", "2025-12-01"],
      "duration": 3600
    }
  }'
```

**Recurrence Parameters:**

Required fields:
- `title` (string) - Event title
- `recurrence` (object) - Recurrence pattern

Recurrence pattern fields:

For **weekly**:
- `frequency`: "weekly"
- `start_date`: Start date (YYYY-MM-DD)
- `end_date`: End date (YYYY-MM-DD)
- `days_of_week`: Array of day names ["monday", "tuesday", ...]
- `interval`: Number of weeks between occurrences (default: 1)
- `duration`: Duration in seconds (default: 3600)
- `exclude_days`: Optional array of dates to skip

For **monthly**:
- `frequency`: "monthly"
- `start_date`: Start date
- `end_date`: End date
- `week_of_month`: Week number (1-5, or -1 for last week)
- `day_of_week`: Day name ("monday", "tuesday", etc.)
- `interval`: Number of months between occurrences (default: 1)
- `duration`: Duration in seconds

For **specific**:
- `frequency`: "specific"
- `dates`: Array of specific dates ["2025-11-01", "2025-11-15", ...]
- `duration`: Duration in seconds

Response:
```json
{
  "success": true,
  "event_id": 123,
  "recurrence_id": 45,
  "recurrence_pattern": {
    "id": 45,
    "frequency": "weekly",
    "interval": 1,
    "duration": 3600,
    "start_date": "2025-11-01",
    "end_date": "2025-12-31",
    "days_of_week_codes": "1,3,5"
  },
  "message": "Recurring event created successfully"
}
```

#### Get Recurrence Pattern
```http
GET /eme/v1/eme_recurrences/{id}
```

Example:
```bash
curl https://yoursite.com/wp-json/eme/v1/eme_recurrences/45
```

#### Get Recurrence Instances
Calculate all event instances for a recurrence pattern:

```http
GET /eme/v1/eme_recurrences/{id}/instances
```

Query parameters:
- `start_date` (optional): Start of date range (default: today)
- `end_date` (optional): End of date range (default: +1 year)

Example:
```bash
curl "https://yoursite.com/wp-json/eme/v1/eme_recurrences/45/instances?start_date=2025-11-01&end_date=2025-11-30"
```

Response:
```json
{
  "recurrence_id": 45,
  "pattern": {
    "frequency": "weekly",
    "days_of_week_codes": "1,3,5"
  },
  "instances": [
    {
      "start": "2025-11-01 10:00:00",
      "end": "2025-11-01 11:00:00"
    },
    {
      "start": "2025-11-03 10:00:00",
      "end": "2025-11-03 11:00:00"
    }
  ],
  "count": 13
}
```

#### Delete Recurrence
```http
DELETE /eme/v1/eme_recurrences/{id}
```

Required authentication: Yes

**Warning:** This deletes the recurrence pattern AND all associated events.

Example:
```bash
curl -X DELETE https://yoursite.com/wp-json/eme/v1/eme_recurrences/45 \
  -u "username:application_password"
```

## Authentication

This plugin uses WordPress's built-in authentication system. You can authenticate using:

### Application Passwords (Recommended)

1. In WordPress admin, go to Users → Profile
2. Scroll to "Application Passwords"
3. Enter a name (e.g., "EME REST API")
4. Click "Add New Application Password"
5. Copy the generated password (format: `xxxx xxxx xxxx xxxx xxxx xxxx`)
6. Use with Basic Auth in requests:

```bash
curl -u "username:xxxx xxxx xxxx xxxx xxxx xxxx" \
  https://yoursite.com/wp-json/eme/v1/eme_events
```

### OAuth or other methods

Any authentication method that sets `current_user_can('edit_posts')` will work.

## Permissions

- **Read operations** (GET): Public (no authentication required)
  - Can be restricted by modifying `eme_rest_read_permission()` function
- **Write operations** (POST, PUT, DELETE): Requires `edit_posts` capability

## Error Responses

The API returns standard HTTP status codes:

- `200` - Success
- `400` - Bad request (missing required fields)
- `401` - Unauthorized (authentication required)
- `403` - Forbidden (insufficient permissions)
- `404` - Not found
- `500` - Server error

Error response format:
```json
{
  "code": "missing_title",
  "message": "Event title is required",
  "data": {
    "status": 400
  }
}
```

## Examples

### Complete Workflow: Create Event with New Location

1. Create location:
```bash
LOCATION_RESPONSE=$(curl -s -X POST https://yoursite.com/wp-json/eme/v1/eme_locations \
  -u "username:app_password" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Third Church",
    "address": "11914 Rustic Lane",
    "city": "San Antonio",
    "state": "Texas",
    "zip": "78230"
  }')

LOCATION_ID=$(echo $LOCATION_RESPONSE | jq -r '.location_id')
```

2. Create event with location:
```bash
curl -X POST https://yoursite.com/wp-json/eme/v1/eme_events \
  -u "username:app_password" \
  -H "Content-Type: application/json" \
  -d "{
    \"title\": \"Annual Meeting\",
    \"description\": \"Everyone is welcome\",
    \"start_date\": \"2025-11-08 13:00:00\",
    \"end_date\": \"2025-11-08 16:00:00\",
    \"location_id\": $LOCATION_ID,
    \"rsvp\": true,
    \"seats\": 100
  }"
```

### PHP Example

```php
$api_url = 'https://yoursite.com/wp-json/eme/v1/eme_events';
$username = 'admin';
$app_password = 'xxxx xxxx xxxx xxxx xxxx xxxx';

$event_data = [
    'title' => 'Annual Meeting',
    'description' => 'Everyone is welcome',
    'start_date' => '2025-11-08 13:00:00',
    'end_date' => '2025-11-08 16:00:00',
    'location_id' => 5,
];

$response = wp_remote_post($api_url, [
    'headers' => [
        'Authorization' => 'Basic ' . base64_encode("$username:$app_password"),
        'Content-Type' => 'application/json',
    ],
    'body' => json_encode($event_data),
]);

if (!is_wp_error($response)) {
    $result = json_decode(wp_remote_retrieve_body($response), true);
    echo "Event created with ID: " . $result['event_id'];
}
```

### JavaScript Example

```javascript
const apiUrl = 'https://yoursite.com/wp-json/eme/v1/eme_events';
const auth = btoa('username:app_password');

const eventData = {
  title: 'Annual Meeting',
  description: 'Everyone is welcome',
  start_date: '2025-11-08 13:00:00',
  end_date: '2025-11-08 16:00:00',
  location_id: 5
};

fetch(apiUrl, {
  method: 'POST',
  headers: {
    'Authorization': `Basic ${auth}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify(eventData)
})
.then(response => response.json())
.then(data => console.log('Event created:', data.event_id));
```

## Development

### File Structure

```
eme-rest-api/
├── eme-rest-api.php    # Main plugin file
└── README.md           # This file
```

### Contributing

This plugin was created to enable REST API access to Events Made Easy. Feel free to:

- Report issues
- Submit pull requests
- Suggest features
- Share improvements

### Testing

Test the API with curl:

```bash
# List events
curl https://yoursite.com/wp-json/eme/v1/eme_events

# Create test event (requires auth)
curl -X POST https://yoursite.com/wp-json/eme/v1/eme_events \
  -u "username:app_password" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Test Event",
    "start_date": "2025-12-01 10:00:00",
    "status": "draft"
  }'
```

Or use a REST API client like:
- Postman
- Insomnia
- Thunder Client (VS Code extension)

## License

GPL v2 or later

## Support

For issues related to:
- **This plugin**: Open an issue in the repository
- **Events Made Easy**: Visit [EME support](https://www.e-dynamics.be/wordpress/)

## Changelog

### 1.1.0 - 2025-10-29
- **NEW:** Recurring events support
  - Weekly recurrence patterns
  - Monthly recurrence patterns (by week/day of month)
  - Specific dates recurrence
  - Exclude days feature
  - Calculate instances endpoint
  - Full CRUD for recurrence patterns
- **NEW:** WordPress Multisite support
  - Network activation capability (`Network: true` header)
  - Automatic site-specific table prefix handling
  - Per-site REST API endpoints
  - Site-isolated data and credentials
  - Comprehensive multisite documentation
- Validation for recurrence patterns
- Comprehensive recurrence documentation

### 1.0.0 - 2025-10-29
- Initial release
- Event CRUD operations
- Location management
- Category management
- WordPress authentication integration
