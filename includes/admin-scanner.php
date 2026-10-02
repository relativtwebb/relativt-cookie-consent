<?php
/**
 * Admin-sidan Cookie Consent → Skanner: kör skanningen och visar
 * resultatet per domän, med knappar för att lägga till en domän i
 * blocklistan.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function rcc_scanner_admin_assets( $hook ) {
	if ( ! rcc_is_admin_page( $hook, RCC_SCANNER_SLUG ) ) {
		return;
	}
	wp_enqueue_style( 'rcc-admin', RCC_PLUGIN_URL . 'assets/css/admin.css', array(), RCC_VERSION );
	wp_enqueue_script( 'rcc-admin-scanner', RCC_PLUGIN_URL . 'assets/js/admin-scanner.js', array(), RCC_VERSION, true );
	wp_localize_script( 'rcc-admin-scanner', 'rccScanner', array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'rcc_scan' ),
		'i18n'    => array(
			'starting' => 'Hämtar sidlista …',
			'scanning' => 'Skannar %1$d av %2$d: %3$s',
			'done'     => 'Klart. Laddar resultatet …',
			'failed'   => 'Skanningen avbröts: %s',
			'skipped'  => 'Hoppade över adresser som inte hör till sajten: %s',
		),
	) );
}
add_action( 'admin_enqueue_scripts', 'rcc_scanner_admin_assets' );

function rcc_scanner_category_labels() {
	$s = rcc_get_settings();
	return array(
		'statistics' => $s['statistics_label'],
		'marketing'  => $s['marketing_label'],
	);
}

/**
 * Status och rekommendation för en värd i resultatet.
 */
function rcc_scanner_assess( $key, $data, $blocked_domains ) {
	list( $host, $key_path ) = rcc_split_domain_entry( $key );
	$path       = '' !== $key_path ? $key_path : ( isset( $data['path'] ) ? $data['path'] : '' );
	$known      = rcc_known_domain_for( $host, $path );
	$suggestion = $known ? $known['category'] : '';
	$listed     = rcc_category_for_host( $host, $blocked_domains, $path );

	if ( ! empty( $data['unblocked'] ) ) {
		if ( 'necessary' === $suggestion ) {
			$status = 'ok-necessary';
		} elseif ( 'none' === $suggestion ) {
			$status = 'ok-none';
		} elseif ( 'conflict' === $suggestion ) {
			$status = 'conflict';
		} else {
			$status = 'unblocked';
		}
	} else {
		$status = 'blocked';
	}

	return array(
		'name'       => $known ? $known['name'] : '',
		'suggestion' => $suggestion,
		'listed'     => $listed,
		'status'     => $status,
	);
}

function rcc_render_scanner_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$s        = rcc_get_settings();
	$results  = rcc_scan_results();
	$labels   = rcc_scanner_category_labels();
	$blocked  = rcc_blocked_domains( $s );
	$hosts    = isset( $results['hosts'] ) ? $results['hosts'] : array();
	$finished = ! empty( $results['finished_at'] );

	$rows = array();
	foreach ( $hosts as $host => $data ) {
		$rows[ $host ] = array_merge( $data, rcc_scanner_assess( $host, $data, $blocked ) );
	}
	// Det som behöver åtgärdas först, sedan blockerat, sist det ofarliga.
	$rank = array( 'conflict' => 0, 'unblocked' => 1, 'blocked' => 2, 'ok-necessary' => 3, 'ok-none' => 4 );
	uksort( $rows, function ( $a, $b ) use ( $rows, $rank ) {
		$cmp = $rank[ $rows[ $a ]['status'] ] <=> $rank[ $rows[ $b ]['status'] ];
		return $cmp ? $cmp : strcmp( $a, $b );
	} );
	$unblocked = count( array_filter( $rows, function ( $r ) {
		return 'unblocked' === $r['status'] || 'conflict' === $r['status'];
	} ) );
	?>
	<div class="wrap rcc-settings rcc-scanner">
		<h1 class="wp-heading-inline">Skanner</h1>
		<hr class="wp-header-end" />

		<?php rcc_scanner_notices(); ?>

		<p>Skannern hämtar startsidan och ett urval av sajtens sidor som en anonym besökare och listar alla skript och inbäddningar från andra domäner, och om pluginet blockerar dem. Det som inte blockeras körs utan samtycke. Lägg till sådana domäner i blocklistan med knapparna nedan, eller under <a href="<?php echo esc_url( rcc_admin_page_url() . '#rcc-tab-video' ); ?>">Inställningar → Blockering</a>.</p>
		<p class="description">Skannern ser den HTML som skickas till besökaren. Skript som laddas av annan JavaScript efteråt, till exempel taggar i Google Tag Manager, syns inte här. Kontrollera alltid också i webbläsarens utvecklarverktyg (Application → Cookies) innan lansering.</p>

		<div class="rcc-card rcc-scan-form">
			<p><label for="rcc-scan-extra"><strong>Fler adresser att skanna</strong> (valfritt, en per rad, t.ex. <code>/kontakt/</code>)</label></p>
			<textarea id="rcc-scan-extra" rows="3" class="large-text code"></textarea>
			<p>
				<button type="button" class="button button-primary" id="rcc-scan-start">Skanna sajten</button>
				<?php if ( ! empty( $results['started_at'] ) ) : ?>
					<span class="description">Senaste skanning: <?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $results['started_at'] ) ); ?><?php echo $finished ? '' : ' (avbröts innan den var klar)'; ?>, <?php echo (int) count( isset( $results['pages'] ) ? $results['pages'] : array() ); ?> sidor.</span>
				<?php endif; ?>
			</p>
			<div id="rcc-scan-progress" class="rcc-scan-progress" aria-live="polite" hidden>
				<progress id="rcc-scan-bar" max="100" value="0"></progress>
				<p id="rcc-scan-status"></p>
			</div>
		</div>

		<?php if ( ! empty( $results['started_at'] ) ) : ?>
			<?php if ( $rows ) : ?>
				<h2><?php echo $unblocked ? esc_html( sprintf( 1 === $unblocked ? '%d domän behöver åtgärdas' : '%d domäner behöver åtgärdas', $unblocked ) ) : 'Inget oblockerat hittades'; ?></h2>
				<table class="widefat striped rcc-scan-table">
					<thead>
						<tr>
							<th scope="col">Domän</th>
							<th scope="col">Typ</th>
							<th scope="col">Hittad på</th>
							<th scope="col">Status</th>
							<th scope="col">Åtgärd</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $host => $row ) : ?>
							<?php rcc_render_scanner_row( $host, $row, $labels ); ?>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p>Inga skript eller inbäddningar från andra domäner hittades på de skannade sidorna.</p>
			<?php endif; ?>

			<?php
			$errors = array_filter( isset( $results['pages'] ) ? $results['pages'] : array(), function ( $p ) {
				return ! empty( $p['error'] );
			} );
			?>
			<details class="rcc-scan-pages">
				<summary>Skannade sidor (<?php echo (int) count( $results['pages'] ); ?><?php echo $errors ? ', ' . (int) count( $errors ) . ' med fel' : ''; ?>)</summary>
				<ul>
					<?php foreach ( $results['pages'] as $url => $page ) : ?>
						<li><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $url ); ?></a>
							<?php if ( $page['error'] ) : ?>
								– <span class="rcc-error"><?php echo esc_html( $page['error'] ); ?></span>
							<?php else : ?>
								– <?php echo (int) $page['count']; ?> fynd
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</details>
		<?php endif; ?>
	</div>
	<?php
}

function rcc_render_scanner_row( $host, $row, $labels ) {
	$type_labels = array(
		'script' => 'Skript',
		'inline' => 'Inline-skript',
		'iframe' => 'Inbäddning',
	);
	$types = array();
	foreach ( array_keys( $row['types'] ) as $type ) {
		$types[] = isset( $type_labels[ $type ] ) ? $type_labels[ $type ] : $type;
	}
	$pages = array_keys( $row['pages'] );
	?>
	<tr class="rcc-scan-row rcc-scan-row--<?php echo esc_attr( $row['status'] ); ?>">
		<td>
			<strong><?php echo esc_html( $host ); ?></strong>
			<?php if ( $row['name'] ) : ?>
				<br /><span class="rcc-muted"><?php echo esc_html( $row['name'] ); ?></span>
			<?php endif; ?>
			<?php if ( $row['samples'] ) : ?>
				<details class="rcc-samples"><summary>Exempel</summary>
					<?php foreach ( $row['samples'] as $sample ) : ?>
						<code><?php echo esc_html( $sample ); ?></code>
					<?php endforeach; ?>
				</details>
			<?php endif; ?>
		</td>
		<td><?php echo esc_html( implode( ', ', $types ) ); ?></td>
		<td>
			<?php echo esc_html( sprintf( 1 === count( $pages ) ? '%d sida' : '%d sidor', count( $pages ) ) ); ?>
			<details class="rcc-samples"><summary>Visa</summary>
				<?php foreach ( $pages as $url ) : ?>
					<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_make_link_relative( $url ) ); ?></a><br />
				<?php endforeach; ?>
			</details>
		</td>
		<td>
			<?php
			switch ( $row['status'] ) {
				case 'blocked':
					$cats = array();
					foreach ( array_keys( $row['blocked'] ) as $cat_string ) {
						foreach ( preg_split( '/\s+/', $cat_string ) as $cat ) {
							if ( isset( $labels[ $cat ] ) ) {
								$cats[ $labels[ $cat ] ] = true;
							}
						}
					}
					echo '<span class="rcc-badge rcc-badge--ok">Blockeras</span> <span class="rcc-muted">' . esc_html( implode( ' / ', array_keys( $cats ) ) ) . '</span>';
					break;
				case 'unblocked':
					echo '<span class="rcc-badge rcc-badge--warn">Blockeras inte</span>';
					if ( $row['listed'] ) {
						echo '<br /><span class="rcc-muted">Står i blocklistan men laddades ändå, troligen via annan JavaScript.</span>';
					}
					break;
				case 'conflict':
					echo '<span class="rcc-badge rcc-badge--warn">Annan cookie-lösning</span>';
					break;
				case 'ok-necessary':
					echo '<span class="rcc-badge rcc-badge--neutral">Nödvändig</span>';
					break;
				default:
					echo '<span class="rcc-badge rcc-badge--neutral">Sätter normalt inga cookies</span>';
			}
			?>
		</td>
		<td>
			<?php if ( 'unblocked' === $row['status'] && ! $row['listed'] ) : ?>
				<?php
				$order = array( 'statistics', 'marketing' );
				if ( 'marketing' === $row['suggestion'] ) {
					$order = array( 'marketing', 'statistics' );
				}
				foreach ( $order as $i => $cat ) {
					$primary = $row['suggestion'] ? ( $cat === $row['suggestion'] ) : false;
					?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rcc-inline-form">
						<?php wp_nonce_field( 'rcc_scan_block_domain' ); ?>
						<input type="hidden" name="action" value="rcc_scan_block_domain" />
						<input type="hidden" name="domain" value="<?php echo esc_attr( $host ); ?>" />
						<input type="hidden" name="category" value="<?php echo esc_attr( $cat ); ?>" />
						<button type="submit" class="button <?php echo $primary ? 'button-primary' : ''; ?>">Blockera som <?php echo esc_html( strtolower( $labels[ $cat ] ) ); ?></button>
					</form>
					<?php
				}
				if ( $row['suggestion'] && isset( $labels[ $row['suggestion'] ] ) ) {
					echo '<p class="rcc-muted">Förslag: ' . esc_html( strtolower( $labels[ $row['suggestion'] ] ) ) . '.</p>';
				}
				?>
			<?php elseif ( 'conflict' === $row['status'] ) : ?>
				<span class="rcc-muted">Ta bort den andra lösningen så att besökarna inte får två cookie-rutor.</span>
			<?php elseif ( 'ok-necessary' === $row['status'] ) : ?>
				<span class="rcc-muted">Behövs för att sajten ska fungera. Blockera inte.</span>
			<?php elseif ( 'ok-none' === $row['status'] ) : ?>
				<span class="rcc-muted">Behöver normalt inte blockeras.</span>
			<?php else : ?>
				<span class="rcc-muted">–</span>
			<?php endif; ?>
		</td>
	</tr>
	<?php
}

function rcc_scanner_notices() {
	if ( empty( $_GET['rcc_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$notice = sanitize_key( wp_unslash( $_GET['rcc_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$domain = isset( $_GET['domain'] ) ? sanitize_text_field( wp_unslash( $_GET['domain'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'blocked' === $notice ) {
		?>
		<div class="notice notice-success is-dismissible">
			<p><strong><?php echo esc_html( $domain ); ?></strong> blockeras nu tills besökaren samtyckt. Skanna igen för att se resultatet.</p>
			<p>Besökare som redan gjort ett val samtyckte innan domänen fanns med. Höj gärna <a href="<?php echo esc_url( rcc_admin_page_url() . '#rcc-tab-general' ); ?>">samtyckesversionen</a> så att alla tar ställning på nytt.</p>
		</div>
		<?php
	} elseif ( 'invalid' === $notice ) {
		echo '<div class="notice notice-error is-dismissible"><p>Domänen kunde inte läggas till.</p></div>';
	}
}

/**
 * Lägger till en domän i blocklistan för en kategori.
 */
function rcc_handle_scan_block_domain() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Behörighet saknas.' );
	}
	check_admin_referer( 'rcc_scan_block_domain' );

	$domain   = isset( $_POST['domain'] ) ? rcc_normalize_domain( sanitize_text_field( wp_unslash( $_POST['domain'] ) ) ) : '';
	$category = isset( $_POST['category'] ) ? sanitize_key( wp_unslash( $_POST['category'] ) ) : '';

	if ( ! $domain || ! in_array( $category, array( 'statistics', 'marketing' ), true ) ) {
		wp_safe_redirect( rcc_admin_page_url( RCC_SCANNER_SLUG, array( 'rcc_notice' => 'invalid' ) ) );
		exit;
	}

	$saved = get_option( RCC_OPTION_KEY, array() );
	$saved = wp_parse_args( is_array( $saved ) ? $saved : array(), rcc_default_settings() );
	$key   = 'blocked_domains_' . $category;
	$list  = rcc_parse_domain_list( $saved[ $key ] );
	if ( ! in_array( $domain, $list, true ) ) {
		$list[] = $domain;
	}
	$saved[ $key ] = implode( "\n", $list );

	// Går genom samma sanering som formuläret (register_setting).
	update_option( RCC_OPTION_KEY, $saved );

	wp_safe_redirect( rcc_admin_page_url( RCC_SCANNER_SLUG, array( 'rcc_notice' => 'blocked', 'domain' => $domain ) ) );
	exit;
}
add_action( 'admin_post_rcc_scan_block_domain', 'rcc_handle_scan_block_domain' );
