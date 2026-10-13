<?php
/**
 * /llms.txt – sažetak stranice za AI pretraživače i asistente (ChatGPT, Claude, Perplexity,
 * Google AI): tko smo, nadolazeći izleti (termin, trajanje, cijena, slobodna mjesta),
 * važne stranice i članci, kontakt. Slaže se sam iz stranice (spremljeno 6 sati ili do
 * prve promjene sadržaja).
 *
 * @package Plan_A_Seo
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Seo_Llms {

	const CACHE = 'plan_a_seo_llms';

	public static function init() {
		add_action( 'parse_request', array( __CLASS__, 'serve' ), 0 );
		add_action( 'save_post', array( __CLASS__, 'flush' ) );
		add_filter( 'robots_txt', array( __CLASS__, 'robots' ), 20 );
	}

	public static function flush() {
		delete_transient( self::CACHE );
	}

	public static function serve() {
		$path = trim( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
		if ( ( '' !== $home ? $home . '/' : '' ) . 'llms.txt' !== $path ) {
			return;
		}
		$txt = get_transient( self::CACHE );
		if ( ! is_string( $txt ) || '' === $txt ) {
			$txt = self::build();
			set_transient( self::CACHE, $txt, 6 * HOUR_IN_SECONDS );
		}
		status_header( 200 );
		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' ); // sažetak za AI, ne zasebna stranica u Googleu
		echo $txt; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- čisti tekst
		exit;
	}

	/** U robots.txt: poveznica na sažetak (komentar, ne smeta pretraživačima). */
	public static function robots( $txt ) {
		return rtrim( (string) $txt ) . "\n\n# Sažetak za AI pretraživače: " . home_url( '/llms.txt' ) . "\n";
	}

	private static function line( string $s ): string {
		return trim( (string) preg_replace( '/\s+/u', ' ', Plan_A_Seo_Text::clean( $s ) ) );
	}

	private static function desc( int $id ): string {
		$d = (string) get_post_meta( $id, 'rank_math_description', true );
		if ( '' === trim( $d ) || false !== strpos( $d, '%' ) ) {
			$p = get_post( $id );
			$d = $p ? Plan_A_Seo_Text::cut( Plan_A_Seo_Text::lead( $p ), 200 ) : '';
		}
		return self::line( $d );
	}

	private static function money( float $p ): string {
		return ( 0.0 === fmod( $p, 1.0 ) ? (string) (int) $p : number_format( $p, 2, ',', '.' ) ) . ' €';
	}

	public static function build(): string {
		$o   = Plan_A_Seo_Schema::ORG;
		$out = array();
		$out[] = '# Plan A – planinarenje i avantura (' . $o['legalName'] . ')';
		$out[] = '';
		$out[] = '> ' . $o['description'] . ' Izleti se objavljuju i prijavljuju na ' . home_url( '/' ) . ', a plaćaju uplatom na račun agencije organizatora (Adventure Donkey j.d.o.o.). Na izletima je potrebno članstvo u udruzi (pristupnica i godišnja članarina).';
		$out[] = '';
		$out[] = '- Sjedište: ' . $o['street'] . ', ' . $o['postal'] . ' ' . $o['city'] . ', Hrvatska';
		$out[] = '- Kontakt: ' . $o['email'] . ', ' . $o['telephone'];
		$out[] = '- Vođenje: licencirani planinski vodiči; izlete organizira turistička agencija Adventure Donkey j.d.o.o. u suradnji s udrugom';
		$out[] = '- Jezik: hrvatski';
		$out[] = '';

		// Nadolazeći izleti.
		$tours = get_posts( array( 'post_type' => 'ttbm_tour', 'post_status' => 'publish', 'posts_per_page' => 100 ) );
		$rows  = array();
		foreach ( $tours as $t ) {
			foreach ( Plan_A_Seo_Schema::terms( $t->ID ) as $term ) {
				$d     = substr( $term[0], 0, 10 );
				$e     = substr( (string) $term[1], 0, 10 );
				$when  = gmdate( 'j.n.Y.', strtotime( $d ) ) . ( $e && $e > $d ? ' – ' . gmdate( 'j.n.Y.', strtotime( $e ) ) : '' );
				$facts = array( 'termin ' . $when );
				$place = self::line( (string) get_post_meta( $t->ID, 'ttbm_location_name', true ) );
				if ( '' !== $place ) {
					$facts[] = $place;
				}
				$price = Plan_A_Seo_Schema::price( $t->ID );
				if ( $price > 0 ) {
					$facts[] = 'cijena od ' . self::money( $price );
				}
				$free = Plan_A_Seo_Schema::free( $t->ID, $d );
				if ( null !== $free ) {
					$facts[] = $free > 0 ? 'slobodnih mjesta: ' . $free : 'popunjeno';
				}
				$rows[ $d . '-' . $t->ID ] = '- [' . self::line( $t->post_title ) . '](' . get_permalink( $t ) . '): ' . implode( ', ', $facts ) . '. ' . self::desc( $t->ID );
			}
		}
		ksort( $rows );
		$out[] = '## Nadolazeći izleti';
		$out[] = '';
		$out   = array_merge( $out, $rows ? array_values( $rows ) : array( '- Trenutno nema objavljenih termina. Svi izleti: ' . home_url( '/izleti/' ) ) );
		$out[] = '';

		// Važne stranice.
		$skip  = array_merge( Plan_A_Seo_Index::noindex_ids(), array_map( 'intval', array( get_option( 'woocommerce_cart_page_id' ), get_option( 'woocommerce_checkout_page_id' ), get_option( 'woocommerce_myaccount_page_id' ) ) ) );
		$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => 100, 'orderby' => 'menu_order title', 'order' => 'ASC' ) );
		$out[] = '## Stranice';
		$out[] = '';
		foreach ( $pages as $p ) {
			if ( in_array( (int) $p->ID, $skip, true ) || post_password_required( $p ) ) {
				continue;
			}
			$d     = self::desc( $p->ID );
			$out[] = '- [' . self::line( $p->post_title ) . '](' . get_permalink( $p ) . ')' . ( '' !== $d ? ': ' . $d : '' );
		}
		$out[] = '';

		// Vodiči.
		$guides = get_posts( array( 'post_type' => 'ttbm_guide', 'post_status' => 'publish', 'posts_per_page' => 50 ) );
		if ( $guides ) {
			$out[] = '## Vodiči';
			$out[] = '';
			foreach ( $guides as $g ) {
				$d     = self::desc( $g->ID );
				$out[] = '- [' . self::line( $g->post_title ) . '](' . get_permalink( $g ) . ')' . ( '' !== $d ? ': ' . $d : '' );
			}
			$out[] = '';
		}

		// Članci.
		$posts = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 60 ) );
		if ( $posts ) {
			$out[] = '## Članci (blog)';
			$out[] = '';
			foreach ( $posts as $p ) {
				$d     = self::desc( $p->ID );
				$out[] = '- [' . self::line( $p->post_title ) . '](' . get_permalink( $p ) . ')' . ( '' !== $d ? ': ' . $d : '' );
			}
			$out[] = '';
		}
		$out[] = '## Kontakt';
		$out[] = '';
		$out[] = '- E-mail: ' . $o['email'];
		$out[] = '- Telefon: ' . $o['telephone'];
		foreach ( $o['sameAs'] as $s ) {
			$out[] = '- ' . $s;
		}
		$out[] = '';
		$out[] = '_Ažurirano: ' . current_time( 'j.n.Y. H:i' ) . '_';
		return implode( "\n", $out ) . "\n";
	}
}
