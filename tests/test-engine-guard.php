<?php
// Real WordPress regression: an unqualified legacy engine must receive no writes.
if ( 'staging' !== wp_get_environment_type() || ! preg_match( '#^localhost:/home/[a-z0-9_]+/\.php-upgrade/eme-[a-zA-Z0-9-]+/mysql\.sock$#', DB_HOST ) ) {
	throw new RuntimeException( 'Run this fixture only in the private socket-only EME rehearsal.' );
}
global $wpdb;
$guard_event_max   = (int) $wpdb->get_var( "SELECT MAX(event_id) FROM {$wpdb->prefix}eme_events" );
$guard_pattern_max = (int) $wpdb->get_var( "SELECT MAX(recurrence_id) FROM {$wpdb->prefix}eme_recurrence" );
$guard_before      = array(
	(int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eme_events" ),
	(int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eme_recurrence" ),
);
$guard_admins      = get_super_admins();
wp_set_current_user( get_user_by( 'login', $guard_admins[0] )->ID );
try {
	$guard_request = new WP_REST_Request( 'POST', '/eme/v1/eme_recurrences' );
	$guard_request->set_header( 'content-type', 'application/json' );
	$guard_request->set_body(
		wp_json_encode(
			array(
				'title'      => 'Private engine guard fixture',
				'status'     => 'draft',
				'recurrence' => array(
					'frequency'    => 'weekly',
					'start_date'   => '2030-01-07',
					'end_date'     => '2030-01-21',
					'days_of_week' => array( 'monday' ),
				),
			)
		)
	);
	$guard_response = rest_do_request( $guard_request );
	$guard_after    = array(
		(int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eme_events" ),
		(int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eme_recurrence" ),
	);
	$guard_pass     = 409 === $guard_response->get_status() && $guard_before === $guard_after;
	echo 'EME_ENGINE_GUARD=' . wp_json_encode(
		array(
			'ok'       => $guard_pass,
			'status'   => $guard_response->get_status(),
			'noWrites' => $guard_before === $guard_after,
		)
	) . "\n";
} finally {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}eme_events WHERE event_id>%d", $guard_event_max ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}eme_recurrence WHERE recurrence_id>%d", $guard_pattern_max ) );
}
if ( ! $guard_pass ) {
	exit( 1 );
}
