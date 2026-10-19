<?php
/**
 * Plugin Name:       Plan A baneri – vrh naslovnice
 * Description:       Kratki kod [plan-a-naslovnica] umjesto slidera: velika slika s porukom i dva gumba, kartica „Sljedeći polazak” (odbrojavanje, slobodna mjesta), traka akcije (npr. članarina 5 €), traka Tinder izleta (brojač ♂/♀) i kartice sljedećih izleta. Podaci iz postojećih izleta (Plan A izleti).
 * Version:           1.1.4
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Plan A
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       plan-a-baneri
 *
 * Ne mijenja izlete, WooCommerce, temu ni druge dodatke. Postavke: Postavke → Plan A naslovnica.
 */

defined( 'ABSPATH' ) || exit;

define( 'PLAN_A_BANERI_VERSION', '1.1.4' );
define( 'PLAN_A_BANERI_URL', plugin_dir_url( __FILE__ ) );
define( 'PLAN_A_BANERI_DIR', plugin_dir_path( __FILE__ ) );

require_once __DIR__ . '/includes/class-plan-a-baneri.php';
require_once __DIR__ . '/includes/class-plan-a-baneri-front.php';
require_once __DIR__ . '/includes/class-plan-a-baneri-admin.php';

Plan_A_Baneri::init();
Plan_A_Baneri_Front::init();
Plan_A_Baneri_Admin::init();
