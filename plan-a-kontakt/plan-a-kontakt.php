<?php
/**
 * Plugin Name:       Plan A kontakt
 * Description:       Obrazac za upite ([plan-a-kontakt]) umjesto Contact Form 7 / WPForms: poruka stiže e-mailom u Plan A izgledu i sprema se u admin (Poruke).
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Plan A
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       plan-a-kontakt
 *
 * Zaštita od spama bez vanjskih servisa (skriveno polje, JavaScript, ograničenje broja slanja).
 * Ne mijenja temu ni druge dodatke.
 */

defined( 'ABSPATH' ) || exit;

define( 'PLAN_A_KONTAKT_VERSION', '1.0.0' );
define( 'PLAN_A_KONTAKT_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/includes/plan-a-email.php';
require_once __DIR__ . '/includes/class-plan-a-kontakt.php';
require_once __DIR__ . '/includes/class-plan-a-kontakt-admin.php';

Plan_A_Kontakt::init();
Plan_A_Kontakt_Admin::init();

register_activation_hook( __FILE__, array( 'Plan_A_Kontakt_Admin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Plan_A_Kontakt_Admin', 'deactivate' ) );
