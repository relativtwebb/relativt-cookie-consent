<?php
/**
 * Admin-sidan Cookie Consent → Statistik och widgeten på WP-panelen.
 * Diagrammen är ren SVG som byggs här i PHP, utan bibliotek. Varje
 * stapel och punkt har en <title> som visas vid hovring, och alla siffror
 * finns också i en tabell under diagrammen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Färger per status. Samma tre nyanser används i diagrammen och i
 * loggens etiketter, så att "Accepterat" alltid är blått osv.
 */
function rcc_stats_colors() {
	return array(
		'accepted' => '#2a78d6',
		'rejected' => '#eb6834',
		'partial'  => '#1baf7a',
		'views'    => '#4a3aa7',
	);
}

function rcc_stats_admin_assets( $hook ) {
	if ( ! rcc_is_admin_page( $hook, RCC_STATS_SLUG ) && 'index.php' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'rcc-admin', RCC_PLUGIN_URL . 'assets/css/admin.css', array(), RCC_VERSION );
}
add_action( 'admin_enqueue_scripts', 'rcc_stats_admin_assets' );

/* -------------------------------------------------------------------------
 * Formatering
 * ---------------------------------------------------------------------- */

function rcc_stats_format_percent( $value ) {
	return null === $value ? '–' : number_format_i18n( $value, 1 ) . ' %';
}

function rcc_stats_short_date( $ymd ) {
	return date_i18n( 'j M', strtotime( $ymd . ' 12:00:00' ) );
}

/**
 * Ett "jämnt" tak för y-axeln (1, 2, 5, 10, 20, 25, 50 …) och steget mellan
 * stödlinjerna.
 */
function rcc_stats_nice_max( $max, $ticks = 4 ) {
	$max = max( 1, (int) ceil( $max ) );
	$raw = $max / $ticks;
	$pow = pow( 10, floor( log10( $raw ) ) );
	// 2,5 bara när steget blir ett heltal (25, 250 …).
	$multipliers = $pow >= 10 ? array( 1, 2, 2.5, 5, 10 ) : array( 1, 2, 5, 10 );
	foreach ( $multipliers as $m ) {
		$step = $m * $pow;
		if ( $step >= $raw ) {
			break;
		}
	}
	$step = max( 1, (int) ceil( $step ) );
	return array( $step * $ticks, $step );
}

/* -------------------------------------------------------------------------
 * SVG
 * ---------------------------------------------------------------------- */

/**
 * Munkdiagram. $segments: array( array( label, value, color ), … ).
 */
function rcc_svg_donut( $segments, $total_label, $size = 180 ) {
	$total = 0;
	foreach ( $segments as $seg ) {
		$total += (int) $seg[1];
	}

	$r      = $size / 2 - 14;
	$stroke = 22;
	$c      = 2 * M_PI * $r;
	$cx     = $size / 2;
	$parts  = array_filter( $segments, function ( $seg ) {
		return (int) $seg[1] > 0;
	} );
	$gap    = count( $parts ) > 1 ? 2 : 0;

	$svg = sprintf( '<svg class="rcc-donut" viewBox="0 0 %1$d %1$d" width="%1$d" height="%1$d" role="img" aria-label="%2$s">', $size, esc_attr( $total_label ) );
	$svg .= sprintf( '<circle cx="%1$s" cy="%1$s" r="%2$s" fill="none" stroke="#f0f0f1" stroke-width="%3$d"/>', $cx, $r, $stroke );

	$offset = 0;
	foreach ( $parts as $seg ) {
		$len = $c * (int) $seg[1] / max( 1, $total );
		$svg .= sprintf(
			'<circle class="rcc-donut__seg" cx="%1$s" cy="%1$s" r="%2$s" fill="none" stroke="%3$s" stroke-width="%4$d" stroke-dasharray="%5$s %6$s" stroke-dashoffset="%7$s" transform="rotate(-90 %1$s %1$s)"><title>%8$s</title></circle>',
			$cx,
			$r,
			esc_attr( $seg[2] ),
			$stroke,
			round( max( 0.5, $len - $gap ), 2 ),
			round( $c, 2 ),
			round( -$offset, 2 ),
			esc_html( $seg[0] . ': ' . number_format_i18n( (int) $seg[1] ) . ' (' . rcc_stats_format_percent( rcc_stats_percent( (int) $seg[1], $total ) ) . ')' )
		);
		$offset += $len;
	}

	$svg .= sprintf( '<text x="%1$s" y="%2$s" text-anchor="middle" class="rcc-donut__value">%3$s</text>', $cx, $cx + 4, esc_html( number_format_i18n( $total ) ) );
	$svg .= sprintf( '<text x="%1$s" y="%2$s" text-anchor="middle" class="rcc-donut__label">val</text>', $cx, $cx + 24 );
	$svg .= '</svg>';
	return $svg;
}

/**
 * Rektangel med rundade övre hörn (den fria änden av en stapel).
 */
function rcc_svg_top_rounded_rect( $x, $y, $w, $h, $r ) {
	$r = min( $r, $w / 2, $h );
	return sprintf(
		'M%1$s %2$s V%3$s Q%1$s %4$s %5$s %4$s H%6$s Q%7$s %4$s %7$s %3$s V%2$s Z',
		round( $x, 2 ),
		round( $y + $h, 2 ),
		round( $y + $r, 2 ),
		round( $y, 2 ),
		round( $x + $r, 2 ),
		round( $x + $w - $r, 2 ),
		round( $x + $w, 2 )
	);
}

/**
 * Gemensam ram för dagsdiagrammen: stödlinjer, y-etiketter och
 * datumetiketter. Returnerar array( svg-början, geometri ).
 */
function rcc_svg_day_frame( $dates, $max_value, $label, $w = 720, $h = 220 ) {
	$left   = 40;
	$right  = 24;
	$top    = 10;
	$bottom = 26;

	list( $max, $step ) = rcc_stats_nice_max( $max_value );

	$plot_w = $w - $left - $right;
	$plot_h = $h - $top - $bottom;
	$n      = max( 1, count( $dates ) );
	$slot   = $plot_w / $n;

	$svg = sprintf( '<svg class="rcc-chart" viewBox="0 0 %1$d %2$d" preserveAspectRatio="xMidYMid meet" role="img" aria-label="%3$s">', $w, $h, esc_attr( $label ) );

	for ( $v = 0; $v <= $max; $v += $step ) {
		$y    = $top + $plot_h - ( $v / $max ) * $plot_h;
		$svg .= sprintf( '<line x1="%1$d" x2="%2$d" y1="%3$s" y2="%3$s" class="%4$s"/>', $left, $w - $right, round( $y, 2 ), 0 === $v ? 'rcc-chart__base' : 'rcc-chart__grid' );
		$svg .= sprintf( '<text x="%1$d" y="%2$s" text-anchor="end" class="rcc-chart__tick">%3$s</text>', $left - 6, round( $y + 4, 2 ), esc_html( number_format_i18n( $v ) ) );
	}

	$every = max( 1, (int) ceil( $n / ( $w > 900 ? 15 : 10 ) ) );
	foreach ( array_values( $dates ) as $i => $date ) {
		if ( 0 !== ( $n - 1 - $i ) % $every ) {
			continue;
		}
		$svg .= sprintf( '<text x="%1$s" y="%2$d" text-anchor="middle" class="rcc-chart__tick">%3$s</text>', round( $left + $slot * ( $i + 0.5 ), 2 ), $h - 8, esc_html( rcc_stats_short_date( $date ) ) );
	}

	return array( $svg, compact( 'w', 'h', 'left', 'top', 'plot_w', 'plot_h', 'slot', 'max' ) );
}

/**
 * Staplade staplar per dag: accepterat (nederst), delvis, avvisat.
 */
function rcc_svg_daily_choices( $daily ) {
	$colors = rcc_stats_colors();
	$labels = rcc_consent_status_labels();
	$order  = array( 'accepted', 'partial', 'rejected' );

	$max = 0;
	foreach ( $daily as $row ) {
		$max = max( $max, $row['accepted'] + $row['partial'] + $row['rejected'] );
	}

	list( $svg, $g ) = rcc_svg_day_frame( array_keys( $daily ), $max, 'Val per dag' );
	$bar_w = min( 28, $g['slot'] * 0.7 );
	$base  = $g['top'] + $g['plot_h'];

	$i = 0;
	foreach ( $daily as $date => $row ) {
		$x     = $g['left'] + $g['slot'] * $i + ( $g['slot'] - $bar_w ) / 2;
		$total = $row['accepted'] + $row['partial'] + $row['rejected'];
		$y     = $base;

		$present = array_values( array_filter( $order, function ( $key ) use ( $row ) {
			return $row[ $key ] > 0;
		} ) );
		foreach ( $present as $j => $key ) {
			$hgt = $row[ $key ] / $g['max'] * $g['plot_h'];
			$top = $y - $hgt;
			// 2 px luft mellan segmenten, men aldrig så att ett segment försvinner.
			$draw_h = $j > 0 ? max( 1, $hgt - 2 ) : $hgt;
			if ( $j === count( $present ) - 1 ) {
				$svg .= sprintf( '<path d="%s" fill="%s"/>', rcc_svg_top_rounded_rect( $x, $top, $bar_w, $draw_h, 4 ), esc_attr( $colors[ $key ] ) );
			} else {
				$svg .= sprintf( '<rect x="%s" y="%s" width="%s" height="%s" fill="%s"/>', round( $x, 2 ), round( $top, 2 ), round( $bar_w, 2 ), round( $draw_h, 2 ), esc_attr( $colors[ $key ] ) );
			}
			$y = $top;
		}

		$tip = rcc_stats_short_date( $date ) . ': ' . number_format_i18n( $total ) . ' val';
		if ( $total ) {
			$tip .= ' (' . $labels['accepted'] . ' ' . $row['accepted'] . ', ' . $labels['partial'] . ' ' . $row['partial'] . ', ' . $labels['rejected'] . ' ' . $row['rejected'] . ')';
		}
		$svg .= sprintf( '<rect class="rcc-chart__hit" x="%s" y="%s" width="%s" height="%s"><title>%s</title></rect>', round( $g['left'] + $g['slot'] * $i, 2 ), $g['top'], round( $g['slot'], 2 ), $g['plot_h'], esc_html( $tip ) );
		$i++;
	}

	return $svg . '</svg>';
}

/**
 * Linje för visningar av rutan per dag.
 */
function rcc_svg_daily_views( $daily ) {
	$colors = rcc_stats_colors();
	$max    = 0;
	foreach ( $daily as $row ) {
		$max = max( $max, $row['views'] );
	}

	list( $svg, $g ) = rcc_svg_day_frame( array_keys( $daily ), $max, 'Visningar av rutan per dag', 1120, 200 );
	$base   = $g['top'] + $g['plot_h'];
	$points = array();
	$i      = 0;
	foreach ( $daily as $row ) {
		$points[] = round( $g['left'] + $g['slot'] * ( $i + 0.5 ), 2 ) . ',' . round( $base - $row['views'] / $g['max'] * $g['plot_h'], 2 );
		$i++;
	}
	$svg .= sprintf( '<polyline points="%s" fill="none" stroke="%s" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>', esc_attr( implode( ' ', $points ) ), esc_attr( $colors['views'] ) );

	$show_markers = count( $daily ) <= 31;
	$i            = 0;
	foreach ( $daily as $date => $row ) {
		list( $px, $py ) = explode( ',', $points[ $i ] );
		if ( $show_markers ) {
			$svg .= sprintf( '<circle cx="%s" cy="%s" r="4" fill="%s" stroke="#fff" stroke-width="2"/>', $px, $py, esc_attr( $colors['views'] ) );
		}
		$svg .= sprintf( '<rect class="rcc-chart__hit" x="%s" y="%s" width="%s" height="%s"><title>%s</title></rect>', round( $g['left'] + $g['slot'] * $i, 2 ), $g['top'], round( $g['slot'], 2 ), $g['plot_h'], esc_html( rcc_stats_short_date( $date ) . ': ' . number_format_i18n( $row['views'] ) . ' visningar' ) );
		$i++;
	}

	return $svg . '</svg>';
}

/**
 * Teckenförklaring med värden, så att färgen aldrig bär informationen
 * ensam.
 */
function rcc_stats_legend( $totals ) {
	$colors = rcc_stats_colors();
	$labels = rcc_consent_status_labels();
	$out    = '<ul class="rcc-legend">';
	foreach ( array( 'accepted', 'partial', 'rejected' ) as $key ) {
		$out .= sprintf(
			'<li><span class="rcc-legend__swatch" style="background:%s"></span><span class="rcc-legend__label">%s</span> <strong>%s</strong> <span class="rcc-muted">%s</span></li>',
			esc_attr( $colors[ $key ] ),
			esc_html( $labels[ $key ] ),
			esc_html( number_format_i18n( $totals[ $key ] ) ),
			esc_html( rcc_stats_format_percent( rcc_stats_percent( $totals[ $key ], $totals['choices'] ) ) )
		);
	}
	return $out . '</ul>';
}

function rcc_stats_donut_for( $totals, $size = 180 ) {
	$colors = rcc_stats_colors();
	$labels = rcc_consent_status_labels();
	return rcc_svg_donut( array(
		array( $labels['accepted'], $totals['accepted'], $colors['accepted'] ),
		array( $labels['partial'], $totals['partial'], $colors['partial'] ),
		array( $labels['rejected'], $totals['rejected'], $colors['rejected'] ),
	), 'Fördelning av val: ' . number_format_i18n( $totals['choices'] ) . ' totalt', $size );
}

/* -------------------------------------------------------------------------
 * Sidan
 * ---------------------------------------------------------------------- */

function rcc_stats_allowed_periods() {
	return array(
		7  => '7 dagar',
		30 => '30 dagar',
		90 => '90 dagar',
	);
}

function rcc_render_stats_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$periods = rcc_stats_allowed_periods();
	$days    = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $periods[ $days ] ) ) {
		$days = 30;
	}

	$data   = rcc_stats_summary( $days );
	$t      = $data['totals'];
	$labels = rcc_consent_status_labels();
	$views  = rcc_count_banner_views_enabled();
	?>
	<div class="wrap rcc-settings rcc-stats">
		<h1 class="wp-heading-inline">Statistik</h1>
		<hr class="wp-header-end" />

		<ul class="subsubsub rcc-periods">
			<?php
			$links = array();
			foreach ( $periods as $value => $label ) {
				$links[] = sprintf(
					'<li><a href="%s"%s>%s</a>',
					esc_url( rcc_admin_page_url( RCC_STATS_SLUG, array( 'days' => $value ) ) ),
					$value === $days ? ' class="current" aria-current="page"' : '',
					esc_html( $label )
				);
			}
			echo implode( ' | </li>', $links ) . '</li>'; // phpcs:ignore -- byggt och escapat ovan.
			?>
		</ul>
		<p class="rcc-period-range"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $data['period']['from_date'] ) ) . ' – ' . date_i18n( get_option( 'date_format' ), strtotime( $data['period']['to_date'] ) ) ); ?></p>
		<div class="clear"></div>

		<?php if ( ! rcc_consent_log_enabled() ) : ?>
			<div class="notice notice-warning inline"><p>Samtyckesloggen är avstängd, så inga nya val räknas. Slå på den under <a href="<?php echo esc_url( rcc_admin_page_url() . '#rcc-tab-general' ); ?>">Inställningar → Allmänt</a>.</p></div>
		<?php elseif ( ! $views ) : ?>
			<div class="notice notice-info inline"><p>Visningar av rutan räknas inte. Slå på det under <a href="<?php echo esc_url( rcc_admin_page_url() . '#rcc-tab-general' ); ?>">Inställningar → Allmänt</a> för att få svarsfrekvensen.</p></div>
		<?php endif; ?>

		<div class="rcc-kpis">
			<div class="rcc-kpi">
				<span class="rcc-kpi__label">Val</span>
				<span class="rcc-kpi__value"><?php echo esc_html( number_format_i18n( $t['choices'] ) ); ?></span>
				<span class="rcc-kpi__sub"><?php echo esc_html( number_format_i18n( $t['unique'] ) ); ?> unika samtyckes-ID</span>
			</div>
			<?php foreach ( array( 'accepted', 'partial', 'rejected' ) as $key ) : ?>
				<div class="rcc-kpi">
					<span class="rcc-kpi__label"><span class="rcc-legend__swatch" style="background:<?php echo esc_attr( rcc_stats_colors()[ $key ] ); ?>"></span><?php echo esc_html( $labels[ $key ] ); ?></span>
					<span class="rcc-kpi__value"><?php echo esc_html( rcc_stats_format_percent( rcc_stats_percent( $t[ $key ], $t['choices'] ) ) ); ?></span>
					<span class="rcc-kpi__sub"><?php echo esc_html( number_format_i18n( $t[ $key ] ) ); ?> val</span>
				</div>
			<?php endforeach; ?>
			<div class="rcc-kpi">
				<span class="rcc-kpi__label">Visningar av rutan</span>
				<span class="rcc-kpi__value"><?php echo $views || $t['views'] ? esc_html( number_format_i18n( $t['views'] ) ) : '–'; ?></span>
				<span class="rcc-kpi__sub">Svarsfrekvens <?php echo esc_html( rcc_stats_format_percent( rcc_stats_percent( $t['choices'], $t['views'] ) ) ); ?></span>
			</div>
		</div>

		<?php if ( ! $t['choices'] && ! $t['views'] ) : ?>
			<p class="rcc-empty">Inga val eller visningar under perioden ännu.</p>
		<?php else : ?>
			<div class="rcc-stats-grid">
				<div class="rcc-card">
					<h2>Fördelning</h2>
					<div class="rcc-donut-wrap">
						<?php echo rcc_stats_donut_for( $t ); // phpcs:ignore -- SVG byggd och escapad i rcc_svg_donut(). ?>
						<?php echo rcc_stats_legend( $t ); // phpcs:ignore -- escapat i funktionen. ?>
					</div>
				</div>
				<div class="rcc-card rcc-card--wide">
					<h2>Val per dag</h2>
					<?php echo rcc_svg_daily_choices( $data['daily'] ); // phpcs:ignore -- SVG byggd och escapad i funktionen. ?>
					<?php echo rcc_stats_legend( $t ); // phpcs:ignore -- escapat i funktionen. ?>
				</div>
			</div>

			<?php if ( $views || $t['views'] ) : ?>
				<div class="rcc-card">
					<h2>Visningar av rutan per dag</h2>
					<?php echo rcc_svg_daily_views( $data['daily'] ); // phpcs:ignore -- SVG byggd och escapad i funktionen. ?>
				</div>
			<?php endif; ?>

			<details class="rcc-card rcc-stats-table">
				<summary>Visa siffrorna som tabell</summary>
				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col">Datum</th>
							<th scope="col" class="num"><?php echo esc_html( $labels['accepted'] ); ?></th>
							<th scope="col" class="num"><?php echo esc_html( $labels['partial'] ); ?></th>
							<th scope="col" class="num"><?php echo esc_html( $labels['rejected'] ); ?></th>
							<th scope="col" class="num">Val totalt</th>
							<th scope="col" class="num">Visningar</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( array_reverse( $data['daily'], true ) as $date => $row ) : ?>
							<tr>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $date . ' 12:00:00' ) ) ); ?></td>
								<td class="num"><?php echo esc_html( number_format_i18n( $row['accepted'] ) ); ?></td>
								<td class="num"><?php echo esc_html( number_format_i18n( $row['partial'] ) ); ?></td>
								<td class="num"><?php echo esc_html( number_format_i18n( $row['rejected'] ) ); ?></td>
								<td class="num"><?php echo esc_html( number_format_i18n( $row['accepted'] + $row['partial'] + $row['rejected'] ) ); ?></td>
								<td class="num"><?php echo esc_html( number_format_i18n( $row['views'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</details>
		<?php endif; ?>

		<p class="description rcc-stats-note">Val räknas från samtyckesloggen: varje gång en besökare väljer i rutan blir det ett val, även när samma besökare ändrar sig. Visningar räknas per sidvisning där rutan visas utan att besökaren gjort ett val, utan cookies eller andra uppgifter om besökaren. Svarsfrekvensen (val delat med visningar) är därför ett golv. Både val och visningar rapporteras av besökarens webbläsare utan inloggning, så siffrorna kan påverkas av robotar eller konstgjorda anrop – se dem som en trend snarare än exakta tal. Statistiken gallras efter samma tid som loggen.</p>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Widget på panelen
 * ---------------------------------------------------------------------- */

function rcc_register_dashboard_widget() {
	if ( ! current_user_can( 'manage_options' ) || ! apply_filters( 'rcc_dashboard_widget', true ) ) {
		return;
	}
	wp_add_dashboard_widget( 'rcc_stats_widget', 'Cookie Consent – senaste 30 dagarna', 'rcc_render_dashboard_widget' );
}
add_action( 'wp_dashboard_setup', 'rcc_register_dashboard_widget' );

function rcc_render_dashboard_widget() {
	$data = rcc_stats_summary( 30 );
	$t    = $data['totals'];
	?>
	<div class="rcc-widget">
		<?php if ( ! $t['choices'] ) : ?>
			<p>Inga val loggade de senaste 30 dagarna.</p>
		<?php else : ?>
			<div class="rcc-donut-wrap">
				<?php echo rcc_stats_donut_for( $t, 140 ); // phpcs:ignore -- SVG byggd och escapad i rcc_svg_donut(). ?>
				<?php echo rcc_stats_legend( $t ); // phpcs:ignore -- escapat i funktionen. ?>
			</div>
		<?php endif; ?>
		<p class="rcc-widget__meta">
			<?php if ( rcc_count_banner_views_enabled() || $t['views'] ) : ?>
				Visningar av rutan: <strong><?php echo esc_html( number_format_i18n( $t['views'] ) ); ?></strong> · Svarsfrekvens: <strong><?php echo esc_html( rcc_stats_format_percent( rcc_stats_percent( $t['choices'], $t['views'] ) ) ); ?></strong><br />
			<?php endif; ?>
			<a href="<?php echo esc_url( rcc_admin_page_url( RCC_STATS_SLUG ) ); ?>">Visa all statistik</a>
		</p>
	</div>
	<?php
}
