<?php
/**
 * Plugin Name:       Plan A kalkulator
 * Description:       Planinarski kalkulator (shortcode [plan-a-kalkulator]): procjena vremena hoda s odmorima, povratka, vode i energije za planinarsku turu.
 * Version:           1.0.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Plan A
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       plan-a-kalkulator
 *
 * Sve se računa u pregledniku; dodatak ne sprema podatke, ne šalje ih nikamo
 * i ne mijenja temu ni druge dodatke.
 */

defined( 'ABSPATH' ) || exit;

define( 'PLAN_A_KALK_VERSION', '1.0.1' );
define( 'PLAN_A_KALK_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/includes/class-plan-a-kalkulator.php';

Plan_A_Kalkulator::init();
