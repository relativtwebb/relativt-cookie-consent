<?php
/**
 * Körs när pluginet raderas via WP-admin (inte vid avaktivering).
 * Tar bort sparade inställningar, samtyckesloggen, cron-jobbet och
 * uppdaterarens cache.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

function rcc_uninstall_cleanup_site() {
	global $wpdb;
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rcc_consent_log' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	wp_clear_scheduled_hook( 'rcc_consent_log_cleanup' );
	delete_option( 'rcc_settings' );
	delete_option( 'rcc_db_version' );
	delete_site_transient( 'rcc_github_release' );
	delete_transient( 'rcc_github_release' );
}

if ( is_multisite() ) {
	$rcc_site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
	foreach ( $rcc_site_ids as $rcc_site_id ) {
		switch_to_blog( $rcc_site_id );
		rcc_uninstall_cleanup_site();
		restore_current_blog();
	}
} else {
	rcc_uninstall_cleanup_site();
}
