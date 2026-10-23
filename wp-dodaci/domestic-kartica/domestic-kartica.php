<?php
/**
 * Plugin Name: Domestic – kartica proizvoda
 * Description: Vlastiti izgled kartice proizvoda u trgovini, kategorijama i povezanim proizvodima (WooCommerce + Flatsome): raspored i redoslijed elemenata, oznake, marka, zapremnina i alkohol, cijena po litri, gumb, boje i dodatni CSS. Izbornik Domestic.
 * Version: 0.5.0
 * Author: Vertikal
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Text Domain: domestic-kartica
 */

defined( 'ABSPATH' ) || exit;

define( 'DSK_VER', '0.5.0' );
define( 'DSK_FILE', __FILE__ );
define( 'DSK_DIR', __DIR__ );
define( 'DSK_URL', plugin_dir_url( __FILE__ ) );

require_once DSK_DIR . '/includes/domestic-menu.php';
require_once DSK_DIR . '/includes/class-domestic-kartica.php';
require_once DSK_DIR . '/includes/class-domestic-kartica-trgovina.php';
require_once DSK_DIR . '/includes/class-domestic-kartica-trazilica.php';
require_once DSK_DIR . '/includes/class-domestic-kartica-zaglavlje.php';
require_once DSK_DIR . '/includes/class-domestic-kartica-admin.php';

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', DSK_FILE, true );
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		Domestic_Kartica::init();
		if ( is_admin() ) {
			Domestic_Kartica_Admin::init();
		}
	}
);
