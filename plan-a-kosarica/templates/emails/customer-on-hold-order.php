<?php
/**
 * E-mail kupcu: Prijava zaprimljena (narudžba na čekanju uplate) (Plan A košarica).
 * Zamjenjuje WooCommerce emails/customer-on-hold-order.php (HTML); sadržaj je u paka-email.php.
 *
 * @package Plan_A_Kosarica
 */

defined( 'ABSPATH' ) || exit;

$paka_mode = 'received';
include __DIR__ . '/paka-email.php';
