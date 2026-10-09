<?php
/**
 * Brisanje dodatka: postavke se brišu, a članovi (podaci) ostaju u bazi radi evidencije
 * udruge. Za potpuno brisanje članova obriši ih u administraciji prije brisanja dodatka.
 *
 * @package Plan_A_Clanstvo
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'plan_a_clanstvo' );
delete_option( 'plan_a_clanstvo_page' );
delete_option( 'plan_a_clanstvo_delete_queue' );
wp_clear_scheduled_hook( 'plan_a_clanstvo_cron' );
