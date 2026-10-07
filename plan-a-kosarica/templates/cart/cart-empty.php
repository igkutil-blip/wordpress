<?php
/**
 * Prazna košarica (Plan A košarica). Zamjenjuje WooCommerce cart/cart-empty.php (verzija 7.0.1).
 *
 * Zadana poruka i gumb "Povratak u trgovinu" zamijenjeni su blokom s gumbom "Pogledaj izlete";
 * kuka woocommerce_cart_is_empty i dalje se izvršava za druge dodatke.
 *
 * @package Plan_A_Kosarica
 */

defined( 'ABSPATH' ) || exit;

$paka_removed = remove_action( 'woocommerce_cart_is_empty', 'wc_empty_cart_message', 10 );
do_action( 'woocommerce_cart_is_empty' );
if ( $paka_removed ) {
	add_action( 'woocommerce_cart_is_empty', 'wc_empty_cart_message', 10 );
}

$paka_wa = (string) Plan_A_Kosarica_Settings::get( 'whatsapp' );
$paka_wa = '' !== $paka_wa ? $paka_wa : '385959060556';
?>
<div class="paka paka-empty">
	<?php // Klasa wc-empty-cart-message: po njoj WooCommerce prepoznaje praznu košaricu nakon uklanjanja zadnje stavke. ?>
	<section class="paka-empty__box wc-empty-cart-message">
		<span class="paka-empty__icon"><?php echo Plan_A_Kosarica::icon( 'backpack' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- statički SVG. ?></span>
		<h1 class="paka-title"><?php esc_html_e( 'Tvoja košarica je prazna', 'plan-a-kosarica' ); ?></h1>
		<p class="paka-subtitle"><?php esc_html_e( 'Odaberi izlet i rezerviraj mjesto u nekoliko koraka.', 'plan-a-kosarica' ); ?></p>
		<a class="paka-btn paka-btn--primary" href="<?php echo esc_url( Plan_A_Kosarica::more_url() ); ?>"><span><?php esc_html_e( 'Pogledaj izlete', 'plan-a-kosarica' ); ?></span><?php echo Plan_A_Kosarica::icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- statički SVG. ?></a>
	</section>

	<?php if ( shortcode_exists( 'plan-a-izleti' ) ) : ?>
		<section class="paka-empty__tours" aria-labelledby="paka-empty-tours">
			<h2 class="paka-h2" id="paka-empty-tours"><?php esc_html_e( 'Najbliži izleti', 'plan-a-kosarica' ); ?></h2>
			<?php echo do_shortcode( '[plan-a-izleti show="3" all_url="/izleti/"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- izlaz shortcodea dodatka Plan A izleti. ?>
		</section>
	<?php endif; ?>

	<p class="paka-empty__help">
		<?php esc_html_e( 'Ne znaš koji izlet odabrati?', 'plan-a-kosarica' ); ?>
		<a href="<?php echo esc_url( 'https://wa.me/' . rawurlencode( $paka_wa ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Javi nam se na WhatsApp', 'plan-a-kosarica' ); ?></a>
	</p>
</div>
