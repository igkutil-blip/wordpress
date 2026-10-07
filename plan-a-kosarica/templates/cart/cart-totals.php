<?php
/**
 * Sažetak rezervacije u košarici (Plan A košarica). Zamjenjuje WooCommerce
 * cart/cart-totals.php (verzija 2.3.6). Korijenski element ostaje div.cart_totals
 * (cart.js ga zamjenjuje nakon kupona i uklanjanja), a retci ostaju u tablici jer
 * drugi dodaci u kuke ovog predloška ispisuju retke <tr>.
 *
 * @package Plan_A_Kosarica
 */

defined( 'ABSPATH' ) || exit;

$paka_adjust = Plan_A_Kosarica::has_adjustments();
?>
<div class="cart_totals paka-card paka-summary <?php echo ( WC()->customer->has_calculated_shipping() ) ? 'calculated_shipping' : ''; ?>">

	<?php do_action( 'woocommerce_before_cart_totals' ); ?>

	<h2 class="paka-h2"><?php esc_html_e( 'Sažetak rezervacije', 'plan-a-kosarica' ); ?></h2>

	<table cellspacing="0" class="shop_table paka-sum">
		<tbody>
			<?php
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) :
				$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
				if ( ! ( $_product instanceof WC_Product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) ) {
					continue;
				}
				$paka_tour = Plan_A_Kosarica::tour( $cart_item );
				if ( $paka_tour['id'] && count( WC()->cart->get_cart() ) > 1 ) :
					?>
					<tr class="paka-sum-item"><th colspan="2"><?php echo esc_html( Plan_A_Kosarica::item_name( $cart_item, $cart_item_key ) ); ?></th></tr>
					<?php
				endif;
				foreach ( Plan_A_Kosarica::summary_lines( $cart_item, $cart_item_key ) as $paka_line ) :
					?>
					<tr class="paka-sum-line">
						<th><?php echo esc_html( $paka_line[0] ); ?></th>
						<td><?php echo wp_kses_post( $paka_line[1] ); ?></td>
					</tr>
					<?php
				endforeach;
			endforeach;
			?>

			<tr class="cart-subtotal<?php echo $paka_adjust ? '' : ' paka-hidden'; ?>">
				<th><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></th>
				<td data-title="<?php esc_attr_e( 'Subtotal', 'woocommerce' ); ?>"><?php wc_cart_totals_subtotal_html(); ?></td>
			</tr>

			<?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
				<tr class="cart-discount coupon-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
					<th><?php wc_cart_totals_coupon_label( $coupon ); ?></th>
					<td data-title="<?php echo esc_attr( wc_cart_totals_coupon_label( $coupon, false ) ); ?>"><?php wc_cart_totals_coupon_html( $coupon ); ?></td>
				</tr>
			<?php endforeach; ?>

			<?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>

				<?php do_action( 'woocommerce_cart_totals_before_shipping' ); ?>

				<?php wc_cart_totals_shipping_html(); ?>

				<?php do_action( 'woocommerce_cart_totals_after_shipping' ); ?>

			<?php elseif ( WC()->cart->needs_shipping() && 'yes' === get_option( 'woocommerce_enable_shipping_calc' ) ) : ?>

				<tr class="shipping">
					<th><?php esc_html_e( 'Shipping', 'woocommerce' ); ?></th>
					<td data-title="<?php esc_attr_e( 'Shipping', 'woocommerce' ); ?>"><?php woocommerce_shipping_calculator(); ?></td>
				</tr>

			<?php endif; ?>

			<?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
				<tr class="fee">
					<th><?php echo esc_html( $fee->name ); ?></th>
					<td data-title="<?php echo esc_attr( $fee->name ); ?>"><?php wc_cart_totals_fee_html( $fee ); ?></td>
				</tr>
			<?php endforeach; ?>

			<?php
			if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) {
				$taxable_address = WC()->customer->get_taxable_address();
				$estimated_text  = '';

				if ( WC()->customer->is_customer_outside_base() && ! WC()->customer->has_calculated_shipping() ) {
					/* translators: %s location. */
					$estimated_text = sprintf( ' <small>' . esc_html__( '(estimated for %s)', 'woocommerce' ) . '</small>', WC()->countries->estimated_for_prefix( $taxable_address[0] ) . WC()->countries->countries[ $taxable_address[0] ] );
				}

				if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) {
					foreach ( WC()->cart->get_tax_totals() as $code => $tax ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
						?>
						<tr class="tax-rate tax-rate-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
							<th><?php echo esc_html( $tax->label ) . $estimated_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
							<td data-title="<?php echo esc_attr( $tax->label ); ?>"><?php echo wp_kses_post( $tax->formatted_amount ); ?></td>
						</tr>
						<?php
					}
				} else {
					?>
					<tr class="tax-total">
						<th><?php echo esc_html( WC()->countries->tax_or_vat() ) . $estimated_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
						<td data-title="<?php echo esc_attr( WC()->countries->tax_or_vat() ); ?>"><?php wc_cart_totals_taxes_total_html(); ?></td>
					</tr>
					<?php
				}
			}
			?>

			<?php do_action( 'woocommerce_cart_totals_before_order_total' ); ?>

			<tr class="order-total">
				<th><?php esc_html_e( 'Ukupno za uplatu', 'plan-a-kosarica' ); ?></th>
				<td data-title="<?php esc_attr_e( 'Total', 'woocommerce' ); ?>"><?php wc_cart_totals_order_total_html(); ?></td>
			</tr>

			<?php do_action( 'woocommerce_cart_totals_after_order_total' ); ?>
		</tbody>
	</table>

	<div class="wc-proceed-to-checkout">
		<?php do_action( 'woocommerce_proceed_to_checkout' ); ?>
	</div>

	<?php do_action( 'woocommerce_after_cart_totals' ); ?>

</div>
