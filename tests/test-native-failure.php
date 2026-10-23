<?php
/** Real WordPress fault injection; this file must run only in a private rehearsal. */
if ( ! function_exists( 'wp_get_environment_type' ) || 'staging' !== wp_get_environment_type() || ! preg_match( '#^localhost:/home/[a-z0-9_]+/\.php-upgrade/eme-[a-zA-Z0-9-]+/mysql\.sock$#', DB_HOST ) ) {
	throw new RuntimeException( 'Bootstrap this test only through the private socket-only EME rehearsal; never use a live database.' );
}
global $wpdb, $wp_filter;
$native_failure_before = array(
	(int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eme_events" ),
	(int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eme_recurrence" ),
);
$native_failure_admins = get_super_admins();
wp_set_current_user( get_user_by( 'login', $native_failure_admins[0] )->ID );
$native_failure_year    = (int) gmdate( 'Y' ) + 2;
$native_failure_start   = new DateTimeImmutable( 'first monday of January ' . $native_failure_year );
$native_failure_cases   = array(
	'pattern-insert-fails'  => array(
		'deny'   => 'pattern',
		'status' => 500,
	),
	'partial-native-save'   => array(
		'deny'   => 'second',
		'status' => 500,
	),
	'all-native-saves-fail' => array(
		'deny'   => 'all',
		'status' => 500,
	),
	'unrelated-hook-event'  => array(
		'hook_single' => true,
		'status'      => 200,
	),
	'empty-sanitized-title' => array(
		'title'  => '<b></b>',
		'status' => 400,
	),
	'text-byte-limit'       => array(
		'title'  => str_repeat( 'x', 65536 ),
		'status' => 400,
	),
	'multibyte-text-limit'  => array(
		'title'  => str_repeat( "\xc3\xa9", 32768 ),
		'status' => 400,
	),
);
$native_failure_results = array();
foreach ( $native_failure_cases as $native_failure_label => $native_failure_case ) {
	$fixture_marker           = 'EME native failure fixture ' . bin2hex( random_bytes( 8 ) );
	$fixture_patterns         = array();
	$fixture_attempts         = 0;
	$fixture_denials          = 0;
	$fixture_pattern_attempts = 0;
	$fixture_pattern_denials  = 0;
	$fixture_delete_hooks     = 0;
	$fixture_trash_hooks      = array();
	$fixture_single           = 0;
	$fixture_single_started   = false;
	$capture                  = static function ( $event ) use ( $fixture_marker, $native_failure_case, &$fixture_patterns, &$fixture_single, &$fixture_single_started ) {
		if ( $fixture_marker === $event['event_notes'] && ! empty( $event['recurrence_id'] ) ) {
			$fixture_patterns[ (int) $event['recurrence_id'] ] = true;
			if ( ! empty( $native_failure_case['hook_single'] ) && ! $fixture_single_started ) {
				$fixture_single_started     = true;
				$single                     = eme_new_event();
				$single['event_properties'] = eme_init_event_props( array(), 1 );
				foreach ( array( 'event_name', 'event_notes', 'event_start', 'event_end', 'event_author', 'event_status' ) as $field ) {
					$single[ $field ] = $event[ $field ];
				}
				$single['recurrence_id'] = 0;
				$fixture_single          = (int) eme_db_insert_event( $single );
			}
		}
		return $event;
	};
	$deny                     = static function ( $sql ) use ( $wpdb, $fixture_marker, $native_failure_case, &$fixture_attempts, &$fixture_denials, &$fixture_pattern_attempts, &$fixture_pattern_denials ) {
		if ( 0 === strpos( $sql, 'INSERT INTO `' . $wpdb->prefix . 'eme_recurrence`' ) ) {
			++$fixture_pattern_attempts;
			if ( isset( $native_failure_case['deny'] ) && 'pattern' === $native_failure_case['deny'] && 1 === $fixture_pattern_attempts ) {
				++$fixture_pattern_denials;
				return '';
			}
		}
		if ( 0 === strpos( $sql, 'INSERT INTO `' . $wpdb->prefix . 'eme_events`' ) && false !== strpos( $sql, $fixture_marker ) ) {
			++$fixture_attempts;
			if ( isset( $native_failure_case['deny'] ) && ( 'all' === $native_failure_case['deny'] || ( 'second' === $native_failure_case['deny'] && 2 === $fixture_attempts ) ) ) {
				++$fixture_denials;
				// wpdb::query returns false for a denied empty query, without
				// executing a broken SQL statement or hiding an unrelated error.
				return '';
			}
		}
		return $sql;
	};
	$deleted                  = static function ( $event, $recurrence ) use ( &$fixture_patterns, &$fixture_delete_hooks ) {
		if ( isset( $fixture_patterns[ (int) $recurrence['recurrence_id'] ] ) ) {
			++$fixture_delete_hooks;
		}
	};
	$trashed                  = static function ( $event ) use ( $fixture_marker, &$fixture_trash_hooks ) {
		if ( $fixture_marker === $event['event_notes'] ) {
			$fixture_trash_hooks[] = (int) $event['event_id'];
		}
	};
	add_filter( 'eme_event_preinsert_filter', $capture );
	add_filter( 'query', $deny, PHP_INT_MAX );
	add_action( 'eme_delete_recurrence_action', $deleted, 10, 2 );
	add_action( 'eme_trash_event_action', $trashed );
	$guard_filters_before = count( $wp_filter['eme_event_preinsert_filter']->callbacks[ PHP_INT_MIN ] ?? array() );
	try {
		$request = new WP_REST_Request( 'POST', '/eme/v1/eme_recurrences' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'title'       => $native_failure_case['title'] ?? $fixture_marker,
					'description' => $fixture_marker,
					'status'      => 'draft',
					'recurrence'  => array(
						'frequency'    => 'weekly',
						'start_date'   => $native_failure_start->format( 'Y-m-d' ),
						'end_date'     => $native_failure_start->modify( '+14 days' )->format( 'Y-m-d' ),
						'days_of_week' => array( 'monday' ),
						'start_time'   => '10:00:00',
						'duration'     => 3600,
					),
				)
			)
		);
		$response           = rest_do_request( $request );
		$rows               = $wpdb->get_results( $wpdb->prepare( "SELECT event_id,event_status,recurrence_id FROM {$wpdb->prefix}eme_events WHERE event_notes=%s ORDER BY event_id", $fixture_marker ), ARRAY_A );
		$patterns_remaining = 0;
		foreach ( array_keys( $fixture_patterns ) as $pattern_id ) {
			$patterns_remaining += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}eme_recurrence WHERE recurrence_id=%d", $pattern_id ) );
		}
		$guard_removed = count( $wp_filter['eme_event_preinsert_filter']->callbacks[ PHP_INT_MIN ] ?? array() ) === $guard_filters_before;
		$ok            = $native_failure_case['status'] === $response->get_status() && $guard_removed;
		if ( isset( $native_failure_case['deny'] ) && 'pattern' === $native_failure_case['deny'] ) {
			$ok = $ok && 1 === $fixture_pattern_attempts && 1 === $fixture_pattern_denials && 0 === $fixture_attempts && empty( $rows ) && empty( $fixture_patterns );
		} elseif ( isset( $native_failure_case['deny'] ) ) {
			$expected_saved = 'second' === $native_failure_case['deny'] ? 2 : 0;
			$ok             = $ok && 3 === $fixture_attempts && ( 3 - $expected_saved ) === $fixture_denials && count( $rows ) === $expected_saved && 0 === $patterns_remaining;
			foreach ( $rows as $row ) {
				$ok = $ok && 0 === (int) $row['event_status'] && 0 === (int) $row['recurrence_id'];
			}
			if ( $expected_saved ) {
				$ok = $ok && 1 === $fixture_delete_hooks && count( $fixture_trash_hooks ) === $expected_saved;
			}
		} elseif ( ! empty( $native_failure_case['hook_single'] ) ) {
			$single_rows = array_filter(
				$rows,
				static function ( $row ) use ( $fixture_single ) {
					return (int) $row['event_id'] === $fixture_single && 0 === (int) $row['recurrence_id'] && 5 === (int) $row['event_status'];
				}
			);
			$ok          = $ok && $fixture_single > 0 && 1 === count( $single_rows ) && 4 === count( $rows ) && 4 === $fixture_attempts && 1 === $patterns_remaining;
		} else {
			$ok = $ok && 0 === $fixture_attempts && empty( $fixture_patterns ) && empty( $rows );
		}
		$native_failure_results[] = array(
			'case'                  => $native_failure_label,
			'ok'                    => $ok,
			'status'                => $response->get_status(),
			'insertAttempts'        => $fixture_attempts,
			'deniedInserts'         => $fixture_denials,
			'patternInsertAttempts' => $fixture_pattern_attempts,
			'deniedPatternInserts'  => $fixture_pattern_denials,
			'savedFixtureRows'      => count( $rows ),
			'patternsRemaining'     => $patterns_remaining,
			'nativeDeleteHooks'     => $fixture_delete_hooks,
			'nativeTrashHooks'      => count( $fixture_trash_hooks ),
			'guardRemoved'          => $guard_removed,
		);
	} finally {
		remove_filter( 'query', $deny, PHP_INT_MAX );
		remove_filter( 'eme_event_preinsert_filter', $capture );
		remove_action( 'eme_delete_recurrence_action', $deleted, 10 );
		remove_action( 'eme_trash_event_action', $trashed );
		// Remove only this uniquely marked fixture, never source IDs or another
		// process's newer events. Native cleanup above is checked before this.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}eme_events WHERE event_notes=%s", $fixture_marker ) );
		foreach ( array_keys( $fixture_patterns ) as $pattern_id ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}eme_recurrence WHERE recurrence_id=%d", $pattern_id ) );
		}
	}
}
$native_failure_after     = array(
	(int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eme_events" ),
	(int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eme_recurrence" ),
);
$native_failure_results[] = array(
	'case' => 'source-counts-preserved',
	'ok'   => $native_failure_before === $native_failure_after,
);
echo 'EME_NATIVE_FAILURE=' . wp_json_encode( $native_failure_results ) . "\n";
if ( array_filter(
	$native_failure_results,
	static function ( $result ) {
		return ! $result['ok']; }
) ) {
	exit( 1 );
}
