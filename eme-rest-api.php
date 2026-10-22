<?php
/**
 * Plugin Name: Events Made Easy REST API
 * Plugin URI: https://github.com/gserafini/eme-rest-api
 * GitHub Plugin URI: gserafini/eme-rest-api
 * Description: REST API endpoints for Events Made Easy plugin including recurring events support
 * Version: 1.10.0-rc.1
 * Author: Gabriel Serafini
 * Author URI: https://gabrielserafini.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Text Domain: eme-rest-api
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Silently check if Events Made Easy is active
// If not active, plugin does nothing (safe for network activation)
function eme_rest_api_check_dependencies() {
	return function_exists( 'eme_new_event' );
}

// Plugin activation hook - flush permalinks to register new routes
function eme_rest_api_activate() {
	// Flush rewrite rules to ensure REST API routes are properly registered
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'eme_rest_api_activate' );

// Disable canonical redirects for REST API requests
// This prevents WordPress from adding trailing slashes which breaks REST API POST requests
add_filter( 'redirect_canonical', 'eme_rest_api_disable_canonical_redirect', 10, 2 );
function eme_rest_api_disable_canonical_redirect( $redirect_url, $requested_url ) {
	// If this is a REST API request, don't redirect
	if ( strpos( $requested_url, '/wp-json/' ) !== false || strpos( $requested_url, rest_get_url_prefix() ) !== false ) {
		return false;
	}
	return $redirect_url;
}

// Register REST API routes with high priority to prevent conflicts with EME post types
add_action(
	'rest_api_init',
	function () {
		if ( ! function_exists( 'eme_new_event' ) ) {
			return; // EME not active
		}

		$namespace = 'eme/v1';

		// Event endpoints (renamed to /eme_events to avoid conflict with EME custom post types)
		register_rest_route(
			$namespace,
			'/eme_events',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => 'eme_rest_get_events',
					'permission_callback' => 'eme_rest_read_permission',
				),
				array(
					'methods'             => 'POST',
					'callback'            => 'eme_rest_create_event',
					'permission_callback' => 'eme_rest_write_permission',
				),
			)
		);

		register_rest_route(
			$namespace,
			'/eme_events/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => 'eme_rest_get_event',
					'permission_callback' => 'eme_rest_read_permission',
				),
				array(
					'methods'             => array( 'POST', 'PUT', 'PATCH' ),
					'callback'            => 'eme_rest_update_event',
					'permission_callback' => 'eme_rest_write_permission',
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => 'eme_rest_delete_event',
					'permission_callback' => 'eme_rest_write_permission',
				),
			)
		);

		// Location endpoints (renamed to /eme_locations to avoid conflict with EME custom post types)
		register_rest_route(
			$namespace,
			'/eme_locations',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => 'eme_rest_get_locations',
					'permission_callback' => 'eme_rest_read_permission',
				),
				array(
					'methods'             => 'POST',
					'callback'            => 'eme_rest_create_location',
					'permission_callback' => 'eme_rest_write_permission',
				),
			)
		);

		register_rest_route(
			$namespace,
			'/eme_locations/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => 'eme_rest_get_location',
					'permission_callback' => 'eme_rest_read_permission',
				),
				array(
					'methods'             => array( 'POST', 'PUT', 'PATCH' ),
					'callback'            => 'eme_rest_update_location',
					'permission_callback' => 'eme_rest_write_permission',
				),
			)
		);

		// Category endpoints (renamed to /eme_categories to avoid conflict with EME custom post types)
		register_rest_route(
			$namespace,
			'/eme_categories',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => 'eme_rest_get_categories',
					'permission_callback' => 'eme_rest_read_permission',
				),
				array(
					'methods'             => 'POST',
					'callback'            => 'eme_rest_create_category',
					'permission_callback' => 'eme_rest_write_permission',
				),
			)
		);

		// Recurrence endpoints (renamed to /eme_recurrences to avoid conflict with EME custom post types)
		register_rest_route(
			$namespace,
			'/eme_recurrences',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => 'eme_rest_get_recurrences',
					'permission_callback' => 'eme_rest_read_permission',
				),
				array(
					'methods'             => 'POST',
					'callback'            => 'eme_rest_create_recurrence',
					'permission_callback' => 'eme_rest_write_permission',
				),
			)
		);

		register_rest_route(
			$namespace,
			'/eme_recurrences/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => 'eme_rest_get_recurrence',
					'permission_callback' => 'eme_rest_read_permission',
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => 'eme_rest_delete_recurrence',
					'permission_callback' => 'eme_rest_write_permission',
				),
			)
		);

		register_rest_route(
			$namespace,
			'/eme_recurrences/(?P<id>\d+)/instances',
			array(
				'methods'             => 'GET',
				'callback'            => 'eme_rest_get_recurrence_instances',
				'permission_callback' => 'eme_rest_read_permission',
			)
		);
	}
);

// Permission callbacks
function eme_rest_read_permission() {
	// Allow reading for anyone (can be restricted if needed)
	return true;
}

function eme_rest_write_permission() {
	// Require authentication and edit_posts capability
	return current_user_can( 'edit_posts' );
}

// Geocoding function using OpenStreetMap Nominatim (same as EME frontend)
function eme_rest_geocode_location( $location ) {
	// Build search query from location components
	$address_parts = array_filter(
		array(
			isset( $location['location_address1'] ) ? $location['location_address1'] : '',
			isset( $location['location_address2'] ) ? $location['location_address2'] : '',
			isset( $location['location_city'] ) ? $location['location_city'] : '',
			isset( $location['location_state'] ) ? $location['location_state'] : '',
			isset( $location['location_zip'] ) ? $location['location_zip'] : '',
			isset( $location['location_country'] ) ? $location['location_country'] : '',
		)
	);

	if ( empty( $address_parts ) ) {
		return false; // No address to geocode
	}

	$search_query = implode( ', ', $address_parts );

	// Use OpenStreetMap Nominatim API (same as EME uses)
	$geocode_url = 'https://nominatim.openstreetmap.org/search';
	$params      = array(
		'format' => 'json',
		'limit'  => 1,
		'q'      => $search_query,
	);

	$url = $geocode_url . '?' . http_build_query( $params );

	// Set user agent as required by Nominatim usage policy
	$args = array(
		'headers' => array(
			'User-Agent' => 'EME-REST-API/1.6.7 WordPress/' . get_bloginfo( 'version' ),
		),
		'timeout' => 10,
	);

	$response = wp_remote_get( $url, $args );

	if ( is_wp_error( $response ) ) {
		return false;
	}

	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );

	if ( ! empty( $data ) && isset( $data[0]['lat'] ) && isset( $data[0]['lon'] ) ) {
		return array(
			'latitude'  => $data[0]['lat'],
			'longitude' => $data[0]['lon'],
		);
	}

	return false;
}

// Helper function to map status strings to EME status codes
function eme_rest_map_status( $status_string ) {
	// EME status constants:
	// EME_EVENT_STATUS_TRASH = 0
	// EME_EVENT_STATUS_PUBLIC = 1
	// EME_EVENT_STATUS_PRIVATE = 2
	// EME_EVENT_STATUS_UNLISTED = 3
	// EME_EVENT_STATUS_DRAFT = 5
	// EME_EVENT_STATUS_FS_DRAFT = 6

	$status_map = array(
		'trash'     => 0,
		'published' => 1,
		'public'    => 1,
		'private'   => 2,
		'unlisted'  => 3,
		'draft'     => 5,
		'fs_draft'  => 6,
	);

	$normalized_status = strtolower( trim( $status_string ) );
	return isset( $status_map[ $normalized_status ] ) ? $status_map[ $normalized_status ] : 5; // default to draft
}

// Helper function to map EME status codes to readable strings
function eme_rest_format_status( $status_code ) {
	$status_names = array(
		0 => 'trash',
		1 => 'published',
		2 => 'private',
		3 => 'unlisted',
		5 => 'draft',
		6 => 'fs_draft',
	);

	return isset( $status_names[ $status_code ] ) ? $status_names[ $status_code ] : 'unknown';
}

// Event endpoints
function eme_rest_get_events( $request ) {
	$params = $request->get_params();

	// EME's eme_get_events() function signature:
	// function eme_get_events($o_limit, $scope, $order, $o_offset, $location_id, $category, $author, $contact_person, $show_ongoing, $notcategory, $show_recurrent_events_once, $extra_conditions, $count, $include_customformfields, $search_customfieldids, $search_customfields)

	// Parameter 1: o_limit (0 = unlimited, like the calendar uses)
	$limit = isset( $params['per_page'] ) ? intval( $params['per_page'] ) : 0;

	// Parameter 2: scope (date range or named scope like 'future')
	if ( isset( $params['start_date'] ) || isset( $params['end_date'] ) ) {
		$start_date = isset( $params['start_date'] ) ? sanitize_text_field( $params['start_date'] ) : '';
		$end_date   = isset( $params['end_date'] ) ? sanitize_text_field( $params['end_date'] ) : '';
		$scope      = $start_date . '--' . $end_date;
	} else {
		$scope = isset( $params['scope'] ) ? sanitize_text_field( $params['scope'] ) : 'future';
	}

	// Parameter 3: order
	$order = isset( $params['order'] ) ? sanitize_text_field( $params['order'] ) : 'ASC';

	// Parameter 4: o_offset
	$offset = isset( $params['page'] ) && $limit > 0 ? ( intval( $params['page'] ) - 1 ) * $limit : 0;

	// Parameter 5: location_id
	$location_id = isset( $params['location'] ) ? sanitize_text_field( $params['location'] ) : '';

	// Parameter 6: category
	$category = isset( $params['category'] ) ? sanitize_text_field( $params['category'] ) : '';

	// Parameter 7: author
	$author = isset( $params['author'] ) ? sanitize_text_field( $params['author'] ) : '';

	// Parameter 8: contact_person
	$contact_person = isset( $params['contact_person'] ) ? sanitize_text_field( $params['contact_person'] ) : '';

	// Parameter 9: show_ongoing (critical for multi-day events!)
	$show_ongoing = isset( $params['show_ongoing'] ) ? intval( $params['show_ongoing'] ) : 1;

	// Parameter 10: notcategory
	$notcategory = isset( $params['notcategory'] ) ? sanitize_text_field( $params['notcategory'] ) : '';

	// Parameter 11: show_recurrent_events_once
	$show_recurrent_events_once = isset( $params['show_recurrent_events_once'] ) ? intval( $params['show_recurrent_events_once'] ) : 0;

	// Call EME function with POSITIONAL parameters (not array!)
	// This matches how the calendar calls it
	$events = eme_get_events(
		$limit,                      // o_limit
		$scope,                      // scope
		$order,                      // order
		$offset,                     // o_offset
		$location_id,                // location_id
		$category,                   // category
		$author,                     // author
		$contact_person,             // contact_person
		$show_ongoing,               // show_ongoing
		$notcategory,                // notcategory
		$show_recurrent_events_once  // show_recurrent_events_once
	);

	if ( empty( $events ) ) {
		return rest_ensure_response( array() );
	}

	// Public API reads follow event visibility even when a caller supplies IDs.
	if ( ! current_user_can( 'edit_posts' ) ) {
		$events = array_values(
			array_filter(
				$events,
				function ( $event ) {
					return 1 === (int) $event['event_status'];
				}
			)
		);
	}
	// Format events for REST response
	$formatted_events = array_map( 'eme_rest_format_event', $events );

	// Add debug info to response headers
	$debug_args = compact( 'limit', 'scope', 'order', 'offset', 'location_id', 'category', 'author', 'contact_person', 'show_ongoing', 'notcategory', 'show_recurrent_events_once' );
	$response   = rest_ensure_response( $formatted_events );
	$response->header( 'X-EME-Query-Args', wp_json_encode( $debug_args ) );
	$response->header( 'X-EME-Event-Count', count( $events ) );

	return $response;
}

function eme_rest_get_event( $request ) {
	$event_id = intval( $request['id'] );

	// Use EME's built-in function to get event
	$event = eme_get_event( $event_id );

	if ( ! $event || ( ! current_user_can( 'edit_posts' ) && ! in_array( (int) $event['event_status'], array( 1, 3 ), true ) ) ) {
		return new WP_Error( 'not_found', 'Event not found', array( 'status' => 404 ) );
	}

	return rest_ensure_response( eme_rest_format_event( $event ) );
}

function eme_rest_create_event( $request ) {
	$params = $request->get_json_params();

	// Validate required fields
	if ( empty( $params['title'] ) ) {
		return new WP_Error( 'missing_title', 'Event title is required', array( 'status' => 400 ) );
	}
	if ( empty( $params['start_date'] ) ) {
		return new WP_Error( 'missing_start_date', 'Event start date is required', array( 'status' => 400 ) );
	}

	// Check if EME functions are available
	if ( ! function_exists( 'eme_new_event' ) || ! function_exists( 'eme_db_insert_event' ) ) {
		return new WP_Error( 'eme_not_available', 'Events Made Easy plugin functions not available', array( 'status' => 500 ) );
	}

	// Use EME's native template function
	$event = eme_new_event();

	// Map REST API parameters to EME structure
	$event['event_name']  = sanitize_text_field( $params['title'] );
	$event['event_notes'] = isset( $params['description'] ) ? wp_kses_post( $params['description'] ) : '';

	// EME can handle both full datetime or separate date/time
	// Priority: full datetime if provided, otherwise separate fields
	if ( isset( $params['start_datetime'] ) ) {
		$event['event_start'] = sanitize_text_field( $params['start_datetime'] );
	} else {
		$start_time           = isset( $params['start_time'] ) ? sanitize_text_field( $params['start_time'] ) : '00:00:00';
		$event['event_start'] = sanitize_text_field( $params['start_date'] ) . ' ' . $start_time;
	}

	if ( isset( $params['end_datetime'] ) ) {
		$event['event_end'] = sanitize_text_field( $params['end_datetime'] );
	} else {
		$end_date           = isset( $params['end_date'] ) ? sanitize_text_field( $params['end_date'] ) : sanitize_text_field( $params['start_date'] );
		$end_time           = isset( $params['end_time'] ) ? sanitize_text_field( $params['end_time'] ) : '23:59:59';
		$event['event_end'] = $end_date . ' ' . $end_time;
	}

	// Set event status (defaults to draft if not specified)
	$event['event_status'] = isset( $params['status'] ) ? eme_rest_map_status( $params['status'] ) : 5;

	// Optional fields
	if ( isset( $params['location_id'] ) ) {
		$event['location_id'] = intval( $params['location_id'] );
	}

	if ( isset( $params['category_ids'] ) ) {
		$event['event_category_ids'] = is_array( $params['category_ids'] )
			? implode( ',', array_map( 'intval', $params['category_ids'] ) )
			: sanitize_text_field( $params['category_ids'] );
	}

	if ( isset( $params['rsvp'] ) ) {
		$event['event_rsvp'] = (bool) $params['rsvp'] ? 1 : 0;
	}

	if ( isset( $params['seats'] ) ) {
		$event['event_seats'] = intval( $params['seats'] );
	}

	if ( isset( $params['price'] ) ) {
		$event['price'] = sanitize_text_field( $params['price'] );
	}

	if ( isset( $params['currency'] ) ) {
		$event['currency'] = sanitize_text_field( $params['currency'] );
	}

	if ( isset( $params['image_id'] ) ) {
		$event['event_image_id'] = intval( $params['image_id'] );
	}

	if ( isset( $params['contact_person_id'] ) ) {
		$event['event_contactperson_id'] = intval( $params['contact_person_id'] );
	}

	if ( isset( $params['url'] ) ) {
		$event['event_url'] = esc_url_raw( $params['url'] );
	}

	// Use EME's native insert function
	$event_id = eme_db_insert_event( $event );

	if ( ! $event_id ) {
		return new WP_Error( 'creation_failed', 'Failed to create event', array( 'status' => 500 ) );
	}

	// Get the created event using EME's function
	if ( function_exists( 'eme_get_event' ) ) {
		$created_event = eme_get_event( $event_id );
	} else {
		$created_event = array( 'event_id' => $event_id );
	}

	return rest_ensure_response(
		array(
			'success'  => true,
			'event_id' => $event_id,
			'event'    => eme_rest_format_event( $created_event ),
			'message'  => 'Event created successfully',
		)
	);
}

function eme_rest_update_event( $request ) {
	$event_id = intval( $request['id'] );
	$params   = $request->get_json_params();

	// Get existing event using EME's built-in function
	$event = eme_get_event( $event_id );

	if ( ! $event ) {
		return new WP_Error( 'not_found', 'Event not found', array( 'status' => 404 ) );
	}

	// Update event fields with provided parameters
	if ( isset( $params['title'] ) ) {
		$event['event_name'] = sanitize_text_field( $params['title'] );
	}
	if ( isset( $params['description'] ) ) {
		$event['event_notes'] = wp_kses_post( $params['description'] );
	}
	if ( isset( $params['start_date'] ) ) {
		$event['event_start'] = sanitize_text_field( $params['start_date'] );
	}
	if ( isset( $params['end_date'] ) ) {
		$event['event_end'] = sanitize_text_field( $params['end_date'] );
	}
	if ( isset( $params['status'] ) ) {
		$event['event_status'] = eme_rest_map_status( $params['status'] );
	}
	if ( isset( $params['location_id'] ) ) {
		$event['location_id'] = intval( $params['location_id'] );
	}
	if ( isset( $params['category_ids'] ) ) {
		$event['event_category_ids'] = is_array( $params['category_ids'] )
			? implode( ',', array_map( 'intval', $params['category_ids'] ) )
			: sanitize_text_field( $params['category_ids'] );
	}
	if ( isset( $params['rsvp'] ) ) {
		$event['event_rsvp'] = (bool) $params['rsvp'] ? 1 : 0;
	}
	if ( isset( $params['seats'] ) ) {
		$event['event_seats'] = intval( $params['seats'] );
	}
	if ( isset( $params['price'] ) ) {
		$event['price'] = sanitize_text_field( $params['price'] );
	}
	if ( isset( $params['currency'] ) ) {
		$event['currency'] = sanitize_text_field( $params['currency'] );
	}
	if ( isset( $params['image_id'] ) ) {
		$event['event_image_id'] = intval( $params['image_id'] );
	}
	if ( isset( $params['contact_person_id'] ) ) {
		$event['event_contactperson_id'] = intval( $params['contact_person_id'] );
	}

	// Update event using EME's built-in function
	$result = eme_db_update_event( $event, $event_id );

	if ( ! $result ) {
		return new WP_Error( 'update_failed', 'Failed to update event', array( 'status' => 500 ) );
	}

	// Get updated event
	$updated_event = eme_get_event( $event_id );

	return rest_ensure_response(
		array(
			'success' => true,
			'event'   => eme_rest_format_event( $updated_event ),
			'message' => 'Event updated successfully',
		)
	);
}

function eme_rest_delete_event( $request ) {
	$event_id = intval( $request['id'] );

	// Check if event exists before deletion
	$event = eme_get_event( $event_id );
	if ( ! $event ) {
		return new WP_Error( 'not_found', 'Event not found', array( 'status' => 404 ) );
	}

	// Delete event (eme_db_delete_event returns void/null, not success/failure)
	eme_db_delete_event( $event_id );

	// Clear WordPress object cache for this event to ensure fresh DB query
	wp_cache_delete( "eme_event $event_id" );

	// Verify deletion by querying database (cache is cleared, so this hits DB)
	$check = eme_get_event( $event_id );
	if ( $check ) {
		// Event still exists in database, deletion failed
		return new WP_Error( 'deletion_failed', 'Failed to delete event', array( 'status' => 500 ) );
	}

	// Event successfully deleted
	return rest_ensure_response(
		array(
			'success' => true,
			'message' => 'Event deleted successfully',
		)
	);
}

function eme_rest_change_status( $request ) {
	$event_id = intval( $request['id'] );
	$params   = $request->get_json_params();

	// Validate status parameter
	if ( ! isset( $params['status'] ) ) {
		return new WP_Error( 'missing_status', 'Status parameter is required', array( 'status' => 400 ) );
	}

	// Get existing event using EME's built-in function
	$event = eme_get_event( $event_id );

	if ( ! $event ) {
		return new WP_Error( 'not_found', 'Event not found', array( 'status' => 404 ) );
	}

	// Map status string to EME status code
	$new_status = eme_rest_map_status( $params['status'] );

	// Validate status code (must be 0-6)
	// Status codes: 0=trash, 1=public, 2=private, 3=unlisted, 5=draft, 6=fs_draft.
	$valid_statuses = array( 0, 1, 2, 3, 5, 6 );
	if ( ! in_array( $new_status, $valid_statuses, true ) ) {
		return new WP_Error( 'invalid_status', 'Invalid status code', array( 'status' => 400 ) );
	}

	// Update status using EME's built-in function
	eme_change_event_status( $event_id, $new_status );

	// Get updated event
	$updated_event = eme_get_event( $event_id );

	return rest_ensure_response(
		array(
			'success'         => true,
			'event'           => eme_rest_format_event( $updated_event ),
			'previous_status' => eme_rest_format_status( $event['event_status'] ),
			'new_status'      => eme_rest_format_status( $new_status ),
			'message'         => sprintf( 'Event status changed from %s to %s', eme_rest_format_status( $event['event_status'] ), eme_rest_format_status( $new_status ) ),
		)
	);
}

function eme_rest_publish_event( $request ) {
	$event_id = intval( $request['id'] );

	// Get existing event using EME's built-in function
	$event = eme_get_event( $event_id );

	if ( ! $event ) {
		return new WP_Error( 'not_found', 'Event not found', array( 'status' => 404 ) );
	}

	// Update status to published using EME's built-in function
	eme_change_event_status( $event_id, 1 ); // 1 = published/public

	// Get updated event
	$updated_event = eme_get_event( $event_id );

	return rest_ensure_response(
		array(
			'success' => true,
			'event'   => eme_rest_format_event( $updated_event ),
			'message' => 'Event published successfully',
		)
	);
}

// Location endpoints.
function eme_rest_get_locations( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by WP REST API callback signature.
	$locations = eme_get_locations();

	if ( empty( $locations ) ) {
		return rest_ensure_response( array() );
	}

	return rest_ensure_response( array_map( 'eme_rest_format_location', $locations ) );
}

function eme_rest_get_location( $request ) {
	$location_id = intval( $request['id'] );
	$location    = eme_get_location( $location_id );

	if ( ! $location ) {
		return new WP_Error( 'not_found', 'Location not found', array( 'status' => 404 ) );
	}

	return rest_ensure_response( eme_rest_format_location( $location ) );
}

function eme_rest_create_location( $request ) {
	$params = $request->get_json_params();

	// Validate required fields
	if ( empty( $params['name'] ) ) {
		return new WP_Error( 'missing_name', 'Location name is required', array( 'status' => 400 ) );
	}

	// Check if EME functions are available
	if ( ! function_exists( 'eme_new_location' ) || ! function_exists( 'eme_insert_location' ) ) {
		return new WP_Error( 'eme_not_available', 'Events Made Easy plugin functions not available', array( 'status' => 500 ) );
	}

	// Use EME's native template function
	$location = eme_new_location();

	// Map REST API parameters to EME structure
	$location['location_name']     = sanitize_text_field( $params['name'] );
	$location['location_address1'] = isset( $params['address'] ) ? sanitize_text_field( $params['address'] ) : '';
	$location['location_address2'] = isset( $params['address2'] ) ? sanitize_text_field( $params['address2'] ) : '';
	$location['location_city']     = isset( $params['city'] ) ? sanitize_text_field( $params['city'] ) : '';
	$location['location_state']    = isset( $params['state'] ) ? sanitize_text_field( $params['state'] ) : '';
	$location['location_zip']      = isset( $params['zip'] ) ? sanitize_text_field( $params['zip'] ) : '';
	$location['location_country']  = isset( $params['country'] ) ? sanitize_text_field( $params['country'] ) : '';

	// Coordinates are stored as strings in EME
	$location['location_latitude']  = isset( $params['latitude'] ) ? strval( $params['latitude'] ) : '';
	$location['location_longitude'] = isset( $params['longitude'] ) ? strval( $params['longitude'] ) : '';

	// Auto-geocode if coordinates not provided but address is
	if ( empty( $location['location_latitude'] ) && empty( $location['location_longitude'] ) ) {
		$coords = eme_rest_geocode_location( $location );
		if ( $coords ) {
			$location['location_latitude']  = strval( $coords['latitude'] );
			$location['location_longitude'] = strval( $coords['longitude'] );
		}
	}

	if ( isset( $params['description'] ) ) {
		$location['location_description'] = wp_kses_post( $params['description'] );
	}

	// Use EME's native insert function
	// force=1 bypasses capability check (we've already checked via REST API permission_callback)
	$location_id = eme_insert_location( $location, 1 );

	if ( ! $location_id ) {
		return new WP_Error( 'creation_failed', 'Failed to create location', array( 'status' => 500 ) );
	}

	// Get created location using EME's function
	if ( function_exists( 'eme_get_location' ) ) {
		$created_location = eme_get_location( $location_id );
	} else {
		$created_location = array( 'location_id' => $location_id );
	}

	return rest_ensure_response(
		array(
			'success'     => true,
			'location_id' => $location_id,
			'location'    => eme_rest_format_location( $created_location ),
			'message'     => 'Location created successfully',
		)
	);
}

function eme_rest_update_location( $request ) {
	$location_id = intval( $request['id'] );
	$params      = $request->get_json_params();

	// Get existing location using EME's built-in function
	$location = eme_get_location( $location_id );

	if ( ! $location ) {
		return new WP_Error( 'not_found', 'Location not found', array( 'status' => 404 ) );
	}

	// Update location fields with provided parameters
	if ( isset( $params['name'] ) ) {
		$location['location_name'] = sanitize_text_field( $params['name'] );
	}
	if ( isset( $params['address'] ) ) {
		$location['location_address1'] = sanitize_text_field( $params['address'] );
	}
	if ( isset( $params['address2'] ) ) {
		$location['location_address2'] = sanitize_text_field( $params['address2'] );
	}
	if ( isset( $params['city'] ) ) {
		$location['location_city'] = sanitize_text_field( $params['city'] );
	}
	if ( isset( $params['state'] ) ) {
		$location['location_state'] = sanitize_text_field( $params['state'] );
	}
	if ( isset( $params['zip'] ) ) {
		$location['location_zip'] = sanitize_text_field( $params['zip'] );
	}
	if ( isset( $params['country'] ) ) {
		$location['location_country'] = sanitize_text_field( $params['country'] );
	}
	if ( isset( $params['latitude'] ) ) {
		$location['location_latitude'] = strval( $params['latitude'] );
	}
	if ( isset( $params['longitude'] ) ) {
		$location['location_longitude'] = strval( $params['longitude'] );
	}
	if ( isset( $params['description'] ) ) {
		$location['location_description'] = wp_kses_post( $params['description'] );
	}

	// Auto-geocode if address fields were updated but coordinates weren't provided
	$address_fields_updated = isset( $params['address'] ) || isset( $params['address2'] ) ||
							isset( $params['city'] ) || isset( $params['state'] ) ||
							isset( $params['zip'] ) || isset( $params['country'] );
	$coords_provided        = isset( $params['latitude'] ) || isset( $params['longitude'] );

	if ( $address_fields_updated && ! $coords_provided ) {
		$coords = eme_rest_geocode_location( $location );
		if ( $coords ) {
			$location['location_latitude']  = strval( $coords['latitude'] );
			$location['location_longitude'] = strval( $coords['longitude'] );
		}
	}

	// Update location using EME's built-in function
	// Note: eme_update_location may return location_id on success, 0/false on failure
	$result = eme_update_location( $location, $location_id );

	// Get updated location (regardless of return value, as EME functions vary)
	$updated_location = eme_get_location( $location_id );

	if ( ! $updated_location ) {
		return new WP_Error( 'update_failed', 'Failed to update location', array( 'status' => 500 ) );
	}

	return rest_ensure_response(
		array(
			'success'  => true,
			'location' => eme_rest_format_location( $updated_location ),
			'message'  => 'Location updated successfully',
		)
	);
}

// Category endpoints.
function eme_rest_get_categories( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by WP REST API callback signature.
	$categories = eme_get_categories();

	if ( empty( $categories ) ) {
		return rest_ensure_response( array() );
	}

	return rest_ensure_response( array_map( 'eme_rest_format_category', $categories ) );
}

function eme_rest_create_category( $request ) {
	$params = $request->get_json_params();

	// Validate required fields
	if ( empty( $params['name'] ) ) {
		return new WP_Error( 'missing_name', 'Category name is required', array( 'status' => 400 ) );
	}

	global $wpdb;
	$table = $wpdb->prefix . 'eme_categories';

	$category_slug = isset( $params['slug'] )
		? sanitize_title( $params['slug'] )
		: sanitize_title( $params['name'] );

	// Insert category
	$result = $wpdb->insert(
		$table,
		array(
			'category_name' => sanitize_text_field( $params['name'] ),
			'category_slug' => $category_slug,
		)
	);

	if ( ! $result ) {
		return new WP_Error( 'creation_failed', 'Failed to create category', array( 'status' => 500 ) );
	}

	$category_id = $wpdb->insert_id;

	return rest_ensure_response(
		array(
			'success'     => true,
			'category_id' => $category_id,
			'category'    => array(
				'id'   => $category_id,
				'name' => $params['name'],
				'slug' => $category_slug,
			),
			'message'     => 'Category created successfully',
		)
	);
}

// Formatting functions
function eme_rest_format_event( $event ) {
	// Build event URL manually to avoid slow eme_event_url() function
	$event_url = '';
	if ( ! empty( $event['event_url'] ) ) {
		$event_url = esc_url( $event['event_url'] );
	} elseif ( ! empty( $event['event_slug'] ) ) {
		$event_url = home_url( '/events/' . $event['event_slug'] . '/' );
	} else {
		$event_url = home_url( '/?event_id=' . $event['event_id'] );
	}

	$formatted = array(
		'id'                => intval( $event['event_id'] ),
		'recurrence_id'     => isset( $event['recurrence_id'] ) ? intval( $event['recurrence_id'] ) : 0,
		'title'             => $event['event_name'],
		'description'       => $event['event_notes'],
		'start_date'        => $event['event_start'],
		'end_date'          => $event['event_end'],
		'status'            => eme_rest_format_status( $event['event_status'] ),
		'slug'              => $event['event_slug'],
		'url'               => $event_url,
		'location_id'       => intval( $event['location_id'] ),
		'category_ids'      => ! empty( $event['event_category_ids'] )
			? array_map( 'intval', explode( ',', $event['event_category_ids'] ) )
			: array(),
		'rsvp_enabled'      => (bool) $event['event_rsvp'],
		'seats'             => $event['event_seats'],
		'price'             => $event['price'],
		'currency'          => $event['currency'],
		'image_id'          => intval( $event['event_image_id'] ),
		'image_url'         => $event['event_image_url'],
		'contact_person_id' => intval( $event['event_contactperson_id'] ),
		'created_date'      => $event['creation_date'],
		'modified_date'     => $event['modif_date'],
	);

	// Skip location lookup to avoid slow eme_get_location() function
	// Clients can fetch location separately if needed via /locations/{id}

	return $formatted;
}

function eme_rest_format_location( $location ) {
	// EME stores address in location_address1 and location_address2
	// but may also have a computed location_address field
	$address = '';
	if ( isset( $location['location_address'] ) && ! empty( $location['location_address'] ) ) {
		$address = $location['location_address'];
	} elseif ( isset( $location['location_address1'] ) ) {
		$address = trim( $location['location_address1'] );
		if ( isset( $location['location_address2'] ) && ! empty( $location['location_address2'] ) ) {
			$address .= ( $address ? ' ' : '' ) . trim( $location['location_address2'] );
		}
	}

	return array(
		'id'        => intval( $location['location_id'] ),
		'name'      => $location['location_name'],
		'address'   => $address,
		'city'      => $location['location_city'],
		'state'     => $location['location_state'],
		'zip'       => $location['location_zip'],
		'country'   => $location['location_country'],
		'latitude'  => isset( $location['location_latitude'] ) ? $location['location_latitude'] : '',
		'longitude' => isset( $location['location_longitude'] ) ? $location['location_longitude'] : '',
	);
}

function eme_rest_format_category( $category ) {
	return array(
		'id'   => intval( $category['category_id'] ),
		'name' => $category['category_name'],
		'slug' => $category['category_slug'],
	);
}

// Recurrence endpoints.
function eme_rest_get_recurrences( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- REST callback signature.
	global $wpdb;
	// A recurrence ID identifies a pattern, not another event. Return one real
	// representative per pattern, retaining the existing event response shape.
	$events = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT e.* FROM {$wpdb->prefix}eme_events e INNER JOIN
        (SELECT recurrence_id, MIN(event_id) AS event_id FROM {$wpdb->prefix}eme_events
         WHERE recurrence_id > 0 AND (%d = 1 OR event_status = 1) GROUP BY recurrence_id) patterns
        ON patterns.event_id = e.event_id ORDER BY e.event_start DESC",
			current_user_can( 'edit_posts' ) ? 1 : 0
		),
		ARRAY_A
	);
	return rest_ensure_response( array_map( 'eme_rest_format_event', is_array( $events ) ? $events : array() ) );
}


function eme_rest_create_recurrence( $request ) {
	$params = $request->get_json_params();
	$title  = isset( $params['title'] ) ? sanitize_text_field( $params['title'] ) : '';
	if ( '' === $title ) {
		return new WP_Error( 'missing_title', 'Event title is required', array( 'status' => 400 ) );
	}
	if ( strlen( $title ) > 65535 ) {
		return new WP_Error( 'invalid_title', 'Event title must fit the native TEXT limit of 65535 bytes', array( 'status' => 400 ) );
	}
	$engine = eme_new_recurrence();
	if ( ! array_key_exists( 'specific_days', $engine ) || ! array_key_exists( 'exclude_days', $engine ) || ! defined( 'EME_VERSION' ) || version_compare( EME_VERSION, '3.2.26', '<' ) || ! defined( 'EME_DB_VERSION' ) || (int) get_option( 'eme_version' ) !== (int) EME_DB_VERSION ) {
		return new WP_Error( 'eme_upgrade_required', 'Upgrade and qualify Events Made Easy 3.2.26 or newer with its matching database schema before creating recurrences.', array( 'status' => 409 ) );
	}
	// Retain the nested API and accept the flat form documented by older releases.
	$pattern = isset( $params['recurrence'] ) ? $params['recurrence'] : $params;
	if ( ! is_array( $pattern ) ) {
		return new WP_Error( 'invalid_recurrence', 'Recurrence must be an object', array( 'status' => 400 ) );
	}
	$valid = eme_rest_validate_recurrence( $pattern );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}
	try {
		$recurrence  = eme_rest_parse_recurrence( $pattern );
		$date        = $recurrence['recurrence_start_date'];
		$start_input = isset( $pattern['start_date'] ) ? $pattern['start_date'] : $date;
		// A bound's date may never occur in the pattern. Validate its clock in
		// UTC first, without assigning an unused DST gap/fold to an actual event.
		$clock = eme_rest_datetime( $start_input, 'UTC' )->format( 'H:i:s' );
		if ( isset( $pattern['start_time'] ) ) {
			$clock = eme_rest_datetime( $date . ' ' . $pattern['start_time'], 'UTC' )->format( 'H:i:s' );
		} elseif ( isset( $params['start_time'] ) ) {
			$clock = eme_rest_datetime( $date . ' ' . $params['start_time'], 'UTC' )->format( 'H:i:s' );
		}
		global $eme_timezone;
		$timezone = new DateTimeZone( defined( 'EME_TIMEZONE' ) ? EME_TIMEZONE : ( $eme_timezone ? $eme_timezone : 'UTC' ) );
		$now      = new DateTimeImmutable( 'now', $timezone );
		$start    = null;
		foreach ( eme_get_recurrence_days( $recurrence ) as $day ) {
			$input = $day . ' ' . $clock;
			if ( 'specific' !== $recurrence['recurrence_freq'] && new DateTimeImmutable( $input, $timezone ) < $now ) {
				continue;
			}
			$start = eme_rest_datetime( $input );
			break;
		}
		if ( null === $start ) {
			throw new InvalidArgumentException( 'No recurring events match the requested future date range' );
		}
		$seconds = isset( $pattern['duration'] ) ? (int) $pattern['duration'] : 3600;
		if ( $seconds < 1 ) {
			throw new InvalidArgumentException( 'Duration must be positive seconds' );
		}
		list( $end, $duration_days ) = eme_rest_recurrence_times( $recurrence, $start, $seconds );
		// Native EME stores inclusive calendar days; the REST API accepts seconds.
		$recurrence['event_duration'] = $duration_days;
	} catch ( Exception $failure ) {
		return new WP_Error( 'invalid_recurrence', $failure->getMessage(), array( 'status' => 400 ) );
	}
	$event                     = eme_new_event();
	$event['event_properties'] = eme_init_event_props( array(), 1 );
	$event['event_name']       = $title;
	$event['event_notes']      = isset( $params['description'] ) ? wp_kses_post( $params['description'] ) : '';
	$event['event_status']     = isset( $params['status'] ) ? eme_rest_map_status( $params['status'] ) : 5;
	$event['event_author']     = get_current_user_id();
	$event['event_start']      = $start->format( 'Y-m-d H:i:s' );
	$event['event_end']        = $end->format( 'Y-m-d H:i:s' );
	foreach ( array(
		'location_id'       => 'location_id',
		'seats'             => 'event_seats',
		'image_id'          => 'event_image_id',
		'contact_person_id' => 'event_contactperson_id',
	) as $input => $field ) {
		if ( isset( $params[ $input ] ) ) {
			$event[ $field ] = (int) $params[ $input ];
		}
	}
	if ( isset( $params['category_ids'] ) ) {
		$event['event_category_ids'] = is_array( $params['category_ids'] ) ? implode( ',', array_map( 'intval', $params['category_ids'] ) ) : sanitize_text_field( $params['category_ids'] );
	}
	foreach ( array( 'price', 'currency' ) as $field ) {
		if ( isset( $params[ $field ] ) ) {
			$event[ $field ] = sanitize_text_field( $params[ $field ] );
		}
	}
	if ( isset( $params['rsvp'] ) ) {
		$event['event_rsvp'] = $params['rsvp'] ? 1 : 0;
	}
	if ( ! empty( $event['event_image_id'] ) ) {
		$event['event_image_url'] = wp_get_attachment_url( $event['event_image_id'] );
	}
	if ( isset( $params['url'] ) ) {
		$event['event_url'] = esc_url_raw( $params['url'] );
	}
	// Let EME generate weekly/monthly/specific occurrences, interval/exclusion
	// handling, native hooks and linked records together.
	$planned = array();
	$now     = new DateTimeImmutable( 'now', $start->getTimezone() );
	foreach ( eme_get_recurrence_days( $recurrence ) as $day ) {
		$input = $day . ' ' . $start->format( 'H:i:s' );
		if ( 'specific' !== $recurrence['recurrence_freq'] && new DateTimeImmutable( $input, $start->getTimezone() ) < $now ) {
			continue;
		}
		$occurrence_start = eme_rest_datetime( $input );
		$planned[]        = array(
			'event_start' => $occurrence_start->format( 'Y-m-d H:i:s' ),
			'event_end'   => $occurrence_start->modify( '+' . ( $recurrence['event_duration'] - 1 ) . ' days' )->format( 'Y-m-d' ) . ' ' . $end->format( 'H:i:s' ),
		);
	}
	if ( empty( $planned ) ) {
		return new WP_Error( 'creation_failed', 'No recurring events were created; check the date range', array( 'status' => 400 ) );
	}
	// EME otherwise continues with recurrence_id=0 after a failed pattern INSERT,
	// creating orphan single events. The transient identity is discarded by EME's
	// native event-column intersection and leaves unrelated hook-created events alone.
	$guard_identity                  = new stdClass();
	$event['_eme_rest_native_guard'] = $guard_identity;
	$missing_pattern                 = new RuntimeException( 'Native recurrence storage failed' );
	$guard                           = static function ( $candidate ) use ( $guard_identity, $missing_pattern ) {
		if ( isset( $candidate['_eme_rest_native_guard'] ) && $candidate['_eme_rest_native_guard'] === $guard_identity && empty( $candidate['recurrence_id'] ) ) {
			throw $missing_pattern;
		}
		return $candidate;
	};
	add_filter( 'eme_event_preinsert_filter', $guard, PHP_INT_MIN );
	try {
		$id = eme_db_insert_recurrence( $recurrence, $event );
	} catch ( RuntimeException $failure ) {
		if ( $failure !== $missing_pattern ) {
			throw $failure;
		}
		return new WP_Error( 'creation_failed', 'Native EME could not store the recurrence. No recurring events were created.', array( 'status' => 500 ) );
	} finally {
		remove_filter( 'eme_event_preinsert_filter', $guard, PHP_INT_MIN );
	}
	if ( ! $id ) {
		return new WP_Error( 'creation_failed', 'Native EME could not store the planned recurring events.', array( 'status' => 500 ) );
	}
	global $wpdb;
	$saved  = $wpdb->get_results( $wpdb->prepare( "SELECT event_id,event_start,event_end FROM {$wpdb->prefix}eme_events WHERE recurrence_id=%d ORDER BY event_start,event_id", $id ), ARRAY_A );
	$actual = array();
	foreach ( is_array( $saved ) ? $saved : array() as $row ) {
		$actual[] = array(
			'event_start' => $row['event_start'],
			'event_end'   => $row['event_end'],
		);
	}
	// Native EME counts attempted inserts, including failed ones. Its return ID
	// alone does not prove that every requested occurrence reached the database.
	if ( $actual !== $planned ) {
		eme_db_delete_recurrence( $id );
		$clean = 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}eme_recurrence WHERE recurrence_id=%d", $id ) );
		foreach ( is_array( $saved ) ? $saved : array() as $row ) {
			$clean = $clean && 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}eme_events WHERE event_id=%d AND event_status=0 AND recurrence_id=0", $row['event_id'] ) );
		}
		if ( ! $clean ) {
			return new WP_Error( 'creation_cleanup_failed', 'Native EME did not save every planned occurrence and cleanup could not be verified. Inspect the recurrence and event trash before retrying.', array( 'status' => 500 ) );
		}
		return new WP_Error( 'creation_failed', 'Native EME did not save every planned occurrence. The incomplete recurrence was removed and saved events were moved to trash.', array( 'status' => 500 ) );
	}
	$recurrence = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}eme_recurrence WHERE recurrence_id=%d", $id ), ARRAY_A );
	$first      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MIN(event_id) FROM {$wpdb->prefix}eme_events WHERE recurrence_id=%d", $id ) );
	return rest_ensure_response(
		array(
			'success'            => true,
			'event_id'           => $first,
			'recurrence_id'      => $id,
			'recurrence_pattern' => eme_rest_format_recurrence( $recurrence ),
			'message'            => 'Recurring events created successfully',
		)
	);
}


function eme_rest_get_recurrence( $request ) {
	global $wpdb;
	$id         = (int) $request['id'];
	$recurrence = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}eme_recurrence WHERE recurrence_id=%d", $id ), ARRAY_A );
	if ( ! $recurrence || ( ! current_user_can( 'edit_posts' ) && ! (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}eme_events WHERE recurrence_id=%d AND event_status IN (1,3)", $id ) ) ) ) {
		return new WP_Error( 'not_found', 'Recurrence pattern not found', array( 'status' => 404 ) );
	}
	return rest_ensure_response( eme_rest_format_recurrence( $recurrence ) );
}

function eme_rest_delete_recurrence( $request ) {
	$recurrence_id = intval( $request['id'] );

	// Check if recurrence exists.
	global $wpdb;
	$recurrence_table = $wpdb->prefix . 'eme_recurrence';

	$recurrence = $wpdb->get_row(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
		$wpdb->prepare( "SELECT * FROM $recurrence_table WHERE recurrence_id = %d", $recurrence_id ),
		ARRAY_A
	);

	if ( ! $recurrence ) {
		return new WP_Error( 'not_found', 'Recurrence pattern not found', array( 'status' => 404 ) );
	}

	// Use EME's lifecycle hooks and its native event/booking/task cleanup policy.
	if ( ! eme_db_delete_recurrence( $recurrence_id ) ) {
		return new WP_Error( 'deletion_failed', 'EME could not remove the recurrence; inspect its logs before retrying.', array( 'status' => 500 ) );
	}

	return rest_ensure_response(
		array(
			'success' => true,
			'message' => 'Recurrence removed and associated events moved to trash',
		)
	);
}

function eme_rest_get_recurrence_instances( $request ) {
	$pattern = eme_rest_get_recurrence( $request );
	if ( is_wp_error( $pattern ) ) {
		return $pattern;
	}
	try {
		$start = isset( $request['start_date'] ) ? eme_rest_datetime( $request['start_date'] ) : eme_rest_datetime( gmdate( 'Y-m-d' ) );
		$end   = isset( $request['end_date'] ) ? eme_rest_datetime( $request['end_date'] ) : $start->modify( '+1 year' );
		if ( $end < $start ) {
			throw new InvalidArgumentException( 'End date must not precede start date' );
		}
	} catch ( Exception $failure ) {
		return new WP_Error( 'invalid_date_range', $failure->getMessage(), array( 'status' => 400 ) );
	}
	global $wpdb;
	// Return actual generated occurrences, rather than a second recurrence
	// calculator with different interval, exclusion and duration semantics.
	$instances = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT event_start AS start,event_end AS end FROM {$wpdb->prefix}eme_events
        WHERE recurrence_id=%d AND event_end >= %s AND event_start <= %s AND (%d = 1 OR event_status IN (1,3))
        ORDER BY event_start,event_id",
			(int) $request['id'],
			$start->setTime( 0, 0 )->format( 'Y-m-d H:i:s' ),
			$end->setTime( 23, 59, 59 )->format( 'Y-m-d H:i:s' ),
			current_user_can( 'edit_posts' ) ? 1 : 0
		),
		ARRAY_A
	);
	return rest_ensure_response(
		array(
			'recurrence_id' => (int) $request['id'],
			'pattern'       => $pattern->get_data(),
			'instances'     => $instances,
			'count'         => count( $instances ),
		)
	);
}

function eme_rest_validate_recurrence( $recurrence ) {
	// Validate frequency
	$valid_frequencies = array( 'weekly', 'monthly', 'specific' );
	if ( empty( $recurrence['frequency'] ) || ! in_array( $recurrence['frequency'], $valid_frequencies, true ) ) {
		return new WP_Error( 'invalid_frequency', 'Frequency must be one of: weekly, monthly, specific', array( 'status' => 400 ) );
	}

	// Validate dates for weekly/monthly
	if ( in_array( $recurrence['frequency'], array( 'weekly', 'monthly' ), true ) ) {
		if ( empty( $recurrence['start_date'] ) ) {
			return new WP_Error( 'missing_start_date', 'Start date is required', array( 'status' => 400 ) );
		}
		if ( empty( $recurrence['end_date'] ) ) {
			return new WP_Error( 'missing_end_date', 'End date is required', array( 'status' => 400 ) );
		}
	}

	// Validate interval
	if ( isset( $recurrence['interval'] ) && ( ! is_numeric( $recurrence['interval'] ) || floor( (float) $recurrence['interval'] ) !== (float) $recurrence['interval'] || 1 > $recurrence['interval'] || 127 < $recurrence['interval'] ) ) {
		return new WP_Error( 'invalid_interval', 'Interval must be an integer from 1 to 127', array( 'status' => 400 ) );
	}

	// Validate weekly pattern
	if ( 'weekly' === $recurrence['frequency'] ) {
		if ( empty( $recurrence['days_of_week'] ) ) {
			return new WP_Error( 'missing_days', 'Days of week are required for weekly recurrence', array( 'status' => 400 ) );
		}
	}

	// Validate monthly pattern.
	if ( 'monthly' === $recurrence['frequency'] ) {
		if ( ! isset( $recurrence['day_of_week'] ) || '' === (string) $recurrence['day_of_week'] || ! isset( $recurrence['week_of_month'] ) ) {
			return new WP_Error( 'missing_monthly_pattern', 'Day of week and week of month are required', array( 'status' => 400 ) );
		}
	}

	// Validate specific days
	if ( 'specific' === $recurrence['frequency'] ) {
		if ( empty( $recurrence['dates'] ) ) {
			return new WP_Error( 'missing_dates', 'Specific dates are required', array( 'status' => 400 ) );
		}
	}

	return true;
}


function eme_rest_parse_recurrence( $pattern ) {
	$data                        = eme_new_recurrence();
	$data['recurrence_freq']     = $pattern['frequency'];
	$data['recurrence_interval'] = isset( $pattern['interval'] ) ? (int) $pattern['interval'] : 1;
	$days                        = array(
		'sunday'    => 7,
		'monday'    => 1,
		'tuesday'   => 2,
		'wednesday' => 3,
		'thursday'  => 4,
		'friday'    => 5,
		'saturday'  => 6,
	);
	if ( 'specific' === $pattern['frequency'] ) {
		$dates = is_array( $pattern['dates'] ) ? $pattern['dates'] : explode( ',', $pattern['dates'] );
		$dates = array_map(
			function ( $date ) {
				return eme_rest_datetime( $date, 'UTC' )->format( 'Y-m-d' );
			},
			$dates
		);
		sort( $dates );
		$data['specific_days']         = implode( ',', array_unique( $dates ) );
		$data['recurrence_start_date'] = reset( $dates );
		$data['recurrence_end_date']   = end( $dates );
	} else {
		$data['recurrence_start_date'] = eme_rest_datetime( $pattern['start_date'], 'UTC' )->format( 'Y-m-d' );
		$data['recurrence_end_date']   = eme_rest_datetime( $pattern['end_date'], 'UTC' )->format( 'Y-m-d' );
		if ( $data['recurrence_end_date'] < $data['recurrence_start_date'] ) {
			throw new InvalidArgumentException( 'End date must not precede start date' );
		}
	}
	if ( 'weekly' === $pattern['frequency'] ) {
		$requested = is_array( $pattern['days_of_week'] ) ? $pattern['days_of_week'] : explode( ',', $pattern['days_of_week'] );
		$codes     = array();
		foreach ( $requested as $day ) {
			$day = strtolower( trim( (string) $day ) );
			if ( isset( $days[ $day ] ) ) {
				$codes[] = $days[ $day ];
			} elseif ( preg_match( '/^[0-7]$/', $day ) ) {
				$codes[] = '0' === $day ? 7 : (int) $day;
			} else {
				throw new InvalidArgumentException( 'Invalid day of week' );
			}
		}
		$data['recurrence_byday'] = implode( ',', array_unique( $codes ) );
	} elseif ( 'monthly' === $pattern['frequency'] ) {
		$day = strtolower( trim( (string) $pattern['day_of_week'] ) );
		if ( ! isset( $days[ $day ] ) && ! preg_match( '/^[0-7]$/', $day ) ) {
			throw new InvalidArgumentException( 'Invalid day of week' );
		}
		$week = (int) $pattern['week_of_month'];
		if ( ! in_array( $week, array( -1, 1, 2, 3, 4, 5 ), true ) ) {
			throw new InvalidArgumentException( 'Week of month must be 1-5 or -1 for last' );
		}
		$data['recurrence_byday']    = isset( $days[ $day ] ) ? $days[ $day ] : ( '0' === $day ? 7 : (int) $day );
		$data['recurrence_byweekno'] = $week;
	}
	if ( isset( $pattern['exclude_days'] ) ) {
		$exclude              = is_array( $pattern['exclude_days'] ) ? $pattern['exclude_days'] : explode( ',', $pattern['exclude_days'] );
		$data['exclude_days'] = implode(
			',',
			array_map(
				function ( $date ) {
					return eme_rest_datetime( $date, 'UTC' )->format( 'Y-m-d' );
				},
				$exclude
			)
		);
	}
	return $data;
}


function eme_rest_format_recurrence( $recurrence ) {
	global $wpdb;
	$id        = (int) $recurrence['recurrence_id'];
	$event     = $wpdb->get_row( $wpdb->prepare( "SELECT event_start,event_end FROM {$wpdb->prefix}eme_events WHERE recurrence_id=%d AND (%d=1 OR event_status IN (1,3)) ORDER BY event_start,event_id LIMIT 1", $id, current_user_can( 'edit_posts' ) ? 1 : 0 ), ARRAY_A );
	$seconds   = $event ? eme_rest_datetime( $event['event_end'] )->getTimestamp() - eme_rest_datetime( $event['event_start'] )->getTimestamp() : 0;
	$formatted = array(
		'id'            => $id,
		'frequency'     => $recurrence['recurrence_freq'],
		'interval'      => (int) $recurrence['recurrence_interval'],
		'duration'      => $seconds,
		'duration_days' => (int) $recurrence['event_duration'],
	);
	foreach ( array(
		'recurrence_start_date' => 'start_date',
		'recurrence_end_date'   => 'end_date',
		'recurrence_byday'      => 'days_of_week_codes',
		'recurrence_byweekno'   => 'week_of_month',
	) as $key => $field ) {
		if ( isset( $recurrence[ $key ] ) ) {
			$formatted[ $field ] = 'week_of_month' === $field ? (int) $recurrence[ $key ] : $recurrence[ $key ];
		}
	}
	if ( ! empty( $recurrence['specific_days'] ) ) {
		$formatted['specific_dates'] = explode( ',', $recurrence['specific_days'] );
	}
	if ( ! empty( $recurrence['exclude_days'] ) ) {
		$formatted['excluded_dates'] = explode( ',', $recurrence['exclude_days'] );
	}
	return $formatted;
}



function eme_rest_calculate_instances( $recurrence, $start_date, $end_date ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Retained legacy helper signature; API now reads stored occurrences.
	// Deprecated compatibility helper only. REST endpoints must read actual
	// stored occurrences and must not call this older recurrence calculator.

	$instances = array();
	$freq      = $recurrence['recurrence_freq'];

	if ( 'weekly' === $freq ) {
		// Calculate weekly instances.
		$current      = strtotime( $recurrence['recurrence_start_date'] );
		$end          = strtotime( $recurrence['recurrence_end_date'] );
		$days_of_week = explode( ',', $recurrence['recurrence_byday'] );

		while ( $current <= $end ) {
			$day_of_week = gmdate( 'w', $current );
			if ( in_array( $day_of_week, $days_of_week, true ) ) {
				$instances[] = array(
					'start' => gmdate( 'Y-m-d H:i:s', $current ),
					'end'   => gmdate( 'Y-m-d H:i:s', $current + intval( $recurrence['event_duration'] ) ),
				);
			}
			$current += 86400; // next day.
		}
	} elseif ( 'specific' === $freq ) {
		// Specific dates.
		$dates = explode( ',', $recurrence['specific_days'] );
		foreach ( $dates as $specific_date ) {
			$timestamp   = strtotime( $specific_date );
			$instances[] = array(
				'start' => gmdate( 'Y-m-d H:i:s', $timestamp ),
				'end'   => gmdate( 'Y-m-d H:i:s', $timestamp + intval( $recurrence['event_duration'] ) ),
			);
		}
	}

	return $instances;
}

// Dates use the EME site's timezone unless an internal calendar/clock parse
// explicitly requests UTC. Reject relative or normalized invalid inputs.
function eme_rest_datetime( $input, $timezone = null ) {
	$input = trim( (string) $input );
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/', $input ) ) {
		throw new InvalidArgumentException( 'Use an ISO date or local date/time' );
	}
	global $eme_timezone;
	$zone       = new DateTimeZone( null !== $timezone ? $timezone : ( defined( 'EME_TIMEZONE' ) ? EME_TIMEZONE : ( $eme_timezone ? $eme_timezone : 'UTC' ) ) );
	$date       = new DateTimeImmutable( $input, $zone );
	$normalized = str_replace( 'T', ' ', $input );
	$format     = strlen( $normalized ) === 10 ? 'Y-m-d' : ( strlen( $normalized ) === 16 ? 'Y-m-d H:i' : 'Y-m-d H:i:s' );
	if ( $date->format( $format ) !== $normalized ) {
		throw new InvalidArgumentException( 'Invalid calendar date or nonexistent local time' );
	}
	return $date;
}

function eme_rest_recurrence_times( $recurrence, $start, $seconds ) {
	// Adding wall-clock seconds can silently double an elapsed hour at the fall
	// transition. Native EME stores local datetimes without UTC offsets instead.
	$end           = $start->setTimestamp( $start->getTimestamp() + $seconds );
	$duration_days = (int) $start->setTime( 0, 0 )->diff( $end->setTime( 0, 0 ) )->days + 1;
	if ( eme_rest_datetime( $end->format( 'Y-m-d H:i:s' ) )->getTimestamp() !== $end->getTimestamp() ) {
		throw new InvalidArgumentException( 'This elapsed duration cannot be represented by native local times across daylight saving. Split the pattern or choose different times.' );
	}
	$now = new DateTimeImmutable( 'now', $start->getTimezone() );
	foreach ( eme_get_recurrence_days( $recurrence ) as $day ) {
		$input = $day . ' ' . $start->format( 'H:i:s' );
		if ( 'specific' !== $recurrence['recurrence_freq'] && new DateTimeImmutable( $input, $start->getTimezone() ) < $now ) {
			continue;
		}
		$occurrence_start = eme_rest_datetime( $input );
		$end_day          = $occurrence_start->modify( '+' . ( $duration_days - 1 ) . ' days' )->format( 'Y-m-d' );
		$occurrence_end   = eme_rest_datetime( $end_day . ' ' . $end->format( 'H:i:s' ) );
		if ( $occurrence_end->getTimestamp() - $occurrence_start->getTimestamp() !== $seconds ) {
			throw new InvalidArgumentException( 'The requested seconds vary across this native local-time pattern. Split the pattern around daylight saving or choose different times.' );
		}
	}
	return array( $end, $duration_days );
}
