<?php
/**
 * Plugin Name:       Relativt Cookie Consent
 * Plugin URI:        https://github.com/relativtwebb/relativt-cookie-consent
 * Description:       Lättviktig GDPR-anpassad cookie-ruta med samtyckeslogg, statistik, cookiedeklaration och skriptskanner. Blockerar Google Analytics, Google Ads, Google Tag Manager, Meta-pixeln, TikTok, Pinterest, Snapchat, LinkedIn, Reddit, Hotjar, Microsoft Clarity, Bing UET, egen kod, valfria domäner samt YouTube-/Vimeo-inbäddningar tills besökaren samtyckt.
 * Version:           1.4.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Relativt
 * Author URI:        https://relativt.se
 * Text Domain:       relativt-cookie-consent
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://github.com/relativtwebb/relativt-cookie-consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Direkt åtkomst inte tillåten.
}

define( 'RCC_VERSION', '1.4.0' );
define( 'RCC_PLUGIN_FILE', __FILE__ );
define( 'RCC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RCC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RCC_OPTION_KEY', 'rcc_settings' );

/**
 * GitHub-repot som pluginet hämtar uppdateringar från (ägare/repo).
 * Byt till ert eget konto om repot flyttas. För privata repon: lägg
 * define( 'RCC_GITHUB_TOKEN', '...' ); i wp-config.php på kundsajten.
 */
if ( ! defined( 'RCC_GITHUB_REPO' ) ) {
	define( 'RCC_GITHUB_REPO', 'relativtwebb/relativt-cookie-consent' );
}

require_once RCC_PLUGIN_DIR . 'includes/settings.php';
require_once RCC_PLUGIN_DIR . 'includes/known-domains.php';
require_once RCC_PLUGIN_DIR . 'includes/vendors.php';
require_once RCC_PLUGIN_DIR . 'includes/frontend.php';
require_once RCC_PLUGIN_DIR . 'includes/script-blocking.php';
require_once RCC_PLUGIN_DIR . 'includes/cookie-declaration.php';
require_once RCC_PLUGIN_DIR . 'includes/consent.php';
require_once RCC_PLUGIN_DIR . 'includes/cookie-registry.php';
require_once RCC_PLUGIN_DIR . 'includes/wp-consent-api.php';
require_once RCC_PLUGIN_DIR . 'includes/consent-log.php';
require_once RCC_PLUGIN_DIR . 'includes/stats.php';
require_once RCC_PLUGIN_DIR . 'includes/scanner.php';
require_once RCC_PLUGIN_DIR . 'includes/rest-config.php';
require_once RCC_PLUGIN_DIR . 'includes/admin-menu.php';
require_once RCC_PLUGIN_DIR . 'includes/admin-consent-log.php';
require_once RCC_PLUGIN_DIR . 'includes/admin-stats.php';
require_once RCC_PLUGIN_DIR . 'includes/admin-scanner.php';
require_once RCC_PLUGIN_DIR . 'includes/class-rcc-github-updater.php';

/**
 * Aktivering: se till att det finns sparade inställningar direkt så att
 * bannern fungerar (med tomma tag-ID:n) innan admin fyllt i något.
 */
function rcc_activate() {
	if ( false === get_option( RCC_OPTION_KEY ) ) {
		add_option( RCC_OPTION_KEY, rcc_default_settings() );
	}
	rcc_install_consent_log_table();
	rcc_schedule_consent_log_cleanup();
}
register_activation_hook( __FILE__, 'rcc_activate' );

/**
 * Avaktivering: stoppa gallringsjobbet. Tabellen och inställningarna
 * ligger kvar tills pluginet raderas (uninstall.php).
 */
function rcc_deactivate() {
	rcc_unschedule_consent_log_cleanup();
}
register_deactivation_hook( __FILE__, 'rcc_deactivate' );

/**
 * Snabblänk till inställningarna i plugin-listan.
 */
function rcc_plugin_action_links( $links ) {
	$url  = rcc_admin_page_url();
	$link = '<a href="' . esc_url( $url ) . '">Inställningar</a>';
	array_unshift( $links, $link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'rcc_plugin_action_links' );

/**
 * Starta GitHub-uppdateraren. Kan stängas av med
 * add_filter( 'rcc_enable_github_updates', '__return_false' );
 */
function rcc_init_updater() {
	if ( ! apply_filters( 'rcc_enable_github_updates', true ) ) {
		return;
	}
	new RCC_GitHub_Updater( RCC_PLUGIN_FILE, RCC_GITHUB_REPO, RCC_VERSION );
}
add_action( 'init', 'rcc_init_updater' );
