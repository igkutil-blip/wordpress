<?php
/**
 * Što ne ide u Google: probne i pomoćne stranice (noindex i izvan sitemapa), dijelovi teme
 * (blokovi, portfolio), mjesta i hoteli WpTravellyja te skriveni proizvodi izleta.
 *
 * @package Plan_A_Seo
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Seo_Index {

	/** Vrste sadržaja koje nisu za Google (dijelovi dizajna i pomoćni zapisi). */
	const HIDDEN_TYPES = array( 'blocks', 'featured_item', 'ttbm_places', 'ttbm_hotel', 'ttbm_ticket_types', 'rm_content_editor' );

	public static function init() {
		add_filter( 'rank_math/frontend/robots', array( __CLASS__, 'robots' ), 99 );
		add_filter( 'wp_robots', array( __CLASS__, 'wp_robots' ), 99 );
		add_filter( 'rank_math/sitemap/posts_to_exclude', array( __CLASS__, 'sitemap_posts' ), 99 );
		add_filter( 'rank_math/sitemap/exclude_post_type', array( __CLASS__, 'sitemap_type' ), 99, 2 );
		add_action( 'save_post', array( __CLASS__, 'flush' ) );
	}

	/** Stranice s noindexom (po adresi iz postavki). */
	public static function noindex_ids(): array {
		static $ids = null;
		if ( null !== $ids ) {
			return $ids;
		}
		$ids = array();
		// Košarica, blagajna i račun (WooCommerce ih ionako ne daje u Google).
		foreach ( array( 'woocommerce_cart_page_id', 'woocommerce_checkout_page_id', 'woocommerce_myaccount_page_id' ) as $o ) {
			if ( (int) get_option( $o ) ) {
				$ids[] = (int) get_option( $o );
			}
		}
		foreach ( preg_split( '/[\s,]+/', (string) Plan_A_Seo::opt( 'noindex' ) ) as $slug ) {
			$slug = trim( (string) $slug, '/ ' );
			if ( '' === $slug ) {
				continue;
			}
			if ( ctype_digit( $slug ) ) {
				$ids[] = (int) $slug;
				continue;
			}
			$p  = get_page_by_path( $slug, OBJECT, array( 'page', 'post' ) );
			$id = $p ? (int) $p->ID : 0;
			if ( ! $id ) {
				global $wpdb;
				// Dodatak Custom Permalinks: adresa je u podacima stranice.
				$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'custom_permalink' AND meta_value IN (%s, %s) LIMIT 1", $slug, $slug . '/' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
			if ( ! $id ) {
				$id = (int) url_to_postid( home_url( '/' . $slug . '/' ) );
			}
			if ( $id ) {
				$ids[] = $id;
			}
		}
		// Stranica „Unos izleta” (samo za vodiče) i stranice čiji naš kratki kod više ne postoji (npr. obrisan dodatak –
		// na stranici bi bio samo „[plan-a-poklon-bon]”): ne u Google, sitemap ni llms.txt.
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE '%[plan-a-%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( (array) $rows as $r ) {
			if ( ! preg_match_all( '/\[(plan-a-[a-z0-9-]+)/', (string) $r->post_content, $m ) ) {
				continue;
			}
			foreach ( array_unique( $m[1] ) as $tag ) {
				if ( 'plan-a-unos-izleta' === $tag || ! shortcode_exists( $tag ) ) {
					$ids[] = (int) $r->ID;
					break;
				}
			}
		}
		return $ids = array_values( array_unique( $ids ) );
	}

	/** Skriveni proizvodi (proizvodi izleta i jedrenja koje WooCommerce ne prikazuje u trgovini). */
	public static function hidden_products(): array {
		$ids = get_transient( 'plan_a_seo_hidden_products' );
		if ( is_array( $ids ) ) {
			return $ids;
		}
		$ids = array();
		if ( taxonomy_exists( 'product_visibility' ) ) {
			$ids = get_posts(
				array(
					'post_type'      => 'product',
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'tax_query'      => array( array( 'taxonomy' => 'product_visibility', 'field' => 'slug', 'terms' => array( 'exclude-from-catalog', 'exclude-from-search' ), 'operator' => 'IN' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
				)
			);
		}
		$linked = get_posts( array( 'post_type' => 'product', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => 'link_ttbm_id' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$ids    = array_values( array_unique( array_map( 'intval', array_merge( $ids, $linked ) ) ) );
		set_transient( 'plan_a_seo_hidden_products', $ids, DAY_IN_SECONDS );
		return $ids;
	}

	public static function flush( $id ) {
		if ( 'product' === get_post_type( $id ) ) {
			delete_transient( 'plan_a_seo_hidden_products' );
		}
	}

	/** Je li trenutna stranica za noindex. */
	public static function is_hidden(): bool {
		if ( ! is_singular() ) {
			return false;
		}
		$id   = (int) get_queried_object_id();
		$type = get_post_type( $id );
		return in_array( $type, self::HIDDEN_TYPES, true )
			|| in_array( $id, self::noindex_ids(), true )
			|| ( 'product' === $type && in_array( $id, self::hidden_products(), true ) );
	}

	public static function robots( $robots ) {
		if ( self::is_hidden() ) {
			$robots          = is_array( $robots ) ? $robots : array();
			$robots['index']  = 'noindex';
			$robots['follow'] = 'follow';
		}
		return $robots;
	}

	/** Bez Rank Matha (ili uz njega) i WordPressova oznaka. */
	public static function wp_robots( $robots ) {
		if ( self::is_hidden() ) {
			unset( $robots['index'] );
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}

	public static function sitemap_posts( $ids ) {
		return array_values( array_unique( array_merge( array_map( 'intval', (array) $ids ), self::noindex_ids(), self::hidden_products() ) ) );
	}

	public static function sitemap_type( $exclude, $type ) {
		return in_array( $type, self::HIDDEN_TYPES, true ) ? true : $exclude;
	}
}
