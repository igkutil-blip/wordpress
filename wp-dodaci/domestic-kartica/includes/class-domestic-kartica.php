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
		'istaknuto'  => 'Istaknuto (zvjezdica u WooCommerceu)',
		'rasprodano' => 'Rasprodano',
	);

	public static function zadano(): array {
		return array(
			'ukljuceno'  => 'no',
			'redoslijed' => array( 'marka', 'naziv', 'podaci', 'cijena', 'jedinicna', 'gumb' ),
			'oznake'     => array( 'akcija', 'novo', 'nagradeno', 'rasprodano' ),
			'novo_dana'  => 30,
			'omjer'      => '1:1',
			'gumb_tekst' => 'U košaricu',
			'brzi'       => 'yes',
			'poravnanje' => 'lijevo',
			'naglasak'   => '#702833',
			'pozadina'   => '#ffffff',
			'slika_poz'  => '#efe7dc',
			'tekst'      => '#29231f',
			'zaobljenje' => 8,
			'okvir'      => 'yes',
			'sjena'      => 'no',
			'stranica'   => '',
			'css'        => '',
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
		return $m;
	}

	/** Zapremnina u litrama iz „0,7 l”, „700 ml”, „0.75L”. */
	public static function litre( string $z ): float {
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
			}
		}
		return (array) apply_filters( 'domestic_kartica_oznake', $out, $p );
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
		if ( $l <= 0 || $c <= 0 || $p->is_type( 'variable' ) ) {
			return '';
		}
		return wc_price( $c / $l ) . ' / l';
	}
}
