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
		'lijevo' => '<path d="m14.5 6-6 6 6 6"/>',
		'stit'    => '<path d="M12 3 4.5 6v5.5c0 4.6 3.2 8.4 7.5 9.5 4.3-1.1 7.5-4.9 7.5-9.5V6L12 3Z"/><path d="m8.8 12 2.2 2.2 4.3-4.4"/>',
		'nagrada' => '<circle cx="12" cy="9" r="6"/><path d="m8 14-2 8 6-3 6 3-2-8"/>',
		'desno'  => '<path d="m9.5 6 6 6-6 6"/>',
		'kamion' => '<path d="M2.5 6.5h11v9h-11zM13.5 9.5h4l3 3.2v2.8h-7"/><circle cx="6.5" cy="17" r="1.8"/><circle cx="16.5" cy="17" r="1.8"/>',
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
		// Flatsome „Brzi pregled”: isti izgled kao stranica proizvoda, sažeto
		add_filter( 'wc_get_template_part', array( __CLASS__, 'quickview_part' ), 20, 3 );
		add_filter( 'wc_get_template', array( __CLASS__, 'quickview_tpl' ), 20, 2 );
	}

	public static function quickview_part( $tpl, $slug, $name ) {
		return 'content' === $slug && 'single-product-lightbox' === $name ? self::quickview_file( $tpl ) : $tpl;
	}

	public static function quickview_tpl( $tpl, $name ) {
		return 'content-single-product-lightbox.php' === $name ? self::quickview_file( $tpl ) : $tpl;
	}

	private static function quickview_file( $tpl ) {
		global $product;
		$p = $product instanceof WC_Product ? $product : wc_get_product( get_the_ID() );
		return $p && Domestic_Proizvod_Data::is_on( $p ) ? DSP_DIR . '/templates/quickview.php' : $tpl;
	}

	/** Sadržaj brzog pregleda: galerija | naslov, cijena, zapremnine, kupnja, uvod, poveznica na proizvod. */
	public static function quickview( WC_Product $p ): string {
		$head = preg_replace( '#<h1 class="dsp-title">(.*?)</h1>#s', '<h2 class="dsp-title"><a href="' . esc_url( $p->get_permalink() ) . '">$1</a></h2>', self::head( $p ) );
		$buy  = str_replace( 'href="#dsp-dostava" data-dsp-open="dostava"', 'href="' . esc_url( $p->get_permalink() . '#dsp-dostava' ) . '"', self::buy( $p ) );
		$lead = self::lead_text( $p );
		$v    = DSP_VER . '.' . filemtime( DSP_DIR . '/assets/proizvod.css' );
		return '<link rel="stylesheet" href="' . esc_url( DSP_URL . 'assets/proizvod.css?ver=' . $v ) . '">'
			. '<div class="dsp dsp-qv"><section class="dsp-product">' . self::gallery( $p )
			. '<div class="dsp-info-col">' . $head . $buy
			. ( '' !== $lead ? '<p class="dsp-intro">' . esc_html( $lead ) . '</p>' : '' )
			. '<a class="dsp-qv-more" href="' . esc_url( $p->get_permalink() ) . '">Sve o proizvodu: opis, posluživanje, nagrade ' . self::svg( 'desno' ) . '</a>'
			. '</div></section></div>';
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
			// ostale stranice: samo skripta za brzi pregled (CSS dolazi s njegovim sadržajem)
			if ( 'flatsome' === get_template() ) {
				wp_enqueue_script( 'domestic-proizvod', DSP_URL . 'assets/proizvod.js', array(), DSP_VER . '.' . filemtime( DSP_DIR . '/assets/proizvod.js' ), true );
			}
			return;
		}
		$v = DSP_VER . '.' . filemtime( DSP_DIR . '/assets/proizvod.css' );
		wp_enqueue_style( 'domestic-proizvod', DSP_URL . 'assets/proizvod.css', array(), $v );
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
			. '<section class="dsp-product">' . self::gallery( $p )
			. '<div class="dsp-info-col">' . self::head( $p ) . self::buy( $p ) . self::extra( $p ) . '</div></section>'
			. self::tabs( $p )
			. self::related( $p )
			. self::sticky( $p )
			. '</div>';
	}

	/** Putanja: Svi proizvodi / kategorija / marka. */
	public static function crumb( WC_Product $p ): string {
		$h   = '<nav class="dsp-crumb" aria-label="Putanja"><a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">Svi proizvodi</a>';
		$cat = self::primary_cat( $p );
		if ( $cat ) {
			$h .= '<span>/</span><a href="' . esc_url( get_term_link( $cat ) ) . '">' . esc_html( $cat->name ) . '</a>';
		}
		list( $big ) = Domestic_Proizvod_Data::title_parts( $p );
		return $h . '<span>/</span><span aria-current="page">' . esc_html( $big ) . '</span></nav>';
	}

	private static function primary_cat( WC_Product $p ): ?WP_Term {
		$best = null;
		foreach ( $p->get_category_ids() as $id ) {
			$t = get_term( $id, 'product_cat' );
			if ( $t instanceof WP_Term && ( ! $best || count( get_ancestors( $t->term_id, 'product_cat' ) ) > count( get_ancestors( $best->term_id, 'product_cat' ) ) ) ) {
				$best = $t; // najdublja kategorija
			}
		}
		return $best;
	}

	/* ---------- naslov ---------- */

	/** Naslov: marka/vrsta iznad, puni naziv (bez zapremnine) jednim naslovom, ključne činjenice kao oznake. */
	public static function head( WC_Product $p ): string {
		$m     = Domestic_Proizvod_Data::meta( $p );
		$marka = Domestic_Proizvod_Data::attr( $p, 'marka' );
		if ( ! $marka ) {
			list( $big, $small ) = Domestic_Proizvod_Data::title_parts( $p );
			$marka = $small ? $big : '';
		}
		$cat = self::primary_cat( $p );
		$eb  = $m['eyebrow'] ? $m['eyebrow'] : implode( ' · ', array_filter( array( $marka, $cat ? $cat->name : '' ) ) );
		$lit = Domestic_Proizvod_Data::litres_of( $p );
		$alk = Domestic_Proizvod_Data::attr( $p, 'alkohol' );
		$pod = Domestic_Proizvod_Data::attr( $p, 'podrijetlo' );
		$chips = array_filter(
			array(
				Domestic_Proizvod_Data::volume_label( $p ),
				$alk ? ( preg_match( '/(\d+(?:[.,]\d+)?)/', $alk, $am ) ? str_replace( '.', ',', $am[1] ) . ' % vol.' : $alk ) : '',
				$pod ? self::short_origin( $pod ) : '',
			)
		);
		$h = '<header class="dsp-head">' . ( $eb ? '<div class="dsp-eyebrow">' . esc_html( $eb ) . '</div>' : '' )
			. '<h1 class="dsp-title">' . esc_html( Domestic_Proizvod_Data::name_without_volume( $p->get_name() ) ) . '</h1>';
		if ( $chips ) {
			$h .= '<ul class="dsp-chips">';
			foreach ( $chips as $c ) {
				$h .= '<li>' . esc_html( $c ) . '</li>';
			}
			$h .= '</ul>';
		}
		return $h . '</header>';
	}

	/* ---------- galerija: klizač s oznakama slika ---------- */

	public static function gallery( WC_Product $p ): string {
		$ids    = array_values( array_unique( array_filter( array_merge( array( $p->get_image_id() ), $p->get_gallery_image_ids() ) ) ) );
		$labels = array_map( 'trim', explode( '|', (string) Domestic_Proizvod_Data::settings()['slike'] ) );
		$naziv  = $p->get_name();
		$b      = '';
		foreach ( Domestic_Proizvod_Data::badges( $p ) as $k => $t ) {
			$b .= '<span class="dsp-tag dsp-tag-' . esc_attr( $k ) . '">' . esc_html( $t ) . '</span>';
		}
		if ( ! $ids ) {
			return '<div class="dsp-gallery"><div class="dsp-stage"><div class="dsp-track"><div class="dsp-slide">' . wc_placeholder_img( 'woocommerce_single' ) . '</div></div></div></div>';
		}
		$n   = count( $ids );
		$lab = function ( $i ) use ( $labels ) {
			return ! empty( $labels[ $i ] ) ? $labels[ $i ] : '';
		};
		$h = '<div class="dsp-gallery" data-n="' . $n . '"><div class="dsp-stage"><div class="dsp-track" tabindex="0" aria-label="Slike proizvoda, ' . $n . '">';
		foreach ( $ids as $i => $id ) {
			$alt = get_post_meta( $id, '_wp_attachment_image_alt', true );
			$h  .= '<figure class="dsp-slide" data-label="' . esc_attr( $lab( $i ) ) . '">'
				. wp_get_attachment_image( $id, 'woocommerce_single', false, array( 'class' => 'dsp-img', 'alt' => $alt ? $alt : $naziv, 'data-full' => wp_get_attachment_image_url( $id, 'full' ), 'loading' => $i ? 'lazy' : 'eager', 'fetchpriority' => $i ? 'low' : 'high', 'sizes' => '(max-width: 760px) 100vw, 640px' ) )
				. '</figure>';
		}
		$h .= '</div>'
			. ( class_exists( 'Domestic_Kartica' ) && 'yes' === Domestic_Kartica::postavke()['ukljuceno'] ? Domestic_Kartica::oznake_html( $p, 3, 'dsk-oznake dsp-oznake' ) : ( $b ? '<div class="dsp-tags">' . $b . '</div>' : '' ) )
			. ( $n > 1 ? '<span class="dsp-count"><span class="dsp-count-n">1</span> / ' . $n . '</span>'
				. '<button type="button" class="dsp-arrow dsp-prev" aria-label="Prethodna slika">' . self::svg( 'lijevo' ) . '</button><button type="button" class="dsp-arrow dsp-next" aria-label="Sljedeća slika">' . self::svg( 'desno' ) . '</button>' : '' )
			. '<button type="button" class="dsp-zoom" aria-label="Povećaj sliku">' . self::svg( 'zoom' ) . '</button>'
			. '</div>';
		if ( $n > 1 ) {
			$h .= '<div class="dsp-strip' . ( $n > 3 ? ' dsp-strip-vise' : '' ) . '"><div class="dsp-thumbs" role="list">';
			foreach ( $ids as $i => $id ) {
				$h .= '<button type="button" role="listitem" class="dsp-thumb' . ( $i ? '' : ' on' ) . '" data-i="' . $i . '" aria-label="' . esc_attr( 'Slika ' . ( $i + 1 ) . ( $lab( $i ) ? ': ' . $lab( $i ) : '' ) ) . '">'
					. wp_get_attachment_image( $id, 'woocommerce_gallery_thumbnail', false, array( 'loading' => $i < 3 ? 'eager' : 'lazy', 'alt' => '' ) )
					. ( $lab( $i ) ? '<span>' . esc_html( $lab( $i ) ) . '</span>' : '' ) . '</button>';
			}
			$h .= '</div>' . ( $n > 3 ? '<button type="button" class="dsp-strip-btn dsp-strip-prev" aria-label="Prethodne sličice" hidden>' . self::svg( 'lijevo' ) . '</button><button type="button" class="dsp-strip-btn dsp-strip-next" aria-label="Sljedeće sličice">' . self::svg( 'desno' ) . '</button>' : '' ) . '</div>';
		}
		return $h . '</div>';
	}

	/* ---------- kupnja ---------- */

	public static function buy( WC_Product $p ): string {
		$s     = Domestic_Proizvod_Data::settings();
		$m     = Domestic_Proizvod_Data::meta( $p );
		$lit   = Domestic_Proizvod_Data::litres_of( $p );
		$price = (float) wc_get_price_to_display( $p );
		$h     = '<div class="dsp-buybox">';
		$sib   = Domestic_Proizvod_Data::siblings( $p );
		if ( $sib ) {
			$h .= '<div class="dsp-vol"><div class="dsp-label">Odaberi zapremninu</div><div class="dsp-vol-list' . ( count( $sib ) > 2 ? ' dsp-vol-vise' : '' ) . '">';
			foreach ( $sib as $s_ ) {
				list( $x, $l ) = $s_;
				$on    = $x->get_id() === $p->get_id();
				$ima   = $s_[2] ?? true;
				$cij   = $ima || $on ? self::eur( (float) wc_get_price_to_display( $x ) ) : 'Rasprodano';
				$sad   = '<b>' . esc_html( Domestic_Proizvod_Data::volume_label( $x ) ) . '</b><span>' . esc_html( $cij ) . '</span>';
				$klasa = 'dsp-vol-opt' . ( $on ? ' on' : '' ) . ( $ima ? '' : ' dsp-vol-nema' );
				if ( ! empty( $s_[3] ) ) {
					$h .= '<span class="' . $klasa . '" aria-disabled="true">' . $sad . '</span>';
				} else {
					$h .= '<a class="' . $klasa . '" href="' . esc_url( $x->get_permalink() ) . '"' . ( $on ? ' aria-current="true"' : '' ) . '>' . $sad . '</a>';
				}
			}
			$h .= '</div></div>';
		}

		$vol     = $h;
		$h       = '';
		$ph      = $p->get_price_html();
		$sidrena = '';
		if ( preg_match( '#<span class="sidrena-cijena">.*?</span></span>#s', $ph, $sm ) || preg_match( '#<span class="sidrena-cijena">.*?</span>#s', $ph, $sm ) ) {
			$sidrena = $sm[0]; // dodatak „Sidrena cijena” je dopisuje u cijenu – prikazujemo je ispod
			$ph      = str_replace( $sidrena, '', $ph );
		}
		$h .= '<div class="dsp-price-row"><div class="dsp-price">' . $ph . '</div>';
		$info = array();
		if ( $lit && $price > 0 && ! $p->is_type( 'variable' ) ) {
			$info[] = self::eur( $price / $lit ) . ' / l';
		}
		if ( wc_tax_enabled() || 'yes' === get_option( 'woocommerce_prices_include_tax' ) ) {
			$info[] = 'PDV uključen';
		}
		$h .= $info ? '<span class="dsp-unit">' . esc_html( implode( ' · ', $info ) ) . '</span>' : '';
		$h .= '</div>';
		if ( $sidrena ) {
			$h .= '<p class="dsp-anchor">' . wp_kses_post( $sidrena ) . '</p>';
		}
		if ( $p->is_on_sale() && ! $p->is_type( 'variable' ) ) {
			$low = Domestic_Proizvod_Data::lowest_30( $p );
			if ( $low ) {
				$h .= '<p class="dsp-anchor">Najniža cijena u zadnjih 30 dana: ' . esc_html( self::eur( $low ) ) . '</p>';
			}
		}
		if ( $m['sidrena'] && ! $sidrena ) {
			$h .= '<p class="dsp-anchor">Sidrena cijena 02.05.2025.: ' . esc_html( self::eur( (float) str_replace( ',', '.', $m['sidrena'] ) ) ) . '</p>';
		}

		// cijena na vrhu okvira, pa zapremnine
		$h = '<div class="dsp-buybox">' . $h . substr( $vol, strlen( '<div class="dsp-buybox">' ) );
		ob_start();
		woocommerce_template_single_add_to_cart();
		$h .= '<div class="dsp-cart">' . ob_get_clean() . '</div>';
		// povjerenje: zaliha i rok, dostava, sigurna kupnja
		$h .= '<ul class="dsp-trust">';
		$h .= $p->is_in_stock()
			? '<li class="dsp-ok">' . self::svg( 'check' ) . '<span><b>' . ( $p->is_on_backorder() ? 'Po narudžbi' : 'Na stanju' ) . '</b> · šaljemo u ' . esc_html( $s['rok'] ) . '</span></li>'
			: '<li class="dsp-out">Trenutno nema na zalihi</li>';
		if ( (float) $s['besplatno_od'] > 0 ) {
			$h .= '<li>' . self::svg( 'kamion' ) . '<span>Besplatna dostava iznad ' . esc_html( self::eur( (float) $s['besplatno_od'] ) ) . ( $s['podrucje'] ? ' (' . esc_html( $s['podrucje'] ) . ')' : '' ) . ' · <a class="dsp-link" href="#dsp-dostava" data-dsp-open="dostava">uvjeti</a></span></li>';
		}
		$h .= '<li>' . self::svg( 'stit' ) . '<span>Sigurno pakiranje' . ( $lit ? ' boca' : '' ) . ' i plaćanje</span></li></ul>';
		return $h . '</div>';
	}

	/** Ispod kupnje: uvodna rečenica, red s nagradama, poveznica na posluživanje. */
	public static function extra( WC_Product $p ): string {
		$m     = Domestic_Proizvod_Data::meta( $p );
		$short = self::lead_text( $p );
		$h     = '' !== $short ? '<p class="dsp-intro">' . esc_html( $short ) . '</p>' : '';
		if ( $m['nagrade'] ) {
			$t  = implode( ' · ', array_map( function ( $n ) { return rtrim( $n['naslov'], '.' ) . '.'; }, array_slice( $m['nagrade'], 0, 3 ) ) );
			$h .= '<a class="dsp-awardline" href="#dsp-nagrade" data-dsp-open="nagrade">' . self::svg( 'nagrada' ) . '<span>' . esc_html( $t ) . '</span><i aria-hidden="true">↓</i></a>';
		}
		if ( $m['posluzivanje'] || Domestic_Proizvod_Data::attr( $p, 'gastro' ) || Domestic_Proizvod_Data::attr( $p, 'temperatura' ) ) {
			$h .= '<a class="dsp-link dsp-serve-link" href="#dsp-posluzivanje" data-dsp-open="posluzivanje">Kako poslužiti ↓</a>';
		}
		return $h ? '<div class="dsp-extra">' . $h . '</div>' : '';
	}

	/** Uvodna rečenica: kratki opis ili opis, bez blokova „Naziv: vrijednost”. */
	private static function lead_text( WC_Product $p ): string {
		if ( preg_match( '#<p>(.*?)</p>#s', Domestic_Proizvod_Data::opis( $p )['tekst'], $m ) ) {
			$t = trim( html_entity_decode( wp_strip_all_tags( $m[1] ), ENT_QUOTES, 'UTF-8' ) );
			if ( mb_strlen( $t ) >= 20 ) {
				return mb_strlen( $t ) > 220 ? wp_trim_words( $t, 30 ) : $t;
			}
		}
		foreach ( array( $p->get_short_description(), $p->get_description() ) as $src ) {
			$t = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( str_replace( array( '</p>', '<br>', '<br />', '</li>' ), ' ¶ ', (string) $src ) ) ) );
			foreach ( array_map( 'trim', explode( '¶', $t ) ) as $odlomak ) {
				$odlomak = preg_replace( '/^(opis|o proizvodu|kratki opis)\s*:\s*/iu', '', $odlomak );
				if ( '' === $odlomak || preg_match( '/^[^.:!?]{1,40}:/u', $odlomak ) ) {
					continue; // „Naziv likera: …”, „Vrsta: …”
				}
				$recenice = preg_split( '/(?<=[.!?])\s+/u', $odlomak );
				$out      = '';
				foreach ( $recenice as $r ) {
					if ( mb_strlen( $out . ' ' . $r ) > 190 && '' !== $out ) {
						break;
					}
					$out = trim( $out . ' ' . $r );
				}
				if ( mb_strlen( $out ) >= 20 ) {
					return mb_strlen( $out ) > 220 ? wp_trim_words( $out, 30 ) : $out;
				}
			}
		}
		return '';
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

	/* ---------- sekcije (harmonika) ---------- */

	private static function section( string $id, string $naslov, string $pod, string $sadrzaj, bool $otvoreno = false ): string {
		return '<details class="dsp-sec" id="dsp-' . esc_attr( $id ) . '"' . ( $otvoreno ? ' open' : '' ) . '><summary><span><b>' . esc_html( $naslov ) . '</b><small>' . esc_html( $pod ) . '</small></span><i aria-hidden="true"></i></summary><div class="dsp-sec-body">' . $sadrzaj . '</div></details>';
	}

	public static function tabs( WC_Product $p ): string {
		$m     = Domestic_Proizvod_Data::meta( $p );
		$st    = Domestic_Proizvod_Data::settings();
		$lijevo = array();
		$desno  = array();
		$nav    = array();

		// Opis
		$opis = Domestic_Proizvod_Data::opis( $p );
		$o    = '' !== $opis['tekst'] ? $opis['tekst'] : apply_filters( 'the_content', $p->get_description() );
		if ( $m['razlozi'] ) {
			$o .= '<h3>' . esc_html( $m['razlozi_naslov'] ? $m['razlozi_naslov'] : 'Zašto odabrati ovaj proizvod?' ) . '</h3><ul>';
			foreach ( $m['razlozi'] as $r ) {
				$o .= '<li>' . esc_html( $r ) . '</li>';
			}
			$o .= '</ul>' . ( $m['zakljucak'] ? '<p>' . esc_html( $m['zakljucak'] ) . '</p>' : '' );
		}
		$lijevo[]    = self::section( 'opis', $m['naslov'] ? $m['naslov'] : 'O proizvodu', 'Opis i karakteristike', $o, true );
		$nav['opis'] = 'Opis';

		// Podaci
		$rows = Domestic_Proizvod_Data::attributes( $p );
		$ima  = array_map( function ( $r ) { return Domestic_Proizvod_Data::norm( $r[0] ); }, $rows );
		foreach ( $opis['podaci'] as $r ) {
			if ( ! in_array( Domestic_Proizvod_Data::norm( $r[0] ), $ima, true ) ) {
				$rows[] = array( $r[0], $r[1], null );
				$ima[]  = Domestic_Proizvod_Data::norm( $r[0] );
			}
		}
		$ean = method_exists( $p, 'get_global_unique_id' ) ? $p->get_global_unique_id() : '';
		$ima_ean = array_filter( $rows, function ( $r ) { return 0 === strpos( Domestic_Proizvod_Data::norm( $r[0] ), 'ean' ); } );
		if ( $ean && ! $ima_ean ) {
			$rows[] = array( 'EAN kod', $ean, null );
			$ima_ean = true;
		}
		$vrijednosti = array_map( function ( $r ) { return trim( $r[1] ); }, $rows );
		if ( $p->get_sku() && ! in_array( $p->get_sku(), $vrijednosti, true ) ) {
			$rows[] = array( 'Šifra', $p->get_sku(), null );
		}
		if ( $rows ) {
			$t = '<table class="dsp-data"><tbody>';
			foreach ( $rows as $r ) {
				$t .= '<tr><th scope="row">' . esc_html( $r[0] ) . '</th><td>' . esc_html( $r[1] ) . '</td></tr>';
			}
			$lijevo[]      = self::section( 'podaci', 'Podaci o proizvodu', 'Podrijetlo, ambalaža i deklaracija', $t . '</tbody></table>', true );
			$nav['podaci'] = 'Podaci o proizvodu';
		}

		// Kako poslužiti: kartice iz kartice „Domestic” ili gastro preporuka, temperatura, slika s uputama (3. slika)
		$gastro = Domestic_Proizvod_Data::attr( $p, 'gastro' );
		$temp   = Domestic_Proizvod_Data::attr( $p, 'temperatura' );
		$ids    = array_values( array_unique( array_filter( array_merge( array( $p->get_image_id() ), $p->get_gallery_image_ids() ) ) ) );
		$upute  = $ids[2] ?? 0;
		if ( $m['posluzivanje'] || $gastro || $temp || $upute ) {
			$k = '';
			if ( $m['posluzivanje'] ) {
				$k .= '<div class="dsp-serve">';
				foreach ( $m['posluzivanje'] as $r ) {
					$ilu = ! empty( $r['slika'] ) ? wp_get_attachment_image( (int) $r['slika'], 'medium', false, array( 'class' => 'dsp-serve-ilu', 'alt' => '', 'loading' => 'lazy' ) ) : '';
					$k  .= '<article' . ( $ilu ? ' class="dsp-serve-sa-ilu"' : '' ) . '>' . ( $ilu ? $ilu : '<div class="dsp-serve-ico">' . self::svg( isset( self::SVG[ $r['ikona'] ?? '' ] ) ? $r['ikona'] : 'casa' ) . '</div>' ) . '<h4>' . esc_html( $r['naziv'] ) . '</h4>'
						. ( ! empty( $r['kolicina'] ) ? '<div class="dsp-dose">' . esc_html( $r['kolicina'] ) . '</div>' : '' )
						. ( ! empty( $r['opis'] ) ? '<p>' . esc_html( $r['opis'] ) . '</p>' : '' ) . '</article>';
				}
				$k .= '</div>';
			} elseif ( $gastro ) {
				$k .= '<p>' . esc_html( mb_strtoupper( mb_substr( $gastro, 0, 1 ) ) . mb_substr( $gastro, 1 ) ) . '</p>';
			}
			if ( $temp ) {
				$k .= '<div class="dsp-temp"><span>Temperatura serviranja</span><strong>' . esc_html( $temp ) . '</strong></div>';
			}
			if ( $upute && ! $m['posluzivanje'] ) { // velika kartica samo kad nema kućica s načinima posluživanja
				$k .= '<button type="button" class="dsp-guide dsp-zoomable" data-full="' . esc_url( wp_get_attachment_image_url( $upute, 'full' ) ) . '">'
					. wp_get_attachment_image( $upute, 'woocommerce_single', false, array( 'loading' => 'lazy' ) )
					. '<span>Povećaj sliku ' . self::svg( 'zoom' ) . '</span></button>';
			}
			$desno[]             = self::section( 'posluzivanje', 'Kako poslužiti', 'Preporuka za ovaj proizvod', $k, true );
			$nav['posluzivanje'] = 'Posluživanje';
		}

		// Nagrade
		if ( $m['nagrade'] ) {
			$k   = '';
			$god = array();
			foreach ( $m['nagrade'] as $n ) {
				if ( preg_match( '/(19|20)\d{2}/', $n['naslov'] . ' ' . ( $n['mjesto'] ?? '' ) . ' ' . ( $n['tekst'] ?? '' ), $g ) ) {
					$god[] = $g[0];
				}
				$img = ! empty( $n['slika'] ) ? wp_get_attachment_image( (int) $n['slika'], 'thumbnail', false, array( 'data-full' => wp_get_attachment_image_url( (int) $n['slika'], 'full' ), 'class' => 'dsp-zoomable' ) ) : '<span class="dsp-medal">' . self::svg( 'nagrada' ) . '</span>';
				$k  .= '<article class="dsp-award">' . $img . '<div><h3>' . esc_html( $n['naslov'] ) . '</h3>' . ( ! empty( $n['mjesto'] ) ? '<div class="dsp-where">' . esc_html( $n['mjesto'] ) . '</div>' : '' ) . ( ! empty( $n['tekst'] ) ? '<p>' . esc_html( $n['tekst'] ) . '</p>' : '' ) . '</div></article>';
			}
			$god            = array_values( array_unique( $god ) );
			rsort( $god );
			$pod            = $god ? ( count( $god ) > 1 ? implode( '., ', array_slice( $god, 0, -1 ) ) . '. i ' . end( $god ) . '.' : $god[0] . '.' ) : 'Priznanja';
			$desno[]        = self::section( 'nagrade', 'Nagrade i priznanja', $pod, $k, true );
			$nav['nagrade'] = 'Nagrade';
		}

		// Dostava i plaćanje
		$url = $st['dostava_url'];
		if ( ! $url ) {
			$o_shopu = get_page_by_path( 'o-shopu' );
			$url     = $o_shopu ? get_permalink( $o_shopu ) : '';
		}
		$d = self::dostava_placanje( $st, (string) $url );
		$desno[] = self::section( 'dostava', 'Dostava i plaćanje', 'Cijene dostave, rok slanja i načini plaćanja', $d, true );
		$nav['dostava'] = 'Dostava i plaćanje';

		// Recenzije – WooCommerceov predložak
		if ( comments_open( $p->get_id() ) && 'yes' === get_option( 'woocommerce_enable_reviews', 'yes' ) ) {
			ob_start();
			comments_template();
			$lijevo[] = self::section( 'recenzije', 'Recenzije', (int) $p->get_review_count() . ' recenzija', ob_get_clean() );
		}

		$h = '<nav class="dsp-secnav" aria-label="Sadržaj stranice"><span>Na ovoj stranici</span>';
		foreach ( $nav as $k => $t ) {
			$h .= '<a href="#dsp-' . esc_attr( $k ) . '" data-dsp-open="' . esc_attr( $k ) . '">' . esc_html( $t ) . '</a>';
		}
		return $h . '</nav><section class="dsp-info" id="dsp-info"><div>' . implode( '', $lijevo ) . '</div><div>' . implode( '', $desno ) . '</div></section>';
	}

	/** Dostava i plaćanje iz WooCommercea (zone i načini dostave, uključeni načini plaćanja) – uvijek točno. */
	private static function dostava_placanje( array $st, string $url ): string {
		$zone = array();
		if ( class_exists( 'WC_Shipping_Zones' ) ) {
			$drz = WC()->countries->get_states( 'HR' );
			foreach ( WC_Shipping_Zones::get_zones() as $z ) {
				$mjesta = array();
				$samo_hr = false;
				foreach ( $z['zone_locations'] as $l ) {
					if ( 'state' === $l->type && 0 === strpos( $l->code, 'HR:' ) ) {
						$mjesta[] = $drz[ substr( $l->code, 3 ) ] ?? $l->code;
					} elseif ( 'country' === $l->type && 'HR' === $l->code ) {
						$samo_hr = true;
					}
				}
				$cijena = '';
				$besplatno = '';
				foreach ( $z['shipping_methods'] as $m ) {
					if ( 'yes' !== $m->enabled ) {
						continue;
					}
					if ( 'flat_rate' === $m->id && '' !== (string) $m->get_option( 'cost' ) && is_numeric( str_replace( ',', '.', (string) $m->get_option( 'cost' ) ) ) ) {
						$cijena = self::eur( (float) str_replace( ',', '.', (string) $m->get_option( 'cost' ) ) );
					} elseif ( 'free_shipping' === $m->id ) {
						$min       = (float) $m->get_option( 'min_amount' );
						$besplatno = $min > 0 ? 'besplatno iznad ' . self::eur( $min ) : 'besplatno';
					} elseif ( 'local_pickup' === $m->id || 'pickup_location' === $m->id ) {
						$besplatno = $besplatno ? $besplatno : 'osobno preuzimanje';
					}
				}
				if ( ! $cijena && ! $besplatno ) {
					continue;
				}
				$gdje   = $mjesta && count( $mjesta ) <= 3 ? implode( ' i ', array_reverse( $mjesta ) ) : ( $mjesta || $samo_hr ? 'Ostatak Hrvatske' : $z['zone_name'] );
				$zone[] = array( $gdje, $cijena, $besplatno );
			}
		}
		$plac = array();
		if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
			foreach ( WC()->payment_gateways()->payment_gateways() as $g ) {
				if ( 'yes' === $g->enabled ) {
					$plac[] = wp_strip_all_tags( $g->get_title() );
				}
			}
		}
		$h = '<div class="dsp-dp"><div class="dsp-dp-col"><h4>' . self::svg( 'kamion' ) . 'Dostava</h4><ul>';
		foreach ( $zone as list( $gdje, $cijena, $besplatno ) ) {
			$h .= '<li><span>' . esc_html( $gdje ) . '</span><b>' . esc_html( $cijena ? $cijena : $besplatno ) . ( $cijena && $besplatno ? '<small>' . esc_html( $besplatno ) . '</small>' : '' ) . '</b></li>';
		}
		$h .= '<li><span>Rok slanja</span><b>' . esc_html( $st['rok'] ) . '</b></li></ul></div>';
		if ( $plac ) {
			$h .= '<div class="dsp-dp-col"><h4>' . self::svg( 'stit' ) . 'Plaćanje</h4><ul class="dsp-dp-plac">';
			foreach ( $plac as $t ) {
				$h .= '<li>' . self::svg( 'check' ) . esc_html( $t ) . '</li>';
			}
			$h .= '</ul></div>';
		}
		$h .= '</div><p class="dsp-dp-nap">Boce pakiramo pažljivo, zaštićene za prijevoz.</p>';
		if ( $url ) {
			$h .= '<a class="dsp-link" href="' . esc_url( $url ) . '">Svi uvjeti dostave i plaćanja →</a>';
		}
		return $h;
	}

	/* ---------- povezani ---------- */

	public static function related( WC_Product $p ): string {
		$ids = $p->get_upsell_ids();
		if ( count( $ids ) < 6 ) {
			$ids = array_unique( array_merge( $ids, wc_get_related_products( $p->get_id(), 12, $ids ) ) );
		}
		if ( count( $ids ) < 6 ) {
			// dopuna iz istih kategorija (uključuje i proizvode skrivene iz kataloga, ako je i ovaj skriven)
			$more = wc_get_products( array( 'limit' => 12, 'status' => 'publish', 'return' => 'ids', 'exclude' => array_merge( array( $p->get_id() ), $ids ), 'product_category_id' => $p->get_category_ids(), 'orderby' => 'rand' ) );
			$ids  = array_unique( array_merge( $ids, $more ) );
		}
		$hidden = 'hidden' === $p->get_catalog_visibility();
		$ids    = array_slice( array_values( array_filter( $ids, function ( $id ) use ( $hidden, $p ) {
			$x = wc_get_product( $id );
			return $x && (int) $id !== $p->get_id() && 'publish' === $x->get_status() && ( $x->is_visible() || ( $hidden && 'hidden' === $x->get_catalog_visibility() ) );
		} ) ), 0, 6 );
		if ( ! $ids ) {
			return '';
		}
		$h = '<section class="dsp-related"><div class="dsp-rel-head"><h2>Još domaćih priča.</h2><a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">Pogledaj sve →</a></div><div class="dsp-grid">';
		foreach ( $ids as $id ) {
			$x   = wc_get_product( $id );
			$l   = Domestic_Proizvod_Data::litres_of( $x );
			$cat = self::primary_cat( $x );
			$meta = array_filter( array( $cat ? $cat->name : '', Domestic_Proizvod_Data::volume_label( $x ) ) );
			$h  .= '<a class="dsp-card" href="' . esc_url( $x->get_permalink() ) . '"><span class="dsp-card-img">' . $x->get_image( 'woocommerce_single', array( 'loading' => 'lazy', 'sizes' => '(max-width: 760px) 45vw, 220px' ) ) . '</span>'
				. ( $meta ? '<span class="dsp-card-cat">' . esc_html( implode( ' · ', $meta ) ) . '</span>' : '' )
				. '<h3>' . esc_html( Domestic_Proizvod_Data::name_without_volume( $x->get_name() ) ) . '</h3>'
				. '<span class="dsp-card-row"><strong>' . esc_html( self::eur( (float) wc_get_price_to_display( $x ) ) ) . '</strong><span>Pogledaj ' . self::svg( 'desno' ) . '</span></span></a>';
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
		$lit = Domestic_Proizvod_Data::litres_of( $p );
		return '<div class="dsp-sticky" aria-hidden="true"><div class="dsp-sticky-p">' . ( $lit ? '<small>' . esc_html( Domestic_Proizvod_Data::volume_label( $p ) ) . '</small>' : '' )
			. '<b>' . esc_html( self::eur( (float) wc_get_price_to_display( $p ) ) ) . '</b></div>'
			. '<button type="button" class="dsp-btn" tabindex="-1">' . self::svg( 'bag' ) . 'Dodaj u košaricu</button></div>';
	}
}
