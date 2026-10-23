<?php
/**
 * Pretraga u zaglavlju: prijedlozi dok se tipka (sličica, marka, naziv, cijena), bez obzira na kvačice (ljesnjak = lješnjak),
 * po nazivu, marki, kategoriji i šifri. Ista pretraga vrijedi i za stranicu rezultata (?s=…&post_type=product).
 */

defined( 'ABSPATH' ) || exit;

final class Domestic_Kartica_Trazilica {

	const KLJUC = 'dsk_trazilica_popis';

	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'ruta' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'skripta' ), 30 );
		add_filter( 'theme_mod_live_search', array( __CLASS__, 'flatsome_iskljuci' ) );
		add_filter( 'theme_mod_search_placeholder', array( __CLASS__, 'placeholder' ) );
		add_filter( 'posts_search', array( __CLASS__, 'rezultati' ), 20, 2 );
		add_filter(
			'body_class',
			static function ( $c ) {
				$s = Domestic_Kartica::postavke();
				if ( 'yes' === $s['ukljuceno'] && 'yes' === $s['zaglavlje'] ) {
					$c[] = 'dsk-zaglavlje';
				}
				return $c;
			}
		);
		foreach ( array( 'save_post_product', 'woocommerce_update_product', 'edited_product_cat', 'deleted_post' ) as $h ) {
			add_action( $h, array( __CLASS__, 'obrisi' ) );
		}
	}

	private static function ukljuceno(): bool {
		return 'yes' === Domestic_Kartica::postavke()['ukljuceno'] && 'yes' === Domestic_Kartica::postavke()['trazilica'];
	}

	public static function flatsome_iskljuci( $v ) {
		return self::ukljuceno() && ! is_admin() ? 0 : $v;
	}

	public static function placeholder( $v ) {
		return self::ukljuceno() && ! is_admin() ? 'Pretraži likere, vina, rakije…' : $v;
	}

	public static function obrisi(): void {
		delete_transient( self::KLJUC );
	}

	public static function norm( string $s ): string {
		$s = remove_accents( html_entity_decode( wp_strip_all_tags( $s ), ENT_QUOTES, 'UTF-8' ) );
		$s = strtr( $s, array( 'đ' => 'd', 'Đ' => 'D' ) );
		return trim( preg_replace( '/[^a-z0-9]+/', ' ', strtolower( $s ) ) );
	}

	/** Popis vidljivih proizvoda (id + tekst za traženje), u predmemoriji dok se proizvod ne promijeni. */
	private static function popis(): array {
		$p = get_transient( self::KLJUC );
		if ( is_array( $p ) ) {
			return $p;
		}
		$p   = array();
		$ids = wc_get_products( array( 'status' => 'publish', 'visibility' => 'search', 'limit' => -1, 'return' => 'ids' ) );
		foreach ( $ids as $id ) {
			$x = wc_get_product( $id );
			if ( ! $x ) {
				continue;
			}
			$kat = wp_get_post_terms( $id, 'product_cat', array( 'fields' => 'names' ) );
			$p[] = array(
				'id' => $id,
				'n'  => self::norm( $x->get_name() ),
				't'  => self::norm( Domestic_Kartica::marka( $x ) . ' ' . implode( ' ', is_array( $kat ) ? $kat : array() ) . ' ' . $x->get_sku() ),
			);
		}
		set_transient( self::KLJUC, $p, 12 * HOUR_IN_SECONDS );
		return $p;
	}

	/** ID-ovi po važnosti: sve riječi upita moraju se naći; naziv vrijedi više, početak riječi više. */
	public static function trazi( string $q ): array {
		$rijeci = array_filter( explode( ' ', self::norm( $q ) ) );
		if ( ! $rijeci ) {
			return array();
		}
		$bod = array();
		foreach ( self::popis() as $r ) {
			$s = 0;
			foreach ( $rijeci as $w ) {
				$u_nazivu = strpos( ' ' . $r['n'], ' ' . $w );
				if ( false !== $u_nazivu ) {
					$s += 10;
				} elseif ( strlen( $w ) > 2 && false !== strpos( $r['n'], $w ) ) {
					$s += 6; // usred riječi tek od 3 slova („av” ne nalazi „kravata”)
				} elseif ( false !== strpos( ' ' . $r['t'], ' ' . $w ) ) {
					$s += 4;
				} elseif ( strlen( $w ) > 2 && false !== strpos( $r['t'], $w ) ) {
					$s += 2;
				} else {
					continue 2;
				}
			}
			$bod[ $r['id'] ] = $s - strlen( $r['n'] ) / 100;
		}
		arsort( $bod );
		return array_keys( $bod );
	}

	public static function ruta(): void {
		register_rest_route(
			'domestic-kartica/v1',
			'/trazi',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => array( 'q' => array( 'type' => 'string', 'required' => true ) ),
				'callback'            => array( __CLASS__, 'odgovor' ),
			)
		);
	}

	public static function odgovor( WP_REST_Request $req ) {
		$q   = mb_substr( sanitize_text_field( (string) $req['q'] ), 0, 60 );
		$ids = self::ukljuceno() && mb_strlen( $q ) >= 2 ? self::trazi( $q ) : array();
		$out = array();
		foreach ( array_slice( $ids, 0, 6 ) as $id ) {
			$x = wc_get_product( $id );
			if ( ! $x ) {
				continue;
			}
			$out[] = array(
				'naziv'  => $x->get_name(),
				'marka'  => Domestic_Kartica::marka( $x ),
				'url'    => $x->get_permalink(),
				'slika'  => $x->get_image_id() ? wp_get_attachment_image_url( $x->get_image_id(), 'thumbnail' ) : wc_placeholder_img_src( 'thumbnail' ),
				'cijena' => html_entity_decode( wp_strip_all_tags( wc_price( (float) wc_get_price_to_display( $x ) ) ), ENT_QUOTES, 'UTF-8' ),
				'nema'   => ! $x->is_in_stock(),
			);
		}
		$r = rest_ensure_response( array( 'ukupno' => count( $ids ), 'proizvodi' => $out, 'sve' => add_query_arg( array( 's' => $q, 'post_type' => 'product' ), home_url( '/' ) ) ) );
		$r->header( 'Cache-Control', 'public, max-age=300' );
		return $r;
	}

	/** Stranica rezultata: ista pretraga (kvačice, marka, kategorija) umjesto WordPressove. */
	public static function rezultati( $sql, $q ) {
		if ( is_admin() || ! $q->is_main_query() || ! $q->is_search() || 'product' !== $q->get( 'post_type' ) || ! self::ukljuceno() ) {
			return $sql;
		}
		global $wpdb;
		$ids = self::trazi( (string) $q->get( 's' ) );
		if ( ! $ids ) {
			return $sql; // ništa naše – ostavi WordPressovu pretragu (npr. riječ iz opisa)
		}
		if ( empty( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$q->set( 'orderby', 'post__in' ); // po važnosti, osim kad kupac sam izabere poredak
			$q->set( 'post__in', $ids );
		}
		return " AND {$wpdb->posts}.ID IN (" . implode( ',', array_map( 'intval', $ids ) ) . ') ';
	}

	public static function skripta(): void {
		if ( ! self::ukljuceno() ) {
			return;
		}
		wp_enqueue_script( 'domestic-trazilica', DSK_URL . 'assets/trazilica.js', array(), DSK_VER . '.' . filemtime( DSK_DIR . '/assets/trazilica.js' ), true );
		wp_localize_script( 'domestic-trazilica', 'dskTrazi', array( 'url' => esc_url_raw( rest_url( 'domestic-kartica/v1/trazi' ) ) ) );
	}
}
