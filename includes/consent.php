<?php
/**
 * Besökarens samtycke i PHP, för andra plugin och teman.
 *
 *   if ( function_exists( 'rcc_has_consent' ) && rcc_has_consent( 'statistics' ) ) { … }
 *
 * Läser samtyckescookien och godtar den bara om den har samma
 * samtyckesversion som sajten, precis som JS-filen. Ett samtycke till en
 * äldre version räknas som inget samtycke, så att PHP och JS aldrig ger
 * olika svar.
 *
 * Tänk på sidcache: en cachad sida byggs en gång och visas för alla. Gör
 * därför beslut som ska följa besökaren i JS (window.rcc), och använd PHP
 * för förfrågningar som inte cachas: formulärinskick, AJAX och REST.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sajtens samtyckesversion (minst 1). Samma värde som skickas till JS.
 */
function rcc_current_consent_version() {
	$s = rcc_get_settings();
	return max( 1, (int) $s['consent_version'] );
}

/**
 * Sanningsvärde som i JavaScript (!!värde): false, null, 0 och tom sträng
 * är falska, allt annat sant (även "0" och tomma arrayer). JS-filen läser
 * kategorierna så, och PHP ska ge exakt samma svar.
 */
function rcc_js_truthy( $value ) {
	if ( null === $value || false === $value || '' === $value ) {
		return false;
	}
	if ( is_int( $value ) || is_float( $value ) ) {
		return 0 != $value && ! is_nan( (float) $value ); // phpcs:ignore Universal.Operators.StrictComparisons -- 0 och 0.0.
	}
	return true;
}

/**
 * parseInt( värde, 10 ) som i JavaScript, eller null för NaN. Värdet görs
 * om till sträng som JS gör (arrayer blir kommaseparerade, true/false
 * och null blir ord) och de inledande siffrorna läses.
 */
function rcc_js_parse_int( $value ) {
	if ( is_int( $value ) ) {
		return $value;
	}
	if ( is_float( $value ) ) {
		$value = is_finite( $value ) ? ( abs( $value ) >= 1e21 ? sprintf( '%.0e', $value ) : sprintf( '%.0f', $value < 0 ? ceil( $value ) : floor( $value ) ) ) : 'NaN';
	} elseif ( is_array( $value ) ) {
		$value = implode( ',', array_map( function ( $part ) {
			return ( null === $part || is_array( $part ) ) ? '' : ( is_bool( $part ) ? ( $part ? 'true' : 'false' ) : (string) $part );
		}, $value ) );
	} elseif ( ! is_string( $value ) ) {
		return null;
	}
	if ( ! preg_match( '/^\s*([+-]?\d+)/', $value, $m ) ) {
		return null;
	}
	return (int) $m[1];
}

/**
 * Tolkar cookievärdet till ett samtycke, eller null om det inte gäller.
 * Separat från rcc_get_consent() så att det går att testa utan $_COOKIE.
 *
 * Allt tolkas som i JS-filen: versionen som parseInt( version ) || 1
 * (saknas den, eller är den 0 eller inte ett tal, räknas den som 1;
 * cookies från 1.0.0 saknar version) och kategorierna som !!värde.
 *
 * @param string $raw Cookievärdet, URL-avkodat och utan WordPress snedstreck.
 * @return array|null array( necessary, statistics, marketing, id, version, timestamp ).
 */
function rcc_parse_consent_cookie( $raw ) {
	if ( ! is_string( $raw ) || '' === $raw || strlen( $raw ) > 4096 ) {
		return null;
	}
	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) ) {
		return null;
	}

	$version = isset( $data['version'] ) ? rcc_js_parse_int( $data['version'] ) : null;
	if ( ! $version ) {
		$version = 1;
	}
	if ( rcc_current_consent_version() !== $version ) {
		return null;
	}

	$id = ( isset( $data['id'] ) && is_string( $data['id'] ) && preg_match( '/^[A-Za-z0-9]{12,32}$/', $data['id'] ) ) ? $data['id'] : '';

	return array(
		'necessary'  => true,
		'statistics' => isset( $data['statistics'] ) && rcc_js_truthy( $data['statistics'] ),
		'marketing'  => isset( $data['marketing'] ) && rcc_js_truthy( $data['marketing'] ),
		'id'         => $id,
		'version'    => $version,
		'timestamp'  => ( isset( $data['timestamp'] ) && is_string( $data['timestamp'] ) ) ? substr( $data['timestamp'], 0, 40 ) : '',
	);
}

/**
 * Besökarens gällande samtycke, eller null om besökaren inte gjort ett
 * val för sajtens nuvarande samtyckesversion.
 *
 * @return array|null array( 'necessary' => true, 'statistics' => bool,
 *                    'marketing' => bool, 'id' => string, 'version' => int,
 *                    'timestamp' => string ).
 */
function rcc_get_consent() {
	static $cache = array();

	$name    = rcc_cookie_name();
	$raw     = ( isset( $_COOKIE[ $name ] ) && is_string( $_COOKIE[ $name ] ) ) ? wp_unslash( $_COOKIE[ $name ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON som valideras fält för fält nedan.
	$version = rcc_current_consent_version();
	$key     = $version . '|' . $raw;

	if ( ! array_key_exists( $key, $cache ) ) {
		$cache = array( $key => '' === $raw ? null : rcc_parse_consent_cookie( $raw ) );
	}

	/**
	 * Ändra det tolkade samtycket, t.ex. för tester.
	 *
	 * @param array|null $consent Samtycket eller null.
	 */
	return apply_filters( 'rcc_get_consent', $cache[ $key ] );
}

/**
 * Har besökaren samtyckt till kategorin?
 *
 * Nödvändiga ger alltid true. Statistik och marknadsföring ger true bara
 * när besökaren uttryckligen godkänt kategorin för sajtens nuvarande
 * samtyckesversion. Okända kategorier ger false.
 *
 * @param string $category 'necessary', 'statistics' eller 'marketing'
 *                         (svenska namn går också: 'statistik', 'marknadsföring').
 * @return bool
 */
function rcc_has_consent( $category ) {
	$key     = is_string( $category ) ? rcc_parse_category( $category ) : null;
	$consent = rcc_get_consent();

	if ( 'necessary' === $key ) {
		$has = true;
	} elseif ( 'statistics' === $key || 'marketing' === $key ) {
		$has = $consent ? (bool) $consent[ $key ] : false;
	} else {
		$has = false;
	}

	/**
	 * Ändra svaret, t.ex. för att alltid ge false på staging.
	 *
	 * @param bool       $has      Svaret.
	 * @param string     $category Kategorin som efterfrågades (som den skickades in).
	 * @param array|null $consent  Det tolkade samtycket.
	 */
	return (bool) apply_filters( 'rcc_has_consent', $has, $category, $consent );
}
