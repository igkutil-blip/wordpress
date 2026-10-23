<?php
/**
 * Raspored trgovine i kategorija: filteri iznad artikala (padajući izbornici), bez bočne trake,
 * 4 artikla u redu, „Nedavno pregledano” ispod artikala, uređene stranice 1, 2, 3.
 * Adrese kategorija se ne mijenjaju – filteri su samo parametri (?min_price, ?max_price, ?orderby).
 */

defined( 'ABSPATH' ) || exit;

final class Domestic_Kartica_Trgovina {

	const CIJENE = array( array( 0, 10 ), array( 10, 20 ), array( 20, 30 ), array( 30, 50 ), array( 50, 0 ) );

	public static function init(): void {
		add_action( 'wp', array( __CLASS__, 'pokreni' ) );
		add_action( 'template_redirect', array( __CLASS__, 'prati' ), 25 );
	}

	private static function ukljuceno(): bool {
		$s = Domestic_Kartica::postavke();
		return 'yes' === $s['ukljuceno'] && 'gore' === $s['raspored'];
	}

	private static function trgovina(): bool {
		return function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() || ( is_search() && 'product' === get_query_var( 'post_type' ) ) );
	}

	public static function pokreni(): void {
		if ( is_admin() || ! self::ukljuceno() || ! self::trgovina() ) {
			return;
		}
		$st = max( 2, min( 5, (int) Domestic_Kartica::postavke()['stupci'] ) );
		add_filter( 'theme_mod_category_sidebar', static function () { return 'none'; } );
		add_filter( 'theme_mod_category_row_count', static function () use ( $st ) { return $st; } );
		add_filter( 'theme_mod_category_row_count_tablet', static function () { return 3; } );
		add_filter( 'theme_mod_category_shadow', static function () { return 0; } );
		add_action( 'woocommerce_before_shop_loop', array( __CLASS__, 'traka' ), 5 );
		add_action( 'woocommerce_after_shop_loop', array( __CLASS__, 'nedavno' ), 50 );
		add_action( 'woocommerce_no_products_found', array( __CLASS__, 'traka' ), 5 );
		add_filter( 'body_class', static function ( $c ) { $c[] = 'dsk-trgovina'; return $c; } );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'skripta' ), 30 );
	}

	/** „Nedavno pregledano”: WooCommerce kolačić puni samo kad je widget aktivan – ovdje uvijek. */
	public static function prati(): void {
		if ( ! is_singular( 'product' ) || ! self::ukljuceno() || is_active_widget( false, false, 'woocommerce_recently_viewed_products', true ) ) {
			return;
		}
		$id   = get_queried_object_id();
		$ids  = empty( $_COOKIE['woocommerce_recently_viewed'] ) ? array() : wp_parse_id_list( (array) explode( '|', wp_unslash( $_COOKIE['woocommerce_recently_viewed'] ) ) ); // phpcs:ignore
		$ids  = array_values( array_diff( $ids, array( $id ) ) );
		$ids[] = $id;
		$ids  = array_slice( $ids, -15 );
		wc_setcookie( 'woocommerce_recently_viewed', implode( '|', $ids ) );
	}

	public static function skripta(): void {
		wp_register_script( 'domestic-trgovina', false, array(), DSK_VER, true );
		wp_enqueue_script( 'domestic-trgovina' );
		// samo jedan izbornik otvoren; zatvaranje klikom izvan i tipkom Escape
		wp_add_inline_script(
			'domestic-trgovina',
			"(function(){var d=document.querySelectorAll('.dsk-filter');function z(o){d.forEach(function(x){if(x!==o)x.open=false;});}d.forEach(function(x){x.addEventListener('toggle',function(){if(x.open)z(x);});});document.addEventListener('click',function(e){if(!e.target.closest('.dsk-filter'))z(null);});document.addEventListener('keydown',function(e){if(e.key==='Escape'){var o=document.querySelector('.dsk-filter[open]');if(o){o.open=false;o.querySelector('summary').focus();}}});})();"
		);
	}

	/* ---------- adrese: osnovna adresa arhive bez /page/N/ + zadržani parametri ---------- */

	private static function baza(): string {
		if ( is_search() ) {
			return add_query_arg( array( 's' => get_search_query(), 'post_type' => 'product' ), home_url( '/' ) );
		}
		if ( is_product_taxonomy() ) {
			$l = get_term_link( get_queried_object() );
			return is_wp_error( $l ) ? home_url( '/' ) : $l;
		}
		return wc_get_page_permalink( 'shop' );
	}

	private static function url( array $promjena ): string {
		$args = array();
		foreach ( array( 'min_price', 'max_price', 'orderby' ) as $k ) {
			if ( isset( $_GET[ $k ] ) && '' !== $_GET[ $k ] ) { // phpcs:ignore WordPress.Security.NonceVerification
				$args[ $k ] = sanitize_text_field( wp_unslash( $_GET[ $k ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			}
		}
		foreach ( $promjena as $k => $v ) {
			if ( null === $v ) {
				unset( $args[ $k ] );
			} else {
				$args[ $k ] = $v;
			}
		}
		return add_query_arg( $args, self::baza() );
	}

	private static function get( string $k ): string {
		return isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	}

	/* ---------- traka s filterima ---------- */

	public static function traka(): void {
		static $jednom = false;
		if ( $jednom ) {
			return;
		}
		$jednom = true;
		$f      = array();

		// Kategorija: podkategorije trenutne, inače kategorije iste razine
		$kat  = is_product_category() ? get_queried_object() : null;
		$roditelj = 0;
		$djeca    = array();
		if ( $kat ) {
			$djeca = get_terms( array( 'taxonomy' => 'product_cat', 'parent' => $kat->term_id, 'hide_empty' => true ) );
			if ( ! $djeca || is_wp_error( $djeca ) ) {
				$roditelj = (int) $kat->parent;
				$djeca    = $roditelj ? get_terms( array( 'taxonomy' => 'product_cat', 'parent' => $roditelj, 'hide_empty' => true ) ) : array();
			}
		} elseif ( ! is_product_taxonomy() ) {
			$djeca = get_terms( array( 'taxonomy' => 'product_cat', 'parent' => 0, 'hide_empty' => true, 'exclude' => array( (int) get_option( 'default_product_cat' ) ) ) );
		}
		if ( $djeca && ! is_wp_error( $djeca ) ) {
			$o   = '';
			$sve = $roditelj ? get_term( $roditelj, 'product_cat' ) : $kat;
			if ( $sve instanceof WP_Term ) {
				$o .= str_replace( 'class="dsk-opcija', 'class="dsk-opcija dsk-opcija-sve', self::opcija( get_term_link( $sve ), 'Sve: ' . $sve->name, -1, $kat && $kat->term_id === $sve->term_id ) );
			}
			usort( $djeca, static function ( $a, $b ) { return strcoll( remove_accents( $a->name ), remove_accents( $b->name ) ); } );
			foreach ( $djeca as $t ) {
				$o .= self::opcija( get_term_link( $t ), $t->name, (int) $t->count, $kat && $kat->term_id === $t->term_id );
			}
			$f[] = self::izbornik( 'kategorija', 'Kategorija', $kat && $roditelj ? $kat->name : '', $o );
		}

		// Cijena: rasponi (WooCommerce ih filtrira sam preko ?min_price i ?max_price)
		$min  = self::get( 'min_price' );
		$max  = self::get( 'max_price' );
		$broj = self::broj_po_cijeni();
		$o    = self::opcija( self::url( array( 'min_price' => null, 'max_price' => null ) ), 'Sve cijene', -1, '' === $min && '' === $max );
		$odabrano = '';
		foreach ( self::CIJENE as $i => list( $od, $do ) ) {
			$t  = self::raspon( $od, $do );
			$on = (string) $od === ( '' === $min ? '0' : $min ) && (string) $do === ( '' === $max ? '0' : $max ) && ( '' !== $min || '' !== $max );
			if ( $on ) {
				$odabrano = $t;
			}
			$o .= self::opcija( self::url( array( 'min_price' => $od ? $od : null, 'max_price' => $do ? $do : null ) ), $t, $broj[ $i ] ?? -1, $on );
		}
		$f[] = self::izbornik( 'cijena', 'Cijena', $odabrano, $o );

		// Poredak
		$poredci = apply_filters(
			'woocommerce_catalog_orderby',
			array(
				'menu_order' => 'Preporučeno',
				'popularity' => 'Najprodavanije',
				'rating'     => 'Najbolje ocijenjeno',
				'date'       => 'Najnovije',
				'price'      => 'Cijena: od niže',
				'price-desc' => 'Cijena: od više',
			)
		);
		if ( 'no' === get_option( 'woocommerce_enable_review_rating' ) ) {
			unset( $poredci['rating'] );
		}
		$zadano = apply_filters( 'woocommerce_default_catalog_orderby', get_option( 'woocommerce_default_catalog_orderby', 'menu_order' ) );
		$sad    = self::get( 'orderby' ) ? self::get( 'orderby' ) : $zadano;
		$o      = '';
		foreach ( $poredci as $k => $t ) {
			$t  = self::hr_poredak( $k, $t );
			$o .= self::opcija( self::url( array( 'orderby' => $k === $zadano ? null : $k ) ), $t, -1, $k === $sad );
		}
		$poredak = self::izbornik( 'poredak', 'Poredaj', $sad !== $zadano && isset( $poredci[ $sad ] ) ? self::hr_poredak( $sad, $poredci[ $sad ] ) : '', $o, true );

		// odabrani filteri
		$aktivni = '';
		if ( $odabrano ) {
			$aktivni .= '<a class="dsk-aktivni" href="' . esc_url( self::url( array( 'min_price' => null, 'max_price' => null ) ) ) . '" aria-label="' . esc_attr( 'Ukloni filtar ' . $odabrano ) . '">' . esc_html( $odabrano ) . ' <span aria-hidden="true">×</span></a>';
		}

		$ukupno = (int) wc_get_loop_prop( 'total' );
		echo self::naslov(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="dsk-filteri" role="region" aria-label="Filteri proizvoda"><div class="dsk-filteri-lijevo">' . implode( '', $f ) . $aktivni // phpcs:ignore WordPress.Security.EscapeOutput
			. '</div><div class="dsk-filteri-desno"><span class="dsk-ukupno">' . esc_html( $ukupno . ' ' . self::proizvoda( $ukupno ) ) . '</span>' . $poredak . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/** Putanja i naslov stranice (Flatsomeova traka naslova je prazna, kategorije nemaju H1). */
	private static function naslov(): string {
		$shop = wc_get_page_permalink( 'shop' );
		$put  = array();
		if ( is_search() ) {
			$h1 = 'Rezultati pretrage: ' . get_search_query();
		} elseif ( is_product_taxonomy() ) {
			$t  = get_queried_object();
			$h1 = $t->name;
			foreach ( array_reverse( get_ancestors( $t->term_id, $t->taxonomy ) ) as $a ) {
				$at    = get_term( $a, $t->taxonomy );
				$put[] = '<a href="' . esc_url( get_term_link( $at ) ) . '">' . esc_html( $at->name ) . '</a>';
			}
		} else {
			$h1 = 'Svi proizvodi';
		}
		$c = '<a href="' . esc_url( $shop ) . '">Svi proizvodi</a>' . ( $put ? '<span>/</span>' . implode( '<span>/</span>', $put ) : '' );
		return '<header class="dsk-naslov">' . ( is_shop() ? '' : '<nav class="dsk-put" aria-label="Putanja">' . $c . '</nav>' ) . '<h1>' . esc_html( $h1 ) . '</h1></header>';
	}

	private static function hr_poredak( string $k, string $t ): string {
		$hr = array( 'menu_order' => 'Preporučeno', 'popularity' => 'Najprodavanije', 'rating' => 'Najbolje ocijenjeno', 'date' => 'Najnovije', 'price' => 'Cijena: od niže', 'price-desc' => 'Cijena: od više' );
		return $hr[ $k ] ?? $t;
	}

	private static function proizvoda( int $n ): string {
		return 1 === $n % 10 && 11 !== $n % 100 ? 'proizvod' : 'proizvoda';
	}

	private static function raspon( int $od, int $do ): string {
		if ( ! $od ) {
			return 'do ' . $do . ' €';
		}
		return $do ? $od . '–' . $do . ' €' : 'iznad ' . $od . ' €';
	}

	private static function izbornik( string $id, string $naziv, string $odabrano, string $opcije, bool $desno = false ): string {
		return '<details class="dsk-filter dsk-filter-' . esc_attr( $id ) . ( $odabrano ? ' dsk-filter-on' : '' ) . ( $desno ? ' dsk-filter-desno' : '' ) . '"><summary><span class="dsk-filter-naziv">' . esc_html( $naziv ) . '</span>'
			. ( $odabrano ? '<span class="dsk-filter-vrijednost">' . esc_html( $odabrano ) . '</span>' : '' )
			. '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary><div class="dsk-panel">' . $opcije . '</div></details>';
	}

	private static function opcija( $url, string $tekst, int $broj, bool $on ): string {
		if ( is_wp_error( $url ) ) {
			return '';
		}
		$prazno = 0 === $broj;
		$b      = $broj > 0 ? '<small>' . $broj . '</small>' : '';
		if ( $prazno && ! $on ) {
			return '<span class="dsk-opcija dsk-opcija-prazno" aria-disabled="true">' . esc_html( $tekst ) . '</span>';
		}
		return '<a class="dsk-opcija' . ( $on ? ' on' : '' ) . '" href="' . esc_url( $url ) . '"' . ( $on ? ' aria-current="true"' : '' ) . '>' . esc_html( $tekst ) . $b . '</a>';
	}

	/** Broj proizvoda po rasponu cijene u trenutnoj kategoriji (zasivljuje prazne raspone). */
	private static function broj_po_cijeni(): array {
		global $wpdb;
		$where = "p.post_type = 'product' AND p.post_status = 'publish'";
		$join  = '';
		if ( is_product_taxonomy() ) {
			$t   = get_queried_object();
			$ids = array_merge( array( (int) $t->term_id ), array_map( 'intval', (array) get_term_children( $t->term_id, $t->taxonomy ) ) );
			$tt  = $wpdb->get_col( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id IN (" . implode( ',', $ids ) . ')' ); // phpcs:ignore
			if ( ! $tt ) {
				return array();
			}
			$join   = "JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID";
			$where .= ' AND tr.term_taxonomy_id IN (' . implode( ',', array_map( 'intval', $tt ) ) . ')';
		} elseif ( is_search() ) {
			return array();
		}
		$kljuc = 'dsk_cijene_' . md5( $join . $where );
		$out   = get_transient( $kljuc );
		if ( false !== $out ) {
			return $out;
		}
		$cijene = $wpdb->get_col( "SELECT DISTINCT p.ID, l.min_price FROM {$wpdb->posts} p {$join} JOIN {$wpdb->wc_product_meta_lookup} l ON l.product_id = p.ID WHERE {$where}", 1 ); // phpcs:ignore
		$out    = array();
		foreach ( self::CIJENE as $i => list( $od, $do ) ) {
			$out[ $i ] = count( array_filter( $cijene, static function ( $c ) use ( $od, $do ) { return (float) $c >= $od && ( ! $do || (float) $c <= $do ); } ) );
		}
		set_transient( $kljuc, $out, HOUR_IN_SECONDS );
		return $out;
	}

	/* ---------- nedavno pregledano, ispod artikala ---------- */

	public static function nedavno(): void {
		if ( 'yes' !== Domestic_Kartica::postavke()['nedavno'] || empty( $_COOKIE['woocommerce_recently_viewed'] ) ) {
			return;
		}
		$ids = array_reverse( wp_parse_id_list( (array) explode( '|', wp_unslash( $_COOKIE['woocommerce_recently_viewed'] ) ) ) ); // phpcs:ignore
		$st  = 6;
		$out = array();
		foreach ( $ids as $id ) {
			$p = wc_get_product( $id );
			if ( $p && $p->is_visible() ) {
				$out[] = $p;
			}
			if ( count( $out ) >= $st ) {
				break;
			}
		}
		if ( ! $out ) {
			return;
		}
		echo '<section class="dsk-nedavno" aria-labelledby="dsk-nedavno-naslov"><h2 id="dsk-nedavno-naslov">Nedavno pregledano</h2><ul class="dsk-nedavno-niz">';
		foreach ( $out as $p ) {
			echo '<li><a href="' . esc_url( $p->get_permalink() ) . '"><span class="dsk-nedavno-slika">' . $p->get_image( 'thumbnail', array( 'loading' => 'lazy', 'alt' => '' ) ) . '</span>' // phpcs:ignore WordPress.Security.EscapeOutput
				. '<span class="dsk-nedavno-naziv">' . esc_html( $p->get_name() ) . '</span>'
				. '<span class="dsk-nedavno-cijena">' . esc_html( wp_strip_all_tags( wc_price( (float) wc_get_price_to_display( $p ) ) ) ) . '</span></a></li>';
		}
		echo '</ul></section>';
	}
}
