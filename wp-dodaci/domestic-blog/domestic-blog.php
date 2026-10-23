<?php
/**
 * Plugin Name: Domestic – blog
 * Description: Blog (vinarije i proizvođači) u izgledu shopa: popis priča s filtrom po regiji, kartice, uređena objava s tablicom „Za stolom” i proizvodima u ponudi, natpisi na hrvatskom. Samo prikaz – sadržaj u bazi i adrese ostaju isti. Izbornik Domestic.
 * Version: 0.2.0
 * Author: Vertikal
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: domestic-blog
 */

defined( 'ABSPATH' ) || exit;

define( 'DSB_VER', '0.2.0' );
define( 'DSB_DIR', __DIR__ );
define( 'DSB_URL', plugin_dir_url( __FILE__ ) );

require_once DSB_DIR . '/includes/domestic-menu.php';
require_once DSB_DIR . '/includes/class-domestic-blog.php';
require_once DSB_DIR . '/includes/class-domestic-blog-prikaz.php';
require_once DSB_DIR . '/includes/class-domestic-blog-admin.php';

add_action(
	'plugins_loaded',
	function () {
		Domestic_Blog::init();
		if ( is_admin() ) {
			Domestic_Blog_Admin::init();
		}
	}
);
