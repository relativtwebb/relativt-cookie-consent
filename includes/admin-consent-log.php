<?php
/**
 * Admin-sidan Inställningar → Samtyckeslogg: lista med filter och
 * sökning, CSV-export och tömning.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RCC_LOG_PAGE_SLUG', 'relativt-cookie-consent-log' );

function rcc_add_consent_log_page() {
	add_options_page(
		'Samtyckeslogg – Relativt Cookie Consent',
		'Samtyckeslogg',
		'manage_options',
		RCC_LOG_PAGE_SLUG,
		'rcc_render_consent_log_page'
	);
}
add_action( 'admin_menu', 'rcc_add_consent_log_page' );

function rcc_consent_log_admin_url( $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => RCC_LOG_PAGE_SLUG ), $args ), admin_url( 'options-general.php' ) );
}

function rcc_consent_log_admin_assets( $hook ) {
	if ( 'settings_page_' . RCC_LOG_PAGE_SLUG !== $hook ) {
		return;
	}
	wp_enqueue_style( 'rcc-admin', RCC_PLUGIN_URL . 'assets/css/admin.css', array(), RCC_VERSION );
}
add_action( 'admin_enqueue_scripts', 'rcc_consent_log_admin_assets' );

/**
 * Filter från query-strängen, sanerade.
 */
function rcc_consent_log_current_filters() {
	return array(
		'status' => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		'month'  => isset( $_GET['month'] ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		'search' => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	);
}

/**
 * Tidsstämpel i sajtens tidszon och datumformat.
 */
function rcc_consent_log_format_date( $gmt ) {
	$local = get_date_from_gmt( $gmt, 'Y-m-d H:i:s' );
	return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $local ) );
}

/* -------------------------------------------------------------------------
 * Listtabell
 * ---------------------------------------------------------------------- */

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class RCC_Consent_Log_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => 'samtycke',
			'plural'   => 'samtycken',
			'ajax'     => false,
		) );
	}

	public function get_columns() {
		return array(
			'consent_id'      => 'Samtyckes-ID',
			'created_at'      => 'Tidpunkt',
			'status'          => 'Status',
			'categories'      => 'Kategorier',
			'country'         => 'Land',
			'consent_version' => 'Version',
			'ip_hash'         => 'IP-hash',
		);
	}

	protected function get_sortable_columns() {
		return array(
			'created_at'      => array( 'created_at', true ),
			'status'          => array( 'status', false ),
			'country'         => array( 'country', false ),
			'consent_version' => array( 'consent_version', false ),
		);
	}

	public function prepare_items() {
		$per_page = 50;
		$page     = $this->get_pagenum();
		$filters  = rcc_consent_log_current_filters();

		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'created_at'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order   = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$total = rcc_consent_log_count( $filters );

		$this->items = rcc_consent_log_rows( $filters, $per_page, ( $page - 1 ) * $per_page, $orderby, $order );

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$this->set_pagination_args( array(
			'total_items' => $total,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total / $per_page ),
		) );
	}

	public function no_items() {
		echo 'Inga samtycken loggade ännu.';
	}

	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$filters = rcc_consent_log_current_filters();
		$labels  = rcc_consent_status_labels();
		$months  = rcc_consent_log_months();
		?>
		<div class="alignleft actions">
			<label for="rcc-filter-status" class="screen-reader-text">Filtrera på status</label>
			<select name="status" id="rcc-filter-status">
				<option value="">Alla statusar</option>
				<?php foreach ( $labels as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['status'], $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>

			<label for="rcc-filter-month" class="screen-reader-text">Filtrera på månad</label>
			<select name="month" id="rcc-filter-month">
				<option value="">Alla månader</option>
				<?php foreach ( $months as $month ) : ?>
					<option value="<?php echo esc_attr( $month ); ?>" <?php selected( $filters['month'], $month ); ?>><?php echo esc_html( date_i18n( 'F Y', strtotime( $month . '-01' ) ) ); ?></option>
				<?php endforeach; ?>
			</select>

			<?php submit_button( 'Filtrera', '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	protected function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
	}

	protected function column_consent_id( $item ) {
		$url = rcc_consent_log_admin_url( array( 's' => $item['consent_id'] ) );
		return '<code><a href="' . esc_url( $url ) . '" title="Visa alla val med detta ID">' . esc_html( $item['consent_id'] ) . '</a></code>';
	}

	protected function column_created_at( $item ) {
		return esc_html( rcc_consent_log_format_date( $item['created_at'] ) );
	}

	protected function column_status( $item ) {
		$labels = rcc_consent_status_labels();
		$label  = isset( $labels[ $item['status'] ] ) ? $labels[ $item['status'] ] : $item['status'];
		return '<span class="rcc-badge rcc-badge--' . esc_attr( $item['status'] ) . '">' . esc_html( $label ) . '</span>';
	}

	protected function column_categories( $item ) {
		$s     = rcc_get_settings();
		$parts = array( esc_html( $s['necessary_label'] ) );
		if ( ! empty( $item['statistics'] ) ) {
			$parts[] = esc_html( $s['statistics_label'] );
		}
		if ( ! empty( $item['marketing'] ) ) {
			$parts[] = esc_html( $s['marketing_label'] );
		}
		return implode( ', ', $parts );
	}

	protected function column_country( $item ) {
		return $item['country'] ? esc_html( $item['country'] ) : '<span class="rcc-muted">–</span>';
	}

	protected function column_consent_version( $item ) {
		return esc_html( $item['consent_version'] ) . ' <span class="rcc-muted">(plugin ' . esc_html( $item['plugin_version'] ) . ')</span>';
	}

	protected function column_ip_hash( $item ) {
		if ( ! $item['ip_hash'] ) {
			return '<span class="rcc-muted">–</span>';
		}
		return '<code title="' . esc_attr( $item['ip_hash'] ) . '">' . esc_html( substr( $item['ip_hash'], 0, 12 ) ) . '…</code>';
	}
}

/* -------------------------------------------------------------------------
 * Sidan
 * ---------------------------------------------------------------------- */

function rcc_render_consent_log_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$s       = rcc_get_settings();
	$filters = rcc_consent_log_current_filters();
	$table   = new RCC_Consent_Log_Table();
	$table->prepare_items();

	$total_all = rcc_consent_log_count();
	$export    = wp_nonce_url( admin_url( 'admin-post.php?action=rcc_export_consent_log&' . http_build_query( array_filter( $filters ) ) ), 'rcc_export_consent_log' );
	?>
	<div class="wrap rcc-settings rcc-consent-log">
		<h1 class="wp-heading-inline">Samtyckeslogg</h1>
		<a href="<?php echo esc_url( $export ); ?>" class="page-title-action">Exportera CSV<?php echo array_filter( $filters ) ? ' (filtrerat urval)' : ''; ?></a>
		<hr class="wp-header-end" />

		<?php if ( isset( $_GET['rcc_notice'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<?php if ( 'truncated' === $_GET['rcc_notice'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p>Loggen är tömd.</p></div>
			<?php elseif ( 'cleaned' === $_GET['rcc_notice'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p>Gallring utförd. <?php echo (int) ( $_GET['rcc_count'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?> poster äldre än <?php echo (int) $s['consent_log_retention_months']; ?> månader raderades.</p></div>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( ! rcc_consent_log_enabled() ) : ?>
			<div class="notice notice-warning"><p>Loggningen är avstängd. Nya val sparas inte förrän den slås på under <a href="<?php echo esc_url( admin_url( 'options-general.php?page=relativt-cookie-consent#rcc-tab-general' ) ); ?>">Cookie Consent → Allmänt</a>.</p></div>
		<?php endif; ?>

		<p>Varje val en besökare gör i cookie-rutan sparas här som bevis på att samtycke inhämtats. Besökaren ser sitt samtyckes-ID i cookie-inställningarna och kan uppge det vid en förfrågan – sök på ID:t för att se personens hela historik. Poster äldre än <strong><?php echo (int) $s['consent_log_retention_months']; ?> månader</strong> gallras automatiskt varje dygn. Totalt <strong><?php echo number_format_i18n( $total_all ); ?></strong> poster.</p>

		<form method="get">
			<input type="hidden" name="page" value="<?php echo esc_attr( RCC_LOG_PAGE_SLUG ); ?>" />
			<?php $table->search_box( 'Sök samtyckes-ID', 'rcc-consent' ); ?>
			<?php $table->display(); ?>
		</form>

		<h2 class="title">Underhåll</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rcc-inline-form">
			<?php wp_nonce_field( 'rcc_cleanup_consent_log' ); ?>
			<input type="hidden" name="action" value="rcc_cleanup_consent_log" />
			<?php submit_button( 'Gallra nu', 'secondary', 'submit', false ); ?>
			<span class="description">Kör samma gallring som det dagliga cron-jobbet direkt.</span>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rcc-inline-form" onsubmit="return window.confirm( 'Radera alla poster i samtyckesloggen? Det går inte att ångra.' );">
			<?php wp_nonce_field( 'rcc_truncate_consent_log' ); ?>
			<input type="hidden" name="action" value="rcc_truncate_consent_log" />
			<?php submit_button( 'Töm hela loggen', 'delete', 'submit', false ); ?>
			<span class="description">Raderar alla poster oavsett ålder. Exportera först om ni behöver behålla bevisen.</span>
		</form>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Åtgärder (admin-post.php)
 * ---------------------------------------------------------------------- */

function rcc_handle_export_consent_log() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Behörighet saknas.' );
	}
	check_admin_referer( 'rcc_export_consent_log' );

	$s       = rcc_get_settings();
	$filters = rcc_consent_log_current_filters();
	$labels  = rcc_consent_status_labels();
	$rows    = rcc_consent_log_rows( $filters, 0 );

	$filename = sanitize_file_name( 'samtyckeslogg-' . wp_parse_url( home_url(), PHP_URL_HOST ) . '-' . gmdate( 'Y-m-d' ) . '.csv' );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" ); // BOM så att Excel läser åäö rätt.

	fputcsv( $out, array( 'Samtyckes-ID', 'Tidpunkt (' . wp_timezone_string() . ')', 'Tidpunkt (UTC)', 'Status', $s['statistics_label'], $s['marketing_label'], 'Land', 'Samtyckesversion', 'Plugin-version', 'IP-hash', 'User agent' ), ';' );

	foreach ( $rows as $row ) {
		fputcsv( $out, array(
			$row['consent_id'],
			get_date_from_gmt( $row['created_at'], 'Y-m-d H:i:s' ),
			$row['created_at'],
			isset( $labels[ $row['status'] ] ) ? $labels[ $row['status'] ] : $row['status'],
			$row['statistics'] ? 'Ja' : 'Nej',
			$row['marketing'] ? 'Ja' : 'Nej',
			$row['country'],
			$row['consent_version'],
			$row['plugin_version'],
			$row['ip_hash'],
			$row['user_agent'],
		), ';' );
	}

	fclose( $out );
	exit;
}
add_action( 'admin_post_rcc_export_consent_log', 'rcc_handle_export_consent_log' );

function rcc_handle_cleanup_consent_log() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Behörighet saknas.' );
	}
	check_admin_referer( 'rcc_cleanup_consent_log' );

	$count = rcc_consent_log_cleanup();

	wp_safe_redirect( rcc_consent_log_admin_url( array( 'rcc_notice' => 'cleaned', 'rcc_count' => $count ) ) );
	exit;
}
add_action( 'admin_post_rcc_cleanup_consent_log', 'rcc_handle_cleanup_consent_log' );

function rcc_handle_truncate_consent_log() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Behörighet saknas.' );
	}
	check_admin_referer( 'rcc_truncate_consent_log' );

	rcc_consent_log_truncate();

	wp_safe_redirect( rcc_consent_log_admin_url( array( 'rcc_notice' => 'truncated' ) ) );
	exit;
}
add_action( 'admin_post_rcc_truncate_consent_log', 'rcc_handle_truncate_consent_log' );
