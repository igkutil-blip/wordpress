<?php
/**
 * Plugin Name: Domestic – stranica proizvoda
 * Description: Novi izgled stranice proizvoda (galerija, kupnja, posluživanje, nagrade, podaci), kartica „Domestic” na proizvodu i provjera podataka. Izbornik Domestic.
 * Version: 0.6.3
 * Author: Vertikal
 * Requires Plugins: woocommerce
 * Text Domain: domestic-proizvod
 */

defined( 'ABSPATH' ) || exit;

define( 'DSP_VER', '0.6.3' );
define( 'DSP_FILE', __FILE__ );
define( 'DSP_DIR', __DIR__ );
define( 'DSP_URL', plugin_dir_url( __FILE__ ) );

require_once DSP_DIR . '/includes/domestic-menu.php';
require_once DSP_DIR . '/includes/class-domestic-proizvod-data.php';
require_once DSP_DIR . '/includes/class-domestic-proizvod-tekst.php';
require_once DSP_DIR . '/includes/class-domestic-proizvod-front.php';
require_once DSP_DIR . '/includes/class-domestic-proizvod-admin.php';

add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		Domestic_Proizvod_Data::init();
		Domestic_Proizvod_Tekst::init();
		Domestic_Proizvod_Front::init();
		if ( is_admin() ) {
			Domestic_Proizvod_Admin::init();
		}
	}
);
