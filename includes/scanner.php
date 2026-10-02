<?php
/**
 * Skriptskanner: hämtar sajtens sidor som en anonym besökare och listar
 * tredjepartsskript och iframes, och om pluginet blockerar dem.
 *
 * Skannern läser den HTML som skickas till besökaren (efter pluginets
 * egen bearbetning). Skript som laddas av annan JS efteråt, t.ex. taggar
 * i Google Tag Manager, syns inte i HTML:en och kommer därför inte med.
 * Den ersätter alltså inte en kontroll i webbläsarens utvecklarverktyg.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RCC_SCAN_OPTION', 'rcc_scan_results' );
define( 'RCC_SCAN_MAX_PAGES', 15 );

/* -------------------------------------------------------------------------
 * Analys av en sida
 * ---------------------------------------------------------------------- */

/**
 * Alla tredjepartsfynd i en HTML-sträng. Varje fynd:
 *   host, type (script/inline/iframe), category (blockerad kategori eller
 *   tom sträng), sample (adress eller kort utdrag).
 */
function rcc_scan_html( $html, $forced_category = '' ) {
	$own      = rcc_own_host();
	$findings = array();

	$scripts = rcc_find_scripts( $html );
	if ( $scripts ) {
		foreach ( $scripts as $m ) {
			$attrs = rcc_parse_attributes( $m[0] );
			$code  = $m[1];
			$type  = isset( $attrs['type'] ) ? strtolower( trim( $attrs['type'] ) ) : '';

			if ( isset( $attrs['data-rcc-core'] ) ) {
				continue;
			}

			$category = $forced_category;
			if ( ! $category && isset( $attrs['data-cookiecategory'] ) && in_array( $type, array( 'text/plain', 'application/json' ), true ) ) {
				$category = trim( $attrs['data-cookiecategory'] );
			}

			// Egen kod: ett HTML-block lagrat som JSON. Analysera innehållet.
			if ( isset( $attrs['data-rcc-html-block'] ) || isset( $attrs['data-html-block'] ) ) {
				$inner = isset( $attrs['data-rcc-html-block'] ) ? json_decode( $code, true ) : $code;
				if ( is_string( $inner ) ) {
					$findings = array_merge( $findings, rcc_scan_html( $inner, $category ? $category : 'statistics' ) );
				}
				continue;
			}

			$is_blocked_plain = 'text/plain' === $type && $category;
			if ( ! $is_blocked_plain && ! rcc_is_js_type( $type ) ) {
				continue;
			}

			$src = '';
			if ( isset( $attrs['data-cookiesrc'] ) ) {
				$src = $attrs['data-cookiesrc'];
			} elseif ( isset( $attrs['src'] ) ) {
				$src = $attrs['src'];
			}

			if ( '' !== trim( $src ) ) {
				$host = rcc_url_host( $src );
				if ( $host && $host !== $own ) {
					$findings[] = array(
						'host'     => $host,
						'path'     => (string) wp_parse_url( $src, PHP_URL_PATH ),
						'type'     => 'script',
						'category' => $category,
						'sample'   => $src,
					);
				}
				continue;
			}

			foreach ( rcc_scan_inline_hosts( $code, $attrs ) as $host ) {
				$findings[] = array(
					'host'     => $host,
					'path'     => '',
					'type'     => 'inline',
					'category' => $category,
					'sample'   => rcc_scan_excerpt( $code, $host ),
				);
			}
		}
	}

	if ( preg_match_all( '#<iframe\b([^>]*)>#i', $html, $iframes, PREG_SET_ORDER ) ) {
		foreach ( $iframes as $m ) {
			$attrs = rcc_parse_attributes( $m[1] );
			$src   = isset( $attrs['data-cookiesrc'] ) ? $attrs['data-cookiesrc'] : ( isset( $attrs['src'] ) ? $attrs['src'] : '' );
			$host  = rcc_url_host( $src );
			if ( ! $host || $host === $own ) {
				continue;
			}
			$category = $forced_category;
			if ( ! $category && isset( $attrs['data-cookiecategory'] ) && isset( $attrs['data-cookiesrc'] ) ) {
				$category = trim( $attrs['data-cookiecategory'] );
			}
			$findings[] = array(
				'host'     => $host,
				'path'     => (string) wp_parse_url( $src, PHP_URL_PATH ),
				'type'     => 'iframe',
				'category' => $category,
				'sample'   => $src,
			);
		}
	}

	return $findings;
}

/**
 * Tredjepartsvärdar som ett inline-skript hör till. Kända verktyg känns
 * igen på domän eller signatur. Okända värdar räknas bara när skriptet
 * ser ut att ladda något (createElement/appendChild), annars blir det
 * för mycket brus från adresser i inställningsobjekt.
 */
function rcc_scan_inline_hosts( $code, $attrs ) {
	if ( '' === trim( (string) $code ) ) {
		return array();
	}
	$own     = rcc_own_host();
	$hosts   = array();
	$loader  = (bool) preg_match( '/createElement|appendChild|insertBefore|\.src\s*=/', $code );
	$is_data = isset( $attrs['id'] ) && '-js-extra' === substr( $attrs['id'], -9 );

	if ( preg_match_all( '#(?:https?:)?(?:\\\\?/){2}([a-z0-9][a-z0-9.-]*\.[a-z]{2,})#i', $code, $m ) ) {
		foreach ( array_unique( array_map( 'strtolower', $m[1] ) ) as $host ) {
			if ( $host === $own || rcc_host_matches( $host, $own ) ) {
				continue;
			}
			$known = rcc_known_domain_for( $host );
			if ( ( $known && in_array( $known['category'], array( 'statistics', 'marketing', 'conflict' ), true ) && ! $is_data ) || ( $loader && ! $is_data ) ) {
				$hosts[ $host ] = true;
			}
		}
	}

	foreach ( rcc_known_domains() as $domain => $info ) {
		if ( empty( $info['signatures'] ) || false !== strpos( $domain, '/' ) || $is_data ) {
			continue;
		}
		foreach ( $info['signatures'] as $signature ) {
			if ( false !== stripos( $code, $signature ) ) {
				$hosts[ $domain ] = true;
				break;
			}
		}
	}

	// En signatur och en adress för samma verktyg ska inte ge två rader.
	$out = array_keys( $hosts );
	foreach ( $out as $i => $host ) {
		foreach ( $out as $other ) {
			if ( $other !== $host && rcc_host_matches( $other, $host ) && rcc_known_domain_for( $other ) && rcc_known_domain_for( $host ) && rcc_known_domain_for( $other )['name'] === rcc_known_domain_for( $host )['name'] ) {
				unset( $out[ $i ] );
				break;
			}
		}
	}
	return array_values( $out );
}

/**
 * Kort utdrag kring första förekomsten av värden, för att visa var
 * skriptet kommer ifrån.
 */
function rcc_scan_excerpt( $code, $host ) {
	$code = preg_replace( '/\s+/', ' ', (string) $code );
	$pos  = stripos( $code, $host );
	if ( false === $pos ) {
		$pos = 0;
	}
	$start = max( 0, $pos - 40 );
	return ( $start > 0 ? '…' : '' ) . trim( substr( $code, $start, 120 ) ) . ( strlen( $code ) > $start + 120 ? '…' : '' );
}

/* -------------------------------------------------------------------------
 * Sidor att skanna
 * ---------------------------------------------------------------------- */

/**
 * Startsidan, de senast ändrade sidorna, senaste inläggen och ett exempel
 * per övrig publik inläggstyp (t.ex. produkter). Filtret
 * rcc_scanner_urls ändrar urvalet.
 */
function rcc_scanner_default_urls() {
	$urls = array( home_url( '/' ) );

	$pages = get_posts( array(
		'post_type'        => 'page',
		'post_status'      => 'publish',
		'numberposts'      => 8,
		'orderby'          => 'modified',
		'order'            => 'DESC',
		'has_password'     => false,
		'suppress_filters' => false,
	) );
	foreach ( $pages as $page ) {
		$urls[] = get_permalink( $page );
	}

	$posts = get_posts( array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'numberposts'  => 2,
		'has_password' => false,
	) );
	foreach ( $posts as $post ) {
		$urls[] = get_permalink( $post );
	}

	$types = get_post_types( array( 'public' => true, '_builtin' => false ) );
	foreach ( array_slice( array_values( $types ), 0, 3 ) as $type ) {
		$one = get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'numberposts' => 1, 'has_password' => false ) );
		if ( $one ) {
			$urls[] = get_permalink( $one[0] );
		}
	}

	$urls = array_values( array_unique( array_filter( $urls ) ) );
	$urls = apply_filters( 'rcc_scanner_urls', $urls );
	return array_slice( $urls, 0, RCC_SCAN_MAX_PAGES );
}

/**
 * Om adressen hör till sajten: samma värd (med eller utan www), http(s)
 * och samma port som sajten. Skannern hämtar aldrig andra sajter eller
 * andra tjänster på samma server.
 */
function rcc_scanner_url_allowed( $url ) {
	$parts = wp_parse_url( $url );
	if ( ! $parts || empty( $parts['host'] ) || empty( $parts['scheme'] ) ) {
		return false;
	}
	if ( ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
		return false;
	}
	if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
		return false;
	}

	$host = strtolower( $parts['host'] );
	$own  = rcc_own_host();
	if ( $host !== $own && preg_replace( '/^www\./', '', $host ) !== preg_replace( '/^www\./', '', $own ) ) {
		return false;
	}

	$home      = wp_parse_url( home_url() );
	$own_port  = isset( $home['port'] ) ? (int) $home['port'] : 0;
	$port      = isset( $parts['port'] ) ? (int) $parts['port'] : 0;
	$defaults  = array( 0, 80, 443 );
	if ( $port !== $own_port && ! ( in_array( $port, $defaults, true ) && in_array( $own_port, $defaults, true ) ) ) {
		return false;
	}
	return true;
}

/* -------------------------------------------------------------------------
 * Hämtning och resultat
 * ---------------------------------------------------------------------- */

/**
 * Hämtar en sida som en anonym besökare (inga cookies skickas med).
 * Returnerar array( status, error, findings ).
 */
function rcc_scan_url( $url ) {
	// Omdirigeringar följs för hand, och bara inom sajten, så att en
	// öppen omdirigering inte kan leda skannern till en annan server.
	for ( $hops = 0; $hops <= 3; $hops++ ) {
		$response = wp_remote_get( $url, array(
			'timeout'     => 20,
			'redirection' => 0,
			'sslverify'   => apply_filters( 'https_local_ssl_verify', false ),
			'headers'     => array(
				'Cache-Control' => 'no-cache',
				'User-Agent'    => 'Mozilla/5.0 (compatible; RelativtCookieConsentScanner/' . RCC_VERSION . '; +' . home_url( '/' ) . ')',
			),
			'cookies'     => array(),
		) );

		if ( is_wp_error( $response ) ) {
			return array( 'status' => 0, 'error' => $response->get_error_message(), 'findings' => array() );
		}

		$status   = (int) wp_remote_retrieve_response_code( $response );
		$location = wp_remote_retrieve_header( $response, 'location' );
		if ( $status < 300 || $status >= 400 || ! $location ) {
			break;
		}
		$location = is_array( $location ) ? end( $location ) : $location;
		$next     = WP_Http::make_absolute_url( $location, $url );
		if ( ! rcc_scanner_url_allowed( $next ) ) {
			return array( 'status' => $status, 'error' => 'Sidan omdirigerar utanför sajten (' . $next . ') och skannades inte.', 'findings' => array() );
		}
		$url = $next;
	}

	if ( $status >= 300 && $status < 400 && $location ) {
		return array( 'status' => $status, 'error' => 'För många omdirigeringar.', 'findings' => array() );
	}

	$body = (string) wp_remote_retrieve_body( $response );

	if ( $status >= 400 ) {
		$hint = 401 === $status ? ' Är sajten lösenordsskyddad?' : '';
		return array( 'status' => $status, 'error' => 'Servern svarade ' . $status . '.' . $hint, 'findings' => array() );
	}

	return array( 'status' => $status, 'error' => '', 'findings' => rcc_scan_html( $body ) );
}

/**
 * Tomt resultat för en ny skanning.
 */
function rcc_scan_reset() {
	$results = array(
		'started_at'  => time(),
		'finished_at' => 0,
		'pages'       => array(),
		'hosts'       => array(),
	);
	update_option( RCC_SCAN_OPTION, $results, false );
	return $results;
}

function rcc_scan_results() {
	$results = get_option( RCC_SCAN_OPTION, array() );
	return is_array( $results ) ? $results : array();
}

/**
 * Lägger in en sidas fynd i resultatet, grupperat per värd.
 */
function rcc_scan_merge( $url, $page_result ) {
	$results = rcc_scan_results();
	if ( empty( $results['started_at'] ) ) {
		$results = rcc_scan_reset();
	}

	$results['pages'][ $url ] = array(
		'status' => $page_result['status'],
		'error'  => $page_result['error'],
		'count'  => count( $page_result['findings'] ),
	);

	foreach ( $page_result['findings'] as $f ) {
		// Gruppera per värd, men håll isär kända tjänster som bara skiljer
		// sig i sökvägen (Google Maps och reCAPTCHA på www.google.com).
		$host  = $f['host'];
		$known = rcc_known_domain_for( $host, $f['path'] );
		if ( $known && false !== strpos( $known['domain'], '/' ) ) {
			list( , $known_path ) = rcc_split_domain_entry( $known['domain'] );
			$host .= $known_path;
		}
		if ( ! isset( $results['hosts'][ $host ] ) ) {
			$results['hosts'][ $host ] = array(
				'types'      => array(),
				'pages'      => array(),
				'blocked'    => array(),
				'unblocked'  => 0,
				'samples'    => array(),
				'path'       => $f['path'],
			);
		}
		$h                   = &$results['hosts'][ $host ];
		$h['types'][ $f['type'] ] = true;
		$h['pages'][ $url ]  = true;
		if ( $f['category'] ) {
			$h['blocked'][ $f['category'] ] = true;
		} else {
			$h['unblocked']++;
			if ( count( $h['samples'] ) < 3 && ! in_array( $f['sample'], $h['samples'], true ) ) {
				$h['samples'][] = $f['sample'];
			}
		}
		unset( $h );
	}

	update_option( RCC_SCAN_OPTION, $results, false );
	return $results;
}

function rcc_scan_finish() {
	$results = rcc_scan_results();
	if ( $results ) {
		$results['finished_at'] = time();
		update_option( RCC_SCAN_OPTION, $results, false );
	}
	return $results;
}

/* -------------------------------------------------------------------------
 * AJAX (admin-ajax.php) – en sida per anrop så att inget anrop tar för
 * lång tid och sidan kan visa förloppet.
 * ---------------------------------------------------------------------- */

function rcc_scan_ajax_guard() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Behörighet saknas.' ), 403 );
	}
	check_ajax_referer( 'rcc_scan', 'nonce' );
}

function rcc_ajax_scan_start() {
	rcc_scan_ajax_guard();

	$urls  = rcc_scanner_default_urls();
	$extra = isset( $_POST['extra'] ) ? sanitize_textarea_field( wp_unslash( $_POST['extra'] ) ) : '';
	$bad   = array();
	foreach ( preg_split( '/\r\n|\r|\n/', $extra ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		if ( 0 === strpos( $line, '/' ) ) {
			$line = home_url( $line );
		}
		$line = esc_url_raw( $line );
		if ( $line && rcc_scanner_url_allowed( $line ) ) {
			$urls[] = $line;
		} else {
			$bad[] = $line;
		}
	}
	$urls = array_slice( array_values( array_unique( $urls ) ), 0, RCC_SCAN_MAX_PAGES + 10 );

	rcc_scan_reset();
	wp_send_json_success( array( 'urls' => $urls, 'skipped' => $bad ) );
}
add_action( 'wp_ajax_rcc_scan_start', 'rcc_ajax_scan_start' );

function rcc_ajax_scan_url() {
	rcc_scan_ajax_guard();

	$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
	if ( ! $url || ! rcc_scanner_url_allowed( $url ) ) {
		wp_send_json_error( array( 'message' => 'Adressen hör inte till sajten.' ), 400 );
	}

	$result = rcc_scan_url( $url );
	rcc_scan_merge( $url, $result );

	wp_send_json_success( array(
		'url'      => $url,
		'status'   => $result['status'],
		'error'    => $result['error'],
		'findings' => count( $result['findings'] ),
	) );
}
add_action( 'wp_ajax_rcc_scan_url', 'rcc_ajax_scan_url' );

function rcc_ajax_scan_finish() {
	rcc_scan_ajax_guard();
	rcc_scan_finish();
	wp_send_json_success();
}
add_action( 'wp_ajax_rcc_scan_finish', 'rcc_ajax_scan_finish' );
