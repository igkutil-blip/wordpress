<?php
/**
 * Brisanje dodatka: uklanja postavke i privremene podatke. Meta na narudžbama (provjereno + vrijeme) ostaje kao dokaz provjere.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'domestic_provjera_dobi' );
delete_option( 'dsd_rewrite' );

global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_dsd\\_%' OR option_name LIKE '\\_transient\\_timeout\\_dsd\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
