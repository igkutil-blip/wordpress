<?php
/**
 * Čišćenje metapodataka iz slika (bez ponovnog sažimanja – pikseli i adrese ostaju isti).
 * JPEG: ostaju JFIF (APP0), profil boja ICC (APP2) i Adobe (APP14, boje CMYK); brišu se EXIF, XMP, C2PA/JUMBF (APP11), ostali APPn,
 *       komentari (npr. „CREATOR: gd-jpeg”) i sve što je dopisano iza kraja slike.
 *       Iznimka: EXIF s orijentacijom ≠ 1 ostaje (stare fotografije s mobitela bi se inače prikazale zarotirane).
 * PNG:  ostaju IHDR, PLTE, tRNS, IDAT, IEND; brišu se tekstovi (tEXt/iTXt/zTXt), eXIf, caBX (C2PA), pHYs, tIME i ostalo.
 *
 * @package Domestic_Slike
 */

defined( 'ABSPATH' ) || exit;

class Domestic_Slike_Meta {

	public static function init(): void {
		// svaka nova ili zamijenjena slika i sve njezine izvedenice (učitavanje u WP, uvoz paketa, zamjena na istoj adresi)
		add_filter( 'wp_generate_attachment_metadata', array( __CLASS__, 'nakon_izvedenica' ), 999, 2 );
	}

	public static function nakon_izvedenica( $meta, $id ) {
		foreach ( self::datoteke( (int) $id, is_array( $meta ) ? $meta : array() ) as $f ) {
			self::ocisti( $f );
		}
		return $meta;
	}

	/** Izvornik, „-scaled” i sve izvedenice priloga. */
	public static function datoteke( int $id, array $meta = array() ): array {
		$put = get_attached_file( $id );
		if ( ! $put ) {
			return array();
		}
		$meta = $meta ? $meta : (array) wp_get_attachment_metadata( $id );
		$dir  = trailingslashit( dirname( $put ) );
		$out  = array( $put );
		if ( ! empty( $meta['original_image'] ) ) {
			$out[] = $dir . $meta['original_image'];
		}
		foreach ( (array) ( $meta['sizes'] ?? array() ) as $s ) {
			if ( ! empty( $s['file'] ) ) {
				$out[] = $dir . $s['file'];
			}
		}
		return array_values( array_unique( array_filter( $out, 'file_exists' ) ) );
	}

	/** @return bool true ako je datoteka promijenjena */
	public static function ocisti( string $put ): bool {
		$b = @file_get_contents( $put ); // phpcs:ignore
		if ( false === $b || strlen( $b ) < 12 ) {
			return false;
		}
		if ( "\xFF\xD8" === substr( $b, 0, 2 ) ) {
			$n = self::jpeg( $b );
		} elseif ( "\x89PNG\r\n\x1a\n" === substr( $b, 0, 8 ) ) {
			$n = self::png( $b );
		} else {
			return false;
		}
		if ( null === $n || $n === $b ) {
			return false;
		}
		return false !== file_put_contents( $put, $n ); // phpcs:ignore
	}

	private static function jpeg( string $b ): ?string {
		$out = "\xFF\xD8";
		$i   = 2;
		$len = strlen( $b );
		while ( $i + 4 <= $len ) {
			if ( "\xFF" !== $b[ $i ] ) {
				return null; // neočekivano – ne diramo
			}
			$m = ord( $b[ $i + 1 ] );
			if ( 0xFF === $m ) { // popuna
				$i++;
				continue;
			}
			if ( 0xDA === $m || 0xD9 === $m ) { // početak slike – ostatak se kopira bez promjene, do zadnjeg kraja slike (FFD9)
				$kraj = strrpos( $b, "\xFF\xD9" );
				return $out . substr( $b, $i, false !== $kraj && $kraj >= $i ? $kraj + 2 - $i : null );
			}
			$l   = unpack( 'n', substr( $b, $i + 2, 2 ) )[1];
			$seg = substr( $b, $i, 2 + $l );
			$i  += 2 + $l;
			$app = $m >= 0xE0 && $m <= 0xEF;
			if ( 0xFE === $m ) {
				continue; // komentar
			}
			if ( 0xE2 === $m && 0 === strpos( substr( $seg, 4 ), "ICC_PROFILE\0" ) || 0xEE === $m && 0 === strpos( substr( $seg, 4 ), 'Adobe' ) ) {
				$out .= $seg; // boje
				continue;
			}
			if ( $app && 0xE0 !== $m ) {
				if ( 0xE1 === $m && 0 === strpos( substr( $seg, 4 ), "Exif\0\0" ) && self::orijentacija( substr( $seg, 10 ) ) > 1 ) {
					$out .= $seg; // zakrenuta fotografija – EXIF ostaje
				}
				continue;
			}
			$out .= $seg;
		}
		return null;
	}

	/** Orijentacija iz EXIF-a (TIFF IFD0, oznaka 0x0112), 1 ako je nema. */
	private static function orijentacija( string $t ): int {
		if ( strlen( $t ) < 8 ) {
			return 1;
		}
		$le  = 'II' === substr( $t, 0, 2 );
		$u16 = static function ( $o ) use ( $t, $le ) { return unpack( $le ? 'v' : 'n', substr( $t, $o, 2 ) )[1]; };
		$u32 = static function ( $o ) use ( $t, $le ) { return unpack( $le ? 'V' : 'N', substr( $t, $o, 4 ) )[1]; };
		$ifd = $u32( 4 );
		if ( $ifd + 2 > strlen( $t ) ) {
			return 1;
		}
		$n = $u16( $ifd );
		for ( $k = 0; $k < $n; $k++ ) {
			$e = $ifd + 2 + 12 * $k;
			if ( $e + 12 > strlen( $t ) ) {
				break;
			}
			if ( 0x0112 === $u16( $e ) ) {
				return (int) $u16( $e + 8 );
			}
		}
		return 1;
	}

	private static function png( string $b ): ?string {
		$out = substr( $b, 0, 8 );
		$i   = 8;
		$len = strlen( $b );
		$ok  = array( 'IHDR', 'PLTE', 'tRNS', 'IDAT', 'IEND' );
		while ( $i + 12 <= $len ) {
			$l = unpack( 'N', substr( $b, $i, 4 ) )[1];
			$t = substr( $b, $i + 4, 4 );
			if ( $i + 12 + $l > $len ) {
				return null;
			}
			if ( in_array( $t, $ok, true ) ) {
				$out .= substr( $b, $i, 12 + $l );
			}
			$i += 12 + $l;
			if ( 'IEND' === $t ) {
				return $out;
			}
		}
		return null;
	}

	/** REST: POST ocisti {stranica, po} – prolaz kroz sve slike u biblioteci, po dijelovima. */
	public static function rest_ocisti( WP_REST_Request $r ): array {
		$po  = max( 1, min( 100, (int) ( $r['po'] ?? 40 ) ) );
		$str = max( 1, (int) ( $r['stranica'] ?? 1 ) );
		$q   = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => array( 'image/jpeg', 'image/png' ),
				'posts_per_page' => $po,
				'paged'          => $str,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
		$dat = 0;
		$pro = 0;
		foreach ( $q->posts as $id ) {
			foreach ( self::datoteke( (int) $id ) as $f ) {
				$dat++;
				$pro += self::ocisti( $f ) ? 1 : 0;
			}
		}
		return array( 'stranica' => $str, 'slika' => count( $q->posts ), 'datoteka' => $dat, 'ocisceno' => $pro, 'ukupno_stranica' => (int) $q->max_num_pages );
	}
}
