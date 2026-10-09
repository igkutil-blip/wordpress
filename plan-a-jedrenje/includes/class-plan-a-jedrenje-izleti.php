<?php
/**
 * Jedrenje u popisu izleta i planu izleta (dodatak Plan A izleti), bez mijenjanja WpTravellyja.
 *
 * Izlet jedrenja iz WpTravellyja (Postavke → "Izlet jedrenja u popisu") ostaje kartica u popisu
 * izleta, ali:
 *   - termini su slobodni tjedni iz kalendara jedrenja (filtar po mjesecu radi),
 *   - kartica, plan i "Predloži ekipi" vode na stranicu s rezervacijom,
 *   - cijena je "od … za cijeli brod",
 *   - u planu izleta je jedan redak za cijelu sezonu umjesto retka po tjednu.
 *
 * @package Plan_A_Jedrenje
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Jedrenje_Izleti {

	public static function init() {
		add_filter( 'plan_a_izleti_tour_dates', array( __CLASS__, 'dates' ), 10, 2 );
		add_filter( 'plan_a_izleti_tour_url', array( __CLASS__, 'url' ), 10, 2 );
		add_filter( 'plan_a_izleti_price_html', array( __CLASS__, 'price' ), 10, 2 );
		add_filter( 'plan_a_izleti_sold_out', array( __CLASS__, 'sold_out' ), 20, 2 );
		add_filter( 'plan_a_izleti_plan_rows', array( __CLASS__, 'plan_rows' ) );
		add_filter( 'plan_a_izleti_duration', array( __CLASS__, 'duration' ), 10, 2 );

		// Promjena tjedana, cijena ili rezervacija odmah se vidi u popisu i planu.
		foreach ( array( Plan_A_Jedrenje_Data::OPTION, Plan_A_Jedrenje_Data::PERIODS, Plan_A_Jedrenje_Data::WEEKS, Plan_A_Jedrenje_Booking::PAGE_OPTION ) as $option ) {
			add_action( 'add_option_' . $option, array( __CLASS__, 'flush' ) );
			add_action( 'update_option_' . $option, array( __CLASS__, 'flush' ) );
		}
		add_action( 'added_post_meta', array( __CLASS__, 'meta_changed' ), 10, 3 );
		add_action( 'updated_post_meta', array( __CLASS__, 'meta_changed' ), 10, 3 );
	}

	/**
	 * Izlet jedrenja: odabran u postavkama, inače izlet za fotografije (ili prvi s "jedren" u nazivu).
	 */
	public static function tour_id(): int {
		$id = (int) Plan_A_Jedrenje_Data::value( 'list_tour' );
		if ( $id < 0 ) {
			return 0;
		}
		if ( Plan_A_Jedrenje_Slider::valid_tour( $id ) ) {
			return $id;
		}
		$gallery = (int) Plan_A_Jedrenje_Data::value( 'gallery_tour' );
		return Plan_A_Jedrenje_Slider::valid_tour( $gallery ) ? $gallery : Plan_A_Jedrenje_Slider::detect();
	}

	private static function is_ours( $tour_id ): bool {
		$ours = self::tour_id();
		if ( ! $ours ) {
			return false;
		}
		$tour_id = (int) $tour_id;
		if ( $tour_id === $ours ) {
			return true;
		}
		// Prijevod istog izleta (WPML/Polylang).
		return class_exists( 'Plan_A_Izleti_Data' ) && Plan_A_Izleti_Data::source_id( $tour_id ) === $ours;
	}

	public static function page_url(): string {
		$page = (int) get_option( Plan_A_Jedrenje_Booking::PAGE_OPTION );
		return $page && 'publish' === get_post_status( $page ) ? (string) get_permalink( $page ) : '';
	}

	/**
	 * Tjedni koje se još može zatražiti (slobodni i na upitu).
	 *
	 * @return array[]
	 */
	private static function open_weeks(): array {
		return array_values(
			array_filter(
				Plan_A_Jedrenje_Data::public_weeks(),
				static function ( $w ) {
					return 'booked' !== $w['state'];
				}
			)
		);
	}

	public static function dates( $dates, $tour_id ) {
		if ( ! self::is_ours( $tour_id ) ) {
			return $dates;
		}
		return array_column( self::open_weeks(), 'start' );
	}

	public static function url( $url, $tour_id ) {
		if ( ! self::is_ours( $tour_id ) ) {
			return $url;
		}
		$page = self::page_url();
		return $page ? $page : $url;
	}

	public static function price( $html, $tour_id ) {
		if ( ! self::is_ours( $tour_id ) ) {
			return $html;
		}
		$prices = array_column( self::open_weeks(), 'price' );
		$from   = $prices ? min( $prices ) : (float) Plan_A_Jedrenje_Data::value( 'base_price' );
		if ( $from <= 0 ) {
			return $html;
		}
		return '<span class="woocommerce-Price-amount amount"><bdi>' . esc_html( Plan_A_Jedrenje_Data::money( (float) $from ) ) . '</bdi></span> <span class="paiz-price-unit">za cijeli brod</span>';
	}

	public static function duration( $text, $tour_id ) {
		return '' === (string) $text && self::is_ours( $tour_id ) ? '7 dana' : $text;
	}

	public static function sold_out( $sold_out, $tour_id ) {
		if ( ! self::is_ours( $tour_id ) ) {
			return $sold_out;
		}
		return ! self::open_weeks();
	}

	/**
	 * Umjesto retka za svaki tjedan jedan redak po sezoni: od prvog tjedna koji se još nudi
	 * do zadnjeg iskrcaja.
	 */
	public static function plan_rows( $rows ) {
		$tour = self::tour_id();
		if ( ! $tour || ! is_array( $rows ) ) {
			return $rows;
		}
		$rows = array_values(
			array_filter(
				$rows,
				static function ( $row ) use ( $tour ) {
					return (int) ( $row['tour'] ?? 0 ) !== $tour;
				}
			)
		);

		$weeks = Plan_A_Jedrenje_Data::public_weeks(); // bez prošlih
		$years = array();
		foreach ( $weeks as $w ) {
			$years[ substr( $w['start'], 0, 4 ) ][] = $w;
		}
		$s     = Plan_A_Jedrenje_Data::get();
		$title = class_exists( 'Plan_A_Izleti_Plan' ) ? Plan_A_Izleti_Plan::plain_title( $tour ) : wp_strip_all_tags( get_the_title( $tour ) );
		foreach ( $years as $year => $list ) {
			$open   = array_filter(
				$list,
				static function ( $w ) {
					return 'booked' !== $w['state'];
				}
			);
			$last   = end( $list );
			$rows[] = array(
				'key'      => 'jedrenje-' . $year,
				'entry'    => 0,
				'tour'     => $tour,
				'from'     => $list[0]['start'],
				'to'       => $last['end'],
				'title'    => $title,
				'guides'   => '',
				'note'     => 'Tjedan po izboru, subota do subote · cijeli brod za ekipu do ' . Plan_A_Jedrenje_Data::max_persons() . ' osoba · ' . $s['marina']
					. ' · slobodnih tjedana: ' . count( $open ),
				'sold_out' => ! $open,
				'auto'     => true,
			);
		}
		return $rows;
	}

	public static function flush() {
		if ( class_exists( 'Plan_A_Izleti_Data' ) && method_exists( 'Plan_A_Izleti_Data', 'flush_cache' ) ) {
			Plan_A_Izleti_Data::flush_cache();
		}
		if ( class_exists( 'Plan_A_Izleti_Plan' ) ) {
			Plan_A_Izleti_Plan::flush();
		}
	}

	public static function meta_changed( $meta_id, $object_id, $meta_key ) {
		if ( '_paj_state' === $meta_key ) {
			self::flush();
		}
	}
}
