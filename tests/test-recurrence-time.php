<?php
// Exercise elapsed seconds against real native local-time recurrence storage.
if ( 'staging' !== wp_get_environment_type() || ! preg_match( '#^localhost:/home/[a-z0-9_]+/\.php-upgrade/eme-[a-zA-Z0-9-]+/mysql\.sock$#', DB_HOST ) ) {
	throw new RuntimeException( 'Run time contracts only in the private socket-only rehearsal.' );
}
global $wpdb;
$time_event_max   = (int) $wpdb->get_var( "SELECT MAX(event_id) FROM {$wpdb->prefix}eme_events" );
$time_pattern_max = (int) $wpdb->get_var( "SELECT MAX(recurrence_id) FROM {$wpdb->prefix}eme_recurrence" );
$time_before      = array( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eme_events" ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eme_recurrence" ) );
$time_admins      = get_super_admins();
wp_set_current_user( get_user_by( 'login', $time_admins[0] )->ID );
$time_results = array();
$time_cases   = array(
	'spring-elapsed-hour'       => array( array( '2028-03-12' ), '01:30:00', 200, '03:30:00' ),
	'spring-nonexistent-start'  => array( array( '2028-03-12' ), '02:30:00', 400, null ),
	'fall-unrepresentable-hour' => array( array( '2028-11-05' ), '01:30:00', 400, null ),
	'varying-local-duration'    => array( array( '2028-03-05', '2028-03-12' ), '01:30:00', 400, null ),
	'ordinary-series-over-DST'  => array( array( '2028-03-05', '2028-03-12', '2028-03-19' ), '10:00:00', 200, '11:00:00' ),
	'invalid-local-minute'      => array( array( '2028-03-12' ), '15:99:00', 400, null ),
	'unused-fall-anchor'        => array(
		array( '2028-11-06', '2028-11-13', '2028-11-20' ),
		'01:30:00',
		200,
		'02:30:00',
		array(
			'frequency'    => 'weekly',
			'start_date'   => '2028-11-05 01:30:00',
			'end_date'     => '2028-11-20',
			'days_of_week' => array( 'monday' ),
		),
	),
	'unused-spring-anchor'      => array(
		array( '2028-03-13', '2028-03-20', '2028-03-27' ),
		'02:30:00',
		200,
		'03:30:00',
		array(
			'frequency'    => 'weekly',
			'start_date'   => '2028-03-12 02:30:00',
			'end_date'     => '2028-03-27',
			'days_of_week' => array( 'monday' ),
		),
	),
);
try {
	foreach ( $time_cases as $label => $case ) {
		$before  = array( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eme_events" ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eme_recurrence" ) );
		$request = new WP_REST_Request( 'POST', '/eme/v1/eme_recurrences' );
		$request->set_header( 'content-type', 'application/json' );
		$pattern               = $case[4] ?? array(
			'frequency' => 'specific',
			'dates'     => $case[0],
		);
		$pattern['start_time'] = $case[1];
		$pattern['duration']   = 3600;
		$request->set_body(
			wp_json_encode(
				array(
					'title'      => 'Private time fixture ' . $label,
					'status'     => 'draft',
					'recurrence' => $pattern,
				)
			)
		);
		$response = rest_do_request( $request );
		$ok       = $response->get_status() === $case[2];
		if ( 400 === $case[2] ) {
				$ok = $ok && array( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eme_events" ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eme_recurrence" ) ) === $before;
		} else {
			$time_recurrence_id = (int) ( $response->get_data()['recurrence_id'] ?? 0 );
			$rows               = $wpdb->get_results( $wpdb->prepare( "SELECT event_start,event_end FROM {$wpdb->prefix}eme_events WHERE recurrence_id=%d ORDER BY event_start,event_id", $time_recurrence_id ), ARRAY_A );
			$ok                 = $ok && count( $rows ) === count( $case[0] );
			$ok                 = $ok && array_map(
				static function ( $row ) {
					return substr( $row['event_start'], 0, 10 );
				},
				$rows
			) === $case[0];
			if ( isset( $case[4] ) ) {
					$bound = $wpdb->get_var( $wpdb->prepare( "SELECT recurrence_start_date FROM {$wpdb->prefix}eme_recurrence WHERE recurrence_id=%d", $time_recurrence_id ) );
					$ok    = $ok && substr( $case[4]['start_date'], 0, 10 ) === $bound;
			}
			foreach ( $rows as $row ) {
				$start = eme_rest_datetime( $row['event_start'] );
				$end   = eme_rest_datetime( $row['event_end'] );
				$ok    = $ok && $end->getTimestamp() - $start->getTimestamp() === 3600 && substr( $row['event_start'], 11 ) === $case[1] && substr( $row['event_end'], 11 ) === $case[3];
			}
		}
		$time_results[] = array(
			'case'   => $label,
			'ok'     => $ok,
			'status' => $response->get_status(),
		);
	}
} finally {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}eme_events WHERE event_id>%d", $time_event_max ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}eme_recurrence WHERE recurrence_id>%d", $time_pattern_max ) );
	if ( array( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eme_events" ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eme_recurrence" ) ) !== $time_before ) {
		throw new RuntimeException( 'Time fixture cleanup changed original row counts.' );
	}
}
echo 'EME_TIME=' . wp_json_encode( $time_results ) . "\n";
if ( array_filter(
	$time_results,
	function ( $result ) {
		return ! $result['ok'];
	}
) ) {
	exit( 1 ); }
