<?php
/**
 * Publikt konfigurationsendpoint för headless-frontends:
 * GET /wp-json/rcc/v1/config
 *
 * En frontend på en annan domän ritar sin egen cookie-ruta och ser varken
 * pluginets cookie, HTML eller rccSettings. Endpointet lämnar ut det den
 * behöver för att bete sig som pluginet: texter, cookieformat,
 * samtyckesversion, Consent Mode-defaulten, vilka verktyg som är på och
 * under vilken kategori, samt vart valet ska loggas.
 *
 * Svaret byggs från en uttrycklig lista över nycklar, aldrig "allt utom".
 * Ett fält som läggs till i inställningarna i framtiden kommer alltså inte
 * med av sig självt. Egen kod och egen CSS lämnas medvetet utanför: koden
 * är rå HTML med <script> som en frontend inte kan köra säkert, och CSS:en
 * är skriven för pluginets egen markup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bygger konfigurationen från inställningarna. Separat från callbacken så
 * att den går att anropa och testa utan en REST-förfrågan.
 */
function rcc_rest_config_data( $s ) {
	$texts = array();
	foreach ( array(
		'banner_heading',
		'banner_text',
		'privacy_link_text',
		'privacy_url',
		'necessary_label',
		'necessary_desc',
		'statistics_label',
		'statistics_desc',
		'marketing_label',
		'marketing_desc',
		'btn_accept_all',
		'btn_reject_all',
		'btn_customize',
		'btn_save',
		'floating_button_label',
		'consent_id_label',
	) as $key ) {
		$texts[ $key ] = isset( $s[ $key ] ) ? (string) $s[ $key ] : '';
	}

	// Samma villkor som utskriften i vendors.php: tomt fält = verktyget
	// finns inte. Kategorin hämtas från samma ställe som utskriften.
	$vendors = array();
	foreach ( rcc_vendors() as $vendor => $def ) {
		if ( empty( $s[ $def['setting'] ] ) ) {
			continue;
		}
		$entry = array( 'id' => (string) $s[ $def['setting'] ] );
		if ( 'gtm' === $vendor ) {
			// "blocked" = vänta på samtycke, "always" = ladda direkt och
			// låt Consent Mode styra taggarna, precis som på en vanlig sajt.
			$entry['mode'] = (string) $s['gtm_load_mode'];
		}
		$entry['category']  = rcc_vendor_category( $vendor, $s );
		$vendors[ $vendor ] = $entry;
	}

	$config = array(
		'plugin_version'        => RCC_VERSION,
		'cookie'                => array(
			'name'        => rcc_cookie_name(),
			'expiry_days' => (int) $s['cookie_expiry_days'],
		),
		'consent_version'       => max( 1, (int) $s['consent_version'] ),
		'log_endpoint'          => rcc_consent_log_enabled() ? esc_url_raw( rest_url( 'rcc/v1/consent' ) ) : '',
		'consent_mode_defaults' => rcc_consent_mode_defaults(),
		'texts'                 => $texts,
		'vendors'               => $vendors,
		'gsc_verification'      => (string) $s['gsc_verification'],
		'appearance'            => array(
			'layout'                   => (string) $s['banner_layout'],
			'show_backdrop'            => ! empty( $s['show_backdrop'] ),
			'color_bg'                 => (string) $s['color_bg'],
			'color_text'               => (string) $s['color_text'],
			'color_accent'             => (string) $s['color_accent'],
			'color_button_bg'          => (string) $s['color_button_bg'],
			'color_button_text'        => (string) $s['color_button_text'],
			'border_radius'            => (int) $s['border_radius'],
			'inherit_font'             => ! empty( $s['inherit_font'] ),
			'show_floating_button'     => ! empty( $s['show_floating_button'] ),
			'floating_button_position' => (string) $s['floating_button_position'],
		),
	);

	/**
	 * Låter en sajt lägga till eller ta bort fält i svaret, samma mönster
	 * som rcc_script_config för den inbyggda rutan.
	 */
	return apply_filters( 'rcc_rest_config', $config, $s );
}

function rcc_register_config_rest_route() {
	register_rest_route( 'rcc/v1', '/config', array(
		'methods'             => 'GET',
		'callback'            => 'rcc_rest_get_config',
		'permission_callback' => '__return_true', // Samma uppgifter som redan står i sidans HTML på en vanlig sajt.
	) );
}
add_action( 'rest_api_init', 'rcc_register_config_rest_route' );

function rcc_rest_get_config( WP_REST_Request $request ) {
	$config = rcc_rest_config_data( rcc_get_settings() );

	// En tom PHP-array blir [] i JSON. Objekten ska alltid vara {} så att
	// en typad frontend inte får en lista där den väntar sig ett objekt,
	// t.ex. "vendors" på en sajt där inga verktyg är ifyllda.
	foreach ( array( 'cookie', 'consent_mode_defaults', 'texts', 'vendors', 'appearance' ) as $key ) {
		if ( isset( $config[ $key ] ) && array() === $config[ $key ] ) {
			$config[ $key ] = new stdClass();
		}
	}

	// Konfigurationen ändras sällan och en frontend hämtar den ändå vid
	// bygget, så en kort publik cache avlastar WordPress utan att en
	// ändring i admin dröjer märkbart.
	$response = new WP_REST_Response( $config, 200 );
	$response->header( 'Cache-Control', 'public, max-age=300' );
	return $response;
}
