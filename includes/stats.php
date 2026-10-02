<?php
/**
 * Statistik: visningar av cookie-rutan och sammanställningar av
 * samtyckesloggen.
 *
 * Visningarna räknas per dag i en egen tabell utan personuppgifter: ingen
 * IP, inget ID, ingen cookie eller webblagring. Varje sidvisning där
 * rutan visas automatiskt (besökaren har inte gjort ett giltigt val)
 * räknas en gång. Svarsfrekvensen (val / visningar) blir därför ett
 * golv: en besökare som klickar runt utan att välja ger flera visningar.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function rcc_banner_views_table() {
	global $wpdb;
	return $wpdb->prefix . 'rcc_banner_views';
}

/**
 * Om visningar ska räknas. Kräver att loggningen är på, eftersom
 * statistiken bygger på den.
 */
function rcc_count_banner_views_enabled() {
	$s = rcc_get_settings();
	return (bool) apply_filters( 'rcc_count_banner_views', ! empty( $s['count_banner_views'] ) && rcc_consent_log_enabled() );
}

/* -------------------------------------------------------------------------
 * Räkning: POST /wp-json/rcc/v1/view
 * ---------------------------------------------------------------------- */

function rcc_register_view_rest_route() {
	register_rest_route( 'rcc/v1', '/view', array(
		'methods'             => 'POST',
		'callback'            => 'rcc_rest_count_view',
		'permission_callback' => '__return_true', // Anonyma besökare.
	) );
}
add_action( 'rest_api_init', 'rcc_register_view_rest_route' );

function rcc_rest_count_view( WP_REST_Request $request ) {
	$counted = false;
	if ( rcc_count_banner_views_enabled() ) {
		$counted = rcc_count_banner_view();
	}
	$response = new WP_REST_Response( array( 'counted' => $counted ), 200 );
	$response->header( 'Cache-Control', 'no-store' );
	return $response;
}

/**
 * Räknar upp dagens visningar med ett. Dagen följer sajtens tidszon.
 */
function rcc_count_banner_view() {
	global $wpdb;
	$table = rcc_banner_views_table();
	$day   = wp_date( 'Y-m-d' );
	$ok    = $wpdb->query( $wpdb->prepare(
		"INSERT INTO {$table} (day, views) VALUES (%s, 1) ON DUPLICATE KEY UPDATE views = views + 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$day
	) );
	return false !== $ok;
}

/* -------------------------------------------------------------------------
 * Sammanställningar
 * ---------------------------------------------------------------------- */

/**
 * Perioden som de senaste $days dagarna inklusive i dag, i sajtens
 * tidszon. Returnerar lokala datum (Y-m-d) och motsvarande gränser i UTC
 * för frågor mot loggen.
 */
function rcc_stats_period( $days ) {
	$days  = max( 1, (int) $days );
	$tz    = wp_timezone();
	$today = new DateTimeImmutable( 'today', $tz );
	$start = $today->modify( '-' . ( $days - 1 ) . ' days' );
	$end   = $today->modify( '+1 day' );
	$utc   = new DateTimeZone( 'UTC' );

	$dates = array();
	for ( $d = $start; $d < $end; $d = $d->modify( '+1 day' ) ) {
		$dates[] = $d->format( 'Y-m-d' );
	}

	return array(
		'days'      => $days,
		'dates'     => $dates,
		'from_date' => $start->format( 'Y-m-d' ),
		'to_date'   => $today->format( 'Y-m-d' ),
		'from_gmt'  => $start->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
		'to_gmt'    => $end->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
	);
}

/**
 * Allt statistiksidan och widgeten behöver för en period:
 *
 * totals: accepted/rejected/partial/choices/unique/views
 * daily:  datum => accepted/rejected/partial/views
 *
 * Loggen grupperas per timme i UTC och fördelas på lokala dagar i PHP,
 * så att dygnsgränserna följer sajtens tidszon (även över sommartid)
 * utan databasspecifika datumfunktioner.
 */
function rcc_stats_summary( $days = 30 ) {
	global $wpdb;

	$period = rcc_stats_period( $days );
	$blank  = array( 'accepted' => 0, 'rejected' => 0, 'partial' => 0, 'views' => 0 );
	$daily  = array_fill_keys( $period['dates'], $blank );
	$totals = array( 'accepted' => 0, 'rejected' => 0, 'partial' => 0, 'choices' => 0, 'unique' => 0, 'views' => 0 );

	$log  = rcc_consent_log_table();
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT SUBSTR(created_at, 1, 13) AS hour, status, COUNT(*) AS n FROM {$log} WHERE created_at >= %s AND created_at < %s GROUP BY hour, status", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$period['from_gmt'],
		$period['to_gmt']
	), ARRAY_A );

	foreach ( (array) $rows as $row ) {
		if ( ! isset( $blank[ $row['status'] ] ) || 'views' === $row['status'] ) {
			continue;
		}
		$day = get_date_from_gmt( $row['hour'] . ':00:00', 'Y-m-d' );
		$n   = (int) $row['n'];
		if ( isset( $daily[ $day ] ) ) {
			$daily[ $day ][ $row['status'] ] += $n;
		}
		$totals[ $row['status'] ] += $n;
	}
	$totals['choices'] = $totals['accepted'] + $totals['rejected'] + $totals['partial'];

	$totals['unique'] = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(DISTINCT consent_id) FROM {$log} WHERE created_at >= %s AND created_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$period['from_gmt'],
		$period['to_gmt']
	) );

	$views_table = rcc_banner_views_table();
	$views       = $wpdb->get_results( $wpdb->prepare(
		"SELECT day, views FROM {$views_table} WHERE day >= %s AND day <= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$period['from_date'],
		$period['to_date']
	), ARRAY_A );
	foreach ( (array) $views as $row ) {
		$day = substr( (string) $row['day'], 0, 10 );
		if ( isset( $daily[ $day ] ) ) {
			$daily[ $day ]['views'] = (int) $row['views'];
			$totals['views']       += (int) $row['views'];
		}
	}

	return array(
		'period' => $period,
		'totals' => $totals,
		'daily'  => $daily,
	);
}

/**
 * Andel i procent med en decimal, eller null när nämnaren är noll.
 */
function rcc_stats_percent( $part, $whole ) {
	return $whole > 0 ? round( 100 * $part / $whole, 1 ) : null;
}

/**
 * Raderar visningsdagar äldre än loggens gallringstid. Körs av samma
 * dagliga cron-jobb som loggen.
 */
function rcc_banner_views_cleanup() {
	global $wpdb;
	$s      = rcc_get_settings();
	$months = max( 1, (int) $s['consent_log_retention_months'] );
	$cutoff = wp_date( 'Y-m-d', strtotime( "-{$months} months" ) );
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . rcc_banner_views_table() . ' WHERE day < %s', $cutoff ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}
add_action( RCC_CRON_CLEANUP, 'rcc_banner_views_cleanup' );
