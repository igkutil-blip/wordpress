<?php
/**
 * Kategorije izleta za gumbe filtra.
 *
 * WpTravelly (v2.3.4) ima dva različita pojma "kategorije":
 *
 * 1. Aktivnosti – taksonomija `ttbm_tour_activities`. To je ono što WpTravellyjev
 *    filtar na popisu izleta prikazuje pod naslovom "Category":
 *    TTBM_Filter_Pagination::activity_filter_multiple() čita
 *    get_terms( [ 'taxonomy' => 'ttbm_tour_activities', 'hide_empty' => true ] ),
 *    a kartice dobivaju data-activity iz
 *    TTBM_Function::get_taxonomy_id_string( $tour_id, 'ttbm_tour_activities' ) (get_the_terms()).
 *    Zadani shortcode popisa ima 'activity-filter' => 'yes' i 'category-filter' => 'no'.
 *    Kartica "Activities" u uređivaču izleta uz to sprema odabrane aktivnosti u
 *    meta polje `ttbm_tour_activities` (niz ID-eva termina; stariji izleti imaju
 *    nazive termina). WpTravelly i taj meta podatak spaja s taksonomijom
 *    (TTBM_Function::get_auto_related_tour_ids()), pa ga podržavamo i ovdje.
 *
 * 2. Kategorije – taksonomija `ttbm_tour_cat` ("Tour Type" u WpTravellyjevu
 *    bočnom filtru, category_filter_left()), zadano isključena u shortcodeu popisa.
 *
 * Koristi se prvi izvor koji vraća barem jednu kategoriju za neki budući izlet.
 *
 * @package Plan_A_Izleti
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Izleti_Categories {

	const ACTIVITY_TAXONOMY = 'ttbm_tour_activities';
	const ACTIVITY_META     = 'ttbm_tour_activities';
	const CATEGORY_TAXONOMY = 'ttbm_tour_cat';

	/**
	 * Izvori kategorija, redom kojim se pokušavaju.
	 *
	 * @return array<string, array{taxonomy: string, label: string}>
	 */
	public static function sources(): array {
		return array(
			'activities' => array(
				'taxonomy' => self::ACTIVITY_TAXONOMY,
				'label'    => __( 'Aktivnosti: taksonomija ttbm_tour_activities + meta polje ttbm_tour_activities (isti izvor kao WpTravelly filtar „Category”)', 'plan-a-izleti' ),
			),
			'tour_cat'   => array(
				'taxonomy' => self::CATEGORY_TAXONOMY,
				'label'    => __( 'Kategorije izleta: taksonomija ttbm_tour_cat', 'plan-a-izleti' ),
			),
		);
	}

	/**
	 * Sve kategorije izleta iz svih izvora, odvojeno po izvoru.
	 *
	 * @return array{activities_tax: int[], activities_meta: int[], activities_meta_raw: mixed, tour_cat: int[]}
	 */
	public static function lookup( int $post_id, int $source_id ): array {
		$raw_meta = get_post_meta( $post_id, self::ACTIVITY_META, true );
		if ( empty( $raw_meta ) && $source_id !== $post_id ) {
			$raw_meta = get_post_meta( $source_id, self::ACTIVITY_META, true );
		}

		return array(
			'activities_tax'      => self::taxonomy_ids( $post_id, self::ACTIVITY_TAXONOMY ),
			'activities_meta'     => self::meta_ids( $raw_meta ),
			'activities_meta_raw' => $raw_meta,
			'tour_cat'            => self::taxonomy_ids( $post_id, self::CATEGORY_TAXONOMY ),
		);
	}

	/**
	 * ID-evi kategorija izleta za odabrani izvor, uključujući nadređene termine
	 * (izlet iz podkategorije vidi se i pod glavnom kategorijom).
	 */
	public static function ids_for_source( array $lookup, string $source ): array {
		if ( 'activities' === $source ) {
			$ids = array_merge( $lookup['activities_tax'], $lookup['activities_meta'] );
		} elseif ( 'tour_cat' === $source ) {
			$ids = $lookup['tour_cat'];
		} else {
			return array();
		}

		$taxonomy = self::sources()[ $source ]['taxonomy'];
		$all      = array();
		foreach ( $ids as $id ) {
			$all[] = (int) $id;
			foreach ( get_ancestors( (int) $id, $taxonomy, 'taxonomy' ) as $ancestor ) {
				$all[] = (int) $ancestor;
			}
		}
		return array_values( array_unique( $all ) );
	}

	/**
	 * Prvi izvor koji vraća barem jednu kategoriju za neki izlet s budućim terminom.
	 *
	 * @param array[] $tours   Retci iz Plan_A_Izleti_Data::get_tours().
	 * @param array[] $lookups Rezultati lookup() po ID-u izleta.
	 * @return string Ključ izvora ili '' ako nijedan izvor nema podataka.
	 */
	public static function choose_source( array $tours, array $lookups ): string {
		foreach ( array_keys( self::sources() ) as $source ) {
			foreach ( $tours as $tour ) {
				if ( '' !== $tour['date'] && self::ids_for_source( $lookups[ $tour['id'] ], $source ) ) {
					return $source;
				}
			}
		}
		return '';
	}

	/**
	 * Aktivnosti dodijeljene izletu (taksonomija + meta polje ttbm_tour_activities),
	 * bez nadređenih termina, redom kojim su dodijeljene.
	 *
	 * @return WP_Term[]
	 */
	public static function activity_terms( array $lookup ): array {
		$terms = array();
		foreach ( array_unique( array_merge( $lookup['activities_tax'], $lookup['activities_meta'] ) ) as $id ) {
			$term = get_term( (int) $id, self::ACTIVITY_TAXONOMY );
			if ( $term instanceof WP_Term ) {
				$terms[] = $term;
			}
		}
		return $terms;
	}

	/**
	 * Ikona aktivnosti kako je sprema WpTravelly: CSS klasa u term meta polju
	 * `ttbm_activities_icon` (npr. "mi mi-hiking" iz fonta Mage Icons ili
	 * "fas fa-person-hiking" iz Font Awesomea). Prazno ako ikona nije postavljena.
	 */
	public static function activity_icon( int $term_id ): string {
		$icon = get_term_meta( $term_id, 'ttbm_activities_icon', true );
		$icon = is_string( $icon ) ? preg_replace( '/[^A-Za-z0-9 _-]/', '', $icon ) : '';
		return trim( preg_replace( '/\s+/', ' ', $icon ) );
	}

	private static function taxonomy_ids( int $post_id, string $taxonomy ): array {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return array();
		}
		return array_map( 'intval', wp_list_pluck( $terms, 'term_id' ) );
	}

	/**
	 * Meta polje ttbm_tour_activities: niz ID-eva termina, a kod starijih izleta
	 * nazivi termina (ponekad i tekst odvojen zarezima). Vrijednosti koje ne
	 * odgovaraju postojećem terminu se zanemaruju.
	 *
	 * @param mixed $raw Vrijednost meta polja.
	 */
	private static function meta_ids( $raw ): array {
		if ( empty( $raw ) || ! taxonomy_exists( self::ACTIVITY_TAXONOMY ) ) {
			return array();
		}
		$values = is_array( $raw ) ? $raw : explode( ',', (string) $raw );

		$ids = array();
		foreach ( $values as $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}
			$value = trim( (string) $value );
			if ( '' === $value ) {
				continue;
			}
			$term = ctype_digit( $value )
				? get_term( (int) $value, self::ACTIVITY_TAXONOMY )
				: ( get_term_by( 'name', $value, self::ACTIVITY_TAXONOMY ) ?: get_term_by( 'slug', sanitize_title( $value ), self::ACTIVITY_TAXONOMY ) );
			if ( $term instanceof WP_Term ) {
				$ids[] = (int) $term->term_id;
			}
		}
		return array_values( array_unique( $ids ) );
	}
}
