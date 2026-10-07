<?php
/**
 * Košarica (Plan A košarica). Zamjenjuje WooCommerce cart/cart.php (verzija 11.0.0)
 * i zadržava sve njegove kuke, filtre i oznake na koje se oslanja cart.js:
 * form.woocommerce-cart-form, .woocommerce-cart-form__contents, .cart_item,
 * .product-remove > a, #coupon_code, gumbi apply_coupon i update_cart, nonce.
 *
 * @package Plan_A_Kosarica
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_cart' );

$paka_has_qty_input = false;
?>
<div class="paka paka-cart">
	<?php Plan_A_Kosarica::steps( 1 ); ?>

	<header class="paka-head">
		<h1 class="paka-title"><?php esc_html_e( 'Tvoja košarica', 'plan-a-kosarica' ); ?></h1>
		<p class="paka-subtitle"><?php esc_html_e( 'Još samo nekoliko koraka do avanture.', 'plan-a-kosarica' ); ?></p>
	</header>

	<div class="paka-layout">
		<div class="paka-main">
			<form class="woocommerce-cart-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
				<?php do_action( 'woocommerce_before_cart_table' ); ?>

				<div class="paka-items woocommerce-cart-form__contents">
					<?php do_action( 'woocommerce_before_cart_contents' ); ?>

					<?php
					foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
						$_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
						$product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );
						$visible    = apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key );

						if ( ! ( $_product instanceof WC_Product && $_product->exists() && $cart_item['quantity'] > 0 && $visible ) ) {
							continue;
						}

						$product_name      = apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key );
						$product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
						$tour              = Plan_A_Kosarica::tour( $cart_item );
						$is_tour           = $tour['id'] > 0;
						$structured        = $is_tour && ( $tour['tickets'] || $tour['date'] );

						if ( $is_tour ) {
							$product_permalink = get_permalink( $tour['id'] );
						}

						$thumbnail = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key );
						if ( $is_tour ) {
							$thumbnail = Plan_A_Kosarica::tour_image( $tour['id'], $thumbnail );
						}

						// Količina: izleti su "sold individually" (broj sudionika je u kartama), ostali proizvodi imaju polje.
						if ( $_product->is_sold_individually() ) {
							$min_quantity = 1;
							$max_quantity = 1;
						} else {
							$min_quantity       = 0;
							$max_quantity       = $_product->get_max_purchase_quantity();
							$paka_has_qty_input = true;
						}
						$product_quantity = woocommerce_quantity_input(
							array(
								'input_name'   => "cart[{$cart_item_key}][qty]",
								'input_value'  => $cart_item['quantity'],
								'max_value'    => $max_quantity,
								'min_value'    => $min_quantity,
								'product_name' => $product_name,
							),
							$_product,
							false
						);
						?>
						<article class="paka-card paka-item woocommerce-cart-form__cart-item <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">
							<div class="paka-item__top">
								<div class="paka-item__media product-thumbnail">
									<?php
									if ( $product_permalink ) {
										printf( '<a href="%s" tabindex="-1">%s</a>', esc_url( $product_permalink ), $thumbnail ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML slike iz WooCommercea.
									} else {
										echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									}
									?>
								</div>

								<div class="paka-item__info product-name">
									<h2 class="paka-item__title">
										<?php
										if ( ! $product_permalink ) {
											echo wp_kses_post( $product_name );
										} else {
											echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', sprintf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $_product->get_name() ), $cart_item, $cart_item_key ) );
										}
										?>
									</h2>
									<?php do_action( 'woocommerce_after_cart_item_name', $cart_item, $cart_item_key ); ?>
									<?php echo Plan_A_Kosarica::meta_list( $tour, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
								</div>

								<div class="paka-item__remove product-remove">
									<?php
									echo apply_filters( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										'woocommerce_cart_item_remove_link',
										sprintf(
											'<a role="button" href="%s" class="remove" aria-label="%s" data-product_id="%s" data-product_sku="%s">%s</a>',
											esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
											/* translators: %s is the product name */
											esc_attr( sprintf( __( 'Remove %s from cart', 'woocommerce' ), wp_strip_all_tags( $product_name ) ) ),
											esc_attr( $product_id ),
											esc_attr( $_product->get_sku() ),
											Plan_A_Kosarica::icon( 'trash' )
										),
										$cart_item_key
									);
									?>
								</div>
							</div>

							<div class="paka-item__rows">
								<?php if ( $is_tour && $tour['tickets'] ) : ?>
									<?php if ( 1 === count( $tour['tickets'] ) ) : ?>
										<?php $ticket = $tour['tickets'][0]; ?>
										<div class="paka-row">
											<span class="paka-row__label"><?php esc_html_e( 'Broj sudionika', 'plan-a-kosarica' ); ?><?php echo '' !== $ticket['name'] ? ' <small>(' . esc_html( $ticket['name'] ) . ')</small>' : ''; ?></span>
											<span class="paka-pill"><?php echo esc_html( (string) $ticket['qty'] ); ?></span>
										</div>
										<div class="paka-row">
											<span class="paka-row__label"><?php echo esc_html( $ticket['days'] ? __( 'Cijena po osobi i danu', 'plan-a-kosarica' ) : __( 'Cijena po osobi', 'plan-a-kosarica' ) ); ?></span>
											<strong class="paka-row__value"><?php echo wp_kses_post( $ticket['price'] ); ?></strong>
										</div>
									<?php else : ?>
										<p class="paka-rows-title"><?php esc_html_e( 'Sudionici', 'plan-a-kosarica' ); ?></p>
										<?php foreach ( $tour['tickets'] as $ticket ) : ?>
											<div class="paka-row">
												<span class="paka-row__label"><?php echo esc_html( $ticket['name'] ); ?> <small>× <?php echo wp_kses_post( $ticket['price'] ); ?></small></span>
												<span class="paka-pill"><?php echo esc_html( (string) $ticket['qty'] ); ?></span>
											</div>
										<?php endforeach; ?>
									<?php endif; ?>
								<?php endif; ?>

								<?php if ( $is_tour && $tour['services'] ) : ?>
									<p class="paka-rows-title"><?php esc_html_e( 'Dodatne usluge', 'plan-a-kosarica' ); ?></p>
									<?php foreach ( $tour['services'] as $service ) : ?>
										<div class="paka-row">
											<span class="paka-row__label"><?php echo esc_html( $service['name'] ); ?> <small>× <?php echo wp_kses_post( $service['price'] ); ?></small></span>
											<span class="paka-pill"><?php echo esc_html( (string) $service['qty'] ); ?></span>
										</div>
									<?php endforeach; ?>
								<?php endif; ?>

								<?php
								// Ostali podaci stavke (varijacije, podaci drugih dodataka) i kuke WpTravellyja za dodatke.
								$paka_data = Plan_A_Kosarica::item_data( $cart_item, $structured );
								if ( '' !== $paka_data ) {
									echo '<div class="paka-item__data">' . $paka_data . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- predložak WooCommercea.
								}
								if ( $structured ) {
									echo Plan_A_Kosarica::tour_hooks( $cart_item, $tour['id'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- izlaz kuka WpTravellyja.
								}

								if ( $_product->backorders_require_notification() && $_product->is_on_backorder( $cart_item['quantity'] ) ) {
									echo wp_kses_post( apply_filters( 'woocommerce_cart_item_backorder_notification', '<p class="backorder_notification">' . esc_html__( 'Available on backorder', 'woocommerce' ) . '</p>', $product_id ) );
								}
								?>

								<?php if ( ! $is_tour || ! $tour['tickets'] ) : ?>
									<div class="paka-row product-price">
										<span class="paka-row__label"><?php esc_html_e( 'Price', 'woocommerce' ); ?></span>
										<strong class="paka-row__value"><?php echo apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
									</div>
								<?php endif; ?>

								<div class="paka-row product-quantity<?php echo $_product->is_sold_individually() ? ' paka-hidden' : ''; ?>">
									<span class="paka-row__label"><?php esc_html_e( 'Quantity', 'woocommerce' ); ?></span>
									<?php echo apply_filters( 'woocommerce_cart_item_quantity', $product_quantity, $cart_item_key, $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</div>

								<?php if ( ( $is_tour && ( count( $tour['tickets'] ) > 1 || $tour['services'] ) ) || ( ! $is_tour && $cart_item['quantity'] > 1 ) || ( ! $is_tour && ! $_product->is_sold_individually() ) ) : ?>
									<div class="paka-row paka-row--total product-subtotal">
										<span class="paka-row__label"><?php esc_html_e( 'Ukupno za stavku', 'plan-a-kosarica' ); ?></span>
										<strong class="paka-row__value"><?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
									</div>
								<?php endif; ?>
							</div>
						</article>
						<?php
					}
					?>

					<?php do_action( 'woocommerce_cart_contents' ); ?>

					<div class="paka-actions actions<?php echo $paka_has_qty_input ? '' : ' paka-actions--no-qty'; ?>">
						<?php if ( wc_coupons_enabled() ) : ?>
							<details class="paka-coupon">
								<summary><?php esc_html_e( 'Imaš kod za popust?', 'plan-a-kosarica' ); ?></summary>
								<div class="coupon">
									<label for="coupon_code" class="screen-reader-text"><?php esc_html_e( 'Coupon:', 'woocommerce' ); ?></label>
									<input type="text" name="coupon_code" class="input-text" id="coupon_code" value="" placeholder="<?php esc_attr_e( 'Coupon code', 'woocommerce' ); ?>" />
									<button type="submit" class="button paka-btn paka-btn--ghost" name="apply_coupon" value="<?php esc_attr_e( 'Apply coupon', 'woocommerce' ); ?>"><?php esc_html_e( 'Apply coupon', 'woocommerce' ); ?></button>
									<?php do_action( 'woocommerce_cart_coupon' ); ?>
								</div>
							</details>
						<?php endif; ?>

						<button type="submit" class="button paka-btn paka-btn--ghost paka-update" name="update_cart" value="<?php esc_attr_e( 'Update cart', 'woocommerce' ); ?>"><?php esc_html_e( 'Update cart', 'woocommerce' ); ?></button>

						<?php
						ob_start();
						do_action( 'woocommerce_cart_actions' );
						echo Plan_A_Kosarica::cart_actions( (string) ob_get_clean() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- izlaz kuke; zamijenjen je samo gumb "Nastavite kupnju".
						?>

						<?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
					</div>

					<?php do_action( 'woocommerce_after_cart_contents' ); ?>
				</div>

				<?php do_action( 'woocommerce_after_cart_table' ); ?>
			</form>
		</div>

		<aside class="paka-side">
			<?php do_action( 'woocommerce_before_cart_collaterals' ); ?>

			<div class="cart-collaterals">
				<?php
				/**
				 * @hooked woocommerce_cross_sell_display
				 * @hooked woocommerce_cart_totals - 10
				 */
				do_action( 'woocommerce_cart_collaterals' );
				?>
			</div>

			<?php Plan_A_Kosarica::help_links( true ); ?>
		</aside>
	</div>
</div>

<?php do_action( 'woocommerce_after_cart' ); ?>
