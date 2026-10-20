<?php
/**
 * Plugin Name: Domestic Spirits – probna trgovina
 * Description: Statička probna trgovina (mapa shop/) za pregled na kopiji stranice. Adresa: /domestic-spirits/
 * Version: 0.6.0
 * Author: Igor Kutil
 */

defined( 'ABSPATH' ) || exit;

// /domestic-spirits/ → shop/index.html u ovom dodatku (datoteke služi sam web poslužitelj)
add_action(
	'init',
	function () {
		$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
		if ( 'domestic-spirits' === $path ) {
			nocache_headers();
			wp_redirect( plugins_url( 'shop/index.html', __FILE__ ), 302 );
			exit;
		}
	},
	0
);
