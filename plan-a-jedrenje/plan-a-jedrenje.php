<?php
/**
 * Plugin Name:       Plan A jedrenje
 * Description:       Rezervacija tjednog jedrenja za ekipu (subota–subota): kalendar tjedana s cijenama za cijeli brod, zahtjev, potvrda s uplatnicom za akontaciju, uplata ostatka. Shortcode [plan-a-jedrenje].
 * Version:           1.6.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Plan A
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       plan-a-jedrenje
 *
 * Koristi WooCommerce (narudžbe s virmanom), dodatak Hub3 (uplatnica s 2D kodom),
 * Plan A košaricu i Plan A poklon bon (plaćanje preko košarice) te gumb "Predloži ekipi"
 * iz dodatka Plan A izleti. Te dodatke ni temu ne mijenja.
 */

defined( 'ABSPATH' ) || exit;

define( 'PLAN_A_JEDRENJE_VERSION', '1.6.0' );
define( 'PLAN_A_JEDRENJE_FILE', __FILE__ );
define( 'PLAN_A_JEDRENJE_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/includes/class-plan-a-jedrenje-data.php';
require_once __DIR__ . '/includes/class-plan-a-jedrenje-mail.php';
require_once __DIR__ . '/includes/class-plan-a-jedrenje-booking.php';
require_once __DIR__ . '/includes/class-plan-a-jedrenje-front.php';
require_once __DIR__ . '/includes/class-plan-a-jedrenje-admin.php';
require_once __DIR__ . '/includes/class-plan-a-jedrenje-slider.php';
require_once __DIR__ . '/includes/class-plan-a-jedrenje-izleti.php';

register_activation_hook(
	__FILE__,
	static function () {
		if ( class_exists( 'WooCommerce' ) ) {
			Plan_A_Jedrenje_Booking::product_id( true );
		}
		Plan_A_Jedrenje_Booking::schedule();
	}
);
register_deactivation_hook(
	__FILE__,
	static function () {
		wp_clear_scheduled_hook( Plan_A_Jedrenje_Booking::CRON );
	}
);

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>Plan A jedrenje treba WooCommerce.</p></div>';
				}
			);
			return;
		}
		Plan_A_Jedrenje_Data::seed();
		Plan_A_Jedrenje_Booking::init();
		Plan_A_Jedrenje_Front::init();
		Plan_A_Jedrenje_Slider::init();
		Plan_A_Jedrenje_Izleti::init();
		if ( is_admin() ) {
			Plan_A_Jedrenje_Admin::init();
		}
	},
	20
);
