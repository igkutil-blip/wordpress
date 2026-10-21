<?php
/**
 * Prikaz stranice proizvoda: predložak, dijelovi (kratki kodovi), CSS/JS, podaci za Google.
 */

defined( 'ABSPATH' ) || exit;

final class Domestic_Proizvod_Front {

	const SVG = array(
		'casa'    => '<path d="M21 12h30l-3 24c-.7 7-5.3 12-12 12s-11.3-5-12-12l-3-24Z"/><path d="M24 27h24M36 48v13m-12 0h24"/>',
		'kava'    => '<path d="M13 29h40v12a17 17 0 0 1-17 17h-6a17 17 0 0 1-17-17V29Z"/><path d="M53 31h4a8 8 0 0 1 0 16h-5M9 62h52M25 10c-7 6 7 8 0 14m13-14c-7 6 7 8 0 14"/>',
		'kolac'   => '<path d="m14 29 34-14 12 19v24H14V29Zm0 0 46 5M14 43h46M9 63h55"/><path d="M31 29c-3-6 0-12 5-12s8 6 5 12M36 17c0-5 4-7 7-7"/>',
		'tanjur'  => '<circle cx="37" cy="37" r="21"/><circle cx="37" cy="37" r="14"/><path d="M7 10v19m5-19v19M3 10v14c0 8 13 8 13 0V10M10 31v30m54-51v51m0-51c-7 9-7 18 0 21"/>',
		'riba'    => '<path d="M14 35C26 16 45 16 60 35 45 54 26 54 14 35Zm0 0L4 23v24l10-12ZM35 20l7-9 4 14m-11 25 6 10 6-14"/><circle cx="49" cy="32" r="1.5"/>',
		'sir'     => '<path d="m12 30 32-16 16 16v28H12V30Zm0 0h48M44 14v16"/><circle cx="25" cy="44" r="4"/><circle cx="47" cy="48" r="3"/><path d="M35 30v6"/>',
		'koktel'  => '<path d="M12 21h48L36 46 12 21Zm24 25v16m-12 0h24M38 21l12-13h9M20 29h32"/><path d="M14 21a12 12 0 1 1 19-10"/>',
		'medalja' => '<circle cx="36" cy="44" r="17"/><circle cx="36" cy="44" r="11"/><path d="m25 30-9-20h14l6 12m11 8 9-20H42l-6 12"/>',
		'temp'    => '<path d="M31 41V15a7 7 0 0 1 14 0v26a13 13 0 1 1-14 0Z"/><path d="M38 26v25m11-31h8m-8 10h5m-5 10h8"/><circle cx="38" cy="53" r="5"/>',
	);
	const ICO = array(
		'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
		'bag'   => '<path d="M5 7.5h14l-1 13H6l-1-13Z"/><path d="M9 7.5V6a3 3 0 0 1 6 0v1.5"/>',
		'zoom'  => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5M11 8v6M8 11h6"/>',
	);

	const FONTS = array(
		array( 'DSP Serif', 500, 'eb-garamond-latin-ext-500-normal.woff2', 'U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF' ),
		array( 'DSP Serif', 500, 'eb-garamond-latin-500-normal.woff2', 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD' ),
		array( 'DSP Serif', 600, 'eb-garamond-latin-ext-600-normal.woff2', 'U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF' ),
		array( 'DSP Serif', 600, 'eb-garamond-latin-600-normal.woff2', 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD' ),
		array( 'DSP Sans', 400, 'inter-latin-ext-400-normal.woff2', 'U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF' ),
		array( 'DSP Sans', 400, 'inter-latin-400-normal.woff2', 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD' ),
		array( 'DSP Sans', 600, 'inter-latin-ext-600-normal.woff2', 'U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF' ),
		array( 'DSP Sans', 600, 'inter-latin-600-normal.woff2', 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD' ),
	);

	public static function init(): void {
		add_filter( 'template_include', array( __CLASS__, 'template' ), 99 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_shortcode( 'domestic_proizvod', array( __CLASS__, 'shortcode' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	private static function current(): ?WC_Product {
		if ( ! is_singular( 'product' ) ) {
			return null;
		}
		$p = wc_get_product( get_queried_object_id() );
		return $p && Domestic_Proizvod_Data::is_on( $p ) ? $p : null;
	}

	public static function template( $tpl ) {
		return self::current() ? DSP_DIR . '/templates/single-product.php' : $tpl;
	}

	public static function body_class( $c ) {
		if ( self::current() ) {
			$c[] = 'dsp-page';
		}
		return $c;
	}

	public static function assets(): void {
		if ( ! self::current() && ! self::has_shortcode() ) {
			return;
		}
		$v = DSP_VER . '.' . filemtime( DSP_DIR . '/assets/proizvod.css' );
		wp_enqueue_style( 'domestic-proizvod', DSP_URL . 'assets/proizvod.css', array(), $v );
		// fontovi s punom adresom – predmemorija (SpeedyCache, LiteSpeed) premješta CSS i relativni put tada ne vrijedi
		$css = '';
		foreach ( self::FONTS as $f ) {
			$css .= "@font-face{font-family:'{$f[0]}';font-style:normal;font-weight:{$f[1]};font-display:swap;src:url(" . esc_url( DSP_URL . 'assets/fonts/' . $f[2] ) . ") format('woff2');unicode-range:{$f[3]}}";
		}
		wp_add_inline_style( 'domestic-proizvod', $css );
		wp_enqueue_script( 'domestic-proizvod', DSP_URL . 'assets/proizvod.js', array(), $v, true );
		wp_enqueue_script( 'wc-add-to-cart' );
		wp_enqueue_script( 'wc-add-to-cart-variation' );
	}

	private static function has_shortcode(): bool {
		global $post;
		return $post instanceof WP_Post && has_shortcode( (string) $post->post_content, 'domestic_proizvod' );
	}

	/** [domestic_proizvod dio="galerija|kupnja|kartice|povezani|sve" id=""] */
	public static function shortcode( $atts ): string {
		$a = shortcode_atts( array( 'dio' => 'sve', 'id' => 0 ), $atts );
		$p = wc_get_product( $a['id'] ? (int) $a['id'] : get_the_ID() );
		if ( ! $p ) {
			return '';
		}
		$map = array( 'galerija' => 'gallery', 'kupnja' => 'buy', 'kartice' => 'tabs', 'povezani' => 'related' );
		if ( isset( $map[ $a['dio'] ] ) ) {
			return '<div class="dsp">' . self::{$map[ $a['dio'] ]}( $p ) . '</div>';
		}
		return self::render( $p );
	}

	/* ---------- pomoćno ---------- */

	public static function svg( string $k, string $cls = 'dsp-i' ): string {
		$big = isset( self::SVG[ $k ] );
		$d   = $big ? self::SVG[ $k ] : ( self::ICO[ $k ] ?? '' );
		return '<svg class="' . esc_attr( $cls ) . '" viewBox="' . ( $big ? '0 0 72 72' : '0 0 24 24' ) . '" aria-hidden="true">' . $d . '</svg>';
	}

	private static function eur( float $n ): string {
		return wp_strip_all_tags( wc_price( $n ) );
	}

	/* ---------- cijela stranica ---------- */

	public static function render( WC_Product $p ): string {
		return '<div class="dsp">'
			. '<section class="dsp-product">' . self::gallery( $p ) . self::buy( $p ) . '</section>'
			. self::tabs( $p )
			. self::related( $p )
			. self::sticky( $p )
			. '</div>';
	}

	/* ---------- galerija ---------- */

	public static function gallery( WC_Product $p ): string {
		$ids = array_values( array_filter( array_merge( array( $p->get_image_id() ), $p->get_gallery_image_ids() ) ) );
		$h   = '<div class="dsp-gallery">';
		$b   = '';
		foreach ( Domestic_Proizvod_Data::badges( $p ) as $k => $t ) {
			$b .= '<span class="dsp-tag dsp-tag-' . esc_attr( $k ) . '">' . ( 'nagradeno' === $k ? self::svg( 'medalja' ) : '' ) . esc_html( $t ) . '</span>';
		}
		if ( ! $ids ) {
			return $h . '<div class="dsp-photo">' . wc_placeholder_img( 'woocommerce_single' ) . '<div class="dsp-tags">' . $b . '</div></div></div>';
		}
		$first = $ids[0];
		$h    .= '<div class="dsp-photo"><button type="button" class="dsp-zoom" aria-label="Povećaj sliku">' . self::svg( 'zoom' ) . '</button>'
			. wp_get_attachment_image( $first, 'woocommerce_single', false, array( 'class' => 'dsp-main', 'data-full' => wp_get_attachment_image_url( $first, 'full' ), 'fetchpriority' => 'high', 'loading' => 'eager' ) )
			. '<div class="dsp-tags">' . $b . '</div></div>';
		if ( count( $ids ) > 1 ) {
			$h .= '<div class="dsp-thumbs">';
			foreach ( $ids as $i => $id ) {
				$h .= '<button type="button" class="dsp-thumb' . ( $i ? '' : ' on' ) . '" data-src="' . esc_url( wp_get_attachment_image_url( $id, 'woocommerce_single' ) ) . '" data-full="' . esc_url( wp_get_attachment_image_url( $id, 'full' ) ) . '" aria-label="Slika ' . ( $i + 1 ) . '">'
					. wp_get_attachment_image( $id, 'woocommerce_gallery_thumbnail' ) . '</button>';
			}
			$h .= '</div>';
		}
		return $h . '</div>';
	}

	/* ---------- naslov, činjenice, cijena, kupnja ---------- */

	public static function buy( WC_Product $p ): string {
		$m   = Domestic_Proizvod_Data::meta( $p );
		$s   = Domestic_Proizvod_Data::settings();
		list( $big, $small ) = Domestic_Proizvod_Data::title_parts( $p );
		$vol   = Domestic_Proizvod_Data::attr( $p, 'zapremnina' );
		$lit   = Domestic_Proizvod_Data::litres( $vol );
		$alk   = Domestic_Proizvod_Data::attr( $p, 'alkohol' );
		$temp  = Domestic_Proizvod_Data::attr( $p, 'temperatura' );
		$pod   = Domestic_Proizvod_Data::attr( $p, 'podrijetlo' );
		$vrsta = Domestic_Proizvod_Data::attr( $p, 'vrsta' );

		$h  = '<div class="dsp-info">';
		$eb = $m['eyebrow'] ? $m['eyebrow'] : $vrsta;
		if ( $eb ) {
			$h .= '<div class="dsp-eyebrow">' . esc_html( $eb ) . '</div>';
		}
		$h .= '<h1 class="dsp-title">' . esc_html( $big ) . ( $small ? '<span>' . esc_html( $small ) . '</span>' : '' ) . '</h1>';
		$short = $p->get_short_description();
		if ( $short ) {
			$h .= '<div class="dsp-intro">' . wp_kses_post( wpautop( $short ) ) . '</div>';
		}

		// tri činjenice
		$facts = array();
		if ( $vol ) {
			$facts[] = array( $lit ? Domestic_Proizvod_Data::litres_label( $lit ) : $vol, 'Zapremnina' );
		}
		if ( $alk ) {
			$facts[] = array( $alk, 'Alkohol' );
		} elseif ( $temp ) {
			$facts[] = array( $temp, 'Posluživanje' );
		}
		if ( $pod ) {
			$facts[] = array( self::short_origin( $pod ), 'Podrijetlo' );
		}
		if ( $facts ) {
			$h .= '<div class="dsp-facts">';
			foreach ( array_slice( $facts, 0, 3 ) as $f ) {
				$h .= '<div><strong>' . esc_html( $f[0] ) . '</strong><span>' . esc_html( $f[1] ) . '</span></div>';
			}
			$h .= '</div>';
		}

		// nagrade
		if ( $m['nagrade'] ) {
			$h .= '<div class="dsp-awards">';
			foreach ( array_slice( $m['nagrade'], 0, 3 ) as $n ) {
				$img = ! empty( $n['slika'] ) ? wp_get_attachment_image( (int) $n['slika'], 'thumbnail' ) : '';
				$h  .= '<a href="#dsp-nagrade" data-dsp-tab="nagrade">' . ( $img ? $img : '<span class="dsp-medal">' . self::svg( 'medalja' ) . '</span>' )
					. '<span><b>' . esc_html( $n['naslov'] ) . '</b><small>' . esc_html( $n['mjesto'] ?? '' ) . '</small></span></a>';
			}
			$h .= '</div>';
		}

		// cijena
		$h .= '<div class="dsp-buy"><div class="dsp-price">' . $p->get_price_html();
		$price = (float) wc_get_price_to_display( $p );
		if ( $lit && $price > 0 && ! $p->is_type( 'variable' ) ) {
			$h .= '<span class="dsp-unit">' . esc_html( self::eur( $price / $lit ) ) . '/l</span>';
		}
		$h .= '</div>';
		if ( $p->is_on_sale() && ! $p->is_type( 'variable' ) ) {
			$low = Domestic_Proizvod_Data::lowest_30( $p );
			if ( $low ) {
				$h .= '<p class="dsp-anchor">Najniža cijena u zadnjih 30 dana: ' . esc_html( self::eur( $low ) ) . '</p>';
			}
		}
		if ( $m['sidrena'] ) {
			$h .= '<p class="dsp-anchor">Sidrena cijena na dan 2. 5. 2025.: ' . esc_html( self::eur( (float) str_replace( ',', '.', $m['sidrena'] ) ) ) . '</p>';
		}

		// WooCommerceov obrazac (jednostavni i varijabilni proizvodi, zaliha, količina)
		ob_start();
		woocommerce_template_single_add_to_cart();
		$h .= '<div class="dsp-cart">' . ob_get_clean() . '</div>';

		// dostava i stanje
		$h .= '<ul class="dsp-ship">';
		if ( $p->is_in_stock() ) {
			$h .= '<li>' . self::svg( 'check' ) . ( $p->is_on_backorder() ? 'Po narudžbi' : 'Na stanju' ) . ' · šaljemo u ' . esc_html( $s['rok'] ) . '</li>';
		} else {
			$h .= '<li class="dsp-out">Trenutno nema na zalihi</li>';
		}
		if ( (float) $s['besplatno_od'] > 0 ) {
			$h .= '<li>' . self::svg( 'check' ) . 'Besplatna dostava iznad ' . esc_html( self::eur( (float) $s['besplatno_od'] ) ) . ( $s['podrucje'] ? ' (' . esc_html( $s['podrucje'] ) . ')' : '' ) . '</li>';
		}
		$pak = Domestic_Proizvod_Data::attr( $p, 'pakiranje' );
		$h  .= '<li>' . self::svg( 'check' ) . 'Sigurno pakiranje' . ( $pak ? ' · ' . esc_html( $pak ) : '' ) . '</li>';
		$h  .= '</ul></div>';

		// mini red
		$mini = array();
		$withq = array_filter( $m['posluzivanje'], function ( $r ) { return ! empty( $r['kolicina'] ); } );
		if ( count( $withq ) >= 2 ) {
			foreach ( array_slice( $withq, 0, 3 ) as $r ) {
				$mini[] = array( $r['naziv'], $r['kolicina'] );
			}
		} else {
			if ( $pod ) {
				$mini[] = array( 'Podrijetlo', self::short_origin( $pod ) );
			}
			if ( $vrsta ) {
				$mini[] = array( 'Vrsta', self::short( $vrsta, 22 ) );
			}
			if ( $temp ) {
				$mini[] = array( 'Temperatura', $temp );
			}
		}
		if ( $mini ) {
			$h .= '<div class="dsp-mini">';
			foreach ( array_slice( $mini, 0, 3 ) as $r ) {
				$h .= '<div><span>' . esc_html( $r[0] ) . '</span><b>' . esc_html( $r[1] ) . '</b></div>';
			}
			if ( $m['posluzivanje'] ) {
				$h .= '<a href="#dsp-posluzivanje" data-dsp-tab="posluzivanje">Kako poslužiti →</a>';
			}
			$h .= '</div>';
		}
		return $h . '</div>';
	}

	private static function short( string $s, int $n ): string {
		return mb_strlen( $s ) > $n ? rtrim( mb_substr( $s, 0, $n - 1 ) ) . '…' : $s;
	}

	/** „Primorska Hrvatska, podregija Hrvatska Istra, vinogorje Zapadna Istra” → „Zapadna Istra”. */
	private static function short_origin( string $s ): string {
		if ( mb_strlen( $s ) <= 18 ) {
			return $s;
		}
		$parts = array_map( 'trim', explode( ',', $s ) );
		$last  = end( $parts );
		$last  = preg_replace( '/^(vinogorje|podregija|regija)\s+/iu', '', $last );
		return self::short( $last, 22 );
	}

	/* ---------- kartice ---------- */

	public static function tabs( WC_Product $p ): string {
		$m    = Domestic_Proizvod_Data::meta( $p );
		$tabs = array( 'opis' => 'O proizvodu' );
		if ( $m['posluzivanje'] ) {
			$tabs['posluzivanje'] = 'Kako poslužiti';
		}
		if ( $m['nagrade'] ) {
			$tabs['nagrade'] = 'Nagrade';
		}
		$tabs['podaci'] = 'Podaci';
		$rev            = comments_open( $p->get_id() ) && 'yes' === get_option( 'woocommerce_enable_reviews', 'yes' );
		if ( $rev ) {
			$tabs['recenzije'] = 'Recenzije (' . (int) $p->get_review_count() . ')';
		}

		$h = '<section class="dsp-info-tabs" id="dsp-info"><div class="dsp-tabs" role="tablist">';
		$i = 0;
		foreach ( $tabs as $k => $t ) {
			$h .= '<button type="button" role="tab" id="dsp-t-' . $k . '" aria-controls="dsp-' . $k . '" aria-selected="' . ( $i++ ? 'false' : 'true' ) . '" data-dsp-tab="' . $k . '">' . esc_html( $t ) . '</button>';
		}
		$h .= '</div>';

		// O proizvodu
		$content = apply_filters( 'the_content', $p->get_description() );
		$h      .= '<div class="dsp-panel" id="dsp-opis" role="tabpanel"><div class="dsp-cols"><div><div class="dsp-eyebrow">Domaća priča</div><h2>' . esc_html( $m['naslov'] ? $m['naslov'] : $p->get_name() ) . '</h2>' . $content . '</div>';
		if ( $m['razlozi'] ) {
			$h .= '<div class="dsp-box"><h3>' . esc_html( $m['razlozi_naslov'] ? $m['razlozi_naslov'] : 'Zašto odabrati ovaj proizvod?' ) . '</h3><ul>';
			foreach ( $m['razlozi'] as $r ) {
				$h .= '<li>' . esc_html( $r ) . '</li>';
			}
			$h .= '</ul>' . ( $m['zakljucak'] ? '<p class="dsp-closing">' . esc_html( $m['zakljucak'] ) . '</p>' : '' ) . '</div>';
		} else {
			$rows = array();
			foreach ( array( 'vrsta' => 'Vrsta', 'podrijetlo' => 'Podrijetlo', 'gastro' => 'Gastro preporuka' ) as $k => $label ) {
				$v = Domestic_Proizvod_Data::attr( $p, $k );
				if ( $v ) {
					$rows[] = '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( mb_strtoupper( mb_substr( $v, 0, 1 ) ) . mb_substr( $v, 1 ) ) . '</dd>';
				}
			}
			if ( $rows ) {
				$h .= '<div class="dsp-box"><h3>Ukratko o proizvodu</h3><dl>' . implode( '', $rows ) . '</dl></div>';
			}
		}
		$h .= '</div></div>';

		// Kako poslužiti
		if ( $m['posluzivanje'] ) {
			list( $big ) = Domestic_Proizvod_Data::title_parts( $p );
			$h          .= '<div class="dsp-panel" id="dsp-posluzivanje" role="tabpanel" hidden><div class="dsp-eyebrow">' . esc_html( $big ) . ' · Gastro preporuka</div><h2>' . ( 3 === count( $m['posluzivanje'] ) ? 'Tri načina za uživanje.' : 'Kako poslužiti.' ) . '</h2><div class="dsp-serve">';
			foreach ( $m['posluzivanje'] as $i => $r ) {
				$h .= '<article><div class="dsp-serve-top">' . self::svg( isset( self::SVG[ $r['ikona'] ?? '' ] ) ? $r['ikona'] : 'casa' ) . '<span>0' . ( $i + 1 ) . '</span></div><h3>' . esc_html( $r['naziv'] ) . '</h3>'
					. ( ! empty( $r['kolicina'] ) ? '<div class="dsp-dose">' . esc_html( $r['kolicina'] ) . '</div>' : '' )
					. ( ! empty( $r['opis'] ) ? '<p>' . esc_html( $r['opis'] ) . '</p>' : '' ) . '</article>';
			}
			$h   .= '</div>';
			$temp = Domestic_Proizvod_Data::attr( $p, 'temperatura' );
			if ( $temp ) {
				$h .= '<div class="dsp-temp">' . self::svg( 'temp' ) . '<div><span>Temperatura serviranja</span><strong>' . esc_html( $temp ) . '</strong></div></div>';
			}
			$h .= '</div>';
		}

		// Nagrade
		if ( $m['nagrade'] ) {
			$h .= '<div class="dsp-panel" id="dsp-nagrade" role="tabpanel" hidden><div class="dsp-eyebrow">Priznanja</div><h2>Nagrađena kvaliteta.</h2><div class="dsp-award-list">';
			foreach ( $m['nagrade'] as $n ) {
				$img = ! empty( $n['slika'] ) ? wp_get_attachment_image( (int) $n['slika'], 'medium', false, array( 'data-full' => wp_get_attachment_image_url( (int) $n['slika'], 'full' ), 'class' => 'dsp-zoomable' ) ) : '<span class="dsp-medal dsp-medal-big">' . self::svg( 'medalja' ) . '</span>';
				$h  .= '<article>' . $img . '<div><h3>' . esc_html( $n['naslov'] ) . '</h3>' . ( ! empty( $n['mjesto'] ) ? '<div class="dsp-where">' . esc_html( $n['mjesto'] ) . '</div>' : '' ) . ( ! empty( $n['tekst'] ) ? '<p>' . esc_html( $n['tekst'] ) . '</p>' : '' ) . '</div></article>';
			}
			$h .= '</div></div>';
		}

		// Podaci
		$rows = Domestic_Proizvod_Data::attributes( $p );
		$ean  = method_exists( $p, 'get_global_unique_id' ) ? $p->get_global_unique_id() : '';
		if ( $ean ) {
			$rows[] = array( 'EAN kod', $ean, null );
		}
		if ( $p->get_sku() ) {
			$rows[] = array( 'Šifra', $p->get_sku(), null );
		}
		$h .= '<div class="dsp-panel" id="dsp-podaci" role="tabpanel" hidden><div class="dsp-eyebrow">Sve na jednom mjestu</div><h2>Podaci o proizvodu.</h2>';
		if ( $rows ) {
			$h .= '<table class="dsp-data"><tbody>';
			foreach ( $rows as $r ) {
				$h .= '<tr><th>' . esc_html( $r[0] ) . '</th><td>' . esc_html( $r[1] ) . '</td></tr>';
			}
			$h .= '</tbody></table>';
		}
		$h .= '</div>';

		// Recenzije – WooCommerceov predložak
		if ( $rev ) {
			ob_start();
			comments_template();
			$h .= '<div class="dsp-panel" id="dsp-recenzije" role="tabpanel" hidden>' . ob_get_clean() . '</div>';
		}
		return $h . '</section>';
	}

	/* ---------- povezani ---------- */

	public static function related( WC_Product $p ): string {
		$ids = $p->get_upsell_ids();
		if ( count( $ids ) < 4 ) {
			$ids = array_unique( array_merge( $ids, wc_get_related_products( $p->get_id(), 8, $ids ) ) );
		}
		if ( count( $ids ) < 4 ) {
			// dopuna iz istih kategorija (uključuje i proizvode skrivene iz kataloga, ako je i ovaj skriven)
			$more = wc_get_products( array( 'limit' => 8, 'status' => 'publish', 'return' => 'ids', 'exclude' => array_merge( array( $p->get_id() ), $ids ), 'product_category_id' => $p->get_category_ids(), 'orderby' => 'rand' ) );
			$ids  = array_unique( array_merge( $ids, $more ) );
		}
		$hidden = 'hidden' === $p->get_catalog_visibility();
		$ids    = array_slice( array_values( array_filter( $ids, function ( $id ) use ( $hidden, $p ) {
			$x = wc_get_product( $id );
			return $x && (int) $id !== $p->get_id() && 'publish' === $x->get_status() && ( $x->is_visible() || ( $hidden && 'hidden' === $x->get_catalog_visibility() ) );
		} ) ), 0, 4 );
		if ( ! $ids ) {
			return '';
		}
		$h = '<section class="dsp-related"><div class="dsp-head"><h2>Možda će vam se svidjeti</h2><a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">Pogledaj sve →</a></div><div class="dsp-grid">';
		foreach ( $ids as $id ) {
			$x  = wc_get_product( $id );
			$v  = Domestic_Proizvod_Data::attr( $x, 'zapremnina' );
			$l  = Domestic_Proizvod_Data::litres( $v );
			$vr = Domestic_Proizvod_Data::attr( $x, 'vrsta' );
			$meta = array_filter( array( $l ? Domestic_Proizvod_Data::litres_label( $l ) : $v, $vr ? self::short( $vr, 24 ) : '' ) );
			$h .= '<article class="dsp-card"><a class="dsp-card-img" href="' . esc_url( $x->get_permalink() ) . '" tabindex="-1" aria-hidden="true">' . $x->get_image( 'woocommerce_thumbnail' ) . '</a>'
				. '<h3><a href="' . esc_url( $x->get_permalink() ) . '">' . esc_html( $x->get_name() ) . '</a></h3>'
				. ( $meta ? '<div class="dsp-meta">' . esc_html( implode( ' · ', $meta ) ) . '</div>' : '' )
				. '<div class="dsp-card-row"><span class="dsp-card-price">' . $x->get_price_html() . '</span>' . self::loop_button( $x ) . '</div></article>';
		}
		return $h . '</div></section>';
	}

	private static function loop_button( WC_Product $x ): string {
		$ajax = $x->is_type( 'simple' ) && $x->is_purchasable() && $x->is_in_stock() && $x->supports( 'ajax_add_to_cart' );
		return sprintf(
			'<a href="%s" data-quantity="1" data-product_id="%d" data-product_sku="%s" class="dsp-btn dsp-btn-sm button add_to_cart_button%s" rel="nofollow" aria-label="%s">%s</a>',
			esc_url( $x->add_to_cart_url() ),
			$x->get_id(),
			esc_attr( $x->get_sku() ),
			$ajax ? ' ajax_add_to_cart' : '',
			esc_attr( $x->add_to_cart_description() ),
			$ajax ? 'U košaricu' : 'Pogledaj'
		);
	}

	/* ---------- traka „U košaricu” (mobitel) ---------- */

	public static function sticky( WC_Product $p ): string {
		if ( ! $p->is_purchasable() || ! $p->is_in_stock() ) {
			return '';
		}
		return '<div class="dsp-sticky" aria-hidden="true">' . $p->get_image( 'woocommerce_gallery_thumbnail' ) . '<div><b>' . esc_html( $p->get_name() ) . '</b><span>' . $p->get_price_html() . '</span></div>'
			. '<button type="button" class="dsp-btn" tabindex="-1">' . self::svg( 'bag' ) . 'U košaricu</button></div>';
	}
}
