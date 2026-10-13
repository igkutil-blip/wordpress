<?php
/**
 * SEO (Rank Math) iz podataka izleta: ključna riječ, SEO naslov, opis za Google i
 * alternativni tekst glavne slike. Polje se popunjava samo ako je prazno ili ako u
 * njemu još stoji ono što je ovdje zadnji put složeno – ručne promjene se ne diraju.
 *
 * @package Plan_A_Prijave
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Prijave_Seo {

	/** Zadnje složene vrijednosti (da se prepozna ručna promjena). */
	const AUTO = '_pap_seo_auto';

	const TITLE_MAX = 60;
	const DESC_MAX  = 158;

	public static function init() {
		// Nakon spremanja svih okvira (Rank Math sprema svoja polja na save_post).
		add_action( 'wp_after_insert_post', array( __CLASS__, 'fill' ), 20, 2 );
		// Već upisani izleti: prazna SEO polja se popune čim se izlet otvori (prije okvira Rank Matha).
		add_action( 'load-post.php', array( __CLASS__, 'on_load' ) );
		add_action( 'add_meta_boxes_' . Plan_A_Prijave_Tour::CPT, array( __CLASS__, 'box' ) );
		add_action( 'admin_post_pap_seo_fill', array( __CLASS__, 'force' ) );
	}

	public static function on_load() {
		$id   = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post = $id ? get_post( $id ) : null;
		if ( $post && current_user_can( 'edit_post', $id ) ) {
			self::fill( $id, $post );
		}
	}

	/** Okvir uz Rank Math (kartica „Ostalo”): što je složeno i gumb da se SEO složi ispočetka. */
	public static function box() {
		if ( ! self::active() ) {
			return;
		}
		add_meta_box( 'pap_seo', 'SEO iz podataka izleta', array( __CLASS__, 'box_html' ), Plan_A_Prijave_Tour::CPT, 'normal', 'high' );
	}

	public static function box_html( $post ) {
		$new = self::build( (int) $post->ID, $post );
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=pap_seo_fill&post=' . (int) $post->ID ), 'pap_seo_' . (int) $post->ID );
		echo '<p>Ključna riječ, SEO naslov i opis za Google slažu se sami iz naziva, datuma, trajanja, cijene i opisa izleta. Prazna polja se popune sama; ono što ste ručno promijenili ostaje.</p>';
		echo '<table class="widefat striped" style="max-width:900px"><tbody>'
			. '<tr><th style="width:150px">Ključna riječ</th><td>' . esc_html( $new['rank_math_focus_keyword'] ) . '</td></tr>'
			. '<tr><th>SEO naslov</th><td>' . esc_html( $new['rank_math_title'] ) . '</td></tr>'
			. '<tr><th>Opis za Google</th><td>' . esc_html( $new['rank_math_description'] ) . '</td></tr></tbody></table>';
		echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '" onclick="return confirm(\'SEO polja Rank Matha (ključna riječ, naslov, opis) zamijenit će se ovime. Nespremljene promjene izleta neće se spremiti. Nastaviti?\')">Upiši ovo u SEO (Rank Math)</a> '
			. '<span class="description">Prepiše i ručno upisano. Nakon toga u Rank Mathu možete i dalje mijenjati.</span></p>';
		if ( isset( $_GET['pap_seo'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<p style="color:#1e7d3a;font-weight:600">✓ SEO je upisan.</p>';
		}
	}

	/** Gumb „Upiši ovo u SEO”: složi ispočetka i prepiše polja Rank Matha. */
	public static function force() {
		$id   = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post = $id ? get_post( $id ) : null;
		if ( ! $post || ! current_user_can( 'edit_post', $id ) || ! wp_verify_nonce( (string) ( $_GET['_wpnonce'] ?? '' ), 'pap_seo_' . $id ) ) {
			wp_die( 'Nije dopušteno.' );
		}
		self::fill( $id, $post, true );
		wp_safe_redirect( add_query_arg( 'pap_seo', 1, get_edit_post_link( $id, 'url' ) ) . '#ostalo' );
		exit;
	}

	private static function active(): bool {
		return defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' );
	}

	public static function fill( $id, $post, bool $force = false ) {
		if ( ! self::active() || ! $post || Plan_A_Prijave_Tour::CPT !== $post->post_type ) {
			return;
		}
		if ( wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) || in_array( $post->post_status, array( 'auto-draft', 'trash', 'inherit' ), true ) ) {
			return;
		}
		$id   = (int) $id;
		$new  = self::build( $id, $post );
		$auto = get_post_meta( $id, self::AUTO, true );
		$auto = is_array( $auto ) ? $auto : array();
		foreach ( array( 'rank_math_focus_keyword', 'rank_math_title', 'rank_math_description' ) as $k ) {
			$cur = (string) get_post_meta( $id, $k, true );
			if ( '' === $new[ $k ] || ( ! $force && '' !== $cur && $cur !== ( $auto[ $k ] ?? null ) ) ) {
				continue; // ručno upisano – ostaje
			}
			update_post_meta( $id, $k, $new[ $k ] );
			$auto[ $k ] = $new[ $k ];
		}
		update_post_meta( $id, self::AUTO, $auto );

		// Glavna slika bez opisa (alt): naziv izleta, u kojem je i ključna riječ.
		$img = (int) get_post_thumbnail_id( $id );
		if ( $img && '' === trim( (string) get_post_meta( $img, '_wp_attachment_image_alt', true ) ) ) {
			update_post_meta( $img, '_wp_attachment_image_alt', self::clean( $post->post_title ) );
		}
	}

	/* ------------------------------------------------------------------ */

	private static function clean( string $s ): string {
		$s = html_entity_decode( wp_strip_all_tags( $s ), ENT_QUOTES, 'UTF-8' );
		$s = preg_replace( '/\s+([.,!?;:])/u', '$1', preg_replace( '/\s+/u', ' ', $s ) );
		return trim( $s );
	}

	private static function len( string $s ): int {
		return mb_strlen( $s );
	}

	/** Skrati na granici riječi. */
	private static function cut( string $s, int $max ): string {
		if ( self::len( $s ) <= $max ) {
			return $s;
		}
		$s = mb_substr( $s, 0, $max - 1 );
		$s = preg_replace( '/[\s,;:–-]+\S*$/u', '', $s );
		return rtrim( $s, ' ,;:–-' ) . '…';
	}

	/** „Hvar - Sunce, more i klinovi” → „hvar”; „Ferrata za početnike 18” → „ferrata za početnike”. */
	public static function keyword( string $title ): string {
		$t     = self::clean( $title );
		$parts = preg_split( '/\s+[–—-]\s+|:\s+|\s*\|\s*|,\s+/u', $t );
		$k     = trim( (string) ( $parts[0] ?? $t ) );
		$k     = preg_replace( '/\s+(\d+\.?|[IVXLC]+\.?|\d{4}\.?)$/u', '', $k ); // redni broj na kraju
		$k     = trim( preg_replace( '/[„“”"\'()]/u', '', $k ) );
		return mb_strtolower( $k );
	}

	private static function duration( int $id ): string {
		$n = (float) str_replace( ',', '.', (string) Plan_A_Prijave_Tour::meta( $id, 'ttbm_travel_duration' ) );
		if ( $n <= 0 ) {
			return '';
		}
		$num  = 0.0 === fmod( $n, 1.0 ) ? (string) (int) $n : str_replace( '.', ',', (string) $n );
		$type = (string) Plan_A_Prijave_Tour::meta( $id, 'ttbm_travel_duration_type', 'day' );
		if ( 'hour' === $type ) {
			return $num . ' h';
		}
		if ( 'min' === $type ) {
			return $num . ' min';
		}
		$i = (int) $n;
		return $num . ( 1 === $i % 10 && 11 !== $i % 100 ? ' dan' : ' dana' );
	}

	/** Prvi budući termin (ili zadnji, ako su svi prošli), npr. „21.11.2026.” */
	private static function date( int $id ): string {
		$all = Plan_A_Prijave_Tour::dates( $id );
		if ( ! $all ) {
			return '';
		}
		$today = current_time( 'Y-m-d' );
		$next  = '';
		foreach ( $all as $d ) {
			if ( $d >= $today ) {
				$next = $d;
				break;
			}
		}
		$d = $next ?: end( $all );
		return gmdate( 'j.n.Y.', strtotime( $d ) );
	}

	/** Najniža puna cijena (bez karte za prvu ratu). */
	private static function price( int $id ): string {
		$full = array();
		$any  = array();
		foreach ( Plan_A_Prijave_Tour::rows( $id, 'ttbm_ticket_type' ) as $t ) {
			$p = (float) ( $t['ticket_type_price'] ?? ( $t['ticket_price'] ?? 0 ) );
			if ( $p <= 0 ) {
				continue;
			}
			$any[] = $p;
			if ( false === mb_stripos( (string) ( $t['ticket_type_name'] ?? '' ), 'prve rate' ) ) {
				$full[] = $p;
			}
		}
		$list = $full ?: $any;
		if ( ! $list ) {
			return '';
		}
		$p = min( $list );
		return ( 0.0 === fmod( $p, 1.0 ) ? (string) (int) $p : number_format( $p, 2, ',', '.' ) ) . ' €';
	}

	/** Uvodna rečenica iz sažetka ili opisa (preskače naslov, cijenu, termin i slične retke). */
	private static function lead( WP_Post $post ): string {
		$title = mb_strtolower( self::clean( $post->post_title ) );
		$src   = '' !== trim( (string) $post->post_excerpt ) ? (string) $post->post_excerpt : (string) $post->post_content;
		$src   = strip_shortcodes( $src );
		$src   = preg_replace( '#<(br|/p|/h[1-6]|/li|/div)\b[^>]*>#i', "\n", $src );
		$skip  = '/^(cijena|uvjet|termin|mjesto|polazak|vodi[čc]|uplata|prijav|napomena|važno|vazno|radi |oprema|kontakt|i |a |\\(|[\\d.]+\\s*(€|eur))/iu';
		foreach ( preg_split( '/\n+/', $src ) as $line ) {
			$line = self::clean( $line );
			if ( self::len( $line ) < 80 || mb_strtolower( $line ) === $title || preg_match( $skip, $line )
				|| preg_match( '/\\beura?\\b|€|\\d{3}\\s?\\d{2,3}\\s?\\d{3}|tel:/iu', $line ) // cijene i telefoni
				|| substr_count( $line, '(' ) > 1 || ':' === mb_substr( $line, -1 ) // popisi vrhova, uvod u popis
				|| ! preg_match( '/[.!?]/u', $line ) ) {
				continue;
			}
			return $line;
		}
		return '';
	}

	public static function build( int $id, WP_Post $post ): array {
		$title = self::clean( $post->post_title );
		$kw    = self::keyword( $title );
		$dur   = self::duration( $id );
		$date  = self::date( $id );
		$price = self::price( $id );

		// Dodatne ključne riječi: aktivnosti izleta (Rank Math prima više, odvojenih zarezom).
		$words = array( $kw );
		foreach ( array_map( 'absint', (array) Plan_A_Prijave_Tour::meta( $id, 'ttbm_tour_activities', array() ) ) as $t ) {
			$term = $t ? get_term( $t, 'ttbm_tour_activities' ) : null;
			if ( $term && ! is_wp_error( $term ) && count( $words ) < 3 ) {
				$words[] = mb_strtolower( $term->name );
			}
		}
		$words = array_values( array_unique( array_filter( $words ) ) );

		// SEO naslov: ključna riječ na početku (naziv izleta), broj (trajanje, datum), do 60 znakova.
		$sep   = ' – ';
		$tail  = trim( implode( ', ', array_filter( array( $dur, $date ) ) ) );
		$cands = array();
		if ( '' !== $tail ) {
			$cands[] = $title . $sep . $tail . ' %sep% %sitename%';
			$cands[] = $title . $sep . $tail;
		}
		if ( '' !== $date ) {
			$cands[] = $title . $sep . $date;
		}
		$cands[] = $title . ' %sep% %sitename%';
		$cands[] = $title;
		$site    = self::clean( get_bloginfo( 'name' ) );
		$seo     = $title;
		foreach ( $cands as $c ) {
			$real = str_replace( array( ' %sep% ', '%sitename%' ), array( ' - ', $site ), $c );
			if ( self::len( $real ) <= self::TITLE_MAX ) {
				$seo = $c;
				break;
			}
		}

		// Opis za Google: uvod iz teksta + termin, trajanje, cijena; ključna riječ obavezno.
		$facts = array();
		if ( $date ) {
			$facts[] = 'Termin ' . $date;
		}
		if ( $dur ) {
			$facts[] = $dur;
		}
		if ( $price ) {
			$facts[] = 'od ' . $price;
		}
		$end  = $facts ? implode( ', ', $facts ) . '.' : '';
		$lead = self::lead( $post );
		if ( '' === $lead ) {
			$acts = array_slice( $words, 1 );
			$lead = $title . ': ' . ( $acts ? implode( ' i ', $acts ) . ' s udrugom Plan A.' : 'izlet s udrugom Plan A.' );
		} elseif ( false === mb_stripos( $lead, $kw ) ) {
			$lead = $title . ': ' . $lead;
		}
		$room = self::DESC_MAX - self::len( $end ) - ( '' !== $end ? 1 : 0 );
		if ( $room < 40 ) {
			$room = self::DESC_MAX;
			$end  = '';
		}
		// Cijele rečenice dok stanu; ako ni prva ne stane, skraćena prva.
		$desc = '';
		foreach ( preg_split( '/(?<=[.!?])\\s+(?=[A-ZČĆŠĐŽ„"])/u', $lead ) as $sent ) {
			if ( self::len( trim( $desc . ' ' . $sent ) ) > $room ) {
				break;
			}
			$desc = trim( $desc . ' ' . $sent );
		}
		if ( self::len( $desc ) < 50 ) {
			$desc = self::cut( $lead, $room );
		}
		if ( ! preg_match( '/[.!?…]$/u', $desc ) ) {
			$desc .= '.';
		}
		$desc = trim( $desc . ' ' . $end );
		if ( self::len( $desc ) + 16 <= self::DESC_MAX ) {
			$desc .= ' Prijave online.';
		}
		if ( false === mb_stripos( $desc, $kw ) ) {
			$desc = self::cut( $title . ': ' . $desc, self::DESC_MAX );
		}

		return array(
			'rank_math_focus_keyword' => implode( ',', $words ),
			'rank_math_title'         => $seo,
			'rank_math_description'   => $desc,
		);
	}
}
