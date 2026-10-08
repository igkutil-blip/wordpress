<?php
/**
 * Postavke, sezone, tjedni, cijene i stanja tjedana.
 *
 * Opcije:
 *   plan_a_jedrenje         postavke programa (vidi defaults())
 *   plan_a_jedrenje_periods cjenik po razdobljima: [ [name, from, to, price, regular], … ]
 *   plan_a_jedrenje_weeks   ručno po tjednu: [ 'Y-m-d' => [ 'closed' => 1, 'price' => 5800 ] ]
 *
 * @package Plan_A_Jedrenje
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Jedrenje_Data {

	const OPTION  = 'plan_a_jedrenje';
	const PERIODS = 'plan_a_jedrenje_periods';
	const WEEKS   = 'plan_a_jedrenje_weeks';

	/** @var array|null */
	private static $settings = null;

	/** @var array|null Stanja tjedana iz rezervacija (za jedan zahtjev). */
	private static $states = null;

	public static function defaults(): array {
		return array(
			'title'          => 'Tjedan jedrenja za ekipu',
			'boat'           => 'Bavaria 46',
			'cabins'         => 4,
			'skipper'        => 'skiper',
			'marina'         => 'ACI marina Split',
			'embark_time'    => '17:00',
			'disembark_time' => '09:00',
			'included'       => "najam jedrilice\nskiper\nvođenje na kopnenim turama\ngorivo\nmarine i vezovi\nturistička pristojba\nzavršno čišćenje\nposteljina i ručnici",
			'excluded'       => "hrana i piće (zajednička blagajna ili pojedinačno, prema dogovoru ekipe)\nulaznice za NP Mljet i PP Lastovsko otočje\nnajam bicikala\ndegustacije i restorani",
			'routes'         => "Ruta A: Šolta, Brač, Hvar, Vis\nRuta B: Lastovo, Mljet, Korčula\nDogovor sa skiperom",
			'min_persons'    => 5,
			'max_persons'    => 8,
			'base_price'     => 5600,
			'base_regular'   => 0,
			'deposit_pct'    => 30,
			'deposit_days'   => 5,
			'rest_days'      => 30,
			'seasons'        => array(),
			'admin_email'    => '',
			'mail_request'   => 'Hvala na zahtjevu! Provjeravamo je li brod slobodan u charter bazi i javit ćemo ti se u roku 48 sati. Dok ne potvrdimo, ništa ne plaćaš.',
			'mail_confirm'   => 'Brod je slobodan i tjedan je rezerviran za tvoju ekipu. Rezervacija postaje konačna kad uplatiš akontaciju u roku.',
			'mail_reject'    => 'Nažalost, brod u odabranom tjednu više nije slobodan. Rado ćemo ti predložiti drugi tjedan – odgovori na ovaj e-mail ili odaberi novi termin na stranici.',
			'mail_booked'    => 'Akontacija je uplaćena i tjedan jedrenja je tvoj. Ostatak uplaćuješ prema uplatnici koju ćemo poslati prije roka.',
			'mail_paid'      => 'Sve je uplaćeno. Vidimo se u marini! Nekoliko dana prije polaska poslat ćemo ti popis stvari za ponijeti i upute za ukrcaj.',
			'cancel_terms'   => "Otkaz do 60 dana prije ukrcaja: vraća se uplaćeni iznos umanjen za 10 % cijene.\nOtkaz od 59 do 30 dana prije ukrcaja: zadržava se akontacija.\nOtkaz manje od 30 dana prije ukrcaja: zadržava se cijeli iznos.\nUmjesto otkaza možeš pronaći zamjenu za člana ekipe bez troška.",
			'organizer'      => 'Organizator: Adventure Donkey j.d.o.o., turistička agencija, Meksička ulica 11, 10000 Zagreb, OIB 78664134608, u suradnji sa S.R.D. Plan A',
		);
	}

	/**
	 * Početni cjenik (jednom): špica od subote 26. 6. do 15. 9. 2027. 6.400 € (100 € po osobi više),
	 * od 16. 9. do 15. 10. 2027. 5.600 €. Ne dira cjenik koji je administrator već upisao.
	 */
	public static function seed() {
		if ( get_option( 'plan_a_jedrenje_seeded' ) ) {
			return;
		}
		if ( ! self::periods() ) {
			update_option(
				self::PERIODS,
				array(
					array(
						'name'    => 'Špica',
						'from'    => '2027-06-26',
						'to'      => '2027-09-15',
						'price'   => 6400,
						'regular' => 0,
					),
					array(
						'name'    => 'Posezona',
						'from'    => '2027-09-16',
						'to'      => '2027-10-15',
						'price'   => 5600,
						'regular' => 0,
					),
				),
				false
			);
		}
		update_option( 'plan_a_jedrenje_seeded', 1, false );
	}

	public static function get(): array {
		if ( null === self::$settings ) {
			$saved          = get_option( self::OPTION, array() );
			self::$settings = array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
		}
		return self::$settings;
	}

	public static function set( array $values ) {
		update_option( self::OPTION, $values, false );
		self::$settings = null;
	}

	public static function value( string $key ) {
		$s = self::get();
		return $s[ $key ] ?? null;
	}

	public static function lines( string $key ): array {
		return array_values( array_filter( array_map( 'trim', preg_split( '/\R/u', (string) self::value( $key ) ) ) ) );
	}

	public static function min_persons(): int {
		return max( 1, min( (int) self::value( 'min_persons' ), self::max_persons() ) );
	}

	public static function max_persons(): int {
		return max( 1, (int) self::value( 'max_persons' ) );
	}

	public static function admin_email(): string {
		$email = (string) self::value( 'admin_email' );
		return is_email( $email ) ? $email : (string) get_option( 'admin_email' );
	}

	/* ---------------------------------------------------------------------
	 * Datumi
	 * ------------------------------------------------------------------- */

	public static function today(): string {
		return current_time( 'Y-m-d' );
	}

	public static function valid_date( $value ): string {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( preg_match( '/^(\d{1,2})\s*[.\/-]\s*(\d{1,2})\s*[.\/-]\s*(\d{4})\s*\.?$/', $value, $m ) ) {
			$value = sprintf( '%04d-%02d-%02d', $m[3], $m[2], $m[1] );
		}
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return '';
		}
		return $value;
	}

	public static function add_days( string $ymd, int $days ): string {
		return gmdate( 'Y-m-d', strtotime( $ymd . ' 12:00:00 UTC' ) + $days * DAY_IN_SECONDS );
	}

	public static function weekday( string $ymd ): int {
		return (int) gmdate( 'w', strtotime( $ymd . ' 12:00:00 UTC' ) );
	}

	public static function is_saturday( string $ymd ): bool {
		return '' !== $ymd && 6 === self::weekday( $ymd );
	}

	/** "Sub 15. 5." */
	public static function short( string $ymd ): string {
		$t = strtotime( $ymd . ' 12:00:00 UTC' );
		$d = array( 'Ned', 'Pon', 'Uto', 'Sri', 'Čet', 'Pet', 'Sub' );
		return $d[ (int) gmdate( 'w', $t ) ] . ' ' . gmdate( 'j. n.', $t );
	}

	/** "15. svibnja 2027." */
	public static function long( string $ymd ): string {
		$m = array( 1 => 'siječnja', 'veljače', 'ožujka', 'travnja', 'svibnja', 'lipnja', 'srpnja', 'kolovoza', 'rujna', 'listopada', 'studenoga', 'prosinca' );
		$t = strtotime( $ymd . ' 12:00:00 UTC' );
		return gmdate( 'j', $t ) . '. ' . $m[ (int) gmdate( 'n', $t ) ] . ' ' . gmdate( 'Y', $t ) . '.';
	}

	/** "15. 5. 2027." */
	public static function numeric( string $ymd ): string {
		return $ymd ? gmdate( 'j. n. Y.', strtotime( $ymd . ' 12:00:00 UTC' ) ) : '';
	}

	/** "Sub 15. 5. do Sub 22. 5." */
	public static function week_label( string $start ): string {
		return self::short( $start ) . ' do ' . self::short( self::add_days( $start, 7 ) );
	}

	/** "subota 15. 5. – subota 22. 5. 2027." */
	public static function week_long( string $start ): string {
		$end = self::add_days( $start, 7 );
		return 'subota ' . gmdate( 'j. n.', strtotime( $start . ' 12:00:00 UTC' ) ) . ( substr( $start, 0, 4 ) !== substr( $end, 0, 4 ) ? ' ' . substr( $start, 0, 4 ) . '.' : '' )
			. ' – subota ' . self::numeric( $end );
	}

	public static function money( float $amount ): string {
		$decimals = abs( $amount - round( $amount ) ) < 0.005 ? 0 : 2;
		return number_format( $amount, $decimals, ',', '.' ) . ' €';
	}

	/* ---------------------------------------------------------------------
	 * Sezone i tjedni
	 * ------------------------------------------------------------------- */

	/**
	 * Prva i zadnja subota sezone (zadnja subota = dan iskrcaja zadnjeg tjedna).
	 * Zadano: prva subota od 15. svibnja i prva subota od 15. listopada.
	 *
	 * @return array{0: string, 1: string}
	 */
	public static function season( int $year ): array {
		$seasons = (array) self::value( 'seasons' );
		$first   = self::valid_date( $seasons[ $year ]['first'] ?? '' );
		$last    = self::valid_date( $seasons[ $year ]['last'] ?? '' );
		if ( ! self::is_saturday( $first ) ) {
			$first = sprintf( '%04d-05-15', $year );
			while ( ! self::is_saturday( $first ) ) {
				$first = self::add_days( $first, 1 );
			}
		}
		if ( ! self::is_saturday( $last ) || $last <= $first ) {
			// Zadnji iskrcaj: prva subota od 15. listopada (sezona traje do 15. 10.).
			$last = sprintf( '%04d-10-15', $year );
			while ( ! self::is_saturday( $last ) ) {
				$last = self::add_days( $last, 1 );
			}
		}
		return array( $first, $last );
	}

	/** Godine koje se nude: ova i sljedeća. */
	public static function years(): array {
		$y = (int) substr( self::today(), 0, 4 );
		return array( $y, $y + 1 );
	}

	/**
	 * Početne subote svih tjedana sezone (i prošlih).
	 *
	 * @return string[]
	 */
	public static function season_weeks( int $year ): array {
		list( $first, $last ) = self::season( $year );
		$weeks = array();
		for ( $s = $first; self::add_days( $s, 7 ) <= $last; $s = self::add_days( $s, 7 ) ) {
			$weeks[] = $s;
		}
		return $weeks;
	}

	public static function is_season_week( string $start ): bool {
		if ( ! self::is_saturday( $start ) ) {
			return false;
		}
		return in_array( $start, self::season_weeks( (int) substr( $start, 0, 4 ) ), true );
	}

	/* ---------------------------------------------------------------------
	 * Cijene
	 * ------------------------------------------------------------------- */

	public static function periods(): array {
		$periods = get_option( self::PERIODS, array() );
		return is_array( $periods ) ? $periods : array();
	}

	public static function week_overrides(): array {
		$weeks = get_option( self::WEEKS, array() );
		return is_array( $weeks ) ? $weeks : array();
	}

	/**
	 * Cijena tjedna za cijeli brod: ručna cijena tjedna, inače razdoblje u koje pada
	 * subota ukrcaja, inače osnovna cijena.
	 *
	 * @return array{price: float, regular: float, source: string, label: string}
	 */
	public static function price( string $start ): array {
		$over = self::week_overrides()[ $start ] ?? array();
		$base = array(
			'price'   => (float) self::value( 'base_price' ),
			'regular' => (float) self::value( 'base_regular' ),
			'source'  => 'base',
			'label'   => 'osnovna',
		);
		foreach ( self::periods() as $p ) {
			if ( ! empty( $p['from'] ) && ! empty( $p['to'] ) && $start >= $p['from'] && $start <= $p['to'] && (float) $p['price'] > 0 ) {
				$base = array(
					'price'   => (float) $p['price'],
					'regular' => (float) ( $p['regular'] ?? 0 ),
					'source'  => 'period',
					'label'   => 'razdoblje: ' . (string) $p['name'],
				);
				break;
			}
		}
		if ( isset( $over['price'] ) && (float) $over['price'] > 0 ) {
			$base['price']  = (float) $over['price'];
			$base['source'] = 'manual';
			$base['label']  = 'ručno';
		}
		if ( $base['regular'] <= $base['price'] ) {
			$base['regular'] = 0.0;
		}
		return $base;
	}

	/**
	 * Akontacija, ostatak i rokovi za tjedan, ako se potvrdi danas (ili $on).
	 *
	 * @return array{full: bool, deposit: float, rest: float, rest_due: string, pct: int}
	 */
	public static function payment_plan( string $start, float $price, string $on = '' ): array {
		$on       = $on ?: self::today();
		$pct      = max( 1, min( 100, (int) self::value( 'deposit_pct' ) ) );
		$rest_due = self::add_days( $start, -1 * max( 0, (int) self::value( 'rest_days' ) ) );
		$full     = $on >= $rest_due || 100 === $pct;
		$deposit  = $full ? $price : round( $price * $pct / 100, 2 );
		return array(
			'full'     => $full,
			'deposit'  => $deposit,
			'rest'     => round( $price - $deposit, 2 ),
			'rest_due' => $full ? '' : $rest_due,
			'pct'      => $pct,
		);
	}

	/* ---------------------------------------------------------------------
	 * Stanja tjedana
	 * ------------------------------------------------------------------- */

	public static function flush_states() {
		self::$states = null;
	}

	/**
	 * Stanja iz rezervacija: [ 'Y-m-d' => 'booked'|'request' ].
	 */
	private static function reservation_states(): array {
		if ( null !== self::$states ) {
			return self::$states;
		}
		self::$states = array();
		$ids          = get_posts(
			array(
				'post_type'      => Plan_A_Jedrenje_Booking::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'     => '_paj_state',
						'value'   => array( 'zahtjev', 'potvrdeno', 'rezervirano', 'placeno' ),
						'compare' => 'IN',
					),
				),
			)
		);
		foreach ( $ids as $id ) {
			$week  = (string) get_post_meta( $id, '_paj_week', true );
			$state = (string) get_post_meta( $id, '_paj_state', true );
			if ( in_array( $state, array( 'rezervirano', 'placeno' ), true ) ) {
				self::$states[ $week ] = 'booked';
			} elseif ( ! isset( self::$states[ $week ] ) ) {
				self::$states[ $week ] = 'request';
			}
		}
		return self::$states;
	}

	/**
	 * Stanje tjedna: free, request (na upitu), booked (zauzeto ili zatvoreno), past.
	 */
	public static function state( string $start ): string {
		if ( $start <= self::today() ) {
			return 'past';
		}
		$over = self::week_overrides()[ $start ] ?? array();
		if ( ! empty( $over['closed'] ) ) {
			return 'booked';
		}
		return self::reservation_states()[ $start ] ?? 'free';
	}

	/**
	 * Tjedni za javni kalendar (bez prošlih), po mjesecima.
	 *
	 * @return array[]
	 */
	public static function public_weeks(): array {
		$out = array();
		foreach ( self::years() as $year ) {
			foreach ( self::season_weeks( $year ) as $start ) {
				$state = self::state( $start );
				if ( 'past' === $state ) {
					continue;
				}
				$price = self::price( $start );
				$plan  = self::payment_plan( $start, $price['price'] );
				$out[] = array(
					'start'    => $start,
					'end'      => self::add_days( $start, 7 ),
					'month'    => substr( $start, 0, 7 ),
					'label'    => self::week_label( $start ),
					'from'     => self::short( $start ),
					'to'       => self::short( self::add_days( $start, 7 ) ),
					'long'     => self::week_long( $start ),
					'price'    => $price['price'],
					'regular'  => $price['regular'],
					'state'    => $state,
					'full'     => $plan['full'],
					'deposit'  => $plan['deposit'],
					'rest'     => $plan['rest'],
					'restDue'  => $plan['rest_due'] ? self::numeric( $plan['rest_due'] ) : '',
				);
			}
		}
		return $out;
	}
}
