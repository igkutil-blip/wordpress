<?php
/**
 * Plugin Name: Domestic – slike proizvoda
 * Description: Uvoz paketa slika za proizvod (ambijentalna na staru adresu glavne slike, studijska, kartica posluživanja, ikone izrezane s kartice, galerija, „Kako poslužiti”), zamjena slike na istoj adresi (SEO), uklanjanje metapodataka, popis „Za provjeru”. Izbornik Domestic.
 * Version: 0.4.3
 * Author: Vertikal
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Text Domain: domestic-slike
 */

defined( 'ABSPATH' ) || exit;

define( 'DSS_VER', '0.4.3' );
define( 'DSS_DIR', __DIR__ );
define( 'DSS_URL', plugin_dir_url( __FILE__ ) );

require_once DSS_DIR . '/includes/domestic-menu.php';
require_once DSS_DIR . '/includes/class-domestic-slike.php';
require_once DSS_DIR . '/includes/class-domestic-slike-meta.php';
require_once DSS_DIR . '/includes/class-domestic-slike-paket.php';
require_once DSS_DIR . '/includes/class-domestic-slike-admin.php';

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		Domestic_Slike::init();
		Domestic_Slike_Meta::init();
		if ( is_admin() ) {
			Domestic_Slike_Admin::init();
		}
	}
);
