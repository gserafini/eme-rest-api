<?php
/** Functional API contracts. Run only inside the isolated Ops rehearsal. */
if ( wp_get_environment_type() !== 'staging' || ! preg_match( '#^localhost:/home/[a-z0-9_]+/\.php-upgrade/eme-[a-zA-Z0-9-]+/mysql\.sock$#', DB_HOST ) ) {
	throw new RuntimeException( 'Functional fixtures require the private socket-only EME rehearsal; never run them against a live database.' );
}
global $wpdb;
$functional_results         = array();
$functional_admins          = get_super_admins();
$functional_admin           = get_user_by( 'login', $functional_admins[0] );
$functional_call            = function ( $method, $route, $payload = array() ) {
	$request = new WP_REST_Request( $method, '/eme/v1/' . $route );
	if ( 'GET' === $method ) {
		$request->set_query_params( $payload );
	} else {
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $payload ) ); }
	return rest_do_request( $request );
};
$functional_assert          = function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Private CLI assertion, not HTML output.
	} };
$functional_prefix          = 'EME REST fixture ' . bin2hex( random_bytes( 6 ) );
$functional_event_max       = (int) $wpdb->get_var( "SELECT MAX(event_id) FROM {$wpdb->prefix}eme_events" );
$functional_rec_max         = (int) $wpdb->get_var( "SELECT MAX(recurrence_id) FROM {$wpdb->prefix}eme_recurrence" );
$functional_events_before   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eme_events" );
$functional_recs_before     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eme_recurrence" );
$functional_visibility_only = defined( 'EME_REST_TEST_VISIBILITY_ONLY' ) && EME_REST_TEST_VISIBILITY_ONLY;
foreach ( $functional_visibility_only ? array() : array( 0, -1, 1.5, 'bad', 128 ) as $interval ) {
	$validation           = eme_rest_validate_recurrence(
		array(
			'frequency'    => 'weekly',
			'start_date'   => '2030-01-01',
			'end_date'     => '2030-02-01',
			'days_of_week' => array( 'monday' ),
			'interval'     => $interval,
		)
	);
	$functional_results[] = array(
		'case' => 'invalid-interval-' . $interval,
		'ok'   => is_wp_error( $validation ),
	);
}
$functional_year   = (int) gmdate( 'Y' ) + 2;
$functional_monday = new DateTimeImmutable( 'first monday of January ' . $functional_year );
$functional_start  = $functional_monday->format( 'Y-m-d' );
$functional_cases  = array(
	'weekly'               => array(
		'frequency'    => 'weekly',
		'start_date'   => $functional_start,
		'end_date'     => $functional_monday->modify( '+14 days' )->format( 'Y-m-d' ),
		'days_of_week' => array( 'monday' ),
		'interval'     => 1,
		'duration'     => 3600,
		'start_time'   => '10:00:00',
	),
	'fortnightly-excluded' => array(
		'frequency'    => 'weekly',
		'start_date'   => $functional_start,
		'end_date'     => $functional_monday->modify( '+42 days' )->format( 'Y-m-d' ),
		'days_of_week' => array( 'monday' ),
		'interval'     => 2,
		'duration'     => 3600,
		'start_time'   => '10:00:00',
		'exclude_days' => array( $functional_monday->modify( '+14 days' )->format( 'Y-m-d' ) ),
	),
	'weekly-sunday'        => array(
		'frequency'    => 'weekly',
		'start_date'   => $functional_start,
		'end_date'     => $functional_monday->modify( '+20 days' )->format( 'Y-m-d' ),
		'days_of_week' => array( 0 ),
		'duration'     => 3600,
		'start_time'   => '10:00:00',
	),
	'monthly'              => array(
		'frequency'     => 'monthly',
		'start_date'    => $functional_year . '-01-01',
		'end_date'      => $functional_year . '-03-31',
		'day_of_week'   => 'monday',
		'week_of_month' => 2,
		'duration'      => 3600,
		'start_time'    => '10:00:00',
	),
	'monthly-sunday'       => array(
		'frequency'     => 'monthly',
		'start_date'    => $functional_year . '-01-01',
		'end_date'      => $functional_year . '-03-31',
		'day_of_week'   => 0,
		'week_of_month' => 1,
		'duration'      => 3600,
		'start_time'    => '10:00:00',
	),
	'specific'             => array(
		'frequency'  => 'specific',
		'dates'      => array( $functional_start, $functional_monday->modify( '+2 days' )->format( 'Y-m-d' ), $functional_monday->modify( '+4 days' )->format( 'Y-m-d' ) ),
		'duration'   => 3600,
		'start_time' => '10:00:00',
	),
);
if ( $functional_visibility_only ) {
	$functional_cases = array();
}
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
	foreach ( array( 0, 2, 3, 5, 6, 1 ) as $functional_status ) {
		wp_set_current_user( $functional_admin->ID );
		$functional_call( 'PUT', 'eme_events/' . $draft, array( 'status' => eme_rest_format_status( $functional_status ) ) );
		wp_set_current_user( 0 );
		$read                 = $functional_call( 'GET', 'eme_events/' . $draft );
		$expected             = in_array( $functional_status, array( 1, 3 ), true ) ? 200 : 404;
		$functional_results[] = array(
			'case' => 'anonymous-status-' . $functional_status,
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
	$functional_results[] = array(
		'case' => 'event-CRUD',
		'ok'   => true,
	);
	foreach ( $functional_cases as $label => $pattern ) {
		try {
			$made                     = $functional_call(
				'POST',
				'eme_recurrences',
				array(
					'title'      => $functional_prefix . ' ' . $label,
					'status'     => 'draft',
					'recurrence' => $pattern,
				)
			);
			$data                     = $made->get_data();
			$functional_recurrence_id = (int) ( $data['recurrence_id'] ?? 0 );
			$functional_assert( $made->get_status() < 400 && $functional_recurrence_id > 0, 'Recurrence create failed: ' . wp_json_encode( $data ) );
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT event_id,event_start,event_end FROM {$wpdb->prefix}eme_events WHERE recurrence_id=%d ORDER BY event_start", $functional_recurrence_id ), ARRAY_A );
			$functional_assert( count( $rows ) === 3, 'Expected three stored occurrences, got ' . count( $rows ) );
			foreach ( $rows as $row ) {
				$functional_assert( substr( $row['event_start'], 11 ) === '10:00:00' && substr( $row['event_end'], 11 ) === '11:00:00', 'Occurrence time/duration differs from request' );
				$functional_assert( substr( $row['event_start'], 0, 4 ) === (string) $functional_year, 'Occurrence date differs from request' );
				if ( in_array( $label, array( 'weekly-sunday', 'monthly-sunday' ), true ) ) {
					$functional_assert( '7' === ( new DateTimeImmutable( $row['event_start'] ) )->format( 'N' ), 'API Sunday must generate actual Sundays in the native engine' );
				}
			}
			if ( 'fortnightly-excluded' === $label ) {
				$expected = array( $functional_start, $functional_monday->modify( '+28 days' )->format( 'Y-m-d' ), $functional_monday->modify( '+42 days' )->format( 'Y-m-d' ) );
				$functional_assert(
					array_map(
						function ( $r ) {
							return substr( $r['event_start'], 0, 10 );
						},
						$rows
					) === $expected,
					'Interval or excluded date was ignored'
				);
			}
			$listed = $functional_call( 'GET', 'eme_recurrences' )->get_data();
			$functional_assert(
				in_array(
					$functional_recurrence_id,
					array_map(
						function ( $e ) {
							return (int) ( $e['recurrence_id'] ?? 0 );
						},
						$listed
					),
					true
				),
				'Created pattern missing from recurrence list'
			);
			$from      = substr( $rows[1]['event_start'], 0, 10 );
			$instances = $functional_call(
				'GET',
				'eme_recurrences/' . $functional_recurrence_id . '/instances',
				array(
					'start_date' => $from,
					'end_date'   => $from,
				)
			)->get_data();
			$functional_assert( ( $instances['count'] ?? 0 ) === 1, 'Instance date window was ignored' );
			$functional_assert( (int) ( $instances['pattern']['duration'] ?? 0 ) === 3600, 'API seconds were confused with native duration days' );
			wp_set_current_user( 0 );
			$functional_assert( $functional_call( 'GET', 'eme_recurrences/' . $functional_recurrence_id )->get_status() === 404, 'Anonymous reader can see a draft recurrence' );
			wp_set_current_user( $functional_admin->ID );
			$delete_hook_fired = false;
			$delete_hook       = function ( $event, $recurrence ) use ( &$delete_hook_fired, $functional_recurrence_id ) {
				if ( (int) $recurrence['recurrence_id'] === $functional_recurrence_id ) {
					$delete_hook_fired = true; }
			};
			add_action( 'eme_delete_recurrence_action', $delete_hook, 10, 2 );
			try {
				$functional_assert( $functional_call( 'DELETE', 'eme_recurrences/' . $functional_recurrence_id )->get_status() === 200, 'Recurrence deletion failed' );
			} finally {
				remove_action( 'eme_delete_recurrence_action', $delete_hook, 10 ); }
			$functional_assert( $delete_hook_fired && 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}eme_recurrence WHERE recurrence_id=%d", $functional_recurrence_id ) ), 'Native delete hook/pattern removal failed' );
			foreach ( $rows as $stored ) {
				$trashed = $wpdb->get_row( $wpdb->prepare( "SELECT event_status,recurrence_id FROM {$wpdb->prefix}eme_events WHERE event_id=%d", $stored['event_id'] ), ARRAY_A );
				$functional_assert( $trashed && 0 === (int) $trashed['event_status'] && 0 === (int) $trashed['recurrence_id'], 'Native deletion must trash each original event and detach its pattern' );
			}
			$functional_results[] = array(
				'case'        => $label,
				'ok'          => true,
				'occurrences' => 3,
			);
		} catch ( Throwable $failure ) {
			$functional_results[] = array(
				'case'  => $label,
				'ok'    => false,
				'error' => $failure->getMessage(),
			);
			wp_set_current_user( $functional_admin->ID );
		}
	}
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
