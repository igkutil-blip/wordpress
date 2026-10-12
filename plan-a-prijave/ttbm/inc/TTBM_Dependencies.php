<?php
/*
 * Plan A: namjerno prazno.
 *
 * Dok je uključen naš dodatak, TTBM_PLUGIN_DIR pokazuje na ovu mapu. Kad se WpTravelly
 * ponovno uključuje (povratak), WordPress u istom zahtjevu učita i njegovu glavnu datoteku,
 * koja traži TTBM_PLUGIN_DIR . '/inc/TTBM_Dependencies.php'. Bez ove datoteke uključivanje
 * WpTravellyja javlja kobnu grešku. Od sljedećeg zahtjeva radi samo WpTravelly
 * (naš dodatak tada ne učitava zamjenu).
 */
defined( 'ABSPATH' ) || exit;
