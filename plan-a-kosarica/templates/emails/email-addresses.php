<?php
/**
 * Podaci kupca u ostalim WooCommerceovim mailovima (Plan A košarica): kartica „Vaši podaci”
 * kao u mailu „Prijava zaprimljena”.
 *
 * @package Plan_A_Kosarica
 *
 * @var WC_Order $order
 * @var bool     $sent_to_admin
 */

defined( 'ABSPATH' ) || exit;

$paka_accent = Plan_A_Email::color( 'accent' );
$paka_lines  = wp_kses_post( $order->get_formatted_billing_address( esc_html__( 'N/A', 'woocommerce' ) ) );
if ( $order->get_billing_phone() ) {
	$paka_lines .= '<br>' . ( $sent_to_admin ? '<a href="tel:' . esc_attr( preg_replace( '/[^\d+]/', '', $order->get_billing_phone() ) ) . '" style="color:' . esc_attr( $paka_accent ) . ';">' . esc_html( $order->get_billing_phone() ) . '</a>' : esc_html( $order->get_billing_phone() ) );
}
if ( $order->get_billing_email() ) {
	$paka_lines .= '<br>' . ( $sent_to_admin ? '<a href="mailto:' . esc_attr( $order->get_billing_email() ) . '" style="color:' . esc_attr( $paka_accent ) . ';">' . esc_html( $order->get_billing_email() ) . '</a>' : esc_html( $order->get_billing_email() ) );
}
ob_start();
do_action( 'woocommerce_email_customer_address_section', 'billing', $order, $sent_to_admin, false );
$paka_lines .= (string) ob_get_clean();

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML iz Plan_A_Email / escaped above.
echo Plan_A_Kosarica_Order::mail_card_close();
echo Plan_A_Email::card( $sent_to_admin ? __( 'Kupac', 'plan-a-kosarica' ) : __( 'Vaši podaci', 'plan-a-kosarica' ), Plan_A_Email::p( $paka_lines ) );
echo Plan_A_Kosarica_Order::mail_card_open();
// phpcs:enable
