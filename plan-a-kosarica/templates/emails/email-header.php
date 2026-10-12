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

$paka_id   = isset( $email ) && is_object( $email ) ? (string) $email->id : '';
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
?>
<tr><td style="padding:14px 0 0;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td class="pae-card" style="background:#ffffff;border-radius:14px;padding:22px 24px;color:#24323f;font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;font-size:15px;line-height:1.6;">
