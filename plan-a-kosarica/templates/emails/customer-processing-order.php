<?php
/**
 * E-mail kupcu: Uplata zaprimljena (narudžba u obradi) (Plan A košarica).
 * Zamjenjuje WooCommerce emails/customer-processing-order.php (HTML); sadržaj je u paka-email.php.
 *
 * @package Plan_A_Kosarica
 */

defined( 'ABSPATH' ) || exit;

$paka_mode = 'paid';
include __DIR__ . '/paka-email.php';
