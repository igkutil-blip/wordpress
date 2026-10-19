<?php
/**
 * Dok izlet nije objavljen (skica, na čekanju, zakazan), svako spremanje iznova složi ono što se
 * računa iz podataka izleta – kao kod unosa kolega:
 *  - osiguranje od otkaza (dodatna usluga čiji naziv sadrži „osiguranj”) prema cijeni karte „Uplata”,
 *  - kategoriju prema trajanju (Izlet 1 dan / Izlet 2 dana / Izlet 3 dana / Višednevni izleti).
 * SEO se pri tome složi iznova (Plan_A_Prijave_Seo::fill). Nakon objave ništa od ovoga se ne mijenja samo.
 *
 * @package Plan_A_Prijave
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Prijave_Skica {

	const DURATIONS = array( 'Izlet 1 dan', 'Izlet 2 dana', 'Izlet 3 dana', 'Višednevni izleti' );

	public static function init() {
		// Nakon spremanja okvira izleta (save_post), prije SEO-a (20).
		add_action( 'wp_after_insert_post', array( __CLASS__, 'refresh' ), 15, 2 );
	}

	/** Izlet koji još nije objavljen. */
	public static function unpublished( $post ): bool {
		return $post && in_array( $post->post_status, array( 'draft', 'pending', 'future' ), true );
	}

	public static function refresh( $id, $post ) {
		if ( ! $post || Plan_A_Prijave_Tour::CPT !== $post->post_type || ! self::unpublished( $post ) ) {
			return;
		}
		if ( wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}
		$id = (int) $id;
		if ( get_post_meta( $id, '_pap_template', true ) ) {
			return; // predložak za unos kolega se ne dira
		}
		self::insurance( $id );
		self::category( $id );
	}

	/** Cijena karte „Uplata” (bez „prve rate”), 0 = nema. */
	public static function price( int $id ): float {
		foreach ( Plan_A_Prijave_Tour::rows( $id, 'ttbm_ticket_type' ) as $t ) {
			if ( false === stripos( (string) ( $t['ticket_type_name'] ?? '' ), 'prve rate' ) ) {
				return (float) str_replace( ',', '.', (string) ( $t['ticket_type_price'] ?? 0 ) );
			}
		}
		return 0.0;
	}

	private static function insurance( int $id ) {
		$price = self::price( $id );
		$rows  = Plan_A_Prijave_Tour::rows( $id, 'ttbm_extra_service_data' );
		if ( $price <= 0 || ! $rows || ! method_exists( 'Plan_A_Prijave_Unos', 'insurance' ) ) {
			return;
		}
		$new     = Plan_A_Prijave_Unos::insurance( $price );
		$changed = false;
		foreach ( $rows as $i => $s ) {
			if ( false !== mb_stripos( (string) ( $s['service_name'] ?? '' ), 'osiguranj' ) && (string) ( $s['service_price'] ?? '' ) !== $new ) {
				$rows[ $i ]['service_price'] = $new;
				$changed                     = true;
			}
		}
		if ( $changed ) {
			update_post_meta( $id, 'ttbm_extra_service_data', $rows );
		}
	}

	private static function category( int $id ) {
		$tax  = Plan_A_Prijave_Unos::DUR_TAX;
		$days = (int) ceil( (float) str_replace( ',', '.', (string) Plan_A_Prijave_Tour::meta( $id, 'ttbm_travel_duration' ) ) );
		if ( $days < 1 || ! taxonomy_exists( $tax ) ) {
			return;
		}
		$want = get_term_by( 'name', $days >= 4 ? self::DURATIONS[3] : self::DURATIONS[ $days - 1 ], $tax );
		if ( ! $want ) {
			return;
		}
		// Zamijeni samo pojam trajanja; ostali pojmovi te taksonomije ostaju.
		$keep = array();
		foreach ( (array) wp_get_object_terms( $id, $tax ) as $t ) {
			if ( $t instanceof WP_Term && ! in_array( $t->name, self::DURATIONS, true ) ) {
				$keep[] = (int) $t->term_id;
			}
		}
		$keep[] = (int) $want->term_id;
		wp_set_object_terms( $id, array_values( array_unique( $keep ) ), $tax );
	}
}
