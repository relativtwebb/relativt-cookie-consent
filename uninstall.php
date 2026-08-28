<?php
/**
 * Körs när pluginet raderas via WP-admin (inte vid avaktivering).
 * Tar bort sparade inställningar och uppdaterarens cache.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

function rcc_uninstall_cleanup_site() {
	delete_option( 'rcc_settings' );
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
