<?php
/**
 * Popis sudionika za vodiča kao PDF (A4, za print). Stranice se crtaju GD-om s fontom Lato
 * (hrvatska slova) i spremaju kao slike u najjednostavniji PDF, bez vanjskih biblioteka.
 *
 * @package Plan_A_Clanstvo
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Clanstvo_Pdf {

	const W      = 1240; // A4 pri 150 dpi
	const H      = 1754;
	const PDF_W  = 595.28; // A4 u točkama
	const PDF_H  = 841.89;
	const MARGIN = 80;

	public static function available(): bool {
		return function_exists( 'imagecreatetruecolor' ) && function_exists( 'imagettftext' ) && function_exists( 'imagejpeg' ) && file_exists( self::font( false ) );
	}

	private static function font( bool $bold ): string {
		return PLAN_A_CLANSTVO_DIR . 'assets/fonts/Lato-' . ( $bold ? 'Bold' : 'Regular' ) . '.ttf';
	}

	/**
	 * $cols: [[naslov, širina u px, poravnanje 'left'|'center'], …]; $rows: [[ćelije…], …];
	 * $gone: otkazani (precrtani, na dnu). Vraća sadržaj PDF-a ili '' ako GD nije dostupan.
	 */
	public static function table( string $title, string $subtitle, array $cols, array $rows, array $gone = array(), string $foot = '' ): string {
		if ( ! self::available() ) {
			return '';
		}
		$pages = array();
		$all   = array_merge(
			array_map( static fn( $r ) => array( 'r' => $r, 'x' => false ), $rows ),
			$gone ? array( array( 'h' => 'Otkazali' ) ) : array(),
			array_map( static fn( $r ) => array( 'r' => $r, 'x' => true ), $gone )
		);
		$i = 0;
		do {
			$im   = imagecreatetruecolor( self::W, self::H );
			$c    = array(
				'bg'    => imagecolorallocate( $im, 255, 255, 255 ),
				'navy'  => imagecolorallocate( $im, 18, 48, 75 ),
				'text'  => imagecolorallocate( $im, 36, 50, 63 ),
				'muted' => imagecolorallocate( $im, 110, 120, 130 ),
				'line'  => imagecolorallocate( $im, 215, 222, 229 ),
				'head'  => imagecolorallocate( $im, 232, 238, 244 ),
				'zebra' => imagecolorallocate( $im, 247, 249, 251 ),
			);
			imagefilledrectangle( $im, 0, 0, self::W, self::H, $c['bg'] );
			$y = self::MARGIN;
			if ( ! $pages ) {
				$y = self::text( $im, 40, self::MARGIN, $y, $c['navy'], true, $title, self::W - 2 * self::MARGIN ) + 14;
				if ( '' !== $subtitle ) {
					$y = self::text( $im, 24, self::MARGIN, $y, $c['muted'], false, $subtitle, self::W - 2 * self::MARGIN ) + 24;
				}
			}
			// Nazivi stupaca.
			imagefilledrectangle( $im, self::MARGIN, $y, self::W - self::MARGIN, $y + 44, $c['head'] );
			$x = self::MARGIN;
			foreach ( $cols as $col ) {
				self::cell( $im, 21, $x, $y + 11, (int) $col[1], $c['navy'], true, (string) $col[0], $col[2] ?? 'left' );
				$x += (int) $col[1];
			}
			$y += 44;
			$n = 0;
			for ( ; $i < count( $all ) && $y + 46 < self::H - self::MARGIN - 40; $i++ ) {
				$it = $all[ $i ];
				if ( isset( $it['h'] ) ) {
					$y = self::text( $im, 24, self::MARGIN, $y + 22, $c['navy'], true, $it['h'], self::W - 2 * self::MARGIN ) + 10;
					continue;
				}
				if ( 0 === $n % 2 ) {
					imagefilledrectangle( $im, self::MARGIN, $y, self::W - self::MARGIN, $y + 46, $c['zebra'] );
				}
				$x = self::MARGIN;
				foreach ( $cols as $k => $col ) {
					$w = self::cell( $im, 22, $x, $y + 12, (int) $col[1], $it['x'] ? $c['muted'] : $c['text'], 0 === $k ? false : 1 === $k, (string) ( $it['r'][ $k ] ?? '' ), $col[2] ?? 'left' );
					if ( $it['x'] && 1 === $k && $w > 0 ) {
						imagefilledrectangle( $im, $x + 8, $y + 24, $x + 8 + $w, $y + 25, $c['muted'] );
					}
					$x += (int) $col[1];
				}
				imageline( $im, self::MARGIN, $y + 46, self::W - self::MARGIN, $y + 46, $c['line'] );
				$y += 46;
				++$n;
			}
			$pages[] = $im;
			if ( $i >= count( $all ) && '' !== $foot ) {
				self::text( $im, 20, self::MARGIN, min( $y + 30, self::H - self::MARGIN - 30 ), $c['muted'], false, $foot, self::W - 2 * self::MARGIN );
			}
		} while ( $i < count( $all ) && count( $pages ) < 20 );

		$jpegs = array();
		foreach ( $pages as $p => $im ) {
			if ( count( $pages ) > 1 ) {
				$c = imagecolorallocate( $im, 110, 120, 130 );
				self::cell( $im, 18, self::W - self::MARGIN - 200, self::H - self::MARGIN + 10, 200, $c, false, ( $p + 1 ) . ' / ' . count( $pages ), 'right' );
			}
			ob_start();
			imagejpeg( $im, null, 88 );
			$jpegs[] = (string) ob_get_clean();
			imagedestroy( $im );
		}
		return self::pdf( $jpegs, $title );
	}

	private static function pt( int $px ): float {
		return $px * 0.75;
	}

	private static function width( int $px, bool $bold, string $text ): int {
		$box = imagettfbbox( self::pt( $px ), 0, self::font( $bold ), $text );
		return (int) ( $box[2] - $box[0] );
	}

	/** Tekst u ćeliji (skraćen da stane); vraća širinu ispisanog teksta. */
	private static function cell( $im, int $px, int $x, int $y, int $w, int $color, bool $bold, string $text, string $align ): int {
		$max = $w - 16;
		while ( '' !== $text && self::width( $px, $bold, $text ) > $max ) {
			$text = rtrim( mb_substr( $text, 0, mb_strlen( $text ) - 2 ) ) . '…';
		}
		if ( '' === $text ) {
			return 0;
		}
		$tw = self::width( $px, $bold, $text );
		$tx = 'center' === $align ? $x + (int) ( ( $w - $tw ) / 2 ) : ( 'right' === $align ? $x + $w - 8 - $tw : $x + 8 );
		imagettftext( $im, self::pt( $px ), 0, $tx, $y + $px, $color, self::font( $bold ), $text );
		return $tw;
	}

	/** Odlomak s prelamanjem; vraća donji rub. */
	private static function text( $im, int $px, int $x, int $y, int $color, bool $bold, string $text, int $w ): int {
		$line = '';
		foreach ( preg_split( '/\s+/u', trim( $text ) ) as $word ) {
			$try = '' === $line ? $word : $line . ' ' . $word;
			if ( '' !== $line && self::width( $px, $bold, $try ) > $w ) {
				imagettftext( $im, self::pt( $px ), 0, $x, $y + $px, $color, self::font( $bold ), $line );
				$y   += (int) ( $px * 1.4 );
				$line = $word;
			} else {
				$line = $try;
			}
		}
		if ( '' !== $line ) {
			imagettftext( $im, self::pt( $px ), 0, $x, $y + $px, $color, self::font( $bold ), $line );
			$y += (int) ( $px * 1.4 );
		}
		return $y;
	}

	/** PDF 1.4: svaka stranica jedna JPEG slika preko cijele A4 stranice. */
	private static function pdf( array $jpegs, string $title ): string {
		$title   = preg_replace( '/[^A-Za-z0-9 .\-]/', '', remove_accents( $title ) );
		$objects = array( 1 => '<< /Type /Catalog /Pages 2 0 R >>' );
		$kids    = array();
		$n       = 3;
		$pages   = array();
		foreach ( $jpegs as $jpeg ) {
			$page    = $n;
			$img     = $n + 1;
			$content = $n + 2;
			$kids[]  = $page . ' 0 R';
			$draw    = sprintf( 'q %.2F 0 0 %.2F 0 0 cm /Im1 Do Q', self::PDF_W, self::PDF_H );
			$pages[ $page ]    = sprintf( '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /XObject << /Im1 %d 0 R >> >> /Contents %d 0 R >>', self::PDF_W, self::PDF_H, $img, $content );
			$pages[ $img ]     = '<< /Type /XObject /Subtype /Image /Width ' . self::W . ' /Height ' . self::H . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen( $jpeg ) . " >>\nstream\n" . $jpeg . "\nendstream";
			$pages[ $content ] = '<< /Length ' . strlen( $draw ) . " >>\nstream\n" . $draw . "\nendstream";
			$n += 3;
		}
		$objects[2] = '<< /Type /Pages /Kids [' . implode( ' ', $kids ) . '] /Count ' . count( $kids ) . ' >>';
		$objects   += $pages;
		$objects[ $n ] = '<< /Title (' . $title . ') /Producer (Plan A clanstvo) /CreationDate (D:' . gmdate( 'YmdHis' ) . 'Z) >>';
		ksort( $objects );
		$out     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array();
		foreach ( $objects as $i => $body ) {
			$offsets[ $i ] = strlen( $out );
			$out          .= $i . " 0 obj\n" . $body . "\nendobj\n";
		}
		$xref = strlen( $out );
		$out .= "xref\n0 " . ( count( $objects ) + 1 ) . "\n0000000000 65535 f \n";
		foreach ( $offsets as $offset ) {
			$out .= sprintf( "%010d 00000 n \n", $offset );
		}
		return $out . "trailer\n<< /Size " . ( count( $objects ) + 1 ) . ' /Root 1 0 R /Info ' . $n . " 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
	}
}
