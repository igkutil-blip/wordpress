<?php
/**
 * Slika bona (A5 položeno, 2480 × 1748 px = 300 dpi) iscrtana GD-om s ugrađenim fontom Lato,
 * spremljena kao PDF (jedna stranica A5, slika ugrađena kao JPEG) i PNG za slanje porukom.
 * QR kod izrađuje uključena biblioteka chillerlan/php-qrcode; ništa se ne šalje vanjskim servisima.
 *
 * @package Plan_A_Poklon_Bon
 */

defined( 'ABSPATH' ) || exit;

use PlanAPoklonBon\Vendor\chillerlan\QRCode\QRCode;
use PlanAPoklonBon\Vendor\chillerlan\QRCode\QROptions;

class Plan_A_Bon_Render {

	const W      = 2480;
	const H      = 1748;
	const MARGIN = 130; // > 10 mm (118 px pri 300 dpi)
	const PNG_W  = 1600;

	/** A5 položeno u točkama (1 pt = 1/72 inča). */
	const PDF_W = 595.28;
	const PDF_H = 419.53;

	/**
	 * Izrađuje $base.pdf i $base.png.
	 */
	public static function files( array $v, string $base ): bool {
		if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagettftext' ) ) {
			return false;
		}
		wp_raise_memory_limit( 'image' );
		$im = self::draw( $v );
		if ( ! $im ) {
			return false;
		}

		// PNG (manji, za WhatsApp).
		$ph  = (int) round( self::H * self::PNG_W / self::W );
		$png = imagecreatetruecolor( self::PNG_W, $ph );
		imagecopyresampled( $png, $im, 0, 0, 0, 0, self::PNG_W, $ph, self::W, self::H );
		imagepng( $png, $base . '.png', 6 );
		imagedestroy( $png );

		// PDF s ugrađenom JPEG slikom pune razlučivosti.
		ob_start();
		imagejpeg( $im, null, 90 );
		$jpeg = (string) ob_get_clean();
		imagedestroy( $im );
		file_put_contents( $base . '.pdf', self::pdf( $jpeg, self::W, self::H, 'Poklon bon ' . $v['code'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return true;
	}

	/**
	 * Najjednostavniji ispravan PDF 1.4: jedna stranica A5 položeno s jednom slikom preko cijele stranice.
	 */
	public static function pdf( string $jpeg, int $w, int $h, string $title ): string {
		$title   = preg_replace( '/[^A-Za-z0-9 \-]/', '', $title );
		$content = sprintf( 'q %.2F 0 0 %.2F 0 0 cm /Im1 Do Q', self::PDF_W, self::PDF_H );
		$objects = array(
			1 => '<< /Type /Catalog /Pages 2 0 R >>',
			2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
			3 => sprintf( '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /XObject << /Im1 4 0 R >> >> /Contents 5 0 R >>', self::PDF_W, self::PDF_H ),
			4 => "<< /Type /XObject /Subtype /Image /Width $w /Height $h /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen( $jpeg ) . " >>\nstream\n" . $jpeg . "\nendstream",
			5 => '<< /Length ' . strlen( $content ) . " >>\nstream\n" . $content . "\nendstream",
			6 => '<< /Title (' . $title . ') /Producer (Plan A poklon bon) /CreationDate (D:' . gmdate( 'YmdHis' ) . 'Z) >>',
		);
		$out     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array();
		foreach ( $objects as $n => $body ) {
			$offsets[ $n ] = strlen( $out );
			$out          .= $n . " 0 obj\n" . $body . "\nendobj\n";
		}
		$xref = strlen( $out );
		$out .= 'xref' . "\n0 " . ( count( $objects ) + 1 ) . "\n0000000000 65535 f \n";
		foreach ( $offsets as $offset ) {
			$out .= sprintf( "%010d 00000 n \n", $offset );
		}
		$out .= 'trailer' . "\n<< /Size " . ( count( $objects ) + 1 ) . " /Root 1 0 R /Info 6 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
		return $out;
	}

	/**
	 * Raspored (px na 2480 × 1748; margina 130 px ≈ 11 mm):
	 * - lijevo fotografija 960 px široka, zaobljena, s logotipom u bijeloj kartici,
	 * - desno: "Poklon bon", iznos, Za / Od / poruka, kod u tamnom okviru i "Vrijedi do",
	 * - dolje desno upute "Kako iskoristiti" i QR kod, ispod sitno tekst izdavatelja.
	 *
	 * @return \GdImage|resource|false
	 */
	public static function draw( array $v ) {
		$w  = self::W;
		$h  = self::H;
		$m  = self::MARGIN;
		$im = imagecreatetruecolor( $w, $h );
		if ( ! $im ) {
			return false;
		}
		imagealphablending( $im, true );
		imageantialias( $im, true );

		$c = array(
			'bg'     => self::color( $im, '#ffffff' ),
			'navy'   => self::color( $im, '#12304b' ),
			'accent' => self::color( $im, '#1a9ad6' ),
			'orange' => self::color( $im, '#e8862a' ),
			'text'   => self::color( $im, '#24323f' ),
			'muted'  => self::color( $im, '#5f6b77' ),
			'line'   => self::color( $im, '#e3e8ee' ),
			'soft'   => self::color( $im, '#e8f4fb' ),
			'white'  => self::color( $im, '#ffffff' ),
			'pale'   => self::color( $im, '#9fd3f0' ),
		);
		imagefill( $im, 0, 0, $c['bg'] );

		$f = array(
			'black'   => PLAN_A_BON_DIR . 'assets/fonts/Lato-Black.ttf',
			'bold'    => PLAN_A_BON_DIR . 'assets/fonts/Lato-Bold.ttf',
			'regular' => PLAN_A_BON_DIR . 'assets/fonts/Lato-Regular.ttf',
			'italic'  => PLAN_A_BON_DIR . 'assets/fonts/Lato-Italic.ttf',
		);

		// --- Fotografija -------------------------------------------------------
		$pw    = 960;
		$photo = self::load( (int) Plan_A_Bon_Settings::get( 'photo' ), 2048 );
		if ( $photo ) {
			self::cover( $im, $photo, $m, $m, $pw, $h - 2 * $m );
			imagedestroy( $photo );
		} else {
			imagefilledrectangle( $im, $m, $m, $m + $pw - 1, $h - $m - 1, $c['navy'] );
			// Jednostavni obrisi planina kad fotografija nije odabrana.
			$hill = self::color( $im, '#1d4468' );
			imagefilledpolygon( $im, array( $m, $h - $m - 1, $m, $h - $m - 520, $m + 300, $h - $m - 860, $m + 560, $h - $m - 560, $m + 760, $h - $m - 760, $m + $pw - 1, $h - $m - 420, $m + $pw - 1, $h - $m - 1 ), $hill );
		}
		self::round_corners( $im, $m, $m, $pw, $h - 2 * $m, 46, array( 255, 255, 255 ) );

		// Logotip u bijeloj kartici na fotografiji.
		$logo = self::load( self::logo_id(), 800 );
		$lx   = $m + 50;
		$ly   = $m + 50;
		if ( $logo ) {
			$lh = 130;
			$lw = (int) round( imagesx( $logo ) * $lh / max( 1, imagesy( $logo ) ) );
			if ( $lw > $pw - 160 ) {
				$lw = $pw - 160;
				$lh = (int) round( imagesy( $logo ) * $lw / max( 1, imagesx( $logo ) ) );
			}
			self::rounded( $im, $lx, $ly, $lx + $lw + 64, $ly + $lh + 48, 26, $c['white'] );
			imagecopyresampled( $im, $logo, $lx + 32, $ly + 24, 0, 0, $lw, $lh, imagesx( $logo ), imagesy( $logo ) );
			imagedestroy( $logo );
		} else {
			self::rounded( $im, $lx, $ly, $lx + 330, $ly + 130, 26, $c['white'] );
			self::text( $im, 62, $lx + 165, $ly + 65, $c['navy'], $f['black'], 'Plan A', 'center' );
		}

		// --- Desni stupac ------------------------------------------------------
		$x0 = $m + $pw + 120;
		$x1 = $w - $m;
		$cw = $x1 - $x0;

		// Gornji dio (najviše do ~1120 px i kad poruka ima tri reda).
		$y = $m;
		$y = self::text( $im, 120, $x0, $y, $c['navy'], $f['black'], 'Poklon bon' );
		$y = self::text( $im, 220, $x0, $y + 10, $c['orange'], $f['black'], Plan_A_Bon_Voucher::money_short( (float) $v['amount'] ) );

		$y += 30;
		imagefilledrectangle( $im, $x0, $y, $x1, $y + 3, $c['line'] );
		$y += 40;

		$y = self::label_value( $im, $x0, $y, $cw, 'Za: ', (string) $v['to'], 60, $f, $c ) + 14;
		$y = self::label_value( $im, $x0, $y, $cw, 'Od: ', (string) $v['from'], 50, $f, $c );
		if ( '' !== trim( (string) $v['message'] ) ) {
			$y += 20;
			foreach ( self::wrap( '„' . trim( (string) $v['message'] ) . '“', $f['italic'], 40, $cw, 3 ) as $line ) {
				$y = self::text( $im, 40, $x0, $y, $c['text'], $f['italic'], $line ) + 10;
			}
		}

		// Kod u tamnom okviru.
		$y += 36;
		$bh = 170;
		self::rounded( $im, $x0, $y, $x1, $y + $bh, 26, $c['navy'] );
		self::text( $im, 30, $x0 + 44, $y + 28, $c['pale'], $f['bold'], 'KOD BONA', 'left', 6 );
		self::text( $im, 80, $x0 + 44, $y + 68, $c['white'], $f['black'], (string) $v['code'], 'left', 5 );
		$y += $bh + 24;
		self::text( $im, 42, $x0, $y, $c['text'], $f['bold'], 'Vrijedi do: ' . Plan_A_Bon_Voucher::hr_date( (int) $v['expires'] ) );

		// --- Dno (odozdo prema gore): izdavatelj, upute i QR kod ---------------------
		$issuer = self::wrap( (string) Plan_A_Bon_Settings::get( 'issuer' ), $f['regular'], 24, $cw, 3 );
		$fy     = $h - $m - count( $issuer ) * 32;
		foreach ( $issuer as $i => $line ) {
			self::text( $im, 24, $x0, $fy + $i * 32, $c['muted'], $f['regular'], $line );
		}

		$qs = 280;
		$sw = $cw - $qs - 50;
		$host  = preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$lines = array( array( 'Kako iskoristiti:', $f['bold'], $c['navy'] ) );
		foreach ( array( '1. Odaberi izlet na ' . $host, '2. U košarici upiši kod', '3. Iznos bona se oduzima od cijene.' ) as $step ) {
			foreach ( self::wrap( $step, $f['regular'], 34, $sw, 2 ) as $line ) {
				$lines[] = array( $line, $f['regular'], $c['text'] );
			}
		}
		foreach ( self::wrap( 'Ili nam se javi na WhatsApp ' . Plan_A_Bon_Settings::get( 'whatsapp' ) . ' pa ćemo te prijaviti mi.', $f['regular'], 34, $sw, 3 ) as $line ) {
			$lines[] = array( $line, $f['regular'], $c['muted'] );
		}
		$lh     = 44;
		$bottom = $fy - 34;
		$sy     = $bottom - count( $lines ) * $lh;
		foreach ( $lines as $i => $line ) {
			self::text( $im, 0 === $i ? 36 : 34, $x0, $sy + $i * $lh + 4, $line[2], $line[1], $line[0] );
		}
		self::qr( $im, Plan_A_Bon_Voucher::redeem_url( (string) $v['code'] ), $x1 - $qs, $bottom - $qs, $qs, $c['navy'], $c['white'] );

		return $im;
	}

	/* ------------------------------------------------------------------ */
	/* Pomoćne funkcije                                                     */
	/* ------------------------------------------------------------------ */

	private static function logo_id(): int {
		$id = (int) Plan_A_Bon_Settings::get( 'logo' );
		if ( ! $id && class_exists( 'Plan_A_Izleti_Settings' ) ) {
			$id = (int) ( Plan_A_Izleti_Settings::get()['logo_id'] ?? 0 );
		}
		if ( ! $id ) {
			$id = (int) get_theme_mod( 'custom_logo', 0 );
		}
		return $id;
	}

	/**
	 * "Za: " sitnije i sivo, ime podebljano; dugačko ime se smanjuje da stane u red.
	 */
	private static function label_value( $im, int $x, int $y, int $width, string $label, string $value, int $size, array $f, array $c ): int {
		$lw   = self::width( $label, $f['regular'], $size );
		$size = self::fit_size( $value, $f['bold'], $size, $width - $lw, (int) round( $size * 0.6 ) );
		self::text( $im, $size, $x, $y, $c['muted'], $f['regular'], $label );
		return self::text( $im, $size, $x + self::width( $label, $f['regular'], $size ), $y, $c['navy'], $f['bold'], $value );
	}

	private static function color( $im, string $hex ): int {
		$hex = ltrim( $hex, '#' );
		return imagecolorallocate( $im, hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
	}

	private static function pt( int $px ): float {
		return $px * 0.75; // GD računa u točkama pri 96 dpi.
	}

	private static function width( string $text, string $font, int $px, int $spacing = 0 ): int {
		$box = imagettfbbox( self::pt( $px ), 0, $font, $text );
		return (int) ( $box[2] - $box[0] ) + $spacing * max( 0, mb_strlen( $text ) - 1 );
	}

	/**
	 * Ispisuje tekst tako da je $y gornji rub reda (visina reda = veličina fonta) i vraća donji rub.
	 */
	private static function text( $im, int $px, int $x, int $y, int $color, string $font, string $text, string $align = 'left', int $spacing = 0 ): int {
		if ( 'center' === $align ) {
			// Okomito centrirano na $y, vodoravno na $x.
			$x   -= (int) ( self::width( $text, $font, $px, $spacing ) / 2 );
			$y   -= (int) ( $px * 0.5 );
		}
		$baseline = $y + (int) round( $px * 0.82 );
		if ( $spacing ) {
			foreach ( preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY ) as $ch ) {
				imagettftext( $im, self::pt( $px ), 0, $x, $baseline, $color, $font, $ch );
				$x += self::width( $ch, $font, $px ) + $spacing;
			}
		} else {
			imagettftext( $im, self::pt( $px ), 0, $x, $baseline, $color, $font, $text );
		}
		return $y + $px;
	}

	private static function fit_size( string $text, string $font, int $px, int $width, int $min ): int {
		while ( $px > $min && self::width( $text, $font, $px ) > $width ) {
			$px -= 2;
		}
		return $px;
	}

	/**
	 * Prelamanje teksta u najviše $max redova (zadnji red se skraćuje s "…").
	 *
	 * @return string[]
	 */
	private static function wrap( string $text, string $font, int $px, int $width, int $max ): array {
		$words = preg_split( '/\s+/u', trim( $text ) );
		$lines = array();
		$line  = '';
		foreach ( $words as $word ) {
			$try = '' === $line ? $word : $line . ' ' . $word;
			if ( '' !== $line && self::width( $try, $font, $px ) > $width ) {
				$lines[] = $line;
				$line    = $word;
			} else {
				$line = $try;
			}
		}
		if ( '' !== $line ) {
			$lines[] = $line;
		}
		if ( count( $lines ) > $max ) {
			$lines = array_slice( $lines, 0, $max );
			$last  = $lines[ $max - 1 ];
			while ( '' !== $last && self::width( $last . '…', $font, $px ) > $width ) {
				$last = mb_substr( $last, 0, -1 );
			}
			$lines[ $max - 1 ] = rtrim( $last ) . '…';
		}
		// Riječ dulja od reda skraćuje se.
		foreach ( $lines as $i => $l ) {
			while ( mb_strlen( $l ) > 1 && self::width( $l, $font, $px ) > $width ) {
				$l = mb_substr( $l, 0, -2 ) . '…';
			}
			$lines[ $i ] = $l;
		}
		return $lines;
	}

	private static function rounded( $im, int $x1, int $y1, int $x2, int $y2, int $r, int $color ) {
		imagefilledrectangle( $im, $x1 + $r, $y1, $x2 - $r, $y2, $color );
		imagefilledrectangle( $im, $x1, $y1 + $r, $x2, $y2 - $r, $color );
		foreach ( array( array( $x1 + $r, $y1 + $r ), array( $x2 - $r, $y1 + $r ), array( $x1 + $r, $y2 - $r ), array( $x2 - $r, $y2 - $r ) ) as $p ) {
			imagefilledellipse( $im, $p[0], $p[1], 2 * $r, 2 * $r, $color );
		}
	}

	/**
	 * Zaobljuje kutove područja bojom pozadine (s mekim rubom).
	 */
	private static function round_corners( $im, int $x, int $y, int $w, int $h, int $r, array $rgb ) {
		// Kvadrat r × r u kutu (gornji lijevi piksel) i središte kružnice zaobljenja.
		$corners = array(
			array( $x, $y, $x + $r, $y + $r ),
			array( $x + $w - $r, $y, $x + $w - $r, $y + $r ),
			array( $x, $y + $h - $r, $x + $r, $y + $h - $r ),
			array( $x + $w - $r, $y + $h - $r, $x + $w - $r, $y + $h - $r ),
		);
		foreach ( $corners as $k ) {
			list( $sx, $sy, $cx, $cy ) = $k;
			for ( $py = $sy; $py < $sy + $r; $py++ ) {
				for ( $px = $sx; $px < $sx + $r; $px++ ) {
					$d     = sqrt( ( $px + 0.5 - $cx ) ** 2 + ( $py + 0.5 - $cy ) ** 2 );
					$alpha = max( 0.0, min( 1.0, $d - $r + 0.5 ) ); // 0 = unutra, 1 = vani
					if ( $alpha <= 0 ) {
						continue;
					}
					$old = imagecolorat( $im, $px, $py );
					$nr  = (int) round( ( ( $old >> 16 ) & 255 ) * ( 1 - $alpha ) + $rgb[0] * $alpha );
					$ng  = (int) round( ( ( $old >> 8 ) & 255 ) * ( 1 - $alpha ) + $rgb[1] * $alpha );
					$nb  = (int) round( ( $old & 255 ) * ( 1 - $alpha ) + $rgb[2] * $alpha );
					imagesetpixel( $im, $px, $py, ( $nr << 16 ) | ( $ng << 8 ) | $nb );
				}
			}
		}
	}

	/**
	 * Slika iz medijateke u razumnoj veličini (ne izvornik od 6000 px, zbog memorije).
	 *
	 * @return \GdImage|resource|false
	 */
	private static function load( int $attachment_id, int $min ) {
		if ( ! $attachment_id ) {
			return false;
		}
		$file = get_attached_file( $attachment_id );
		if ( ! $file ) {
			return false;
		}
		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( ! empty( $meta['sizes'] ) ) {
			$best = '';
			$bw   = PHP_INT_MAX;
			foreach ( $meta['sizes'] as $size ) {
				$sw = (int) ( $size['width'] ?? 0 );
				if ( $sw >= $min && $sw < $bw && ! empty( $size['file'] ) && file_exists( dirname( $file ) . '/' . $size['file'] ) ) {
					$best = dirname( $file ) . '/' . $size['file'];
					$bw   = $sw;
				}
			}
			if ( '' !== $best ) {
				$file = $best;
			}
		}
		if ( ! file_exists( $file ) || filesize( $file ) > 20 * MB_IN_BYTES ) {
			return false;
		}
		$data = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- lokalna datoteka iz medijateke.
		$img  = $data ? @imagecreatefromstring( $data ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( $img && ! imageistruecolor( $img ) ) {
			imagepalettetotruecolor( $img );
		}
		return $img;
	}

	private static function cover( $dst, $src, int $x, int $y, int $w, int $h ) {
		$sw    = imagesx( $src );
		$sh    = imagesy( $src );
		$scale = max( $w / $sw, $h / $sh );
		$cw    = (int) round( $w / $scale );
		$ch    = (int) round( $h / $scale );
		imagecopyresampled( $dst, $src, $x, $y, (int) ( ( $sw - $cw ) / 2 ), (int) ( ( $sh - $ch ) / 2 ), $w, $h, $cw, $ch );
	}

	/**
	 * QR kod (razina ispravka M) s bijelim rubom od 4 modula.
	 */
	private static function qr( $im, string $data, int $x, int $y, int $size, int $dark, int $light ) {
		$options = new QROptions(
			array(
				'eccLevel'     => QRCode::ECC_M,
				'addQuietzone' => false,
			)
		);
		$matrix = ( new QRCode( $options ) )->getMatrix( $data );
		$n      = $matrix->size();
		$module = (int) floor( $size / ( $n + 8 ) );
		$total  = $module * ( $n + 8 );
		$ox     = $x + (int) ( ( $size - $total ) / 2 );
		$oy     = $y + (int) ( ( $size - $total ) / 2 );
		imagefilledrectangle( $im, $ox, $oy, $ox + $total - 1, $oy + $total - 1, $light );
		for ( $row = 0; $row < $n; $row++ ) {
			for ( $col = 0; $col < $n; $col++ ) {
				if ( $matrix->check( $col, $row ) ) {
					$px = $ox + ( $col + 4 ) * $module;
					$py = $oy + ( $row + 4 ) * $module;
					imagefilledrectangle( $im, $px, $py, $px + $module - 1, $py + $module - 1, $dark );
				}
			}
		}
	}
}
