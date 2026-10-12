<?php
/**
 * Plugin Name:       Plan A – pomoćnik za kopiju
 * Description:       SAMO ZA KOPIJU STRANICE (staging). Claudeu omogućuje da preko REST API-ja postavlja i ažurira naše dodatke (plan-a-*), pokreće našu automatiku i čita zapis grešaka. Na srd-plan-a.hr odbija sve.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            S.R.D. Plan A
 * License:           GPLv2 or later
 *
 * @package Plan_A_Pomocnik
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Pomocnik {

	const NS = 'plan-a-pomocnik/v1';

	/** Žive stranice na kojima pomoćnik ne radi ništa. */
	const LIVE_HOSTS = array( 'srd-plan-a.hr', 'www.srd-plan-a.hr' );

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
	}

	public static function is_live(): bool {
		$hosts = array(
			strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ),
			strtolower( (string) wp_parse_url( site_url(), PHP_URL_HOST ) ),
		);
		return (bool) array_intersect( $hosts, self::LIVE_HOSTS );
	}

	public static function notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo self::is_live()
			? '<div class="notice notice-error"><p><strong>Plan A – pomoćnik za kopiju</strong> je instaliran na živoj stranici. Ne radi ništa, ali ga obriši (Dodaci → Deaktiviraj → Obriši).</p></div>'
			: '<div class="notice notice-info"><p><strong>Kopija stranice</strong> – Plan A pomoćnik je uključen (Claude ovdje smije postavljati naše dodatke i pokretati automatiku).</p></div>';
	}

	/** Samo administrator prijavljen lozinkom aplikacije, i nikad na živoj stranici. */
	public static function allowed() {
		if ( self::is_live() ) {
			return new WP_Error( 'pap_live', 'Ovo je živa stranica – pomoćnik ne radi ništa.', array( 'status' => 403 ) );
		}
		if ( ! current_user_can( 'install_plugins' ) ) {
			return new WP_Error( 'pap_cap', 'Potreban je administrator.', array( 'status' => 403 ) );
		}
		if ( function_exists( 'rest_get_authenticated_app_password' ) && null === rest_get_authenticated_app_password() ) {
			return new WP_Error( 'pap_auth', 'Potrebna je prijava lozinkom aplikacije.', array( 'status' => 403 ) );
		}
		return true;
	}

	public static function routes() {
		$ok = array( __CLASS__, 'allowed' );
		register_rest_route( self::NS, '/status', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'status' ), 'permission_callback' => $ok ) );
		register_rest_route( self::NS, '/plugin', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'install' ), 'permission_callback' => $ok ) );
		register_rest_route( self::NS, '/plugin/(?P<slug>plan-a-[a-z0-9-]+)/(?P<do>activate|deactivate)', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'toggle' ), 'permission_callback' => $ok ) );
		register_rest_route( self::NS, '/cron', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'cron' ), 'permission_callback' => $ok ) );
		register_rest_route( self::NS, '/log', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'log' ), 'permission_callback' => $ok ) );
		register_rest_route( self::NS, '/meta/(?P<id>\d+)', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'meta_get' ), 'permission_callback' => $ok ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'meta_set' ), 'permission_callback' => $ok ),
		) );
		register_rest_route( self::NS, '/option/(?P<name>(plan_a|ttbm|pan_|pac_)[A-Za-z0-9_\-]*)', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'option' ), 'permission_callback' => $ok ) );
	}

	private static function plugin_file( string $slug ): string {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		foreach ( array_keys( get_plugins() ) as $f ) {
			if ( 0 === strpos( $f, $slug . '/' ) ) {
				return $f;
			}
		}
		return '';
	}

	public static function status() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugins = array();
		foreach ( get_plugins() as $f => $p ) {
			$plugins[] = array(
				'file'    => $f,
				'name'    => $p['Name'],
				'version' => $p['Version'],
				'active'  => is_plugin_active( $f ),
			);
		}
		return array(
			'home'        => home_url(),
			'live'        => self::is_live(),
			'wp'          => get_bloginfo( 'version' ),
			'php'         => PHP_VERSION,
			'cron_off'    => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'debug_log'   => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
			'fs_direct'   => 'direct' === get_filesystem_method(),
			'emails_off'  => is_plugin_active( 'disable-emails/disable-emails.php' ),
			'plugins'     => $plugins,
		);
	}

	/** Postavi ili zamijeni naš dodatak iz zipa (polje „zip”). Samo mape plan-a-*. */
	public static function install( WP_REST_Request $req ) {
		$files = $req->get_file_params();
		$zip   = $files['zip']['tmp_name'] ?? '';
		if ( ! $zip || ! is_uploaded_file( $zip ) || ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'pap_zip', 'Nema zipa (polje „zip”).', array( 'status' => 400 ) );
		}
		$z = new ZipArchive();
		if ( true !== $z->open( $zip ) ) {
			return new WP_Error( 'pap_zip', 'Zip se ne može otvoriti.', array( 'status' => 400 ) );
		}
		$top = array();
		for ( $i = 0; $i < $z->numFiles; $i++ ) {
			$top[ strtok( (string) $z->getNameIndex( $i ), '/' ) ] = 1;
		}
		$z->close();
		$slug = 1 === count( $top ) ? (string) array_key_first( $top ) : '';
		if ( ! preg_match( '/^plan-a-[a-z0-9-]+$/', $slug ) ) {
			return new WP_Error( 'pap_slug', 'Dopušteni su samo naši dodaci (jedna mapa plan-a-*).', array( 'status' => 400 ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		if ( 'direct' !== get_filesystem_method() ) {
			return new WP_Error( 'pap_fs', 'Hosting ne dopušta izravno pisanje datoteka (FS_METHOD).', array( 'status' => 500 ) );
		}
		$was    = self::plugin_file( $slug );
		$active = $was && is_plugin_active( $was );
		$up     = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );
		$res    = $up->install( $zip, array( 'overwrite_package' => true ) );
		if ( is_wp_error( $res ) || ! $res ) {
			$err = is_wp_error( $res ) ? $res->get_error_message() : implode( ' ', (array) $up->skin->get_error_messages() );
			return new WP_Error( 'pap_install', 'Instalacija nije uspjela: ' . $err, array( 'status' => 500 ) );
		}
		$file = self::plugin_file( $slug );
		if ( $file && ( $active || $req->get_param( 'activate' ) ) && ! is_plugin_active( $file ) ) {
			$act = activate_plugin( $file );
			if ( is_wp_error( $act ) ) {
				return new WP_Error( 'pap_activate', 'Postavljeno, ali ne i uključeno: ' . $act->get_error_message(), array( 'status' => 500 ) );
			}
		}
		$data = $file ? get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false ) : array();
		return array(
			'ok'      => true,
			'plugin'  => $file,
			'version' => $data['Version'] ?? '',
			'active'  => $file && is_plugin_active( $file ),
		);
	}

	public static function toggle( WP_REST_Request $req ) {
		$file = self::plugin_file( (string) $req['slug'] );
		if ( ! $file ) {
			return new WP_Error( 'pap_none', 'Dodatak nije instaliran.', array( 'status' => 404 ) );
		}
		if ( 'activate' === $req['do'] ) {
			$r = activate_plugin( $file );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
		} else {
			deactivate_plugins( $file );
		}
		return array( 'ok' => true, 'plugin' => $file, 'active' => is_plugin_active( $file ) );
	}

	/** Pokreni našu zakazanu radnju odmah (hook plan_a_*), s argumentima iz rasporeda. */
	public static function cron( WP_REST_Request $req ) {
		$hook = (string) $req->get_param( 'hook' );
		if ( ! preg_match( '/^plan_a_[a-z0-9_]+$/', $hook ) ) {
			return new WP_Error( 'pap_hook', 'Dopuštene su samo naše radnje (plan_a_*).', array( 'status' => 400 ) );
		}
		$args = array();
		foreach ( (array) _get_cron_array() as $events ) {
			if ( isset( $events[ $hook ] ) ) {
				$args = (array) ( current( $events[ $hook ] )['args'] ?? array() );
				break;
			}
		}
		$t = microtime( true );
		do_action_ref_array( $hook, $args );
		return array( 'ok' => true, 'hook' => $hook, 'seconds' => round( microtime( true ) - $t, 2 ) );
	}

	public static function log( WP_REST_Request $req ) {
		$file = WP_CONTENT_DIR . '/debug.log';
		if ( ! is_readable( $file ) ) {
			return array( 'lines' => array(), 'note' => 'debug.log ne postoji (WP_DEBUG_LOG isključen).' );
		}
		$n     = max( 10, min( 500, (int) ( $req->get_param( 'lines' ) ?: 100 ) ) );
		$size  = filesize( $file );
		$fh    = fopen( $file, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fseek( $fh, max( 0, $size - 200000 ) );
		$tail  = stream_get_contents( $fh );
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$lines = array_slice( explode( "\n", trim( (string) $tail ) ), -$n );
		return array( 'lines' => $lines );
	}

	public static function meta_get( WP_REST_Request $req ) {
		$id = (int) $req['id'];
		if ( ! get_post( $id ) ) {
			return new WP_Error( 'pap_post', 'Nema tog zapisa.', array( 'status' => 404 ) );
		}
		$out = array();
		foreach ( get_post_meta( $id ) as $k => $v ) {
			$out[ $k ] = maybe_unserialize( $v[0] );
		}
		return array( 'id' => $id, 'type' => get_post_type( $id ), 'status' => get_post_status( $id ), 'title' => get_the_title( $id ), 'meta' => $out );
	}

	/** Upiši podatke uz zapis: {"meta": {"kljuc": vrijednost, …}}; null briše ključ. */
	public static function meta_set( WP_REST_Request $req ) {
		$id = (int) $req['id'];
		if ( ! get_post( $id ) ) {
			return new WP_Error( 'pap_post', 'Nema tog zapisa.', array( 'status' => 404 ) );
		}
		$meta = (array) $req->get_param( 'meta' );
		foreach ( $meta as $k => $v ) {
			$k = sanitize_key( (string) $k );
			if ( null === $v ) {
				delete_post_meta( $id, $k );
			} else {
				update_post_meta( $id, $k, $v );
			}
		}
		clean_post_cache( $id );
		return array( 'ok' => true, 'id' => $id, 'keys' => array_keys( $meta ) );
	}

	public static function option( WP_REST_Request $req ) {
		return array( 'name' => (string) $req['name'], 'value' => get_option( (string) $req['name'] ) );
	}
}

Plan_A_Pomocnik::init();
