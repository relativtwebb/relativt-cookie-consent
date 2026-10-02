<?php
/**
 * Kakregister: cookies som andra plugin och teman sätter, med kategori.
 *
 * Registret används till två saker:
 *   1. Cookiedeklarationen. Registrerade cookies listas under sin kategori
 *      i [relativt_cookie_declaration] och i /rcc/v1/config.
 *   2. Städning. När besökaren nekar eller drar tillbaka en kategori
 *      raderas registrerade förstapartscookies i den kategorin direkt
 *      (JS), och vid varje sidladdning där de ändå finns kvar (JS, och
 *      PHP för HttpOnly-cookies som JS inte ser).
 *
 * Andra plugin registrerar sina cookies med filtret rcc_registered_cookies:
 *
 *   add_filter( 'rcc_registered_cookies', function ( $cookies ) {
 *       $cookies[] = array(
 *           'name'     => 'xf_src',              // * = valfria tecken: 'xf_*'
 *           'category' => 'statistics marketing', // endera räcker
 *           'provider' => 'Relativt Formulär',
 *           'purpose'  => 'Sparar vilken kampanj besökaren kom från.',
 *           'duration' => '90 dagar',
 *       );
 *       return $cookies;
 *   } );
 *
 * Cookies som registrerats via WP Consent API (wp_add_cookie_info) tas med
 * automatiskt när det pluginet är aktivt.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Poster för cookies från verktyg som pluginet själv laddar. De finns med
 * för städningens skull även när verktyget inte har ett ID ifyllt, eftersom
 * samma cookies kan sättas via Google Tag Manager eller egen kod.
 *
 * 'declare' => false: deklarationen listar redan verktygens cookies (med
 * rätt ID insatt) när verktyget är på, se rcc_vendor_cookies().
 */
function rcc_builtin_registered_cookies( $s ) {
	$known = rcc_vendor_cookies();
	$text  = function ( $vendor, $name, $index ) use ( $known ) {
		foreach ( isset( $known[ $vendor ] ) ? $known[ $vendor ] : array() as $row ) {
			if ( $row[0] === $name ) {
				return $row[ $index ];
			}
		}
		return '';
	};
	$entry = function ( $name, $vendor, $category, $lookup ) use ( $text ) {
		return array(
			'name'     => $name,
			'category' => $category,
			'provider' => $text( $vendor, $lookup, 1 ),
			'purpose'  => $text( $vendor, $lookup, 2 ),
			'duration' => $text( $vendor, $lookup, 3 ),
			'declare'  => false,
			'plugin'   => 'Relativt Cookie Consent',
		);
	};

	$ga4 = rcc_vendor_category( 'ga4', $s );

	return array(
		$entry( '_ga', 'ga4', $ga4, '_ga' ),
		$entry( '_ga_*', 'ga4', $ga4, '_ga_{id}' ),
		$entry( '_gcl_*', 'google_ads', 'marketing', '_gcl_au' ),
		$entry( '_fbp', 'meta_pixel', 'marketing', '_fbp' ),
		$entry( '_fbc', 'meta_pixel', 'marketing', '_fbc' ),
	);
}

/**
 * Kategorinycklar ur en registrerad post: 'statistics', 'statistik',
 * 'statistics marketing' eller array( 'statistics', 'marketing' ).
 * Tom array om någon del är okänd.
 */
function rcc_registry_categories( $value ) {
	if ( is_string( $value ) ) {
		$value = preg_split( '/[\s,]+/', trim( $value ) );
	}
	if ( ! is_array( $value ) ) {
		return array();
	}
	$out = array();
	foreach ( $value as $part ) {
		if ( ! is_string( $part ) || '' === trim( $part ) ) {
			continue;
		}
		$cat = rcc_parse_category( $part );
		if ( ! $cat ) {
			return array();
		}
		$out[ $cat ] = $cat;
	}
	// Nödvändiga tillsammans med en annan kategori är meningslöst: posten
	// skulle aldrig städas. Räkna den som nödvändig.
	if ( isset( $out['necessary'] ) ) {
		return array( 'necessary' );
	}
	return array_values( $out );
}

/**
 * Är cookienamnet (eller mönstret) giltigt? Tecknen följer RFC 6265
 * (token), * betyder valfria tecken. Ett mönster med * måste ha minst tre
 * andra tecken, så att ingen råkar registrera alla cookies.
 */
function rcc_valid_cookie_pattern( $name ) {
	if ( ! is_string( $name ) || '' === $name || strlen( $name ) > 200 ) {
		return false;
	}
	if ( ! preg_match( '/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/', $name ) ) {
		return false;
	}
	if ( false !== strpos( $name, '*' ) && strlen( str_replace( '*', '', $name ) ) < 3 ) {
		return false;
	}
	return true;
}

/**
 * Cookies registrerade via WP Consent API (wp_add_cookie_info), översatta
 * till pluginets kategorier. Bara HTTP-cookies som besökare kan få;
 * administratörscookies och localStorage hoppas över.
 */
function rcc_wp_consent_api_cookie_info() {
	if ( ! function_exists( 'wp_get_cookie_info' ) || ! apply_filters( 'rcc_import_wp_consent_cookie_info', true ) ) {
		return array();
	}
	$info = wp_get_cookie_info();
	if ( ! is_array( $info ) ) {
		return array();
	}
	$map = rcc_wp_consent_category_map();
	$out = array();
	foreach ( $info as $name => $cookie ) {
		if ( ! is_array( $cookie ) || ! empty( $cookie['administratorCookie'] ) ) {
			continue;
		}
		if ( isset( $cookie['type'] ) && 'HTTP' !== strtoupper( (string) $cookie['type'] ) ) {
			continue;
		}
		$api_category = isset( $cookie['category'] ) ? (string) $cookie['category'] : 'marketing';
		$out[]        = array(
			// Platshållare som _ga_{ID} blir mönster: _ga_*.
			'name'     => preg_replace( '/\{[^}]*\}/', '*', (string) $name ),
			'category' => isset( $map[ $api_category ] ) ? $map[ $api_category ] : 'marketing',
			'provider' => isset( $cookie['plugin_or_service'] ) ? (string) $cookie['plugin_or_service'] : '',
			'purpose'  => isset( $cookie['function'] ) ? (string) $cookie['function'] : '',
			'duration' => isset( $cookie['expires'] ) ? (string) $cookie['expires'] : '',
			'plugin'   => isset( $cookie['plugin_or_service'] ) ? (string) $cookie['plugin_or_service'] : 'WP Consent API',
		);
	}
	return $out;
}

/**
 * Ogiltiga poster i registret. Sparas för admin (fliken Cookiedeklaration)
 * och rapporteras med _doing_it_wrong en gång per post, och bara på
 * admin-sidor: på frontend, i REST och före headers skulle en synlig
 * notis förstöra svaret.
 *
 * @param string|null $name Lägg till en post, eller null för att läsa.
 * @return string[]
 */
function rcc_registry_error( $name = null ) {
	static $errors = array();
	if ( null === $name ) {
		return array_keys( $errors );
	}
	if ( isset( $errors[ $name ] ) ) {
		return array_keys( $errors );
	}
	$errors[ $name ] = true;
	if ( function_exists( '_doing_it_wrong' ) && is_admin() && ! wp_doing_ajax() && did_action( 'admin_init' ) ) {
		_doing_it_wrong(
			'rcc_registered_cookies',
			esc_html( sprintf( 'Ogiltig post i kakregistret (%s). Kräver ett giltigt name och category (necessary, statistics, marketing).', $name ) ),
			'1.4.0'
		);
	}
	return array_keys( $errors );
}

/**
 * Hela registret, validerat. Varje post:
 *   name       Namn eller mönster med * (t.ex. '_ga_*').
 *   categories Kategorinycklar; endera räcker för att cookien får finnas.
 *   category   Första kategorin (bekvämlighet).
 *   provider, purpose, duration  Text för deklarationen.
 *   declare    Om posten listas i cookiedeklarationen.
 *   plugin     Vem som registrerade posten (visas i admin).
 *
 * Ogiltiga poster hoppas över, se rcc_registry_error().
 *
 * @param array|null $s Inställningar.
 * @return array
 */
function rcc_registered_cookies( $s = null ) {
	$s = $s ? $s : rcc_get_settings();

	$entries = array_merge( rcc_builtin_registered_cookies( $s ), rcc_wp_consent_api_cookie_info() );

	/**
	 * Registrera cookies som ert plugin eller tema sätter.
	 *
	 * @param array $entries  Poster: array( 'name', 'category', 'provider',
	 *                        'purpose', 'duration' ), valfritt 'declare'
	 *                        (false = används bara för städning) och 'plugin'.
	 * @param array $settings Sajtens inställningar.
	 */
	$entries = apply_filters( 'rcc_registered_cookies', $entries, $s );

	$out = array();
	foreach ( is_array( $entries ) ? $entries : array() as $entry ) {
		$name       = ( is_array( $entry ) && isset( $entry['name'] ) && is_string( $entry['name'] ) ) ? trim( $entry['name'] ) : '';
		$categories = ( is_array( $entry ) && isset( $entry['category'] ) ) ? rcc_registry_categories( $entry['category'] ) : array();

		if ( ! rcc_valid_cookie_pattern( $name ) || ! $categories ) {
			rcc_registry_error( $name ? $name : '(namn saknas)' );
			continue;
		}

		$string = function ( $key ) use ( $entry ) {
			return ( isset( $entry[ $key ] ) && is_scalar( $entry[ $key ] ) ) ? sanitize_text_field( (string) $entry[ $key ] ) : '';
		};

		$out[] = array(
			'name'       => $name,
			'categories' => $categories,
			'category'   => $categories[0],
			'provider'   => $string( 'provider' ),
			'purpose'    => $string( 'purpose' ),
			'duration'   => $string( 'duration' ),
			'declare'    => ! isset( $entry['declare'] ) || (bool) $entry['declare'],
			'plugin'     => $string( 'plugin' ),
		);
	}
	return $out;
}

/**
 * Cookies som aldrig raderas, oavsett vad som registrerats: pluginets
 * egen cookie, WP Consent API:s cookies och WordPress egna (inloggning,
 * inställningar, språk, kommentarer, lösenordsskyddade sidor).
 */
function rcc_protected_cookies() {
	$protected = array(
		rcc_cookie_name(),
		'wp_consent_*',
		'wordpress_*',
		'wp-settings-*',
		'wp_lang',
		'comment_author_*',
		'wp-postpass_*',
		'PHPSESSID',
	);
	if ( function_exists( 'rcc_wp_consent_api_active' ) && rcc_wp_consent_api_active() && class_exists( 'WP_Consent_API' ) && isset( WP_Consent_API::$config ) ) {
		$protected[] = WP_Consent_API::$config->consent_cookie_prefix() . '_*';
	}

	/**
	 * Fler cookies som städningen aldrig får röra. Namn eller mönster med *.
	 */
	return array_values( array_unique( array_filter( (array) apply_filters( 'rcc_protected_cookies', $protected ), 'is_string' ) ) );
}

/**
 * Matchar ett cookienamn mot ett namn eller mönster med *. Skiftlägeskänsligt,
 * som cookienamn är.
 */
function rcc_cookie_matches( $name, $pattern ) {
	if ( false === strpos( $pattern, '*' ) ) {
		return $name === $pattern;
	}
	$regex = '/^' . implode( '.*', array_map( function ( $part ) {
		return preg_quote( $part, '/' );
	}, explode( '*', $pattern ) ) ) . '$/';
	return (bool) preg_match( $regex, $name );
}

/**
 * Vilka av cookienamnen som ska raderas med det här samtycket. En cookie
 * raderas när den matchar minst en registrerad post och ingen av de
 * matchande posterna har en godkänd (eller nödvändig) kategori. Skyddade
 * cookies raderas aldrig.
 *
 * @param string[] $names   Cookienamn.
 * @param array    $consent Ett gällande samtycke (rcc_get_consent()).
 * @param array    $registry Registret (rcc_registered_cookies()).
 * @return string[]
 */
function rcc_cookies_to_delete( $names, $consent, $registry = null ) {
	if ( ! is_array( $consent ) ) {
		return array();
	}
	$registry  = null === $registry ? rcc_registered_cookies() : $registry;
	$protected = rcc_protected_cookies();
	$delete    = array();

	foreach ( $names as $name ) {
		foreach ( $protected as $pattern ) {
			if ( rcc_cookie_matches( $name, $pattern ) ) {
				continue 2;
			}
		}
		$matched = false;
		$allowed = false;
		foreach ( $registry as $entry ) {
			if ( ! rcc_cookie_matches( $name, $entry['name'] ) ) {
				continue;
			}
			$matched = true;
			foreach ( $entry['categories'] as $cat ) {
				if ( 'necessary' === $cat || ! empty( $consent[ $cat ] ) ) {
					$allowed = true;
					break 2;
				}
			}
		}
		if ( $matched && ! $allowed ) {
			$delete[] = $name;
		}
	}
	return $delete;
}

/**
 * Registret i den form JS-filen behöver (rccSettings.cookieRegistry).
 */
function rcc_cookie_registry_for_js( $s = null ) {
	return array_map( function ( $entry ) {
		return array(
			'name'       => $entry['name'],
			'categories' => $entry['categories'],
		);
	}, rcc_registered_cookies( $s ) );
}

/* -------------------------------------------------------------------------
 * Städning i PHP
 * ---------------------------------------------------------------------- */

/**
 * Cookienamnen i förfrågan, ur Cookie-headern. PHP byter punkt och
 * mellanslag mot understreck i $_COOKIE, så headern ger de riktiga namnen.
 */
function rcc_request_cookie_names() {
	$header = isset( $_SERVER['HTTP_COOKIE'] ) ? (string) wp_unslash( $_SERVER['HTTP_COOKIE'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- bara namnen används, och de valideras.
	$names  = array();
	foreach ( explode( ';', $header ) as $pair ) {
		$parts = explode( '=', $pair, 2 );
		$name  = trim( $parts[0] );
		if ( '' !== $name && preg_match( '/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/', $name ) ) {
			$names[ $name ] = $name;
		}
	}
	return array_values( $names );
}

/**
 * Domänerna en cookie kan ha satts på: värden utan domän (host-only)
 * samt värden och varje överordnad domän med punkt framför.
 */
function rcc_cookie_domains( $host ) {
	$domains = array( '' );
	$host    = trim( strtolower( (string) $host ), '[]' );
	if ( '' === $host || filter_var( $host, FILTER_VALIDATE_IP ) || false === strpos( $host, '.' ) ) {
		return $domains;
	}
	$parts = explode( '.', $host );
	for ( $i = 0; $i < count( $parts ) - 1; $i++ ) {
		$domains[] = '.' . implode( '.', array_slice( $parts, $i ) );
	}
	if ( defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ) {
		$domains[] = '.' . ltrim( strtolower( (string) COOKIE_DOMAIN ), '.' );
	}
	return array_values( array_unique( $domains ) );
}

/**
 * Raderar nekade registrerade cookies som webbläsaren fortfarande skickar,
 * t.ex. HttpOnly-cookies satta av servern, som JS inte kan se. Körs bara
 * när besökaren har ett gällande samtycke där någon kategori är nekad, och
 * bara när en sådan cookie faktiskt finns i förfrågan.
 */
function rcc_cleanup_denied_cookies() {
	if ( headers_sent() || ! apply_filters( 'rcc_cleanup_cookies_on_request', true ) ) {
		return;
	}
	$consent = rcc_get_consent();
	if ( ! $consent || ( $consent['statistics'] && $consent['marketing'] ) ) {
		return;
	}
	$names = rcc_request_cookie_names();
	if ( ! $names ) {
		return;
	}
	$delete = rcc_cookies_to_delete( $names, $consent );
	if ( ! $delete ) {
		return;
	}

	$host  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$paths = array_unique( array_filter( array(
		'/',
		defined( 'COOKIEPATH' ) ? COOKIEPATH : '',
		defined( 'SITECOOKIEPATH' ) ? SITECOOKIEPATH : '',
	) ) );

	// Svaret får inte cachas med raderingarna, då skulle de nå andra
	// besökare. DONOTCACHEPAGE gäller cache-plugin som sparar sidan i PHP.
	nocache_headers();
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}

	foreach ( $delete as $name ) {
		foreach ( rcc_cookie_domains( $host ) as $domain ) {
			foreach ( $paths as $path ) {
				setcookie( $name, '', array(
					'expires'  => 1,
					'path'     => $path,
					'domain'   => $domain,
					'secure'   => is_ssl(),
					'httponly' => false,
					'samesite' => 'Lax',
				) );
			}
		}
	}

	/**
	 * Körs efter att nekade cookies raderats i PHP.
	 *
	 * @param string[] $delete  Namnen.
	 * @param array    $consent Samtycket.
	 */
	do_action( 'rcc_cookies_deleted', $delete, $consent );
}
add_action( 'send_headers', 'rcc_cleanup_denied_cookies' );
