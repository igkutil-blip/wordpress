<?php
/**
 * Plugin Name:       Plan A izleti
 * Description:       Shortcode [plan-a-izleti] prikazuje nadolazeće izlete iz dodatka WpTravelly (Tour Booking Manager) u mreži s izbornicima vrste izleta i termina.
 * Version:           1.17.6
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Plan A
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       plan-a-izleti
 *
 * Dodatak samo čita podatke koje sprema WpTravelly; ne mijenja WpTravelly,
 * temu ni bazu podataka (osim jednog privremenog transienta za cache).
 */

defined( 'ABSPATH' ) || exit;

define( 'PLAN_A_IZLETI_VERSION', '1.17.6' );
define( 'PLAN_A_IZLETI_FILE', __FILE__ );
define( 'PLAN_A_IZLETI_URL', plugin_dir_url( __FILE__ ) );
define( 'PLAN_A_IZLETI_DIR', plugin_dir_path( __FILE__ ) );

require_once __DIR__ . '/includes/class-plan-a-izleti-data.php';
require_once __DIR__ . '/includes/class-plan-a-izleti-categories.php';
require_once __DIR__ . '/includes/class-plan-a-izleti-shortcode.php';
require_once __DIR__ . '/includes/class-plan-a-izleti-share.php';
require_once __DIR__ . '/includes/class-plan-a-izleti-card.php';
require_once __DIR__ . '/includes/class-plan-a-izleti-settings.php';
require_once __DIR__ . '/includes/class-plan-a-izleti-cleanup.php';
require_once __DIR__ . '/includes/class-plan-a-izleti-plan.php';
require_once __DIR__ . '/includes/class-plan-a-izleti-plan-view.php';
require_once __DIR__ . '/includes/class-plan-a-izleti-plan-ics.php';
require_once __DIR__ . '/includes/class-plan-a-izleti-plan-notify.php';

Plan_A_Izleti_Data::init();
Plan_A_Izleti_Shortcode::init();
Plan_A_Izleti_Share::init();
Plan_A_Izleti_Card::init();
Plan_A_Izleti_Settings::init();
Plan_A_Izleti_Cleanup::init(); // jednokratno: uklanja podatke ukinute funkcije "Dogovor s ekipom"
Plan_A_Izleti_Plan::init();
Plan_A_Izleti_Plan_View::init();
Plan_A_Izleti_Plan_Ics::init();
Plan_A_Izleti_Plan_Notify::init();

register_deactivation_hook( __FILE__, array( 'Plan_A_Izleti_Data', 'flush_cache' ) );
register_deactivation_hook( __FILE__, array( 'Plan_A_Izleti_Plan_Notify', 'unschedule' ) );
