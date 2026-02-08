<?php
/**
 * Upstream Compatibility Test for Events Made Easy REST API.
 *
 * Verifies that the upstream EME plugin still provides the functions, table
 * structures, and return shapes that eme-rest-api depends on.
 *
 * Run inside WordPress via WP-CLI:
 *   wp eval-file tests/test-upstream-compat.php
 *
 * Exit code: 0 = all pass, 1 = failures detected.
 *
 * @package EME_REST_API
 */

// ─── Minimal test harness ───
// Note: wp eval-file includes this file inside a function scope, so top-level
// variables are LOCAL. We must declare them global so the helper functions
// (which also use `global`) share the same counters.

global $compat_passed, $compat_failed, $compat_skipped;
$compat_passed  = 0;
$compat_failed  = 0;
$compat_skipped = 0;

function compat_pass( $msg ) {
	global $compat_passed;
	++$compat_passed;
	echo "  [PASS] $msg\n";
}

function compat_fail( $msg ) {
	global $compat_failed;
	++$compat_failed;
	echo "  [FAIL] $msg\n";
}

function compat_skip( $msg ) {
	global $compat_skipped;
	++$compat_skipped;
	echo "  [SKIP] $msg\n";
}

function assert_true( $condition, $msg ) {
	if ( $condition ) {
		compat_pass( $msg );
	} else {
		compat_fail( $msg );
	}
}

function assert_equal( $expected, $actual, $msg ) {
	if ( $expected === $actual ) {
		compat_pass( $msg );
	} else {
		compat_fail( "$msg (expected: $expected, got: $actual)" );
	}
}

// ─── Load .upstream metadata ───

$upstream_file = dirname( __DIR__ ) . '/.upstream';
if ( ! file_exists( $upstream_file ) ) {
	echo "[ERROR] .upstream file not found at $upstream_file\n";
	exit( 1 );
}

$upstream = json_decode( file_get_contents( $upstream_file ), true );
if ( ! $upstream ) {
	echo "[ERROR] Failed to parse .upstream JSON\n";
	exit( 1 );
}

// ─── Detect installed version ───

$installed_version = 'unknown';
if ( function_exists( 'get_plugins' ) ) {
	$plugins = get_plugins();
	foreach ( $plugins as $file => $data ) {
		if ( strpos( $file, 'events-made-easy' ) !== false ) {
			$installed_version = $data['Version'];
			break;
		}
	}
} else {
	// Fallback: read the plugin file header directly.
	$eme_main = WP_PLUGIN_DIR . '/events-made-easy/events-made-easy.php';
	if ( file_exists( $eme_main ) ) {
		$header = get_file_data( $eme_main, array( 'Version' => 'Version' ) );
		if ( ! empty( $header['Version'] ) ) {
			$installed_version = $header['Version'];
		}
	}
}

echo "\n=== Upstream Compatibility: eme-rest-api ===\n";
echo "Tested against: {$upstream['parent_plugin']} {$upstream['tested_version']}\n";
echo "Installed:      {$upstream['parent_plugin']} $installed_version\n";

if ( $installed_version !== $upstream['tested_version'] ) {
	echo "  ** VERSION DRIFT DETECTED **\n";
}
echo "\n";

// ─── Check: Is EME active? ───

if ( ! function_exists( 'eme_new_event' ) ) {
	echo "[ERROR] Events Made Easy is not active. Cannot run compatibility tests.\n";
	echo "        Activate the plugin and try again.\n";
	exit( 1 );
}

// ─── Layer 1: Function existence ───

echo "--- Layer 1: Function Existence ---\n";
foreach ( $upstream['functions_used'] as $func ) {
	assert_true( function_exists( $func ), "$func() exists" );
}

// Optional functions — used with function_exists() guard in our code.
$optional = isset( $upstream['optional_functions'] ) ? $upstream['optional_functions'] : array();
foreach ( $optional as $func ) {
	if ( function_exists( $func ) ) {
		compat_pass( "$func() exists (optional)" );
	} else {
		compat_skip( "$func() not available (optional, guarded with function_exists)" );
	}
}
echo "\n";

// ─── Layer 2: Function signatures ───

echo "--- Layer 2: Function Signatures ---\n";

// eme_get_events() — we call it with up to 11 positional args.
if ( function_exists( 'eme_get_events' ) ) {
	$ref = new ReflectionFunction( 'eme_get_events' );
	$param_count = $ref->getNumberOfParameters();
	assert_true(
		$param_count >= 11,
		"eme_get_events() accepts >= 11 parameters (got $param_count)"
	);
} else {
	compat_skip( 'eme_get_events signature (function missing)' );
}

// eme_new_event() — should return an array (blank event template).
if ( function_exists( 'eme_new_event' ) ) {
	$event = eme_new_event();
	assert_true( is_array( $event ), 'eme_new_event() returns array' );
} else {
	compat_skip( 'eme_new_event return type (function missing)' );
}

// eme_new_location() — should return an array.
if ( function_exists( 'eme_new_location' ) ) {
	$location = eme_new_location();
	assert_true( is_array( $location ), 'eme_new_location() returns array' );
} else {
	compat_skip( 'eme_new_location return type (function missing)' );
}
echo "\n";

// ─── Layer 3: Return shape — event fields ───

echo "--- Layer 3: Event Return Shape ---\n";
if ( function_exists( 'eme_new_event' ) ) {
	$event = eme_new_event();

	$required_event_fields = array(
		'event_name',
		'event_notes',
		'event_start',
		'event_end',
		'event_status',
		'location_id',
		'event_category_ids',
		'event_rsvp',
		'event_seats',
		'event_url',
		'event_slug',
		'event_image_id',
		'event_contactperson_id',
		'event_author',
	);

	foreach ( $required_event_fields as $field ) {
		assert_true(
			array_key_exists( $field, $event ),
			"event has '$field' field"
		);
	}
} else {
	compat_skip( 'Event return shape (eme_new_event missing)' );
}
echo "\n";

// ─── Layer 3b: Return shape — location fields ───

echo "--- Layer 3b: Location Return Shape ---\n";
if ( function_exists( 'eme_new_location' ) ) {
	$location = eme_new_location();

	$required_location_fields = array(
		'location_name',
		'location_address1',
		'location_city',
		'location_state',
		'location_zip',
		'location_country',
		'location_latitude',
		'location_longitude',
	);

	foreach ( $required_location_fields as $field ) {
		assert_true(
			array_key_exists( $field, $location ),
			"location has '$field' field"
		);
	}
} else {
	compat_skip( 'Location return shape (eme_new_location missing)' );
}
echo "\n";

// ─── Layer 4: Database tables ───

echo "--- Layer 4: Database Tables ---\n";
global $wpdb;
foreach ( $upstream['tables_used'] as $table ) {
	$full_table = $wpdb->prefix . $table;
	$exists     = $wpdb->get_var( "SHOW TABLES LIKE '$full_table'" );
	assert_true( $exists === $full_table, "Table $full_table exists" );
}
echo "\n";

// ─── Layer 5: Status code constants ───

echo "--- Layer 5: Status Code Constants ---\n";
$expected_statuses = $upstream['status_codes'];

// Check if EME defines status constants.
$constant_map = array(
	'public'   => 'EME_EVENT_STATUS_PUBLIC',
	'draft'    => 'EME_EVENT_STATUS_DRAFT',
	'trash'    => 'EME_EVENT_STATUS_TRASH',
	'private'  => 'EME_EVENT_STATUS_PRIVATE',
	'unlisted' => 'EME_EVENT_STATUS_UNLISTED',
	'fs_draft' => 'EME_EVENT_STATUS_FS_DRAFT',
);

$constants_found = false;
foreach ( $constant_map as $key => $const_name ) {
	if ( defined( $const_name ) ) {
		$constants_found = true;
		assert_equal(
			$expected_statuses[ $key ],
			constant( $const_name ),
			"$const_name = {$expected_statuses[ $key ]}"
		);
	}
}

if ( ! $constants_found ) {
	// EME may not define public constants — verify via the status map in our own code.
	compat_skip( 'EME status constants not defined as public PHP constants (we use hardcoded values)' );
}
echo "\n";

// ─── Summary ───

global $compat_passed, $compat_failed, $compat_skipped;
$total = $compat_passed + $compat_failed;
echo "=== Results: $compat_passed/$total passed";
if ( $compat_failed > 0 ) {
	echo ", $compat_failed failed";
}
if ( $compat_skipped > 0 ) {
	echo ", $compat_skipped skipped";
}
echo " ===\n\n";

if ( $compat_failed > 0 ) {
	exit( 1 );
}
exit( 0 );
