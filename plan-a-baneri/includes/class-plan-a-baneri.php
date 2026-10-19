<?php
/**
 * Postavke i podaci za vrh naslovnice.
 *
 * Izleti se čitaju iz dodatka Plan A izleti (isti popis, datumi, cijene i slike kao na stranici Izleti), slobodna
 * mjesta iz sustava izleta (TTBM_Function), brojač Tinder izleta iz dodatka Plan A Tinder (s poticajem ako je uključen),
 * iznos članarine iz Plan A članstva. Ako nekog dodatka nema, taj dio se jednostavno ne prikazuje.
 *
 * @package Plan_A_Baneri
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Baneri {

	const OPT = 'plan_a_baneri';

	public static function init() {
		// Popis izleta se mijenja s izletima i narudžbama – kratko spremanje (5 min) dovoljno je za brzinu.
		foreach ( array( 'save_post', 'woocommerce_order_status_changed', 'woocommerce_checkout_order_processed' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'flush' ) );
		}
		add_action( 'update_option_' . self::OPT, array( __CLASS__, 'flush' ) );
	}

	public static function defaults(): array {
		return array(
			'image'      => 0,
			'image_url'  => '',
			'kicker'     => 'Prijave otvorene · {broj} izleta u sljedećih {dana} dana',
			'title'      => "Tvoj sljedeći izlet\npočinje ovdje.",
			'sub'        => 'Otkrij nove staze i vrhove uz licencirane planinske vodiče, male grupe i dobro društvo.',
			'btn1'       => 'Pogledaj izlete',
			'btn1_url'   => '/izleti/',
			'btn2'       => 'Plan izleta 2026.',
			'btn2_url'   => '/plan-izleta-2026/',
			'trust'      => "Licencirani vodiči SGVH · UIMLA\nMale grupe\nPolazak iz Zagreba",
			'next_on'    => 1,
			'next_count' => 3,
			'promo_on'   => 1,
			'promo_title' => 'Članarina samo {iznos} do {do}',
			'promo_sub'  => 'Još {dana} · članstvo je uvjet za izlete',
			'promo_btn'  => 'Postani član',
			'promo_url'  => '',
			'promo_until' => '2026-12-31',
			'promo_icon' => '',
			'tinder_on'  => 1,
			'tinder_btn' => 'Prijavi se',
			'cards_on'   => 1,
			'cards_title' => 'Sljedeći izleti',
			'cards_sub'  => 'Ovaj i sljedeći mjesec u planinama',
			'cards_days' => 60,
			'cards_max'  => 8,
			'cards_all'  => 'Svi izleti',
			'top'        => 130,
			'top_mob'    => 92,
		);
	}

	public static function get(): array {
		$s = get_option( self::OPT, array() );
		return array_merge( self::defaults(), is_array( $s ) ? $s : array() );
	}

	public static function flush() {
		delete_transient( 'pab_tours' );
	}

	/** Dostupan je izvor izleta (Plan A izleti + sustav izleta). */
	public static function ready(): bool {
		return class_exists( 'Plan_A_Izleti_Data' ) && class_exists( 'Plan_A_Izleti_Shortcode' );
	}

	/**
	 * Budući izleti od najbližeg: [id, title, url, date (Y-m-d), start (unix), days, country, price (HTML), image (id),
	 * free (prikaz, null = nepoznato), seats, sold_out, tinder (null | [m, z])].
	 */
	public static function tours(): array {
		if ( ! self::ready() ) {
			return array();
		}
		$cached = get_transient( 'pab_tours' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$out   = array();
		$today = current_time( 'Y-m-d' );
		foreach ( Plan_A_Izleti_Data::get_tours() as $t ) {
			$id   = (int) ( $t['id'] ?? 0 );
			$date = (string) ( $t['date'] ?? '' );
			if ( ! $id || '' === $date || $date < $today ) {
				continue;
			}
			$src     = Plan_A_Izleti_Data::source_id( $id );
			$country = Plan_A_Izleti_Shortcode::get_country( $id, $src );
			$seats   = self::seats( $src, $date );
			$out[]   = array(
				'id'       => $id,
				'title'    => html_entity_decode( Plan_A_Izleti_Data::title( $id ), ENT_QUOTES, 'UTF-8' ),
				'url'      => Plan_A_Izleti_Data::tour_url( $id ),
				'date'     => $date,
				'end'      => self::end_date( $src, $date ),
				'start'    => self::start_ts( $src, $date ),
				'days'     => Plan_A_Izleti_Shortcode::get_duration( $id ),
				'country'  => (string) ( $country['value'] ?? '' ),
				'price'    => Plan_A_Izleti_Shortcode::get_price_html( $id, $src ),
				'image'    => Plan_A_Izleti_Shortcode::image_id( $id ),
				'free'     => $seats['free'],
				'seats'    => $seats['total'],
				'sold_out' => ! empty( $t['sold_out'] ) || ( null !== $seats['free'] && $seats['free'] < 1 ),
				'tinder'   => self::tinder( $src ),
			);
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return $a['start'] <=> $b['start'];
			}
		);
		set_transient( 'pab_tours', $out, 5 * MINUTE_IN_SECONDS );
		return $out;
	}

	/** Slobodna mjesta za prikaz: stvarna, a za Tinder izlet s uključenim poticajem umanjena (najmanje 1 dok ima stvarnih). */
	private static function seats( int $id, string $date ): array {
		$res = array(
			'free'  => null,
			'total' => 0,
		);
		if ( ! class_exists( 'TTBM_Function' ) || ! method_exists( 'TTBM_Function', 'get_total_available' ) ) {
			return $res;
		}
		try {
			if ( method_exists( 'TTBM_Function', 'get_tour_type' ) && 'general' !== TTBM_Function::get_tour_type( $id ) ) {
				return $res;
			}
			$total = method_exists( 'TTBM_Function', 'get_total_seat' ) ? (int) TTBM_Function::get_total_seat( $id ) : 0;
			if ( $total < 1 ) {
				return $res;
			}
			$free = max( 0, (int) TTBM_Function::get_total_available( $id, $date ) );
			if ( get_post_meta( $id, '_pap_full', true ) ) {
				$free = 0;
			}
			if ( $free && class_exists( 'Plan_A_Tinder' ) && method_exists( 'Plan_A_Tinder', 'seed_active' ) && Plan_A_Tinder::is_tour( $id ) && Plan_A_Tinder::seed_active( $id ) ) {
				$st   = Plan_A_Tinder::shown( $id );
				$free = max( 1, $free - ( (int) $st['total'] - (int) $st['real'] ) );
			}
			$res['free']  = $free;
			$res['total'] = self::ticket_seats( $id ) ?: $total;
		} catch ( \Throwable $e ) {
			return $res;
		}
		return $res;
	}

	/** Mjesta izleta: zbroj karata bez „prve rate” (prva rata dijeli mjesta s „Uplatom”). */
	private static function ticket_seats( int $id ): int {
		$total = 0;
		foreach ( (array) get_post_meta( $id, 'ttbm_ticket_type', true ) as $t ) {
			if ( is_array( $t ) && false === stripos( (string) ( $t['ticket_type_name'] ?? '' ), 'prve rate' ) ) {
				$total += (int) ( $t['ticket_type_qty'] ?? 0 );
			}
		}
		return $total;
	}

	/** Brojač Tinder izleta (♂/♀ kao na stranici izleta, s poticajem). */
	private static function tinder( int $id ): ?array {
		if ( ! class_exists( 'Plan_A_Tinder' ) || ! Plan_A_Tinder::is_tour( $id ) ) {
			return null;
		}
		$st = method_exists( 'Plan_A_Tinder', 'shown' ) ? Plan_A_Tinder::shown( $id ) : Plan_A_Tinder::stats( $id );
		return array(
			'm' => (int) $st['m']['n'],
			'z' => (int) $st['z']['n'],
		);
	}

	/** Početak termina (unix, vrijeme stranice): datum + sat polaska ako je upisan. */
	private static function start_ts( int $id, string $date ): int {
		$time = '';
		foreach ( (array) get_post_meta( $id, 'ttbm_particular_dates', true ) as $d ) {
			if ( is_array( $d ) && substr( (string) ( $d['ttbm_particular_start_date'] ?? '' ), 0, 10 ) === $date ) {
				$time = (string) ( $d['ttbm_particular_start_time'] ?? '' );
				break;
			}
		}
		if ( '' === $time && (string) get_post_meta( $id, 'ttbm_travel_start_date', true ) === $date ) {
			$time = (string) get_post_meta( $id, 'ttbm_travel_start_time', true );
		}
		if ( ! preg_match( '/^\d{1,2}:\d{2}/', $time ) ) {
			$time = '00:00';
		}
		try {
			return ( new DateTimeImmutable( $date . ' ' . substr( $time, 0, 5 ), wp_timezone() ) )->getTimestamp();
		} catch ( \Exception $e ) {
			return (int) strtotime( $date );
		}
	}

	/** Zadnji dan termina (za „21.–22.11.”): početak + trajanje u danima − 1. */
	private static function end_date( int $id, string $date ): string {
		$days = 'day' === ( get_post_meta( $id, 'ttbm_travel_duration_type', true ) ?: 'day' ) ? (int) get_post_meta( $id, 'ttbm_travel_duration', true ) : 1;
		return $days > 1 ? gmdate( 'Y-m-d', strtotime( $date . ' +' . ( $days - 1 ) . ' days' ) ) : $date;
	}

	/** Iznos članarine za tekuću godinu (Plan A članstvo), npr. „5 €”. */
	public static function fee(): string {
		if ( ! class_exists( 'Plan_A_Clanstvo_Data' ) ) {
			return '';
		}
		$fee = method_exists( 'Plan_A_Clanstvo_Data', 'fee_amount' ) ? Plan_A_Clanstvo_Data::fee_amount() : (float) ( Plan_A_Clanstvo_Data::get()['iznos'] ?? 0 );
		return $fee > 0 ? number_format( $fee, abs( $fee - round( $fee ) ) < 0.005 ? 0 : 2, ',', '.' ) . ' €' : '';
	}

	/** Stranica pristupnice (Plan A članstvo). */
	public static function join_url(): string {
		return class_exists( 'Plan_A_Clanstvo_Data' ) && method_exists( 'Plan_A_Clanstvo_Data', 'page_url' ) ? (string) Plan_A_Clanstvo_Data::page_url() : home_url( '/pristupnica/' );
	}
}
