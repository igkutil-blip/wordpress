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
	public static function daywise( $days, $id ) {
		$text = self::text_for( (int) $id );
		if ( '' === $text ) {
			return $days;
		}
		$days  = is_array( $days ) ? $days : array();
		$found = false;
		foreach ( $days as $k => $d ) {
			if ( is_array( $d ) && self::is_block( (string) ( $d['ttbm_day_title'] ?? '' ) ) ) {
				$days[ $k ]['ttbm_day_content'] = $text;
				$found                          = true;
			}
		}
		if ( ! $found ) {
			$days[] = array(
				'ttbm_day_title'   => self::TITLE,
				'ttbm_day_time'    => '',
				'ttbm_day_content' => $text,
			);
		}
		return $days;
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
