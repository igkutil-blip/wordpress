<?php
/**
 * Pomoćne funkcije za tekst: čišćenje, skraćivanje, ključna riječ, uvodni odlomak.
 *
 * @package Plan_A_Seo
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Seo_Text {

	const TITLE_MAX = 60;
	const DESC_MAX  = 158;
	const DESC_MIN  = 70;

	/** Tekst koji je očito ostao iz predloška teme ili probe. */
	const JUNK = '/uxthemes|IPython|Take a look here|lorem ipsum|from\s+\w+\s+import|sample page|^proba$/iu';

	public static function clean( string $s ): string {
		$s = html_entity_decode( wp_strip_all_tags( $s ), ENT_QUOTES, 'UTF-8' );
		$s = str_replace( "\xC2\xA0", ' ', $s );
		return trim( (string) preg_replace( '/\s+/u', ' ', $s ) );
	}

	public static function len( string $s ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $s ) : strlen( $s );
	}

	/** Skrati na granici riječi (s „…”). */
	public static function cut( string $s, int $max ): string {
		if ( self::len( $s ) <= $max ) {
			return $s;
		}
		$s = mb_substr( $s, 0, $max - 1 );
		$s = preg_replace( '/[\s,;:–-]+\S*$/u', '', $s );
		return rtrim( (string) $s, ' ,;:–-' ) . '…';
	}

	/** Skrati na granici riječi, bez „…” i bez veznika na kraju (za naslove). */
	public static function cut_words( string $s, int $max ): string {
		if ( self::len( $s ) <= $max ) {
			return $s;
		}
		$s = mb_substr( $s, 0, $max + 1 );
		$s = preg_replace( '/\s+\S*$/u', '', $s );
		do {
			$before = $s;
			$s      = preg_replace( '/[\s,;:–-]+(i|u|na|za|od|do|s|sa|a|ili|kroz|po|o|te|kao)?$/iu', '', (string) $s );
		} while ( $s !== $before );
		return trim( (string) $s );
	}

	/** Prvi dio naslova (prije „ – ”, „ - ” ili „: ”), bez rednog broja. */
	public static function head( string $title ): string {
		$t = self::clean( $title );
		$h = preg_split( '/\s+[–—-]\s+|:\s+/u', $t )[0] ?? $t;
		return trim( (string) preg_replace( '/^\d+\.\s+/u', '', (string) $h ) );
	}

	/** Usporedba bez velikih slova, interpunkcije i završetka „- Plan A”. */
	public static function norm( string $s ): string {
		$s = mb_strtolower( self::clean( $s ) );
		$s = preg_replace( '/\s*[-–|]\s*plan a.*$/u', '', $s );
		return trim( (string) preg_replace( '/[^\p{L}\p{N}]+/u', ' ', (string) $s ) );
	}

	/** „Hvar - Sunce, more i klinovi” → „hvar”; „Ferrata za početnike 18” → „ferrata za početnike”. */
	public static function keyword( string $title ): string {
		$t     = self::clean( $title );
		$parts = preg_split( '/\s+[–—-]\s+|:\s+|\s*\|\s*|,\s+|\?\s*/u', $t );
		$k     = trim( (string) ( $parts[0] ?? $t ) );
		$k     = preg_replace( '/^\d+\.\s+/u', '', $k );                         // „3. Odabir opreme”
		$k     = preg_replace( '/\s+(\d+\.?|[IVXLC]+\.?|\d{4}\.?)$/u', '', $k ); // redni broj na kraju
		$k     = trim( (string) preg_replace( '/[„“”"\'()!]/u', '', (string) $k ) );
		return mb_strtolower( $k );
	}

	/** Kratkih kodova (Flatsome) se rješava, ali tekst unutar njih ostaje. */
	public static function strip_codes( string $html ): string {
		$html = preg_replace( '/\[\/?[a-zA-Z_][\w-]*(?:\s[^\]]*)?\]/u', "\n", $html );
		return (string) $html;
	}

	/** Odlomci teksta (bez naslova h1–h6, popisa vrhova i sl.). */
	public static function paragraphs( string $html ): array {
		$html = self::strip_codes( $html );
		$html = preg_replace( '#<h[1-6]\b[^>]*>.*?</h[1-6]>#is', "\n\n", $html );     // naslovi ne
		$html = preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', '', (string) $html );
		$html = preg_replace( '#<(br|/p|/li|/div|/blockquote|/td|/tr)\b[^>]*>#i', "\n\n", (string) $html );
		$out  = array();
		foreach ( preg_split( '/\n\s*\n|\r\n\s*\r\n/', (string) $html ) as $p ) {
			$p = self::clean( $p );
			if ( '' !== $p ) {
				$out[] = $p;
			}
		}
		return $out;
	}

	/** Je li odlomak dobar za opis: prava rečenica, ne cijena, telefon, popis ili ostatak predloška. */
	public static function good_lead( string $p, string $title ): bool {
		if ( self::len( $p ) < 60 || self::norm( $p ) === self::norm( $title ) || preg_match( self::JUNK, $p ) ) {
			return false;
		}
		if ( ! preg_match( '/^[\p{Lu}0-9„"]/u', $p ) || ! preg_match( '/[.!?]/u', $p ) || ':' === mb_substr( $p, -1 ) ) {
			return false;
		}
		if ( preg_match( '#https?://|www\.|\d{3}\s?\d{2,3}\s?\d{3}|tel:#iu', $p ) ) {
			return false;
		}
		return ! preg_match( '/^(cijena|uvjet|termin|polazak|uplata|prijav|napomena|kontakt|oprema|\d+[.)]?\s*$)/iu', $p );
	}

	/** Opis do $max znakova od cijelih rečenica (inače skraćena prva). */
	public static function sentences( string $text, int $max ): string {
		$desc = '';
		foreach ( preg_split( '/(?<=[.!?])\s+(?=[A-ZČĆŠĐŽ„"0-9])/u', $text ) as $s ) {
			if ( self::len( trim( $desc . ' ' . $s ) ) > $max ) {
				break;
			}
			$desc = trim( $desc . ' ' . $s );
		}
		if ( self::len( $desc ) < 50 ) {
			$desc = self::cut( $text, $max );
		}
		return $desc;
	}

	/** Uvod iz sažetka ili teksta (prvi dobar odlomak; ako je kratak, i sljedeći). */
	public static function lead( WP_Post $post ): string {
		$src = '' !== trim( (string) $post->post_excerpt ) ? (string) $post->post_excerpt . "\n\n" . (string) $post->post_content : (string) $post->post_content;
		$out = '';
		foreach ( self::paragraphs( $src ) as $p ) {
			if ( ! self::good_lead( $p, $post->post_title ) ) {
				continue;
			}
			$out = trim( $out . ' ' . $p );
			if ( self::len( $out ) >= self::DESC_MIN + 30 ) {
				break;
			}
		}
		return $out;
	}

	/** Naziv datoteke ili slike u čitljiv tekst: „Hvar-5.png” → „Hvar”, „IMG_1234” → ''. */
	public static function from_filename( string $name ): string {
		$name = preg_replace( '#https?:/*|www\.|[a-z0-9-]+\.(com|net|org|hr)\b|/#i', ' ', $name );
		$name = preg_replace( '/\.(jpe?g|png|gif|webp|avif|svg)$/i', '', (string) $name );
		$name = preg_replace( '/-?(scaled|rotated|e\d{10,}|\d+x\d+)$/i', '', (string) $name );
		$name = str_replace( array( '-', '_', '+' ), ' ', (string) $name );
		$name = preg_replace( '/\b(img|dsc|dscn|dji|pxl|photo|image|whatsapp image|screenshot|copy|kopija|final)\b/iu', ' ', (string) $name );
		$name = preg_replace( '/\b\d+\b/u', ' ', (string) $name ); // brojevi i datumi
		$name = preg_replace( '/\b(plan a|plan|planinarenje avantura|planinarenje i avantura|free|freepik|stock)\b/iu', ' ', (string) $name );
		// „…jedan dantri drzave…”: ponovljeni naziv (spojena dva imena datoteke).
		$w = preg_split( '/\s+/u', trim( (string) $name ) );
		if ( count( $w ) >= 6 ) {
			$half = (int) floor( count( $w ) / 2 );
			$name = implode( ' ', array_slice( $w, 0, $half ) );
		}
		$name = trim( (string) preg_replace( '/\s+/u', ' ', (string) $name ), " \t.,;:-–" );
		if ( preg_match( '/^(dummy|placeholder|untitled|bez naslova|woocommerce placeholder)$/iu', $name ) ) {
			return '';
		}
		if ( self::len( $name ) < 3 || ! preg_match( '/\p{L}{3,}/u', $name ) ) {
			return '';
		}
		return mb_strtoupper( mb_substr( $name, 0, 1 ) ) . mb_substr( $name, 1 );
	}
}
