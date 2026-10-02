<?php
/**
 * Pluginets egen meny i WP-admin:
 *
 *   Cookie Consent
 *   ├── Inställningar
 *   ├── Statistik
 *   ├── Samtyckeslogg
 *   └── Skanner
 *
 * Sidorna låg före 1.3.0 under Inställningar (options-general.php). De
 * gamla adresserna skickas vidare hit så att bokmärken fortsätter fungera.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RCC_SETTINGS_SLUG', 'relativt-cookie-consent' );
define( 'RCC_STATS_SLUG', 'relativt-cookie-consent-stats' );
define( 'RCC_LOG_PAGE_SLUG', 'relativt-cookie-consent-log' );
define( 'RCC_SCANNER_SLUG', 'relativt-cookie-consent-scanner' );

/**
 * Adress till en av pluginets admin-sidor.
 */
function rcc_admin_page_url( $slug = RCC_SETTINGS_SLUG, $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => $slug ), $args ), admin_url( 'admin.php' ) );
}

/**
 * Hook-namnen som WordPress gav sidorna, per slug. Används för att bara
 * ladda CSS/JS på pluginets egna sidor utan att gissa hook-namnen (de
 * beror på menyns titel).
 */
function rcc_admin_hooks( $slug = null, $hook = null ) {
	static $hooks = array();
	if ( null !== $hook ) {
		$hooks[ $slug ] = $hook;
	}
	if ( null === $slug ) {
		return $hooks;
	}
	return isset( $hooks[ $slug ] ) ? $hooks[ $slug ] : '';
}

function rcc_is_admin_page( $hook, $slug ) {
	$expected = rcc_admin_hooks( $slug );
	return $expected && $expected === $hook;
}

/**
 * Menyikonen: samma kaka som den flytande knappen, men fylld så att
 * WordPress kan färga den efter admin-färgschemat.
 */
function rcc_menu_icon() {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" fill-rule="evenodd" d="M10 2a8 8 0 1 0 8 8 2.6 2.6 0 0 1-3.1-2.5 2.6 2.6 0 0 1-2.6-2.7A2.4 2.4 0 0 1 10 2zM6.2 8.6m-1.2 0a1.2 1.2 0 1 0 2.4 0a1.2 1.2 0 1 0-2.4 0zM8.4 13.4m-1.1 0a1.1 1.1 0 1 0 2.2 0a1.1 1.1 0 1 0-2.2 0zM12.9 11.2m-1 0a1 1 0 1 0 2 0a1 1 0 1 0-2 0z"/></svg>';
	return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
}

function rcc_register_admin_menu() {
	$cap = 'manage_options';

	$hook = add_menu_page(
		'Relativt Cookie Consent',
		'Cookie Consent',
		$cap,
		RCC_SETTINGS_SLUG,
		'rcc_render_settings_page',
		rcc_menu_icon(),
		81
	);
	rcc_admin_hooks( RCC_SETTINGS_SLUG, $hook );

	// Första undersidan har samma slug som huvudmenyn och ersätter den
	// automatiska dubbletten "Cookie Consent" med "Inställningar".
	add_submenu_page( RCC_SETTINGS_SLUG, 'Relativt Cookie Consent', 'Inställningar', $cap, RCC_SETTINGS_SLUG, 'rcc_render_settings_page' );

	$hook = add_submenu_page( RCC_SETTINGS_SLUG, 'Statistik – Relativt Cookie Consent', 'Statistik', $cap, RCC_STATS_SLUG, 'rcc_render_stats_page' );
	rcc_admin_hooks( RCC_STATS_SLUG, $hook );

	$hook = add_submenu_page( RCC_SETTINGS_SLUG, 'Samtyckeslogg – Relativt Cookie Consent', 'Samtyckeslogg', $cap, RCC_LOG_PAGE_SLUG, 'rcc_render_consent_log_page' );
	rcc_admin_hooks( RCC_LOG_PAGE_SLUG, $hook );

	$hook = add_submenu_page( RCC_SETTINGS_SLUG, 'Skanner – Relativt Cookie Consent', 'Skanner', $cap, RCC_SCANNER_SLUG, 'rcc_render_scanner_page' );
	rcc_admin_hooks( RCC_SCANNER_SLUG, $hook );
}
add_action( 'admin_menu', 'rcc_register_admin_menu' );

/**
 * Gamla adresser (options-general.php?page=relativt-cookie-consent och
 * …-log) skickas vidare till den nya menyn med övriga parametrar
 * (filter, sökning, sortering) kvar. Körs både på admin_page_access_denied,
 * som kommer innan WordPress hinner visa "Du har inte behörighet", och på
 * admin_init som reserv.
 */
function rcc_redirect_legacy_admin_urls() {
	global $pagenow;

	if ( 'options-general.php' !== $pagenow || empty( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$page = sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! in_array( $page, array( RCC_SETTINGS_SLUG, RCC_LOG_PAGE_SLUG ), true ) ) {
		return;
	}

	$args = array();
	foreach ( wp_unslash( $_GET ) as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'page' === $key || ! is_scalar( $value ) ) {
			continue;
		}
		$args[ sanitize_key( $key ) ] = sanitize_text_field( (string) $value );
	}

	wp_safe_redirect( rcc_admin_page_url( $page, $args ) );
	exit;
}
add_action( 'admin_page_access_denied', 'rcc_redirect_legacy_admin_urls' );
add_action( 'admin_init', 'rcc_redirect_legacy_admin_urls', 1 );
