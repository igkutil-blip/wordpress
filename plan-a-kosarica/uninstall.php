<?php
/**
 * Brisanje dodatka: uklanja samo postavke. WooCommerce, narudžbe i tema ostaju netaknuti.
 *
 * @package Plan_A_Kosarica
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'plan_a_kosarica' );
