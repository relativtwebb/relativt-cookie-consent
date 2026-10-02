<?php
/**
 * Integration med WP Consent API (wordpress.org/plugins/wp-consent-api).
 *
 * WP Consent API är ett gemensamt gränssnitt som t.ex. WooCommerce,
 * Site Kit och många statistik- och formulärplugin läser. När det är
 * aktivt blir Relativt Cookie Consent leverantören av samtycket:
 *
 *   - Samtyckestypen sätts till "optin" (wp_get_consent_type i PHP,
 *     window.wp_consent_type + händelsen wp_consent_type_defined i JS).
 *   - JS-filen sätter API:ts kategorier med wp_set_consent() vid varje val
 *     och vid sidladdning. API:t skickar då händelsen
 *     wp_listen_for_consent_change till plugin som lyssnar.
 *   - wp_has_consent() i PHP svarar utifrån pluginets egen, versionskollade
 *     cookie (rcc_has_consent), inte utifrån API:ts cookies.
 *
 * Utan WP Consent API händer ingenting här.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Är WP Consent API aktivt, och integrationen påslagen?
 * Stängs av med add_filter( 'rcc_wp_consent_api', '__return_false' ).
 */
function rcc_wp_consent_api_active() {
	return function_exists( 'wp_has_consent' )
		&& function_exists( 'wp_set_consent' )
		&& (bool) apply_filters( 'rcc_wp_consent_api', true );
}

/**
 * WP Consent API:s kategorier → pluginets kategorier.
 *
 * - functional: nödvändiga, alltid tillåtet.
 * - preferences: följer marknadsföring, som personalization_storage i
 *   Google Consent Mode. Sajter som bara har inställningscookies som
 *   besökaren själv bett om kan flytta den till 'necessary' med filtret.
 * - statistics-anonymous: följer statistik. Pluginet skiljer inte på
 *   anonym och vanlig statistik, så det säkra valet är att kräva samtycke.
 */
function rcc_wp_consent_category_map() {
	$map = apply_filters( 'rcc_wp_consent_categories', array(
		'functional'           => 'necessary',
		'preferences'          => 'marketing',
		'statistics'           => 'statistics',
		'statistics-anonymous' => 'statistics',
		'marketing'            => 'marketing',
	) );
	$out = array();
	foreach ( (array) $map as $api_category => $ours ) {
		if ( is_string( $api_category ) && in_array( $ours, array( 'necessary', 'statistics', 'marketing' ), true ) ) {
			$out[ $api_category ] = $ours;
		}
	}
	return $out;
}

/**
 * Samtyckestypen. "optin": inget är tillåtet förrän besökaren sagt ja.
 */
function rcc_wp_consent_type( $type ) {
	return rcc_wp_consent_api_active() ? 'optin' : $type;
}
add_filter( 'wp_get_consent_type', 'rcc_wp_consent_type' );

/**
 * wp_has_consent() i PHP: svara utifrån pluginets cookie. API:ts egna
 * cookies kan vara inaktuella efter en höjd samtyckesversion eller saknas
 * tills JS-filen hunnit köra; pluginets cookie är facit.
 *
 * Kategorin har redan validerats av API:t (okända blir 'functional').
 */
function rcc_wp_has_consent( $has_consent, $category ) {
	if ( ! rcc_wp_consent_api_active() ) {
		return $has_consent;
	}
	$map = rcc_wp_consent_category_map();
	if ( ! isset( $map[ $category ] ) ) {
		return $has_consent;
	}
	return rcc_has_consent( $map[ $category ] );
}
add_filter( 'wp_has_consent', 'rcc_wp_has_consent', 10, 2 );

/**
 * Pluginet följer API:t (syns under Webbplatshälsa).
 */
add_filter( 'wp_consent_api_registered_' . plugin_basename( RCC_PLUGIN_FILE ), '__return_true' );

/**
 * API:ts cookies får samma livslängd som pluginets samtycke (API:ts
 * standard är 30 dagar). De förnyas ändå vid varje sidladdning.
 */
function rcc_wp_consent_cookie_expiration( $days ) {
	if ( ! rcc_wp_consent_api_active() ) {
		return $days;
	}
	$s = rcc_get_settings();
	return max( 1, (int) $s['cookie_expiry_days'] );
}
add_filter( 'wp_consent_api_cookie_expiration', 'rcc_wp_consent_cookie_expiration' );

/**
 * Konfigurationen till JS (rccSettings.wpConsentApi), eller null.
 */
function rcc_wp_consent_api_js_config() {
	if ( ! rcc_wp_consent_api_active() ) {
		return null;
	}
	return array(
		'type'       => 'optin',
		'categories' => rcc_wp_consent_category_map(),
	);
}

/**
 * API:ts skript ska köras före pluginets, så att wp_set_consent() finns
 * när pluginet synkar valet (API:t ber leverantörer att ange det som
 * beroende). API:t registrerar skriptet sent (PHP_INT_MAX - 100), så
 * beroendet läggs till ännu senare, och bara om skriptet faktiskt finns.
 */
function rcc_wp_consent_api_script_dependency() {
	if ( ! rcc_wp_consent_api_active() ) {
		return;
	}
	$scripts = wp_scripts();
	if ( ! isset( $scripts->registered['rcc-script'], $scripts->registered['wp-consent-api'] ) ) {
		return;
	}
	$deps = &$scripts->registered['rcc-script']->deps;
	if ( ! in_array( 'wp-consent-api', $deps, true ) ) {
		$deps[] = 'wp-consent-api';
	}
}
add_action( 'wp_enqueue_scripts', 'rcc_wp_consent_api_script_dependency', PHP_INT_MAX );

/**
 * API:ts cookies i cookiedeklarationen, under Nödvändiga. De sätts av
 * pluginet för att andra plugin ska kunna läsa samtycket.
 */
function rcc_wp_consent_api_registry( $entries ) {
	if ( ! rcc_wp_consent_api_active() || ! class_exists( 'WP_Consent_API' ) || ! isset( WP_Consent_API::$config ) ) {
		return $entries;
	}
	$days      = function_exists( 'wp_consent_api_cookie_expiration' ) ? wp_consent_api_cookie_expiration() : 30;
	$entries[] = array(
		'name'     => WP_Consent_API::$config->consent_cookie_prefix() . '_*',
		'category' => 'necessary',
		'provider' => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
		'purpose'  => 'Sparar dina cookie-val i ett format som sajtens andra tillägg kan läsa.',
		'duration' => rcc_format_days( $days ),
		'plugin'   => 'Relativt Cookie Consent (WP Consent API)',
	);
	return $entries;
}
add_filter( 'rcc_registered_cookies', 'rcc_wp_consent_api_registry', 1 );
