<?php
/**
 * Ikone aplikacije generirane iz odabranog logotipa (GD).
 *
 * Logotip se smješta na kvadratnu bijelu podlogu:
 * - "any" ikone (192, 512) i Apple ikona (180) s malim rubom,
 * - "maskable" ikona (512) s logotipom unutar sigurne zone (Android ju izrezuje u krug ili kapljicu).
 *
 * @package Plan_A_Aplikacija
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_App_Icons {

	const OPTION = 'plan_a_app_icons';
	const DIR    = 'plan-a-aplikacija';

	/**
	 * Veličina => [ naziv datoteke, udio logotipa u ikoni ].
	 */
	private static function specs(): array {
		return array(
			'192'          => array( 'icon-192', 0.86 ),
			'512'          => array( 'icon-512', 0.86 ),
			'maskable-512' => array( 'maskable-512', 0.62 ),
			'apple-180'    => array( 'apple-180', 0.82 ),
		);
	}

	/**
	 * @return array{icons: array<string, string>, error: string}
	 */
	public static function get(): array {
		$data = get_option( self::OPTION, array() );
		$data = is_array( $data ) ? $data : array();
		return array(
			'icons' => is_array( $data['icons'] ?? null ) ? $data['icons'] : array(),
			'error' => (string) ( $data['error'] ?? '' ),
		);
	}

	/**
	 * Adresa ikone; bez logotipa koristi se ikona stranice (Prilagodba > Identitet stranice).
	 */
	public static function url( string $key ): string {
		$icons = self::get()['icons'];
		if ( ! empty( $icons[ $key ] ) ) {
			return (string) $icons[ $key ];
		}
		$size = (int) preg_replace( '/\D/', '', $key );
		return $size ? (string) get_site_icon_url( $size ) : '';
	}

	public static function regenerate() {
		self::delete_files();

		$logo_id = (int) Plan_A_App_Settings::get( 'logo_id' );
		$result  = array(
			'icons' => array(),
			'error' => '',
		);

		if ( ! $logo_id ) {
			update_option( self::OPTION, $result, false );
			return;
		}
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$result['error'] = __( 'Na poslužitelju nije dostupna PHP knjižnica GD, pa se ikone ne mogu generirati.', 'plan-a-aplikacija' );
			update_option( self::OPTION, $result, false );
			return;
		}

		$file = get_attached_file( $logo_id );
		$data = ( $file && is_readable( $file ) ) ? file_get_contents( $file ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- lokalna datoteka.
		$src  = $data ? @imagecreatefromstring( $data ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- neispravna slika vraća false.
		if ( ! $src ) {
			$result['error'] = __( 'Logotip se ne može pročitati. Odaberite PNG, JPG ili WebP sliku (SVG nije podržan).', 'plan-a-aplikacija' );
			update_option( self::OPTION, $result, false );
			return;
		}

		$upload = wp_upload_dir();
		$dir    = trailingslashit( $upload['basedir'] ) . self::DIR;
		if ( ! wp_mkdir_p( $dir ) ) {
			$result['error'] = __( 'Mapa za ikone u uploads se ne može stvoriti.', 'plan-a-aplikacija' );
			update_option( self::OPTION, $result, false );
			imagedestroy( $src );
			return;
		}

		$hash = substr( md5( $logo_id . '|' . filemtime( $file ) . '|' . PLAN_A_APP_VERSION ), 0, 8 );
		foreach ( self::specs() as $key => $spec ) {
			list( $name, $ratio ) = $spec;
			$size = (int) preg_replace( '/\D/', '', $key );
			$path = $dir . '/' . $name . '-' . $hash . '.png';
			if ( self::render( $src, $size, $ratio, $path ) ) {
				$result['icons'][ $key ] = trailingslashit( $upload['baseurl'] ) . self::DIR . '/' . basename( $path );
			}
		}
		imagedestroy( $src );

		if ( count( $result['icons'] ) !== count( self::specs() ) ) {
			$result['error'] = __( 'Neke ikone nije bilo moguće spremiti.', 'plan-a-aplikacija' );
		}
		update_option( self::OPTION, $result, false );
	}

	/**
	 * @param resource|\GdImage $src
	 */
	private static function render( $src, int $size, float $ratio, string $path ): bool {
		$canvas = imagecreatetruecolor( $size, $size );
		imagefill( $canvas, 0, 0, imagecolorallocate( $canvas, 255, 255, 255 ) );
		imagealphablending( $canvas, true );

		$w     = imagesx( $src );
		$h     = imagesy( $src );
		$box   = $size * $ratio;
		$scale = min( $box / $w, $box / $h );
		$dw    = (int) round( $w * $scale );
		$dh    = (int) round( $h * $scale );

		imagecopyresampled( $canvas, $src, (int) ( ( $size - $dw ) / 2 ), (int) ( ( $size - $dh ) / 2 ), 0, 0, $dw, $dh, $w, $h );
		$ok = imagepng( $canvas, $path, 9 );
		imagedestroy( $canvas );
		return (bool) $ok;
	}

	public static function delete_files() {
		$upload = wp_upload_dir();
		$dir    = trailingslashit( $upload['basedir'] ) . self::DIR;
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( (array) glob( $dir . '/*.png' ) as $file ) {
			wp_delete_file( $file );
		}
	}

	public static function delete_all() {
		self::delete_files();
		$upload = wp_upload_dir();
		$dir    = trailingslashit( $upload['basedir'] ) . self::DIR;
		if ( is_dir( $dir ) ) {
			@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- prazna mapa dodatka.
		}
		delete_option( self::OPTION );
	}
}
