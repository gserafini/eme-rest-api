<?php
/** Functional API contracts. Run only inside the isolated Ops rehearsal. */
if ( wp_get_environment_type() !== 'staging' || ! preg_match( '#^localhost:/home/[a-z0-9_]+/\.php-upgrade/eme-[a-zA-Z0-9-]+/mysql\.sock$#', DB_HOST ) ) {
	throw new RuntimeException( 'Functional fixtures require the private socket-only EME rehearsal; never run them against a live database.' );
}
global $wpdb;
$functional_results       = array();
$functional_admins        = get_super_admins();
$functional_admin         = get_user_by( 'login', $functional_admins[0] );
$functional_call          = function ( $method, $route, $payload = array() ) {
	$request = new WP_REST_Request( $method, '/eme/v1/' . $route );
	if ( 'GET' === $method ) {
		$request->set_query_params( $payload );
	} else {
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $payload ) ); }
	return rest_do_request( $request );
};
$functional_assert        = function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI test diagnostics, never HTML.
	} };
$functional_prefix        = 'EME REST fixture ' . bin2hex( random_bytes( 6 ) );
$functional_event_max     = (int) $wpdb->get_var( "SELECT MAX(event_id) FROM {$wpdb->prefix}eme_events" );
$functional_rec_max       = (int) $wpdb->get_var( "SELECT MAX(recurrence_id) FROM {$wpdb->prefix}eme_recurrence" );
$functional_events_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eme_events" );
$functional_recs_before   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eme_recurrence" );
$functional_year          = (int) gmdate( 'Y' ) + 2;
$functional_monday        = new DateTimeImmutable( 'first monday of January ' . $functional_year );
$functional_start         = $functional_monday->format( 'Y-m-d' );
try {
	wp_set_current_user( $functional_admin->ID );
	$response = $functional_call(
		'POST',
		'eme_events',
		array(
			'title'      => $functional_prefix . ' draft',
			'start_date' => $functional_start,
			'status'     => 'draft',
		)
	);
	$draft    = (int) ( $response->get_data()['event_id'] ?? 0 );
	$functional_assert( $draft > 0, 'Draft fixture creation failed' );
	wp_set_current_user( 0 );
	$anonymous            = $functional_call( 'GET', 'eme_events/' . $draft );
	$functional_results[] = array(
		'case'   => 'anonymous-draft-read',
		'ok'     => $anonymous->get_status() === 404,
		'status' => $anonymous->get_status(),
	);
	foreach ( array( 0, 2, 3, 5, 6, 1 ) as $fixture_status ) {
		wp_set_current_user( $functional_admin->ID );
		$functional_call( 'PUT', 'eme_events/' . $draft, array( 'status' => eme_rest_format_status( $fixture_status ) ) );
		wp_set_current_user( 0 );
		$read                 = $functional_call( 'GET', 'eme_events/' . $draft );
		$expected             = in_array( $fixture_status, array( 1, 3 ), true ) ? 200 : 404;
		$functional_results[] = array(
			'case' => 'anonymous-status-' . $fixture_status,
			'ok'   => $read->get_status() === $expected,
		);
	}
	wp_set_current_user( $functional_admin->ID );
	$functional_call( 'PUT', 'eme_events/' . $draft, array( 'status' => 'draft' ) );
	wp_set_current_user( 0 );
	$denied               = $functional_call(
		'POST',
		'eme_events',
		array(
			'title'      => 'Denied',
			'start_date' => $functional_start,
		)
	);
	$functional_results[] = array(
		'case' => 'anonymous-write',
		'ok'   => in_array( $denied->get_status(), array( 401, 403 ), true ),
	);
	wp_set_current_user( $functional_admin->ID );
	$functional_assert( $functional_call( 'GET', 'eme_events/' . $draft )->get_status() === 200, 'Editor cannot read own draft' );
	$changed = $functional_call( 'PUT', 'eme_events/' . $draft, array( 'title' => $functional_prefix . ' edited' ) );
	$functional_assert( $changed->get_status() === 200 && $functional_call( 'GET', 'eme_events/' . $draft )->get_data()['title'] === $functional_prefix . ' edited', 'Event update did not persist' );
	$functional_assert( $functional_call( 'DELETE', 'eme_events/' . $draft )->get_status() === 200 && $functional_call( 'GET', 'eme_events/' . $draft )->get_status() === 404, 'Event deletion did not persist' );
	$functional_results[]      = array(
		'case' => 'event-CRUD',
		'ok'   => true,
	);
	$event                     = eme_new_event();
	$event['event_name']       = $functional_prefix . ' private recurrence';
	$event['event_start']      = $functional_start . ' 10:00:00';
	$event['event_end']        = $functional_start . ' 11:00:00';
	$event['event_status']     = 5;
	$event['event_author']     = $functional_admin->ID;
	$event['event_properties'] = eme_init_event_props( array(), 1 );
	$recurrence                = array_merge(
		eme_new_recurrence(),
		array(
			'recurrence_start_date' => $functional_start,
			'recurrence_end_date'   => $functional_monday->modify( '+14 days' )->format( 'Y-m-d' ),
			'recurrence_freq'       => 'weekly',
			'recurrence_byday'      => '1',
		)
	);
	$pattern_id                = (int) eme_db_insert_recurrence( $recurrence, $event );
	$functional_assert( $pattern_id > 0, 'Native private recurrence fixture failed' );
	wp_set_current_user( 0 );
	foreach ( array( '', '/instances' ) as $suffix ) {
		$read                 = $functional_call( 'GET', 'eme_recurrences/' . $pattern_id . $suffix );
		$functional_results[] = array(
			'case' => 'anonymous-private-pattern' . $suffix,
			'ok'   => $read->get_status() === 404,
		);
	}
	$wpdb->last_error     = '';
	$listed               = $functional_call( 'GET', 'eme_recurrences' )->get_data();
	$functional_results[] = array(
		'case' => 'anonymous-recurrence-list',
		'ok'   => ! $wpdb->last_error && ! array_filter(
			$listed,
			function ( $item ) use ( $pattern_id ) {
				return (int) ( $item['recurrence_id'] ?? 0 ) === $pattern_id;
			}
		),
	);
	wp_set_current_user( $functional_admin->ID );
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT event_id FROM {$wpdb->prefix}eme_events WHERE recurrence_id=%d ORDER BY event_start,event_id", $pattern_id ) );
	foreach ( $ids as $fixture_event_id ) {
		$functional_assert( $functional_call( 'PUT', 'eme_events/' . $fixture_event_id, array( 'status' => 'public' ) )->get_status() === 200, 'Cannot publish native fixture' );
	}
	wp_set_current_user( 0 );
	$listed               = $functional_call( 'GET', 'eme_recurrences' )->get_data();
	$functional_results[] = array(
		'case' => 'public-recurrence-list',
		'ok'   => count(
			array_filter(
				$listed,
				function ( $item ) use ( $pattern_id ) {
					return (int) ( $item['recurrence_id'] ?? 0 ) === $pattern_id;
				}
			)
		) === 1,
	);
	$day                  = $functional_monday->modify( '+7 days' )->format( 'Y-m-d' );
	$instances            = $functional_call(
		'GET',
		'eme_recurrences/' . $pattern_id . '/instances',
		array(
			'start_date' => $day,
			'end_date'   => $day,
		)
	)->get_data();
	$functional_results[] = array(
		'case' => 'public-recurrence-window',
		'ok'   => ( $instances['count'] ?? 0 ) === 1 && ( $instances['instances'][0]['start'] ?? '' ) === $day . ' 10:00:00' && ( $instances['instances'][0]['end'] ?? '' ) === $day . ' 11:00:00',
	);
	$public_duration      = (int) $functional_call( 'GET', 'eme_recurrences/' . $pattern_id )->get_data()['duration'];
	wp_set_current_user( $functional_admin->ID );
	$functional_assert(
		200 === $functional_call(
			'PUT',
			'eme_events/' . $ids[0],
			array(
				'status'   => 'private',
				'end_date' => $functional_start . ' 12:00:00',
			)
		)->get_status(),
		'Cannot make one occurrence private'
	);
	$first_row = $wpdb->get_row( $wpdb->prepare( "SELECT event_start,event_end,event_status FROM {$wpdb->prefix}eme_events WHERE event_id=%d", $ids[0] ), ARRAY_A );
	$functional_assert( $functional_start . ' 10:00:00' === $first_row['event_start'] && $functional_start . ' 12:00:00' === $first_row['event_end'] && 2 === (int) $first_row['event_status'], 'Mixed visibility fixture differs from intended first occurrence: ' . wp_json_encode( $first_row ) );
	wp_set_current_user( 0 );
	$public_pattern       = $functional_call( 'GET', 'eme_recurrences/' . $pattern_id )->get_data();
	$functional_results[] = array(
		'case' => 'public-pattern-metadata-excludes-private-occurrences',
		'ok'   => $public_duration === (int) $public_pattern['duration'],
	);
	wp_set_current_user( $functional_admin->ID );
	$legacy_create        = $functional_call(
		'POST',
		'eme_recurrences',
		array(
			'title'      => $functional_prefix . ' legacy create response',
			'status'     => 'draft',
			'recurrence' => array(
				'frequency'    => 'weekly',
				'start_date'   => $functional_start,
				'end_date'     => $functional_monday->modify( '+14 days' )->format( 'Y-m-d' ),
				'days_of_week' => array( 'monday' ),
				'duration'     => 1800,
			),
		)
	)->get_data();
	$functional_results[] = array(
		'case' => 'legacy-create-response-duration',
		'ok'   => 1800 === (int) ( $legacy_create['recurrence_pattern']['duration'] ?? 0 ),
	);
} finally {
	// This private instance has no concurrent event writers; remove only newly
	// allocated fixture IDs and prove that every existing row remains present.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}eme_events WHERE event_id>%d", $functional_event_max ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}eme_recurrence WHERE recurrence_id>%d", $functional_rec_max ) );
	$functional_assert( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eme_events" ) === $functional_events_before && (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eme_recurrence" ) === $functional_recs_before, 'Fixture cleanup changed source counts' );
}
echo 'EME_FUNCTIONAL=' . wp_json_encode( $functional_results ) . "\n";
if ( array_filter(
	$functional_results,
	function ( $result ) {
		return ! $result['ok'];
	}
) ) {
	exit( 1 );
}
