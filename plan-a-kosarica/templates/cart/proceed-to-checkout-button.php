<?php
/**
 * Gumb "Nastavi na plaćanje" (Plan A košarica). Zamjenjuje WooCommerce
 * cart/proceed-to-checkout-button.php (verzija 7.0.1); klase gumba ostaju iste.
 *
 * @package Plan_A_Kosarica
 */

defined( 'ABSPATH' ) || exit;
?>
<a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="checkout-button button alt wc-forward paka-btn paka-btn--cta<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>">
	<span><?php esc_html_e( 'Nastavi na plaćanje', 'plan-a-kosarica' ); ?></span><?php echo Plan_A_Kosarica::icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- statički SVG. ?>
</a>
