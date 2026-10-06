<?php
/**
 * Postavke dodatka i pomoćne funkcije za adrese.
 *
 * @package Plan_A_Aplikacija
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_App_Settings {

	const OPTION = 'plan_a_app_settings';

	public static function init() {
		// Nove ikone kad se promijeni logotip (ili ako ikone još ne postoje).
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'settings_changed' ), 10, 2 );
		add_action( 'add_option_' . self::OPTION, array( 'Plan_A_App_Icons', 'regenerate' ) );
	}

	public static function settings_changed( $old, $new ) {
		if ( (int) ( $old['logo_id'] ?? 0 ) !== (int) ( $new['logo_id'] ?? 0 ) || ! Plan_A_App_Icons::get()['icons'] ) {
			Plan_A_App_Icons::regenerate();
		}
	}

	public static function defaults(): array {
		return array(
			'enabled'        => 1,
			'logo_id'        => 0,
			'theme_color'    => self::default_theme_color(),
			'start_url'      => '/izleti/',
			'nav_izleti'     => '/izleti/',
			'nav_plan'       => '/plan-izleta-2026/',
			'nav_cart'       => '/cart/',
			'nav_contact'    => '/kontakt/',
			'install_prompt' => 1,
			'prompt_every'   => 1, // 1 = pri svakoj posjeti, 0 = od druge posjete, "Ne sada" na 30 dana
		);
	}

	public static function get( string $key = '' ) {
		$settings = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		return '' === $key ? $settings : ( $settings[ $key ] ?? null );
	}

	/**
	 * Tamnoplava boja zaglavlja iz postavki teme Flatsome (Customizer), ako postoji.
	 */
	public static function default_theme_color(): string {
		foreach ( array( 'header_bg', 'nav_position_bg', 'color_primary' ) as $mod ) {
			$color = sanitize_hex_color( (string) get_theme_mod( $mod, '' ) );
			if ( $color ) {
				return $color;
			}
		}
		return '#1f3a5f';
	}

	public static function sanitize( $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$clean    = array(
			'enabled'        => empty( $input['enabled'] ) ? 0 : 1,
			'install_prompt' => empty( $input['install_prompt'] ) ? 0 : 1,
			'prompt_every'   => isset( $input['prompt_every'] ) && '0' === (string) $input['prompt_every'] ? 0 : 1,
			'logo_id'        => absint( $input['logo_id'] ?? 0 ),
			'theme_color'    => sanitize_hex_color( (string) ( $input['theme_color'] ?? '' ) ) ?: $defaults['theme_color'],
		);
		if ( $clean['logo_id'] && ! wp_attachment_is_image( $clean['logo_id'] ) ) {
			$clean['logo_id'] = 0;
		}
		foreach ( array( 'start_url', 'nav_izleti', 'nav_plan', 'nav_cart', 'nav_contact' ) as $key ) {
			$clean[ $key ] = self::sanitize_path( (string) ( $input[ $key ] ?? '' ), $defaults[ $key ] );
		}
		return $clean;
	}

	/**
	 * Adresa na ovoj stranici kao putanja ("/izleti/"). Vanjske adrese nisu dopuštene.
	 */
	public static function sanitize_path( string $value, string $fallback ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return $fallback;
		}
		$parts = wp_parse_url( $value );
		if ( false === $parts ) {
			return $fallback;
		}
		if ( ! empty( $parts['host'] ) && strtolower( $parts['host'] ) !== strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) {
			return $fallback;
		}
		$path = '/' . ltrim( (string) ( $parts['path'] ?? '/' ), '/' );
		$path = preg_replace( '#[^A-Za-z0-9/_\-.~%]#', '', $path );
		if ( ! empty( $parts['query'] ) ) {
			$path .= '?' . preg_replace( '#[^A-Za-z0-9=&_\-.~%]#', '', $parts['query'] );
		}
		return $path;
	}

	/**
	 * Puna adresa za putanju iz postavki (poštuje WordPress u podmapi).
	 */
	public static function url( string $key ): string {
		$path = (string) self::get( $key );
		$home = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
		if ( '' !== $home && 0 === strpos( $path, $home . '/' ) ) {
			$path = substr( $path, strlen( $home ) );
		}
		return home_url( $path );
	}

	/**
	 * Putanja početne mape stranice ("/" ili "/podmapa/").
	 */
	public static function home_path(): string {
		return trailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
	}

	public static function endpoint_url( string $name ): string {
		return add_query_arg( 'plan-a-app', $name, home_url( '/' ) );
	}

	/**
	 * Verzija predmemorije: mijenja se s verzijom dodatka i postavkama,
	 * pa nakon promjene stari service worker i predmemorija nestaju.
	 */
	public static function cache_version(): string {
		return PLAN_A_APP_VERSION . '-' . substr( md5( (string) wp_json_encode( self::get() ) ), 0, 8 );
	}
}
