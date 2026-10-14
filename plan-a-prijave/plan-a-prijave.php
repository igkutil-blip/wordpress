<?php
/**
 * Plugin Name:       Plan A izleti – prijave
 * Description:       Vlastiti sustav izleta i prijava za Plan A, po uzoru na WpTravelly i s istim podacima. Obrazac za uređivanje izleta (faza 2); stranica izleta, prijava i narudžbe bez WpTravellyja kad je on isključen (faza 3).
 * Version:           0.8.2
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            S.R.D. Plan A
 * License:           GPLv2 or later
 * Text Domain:       plan-a-prijave
 *
 * @package Plan_A_Prijave
 */

defined( 'ABSPATH' ) || exit;

define( 'PLAN_A_PRIJAVE_VERSION', '0.8.2' );
define( 'PLAN_A_PRIJAVE_FILE', __FILE__ );
define( 'PLAN_A_PRIJAVE_DIR', plugin_dir_path( __FILE__ ) );
define( 'PLAN_A_PRIJAVE_URL', plugin_dir_url( __FILE__ ) );

require_once PLAN_A_PRIJAVE_DIR . 'includes/class-plan-a-prijave-tour.php';
require_once PLAN_A_PRIJAVE_DIR . 'includes/class-plan-a-prijave-editor.php';
require_once PLAN_A_PRIJAVE_DIR . 'includes/class-plan-a-prijave-copy.php';
require_once PLAN_A_PRIJAVE_DIR . 'includes/class-plan-a-prijave-ttbm.php';
require_once PLAN_A_PRIJAVE_DIR . 'includes/class-plan-a-prijave-admin.php';
require_once PLAN_A_PRIJAVE_DIR . 'includes/class-plan-a-prijave-seo.php';
require_once PLAN_A_PRIJAVE_DIR . 'includes/class-plan-a-prijave-seats.php';
require_once PLAN_A_PRIJAVE_DIR . 'includes/class-plan-a-prijave-text.php';
require_once PLAN_A_PRIJAVE_DIR . 'includes/class-plan-a-prijave-hero.php';
require_once PLAN_A_PRIJAVE_DIR . 'includes/plan-a-email.php';
require_once PLAN_A_PRIJAVE_DIR . 'includes/class-plan-a-prijave-enquiry.php';
require_once PLAN_A_PRIJAVE_DIR . 'includes/class-plan-a-prijave-unos.php';
require_once PLAN_A_PRIJAVE_DIR . 'includes/class-plan-a-prijave-granice.php';
require_once PLAN_A_PRIJAVE_DIR . 'includes/class-plan-a-prijave-uvjeti.php';
require_once PLAN_A_PRIJAVE_DIR . 'includes/class-plan-a-prijave-video.php';
require_once PLAN_A_PRIJAVE_DIR . 'includes/class-plan-a-prijave-cekanje.php';

Plan_A_Prijave_Editor::init();
Plan_A_Prijave_Copy::init();
Plan_A_Prijave_Ttbm::init();
Plan_A_Prijave_Admin::init();
Plan_A_Prijave_Seo::init();
Plan_A_Prijave_Seats::init();
Plan_A_Prijave_Text::init();
Plan_A_Prijave_Hero::init();
Plan_A_Prijave_Enquiry::init();
Plan_A_Prijave_Unos::init();
Plan_A_Prijave_Granice::init();
Plan_A_Prijave_Uvjeti::init();
Plan_A_Prijave_Video::init();
Plan_A_Prijave_Cekanje::init();
