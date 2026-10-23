<?php
/**
 * Jednobrazan prikaz tekstova u trgovini: nazivi proizvoda, kategorije, opisi i podaci.
 * Ništa se ne sprema u bazu – tekst se uređuje samo pri prikazu, pa ga u administraciji mogu upisivati kako žele.
 * Adrese (slugovi) se ne diraju.
 */

defined( 'ABSPATH' ) || exit;

final class Domestic_Proizvod_Tekst {

	const OPT = 'domestic_tekst';

	/** Ispravci riječi u opisima (točno tako napisano → ispravno). Dopunjuje se u Domestic → Tekstovi. */
	const ISPRAVCI = array(
		'vrunsko'           => 'vrhunsko',
		'Vrunsko'           => 'Vrhunsko',
		'biti će'           => 'bit će',
		'Biti će'           => 'Bit će',
		'dugačije'          => 'drugačije',
		'Dominatno'         => 'Dominantno',
		'katonski'          => 'kartonski',
		'autohtna'          => 'autohtona',
		'lepšavošću'        => 'lepršavošću',
		'namjenjena'        => 'namijenjena',
		'Južna Korea'       => 'Južna Koreja',
		'đem'               => 'džem',
		'bariq'             => 'barrique',
		'riješenje'         => 'rješenje',
		'velikm'            => 'velikim',
		'upotpunje'         => 'upotpunjuje',
		'Ganitno'           => 'Granatno',
		'Cabernet savignion' => 'Cabernet Sauvignon',
		'bouqet'            => 'bouquet',
		'osvježavajuć'      => 'osvježavajući',
		'malvazijeu'        => 'malvaziju u',
		'ružićasto'         => 'ružičasto',
		'Uzdravlje'         => 'U zdravlje',
		'Krakteristične'    => 'Karakteristične',
		'sjemnki'           => 'sjemenki',
		'vinogra- darske'   => 'vinogradarske',
		'preporuča'         => 'preporučuje',
		'Preporuča'         => 'Preporučuje',
		'Rose'              => 'Rosé',
		'Cuvee'             => 'Cuvée',
	);

	/** Ispravci u nazivima proizvoda. */
	const NAZIVI = array(
		'Malvazija Istarska' => 'Malvazija istarska',
		'Pinot Bijeli'       => 'Pinot bijeli',
		'Pinot Sivi'         => 'Pinot sivi',
		'Muškat Bijeli'      => 'Muškat bijeli',
		'Sur Lie'            => 'sur lie',
		'Sur lie'            => 'sur lie',
		'Excluzive'          => 'Exclusive',
		'BUTELJA SA DVIJE ČAŠE' => 'Butelja s dvije čaše',
		'mix rakija'         => 'Mix rakija',
		'mix likeri'         => 'Mix likeri',
		'limited edition'    => 'Limited Edition',
		'Rose'               => 'Rosé',
		'Cuvee'              => 'Cuvée',
	);

	/** Ispravci naziva kategorija. */
	const KATEGORIJE = array(
		'Domestic spirits' => 'Domestic Spirits',
		'Rose'             => 'Rosé',
	);

	/** Oznake u podacima: razne inačice → jedna. */
	const OZNAKE = array(
		'naziv vina'               => 'Naziv',
		'naziv likera'             => 'Naziv',
		'naziv rakije'             => 'Naziv',
		'naziv artikla'            => 'Naziv',
		'naziv proizvoda'          => 'Naziv',
		'temperatura posluzivanja' => 'Temperatura serviranja',
		'jela uz koja ide'         => 'Gastro preporuka',
		'ean'                      => 'EAN kod',
		'ean kod'                  => 'EAN kod',
		'napomena'                 => 'Napomena',
		'paket sadrzi'             => 'Paket sadrži',
		'alk'                      => 'Alkohol',
	);

	public static function postavke(): array {
		$s = get_option( self::OPT, array() );
		return array_merge( array( 'ukljuceno' => 'yes', 'rjecnik' => '' ), is_array( $s ) ? $s : array() );
	}

	public static function ukljuceno(): bool {
		return 'yes' === self::postavke()['ukljuceno'];
	}

	/** Vlastiti ispravci iz administracije („pogrešno => ispravno”, jedan po retku). */
	public static function vlastiti(): array {
		$out = array();
		foreach ( preg_split( '/\R/', (string) self::postavke()['rjecnik'] ) as $r ) {
			if ( false !== strpos( $r, '=>' ) ) {
				list( $a, $b ) = array_map( 'trim', explode( '=>', $r, 2 ) );
				if ( '' !== $a ) {
					$out[ $a ] = $b;
				}
			}
		}
		return $out;
	}

	public static function init(): void {
		if ( ! self::ukljuceno() ) {
			return;
		}
		add_filter( 'the_title', array( __CLASS__, 'f_naslov' ), 20, 2 );
		add_filter( 'woocommerce_product_get_name', array( __CLASS__, 'f_ime' ), 20 );
		add_filter( 'woocommerce_product_variation_get_name', array( __CLASS__, 'f_ime' ), 20 );
		add_filter( 'woocommerce_short_description', array( __CLASS__, 'html' ), 20 );
		add_filter( 'get_term', array( __CLASS__, 'f_term' ), 20, 2 );
		add_filter( 'get_terms', array( __CLASS__, 'f_terms' ), 20, 2 );
		add_filter( 'the_content', array( __CLASS__, 'f_sadrzaj' ), 20 );
	}

	/** Samo na stranici za kupce (ne u administraciji, gdje se upisuje izvorni tekst). */
	private static function front(): bool {
		return ! is_admin() || wp_doing_ajax();
	}

	public static function f_naslov( $t, $id = 0 ) {
		return self::front() && $id && 'product' === get_post_type( $id ) ? self::naziv( (string) $t ) : $t;
	}

	public static function f_ime( $t ) {
		return self::front() ? self::naziv( (string) $t ) : $t;
	}

	public static function f_sadrzaj( $c ) {
		return self::front() && is_singular( 'product' ) && in_the_loop() ? self::html( (string) $c ) : $c;
	}

	public static function f_term( $term, $tax ) {
		if ( self::front() && $term instanceof WP_Term && in_array( $tax, array( 'product_cat', 'product_tag' ), true ) ) {
			$term->name = self::kategorija( $term->name );
		}
		return $term;
	}

	public static function f_terms( $terms, $tax ) {
		if ( self::front() && is_array( $terms ) ) {
			foreach ( $terms as $t ) {
				if ( $t instanceof WP_Term && in_array( $t->taxonomy, array( 'product_cat', 'product_tag' ), true ) ) {
					$t->name = self::kategorija( $t->name );
				}
			}
		}
		return $terms;
	}

	/* ---------- pravila ---------- */

	private static function zamijeni( string $s, array $mapa ): string {
		foreach ( $mapa as $a => $b ) {
			$s = preg_replace( '/(?<![\p{L}\d])' . preg_quote( $a, '/' ) . '(?![\p{L}\d])/u', str_replace( array( '\\', '$' ), array( '\\\\', '\\$' ), $b ), $s );
		}
		return $s;
	}

	/** Navodnici "…" i “…” → „…”. */
	private static function navodnici( string $s ): string {
		$s = preg_replace( '/["“”]([^"“”]{1,80})["“”]/u', '„$1”', $s );
		return $s;
	}

	/** 0,75L · 0,75 L · 1,00L · 0.7l → 0,75 l · 1 l · 0,7 l; 4x0,05L → 4 × 0,05 l. */
	public static function zapremnine( string $s ): string {
		$s = preg_replace_callback(
			'/(?<![\p{L}\d,.])(\d+)\s*[xX×]\s*(\d+(?:[.,]\d+)?)\s*[lL](?![\p{L}])/u',
			function ( $m ) {
				return $m[1] . ' × ' . self::broj( $m[2] ) . ' l';
			},
			$s
		);
		return preg_replace_callback(
			'/(?<![\p{L}\d,.×])(\d+(?:[.,]\d+)?)\s*[lL](?![\p{L}])/u',
			function ( $m ) {
				return self::broj( $m[1] ) . ' l';
			},
			$s
		);
	}

	private static function broj( string $n ): string {
		$n = str_replace( '.', ',', $n );
		return preg_replace( '/,0+$/', '', $n ); // 1,00 → 1; 0,70 ostaje
	}

	private static function razmaci( string $s ): string {
		$s = preg_replace( '/[ \t\x{00A0}]{2,}/u', ' ', $s );
		$s = preg_replace( '/ +([,.;:!?])(?!\d)/u', '$1', $s );                        // bez razmaka prije interpunkcije
		$s = preg_replace( '/,(?=[\p{L}])/u', ', ', $s );                              // razmak iza zareza
		$s = preg_replace( '/(?<=[\p{L}\d)]) - (?=[\p{L}\d(„])/u', ' – ', $s );        // crtica između riječi → spojnica
		return trim( $s );
	}

	/** Pravopisna pravila u tekstu (ne u adresama). */
	private static function pravopis( string $s ): string {
		$s = self::zamijeni( $s, self::ISPRAVCI );
		$s = self::zamijeni( $s, self::vlastiti() );
		// „sa” samo ispred s, z, š, ž (i ps, ks, mn); inače „s”
		$s = preg_replace( '/(?<![\p{L}])([Ss])a (?![sSzZšŠžŽ]|ps|ks|mn|Ps|Ks|Mn|\d)(?=\p{L})/u', '$1 ', $s );
		// temperature: 8-10°C, 10 -12 C → 8 – 10 °C
		$s = preg_replace( '/(\d+)\s*[-–]\s*(\d+)\s*°?\s*C\b/u', '$1 – $2 °C', $s );
		$s = preg_replace( '/(\d)\s*°\s*C\b/u', '$1 °C', $s );
		// alkohol: 20 vol. %, 20%vol, 20 % → 20 % vol.
		$s = preg_replace( '/(\d+(?:[.,]\d+)?)\s*(?:vol\.?\s*%|%\s*vol\.?)/u', '$1 % vol.', $s );
		$s = preg_replace( '/(\d)%/u', '$1 %', $s );
		$s = self::zapremnine( $s );
		$s = self::navodnici( $s );
		return self::razmaci( $s );
	}

	/** Tekst s HTML oznakama: mijenja se samo tekst, ne oznake ni adrese. */
	public static function html( $h ) {
		if ( ! is_string( $h ) || '' === trim( $h ) ) {
			return $h;
		}
		$dijelovi = preg_split( '/(<[^>]+>)/', $h, -1, PREG_SPLIT_DELIM_CAPTURE );
		foreach ( $dijelovi as $i => $d ) {
			if ( '' === $d || '<' === $d[0] || '' === trim( $d ) ) {
				continue;
			}
			$lijevo        = preg_match( '/^\s/u', $d ) ? ' ' : '';
			$desno         = preg_match( '/\s$/u', $d ) ? ' ' : '';
			$dijelovi[ $i ] = $lijevo . self::tekst( $d ) . $desno;
		}
		return implode( '', $dijelovi );
	}

	/** Običan tekst; adrese (http…, www…, e-mail) ostaju netaknute. */
	public static function tekst( string $s ): string {
		$dijelovi = preg_split( '#(\S*(?:https?://|www\.|@)\S*)#iu', $s, -1, PREG_SPLIT_DELIM_CAPTURE );
		foreach ( $dijelovi as $i => $d ) {
			if ( 0 === $i % 2 && '' !== trim( $d ) ) {
				$l              = preg_match( '/^\s/u', $d ) ? ' ' : '';
				$r              = preg_match( '/\s$/u', $d ) ? ' ' : '';
				$dijelovi[ $i ] = $l . self::pravopis( $d ) . $r;
			}
		}
		return trim( implode( '', $dijelovi ) );
	}

	/** Naziv proizvoda. */
	public static function naziv( string $s ): string {
		$s = html_entity_decode( $s, ENT_QUOTES, 'UTF-8' );
		$s = preg_replace( '/\s*\*+\s*$/u', '', $s );                 // zvjezdica na kraju
		$s = preg_replace( '/(\d+[,.]\d{2})\s*$/u', '$1 l', $s );     // „… 0,75” bez jedinice
		$s = self::zamijeni( $s, self::NAZIVI );
		$s = self::zamijeni( $s, self::vlastiti() );
		$s = preg_replace( '/(„[^”]+”|"[^"]+")\s+Poklon paket/u', '$1 poklon paket', $s );
		$s = self::zapremnine( $s );
		$s = self::navodnici( $s );
		$s = preg_replace( '/\s+-\s+/u', ' – ', $s );
		return self::razmaci( $s );
	}

	public static function kategorija( string $s ): string {
		$s = self::zamijeni( html_entity_decode( $s, ENT_QUOTES, 'UTF-8' ), self::KATEGORIJE );
		return self::razmaci( $s );
	}

	/** Oznaka u tablici podataka („NAPOMENA”, „Naziv vina” → „Napomena”, „Naziv”). */
	public static function oznaka( string $s ): string {
		$s = trim( preg_replace( '/\s+/u', ' ', $s ) );
		$n = Domestic_Proizvod_Data::norm( $s );
		if ( isset( self::OZNAKE[ $n ] ) ) {
			return self::OZNAKE[ $n ];
		}
		if ( mb_strtoupper( $s ) === $s ) {
			$s = mb_strtolower( $s );
		}
		return mb_strtoupper( mb_substr( $s, 0, 1 ) ) . mb_substr( $s, 1 );
	}

	/** Vrijednost u tablici: pravopis, veliko početno slovo, bez točke na kraju kratkih vrijednosti. */
	public static function vrijednost( string $s ): string {
		$s = self::tekst( $s );
		if ( mb_strlen( $s ) < 60 && preg_match( '/[^.]\.$/u', $s ) && ! preg_match( '/\b(npr|tj|d\.o\.o|vol)\.$/iu', $s ) ) {
			$s = mb_substr( $s, 0, -1 );
		}
		if ( preg_match( '/^\p{Ll}/u', $s ) && ! preg_match( '#^(https?://|www\.)#i', $s ) ) {
			$s = mb_strtoupper( mb_substr( $s, 0, 1 ) ) . mb_substr( $s, 1 );
		}
		return $s;
	}
}
