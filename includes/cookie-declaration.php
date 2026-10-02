<?php
/**
 * Cookiedeklaration: vilka cookies sajten sätter, per kategori.
 *
 * Listan byggs av tre källor och är därför alltid i synk med
 * inställningarna:
 *   1. Pluginets egen samtyckescookie (Nödvändiga).
 *   2. Kända cookies för varje verktyg som har ett ID ifyllt, och för
 *      YouTube/Vimeo när video-gatingen är på.
 *   3. Egna cookies som admin skrivit in (verktyg via "Egen kod",
 *      domänblockering, temat m.m.).
 *
 * Visas med kortkoden [relativt_cookie_declaration] och ingår i
 * /wp-json/rcc/v1/config för headless-frontends.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kända cookies per verktyg (nycklar som i rcc_vendors()). Namn med
 * {id} får verktygets ID insatt. Listan tar upp de cookies verktygen
 * normalt sätter; leverantörerna ändrar dem ibland, så stäm av mot
 * webbläsarens utvecklarverktyg innan lansering.
 */
function rcc_vendor_cookies() {
	$cookies = array(
		'ga4'        => array(
			array( '_ga', 'Google', 'Skiljer besökare åt i statistiken.', '2 år' ),
			array( '_ga_{id}', 'Google', 'Håller reda på besöket (sessionen).', '2 år' ),
		),
		'google_ads' => array(
			array( '_gcl_au', 'Google', 'Kopplar annonsklick till konverteringar.', '90 dagar' ),
			array( '_gcl_aw', 'Google', 'Sparar klick-ID när besökaren kommer från en Google Ads-annons.', '90 dagar' ),
			array( 'IDE', 'Google (doubleclick.net)', 'Visar och mäter annonser, även på andra webbplatser.', '13 månader' ),
		),
		'gtm'        => array(),
		'meta_pixel' => array(
			array( '_fbp', 'Meta', 'Känner igen webbläsaren för annonsering och mätning.', '90 dagar' ),
			array( '_fbc', 'Meta', 'Sparar klick-ID när besökaren kommer från en Meta-annons.', '90 dagar' ),
			array( 'fr', 'Meta (facebook.com)', 'Visar och mäter annonser.', '90 dagar' ),
		),
		'hotjar'     => array(
			array( '_hjSessionUser_{id}', 'Hotjar', 'Skiljer besökare åt.', '1 år' ),
			array( '_hjSession_{id}', 'Hotjar', 'Håller ihop data från samma besök.', '30 minuter' ),
		),
		'clarity'    => array(
			array( '_clck', 'Microsoft', 'Skiljer besökare åt.', '1 år' ),
			array( '_clsk', 'Microsoft', 'Kopplar ihop sidvisningar till ett besök.', '1 dag' ),
			array( 'CLID', 'Microsoft (clarity.ms)', 'Känner igen besökaren mellan webbplatser som använder Clarity.', '1 år' ),
			array( 'MUID', 'Microsoft (clarity.ms)', 'Känner igen webbläsaren hos Microsoft.', '13 månader' ),
		),
		'bing_uet'   => array(
			array( '_uetsid', 'Microsoft', 'Kopplar ihop besök för annonsmätning.', '1 dag' ),
			array( '_uetvid', 'Microsoft', 'Skiljer besökare åt för annonsmätning.', '13 månader' ),
			array( 'MUID', 'Microsoft (bing.com)', 'Känner igen webbläsaren hos Microsoft.', '13 månader' ),
		),
		'linkedin'   => array(
			array( 'li_fat_id', 'LinkedIn', 'Kopplar annonsklick till konverteringar.', '30 dagar' ),
			array( 'bcookie', 'LinkedIn (linkedin.com)', 'Känner igen webbläsaren hos LinkedIn.', '1 år' ),
			array( 'lidc', 'LinkedIn (linkedin.com)', 'Fördelar trafiken mellan LinkedIns servrar.', '1 dag' ),
			array( 'UserMatchHistory', 'LinkedIn (linkedin.com)', 'Matchar besökaren mot LinkedIns annonsering.', '30 dagar' ),
			array( 'AnalyticsSyncHistory', 'LinkedIn (linkedin.com)', 'Sparar när besökaren senast synkades mot LinkedIn.', '30 dagar' ),
			array( 'li_sugr', 'LinkedIn (linkedin.com)', 'Uppskattar besökarens identitet för annonsering.', '90 dagar' ),
		),
		'reddit'     => array(
			array( '_rdt_uuid', 'Reddit', 'Känner igen besökaren för annonsmätning.', '90 dagar' ),
		),
		'tiktok'     => array(
			array( '_ttp', 'TikTok', 'Känner igen besökaren för annonsmätning.', '13 månader' ),
			array( '_tt_enable_cookie', 'TikTok', 'Kontrollerar att webbläsaren tar emot cookies.', '13 månader' ),
		),
		'pinterest'  => array(
			array( '_pin_unauth', 'Pinterest', 'Känner igen besökaren för annonsmätning.', '1 år' ),
			array( '_epik', 'Pinterest', 'Sparar klick-ID när besökaren kommer från en Pinterest-annons.', '1 år' ),
		),
		'snapchat'   => array(
			array( '_scid', 'Snap', 'Känner igen besökaren för annonsmätning.', '13 månader' ),
			array( '_sctr', 'Snap', 'Sparar tidpunkten för senaste besöket för annonsmätning.', '1 år' ),
		),
		'youtube'    => array(
			array( 'YSC', 'Google (youtube.com)', 'Registrerar visningar av inbäddade videor.', 'Sessionen' ),
			array( 'VISITOR_INFO1_LIVE', 'Google (youtube.com)', 'Anpassar videospelaren efter uppkopplingen.', '6 månader' ),
			array( 'VISITOR_PRIVACY_METADATA', 'Google (youtube.com)', 'Sparar besökarens val hos YouTube.', '6 månader' ),
		),
		'vimeo'      => array(
			array( 'vuid', 'Vimeo (vimeo.com)', 'Registrerar visningar av inbäddade videor.', '2 år' ),
		),
	);

	/**
	 * Ändra eller komplettera de kända cookiesarna. Varje rad är
	 * array( namn, leverantör, syfte, lagringstid ).
	 */
	return apply_filters( 'rcc_vendor_cookies', $cookies );
}

/**
 * Namn på verktygen, för admin-förhandsvisningen.
 */
function rcc_vendor_labels() {
	return array(
		'ga4'        => 'Google Analytics 4',
		'google_ads' => 'Google Ads',
		'gtm'        => 'Google Tag Manager',
		'meta_pixel' => 'Meta Pixel',
		'hotjar'     => 'Hotjar',
		'clarity'    => 'Microsoft Clarity',
		'bing_uet'   => 'Microsoft Advertising (UET)',
		'linkedin'   => 'LinkedIn Insight Tag',
		'reddit'     => 'Reddit-pixeln',
		'tiktok'     => 'TikTok-pixeln',
		'pinterest'  => 'Pinterest-taggen',
		'snapchat'   => 'Snap-pixeln',
		'youtube'    => 'YouTube',
		'vimeo'      => 'Vimeo',
	);
}

/**
 * Kategorinyckel från fritext: svenska eller engelska namn, med eller
 * utan å/ä/ö. Null om texten inte känns igen.
 */
function rcc_parse_category( $text ) {
	$text = strtolower( remove_accents( trim( (string) $text ) ) );
	$map  = array(
		'necessary'      => 'necessary',
		'nodvandiga'     => 'necessary',
		'nodvandig'      => 'necessary',
		'statistics'     => 'statistics',
		'statistik'      => 'statistics',
		'marketing'      => 'marketing',
		'marknadsforing' => 'marketing',
	);
	return isset( $map[ $text ] ) ? $map[ $text ] : null;
}

/**
 * Egna cookies ur textarean. Format per rad:
 *   namn | leverantör | kategori | syfte | lagringstid
 * Rader med okänd kategori hoppas över och rapporteras i admin.
 */
function rcc_parse_custom_cookies( $text, &$errors = null ) {
	$rows   = array();
	$errors = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $i => $line ) {
		$line = trim( $line );
		if ( '' === $line || '#' === $line[0] ) {
			continue;
		}
		$parts = array_map( 'trim', explode( '|', $line ) );
		$parts = array_pad( $parts, 5, '' );
		$cat   = rcc_parse_category( $parts[2] );
		if ( '' === $parts[0] || ! $cat ) {
			$errors[] = sprintf( 'Rad %d: %s', $i + 1, $line );
			continue;
		}
		$rows[] = array(
			'category' => $cat,
			'name'     => $parts[0],
			'provider' => $parts[1],
			'purpose'  => $parts[3],
			'duration' => $parts[4],
			'source'   => 'custom',
		);
	}
	return $rows;
}

/**
 * Lagringstid för pluginets egen cookie i läsbar form.
 */
function rcc_format_days( $days ) {
	$days = (int) $days;
	if ( $days > 0 && 0 === $days % 365 ) {
		$years = $days / 365;
		return 1 === $years ? '1 år' : $years . ' år';
	}
	if ( $days > 0 && 0 === $days % 30 ) {
		$months = $days / 30;
		return 1 === $months ? '1 månad' : $months . ' månader';
	}
	return 1 === $days ? '1 dag' : $days . ' dagar';
}

/**
 * Hela deklarationen: array( 'necessary' => rader, 'statistics' => …,
 * 'marketing' => … ). Varje rad: name, provider, purpose, duration,
 * category, source (plugin/vendor-nyckel/custom).
 */
function rcc_cookie_declaration( $s = null ) {
	$s   = $s ? $s : rcc_get_settings();
	$out = array(
		'necessary'  => array(),
		'statistics' => array(),
		'marketing'  => array(),
	);

	$out['necessary'][] = array(
		'category' => 'necessary',
		'name'     => rcc_cookie_name(),
		'provider' => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
		'purpose'  => 'Sparar dina cookie-val och ditt samtyckes-ID.',
		'duration' => rcc_format_days( $s['cookie_expiry_days'] ),
		'source'   => 'plugin',
	);

	$known   = rcc_vendor_cookies();
	$sources = array();
	foreach ( rcc_vendors() as $vendor => $def ) {
		if ( ! empty( $s[ $def['setting'] ] ) ) {
			$sources[ $vendor ] = array(
				'category' => rcc_vendor_category( $vendor, $s ),
				'id'       => (string) $s[ $def['setting'] ],
			);
		}
	}
	if ( ! empty( $s['gate_video_embeds'] ) ) {
		$sources['youtube'] = array( 'category' => $s['youtube_category'], 'id' => '' );
		$sources['vimeo']   = array( 'category' => $s['vimeo_category'], 'id' => '' );
	}

	foreach ( $sources as $vendor => $info ) {
		if ( empty( $known[ $vendor ] ) ) {
			continue;
		}
		// GTM laddas under "statistics marketing"; dess cookies kommer från
		// taggarna i containern och listas inte här.
		$category = in_array( $info['category'], array( 'statistics', 'marketing' ), true ) ? $info['category'] : 'marketing';
		$id       = $info['id'];
		if ( 'ga4' === $vendor ) {
			$id = preg_replace( '/^G-/i', '', $id );
		}
		foreach ( $known[ $vendor ] as $row ) {
			$out[ $category ][] = array(
				'category' => $category,
				'name'     => str_replace( '{id}', $id, $row[0] ),
				'provider' => $row[1],
				'purpose'  => $row[2],
				'duration' => $row[3],
				'source'   => $vendor,
			);
		}
	}

	foreach ( rcc_parse_custom_cookies( $s['custom_cookies'] ) as $row ) {
		$out[ $row['category'] ][] = $row;
	}

	// Samma cookie från samma leverantör i samma kategori listas en gång
	// (t.ex. om Hotjar både har ett fält och en egen rad).
	foreach ( $out as $category => $rows ) {
		$seen = array();
		foreach ( $rows as $i => $row ) {
			$key = strtolower( $row['name'] . '|' . $row['provider'] );
			if ( isset( $seen[ $key ] ) ) {
				unset( $out[ $category ][ $i ] );
				continue;
			}
			$seen[ $key ] = true;
		}
		$out[ $category ] = array_values( $out[ $category ] );
	}

	return apply_filters( 'rcc_cookie_declaration', $out, $s );
}

/**
 * Tabellen som HTML. Används av kortkoden och av förhandsvisningen i
 * admin, så att de alltid ser likadana ut.
 */
function rcc_render_cookie_declaration( $s, $args = array() ) {
	$args = wp_parse_args( $args, array(
		'categories'   => array( 'necessary', 'statistics', 'marketing' ),
		'descriptions' => true,
		'heading_tag'  => 'h3',
	) );

	$declaration = rcc_cookie_declaration( $s );
	$labels      = array(
		'necessary'  => $s['necessary_label'],
		'statistics' => $s['statistics_label'],
		'marketing'  => $s['marketing_label'],
	);
	$descs       = array(
		'necessary'  => $s['necessary_desc'],
		'statistics' => $s['statistics_desc'],
		'marketing'  => $s['marketing_desc'],
	);
	$tag         = in_array( $args['heading_tag'], array( 'h2', 'h3', 'h4', 'h5', 'strong' ), true ) ? $args['heading_tag'] : 'h3';

	ob_start();
	echo '<div class="rcc-declaration">';
	foreach ( $args['categories'] as $category ) {
		if ( ! isset( $declaration[ $category ] ) ) {
			continue;
		}
		$rows = $declaration[ $category ];
		echo '<div class="rcc-declaration__category rcc-declaration__category--' . esc_attr( $category ) . '">';
		printf( '<%1$s class="rcc-declaration__heading">%2$s</%1$s>', $tag, esc_html( $labels[ $category ] ) ); // phpcs:ignore -- $tag kommer från vitlistan ovan.
		if ( $args['descriptions'] && '' !== trim( (string) $descs[ $category ] ) ) {
			echo '<p class="rcc-declaration__desc">' . esc_html( $descs[ $category ] ) . '</p>';
		}
		if ( ! $rows ) {
			echo '<p class="rcc-declaration__empty">Inga cookies i den här kategorin.</p>';
		} else {
			echo '<div class="rcc-declaration__scroll"><table class="rcc-declaration__table">';
			echo '<thead><tr><th scope="col">Cookie</th><th scope="col">Leverantör</th><th scope="col">Syfte</th><th scope="col">Lagringstid</th></tr></thead><tbody>';
			foreach ( $rows as $row ) {
				printf(
					'<tr><td><code>%s</code></td><td>%s</td><td>%s</td><td>%s</td></tr>',
					esc_html( $row['name'] ),
					esc_html( $row['provider'] ),
					esc_html( $row['purpose'] ),
					esc_html( $row['duration'] )
				);
			}
			echo '</tbody></table></div>';
		}
		echo '</div>';
	}
	echo '</div>';
	return ob_get_clean();
}

/**
 * [relativt_cookie_declaration category="" descriptions="ja" heading="h3"]
 *
 * category: necessary/statistics/marketing (eller svenska namnen),
 *           flera kommaseparerade. Tomt = alla.
 * descriptions: "nej" döljer kategoribeskrivningarna.
 * heading: h2–h5 eller strong.
 */
function rcc_declaration_shortcode( $atts ) {
	$atts = shortcode_atts( array(
		'category'     => '',
		'descriptions' => 'ja',
		'heading'      => 'h3',
	), $atts, 'relativt_cookie_declaration' );

	$categories = array();
	foreach ( array_filter( array_map( 'trim', explode( ',', (string) $atts['category'] ) ) ) as $text ) {
		$cat = rcc_parse_category( $text );
		if ( $cat ) {
			$categories[] = $cat;
		}
	}
	if ( ! $categories ) {
		$categories = array( 'necessary', 'statistics', 'marketing' );
	}

	return rcc_render_cookie_declaration( rcc_get_settings(), array(
		'categories'   => $categories,
		'descriptions' => ! in_array( strtolower( (string) $atts['descriptions'] ), array( 'nej', 'no', '0', 'false' ), true ),
		'heading_tag'  => strtolower( (string) $atts['heading'] ),
	) );
}
add_shortcode( 'relativt_cookie_declaration', 'rcc_declaration_shortcode' );
