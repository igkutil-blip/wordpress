<?php
/**
 * Bon kao ulaznica (karta za žičaru), omjer 2:1: lijevo fotografija ili ilustracija grebena,
 * u sredini iznos i imena, desno otkidni dio s QR kodom i kodom bona.
 *
 * Isti crtež koristi se za PDF (A5 položeno, bon + upute + izdavatelj), PNG (1600 × 800 px)
 * i sličicu u košarici/narudžbi/e-mailu. Crta se GD-om s ugrađenim fontom Lato; QR kod izrađuje
 * uključena biblioteka chillerlan/php-qrcode. Ništa se ne šalje vanjskim servisima.
 *
 * @package Plan_A_Poklon_Bon
 */

defined( 'ABSPATH' ) || exit;

use PlanAPoklonBon\Vendor\chillerlan\QRCode\QRCode;
use PlanAPoklonBon\Vendor\chillerlan\QRCode\QROptions;

class Plan_A_Bon_Render {

	/** Inačica crteža (mijenja naziv datoteka, pa se stari bonovi iscrtaju ponovno). */
	const VERSION = 2;

	/** Osnovna veličina ulaznice (sve mjere u crtežu odnose se na nju). */
	const BASE_W = 2200;
	const BASE_H = 1100;

	/** A5 položeno: 2480 × 1748 px pri 300 dpi; margina 140 px ≈ 12 mm. */
	const PAGE_W = 2480;
	const PAGE_H = 1748;
	const MARGIN = 140;
	const PDF_W  = 595.28;
	const PDF_H  = 419.53;

	const PNG_W = 1600;

	/** Boje logotipa. */
	const BLUE   = '#3d8fd9';
	const NAVY   = '#1b2d4a';
	const ORANGE = '#e0893a';
	const RED    = '#c0392b';

	/** Zamjena za kod prije izdavanja. */
	const PLACEHOLDER = 'PLANA-••••-••••';

	/**
	 * Izrađuje $base.pdf i $base.png.
	 */
	public static function files( array $v, string $base ): bool {
		if ( ! self::available() ) {
			return false;
		}
		wp_raise_memory_limit( 'image' );

		// PNG 1600 × 800 (crtano dvostruko pa smanjeno, za glatke rubove).
		$big = self::ticket( $v, self::PNG_W * 2, '#ffffff' );
		$png = imagecreatetruecolor( self::PNG_W, self::PNG_W / 2 );
		imagecopyresampled( $png, $big, 0, 0, 0, 0, self::PNG_W, self::PNG_W / 2, imagesx( $big ), imagesy( $big ) );
		imagedestroy( $big );
		imagepng( $png, $base . '.png', 6 );
		imagedestroy( $png );

		// PDF: stranica A5 s bonom, uputama i tekstom izdavatelja.
		$page = self::page( $v );
		ob_start();
		imagejpeg( $page, null, 90 );
		$jpeg = (string) ob_get_clean();
		imagedestroy( $page );
		file_put_contents( $base . '.pdf', self::pdf( $jpeg, self::PAGE_W, self::PAGE_H, 'Poklon bon ' . $v['code'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return true;
	}

	public static function available(): bool {
		return function_exists( 'imagecreatetruecolor' ) && function_exists( 'imagettftext' );
	}

	/**
	 * Sličica bona (bez koda) za košaricu, narudžbu i e-mail; PNG širine $w.
	 */
	public static function thumb( array $v, string $path, int $w = 600 ): bool {
		if ( ! self::available() ) {
			return false;
		}
		$v['code'] = '';
		$big       = self::ticket( $v, $w * 2, '#ffffff' );
		$im        = imagecreatetruecolor( $w, (int) ( $w / 2 ) );
		imagecopyresampled( $im, $big, 0, 0, 0, 0, $w, (int) ( $w / 2 ), imagesx( $big ), imagesy( $big ) );
		imagedestroy( $big );
		$ok = imagepng( $im, $path, 6 );
		imagedestroy( $im );
		return $ok;
	}

	/**
	 * Stranica A5: bon gore, ispod upute "Kako iskoristiti" i tekst izdavatelja.
	 *
	 * @return \GdImage|resource
	 */
	public static function page( array $v ) {
		$m    = self::MARGIN;
		$page = imagecreatetruecolor( self::PAGE_W, self::PAGE_H );
		$c    = self::palette( $page );
		imagefill( $page, 0, 0, $c['white'] );

		$tw     = self::PAGE_W - 2 * $m;
		$ticket = self::ticket( $v, $tw, '#ffffff' );
		imagecopy( $page, $ticket, $m, $m, 0, 0, imagesx( $ticket ), imagesy( $ticket ) );
		$y = $m + imagesy( $ticket ) + 70;
		imagedestroy( $ticket );

		$f    = self::fonts();
		$host = self::host();
		self::text( $page, 40, $m, $y, $c['navy'], $f['bold'], 'Kako iskoristiti:' );
		$y   += 62;
		$y    = self::text( $page, 36, $m, $y, $c['text'], $f['regular'], '1. Odaberi izlet na ' . $host . '    2. U košarici upiši kod    3. Iznos bona se oduzima od cijene.' ) + 14;
		self::text( $page, 36, $m, $y, $c['text'], $f['regular'], 'Ili nam se javi na WhatsApp ' . Plan_A_Bon_Settings::get( 'whatsapp' ) . ' pa ćemo te prijaviti mi.' );

		$issuer = self::wrap( (string) Plan_A_Bon_Settings::get( 'issuer' ), $f['regular'], 28, $tw, 3 );
		$fy     = self::PAGE_H - $m - count( $issuer ) * 38;
		foreach ( $issuer as $i => $line ) {
			self::text( $page, 28, $m, $fy + $i * 38, $c['muted'], $f['regular'], $line );
		}
		return $page;
	}

	/**
	 * Ulaznica širine $w (visina $w / 2). Prazan kod = pregled prije izdavanja.
	 *
	 * @return \GdImage|resource
	 */
	public static function ticket( array $v, int $w, string $bg ) {
		$s  = $w / self::BASE_W;
		$p  = static function ( $value ) use ( $s ): int {
			return (int) round( $value * $s );
		};
		$h  = (int) round( $w / 2 );
		$im = imagecreatetruecolor( $w, $h );
		imagealphablending( $im, true );
		$c  = self::palette( $im );
		$f  = self::fonts();
		$bgc = self::color( $im, $bg );
		imagefill( $im, 0, 0, $c['white'] );

		$left  = $p( 880 );
		$cut   = $p( 1760 );
		$code  = (string) ( $v['code'] ?? '' );

		// --- Lijevo: fotografija ili ilustracija ---------------------------------
		$photo = self::load( (int) Plan_A_Bon_Settings::get( 'photo' ), min( 2048, max( 400, $left ) ) );
		if ( $photo ) {
			self::cover( $im, $photo, 0, 0, $left, $h );
			imagedestroy( $photo );
			// Blagi tamnoplavi prijelaz prema dnu.
			$start = (int) ( $h * 0.45 );
			for ( $y = $start; $y < $h; $y++ ) {
				$t     = ( $y - $start ) / max( 1, $h - $start );
				$alpha = (int) round( 127 - 80 * $t * $t );
				$col   = imagecolorallocatealpha( $im, 27, 45, 74, $alpha );
				imageline( $im, 0, $y, $left - 1, $y, $col );
			}
		} else {
			self::ridge( $im, 0, 0, $left, $h, $c );
		}

		// Logotip na bijeloj podlozi.
		$logo = self::load( self::logo_id(), 600 );
		$lx   = $p( 46 );
		$ly   = $p( 46 );
		if ( $logo ) {
			$lh = $p( 96 );
			$lw = (int) round( imagesx( $logo ) * $lh / max( 1, imagesy( $logo ) ) );
			if ( $lw > $left - $p( 160 ) ) {
				$lw = $left - $p( 160 );
				$lh = (int) round( imagesy( $logo ) * $lw / max( 1, imagesx( $logo ) ) );
			}
			self::rounded( $im, $lx, $ly, $lx + $lw + $p( 52 ), $ly + $lh + $p( 40 ), $p( 22 ), $c['white'] );
			imagecopyresampled( $im, $logo, $lx + $p( 26 ), $ly + $p( 20 ), 0, 0, $lw, $lh, imagesx( $logo ), imagesy( $logo ) );
			imagedestroy( $logo );
		} else {
			self::rounded( $im, $lx, $ly, $lx + $p( 260 ), $ly + $p( 110 ), $p( 22 ), $c['white'] );
			self::text( $im, $p( 50 ), $lx + $p( 130 ), $ly + $p( 55 ), $c['navy'], $f['black'], 'Plan A', 'center' );
		}

		// --- Sredina: iznos i imena --------------------------------------------
		$x0 = $left + $p( 80 );
		$cw = $cut - $p( 70 ) - $x0;
		self::text( $im, $p( 32 ), $x0, $p( 104 ), $c['blue'], $f['bold'], 'POKLON BON ZA IZLET', 'left', $p( 9 ) );
		self::text( $im, $p( 230 ), $x0 - $p( 8 ), $p( 158 ), $c['orange'], $f['black'], Plan_A_Bon_Voucher::money_short( (float) $v['amount'] ) );

		$y = $p( 452 );
		$y = self::label_value( $im, $x0, $y, $cw, 'Za: ', '' !== (string) $v['to'] ? (string) $v['to'] : 'Ime primatelja', $p( 60 ), $f, $c ) + $p( 16 );
		$y = self::label_value( $im, $x0, $y, $cw, 'Od: ', '' !== (string) $v['from'] ? (string) $v['from'] : 'Tvoje ime', $p( 48 ), $f, $c );
		if ( '' !== trim( (string) $v['message'] ) ) {
			$y += $p( 26 );
			foreach ( self::wrap( '„' . trim( (string) $v['message'] ) . '“', $f['italic'], $p( 40 ), $cw, 3 ) as $line ) {
				$y = self::text( $im, $p( 40 ), $x0, $y, $c['text'], $f['italic'], $line ) + $p( 12 );
			}
		}
		$valid = ! empty( $v['expires'] ) ? Plan_A_Bon_Voucher::hr_date( (int) $v['expires'] ) : Plan_A_Bon_Voucher::hr_date( Plan_A_Bon_Voucher::default_expiry() );
		self::text( $im, $p( 40 ), $x0, $h - $p( 150 ), $c['navy'], $f['bold'], 'Vrijedi do: ' . $valid );

		// --- Desno: otkidni dio --------------------------------------------------
		$sx  = $cut;
		$sw  = $w - $cut;
		$qs  = $p( 290 );
		$gy  = (int) ( ( $h - ( $qs + $p( 220 ) ) ) / 2 );
		$url = '' !== $code ? Plan_A_Bon_Voucher::redeem_url( $code ) : home_url( '/izleti/' );
		self::qr( $im, $url, $sx + (int) ( ( $sw - $qs ) / 2 ), $gy, $qs, '' !== $code ? $c['navy'] : $c['faint'], $c['white'] );
		$shown = '' !== $code ? $code : self::PLACEHOLDER;
		$parts = explode( '-', $shown, 2 );
		$cy    = $gy + $qs + $p( 36 );
		$cx    = $sx + (int) ( $sw / 2 );
		self::text( $im, $p( 46 ), $cx, $cy + $p( 23 ), '' !== $code ? $c['navy'] : $c['muted'], $f['black'], $parts[0], 'center', $p( 4 ) );
		self::text( $im, $p( 46 ), $cx, $cy + $p( 85 ), '' !== $code ? $c['navy'] : $c['muted'], $f['black'], $parts[1] ?? '', 'center', $p( 3 ) );
		self::text( $im, $p( 28 ), $cx, $cy + $p( 160 ), $c['muted'], $f['regular'], self::host(), 'center' );

		// --- Oblik ulaznice: zaobljeni kutovi, urezi, isprekidana linija, obrub --------
		$r   = $p( 44 );
		$rgb = sscanf( ltrim( $bg, '#' ), '%02x%02x%02x' );
		self::round_corners( $im, 0, 0, $w, $h, $r, $rgb );
		$nr = $p( 40 );
		imagefilledellipse( $im, $cut, 0, 2 * $nr, 2 * $nr, $bgc );
		imagefilledellipse( $im, $cut, $h - 1, 2 * $nr, 2 * $nr, $bgc );

		$dash = $p( 22 );
		$gap  = $p( 16 );
		$lw   = max( 1, $p( 4 ) );
		for ( $y = $nr + $p( 24 ); $y < $h - $nr - $p( 24 ); $y += $dash + $gap ) {
			imagefilledrectangle( $im, $cut - (int) ( $lw / 2 ), $y, $cut + (int) ( $lw / 2 ), min( $y + $dash, $h - $nr - $p( 24 ) ), $c['dash'] );
		}

		$t = max( 1, $p( 3 ) );
		imagesetthickness( $im, $t );
		$o  = (int) ( $t / 2 );
		$x2 = $w - 1 - $o;
		$y2 = $h - 1 - $o;
		// Ravni dijelovi obruba (prekinuti urezima).
		imageline( $im, $r, $o, $cut - $nr, $o, $c['edge'] );
		imageline( $im, $cut + $nr, $o, $w - $r, $o, $c['edge'] );
		imageline( $im, $r, $y2, $cut - $nr, $y2, $c['edge'] );
		imageline( $im, $cut + $nr, $y2, $w - $r, $y2, $c['edge'] );
		imageline( $im, $o, $r, $o, $h - $r, $c['edge'] );
		imageline( $im, $x2, $r, $x2, $h - $r, $c['edge'] );
		// Kutovi i urezi.
		imagearc( $im, $r, $r, 2 * $r - $t, 2 * $r - $t, 180, 270, $c['edge'] );
		imagearc( $im, $w - $r, $r, 2 * $r - $t, 2 * $r - $t, 270, 360, $c['edge'] );
		imagearc( $im, $r, $h - $r, 2 * $r - $t, 2 * $r - $t, 90, 180, $c['edge'] );
		imagearc( $im, $w - $r, $h - $r, 2 * $r - $t, 2 * $r - $t, 0, 90, $c['edge'] );
		imagearc( $im, $cut, 0, 2 * $nr + $t, 2 * $nr + $t, 0, 180, $c['edge'] );
		imagearc( $im, $cut, $h - 1, 2 * $nr + $t, 2 * $nr + $t, 180, 360, $c['edge'] );
		imagesetthickness( $im, 1 );

		return $im;
	}

	/**
	 * Ilustracija planinskog grebena (kad fotografija nije odabrana): nebo, sunce i vrhovi
	 * u bojama logotipa. Iste oblike crta i SVG pregleda na stranici.
	 */
	private static function ridge( $im, int $x, int $y, int $w, int $h, array $c ) {
		$px = static function ( $fx ) use ( $x, $w ): int {
			return $x + (int) round( $fx * $w );
		};
		$py = static function ( $fy ) use ( $y, $h ): int {
			return $y + (int) round( $fy * $h );
		};
		imagesetclip( $im, $x, $y, $x + $w - 1, $y + $h - 1 );
		imagefilledrectangle( $im, $x, $y, $x + $w - 1, $y + $h - 1, $c['sky'] );
		imagefilledellipse( $im, $px( 0.66 ), $py( 0.27 ), (int) ( 0.2 * $w ), (int) ( 0.2 * $w ), $c['sun'] );
		foreach ( self::ridge_shapes() as $shape ) {
			$points = array();
			foreach ( $shape[1] as $i => $value ) {
				$points[] = 0 === $i % 2 ? $px( $value ) : $py( $value );
			}
			imagefilledpolygon( $im, $points, $c[ $shape[0] ] );
		}
		imagesetclip( $im, 0, 0, imagesx( $im ) - 1, imagesy( $im ) - 1 );
	}

	/**
	 * Vrhovi grebena: [boja, [x1, y1, x2, y2, …]] u udjelima širine i visine.
	 */
	public static function ridge_shapes(): array {
		return array(
			array( 'blue', array( -0.12, 1, 0.3, 0.4, 0.72, 1 ) ),
			array( 'blue', array( 0.52, 1, 0.88, 0.47, 1.24, 1 ) ),
			array( 'red', array( 0.34, 1, 0.62, 0.56, 0.9, 1 ) ),
			array( 'orange', array( -0.04, 1, 0.2, 0.66, 0.46, 1 ) ),
			array( 'white', array( 0.3, 0.4, 0.355, 0.478, 0.325, 0.462, 0.3, 0.49, 0.272, 0.462, 0.245, 0.478 ) ),
			array( 'white', array( 0.88, 0.47, 0.93, 0.54, 0.905, 0.527, 0.88, 0.55, 0.855, 0.527, 0.83, 0.54 ) ),
			array( 'navy', array( -0.1, 1, 0.24, 0.8, 0.62, 1 ) ),
			array( 'navy', array( 0.42, 1, 0.78, 0.82, 1.14, 1 ) ),
		);
	}

	/* ------------------------------------------------------------------ */
	/* PDF                                                                  */
	/* ------------------------------------------------------------------ */

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

	/* ------------------------------------------------------------------ */
	/* QR kao SVG (pregled na stranici)                                     */
	/* ------------------------------------------------------------------ */

	public static function qr_svg( string $data, string $color ): string {
		$matrix = self::matrix( $data );
		$n      = $matrix->size();
		$path   = '';
		for ( $row = 0; $row < $n; $row++ ) {
			for ( $col = 0; $col < $n; $col++ ) {
				if ( $matrix->check( $col, $row ) ) {
					$path .= 'M' . ( $col + 4 ) . ' ' . ( $row + 4 ) . 'h1v1h-1z';
				}
			}
		}
		$size = $n + 8;
		return '<svg viewBox="0 0 ' . $size . ' ' . $size . '" xmlns="http://www.w3.org/2000/svg" shape-rendering="crispEdges" aria-hidden="true" focusable="false"><rect width="' . $size . '" height="' . $size . '" fill="#fff"/><path d="' . $path . '" fill="' . esc_attr( $color ) . '"/></svg>';
	}

	/* ------------------------------------------------------------------ */
	/* Pomoćne funkcije                                                     */
	/* ------------------------------------------------------------------ */

	public static function host(): string {
		return (string) preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	}

	private static function fonts(): array {
		return array(
			'black'   => PLAN_A_BON_DIR . 'assets/fonts/Lato-Black.ttf',
			'bold'    => PLAN_A_BON_DIR . 'assets/fonts/Lato-Bold.ttf',
			'regular' => PLAN_A_BON_DIR . 'assets/fonts/Lato-Regular.ttf',
			'italic'  => PLAN_A_BON_DIR . 'assets/fonts/Lato-Italic.ttf',
		);
	}

	private static function palette( $im ): array {
		return array(
			'white'  => self::color( $im, '#ffffff' ),
			'navy'   => self::color( $im, self::NAVY ),
			'blue'   => self::color( $im, self::BLUE ),
			'orange' => self::color( $im, self::ORANGE ),
			'red'    => self::color( $im, self::RED ),
			'text'   => self::color( $im, '#24323f' ),
			'muted'  => self::color( $im, '#6b7785' ),
			'faint'  => self::color( $im, '#c9d3de' ),
			'edge'   => self::color( $im, '#d3dce6' ),
			'dash'   => self::color( $im, '#b8c4d1' ),
			'sky'    => self::color( $im, '#dcecfa' ),
			'sun'    => self::color( $im, '#f3b064' ),
		);
	}

	public static function logo_id(): int {
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
		if ( '' === $text ) {
			return 0;
		}
		$box = imagettfbbox( self::pt( $px ), 0, $font, $text );
		return (int) ( $box[2] - $box[0] ) + $spacing * max( 0, mb_strlen( $text ) - 1 );
	}

	/**
	 * Ispisuje tekst tako da je $y gornji rub reda (visina reda = veličina fonta) i vraća donji rub.
	 * Kod 'center' $x i $y su središte teksta.
	 */
	private static function text( $im, int $px, int $x, int $y, int $color, string $font, string $text, string $align = 'left', int $spacing = 0 ): int {
		if ( '' === $text ) {
			return $y + $px;
		}
		if ( 'center' === $align ) {
			$x -= (int) ( self::width( $text, $font, $px, $spacing ) / 2 );
			$y -= (int) ( $px * 0.5 );
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
		foreach ( array( array( $x1 + $r, $y1 + $r ), array( $x2 - $r, $y1 + $r ), array( $x1 + $r, $y2 - $r ), array( $x2 - $r, $y2 - $r ) ) as $pt ) {
			imagefilledellipse( $im, $pt[0], $pt[1], 2 * $r, 2 * $r, $color );
		}
	}

	/**
	 * Zaobljuje kutove područja bojom pozadine (s mekim rubom).
	 */
	private static function round_corners( $im, int $x, int $y, int $w, int $h, int $r, array $rgb ) {
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
					$alpha = max( 0.0, min( 1.0, $d - $r + 0.5 ) );
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

	private static function matrix( string $data ) {
		$options = new QROptions(
			array(
				'eccLevel'     => QRCode::ECC_M,
				'addQuietzone' => false,
			)
		);
		return ( new QRCode( $options ) )->getMatrix( $data );
	}

	/**
	 * QR kod (razina ispravka M) s bijelim rubom od 4 modula.
	 */
	private static function qr( $im, string $data, int $x, int $y, int $size, int $dark, int $light ) {
		$matrix = self::matrix( $data );
		$n      = $matrix->size();
		$module = max( 1, (int) floor( $size / ( $n + 8 ) ) );
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
