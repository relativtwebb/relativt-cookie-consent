<?php
/**
 * Lättviktig uppdaterare mot GitHub Releases.
 *
 * Hakar in i WordPress inbyggda uppdateringskontroll via filtret
 * update_plugins_{hostname} (WP 5.8+), som körs för plugin med en
 * "Update URI"-rad i huvudet. Nya versioner visas då under Uppdateringar
 * i WP-admin precis som för plugin från wordpress.org, inklusive
 * "Visa detaljer" med changelog från releasen.
 *
 * Så här släpps en ny version:
 *   1. Höj versionsnumret i plugin-huvudet och i RCC_VERSION.
 *   2. Tagga commit:en (v1.2.0) och pusha taggen.
 *   3. GitHub Actions bygger en zip och skapar en release med den som
 *      bilaga. Uppdateraren föredrar bilagan relativt-cookie-consent.zip
 *      och faller annars tillbaka på GitHubs automatiska källkods-zip.
 *
 * Privata repon: definiera RCC_GITHUB_TOKEN i wp-config.php (en
 * fine-grained token med läsrättighet till Contents räcker).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RCC_GitHub_Updater {

	const CACHE_KEY     = 'rcc_github_release';
	const CACHE_TTL     = 6 * HOUR_IN_SECONDS;
	const FAIL_TTL      = HOUR_IN_SECONDS;
	const CHECK_ACTION  = 'rcc_check_update';

	/** @var string Absolut sökväg till huvudfilen. */
	private $plugin_file;

	/** @var string T.ex. relativt-cookie-consent/relativt-cookie-consent.php */
	private $basename;

	/** @var string T.ex. relativt-cookie-consent */
	private $slug;

	/** @var string ägare/repo */
	private $repo;

	/** @var string Installerad version. */
	private $version;

	/** @var string Release-bilagan som föredras, t.ex. relativt-cookie-consent.zip */
	private $asset_name;

	public function __construct( $plugin_file, $repo, $version ) {
		$this->plugin_file = $plugin_file;
		$this->basename    = plugin_basename( $plugin_file );
		$this->slug        = dirname( $this->basename );
		$this->repo        = trim( $repo, '/' );
		$this->version     = $version;
		$this->asset_name  = basename( $plugin_file, '.php' ) . '.zip';

		if ( empty( $this->repo ) || false === strpos( $this->repo, '/' ) ) {
			return;
		}

		add_filter( 'update_plugins_github.com', array( $this, 'check_for_update' ), 10, 3 );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_pre_download', array( $this, 'pre_download' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_directory_name' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( $this, 'purge_cache_after_update' ), 10, 2 );

		add_filter( 'plugin_action_links_' . $this->basename, array( $this, 'action_links' ) );
		add_action( 'admin_init', array( $this, 'handle_manual_check' ) );
		add_action( 'admin_notices', array( $this, 'manual_check_notice' ) );
	}

	/* ---------------------------------------------------------------
	 * GitHub API
	 * ------------------------------------------------------------ */

	private function token() {
		$token = defined( 'RCC_GITHUB_TOKEN' ) ? RCC_GITHUB_TOKEN : '';
		return (string) apply_filters( 'rcc_github_token', $token );
	}

	private function request_headers( $accept = 'application/vnd.github+json' ) {
		$headers = array(
			'Accept'               => $accept,
			'X-GitHub-Api-Version' => '2022-11-28',
			'User-Agent'           => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url( '/' ),
		);
		$token = $this->token();
		if ( $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}
		return $headers;
	}

	/**
	 * Senaste releasen som en normaliserad array, eller null. Cachas i
	 * sex timmar; misslyckade anrop cachas en timme så att GitHub inte
	 * belastas i onödan (och admin inte blir seg) vid nätverksfel.
	 */
	public function get_release( $force = false ) {
		if ( ! $force ) {
			$cached = get_site_transient( self::CACHE_KEY );
			if ( 'failed' === $cached ) {
				return null;
			}
			if ( is_array( $cached ) && ! empty( $cached['version'] ) ) {
				return $cached;
			}
		}

		$url      = 'https://api.github.com/repos/' . $this->repo . '/releases/latest';
		$response = wp_remote_get( $url, array(
			'timeout' => 10,
			'headers' => $this->request_headers(),
		) );

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			set_site_transient( self::CACHE_KEY, 'failed', self::FAIL_TTL );
			return null;
		}

		$data    = json_decode( wp_remote_retrieve_body( $response ), true );
		$release = $this->normalize_release( $data );

		if ( ! $release ) {
			set_site_transient( self::CACHE_KEY, 'failed', self::FAIL_TTL );
			return null;
		}

		set_site_transient( self::CACHE_KEY, $release, self::CACHE_TTL );
		return $release;
	}

	/**
	 * Plockar ut det vi behöver ur GitHubs release-objekt.
	 */
	public function normalize_release( $data ) {
		if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
			return null;
		}
		if ( ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) ) {
			return null;
		}

		$version = ltrim( trim( $data['tag_name'] ), 'vV' );
		if ( ! preg_match( '/^\d+(\.\d+)*/', $version ) ) {
			return null;
		}

		$download_url  = '';
		$asset_api_url = '';
		$assets        = ( ! empty( $data['assets'] ) && is_array( $data['assets'] ) ) ? $data['assets'] : array();

		// Föredra bilagan med pluginets eget namn, annars första zip-bilagan.
		$chosen = null;
		foreach ( $assets as $asset ) {
			if ( isset( $asset['name'] ) && $asset['name'] === $this->asset_name ) {
				$chosen = $asset;
				break;
			}
		}
		if ( ! $chosen ) {
			foreach ( $assets as $asset ) {
				if ( isset( $asset['name'] ) && preg_match( '/\.zip$/i', $asset['name'] ) ) {
					$chosen = $asset;
					break;
				}
			}
		}

		if ( $chosen ) {
			$download_url  = isset( $chosen['browser_download_url'] ) ? $chosen['browser_download_url'] : '';
			$asset_api_url = isset( $chosen['url'] ) ? $chosen['url'] : '';
		}
		if ( ! $download_url && ! empty( $data['zipball_url'] ) ) {
			$download_url = $data['zipball_url'];
		}
		if ( ! $download_url ) {
			return null;
		}

		// Privata repon måste gå via API:et (med token), inte via
		// browser_download_url.
		if ( $this->token() && $asset_api_url ) {
			$download_url = $asset_api_url;
		}

		return array(
			'version'      => $version,
			'download_url' => $download_url,
			'html_url'     => ! empty( $data['html_url'] ) ? $data['html_url'] : 'https://github.com/' . $this->repo . '/releases',
			'changelog'    => isset( $data['body'] ) ? (string) $data['body'] : '',
			'published_at' => ! empty( $data['published_at'] ) ? $data['published_at'] : '',
			'name'         => ! empty( $data['name'] ) ? $data['name'] : $data['tag_name'],
		);
	}

	public function purge_cache() {
		delete_site_transient( self::CACHE_KEY );
	}

	/* ---------------------------------------------------------------
	 * WordPress uppdateringskontroll
	 * ------------------------------------------------------------ */

	/**
	 * Körs av wp_update_plugins() för varje plugin vars Update URI
	 * pekar på github.com. Returnerar releasedata för vårt plugin.
	 */
	public function check_for_update( $update, $plugin_data, $plugin_file ) {
		if ( $plugin_file !== $this->basename ) {
			return $update;
		}

		$release = $this->get_release();
		if ( ! $release ) {
			return $update;
		}

		return array(
			'id'           => 'https://github.com/' . $this->repo,
			'slug'         => $this->slug,
			'plugin'       => $this->basename,
			'version'      => $release['version'],
			'new_version'  => $release['version'],
			'url'          => 'https://github.com/' . $this->repo,
			'package'      => $release['download_url'],
			'requires'     => isset( $plugin_data['RequiresWP'] ) ? $plugin_data['RequiresWP'] : '',
			'requires_php' => isset( $plugin_data['RequiresPHP'] ) ? $plugin_data['RequiresPHP'] : '',
			'icons'        => array(),
			'banners'      => array(),
			'banners_rtl'  => array(),
		);
	}

	/**
	 * "Visa detaljer"-rutan i plugin-listan.
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->slug ) {
			return $result;
		}

		$release = $this->get_release();
		if ( ! $release ) {
			return $result;
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugin_data = get_plugin_data( $this->plugin_file, false, false );

		$changelog = trim( $release['changelog'] )
			? $this->markdown_to_html( $release['changelog'] )
			: '<p>Se <a href="' . esc_url( $release['html_url'] ) . '">releasen på GitHub</a>.</p>';

		return (object) array(
			'name'          => $plugin_data['Name'],
			'slug'          => $this->slug,
			'version'       => $release['version'],
			'author'        => $plugin_data['AuthorURI'] ? '<a href="' . esc_url( $plugin_data['AuthorURI'] ) . '">' . esc_html( $plugin_data['Author'] ) . '</a>' : esc_html( $plugin_data['Author'] ),
			'homepage'      => 'https://github.com/' . $this->repo,
			'requires'      => $plugin_data['RequiresWP'],
			'requires_php'  => $plugin_data['RequiresPHP'],
			'last_updated'  => $release['published_at'],
			'download_link' => $release['download_url'],
			'sections'      => array(
				'description' => wpautop( esc_html( $plugin_data['Description'] ) ),
				'changelog'   => '<h4>' . esc_html( $release['name'] ) . '</h4>' . $changelog,
			),
		);
	}

	/* ---------------------------------------------------------------
	 * Nedladdning och installation
	 * ------------------------------------------------------------ */

	private function is_our_package( $package ) {
		if ( ! is_string( $package ) || '' === $package ) {
			return false;
		}
		$host = wp_parse_url( $package, PHP_URL_HOST );
		if ( ! in_array( $host, array( 'api.github.com', 'github.com', 'codeload.github.com' ), true ) ) {
			return false;
		}
		return false !== stripos( $package, '/' . $this->repo . '/' );
	}

	/**
	 * Privata repon: GitHub svarar med en omdirigering till en signerad
	 * lagrings-URL som inte får ta emot Authorization-huvudet. Därför
	 * görs första anropet utan att följa omdirigeringar, varefter själva
	 * filen hämtas från platsen GitHub pekar ut. Publika repon lämnas
	 * till WordPress vanliga nedladdning.
	 */
	public function pre_download( $reply, $package, $upgrader ) {
		if ( false !== $reply || ! $this->token() || ! $this->is_our_package( $package ) ) {
			return $reply;
		}

		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$response = wp_remote_get( $package, array(
			'timeout'     => 30,
			'redirection' => 0,
			'headers'     => $this->request_headers( 'application/octet-stream' ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( in_array( $code, array( 301, 302, 303, 307, 308 ), true ) ) {
			$location = wp_remote_retrieve_header( $response, 'location' );
			if ( empty( $location ) ) {
				return new WP_Error( 'rcc_no_redirect', 'GitHub svarade med en omdirigering utan adress.' );
			}
			return download_url( $location, 300 );
		}

		if ( 200 === $code ) {
			$tmp = wp_tempnam( $this->slug . '.zip' );
			if ( ! $tmp ) {
				return new WP_Error( 'rcc_tmp_failed', 'Kunde inte skapa en temporär fil för nedladdningen.' );
			}
			$written = file_put_contents( $tmp, wp_remote_retrieve_body( $response ) ); // phpcs:ignore -- samma metod som WP:s egen download_url().
			if ( false === $written ) {
				@unlink( $tmp ); // phpcs:ignore
				return new WP_Error( 'rcc_write_failed', 'Kunde inte skriva nedladdningen till disk.' );
			}
			return $tmp;
		}

		return new WP_Error( 'rcc_download_failed', sprintf( 'GitHub svarade med HTTP %d vid nedladdning av uppdateringen.', $code ) );
	}

	/**
	 * GitHubs automatiska källkods-zip packas upp i en mapp som heter
	 * ägare-repo-hash. Döp om till pluginets mappnamn så att WordPress
	 * uppdaterar på plats i stället för att installera en kopia.
	 */
	public function fix_directory_name( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		if ( ! is_array( $hook_extra ) || empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename ) {
			return $source;
		}

		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			return $source;
		}

		$expected = trailingslashit( $remote_source ) . dirname( $this->basename ) . '/';
		if ( untrailingslashit( $source ) === untrailingslashit( $expected ) ) {
			return $source;
		}

		if ( $wp_filesystem->move( $source, $expected, true ) ) {
			return $expected;
		}

		return new WP_Error( 'rcc_rename_failed', 'Kunde inte döpa om uppdateringens mapp till pluginets mappnamn.' );
	}

	public function purge_cache_after_update( $upgrader, $hook_extra ) {
		if ( isset( $hook_extra['type'] ) && 'plugin' === $hook_extra['type'] ) {
			$this->purge_cache();
		}
	}

	/* ---------------------------------------------------------------
	 * "Sök efter uppdatering" i plugin-listan
	 * ------------------------------------------------------------ */

	public function action_links( $links ) {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return $links;
		}
		$url     = wp_nonce_url( add_query_arg( self::CHECK_ACTION, '1', self_admin_url( 'plugins.php' ) ), self::CHECK_ACTION );
		$links[] = '<a href="' . esc_url( $url ) . '">Sök efter uppdatering</a>';
		return $links;
	}

	public function handle_manual_check() {
		if ( empty( $_GET[ self::CHECK_ACTION ] ) ) {
			return;
		}
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}
		check_admin_referer( self::CHECK_ACTION );

		$this->purge_cache();
		$release = $this->get_release( true );

		// Tvinga WordPress att köra om sin uppdateringskontroll direkt.
		wp_clean_plugins_cache( true );

		$status = ! $release ? 'error' : ( version_compare( $release['version'], $this->version, '>' ) ? 'available' : 'latest' );

		wp_safe_redirect( add_query_arg( 'rcc_update_status', $status, self_admin_url( 'plugins.php' ) ) );
		exit;
	}

	public function manual_check_notice() {
		if ( empty( $_GET['rcc_update_status'] ) ) {
			return;
		}
		$status = sanitize_key( $_GET['rcc_update_status'] );

		if ( 'available' === $status ) {
			$release = $this->get_release();
			$version = $release ? $release['version'] : '';
			echo '<div class="notice notice-info is-dismissible"><p><strong>Relativt Cookie Consent:</strong> version ' . esc_html( $version ) . ' finns på GitHub och kan installeras nedan.</p></div>';
		} elseif ( 'latest' === $status ) {
			echo '<div class="notice notice-success is-dismissible"><p><strong>Relativt Cookie Consent:</strong> du kör redan den senaste versionen (' . esc_html( $this->version ) . ').</p></div>';
		} else {
			echo '<div class="notice notice-error is-dismissible"><p><strong>Relativt Cookie Consent:</strong> kunde inte hämta releaser från GitHub-repot <code>' . esc_html( $this->repo ) . '</code>. Kontrollera att repot finns, att det har minst en publicerad release och, om repot är privat, att <code>RCC_GITHUB_TOKEN</code> är satt i wp-config.php.</p></div>';
		}
	}

	/* ---------------------------------------------------------------
	 * Hjälpfunktioner
	 * ------------------------------------------------------------ */

	/**
	 * Minimal Markdown-till-HTML för release-texter: rubriker, listor,
	 * fetstil, kod, länkar och stycken. All text escapas först.
	 */
	public function markdown_to_html( $markdown ) {
		$lines  = preg_split( '/\r\n|\r|\n/', (string) $markdown );
		$html   = '';
		$in_ul  = false;
		$para   = array();

		$flush_para = function () use ( &$para, &$html ) {
			if ( $para ) {
				$html .= '<p>' . implode( '<br />', $para ) . '</p>';
				$para  = array();
			}
		};
		$close_ul = function () use ( &$in_ul, &$html ) {
			if ( $in_ul ) {
				$html .= '</ul>';
				$in_ul = false;
			}
		};

		foreach ( $lines as $line ) {
			$trimmed = trim( $line );

			if ( '' === $trimmed ) {
				$flush_para();
				$close_ul();
				continue;
			}

			if ( preg_match( '/^(#{1,6})\s+(.*)$/', $trimmed, $m ) ) {
				$flush_para();
				$close_ul();
				$level = min( 6, strlen( $m[1] ) + 3 );
				$html .= '<h' . $level . '>' . $this->inline_markdown( $m[2] ) . '</h' . $level . '>';
				continue;
			}

			if ( preg_match( '/^[-*+]\s+(.*)$/', $trimmed, $m ) ) {
				$flush_para();
				if ( ! $in_ul ) {
					$html .= '<ul>';
					$in_ul = true;
				}
				$html .= '<li>' . $this->inline_markdown( $m[1] ) . '</li>';
				continue;
			}

			$close_ul();
			$para[] = $this->inline_markdown( $trimmed );
		}

		$flush_para();
		$close_ul();

		return $html;
	}

	private function inline_markdown( $text ) {
		$text = esc_html( $text );
		$text = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $text );
		$text = preg_replace( '/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text );
		$text = preg_replace( '/(?<![\w*])\*([^*\s][^*]*?)\*(?![\w*])/', '<em>$1</em>', $text );
		$text = preg_replace_callback( '/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/', function ( $m ) {
			return '<a href="' . esc_url( $m[2] ) . '" target="_blank" rel="noopener">' . $m[1] . '</a>';
		}, $text );
		return $text;
	}
}
