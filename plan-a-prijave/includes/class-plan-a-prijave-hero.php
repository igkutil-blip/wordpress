<?php
/**
 * Velika slika na vrhu izleta: datum uz lokaciju, strelice iznad naslova (dva reda naslova
 * stanu i na mobitelu) i standardna veličina slika (3:2, 1800 × 1200).
 *
 * @package Plan_A_Prijave
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Prijave_Hero {

	/** Standard za slike izleta: vodoravno 3:2. */
	const W     = 1800;
	const H     = 1200;
	const MIN_W = 1200;

	public static function init() {
		add_action( 'after_setup_theme', array( __CLASS__, 'sizes' ) );
		add_action( 'ttbm_details_location', array( __CLASS__, 'date_chip' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'css' ), 41 );
	}

	/** Veličina za veliku sliku (bez rezanja; slider je obrezuje sam). Nastaje za nove slike. */
	public static function sizes() {
		add_image_size( 'pap_hero', self::W, self::W, false );
	}

	/** Datum termina, npr. „21.–22.11.2026.” ili „31.12.2026. – 2.1.2027.” (+ broj ostalih termina). */
	public static function date_text( int $id ): string {
		$type  = (string) Plan_A_Prijave_Tour::meta( $id, 'ttbm_travel_type', 'fixed' );
		$today = current_time( 'Y-m-d' );
		$terms = array(); // [start, end]
		if ( 'particular' === $type ) {
			foreach ( Plan_A_Prijave_Tour::rows( $id, 'ttbm_particular_dates' ) as $r ) {
				$s = substr( (string) ( $r['ttbm_particular_start_date'] ?? '' ), 0, 10 );
				if ( $s ) {
					$terms[] = array( $s, substr( (string) ( $r['ttbm_particular_end_date'] ?? '' ), 0, 10 ) );
				}
			}
		} else {
			$s = substr( (string) Plan_A_Prijave_Tour::meta( $id, 'ttbm_travel_start_date' ), 0, 10 );
			if ( $s ) {
				$terms[] = array( $s, substr( (string) Plan_A_Prijave_Tour::meta( $id, 'ttbm_travel_end_date' ), 0, 10 ) );
			}
		}
		usort( $terms, static fn( $a, $b ) => strcmp( $a[0], $b[0] ) );
		$next = array_values( array_filter( $terms, static fn( $t ) => $t[0] >= $today ) );
		if ( ! $next ) {
			return '';
		}
		list( $s, $e ) = $next[0];
		$st = strtotime( $s );
		$et = $e && $e > $s ? strtotime( $e ) : 0;
		if ( ! $et ) {
			$txt = gmdate( 'j.n.Y.', $st );
		} elseif ( gmdate( 'Y-n', $st ) === gmdate( 'Y-n', $et ) ) {
			$txt = gmdate( 'j.', $st ) . '–' . gmdate( 'j.n.Y.', $et );
		} elseif ( gmdate( 'Y', $st ) === gmdate( 'Y', $et ) ) {
			$txt = gmdate( 'j.n.', $st ) . ' – ' . gmdate( 'j.n.Y.', $et );
		} else {
			$txt = gmdate( 'j.n.Y.', $st ) . ' – ' . gmdate( 'j.n.Y.', $et );
		}
		$more = count( $next ) - 1;
		if ( $more > 0 ) {
			$txt .= ' (+' . $more . ' ' . ( 1 === $more ? 'termin' : 'termina' ) . ')';
		}
		return $txt;
	}

	/** Na slici izleta uz lokaciju: datum termina. */
	public static function date_chip() {
		$id  = (int) get_the_ID();
		$txt = $id ? self::date_text( $id ) : '';
		if ( '' === $txt ) {
			return;
		}
		echo '<div class="ttbm_list_info location_name pap-hero-date"><span class="mi mi-calendar"></span><span class="ttbm_list_info_text">' . esc_html( $txt ) . '</span></div>';
	}

	public static function css() {
		if ( ! is_singular( Plan_A_Prijave_Tour::CPT ) ) {
			return;
		}
		$g   = '#ttbm_content .ttbm_smart_theme .ttbm_smart_gallery';
		$css = $g . ' .ttbm_smart_hero_overlay .pap-hero-date .mi{color:#8fd3ff;font-size:16px}'
			// Strelice u gornji dio slike, da ne prelaze preko naslova (i u dva-tri reda).
			. $g . ' .superSlider .iconIndicator.prevItem,' . $g . ' .superSlider .iconIndicator.nextItem{top:calc(30% - 15px)}'
			. '@media (max-width:959px){'
			. $g . ' .superSlider .iconIndicator.prevItem,' . $g . ' .superSlider .iconIndicator.nextItem{top:14px}'
			. $g . ' .ttbm_smart_hero_overlay .location_name{padding:6px 11px;font-size:12.5px}'
			. $g . ' .ttbm_smart_hero_overlay .ttbm-review-location-area{gap:6px}'
			. '}';
		wp_register_style( 'pap-hero', false, array(), PLAN_A_PRIJAVE_VERSION );
		wp_enqueue_style( 'pap-hero' );
		wp_add_inline_style( 'pap-hero', $css );
	}
}
