<?php
/**
 * Detalji narudžbe u ostalim WooCommerceovim mailovima (otkazano, povrat, napomena, račun …),
 * Plan A košarica: isti sažetak kao u mailu „Prijava zaprimljena” (bez tablice sa sličicama
 * proizvoda), u svojoj kartici. Uplatnicu i 2D kod (WSB HUB3) dobiva samo račun.
 *
 * @package Plan_A_Kosarica
 *
 * @var WC_Order $order
 * @var bool     $sent_to_admin
 * @var bool     $plain_text
 * @var WC_Email $email
 */

defined( 'ABSPATH' ) || exit;

$paka_id      = isset( $email ) && is_object( $email ) ? (string) $email->id : '';
$paka_invoice = 'customer_invoice' === $paka_id;
$paka_navy    = Plan_A_Email::color( 'navy' );

if ( $paka_invoice && ! $sent_to_admin ) {
	$paka_before = Plan_A_Kosarica_Order::capture( 'woocommerce_email_before_order_table', $order, $sent_to_admin, $plain_text, $email );
	$paka_after  = Plan_A_Kosarica_Order::capture( 'woocommerce_email_after_order_table', $order, $sent_to_admin, $plain_text, $email );
} else {
	$paka_before = Plan_A_Kosarica_Order::capture_without_hub3( 'woocommerce_email_before_order_table', $order, $sent_to_admin, $plain_text, $email );
	$paka_after  = Plan_A_Kosarica_Order::capture_without_hub3( 'woocommerce_email_after_order_table', $order, $sent_to_admin, $plain_text, $email );
}

$paka_sum  = Plan_A_Kosarica_Order::summary( $order );
$paka_tour = Plan_A_Kosarica_Order::first_tour_id( $order );
$paka_body = '';
foreach ( $paka_sum['items'] as $paka_item_rows ) {
	$paka_body .= Plan_A_Email::rows( $paka_item_rows ) . '<div style="height:10px;line-height:10px;font-size:0;">&nbsp;</div>';
}
$paka_totals   = $paka_sum['extra'];
$paka_totals[] = array( __( 'Ukupno', 'plan-a-kosarica' ), '<strong style="color:' . esc_attr( $paka_navy ) . ';font-size:18px;">' . wp_kses_post( $paka_sum['total'] ) . '</strong>' );
foreach ( $order->get_refunds() as $paka_refund ) {
	$paka_totals[] = array( __( 'Povrat', 'plan-a-kosarica' ), '−' . wp_kses_post( wc_price( (float) $paka_refund->get_amount(), array( 'currency' => $order->get_currency() ) ) ) );
}
if ( $order->get_payment_method_title() ) {
	$paka_totals[] = array( __( 'Način plaćanja', 'plan-a-kosarica' ), esc_html( wp_strip_all_tags( $order->get_payment_method_title() ) ) );
}
if ( '' !== $paka_sum['note'] ) {
	$paka_totals[] = array( __( 'Bilješka', 'plan-a-kosarica' ), nl2br( esc_html( $paka_sum['note'] ) ) );
}
$paka_body .= '<div style="border-top:1px solid #e3e8ee;padding-top:8px;">' . Plan_A_Email::rows( $paka_totals ) . '</div>';

$paka_title  = $paka_tour ? ( $sent_to_admin ? __( 'Izlet', 'plan-a-kosarica' ) : __( 'Vaš izlet', 'plan-a-kosarica' ) ) : __( 'Detalji narudžbe', 'plan-a-kosarica' );
$paka_number = '<p style="margin:-6px 0 10px;color:#5f6b77;font-size:14px;">' . esc_html__( 'Broj narudžbe:', 'plan-a-kosarica' ) . ' <strong style="color:#24323f;">'
	. ( $sent_to_admin ? '<a href="' . esc_url( $order->get_edit_order_url() ) . '" style="color:#24323f;">' . esc_html( $order->get_order_number() ) . '</a>' : esc_html( $order->get_order_number() ) ) . '</strong>'
	. ( $order->get_date_created() ? ' &middot; ' . esc_html( Plan_A_Kosarica_Order::hr_date( $order->get_date_created()->getOffsetTimestamp() ) ) : '' ) . '</p>';

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML iz Plan_A_Email / escaped inside; izlaz kuka drugih dodataka.
echo Plan_A_Kosarica_Order::mail_card_close();
if ( Plan_A_Kosarica_Order::has_content( $paka_before ) ) {
	echo Plan_A_Email::card( '', $paka_before );
}
echo Plan_A_Email::card( $paka_title, $paka_number . $paka_body );
if ( Plan_A_Kosarica_Order::has_content( $paka_after ) ) {
	if ( $paka_invoice ) {
		list( $paka_code, $paka_rest ) = Plan_A_Kosarica_Order::payment_parts( $order, $paka_after, 'woocommerce_email_after_order_table' );
		echo Plan_A_Email::card(
			__( 'Podaci za plaćanje', 'plan-a-kosarica' ),
			( '' !== $paka_code ? Plan_A_Email::p( '<strong style="color:' . esc_attr( $paka_navy ) . ';">' . esc_html__( 'Skenirajte i platite', 'plan-a-kosarica' ) . '</strong>', 'text-align:center;' ) . '<div style="text-align:center;margin:0 0 16px;">' . $paka_code . '</div>' : '' )
			. '<div class="paka-mail-pay">' . $paka_rest . '</div>'
		);
	} else {
		echo Plan_A_Email::card( '', $paka_after );
	}
}
echo Plan_A_Kosarica_Order::mail_card_open();
// phpcs:enable
