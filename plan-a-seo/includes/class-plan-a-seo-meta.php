<?php
/**
 * SEO polja Rank Matha (ključna riječ, naslov, opis) za stranice, objave i vodiče.
 *
 * Pravilo: upisuje samo u prazna polja ili u ono što je sam ranije složio. Ručno upisano se
 * ne dira, osim ako je očito pogrešno: opis jednak naslovu, ostatak predloška teme, ili
 * naslov/opis prepisan s druge stranice (duplikat). Prije prvog upisa sprema staro (za „Vrati”).
 *
 * @package Plan_A_Seo
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Seo_Meta {

	const AUTO  = '_paseo_auto'; // zadnje složene vrijednosti
	const PREV  = '_paseo_prev'; // vrijednosti prije prvog upisa
	const TYPES = array( 'page', 'post', 'ttbm_guide' );
	const KEYS  = array( 'rank_math_focus_keyword', 'rank_math_title', 'rank_math_description' );

	public static function init() {
		add_action( 'wp_after_insert_post', array( __CLASS__, 'on_save' ), 25, 2 );
		add_action( 'load-post.php', array( __CLASS__, 'on_load' ) );
	}

	public static function on_save( $id, $post ) {
		if ( $post && in_array( $post->post_type, self::TYPES, true ) && ! wp_is_post_revision( $id ) && ! wp_is_post_autosave( $id ) ) {
			self::apply( $post );
		}
	}

	public static function on_load() {
		$id   = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post = $id ? get_post( $id ) : null;
		if ( $post && in_array( $post->post_type, self::TYPES, true ) && current_user_can( 'edit_post', $id ) ) {
			self::apply( $post );
		}
	}

	/** Stranice, objave i vodiči koji se objavljuju (bez onih s noindexom). */
	public static function posts(): array {
		$ids = get_posts(
			array(
				'post_type'      => self::TYPES,
				'post_status'    => array( 'publish' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
		return array_values( array_diff( array_map( 'intval', $ids ), Plan_A_Seo_Index::noindex_ids() ) );
	}

	/* ---------------- Složeno ---------------- */

	public static function title_for( WP_Post $post ): string {
		$base  = Plan_A_Seo_Text::clean( $post->post_title );
		$base  = (string) preg_replace( '/\s*[-–|]\s*Plan A\s*$/u', '', $base );
		$tails = false !== mb_stripos( $base, 'plan a' ) ? array( ' – planinarenje', '' ) : array( Plan_A_Seo::opt( 'suffix' ), ' – Plan A', '' );
		if ( false !== mb_stripos( $base, 'jedren' ) ) {
			$tails = array( ' – Plan A jedrenje', ' – Plan A', '' );
		}
		foreach ( $tails as $t ) {
			// Bez ponavljanja: „Jedrenje – Plan A jedrenje”.
			$last = mb_strtolower( (string) preg_replace( '/^.*\s/u', '', trim( $t ) ) );
			if ( '' !== $t && 'a' !== $last && false !== mb_stripos( $base, $last ) ) {
				continue;
			}
			$c = $base . $t;
			if ( Plan_A_Seo_Text::len( $c ) <= Plan_A_Seo_Text::TITLE_MAX ) {
				return $c;
			}
		}
		return Plan_A_Seo_Text::cut_words( $base, Plan_A_Seo_Text::TITLE_MAX );
	}

	/** Opisi stranica koje nemaju tekst (obrazac, popis izleta …), po adresi. */
	const FALLBACK = array(
		'pristupnica'           => 'Pristupnica za učlanjenje u S.R.D. Plan A: ispuni je online, potvrdi e-mail i uplati članarinu. Članstvo je potrebno za sudjelovanje na izletima.',
		'prosli-izleti'         => 'Prošli izleti udruge Plan A: ferrate, zimski usponi, planinarske ture i tečajevi s licenciranim planinskim vodičima, uz fotografije i opise.',
		'protekli-izleti'       => 'Protekli izleti udruge Plan A: planinarski, ferrata i penjački izleti s licenciranim planinskim vodičima, uz fotografije i opise.',
		'politika-kolacica'     => 'Politika kolačića stranice srd-plan-a.hr: koje kolačiće koristimo, zašto ih koristimo i kako upravljati svojom privolom.',
		'plan-izleta'           => 'Plan izleta udruge Plan A: termini planinarskih izleta, ferrata, zimskih uspona i tečajeva s licenciranim planinskim vodičima.',
		'planinarski-kalkulator' => 'Planinarski kalkulator udruge Plan A: izračunaj trajanje ture prema duljini i usponu, tempo hoda i potrebnu količinu vode.',
		'jedrenje'              => 'Jedrenje s udrugom Plan A: tjedan plovidbe Jadranom za ekipu, sa skiperom, i rezervacija slobodnih termina online.',
		'poklon-bon'            => 'Poklon bon udruge Plan A: poklonite planinarski izlet, ferratu ili tečaj s licenciranim planinskim vodičima. Bon se koristi pri prijavi na izlet.',
	);

	public static function description_for( WP_Post $post ): string {
		$title = Plan_A_Seo_Text::clean( $post->post_title );
		if ( (int) get_option( 'page_for_posts' ) === (int) $post->ID || 'blog' === $post->post_name ) {
			return 'Blog udruge Plan A: iskustva s izleta i tura, savjeti za planinarenje, ferrate, zimske uspone i opremu, iz pera licenciranih planinskih vodiča.';
		}
		if ( 'ttbm_guide' === $post->post_type && '' !== Plan_A_Seo_Schema::guide_short( $post ) ) {
			return Plan_A_Seo_Schema::guide_short( $post );
		}
		$lead = Plan_A_Seo_Text::lead( $post );
		$names = array( (string) $post->post_name, trim( (string) get_post_meta( $post->ID, 'custom_permalink', true ), '/' ), sanitize_title( $post->post_title ) );
		foreach ( self::FALLBACK as $slug => $text ) {
			$hit = false;
			foreach ( $names as $n ) {
				$hit = $hit || ( '' !== $n && 0 === strpos( $n, $slug ) );
			}
			if ( $hit && Plan_A_Seo_Text::len( $lead ) < 120 ) {
				return $text;
			}
		}
		if ( '' === $lead ) {
			$what = 'ttbm_guide' === $post->post_type ? 'licencirani planinski vodič udruge Plan A' : 'S.R.D. Plan A, organizirani planinarski izleti, ferrate i zimske ture s licenciranim planinskim vodičima';
			return Plan_A_Seo_Text::cut( $title . ' – ' . $what . '.', Plan_A_Seo_Text::DESC_MAX );
		}
		$desc = Plan_A_Seo_Text::sentences( $lead, Plan_A_Seo_Text::DESC_MAX );
		// Ključna riječ (naziv) treba biti u opisu: kratki naziv ispred uvoda.
		$kw = Plan_A_Seo_Text::keyword( $title );
		if ( '' !== $kw && false === mb_stripos( $desc, $kw ) ) {
			$head = Plan_A_Seo_Text::head( $title );
			$head = Plan_A_Seo_Text::len( $head ) <= 45 ? $head : $kw;
			$head = mb_strtoupper( mb_substr( $head, 0, 1 ) ) . mb_substr( $head, 1 );
			$desc = Plan_A_Seo_Text::sentences( $head . ': ' . $lead, Plan_A_Seo_Text::DESC_MAX );
		}
		if ( ! preg_match( '/[.!?…]$/u', $desc ) ) {
			$desc .= '.';
		}
		return $desc;
	}

	public static function build( WP_Post $post ): array {
		return array(
			'rank_math_focus_keyword' => Plan_A_Seo_Text::keyword( $post->post_title ),
			'rank_math_title'         => self::title_for( $post ),
			'rank_math_description'   => self::description_for( $post ),
		);
	}

	/* ---------------- Odluka ---------------- */

	/** Isti SEO naslov ili opis na više stranica: id => true za one koji nisu „vlasnik”. */
	public static function duplicates( string $key ): array {
		global $wpdb;
		$types = "'" . implode( "','", array_map( 'esc_sql', self::TYPES ) ) . "'";
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = %s AND m.meta_value <> '' AND p.post_status = 'publish' AND p.post_type IN ($types)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$key
			)
		);
		$groups = array();
		foreach ( (array) $rows as $r ) {
			$groups[ Plan_A_Seo_Text::norm( (string) $r->meta_value ) ][] = $r;
		}
		$out = array();
		foreach ( $groups as $g ) {
			if ( count( $g ) < 2 ) {
				continue;
			}
			// Vlasnik je najstarija stranica (s nje je tekst prepisan), osim ako neka druga
			// po naslovu jasno više odgovara tekstu.
			usort( $g, static fn( $a, $b ) => (int) $a->ID <=> (int) $b->ID );
			$best = (int) $g[0]->ID;
			similar_text( Plan_A_Seo_Text::norm( (string) $g[0]->post_title ), Plan_A_Seo_Text::norm( (string) $g[0]->meta_value ), $top );
			foreach ( $g as $r ) {
				similar_text( Plan_A_Seo_Text::norm( (string) $r->post_title ), Plan_A_Seo_Text::norm( (string) $r->meta_value ), $pct );
				if ( $pct > $top + 20 ) {
					$top  = $pct;
					$best = (int) $r->ID;
				}
			}
			foreach ( $g as $r ) {
				if ( (int) $r->ID !== $best ) {
					$out[ (int) $r->ID ] = true;
				}
			}
		}
		return $out;
	}

	/**
	 * Što bi se promijenilo: [ključ => [staro, novo, razlog]].
	 *
	 * @param array|null $dups [ 'rank_math_title' => [id => true], 'rank_math_description' => […] ]
	 */
	public static function plan( WP_Post $post, ?array $dups = null, bool $force = false ): array {
		if ( (int) get_option( 'page_on_front' ) === (int) $post->ID && ! $force ) {
			return array(); // naslovnica: ručno složena, ne dira se
		}
		$new  = self::build( $post );
		$auto = get_post_meta( $post->ID, self::AUTO, true );
		$auto = is_array( $auto ) ? $auto : array();
		$out  = array();
		foreach ( self::KEYS as $k ) {
			$cur = (string) get_post_meta( $post->ID, $k, true );
			if ( '' === $new[ $k ] || $cur === $new[ $k ] ) {
				continue;
			}
			$why = '';
			if ( $force ) {
				$why = 'složi ispočetka';
			} elseif ( '' === $cur ) {
				$why = 'prazno';
			} elseif ( isset( $auto[ $k ] ) && $cur === $auto[ $k ] ) {
				$why = 'osvježeno';
			} elseif ( 'rank_math_focus_keyword' !== $k && null !== $dups && ! empty( $dups[ $k ][ $post->ID ] ) ) {
				$why = 'isto kao na drugoj stranici';
			} elseif ( 'rank_math_focus_keyword' !== $k && preg_match( Plan_A_Seo_Text::JUNK, $cur ) ) {
				$why = 'ostatak predloška';
			} elseif ( 'rank_math_description' === $k && ( Plan_A_Seo_Text::norm( $cur ) === Plan_A_Seo_Text::norm( $post->post_title ) || Plan_A_Seo_Text::len( $cur ) < 50 ) ) {
				$why = 'opis je samo naslov ili prekratak';
			}
			if ( '' !== $why ) {
				$out[ $k ] = array( $cur, $new[ $k ], $why );
			}
		}
		return $out;
	}

	/** Upiši (prije prvog upisa spremi staro). Vraća broj promijenjenih polja. */
	public static function apply( WP_Post $post, ?array $dups = null, bool $force = false ): int {
		if ( null === $dups ) {
			$dups = array(
				'rank_math_title'       => self::duplicates( 'rank_math_title' ),
				'rank_math_description' => self::duplicates( 'rank_math_description' ),
			);
		}
		$plan = self::plan( $post, $dups, $force );
		if ( ! $plan ) {
			return 0;
		}
		$prev = get_post_meta( $post->ID, self::PREV, true );
		$prev = is_array( $prev ) ? $prev : array();
		$auto = get_post_meta( $post->ID, self::AUTO, true );
		$auto = is_array( $auto ) ? $auto : array();
		foreach ( $plan as $k => $p ) {
			if ( ! array_key_exists( $k, $prev ) ) {
				$prev[ $k ] = $p[0];
			}
			update_post_meta( $post->ID, $k, wp_slash( $p[1] ) );
			$auto[ $k ] = $p[1];
		}
		update_post_meta( $post->ID, self::PREV, $prev );
		update_post_meta( $post->ID, self::AUTO, $auto );
		return count( $plan );
	}

	/** Vrati vrijednosti od prije prvog upisa (samo polja koja se od tada nisu ručno mijenjala). */
	public static function undo( int $id ): int {
		$prev = get_post_meta( $id, self::PREV, true );
		$auto = get_post_meta( $id, self::AUTO, true );
		if ( ! is_array( $prev ) ) {
			return 0;
		}
		$n = 0;
		foreach ( $prev as $k => $v ) {
			$cur = (string) get_post_meta( $id, $k, true );
			if ( is_array( $auto ) && isset( $auto[ $k ] ) && $cur !== $auto[ $k ] ) {
				continue; // u međuvremenu ručno promijenjeno
			}
			if ( '' === (string) $v ) {
				delete_post_meta( $id, $k );
			} else {
				update_post_meta( $id, $k, wp_slash( (string) $v ) );
			}
			++$n;
		}
		delete_post_meta( $id, self::PREV );
		delete_post_meta( $id, self::AUTO );
		return $n;
	}
}
