<?php
/**
 * Kända tredjepartsdomäner. Används av skannern för att föreslå en
 * kategori och av domänblockeringen för att känna igen inline-skript som
 * hör till ett blockerat verktyg men inte nämner domänen (t.ex.
 * fbq('init', …) efter Meta-pixelns loader).
 *
 * Kategorier:
 *   statistics / marketing – samtyckespliktigt, bör blockeras.
 *   necessary – behövs för att sajten ska fungera (t.ex. reCAPTCHA,
 *               betalning). Blockera inte.
 *   none      – sätter normalt inga cookies (CDN:er, cookiefri statistik).
 *   conflict  – en annan cookie-lösning som bör tas bort när detta
 *               plugin används.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function rcc_known_domains() {
	$domains = array(
		// Google.
		'google-analytics.com'     => array( 'name' => 'Google Analytics', 'category' => 'statistics' ),
		'googletagmanager.com'     => array( 'name' => 'Google Tag Manager / gtag.js', 'category' => 'statistics', 'signatures' => array( "gtag('config'", 'gtag("config"', "gtag('event'", 'gtag("event"' ) ),
		'googleadservices.com'     => array( 'name' => 'Google Ads', 'category' => 'marketing' ),
		'doubleclick.net'          => array( 'name' => 'Google Ads (DoubleClick)', 'category' => 'marketing' ),
		'googlesyndication.com'    => array( 'name' => 'Google AdSense', 'category' => 'marketing' ),
		'maps.googleapis.com'      => array( 'name' => 'Google Maps', 'category' => 'marketing' ),
		'google.com'               => array( 'name' => 'Google', 'category' => '' ),
		'google.com/maps'          => array( 'name' => 'Google Maps-inbäddning', 'category' => 'marketing' ),
		'gstatic.com'              => array( 'name' => 'Google statiska filer (t.ex. reCAPTCHA)', 'category' => 'necessary' ),
		'youtube.com'              => array( 'name' => 'YouTube', 'category' => 'marketing' ),
		'youtube-nocookie.com'     => array( 'name' => 'YouTube (utökad integritet)', 'category' => 'marketing' ),

		// Meta.
		'connect.facebook.net'     => array( 'name' => 'Meta Pixel', 'category' => 'marketing', 'signatures' => array( 'fbq(' ) ),
		'facebook.com'             => array( 'name' => 'Facebook', 'category' => 'marketing' ),
		'instagram.com'            => array( 'name' => 'Instagram', 'category' => 'marketing' ),

		// Microsoft.
		'clarity.ms'               => array( 'name' => 'Microsoft Clarity', 'category' => 'statistics' ),
		'bat.bing.com'             => array( 'name' => 'Microsoft Advertising (UET)', 'category' => 'marketing', 'signatures' => array( 'uetq' ) ),

		// Övriga annonsnätverk och sociala medier.
		'snap.licdn.com'           => array( 'name' => 'LinkedIn Insight Tag', 'category' => 'marketing', 'signatures' => array( '_linkedin_partner_id', 'lintrk(' ) ),
		'px.ads.linkedin.com'      => array( 'name' => 'LinkedIn Ads', 'category' => 'marketing' ),
		'linkedin.com'             => array( 'name' => 'LinkedIn', 'category' => 'marketing' ),
		'analytics.tiktok.com'     => array( 'name' => 'TikTok Pixel', 'category' => 'marketing', 'signatures' => array( 'ttq.load', 'ttq.page' ) ),
		'tiktok.com'               => array( 'name' => 'TikTok', 'category' => 'marketing' ),
		's.pinimg.com'             => array( 'name' => 'Pinterest Tag', 'category' => 'marketing', 'signatures' => array( 'pintrk(' ) ),
		'ct.pinterest.com'         => array( 'name' => 'Pinterest Tag', 'category' => 'marketing' ),
		'sc-static.net'            => array( 'name' => 'Snap Pixel', 'category' => 'marketing', 'signatures' => array( 'snaptr(' ) ),
		'redditstatic.com'         => array( 'name' => 'Reddit Pixel', 'category' => 'marketing', 'signatures' => array( "rdt('", 'rdt("' ) ),
		'platform.twitter.com'     => array( 'name' => 'X (Twitter)', 'category' => 'marketing' ),
		'static.ads-twitter.com'   => array( 'name' => 'X (Twitter) Ads', 'category' => 'marketing', 'signatures' => array( 'twq(' ) ),
		'open.spotify.com'         => array( 'name' => 'Spotify', 'category' => 'marketing' ),
		'vimeo.com'                => array( 'name' => 'Vimeo', 'category' => 'marketing' ),

		// Beteendeanalys och B2B-spårning.
		'hotjar.com'               => array( 'name' => 'Hotjar', 'category' => 'statistics', 'signatures' => array( '_hjSettings' ) ),
		'js.hs-scripts.com'        => array( 'name' => 'HubSpot', 'category' => 'marketing', 'signatures' => array( '_hsq' ) ),
		'js.hs-analytics.net'      => array( 'name' => 'HubSpot Analytics', 'category' => 'marketing' ),
		'js.hsforms.net'           => array( 'name' => 'HubSpot-formulär', 'category' => '' ),
		'sc.lfeeder.com'           => array( 'name' => 'Leadfeeder (Dealfront)', 'category' => 'marketing' ),
		'serve.albacross.com'      => array( 'name' => 'Albacross', 'category' => 'marketing' ),
		'bidtheatre.com'           => array( 'name' => 'Bidtheatre', 'category' => 'marketing' ),
		'upsales.com'              => array( 'name' => 'Upsales', 'category' => 'marketing' ),

		// Behövs för att sajten ska fungera.
		'challenges.cloudflare.com' => array( 'name' => 'Cloudflare Turnstile', 'category' => 'necessary' ),
		'js.stripe.com'            => array( 'name' => 'Stripe', 'category' => 'necessary' ),
		'google.com/recaptcha'     => array( 'name' => 'Google reCAPTCHA', 'category' => 'necessary' ),

		// Sätter normalt inga cookies.
		'static.cloudflareinsights.com' => array( 'name' => 'Cloudflare Web Analytics (cookiefri)', 'category' => 'none' ),
		'plausible.io'             => array( 'name' => 'Plausible (cookiefri)', 'category' => 'none' ),
		'cdnjs.cloudflare.com'     => array( 'name' => 'cdnjs', 'category' => 'none' ),
		'cdn.jsdelivr.net'         => array( 'name' => 'jsDelivr', 'category' => 'none' ),
		'unpkg.com'                => array( 'name' => 'unpkg', 'category' => 'none' ),
		'code.jquery.com'          => array( 'name' => 'jQuery CDN', 'category' => 'none' ),
		'ajax.googleapis.com'      => array( 'name' => 'Google Hosted Libraries', 'category' => 'none' ),
		's.w.org'                  => array( 'name' => 'WordPress.org (emoji)', 'category' => 'none' ),

		// Andra cookie-lösningar.
		'cdn-cookieyes.com'        => array( 'name' => 'CookieYes', 'category' => 'conflict' ),
		'consent.cookiebot.com'    => array( 'name' => 'Cookiebot', 'category' => 'conflict' ),
		'consentcdn.cookiebot.com' => array( 'name' => 'Cookiebot', 'category' => 'conflict' ),
		'cdn.cookielaw.org'        => array( 'name' => 'OneTrust', 'category' => 'conflict' ),
		'js.hs-banner.com'         => array( 'name' => 'HubSpot cookie-banner', 'category' => 'conflict' ),
	);

	/**
	 * Lägg till eller ändra kända domäner, t.ex.
	 * $domains['example-tracker.com'] = array( 'name' => 'Example', 'category' => 'marketing' );
	 */
	return apply_filters( 'rcc_known_domains', $domains );
}

/**
 * Om en värd är domänen eller en subdomän till den.
 */
function rcc_host_matches( $host, $domain ) {
	$host   = strtolower( (string) $host );
	$domain = strtolower( (string) $domain );
	if ( '' === $host || '' === $domain ) {
		return false;
	}
	return $host === $domain || substr( $host, -strlen( '.' . $domain ) ) === '.' . $domain;
}

/**
 * Det mest specifika kända uppslaget för en värd (längsta matchande
 * domän), eller null. Poster med sökväg (google.com/recaptcha) matchar
 * bara när adressens sökväg börjar så.
 */
function rcc_known_domain_for( $host, $path = '' ) {
	$best     = null;
	$best_len = 0;
	foreach ( rcc_known_domains() as $domain => $info ) {
		$domain_host = $domain;
		$domain_path = '';
		if ( false !== strpos( $domain, '/' ) ) {
			list( $domain_host, $domain_path ) = explode( '/', $domain, 2 );
			$domain_path = '/' . $domain_path;
		}
		if ( ! rcc_host_matches( $host, $domain_host ) ) {
			continue;
		}
		if ( $domain_path && 0 !== strpos( (string) $path, $domain_path ) ) {
			continue;
		}
		$len = strlen( $domain );
		if ( $len > $best_len ) {
			$best     = array_merge( array( 'domain' => $domain, 'signatures' => array() ), $info );
			$best_len = $len;
		}
	}
	return $best;
}
