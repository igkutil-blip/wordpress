<?php
/**
 * Pregled rezervacije na plaćanju (Plan A košarica). Zamjenjuje WooCommerce
 * checkout/review-order.php (verzija 11.0.0). Korijen ostaje
 * table.woocommerce-checkout-review-order-table (checkout.js ga osvježava), a
 * sve kuke i retci zbroja ostaju.
 *
 * @package Plan_A_Kosarica
 */

defined( 'ABSPATH' ) || exit;

$paka_adjust = Plan_A_Kosarica::has_adjustments();
?>
<table class="shop_table woocommerce-checkout-review-order-table paka-card paka-review">
	<tbody>
		<?php
		do_action( 'woocommerce_review_order_before_cart_contents' );

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
			$visible  = apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key );

			if ( ! ( $_product instanceof WC_Product && $_product->exists() && $cart_item['quantity'] > 0 && $visible ) ) {
				continue;
			}
			$tour       = Plan_A_Kosarica::tour( $cart_item );
			$is_tour    = $tour['id'] > 0;
			$structured = $is_tour && ( $tour['tickets'] || $tour['date'] );
			$thumbnail  = $is_tour ? Plan_A_Kosarica::tour_image( $tour['id'], '' ) : $_product->get_image( 'woocommerce_thumbnail', array( 'class' => 'paka-item__img' ) );
			?>
			<tr class="<?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?> paka-review-item">
				<td class="product-name" colspan="2">
					<div class="paka-item__top">
						<?php if ( $thumbnail ) : ?>
							<div class="paka-item__media"><?php echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
						<?php endif; ?>
						<div class="paka-item__info">
							<h3 class="paka-item__title">
								<?php echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) ); ?>
								<?php echo $is_tour ? '' : apply_filters( 'woocommerce_checkout_cart_item_quantity', ' <strong class="product-quantity">' . sprintf( '&times;&nbsp;%s', $cart_item['quantity'] ) . '</strong>', $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</h3>
							<?php echo Plan_A_Kosarica::meta_list( $tour, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
						</div>
					</div>
					<?php
					$paka_data = Plan_A_Kosarica::item_data( $cart_item, $structured );
					if ( '' !== $paka_data ) {
						echo '<div class="paka-item__data">' . $paka_data . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					if ( $structured ) {
						echo Plan_A_Kosarica::tour_hooks( $cart_item, $tour['id'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					?>
				</td>
			</tr>
			<?php foreach ( Plan_A_Kosarica::summary_lines( $cart_item, $cart_item_key ) as $paka_line ) : ?>
				<tr class="paka-sum-line">
					<th><?php echo esc_html( $paka_line[0] ); ?></th>
					<td class="product-total"><?php echo wp_kses_post( $paka_line[1] ); ?></td>
				</tr>
			<?php endforeach; ?>
			<?php
		}

		do_action( 'woocommerce_review_order_after_cart_contents' );
		?>
	</tbody>
	<tfoot>

		<tr class="cart-subtotal<?php echo $paka_adjust ? '' : ' paka-hidden'; ?>">
			<th><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></th>
			<td><?php wc_cart_totals_subtotal_html(); ?></td>
		</tr>

		<?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
			<tr class="cart-discount coupon-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
				<th><?php wc_cart_totals_coupon_label( $coupon ); ?></th>
				<td><?php wc_cart_totals_coupon_html( $coupon ); ?></td>
			</tr>
		<?php endforeach; ?>

		<?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>

			<?php do_action( 'woocommerce_review_order_before_shipping' ); ?>

			<?php wc_cart_totals_shipping_html(); ?>

			<?php do_action( 'woocommerce_review_order_after_shipping' ); ?>

		<?php endif; ?>

		<?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
			<tr class="fee">
				<th><?php echo esc_html( $fee->name ); ?></th>
				<td><?php wc_cart_totals_fee_html( $fee ); ?></td>
			</tr>
		<?php endforeach; ?>

		<?php if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) : ?>
			<?php if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) : ?>
				<?php foreach ( WC()->cart->get_tax_totals() as $code => $tax ) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited ?>
					<tr class="tax-rate tax-rate-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
						<th><?php echo esc_html( $tax->label ); ?></th>
						<td><?php echo wp_kses_post( $tax->formatted_amount ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr class="tax-total">
					<th><?php echo esc_html( WC()->countries->tax_or_vat() ); ?></th>
					<td><?php wc_cart_totals_taxes_total_html(); ?></td>
				</tr>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( wc_coupons_enabled() ) : ?>
			<?php // Polje za kod (poklon bon ili popust) uz iznos za uplatu; primjenjuje se bez slanja obrasca (kosarica.js). ?>
			<tr class="paka-code-row">
				<td colspan="2">
					<details class="paka-coupon paka-code" data-paka-code>
						<summary><?php esc_html_e( 'Imaš kod za popust?', 'plan-a-kosarica' ); ?></summary>
						<div class="coupon">
							<label for="paka_code" class="screen-reader-text"><?php esc_html_e( 'Upiši kod', 'plan-a-kosarica' ); ?></label>
							<input type="text" id="paka_code" class="input-text" value="" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="<?php esc_attr_e( 'Upiši kod', 'plan-a-kosarica' ); ?>">
							<button type="button" class="button paka-btn paka-btn--ghost" data-paka-apply><?php esc_html_e( 'Iskoristi', 'plan-a-kosarica' ); ?></button>
						</div>
						<p class="paka-code__msg" data-paka-code-msg role="status" aria-live="polite"></p>
					</details>
				</td>
			</tr>
		<?php endif; ?>

		<?php do_action( 'woocommerce_review_order_before_order_total' ); ?>

		<tr class="order-total">
			<th><?php esc_html_e( 'Ukupno za uplatu', 'plan-a-kosarica' ); ?></th>
			<td><?php wc_cart_totals_order_total_html(); ?></td>
		</tr>

		<?php do_action( 'woocommerce_review_order_after_order_total' ); ?>

	</tfoot>
</table>
