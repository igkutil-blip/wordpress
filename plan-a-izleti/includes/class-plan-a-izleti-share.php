<?php
/**
 * "Predloži ekipi": dijeljenje izleta preko WhatsAppa (wa.me) ili sustavnog
 * izbornika za dijeljenje na mobitelu (Web Share API, vidi assets/js).
 *
 * Na stranici izleta gumb se ispisuje na kuki WpTravellyja `ttbm_registration_before`
 * (ili na dnu stranice ako predložak tu kuku nema), a JavaScript ga premješta odmah
 * iza gumba za rezervaciju. Na karticama [plan-a-izleti] je mala ikona na slici.
 *
 * @package Plan_A_Izleti
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Izleti_Share {

	const UTM = array(
		'utm_source'   => 'whatsapp',
		'utm_medium'   => 'share',
		'utm_campaign' => 'predlozi_ekipi',
	);

	/** @var bool Gumb na stranici izleta je već ispisan. */
	private static $printed = false;

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		add_action( 'ttbm_registration_before', array( __CLASS__, 'single_button' ) );
		add_action( 'wp_footer', array( __CLASS__, 'single_fallback' ), 5 );
		add_action( 'wp_head', array( __CLASS__, 'open_graph' ), 5 );
	}

	private static function is_tour_page(): bool {
		return is_singular( Plan_A_Izleti_Data::post_type() ) && Plan_A_Izleti_Data::is_source_available();
	}

	public static function enqueue() {
		if ( self::is_tour_page() ) {
			wp_enqueue_style( 'plan-a-izleti' );
			wp_enqueue_script( 'plan-a-izleti' );
		}
	}

	/**
	 * Gumb na stranici izleta (kuka ttbm_registration_before).
	 */
	public static function single_button() {
		if ( self::$printed || ! self::is_tour_page() ) {
			return;
		}
		self::$printed = true;
		echo '<div class="paiz-share-wrap" data-paiz-share-wrap>' . self::render_button( get_queried_object_id(), 'button' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
	}

	/**
	 * Predložak bez kuke ttbm_registration_before: gumb se ispisuje skriven na dnu
	 * stranice, a JavaScript ga pokaže pokraj gumba za rezervaciju (ako ga nađe).
	 */
	public static function single_fallback() {
		if ( self::$printed || ! self::is_tour_page() ) {
			return;
		}
		self::$printed = true;
		echo '<div class="paiz-share-wrap" data-paiz-share-wrap data-paiz-share-fallback hidden>' . self::render_button( get_queried_object_id(), 'button' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
	}

	/**
	 * Poveznica na izlet s oznakama za statistiku posjeta.
	 */
	public static function share_url( int $id ): string {
		return add_query_arg( self::UTM, get_permalink( $id ) );
	}

	/**
	 * Podaci za poruku. $tour je redak iz Plan_A_Izleti_Data::get_tours() (datum).
	 *
	 * @return array{title: string, lines: string[], url: string, image: string}
	 */
	public static function data( int $id, ?array $tour = null ): array {
		$source_id = Plan_A_Izleti_Data::source_id( $id );
		if ( null === $tour ) {
			foreach ( Plan_A_Izleti_Data::get_tours() as $row ) {
				if ( (int) $row['id'] === $id ) {
					$tour = $row;
					break;
				}
			}
		}

		$title = wp_strip_all_tags( html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ) );
		$lines = array();

		if ( $tour ) {
			if ( '' !== $tour['date'] ) {
				$date = Plan_A_Izleti_Shortcode::format_date( $tour['date'] );
				if ( ! empty( $tour['more'] ) ) {
					$date .= ' ' . __( '(i drugi termini)', 'plan-a-izleti' );
				}
			} else {
				$date = __( 'Termin uskoro', 'plan-a-izleti' );
			}
			$lines[] = '📅 ' . $date;
		}

		$country = Plan_A_Izleti_Shortcode::get_country( $id, $source_id );
		if ( ! $country['hidden'] ) {
			$place = '' !== $country['raw']['ttbm_location_name'] ? $country['raw']['ttbm_location_name'] : $country['value'];
			if ( '' !== $place ) {
				$lines[] = '📍 ' . $place;
			}
		}

		$price = Plan_A_Izleti_Shortcode::get_price_html( $id, $source_id );
		if ( '' !== $price ) {
			$price   = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $price ), ENT_QUOTES, 'UTF-8' ) ) );
			$lines[] = '💶 ' . sprintf( /* translators: %s: cijena */ __( 'od %s', 'plan-a-izleti' ), $price );
		}

		$image_id = Plan_A_Izleti_Shortcode::image_id( $id );
		$image    = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';

		return array(
			'title' => $title,
			'lines' => $lines,
			'url'   => self::share_url( $id ),
			'image' => $image ? (string) $image : '',
		);
	}

	/**
	 * Tekst poruke bez poveznice (poveznica se dodaje posebno).
	 */
	public static function message( array $data ): string {
		return implode( "\n", array_merge( array( __( 'Ajmo?', 'plan-a-izleti' ) . ' 🏔️', '*' . $data['title'] . '*' ), $data['lines'] ) );
	}

	/**
	 * @param string     $variant 'button' (stranica izleta) ili 'icon' (kartica).
	 * @param array|null $tour    Redak iz get_tours(), ako je već poznat.
	 */
	public static function render_button( int $id, string $variant, ?array $tour = null ): string {
		$data    = self::data( $id, $tour );
		$message = self::message( $data );
		$wa_url  = 'https://wa.me/?text=' . rawurlencode( $message . "\n" . $data['url'] );
		/* translators: %s: naziv izleta */
		$label = sprintf( __( 'Predloži ekipi: %s (WhatsApp)', 'plan-a-izleti' ), $data['title'] );

		// esc_url() bi izbacio prijelome reda (%0A); adresa je fiksna + rawurlencode(), pa je esc_attr() dovoljan.
		$attrs = ' href="' . esc_attr( $wa_url ) . '" target="_blank" rel="noopener noreferrer" data-paiz-share'
			. ' data-paiz-share-title="' . esc_attr( $data['title'] ) . '"'
			. ' data-paiz-share-text="' . esc_attr( $message ) . '"'
			. ' data-paiz-share-url="' . esc_url( $data['url'] ) . '"'
			. ( '' !== $data['image'] ? ' data-paiz-share-image="' . esc_url( $data['image'] ) . '"' : '' );

		if ( 'icon' === $variant ) {
			return '<a class="paiz-share-icon"' . $attrs . ' aria-label="' . esc_attr( $label ) . '" title="' . esc_attr__( 'Predloži ekipi', 'plan-a-izleti' ) . '">' . self::whatsapp_icon( 22 ) . '</a>';
		}
		return '<a class="paiz-share-btn"' . $attrs . ' aria-label="' . esc_attr( $label ) . '">' . self::whatsapp_icon( 20 ) . '<span>' . esc_html__( 'Predloži ekipi', 'plan-a-izleti' ) . '</span></a>';
	}

	/**
	 * Ikona WhatsAppa (SVG, boja iz CSS-a).
	 */
	private static function whatsapp_icon( int $size ): string {
		return '<svg class="paiz-wa-icon" viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="currentColor" aria-hidden="true" focusable="false">'
			. '<path d="M17.47 14.38c-.3-.15-1.75-.86-2.02-.96-.27-.1-.47-.15-.67.15-.2.3-.77.96-.94 1.16-.17.2-.35.22-.64.07-.3-.15-1.25-.46-2.38-1.47-.88-.79-1.47-1.76-1.65-2.06-.17-.3-.02-.46.13-.6.13-.14.3-.35.45-.52.15-.18.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.61-.92-2.2-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.06 2.88 1.21 3.08.15.2 2.1 3.2 5.08 4.48.71.31 1.26.49 1.69.63.71.22 1.36.19 1.87.12.57-.09 1.75-.72 2-1.41.25-.69.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35z"/>'
			. '<path d="M12.04 2C6.5 2 2 6.48 2 12c0 1.77.46 3.5 1.34 5.02L2 22l5.12-1.34A10.03 10.03 0 0 0 12.04 22C17.56 22 22 17.52 22 12S17.56 2 12.04 2zm0 18.3c-1.5 0-2.97-.4-4.25-1.16l-.3-.18-3.04.8.81-2.96-.2-.31A8.26 8.26 0 0 1 3.77 12c0-4.56 3.71-8.27 8.27-8.27 4.56 0 8.25 3.71 8.25 8.27 0 4.57-3.7 8.3-8.25 8.3z"/>'
			. '</svg>';
	}

	/**
	 * Open Graph oznake za pregled poveznice u WhatsAppu. Ne ispisuju se ako ih
	 * već ispisuje SEO dodatak (Yoast, Rank Math, AIOSEO, SEOPress, The SEO
	 * Framework, Slim SEO, Squirrly ili Jetpack).
	 */
	public static function open_graph() {
		if ( ! self::is_tour_page() || self::seo_plugin_handles_og() ) {
			return;
		}
		$id    = get_queried_object_id();
		$data  = self::data( $id );
		$title = $data['title'];

		$description = has_excerpt( $id ) ? get_the_excerpt( $id ) : '';
		if ( '' === trim( $description ) ) {
			$content     = (string) get_post_field( 'post_content', $id );
			$description = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $content ) ), 30, '…' );
		}
		$description = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $description ), ENT_QUOTES, 'UTF-8' ) ) );
		if ( '' === $description ) {
			$description = implode( ' · ', array_map( static function ( $line ) {
				return trim( preg_replace( '/^\S+\s/u', '', $line ) );
			}, $data['lines'] ) );
		}

		$tags = array(
			'og:locale'      => 'hr_HR',
			'og:type'        => 'website',
			'og:site_name'   => wp_strip_all_tags( html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' ) ),
			'og:title'       => $title,
			'og:description' => $description,
			'og:url'         => get_permalink( $id ),
		);

		$image_id = Plan_A_Izleti_Shortcode::image_id( $id );
		$image    = $image_id ? wp_get_attachment_image_src( $image_id, 'large' ) : false;
		if ( $image ) {
			$tags['og:image'] = $image[0];
			if ( 0 === strpos( $image[0], 'https://' ) ) {
				$tags['og:image:secure_url'] = $image[0];
			}
			$tags['og:image:width']  = (string) (int) $image[1];
			$tags['og:image:height'] = (string) (int) $image[2];
			$tags['og:image:alt']    = $title;
		}

		$tags = (array) apply_filters( 'plan_a_izleti_open_graph', $tags, $id );

		echo "\n<!-- Plan A izleti: Open Graph -->\n";
		foreach ( $tags as $property => $content ) {
			if ( '' === (string) $content ) {
				continue;
			}
			$is_url = in_array( $property, array( 'og:url', 'og:image', 'og:image:secure_url' ), true );
			echo '<meta property="' . esc_attr( $property ) . '" content="' . ( $is_url ? esc_url( $content ) : esc_attr( $content ) ) . '" />' . "\n";
		}
	}

	private static function seo_plugin_handles_og(): bool {
		$constants = array( 'WPSEO_VERSION', 'RANK_MATH_VERSION', 'AIOSEO_VERSION', 'SEOPRESS_VERSION', 'THE_SEO_FRAMEWORK_VERSION', 'SLIM_SEO_VER', 'SQ_VERSION' );
		$handled   = false;
		foreach ( $constants as $constant ) {
			if ( defined( $constant ) ) {
				$handled = true;
				break;
			}
		}
		if ( ! $handled && class_exists( 'Jetpack' ) && apply_filters( 'jetpack_enable_open_graph', false ) ) {
			$handled = true;
		}
		/**
		 * true = Open Graph oznake ispisuje drugi dodatak; false = ispisuje ih Plan A izleti.
		 */
		return (bool) apply_filters( 'plan_a_izleti_og_handled', $handled );
	}
}
