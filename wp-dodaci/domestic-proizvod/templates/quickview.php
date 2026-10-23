<?php
/**
 * Brzi pregled proizvoda (Flatsome lightbox) u novom izgledu.
 */

defined( 'ABSPATH' ) || exit;

global $product;
$p = $product instanceof WC_Product ? $product : wc_get_product( get_the_ID() );
if ( $p ) {
	echo Domestic_Proizvod_Front::quickview( $p ); // phpcs:ignore WordPress.Security.EscapeOutput
}
