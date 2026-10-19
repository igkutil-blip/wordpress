<?php
/**
 * Poveznice među stranicama: stranica se traži po svom kratkom kodu (ne po adresi),
 * pa poveznice rade i kad stranica ima drugačiju adresu ili naslov.
 */
defined( 'ABSPATH' ) || exit;

final class AKA_Linkovi {
	const CACHE = 'aka_linkovi';

	/** ključ => [kratki kod, zadana adresa, naslov stranice] */
	const STRANICE = array(
		'naslovnica' => array( 'ai-akademija-naslovnica', '/', 'Naslovnica' ),
		'programi'   => array( 'ai-akademija-programi', '/programi/', 'Programi' ),
		'mentori'    => array( 'ai-akademija-mentori', '/mentori/', 'Mentori' ),
		'tvrtke'     => array( 'ai-akademija-tvrtke', '/za-tvrtke/', 'Za tvrtke' ),
		'o-nama'     => array( 'ai-akademija-o-nama', '/o-nama/', 'O nama' ),
		'kontakt'    => array( 'ai-akademija-kontakt', '/kontakt/', 'Kontakt' ),
		'cijene'     => array( 'ai-akademija-cijene', '/cijene/', 'Cijene' ),
		'pitanja'    => array( 'ai-akademija-pitanja', '/cesta-pitanja/', 'Česta pitanja' ),
		'uvjeti'     => array( 'ai-akademija-uvjeti', '/uvjeti-koristenja/', 'Uvjeti korištenja' ),
	);

	private static $mapa = null;

	public static function init() {
		foreach ( array( 'save_post_page', 'deleted_post', 'trashed_post', 'untrashed_post' ) as $h ) {
			add_action( $h, array( __CLASS__, 'obrisi_cache' ) );
		}
		add_action( 'update_option_page_on_front', array( __CLASS__, 'obrisi_cache' ) );
		add_action( 'update_option_page_for_posts', array( __CLASS__, 'obrisi_cache' ) );
	}

	public static function obrisi_cache() {
		delete_transient( self::CACHE );
		self::$mapa = null;
	}

	/** [ 'stranice' => ključ => ID, 'tecajevi' => slug => ID ] */
	public static function mapa() {
		if ( null !== self::$mapa ) {
			return self::$mapa;
		}
		$m = get_transient( self::CACHE );
		if ( ! is_array( $m ) ) {
			$m     = array( 'stranice' => array(), 'tecajevi' => array() );
			$pages = get_posts(
				array(
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'posts_per_page' => 500,
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				)
			);
			foreach ( $pages as $p ) {
				foreach ( self::STRANICE as $kljuc => $s ) {
					if ( ! isset( $m['stranice'][ $kljuc ] ) && has_shortcode( $p->post_content, $s[0] ) ) {
						$m['stranice'][ $kljuc ] = $p->ID;
					}
				}
				if ( has_shortcode( $p->post_content, 'ai-akademija-tecaj' ) ) {
					$slug = preg_match( '/\[ai-akademija-tecaj[^\]]*slug=["\']?([a-z0-9-]+)/', $p->post_content, $x ) ? $x[1] : $p->post_name;
					if ( ! isset( $m['tecajevi'][ $slug ] ) ) {
						$m['tecajevi'][ $slug ] = $p->ID;
					}
				}
			}
			$front = (int) get_option( 'page_on_front' );
			if ( $front && 'page' === get_option( 'show_on_front' ) && ! isset( $m['stranice']['naslovnica'] ) ) {
				$m['stranice']['naslovnica'] = $front;
			}
			set_transient( self::CACHE, $m, DAY_IN_SECONDS );
		}
		self::$mapa = $m;
		return $m;
	}

	/** Adresa stranice po ključu; ako stranica ne postoji, zadana adresa. */
	public static function stranica( $kljuc ) {
		if ( 'naslovnica' === $kljuc ) {
			return home_url( '/' );
		}
		if ( 'blog' === $kljuc ) {
			$id = (int) get_option( 'page_for_posts' );
			if ( $id && 'publish' === get_post_status( $id ) ) {
				return get_permalink( $id );
			}
			return 'page' === get_option( 'show_on_front' ) ? '' : home_url( '/' ); // nema stranice za objave → poveznica se ne prikazuje
		}
		if ( 'privatnost' === $kljuc ) {
			return get_privacy_policy_url(); // '' dok stranica privatnosti nije objavljena → poveznica se ne prikazuje
		}
		$m = self::mapa();
		if ( isset( $m['stranice'][ $kljuc ] ) ) {
			return get_permalink( $m['stranice'][ $kljuc ] );
		}
		return home_url( isset( self::STRANICE[ $kljuc ] ) ? self::STRANICE[ $kljuc ][1] : '/' );
	}

	/** Tečaj: njegova stranica; bez stranice → kontakt s upisanim tečajem. */
	public static function tecaj( $slug ) {
		$m = self::mapa();
		if ( isset( $m['tecajevi'][ $slug ] ) ) {
			return get_permalink( $m['tecajevi'][ $slug ] );
		}
		return add_query_arg( 'tecaj', $slug, self::stranica( 'kontakt' ) );
	}

	public static function postoji( $kljuc ) {
		$m = self::mapa();
		return isset( $m['stranice'][ $kljuc ] );
	}

	/**
	 * Stara zapisana adresa („/programi/”, „/kontakt/?tecaj=x”, „/programi/uvod-u-ai/”) → prava adresa stranice.
	 */
	public static function iz_putanje( $path ) {
		$q    = '';
		$frag = '';
		if ( false !== ( $i = strpos( $path, '#' ) ) ) {
			$frag = substr( $path, $i );
			$path = substr( $path, 0, $i );
		}
		if ( false !== ( $i = strpos( $path, '?' ) ) ) {
			$q    = substr( $path, $i + 1 );
			$path = substr( $path, 0, $i );
		}
		$path = '/' . trim( $path, '/' ) . ( '/' === $path ? '' : '/' );
		$url  = null;
		if ( preg_match( '#^/programi/([a-z0-9-]+)/$#', $path, $x ) && AKA_Podaci::tecaj( $x[1] ) ) {
			$url = self::tecaj( $x[1] );
		} elseif ( '/blog/' === $path ) {
			$url = self::stranica( 'blog' );
		} else {
			foreach ( self::STRANICE as $kljuc => $s ) {
				if ( $s[1] === $path || ( '/' === $s[1] && '//' === $path ) ) {
					$url = self::stranica( $kljuc );
					break;
				}
			}
		}
		if ( null === $url ) {
			$url = home_url( $path );
		}
		if ( $q ) {
			parse_str( $q, $args );
			$url = add_query_arg( array_map( 'rawurlencode', $args ), $url );
		}
		return $url . $frag;
	}

	/** Popis za admin: što postoji, što nedostaje. */
	public static function stanje() {
		$m   = self::mapa();
		$out = array();
		foreach ( self::STRANICE as $kljuc => $s ) {
			$id    = isset( $m['stranice'][ $kljuc ] ) ? $m['stranice'][ $kljuc ] : 0;
			$out[] = array( 'naslov' => $s[2], 'kod' => '[' . $s[0] . ']', 'id' => $id, 'url' => $id ? get_permalink( $id ) : '', 'adresa' => $s[1], 'kljuc' => $kljuc );
		}
		foreach ( AKA_Podaci::tecajevi() as $slug => $t ) {
			$id    = isset( $m['tecajevi'][ $slug ] ) ? $m['tecajevi'][ $slug ] : 0;
			$out[] = array( 'naslov' => 'Tečaj: ' . $t['naslov'], 'kod' => '[ai-akademija-tecaj]', 'id' => $id, 'url' => $id ? get_permalink( $id ) : '', 'adresa' => '/programi/' . $slug . '/', 'kljuc' => 'tecaj:' . $slug );
		}
		$out[] = array( 'naslov' => 'Blog (stranica za objave)', 'kod' => 'Postavke → Čitanje → „Stranica objava”', 'id' => (int) ( self::stranica( 'blog' ) ? get_option( 'page_for_posts' ) : 0 ), 'url' => self::stranica( 'blog' ), 'adresa' => 'nije postavljena – poveznica Blog se ne prikazuje', 'kljuc' => 'blog' );
		$priv  = get_privacy_policy_url();
		$out[] = array( 'naslov' => 'Pravila privatnosti', 'kod' => 'Postavke → Privatnost', 'id' => $priv ? (int) get_option( 'wp_page_for_privacy_policy' ) : 0, 'url' => $priv, 'adresa' => 'nije objavljena – poveznica se ne prikazuje', 'kljuc' => 'privatnost' );
		return $out;
	}
}
