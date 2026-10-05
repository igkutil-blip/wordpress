<?php
/**
 * Brisanje dodatka: uklanja jedini podatak koji dodatak sprema (privremeni cache).
 * Izleti, kategorije i postavke WpTravellyja ostaju netaknuti.
 *
 * @package Plan_A_Izleti
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $plan_a_izleti_site_id ) {
		switch_to_blog( $plan_a_izleti_site_id );
		delete_transient( 'plan_a_izleti_cache' );
		restore_current_blog();
	}
} else {
	delete_transient( 'plan_a_izleti_cache' );
}
