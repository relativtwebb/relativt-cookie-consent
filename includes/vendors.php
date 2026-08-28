<?php
/**
 * Tredjepartsskript. Allt utom Google Search Console-verifieringen och
 * Consent Mode-defaulten skrivs ut som <script type="text/plain"
 * data-cookiecategory="..."> och aktiveras av JS-filen först när
 * besökaren samtyckt till rätt kategori.
 *
 * Kategorier: "statistics", "marketing" eller "statistics marketing"
 * (aktiveras av endera).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Öppnar ett blockerat skript-block. Stäng med echo '</script>'.
 */
function rcc_blocked_script_open( $category, $extra_attrs = '' ) {
	printf(
		'<script type="text/plain" data-cookiecategory="%s"%s>',
		esc_attr( $category ),
		$extra_attrs ? ' ' . $extra_attrs : '' // phpcs:ignore -- attributen byggs i koden nedan och escapas där.
	);
}

/**
 * Google Search Console-verifiering: ren ägarskaps-verifiering, sätter
 * inga cookies och läggs därför alltid in oblockerat.
 */
function rcc_output_gsc_verification() {
	$s = rcc_get_settings();
	if ( ! empty( $s['gsc_verification'] ) ) {
		printf(
			'<meta name="google-site-verification" content="%s" />' . "\n",
			esc_attr( $s['gsc_verification'] )
		);
	}
}
add_action( 'wp_head', 'rcc_output_gsc_verification', 1 );

/**
 * Google Consent Mode v2 med "denied" som utgångsläge. Körs alltid
 * (sätter inga cookies, bara ett JS-tillstånd) så att alla Google-taggar
 * som laddas senare respekterar valet från start.
 */
function rcc_output_consent_mode_default() {
	$defaults = apply_filters( 'rcc_consent_mode_defaults', array(
		'ad_storage'              => 'denied',
		'ad_user_data'            => 'denied',
		'ad_personalization'      => 'denied',
		'analytics_storage'       => 'denied',
		'functionality_storage'   => 'granted',
		'personalization_storage' => 'denied',
		'security_storage'        => 'granted',
		'wait_for_update'         => 500,
	) );
	?>
	<script>
	window.dataLayer = window.dataLayer || [];
	function gtag(){ dataLayer.push(arguments); }
	gtag('consent', 'default', <?php echo wp_json_encode( $defaults ); ?>);
	</script>
	<?php
}
add_action( 'wp_head', 'rcc_output_consent_mode_default', 2 );

/**
 * Google Tag Manager i läget "alltid": containern laddas direkt och
 * samtycket styrs via Consent Mode v2 + dataLayer-eventet
 * rcc_consent_update. Taggarna i containern måste då ha egna
 * samtyckeskontroller.
 */
function rcc_output_gtm_always() {
	$s = rcc_get_settings();
	if ( empty( $s['gtm_container_id'] ) || 'always' !== $s['gtm_load_mode'] ) {
		return;
	}
	echo '<script>';
	rcc_gtm_snippet( $s['gtm_container_id'] );
	echo '</script>' . "\n";
}
add_action( 'wp_head', 'rcc_output_gtm_always', 3 );

function rcc_gtm_snippet( $container_id ) {
	?>
	(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?php echo esc_js( $container_id ); ?>');
	<?php
}

/**
 * Google Analytics 4 + Google Ads via gtag.js. GA4 kan valfritt räknas
 * som marknadsföring (om det är kopplat mot Ads-remarketing/Signals).
 */
function rcc_output_google_scripts( $s ) {
	if ( ! empty( $s['ga_measurement_id'] ) ) {
		$category = ! empty( $s['ga_treat_as_marketing'] ) ? 'marketing' : 'statistics';
		$src      = esc_url( 'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $s['ga_measurement_id'] ) );
		rcc_blocked_script_open( $category, 'data-cookiesrc="' . $src . '" async' );
		echo '</script>';
		rcc_blocked_script_open( $category );
		?>
			window.dataLayer = window.dataLayer || [];
			function gtag(){ dataLayer.push(arguments); }
			gtag('js', new Date());
			gtag('config', '<?php echo esc_js( $s['ga_measurement_id'] ); ?>', { 'anonymize_ip': true });
		<?php
		echo '</script>';
	}

	if ( ! empty( $s['google_ads_id'] ) ) {
		$src = esc_url( 'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $s['google_ads_id'] ) );
		rcc_blocked_script_open( 'marketing', 'data-cookiesrc="' . $src . '" async' );
		echo '</script>';
		rcc_blocked_script_open( 'marketing' );
		?>
			window.dataLayer = window.dataLayer || [];
			function gtag(){ dataLayer.push(arguments); }
			gtag('js', new Date());
			gtag('config', '<?php echo esc_js( $s['google_ads_id'] ); ?>');
		<?php
		echo '</script>';
	}
}

/**
 * Google Tag Manager i blockerat läge: laddas när besökaren samtyckt
 * till statistik eller marknadsföring.
 */
function rcc_output_gtm_blocked( $s ) {
	if ( empty( $s['gtm_container_id'] ) || 'blocked' !== $s['gtm_load_mode'] ) {
		return;
	}
	rcc_blocked_script_open( 'statistics marketing' );
	rcc_gtm_snippet( $s['gtm_container_id'] );
	echo '</script>';
}

/**
 * Meta Pixel (marknadsföring).
 */
function rcc_output_meta_pixel( $s ) {
	if ( empty( $s['meta_pixel_id'] ) ) {
		return;
	}
	rcc_blocked_script_open( 'marketing' );
	?>
		!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
		n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
		n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
		t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
		document,'script','https://connect.facebook.net/en_US/fbevents.js');
		fbq('init', '<?php echo esc_js( $s['meta_pixel_id'] ); ?>');
		fbq('track', 'PageView');
	<?php
	echo '</script>';
}

/**
 * Hotjar (statistik).
 */
function rcc_output_hotjar( $s ) {
	if ( empty( $s['hotjar_site_id'] ) ) {
		return;
	}
	rcc_blocked_script_open( 'statistics' );
	?>
		(function(h,o,t,j,a,r){
			h.hj=h.hj||function(){(h.hj.q=h.hj.q||[]).push(arguments)};
			h._hjSettings={hjid:<?php echo (int) $s['hotjar_site_id']; ?>,hjsv:6};
			a=o.getElementsByTagName('head')[0];
			r=o.createElement('script');r.async=1;
			r.src=t+h._hjSettings.hjid+j+h._hjSettings.hjsv;
			a.appendChild(r);
		})(window,document,'https://static.hotjar.com/c/hotjar-','.js?sv=');
	<?php
	echo '</script>';
}

/**
 * Microsoft Clarity (statistik).
 */
function rcc_output_clarity( $s ) {
	if ( empty( $s['clarity_project_id'] ) ) {
		return;
	}
	rcc_blocked_script_open( 'statistics' );
	?>
		(function(c,l,a,r,i,t,y){
			c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
			t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
			y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
		})(window, document, "clarity", "script", "<?php echo esc_js( $s['clarity_project_id'] ); ?>");
	<?php
	echo '</script>';
}

/**
 * Microsoft/Bing Advertising, UET-tagg (marknadsföring).
 */
function rcc_output_bing_uet( $s ) {
	if ( empty( $s['bing_uet_tag_id'] ) ) {
		return;
	}
	rcc_blocked_script_open( 'marketing' );
	?>
		(function(w,d,t,r,u){
			var f,n,i;
			w[u]=w[u]||[],f=function(){var o={ti:"<?php echo esc_js( $s['bing_uet_tag_id'] ); ?>",enableAutoSpaTracking:true};o.q=w[u],w[u]=new UET(o),w[u].push("pageLoad")};
			n=d.createElement(t),n.src=r,n.async=1,n.onload=n.onreadystatechange=function(){var s=this.readyState;s&&s!=="loaded"&&s!=="complete"||(f(),n.onload=n.onreadystatechange=null)},
			i=d.getElementsByTagName(t)[0],i.parentNode.insertBefore(n,i);
		})(window,document,"script","https://bat.bing.com/bat.js","uetq");
	<?php
	echo '</script>';
}

/**
 * LinkedIn Insight Tag (marknadsföring).
 */
function rcc_output_linkedin( $s ) {
	if ( empty( $s['linkedin_partner_id'] ) ) {
		return;
	}
	rcc_blocked_script_open( 'marketing' );
	?>
		_linkedin_partner_id = "<?php echo esc_js( $s['linkedin_partner_id'] ); ?>";
		window._linkedin_data_partner_ids = window._linkedin_data_partner_ids || [];
		window._linkedin_data_partner_ids.push(_linkedin_partner_id);
		(function(l){
			if (!l){window.lintrk = function(a,b){window.lintrk.q.push([a,b])};window.lintrk.q=[]}
			var s = document.getElementsByTagName("script")[0];
			var b = document.createElement("script");
			b.type = "text/javascript";b.async = true;
			b.src = "https://snap.licdn.com/li.lms-analytics/insight.min.js";
			s.parentNode.insertBefore(b, s);
		})(window.lintrk);
	<?php
	echo '</script>';
}

/**
 * Reddit-pixel (marknadsföring).
 */
function rcc_output_reddit( $s ) {
	if ( empty( $s['reddit_pixel_id'] ) ) {
		return;
	}
	rcc_blocked_script_open( 'marketing' );
	?>
		!function(w,d){if(!w.rdt){var p=w.rdt=function(){p.sendEvent?p.sendEvent.apply(p,arguments):p.callQueue.push(arguments)};p.callQueue=[];
		var t=d.createElement("script");t.src="https://www.redditstatic.com/ads/pixel.js";t.async=!0;
		var s=d.getElementsByTagName("script")[0];s.parentNode.insertBefore(t,s)}}(window,document);
		rdt('init','<?php echo esc_js( $s['reddit_pixel_id'] ); ?>');
		rdt('track','PageVisit');
	<?php
	echo '</script>';
}

/**
 * TikTok-pixel (marknadsföring).
 */
function rcc_output_tiktok( $s ) {
	if ( empty( $s['tiktok_pixel_id'] ) ) {
		return;
	}
	rcc_blocked_script_open( 'marketing' );
	?>
		!function (w, d, t) {
			w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie","holdConsent","revokeConsent","grantConsent"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=r,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};n=document.createElement("script");n.type="text/javascript",n.async=!0,n.src=r+"?sdkid="+e+"&lib="+t;e=document.getElementsByTagName("script")[0];e.parentNode.insertBefore(n,e)};
			ttq.load('<?php echo esc_js( $s['tiktok_pixel_id'] ); ?>');
			ttq.page();
		}(window, document, 'ttq');
	<?php
	echo '</script>';
}

/**
 * Pinterest-tagg (marknadsföring).
 */
function rcc_output_pinterest( $s ) {
	if ( empty( $s['pinterest_tag_id'] ) ) {
		return;
	}
	rcc_blocked_script_open( 'marketing' );
	?>
		!function(e){if(!window.pintrk){window.pintrk = function () {
		window.pintrk.queue.push(Array.prototype.slice.call(arguments))};var
		n=window.pintrk;n.queue=[],n.version="3.0";var
		t=document.createElement("script");t.async=!0,t.src=e;var
		r=document.getElementsByTagName("script")[0];
		r.parentNode.insertBefore(t,r)}}("https://s.pinimg.com/ct/core.js");
		pintrk('load', '<?php echo esc_js( $s['pinterest_tag_id'] ); ?>');
		pintrk('page');
	<?php
	echo '</script>';
}

/**
 * Snapchat-pixel (marknadsföring).
 */
function rcc_output_snapchat( $s ) {
	if ( empty( $s['snapchat_pixel_id'] ) ) {
		return;
	}
	rcc_blocked_script_open( 'marketing' );
	?>
		(function(e,t,n){if(e.snaptr)return;var a=e.snaptr=function()
		{a.handleRequest?a.handleRequest.apply(a,arguments):a.queue.push(arguments)};
		a.queue=[];var s='script',r=t.createElement(s);r.async=!0;
		r.src=n;var u=t.getElementsByTagName(s)[0];
		u.parentNode.insertBefore(r,u);})(window,document,
		'https://sc-static.net/scevent.min.js');
		snaptr('init', '<?php echo esc_js( $s['snapchat_pixel_id'] ); ?>', {});
		snaptr('track', 'PAGE_VIEW');
	<?php
	echo '</script>';
}

/**
 * Egen kod per kategori. Kan innehålla en blandning av <script>- och
 * andra taggar, så blocket körs genom data-html-block-mekanismen i
 * JS-filen i stället för att klonas som ett enda skript.
 */
function rcc_output_custom_code( $s ) {
	foreach ( array( 'statistics', 'marketing' ) as $category ) {
		$code = $s[ 'custom_code_' . $category ];
		if ( empty( $code ) ) {
			continue;
		}
		rcc_blocked_script_open( $category, 'data-html-block="1"' );
		echo $code; // phpcs:ignore -- sparas redan kontrollerat (kräver unfiltered_html) i rcc_sanitize_custom_code().
		echo '</script>';
	}
}

/**
 * Skriver ut alla blockerade skript i <head>.
 */
function rcc_output_gated_scripts() {
	$s = rcc_get_settings();

	rcc_output_google_scripts( $s );
	rcc_output_gtm_blocked( $s );
	rcc_output_meta_pixel( $s );
	rcc_output_hotjar( $s );
	rcc_output_clarity( $s );
	rcc_output_bing_uet( $s );
	rcc_output_linkedin( $s );
	rcc_output_reddit( $s );
	rcc_output_tiktok( $s );
	rcc_output_pinterest( $s );
	rcc_output_snapchat( $s );
	rcc_output_custom_code( $s );

	/**
	 * Låter tema eller andra plugin skriva ut egna blockerade skript,
	 * t.ex. rcc_blocked_script_open( 'statistics' ); ... echo '</script>';
	 */
	do_action( 'rcc_gated_scripts', $s );
}
add_action( 'wp_head', 'rcc_output_gated_scripts', 20 );
