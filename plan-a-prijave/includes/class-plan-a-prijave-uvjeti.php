<?php
/**
 * Minimalni broj sudionika i rok obavijesti o otkazu – računa se sam.
 *
 * Minimum = 70 % mjesta (karte bez „prve rate”), zaokruženo na niže, najmanje 1.
 * Rok obavijesti o otkazu prema trajanju (Zakon o pružanju usluga u turizmu):
 * manje od 2 dana → 48 sati, 2–6 dana → 7 dana, više od 6 dana → 20 dana.
 * Dok izlet nije objavljen, tekst se računa iznova; pri prvoj objavi se zaključa (_pap_uvjeti)
 * i više se ne mijenja (ugovor s agencijom). Izleti objavljeni prije ovoga ostaju kakvi jesu.
 *
 * @package Plan_A_Prijave
 */

defined( 'ABSPATH' ) || exit;

class Plan_A_Prijave_Uvjeti {

	const META  = '_pap_uvjeti';
	const TITLE = 'Minimalni broj sudionika i rok obavijesti o otkazu';
	const SHARE = 0.7;

	/** @var int[] Izleti koji se upravo objavljuju (zaključavanje na kraju zahtjeva, kad su svi podaci spremljeni). */
	private static $lock = array();

	public static function init() {
		add_filter( 'plan_a_prijave_daywise', array( __CLASS__, 'daywise' ), 20, 2 );
		add_action( 'transition_post_status', array( __CLASS__, 'transition' ), 10, 3 );
	}

	public static function is_block( string $title ): bool {
		$t = function_exists( 'mb_strtolower' ) ? mb_strtolower( $title ) : strtolower( $title );
		return false !== strpos( $t, 'minimaln' );
	}

	/** Mjesta izleta: zbroj mjesta karata bez „prve rate”. */
	public static function seats( int $id ): int {
		$n = 0;
		foreach ( Plan_A_Prijave_Tour::rows( $id, 'ttbm_ticket_type' ) as $t ) {
			if ( ! Plan_A_Prijave_Seats::is_rate( (string) ( $t['ticket_type_name'] ?? '' ) ) ) {
				$n += max( 0, (int) ( $t['ticket_type_qty'] ?? 0 ) );
			}
		}
		return $n;
	}

	public static function minimum( int $seats ): int {
		return max( 1, (int) floor( $seats * self::SHARE + 1e-9 ) );
	}

	public static function deadline( int $days ): string {
		if ( $days < 2 ) {
			return '48 sati';
		}
		return $days <= 6 ? '7 dana' : '20 dana';
	}

	/** Izračun za izlet; null ako nema upisanih mjesta. */
	public static function calc( int $id ): ?array {
		$seats = self::seats( $id );
		if ( $seats < 1 ) {
			return null;
		}
		$days = max( 1, (int) get_post_meta( $id, 'ttbm_travel_duration', true ) );
		$min  = self::minimum( $seats );
		$rok  = self::deadline( $days );
		return array(
			'seats' => $seats,
			'days'  => $days,
			'min'   => $min,
			'rok'   => $rok,
			'html'  => '<p>Za realizaciju izleta potreban je minimalni broj od ' . $min . ' prijavljenih putnika. U slučaju nedovoljnog broja prijava, organizator će putnika obavijestiti o otkazu putovanja najkasnije ' . $rok . ' prije predviđenog datuma polaska.</p>',
		);
	}

	public static function locked( int $id ): ?array {
		$l = get_post_meta( $id, self::META, true );
		return is_array( $l ) && ! empty( $l['html'] ) ? $l : null;
	}

	/**
	 * Tekst koji vrijedi za izlet: zaključan, ili (dok nije objavljen) izračunat.
	 * null = izlet objavljen prije uvođenja ovoga – ostaje ručno upisan tekst.
	 */
	public static function current( int $id ): ?array {
		$l = self::locked( $id );
		if ( $l ) {
			return $l;
		}
		if ( in_array( get_post_status( $id ), array( 'publish', 'private' ), true ) ) {
			return null;
		}
		return self::calc( $id );
	}

	public static function daywise( $days, $id ) {
		$c = self::current( (int) $id );
		if ( ! $c ) {
			return $days;
		}
		return self::put( is_array( $days ) ? $days : array(), (string) $c['html'] );
	}

	/** Tekst u blok „Minimalni broj …” (ili novi blok na kraju). */
	public static function put( array $days, string $html ): array {
		foreach ( $days as $k => $d ) {
			if ( is_array( $d ) && self::is_block( (string) ( $d['ttbm_day_title'] ?? '' ) ) ) {
				$days[ $k ]['ttbm_day_content'] = $html;
				return $days;
			}
		}
		$days[] = array(
			'ttbm_day_title'   => self::TITLE,
			'ttbm_day_time'    => '',
			'ttbm_day_content' => $html,
		);
		return $days;
	}

	public static function transition( $new, $old, $post ) {
		if ( 'publish' !== $new || 'publish' === $old || ! $post || 'ttbm_tour' !== $post->post_type ) {
			return;
		}
		if ( self::locked( (int) $post->ID ) ) {
			return;
		}
		if ( ! self::$lock ) {
			add_action( 'shutdown', array( __CLASS__, 'lock_all' ) );
		}
		self::$lock[ (int) $post->ID ] = true;
	}

	public static function lock_all() {
		foreach ( array_keys( self::$lock ) as $id ) {
			self::lock( (int) $id );
		}
		self::$lock = array();
	}

	/** Zaključaj uvjete izleta (pri prvoj objavi) i upiši tekst i u sam izlet. */
	public static function lock( int $id ) {
		if ( self::locked( $id ) || 'publish' !== get_post_status( $id ) ) {
			return;
		}
		$c = self::calc( $id );
		if ( ! $c ) {
			return;
		}
		$c['time'] = current_time( 'mysql' );
		update_post_meta( $id, self::META, $c );
		update_post_meta( $id, 'ttbm_daywise_details', self::put( Plan_A_Prijave_Tour::rows( $id, 'ttbm_daywise_details' ), $c['html'] ) );
		update_post_meta( $id, 'ttbm_display_schedule', 'on' );
	}
}
