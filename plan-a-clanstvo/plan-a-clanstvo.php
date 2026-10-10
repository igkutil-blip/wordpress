<?php
/**
 * Plugin Name:       Plan A članstvo
 * Description:       Pristupnica za članstvo u udruzi: obrazac na stranici, potvrda klikom u e-mailu, 2D kod za članarinu, automatski upis u Google tablicu i provjera članstva pri prijavi na izlet.
 * Version:           1.12.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            S.R.D. Plan A
 * License:           GPLv2 or later
 * Text Domain:       plan-a-clanstvo
 *
 * @package Plan_A_Clanstvo
 */

defined( 'ABSPATH' ) || exit;

define( 'PLAN_A_CLANSTVO_VERSION', '1.12.1' );
define( 'PLAN_A_CLANSTVO_FILE', __FILE__ );
define( 'PLAN_A_CLANSTVO_DIR', plugin_dir_path( __FILE__ ) );
define( 'PLAN_A_CLANSTVO_URL', plugin_dir_url( __FILE__ ) );

require_once PLAN_A_CLANSTVO_DIR . 'includes/class-plan-a-clanstvo-data.php';
require_once PLAN_A_CLANSTVO_DIR . 'includes/class-plan-a-clanstvo-mail.php';
require_once PLAN_A_CLANSTVO_DIR . 'includes/class-plan-a-clanstvo-hub3.php';
require_once PLAN_A_CLANSTVO_DIR . 'includes/class-plan-a-clanstvo-sheets.php';
require_once PLAN_A_CLANSTVO_DIR . 'includes/class-plan-a-clanstvo-form.php';
require_once PLAN_A_CLANSTVO_DIR . 'includes/class-plan-a-clanstvo-woo.php';
require_once PLAN_A_CLANSTVO_DIR . 'includes/class-plan-a-clanstvo-admin.php';
require_once PLAN_A_CLANSTVO_DIR . 'includes/class-plan-a-clanstvo-import.php';
require_once PLAN_A_CLANSTVO_DIR . 'includes/class-plan-a-clanstvo-agency.php';
require_once PLAN_A_CLANSTVO_DIR . 'includes/class-plan-a-clanstvo-checkout.php';
require_once PLAN_A_CLANSTVO_DIR . 'includes/class-plan-a-clanstvo-pdf.php';
require_once PLAN_A_CLANSTVO_DIR . 'includes/class-plan-a-clanstvo-ops.php';

register_activation_hook(
	__FILE__,
	static function () {
		Plan_A_Clanstvo_Data::register();
		if ( ! wp_next_scheduled( Plan_A_Clanstvo_Data::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', Plan_A_Clanstvo_Data::CRON );
		}
		if ( ! get_option( Plan_A_Clanstvo_Data::OPTION ) ) {
			add_option( Plan_A_Clanstvo_Data::OPTION, array( 'secret' => wp_generate_password( 32, false ) ), '', false );
		}
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		wp_clear_scheduled_hook( Plan_A_Clanstvo_Data::CRON );
	}
);

add_action(
	'plugins_loaded',
	static function () {
		Plan_A_Clanstvo_Data::init();
		Plan_A_Clanstvo_Form::init();
		Plan_A_Clanstvo_Sheets::init();
		Plan_A_Clanstvo_Woo::init();
		Plan_A_Clanstvo_Agency::init();
		Plan_A_Clanstvo_Checkout::init();
		Plan_A_Clanstvo_Ops::init();
		if ( is_admin() ) {
			Plan_A_Clanstvo_Admin::init();
		}
	}
);
