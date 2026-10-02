<?php
/**
 * Inställningar: standardvärden, schema, sanering och admin-sidan.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Standardvärden. Alla texter som besökaren ser går att ändra per sajt,
 * så en engelskspråkig sajt konfigureras helt via inställningarna.
 */
function rcc_default_settings() {
	$defaults = array(
		// Google.
		'ga_measurement_id'     => '',
		'ga_treat_as_marketing' => 0,
		'google_ads_id'         => '',
		'gtm_container_id'      => '',
		'gtm_load_mode'         => 'blocked',
		'gsc_verification'      => '',

		// Meta.
		'meta_pixel_id'         => '',

		// Beteendeanalys (statistik).
		'hotjar_site_id'        => '',
		'clarity_project_id'    => '',

		// Annonsering (marknadsföring).
		'bing_uet_tag_id'       => '',
		'linkedin_partner_id'   => '',
		'reddit_pixel_id'       => '',
		'tiktok_pixel_id'       => '',
		'pinterest_tag_id'      => '',
		'snapchat_pixel_id'     => '',

		// Video-inbäddningar.
		'gate_video_embeds'     => 1,
		'youtube_category'      => 'marketing',
		'vimeo_category'        => 'marketing',

		// Domänblockering (skript och iframes från andra plugin/temat).
		'blocked_domains_statistics' => '',
		'blocked_domains_marketing'  => '',

		// Egen kod per kategori.
		'custom_code_statistics' => '',
		'custom_code_marketing'  => '',

		// Cookiedeklaration: egna cookies, en per rad.
		'custom_cookies'         => '',

		// Allmänt.
		'privacy_url'              => '/integritetspolicy/',
		'cookie_expiry_days'       => 180,
		'reload_on_revoke'         => 0,
		'show_floating_button'     => 1,
		'floating_button_position' => 'left',

		// Samtyckesversion och logg.
		'consent_version'              => 1,
		'consent_log_enabled'          => 1,
		'consent_log_retention_months' => 12,
		'consent_log_ip_hash'          => 0,
		'count_banner_views'           => 1,

		// Texter i cookie-rutan.
		'banner_heading'      => 'Vi använder cookies',
		'banner_text'         => 'Vi använder cookies för att webbplatsen ska fungera, för att analysera trafik och besökarbeteende, samt för att mäta och rikta marknadsföring. Nödvändiga cookies sätts alltid. Statistik- och marknadsföringscookies sätts bara om du samtycker. Du kan när som helst ändra ditt val via cookie-inställningarna.',
		'privacy_link_text'   => 'Läs mer i vår integritetspolicy',
		'necessary_label'     => 'Nödvändiga',
		'necessary_desc'      => 'Krävs för att webbplatsen ska fungera, bland annat för att komma ihåg dina cookie-inställningar. Kräver inte samtycke.',
		'statistics_label'    => 'Statistik',
		'statistics_desc'     => 'Hjälper oss förstå hur webbplatsen används, till exempel via Google Analytics.',
		'marketing_label'     => 'Marknadsföring',
		'marketing_desc'      => 'Används för att mäta och rikta annonsering utifrån ditt besök, till exempel via Meta och Google Ads.',
		'btn_accept_all'      => 'Acceptera alla',
		'btn_reject_all'      => 'Endast nödvändiga',
		'btn_customize'       => 'Anpassa val',
		'btn_save'            => 'Spara inställningar',
		'floating_button_label' => 'Cookie-inställningar',
		'consent_id_label'      => 'Ditt samtyckes-ID',
		'video_overlay_text'    => 'Visa innehåll',
		'video_overlay_requires' => 'kräver samtycke till',

		// Utseende.
		'banner_layout'     => 'bar',
		'show_backdrop'     => 0,
		'color_bg'          => '#ffffff',
		'color_text'        => '#1a1a1a',
		'color_accent'      => '#1a1a1a',
		'color_button_bg'   => '#1a1a1a',
		'color_button_text' => '#ffffff',
		'border_radius'     => 6,
		'inherit_font'      => 1,
		'custom_css'        => '',
	);

	/**
	 * Låter ett tema eller mu-plugin sätta egna standardvärden, t.ex.
	 * byråns husfärger, innan admin sparat något.
	 */
	return apply_filters( 'rcc_default_settings', $defaults );
}

/**
 * Hur varje fält ska saneras. Typer: text, checkbox, int, select,
 * color, textarea, code, css.
 */
function rcc_settings_schema() {
	return array(
		'ga_measurement_id'     => array( 'type' => 'text' ),
		'ga_treat_as_marketing' => array( 'type' => 'checkbox' ),
		'google_ads_id'         => array( 'type' => 'text' ),
		'gtm_container_id'      => array( 'type' => 'text' ),
		'gtm_load_mode'         => array( 'type' => 'select', 'options' => array( 'blocked', 'always' ) ),
		'gsc_verification'      => array( 'type' => 'text' ),

		'meta_pixel_id'         => array( 'type' => 'text' ),

		'hotjar_site_id'        => array( 'type' => 'text' ),
		'clarity_project_id'    => array( 'type' => 'text' ),

		'bing_uet_tag_id'       => array( 'type' => 'text' ),
		'linkedin_partner_id'   => array( 'type' => 'text' ),
		'reddit_pixel_id'       => array( 'type' => 'text' ),
		'tiktok_pixel_id'       => array( 'type' => 'text' ),
		'pinterest_tag_id'      => array( 'type' => 'text' ),
		'snapchat_pixel_id'     => array( 'type' => 'text' ),

		'gate_video_embeds'     => array( 'type' => 'checkbox' ),
		'youtube_category'      => array( 'type' => 'select', 'options' => array( 'marketing', 'statistics' ) ),
		'vimeo_category'        => array( 'type' => 'select', 'options' => array( 'marketing', 'statistics' ) ),

		'blocked_domains_statistics' => array( 'type' => 'domains' ),
		'blocked_domains_marketing'  => array( 'type' => 'domains' ),

		'custom_code_statistics' => array( 'type' => 'code' ),
		'custom_code_marketing'  => array( 'type' => 'code' ),

		'custom_cookies'         => array( 'type' => 'textarea' ),

		'privacy_url'              => array( 'type' => 'text' ),
		'cookie_expiry_days'       => array( 'type' => 'int', 'min' => 1, 'max' => 730 ),
		'reload_on_revoke'         => array( 'type' => 'checkbox' ),
		'show_floating_button'     => array( 'type' => 'checkbox' ),
		'floating_button_position' => array( 'type' => 'select', 'options' => array( 'left', 'right' ) ),

		'consent_version'              => array( 'type' => 'int', 'min' => 1, 'max' => 9999 ),
		'consent_log_enabled'          => array( 'type' => 'checkbox' ),
		'consent_log_retention_months' => array( 'type' => 'int', 'min' => 1, 'max' => 120 ),
		'consent_log_ip_hash'          => array( 'type' => 'checkbox' ),
		'count_banner_views'           => array( 'type' => 'checkbox' ),

		'banner_heading'         => array( 'type' => 'text' ),
		'banner_text'            => array( 'type' => 'textarea' ),
		'privacy_link_text'      => array( 'type' => 'text' ),
		'necessary_label'        => array( 'type' => 'text' ),
		'necessary_desc'         => array( 'type' => 'textarea' ),
		'statistics_label'       => array( 'type' => 'text' ),
		'statistics_desc'        => array( 'type' => 'textarea' ),
		'marketing_label'        => array( 'type' => 'text' ),
		'marketing_desc'         => array( 'type' => 'textarea' ),
		'btn_accept_all'         => array( 'type' => 'text' ),
		'btn_reject_all'         => array( 'type' => 'text' ),
		'btn_customize'          => array( 'type' => 'text' ),
		'btn_save'               => array( 'type' => 'text' ),
		'floating_button_label'  => array( 'type' => 'text' ),
		'consent_id_label'       => array( 'type' => 'text' ),
		'video_overlay_text'     => array( 'type' => 'text' ),
		'video_overlay_requires' => array( 'type' => 'text' ),

		'banner_layout'     => array( 'type' => 'select', 'options' => array( 'bar', 'card-left', 'card-right', 'center' ) ),
		'show_backdrop'     => array( 'type' => 'checkbox' ),
		'color_bg'          => array( 'type' => 'color' ),
		'color_text'        => array( 'type' => 'color' ),
		'color_accent'      => array( 'type' => 'color' ),
		'color_button_bg'   => array( 'type' => 'color' ),
		'color_button_text' => array( 'type' => 'color' ),
		'border_radius'     => array( 'type' => 'int', 'min' => 0, 'max' => 40 ),
		'inherit_font'      => array( 'type' => 'checkbox' ),
		'custom_css'        => array( 'type' => 'css' ),
	);
}

/**
 * Sparade inställningar med standardvärden ifyllda för saknade nycklar.
 * Filtret rcc_settings gör det möjligt att åsidosätta enskilda värden i
 * kod (t.ex. tvinga ett visst GA-ID i en staging-miljö).
 */
function rcc_get_settings() {
	$settings = get_option( RCC_OPTION_KEY, array() );
	if ( ! is_array( $settings ) ) {
		$settings = array();
	}
	return apply_filters( 'rcc_settings', wp_parse_args( $settings, rcc_default_settings() ) );
}

function rcc_register_settings() {
	register_setting( 'rcc_settings_group', RCC_OPTION_KEY, array(
		'type'              => 'array',
		'sanitize_callback' => 'rcc_sanitize_settings',
		'default'           => rcc_default_settings(),
	) );
}
add_action( 'admin_init', 'rcc_register_settings' );

/**
 * Egen kod (custom_code_*) sparas oskadad, med <script>-taggar intakta,
 * bara om den som sparar har rättigheten 'unfiltered_html' (normalt
 * administratörer). Annars städas markup bort, så att ingen med lägre
 * behörighet kan smyga in skript på sajten.
 */
function rcc_sanitize_custom_code( $value, $key = '' ) {
	if ( ! is_string( $value ) ) {
		return '';
	}
	if ( current_user_can( 'unfiltered_html' ) ) {
		return $value;
	}
	// Oförändrad kod får stå kvar. Annars skulle en användare utan
	// unfiltered_html (t.ex. en administratör på en multisite) radera all
	// egen kod bara genom att spara någon annan inställning, och
	// skannerns "Blockera"-knapp skulle göra samma sak.
	if ( $key ) {
		$saved = get_option( RCC_OPTION_KEY, array() );
		if ( is_array( $saved ) && isset( $saved[ $key ] ) && $saved[ $key ] === $value ) {
			return $value;
		}
	}
	return sanitize_textarea_field( $value );
}

/**
 * Egen CSS: tillåt aldrig taggar (skyddar mot </style><script>), i övrigt
 * sparas texten som den är.
 */
function rcc_sanitize_css( $value ) {
	if ( ! is_string( $value ) ) {
		return '';
	}
	$value = wp_strip_all_tags( $value );
	return str_replace( array( '</', '<' ), '', $value );
}

function rcc_sanitize_settings( $input ) {
	$defaults = rcc_default_settings();
	$schema   = rcc_settings_schema();
	$output   = array();

	if ( ! is_array( $input ) ) {
		$input = array();
	}

	foreach ( $schema as $key => $rule ) {
		$default = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
		$raw     = isset( $input[ $key ] ) ? $input[ $key ] : null;

		switch ( $rule['type'] ) {
			case 'checkbox':
				$output[ $key ] = ! empty( $raw ) ? 1 : 0;
				break;

			case 'int':
				$value = ( null === $raw || '' === $raw ) ? $default : absint( $raw );
				if ( isset( $rule['min'] ) && $value < $rule['min'] ) {
					$value = $default;
				}
				if ( isset( $rule['max'] ) && $value > $rule['max'] ) {
					$value = $rule['max'];
				}
				$output[ $key ] = $value;
				break;

			case 'select':
				$output[ $key ] = in_array( $raw, $rule['options'], true ) ? $raw : $default;
				break;

			case 'color':
				$color          = is_string( $raw ) ? sanitize_hex_color( trim( $raw ) ) : '';
				$output[ $key ] = $color ? $color : $default;
				break;

			case 'textarea':
				$output[ $key ] = is_string( $raw ) ? sanitize_textarea_field( $raw ) : $default;
				break;

			case 'code':
				$output[ $key ] = rcc_sanitize_custom_code( $raw, $key );
				break;

			case 'domains':
				$output[ $key ] = rcc_sanitize_domain_list( $raw );
				break;

			case 'css':
				$output[ $key ] = rcc_sanitize_css( $raw );
				break;

			case 'text':
			default:
				$output[ $key ] = is_string( $raw ) ? sanitize_text_field( $raw ) : $default;
				break;
		}
	}

	// Ett tomt tag-ID är tillåtet (verktyget skrivs då inte ut), men
	// synliga texter får inte bli tomma – då faller vi tillbaka på standard.
	foreach ( array( 'banner_heading', 'necessary_label', 'statistics_label', 'marketing_label', 'btn_accept_all', 'btn_reject_all', 'btn_customize', 'btn_save', 'floating_button_label', 'video_overlay_text' ) as $key ) {
		if ( '' === trim( (string) $output[ $key ] ) ) {
			$output[ $key ] = $defaults[ $key ];
		}
	}

	return $output;
}

/* -------------------------------------------------------------------------
 * Admin-sidan
 * ---------------------------------------------------------------------- */

/**
 * Färgväljare och flik-logik laddas bara på pluginets egen sida.
 * Menyn registreras i admin-menu.php.
 */
function rcc_admin_assets( $hook ) {
	if ( ! rcc_is_admin_page( $hook, RCC_SETTINGS_SLUG ) ) {
		return;
	}
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );
	wp_enqueue_style( 'rcc-admin', RCC_PLUGIN_URL . 'assets/css/admin.css', array(), RCC_VERSION );
	wp_enqueue_script( 'rcc-admin', RCC_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'wp-color-picker' ), RCC_VERSION, true );
}
add_action( 'admin_enqueue_scripts', 'rcc_admin_assets' );

/* Små hjälpfunktioner som skriver ut en tabellrad per fält. */

function rcc_field_name( $key ) {
	return RCC_OPTION_KEY . '[' . $key . ']';
}

function rcc_field_text( $s, $key, $label, $args = array() ) {
	$args = wp_parse_args( $args, array( 'placeholder' => '', 'description' => '', 'class' => 'regular-text', 'type' => 'text', 'attrs' => '' ) );
	?>
	<tr>
		<th scope="row"><label for="rcc_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
		<td>
			<input type="<?php echo esc_attr( $args['type'] ); ?>" id="rcc_<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( rcc_field_name( $key ) ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>" class="<?php echo esc_attr( $args['class'] ); ?>" placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>" <?php echo $args['attrs']; // phpcs:ignore -- statiska attribut från koden ovan. ?> />
			<?php if ( $args['description'] ) : ?>
				<p class="description"><?php echo wp_kses_post( $args['description'] ); ?></p>
			<?php endif; ?>
		</td>
	</tr>
	<?php
}

function rcc_field_checkbox( $s, $key, $label, $text, $description = '' ) {
	?>
	<tr>
		<th scope="row"><?php echo esc_html( $label ); ?></th>
		<td>
			<label><input type="checkbox" name="<?php echo esc_attr( rcc_field_name( $key ) ); ?>" value="1" <?php checked( 1, (int) $s[ $key ] ); ?> /> <?php echo esc_html( $text ); ?></label>
			<?php if ( $description ) : ?>
				<p class="description"><?php echo wp_kses_post( $description ); ?></p>
			<?php endif; ?>
		</td>
	</tr>
	<?php
}

function rcc_field_select( $s, $key, $label, $options, $description = '' ) {
	?>
	<tr>
		<th scope="row"><label for="rcc_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
		<td>
			<select id="rcc_<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( rcc_field_name( $key ) ); ?>">
				<?php foreach ( $options as $value => $text ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $s[ $key ] ); ?>><?php echo esc_html( $text ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php if ( $description ) : ?>
				<p class="description"><?php echo wp_kses_post( $description ); ?></p>
			<?php endif; ?>
		</td>
	</tr>
	<?php
}

function rcc_field_textarea( $s, $key, $label, $args = array() ) {
	$args = wp_parse_args( $args, array( 'rows' => 3, 'description' => '', 'class' => 'large-text' ) );
	?>
	<tr>
		<th scope="row"><label for="rcc_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
		<td>
			<textarea id="rcc_<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( rcc_field_name( $key ) ); ?>" rows="<?php echo (int) $args['rows']; ?>" class="<?php echo esc_attr( $args['class'] ); ?>"><?php echo esc_textarea( $s[ $key ] ); ?></textarea>
			<?php if ( $args['description'] ) : ?>
				<p class="description"><?php echo wp_kses_post( $args['description'] ); ?></p>
			<?php endif; ?>
		</td>
	</tr>
	<?php
}

function rcc_field_color( $s, $key, $label, $description = '' ) {
	?>
	<tr>
		<th scope="row"><label for="rcc_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
		<td>
			<input type="text" id="rcc_<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( rcc_field_name( $key ) ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>" class="rcc-color-field" data-default-color="<?php echo esc_attr( rcc_default_settings()[ $key ] ); ?>" />
			<?php if ( $description ) : ?>
				<p class="description"><?php echo wp_kses_post( $description ); ?></p>
			<?php endif; ?>
		</td>
	</tr>
	<?php
}

function rcc_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s = rcc_get_settings();

	$tabs = array(
		'tools'      => 'Verktyg',
		'video'      => 'Blockering',
		'cookies'    => 'Cookiedeklaration',
		'texts'      => 'Texter',
		'appearance' => 'Utseende',
		'general'    => 'Allmänt',
	);
	?>
	<div class="wrap rcc-settings">
		<h1>Relativt Cookie Consent <span class="rcc-version">v<?php echo esc_html( RCC_VERSION ); ?></span></h1>
		<?php
		// Sidor utanför menyn Inställningar visar inte "Inställningarna
		// sparade" av sig själva.
		settings_errors();
		?>
		<p>Fyll bara i ID:n för de verktyg sajten faktiskt använder. Tomma fält gör att motsvarande skript aldrig skrivs ut. Alla verktyg utom Google Search Console-verifieringen blockeras tills besökaren samtyckt till rätt kategori.</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'rcc_settings_group' ); ?>

			<h2 class="nav-tab-wrapper rcc-tabs">
				<?php foreach ( $tabs as $id => $title ) : ?>
					<a href="#rcc-tab-<?php echo esc_attr( $id ); ?>" class="nav-tab" data-rcc-tab="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $title ); ?></a>
				<?php endforeach; ?>
			</h2>

			<div class="rcc-tab-panel" id="rcc-tab-tools">
				<h2 class="title">Google</h2>
				<table class="form-table" role="presentation">
					<?php
					rcc_field_text( $s, 'ga_measurement_id', 'Google Analytics 4 – Measurement ID', array(
						'placeholder' => 'G-XXXXXXXXXX',
					) );
					rcc_field_checkbox( $s, 'ga_treat_as_marketing', 'GA4-kategori', 'Behandla Google Analytics som marknadsföring i stället för statistik', 'Kryssa i om GA4 är kopplat mot Google Ads-remarketing eller Google Signals. Används GA4 bara för ren statistik kan rutan lämnas avkryssad.' );
					rcc_field_text( $s, 'google_ads_id', 'Google Ads – Konto-ID', array(
						'placeholder' => 'AW-XXXXXXXXX',
						'description' => 'Konverterings- och remarketing-taggen. Laddas bara vid samtycke till marknadsföring.',
					) );
					rcc_field_text( $s, 'gtm_container_id', 'Google Tag Manager – Container-ID', array(
						'placeholder' => 'GTM-XXXXXXX',
					) );
					rcc_field_select( $s, 'gtm_load_mode', 'GTM laddas', array(
						'blocked' => 'Först vid samtycke till statistik eller marknadsföring (standard)',
						'always'  => 'Alltid, och förlitar sig på Consent Mode v2 i taggarna',
					), 'Med "Alltid" laddas containern direkt och samtyckessignalen skickas via Google Consent Mode v2 (<code>gtag(\'consent\', ...)</code>) samt dataLayer-eventet <code>rcc_consent_update</code>. Då måste varje tagg i GTM ha samtyckeskontroller inställda, annars kan de sätta cookies utan samtycke. Blockerat läge är säkrast om ni inte hanterar samtycke i GTM.' );
					rcc_field_text( $s, 'gsc_verification', 'Google Search Console – verifieringskod', array(
						'placeholder' => 'innehållet i content= från verifierings-metataggen',
						'description' => 'Ren ägarskaps-verifiering, sätter inga cookies och läggs därför in oavsett samtycke.',
					) );
					?>
				</table>

				<h2 class="title">Meta</h2>
				<table class="form-table" role="presentation">
					<?php
					rcc_field_text( $s, 'meta_pixel_id', 'Meta Pixel – Pixel-ID', array( 'placeholder' => '15-siffrigt ID' ) );
					?>
				</table>

				<h2 class="title">Beteendeanalys (statistik)</h2>
				<table class="form-table" role="presentation">
					<?php
					rcc_field_text( $s, 'hotjar_site_id', 'Hotjar – Site-ID', array( 'placeholder' => 'numeriskt ID' ) );
					rcc_field_text( $s, 'clarity_project_id', 'Microsoft Clarity – Project-ID', array( 'placeholder' => 'kort alfanumeriskt ID' ) );
					?>
				</table>

				<h2 class="title">Annonsering (marknadsföring)</h2>
				<table class="form-table" role="presentation">
					<?php
					rcc_field_text( $s, 'bing_uet_tag_id', 'Microsoft/Bing Advertising – UET Tag-ID', array( 'placeholder' => 'numeriskt ID' ) );
					rcc_field_text( $s, 'linkedin_partner_id', 'LinkedIn Insight Tag – Partner-ID', array( 'placeholder' => 'numeriskt ID' ) );
					rcc_field_text( $s, 'reddit_pixel_id', 'Reddit – Pixel-ID', array( 'placeholder' => 't2_xxxxxxx' ) );
					rcc_field_text( $s, 'tiktok_pixel_id', 'TikTok – Pixel-ID', array( 'placeholder' => 'alfanumeriskt ID' ) );
					rcc_field_text( $s, 'pinterest_tag_id', 'Pinterest – Tag-ID', array( 'placeholder' => 'numeriskt ID' ) );
					rcc_field_text( $s, 'snapchat_pixel_id', 'Snapchat – Pixel-ID', array( 'placeholder' => 'UUID' ) );
					?>
				</table>
			</div>

			<div class="rcc-tab-panel" id="rcc-tab-video" hidden>
				<h2 class="title">Video-inbäddningar</h2>
				<table class="form-table" role="presentation">
					<?php
					rcc_field_checkbox( $s, 'gate_video_embeds', 'Blockera video tills samtycke', 'Ersätt YouTube-/Vimeo-inbäddningar med en "Visa innehåll"-knapp tills rätt samtycke finns', 'Fungerar sajtbrett (även för videor infogade i sidbyggare som Oxygen, Elementor och Bricks) genom att pluginet skriver om sidans HTML innan den skickas till besökaren. Testa på en sida med video efter aktivering. Slå av rutan om det stör layouten någonstans, men tänk då på att dessa videor sätter cookies utan samtycke.' );
					rcc_field_select( $s, 'youtube_category', 'YouTube räknas som', array(
						'marketing'  => 'Marknadsföring (standard – YouTubes vanliga inbäddning sätter annonscookies)',
						'statistics' => 'Statistik',
					) );
					rcc_field_select( $s, 'vimeo_category', 'Vimeo räknas som', array(
						'marketing'  => 'Marknadsföring (standard)',
						'statistics' => 'Statistik',
					) );
					?>
				</table>

				<h2 class="title">Blockera domäner</h2>
				<p>Skript och inbäddningar från domänerna nedan blockeras tills besökaren samtyckt, även när de skrivs ut av andra plugin, temat eller sidbyggaren. Använd det för verktyg som redan ligger på sajten utanför pluginet. <a href="<?php echo esc_url( rcc_admin_page_url( RCC_SCANNER_SLUG ) ); ?>">Skannern</a> visar vilka domäner som laddas utan att blockeras och kan lägga till dem här med ett klick.</p>
				<table class="form-table" role="presentation">
					<?php
					$domain_help = 'En domän per rad, t.ex. <code>connect.facebook.net</code>. Subdomäner ingår. Blockerar <code>&lt;script src&gt;</code> och iframes från domänen, och inline-skript där domänen står eller som bär ett känt verktygs kod (t.ex. <code>fbq(</code> för Meta). Skript som laddas av annan JavaScript efter att sidan visats fångas inte.';
					rcc_field_textarea( $s, 'blocked_domains_statistics', 'Statistik', array( 'rows' => 4, 'class' => 'large-text code', 'description' => $domain_help ) );
					rcc_field_textarea( $s, 'blocked_domains_marketing', 'Marknadsföring', array( 'rows' => 4, 'class' => 'large-text code', 'description' => 'Står en domän i båda listorna räcker samtycke till endera. Lägg inte sajtens egen domän här – den blockeras aldrig.' ) );
					?>
				</table>

				<h2 class="title">Egen kod</h2>
				<p>Klistra in färdiga skript-taggar från verktyg som saknar eget fält (t.ex. Bidtheatre, Upsales, Leadfeeder, Albacross). Koden blockeras och aktiveras precis som de inbyggda verktygen i respektive kategori. Kräver behörigheten <code>unfiltered_html</code> (normalt administratörer) för att sparas med <code>&lt;script&gt;</code>-taggar intakta.</p>
				<table class="form-table" role="presentation">
					<?php
					rcc_field_textarea( $s, 'custom_code_statistics', 'Egen kod – statistik', array( 'rows' => 6, 'class' => 'large-text code' ) );
					rcc_field_textarea( $s, 'custom_code_marketing', 'Egen kod – marknadsföring', array( 'rows' => 6, 'class' => 'large-text code' ) );
					?>
				</table>
			</div>

			<div class="rcc-tab-panel" id="rcc-tab-cookies" hidden>
				<h2 class="title">Cookiedeklaration</h2>
				<p>Listan över sajtens cookies byggs automatiskt av de verktyg som har ett ID ifyllt, YouTube och Vimeo när videoblockeringen är på, cookies som andra tillägg registrerat, och pluginets egen cookie. Visa den på integritetspolicyn med kortkoden <code>[relativt_cookie_declaration]</code>. Vill du bara visa en kategori: <code>[relativt_cookie_declaration category="marknadsföring"]</code>.</p>
				<table class="form-table" role="presentation">
					<?php
					rcc_field_textarea( $s, 'custom_cookies', 'Egna cookies', array(
						'rows'        => 6,
						'class'       => 'large-text code',
						'description' => 'För verktyg under Egen kod eller Blockera domäner, och annat som sätter cookies. En cookie per rad: <code>namn | leverantör | kategori | syfte | lagringstid</code>, t.ex. <code>_upsales_visitor | Upsales | marknadsföring | Känner igen återkommande företagsbesök. | 1 år</code>. Kategori: nödvändiga, statistik eller marknadsföring.',
					) );
					?>
				</table>
				<?php
				rcc_parse_custom_cookies( $s['custom_cookies'], $cookie_errors );
				if ( $cookie_errors ) :
					?>
					<div class="notice notice-warning inline"><p>Raderna nedan kunde inte läsas (saknar namn eller har okänd kategori) och visas inte:</p><ul>
						<?php foreach ( $cookie_errors as $error ) : ?>
							<li><code><?php echo esc_html( $error ); ?></code></li>
						<?php endforeach; ?>
					</ul></div>
				<?php endif; ?>
				<?php
				$registered = array_filter( rcc_registered_cookies( $s ), function ( $entry ) {
					return $entry['declare'] && 0 !== strpos( $entry['plugin'], 'Relativt Cookie Consent' );
				} );
				if ( $registered ) :
					$cat_labels = array(
						'necessary'  => $s['necessary_label'],
						'statistics' => $s['statistics_label'],
						'marketing'  => $s['marketing_label'],
					);
					?>
					<div class="notice notice-info inline"><p>Andra tillägg har registrerat de här cookiesarna. De ingår i listan, och de som inte är nödvändiga raderas när besökaren nekar kategorin:</p><ul>
						<?php foreach ( $registered as $entry ) : ?>
							<li><code><?php echo esc_html( $entry['name'] ); ?></code> – <?php echo esc_html( implode( ' eller ', array_map( function ( $cat ) use ( $cat_labels ) {
								return $cat_labels[ $cat ];
							}, $entry['categories'] ) ) ); ?><?php echo $entry['plugin'] ? ' (' . esc_html( $entry['plugin'] ) . ')' : ''; ?></li>
						<?php endforeach; ?>
					</ul></div>
				<?php endif; ?>
				<?php $registry_errors = rcc_registry_error(); ?>
				<?php if ( $registry_errors ) : ?>
					<div class="notice notice-warning inline"><p>Ett tillägg har registrerat cookies som inte kunde läsas (ogiltigt namn eller okänd kategori) och därför varken listas eller städas:</p><ul>
						<?php foreach ( $registry_errors as $error ) : ?>
							<li><code><?php echo esc_html( $error ); ?></code></li>
						<?php endforeach; ?>
					</ul></div>
				<?php endif; ?>
				<?php if ( rcc_wp_consent_api_active() ) : ?>
					<div class="notice notice-info inline"><p>WP Consent API är aktivt. Tillägg som läser det (t.ex. WooCommerce och Site Kit) följer besökarens val i den här rutan.</p></div>
				<?php endif; ?>
				<?php
				$gtm_note     = ! empty( $s['gtm_container_id'] );
				$domain_count = count( rcc_blocked_domains( $s ) );
				$custom_code  = '' !== trim( $s['custom_code_statistics'] . $s['custom_code_marketing'] );
				if ( $gtm_note || $domain_count || $custom_code ) :
					?>
					<div class="notice notice-info inline"><p>Pluginet vet inte vilka cookies som sätts av
						<?php
						$parts = array();
						if ( $gtm_note ) {
							$parts[] = 'taggarna i Google Tag Manager';
						}
						if ( $custom_code ) {
							$parts[] = 'Egen kod';
						}
						if ( $domain_count ) {
							$parts[] = sprintf( 'de %d blockerade domänerna', $domain_count );
						}
						$last = array_pop( $parts );
						echo esc_html( $parts ? implode( ', ', $parts ) . ' och ' . $last : $last );
						?>. Lägg till dem under Egna cookies.</p></div>
				<?php endif; ?>
				<h2 class="title">Förhandsvisning</h2>
				<p class="description">Så här ser listan ut med de sparade inställningarna. Spara för att se ändringar.</p>
				<div class="rcc-declaration-preview">
					<?php echo rcc_render_cookie_declaration( $s, array( 'heading_tag' => 'h4' ) ); // phpcs:ignore -- escapat i funktionen. ?>
				</div>
			</div>

			<div class="rcc-tab-panel" id="rcc-tab-texts" hidden>
				<h2 class="title">Cookie-rutan</h2>
				<table class="form-table" role="presentation">
					<?php
					rcc_field_text( $s, 'banner_heading', 'Rubrik' );
					rcc_field_textarea( $s, 'banner_text', 'Ingresstext', array( 'rows' => 4 ) );
					rcc_field_text( $s, 'privacy_link_text', 'Länktext till integritetspolicyn', array( 'description' => 'Visas efter ingressen om en länk till integritetspolicyn är angiven under Allmänt.' ) );
					?>
				</table>

				<h2 class="title">Kategorier</h2>
				<table class="form-table" role="presentation">
					<?php
					rcc_field_text( $s, 'necessary_label', 'Nödvändiga – etikett' );
					rcc_field_textarea( $s, 'necessary_desc', 'Nödvändiga – beskrivning', array( 'rows' => 2 ) );
					rcc_field_text( $s, 'statistics_label', 'Statistik – etikett' );
					rcc_field_textarea( $s, 'statistics_desc', 'Statistik – beskrivning', array( 'rows' => 2 ) );
					rcc_field_text( $s, 'marketing_label', 'Marknadsföring – etikett' );
					rcc_field_textarea( $s, 'marketing_desc', 'Marknadsföring – beskrivning', array( 'rows' => 2 ) );
					?>
				</table>

				<h2 class="title">Knappar</h2>
				<table class="form-table" role="presentation">
					<?php
					rcc_field_text( $s, 'btn_accept_all', 'Acceptera alla' );
					rcc_field_text( $s, 'btn_reject_all', 'Endast nödvändiga' );
					rcc_field_text( $s, 'btn_customize', 'Anpassa val' );
					rcc_field_text( $s, 'btn_save', 'Spara inställningar' );
					rcc_field_text( $s, 'floating_button_label', 'Flytande knapp (skärmläsartext)', array( 'description' => 'Används också som standardtext för kortkoden <code>[relativt_cookie_settings]</code>.' ) );
					rcc_field_text( $s, 'consent_id_label', 'Samtyckes-ID – etikett', array( 'description' => 'Visas under kategorierna när en besökare öppnar sina cookie-inställningar, följt av ID:t och tidpunkten för valet. Lämna tom för att inte visa ID:t.' ) );
					?>
				</table>

				<h2 class="title">Blockerad video</h2>
				<table class="form-table" role="presentation">
					<?php
					rcc_field_text( $s, 'video_overlay_text', 'Knapptext', array( 'description' => 'Knappen ovanpå en blockerad video. Kategorin läggs till automatiskt: "Visa innehåll (kräver samtycke till marknadsföring)".' ) );
					rcc_field_text( $s, 'video_overlay_requires', 'Mellantext', array( 'description' => 'Texten mellan knapptexten och kategorinamnet. Lämna tom för att bara visa knapptexten.' ) );
					?>
				</table>
			</div>

			<div class="rcc-tab-panel" id="rcc-tab-appearance" hidden>
				<h2 class="title">Layout</h2>
				<table class="form-table" role="presentation">
					<?php
					rcc_field_select( $s, 'banner_layout', 'Placering', array(
						'bar'        => 'Rad längst ner i full bredd (standard)',
						'card-left'  => 'Kort nere till vänster',
						'card-right' => 'Kort nere till höger',
						'center'     => 'Centrerad ruta med mörkad bakgrund',
					) );
					rcc_field_checkbox( $s, 'show_backdrop', 'Mörka sidan', 'Lägg en halvgenomskinlig bakgrund över sidan tills besökaren gjort ett val', 'Gäller alltid för den centrerade rutan. För rad och kort är det valfritt.' );
					rcc_field_text( $s, 'border_radius', 'Hörnradie (px)', array( 'type' => 'number', 'class' => 'small-text', 'attrs' => 'min="0" max="40"', 'description' => 'Används på knappar och på kortet/rutan.' ) );
					rcc_field_checkbox( $s, 'inherit_font', 'Typsnitt', 'Ärv typsnitt från temat', 'Avkryssad används ett systemtypsnitt (samma som WordPress-admin) oavsett tema.' );
					?>
				</table>

				<h2 class="title">Färger</h2>
				<table class="form-table" role="presentation">
					<?php
					rcc_field_color( $s, 'color_bg', 'Bakgrund' );
					rcc_field_color( $s, 'color_text', 'Text' );
					rcc_field_color( $s, 'color_accent', 'Accent', 'Länkar, aktiva reglage och konturknappar.' );
					rcc_field_color( $s, 'color_button_bg', 'Primärknapp – bakgrund', 'Knappen "Acceptera alla" och knappen ovanpå blockerad video.' );
					rcc_field_color( $s, 'color_button_text', 'Primärknapp – text' );
					?>
				</table>

				<h2 class="title">Egen CSS</h2>
				<table class="form-table" role="presentation">
					<?php
					rcc_field_textarea( $s, 'custom_css', 'Extra CSS', array( 'rows' => 8, 'class' => 'large-text code', 'description' => 'Skrivs ut efter pluginets egen CSS. Alla element har prefixet <code>.rcc-</code>, t.ex. <code>.rcc-banner</code>, <code>.rcc-btn--primary</code>, <code>.rcc-reopen</code>. Färgerna ovan finns som CSS-variabler: <code>--rcc-bg</code>, <code>--rcc-text</code>, <code>--rcc-accent</code>, <code>--rcc-btn-bg</code>, <code>--rcc-btn-text</code>, <code>--rcc-radius</code>.' ) );
					?>
				</table>
			</div>

			<div class="rcc-tab-panel" id="rcc-tab-general" hidden>
				<table class="form-table" role="presentation">
					<?php
					rcc_field_text( $s, 'privacy_url', 'Länk till integritetspolicy', array( 'placeholder' => '/integritetspolicy/', 'description' => 'Relativ eller absolut adress. Lämna tom för att inte visa någon länk.' ) );
					rcc_field_text( $s, 'cookie_expiry_days', 'Samtycket sparas i (dagar)', array( 'type' => 'number', 'class' => 'small-text', 'attrs' => 'min="1" max="730"', 'description' => 'Standard 180 dagar. IMY rekommenderar att samtycke inhämtas på nytt åtminstone årligen.' ) );
					rcc_field_checkbox( $s, 'reload_on_revoke', 'Vid återkallat samtycke', 'Ladda om sidan när besökaren tar bort ett tidigare samtycke', 'Redan laddade skript går inte att "avladda". En omladdning gör att sidan visas helt utan de verktygen direkt.' );
					rcc_field_checkbox( $s, 'show_floating_button', 'Flytande knapp', 'Visa en flytande knapp så besökare kan ändra sitt val när som helst', 'Du kan också placera en inline-länk var som helst med kortkoden <code>[relativt_cookie_settings text="Cookie-inställningar"]</code> eller ge valfritt element attributet <code>data-rcc-open</code>.' );
					rcc_field_select( $s, 'floating_button_position', 'Knappens position', array(
						'left'  => 'Nere till vänster',
						'right' => 'Nere till höger',
					) );
					?>
				</table>

				<h2 class="title">Samtyckesversion</h2>
				<table class="form-table" role="presentation">
					<?php
					rcc_field_text( $s, 'consent_version', 'Aktuell version', array( 'type' => 'number', 'class' => 'small-text', 'attrs' => 'min="1" max="9999"', 'description' => 'Höj siffran med ett när ni lägger till ett nytt verktyg eller ändrar texterna i cookie-rutan. Alla besökare med ett äldre samtycke får då rutan på nytt och måste ta ställning igen. Versionen sparas i varje loggpost så att det syns vilken uppsättning verktyg och texter samtycket gällde.' ) );
					?>
				</table>

				<h2 class="title">Samtyckeslogg</h2>
				<p>Loggen sparar varje val som bevis på att samtycke inhämtats (GDPR art. 7.1). Visas under <a href="<?php echo esc_url( rcc_admin_page_url( RCC_LOG_PAGE_SLUG ) ); ?>">Cookie Consent → Samtyckeslogg</a> där den också kan exporteras som CSV.</p>
				<table class="form-table" role="presentation">
					<?php
					rcc_field_checkbox( $s, 'consent_log_enabled', 'Loggning', 'Spara besökarnas val i samtyckesloggen', 'Sparas: slumpat samtyckes-ID, tidpunkt, valda kategorier, land (om servern eller CDN:et skickar det, t.ex. Cloudflare), samtyckesversion, plugin-version och webbläsarsträng. Ingen IP-adress om inte rutan nedan är ikryssad.' );
					rcc_field_text( $s, 'consent_log_retention_months', 'Gallring efter (månader)', array( 'type' => 'number', 'class' => 'small-text', 'attrs' => 'min="1" max="120"', 'description' => 'Poster äldre än så raderas automatiskt varje dygn. Standard 12 månader. Sätt inte kortare än samtyckets giltighetstid ovan.' ) );
					rcc_field_checkbox( $s, 'count_banner_views', 'Visningar', 'Räkna hur många gånger rutan visas, till statistiken', 'Bara en räknare per dag, inga uppgifter om besökaren. Behövs för svarsfrekvensen under <a href="' . esc_url( rcc_admin_page_url( RCC_STATS_SLUG ) ) . '">Statistik</a>.' );
					rcc_field_checkbox( $s, 'consent_log_ip_hash', 'IP-adress', 'Spara en saltad hash av besökarens IP-adress', 'Hashen går inte att vända till en IP-adress men ger starkare bevisvärde. Nämn i integritetspolicyn om ni slår på detta.' );
					?>
				</table>

				<h2 class="title">Uppdateringar</h2>
				<p>Pluginet hämtar nya versioner från GitHub-repot <code><?php echo esc_html( RCC_GITHUB_REPO ); ?></code> och visar dem under Uppdateringar i WP-admin precis som andra plugin. <?php if ( defined( 'RCC_GITHUB_TOKEN' ) && RCC_GITHUB_TOKEN ) : ?>En åtkomsttoken är konfigurerad i wp-config.php.<?php else : ?>Är repot privat behöver <code>RCC_GITHUB_TOKEN</code> definieras i wp-config.php.<?php endif; ?></p>
			</div>

			<?php submit_button( 'Spara inställningar' ); ?>
		</form>
	</div>
	<?php
}
