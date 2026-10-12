<?php
/**
 * Podnožje ostalih WooCommerceovih mailova (Plan A košarica): zatvara karticu iz
 * email-header.php i dodaje zajedničko Plan A podnožje.
 *
 * @package Plan_A_Kosarica
 */

defined( 'ABSPATH' ) || exit;
?>
</td></tr></table></td></tr>
<?php
echo Plan_A_Email::close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML iz Plan_A_Email.
