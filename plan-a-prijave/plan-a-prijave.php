<?php
/**
 * Plugin Name:       Plan A izleti – prijave
 * Description:       Vlastiti sustav izleta i prijava za Plan A, po uzoru na WpTravelly i s istim podacima. Obrazac za uređivanje izleta (faza 2); stranica izleta, prijava i narudžbe bez WpTravellyja kad je on isključen (faza 3).
 * Version:           0.3.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            S.R.D. Plan A
 * License:           GPLv2 or later
 * Text Domain:       plan-a-prijave
 *
 * @package Plan_A_Prijave
 */

defined( 'ABSPATH' ) || exit;

define( 'PLAN_A_PRIJAVE_VERSION', '0.3.0' );
define( 'PLAN_A_PRIJAVE_FILE', __FILE__ );
define( 'PLAN_A_PRIJAVE_DIR', plugin_dir_path( __FILE__ ) );
define( 'PLAN_A_PRIJAVE_URL', plugin_dir_url( __FILE__ ) );

require_once PLAN_A_PRIJAVE_DIR . 'includes/class-plan-a-prijave-tour.php';
require_once PLAN_A_PRIJAVE_DIR . 'includes/class-plan-a-prijave-editor.php';
require_once PLAN_A_PRIJAVE_DIR . 'includes/class-plan-a-prijave-copy.php';
require_once PLAN_A_PRIJAVE_DIR . 'includes/class-plan-a-prijave-ttbm.php';

Plan_A_Prijave_Editor::init();
Plan_A_Prijave_Copy::init();
Plan_A_Prijave_Ttbm::init();
