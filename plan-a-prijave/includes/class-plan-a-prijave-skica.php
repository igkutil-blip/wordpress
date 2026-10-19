<?php
/**
 * Što se računa iz podataka izleta pri spremanju:
 *  - osiguranje od otkaza (dodatna usluga čiji naziv sadrži „osiguranj”) prema cijeni karte „Uplata” – UVIJEK,
 *    i za objavljene izlete (Igor 19.10.2026.: pravilo vrijedi za sve izlete); jednom (opcija pap_ins_028)
 *    usklađeni su svi postojeći izleti. Postojeće narudžbe se ne mijenjaju;
 *  - dok izlet nije objavljen (skica, na čekanju, zakazan): kategorija prema trajanju (Izlet 1 dan / 2 dana /
 *    3 dana / Višednevni izleti), a SEO se složi iznova (Plan_A_Prijave_Seo::fill).
 *
 * @package Plan_A_Prijave
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Prijave_Skica {

	const DURATIONS = array( 'Izlet 1 dan', 'Izlet 2 dana', 'Izlet 3 dana', 'Višednevni izleti' );

	public static function init() {
		// Nakon spremanja okvira izleta (save_post), prije SEO-a (20).
		add_action( 'wp_after_insert_post', array( __CLASS__, 'refresh' ), 15, 2 );
		add_action( 'admin_init', array( __CLASS__, 'sync_all' ) );
	}

	/** Jednom: novo pravilo osiguranja (do 355 € 9,95 €, iznad 2,8 %) za sve postojeće izlete. */
	public static function sync_all() {
		if ( get_option( 'pap_ins_028' ) || ! add_option( 'pap_ins_028', time(), '', false ) ) {
			return;
		}
		$ids = get_posts(
			array(
				'post_type'      => Plan_A_Prijave_Tour::CPT,
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$log = array();
		foreach ( $ids as $id ) {
			if ( ! get_post_meta( (int) $id, '_pap_template', true ) ) {
				$old = self::insurance( (int) $id );
				if ( null !== $old ) {
					$log[ (int) $id ] = $old;
				}
			}
		}
		update_option( 'pap_ins_028', array( 'time' => time(), 'changed' => $log ), false );
	}

	/** Izlet koji još nije objavljen. */
	public static function unpublished( $post ): bool {
		return $post && in_array( $post->post_status, array( 'draft', 'pending', 'future' ), true );
	}

	public static function refresh( $id, $post ) {
		if ( ! $post || Plan_A_Prijave_Tour::CPT !== $post->post_type || in_array( $post->post_status, array( 'auto-draft', 'trash', 'inherit' ), true ) ) {
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
		if ( self::unpublished( $post ) ) {
			self::category( $id );
		}
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

	/** Iznos osiguranja prema cijeni „Uplate”; vraća stari iznos ako je promijenjen, inače null. */
	private static function insurance( int $id ): ?string {
		$price = self::price( $id );
		$rows  = Plan_A_Prijave_Tour::rows( $id, 'ttbm_extra_service_data' );
		if ( $price <= 0 || ! $rows || ! method_exists( 'Plan_A_Prijave_Unos', 'insurance' ) ) {
			return null;
		}
		$new     = Plan_A_Prijave_Unos::insurance( $price );
		$old = null;
		foreach ( $rows as $i => $s ) {
			$cur = (string) ( $s['service_price'] ?? '' );
			if ( false !== mb_stripos( (string) ( $s['service_name'] ?? '' ), 'osiguranj' ) && abs( (float) str_replace( ',', '.', $cur ) - (float) $new ) > 0.001 ) {
				$rows[ $i ]['service_price'] = $new;
				$old                         = $cur;
			}
		}
		if ( null !== $old ) {
			update_post_meta( $id, 'ttbm_extra_service_data', $rows );
		}
		return $old;
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
