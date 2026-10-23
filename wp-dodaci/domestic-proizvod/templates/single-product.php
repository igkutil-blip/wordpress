<?php
/**
 * Stranica proizvoda u izgledu Domestic (zaglavlje i podnožje ostaju od teme).
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

while ( have_posts() ) :
	the_post();
	global $product;
	$product = wc_get_product( get_the_ID() );
	?>
	<div class="dsp-wrap">
		<?php
		echo Domestic_Proizvod_Front::crumb( $product ); // phpcs:ignore WordPress.Security.EscapeOutput
		add_filter( 'woocommerce_product_single_add_to_cart_text', function ( $t ) { return 'Dodaj u košaricu'; } );
		if ( post_password_required() ) {
			echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput
		} else {
			// obavijesti WooCommercea („dodano u košaricu” i sl.) i kuke drugih dodataka
			do_action( 'woocommerce_before_single_product' );
			echo '<div id="product-' . (int) $product->get_id() . '" class="' . esc_attr( implode( ' ', wc_get_product_class( 'dsp-product-wrap', $product ) ) ) . '">';
			echo Domestic_Proizvod_Front::render( $product ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</div>';
			if ( isset( WC()->structured_data ) ) {
				WC()->structured_data->generate_product_data( $product );
			}
			do_action( 'woocommerce_after_single_product' );
		}
		?>
	</div>
	<?php
endwhile;

get_footer( 'shop' );
