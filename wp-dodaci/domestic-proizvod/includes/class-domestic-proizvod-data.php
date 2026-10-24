<?php
/**
 * Podaci proizvoda: postavke, polja kartice „Domestic”, atributi, cijena po litri, povijest cijene.
 */

defined( 'ABSPATH' ) || exit;

final class Domestic_Proizvod_Data {

	const OPT = 'domestic_proizvod';

	/** Ključevi atributa → prepoznati nazivi (mala slova, bez kvačica). */
	const ATTR = array(
		'marka'       => array( 'marka', 'proizvodac', 'brand' ),
		'vrsta'       => array( 'vrsta', 'vrsta pica', 'tip' ),
		'zapremnina'  => array( 'zapremnina', 'zapremnina boce', 'volumen', 'kolicina' ),
		'alkohol'     => array( 'alkohol', 'udio alkohola', 'alkoholna jakost' ),
		'podrijetlo'  => array( 'podrijetlo', 'geografsko podrijetlo', 'porijeklo', 'regija' ),
		'temperatura' => array( 'temperatura', 'temperatura serviranja', 'temperatura posluzivanja' ),
		'gastro'      => array( 'gastro preporuka', 'uz sto', 'uz sto se posluzuje', 'preporuka' ),
		'okus'        => array( 'okus' ),
		'pakiranje'   => array( 'pakiranje' ),
		'ambalaza'    => array( 'ambalaza' ),
	);

	/** Ikone za posluživanje (naziv za izbornik). */
	const IKONE = array(
		'casa'   => 'Čaša',
		'kava'   => 'Kava',
		'kolac'  => 'Slastica',
		'tanjur' => 'Jelo',
		'riba'   => 'Riba',
		'sir'    => 'Pršut i sir',
		'koktel' => 'Koktel',
	);

	public static function init(): void {
		// povijest cijene (za najnižu cijenu u 30 dana kod akcija)
		add_action( 'woocommerce_after_product_object_save', array( __CLASS__, 'remember_price' ) );
	}

	/* ---------- postavke ---------- */

	public static function settings(): array {
		$d = array(
			'nacin'        => 'oznaceni', // oznaceni | svi
			'rok'          => '1–2 radna dana',
			'besplatno_od' => '60',
			'podrucje'     => 'Zagrebačke županije',
			'novo_dana'    => '60',
			'slike'        => '', // npr. „U ambijentu|Studijska|Kako poslužiti” – Igor: bez oznaka ispod slika
			'dostava_url'  => '',
		);
		$o = get_option( self::OPT, array() );
		return array_merge( $d, is_array( $o ) ? $o : array() );
	}

	/** Prikazuje li se proizvod u novom izgledu. */
	public static function is_on( $product ): bool {
		$product = wc_get_product( $product );
		if ( ! $product ) {
			return false;
		}
		if ( 'svi' === self::settings()['nacin'] ) {
			return 'no' !== $product->get_meta( '_dsp_on' );
		}
		return 'yes' === $product->get_meta( '_dsp_on' );
	}

	/* ---------- kartica „Domestic” ---------- */

	public static function meta( WC_Product $p ): array {
		$arr = function ( $k ) use ( $p ) {
			$v = $p->get_meta( $k );
			return is_array( $v ) ? $v : array();
		};
		return array(
			'eyebrow'         => (string) $p->get_meta( '_dsp_eyebrow' ),
			'naslov'          => (string) $p->get_meta( '_dsp_naslov' ),
			'razlozi_naslov'  => (string) $p->get_meta( '_dsp_razlozi_naslov' ),
			'razlozi'         => array_values( array_filter( array_map( 'trim', $arr( '_dsp_razlozi' ) ) ) ),
			'zakljucak'       => (string) $p->get_meta( '_dsp_zakljucak' ),
			'posluzivanje'    => array_values( array_filter( $arr( '_dsp_posluzivanje' ), function ( $r ) { return ! empty( $r['naziv'] ); } ) ),
			'nagrade'         => array_values( array_filter( $arr( '_dsp_nagrade' ), function ( $r ) { return ! empty( $r['naslov'] ); } ) ),
			'degustacija'     => self::degustacija( $arr( '_dsp_degustacija' ) ),
			'omiljeno'        => 'yes' === $p->get_meta( '_dsp_omiljeno' ),
			'sidrena'         => (string) $p->get_meta( '_dsp_sidrena' ),
		);
	}

	/**
	 * Degustacija (meta `_dsp_degustacija`): { nadnaslov, naslov, uvod, koraci:[{naslov,tekst}],
	 * verzije:[{naziv, slika, naslov, stavke:[{naziv, stil, opis, boja}]}] } – samo verzije sa stavkama.
	 */
	private static function degustacija( array $d ): array {
		$d['verzije'] = array_values( array_filter( (array) ( $d['verzije'] ?? array() ), static function ( $v ) { return is_array( $v ) && ! empty( $v['stavke'] ); } ) );
		return $d['verzije'] ? $d : array();
	}

	/* ---------- atributi ---------- */

	public static function norm( string $s ): string {
		$s = mb_strtolower( trim( wp_strip_all_tags( $s ) ) );
		$s = strtr( $s, array( 'č' => 'c', 'ć' => 'c', 'š' => 's', 'ž' => 'z', 'đ' => 'd' ) );
		return trim( preg_replace( '/[^a-z0-9 ]+/', ' ', $s ) );
	}

	/** Svi vidljivi atributi: [ [naziv, vrijednost, ključ|null], … ]. */
	public static function attributes( WC_Product $p ): array {
		$out = array();
		$src = $p->is_type( 'variation' ) ? wc_get_product( $p->get_parent_id() ) : $p;
		foreach ( $src->get_attributes() as $a ) {
			if ( ! $a instanceof WC_Product_Attribute || ! $a->get_visible() ) {
				continue;
			}
			$label = wc_attribute_label( $a->get_name(), $src );
			if ( $a->is_taxonomy() ) {
				$vals = wc_get_product_terms( $src->get_id(), $a->get_name(), array( 'fields' => 'names' ) );
			} else {
				$vals = $a->get_options();
			}
			$val = implode( ', ', array_filter( array_map( 'trim', (array) $vals ) ) );
			if ( '' === $val ) {
				continue;
			}
			$out[] = array( $label, $val, self::key_for( $label ) );
		}
		return $out;
	}

	public static function key_for( string $label ): ?string {
		$n = self::norm( $label );
		foreach ( self::ATTR as $k => $names ) {
			if ( in_array( $n, $names, true ) ) {
				return $k;
			}
		}
		return null;
	}

	/** Vrijednost atributa po ključu (marka, zapremnina…); bez atributa – iz opisa („Vrsta: …”). */
	public static function attr( WC_Product $p, string $key ): string {
		foreach ( self::attributes( $p ) as $a ) {
			if ( $a[2] === $key ) {
				return $a[1];
			}
		}
		foreach ( self::opis( $p )['podaci'] as $r ) {
			if ( self::key_for( $r[0] ) === $key ) {
				return $r[1];
			}
		}
		return '';
	}

	/** Oznake u opisu koje su tekst, a ne podatak. */
	const OPIS_TEKST = array( 'opis', 'dodatni info', 'dodatne informacije', 'o proizvodu' );

	private static $opis = array();

	/**
	 * Kratki + dugi opis rastavljeni za prikaz (podaci u bazi se ne mijenjaju):
	 * 'podaci' = [ [oznaka, vrijednost], … ] iz redaka „Oznaka: vrijednost”, 'tekst' = HTML priče bez tih redaka i bez slika.
	 */
	public static function opis( WC_Product $p ): array {
		$id = $p->get_id();
		if ( isset( self::$opis[ $id ] ) ) {
			return self::$opis[ $id ];
		}
		$podaci = array();
		$tekst  = '';
		// sirovo iz baze (klasični uređivač sprema retke kao prijelome) + wpautop kao kod prikaza u WooCommerceu
		$html   = wpautop( (string) $p->get_short_description( 'edit' ) ) . "\n" . wpautop( (string) $p->get_description( 'edit' ) );
		// bez slika, spanova i atributa – opisi iz uređivača često imaju nezatvorene <span> oznake
		$html = preg_replace( '#<img[^>]*>|</?(span|font|o:p)[^>]*>#i', '', $html );
		if ( self::komada( $p ) > 1 ) { // stari „(Cijena jednog komada je 1,80 EUR s PDV-om.)” – cijenu boce računamo iz stvarne cijene uz cijenu
			$html = preg_replace( '/\(?\s*Cijena\s+jednog\s+komada\s+je[^)<\n]*\)?\.?/iu', '', $html );
		}
		$html = preg_replace( '#<(p|div|li|ul|ol|b|strong|em|i|h[1-6])\s[^>]*>#i', '<$1>', $html );
		$dijelovi = preg_split( '#(</?(?:p|div|ul|ol|li|h[1-6]|table|tr|td|th|tbody)>|<br\s*/?>)#i', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
		$dozvoljeno = array( 'strong' => array(), 'b' => array(), 'em' => array(), 'i' => array(), 'a' => array( 'href' => true ) );
		$lista      = null; // stavke otvorene liste
		$li         = null; // tekst stavke koja se slaže
		foreach ( $dijelovi as $d ) {
			$tag = strtolower( trim( $d ) );
			if ( in_array( $tag, array( '<ul>', '<ol>' ), true ) ) {
				$lista = array();
				continue;
			}
			if ( in_array( $tag, array( '</ul>', '</ol>' ), true ) ) {
				if ( null !== $li && '' !== trim( wp_strip_all_tags( $li ) ) ) {
					$lista[] = $li;
				}
				$tekst .= $lista ? '<ul><li>' . implode( '</li><li>', array_map( function ( $x ) use ( $dozvoljeno ) { return force_balance_tags( trim( wp_kses( $x, $dozvoljeno ) ) ); }, $lista ) ) . '</li></ul>' : '';
				$lista  = null;
				$li     = null;
				continue;
			}
			if ( '<li>' === $tag ) {
				$li = '';
				continue;
			}
			if ( '</li>' === $tag ) {
				if ( null !== $li && null !== $lista && '' !== trim( wp_strip_all_tags( $li ) ) ) {
					$lista[] = $li;
				}
				$li = null;
				continue;
			}
			if ( preg_match( '#^</?[a-z0-9]+>$|^<br\s*/?>$#i', $tag ) ) {
				continue; // granica odlomka ili retka
			}
			if ( null !== $li ) {
				$li .= $d;
				continue;
			}
			$red = force_balance_tags( trim( wp_kses( $d, $dozvoljeno ) ) );
			$txt = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $red ), ENT_QUOTES, 'UTF-8' ) ), " \t\xC2\xA0" );
			if ( '' === $txt ) {
				continue;
			}
			if ( preg_match( '/^([^.:!?]{2,40}):\s*(.*)$/u', $txt, $m ) ) {
				$oznaka = trim( $m[1] );
				$vr     = trim( $m[2] );
				if ( in_array( self::norm( $oznaka ), self::OPIS_TEKST, true ) ) {
					$tekst .= '' !== $vr ? '<p>' . esc_html( $vr ) . '</p>' : '';
					continue;
				}
				if ( '' !== $vr ) {
					$podaci[] = array( $oznaka, $vr );
					continue;
				}
			}
			if ( preg_match( '/[?:]$/u', $txt ) && mb_strlen( $txt ) < 90 && preg_match( '#^<(strong|b)>.*</\1>$#s', $red ) ) {
				$tekst .= '<h3>' . esc_html( $txt ) . '</h3>'; // podebljani kratki red s upitnikom = podnaslov
			} else {
				$tekst .= '<p>' . $red . '</p>';
			}
		}
		if ( Domestic_Proizvod_Tekst::ukljuceno() ) {
			foreach ( $podaci as $k => $r ) {
				$podaci[ $k ] = array( Domestic_Proizvod_Tekst::oznaka( $r[0] ), Domestic_Proizvod_Tekst::vrijednost( $r[1] ) );
			}
			$tekst = Domestic_Proizvod_Tekst::html( $tekst );
		}
		return self::$opis[ $id ] = array( 'podaci' => $podaci, 'tekst' => $tekst );
	}


	/** Pakiranje „4 × 0,05 l” / „4x0,05L” → [4, 0.05]; inače null. */
	public static function pack( string $s ): ?array {
		if ( preg_match( '/(\d+)\s*[xX×]\s*(\d+(?:[.,]\d+)?)\s*(ml|cl|dl|l)\b/iu', $s, $m ) ) {
			$f = array( 'ml' => 0.001, 'cl' => 0.01, 'dl' => 0.1, 'l' => 1 );
			$v = (float) str_replace( ',', '.', $m[2] ) * $f[ strtolower( $m[3] ) ];
			return (int) $m[1] > 0 && $v > 0 ? array( (int) $m[1], $v ) : null;
		}
		return null;
	}

	/** Zapremnina u litrama iz teksta („0,7 l”, „0,70L”, „700 ml”; pakiranje „4 × 0,05 l” = ukupno 0,20 l). */
	public static function litres( string $s ): float {
		$pk = self::pack( $s );
		if ( $pk ) {
			return $pk[0] * $pk[1];
		}
		if ( ! preg_match( '/(\d+(?:[.,]\d+)?)\s*(ml|cl|l)?/i', $s, $m ) ) {
			return 0.0;
		}
		$n = (float) str_replace( ',', '.', $m[1] );
		$u = strtolower( $m[2] ?? '' );
		if ( 'ml' === $u || ( '' === $u && $n > 10 ) ) {
			$n /= 1000;
		} elseif ( 'cl' === $u ) {
			$n /= 100;
		}
		return $n > 0 && $n < 30 ? $n : 0.0;
	}

	/** „0,70 l” za prikaz. */
	public static function litres_label( float $l ): string {
		return ( abs( $l - round( $l ) ) < 0.001 ? (string) (int) round( $l ) : number_format( $l, 2, ',', '' ) ) . ' l'; // 1 l, 0,70 l
	}

	/** Zapremnina iz naziva proizvoda („… 0,70L”, „… 1 l”, „… 700 ml”) – zadnja pojava. */
	public static function litres_from_name( string $name ): float {
		$pk = self::pack( $name );
		if ( $pk ) {
			return $pk[0] * $pk[1];
		}
		if ( ! preg_match_all( '/(\d+(?:[.,]\d+)?)\s*(ml|cl|l)\b/iu', $name, $m, PREG_SET_ORDER ) ) {
			return 0.0;
		}
		$z = end( $m );
		return self::litres( $z[1] . ' ' . $z[2] );
	}

	/** Oznaka zapremnine proizvoda: „4 × 0,05 l” za pakiranja, inače „0,70 l”. */
	public static function volume_label( WC_Product $p ): string {
		$pk = self::pack( (string) self::attr( $p, 'zapremnina' ) ) ?? self::pack( $p->get_name() );
		if ( $pk ) {
			return $pk[0] . ' × ' . rtrim( self::litres_label( $pk[1] ), ' l' ) . ' l';
		}
		$l = self::litres_of( $p );
		return $l ? self::litres_label( $l ) : '';
	}

	/**
	 * Broj komada kad se proizvod prodaje samo u transportnom pakiranju (cijena je za cijelo pakiranje):
	 * meta `_dsp_komada` ili tekst opisa „cijena transportnog pakiranja od 20 komada” (sokovi). Inače 1.
	 */
	public static function komada( WC_Product $p ): int {
		$n = (int) $p->get_meta( '_dsp_komada' );
		if ( $n > 1 ) {
			return $n;
		}
		$t = wp_strip_all_tags( $p->get_short_description( 'edit' ) . ' ' . $p->get_description( 'edit' ) );
		return preg_match( '/cijena\s+transportnog\s+pakiranja\s+od\s+(\d+)\s+kom/iu', $t, $m ) && (int) $m[1] > 1 ? (int) $m[1] : 1;
	}

	/** Zapremnina proizvoda u litrama: atribut, inače naziv. */
	public static function litres_of( WC_Product $p ): float {
		$v = self::attr( $p, 'zapremnina' );
		$l = $v ? self::litres( $v ) : 0.0;
		return $l > 0 ? $l : self::litres_from_name( $p->get_name() );
	}

	/** Naziv bez zapremnine na kraju („AVELLANA Liker od lješnjaka 0,70L” → „AVELLANA Liker od lješnjaka”). */
	public static function name_without_volume( string $name ): string {
		return trim( preg_replace( '/[\s,–-]*(?:\d+\s*[xX×]\s*)?\d+(?:[.,]\d+)?\s*(ml|cl|l)\b\.?\*?\s*$/iu', '', $name ) );
	}

	/** Naslov rastavljen na marku i ostatak („AVELLANA” + „Liker od lješnjaka”). Bez atributa Marka: velika slova na početku naziva. */
	public static function title_parts( WC_Product $p ): array {
		$name  = self::name_without_volume( $p->get_name() );
		$marka = self::attr( $p, 'marka' );
		if ( $marka && 0 === stripos( $name, $marka ) ) {
			$rest = trim( substr( $name, strlen( $marka ) ), " \t-–·" );
			return array( $marka, $rest );
		}
		if ( preg_match( '/^((?:[\p{Lu}\d][\p{Lu}\d\'’&.\-]*\s+)+)(\p{Lu}\p{Ll}.*)$/u', $name, $m ) ) {
			return array( trim( $m[1] ), trim( $m[2] ) );
		}
		return array( $name, '' );
	}

	/**
	 * Isti proizvod u drugim zapremninama (zasebni proizvodi istog naziva bez zapremnine, u istoj kategoriji).
	 *
	 * @return array [ [ WC_Product, litre ], … ] sortirano po zapremnini, uključuje i ovaj proizvod; prazno ako nema drugih.
	 */
	public static function siblings( WC_Product $p ): array {
		$base = self::norm( self::name_without_volume( $p->get_name() ) );
		$cats = $p->get_category_ids();
		if ( '' === $base || ! $cats || ! self::litres_of( $p ) ) {
			return array();
		}
		// i privatni istog naziva: u izboru zasivljeni kao rasprodani, bez poveznice (status se ne mijenja)
		$ids = wc_get_products( array( 'limit' => 200, 'status' => array( 'publish', 'private' ), 'return' => 'ids', 'product_category_id' => $cats, 'type' => 'simple' ) );
		$out = array();
		foreach ( array_unique( array_merge( array( $p->get_id() ), $ids ) ) as $id ) {
			$x   = (int) $id === $p->get_id() ? $p : wc_get_product( $id );
			$ova = $x && $x->get_id() === $p->get_id();
			if ( ! $x || ( ! $ova && 'private' !== $x->get_status() && ! $x->is_visible() ) ) {
				continue;
			}
			$l = self::litres_of( $x );
			if ( $l <= 0 || self::norm( self::name_without_volume( $x->get_name() ) ) !== $base ) {
				continue;
			}
			$skriven = ! $ova && 'private' === $x->get_status();
			$k       = (string) $l;
			if ( isset( $out[ $k ] ) && ( $out[ $k ][0]->get_id() === $p->get_id() || ( $skriven && ! $out[ $k ][3] ) ) ) {
				continue; // ista zapremnina: ostaje ovaj proizvod, odnosno objavljeni ispred skrivenog
			}
			// [ proizvod, litre, ima ga (na stanju i objavljen), skriven (bez poveznice) ]
			$out[ $k ] = array( $x, $l, ! $skriven && $x->is_in_stock(), $skriven );
		}
		ksort( $out, SORT_NUMERIC );
		$out = array_values( (array) apply_filters( 'domestic_proizvod_zapremnine', $out, $p ) );
		return count( $out ) > 1 ? $out : array();
	}

	/* ---------- oznake ---------- */

	public static function badges( WC_Product $p ): array {
		$m = self::meta( $p );
		$b = array();
		if ( $p->is_on_sale() ) {
			$b['akcija'] = 'Akcija';
		}
		if ( $m['nagrade'] ) {
			$b['nagradeno'] = 'Nagrađeno';
		}
		$days    = (int) self::settings()['novo_dana'];
		$created = $p->get_date_created();
		if ( $days > 0 && $created && $created->getTimestamp() > time() - $days * DAY_IN_SECONDS ) {
			$b['novo'] = 'Novo';
		}
		if ( $m['omiljeno'] ) {
			$b['omiljeno'] = 'Omiljeno';
		}
		return $b;
	}

	/* ---------- povijest cijene ---------- */

	public static function remember_price( $p ): void {
		if ( ! $p instanceof WC_Product || $p->is_type( 'variable' ) ) {
			return;
		}
		$price = (float) $p->get_price();
		if ( $price <= 0 ) {
			return;
		}
		$h = $p->get_meta( '_dsp_cijene' );
		$h = is_array( $h ) ? $h : array();
		$d = current_time( 'Y-m-d' );
		if ( end( $h ) === $price && isset( $h[ array_key_last( $h ) ] ) ) {
			return;
		}
		$h[ $d ] = $price;
		// čuvamo zadnjih 400 dana
		$min = gmdate( 'Y-m-d', time() - 400 * DAY_IN_SECONDS );
		$h   = array_filter( $h, function ( $k ) use ( $min ) { return $k >= $min; }, ARRAY_FILTER_USE_KEY );
		ksort( $h );
		update_post_meta( $p->get_id(), '_dsp_cijene', $h );
	}

	/** Najniža cijena u zadnjih 30 dana prije akcije (ili null ako nema podataka). */
	public static function lowest_30( WC_Product $p ): ?float {
		$h = $p->get_meta( '_dsp_cijene' );
		$regular = (float) $p->get_regular_price();
		if ( ! is_array( $h ) || ! $h ) {
			return $regular > 0 ? $regular : null;
		}
		ksort( $h );
		$from  = gmdate( 'Y-m-d', time() - 30 * DAY_IN_SECONDS );
		$start = $p->get_date_on_sale_from() ? $p->get_date_on_sale_from()->date( 'Y-m-d' ) : current_time( 'Y-m-d' );
		$vals  = array();
		$prev  = null;
		foreach ( $h as $d => $v ) {
			if ( $d < $from ) {
				$prev = (float) $v; // cijena koja je vrijedila na početku razdoblja
				continue;
			}
			if ( $d < $start ) {
				$vals[] = (float) $v;
			}
		}
		if ( null !== $prev ) {
			$vals[] = $prev;
		}
		if ( ! $vals && $regular > 0 ) {
			$vals[] = $regular;
		}
		return $vals ? min( $vals ) : null;
	}
}
