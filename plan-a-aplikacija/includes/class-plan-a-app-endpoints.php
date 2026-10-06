<?php
/**
 * Adrese koje dodatak poslužuje: /?plan-a-app=manifest|sw|offline|ping
 *
 * Service worker se poslužuje s početne adrese stranice, pa smije upravljati
 * cijelom stranicom. Ne koriste se rewrite pravila ni datoteke u korijenu weba,
 * pa deaktivacija dodatka ne ostavlja ništa na poslužitelju.
 *
 * @package Plan_A_Aplikacija
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_App_Endpoints {

	public static function init() {
		// Nakon WooCommercea (init 0–10), prije teme i predložaka.
		add_action( 'init', array( __CLASS__, 'maybe_serve' ), 99 );
	}

	public static function maybe_serve() {
		if ( ! isset( $_GET['plan-a-app'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- javni, samo za čitanje.
			return;
		}
		$what = sanitize_key( wp_unslash( $_GET['plan-a-app'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		switch ( $what ) {
			case 'ping':
				self::send_ping();
				break;
			case 'sw':
				self::send_service_worker();
				break;
			case 'manifest':
				self::send_manifest();
				break;
			case 'offline':
				self::send_offline_page();
				break;
			case 'stat':
				Plan_A_App_Stats::record();
				break;
			default:
				return;
		}
		exit;
	}

	private static function no_cache() {
		nocache_headers();
		header( 'X-Robots-Tag: noindex' );
	}

	/**
	 * Service worker ovdje provjerava je li aplikacija još uključena.
	 * Kad dodatak nije aktivan, ova adresa vraća običnu HTML stranicu, a kad je
	 * aplikacija isključena u postavkama, vraća active=false. U oba slučaja se
	 * service worker sam odjavljuje i briše predmemoriju.
	 */
	private static function send_ping() {
		self::no_cache();
		header( 'Content-Type: application/json; charset=utf-8' );
		echo wp_json_encode(
			array(
				'planAApp' => true,
				'active'   => (bool) Plan_A_App_Settings::get( 'enabled' ),
				'version'  => Plan_A_App_Settings::cache_version(),
			)
		);
	}

	private static function send_manifest() {
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );
		echo wp_json_encode( self::manifest(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
	}

	public static function manifest(): array {
		$icons = array();
		foreach ( array( '192' => '192x192', '512' => '512x512' ) as $key => $sizes ) {
			$url = Plan_A_App_Icons::url( $key );
			if ( $url ) {
				$icons[] = array(
					'src'     => $url,
					'sizes'   => $sizes,
					'type'    => 'image/png',
					'purpose' => 'any',
				);
			}
		}
		$maskable = Plan_A_App_Icons::url( 'maskable-512' );
		if ( $maskable && false !== strpos( $maskable, Plan_A_App_Icons::DIR ) ) {
			$icons[] = array(
				'src'     => $maskable,
				'sizes'   => '512x512',
				'type'    => 'image/png',
				'purpose' => 'maskable',
			);
		}

		return array(
			'id'               => Plan_A_App_Settings::home_path(),
			'name'             => 'Plan A',
			'short_name'       => 'Plan A',
			'description'      => (string) get_bloginfo( 'description' ),
			'lang'             => 'hr',
			'dir'              => 'ltr',
			'start_url'        => Plan_A_App_Settings::url( 'start_url' ),
			'scope'            => Plan_A_App_Settings::home_path(),
			'display'          => 'standalone',
			'orientation'      => 'portrait',
			'theme_color'      => (string) Plan_A_App_Settings::get( 'theme_color' ),
			'background_color' => '#ffffff',
			'icons'            => $icons,
		);
	}

	/**
	 * Putanje koje service worker nikad ne sprema i uvijek šalje na mrežu.
	 */
	public static function network_only_paths(): array {
		$home  = untrailingslashit( Plan_A_App_Settings::home_path() );
		$paths = array( '/cart/', '/checkout/', '/my-account/', '/wp-admin/', '/wp-login.php', '/wp-json/', '/wp-cron.php', '/xmlrpc.php', '/wc-api/' );
		$paths = array_map(
			static function ( $path ) use ( $home ) {
				return $home . $path;
			},
			$paths
		);

		// Stvarne adrese WooCommerce stranica (npr. /kosarica/, /placanje/, /moj-racun/).
		if ( function_exists( 'wc_get_page_id' ) ) {
			foreach ( array( 'cart', 'checkout', 'myaccount' ) as $page ) {
				$id = (int) wc_get_page_id( $page );
				if ( $id > 0 ) {
					$paths[] = trailingslashit( (string) wp_parse_url( get_permalink( $id ), PHP_URL_PATH ) );
				}
			}
		}
		$paths[] = trailingslashit( (string) wp_parse_url( Plan_A_App_Settings::url( 'nav_cart' ), PHP_URL_PATH ) );

		return array_values( array_unique( array_filter( $paths, static function ( $path ) {
			return '' !== trim( (string) $path, '/' );
		} ) ) );
	}

	private static function send_service_worker() {
		self::no_cache();
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: ' . Plan_A_App_Settings::home_path() );

		$config = array(
			'version'          => Plan_A_App_Settings::cache_version(),
			'offlineUrl'       => Plan_A_App_Settings::endpoint_url( 'offline' ),
			'pingUrl'          => Plan_A_App_Settings::endpoint_url( 'ping' ),
			'precache'         => array_values( array_filter( array( Plan_A_App_Settings::endpoint_url( 'offline' ), Plan_A_App_Icons::url( '192' ) ) ) ),
			'networkOnlyPaths' => self::network_only_paths(),
		);

		echo '/* Plan A aplikacija ' . esc_js( PLAN_A_APP_VERSION ) . " */\n";
		echo 'const PLAN_A = ' . wp_json_encode( $config, JSON_UNESCAPED_SLASHES ) . ";\n";
		readfile( PLAN_A_APP_DIR . '/assets/sw.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- statična datoteka dodatka.
	}

	private static function send_offline_page() {
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'Cache-Control: public, max-age=86400' );
		header( 'X-Robots-Tag: noindex' );
		$color = (string) Plan_A_App_Settings::get( 'theme_color' );
		$icon  = Plan_A_App_Icons::url( '192' );
		?>
<!doctype html>
<html lang="hr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<meta name="theme-color" content="<?php echo esc_attr( $color ); ?>">
<title><?php esc_html_e( 'Nema internetske veze – Plan A', 'plan-a-aplikacija' ); ?></title>
<style>
	html, body { height: 100%; margin: 0; }
	body { display: flex; align-items: center; justify-content: center; padding: 24px; box-sizing: border-box; background: #f6f7f9; color: #222; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; text-align: center; }
	main { max-width: 420px; }
	img { width: 96px; height: 96px; border-radius: 22px; box-shadow: 0 4px 16px rgba(0,0,0,.12); }
	h1 { margin: 20px 0 8px; font-size: 22px; }
	p { margin: 0 0 24px; color: #555; font-size: 16px; line-height: 1.5; }
	button { min-height: 48px; padding: 12px 28px; border: 0; border-radius: 999px; background: <?php echo esc_html( $color ); ?>; color: #fff; font-size: 16px; font-weight: 700; cursor: pointer; }
	button:focus-visible { outline: 3px solid <?php echo esc_html( $color ); ?>; outline-offset: 3px; }
</style>
</head>
<body>
<main>
	<?php if ( $icon ) : ?>
		<img src="<?php echo esc_url( $icon ); ?>" alt="Plan A">
	<?php endif; ?>
	<h1><?php esc_html_e( 'Trenutno nema internetske veze.', 'plan-a-aplikacija' ); ?></h1>
	<p><?php esc_html_e( 'Rezervacije su moguće samo uz vezu.', 'plan-a-aplikacija' ); ?></p>
	<button type="button" onclick="window.location.reload()"><?php esc_html_e( 'Pokušaj ponovno', 'plan-a-aplikacija' ); ?></button>
</main>
</body>
</html>
		<?php
	}
}
