<?php
/**
 * Blockering i den färdiga HTML:en, via en output-buffer:
 *
 * 1. Video-gating – iframes från YouTube/Vimeo får data-cookiesrc i
 *    stället för src (fanns sedan 1.0.0).
 * 2. Domänblockering (1.3.0) – <script src> och iframes från domäner i
 *    listorna "Blockera domäner" skrivs om till blockerat format, liksom
 *    inline-skript som nämner domänen eller bär ett känt verktygs
 *    signatur (t.ex. fbq( för Meta).
 *
 * Det fångar skript och inbäddningar som andra plugin, temat eller
 * sidbyggaren skriver ut själva, utanför pluginets egna fält. Skript som
 * laddas av annan JS efter att sidan visats (t.ex. via GTM) syns inte i
 * HTML:en och fångas därför inte här.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Domänlistor
 * ---------------------------------------------------------------------- */

/**
 * Normaliserar en rad till en domän, eventuellt med en sökväg
 * (google.com/maps): utan protokoll, port, query och inledande "*.".
 * Sökvägen gör det möjligt att blockera Google Maps-inbäddningar utan att
 * blockera reCAPTCHA på samma domän. Returnerar tom sträng för ogiltiga
 * rader.
 */
function rcc_normalize_domain( $line ) {
	$line = strtolower( trim( (string) $line ) );
	$line = preg_replace( '#^[a-z][a-z0-9+.-]*://#', '', $line );
	$line = ltrim( $line, '/' );
	$line = preg_replace( '#[?\#\s].*$#', '', $line );
	$line = preg_replace( '#^\*?\.#', '', $line );

	$path = '';
	$slash = strpos( $line, '/' );
	if ( false !== $slash ) {
		$path = rtrim( substr( $line, $slash ), '/' );
		$line = substr( $line, 0, $slash );
	}
	$line = preg_replace( '#:\d+$#', '', $line );

	if ( ! preg_match( '/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $line ) ) {
		return '';
	}
	if ( '' !== $path && ! preg_match( '#^(/[a-z0-9._~%!$&\'()*+,;=:@-]+)+$#', $path ) ) {
		$path = '';
	}
	return $line . $path;
}

/**
 * Delar en post i blocklistan i värd och sökväg.
 */
function rcc_split_domain_entry( $entry ) {
	$slash = strpos( $entry, '/' );
	if ( false === $slash ) {
		return array( $entry, '' );
	}
	return array( substr( $entry, 0, $slash ), substr( $entry, $slash ) );
}

/**
 * Domäner ur en textarea (en per rad, # inleder en kommentar).
 */
function rcc_parse_domain_list( $text ) {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n|,/', (string) $text ) as $line ) {
		$line   = preg_replace( '/#.*$/', '', $line );
		$domain = rcc_normalize_domain( $line );
		if ( $domain ) {
			$out[ $domain ] = true;
		}
	}
	return array_keys( $out );
}

/**
 * Sanering av textareorna: en giltig domän per rad, inga dubbletter.
 */
function rcc_sanitize_domain_list( $value ) {
	return implode( "\n", rcc_parse_domain_list( is_string( $value ) ? $value : '' ) );
}

/**
 * Domän => kategori för alla blockerade domäner. Står en domän i båda
 * listorna räcker samtycke till endera.
 */
function rcc_blocked_domains( $s = null ) {
	$s       = $s ? $s : rcc_get_settings();
	$domains = array();
	foreach ( array( 'statistics', 'marketing' ) as $category ) {
		foreach ( rcc_parse_domain_list( $s[ 'blocked_domains_' . $category ] ) as $domain ) {
			$domains[ $domain ] = isset( $domains[ $domain ] ) ? 'statistics marketing' : $category;
		}
	}
	/**
	 * Lägg till domäner i kod: $domains['example.com'] = 'marketing';
	 */
	return apply_filters( 'rcc_blocked_domains', $domains, $s );
}

/**
 * Kategorin för en värd (och sökväg) utifrån en lista domän => kategori,
 * eller null. Den mest specifika (längsta) matchande posten vinner. En
 * post med sökväg (google.com/maps) matchar bara adresser vars sökväg
 * börjar så.
 */
function rcc_category_for_host( $host, $domains, $path = '' ) {
	$host     = strtolower( (string) $host );
	$path     = strtolower( (string) $path );
	$found    = null;
	$best_len = 0;
	foreach ( $domains as $entry => $category ) {
		list( $domain, $entry_path ) = rcc_split_domain_entry( $entry );
		if ( ! rcc_host_matches( $host, $domain ) ) {
			continue;
		}
		if ( '' !== $entry_path && $path !== $entry_path && 0 !== strpos( $path, $entry_path . '/' ) ) {
			continue;
		}
		if ( strlen( $entry ) > $best_len ) {
			$found    = $category;
			$best_len = strlen( $entry );
		}
	}
	return $found;
}

/**
 * Sökvägen i en adress, med gemener.
 */
function rcc_url_path( $url ) {
	$url = html_entity_decode( trim( (string) $url ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	if ( 0 === strpos( $url, '//' ) ) {
		$url = 'https:' . $url;
	}
	return strtolower( (string) wp_parse_url( $url, PHP_URL_PATH ) );
}

/* -------------------------------------------------------------------------
 * HTML-hjälpare (används även av skannern)
 * ---------------------------------------------------------------------- */

/**
 * Attributen i en taggs attributsträng, med gemena namn och avkodade
 * värden. Attribut utan värde (async) får tom sträng.
 */
function rcc_parse_attributes( $attr_string ) {
	$attrs = array();
	if ( preg_match_all( '/([^\s=\/>"\']+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>"\']+)))?/', (string) $attr_string, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $match ) {
			$name = strtolower( $match[1] );
			if ( isset( $attrs[ $name ] ) ) {
				continue;
			}
			$value = '';
			foreach ( array( 2, 3, 4 ) as $i ) {
				if ( isset( $match[ $i ] ) && '' !== $match[ $i ] ) {
					$value = $match[ $i ];
					break;
				}
			}
			$attrs[ $name ] = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}
	}
	return $attrs;
}

/**
 * Tar bort de angivna attributen ur en rå attributsträng och behåller
 * resten exakt som de skrevs.
 */
function rcc_strip_attributes( $attr_string, $names ) {
	foreach ( $names as $name ) {
		$attr_string = preg_replace( '/\s' . preg_quote( $name, '/' ) . '(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>"\']+))?(?=[\s\/>]|$)/i', '', $attr_string );
	}
	return $attr_string;
}

/**
 * Värden på type som betyder körbar JavaScript.
 */
function rcc_is_js_type( $type ) {
	$type = strtolower( trim( (string) $type ) );
	return in_array( $type, array( '', 'text/javascript', 'application/javascript', 'application/x-javascript', 'text/ecmascript', 'application/ecmascript', 'module' ), true );
}

/**
 * Värden för en URL i HTML: protokollrelativa adresser får https.
 */
function rcc_url_host( $url ) {
	$url = html_entity_decode( trim( (string) $url ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	if ( 0 === strpos( $url, '//' ) ) {
		$url = 'https:' . $url;
	}
	$host = wp_parse_url( $url, PHP_URL_HOST );
	return $host ? strtolower( $host ) : '';
}

/**
 * Sajtens egna värd. Skript därifrån blockeras aldrig av domänlistan.
 */
function rcc_own_host() {
	return strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
}

/**
 * Om ett inline-skript hör till någon av domänerna: domänen står i koden
 * (som del av en adress), eller koden bär signaturen för ett känt verktyg
 * vars domän är blockerad. Returnerar kategorin eller null.
 */
function rcc_inline_script_category( $code, $domains ) {
	if ( '' === trim( (string) $code ) ) {
		return null;
	}
	$found    = null;
	$best_len = 0;
	foreach ( $domains as $domain => $category ) {
		// Snedstreck i en sökväg kan vara escapade i JSON (google.com\/maps).
		$pattern = str_replace( '/', '\\\\?/', preg_quote( $domain, '#' ) );
		$hit     = (bool) preg_match( '#(?<![a-z0-9-])' . $pattern . '(?![a-z0-9-])#i', $code );
		if ( ! $hit ) {
			list( $sig_host ) = rcc_split_domain_entry( $domain );
			foreach ( rcc_signatures_for_domain( $sig_host ) as $signature ) {
				if ( false !== stripos( $code, $signature ) ) {
					$hit = true;
					break;
				}
			}
		}
		if ( $hit && strlen( $domain ) > $best_len ) {
			$found    = $category;
			$best_len = strlen( $domain );
		}
	}
	return $found;
}

/**
 * Signaturerna för kända verktyg som hör till en blockerad domän: samma
 * domän, en subdomän (connect.facebook.net när facebook.net blockeras)
 * eller en överordnad domän (hotjar.com när static.hotjar.com blockeras).
 */
function rcc_signatures_for_domain( $domain ) {
	static $cache = array();
	if ( isset( $cache[ $domain ] ) ) {
		return $cache[ $domain ];
	}
	$signatures = array();
	foreach ( rcc_known_domains() as $known => $info ) {
		if ( empty( $info['signatures'] ) || false !== strpos( $known, '/' ) ) {
			continue;
		}
		if ( rcc_host_matches( $known, $domain ) || rcc_host_matches( $domain, $known ) ) {
			$signatures = array_merge( $signatures, $info['signatures'] );
		}
	}
	$cache[ $domain ] = array_values( array_unique( $signatures ) );
	return $cache[ $domain ];
}

/* -------------------------------------------------------------------------
 * Output-buffern
 * ---------------------------------------------------------------------- */

/**
 * Om iframes från video-tjänsterna ska blockeras på den här förfrågan.
 */
function rcc_video_gating_active( $s ) {
	return ! empty( $s['gate_video_embeds'] ) && apply_filters( 'rcc_gate_video_on_request', true );
}

/**
 * Startar bufferten på vanliga frontend-sidor (inte admin/AJAX/REST/cron/
 * flöden) när det finns något att blockera.
 */
function rcc_maybe_start_output_buffer() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || is_feed() ) {
		return;
	}
	$s = rcc_get_settings();
	if ( ! rcc_video_gating_active( $s ) && ! rcc_blocked_domains( $s ) ) {
		return;
	}
	if ( ! apply_filters( 'rcc_filter_output_on_request', true ) ) {
		return;
	}
	ob_start( 'rcc_filter_output' );
}
add_action( 'template_redirect', 'rcc_maybe_start_output_buffer', 0 );

/**
 * Bearbetar hela sidan. Rör bara svar som ser ut som fullständiga
 * HTML-dokument.
 */
function rcc_filter_output( $html ) {
	if ( ! is_string( $html ) || '' === $html || false === stripos( $html, '</html>' ) ) {
		return $html;
	}

	$s       = rcc_get_settings();
	$domains = rcc_blocked_domains( $s );

	$iframe_hosts = rcc_video_gating_active( $s ) ? rcc_gated_iframe_hosts( $s ) : array();
	$iframe_hosts = array_merge( $iframe_hosts, $domains );

	if ( $iframe_hosts && false !== stripos( $html, '<iframe' ) ) {
		$html = rcc_filter_iframes_in_output( $html, $iframe_hosts );
	}

	if ( $domains && false !== stripos( $html, '<script' ) ) {
		$html = rcc_filter_scripts_in_output( $html, $domains );
	}

	return $html;
}

/**
 * Flyttar src till data-cookiesrc på iframes från blockerade värdar.
 * Fångar även inbäddningar som sidbyggare (Oxygen, Elementor, Bricks
 * m.fl.) skriver direkt i mallen, utanför the_content.
 */
function rcc_filter_iframes_in_output( $html, $hosts = null ) {
	if ( null === $hosts ) {
		$hosts = rcc_gated_iframe_hosts( rcc_get_settings() );
	}
	if ( empty( $hosts ) || false === stripos( $html, '<iframe' ) ) {
		return $html;
	}

	$result = preg_replace_callback(
		'/<iframe\b([^>]*?)\ssrc=(["\'])((?:https?:)?\/\/[^"\']+)\2([^>]*)>/i',
		function ( $m ) use ( $hosts ) {
			$before = $m[1];
			$src    = $m[3];
			$after  = $m[4];

			// Redan bearbetad (t.ex. av temat) – rör inte.
			if ( false !== stripos( $before . $after, 'data-cookiecategory' ) ) {
				return $m[0];
			}

			$category = rcc_iframe_category_for_src( $src, $hosts );
			if ( ! $category ) {
				return $m[0];
			}

			// Har iframen redan en class läggs vår klass till i den. Två
			// class-attribut skulle göra att webbläsaren bara läser det första.
			$class_attr = ' class="rcc-gated-iframe"';
			$merged     = preg_replace( '/(\sclass\s*=\s*)(["\'])(.*?)\2/i', '$1$2$3 rcc-gated-iframe$2', $before, 1, $count );
			if ( $count ) {
				$before     = $merged;
				$class_attr = '';
			} else {
				$merged = preg_replace( '/(\sclass\s*=\s*)(["\'])(.*?)\2/i', '$1$2$3 rcc-gated-iframe$2', $after, 1, $count );
				if ( $count ) {
					$after      = $merged;
					$class_attr = '';
				}
			}

			return '<iframe' . $before . $class_attr . ' data-cookiecategory="' . esc_attr( $category ) . '" data-cookiesrc="' . esc_attr( $src ) . '"' . $after . '>';
		},
		$html
	);

	return null === $result ? $html : $result;
}

/**
 * Returnerar kategorin för en iframe-src, eller null om värden inte ska
 * blockeras.
 */
function rcc_iframe_category_for_src( $src, $hosts ) {
	$host = rcc_url_host( $src );
	if ( ! $host ) {
		return null;
	}
	return rcc_category_for_host( $host, $hosts, rcc_url_path( $src ) );
}

/**
 * Skriver om <script>-taggar från blockerade domäner:
 *
 *   <script src="https://connect.facebook.net/…" async></script>
 *   → <script type="text/plain" data-cookiecategory="marketing"
 *        data-cookiesrc="https://connect.facebook.net/…" async></script>
 *
 * Inline-skript får type="text/plain" och kategori men behåller koden.
 * Ett ursprungligt type (t.ex. module) sparas i data-rcc-type och
 * återställs av JS-filen. Hoppar över skript som redan är blockerade,
 * pluginets egna skript (data-rcc-core), skript från sajtens egen domän,
 * icke-JS (JSON-LD, mallar) och WordPress dataobjekt (id …-js-extra).
 */
function rcc_filter_scripts_in_output( $html, $domains ) {
	$own_host = rcc_own_host();

	return rcc_map_scripts( $html, function ( $raw_attrs, $code ) use ( $domains, $own_host ) {
		$attrs = rcc_parse_attributes( $raw_attrs );

		if ( isset( $attrs['data-cookiecategory'] ) || isset( $attrs['data-rcc-core'] ) ) {
			return null;
		}
		$type = isset( $attrs['type'] ) ? $attrs['type'] : '';
		if ( ! rcc_is_js_type( $type ) ) {
			return null;
		}

		$src = isset( $attrs['src'] ) ? trim( $attrs['src'] ) : '';
		if ( '' !== $src ) {
			$host = rcc_url_host( $src );
			if ( ! $host || $host === $own_host ) {
				return null;
			}
			$category = rcc_category_for_host( $host, $domains, rcc_url_path( $src ) );
		} else {
			if ( isset( $attrs['id'] ) && '-js-extra' === substr( $attrs['id'], -9 ) ) {
				return null;
			}
			$category = rcc_inline_script_category( $code, $domains );
		}

		if ( ! $category ) {
			return null;
		}

		$new = ' type="text/plain" data-cookiecategory="' . esc_attr( $category ) . '"';
		if ( '' !== trim( $type ) ) {
			$new .= ' data-rcc-type="' . esc_attr( $type ) . '"';
		}
		if ( '' !== $src ) {
			$new .= ' data-cookiesrc="' . esc_attr( $src ) . '"';
		}

		return '<script' . $new . rcc_strip_attributes( $raw_attrs, array( 'type', 'src' ) ) . '>' . $code . '</script>';
	} );
}

/**
 * Går igenom alla <script>-element i HTML:en och låter $callback( $attrs,
 * $code ) returnera en ersättning (eller null för att behålla elementet).
 *
 * Skrivet med strpos i stället för ett reguljärt uttryck: ett
 * <script>-block på en megabyte eller mer (vanligt med sidbyggare och
 * WooCommerce) får PCRE att ge upp, och då skulle hela sidan passera
 * oblockerad.
 */
function rcc_map_scripts( $html, $callback ) {
	$out    = '';
	$pos    = 0;
	$length = strlen( $html );

	while ( $pos < $length ) {
		$start = stripos( $html, '<script', $pos );
		if ( false === $start ) {
			break;
		}
		// "<script" måste följas av blanksteg, ">" eller "/", annars är det
		// t.ex. <scripts> eller text.
		$next = isset( $html[ $start + 7 ] ) ? $html[ $start + 7 ] : '';
		if ( '' === $next || false === strpos( " \t\r\n\f>/", $next ) ) {
			$out .= substr( $html, $pos, $start + 7 - $pos );
			$pos  = $start + 7;
			continue;
		}
		$tag_end = strpos( $html, '>', $start );
		if ( false === $tag_end ) {
			break;
		}
		$close = stripos( $html, '</script', $tag_end + 1 );
		if ( false === $close ) {
			break;
		}
		$close_end = strpos( $html, '>', $close );
		if ( false === $close_end ) {
			break;
		}

		$raw_attrs = substr( $html, $start + 7, $tag_end - $start - 7 );
		$code      = substr( $html, $tag_end + 1, $close - $tag_end - 1 );
		$full      = substr( $html, $start, $close_end + 1 - $start );

		$replacement = call_user_func( $callback, $raw_attrs, $code, $full );

		$out .= substr( $html, $pos, $start - $pos );
		$out .= null === $replacement ? $full : $replacement;
		$pos  = $close_end + 1;
	}

	return $out . substr( $html, $pos );
}

/**
 * Alla <script>-element som array( attributsträng, kod ). Samma
 * genomgång som rcc_map_scripts(), för skannern.
 */
function rcc_find_scripts( $html ) {
	$found = array();
	rcc_map_scripts( $html, function ( $raw_attrs, $code ) use ( &$found ) {
		$found[] = array( $raw_attrs, $code );
		return null;
	} );
	return $found;
}
