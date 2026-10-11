<?php
/**
 * Podaci izleta u istom obliku kao WpTravelly (vrsta sadržaja ttbm_tour, ključevi ttbm_*).
 *
 * @package Plan_A_Prijave
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Prijave_Tour {

	const CPT = 'ttbm_tour';

	/** Stanja narudžbi koja zauzimaju mjesto (prijava se ne smije izgubiti). */
	const LIVE = array( 'processing', 'completed', 'on-hold', 'pending', 'partially-paid', 'publish' );

	public static function meta( int $id, string $key, $default = '' ) {
		$v = get_post_meta( $id, $key, true );
		return ( '' === $v || null === $v || false === $v ) ? $default : $v;
	}

	public static function rows( int $id, string $key ): array {
		$v = get_post_meta( $id, $key, true );
		return is_array( $v ) ? array_values( array_filter( $v, 'is_array' ) ) : array();
	}

	/** Termini u obliku Y-m-d (jedan termin ili popis datuma). */
	public static function dates( int $id ): array {
		$out = array();
		if ( 'particular' === self::meta( $id, 'ttbm_travel_type', 'fixed' ) ) {
			foreach ( self::rows( $id, 'ttbm_particular_dates' ) as $r ) {
				if ( ! empty( $r['ttbm_particular_start_date'] ) ) {
					$out[] = substr( (string) $r['ttbm_particular_start_date'], 0, 10 );
				}
			}
		} elseif ( self::meta( $id, 'ttbm_travel_start_date' ) ) {
			$out[] = substr( (string) self::meta( $id, 'ttbm_travel_start_date' ), 0, 10 );
		}
		sort( $out );
		return array_values( array_unique( $out ) );
	}

	/**
	 * Prijave po terminu i karti (zapisi ttbm_booking koje WpTravelly stvara po osobi).
	 *
	 * @return array{dates: array<string,int>, tickets: array<string,int>, services: array<string,int>}
	 */
	public static function booked( int $id ): array {
		$out = array(
			'dates'    => array(),
			'tickets'  => array(),
			'services' => array(),
		);
		if ( ! $id ) {
			return $out;
		}
		$ids = get_posts(
			array(
				'post_type'      => 'ttbm_booking',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'   => 'ttbm_id',
						'value' => $id,
					),
					array(
						'key'     => 'ttbm_order_status',
						'value'   => self::LIVE,
						'compare' => 'IN',
					),
				),
			)
		);
		update_meta_cache( 'post', $ids );
		foreach ( $ids as $b ) {
			$d = substr( (string) get_post_meta( $b, 'ttbm_date', true ), 0, 10 );
			$t = (string) get_post_meta( $b, 'ttbm_ticket_name', true );
			if ( '' !== $d ) {
				$out['dates'][ $d ] = ( $out['dates'][ $d ] ?? 0 ) + 1;
			}
			if ( '' !== $t ) {
				$out['tickets'][ $t ] = ( $out['tickets'][ $t ] ?? 0 ) + 1;
			}
		}
		$sids = get_posts(
			array(
				'post_type'      => 'ttbm_service_booking',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'   => 'ttbm_id',
						'value' => $id,
					),
					array(
						'key'     => 'ttbm_order_status',
						'value'   => self::LIVE,
						'compare' => 'IN',
					),
				),
			)
		);
		foreach ( $sids as $s ) {
			$n = (string) get_post_meta( $s, 'ttbm_service_name', true );
			if ( '' !== $n ) {
				$out['services'][ $n ] = ( $out['services'][ $n ] ?? 0 ) + max( 1, (int) get_post_meta( $s, 'ttbm_service_qty', true ) );
			}
		}
		return $out;
	}

	/**
	 * Postavke prikaza koje WpTravelly drži uz izlet (on/off i izgled). Novi izlet ih preuzima
	 * od zadnjeg objavljenog izleta, da stranica izgleda isto.
	 */
	const DISPLAY_KEYS = array(
		'ttbm_type', 'ttbm_theme_file', 'ttbm_section_title_style', 'ttbm_ticketing_system',
		'ttbm_display_registration', 'ttbm_display_advance', 'ttbm_display_sidebar', 'ttbm_display_tour_type',
		'ttbm_display_hotels', 'ttbm_display_duration', 'ttbm_display_seat_details', 'ttbm_display_price_start',
		'ttbm_display_location', 'ttbm_display_map', 'ttbm_display_description', 'ttbm_display_faq', 'ttbm_faq_active',
		'ttbm_display_schedule', 'ttbm_display_activities', 'ttbm_display_include_service', 'ttbm_display_exclude_service',
		'ttbm_display_why_choose_us', 'ttbm_display_get_question', 'ttbm_display_tour_guide', 'ttbm_guide_style',
		'ttbm_guide_image_style', 'ttbm_guide_description_style', 'ttbm_display_related', 'ttbm_auto_related_tour',
		'ttbm_display_slider', 'ttbm_display_enquiry', 'ttbm_display_rating', 'ttbm_display_order_tour',
		'ttbm_display_min_age', 'ttbm_display_start_location', 'ttbm_display_hiphop', 'ttbm_display_admin_note',
		'ttbm_display_top_picks_deals', 'ttbm_travel_language_status', 'ttbm_display_max_people',
		'mep_disable_ticket_time', 'ttbm_enable_off_schedule', 'ttbm_enable_timewise_stock',
		'ttbm_contact_email', 'ttbm_contact_phone', 'ttbm_contact_text', 'ttbm_why_choose_us_texts',
	);

	/** Zadnji objavljeni izlet (uzor za nove izlete). */
	public static function template_tour( int $not = 0 ): int {
		$ids = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'fields'         => 'ids',
				'post__not_in'   => $not ? array( $not ) : array(),
				'meta_key'       => 'ttbm_ticket_type', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_compare'   => 'EXISTS',
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	/** Novi izlet: postavke prikaza s uzora (samo one kojih još nema). */
	public static function apply_defaults( int $id ) {
		$src = self::template_tour( $id );
		foreach ( self::DISPLAY_KEYS as $k ) {
			if ( metadata_exists( 'post', $id, $k ) ) {
				continue;
			}
			$v = $src ? get_post_meta( $src, $k, true ) : '';
			if ( '' === $v || null === $v ) {
				$v = 0 === strpos( $k, 'ttbm_display_' ) ? 'on' : ( 'ttbm_type' === $k ? 'general' : '' );
			}
			if ( '' !== $v ) {
				update_post_meta( $id, $k, $v );
			}
		}
	}

	/** Izračunati datumi (sljedeći termin, kraj prijava, mjeseci) koje koriste popisi izleta. */
	public static function refresh_dates( int $id ) {
		if ( class_exists( 'TTBM_Function' ) && method_exists( 'TTBM_Function', 'update_upcoming_date_month' ) ) {
			TTBM_Function::update_upcoming_date_month( $id, true );
			return;
		}
		$today = current_time( 'Y-m-d' );
		$all   = array_values( array_filter( self::dates( $id ), static fn( $d ) => $d >= $today ) );
		$first = $all ? $all[0] : '';
		$last  = $all ? end( $all ) : '';
		if ( 'particular' !== self::meta( $id, 'ttbm_travel_type', 'fixed' ) ) {
			$last = (string) self::meta( $id, 'ttbm_travel_reg_end_date', $first );
		}
		update_post_meta( $id, 'ttbm_upcoming_date', $first );
		update_post_meta( $id, 'ttbm_reg_end_date', $last );
		$months = array();
		foreach ( $all as $d ) {
			$months[ (int) substr( $d, 5, 2 ) ] = (int) substr( $d, 5, 2 );
		}
		update_post_meta( $id, 'ttbm_month_list', implode( ',', $months ) );
	}
}
