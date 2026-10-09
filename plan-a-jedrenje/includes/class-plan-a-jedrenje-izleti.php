<?php
/**
 * Jedrenje u popisu izleta i planu izleta (dodatak Plan A izleti), bez mijenjanja WpTravellyja.
 *
 * Stranica s rezervacijom ([plan-a-jedrenje]) dolazi u popis izleta kao dodatna kartica i u plan
 * izleta kao jedan redak za cijelu sezonu. Izleti iz WpTravellyja (i izlet jedrenja) ostaju
 * nepromijenjeni. Kartica: naziv programa, slobodni tjedni (filtar po mjesecu radi), cijena
 * "od … za cijeli brod", fotografija s izleta jedrenja.
 *
 * @package Plan_A_Jedrenje
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Jedrenje_Izleti {

	public static function init() {
		add_filter( 'plan_a_izleti_extra_tours', array( __CLASS__, 'extra' ) );
		add_filter( 'plan_a_izleti_title', array( __CLASS__, 'title' ), 10, 2 );
		add_filter( 'plan_a_izleti_image_id', array( __CLASS__, 'image' ), 10, 2 );
		add_filter( 'plan_a_izleti_price_html', array( __CLASS__, 'price' ), 10, 2 );
		add_filter( 'plan_a_izleti_sold_out', array( __CLASS__, 'sold_out' ), 20, 2 );
		add_filter( 'plan_a_izleti_duration', array( __CLASS__, 'duration' ), 10, 2 );
		add_filter( 'plan_a_izleti_country', array( __CLASS__, 'country' ), 10, 2 );
		add_filter( 'plan_a_izleti_plan_rows', array( __CLASS__, 'plan_rows' ) );

		// Promjena tjedana, cijena ili rezervacija odmah se vidi u popisu i planu.
		foreach ( array( Plan_A_Jedrenje_Data::OPTION, Plan_A_Jedrenje_Data::PERIODS, Plan_A_Jedrenje_Data::WEEKS, Plan_A_Jedrenje_Booking::PAGE_OPTION ) as $option ) {
			add_action( 'add_option_' . $option, array( __CLASS__, 'flush' ) );
			add_action( 'update_option_' . $option, array( __CLASS__, 'flush' ) );
		}
		add_action( 'added_post_meta', array( __CLASS__, 'meta_changed' ), 10, 3 );
		add_action( 'updated_post_meta', array( __CLASS__, 'meta_changed' ), 10, 3 );
	}

	/**
	 * Stranica s rezervacijom, ako se jedrenje prikazuje u popisu i planu izleta.
	 */
	public static function page_id(): int {
		if ( (int) Plan_A_Jedrenje_Data::value( 'list_tour' ) < 0 ) {
			return 0;
		}
		$page = (int) get_option( Plan_A_Jedrenje_Booking::PAGE_OPTION );
		return $page && 'publish' === get_post_status( $page ) ? $page : 0;
	}

	private static function is_ours( $id ): bool {
		$page = self::page_id();
		return $page && (int) $id === $page;
	}

	public static function page_url(): string {
		$page = (int) get_option( Plan_A_Jedrenje_Booking::PAGE_OPTION );
		return $page && 'publish' === get_post_status( $page ) ? (string) get_permalink( $page ) : '';
	}

	public static function extra( $extra ) {
		$page = self::page_id();
		if ( $page && is_array( $extra ) ) {
			$extra[ $page ] = array_column( self::open_weeks(), 'start' );
		}
		return $extra;
	}

	public static function title( $title, $id ) {
		return self::is_ours( $id ) ? (string) Plan_A_Jedrenje_Data::value( 'title' ) : $title;
	}

	public static function image( $image_id, $id ) {
		if ( ! self::is_ours( $id ) || $image_id ) {
			return $image_id;
		}
		$ids = Plan_A_Jedrenje_Slider::image_ids( Plan_A_Jedrenje_Slider::tour_id() );
		return $ids ? $ids[0] : $image_id;
	}

	public static function country( $country, $id ) {
		return self::is_ours( $id ) && '' === (string) $country ? 'Hrvatska' : $country;
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
		$page = self::page_id();
		if ( ! $page || ! is_array( $rows ) ) {
			return $rows;
		}

		$weeks = Plan_A_Jedrenje_Data::public_weeks(); // bez prošlih
		$years = array();
		foreach ( $weeks as $w ) {
			$years[ substr( $w['start'], 0, 4 ) ][] = $w;
		}
		$s = Plan_A_Jedrenje_Data::get();
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
				'tour'     => $page, // stranica s rezervacijom: poveznica, slika, cijena i trajanje
				'from'     => $list[0]['start'],
				'to'       => $last['end'],
				'title'    => (string) $s['title'],
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
