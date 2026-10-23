<?php
/**
 * Plugin Name: Domestic – provjera dobi (mGrađani)
 * Description: Provjera punoljetnosti kupca putem sustava e-Građani / mGrađani prije kupnje alkohola (čl. 11.a Zakona o trgovini, NN 59/26). OIDC + PKCE (S256), RS256 provjera tokena, bez pohrane osobnih podataka. Izbornik Domestic.
 * Version: 0.1.0
 * Author: Vertikal
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Text Domain: domestic-provjera-dobi
 */

defined( 'ABSPATH' ) || exit;

define( 'DSD_VER', '0.1.0' );
define( 'DSD_FILE', __FILE__ );
define( 'DSD_DIR', __DIR__ );
define( 'DSD_URL', plugin_dir_url( __FILE__ ) );

require_once DSD_DIR . '/includes/domestic-menu.php';
require_once DSD_DIR . '/includes/class-domestic-dob-postavke.php';
require_once DSD_DIR . '/includes/class-domestic-dob-oidc.php';
require_once DSD_DIR . '/includes/class-domestic-dob-tok.php';
require_once DSD_DIR . '/includes/class-domestic-dob-blagajna.php';
require_once DSD_DIR . '/includes/class-domestic-dob-admin.php';

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', DSD_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', DSD_FILE, true );
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		Domestic_Dob_Tok::init();
		Domestic_Dob_Blagajna::init();
		if ( is_admin() ) {
			Domestic_Dob_Admin::init();
		}
	}
);

register_activation_hook(
	__FILE__,
	function () {
		Domestic_Dob_Tok::rewrite();
		flush_rewrite_rules();
	}
);

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
