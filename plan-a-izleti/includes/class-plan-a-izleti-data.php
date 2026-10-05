<?php
/**
 * Čitanje izleta iz dodatka WpTravelly (tour-booking-manager).
 *
 * Kako WpTravelly sprema podatke (provjereno u izvornom kodu v2.3.4):
 * - izleti su post type `ttbm_tour` (TTBM_Function::get_cpt_name()),
 * - kategorije: vidi Plan_A_Izleti_Categories (`ttbm_tour_activities` i `ttbm_tour_cat`),
 * - vrsta rasporeda je meta `ttbm_travel_type`: `fixed`, `particular` ili `repeated`,
 *   - fixed:      `ttbm_travel_start_date` (Y-m-d) + `ttbm_travel_start_time`,
 *   - particular: `ttbm_particular_dates` = niz redaka s ključevima
 *                 `ttbm_particular_start_date`, `ttbm_particular_end_date`, `ttbm_particular_start_time`,
 *   - repeated:   `ttbm_travel_repeated_start_date`, `ttbm_travel_repeated_end_date`,
 *                 `ttbm_travel_repeated_after` (razmak u danima), `ttbm_repeat_type`
 *                 (`fixed`/`occurrence`/`continue`), neradni dani `mep_ticket_offdays`
 *                 i datumi `mep_ticket_off_dates` (ako je `ttbm_enable_off_schedule` = yes).
 * - trajanje: `ttbm_travel_duration`, `ttbm_travel_duration_type` (day/hour/min),
 *   `ttbm_travel_duration_night`, `ttbm_display_duration`, `ttbm_display_duration_night`,
 * - država: term meta `ttbm_country_location` lokacije (`ttbm_location_name`),
 *   kopija u post meta `ttbm_country_name`.
 *
 * Sve termine računa sam WpTravelly (TTBM_Function::get_date()), pa se poštuju
 * ponavljanja, neradni dani, iznimke i WPML prijevodi točno kao na samoj stranici izleta.
 *
 * @package Plan_A_Izleti
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Izleti_Data {

	const CACHE_KEY     = 'plan_a_izleti_cache';
	const CACHE_VERSION = 3;

	public static function init() {
		add_action( 'save_post_' . self::post_type(), array( __CLASS__, 'flush_cache' ) );
		add_action( 'deleted_post', array( __CLASS__, 'flush_cache_for_post' ) );
		// Nova ili promijenjena narudžba mijenja popunjenost izleta.
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'flush_cache' ) );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'flush_cache' ) );
		add_action( 'ttbm_wc_order_status_change', array( __CLASS__, 'flush_cache' ) );
	}

	/**
	 * Je li WpTravelly aktivan (i učitan, što zahtijeva i WooCommerce).
	 */
	public static function is_source_available(): bool {
		return class_exists( 'TTBM_Function' ) && method_exists( 'TTBM_Function', 'get_date' );
	}

	public static function post_type(): string {
		if ( class_exists( 'TTBM_Function' ) && method_exists( 'TTBM_Function', 'get_cpt_name' ) ) {
			return (string) TTBM_Function::get_cpt_name();
		}
		return 'ttbm_tour';
	}

	public static function flush_cache() {
		delete_transient( self::CACHE_KEY );
	}

	public static function flush_cache_for_post( $post_id ) {
		if ( get_post_type( $post_id ) === self::post_type() ) {
			self::flush_cache();
		}
	}

	/**
	 * Svi izleti koje treba prikazati, već složeni: prvo po prvom budućem
	 * terminu (od najbližeg), zatim izleti bez datuma. Izleti kojima su svi
	 * termini prošli izostavljeni su.
	 *
	 * @return array[] Retci oblika [ 'id' => int, 'date' => 'Y-m-d'|'', 'more' => bool, 'sold_out' => bool,
	 *                 'months' => [ 'Y-m' => prvi termin u tom mjesecu 'Y-m-d', … ] ].
	 */
	public static function get_tours(): array {
		return self::get_set()['tours'];
	}

	/**
	 * Brojke za dijagnostički prikaz.
	 *
	 * @return array{published: int, upcoming: int, undated: int, past: int, errors: int}
	 */
	public static function get_stats(): array {
		return self::get_set()['stats'];
	}

	private static function get_set(): array {
		$empty = array(
			'tours' => array(),
			'stats' => array(
				'published' => 0,
				'upcoming'  => 0,
				'undated'   => 0,
				'past'      => 0,
				'errors'    => 0,
			),
		);
		if ( ! self::is_source_available() ) {
			return $empty;
		}

		$today = current_time( 'Y-m-d' );
		$lang  = self::language();
		$cache = get_transient( self::CACHE_KEY );
		$fresh = is_array( $cache ) && ( $cache['v'] ?? 0 ) === self::CACHE_VERSION && ( $cache['day'] ?? '' ) === $today;

		if ( $fresh && isset( $cache['sets'][ $lang ] ) ) {
			return $cache['sets'][ $lang ];
		}

		$set = self::build( $today ) + $empty;

		$ttl = (int) apply_filters( 'plan_a_izleti_cache_ttl', HOUR_IN_SECONDS );
		if ( $ttl > 0 ) {
			if ( ! $fresh ) {
				$cache = array(
					'v'    => self::CACHE_VERSION,
					'day'  => $today,
					'sets' => array(),
				);
			}
			$cache['sets'][ $lang ] = $set;
			set_transient( self::CACHE_KEY, $cache, $ttl );
		}

		return $set;
	}

	private static function build( string $today ): array {
		$ids = get_posts(
			array(
				'post_type'        => self::post_type(),
				'post_status'      => 'publish',
				'posts_per_page'   => (int) apply_filters( 'plan_a_izleti_max_tours', 500 ),
				'fields'           => 'ids',
				'orderby'          => 'title',
				'order'            => 'ASC',
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);
		if ( empty( $ids ) ) {
			return array();
		}
		$ids   = array_map( 'intval', $ids );
		$stats = array(
			'published' => count( $ids ),
			'upcoming'  => 0,
			'undated'   => 0,
			'past'      => 0,
			'errors'    => 0,
		);

		update_meta_cache( 'post', $ids );
		if ( method_exists( 'TTBM_Function', 'prime_sold_cache' ) ) {
			TTBM_Function::prime_sold_cache( $ids );
		}

		$dated   = array();
		$undated = array();

		foreach ( $ids as $position => $id ) {
			try {
				$dates = self::get_all_dates( $id );
			} catch ( \Throwable $e ) {
				$stats['errors']++;
				continue; // Neispravan izlet ne smije srušiti cijelu stranicu.
			}

			if ( empty( $dates ) ) {
				$undated[] = array(
					'id'       => $id,
					'date'     => '',
					'more'     => false,
					'months'   => array(),
					'sold_out' => false,
				);
				continue;
			}

			$upcoming = array_values(
				array_filter(
					$dates,
					static function ( $date ) use ( $today ) {
						return $date >= $today;
					}
				)
			);
			if ( empty( $upcoming ) ) {
				$stats['past']++;
				continue; // Svi termini su prošli.
			}

			$dated[] = array(
				'id'       => $id,
				'date'     => $upcoming[0],
				'more'     => count( $upcoming ) > 1,
				'months'   => self::first_date_per_month( $upcoming ),
				'sold_out' => self::is_sold_out( $id ),
				'pos'      => $position,
			);
		}

		usort(
			$dated,
			static function ( $a, $b ) {
				return strcmp( $a['date'], $b['date'] ) ?: $a['pos'] - $b['pos'];
			}
		);
		foreach ( $dated as &$tour ) {
			unset( $tour['pos'] );
		}
		unset( $tour );

		$stats['upcoming'] = count( $dated );
		$stats['undated']  = count( $undated );

		return array(
			'tours' => array_merge( $dated, $undated ),
			'stats' => $stats,
		);
	}

	/**
	 * Prvi termin u svakom mjesecu, npr. [ '2026-11' => '2026-11-03' ].
	 *
	 * @param string[] $dates Sortirani budući termini 'Y-m-d'.
	 */
	private static function first_date_per_month( array $dates ): array {
		$months = array();
		foreach ( $dates as $date ) {
			$month = substr( $date, 0, 7 );
			if ( ! isset( $months[ $month ] ) ) {
				$months[ $month ] = $date;
			}
		}
		return $months;
	}

	/**
	 * Svi termini izleta (i prošli), kao sortirani niz 'Y-m-d'.
	 *
	 * TTBM_Function::get_date( $id, 'yes' ) vraća:
	 * - za `fixed` asocijativni niz s ključem 'date',
	 * - za `particular` niz datuma 'Y-m-d',
	 * - za `repeated` niz datuma 'Y-m-d' (ili 'Y-m-d H:i').
	 */
	private static function get_all_dates( int $tour_id ): array {
		$raw = TTBM_Function::get_date( $tour_id, 'yes' );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		if ( array_key_exists( 'date', $raw ) ) {
			$raw = array( $raw['date'] );
		}

		$dates = array();
		foreach ( $raw as $value ) {
			if ( ! is_string( $value ) ) {
				continue;
			}
			$date = substr( trim( $value ), 0, 10 );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
				$dates[ $date ] = $date;
			}
		}
		sort( $dates );

		return $dates;
	}

	/**
	 * Rasprodan izlet, prema istoj logici koju WpTravelly koristi za oznaku
	 * "Fully Booked" (templates/layout/expire_msg.php). Ako izlet nema
	 * definiran kapacitet, podatak nije dostupan i oznaka se ne prikazuje.
	 */
	private static function is_sold_out( int $id ): bool {
		$sold_out = false;
		try {
			$tour_id = self::source_id( $id );
			if (
				method_exists( 'TTBM_Function', 'get_tour_type' )
				&& method_exists( 'TTBM_Function', 'get_total_seat' )
				&& method_exists( 'TTBM_Function', 'get_total_available' )
				&& method_exists( 'TTBM_Function', 'get_any_date_seat_available' )
				&& 'general' === TTBM_Function::get_tour_type( $tour_id )
				&& (int) TTBM_Function::get_total_seat( $tour_id ) > 0
				&& (int) TTBM_Function::get_total_available( $tour_id ) < 1
			) {
				$sold_out = (int) TTBM_Function::get_any_date_seat_available( $tour_id ) < 1;
			}
		} catch ( \Throwable $e ) {
			$sold_out = false;
		}
		return (bool) apply_filters( 'plan_a_izleti_sold_out', $sold_out, $id );
	}

	/**
	 * ID izleta na glavnom jeziku (WpTravelly tamo drži cijene i karte kod WPML-a/Polylanga).
	 */
	public static function source_id( int $id ): int {
		if ( method_exists( 'TTBM_Function', 'post_id_multi_language' ) ) {
			$source = (int) TTBM_Function::post_id_multi_language( $id );
			if ( $source > 0 ) {
				return $source;
			}
		}
		return $id;
	}

	private static function language(): string {
		$lang = apply_filters( 'wpml_current_language', null );
		if ( ! $lang && function_exists( 'pll_current_language' ) ) {
			$lang = pll_current_language();
		}
		return is_string( $lang ) ? sanitize_key( $lang ) : '';
	}
}
