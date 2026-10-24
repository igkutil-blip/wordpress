<?php
/**
 * Kartica proizvoda: postavke, podaci za karticu i zamjena predloška content-product.php (i Flatsomeova).
 */

defined( 'ABSPATH' ) || exit;

final class Domestic_Kartica {

	const OPT = 'domestic_kartica';

	/** Elementi kartice ispod slike (ključ => naziv u administraciji). */
	const ELEMENTI = array(
		'kategorija' => 'Kategorija',
		'marka'      => 'Marka / proizvođač',
		'naziv'      => 'Naziv proizvoda',
		'podaci'     => 'Zapremnina · alkohol',
		'kratki'     => 'Kratki opis (2 retka)',
		'cijena'     => 'Cijena (sa sidrenom cijenom)',
		'jedinicna'  => 'Cijena po litri',
		'gumb'       => 'Gumb „U košaricu”',
	);

	const OZNAKE = array(
		'akcija'     => 'Akcija (−%)',
		'novo'       => 'Novo',
		'nagradeno'  => 'Nagrađeno (nagrade s kartice „Domestic”)',
		'omiljeno'   => 'Omiljeno (oznaka s kartice „Domestic”)',
		'istaknuto'  => 'Izdvajamo (zvjezdica u WooCommerceu)',
		'ograniceno' => 'Ograničeno izdanje (oznaka proizvoda „ograniceno-izdanje”)',
		'malo'       => 'Malo na zalihi („Još samo N”, kad se prati zaliha)',
		'narudzba'   => 'Po narudžbi (dopuštena narudžba bez zalihe)',
		'rasprodano' => 'Rasprodano',
	);

	/** Redoslijed važnosti: na kartici se prikazuju najviše 3 prve. */
	const VAZNOST = array( 'rasprodano', 'akcija', 'malo', 'novo', 'ograniceno', 'nagradeno', 'omiljeno', 'istaknuto', 'narudzba' );

	/** Ikone oznaka (24×24, linija). */
	const IKONE = array(
		'akcija'     => '<path d="M3.5 12.2V4.5a1 1 0 0 1 1-1h7.7l8.3 8.3-9 9-8-8.6Z"/><circle cx="8" cy="8" r="1.4"/>',
		'novo'       => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6.3 6.3l2.5 2.5M15.2 15.2l2.5 2.5M6.3 17.7l2.5-2.5M15.2 8.8l2.5-2.5"/>',
		'nagradeno'  => '<circle cx="12" cy="9" r="5.5"/><path d="m8.5 13.5-1.5 7 5-2.5 5 2.5-1.5-7"/>',
		'omiljeno'   => '<path d="M12 20s-7.5-4.6-7.5-10A4.3 4.3 0 0 1 12 7.4 4.3 4.3 0 0 1 19.5 10C19.5 15.4 12 20 12 20Z"/>',
		'istaknuto'  => '<path d="m12 3.5 2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.8-5.2 2.8 1-5.8-4.3-4.1 5.9-.9L12 3.5Z"/>',
		'ograniceno' => '<path d="M7 3.5h10M7 20.5h10M8 3.5c0 4.5 8 4.5 8 8.5s-8 4-8 8.5M16 3.5c0 4.5-8 4.5-8 8.5"/>',
		'malo'       => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5v5l3 2"/>',
		'narudzba'   => '<path d="M4 7.5h11v9H4zM15 10.5h3.5l2 2.5v3.5H15"/><circle cx="7.5" cy="17.5" r="1.5"/><circle cx="17" cy="17.5" r="1.5"/>',
		'rasprodano' => '<circle cx="12" cy="12" r="8.5"/><path d="M6 6l12 12"/>',
	);

	public static function zadano(): array {
		return array(
			'ukljuceno'  => 'no',
			'redoslijed' => array( 'marka', 'naziv', 'podaci', 'cijena', 'jedinicna', 'gumb' ),
			'oznake'     => array( 'akcija', 'novo', 'nagradeno', 'omiljeno', 'ograniceno', 'malo', 'narudzba', 'rasprodano' ),
			'malo_do'    => 5,
			'novo_dana'  => 30,
			'omjer'      => '1:1',
			'ispuna'     => 'cijela', // cijela (contain) | ispuni (cover)
			'gumb_tekst' => 'U košaricu',
			'brzi'       => 'yes',
			'poravnanje' => 'lijevo',
			'naglasak'   => '#702833',
			'pozadina'   => '#f2ebe0',
			'slika_poz'  => '#ebe2d4',
			'tekst'      => '#29231f',
			'zaobljenje' => 8,
			'okvir'      => 'yes',
			'sjena'      => 'no',
			'stranica'   => '',
			'fontovi'    => 'cijela', // cijela | shop | ne
			'css'        => '',
			'raspored'   => 'gore', // gore = filteri iznad artikala, bez bočne trake | tema = kako je u Flatsomeu
			'stupci'     => 4,
			'nedavno'    => 'yes',
			'trazilica'  => 'yes', // prijedlozi pretrage i novo polje u zaglavlju
			'zaglavlje'  => 'yes', // novi izgled zaglavlja: gornja traka, kategorije, pretraga, košarica, izbornik na mobitelu
			'traka_kratka' => 'Besplatna dostava iznad 60 € · Zagrebačka županija',
			'donja_traka'  => 'yes', // mobitel: stalna traka na dnu (Početna, Ponuda, Pokloni, Traži, Košarica)
		);
	}

	public static function postavke(): array {
		$s = get_option( self::OPT, array() );
		$s = array_merge( self::zadano(), is_array( $s ) ? $s : array() );
		$s['redoslijed'] = array_values( array_intersect( (array) $s['redoslijed'], array_keys( self::ELEMENTI ) ) );
		$s['oznake']     = array_values( array_intersect( (array) $s['oznake'], array_keys( self::OZNAKE ) ) );
		return $s;
	}

	public static function init(): void {
		add_filter( 'wc_get_template_part', array( __CLASS__, 'predlozak' ), 99, 3 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'css' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'fontovi' ), 25 );
		add_action( 'wp_head', array( __CLASS__, 'preload' ), 2 );
		Domestic_Kartica_Trgovina::init();
		Domestic_Kartica_Trazilica::init();
		Domestic_Kartica_Zaglavlje::init();
	}

	/* ---------- fontovi: Marcellus (naslovi, cijene) + Jost (tekst), lokalno, s hrvatskim slovima ---------- */

	const LATIN     = 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD';
	const LATIN_EXT = 'U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF';
	const FONTOVI   = array(
		array( 'DS Serif', '400 700', 'marcellus', 400 ),
		array( 'DS Sans', '300 400', 'jost', 400 ),
		array( 'DS Sans', '500 700', 'jost', 500 ),
	);

	public static function font_face(): string {
		$css = '';
		foreach ( self::FONTOVI as $f ) {
			foreach ( array( 'latin-ext' => self::LATIN_EXT, 'latin' => self::LATIN ) as $skup => $raspon ) {
				// puna adresa – predmemorija (SpeedyCache i sl.) premješta CSS pa relativni put ne bi vrijedio
				$css .= "@font-face{font-family:'{$f[0]}';font-style:normal;font-weight:{$f[1]};font-display:swap;src:url(" . esc_url( DSK_URL . "assets/fonts/{$f[2]}-{$skup}-{$f[3]}-normal.woff2" ) . ") format('woff2');unicode-range:{$raspon}}";
			}
		}
		return $css;
	}

	public static function preload(): void {
		if ( 'ne' === self::postavke()['fontovi'] ) {
			return;
		}
		foreach ( array( 'marcellus-latin-400', 'jost-latin-400' ) as $f ) {
			echo '<link rel="preload" href="' . esc_url( DSK_URL . "assets/fonts/{$f}-normal.woff2" ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
		}
	}

	public static function fontovi(): void {
		$nacin = self::postavke()['fontovi'];
		if ( 'ne' === $nacin ) {
			return;
		}
		wp_register_style( 'domestic-fontovi', false, array(), DSK_VER );
		wp_enqueue_style( 'domestic-fontovi' );
		$css = self::font_face() . ':root{--ds-serif:"DS Serif",Georgia,"Times New Roman",serif;--ds-sans:"DS Sans",system-ui,-apple-system,"Segoe UI",Arial,sans-serif}';
		if ( 'cijela' === $nacin ) {
			// cijela stranica (Flatsome): tekst Jost, naslovi Marcellus
			$css .= 'body,p,li,td,th,input,select,textarea,button,.button,.nav>li>a,.nav-dropdown li a,.breadcrumbs,.woocommerce-breadcrumb,.price,.product-title,.widget,.footer-widgets,.absolute-footer{font-family:var(--ds-sans)!important}'
				. 'h1,h2,h3,h4,.heading-font,.section-title,.page-title,.entry-title,.product_title,.widget-title,.box-text .name,.product-title a{font-family:var(--ds-serif)!important;font-weight:400;letter-spacing:.02em}';
		}
		wp_add_inline_style( 'domestic-fontovi', $css );
	}

	public static function ukljuceno(): bool {
		return 'yes' === self::postavke()['ukljuceno'] || ( isset( $GLOBALS['dsk_pregled'] ) && $GLOBALS['dsk_pregled'] );
	}

	public static function predlozak( $template, $slug, $name ) {
		if ( 'content' === $slug && 'product' === $name && self::ukljuceno() ) {
			return DSK_DIR . '/templates/content-product.php';
		}
		return $template;
	}

	public static function css(): void {
		$poz = sanitize_hex_color( (string) self::postavke()['stranica'] );
		if ( $poz ) {
			// pozadina cijele stranice (Flatsome: wrapper, sadržaj, glavni dio zaglavlja)
			wp_register_style( 'domestic-pozadina', false, array(), DSK_VER );
			wp_enqueue_style( 'domestic-pozadina' );
			wp_add_inline_style( 'domestic-pozadina', "body,#wrapper,#main,.page-wrapper,.shop-page-title,.header-main,.header-wrapper .header-bg-color{background-color:{$poz}!important}" );
		}
		if ( 'yes' !== self::postavke()['ukljuceno'] ) {
			return;
		}
		wp_enqueue_style( 'domestic-kartica', DSK_URL . 'assets/kartica.css', array(), DSK_VER . '.' . filemtime( DSK_DIR . '/assets/kartica.css' ) );
		wp_add_inline_style( 'domestic-kartica', self::css_varijable() );
	}

	public static function css_varijable(): string {
		$s   = self::postavke();
		$hex = static function ( $v, $d ) {
			return sanitize_hex_color( $v ) ?: $d;
		};
		$omjer = array( '1:1' => '1 / 1', '4:5' => '4 / 5', '3:4' => '3 / 4', '2:3' => '2 / 3' );
		$css   = '.dsk-kartica{'
			. '--dsk-naglasak:' . $hex( $s['naglasak'], '#7a1f2b' ) . ';'
			. '--dsk-pozadina:' . $hex( $s['pozadina'], '#ffffff' ) . ';'
			. '--dsk-slika-poz:' . $hex( $s['slika_poz'], '#f6f3ee' ) . ';'
			. '--dsk-tekst:' . $hex( $s['tekst'], '#2b2b2b' ) . ';'
			. '--dsk-r:' . max( 0, min( 30, (int) $s['zaobljenje'] ) ) . 'px;'
			. '--dsk-ispuna:' . ( 'ispuni' === $s['ispuna'] ? 'cover' : 'contain' ) . ';'
			. '--dsk-omjer:' . ( $omjer[ $s['omjer'] ] ?? 'auto' ) . ';'
			. '--dsk-poravnanje:' . ( 'sredina' === $s['poravnanje'] ? 'center' : 'left' ) . ';}';
		return $css . "\n" . wp_strip_all_tags( (string) $s['css'] );
	}

	/* ---------- podaci za karticu ---------- */

	private static function normaliziraj( string $s ): string {
		return strtolower( remove_accents( trim( $s ) ) );
	}

	/** Vrijednost atributa po nazivu (globalni pa_* ili vlastiti), npr. array( 'marka', 'proizvodac' ). */
	public static function atribut( WC_Product $p, array $imena ): string {
		$izvor = $p->is_type( 'variation' ) ? wc_get_product( $p->get_parent_id() ) : $p;
		if ( ! $izvor ) {
			return '';
		}
		foreach ( $izvor->get_attributes() as $kljuc => $a ) {
			$label = self::normaliziraj( wc_attribute_label( $a instanceof WC_Product_Attribute ? $a->get_name() : (string) $kljuc, $izvor ) );
			foreach ( $imena as $ime ) {
				if ( $label === $ime ) {
					return trim( (string) $izvor->get_attribute( $kljuc ) );
				}
			}
		}
		return '';
	}

	public static function marka( WC_Product $p ): string {
		$m = self::atribut( $p, array( 'marka', 'proizvodac', 'brand' ) );
		if ( '' === $m && taxonomy_exists( 'product_brand' ) ) {
			$t = get_the_terms( $p->get_id(), 'product_brand' );
			$m = is_array( $t ) && $t ? $t[0]->name : '';
		}
		if ( '' === $m && preg_match( '/^((?:[\p{Lu}][\p{Lu}\d&’\'.-]+\s+)+)/u', $p->get_name() . ' ', $r ) ) {
			$m = trim( $r[1] ); // „AVELLANA Premium 0,70 l” → AVELLANA
		}
		return $m;
	}

	/** Zapremnina u litrama iz „0,7 l”, „700 ml”, „0.75L”. */
	public static function litre( string $z ): float {
		// pakiranje „4 × 0,05 l” = ukupno 0,20 l (cijena po litri inače ispadne 4 puta veća)
		if ( preg_match( '/(\d+)\s*[xX×]\s*(\d+(?:[.,]\d+)?)\s*(ml|cl|dl|l)\b/iu', $z, $k ) ) {
			$f = array( 'ml' => 0.001, 'cl' => 0.01, 'dl' => 0.1, 'l' => 1 );
			return (int) $k[1] * (float) str_replace( ',', '.', $k[2] ) * $f[ strtolower( $k[3] ) ];
		}
		if ( ! preg_match( '/(\d+(?:[.,]\d+)?)\s*(ml|cl|dl|l)\b/i', $z, $m ) ) {
			return 0.0;
		}
		$v = (float) str_replace( ',', '.', $m[1] );
		$f = array( 'ml' => 0.001, 'cl' => 0.01, 'dl' => 0.1, 'l' => 1 );
		return $v * $f[ strtolower( $m[2] ) ];
	}

	public static function oznake( WC_Product $p ): array {
		$s   = self::postavke();
		$out = array();
		foreach ( $s['oznake'] as $o ) {
			switch ( $o ) {
				case 'akcija':
					$posto = self::posto( $p );
					if ( $posto > 0 ) {
						$out['akcija'] = '−' . $posto . ' %';
					}
					break;
				case 'novo':
					$d = $p->get_date_created();
					if ( $d && (int) $s['novo_dana'] > 0 && time() - $d->getTimestamp() < (int) $s['novo_dana'] * DAY_IN_SECONDS ) {
						$out['novo'] = 'Novo';
					}
					break;
				case 'nagradeno':
					$n = get_post_meta( $p->get_id(), '_dsp_nagrade', true );
					if ( is_array( $n ) && $n ) {
						$out['nagradeno'] = 'Nagrađeno';
					}
					break;
				case 'omiljeno':
					if ( 'yes' === get_post_meta( $p->get_id(), '_dsp_omiljeno', true ) ) {
						$out['omiljeno'] = 'Omiljeno';
					}
					break;
				case 'istaknuto':
					if ( $p->is_featured() ) {
						$out['istaknuto'] = 'Izdvajamo';
					}
					break;
				case 'rasprodano':
					if ( ! $p->is_in_stock() ) {
						$out['rasprodano'] = 'Rasprodano';
					}
					break;
				case 'ograniceno':
					if ( has_term( 'ograniceno-izdanje', 'product_tag', $p->get_id() ) ) {
						$out['ograniceno'] = 'Ograničeno izdanje';
					}
					break;
				case 'malo':
					$n = $p->managing_stock() ? (int) $p->get_stock_quantity() : 0;
					if ( $p->is_in_stock() && $n > 0 && $n <= max( 1, (int) $s['malo_do'] ) ) {
						$out['malo'] = 'Još samo ' . $n;
					}
					break;
				case 'narudzba':
					if ( $p->is_on_backorder() ) {
						$out['narudzba'] = 'Po narudžbi';
					}
					break;
			}
		}
		$out = (array) apply_filters( 'domestic_kartica_oznake', $out, $p );
		uksort(
			$out,
			static function ( $a, $b ) {
				$i = array_search( $a, self::VAZNOST, true );
				$j = array_search( $b, self::VAZNOST, true );
				return ( false === $i ? 99 : $i ) <=> ( false === $j ? 99 : $j );
			}
		);
		return $out;
	}

	/** Jednobrazne oznake (kartica, stranica proizvoda, brzi pregled). */
	public static function oznake_html( WC_Product $p, int $max = 3, string $klasa = 'dsk-oznake' ): string {
		$o = array_slice( self::oznake( $p ), 0, $max, true );
		if ( ! $o ) {
			return '';
		}
		$h = '<div class="' . esc_attr( $klasa ) . '">';
		foreach ( $o as $k => $t ) {
			$ik = isset( self::IKONE[ $k ] ) ? '<svg viewBox="0 0 24 24" aria-hidden="true">' . self::IKONE[ $k ] . '</svg>' : '';
			$h .= '<span class="dsk-oznaka dsk-oznaka-' . esc_attr( $k ) . '">' . $ik . esc_html( $t ) . '</span>';
		}
		return $h . '</div>';
	}

	private static function posto( WC_Product $p ): int {
		if ( ! $p->is_on_sale() ) {
			return 0;
		}
		$parovi = array();
		if ( $p->is_type( 'variable' ) ) {
			foreach ( $p->get_available_variations( 'objects' ) as $v ) {
				$parovi[] = array( (float) $v->get_regular_price(), (float) $v->get_sale_price() );
			}
		} else {
			$parovi[] = array( (float) $p->get_regular_price(), (float) $p->get_sale_price() );
		}
		$max = 0;
		foreach ( $parovi as list( $r, $a ) ) {
			if ( $r > 0 && $a > 0 && $a < $r ) {
				$max = max( $max, (int) round( ( 1 - $a / $r ) * 100 ) );
			}
		}
		return $max;
	}

	public static function jedinicna( WC_Product $p, string $zapremnina ): string {
		$l = self::litre( $zapremnina );
		$c = (float) wc_get_price_to_display( $p );
		if ( $c <= 0 || $p->is_type( 'variable' ) ) {
			return '';
		}
		$n = self::komada( $p );
		if ( $n > 1 ) { // cijena je za cijelo transportno pakiranje (sokovi) – kupac mora to vidjeti uz cijenu
			return 'Pakiranje ' . $n . ' boca · ' . wc_price( $c / $n ) . ' / boca';
		}
		return $l > 0 ? wc_price( $c / $l ) . ' / l' : '';
	}

	/** Isto pravilo kao domestic-proizvod: meta `_dsp_komada` ili „cijena transportnog pakiranja od N komada” u opisu. */
	public static function komada( WC_Product $p ): int {
		$n = (int) $p->get_meta( '_dsp_komada' );
		if ( $n > 1 ) {
			return $n;
		}
		$t = wp_strip_all_tags( $p->get_short_description( 'edit' ) . ' ' . $p->get_description( 'edit' ) );
		return preg_match( '/cijena\s+transportnog\s+pakiranja\s+od\s+(\d+)\s+kom/iu', $t, $m ) && (int) $m[1] > 1 ? (int) $m[1] : 1;
	}
}
