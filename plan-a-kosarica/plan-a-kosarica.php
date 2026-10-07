<?php
/**
 * Plugin Name:       Plan A košarica
 * Description:       Novi izgled košarice i plaćanja (WooCommerce) za izlete iz WpTravellyja: kartice izleta, koraci Košarica → Podaci → Plaćanje, sažetak rezervacije i jasan gumb za nastavak. Svi podaci i kuke WooCommercea ostaju.
 * Version:           1.0.0
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

define( 'PLAN_A_KOSARICA_VERSION', '1.0.0' );
define( 'PLAN_A_KOSARICA_FILE', __FILE__ );
define( 'PLAN_A_KOSARICA_DIR', plugin_dir_path( __FILE__ ) );
define( 'PLAN_A_KOSARICA_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/includes/class-plan-a-kosarica-settings.php';
require_once __DIR__ . '/includes/class-plan-a-kosarica.php';

Plan_A_Kosarica_Settings::init();
add_action( 'plugins_loaded', array( 'Plan_A_Kosarica', 'init' ), 20 );
