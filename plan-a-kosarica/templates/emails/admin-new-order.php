<?php
/**
 * E-mail vama: Nova narudžba (Plan A košarica).
 * Zamjenjuje WooCommerce emails/admin-new-order.php (HTML); sadržaj je u paka-email.php.
 *
 * @package Plan_A_Kosarica
 */

defined( 'ABSPATH' ) || exit;

$paka_mode = 'admin';
include __DIR__ . '/paka-email.php';
