<?php
/**
 * Frontend: tillgångar, banner-markup, video-gating och kortkod.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Namnet på samtyckescookien. Filtret gör det möjligt att behålla ett
 * äldre namn på en sajt som byter från en tidigare version, så att
 * besökarna slipper samtycka om.
 */
function rcc_cookie_name() {
	return apply_filters( 'rcc_cookie_name', 'relativt_cookie_consent' );
}

/* -------------------------------------------------------------------------
 * Video-gating (värdar)
 * ---------------------------------------------------------------------- */

/**
 * Värdar vars iframes blockeras tills samtycke, med kategori per värd.
 * Subdomäner (www., player. osv.) matchas automatiskt. Fler värdar kan
 * läggas till via filtret rcc_gated_iframe_hosts, t.ex.
 * 'google.com' => 'marketing' för Google Maps-inbäddningar.
 */
function rcc_gated_iframe_hosts( $s ) {
	$hosts = array(
		'youtube.com'          => $s['youtube_category'],
		'youtube-nocookie.com' => $s['youtube_category'],
		'vimeo.com'            => $s['vimeo_category'],
	);
	return apply_filters( 'rcc_gated_iframe_hosts', $hosts, $s );
}

// Själva bearbetningen av HTML:en ligger i script-blocking.php.

/* -------------------------------------------------------------------------
 * Tillgångar
 * ---------------------------------------------------------------------- */

/**
 * CSS-variabler som styrs från Utseende-fliken, plus eventuell egen CSS.
 */
function rcc_inline_css( $s ) {
	$font = ! empty( $s['inherit_font'] )
		? 'inherit'
		: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif';

	$css = sprintf(
		':root{--rcc-bg:%s;--rcc-text:%s;--rcc-accent:%s;--rcc-btn-bg:%s;--rcc-btn-text:%s;--rcc-radius:%dpx;--rcc-font:%s;}',
		sanitize_hex_color( $s['color_bg'] ) ?: '#ffffff',
		sanitize_hex_color( $s['color_text'] ) ?: '#1a1a1a',
		sanitize_hex_color( $s['color_accent'] ) ?: '#1a1a1a',
		sanitize_hex_color( $s['color_button_bg'] ) ?: '#1a1a1a',
		sanitize_hex_color( $s['color_button_text'] ) ?: '#ffffff',
		(int) $s['border_radius'],
		$font
	);

	if ( ! empty( $s['custom_css'] ) ) {
		$css .= "\n" . rcc_sanitize_css( $s['custom_css'] );
	}

	return apply_filters( 'rcc_inline_css', $css, $s );
}

function rcc_enqueue_assets() {
	$s = rcc_get_settings();

	wp_enqueue_style( 'rcc-style', RCC_PLUGIN_URL . 'assets/css/relativt-cookie-consent.css', array(), RCC_VERSION );
	wp_add_inline_style( 'rcc-style', rcc_inline_css( $s ) );

	wp_enqueue_script( 'rcc-script', RCC_PLUGIN_URL . 'assets/js/relativt-cookie-consent.js', array(), RCC_VERSION, true );

	$config = array(
		'cookieName'       => rcc_cookie_name(),
		'cookieExpiryDays' => (int) $s['cookie_expiry_days'],
		'consentVersion'   => max( 1, (int) $s['consent_version'] ),
		'logEndpoint'      => rcc_consent_log_enabled() ? esc_url_raw( rest_url( 'rcc/v1/consent' ) ) : '',
		'viewEndpoint'     => rcc_count_banner_views_enabled() ? esc_url_raw( rest_url( 'rcc/v1/view' ) ) : '',
		'reloadOnRevoke'   => ! empty( $s['reload_on_revoke'] ),
		'cookieRegistry'   => rcc_cookie_registry_for_js( $s ),
		'protectedCookies' => rcc_protected_cookies(),
		'wpConsentApi'     => rcc_wp_consent_api_js_config(),
		'backdrop'         => ( ! empty( $s['show_backdrop'] ) || 'center' === $s['banner_layout'] ),
		'i18n'             => array(
			'statistics'      => $s['statistics_label'],
			'marketing'       => $s['marketing_label'],
			'showContent'     => $s['video_overlay_text'],
			'requiresConsent' => $s['video_overlay_requires'],
		),
	);

	wp_localize_script( 'rcc-script', 'rccSettings', apply_filters( 'rcc_script_config', $config, $s ) );
}
add_action( 'wp_enqueue_scripts', 'rcc_enqueue_assets' );

/* -------------------------------------------------------------------------
 * Banner
 * ---------------------------------------------------------------------- */

/**
 * Bannerns markup, injiceras i footern på varje sida. Är dold tills
 * JS-filen avgjort om besökaren redan gjort ett val.
 */
function rcc_render_banner() {
	$s        = rcc_get_settings();
	$layout   = in_array( $s['banner_layout'], array( 'bar', 'card-left', 'card-right', 'center' ), true ) ? $s['banner_layout'] : 'bar';
	$backdrop = ( ! empty( $s['show_backdrop'] ) || 'center' === $layout );
	?>
	<?php if ( $backdrop ) : ?>
		<div id="rcc-backdrop" class="rcc-backdrop" hidden></div>
	<?php endif; ?>

	<div id="rcc-banner" class="rcc-banner rcc-banner--<?php echo esc_attr( $layout ); ?>" role="dialog" aria-labelledby="rcc-heading" aria-describedby="rcc-text" <?php echo $backdrop ? 'aria-modal="true"' : ''; ?> hidden>
		<div class="rcc-banner__inner">
			<?php do_action( 'rcc_banner_top', $s ); ?>

			<div class="rcc-banner__text">
				<h2 id="rcc-heading" class="rcc-banner__heading"><?php echo esc_html( $s['banner_heading'] ); ?></h2>
				<p id="rcc-text"><?php echo esc_html( $s['banner_text'] ); ?>
					<?php if ( ! empty( $s['privacy_url'] ) && ! empty( $s['privacy_link_text'] ) ) : ?>
						<a href="<?php echo esc_url( $s['privacy_url'] ); ?>"><?php echo esc_html( $s['privacy_link_text'] ); ?></a>.
					<?php endif; ?>
				</p>
			</div>

			<div class="rcc-banner__categories" id="rcc-categories" hidden>
				<div class="rcc-category">
					<div class="rcc-category__row">
						<span class="rcc-switch rcc-switch--locked">
							<input type="checkbox" id="rcc-cat-necessary" checked disabled />
							<span class="rcc-switch__slider" aria-hidden="true"></span>
						</span>
						<label for="rcc-cat-necessary" class="rcc-category__label"><?php echo esc_html( $s['necessary_label'] ); ?></label>
					</div>
					<p><?php echo esc_html( $s['necessary_desc'] ); ?></p>
				</div>
				<div class="rcc-category">
					<div class="rcc-category__row">
						<span class="rcc-switch">
							<input type="checkbox" id="rcc-cat-statistics" />
							<span class="rcc-switch__slider" aria-hidden="true"></span>
						</span>
						<label for="rcc-cat-statistics" class="rcc-category__label"><?php echo esc_html( $s['statistics_label'] ); ?></label>
					</div>
					<p><?php echo esc_html( $s['statistics_desc'] ); ?></p>
				</div>
				<div class="rcc-category">
					<div class="rcc-category__row">
						<span class="rcc-switch">
							<input type="checkbox" id="rcc-cat-marketing" />
							<span class="rcc-switch__slider" aria-hidden="true"></span>
						</span>
						<label for="rcc-cat-marketing" class="rcc-category__label"><?php echo esc_html( $s['marketing_label'] ); ?></label>
					</div>
					<p><?php echo esc_html( $s['marketing_desc'] ); ?></p>
				</div>
				<?php if ( '' !== trim( (string) $s['consent_id_label'] ) ) : ?>
					<p class="rcc-banner__meta" id="rcc-consent-meta" hidden><?php echo esc_html( $s['consent_id_label'] ); ?>: <code id="rcc-consent-id"></code> <span id="rcc-consent-date"></span></p>
				<?php endif; ?>
			</div>

			<div class="rcc-banner__actions">
				<button type="button" class="rcc-btn rcc-btn--text" id="rcc-toggle-settings" aria-expanded="false" aria-controls="rcc-categories"><?php echo esc_html( $s['btn_customize'] ); ?></button>
				<button type="button" class="rcc-btn rcc-btn--outline" id="rcc-reject-all"><?php echo esc_html( $s['btn_reject_all'] ); ?></button>
				<button type="button" class="rcc-btn rcc-btn--outline" id="rcc-save-settings" hidden><?php echo esc_html( $s['btn_save'] ); ?></button>
				<button type="button" class="rcc-btn rcc-btn--primary" id="rcc-accept-all"><?php echo esc_html( $s['btn_accept_all'] ); ?></button>
			</div>

			<?php do_action( 'rcc_banner_bottom', $s ); ?>
		</div>
	</div>

	<?php if ( ! empty( $s['show_floating_button'] ) ) : ?>
		<button type="button" id="rcc-reopen" class="rcc-reopen rcc-reopen--<?php echo esc_attr( $s['floating_button_position'] ); ?>" aria-label="<?php echo esc_attr( $s['floating_button_label'] ); ?>" title="<?php echo esc_attr( $s['floating_button_label'] ); ?>" data-rcc-open hidden>
			<?php echo rcc_cookie_icon_svg(); // phpcs:ignore -- statisk SVG. ?>
		</button>
	<?php endif; ?>
	<?php
}
add_action( 'wp_footer', 'rcc_render_banner' );

function rcc_cookie_icon_svg() {
	$svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M21 12.5c0 5.25-4.25 9.5-9.5 9.5S2 17.75 2 12.5 6.25 3 11.5 3c.5 0 1 .04 1.47.12-.3.6-.47 1.28-.47 2 0 2.49 2.01 4.5 4.5 4.5.4 0 .78-.05 1.15-.15.06.36.35.62.7.68C20.75 10.6 21 11.53 21 12.5z" stroke="currentColor" stroke-width="1.5"/><circle cx="8.5" cy="12" r="1" fill="currentColor"/><circle cx="12" cy="16" r="1" fill="currentColor"/><circle cx="15" cy="11" r="1" fill="currentColor"/></svg>';
	return apply_filters( 'rcc_cookie_icon_svg', $svg );
}

/* -------------------------------------------------------------------------
 * Kortkod
 * ---------------------------------------------------------------------- */

/**
 * [relativt_cookie_settings text="Cookie-inställningar" class=""]
 * En textlänk som öppnar cookie-inställningarna. Praktisk i footern
 * eller på integritetspolicysidan. Fungerar flera gånger på samma sida.
 */
function rcc_settings_shortcode( $atts ) {
	$s    = rcc_get_settings();
	$atts = shortcode_atts( array(
		'text'  => $s['floating_button_label'],
		'class' => '',
	), $atts, 'relativt_cookie_settings' );

	$class = 'rcc-inline-link' . ( $atts['class'] ? ' ' . $atts['class'] : '' );

	return '<button type="button" class="' . esc_attr( $class ) . '" data-rcc-open>' . esc_html( $atts['text'] ) . '</button>';
}
add_shortcode( 'relativt_cookie_settings', 'rcc_settings_shortcode' );
