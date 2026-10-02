<?php
/**
 * Samtyckeslogg: databastabell, REST-endpoint som frontend rapporterar
 * till, samt daglig gallring via WP-Cron.
 *
 * Varje val besökaren gör (acceptera, avvisa, anpassa, ändra) blir en
 * rad. Raderna knyts ihop av ett slumpat samtyckes-ID som besökaren
 * kan se i cookie-inställningarna och uppge vid en förfrågan.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RCC_DB_VERSION', '2' );
define( 'RCC_CRON_CLEANUP', 'rcc_consent_log_cleanup' );

function rcc_consent_log_table() {
	global $wpdb;
	return $wpdb->prefix . 'rcc_consent_log';
}

/**
 * Om loggningen är påslagen. Filtret gör det möjligt att stänga av den
 * i kod, t.ex. på en staging-miljö.
 */
function rcc_consent_log_enabled() {
	$s = rcc_get_settings();
	return (bool) apply_filters( 'rcc_consent_log_enabled', ! empty( $s['consent_log_enabled'] ) );
}

/* -------------------------------------------------------------------------
 * Tabell
 * ---------------------------------------------------------------------- */

/**
 * Skapar eller uppdaterar tabellerna (loggen och visningarna). Körs vid aktivering och – eftersom
 * aktiveringskroken inte körs vid uppdatering via GitHub – även när
 * sparat schemanummer inte matchar RCC_DB_VERSION.
 */
function rcc_install_consent_log_table() {
	global $wpdb;

	$table   = rcc_consent_log_table();
	$charset = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		consent_id varchar(32) NOT NULL DEFAULT '',
		created_at datetime NOT NULL,
		status varchar(10) NOT NULL DEFAULT '',
		statistics tinyint(1) NOT NULL DEFAULT 0,
		marketing tinyint(1) NOT NULL DEFAULT 0,
		country varchar(2) NOT NULL DEFAULT '',
		consent_version int(10) unsigned NOT NULL DEFAULT 1,
		plugin_version varchar(20) NOT NULL DEFAULT '',
		ip_hash varchar(64) NOT NULL DEFAULT '',
		user_agent varchar(255) NOT NULL DEFAULT '',
		PRIMARY KEY  (id),
		KEY consent_id (consent_id),
		KEY created_at (created_at),
		KEY status (status)
	) {$charset};";

	// Visningar av cookie-rutan per dag (statistik, sedan 1.3.0). Inga
	// personuppgifter, bara ett datum och en räknare.
	$views_table = $wpdb->prefix . 'rcc_banner_views';
	$sql_views   = "CREATE TABLE {$views_table} (
		day date NOT NULL,
		views bigint(20) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (day)
	) {$charset};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
	dbDelta( $sql_views );

	update_option( 'rcc_db_version', RCC_DB_VERSION, false );
}

function rcc_maybe_upgrade_consent_log_table() {
	if ( get_option( 'rcc_db_version' ) !== RCC_DB_VERSION ) {
		rcc_install_consent_log_table();
	}
}
add_action( 'plugins_loaded', 'rcc_maybe_upgrade_consent_log_table', 20 );

/* -------------------------------------------------------------------------
 * Hjälpfunktioner
 * ---------------------------------------------------------------------- */

/**
 * Status utifrån valda kategorier: allt = accepted, inget = rejected,
 * annars partial.
 */
function rcc_consent_status( $statistics, $marketing ) {
	if ( $statistics && $marketing ) {
		return 'accepted';
	}
	if ( ! $statistics && ! $marketing ) {
		return 'rejected';
	}
	return 'partial';
}

function rcc_consent_status_labels() {
	return array(
		'accepted' => 'Accepterat',
		'rejected' => 'Avvisat',
		'partial'  => 'Delvis',
	);
}

/**
 * Besökarens land från en header som CDN:et/servern sätter. Cloudflare
 * (CF-IPCountry) är vanligast på våra sajter; CloudFront och nginx/Apache
 * mod_geoip täcks också. Filtret rcc_consent_log_country gör det möjligt
 * att koppla in en egen uppslagning. Tom sträng om inget finns.
 */
function rcc_consent_log_country() {
	$country = '';
	foreach ( array( 'HTTP_CF_IPCOUNTRY', 'HTTP_CLOUDFRONT_VIEWER_COUNTRY', 'HTTP_X_COUNTRY_CODE', 'GEOIP_COUNTRY_CODE', 'HTTP_X_GEOIP_COUNTRY' ) as $key ) {
		if ( ! empty( $_SERVER[ $key ] ) ) {
			$country = strtoupper( sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) );
			break;
		}
	}
	if ( ! preg_match( '/^[A-Z]{2}$/', $country ) ) {
		$country = '';
	}
	return apply_filters( 'rcc_consent_log_country', $country );
}

/**
 * Saltad SHA-256 av IP-adressen. Går inte att vända till en IP-adress,
 * men samma besökare ger samma hash så länge saltet är detsamma, vilket
 * räcker för att styrka en loggpost vid en förfrågan. Sparas bara om
 * inställningen är påslagen.
 */
function rcc_consent_log_ip_hash() {
	$ip = ! empty( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	if ( ! $ip ) {
		return '';
	}
	return hash( 'sha256', $ip . '|' . wp_salt( 'auth' ) );
}

function rcc_consent_log_user_agent() {
	$ua = ! empty( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	return mb_substr( $ua, 0, 255 );
}

/* -------------------------------------------------------------------------
 * Skrivning
 * ---------------------------------------------------------------------- */

/**
 * Sparar en loggpost. Returnerar rad-ID eller false.
 */
function rcc_consent_log_insert( $consent_id, $statistics, $marketing, $consent_version ) {
	global $wpdb;

	$s = rcc_get_settings();

	$record = array(
		'consent_id'      => $consent_id,
		'created_at'      => current_time( 'mysql', true ),
		'status'          => rcc_consent_status( $statistics, $marketing ),
		'statistics'      => $statistics ? 1 : 0,
		'marketing'       => $marketing ? 1 : 0,
		'country'         => rcc_consent_log_country(),
		'consent_version' => (int) $consent_version,
		'plugin_version'  => RCC_VERSION,
		'ip_hash'         => ! empty( $s['consent_log_ip_hash'] ) ? rcc_consent_log_ip_hash() : '',
		'user_agent'      => rcc_consent_log_user_agent(),
	);

	/**
	 * Sista chansen att ändra eller stoppa posten (returnera en tom
	 * array för att hoppa över loggning av just detta val).
	 */
	$record = apply_filters( 'rcc_consent_log_record', $record );
	if ( empty( $record ) ) {
		return false;
	}

	$ok = $wpdb->insert(
		rcc_consent_log_table(),
		$record,
		array( '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s' )
	);

	if ( ! $ok ) {
		return false;
	}

	do_action( 'rcc_consent_logged', $wpdb->insert_id, $record );

	return $wpdb->insert_id;
}

/* -------------------------------------------------------------------------
 * REST-endpoint: POST /wp-json/rcc/v1/consent
 * ---------------------------------------------------------------------- */

function rcc_register_consent_rest_route() {
	register_rest_route( 'rcc/v1', '/consent', array(
		'methods'             => 'POST',
		'callback'            => 'rcc_rest_log_consent',
		'permission_callback' => '__return_true', // Anonyma besökare – ingen inloggning finns att kontrollera.
		'args'                => array(
			'id'         => array(
				'required'          => true,
				'type'              => 'string',
				'validate_callback' => function ( $value ) {
					return is_string( $value ) && preg_match( '/^[A-Za-z0-9]{12,32}$/', $value );
				},
			),
			'statistics' => array( 'type' => 'boolean', 'default' => false ),
			'marketing'  => array( 'type' => 'boolean', 'default' => false ),
			'version'    => array( 'type' => 'integer', 'default' => 1 ),
		),
	) );
}
add_action( 'rest_api_init', 'rcc_register_consent_rest_route' );

function rcc_rest_log_consent( WP_REST_Request $request ) {
	if ( ! rcc_consent_log_enabled() ) {
		return new WP_REST_Response( array( 'logged' => false ), 200 );
	}

	$id = rcc_consent_log_insert(
		$request->get_param( 'id' ),
		rest_sanitize_boolean( $request->get_param( 'statistics' ) ),
		rest_sanitize_boolean( $request->get_param( 'marketing' ) ),
		absint( $request->get_param( 'version' ) )
	);

	$response = new WP_REST_Response( array( 'logged' => (bool) $id ), 200 );
	$response->header( 'Cache-Control', 'no-store' );
	return $response;
}

/* -------------------------------------------------------------------------
 * Gallring
 * ---------------------------------------------------------------------- */

function rcc_schedule_consent_log_cleanup() {
	if ( ! wp_next_scheduled( RCC_CRON_CLEANUP ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', RCC_CRON_CLEANUP );
	}
}
add_action( 'init', 'rcc_schedule_consent_log_cleanup' );

function rcc_unschedule_consent_log_cleanup() {
	wp_clear_scheduled_hook( RCC_CRON_CLEANUP );
}

/**
 * Raderar poster äldre än inställd gallringstid. Returnerar antal rader.
 */
function rcc_consent_log_cleanup() {
	global $wpdb;

	$s      = rcc_get_settings();
	$months = max( 1, (int) $s['consent_log_retention_months'] );
	$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$months} months", time() ) );

	$deleted = $wpdb->query( $wpdb->prepare(
		"DELETE FROM " . rcc_consent_log_table() . " WHERE created_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$cutoff
	) );

	return (int) $deleted;
}
add_action( RCC_CRON_CLEANUP, 'rcc_consent_log_cleanup' );

/* -------------------------------------------------------------------------
 * Läsning (används av admin-sidan och exporten)
 * ---------------------------------------------------------------------- */

/**
 * Bygger WHERE-delen utifrån filter: status, månad (YYYY-MM) och sökning
 * på samtyckes-ID. Returnerar array( sql, args ).
 */
function rcc_consent_log_where( $filters ) {
	global $wpdb;

	$where = array( '1=1' );
	$args  = array();

	$labels = rcc_consent_status_labels();
	if ( ! empty( $filters['status'] ) && isset( $labels[ $filters['status'] ] ) ) {
		$where[] = 'status = %s';
		$args[]  = $filters['status'];
	}

	if ( ! empty( $filters['month'] ) && preg_match( '/^\d{4}-\d{2}$/', $filters['month'] ) ) {
		$where[] = 'created_at >= %s AND created_at < %s';
		$args[]  = $filters['month'] . '-01 00:00:00';
		$args[]  = gmdate( 'Y-m-d H:i:s', strtotime( $filters['month'] . '-01 +1 month' ) );
	}

	if ( ! empty( $filters['search'] ) ) {
		$where[] = 'consent_id LIKE %s';
		$args[]  = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
	}

	$sql = implode( ' AND ', $where );
	if ( $args ) {
		$sql = $wpdb->prepare( $sql, $args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
	return $sql;
}

function rcc_consent_log_count( $filters = array() ) {
	global $wpdb;
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . rcc_consent_log_table() . ' WHERE ' . rcc_consent_log_where( $filters ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

function rcc_consent_log_rows( $filters = array(), $limit = 50, $offset = 0, $orderby = 'created_at', $order = 'DESC' ) {
	global $wpdb;

	$allowed_orderby = array( 'created_at', 'status', 'country', 'consent_version' );
	$orderby         = in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'created_at';
	$order           = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';

	$sql = 'SELECT * FROM ' . rcc_consent_log_table() . ' WHERE ' . rcc_consent_log_where( $filters ) . " ORDER BY {$orderby} {$order}, id {$order}";
	if ( $limit > 0 ) {
		$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, $offset );
	}

	return $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * Månader (YYYY-MM) som har poster, nyaste först. Används till filtret.
 */
function rcc_consent_log_months() {
	global $wpdb;
	return $wpdb->get_col( "SELECT DISTINCT DATE_FORMAT(created_at, '%Y-%m') FROM " . rcc_consent_log_table() . ' ORDER BY 1 DESC' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

function rcc_consent_log_truncate() {
	global $wpdb;
	return $wpdb->query( 'TRUNCATE TABLE ' . rcc_consent_log_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}
