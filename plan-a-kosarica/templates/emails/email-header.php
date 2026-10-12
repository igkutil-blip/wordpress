<?php
/**
 * Zaglavlje svih ostalih WooCommerceovih mailova (Plan A košarica): isti izgled kao svi
 * Plan A mailovi (Plan_A_Email). Sadržaj maila ide u bijelu karticu, zatvara je email-footer.php.
 *
 * @package Plan_A_Kosarica
 *
 * @var string        $email_heading
 * @var WC_Email|null $email
 */

defined( 'ABSPATH' ) || exit;

$paka_mail = isset( $email ) && is_object( $email ) ? $email : Plan_A_Kosarica_Order::current_email();
$paka_id   = $paka_mail ? (string) $paka_mail->id : '';
$paka_icon = 'mail';
if ( false !== strpos( $paka_id, 'cancelled' ) || false !== strpos( $paka_id, 'failed' ) ) {
	$paka_icon = 'x';
} elseif ( false !== strpos( $paka_id, 'refunded' ) || false !== strpos( $paka_id, 'invoice' ) ) {
	$paka_icon = 'euro';
} elseif ( false !== strpos( $paka_id, 'completed' ) || false !== strpos( $paka_id, 'processing' ) ) {
	$paka_icon = 'check';
} elseif ( false !== strpos( $paka_id, 'new_order' ) ) {
	$paka_icon = 'plus';
}
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML iz Plan_A_Email (escaped inside).
echo Plan_A_Email::open(
	array(
		'title' => wp_strip_all_tags( (string) ( $email_heading ?? '' ) ),
		'icon'  => $paka_icon,
	)
);
// Sadržaj se skuplja do email-footer.php (koji miče prazne kartice).
$GLOBALS['paka_mail_ob'] = ob_start();
echo Plan_A_Kosarica_Order::mail_card_open(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
