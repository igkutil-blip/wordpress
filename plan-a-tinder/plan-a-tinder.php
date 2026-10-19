<?php
/**
 * Plugin Name:       Plan A Tinder i planinarenje
 * Description:       Samo za odabrane izlete (npr. „Tinder i planinarenje”): na stranici izleta brojač prijavljenih muškaraca i žena s rasponom godina i gumb za WhatsApp grupu, u košarici spol i godina rođenja za svaku osobu, u adminu popis prijavljenih.
 * Version:           1.2.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Plan A
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       plan-a-tinder
 *
 * Ne mijenja ostale izlete, WooCommerce, temu ni druge dodatke. Prijava i dalje ide u tablicu za agenciju kao za sve izlete.
 */

defined( 'ABSPATH' ) || exit;

define( 'PLAN_A_TINDER_VERSION', '1.2.0' );
define( 'PLAN_A_TINDER_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/includes/class-plan-a-tinder.php';
require_once __DIR__ . '/includes/class-plan-a-tinder-front.php';
require_once __DIR__ . '/includes/class-plan-a-tinder-admin.php';

Plan_A_Tinder::init();
Plan_A_Tinder_Front::init();
Plan_A_Tinder_Admin::init();
