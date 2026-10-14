<?php
/**
 * Granične, vizne i zdravstvene formalnosti – jedan tekst po zemlji, zajednički za sve izlete.
 *
 * Tekstovi su u opciji pap_granice (zemlja => HTML). Zemlja izleta je lokacija (ttbm_location_name),
 * a druga zemlja (nije obavezna) je u _pap_country2. Na stranici izleta blok „Granične, vizne i
 * zdravstvene formalnosti” u Važno dobiva tekst odabranih zemalja; zemlja bez teksta ne mijenja ništa.
 *
 * @package Plan_A_Prijave
 */

defined( 'ABSPATH' ) || exit;

class Plan_A_Prijave_Granice {

	const OPT   = 'pap_granice';
	const META2 = '_pap_country2';
	const TITLE = 'Granične, vizne i zdravstvene formalnosti';

	public static function init() {
		add_filter( 'plan_a_prijave_daywise', array( __CLASS__, 'daywise' ), 10, 2 );
		add_action( 'admin_init', array( __CLASS__, 'seed' ) );
		add_action( 'admin_init', array( __CLASS__, 'split' ), 11 );
	}

	public static function texts(): array {
		$o = get_option( self::OPT, array() );
		return is_array( $o ) ? $o : array();
	}

	/** Je li blok iz Važno onaj o granicama/vizama (naslov se može malo razlikovati). */
	public static function is_block( string $title ): bool {
		$t = function_exists( 'mb_strtolower' ) ? mb_strtolower( $title ) : strtolower( $title );
		return false !== strpos( $t, 'granič' ) || false !== strpos( $t, 'vizn' );
	}

	/** Zemlje izleta: lokacija i (ako je odabrana) druga zemlja. */
	public static function countries( int $id ): array {
		$out = array();
		foreach ( array( get_post_meta( $id, 'ttbm_location_name', true ), get_post_meta( $id, self::META2, true ) ) as $c ) {
			$c = trim( (string) $c );
			if ( '' !== $c && ! in_array( $c, $out, true ) ) {
				$out[] = $c;
			}
		}
		return $out;
	}

	private static function has_text( string $html ): bool {
		return '' !== trim( wp_strip_all_tags( $html ) );
	}

	/** Tekst za izlet; prazno = nijedna zemlja nema upisan tekst (ostaje tekst iz izleta). */
	public static function text_for( int $id ): string {
		$texts = self::texts();
		$cs    = self::countries( $id );
		$parts = array();
		foreach ( $cs as $c ) {
			$t = (string) ( $texts[ $c ] ?? '' );
			if ( self::has_text( $t ) ) {
				$parts[ $c ] = $t;
			}
		}
		if ( ! $parts ) {
			return '';
		}
		if ( count( $cs ) < 2 ) {
			return (string) reset( $parts );
		}
		$out = '';
		foreach ( $parts as $c => $t ) {
			$out .= '<p' . ( '' !== $out ? ' style="margin-top:14px"' : '' ) . '><strong>' . esc_html( $c ) . '</strong></p>' . wpautop( $t );
		}
		return $out;
	}

	/** Blokovi Važno na stranici izleta: tekst zemlje u blok o granicama (ili novi blok na kraju). */
	/** Zemlje izleta koje imaju upisan tekst: zemlja => HTML. */
	public static function parts( int $id ): array {
		$texts = self::texts();
		$out   = array();
		foreach ( self::countries( $id ) as $c ) {
			$t = (string) ( $texts[ $c ] ?? '' );
			if ( self::has_text( $t ) ) {
				$out[ $c ] = $t;
			}
		}
		return $out;
	}

	/** Odabrane zemlje izleta bez upisanog teksta (upozorenje u adminu). */
	public static function missing( int $id ): array {
		return array_values( array_diff( self::countries( $id ), array_keys( self::parts( $id ) ) ) );
	}

	/**
	 * Blokovi Važno na stranici izleta: na mjesto bloka o granicama ide po jedan blok za svaku zemlju,
	 * „Granične, vizne i zdravstvene formalnosti (Hrvatska)”; bez takvog bloka – na kraj.
	 */
	public static function daywise( $days, $id ) {
		$parts = self::parts( (int) $id );
		if ( ! $parts ) {
			return $days;
		}
		$blocks = array();
		foreach ( $parts as $c => $t ) {
			$blocks[] = array(
				'ttbm_day_title'   => self::TITLE . ' (' . $c . ')',
				'ttbm_day_time'    => '',
				'ttbm_day_content' => $t,
			);
		}
		$out  = array();
		$done = false;
		foreach ( is_array( $days ) ? $days : array() as $d ) {
			if ( is_array( $d ) && self::is_block( (string) ( $d['ttbm_day_title'] ?? '' ) ) ) {
				if ( ! $done ) {
					array_push( $out, ...$blocks );
					$done = true;
				}
				continue;
			}
			$out[] = $d;
		}
		if ( ! $done ) {
			array_push( $out, ...$blocks );
		}
		return $out;
	}

	/** Prvo punjenje: za svaku zemlju tekst iz najnovijeg izleta u njoj. */
	public static function seed() {
		if ( false !== get_option( self::OPT, false ) ) {
			return;
		}
		$texts = array();
		$ids   = get_posts(
			array(
				'post_type'   => 'ttbm_tour',
				'post_status' => 'publish',
				'numberposts' => 300,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'fields'      => 'ids',
			)
		);
		foreach ( $ids as $id ) {
			$c = trim( (string) get_post_meta( $id, 'ttbm_location_name', true ) );
			if ( '' === $c || isset( $texts[ $c ] ) ) {
				continue;
			}
			foreach ( Plan_A_Prijave_Tour::rows( (int) $id, 'ttbm_daywise_details' ) as $d ) {
				$t = (string) ( $d['ttbm_day_content'] ?? '' );
				if ( self::is_block( (string) ( $d['ttbm_day_title'] ?? '' ) ) && self::has_text( $t ) ) {
					$texts[ $c ] = $t;
					break;
				}
			}
		}
		add_option( self::OPT, $texts );
	}

	/**
	 * Jednom: lokacije s dvije zemlje („Italija - Francuska”) razdvoji na dvije zemlje – izleti dobivaju
	 * lokaciju prvu zemlju i drugu zemlju, tekst graničnih formalnosti ide prvoj zemlji (ako je prazna).
	 */
	public static function split() {
		if ( (int) get_option( 'pap_granice_split', 0 ) >= 1 ) {
			return;
		}
		$terms = get_terms(
			array(
				'taxonomy'   => 'ttbm_tour_location',
				'hide_empty' => false,
			)
		);
		$texts = self::texts();
		foreach ( is_wp_error( $terms ) ? array() : $terms as $t ) {
			if ( ! preg_match( '/^\s*(.+?)\s+[-–—]\s+(.+?)\s*$/u', $t->name, $m ) ) {
				continue;
			}
			$a  = $m[1];
			$b  = $m[2];
			$ta = get_term_by( 'name', $a, 'ttbm_tour_location' );
			if ( ! $ta ) {
				$r  = wp_insert_term( $a, 'ttbm_tour_location' );
				$ta = is_wp_error( $r ) ? null : get_term( (int) $r['term_id'], 'ttbm_tour_location' );
			}
			if ( ! get_term_by( 'name', $b, 'ttbm_tour_location' ) ) {
				wp_insert_term( $b, 'ttbm_tour_location' );
			}
			if ( ! $ta ) {
				continue;
			}
			$ids = get_posts(
				array(
					'post_type'   => 'ttbm_tour',
					'post_status' => 'any',
					'numberposts' => -1,
					'fields'      => 'ids',
					'meta_key'    => 'ttbm_location_name', // phpcs:ignore WordPress.DB.SlowDBQuery
					'meta_value'  => $t->name, // phpcs:ignore WordPress.DB.SlowDBQuery
				)
			);
			foreach ( $ids as $id ) {
				update_post_meta( $id, 'ttbm_location_name', $a );
				update_post_meta( $id, 'ttbm_country_name', (string) get_term_meta( $ta->term_id, 'ttbm_country_location', true ) );
				if ( '' === (string) get_post_meta( $id, self::META2, true ) ) {
					update_post_meta( $id, self::META2, $b );
				}
			}
			foreach ( get_objects_in_term( $t->term_id, 'ttbm_tour_location' ) as $oid ) {
				wp_add_object_terms( (int) $oid, (int) $ta->term_id, 'ttbm_tour_location' );
			}
			if ( isset( $texts[ $t->name ] ) ) {
				if ( ! self::has_text( (string) ( $texts[ $a ] ?? '' ) ) ) {
					$texts[ $a ] = $texts[ $t->name ];
				}
				unset( $texts[ $t->name ] );
			}
			wp_delete_term( $t->term_id, 'ttbm_tour_location' );
		}
		update_option( self::OPT, $texts );
		update_option( 'pap_granice_split', 1 );
	}

	/** Zemlje za uređivanje: sve lokacije i sve zemlje s tekstom. */
	public static function all_countries(): array {
		$t    = get_terms(
			array(
				'taxonomy'   => 'ttbm_tour_location',
				'hide_empty' => false,
			)
		);
		$list = is_wp_error( $t ) ? array() : wp_list_pluck( $t, 'name' );
		$list = array_values( array_unique( array_merge( array_map( 'strval', $list ), array_map( 'strval', array_keys( self::texts() ) ) ) ) );
		natcasesort( $list );
		return array_values( $list );
	}

	/**
	 * Spremanje iz uređivanja izleta. Mijenja se samo tekst koji je stvarno promijenjen
	 * (orig = otisak teksta kad je stranica otvorena), da otvoreni stari prozor ne prepiše novije.
	 */
	public static function save( array $rows, array $new ) {
		$texts = self::texts();
		$old   = $texts;
		foreach ( $rows as $r ) {
			$name = sanitize_text_field( (string) ( $r['name'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}
			$html = Plan_A_Prijave_Text::clean( wp_kses_post( (string) ( $r['content'] ?? '' ) ) );
			if ( self::fingerprint( $html ) === (string) ( $r['orig'] ?? '' ) ) {
				continue;
			}
			if ( self::has_text( $html ) ) {
				$texts[ $name ] = $html;
			} else {
				unset( $texts[ $name ] );
			}
		}
		foreach ( $new as $r ) {
			$name = sanitize_text_field( (string) ( $r['name'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}
			if ( ! get_term_by( 'name', $name, 'ttbm_tour_location' ) ) {
				wp_insert_term( $name, 'ttbm_tour_location' );
			}
			$html = Plan_A_Prijave_Text::clean( wp_kses_post( (string) ( $r['content'] ?? '' ) ) );
			if ( self::has_text( $html ) ) {
				$texts[ $name ] = $html;
			}
		}
		if ( $texts !== $old ) {
			update_option( self::OPT, $texts );
		}
	}

	/** Otisak teksta (bez razlika u razmacima i odlomcima koje unese uređivač). */
	public static function fingerprint( string $html ): string {
		$h = preg_replace( '#</?p[^>]*>#i', "\n", $html );
		return md5( trim( (string) preg_replace( '/\s+/u', ' ', (string) preg_replace( '/>\s+</', '><', (string) $h ) ) ) );
	}
}
