<?php
/** Native private fixtures. Use the stage's /bBLOG/ URL, never its live domain alias. */
if ( wp_get_environment_type() !== 'staging' || ! preg_match( '#^localhost:/home/[a-z0-9_]+/\.php-upgrade/eme-[a-zA-Z0-9-]+/mysql\.sock$#', DB_HOST ) ) {
	throw new RuntimeException( 'Use only the isolated socket-only EME rehearsal.' );
}
global $wpdb;
$results   = array();
$users     = array();
$admins    = get_super_admins();
$admin     = get_user_by( 'login', $admins[0] );
$max_event = (int) $wpdb->get_var( "SELECT MAX(event_id) FROM {$wpdb->prefix}eme_events" );
$max_rec   = (int) $wpdb->get_var( "SELECT MAX(recurrence_id) FROM {$wpdb->prefix}eme_recurrence" );
$options   = array();
foreach ( array( 'eme_cap_add_event', 'eme_cap_publish_event', 'eme_cap_author_event', 'eme_cap_edit_events' ) as $key ) {
	$options[ $key ] = get_option( $key );
}
$call    = static function ( $method, $route, $payload = array() ) {
	$request = new WP_REST_Request( $method, '/eme/v1/' . $route );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_body( wp_json_encode( $payload ) );
	return rest_do_request( $request );
};
$record  = static function ( $name, $ok ) use ( &$results ) {
	$results[] = array(
		'case' => $name,
		'ok'   => (bool) $ok,
	);
};
$pattern = array(
	'frequency'  => 'specific',
	'dates'      => array( '2030-01-07', '2030-01-14' ),
	'duration'   => 3600,
	'start_time' => '10:00:00',
);
$create  = static function () use ( $call, $pattern ) {
	$response = $call(
		'POST',
		'eme_recurrences',
		array(
			'title'      => 'Private capability fixture',
			'status'     => 'published',
			'recurrence' => $pattern,
		)
	);
	if ( 200 !== $response->get_status() ) {
		throw new RuntimeException( 'Fixture creation failed: ' . wp_json_encode( $response->get_data() ) );
	}
	return $response->get_data();
};
try {
	foreach ( array( 'contributor', 'author', 'subscriber' ) as $fixture_role ) {
		$fixture_user_id = wp_insert_user(
			array(
				'user_login' => 'eme_fixture_' . bin2hex( random_bytes( 5 ) ),
				'user_pass'  => wp_generate_password( 32 ),
				'role'       => $fixture_role,
			)
		);
		if ( is_wp_error( $fixture_user_id ) ) {
			throw new RuntimeException( 'Cannot create private role fixture' ); }
		$users[ $fixture_role ] = (int) $fixture_user_id;
	}
	wp_set_current_user( $users['contributor'] );
	$draft    = $create();
	$rec_id   = (int) $draft['recurrence_id'];
	$statuses = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT event_status FROM {$wpdb->prefix}eme_events WHERE recurrence_id=%d", $rec_id ) );
	$record( 'contributor-recurrence-forced-draft', array( '5' ) === array_map( 'strval', $statuses ) );
	$single    = $call(
		'POST',
		'eme_events',
		array(
			'title'      => 'Private capability event',
			'start_date' => '2030-01-07',
			'status'     => 'published',
		)
	)->get_data();
	$single_id = (int) $single['event_id'];
	$record( 'contributor-event-forced-draft', 5 === (int) eme_get_event( $single_id )['event_status'] );
	wp_set_current_user( $admin->ID );
	$record( 'missing-recurrence-delete-returns-not-found', 404 === $call( 'DELETE', 'eme_recurrences/2147483647' )->get_status() );
	$owned       = $create();
	$owned_id    = (int) $owned['recurrence_id'];
	$owned_event = (int) $owned['event_id'];
	wp_set_current_user( $users['contributor'] );
	$denied = $call( 'DELETE', 'eme_recurrences/' . $owned_id );
	$record( 'contributor-cannot-delete-other-pattern', 403 === $denied->get_status() && 2 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}eme_events WHERE recurrence_id=%d AND event_status=1", $owned_id ) ) );
	$record( 'contributor-cannot-edit-other-event', 403 === $call( 'PUT', 'eme_events/' . $owned_event, array( 'title' => 'Unauthorized edit' ) )->get_status() );
	$record( 'contributor-cannot-delete-other-event', 403 === $call( 'DELETE', 'eme_events/' . $owned_event )->get_status() );
	$record( 'contributor-cannot-change-other-event-status', 403 === $call( 'PUT', 'eme_events/' . $owned_event, array( 'status' => 'published' ) )->get_status() );
	wp_set_current_user( $users['author'] );
	$record( 'author-cannot-delete-other-pattern', 403 === $call( 'DELETE', 'eme_recurrences/' . $owned_id )->get_status() );
	$own = $create();
	$record( 'author-can-delete-own-pattern', 200 === $call( 'DELETE', 'eme_recurrences/' . $own['recurrence_id'] )->get_status() );
	wp_set_current_user( $admin->ID );
	$private = $create();
	$wpdb->update( $wpdb->prefix . 'eme_events', array( 'event_status' => 2 ), array( 'event_id' => (int) $private['event_id'] ) );
	wp_set_current_user( 0 );
	$public = $call( 'GET', 'eme_recurrences/' . $private['recurrence_id'] )->get_data();
	$record( 'public-pattern-omits-private-schedule', ! array_intersect( array( 'frequency', 'interval', 'start_date', 'end_date', 'days_of_week_codes', 'week_of_month', 'specific_dates', 'excluded_dates' ), array_keys( $public ) ) );
	$instances = $call( 'GET', 'eme_recurrences/' . $private['recurrence_id'] . '/instances' );
	$record( 'public-pattern-does-not-contain-hidden-date', false === strpos( wp_json_encode( $public ), '2030-01-07' ) );
	wp_set_current_user( $admin->ID );
	$staff = $call( 'GET', 'eme_recurrences/' . $private['recurrence_id'] )->get_data();
	$record( 'editor-retains-complete-pattern', count( $staff['specific_dates'] ?? array() ) === 2 );
	update_option( 'eme_cap_add_event', 'eme_fixture_add' );
	update_option( 'eme_cap_publish_event', 'eme_fixture_publish' );
	update_option( 'eme_cap_author_event', 'eme_fixture_author' );
	update_option( 'eme_cap_edit_events', 'eme_fixture_edit' );
	$custom = new WP_User( $users['subscriber'] );
	foreach ( array( 'eme_fixture_add', 'eme_fixture_publish', 'eme_fixture_author', 'eme_fixture_edit' ) as $cap ) {
		$custom->add_cap( $cap ); }
	wp_set_current_user( $custom->ID );
	$custom_response = $call(
		'POST',
		'eme_recurrences',
		array(
			'title'      => 'Custom capability fixture',
			'status'     => 'published',
			'recurrence' => $pattern,
		)
	);
	$record( 'configured-create-capability-without-edit-posts', 200 === $custom_response->get_status() );
	if ( 200 === $custom_response->get_status() ) {
		$custom_data = $custom_response->get_data();
		$record( 'configured-publish-capability', 1 === (int) eme_get_event( (int) $custom_data['event_id'] )['event_status'] );
		$record( 'configured-editor-can-delete-pattern', 200 === $call( 'DELETE', 'eme_recurrences/' . $private['recurrence_id'] )->get_status() );
	}
	foreach ( array( 'eme_fixture_add', 'eme_fixture_publish', 'eme_fixture_author' ) as $cap ) {
		$custom->remove_cap( $cap );
	}
	wp_set_current_user( 0 );
	wp_set_current_user( $custom->ID );
	$editor_response = $call(
		'POST',
		'eme_recurrences',
		array(
			'title'      => 'Edit-all-only fixture',
			'status'     => 'published',
			'recurrence' => $pattern,
		)
	);
	$record( 'configured-editor-only-can-create', 200 === $editor_response->get_status() );
	if ( 200 === $editor_response->get_status() ) {
		$editor_data = $editor_response->get_data();
		$record( 'editor-without-publish-forced-draft', 5 === (int) eme_get_event( (int) $editor_data['event_id'] )['event_status'] );
		$editor_read = $call( 'GET', 'eme_recurrences/' . $editor_data['recurrence_id'] );
		$record( 'configured-editor-can-read-private-pattern', 200 === $editor_read->get_status() && count( $editor_read->get_data()['specific_dates'] ?? array() ) === 2 );
	}
	$edit = $call( 'PUT', 'eme_events/' . $owned_event, array( 'title' => 'Edited without publish permission' ) );
	$record( 'editor-update-without-publish-becomes-draft', 200 === $edit->get_status() && 'draft' === ( $edit->get_data()['event']['status'] ?? '' ) );
} finally {
	wp_set_current_user( $admin->ID );
	foreach ( $options as $key => $value ) {
		update_option( $key, $value ); }
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}eme_events WHERE event_id>%d", $max_event ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}eme_recurrence WHERE recurrence_id>%d", $max_rec ) );
	foreach ( $users as $fixture_user_id ) {
		$wpdb->delete( $wpdb->usermeta, array( 'user_id' => $fixture_user_id ) );
		$wpdb->delete( $wpdb->users, array( 'ID' => $fixture_user_id ) );
		clean_user_cache( $fixture_user_id ); }
}
echo 'EME_CAPABILITIES=' . wp_json_encode( $results ) . "\n";
if ( array_filter(
	$results,
	static function ( $r ) {
		return ! $r['ok'];
	}
) ) {
	exit( 1 ); }
