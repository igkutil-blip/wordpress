<?php
/**
 * Kartica proizvoda (Domestic). Zamjenjuje WooCommerceov i Flatsomeov content-product.php dok je dodatak uključen.
 * U Flatsomeu je omot div.product-small.col (mreža .products.row), inače li (ul.products).
 */

defined( 'ABSPATH' ) || exit;

global $product;
if ( ! $product instanceof WC_Product || ! $product->is_visible() ) {
	return;
}

$s          = Domestic_Kartica::postavke();
$flatsome   = 'flatsome' === get_template();
$url        = $product->get_permalink();
$zapremnina = Domestic_Kartica::atribut( $product, array( 'zapremnina', 'volumen', 'kolicina' ) );
$alkohol    = Domestic_Kartica::atribut( $product, array( 'alkohol', 'alkohol vol', 'udio alkohola' ) );
$oznake     = Domestic_Kartica::oznake( $product );
$klase      = array( 'dsk-kartica', $flatsome ? 'product-small col' : '', 'yes' === $s['okvir'] ? 'dsk-s-okvirom' : '', 'yes' === $s['sjena'] ? 'dsk-sa-sjenom' : '' );

echo $flatsome ? '<div ' : '<li ';
wc_product_class( array_filter( $klase ), $product );
echo '>';
?>
	<div class="dsk-unutra<?php echo $flatsome ? ' col-inner' : ''; ?>">
		<div class="dsk-slika">
			<a href="<?php echo esc_url( $url ); ?>" class="dsk-slika-link" tabindex="-1" aria-hidden="true">
				<?php echo $product->get_image( 'woocommerce_single', array( 'class' => 'dsk-img', 'sizes' => '(max-width: 549px) 50vw, (max-width: 849px) 34vw, 320px' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</a>
			<?php if ( $oznake ) : ?>
				<div class="dsk-oznake">
					<?php foreach ( $oznake as $k => $t ) : ?>
						<span class="dsk-oznaka dsk-oznaka-<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $t ); ?></span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php if ( $flatsome && 'yes' === $s['brzi'] ) : ?>
				<a href="#quick-view" class="quick-view dsk-brzi" role="button" data-prod="<?php echo (int) $product->get_id(); ?>" aria-haspopup="dialog" aria-expanded="false" data-flatsome-role-button>Brzi pregled</a>
			<?php endif; ?>
			<?php do_action( 'domestic_kartica_slika', $product ); ?>
		</div>
		<div class="dsk-tekst">
			<?php
			foreach ( $s['redoslijed'] as $el ) {
				switch ( $el ) {
					case 'kategorija':
						$t = get_the_terms( $product->get_id(), 'product_cat' );
						if ( is_array( $t ) && $t ) {
							echo '<p class="dsk-kategorija">' . esc_html( $t[0]->name ) . '</p>';
						}
						break;
					case 'marka':
						$m = Domestic_Kartica::marka( $product );
						if ( '' !== $m ) {
							echo '<p class="dsk-marka">' . esc_html( $m ) . '</p>';
						}
						break;
					case 'naziv':
						echo '<h2 class="dsk-naziv woocommerce-loop-product__title"><a href="' . esc_url( $url ) . '" class="woocommerce-LoopProduct-link woocommerce-loop-product__link">' . esc_html( $product->get_name() ) . '</a></h2>';
						break;
					case 'podaci':
						$d = array_filter( array( $zapremnina, $alkohol ) );
						if ( $d ) {
							echo '<p class="dsk-podaci">' . esc_html( implode( ' · ', $d ) ) . '</p>';
						}
						break;
					case 'kratki':
						$k = wp_strip_all_tags( $product->get_short_description() );
						if ( '' !== $k ) {
							echo '<p class="dsk-kratki">' . esc_html( wp_trim_words( $k, 18 ) ) . '</p>';
						}
						break;
					case 'cijena':
						$c = $product->get_price_html(); // filtri (npr. sidrena cijena) ostaju
						if ( $c ) {
							echo '<div class="dsk-cijena price-wrapper"><span class="price">' . $c . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
						}
						break;
					case 'jedinicna':
						$j = Domestic_Kartica::jedinicna( $product, $zapremnina );
						if ( '' !== $j ) {
							echo '<p class="dsk-jedinicna">' . wp_kses_post( $j ) . '</p>';
						}
						break;
					case 'gumb':
						$tekst = static function ( $t, $p ) use ( $s ) {
							return $p->is_type( 'simple' ) && $p->is_purchasable() && $p->is_in_stock() && '' !== trim( $s['gumb_tekst'] ) ? $s['gumb_tekst'] : $t;
						};
						add_filter( 'woocommerce_product_add_to_cart_text', $tekst, 99, 2 );
						echo '<div class="dsk-gumb">';
						if ( function_exists( 'woocommerce_template_loop_add_to_cart' ) ) {
							woocommerce_template_loop_add_to_cart();
						}
						echo '</div>';
						remove_filter( 'woocommerce_product_add_to_cart_text', $tekst, 99 );
						break;
				}
				do_action( 'domestic_kartica_poslije_' . $el, $product );
			}
			?>
		</div>
	</div>
<?php
echo $flatsome ? '</div>' : '</li>';
