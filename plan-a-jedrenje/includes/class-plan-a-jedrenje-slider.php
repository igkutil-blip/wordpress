<?php
/**
 * Slajder fotografija s jedrenja: istaknuta slika i galerija izleta iz WpTravellyja
 * (meta ttbm_gallery_images). Prikazuje se na vrhu rezervacijskog bloka i shortcodeom
 * [plan-a-jedrenje-slike] (atribut izlet="ID" za drugi izlet).
 *
 * @package Plan_A_Jedrenje
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Jedrenje_Slider {

	const TAG = 'plan-a-jedrenje-slike';
	const MAX = 24;

	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'shortcode' ) );
	}

	private static function tour_type(): string {
		return class_exists( 'TTBM_Function' ) && method_exists( 'TTBM_Function', 'get_cpt_name' ) ? (string) TTBM_Function::get_cpt_name() : 'ttbm_tour';
	}

	/**
	 * Izlet za slike: odabran u postavkama, inače prvi objavljeni izlet s "jedren" u nazivu.
	 */
	public static function tour_id(): int {
		$id = (int) Plan_A_Jedrenje_Data::value( 'gallery_tour' );
		if ( $id > 0 && get_post_type( $id ) === self::tour_type() && 'publish' === get_post_status( $id ) ) {
			return $id;
		}
		if ( $id < 0 ) {
			return 0; // slajder isključen
		}
		$found = get_transient( 'paj_gallery_tour' );
		if ( false === $found ) {
			$found = 0;
			$ids   = get_posts(
				array(
					'post_type'      => self::tour_type(),
					'post_status'    => 'publish',
					'posts_per_page' => 200,
					'fields'         => 'ids',
					'orderby'        => 'date',
					'order'          => 'DESC',
					'no_found_rows'  => true,
				)
			);
			foreach ( $ids as $tid ) {
				if ( false !== stripos( remove_accents( get_the_title( $tid ) ), 'jedren' ) && self::image_ids( (int) $tid ) ) {
					$found = (int) $tid;
					break;
				}
			}
			set_transient( 'paj_gallery_tour', $found, HOUR_IN_SECONDS );
		}
		return (int) $found;
	}

	/**
	 * Istaknuta slika i galerija izleta, bez ponavljanja.
	 *
	 * @return int[]
	 */
	public static function image_ids( int $tour_id ): array {
		if ( ! $tour_id ) {
			return array();
		}
		$ids     = array();
		$thumb   = (int) get_post_thumbnail_id( $tour_id );
		$gallery = get_post_meta( $tour_id, 'ttbm_gallery_images', true );
		if ( is_string( $gallery ) ) {
			$gallery = explode( ',', $gallery );
		}
		foreach ( array_merge( array( $thumb ), (array) $gallery ) as $id ) {
			$id = absint( $id );
			if ( $id && wp_attachment_is_image( $id ) && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}
		return array_slice( $ids, 0, self::MAX );
	}

	public static function shortcode( $atts ): string {
		$atts = shortcode_atts( array( 'izlet' => '' ), $atts, self::TAG );
		$id   = absint( $atts['izlet'] ) ?: self::tour_id();
		wp_enqueue_style( 'plan-a-jedrenje' );
		wp_enqueue_script( 'plan-a-jedrenje' );
		return '<div class="pajd pajd--slider-only">' . self::render( self::image_ids( $id ), false ) . '</div>';
	}

	private static function icon( string $name ): string {
		$paths = array(
			'left'   => '<path d="m15 6-6 6 6 6"/>',
			'right'  => '<path d="m9 6 6 6-6 6"/>',
			'expand' => '<path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/>',
			'close'  => '<path d="M6 6l12 12M18 6 6 18"/>',
			'down'   => '<path d="M12 5v14M6 13l6 6 6-6"/>',
		);
		return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}

	/**
	 * @param int[] $ids     Slike.
	 * @param bool  $overlay Naslov programa i gumb "Odaberi tjedan" preko slika.
	 */
	public static function render( array $ids, bool $overlay = true ): string {
		if ( ! $ids ) {
			return '';
		}
		$s     = Plan_A_Jedrenje_Data::get();
		$count = count( $ids );
		$html  = '<section class="pajd-slider" data-pajd-slider aria-roledescription="carousel" aria-label="Fotografije s jedrenja">'
			. '<div class="pajd-slider__stage">'
			. '<div class="pajd-slider__track" data-pajd-track tabindex="0" aria-label="Fotografije, listaj strelicama">';
		foreach ( $ids as $i => $id ) {
			$alt      = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
			$caption  = trim( (string) wp_get_attachment_caption( $id ) );
			$full     = wp_get_attachment_image_url( $id, 'full' );
			$bg       = wp_get_attachment_image_url( $id, 'medium' );
			$img      = wp_get_attachment_image(
				$id,
				'large',
				false,
				array(
					'class'         => 'pajd-slide__img',
					'alt'           => $alt ?: ( $caption ?: 'Jedrenje, fotografija ' . ( $i + 1 ) ),
					'loading'       => 0 === $i ? 'eager' : 'lazy',
					'fetchpriority' => 0 === $i ? 'high' : 'auto',
					'sizes'         => '(max-width: 1100px) 100vw, 1100px',
					'decoding'      => 'async',
				)
			);
			$html .= '<figure class="pajd-slide' . ( 0 === $i ? ' is-active' : '' ) . '" role="group" aria-roledescription="slide" aria-label="' . esc_attr( ( $i + 1 ) . ' od ' . $count ) . '" data-full="' . esc_url( (string) $full ) . '">'
				. ( $bg ? '<span class="pajd-slide__bg" style="background-image:url(' . esc_url( $bg ) . ')" aria-hidden="true"></span>' : '' )
				. $img
				. ( $caption ? '<figcaption class="pajd-slide__cap">' . esc_html( $caption ) . '</figcaption>' : '' )
				. '</figure>';
		}
		$html .= '</div>';

		if ( $count > 1 ) {
			$html .= '<button type="button" class="pajd-slider__arrow pajd-slider__arrow--prev" data-pajd-slide-prev aria-label="Prethodna fotografija">' . self::icon( 'left' ) . '</button>'
				. '<button type="button" class="pajd-slider__arrow pajd-slider__arrow--next" data-pajd-slide-next aria-label="Sljedeća fotografija">' . self::icon( 'right' ) . '</button>'
				. '<div class="pajd-slider__dots" data-pajd-dots>';
			for ( $i = 0; $i < $count; $i++ ) {
				$html .= '<button type="button" class="pajd-slider__dot' . ( 0 === $i ? ' is-active' : '' ) . '" data-pajd-dot="' . (int) $i . '" aria-label="' . esc_attr( 'Fotografija ' . ( $i + 1 ) ) . '"' . ( 0 === $i ? ' aria-current="true"' : '' ) . '></button>';
			}
			$html .= '</div>';
		}
		$html .= '<p class="pajd-slider__count" aria-hidden="true"><span data-pajd-count>1</span> / ' . (int) $count . '</p>'
			. '<button type="button" class="pajd-slider__full" data-pajd-full aria-label="Prikaži preko cijelog zaslona">' . self::icon( 'expand' ) . '</button>'
			. '</div>';
		if ( $overlay ) {
			$html .= '<div class="pajd-slider__overlay">'
				. '<p class="pajd-slider__kicker">' . esc_html( $s['marina'] ) . ' · subota do subote</p>'
				. '<p class="pajd-slider__title">' . esc_html( $s['title'] ) . '</p>'
				. '<p class="pajd-slider__sub">' . esc_html( $s['boat'] . ' · ekipa do ' . Plan_A_Jedrenje_Data::max_persons() . ' osoba · skiper' ) . '</p>'
				. '<a class="pajd-slider__cta" href="#pajd-kalendar">Odaberi tjedan' . self::icon( 'down' ) . '</a>'
				. '</div>';
		}
		$html .= '<dialog class="pajd-lightbox" data-pajd-lightbox aria-label="Fotografije s jedrenja">'
			. '<img class="pajd-lightbox__img" data-pajd-lb-img alt="">'
			. '<p class="pajd-lightbox__count" data-pajd-lb-count></p>'
			. '<button type="button" class="pajd-lightbox__btn pajd-lightbox__btn--close" data-pajd-lb-close aria-label="Zatvori">' . self::icon( 'close' ) . '</button>'
			. ( $count > 1 ? '<button type="button" class="pajd-lightbox__btn pajd-lightbox__btn--prev" data-pajd-lb-prev aria-label="Prethodna">' . self::icon( 'left' ) . '</button><button type="button" class="pajd-lightbox__btn pajd-lightbox__btn--next" data-pajd-lb-next aria-label="Sljedeća">' . self::icon( 'right' ) . '</button>' : '' )
			. '</dialog>'
			. '</section>';
		return $html;
	}
}
