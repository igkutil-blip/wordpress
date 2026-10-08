<?php
/**
 * Brisanje dodatka uklanja postavke, cjenik i ručne postavke tjedana. Rezervacije,
 * narudžbe i skriveni proizvod ostaju jer su dio poslovne evidencije.
 *
 * @package Plan_A_Jedrenje
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'plan_a_jedrenje' );
delete_option( 'plan_a_jedrenje_periods' );
delete_option( 'plan_a_jedrenje_weeks' );
delete_option( 'plan_a_jedrenje_page' );
wp_clear_scheduled_hook( 'plan_a_jedrenje_daily' );
