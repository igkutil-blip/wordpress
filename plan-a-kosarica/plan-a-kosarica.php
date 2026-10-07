<?php
/**
 * Plugin Name:       Plan A košarica
 * Description:       Novi izgled košarice, plaćanja, završne stranice narudžbe i e-mailova kupcu (WooCommerce) za izlete iz WpTravellyja. Svi podaci i kuke WooCommercea ostaju; 2D kod i podatke za plaćanje i dalje ispisuje Hub3 dodatak.
 * Version:           1.4.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Plan A
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       plan-a-kosarica
 *
 * Dodatak ne mijenja WooCommerce, WpTravelly ni temu: vlastitim predlošcima
 * zamjenjuje samo prikaz košarice i stranice za plaćanje. Isključuje se u
 * Postavke → Plan A košarica ili deaktivacijom dodatka.
 */

defined( 'ABSPATH' ) || exit;

define( 'PLAN_A_KOSARICA_VERSION', '1.4.0' );
define( 'PLAN_A_KOSARICA_FILE', __FILE__ );
define( 'PLAN_A_KOSARICA_DIR', plugin_dir_path( __FILE__ ) );
define( 'PLAN_A_KOSARICA_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/includes/class-plan-a-kosarica-settings.php';
require_once __DIR__ . '/includes/class-plan-a-kosarica.php';
require_once __DIR__ . '/includes/class-plan-a-kosarica-order.php';

Plan_A_Kosarica_Settings::init();
add_action( 'plugins_loaded', array( 'Plan_A_Kosarica', 'init' ), 20 );
add_action( 'plugins_loaded', array( 'Plan_A_Kosarica_Order', 'init' ), 20 );
