<?php
/**
 * Brisanje dodatka: uklanja privremeni cache, postavke i slike kartica za
 * "Predloži ekipi", oznaku jednokratnog čišćenja i,
 * ako su zaostali, podatke ukinute funkcije "Dogovor s ekipom".
 * Izleti, kategorije i postavke WpTravellyja ostaju netaknuti.
 *
 * @package Plan_A_Izleti
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/class-plan-a-izleti-cleanup.php';

$plan_a_izleti_cleanup = static function () {
	delete_transient( 'plan_a_izleti_cache' );
	Plan_A_Izleti_Cleanup::run();
	delete_option( Plan_A_Izleti_Cleanup::DONE_OPTION );
	// Postavke i slike kartica za "Predloži ekipi".
	delete_option( 'plan_a_izleti_settings' );
	delete_option( 'plan_a_izleti_cards' );
	delete_transient( 'plan_a_izleti_glyphs' );
	wp_clear_scheduled_hook( 'plan_a_izleti_make_card' );
	$upload = wp_upload_dir( null, false );
	$dir    = trailingslashit( $upload['basedir'] ) . 'plan-a-izleti/kartice';
	if ( is_dir( $dir ) ) {
		foreach ( (array) glob( $dir . '/*' ) as $file ) {
			wp_delete_file( $file );
		}
		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		@rmdir( dirname( $dir ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- samo ako je prazna.
	}
};

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $plan_a_izleti_site_id ) {
		switch_to_blog( $plan_a_izleti_site_id );
		$plan_a_izleti_cleanup();
		restore_current_blog();
	}
} else {
	$plan_a_izleti_cleanup();
}
