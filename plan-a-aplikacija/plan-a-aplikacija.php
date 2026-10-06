<?php
/**
 * Plugin Name:       Plan A aplikacija
 * Description:       Pretvara stranicu u instalabilnu web aplikaciju (PWA): manifest, service worker koji nikad ne sprema košaricu ni plaćanje, izvanmrežna stranica, poziv na instalaciju i donja navigacija u aplikaciji.
 * Version:           1.2.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Plan A
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       plan-a-aplikacija
 *
 * Ne mijenja temu, WpTravelly ni WooCommerce. Sprema samo svoje postavke
 * (opcije plan_a_app_*) i generirane ikone u uploads/plan-a-aplikacija/.
 */

defined( 'ABSPATH' ) || exit;

define( 'PLAN_A_APP_VERSION', '1.2.0' );
define( 'PLAN_A_APP_FILE', __FILE__ );
define( 'PLAN_A_APP_DIR', __DIR__ );
define( 'PLAN_A_APP_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/includes/class-plan-a-app-settings.php';
require_once __DIR__ . '/includes/class-plan-a-app-icons.php';
require_once __DIR__ . '/includes/class-plan-a-app-endpoints.php';
require_once __DIR__ . '/includes/class-plan-a-app-frontend.php';
require_once __DIR__ . '/includes/class-plan-a-app-admin.php';

Plan_A_App_Settings::init();
Plan_A_App_Endpoints::init();
Plan_A_App_Frontend::init();
if ( is_admin() ) {
	Plan_A_App_Admin::init();
}

register_activation_hook( __FILE__, array( 'Plan_A_App_Icons', 'regenerate' ) );
