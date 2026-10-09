<?php
/**
 * Završna stranica narudžbe (Plan A košarica). Zamjenjuje WooCommerce
 * checkout/thankyou.php (verzija 8.1.0).
 *
 * Sve kuke ostaju: izlaz kuka woocommerce_thankyou_{način plaćanja} i
 * woocommerce_thankyou (tu Hub3 / bankovni prijenos ispisuju 2D kod i podatke za
 * plaćanje) prikazuje se nepromijenjen u bloku "Podaci za plaćanje". Standardna
 * tablica narudžbe (woocommerce_order_details_table) zamijenjena je blokovima
 * "Vaš izlet" i "Vaši podaci"; kuke te tablice za druge dodatke i dalje se izvršavaju.
 *
 * @package Plan_A_Kosarica
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="woocommerce-order paka paka-received">
	<?php
	if ( ! $order ) :
		wc_get_template( 'checkout/order-received.php', array( 'order' => false ) );
		echo '</div>';
		return;
	endif;

	do_action( 'woocommerce_before_thankyou', $order->get_id() );

	$paka_paid    = Plan_A_Kosarica_Order::is_paid( $order );
	$paka_failed  = $order->has_status( 'failed' );
	$paka_method  = $order->get_payment_method();

	// Izlaz dodataka za plaćanje (Hub3, bankovni prijenos …), nepromijenjen.
	$paka_pay_html = Plan_A_Kosarica_Order::capture( 'woocommerce_thankyou_' . $paka_method, $order->get_id() );

	// Standardnu tablicu narudžbe zamjenjuju blokovi ispod; ostali dodaci na ovoj kuki ostaju.
	$paka_removed = remove_action( 'woocommerce_thankyou', 'woocommerce_order_details_table', 10 );
	$paka_generic = Plan_A_Kosarica_Order::capture( 'woocommerce_thankyou', $order->get_id() );
	if ( $paka_removed ) {
		add_action( 'woocommerce_thankyou', 'woocommerce_order_details_table', 10 );
	}

	// Kuke standardne tablice narudžbe (neki dodaci za uplatnicu ispisuju tu).
	$paka_details  = Plan_A_Kosarica_Order::capture( 'woocommerce_order_details_before_order_table', $order );
	$paka_details .= Plan_A_Kosarica_Order::capture( 'woocommerce_order_details_after_order_table', $order );
	$paka_details .= Plan_A_Kosarica_Order::capture( 'woocommerce_after_order_details', $order );
	$paka_customer = Plan_A_Kosarica_Order::capture( 'woocommerce_order_details_after_customer_details', $order );

	$paka_payment_block = ! $paka_paid && ! $paka_failed ? trim( $paka_pay_html . $paka_generic . $paka_details ) : '';
	$paka_payment_block = Plan_A_Kosarica_Order::has_content( $paka_payment_block ) ? $paka_payment_block : '';
	$paka_other         = $paka_payment_block ? '' : trim( $paka_generic . $paka_details );
	$paka_other         = Plan_A_Kosarica_Order::has_content( $paka_other ) ? $paka_other : '';

	$paka_created = $order->get_date_created();
	$paka_first   = trim( (string) $order->get_billing_first_name() );

	Plan_A_Kosarica::steps( 3, true );
	?>

	<header class="paka-card paka-done">
		<?php if ( $paka_failed ) : ?>
			<span class="paka-done__icon paka-done__icon--error" aria-hidden="true">!</span>
			<h1 class="paka-title"><?php esc_html_e( 'Plaćanje nije uspjelo', 'plan-a-kosarica' ); ?></h1>
			<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed"><?php esc_html_e( 'Banka ili kartična kuća odbila je transakciju. Pokušajte ponovno.', 'plan-a-kosarica' ); ?></p>
			<p class="woocommerce-thankyou-order-failed-actions">
				<a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="button pay paka-btn paka-btn--ghost"><?php esc_html_e( 'Plati ponovno', 'plan-a-kosarica' ); ?></a>
			</p>
		<?php else : ?>
			<span class="paka-done__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
			</span>
			<h1 class="paka-title">
				<?php
				echo esc_html(
					'' !== $paka_first
						/* translators: %s: ime kupca */
						? sprintf( __( 'Hvala, %s! Vaša prijava je zaprimljena.', 'plan-a-kosarica' ), $paka_first )
						: __( 'Hvala! Vaša prijava je zaprimljena.', 'plan-a-kosarica' )
				);
				?>
			</h1>
		<?php endif; ?>
		<p class="paka-done__meta">
			<?php esc_html_e( 'Broj narudžbe:', 'plan-a-kosarica' ); ?> <strong><?php echo esc_html( $order->get_order_number() ); ?></strong>
			<?php if ( $paka_created ) : ?>
				<span class="paka-done__sep" aria-hidden="true">·</span> <?php echo esc_html( Plan_A_Kosarica_Order::hr_date( $paka_created->getOffsetTimestamp() ) ); ?>
			<?php endif; ?>
		</p>
	</header>

	<?php if ( ! $paka_failed ) : ?>
		<section class="paka-card paka-next" aria-labelledby="paka-next-h">
			<h2 class="paka-h2" id="paka-next-h"><?php esc_html_e( 'Što sada?', 'plan-a-kosarica' ); ?></h2>
			<ol class="paka-next__list">
				<?php foreach ( Plan_A_Kosarica_Order::next_steps( $order ) as $paka_i => $paka_step ) : ?>
					<li class="<?php echo 0 === $paka_i && $paka_paid ? 'is-done' : ''; ?>">
						<span class="paka-next__num" aria-hidden="true"><?php echo 0 === $paka_i && $paka_paid ? '&#10003;' : esc_html( (string) ( $paka_i + 1 ) ); ?></span>
						<span><?php echo esc_html( $paka_step ); ?></span>
					</li>
				<?php endforeach; ?>
			</ol>
		</section>
	<?php endif; ?>

	<?php if ( '' !== $paka_payment_block ) : ?>
		<section class="paka-card paka-pay" aria-labelledby="paka-pay-h">
			<h2 class="paka-h2" id="paka-pay-h"><?php esc_html_e( 'Podaci za plaćanje', 'plan-a-kosarica' ); ?></h2>
			<?php // Izlaz dodatka za uplatnicu: 2D kod i podaci ostaju točno kakvi jesu; JS samo premješta kod na vrh. ?>
			<?php list( $paka_code, $paka_pay_rest ) = Plan_A_Kosarica_Order::payment_parts( $order, $paka_payment_block, 'woocommerce_thankyou' ); ?>
			<div class="paka-pay__raw" data-paka-pay data-label="<?php esc_attr_e( 'Skenirajte i platite', 'plan-a-kosarica' ); ?>">
				<?php if ( '' !== $paka_code ) : ?>
					<div class="paka-pay__code">
						<p class="paka-pay__label"><?php esc_html_e( 'Skenirajte i platite', 'plan-a-kosarica' ); ?></p>
						<?php echo $paka_code; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- slika 2D koda dodatka za uplatnicu, nepromijenjena. ?>
					</div>
				<?php endif; ?>
				<?php echo $paka_pay_rest; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- izlaz dodatka za plaćanje, nepromijenjen. ?>
			</div>
			<?php if ( '' !== Plan_A_Kosarica_Settings::get( 'deadline' ) ) : ?>
				<p class="paka-pay__deadline"><strong><?php esc_html_e( 'Rok plaćanja:', 'plan-a-kosarica' ); ?></strong> <?php echo esc_html( Plan_A_Kosarica_Settings::get( 'deadline' ) ); ?></p>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<?php $paka_sum = Plan_A_Kosarica_Order::summary( $order ); ?>
	<section class="paka-card paka-trip woocommerce-order-details" aria-labelledby="paka-trip-h">
		<h2 class="paka-h2" id="paka-trip-h"><?php echo esc_html( Plan_A_Kosarica_Order::first_tour_id( $order ) ? __( 'Vaš izlet', 'plan-a-kosarica' ) : __( 'Detalji narudžbe', 'plan-a-kosarica' ) ); ?></h2>
		<?php foreach ( $paka_sum['items'] as $paka_rows ) : ?>
			<dl class="paka-dl">
				<?php foreach ( $paka_rows as $paka_row ) : ?>
					<?php if ( '' === $paka_row[0] ) : ?>
						<dd class="paka-dl__full"><?php echo wp_kses_post( $paka_row[1] ); ?></dd>
					<?php else : ?>
						<dt><?php echo esc_html( $paka_row[0] ); ?></dt>
						<dd><?php echo wp_kses_post( $paka_row[1] ); ?></dd>
					<?php endif; ?>
				<?php endforeach; ?>
			</dl>
		<?php endforeach; ?>
		<dl class="paka-dl paka-dl--totals">
			<?php foreach ( $paka_sum['extra'] as $paka_row ) : ?>
				<dt><?php echo esc_html( $paka_row[0] ); ?></dt>
				<dd><?php echo wp_kses_post( $paka_row[1] ); ?></dd>
			<?php endforeach; ?>
			<dt class="paka-dl__total"><?php esc_html_e( 'Ukupno', 'plan-a-kosarica' ); ?></dt>
			<dd class="paka-dl__total"><?php echo wp_kses_post( $paka_sum['total'] ); ?></dd>
			<?php if ( $order->get_payment_method_title() ) : ?>
				<dt><?php esc_html_e( 'Način plaćanja', 'plan-a-kosarica' ); ?></dt>
				<dd><?php echo wp_kses_post( $order->get_payment_method_title() ); ?></dd>
			<?php endif; ?>
			<?php if ( '' !== $paka_sum['note'] ) : ?>
				<dt><?php esc_html_e( 'Bilješka', 'plan-a-kosarica' ); ?></dt>
				<dd><?php echo wp_kses_post( nl2br( esc_html( $paka_sum['note'] ) ) ); ?></dd>
			<?php endif; ?>
		</dl>
	</section>

	<?php
	/** Blok "Članstvo Plan A" (dodatak Plan A članstvo). */
	$paka_member = (array) apply_filters( 'plan_a_kosarica_member_rows', array(), $order, Plan_A_Kosarica_Order::tour_year( $order ), 'thankyou' );
	/** Podaci upisani iz pristupnice člana ne prikazuju se na stranici (samo ime i e-mail). */
	$paka_hide = (bool) apply_filters( 'plan_a_kosarica_hide_customer_details', false, $order );
	?>
	<?php if ( $paka_member ) : ?>
		<section class="paka-card paka-member" aria-labelledby="paka-member-h">
			<h2 class="paka-h2" id="paka-member-h"><?php esc_html_e( 'Članstvo Plan A', 'plan-a-kosarica' ); ?></h2>
			<dl class="paka-dl">
				<?php foreach ( $paka_member as $paka_row ) : ?>
					<?php if ( '' === $paka_row[0] ) : ?>
						<dd class="paka-dl__full"><?php echo wp_kses_post( $paka_row[1] ); ?></dd>
					<?php else : ?>
						<dt><?php echo esc_html( $paka_row[0] ); ?></dt>
						<dd><?php echo wp_kses_post( $paka_row[1] ); ?></dd>
					<?php endif; ?>
				<?php endforeach; ?>
			</dl>
		</section>
	<?php endif; ?>

	<section class="paka-card paka-customer-data woocommerce-customer-details" aria-labelledby="paka-cust-h">
		<h2 class="paka-h2" id="paka-cust-h"><?php esc_html_e( 'Vaši podaci', 'plan-a-kosarica' ); ?></h2>
		<p class="paka-address">
			<?php if ( $paka_hide ) : ?>
				<?php echo esc_html( $order->get_formatted_billing_full_name() ); ?><br><?php esc_html_e( 'Ostali podaci upisani su iz tvoje pristupnice Plan A.', 'plan-a-kosarica' ); ?>
			<?php else : ?>
				<?php echo wp_kses_post( $order->get_formatted_billing_address( esc_html__( 'N/A', 'woocommerce' ) ) ); ?>
			<?php endif; ?>
			<?php if ( $order->get_billing_phone() && ! $paka_hide ) : ?>
				<br><?php echo esc_html( $order->get_billing_phone() ); ?>
			<?php endif; ?>
			<?php if ( $order->get_billing_email() ) : ?>
				<br><?php echo esc_html( $order->get_billing_email() ); ?>
			<?php endif; ?>
		</p>
		<?php if ( $order->needs_shipping_address() && $order->get_formatted_shipping_address() ) : ?>
			<h3 class="paka-h3"><?php esc_html_e( 'Adresa za dostavu', 'plan-a-kosarica' ); ?></h3>
			<p class="paka-address"><?php echo wp_kses_post( $order->get_formatted_shipping_address() ); ?></p>
		<?php endif; ?>
		<?php echo $paka_customer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- izlaz kuka drugih dodataka. ?>
	</section>

	<?php if ( '' !== $paka_other ) : ?>
		<div class="paka-other"><?php echo $paka_other; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- izlaz kuka drugih dodataka. ?></div>
	<?php endif; ?>

	<?php
	$paka_tour = Plan_A_Kosarica_Order::first_tour_id( $order );
	if ( $paka_tour && ! $paka_failed && class_exists( 'Plan_A_Izleti_Share' ) ) :
		?>
		<section class="paka-invite">
			<p class="paka-invite__text"><?php esc_html_e( 'Povedite prijatelje na isti izlet', 'plan-a-kosarica' ); ?></p>
			<div class="paiz-share-wrap"><?php echo Plan_A_Izleti_Share::render_button( $paka_tour, 'button' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?></div>
		</section>
	<?php endif; ?>
</div>
