<?php
/**
 * Jezgra: čišćenje slika (ponovno spremanje bez metapodataka), zamjena datoteke NA ISTOJ ADRESI (SEO), nova slika u medijima,
 * ?v=<vrijeme zamjene> na zamijenjenim slikama (nginx/preglednik inače daju staru kopiju), REST za rad iz skripte, dnevnik.
 */

defined( 'ABSPATH' ) || exit;

final class Domestic_Slike {

	const NS       = 'domestic-slike/v1';
	const VERZIJE  = 'domestic_slike_verzije';
	const DNEVNIK  = 'domestic_slike_dnevnik';
	const PROVJERA = '_dss_za_provjeru';

	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'rute' ) );
		add_filter( 'wp_get_attachment_url', array( __CLASS__, 'verzija_url' ), 99, 2 );
		add_filter( 'wp_get_attachment_image_src', array( __CLASS__, 'verzija_src' ), 99, 2 );
		add_filter( 'wp_calculate_image_srcset', array( __CLASS__, 'verzija_srcset' ), 99, 5 );
		add_action( 'dss_ciscenje', array( 'Domestic_Slike_Paket', 'ocisti_stare' ) );
		if ( ! wp_next_scheduled( 'dss_ciscenje' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'dss_ciscenje' );
		}
	}

	/* ---------- ?v= na zamijenjenim slikama ---------- */

	private static function verzija( int $id ): int {
		$v = 0;
		foreach ( array( self::VERZIJE, 'domestic_pomocnik_verzije' ) as $o ) { // i zamjene iz pomoćnika probe
			$x = get_option( $o, array() );
			$v = max( $v, is_array( $x ) ? (int) ( $x[ $id ] ?? 0 ) : 0 );
		}
		return $v;
	}

	private static function dodaj_v( string $url, int $v ): string {
		return $v && false === strpos( $url, 'v=' ) ? add_query_arg( 'v', $v, $url ) : $url;
	}

	public static function verzija_url( $url, $id ) {
		return is_string( $url ) ? self::dodaj_v( $url, self::verzija( (int) $id ) ) : $url;
	}

	public static function verzija_src( $img, $id ) {
		if ( is_array( $img ) && ! empty( $img[0] ) ) {
			$img[0] = self::dodaj_v( $img[0], self::verzija( (int) $id ) );
		}
		return $img;
	}

	public static function verzija_srcset( $sources, $size, $src, $meta, $id ) {
		$v = self::verzija( (int) $id );
		foreach ( (array) $sources as $k => $s ) {
			$sources[ $k ]['url'] = self::dodaj_v( $s['url'], $v );
		}
		return $sources;
	}

	/* ---------- slike ---------- */

	/** GD slika iz datoteke (jpg, png, webp) – uvijek truecolor. */
	public static function ucitaj( string $put ) {
		$im = @imagecreatefromstring( (string) file_get_contents( $put ) ); // phpcs:ignore
		if ( ! $im ) {
			return false;
		}
		if ( ! imageistruecolor( $im ) ) {
			imagepalettetotruecolor( $im );
		}
		imagealphablending( $im, false );
		imagesavealpha( $im, true );
		return $im;
	}

	/**
	 * Ponovno spremanje bez metapodataka (EXIF, C2PA…): jpg 85 % progresivno; png – fotografije s paletom 256 boja
	 * (višestruko manje, ista adresa), ikone s prozirnošću bez palete.
	 */
	public static function spremi_cisto( $im, string $cilj, string $format, bool $paleta = false ): bool {
		if ( 'webp' === $format ) { // GD ne upisuje metapodatke u WebP
			imagesavealpha( $im, true );
			return imagewebp( $im, $cilj, 85 );
		}
		if ( 'png' === $format ) {
			if ( $paleta ) {
				$k = imagecreatetruecolor( imagesx( $im ), imagesy( $im ) );
				imagecopy( $k, $im, 0, 0, 0, 0, imagesx( $im ), imagesy( $im ) );
				imagetruecolortopalette( $k, true, 256 );
				$ok = imagepng( $k, $cilj, 9 );
				imagedestroy( $k );
				$ok && Domestic_Slike_Meta::ocisti( $cilj );
				return $ok;
			}
			imagesavealpha( $im, true );
			$ok = imagepng( $im, $cilj, 9 );
			$ok && Domestic_Slike_Meta::ocisti( $cilj );
			return $ok;
		}
		$k = imagecreatetruecolor( imagesx( $im ), imagesy( $im ) );
		imagefill( $k, 0, 0, imagecolorallocate( $k, 255, 255, 255 ) );
		imagealphablending( $k, true );
		imagecopy( $k, $im, 0, 0, 0, 0, imagesx( $im ), imagesy( $im ) );
		imageinterlace( $k, true );
		$ok = imagejpeg( $k, $cilj, 85 );
		imagedestroy( $k );
		$ok && Domestic_Slike_Meta::ocisti( $cilj ); // GD upisuje komentar „CREATOR: gd-jpeg …”
		return $ok;
	}

	/**
	 * Zamjena datoteke postojeće slike NA ISTOJ ADRESI: naziv, naslov i alt ostaju; stare izvedenice se brišu i rade nove.
	 * $izvor = bilo koja slika (jpg/png/webp) – sprema se čisto u formatu postojeće datoteke.
	 *
	 * @return array|WP_Error
	 */
	public static function zamijeni( int $id, string $izvor, ?bool $paleta = null ) {
		$put = get_attached_file( $id );
		if ( ! $put || 'attachment' !== get_post_type( $id ) || ! wp_attachment_is_image( $id ) ) {
			return new WP_Error( 'dss', 'Nema slike s tim ID-om.', array( 'status' => 404 ) );
		}
		$ext = strtolower( pathinfo( $put, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp' ), true ) || ( 'webp' === $ext && ! function_exists( 'imagewebp' ) ) ) {
			return new WP_Error( 'dss', 'Zamjena radi za .jpg, .png i .webp (ova je .' . $ext . ').', array( 'status' => 400 ) );
		}
		$im = self::ucitaj( $izvor );
		if ( ! $im ) {
			return new WP_Error( 'dss', 'Nova datoteka nije slika.', array( 'status' => 400 ) );
		}
		$format = in_array( $ext, array( 'png', 'webp' ), true ) ? $ext : 'jpg';
		$paleta = null === $paleta ? ( 'png' === $format && ! self::ima_prozirnost( $im ) ) : $paleta;
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$meta = wp_get_attachment_metadata( $id );
		$dir  = trailingslashit( dirname( $put ) );
		foreach ( (array) ( $meta['sizes'] ?? array() ) as $v ) {
			if ( ! empty( $v['file'] ) && file_exists( $dir . $v['file'] ) ) {
				wp_delete_file( $dir . $v['file'] );
			}
		}
		if ( ! empty( $meta['original_image'] ) && file_exists( $dir . $meta['original_image'] ) ) {
			wp_delete_file( $dir . $meta['original_image'] ); // „-scaled” zamjena: stari izvornik bi ostao star
		}
		$ok = self::spremi_cisto( $im, $put, $format, $paleta );
		imagedestroy( $im );
		if ( ! $ok ) {
			return new WP_Error( 'dss', 'Spremanje datoteke nije uspjelo.', array( 'status' => 500 ) );
		}
		if ( 'site-icon' === get_post_meta( $id, '_wp_attachment_context', true ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-site-icon.php';
			add_filter( 'intermediate_image_sizes_advanced', array( new WP_Site_Icon(), 'additional_sizes' ) );
		}
		add_filter( 'big_image_size_threshold', '__return_false' ); // isti naziv datoteke, bez novog „-scaled”
		$nm = wp_generate_attachment_metadata( $id, $put );
		remove_filter( 'big_image_size_threshold', '__return_false' );
		if ( $paleta && function_exists( 'imagetruecolortopalette' ) ) {
			foreach ( (array) ( $nm['sizes'] ?? array() ) as $k => $v ) {
				$fp = $dir . $v['file'];
				$x  = file_exists( $fp ) ? imagecreatefrompng( $fp ) : false;
				if ( $x && imagetruecolortopalette( $x, true, 256 ) && imagepng( $x, $fp, 9 ) ) {
					clearstatcache( true, $fp );
					$nm['sizes'][ $k ]['filesize'] = filesize( $fp );
				}
			}
		}
		wp_update_attachment_metadata( $id, $nm );
		clean_post_cache( $id );
		$ver        = get_option( self::VERZIJE, array() );
		$ver        = is_array( $ver ) ? $ver : array();
		$ver[ $id ] = time();
		update_option( self::VERZIJE, $ver, false );
		clearstatcache( true, $put );
		return array( 'id' => $id, 'url' => wp_get_attachment_url( $id ), 'velicina' => filesize( $put ), 'sirina' => $nm['width'] ?? null, 'visina' => $nm['height'] ?? null );
	}

	public static function ima_prozirnost( $im ): bool {
		$w = imagesx( $im );
		$h = imagesy( $im );
		for ( $y = 0; $y < $h; $y += max( 1, (int) ( $h / 60 ) ) ) {
			for ( $x = 0; $x < $w; $x += max( 1, (int) ( $w / 60 ) ) ) {
				if ( ( imagecolorat( $im, $x, $y ) >> 24 ) & 0x7F ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Nova slika u medijima (za studijsku, posluživanje, ikone). $put = već očišćena datoteka; naziv = naziv datoteke na webu.
	 *
	 * @return int|WP_Error
	 */
	public static function nova( string $put, string $naziv, string $alt, string $naslov, int $roditelj = 0 ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$tmp = wp_tempnam( $naziv );
		copy( $put, $tmp );
		$id = media_handle_sideload( array( 'name' => sanitize_file_name( $naziv ), 'tmp_name' => $tmp ), $roditelj, $naslov );
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			return $id;
		}
		update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		return (int) $id;
	}

	/* ---------- dnevnik i „za provjeru” ---------- */

	public static function zapisi( array $z ): void {
		$d = get_option( self::DNEVNIK, array() );
		$d = is_array( $d ) ? $d : array();
		array_unshift( $d, array_merge( array( 't' => time(), 'korisnik' => get_current_user_id() ), $z ) );
		update_option( self::DNEVNIK, array_slice( $d, 0, 100 ), false );
	}

	public static function dodaj_provjeru( int $proizvod, string $tekst, string $izvor ): void {
		$p   = get_post_meta( $proizvod, self::PROVJERA, true );
		$p   = is_array( $p ) ? $p : array();
		$p[] = array( 't' => time(), 'tekst' => $tekst, 'izvor' => $izvor );
		update_post_meta( $proizvod, self::PROVJERA, $p );
	}

	/* ---------- REST (rad iz skripte; prava kao u adminu) ---------- */

	public static function rute(): void {
		$perm = static function () {
			return current_user_can( Domestic_Menu::CAP ) && current_user_can( 'upload_files' );
		};
		register_rest_route( self::NS, '/paket', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_paket' ), 'permission_callback' => $perm ) );
		register_rest_route( self::NS, '/paket/(?P<token>[a-zA-Z0-9]{20})', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_uvoz' ), 'permission_callback' => $perm ) );
		register_rest_route(
			self::NS,
			'/paket/(?P<token>[a-zA-Z0-9]{20})',
			array(
				'methods'             => 'DELETE',
				'permission_callback' => $perm,
				'callback'            => static function ( WP_REST_Request $r ) {
					Domestic_Slike_Paket::obrisi_rad( (string) $r['token'] );
					return array( 'obrisano' => true );
				},
			)
		);
		register_rest_route( self::NS, '/ocisti', array( 'methods' => 'POST', 'callback' => array( 'Domestic_Slike_Meta', 'rest_ocisti' ), 'permission_callback' => $perm ) );
		register_rest_route( self::NS, '/medij/(?P<id>\d+)', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_medij' ), 'permission_callback' => $perm ) );
		register_rest_route(
			self::NS,
			'/provjera',
			array(
				'methods'             => 'POST',
				'permission_callback' => $perm,
				'callback'            => static function ( WP_REST_Request $r ) {
					$pid = (int) $r['proizvod'];
					if ( 'product' !== get_post_type( $pid ) || '' === trim( (string) $r['tekst'] ) ) {
						return new WP_Error( 'dss', 'Proizvod i tekst su obavezni.', array( 'status' => 400 ) );
					}
					self::dodaj_provjeru( $pid, sanitize_textarea_field( (string) $r['tekst'] ), sanitize_text_field( (string) ( $r['izvor'] ?? 'ručno' ) ) );
					return get_post_meta( $pid, self::PROVJERA, true );
				},
			)
		);
		register_rest_route( self::NS, '/dnevnik', array( 'methods' => 'GET', 'callback' => static function () { return get_option( self::DNEVNIK, array() ); }, 'permission_callback' => $perm ) );
	}

	/** POST multipart „zip” → analiza paketa (token, prijedlog proizvoda, posluživanje, ikone). Ništa se još ne mijenja. */
	public static function rest_paket( WP_REST_Request $req ) {
		$f = $req->get_file_params()['zip'] ?? null;
		if ( ! $f || UPLOAD_ERR_OK !== ( $f['error'] ?? 1 ) || ! is_uploaded_file( $f['tmp_name'] ) ) {
			return new WP_Error( 'dss', 'Nedostaje zip (polje „zip”).', array( 'status' => 400 ) );
		}
		$a = Domestic_Slike_Paket::pripremi( $f['tmp_name'], (string) $f['name'] );
		return is_wp_error( $a ) ? $a : Domestic_Slike_Paket::za_rest( $a );
	}

	/** POST JSON {proizvod, cilj, ikone_izvor, posluzivanje[], zadrzi[], dodatne} → uvoz (sve neobavezno – zadano iz analize). */
	public static function rest_uvoz( WP_REST_Request $req ) {
		return Domestic_Slike_Paket::uvezi( (string) $req['token'], (array) $req->get_json_params() );
	}

	/** POST multipart „datoteka” → zamjena slike na istoj adresi (paleta=0/1 neobavezno). */
	public static function rest_medij( WP_REST_Request $req ) {
		$f = $req->get_file_params()['datoteka'] ?? null;
		if ( ! $f || UPLOAD_ERR_OK !== ( $f['error'] ?? 1 ) || ! is_uploaded_file( $f['tmp_name'] ) ) {
			return new WP_Error( 'dss', 'Nedostaje datoteka (polje „datoteka”).', array( 'status' => 400 ) );
		}
		$p = $req->get_param( 'paleta' );
		$r = self::zamijeni( (int) $req['id'], $f['tmp_name'], null === $p ? null : (bool) $p );
		if ( ! is_wp_error( $r ) ) {
			self::zapisi( array( 'vrsta' => 'zamjena', 'slika' => (int) $req['id'] ) );
		}
		return $r;
	}
}
