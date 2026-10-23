<?php
/**
 * Plugin Name: Domestic – pomoćnik probne kopije
 * Description: Samo za probnu kopiju stranice: zadržava sve mailove (ne idu kupcima ni Emiliji), sakriva kopiju od tražilica i omogućuje učitavanje Domestic dodataka preko REST-a. Na živoj stranici se sam isključuje. Izbornik Domestic.
 * Version: 0.3.0
 * Author: Vertikal
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: domestic-pomocnik
 */

defined( 'ABSPATH' ) || exit;

define( 'DSPM_VER', '0.3.0' );
define( 'DSPM_FILE', __FILE__ );
define( 'DSPM_DIR', __DIR__ );

require_once DSPM_DIR . '/includes/domestic-menu.php';
require_once DSPM_DIR . '/includes/class-domestic-pomocnik.php';

Domestic_Pomocnik::init();
