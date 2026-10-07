<?php
/**
 * Plaćanje (Plan A košarica). Zamjenjuje WooCommerce checkout/form-checkout.php
 * (verzija 9.4.0) i zadržava sve njegove kuke te oznake na koje se oslanja
 * checkout.js (form.checkout, #customer_details, #order_review).
 *
 * @package Plan_A_Kosarica
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="paka paka-checkout">
	<?php Plan_A_Kosarica::steps( 2 ); ?>

	<header class="paka-head">
		<p class="paka-back"><a href="<?php echo esc_url( wc_get_cart_url() ); ?>"><?php echo Plan_A_Kosarica::icon( 'back' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'Povratak u košaricu', 'plan-a-kosarica' ); ?></span></a></p>
		<h1 class="paka-title"><?php esc_html_e( 'Dovrši rezervaciju', 'plan-a-kosarica' ); ?></h1>
		<p class="paka-subtitle"><?php esc_html_e( 'Unesi podatke i provjeri svoj izlet.', 'plan-a-kosarica' ); ?></p>
	</header>

	<?php
	do_action( 'woocommerce_before_checkout_form', $checkout );

	// If checkout registration is disabled and not logged in, the user cannot checkout.
	if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
		echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
		echo '</div>';
		return;
	}
	?>

	<form name="checkout" method="post" class="checkout woocommerce-checkout paka-layout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data" aria-label="<?php echo esc_attr__( 'Checkout', 'woocommerce' ); ?>">

		<div class="paka-main">
			<?php if ( $checkout->get_checkout_fields() ) : ?>

				<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

				<div class="col2-set paka-customer" id="customer_details">
					<div class="col-1 paka-card paka-card--billing">
						<?php do_action( 'woocommerce_checkout_billing' ); ?>
					</div>

					<div class="col-2 paka-card paka-card--more">
						<?php do_action( 'woocommerce_checkout_shipping' ); ?>
					</div>
				</div>

				<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>

			<?php endif; ?>
		</div>

		<div class="paka-side">
			<?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>

			<h2 id="order_review_heading" class="paka-h2 paka-review-heading"><?php esc_html_e( 'Tvoja rezervacija', 'plan-a-kosarica' ); ?></h2>

			<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

			<div id="order_review" class="woocommerce-checkout-review-order">
				<?php do_action( 'woocommerce_checkout_order_review' ); ?>
			</div>

			<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>

			<?php Plan_A_Kosarica::help_links( false ); ?>
		</div>

	</form>

	<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
</div>
