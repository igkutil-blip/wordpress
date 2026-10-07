<?php
/**
 * Slika kartice izleta za dijeljenje "Predloži ekipi" (JPG, 1080 × 1350 px).
 *
 * Gornjih ~56 %: istaknuta slika izleta (izrezana da ispuni prostor) i bijela oznaka
 * "Idemo zajedno?". Donji dio u boji zaglavlja stranice: naziv (najviše dva reda),
 * datum, mjesto, trajanje i cijena s jednostavnim bijelim ikonama, ikone aktivnosti
 * u bijelim krugovima te logotip i adresa stranice.
 *
 * Crta se s GD-om (FreeType) u dvostrukoj veličini pa smanjuje, radi glatkih rubova.
 * Font: Lato (zadani font teme Flatsome, OFL), ugrađen u assets/fonts.
 * Ikone aktivnosti iscrtavaju se iz fontova WpTravellyja (Font Awesome 6, Mage Icons).
 *
 * Slika se sprema u uploads/plan-a-izleti/kartice/izlet-<ID>.jpg. Potpis podataka
 * (naziv, datum, mjesto, trajanje, cijena, slika, aktivnosti, logotip, boja) čuva se u
 * opciji plan_a_izleti_cards; kad se bilo što promijeni, slika se izrađuje ponovno.
 *
 * @package Plan_A_Izleti
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Izleti_Card {

	const LAYOUT  = '2';
	const GLYPHS  = '2'; // verzija mape ikona (iz CSS-a WpTravellyja)
	const OPTION  = 'plan_a_izleti_cards';
	const CRON    = 'plan_a_izleti_make_card';
	const DIR     = 'plan-a-izleti/kartice';
	const W       = 1080;
	const H       = 1350;
	const PHOTO_H = 760; // oko 56 % visine
	const S       = 2; // dvostruka veličina za glatke rubove

	/** @var array|null Mape klasa ikona → znak (iz CSS-a WpTravellyja). */
	private static $glyphs = null;

	public static function init() {
		add_action( self::CRON, array( __CLASS__, 'generate' ) );
		// Nakon spremanja izleta (i meta podataka WpTravellyja) izradi sliku odmah.
		add_action( 'wp_after_insert_post', array( __CLASS__, 'after_save' ), 20, 2 );
		add_action( 'before_delete_post', array( __CLASS__, 'delete' ) );
		// Stranica izleta: ako slika nedostaje ili je zastarjela, izradi je (najviše jednu po zahtjevu).
		add_action( 'template_redirect', array( __CLASS__, 'on_tour_page' ) );
	}

	public static function supported(): bool {
		return function_exists( 'imagecreatetruecolor' ) && function_exists( 'imagettftext' ) && function_exists( 'imagejpeg' );
	}

	/* ------------------------------------------------------------------ */
	/* Datoteke                                                             */
	/* ------------------------------------------------------------------ */

	private static function dir(): array {
		$upload = wp_upload_dir( null, false );
		return array(
			'path' => trailingslashit( $upload['basedir'] ) . self::DIR,
			'url'  => trailingslashit( $upload['baseurl'] ) . self::DIR,
		);
	}

	private static function file( int $id ): string {
		return self::dir()['path'] . '/izlet-' . $id . '.jpg';
	}

	/**
	 * Adresa slike kartice ako je izrađena i odgovara trenutnim podacima; inače ''
	 * (i zakaže izradu u pozadini). Tada se dijeli istaknuta slika kao do sada.
	 */
	public static function url( int $id ): string {
		if ( ! self::supported() ) {
			return '';
		}
		$signature = self::signature( $id );
		$map       = (array) get_option( self::OPTION, array() );
		if ( ( $map[ $id ] ?? '' ) === $signature && file_exists( self::file( $id ) ) ) {
			return self::dir()['url'] . '/izlet-' . $id . '.jpg?v=' . substr( $signature, 0, 10 );
		}
		if ( ! wp_next_scheduled( self::CRON, array( $id ) ) ) {
			wp_schedule_single_event( time(), self::CRON, array( $id ) );
		}
		return '';
	}

	public static function after_save( $post_id, $post ) {
		if ( ! $post instanceof WP_Post || Plan_A_Izleti_Data::post_type() !== $post->post_type || 'publish' !== $post->post_status || wp_is_post_revision( $post_id ) ) {
			return;
		}
		self::generate( (int) $post_id );
	}

	public static function on_tour_page() {
		if ( ! is_singular( Plan_A_Izleti_Data::post_type() ) || ! Plan_A_Izleti_Data::is_source_available() || ! self::supported() ) {
			return;
		}
		$id  = get_queried_object_id();
		$map = (array) get_option( self::OPTION, array() );
		if ( ( $map[ $id ] ?? '' ) !== self::signature( $id ) || ! file_exists( self::file( $id ) ) ) {
			self::generate( $id );
		}
	}

	public static function delete( $post_id ) {
		$file = self::file( (int) $post_id );
		if ( file_exists( $file ) ) {
			wp_delete_file( $file );
		}
		$map = (array) get_option( self::OPTION, array() );
		if ( isset( $map[ $post_id ] ) ) {
			unset( $map[ $post_id ] );
			update_option( self::OPTION, $map, false );
		}
	}

	/**
	 * Briše sve slike kartica (postavke: "Ponovno izradi slike", brisanje dodatka).
	 */
	public static function delete_all() {
		$dir = self::dir()['path'];
		if ( is_dir( $dir ) ) {
			foreach ( (array) glob( $dir . '/izlet-*.jpg' ) as $file ) {
				wp_delete_file( $file );
			}
		}
		delete_option( self::OPTION );
	}

	/* ------------------------------------------------------------------ */
	/* Podaci                                                               */
	/* ------------------------------------------------------------------ */

	private static function data( int $id ): array {
		$share     = Plan_A_Izleti_Share::data_without_card( $id );
		$source_id = Plan_A_Izleti_Data::source_id( $id );
		$lookup    = Plan_A_Izleti_Categories::lookup( $id, $source_id );
		$acts      = array();
		foreach ( Plan_A_Izleti_Categories::activity_terms( $lookup ) as $term ) {
			$acts[] = array(
				'name' => $term->name,
				'icon' => Plan_A_Izleti_Categories::activity_icon( $term->term_id ),
			);
		}
		$image_id = Plan_A_Izleti_Shortcode::image_id( $id );
		$settings = Plan_A_Izleti_Settings::get();
		return array(
			'title'    => $share['title'],
			'fields'   => $share['fields'],
			'acts'     => $acts,
			'image'    => $image_id,
			'image_v'  => $image_id ? (string) get_post_modified_time( 'U', true, $image_id ) . '-' . self::file_size( (string) get_attached_file( $image_id ) ) : '',
			'logo'     => (int) $settings['logo_id'],
			'color'    => Plan_A_Izleti_Settings::card_color(),
			'domain'   => self::domain(),
			'layout'   => self::LAYOUT,
		);
	}

	/**
	 * Veličina datoteke (za potpis: zamijenjena datoteka iste slike u medijateci).
	 */
	private static function file_size( string $file ): string {
		return ( '' !== $file && file_exists( $file ) ) ? (string) filesize( $file ) : '0';
	}

	private static function signature( int $id ): string {
		return md5( (string) wp_json_encode( self::data( $id ) ) );
	}

	public static function domain(): string {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		return (string) preg_replace( '/^www\./i', '', $host );
	}

	/* ------------------------------------------------------------------ */
	/* Izrada slike                                                         */
	/* ------------------------------------------------------------------ */

	/**
	 * @return bool Je li slika izrađena.
	 */
	public static function generate( $id ): bool {
		$id = (int) $id;
		if ( ! self::supported() || ! Plan_A_Izleti_Data::is_source_available() ) {
			return false;
		}
		$post = get_post( $id );
		if ( ! $post || Plan_A_Izleti_Data::post_type() !== $post->post_type ) {
			return false;
		}

		// Bez ispravnog fonta slika bi bila bez teksta: tada se dijeli istaknuta slika.
		foreach ( array( 'bold', 'regular' ) as $weight ) {
			$font = self::font( $weight );
			if ( ! is_readable( $font ) || false === @imagettfbbox( 12, 0, $font, 'Ž' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				return false;
			}
		}

		try {
			$data  = self::data( $id );
			$image = self::draw( $data );
		} catch ( \Throwable $e ) {
			return false;
		}
		if ( ! $image ) {
			return false;
		}

		$dir = self::dir()['path'];
		if ( ! wp_mkdir_p( $dir ) ) {
			imagedestroy( $image );
			return false;
		}
		$file = self::file( $id );
		$tmp  = $file . '.' . wp_generate_password( 6, false ) . '.tmp';
		$ok   = imagejpeg( $image, $tmp, 86 );
		imagedestroy( $image );
		if ( ! $ok || ! @rename( $tmp, $file ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename
			if ( file_exists( $tmp ) ) {
				wp_delete_file( $tmp );
			}
			return false;
		}

		$map        = (array) get_option( self::OPTION, array() );
		$map[ $id ] = md5( (string) wp_json_encode( $data ) );
		update_option( self::OPTION, $map, false );
		return true;
	}

	private static function font( string $weight ): string {
		$file = PLAN_A_IZLETI_DIR . 'assets/fonts/' . ( 'bold' === $weight ? 'Lato-Bold.ttf' : 'Lato-Regular.ttf' );
		return (string) apply_filters( 'plan_a_izleti_card_font', $file, $weight );
	}

	private static function rgb( string $hex ): array {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
	}

	/**
	 * @return \GdImage|resource|false
	 */
	private static function draw( array $data ) {
		$s  = self::S;
		$w  = self::W * $s;
		$h  = self::H * $s;
		$ph = self::PHOTO_H * $s;
		$im = imagecreatetruecolor( $w, $h );
		if ( ! $im ) {
			return false;
		}
		imagealphablending( $im, true );

		list( $r, $g, $b ) = self::rgb( $data['color'] );
		$bg    = imagecolorallocate( $im, $r, $g, $b );
		$white = imagecolorallocate( $im, 255, 255, 255 );
		$soft  = imagecolorallocatealpha( $im, 255, 255, 255, 30 ); // bijela, malo prozirna
		imagefilledrectangle( $im, 0, 0, $w, $h, $bg );

		// Fotografija (cover) ili mekša pozadina ako je nema.
		$photo = self::load_image( (int) $data['image'] );
		if ( $photo ) {
			// Svijetla podloga za slike s prozirnošću (PNG).
			imagefilledrectangle( $im, 0, 0, $w, $ph, imagecolorallocate( $im, 241, 241, 241 ) );
			self::cover( $im, $photo, 0, 0, $w, $ph );
			imagedestroy( $photo );
		} else {
			$light = imagecolorallocate( $im, min( 255, $r + 40 ), min( 255, $g + 40 ), min( 255, $b + 40 ) );
			imagefilledrectangle( $im, 0, 0, $w, $ph, $light );
		}

		$bold    = self::font( 'bold' );
		$regular = self::font( 'regular' );
		$pad     = 64 * $s;

		// Oznaka "Idemo zajedno?" u gornjem lijevom kutu.
		$label = __( 'Idemo zajedno?', 'plan-a-izleti' );
		$size  = 27 * $s;
		$box   = self::text_box( $size, $bold, $label );
		$pill  = array( 48 * $s, 48 * $s, 48 * $s + $box['w'] + 2 * 28 * $s, 48 * $s + 76 * $s );
		self::rounded( $im, $pill[0], $pill[1], $pill[2], $pill[3], 38 * $s, $white );
		self::text( $im, $size, $pill[0] + 28 * $s, $pill[1] + 38 * $s, $bg, $bold, $label, 'middle' );

		// Naziv: najviše dva reda, font se po potrebi smanji.
		$y      = $ph + 54 * $s;
		$max_w  = $w - 2 * $pad;
		$title  = self::fit_title( $data['title'], $bold, $max_w );
		$line_h = (int) round( $title['size'] * 1.3 );
		foreach ( $title['lines'] as $line ) {
			self::text( $im, $title['size'], $pad, $y, $white, $bold, $line, 'top' );
			$y += $line_h;
		}
		$y += 18 * $s;

		// Podaci u dva stupca: datum, mjesto, trajanje, cijena.
		$fields = array();
		foreach ( array( 'date', 'place', 'duration', 'price' ) as $key ) {
			if ( ! empty( $data['fields'][ $key ] ) ) {
				$fields[ $key ] = $data['fields'][ $key ];
			}
		}
		$col_w = (int) floor( ( $max_w - 32 * $s ) / 2 );
		$size  = 21 * $s;
		$icon  = 50 * $s; // ikona + razmak do teksta

		// Redovi: dva podatka jedan do drugog; podatak koji ne stane u pola širine
		// (npr. "20. listopada 2026. (i drugi termini)") dobiva cijeli red.
		$rows    = array();
		$pending = null;
		foreach ( $fields as $key => $value ) {
			$fits = self::text_box( $size, $regular, $value )['w'] <= $col_w - $icon;
			if ( ! $fits ) {
				if ( $pending ) {
					$rows[]  = array( $pending );
					$pending = null;
				}
				$rows[] = array( array( $key, $value ) );
			} elseif ( $pending ) {
				$rows[]  = array( $pending, array( $key, $value ) );
				$pending = null;
			} else {
				$pending = array( $key, $value );
			}
		}
		if ( $pending ) {
			$rows[] = array( $pending );
		}
		$row_h = ( count( $rows ) > 2 ? 48 : 54 ) * $s;
		foreach ( $rows as $r_index => $row ) {
			$cy = $y + $r_index * $row_h + (int) ( $row_h / 2 );
			foreach ( $row as $c_index => $item ) {
				$x     = $pad + $c_index * ( $col_w + 32 * $s );
				$width = 1 === count( $row ) ? $max_w : $col_w;
				self::icon( $im, $item[0], $x, $cy, 34 * $s, $white, $bg );
				self::text( $im, $size, $x + $icon, $cy, $white, $regular, self::ellipsis( $item[1], $size, $regular, $width - $icon ), 'middle' );
			}
		}
		$y += count( $rows ) * $row_h + 18 * $s;

		// Ikone aktivnosti u bijelim krugovima.
		if ( $data['acts'] ) {
			$d     = 66 * $s;
			$gap   = 16 * $s;
			$shown = array_slice( $data['acts'], 0, 6 );
			$rest  = count( $data['acts'] ) - count( $shown );
			$x     = $pad;
			foreach ( $shown as $act ) {
				self::circle( $im, $x + $d / 2, $y + $d / 2, $d, $white );
				self::activity( $im, $act, $x + $d / 2, $y + $d / 2, $d, $bg, $bold );
				$x += $d + $gap;
			}
			if ( $rest > 0 ) {
				self::circle( $im, $x + $d / 2, $y + $d / 2, $d, $white );
				self::text_center( $im, 20 * $s, $x + $d / 2, $y + $d / 2, $bg, $bold, '+' . $rest );
			}
		}

		// Dno: logotip (na bijeloj podlozi, da je vidljiv na tamnoj boji) i adresa stranice.
		$foot_h = 84 * $s;
		$foot_y = $h - 44 * $s - $foot_h;
		imageline( $im, $pad, $foot_y - 22 * $s, $w - $pad, $foot_y - 22 * $s, imagecolorallocatealpha( $im, 255, 255, 255, 90 ) );
		$logo = self::load_image( (int) $data['logo'] );
		if ( $logo ) {
			$lh    = 56 * $s;
			$lw    = (int) round( imagesx( $logo ) * $lh / max( 1, imagesy( $logo ) ) );
			$lw    = min( $lw, 340 * $s );
			$lh    = (int) round( imagesy( $logo ) * $lw / max( 1, imagesx( $logo ) ) );
			$inner = 14 * $s;
			self::rounded( $im, $pad, $foot_y, $pad + $lw + 2 * $inner, $foot_y + $foot_h, 16 * $s, $white );
			imagecopyresampled( $im, $logo, $pad + $inner, $foot_y + (int) ( ( $foot_h - $lh ) / 2 ), 0, 0, $lw, $lh, imagesx( $logo ), imagesy( $logo ) );
			imagedestroy( $logo );
		} else {
			self::text( $im, 26 * $s, $pad, $foot_y + (int) ( $foot_h / 2 ), $white, $bold, 'Plan A', 'middle' );
		}
		$domain = $data['domain'];
		$dbox   = self::text_box( 21 * $s, $bold, $domain );
		self::text( $im, 21 * $s, $w - $pad - $dbox['w'], $foot_y + (int) ( $foot_h / 2 ), $soft, $bold, $domain, 'middle' );

		// Smanjenje na 1080 × 1350 (glatki rubovi).
		$out = imagecreatetruecolor( self::W, self::H );
		imagecopyresampled( $out, $im, 0, 0, 0, 0, self::W, self::H, $w, $h );
		imagedestroy( $im );
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Pomoćne funkcije za crtanje                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * Slika iz medijateke u razumnoj veličini (ne izvornik od 6000 px, zbog memorije).
	 *
	 * @return \GdImage|resource|false
	 */
	private static function load_image( int $attachment_id ) {
		if ( ! $attachment_id ) {
			return false;
		}
		$file = get_attached_file( $attachment_id );
		if ( ! $file ) {
			return false;
		}
		foreach ( array( '1536x1536', 'large' ) as $size ) {
			$meta = image_get_intermediate_size( $attachment_id, $size );
			if ( $meta && ! empty( $meta['file'] ) && file_exists( dirname( $file ) . '/' . $meta['file'] ) ) {
				$file = dirname( $file ) . '/' . $meta['file'];
				break;
			}
		}
		if ( ! file_exists( $file ) || filesize( $file ) > 15 * MB_IN_BYTES ) {
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
		$sx    = (int) round( ( $sw - $cw ) / 2 );
		$sy    = (int) round( ( $sh - $ch ) / 2 );
		imagecopyresampled( $dst, $src, $x, $y, $sx, $sy, $w, $h, $cw, $ch );
	}

	private static function rounded( $im, int $x1, int $y1, int $x2, int $y2, int $radius, int $color ) {
		$radius = (int) min( $radius, ( $x2 - $x1 ) / 2, ( $y2 - $y1 ) / 2 );
		imagefilledrectangle( $im, $x1 + $radius, $y1, $x2 - $radius, $y2, $color );
		imagefilledrectangle( $im, $x1, $y1 + $radius, $x2, $y2 - $radius, $color );
		foreach ( array( array( $x1 + $radius, $y1 + $radius ), array( $x2 - $radius, $y1 + $radius ), array( $x1 + $radius, $y2 - $radius ), array( $x2 - $radius, $y2 - $radius ) ) as $c ) {
			imagefilledellipse( $im, $c[0], $c[1], 2 * $radius, 2 * $radius, $color );
		}
	}

	private static function circle( $im, $cx, $cy, $d, int $color ) {
		imagefilledellipse( $im, (int) $cx, (int) $cy, (int) $d, (int) $d, $color );
	}

	/**
	 * Širina i pomaci teksta (GD koristi 96 dpi: veličina je u točkama).
	 */
	private static function text_box( float $size, string $font, string $text ): array {
		$b = imagettfbbox( $size, 0, $font, $text );
		return array(
			'w'      => (int) ( max( $b[2], $b[4] ) - min( $b[0], $b[6] ) ),
			'left'   => (int) min( $b[0], $b[6] ),
			'top'    => (int) min( $b[5], $b[7] ),
			'bottom' => (int) max( $b[1], $b[3] ),
		);
	}

	/**
	 * Tekst s poravnanjem po visini: 'top' (gornji rub velikih slova) ili 'middle'.
	 */
	private static function text( $im, float $size, int $x, int $y, int $color, string $font, string $text, string $valign ) {
		$cap = self::text_box( $size, $font, 'HŽ' ); // visina velikih slova (s kvačicom)
		$ref = self::text_box( $size, $font, 'H' );
		if ( 'top' === $valign ) {
			$base = $y - $cap['top'];
		} else {
			$base = (int) round( $y - $ref['top'] / 2 );
		}
		imagettftext( $im, $size, 0, $x, $base, $color, $font, $text );
	}

	private static function text_center( $im, float $size, $cx, $cy, int $color, string $font, string $text ) {
		$b = self::text_box( $size, $font, $text );
		$x = (int) round( $cx - $b['w'] / 2 - $b['left'] );
		$y = (int) round( $cy - ( $b['top'] + $b['bottom'] ) / 2 );
		imagettftext( $im, $size, 0, $x, $y, $color, $font, $text );
	}

	private static function ellipsis( string $text, float $size, string $font, int $max ): string {
		if ( self::text_box( $size, $font, $text )['w'] <= $max ) {
			return $text;
		}
		while ( mb_strlen( $text ) > 1 && self::text_box( $size, $font, $text . '…' )['w'] > $max ) {
			$text = mb_substr( $text, 0, -1 );
		}
		return rtrim( $text ) . '…';
	}

	/**
	 * Naziv u najviše dva reda; font se smanjuje od 46 do 34 točke, a ako ni tada
	 * ne stane, drugi red završava s "…".
	 */
	private static function fit_title( string $title, string $font, int $max ): array {
		$words = preg_split( '/\s+/u', trim( $title ) ) ?: array( $title );
		for ( $pt = 46; $pt >= 34; $pt -= 2 ) {
			$size  = $pt * self::S;
			$lines = self::wrap( $words, $size, $font, $max );
			if ( count( $lines ) <= 2 && self::lines_fit( $lines, $size, $font, $max ) ) {
				return array(
					'size'  => $size,
					'lines' => $lines,
				);
			}
		}
		$size  = 34 * self::S;
		$lines = self::wrap( $words, $size, $font, $max );
		$first = array_shift( $lines );
		$rest  = implode( ' ', $lines );
		return array(
			'size'  => $size,
			'lines' => array_values( array_filter( array( self::ellipsis( (string) $first, $size, $font, $max ), '' !== $rest ? self::ellipsis( $rest, $size, $font, $max ) : '' ) ) ),
		);
	}

	private static function wrap( array $words, float $size, string $font, int $max ): array {
		$lines   = array();
		$current = '';
		foreach ( $words as $word ) {
			$try = '' === $current ? $word : $current . ' ' . $word;
			if ( '' !== $current && self::text_box( $size, $font, $try )['w'] > $max ) {
				$lines[] = $current;
				$current = $word;
			} else {
				$current = $try;
			}
		}
		if ( '' !== $current ) {
			$lines[] = $current;
		}
		return $lines;
	}

	private static function lines_fit( array $lines, float $size, string $font, int $max ): bool {
		foreach ( $lines as $line ) {
			if ( self::text_box( $size, $font, $line )['w'] > $max ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Jednostavne bijele ikone (emojiji se u slici ne prikazuju pouzdano).
	 * Obrisi se crtaju kao bijeli lik pa unutrašnjost u boji pozadine.
	 */
	private static function icon( $im, string $type, int $x, int $cy, int $size, int $white, int $bg ) {
		$s  = self::S;
		$t  = 3 * $s; // debljina crte
		$ht = intdiv( $t, 2 );
		$cx = $x + intdiv( $size, 2 );
		switch ( $type ) {
			case 'date':
				$top = $cy - (int) ( $size * 0.42 );
				$bot = $cy + (int) ( $size * 0.45 );
				self::rounded( $im, $x + 2 * $s, $top, $x + $size - 2 * $s, $bot, 5 * $s, $white );
				self::rounded( $im, $x + 2 * $s + $t, $top + (int) ( $size * 0.30 ), $x + $size - 2 * $s - $t, $bot - $t, 3 * $s, $bg );
				imagefilledrectangle( $im, $x + (int) ( $size * 0.28 ) - $ht, $top - 5 * $s, $x + (int) ( $size * 0.28 ) + $ht, $top + 5 * $s, $white );
				imagefilledrectangle( $im, $x + (int) ( $size * 0.72 ) - $ht, $top - 5 * $s, $x + (int) ( $size * 0.72 ) + $ht, $top + 5 * $s, $white );
				break;
			case 'place':
				$r  = (int) ( $size * 0.34 );
				$hy = $cy - (int) ( $size * 0.12 );
				self::circle( $im, $cx, $hy, 2 * $r, $white );
				$points = array( $cx - (int) ( $r * 0.86 ), $hy + (int) ( $r * 0.5 ), $cx + (int) ( $r * 0.86 ), $hy + (int) ( $r * 0.5 ), $cx, $cy + (int) ( $size * 0.48 ) );
				if ( PHP_VERSION_ID >= 80000 ) {
					imagefilledpolygon( $im, $points, $white );
				} else {
					imagefilledpolygon( $im, $points, 3, $white ); // PHP 7.4
				}
				self::circle( $im, $cx, $hy, (int) ( $r * 0.9 ), $bg );
				break;
			case 'duration':
				$d = (int) ( $size * 0.92 );
				self::circle( $im, $cx, $cy, $d, $white );
				self::circle( $im, $cx, $cy, $d - 2 * $t, $bg );
				imagefilledrectangle( $im, $cx - $ht, $cy - (int) ( $d * 0.30 ), $cx + $ht, $cy, $white );
				imagefilledrectangle( $im, $cx, $cy - $ht, $cx + (int) ( $d * 0.24 ), $cy + $ht, $white );
				break;
			case 'price':
				$d = (int) ( $size * 0.92 );
				self::circle( $im, $cx, $cy, $d, $white );
				self::circle( $im, $cx, $cy, $d - 2 * $t, $bg );
				self::text_center( $im, 13 * $s, $cx, $cy, $white, self::font( 'bold' ), '€' );
				break;
		}
	}

	/**
	 * Ikona aktivnosti iz fonta WpTravellyja; ako je nema, prvo slovo naziva.
	 */
	private static function activity( $im, array $act, $cx, $cy, $d, int $color, string $bold ) {
		$glyph = self::glyph( (string) $act['icon'] );
		if ( $glyph ) {
			self::text_center( $im, $d * 0.30, $cx, $cy, $color, $glyph['font'], $glyph['char'] );
			return;
		}
		$letter = mb_strtoupper( mb_substr( (string) $act['name'], 0, 1 ) );
		self::text_center( $im, $d * 0.30, $cx, $cy, $color, $bold, $letter );
	}

	/**
	 * Klasa ikone (npr. "fas fa-mountain", "mi mi-hiking") → font i znak.
	 *
	 * @return array{font: string, char: string}|null
	 */
	private static function glyph( string $classes ) {
		if ( '' === trim( $classes ) || ! defined( 'TTBM_PLUGIN_DIR' ) ) {
			return null;
		}
		$maps  = self::glyph_maps();
		$base  = trailingslashit( TTBM_PLUGIN_DIR ) . 'assets/';
		$list  = preg_split( '/\s+/', strtolower( trim( $classes ) ) );
		foreach ( $list as $class ) {
			if ( 0 === strpos( $class, 'mi-' ) && isset( $maps['mi'][ substr( $class, 3 ) ] ) ) {
				$font = $base . 'mage-icon/fonts/mage-icon.woff';
				if ( file_exists( $font ) ) {
					return array(
						'font' => $font,
						'char' => self::chr( $maps['mi'][ substr( $class, 3 ) ] ),
					);
				}
			}
			if ( 0 === strpos( $class, 'fa-' ) && isset( $maps['fa'][ substr( $class, 3 ) ] ) ) {
				$style = in_array( 'fab', $list, true ) || in_array( 'fa-brands', $list, true ) ? 'fa-brands-400.ttf'
					: ( in_array( 'far', $list, true ) || in_array( 'fa-regular', $list, true ) ? 'fa-regular-400.ttf' : 'fa-solid-900.ttf' );
				$font  = $base . 'webfonts/' . $style;
				if ( file_exists( $font ) ) {
					return array(
						'font' => $font,
						'char' => self::chr( $maps['fa'][ substr( $class, 3 ) ] ),
					);
				}
			}
		}
		return null;
	}

	private static function chr( int $code ): string {
		return mb_chr( $code, 'UTF-8' );
	}

	/**
	 * Mape naziv ikone → kod znaka iz CSS-a Font Awesomea i Mage Icons (u WpTravellyju).
	 */
	private static function glyph_maps(): array {
		if ( null !== self::$glyphs ) {
			return self::$glyphs;
		}
		$version = self::GLYPHS . '|' . ( defined( 'TTBM_PLUGIN_VERSION' ) ? TTBM_PLUGIN_VERSION : '' );
		$cached  = get_transient( 'plan_a_izleti_glyphs' );
		if ( is_array( $cached ) && ( $cached['v'] ?? '' ) === $version ) {
			return self::$glyphs = $cached;
		}
		$maps = array(
			'v'  => $version,
			'fa' => array(),
			'mi' => array(),
		);
		$base = trailingslashit( TTBM_PLUGIN_DIR ) . 'assets/';
		$fa   = file_exists( $base . 'all.min.css' ) ? (string) file_get_contents( $base . 'all.min.css' ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		// Font Awesome 6: .fa-mountain{--fa:"\f6fc"} (i popisi selektora za zamjenska imena).
		if ( preg_match_all( '/((?:\.fa-[a-z0-9-]+,?)+)\{--fa:"\\\\([0-9a-f]+)"/i', $fa, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $match ) {
				foreach ( explode( ',', $match[1] ) as $selector ) {
					$maps['fa'][ substr( trim( $selector ), 4 ) ] = hexdec( $match[2] );
				}
			}
		}
		// Starija oblika Font Awesomea: .fa-mountain:before{content:"\f6fc"}.
		if ( preg_match_all( '/((?:\.fa-[a-z0-9-]+:+before,?)+)\{content:"\\\\([0-9a-f]+)"/i', $fa, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $match ) {
				foreach ( explode( ',', $match[1] ) as $selector ) {
					$name = preg_replace( '/:+before$/', '', substr( trim( $selector ), 4 ) );
					if ( ! isset( $maps['fa'][ $name ] ) ) {
						$maps['fa'][ $name ] = hexdec( $match[2] );
					}
				}
			}
		}
		$mi = file_exists( $base . 'mage-icon/css/mage-icon.css' ) ? (string) file_get_contents( $base . 'mage-icon/css/mage-icon.css' ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		// Znak je zapisan kao "\f637" ili kao doslovni znak.
		if ( preg_match_all( '/\.mi-([a-z0-9-]+)::?before\s*\{\s*content:\s*"(\\\\[0-9a-f]+|[^"\\\\])"/iu', $mi, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $match ) {
				$code = '\\' === $match[2][0] ? hexdec( substr( $match[2], 1 ) ) : mb_ord( $match[2], 'UTF-8' );
				if ( $code ) {
					$maps['mi'][ strtolower( $match[1] ) ] = $code;
				}
			}
		}
		set_transient( 'plan_a_izleti_glyphs', $maps, WEEK_IN_SECONDS );
		return self::$glyphs = $maps;
	}
}
