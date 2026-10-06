<?php
/**
 * Prednji dio: manifest i meta oznake, registracija service workera,
 * poziv na instalaciju i donja navigacija u načinu aplikacije.
 *
 * @package Plan_A_Aplikacija
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_App_Frontend {

	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'head' ), 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'template_redirect', array( __CLASS__, 'offline_header' ) );
		add_action( 'wp_footer', array( __CLASS__, 'bottom_nav' ), 20 );
		add_filter( 'woocommerce_add_to_cart_fragments', array( __CLASS__, 'cart_fragment' ) );
	}

	private static function enabled(): bool {
		return (bool) Plan_A_App_Settings::get( 'enabled' );
	}

	public static function head() {
		if ( ! self::enabled() ) {
			// Aplikacija isključena u postavkama: ukloni eventualni postojeći service worker.
			?>
<script>
(function(){if(!('serviceWorker' in navigator))return;navigator.serviceWorker.getRegistrations().then(function(r){r.forEach(function(g){var s=(g.active||g.waiting||g.installing||{}).scriptURL||'';if(s.indexOf('plan-a-app=sw')!==-1){g.unregister();}});});if(window.caches){caches.keys().then(function(k){k.forEach(function(n){if(n.indexOf('plan-a-')===0){caches.delete(n);}});});}})();
</script>
			<?php
			return;
		}
		$color = (string) Plan_A_App_Settings::get( 'theme_color' );
		$apple = Plan_A_App_Icons::url( 'apple-180' );
		?>
<link rel="manifest" href="<?php echo esc_url( Plan_A_App_Settings::endpoint_url( 'manifest' ) ); ?>">
<meta name="theme-color" content="<?php echo esc_attr( $color ); ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Plan A">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
		<?php if ( $apple ) : ?>
<link rel="apple-touch-icon" href="<?php echo esc_url( $apple ); ?>">
		<?php endif; ?>
		<?php
	}

	public static function enqueue() {
		if ( ! self::enabled() ) {
			return;
		}
		wp_enqueue_style( 'plan-a-aplikacija', PLAN_A_APP_URL . 'assets/app.css', array(), PLAN_A_APP_VERSION );
		$color = sanitize_hex_color( (string) Plan_A_App_Settings::get( 'theme_color' ) );
		if ( $color ) {
			wp_add_inline_style( 'plan-a-aplikacija', ':root{--plan-a-app-color:' . $color . ';}' );
		}
		wp_enqueue_script(
			'plan-a-aplikacija',
			PLAN_A_APP_URL . 'assets/app.js',
			array(),
			PLAN_A_APP_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_localize_script(
			'plan-a-aplikacija',
			'planAApp',
			array(
				'swUrl'         => Plan_A_App_Settings::endpoint_url( 'sw' ),
				'statUrl'       => Plan_A_App_Settings::get( 'stats' ) ? Plan_A_App_Settings::endpoint_url( 'stat' ) : '',
				'scope'         => Plan_A_App_Settings::home_path(),
				'installPrompt' => (bool) Plan_A_App_Settings::get( 'install_prompt' ),
				'promptEvery'   => (bool) Plan_A_App_Settings::get( 'prompt_every' ),
				'noPromptPage'  => self::is_shop_flow_page(),
				'i18n'          => array(
					'title'     => __( 'Instaliraj aplikaciju Plan A', 'plan-a-aplikacija' ),
					'install'   => __( 'Instaliraj', 'plan-a-aplikacija' ),
					'notNow'    => __( 'Ne sada', 'plan-a-aplikacija' ),
					'iosText'   => __( 'Dodirnite Podijeli, zatim „Dodaj na početni zaslon”.', 'plan-a-aplikacija' ),
					'ok'        => __( 'U redu', 'plan-a-aplikacija' ),
					'barLabel'  => __( 'Instalacija aplikacije', 'plan-a-aplikacija' ),
				),
			)
		);
	}

	/**
	 * Košarica, plaćanje i račun: tu se ne prikazuje poziv na instalaciju.
	 */
	private static function is_shop_flow_page(): bool {
		if ( ( function_exists( 'is_cart' ) && is_cart() )
			|| ( function_exists( 'is_checkout' ) && is_checkout() )
			|| ( function_exists( 'is_account_page' ) && is_account_page() ) ) {
			return true;
		}
		// I po adresi (košarica, plaćanje, račun, uključujući adresu iz postavki).
		$current = self::current_path();
		foreach ( Plan_A_App_Endpoints::network_only_paths() as $prefix ) {
			if ( 0 === strpos( $current, trailingslashit( $prefix ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Zaglavlje kojim poslužitelj dopušta service workeru spremanje stranice
	 * za izvanmrežni prikaz: samo /izleti/ i stranice izleta, samo za
	 * neprijavljene posjetitelje (bez osobnih podataka u predmemoriji).
	 */
	public static function offline_header() {
		if ( ! self::enabled() || is_user_logged_in() || headers_sent() || self::is_shop_flow_page() ) {
			return;
		}
		$path    = self::current_path();
		$allowed = array(
			trailingslashit( (string) wp_parse_url( Plan_A_App_Settings::url( 'start_url' ), PHP_URL_PATH ) ),
			trailingslashit( (string) wp_parse_url( Plan_A_App_Settings::url( 'nav_izleti' ), PHP_URL_PATH ) ),
		);
		if ( is_singular( 'ttbm_tour' ) || in_array( $path, $allowed, true ) ) {
			header( 'X-Plan-A-Offline: 1' );
		}
	}

	/**
	 * Putanja trenutnog zahtjeva sa završnom kosom crtom ("/izleti/").
	 */
	private static function current_path(): string {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		return trailingslashit( '' !== $path ? $path : '/' );
	}

	private static function cart_count(): int {
		if ( function_exists( 'WC' ) && WC() && isset( WC()->cart ) && WC()->cart ) {
			return (int) WC()->cart->get_cart_contents_count();
		}
		return 0;
	}

	private static function cart_count_html( int $count ): string {
		return '<span class="plan-a-cart-count"' . ( $count > 0 ? '' : ' hidden' ) . ' aria-label="' . esc_attr( sprintf( /* translators: %d: broj stavki */ __( 'Stavki u košarici: %d', 'plan-a-aplikacija' ), $count ) ) . '">' . esc_html( (string) $count ) . '</span>';
	}

	/**
	 * WooCommerce osvježava broj u košarici nakon dodavanja (cart fragments).
	 */
	public static function cart_fragment( $fragments ) {
		if ( self::enabled() && is_array( $fragments ) ) {
			$fragments['span.plan-a-cart-count'] = self::cart_count_html( self::cart_count() );
		}
		return $fragments;
	}

	/**
	 * Donja navigacija. Vidljiva je samo kad je stranica otvorena kao
	 * instalirana aplikacija (display-mode: standalone, ili iOS navigator.standalone).
	 */
	public static function bottom_nav() {
		if ( ! self::enabled() ) {
			return;
		}
		$items = array(
			'nav_izleti'  => array( __( 'Izleti', 'plan-a-aplikacija' ), 'mountain' ),
			'nav_plan'    => array( __( 'Plan izleta', 'plan-a-aplikacija' ), 'calendar' ),
			'nav_cart'    => array( __( 'Košarica', 'plan-a-aplikacija' ), 'cart' ),
			'nav_contact' => array( __( 'Kontakt', 'plan-a-aplikacija' ), 'phone' ),
		);
		$current = self::current_path();

		echo '<nav class="plan-a-nav" aria-label="' . esc_attr__( 'Navigacija aplikacije', 'plan-a-aplikacija' ) . '">';
		foreach ( $items as $key => $item ) {
			$url    = Plan_A_App_Settings::url( $key );
			$active = trailingslashit( (string) wp_parse_url( $url, PHP_URL_PATH ) ) === $current;
			echo '<a class="plan-a-nav__item' . ( $active ? ' is-active' : '' ) . '" href="' . esc_url( $url ) . '"' . ( $active ? ' aria-current="page"' : '' ) . '>';
			echo '<span class="plan-a-nav__icon">' . self::icon( $item[1] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- statični SVG.
			if ( 'nav_cart' === $key ) {
				echo self::cart_count_html( self::cart_count() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
			}
			echo '</span><span class="plan-a-nav__label">' . esc_html( $item[0] ) . '</span></a>';
		}
		echo '</nav>';
	}

	private static function icon( string $name ): string {
		$paths = array(
			'mountain' => '<path d="M2.5 20 9 8.5l4 6.5 2.5-3.5 6 8.5z"/><path d="m7 12 2 1.5 2-1.5"/>',
			'calendar' => '<rect x="3" y="4.5" width="18" height="16" rx="2"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4M7.5 13.5h3M7.5 17h3M13.5 13.5h3"/>',
			'cart'     => '<circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2.5 3.5h2.8l2.4 11.2a1.6 1.6 0 0 0 1.6 1.3h8.4a1.6 1.6 0 0 0 1.6-1.2L21 7.5H6.2"/>',
			'phone'    => '<path d="M21 16.5v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 1.1 3.7 2 2 0 0 1 3.1 1.5h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L7 9.4a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.8 2.2z"/>',
		);
		return '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}
}
