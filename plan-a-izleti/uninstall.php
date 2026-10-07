<?php
/**
 * Brisanje dodatka: uklanja privremeni cache, oznaku jednokratnog čišćenja i,
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
