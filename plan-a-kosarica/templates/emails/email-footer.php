<?php
/**
 * Podnožje ostalih WooCommerceovih mailova (Plan A košarica): zatvara karticu iz
 * email-header.php i dodaje zajedničko Plan A podnožje.
 *
 * @package Plan_A_Kosarica
 */

defined( 'ABSPATH' ) || exit;
echo Plan_A_Kosarica_Order::mail_card_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
if ( ! empty( $GLOBALS['paka_mail_ob'] ) ) {
	$GLOBALS['paka_mail_ob'] = false;
	$paka_body               = (string) ob_get_clean();
	// Prazne kartice (npr. iza zadnjeg bloka ili prazan izlaz kuke) se ne prikazuju.
	$paka_open  = Plan_A_Kosarica_Order::mail_card_open();
	$paka_close = Plan_A_Kosarica_Order::mail_card_close();
	echo preg_replace_callback( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		'#' . preg_quote( $paka_open, '#' ) . '((?:(?!' . preg_quote( $paka_open, '#' ) . ').)*?)' . preg_quote( $paka_close, '#' ) . '#s',
		static function ( $m ) {
			return Plan_A_Kosarica_Order::has_content( $m[1] ) ? $m[0] : '';
		},
		$paka_body
	);
}
echo Plan_A_Email::close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML iz Plan_A_Email.
