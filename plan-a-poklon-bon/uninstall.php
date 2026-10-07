<?php
/**
 * Brisanje dodatka: uklanjaju se samo postavke. Izdani bonovi (kuponi), njihove PDF datoteke
 * i skriveni proizvod ostaju jer su dio poslovne evidencije i mogu još biti u upotrebi.
 *
 * @package Plan_A_Poklon_Bon
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'plan_a_bon' );
delete_option( 'plan_a_bon_page' );
