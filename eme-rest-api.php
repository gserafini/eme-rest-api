<?php
/**
 * Plugin Name: Events Made Easy REST API
 * Plugin URI: https://github.com/gserafini/eme-rest-api
 * Description: REST API endpoints for Events Made Easy plugin including recurring events support
 * Version: 1.6.2
 * Author: Gabriel Serafini
 * Author URI: https://gabrielserafini.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * Text Domain: eme-rest-api
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check if Events Made Easy is active
function eme_rest_api_check_dependencies() {
    if (!function_exists('eme_new_event')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p><strong>EME REST API:</strong> Events Made Easy plugin must be installed and activated.</p></div>';
        });
        return false;
    }
    return true;
}
add_action('plugins_loaded', 'eme_rest_api_check_dependencies');

// Plugin activation hook - flush permalinks to register new routes
function eme_rest_api_activate() {
    // Flush rewrite rules to ensure REST API routes are properly registered
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'eme_rest_api_activate');

// Disable canonical redirects for REST API requests
// This prevents WordPress from adding trailing slashes which breaks REST API POST requests
add_filter('redirect_canonical', 'eme_rest_api_disable_canonical_redirect', 10, 2);
function eme_rest_api_disable_canonical_redirect($redirect_url, $requested_url) {
    // If this is a REST API request, don't redirect
    if (strpos($requested_url, '/wp-json/') !== false || strpos($requested_url, rest_get_url_prefix()) !== false) {
        return false;
    }
    return $redirect_url;
}

// Register REST API routes with high priority to prevent conflicts with EME post types
add_action('rest_api_init', function() {
    if (!function_exists('eme_new_event')) {
        return; // EME not active
    }

    $namespace = 'eme/v1';

    // Event endpoints (renamed to /eme_events to avoid conflict with EME custom post types)
    register_rest_route($namespace, '/eme_events', [
        [
            'methods' => 'GET',
            'callback' => 'eme_rest_get_events',
            'permission_callback' => 'eme_rest_read_permission',
        ],
        [
            'methods' => 'POST',
            'callback' => 'eme_rest_create_event',
            'permission_callback' => 'eme_rest_write_permission',
        ],
    ]);

    register_rest_route($namespace, '/eme_events/(?P<id>\d+)', [
        [
            'methods' => 'GET',
            'callback' => 'eme_rest_get_event',
            'permission_callback' => 'eme_rest_read_permission',
        ],
        [
            'methods' => ['POST', 'PUT', 'PATCH'],
            'callback' => 'eme_rest_update_event',
            'permission_callback' => 'eme_rest_write_permission',
        ],
        [
            'methods' => 'DELETE',
            'callback' => 'eme_rest_delete_event',
            'permission_callback' => 'eme_rest_write_permission',
        ],
    ]);

    // Location endpoints (renamed to /eme_locations to avoid conflict with EME custom post types)
    register_rest_route($namespace, '/eme_locations', [
        [
            'methods' => 'GET',
            'callback' => 'eme_rest_get_locations',
            'permission_callback' => 'eme_rest_read_permission',
        ],
        [
            'methods' => 'POST',
            'callback' => 'eme_rest_create_location',
            'permission_callback' => 'eme_rest_write_permission',
        ],
    ]);

    register_rest_route($namespace, '/eme_locations/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'eme_rest_get_location',
        'permission_callback' => 'eme_rest_read_permission',
    ]);

    // Category endpoints (renamed to /eme_categories to avoid conflict with EME custom post types)
    register_rest_route($namespace, '/eme_categories', [
        [
            'methods' => 'GET',
            'callback' => 'eme_rest_get_categories',
            'permission_callback' => 'eme_rest_read_permission',
        ],
        [
            'methods' => 'POST',
            'callback' => 'eme_rest_create_category',
            'permission_callback' => 'eme_rest_write_permission',
        ],
    ]);

    // Recurrence endpoints (renamed to /eme_recurrences to avoid conflict with EME custom post types)
    register_rest_route($namespace, '/eme_recurrences', [
        [
            'methods' => 'POST',
            'callback' => 'eme_rest_create_recurrence',
            'permission_callback' => 'eme_rest_write_permission',
        ],
    ]);

    register_rest_route($namespace, '/eme_recurrences/(?P<id>\d+)', [
        [
            'methods' => 'GET',
            'callback' => 'eme_rest_get_recurrence',
            'permission_callback' => 'eme_rest_read_permission',
        ],
        [
            'methods' => 'DELETE',
            'callback' => 'eme_rest_delete_recurrence',
            'permission_callback' => 'eme_rest_write_permission',
        ],
    ]);

    register_rest_route($namespace, '/eme_recurrences/(?P<id>\d+)/instances', [
        'methods' => 'GET',
        'callback' => 'eme_rest_get_recurrence_instances',
        'permission_callback' => 'eme_rest_read_permission',
    ]);
});

// Permission callbacks
function eme_rest_read_permission() {
    // Allow reading for anyone (can be restricted if needed)
    return true;
}

function eme_rest_write_permission() {
    // Require authentication and edit_posts capability
    return current_user_can('edit_posts');
}

// Helper function to map status strings to EME status codes
function eme_rest_map_status($status_string) {
    // EME status constants:
    // EME_EVENT_STATUS_TRASH = 0
    // EME_EVENT_STATUS_PUBLIC = 1
    // EME_EVENT_STATUS_PRIVATE = 2
    // EME_EVENT_STATUS_UNLISTED = 3
    // EME_EVENT_STATUS_DRAFT = 5
    // EME_EVENT_STATUS_FS_DRAFT = 6

    $status_map = [
        'trash' => 0,
        'published' => 1,
        'public' => 1,
        'private' => 2,
        'unlisted' => 3,
        'draft' => 5,
        'fs_draft' => 6,
    ];

    $normalized_status = strtolower(trim($status_string));
    return isset($status_map[$normalized_status]) ? $status_map[$normalized_status] : 5; // default to draft
}

// Helper function to map EME status codes to readable strings
function eme_rest_format_status($status_code) {
    $status_names = [
        0 => 'trash',
        1 => 'published',
        2 => 'private',
        3 => 'unlisted',
        5 => 'draft',
        6 => 'fs_draft',
    ];

    return isset($status_names[$status_code]) ? $status_names[$status_code] : 'unknown';
}

// Event endpoints
function eme_rest_get_events($request) {
    $params = $request->get_params();

    // Default parameters
    $limit = isset($params['per_page']) ? intval($params['per_page']) : 10;
    $offset = isset($params['page']) ? (intval($params['page']) - 1) * $limit : 0;
    $scope = isset($params['scope']) ? sanitize_text_field($params['scope']) : 'future';

    // Get events using EME function
    $events = eme_get_events([
        'scope' => $scope,
        'limit' => $limit,
        'offset' => $offset,
    ]);

    if (empty($events)) {
        return rest_ensure_response([]);
    }

    // Format events for REST response
    $formatted_events = array_map('eme_rest_format_event', $events);

    return rest_ensure_response($formatted_events);
}

function eme_rest_get_event($request) {
    $event_id = intval($request['id']);

    // Use EME's built-in function to get event
    $event = eme_get_event($event_id);

    if (!$event) {
        return new WP_Error('not_found', 'Event not found', ['status' => 404]);
    }

    return rest_ensure_response(eme_rest_format_event($event));
}

function eme_rest_create_event($request) {
    $params = $request->get_json_params();

    // Validate required fields
    if (empty($params['title'])) {
        return new WP_Error('missing_title', 'Event title is required', ['status' => 400]);
    }
    if (empty($params['start_date'])) {
        return new WP_Error('missing_start_date', 'Event start date is required', ['status' => 400]);
    }

    // Check if EME functions are available
    if (!function_exists('eme_new_event') || !function_exists('eme_db_insert_event')) {
        return new WP_Error('eme_not_available', 'Events Made Easy plugin functions not available', ['status' => 500]);
    }

    // Use EME's native template function
    $event = eme_new_event();

    // Map REST API parameters to EME structure
    $event['event_name'] = sanitize_text_field($params['title']);
    $event['event_notes'] = isset($params['description']) ? wp_kses_post($params['description']) : '';

    // EME can handle both full datetime or separate date/time
    // Priority: full datetime if provided, otherwise separate fields
    if (isset($params['start_datetime'])) {
        $event['event_start'] = sanitize_text_field($params['start_datetime']);
    } else {
        $start_time = isset($params['start_time']) ? sanitize_text_field($params['start_time']) : '00:00:00';
        $event['event_start'] = sanitize_text_field($params['start_date']) . ' ' . $start_time;
    }

    if (isset($params['end_datetime'])) {
        $event['event_end'] = sanitize_text_field($params['end_datetime']);
    } else {
        $end_date = isset($params['end_date']) ? sanitize_text_field($params['end_date']) : sanitize_text_field($params['start_date']);
        $end_time = isset($params['end_time']) ? sanitize_text_field($params['end_time']) : '23:59:59';
        $event['event_end'] = $end_date . ' ' . $end_time;
    }

    // Set event status (defaults to draft if not specified)
    $event['event_status'] = isset($params['status']) ? eme_rest_map_status($params['status']) : 5;

    // Optional fields
    if (isset($params['location_id'])) {
        $event['location_id'] = intval($params['location_id']);
    }

    if (isset($params['category_ids'])) {
        $event['event_category_ids'] = is_array($params['category_ids'])
            ? implode(',', array_map('intval', $params['category_ids']))
            : sanitize_text_field($params['category_ids']);
    }

    if (isset($params['rsvp'])) {
        $event['event_rsvp'] = (bool)$params['rsvp'] ? 1 : 0;
    }

    if (isset($params['seats'])) {
        $event['event_seats'] = intval($params['seats']);
    }

    if (isset($params['price'])) {
        $event['price'] = sanitize_text_field($params['price']);
    }

    if (isset($params['currency'])) {
        $event['currency'] = sanitize_text_field($params['currency']);
    }

    if (isset($params['image_id'])) {
        $event['event_image_id'] = intval($params['image_id']);
    }

    if (isset($params['contact_person_id'])) {
        $event['event_contactperson_id'] = intval($params['contact_person_id']);
    }

    if (isset($params['url'])) {
        $event['event_url'] = esc_url_raw($params['url']);
    }

    // Use EME's native insert function
    $event_id = eme_db_insert_event($event);

    if (!$event_id) {
        return new WP_Error('creation_failed', 'Failed to create event', ['status' => 500]);
    }

    // Get the created event using EME's function
    if (function_exists('eme_get_event')) {
        $created_event = eme_get_event($event_id);
    } else {
        $created_event = ['event_id' => $event_id];
    }

    return rest_ensure_response([
        'success' => true,
        'event_id' => $event_id,
        'event' => eme_rest_format_event($created_event),
        'message' => 'Event created successfully',
    ]);
}

function eme_rest_update_event($request) {
    $event_id = intval($request['id']);
    $params = $request->get_json_params();

    // Get existing event using EME's built-in function
    $event = eme_get_event($event_id);

    if (!$event) {
        return new WP_Error('not_found', 'Event not found', ['status' => 404]);
    }

    // Update event fields with provided parameters
    if (isset($params['title'])) {
        $event['event_name'] = sanitize_text_field($params['title']);
    }
    if (isset($params['description'])) {
        $event['event_notes'] = wp_kses_post($params['description']);
    }
    if (isset($params['start_date'])) {
        $event['event_start'] = sanitize_text_field($params['start_date']);
    }
    if (isset($params['end_date'])) {
        $event['event_end'] = sanitize_text_field($params['end_date']);
    }
    if (isset($params['status'])) {
        $event['event_status'] = eme_rest_map_status($params['status']);
    }
    if (isset($params['location_id'])) {
        $event['location_id'] = intval($params['location_id']);
    }
    if (isset($params['category_ids'])) {
        $event['event_category_ids'] = is_array($params['category_ids'])
            ? implode(',', array_map('intval', $params['category_ids']))
            : sanitize_text_field($params['category_ids']);
    }
    if (isset($params['rsvp'])) {
        $event['event_rsvp'] = (bool)$params['rsvp'] ? 1 : 0;
    }
    if (isset($params['seats'])) {
        $event['event_seats'] = intval($params['seats']);
    }
    if (isset($params['price'])) {
        $event['price'] = sanitize_text_field($params['price']);
    }
    if (isset($params['currency'])) {
        $event['currency'] = sanitize_text_field($params['currency']);
    }
    if (isset($params['image_id'])) {
        $event['event_image_id'] = intval($params['image_id']);
    }
    if (isset($params['contact_person_id'])) {
        $event['event_contactperson_id'] = intval($params['contact_person_id']);
    }

    // Update event using EME's built-in function
    $result = eme_db_update_event($event, $event_id);

    if (!$result) {
        return new WP_Error('update_failed', 'Failed to update event', ['status' => 500]);
    }

    // Get updated event
    $updated_event = eme_get_event($event_id);

    return rest_ensure_response([
        'success' => true,
        'event' => eme_rest_format_event($updated_event),
        'message' => 'Event updated successfully',
    ]);
}

function eme_rest_delete_event($request) {
    $event_id = intval($request['id']);

    // Check if event exists
    $event = eme_get_event($event_id);
    if (!$event) {
        return new WP_Error('not_found', 'Event not found', ['status' => 404]);
    }

    // Delete event
    $success = eme_db_delete_event($event_id);

    if (!$success) {
        return new WP_Error('deletion_failed', 'Failed to delete event', ['status' => 500]);
    }

    return rest_ensure_response([
        'success' => true,
        'message' => 'Event deleted successfully',
    ]);
}

function eme_rest_change_status($request) {
    $event_id = intval($request['id']);
    $params = $request->get_json_params();

    // Validate status parameter
    if (!isset($params['status'])) {
        return new WP_Error('missing_status', 'Status parameter is required', ['status' => 400]);
    }

    // Get existing event using EME's built-in function
    $event = eme_get_event($event_id);

    if (!$event) {
        return new WP_Error('not_found', 'Event not found', ['status' => 404]);
    }

    // Map status string to EME status code
    $new_status = eme_rest_map_status($params['status']);

    // Validate status code (must be 0-6)
    $valid_statuses = [0, 1, 2, 3, 5, 6]; // trash, public, private, unlisted, draft, fs_draft
    if (!in_array($new_status, $valid_statuses)) {
        return new WP_Error('invalid_status', 'Invalid status code', ['status' => 400]);
    }

    // Update status using EME's built-in function
    eme_change_event_status($event_id, $new_status);

    // Get updated event
    $updated_event = eme_get_event($event_id);

    return rest_ensure_response([
        'success' => true,
        'event' => eme_rest_format_event($updated_event),
        'previous_status' => eme_rest_format_status($event['event_status']),
        'new_status' => eme_rest_format_status($new_status),
        'message' => sprintf('Event status changed from %s to %s', eme_rest_format_status($event['event_status']), eme_rest_format_status($new_status)),
    ]);
}

function eme_rest_publish_event($request) {
    $event_id = intval($request['id']);

    // Get existing event using EME's built-in function
    $event = eme_get_event($event_id);

    if (!$event) {
        return new WP_Error('not_found', 'Event not found', ['status' => 404]);
    }

    // Update status to published using EME's built-in function
    eme_change_event_status($event_id, 1); // 1 = published/public

    // Get updated event
    $updated_event = eme_get_event($event_id);

    return rest_ensure_response([
        'success' => true,
        'event' => eme_rest_format_event($updated_event),
        'message' => 'Event published successfully',
    ]);
}

// Location endpoints
function eme_rest_get_locations($request) {
    $locations = eme_get_locations();

    if (empty($locations)) {
        return rest_ensure_response([]);
    }

    return rest_ensure_response(array_map('eme_rest_format_location', $locations));
}

function eme_rest_get_location($request) {
    $location_id = intval($request['id']);
    $location = eme_get_location($location_id);

    if (!$location) {
        return new WP_Error('not_found', 'Location not found', ['status' => 404]);
    }

    return rest_ensure_response(eme_rest_format_location($location));
}

function eme_rest_create_location($request) {
    $params = $request->get_json_params();

    // Validate required fields
    if (empty($params['name'])) {
        return new WP_Error('missing_name', 'Location name is required', ['status' => 400]);
    }

    // Check if EME functions are available
    if (!function_exists('eme_new_location') || !function_exists('eme_insert_location')) {
        return new WP_Error('eme_not_available', 'Events Made Easy plugin functions not available', ['status' => 500]);
    }

    // Use EME's native template function
    $location = eme_new_location();

    // Map REST API parameters to EME structure
    $location['location_name'] = sanitize_text_field($params['name']);
    $location['location_address1'] = isset($params['address']) ? sanitize_text_field($params['address']) : '';
    $location['location_address2'] = isset($params['address2']) ? sanitize_text_field($params['address2']) : '';
    $location['location_city'] = isset($params['city']) ? sanitize_text_field($params['city']) : '';
    $location['location_state'] = isset($params['state']) ? sanitize_text_field($params['state']) : '';
    $location['location_zip'] = isset($params['zip']) ? sanitize_text_field($params['zip']) : '';
    $location['location_country'] = isset($params['country']) ? sanitize_text_field($params['country']) : '';

    // Coordinates are stored as strings in EME
    $location['location_latitude'] = isset($params['latitude']) ? strval($params['latitude']) : '';
    $location['location_longitude'] = isset($params['longitude']) ? strval($params['longitude']) : '';

    if (isset($params['description'])) {
        $location['location_description'] = wp_kses_post($params['description']);
    }

    // Use EME's native insert function
    // force=1 bypasses capability check (we've already checked via REST API permission_callback)
    $location_id = eme_insert_location($location, 1);

    if (!$location_id) {
        return new WP_Error('creation_failed', 'Failed to create location', ['status' => 500]);
    }

    // Get created location using EME's function
    if (function_exists('eme_get_location')) {
        $created_location = eme_get_location($location_id);
    } else {
        $created_location = ['location_id' => $location_id];
    }

    return rest_ensure_response([
        'success' => true,
        'location_id' => $location_id,
        'location' => eme_rest_format_location($created_location),
        'message' => 'Location created successfully',
    ]);
}

// Category endpoints
function eme_rest_get_categories($request) {
    $categories = eme_get_categories();

    if (empty($categories)) {
        return rest_ensure_response([]);
    }

    return rest_ensure_response(array_map('eme_rest_format_category', $categories));
}

function eme_rest_create_category($request) {
    $params = $request->get_json_params();

    // Validate required fields
    if (empty($params['name'])) {
        return new WP_Error('missing_name', 'Category name is required', ['status' => 400]);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'eme_categories';

    $category_slug = isset($params['slug'])
        ? sanitize_title($params['slug'])
        : sanitize_title($params['name']);

    // Insert category
    $result = $wpdb->insert($table, [
        'category_name' => sanitize_text_field($params['name']),
        'category_slug' => $category_slug,
    ]);

    if (!$result) {
        return new WP_Error('creation_failed', 'Failed to create category', ['status' => 500]);
    }

    $category_id = $wpdb->insert_id;

    return rest_ensure_response([
        'success' => true,
        'category_id' => $category_id,
        'category' => [
            'id' => $category_id,
            'name' => $params['name'],
            'slug' => $category_slug,
        ],
        'message' => 'Category created successfully',
    ]);
}

// Formatting functions
function eme_rest_format_event($event) {
    // Build event URL manually to avoid slow eme_event_url() function
    $event_url = '';
    if (!empty($event['event_url'])) {
        $event_url = esc_url($event['event_url']);
    } elseif (!empty($event['event_slug'])) {
        $event_url = home_url('/events/' . $event['event_slug'] . '/');
    } else {
        $event_url = home_url('/?event_id=' . $event['event_id']);
    }

    $formatted = [
        'id' => intval($event['event_id']),
        'title' => $event['event_name'],
        'description' => $event['event_notes'],
        'start_date' => $event['event_start'],
        'end_date' => $event['event_end'],
        'status' => eme_rest_format_status($event['event_status']),
        'slug' => $event['event_slug'],
        'url' => $event_url,
        'location_id' => intval($event['location_id']),
        'category_ids' => !empty($event['event_category_ids'])
            ? array_map('intval', explode(',', $event['event_category_ids']))
            : [],
        'rsvp_enabled' => (bool)$event['event_rsvp'],
        'seats' => $event['event_seats'],
        'price' => $event['price'],
        'currency' => $event['currency'],
        'image_id' => intval($event['event_image_id']),
        'image_url' => $event['event_image_url'],
        'contact_person_id' => intval($event['event_contactperson_id']),
        'created_date' => $event['creation_date'],
        'modified_date' => $event['modif_date'],
    ];

    // Skip location lookup to avoid slow eme_get_location() function
    // Clients can fetch location separately if needed via /locations/{id}

    return $formatted;
}

function eme_rest_format_location($location) {
    return [
        'id' => intval($location['location_id']),
        'name' => $location['location_name'],
        'address' => $location['location_address'],
        'city' => $location['location_city'],
        'state' => $location['location_state'],
        'zip' => $location['location_zip'],
        'country' => $location['location_country'],
    ];
}

function eme_rest_format_category($category) {
    return [
        'id' => intval($category['category_id']),
        'name' => $category['category_name'],
        'slug' => $category['category_slug'],
    ];
}

// Recurrence endpoints
function eme_rest_create_recurrence($request) {
    $params = $request->get_json_params();

    // Validate required fields
    if (empty($params['title'])) {
        return new WP_Error('missing_title', 'Event title is required', ['status' => 400]);
    }
    if (empty($params['recurrence'])) {
        return new WP_Error('missing_recurrence', 'Recurrence pattern is required', ['status' => 400]);
    }

    $recurrence = $params['recurrence'];

    // Validate recurrence pattern
    $validation = eme_rest_validate_recurrence($recurrence);
    if (is_wp_error($validation)) {
        return $validation;
    }

    // Parse recurrence data
    $recurrence_data = eme_rest_parse_recurrence($recurrence);

    // Create recurrence pattern
    global $wpdb;
    $recurrence_table = $wpdb->prefix . 'eme_recurrence';

    $result = $wpdb->insert($recurrence_table, $recurrence_data);

    if (!$result) {
        return new WP_Error('creation_failed', 'Failed to create recurrence pattern', ['status' => 500]);
    }

    $recurrence_id = $wpdb->insert_id;

    // Create event with recurrence_id
    $event = eme_new_event();
    $event['event_properties'] = eme_init_event_props([], 1);

    // Set event details
    $event['event_name'] = sanitize_text_field($params['title']);
    $event['event_notes'] = isset($params['description']) ? wp_kses_post($params['description']) : '';
    // Set event status (defaults to draft if not specified)
    $event['event_status'] = isset($params['status']) ? eme_rest_map_status($params['status']) : 5;
    $event['event_author'] = get_current_user_id();
    $event['recurrence_id'] = $recurrence_id;

    // Optional fields
    if (isset($params['location_id'])) {
        $event['location_id'] = intval($params['location_id']);
    }
    if (isset($params['category_ids'])) {
        $event['event_category_ids'] = is_array($params['category_ids'])
            ? implode(',', array_map('intval', $params['category_ids']))
            : sanitize_text_field($params['category_ids']);
    }
    if (isset($params['rsvp'])) {
        $event['event_rsvp'] = (bool)$params['rsvp'] ? 1 : 0;
    }
    if (isset($params['seats'])) {
        $event['event_seats'] = intval($params['seats']);
    }
    if (isset($params['price'])) {
        $event['price'] = sanitize_text_field($params['price']);
    }
    if (isset($params['currency'])) {
        $event['currency'] = sanitize_text_field($params['currency']);
    }
    if (isset($params['image_id'])) {
        $event['event_image_id'] = intval($params['image_id']);
        $event['event_image_url'] = wp_get_attachment_url($event['event_image_id']);
    }

    // Insert event
    $event_id = eme_db_insert_event($event);

    if (!$event_id) {
        // Rollback: delete recurrence
        $wpdb->delete($recurrence_table, ['recurrence_id' => $recurrence_id]);
        return new WP_Error('creation_failed', 'Failed to create recurring event', ['status' => 500]);
    }

    return rest_ensure_response([
        'success' => true,
        'event_id' => $event_id,
        'recurrence_id' => $recurrence_id,
        'recurrence_pattern' => eme_rest_format_recurrence($recurrence_data),
        'message' => 'Recurring event created successfully',
    ]);
}

function eme_rest_get_recurrence($request) {
    $recurrence_id = intval($request['id']);

    global $wpdb;
    $table = $wpdb->prefix . 'eme_recurrence';

    $recurrence = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE recurrence_id = %d",
        $recurrence_id
    ), ARRAY_A);

    if (!$recurrence) {
        return new WP_Error('not_found', 'Recurrence pattern not found', ['status' => 404]);
    }

    return rest_ensure_response(eme_rest_format_recurrence($recurrence));
}

function eme_rest_delete_recurrence($request) {
    $recurrence_id = intval($request['id']);

    // Check if recurrence exists
    global $wpdb;
    $recurrence_table = $wpdb->prefix . 'eme_recurrence';

    $recurrence = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $recurrence_table WHERE recurrence_id = %d",
        $recurrence_id
    ), ARRAY_A);

    if (!$recurrence) {
        return new WP_Error('not_found', 'Recurrence pattern not found', ['status' => 404]);
    }

    // Delete associated events
    $events_table = $wpdb->prefix . 'eme_events';
    $wpdb->delete($events_table, ['recurrence_id' => $recurrence_id]);

    // Delete recurrence
    $wpdb->delete($recurrence_table, ['recurrence_id' => $recurrence_id]);

    return rest_ensure_response([
        'success' => true,
        'message' => 'Recurrence pattern and associated events deleted successfully',
    ]);
}

function eme_rest_get_recurrence_instances($request) {
    $recurrence_id = intval($request['id']);

    // Optional date range parameters
    $start_date = isset($request['start_date']) ? sanitize_text_field($request['start_date']) : date('Y-m-d');
    $end_date = isset($request['end_date']) ? sanitize_text_field($request['end_date']) : date('Y-m-d', strtotime('+1 year'));

    // Get recurrence pattern
    global $wpdb;
    $table = $wpdb->prefix . 'eme_recurrence';

    $recurrence = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE recurrence_id = %d",
        $recurrence_id
    ), ARRAY_A);

    if (!$recurrence) {
        return new WP_Error('not_found', 'Recurrence pattern not found', ['status' => 404]);
    }

    // Calculate instances using EME's built-in function
    if (function_exists('eme_get_recurrence_events')) {
        $instances = eme_get_recurrence_events($recurrence_id, $start_date, $end_date);
    } else {
        // Fallback: calculate manually
        $instances = eme_rest_calculate_instances($recurrence, $start_date, $end_date);
    }

    return rest_ensure_response([
        'recurrence_id' => $recurrence_id,
        'pattern' => eme_rest_format_recurrence($recurrence),
        'instances' => $instances,
        'count' => count($instances),
    ]);
}

// Helper functions
function eme_rest_validate_recurrence($recurrence) {
    // Validate frequency
    $valid_frequencies = ['weekly', 'monthly', 'specific'];
    if (empty($recurrence['frequency']) || !in_array($recurrence['frequency'], $valid_frequencies)) {
        return new WP_Error('invalid_frequency', 'Frequency must be one of: weekly, monthly, specific', ['status' => 400]);
    }

    // Validate dates for weekly/monthly
    if (in_array($recurrence['frequency'], ['weekly', 'monthly'])) {
        if (empty($recurrence['start_date'])) {
            return new WP_Error('missing_start_date', 'Start date is required', ['status' => 400]);
        }
        if (empty($recurrence['end_date'])) {
            return new WP_Error('missing_end_date', 'End date is required', ['status' => 400]);
        }
    }

    // Validate interval
    if (isset($recurrence['interval']) && $recurrence['interval'] < 1) {
        return new WP_Error('invalid_interval', 'Interval must be >= 1', ['status' => 400]);
    }

    // Validate weekly pattern
    if ($recurrence['frequency'] === 'weekly') {
        if (empty($recurrence['days_of_week'])) {
            return new WP_Error('missing_days', 'Days of week are required for weekly recurrence', ['status' => 400]);
        }
    }

    // Validate monthly pattern
    if ($recurrence['frequency'] === 'monthly') {
        if (empty($recurrence['day_of_week']) || !isset($recurrence['week_of_month'])) {
            return new WP_Error('missing_monthly_pattern', 'Day of week and week of month are required', ['status' => 400]);
        }
    }

    // Validate specific days
    if ($recurrence['frequency'] === 'specific') {
        if (empty($recurrence['dates'])) {
            return new WP_Error('missing_dates', 'Specific dates are required', ['status' => 400]);
        }
    }

    return true;
}

function eme_rest_parse_recurrence($recurrence) {
    $data = [
        'recurrence_freq' => $recurrence['frequency'],
        'recurrence_interval' => isset($recurrence['interval']) ? intval($recurrence['interval']) : 1,
    ];

    // Parse based on frequency type
    if ($recurrence['frequency'] === 'weekly') {
        $data['recurrence_start_date'] = sanitize_text_field($recurrence['start_date']);
        $data['recurrence_end_date'] = sanitize_text_field($recurrence['end_date']);

        // Convert day names to day codes (0=Sun, 1=Mon, etc.)
        $day_map = [
            'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
            'thursday' => 4, 'friday' => 5, 'saturday' => 6
        ];

        $days = is_array($recurrence['days_of_week']) ? $recurrence['days_of_week'] : explode(',', $recurrence['days_of_week']);
        $day_codes = [];
        foreach ($days as $day) {
            $day = strtolower(trim($day));
            if (isset($day_map[$day])) {
                $day_codes[] = $day_map[$day];
            }
        }
        $data['recurrence_byday'] = implode(',', $day_codes);

        // Duration and start time
        $data['event_duration'] = isset($recurrence['duration']) ? intval($recurrence['duration']) : 3600;

    } elseif ($recurrence['frequency'] === 'monthly') {
        $data['recurrence_start_date'] = sanitize_text_field($recurrence['start_date']);
        $data['recurrence_end_date'] = sanitize_text_field($recurrence['end_date']);

        // Week of month (1-5, or -1 for last)
        $data['recurrence_byweekno'] = intval($recurrence['week_of_month']);

        // Day of week
        $day_map = ['sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6];
        $day = strtolower(trim($recurrence['day_of_week']));
        $data['recurrence_byday'] = isset($day_map[$day]) ? $day_map[$day] : 1;

        $data['event_duration'] = isset($recurrence['duration']) ? intval($recurrence['duration']) : 3600;

    } elseif ($recurrence['frequency'] === 'specific') {
        // Specific dates
        $dates = is_array($recurrence['dates']) ? $recurrence['dates'] : explode(',', $recurrence['dates']);
        $data['specific_days'] = implode(',', array_map('sanitize_text_field', $dates));

        $data['event_duration'] = isset($recurrence['duration']) ? intval($recurrence['duration']) : 3600;
    }

    // Exclude days (optional)
    if (isset($recurrence['exclude_days'])) {
        $exclude = is_array($recurrence['exclude_days']) ? $recurrence['exclude_days'] : explode(',', $recurrence['exclude_days']);
        $data['exclude_days'] = implode(',', array_map('sanitize_text_field', $exclude));
    }

    return $data;
}

function eme_rest_format_recurrence($recurrence) {
    $formatted = [
        'id' => intval($recurrence['recurrence_id']),
        'frequency' => $recurrence['recurrence_freq'],
        'interval' => intval($recurrence['recurrence_interval']),
        'duration' => intval($recurrence['event_duration']),
    ];

    if (isset($recurrence['recurrence_start_date'])) {
        $formatted['start_date'] = $recurrence['recurrence_start_date'];
    }
    if (isset($recurrence['recurrence_end_date'])) {
        $formatted['end_date'] = $recurrence['recurrence_end_date'];
    }
    if (isset($recurrence['recurrence_byday'])) {
        $formatted['days_of_week_codes'] = $recurrence['recurrence_byday'];
    }
    if (isset($recurrence['recurrence_byweekno'])) {
        $formatted['week_of_month'] = intval($recurrence['recurrence_byweekno']);
    }
    if (isset($recurrence['specific_days'])) {
        $formatted['specific_dates'] = explode(',', $recurrence['specific_days']);
    }
    if (isset($recurrence['exclude_days'])) {
        $formatted['excluded_dates'] = explode(',', $recurrence['exclude_days']);
    }

    return $formatted;
}

function eme_rest_calculate_instances($recurrence, $start_date, $end_date) {
    // Simple instance calculation (basic implementation)
    // For production, use EME's built-in calculation functions

    $instances = [];
    $freq = $recurrence['recurrence_freq'];

    if ($freq === 'weekly') {
        // Calculate weekly instances
        $current = strtotime($recurrence['recurrence_start_date']);
        $end = strtotime($recurrence['recurrence_end_date']);
        $interval = intval($recurrence['recurrence_interval']) * 7 * 86400; // weeks to seconds
        $days_of_week = explode(',', $recurrence['recurrence_byday']);

        while ($current <= $end) {
            $day_of_week = date('w', $current);
            if (in_array($day_of_week, $days_of_week)) {
                $instances[] = [
                    'start' => date('Y-m-d H:i:s', $current),
                    'end' => date('Y-m-d H:i:s', $current + intval($recurrence['event_duration'])),
                ];
            }
            $current += 86400; // next day
        }

    } elseif ($freq === 'specific') {
        // Specific dates
        $dates = explode(',', $recurrence['specific_days']);
        foreach ($dates as $date) {
            $timestamp = strtotime($date);
            $instances[] = [
                'start' => date('Y-m-d H:i:s', $timestamp),
                'end' => date('Y-m-d H:i:s', $timestamp + intval($recurrence['event_duration'])),
            ];
        }
    }

    return $instances;
}
