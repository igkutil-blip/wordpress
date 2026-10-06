<?php
/**
 * Brisanje dodatka: uklanja postavke i generirane ikone.
 * Service worker se kod posjetitelja uklanja sam čim primijeti da dodatak
 * više ne radi (vidi assets/sw.js, checkStillActive). Briše se i tablica statistike.
 *
 * @package Plan_A_Aplikacija
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$plan_a_app_cleanup = static function () {
	delete_option( 'plan_a_app_settings' );
	delete_option( 'plan_a_app_icons' );
	delete_option( 'plan_a_app_stats_db' );
	global $wpdb;
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'plan_a_app_stats' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- tablica dodatka.
	$upload = wp_upload_dir();
	$dir    = trailingslashit( $upload['basedir'] ) . 'plan-a-aplikacija';
	if ( is_dir( $dir ) ) {
		foreach ( (array) glob( $dir . '/*.png' ) as $file ) {
			wp_delete_file( $file );
		}
		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}
};

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $plan_a_app_site ) {
		switch_to_blog( $plan_a_app_site );
		$plan_a_app_cleanup();
		restore_current_blog();
	}
} else {
	$plan_a_app_cleanup();
}
