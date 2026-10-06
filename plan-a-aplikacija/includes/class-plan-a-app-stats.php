<?php
/**
 * Anonimna statistika korištenja instalirane aplikacije.
 *
 * Ni Android ni iPhone ne javljaju stranici instalaciju ni brisanje aplikacije,
 * pa se broji ono što aplikacija javi kad se otvori (samo u načinu aplikacije):
 *  - first: prvo otvaranje na uređaju (≈ instalacija),
 *  - day:   prvo otvaranje u danu na uređaju (aktivan dan),
 *  - open:  svako pokretanje aplikacije.
 * Sprema se samo zbroj po danu, platformi i vrsti događaja. Nema kolačića,
 * IP adresa ni identifikatora uređaja ili korisnika.
 *
 * @package Plan_A_Aplikacija
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_App_Stats {

	const DB_VERSION = '1';
	const DB_OPTION  = 'plan_a_app_stats_db';
	const PLATFORMS  = array( 'android', 'ios', 'other' );
	const METRICS    = array( 'first', 'day', 'open' );

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 5 );
	}

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'plan_a_app_stats';
	}

	public static function maybe_install() {
		if ( get_option( self::DB_OPTION ) === self::DB_VERSION ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
				day date NOT NULL,
				platform varchar(10) NOT NULL,
				metric varchar(10) NOT NULL,
				total int(10) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (day,platform,metric)
			) {$charset};"
		);
		update_option( self::DB_OPTION, self::DB_VERSION, false );
	}

	public static function drop() {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- naziv vlastite tablice.
		delete_option( self::DB_OPTION );
	}

	/**
	 * POST /?plan-a-app=stat  (platform=android|ios|other, events=first,day,open)
	 */
	public static function record() {
		status_header( 204 );
		nocache_headers();

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
		if ( 'POST' !== $method || ! Plan_A_App_Settings::get( 'enabled' ) || ! Plan_A_App_Settings::get( 'stats' ) ) {
			return;
		}
		// Administratori (testiranje) se ne broje.
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}
		// Samo zahtjevi s ove stranice.
		$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
		if ( $origin && strtolower( (string) wp_parse_url( $origin, PHP_URL_HOST ) ) !== strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- anonimno brojanje, bez podataka korisnika; ulaz je ograničen na popis dopuštenih vrijednosti.
		$platform = isset( $_POST['platform'] ) ? sanitize_key( wp_unslash( $_POST['platform'] ) ) : '';
		$events   = isset( $_POST['events'] ) ? explode( ',', sanitize_text_field( wp_unslash( $_POST['events'] ) ) ) : array();
		// phpcs:enable
		$events = array_values( array_intersect( self::METRICS, array_map( 'sanitize_key', $events ) ) );
		if ( ! in_array( $platform, self::PLATFORMS, true ) || ! $events || self::rate_limited() ) {
			return;
		}

		foreach ( $events as $metric ) {
			self::increment( $platform, $metric );
		}
	}

	/**
	 * Uveća dnevni zbroj za 1. Metrika "booking" (rezervacija izleta iz aplikacije)
	 * zapisuje se samo s poslužitelja (Plan_A_App_Orders), nikad iz preglednika.
	 */
	public static function increment( string $platform, string $metric ) {
		if ( ! in_array( $platform, self::PLATFORMS, true ) || ! in_array( $metric, array_merge( self::METRICS, array( 'booking' ) ), true ) ) {
			return;
		}
		self::maybe_install();
		global $wpdb;
		$table = self::table();
		// Izravan upit je potreban za atomično brojanje (bez gubitka istovremenih zahtjeva).
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"INSERT INTO {$table} (day, platform, metric, total) VALUES (%s, %s, %s, 1) ON DUPLICATE KEY UPDATE total = total + 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- naziv vlastite tablice.
				current_time( 'Y-m-d' ),
				$platform,
				$metric
			)
		);
	}

	/**
	 * Najviše 60 zahtjeva u 10 minuta s iste adrese. IP se ne sprema, samo
	 * kratki sažetak (hash) u privremenom zapisu koji istekne za 10 minuta.
	 */
	private static function rate_limited(): bool {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'plan_a_app_rl_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 16 );
		$hit = (int) get_transient( $key );
		if ( $hit >= 60 ) {
			return true;
		}
		set_transient( $key, $hit + 1, 10 * MINUTE_IN_SECONDS );
		return false;
	}

	/**
	 * Zbrojevi od zadanog dana: [ 'Y-m-d' => [ platforma => [ metrika => broj ] ] ].
	 */
	public static function by_day( int $days ): array {
		global $wpdb;
		self::maybe_install();
		$table = self::table();
		$from  = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -' . max( 0, $days - 1 ) . ' days' ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT day, platform, metric, total FROM {$table} WHERE day >= %s", $from ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- vlastita tablica.
		$out   = array();
		foreach ( (array) $rows as $row ) {
			$out[ $row['day'] ][ $row['platform'] ][ $row['metric'] ] = (int) $row['total'];
		}
		return $out;
	}

	/**
	 * Ukupno od početka: [ platforma => [ metrika => broj ] ].
	 */
	public static function all_time(): array {
		global $wpdb;
		self::maybe_install();
		$table = self::table();
		$rows  = $wpdb->get_results( "SELECT platform, metric, SUM(total) AS total FROM {$table} GROUP BY platform, metric", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- vlastita tablica, bez ulaza.
		$out   = array();
		foreach ( (array) $rows as $row ) {
			$out[ $row['platform'] ][ $row['metric'] ] = (int) $row['total'];
		}
		return $out;
	}

	/**
	 * Zbroj metrike u zadnjih $days dana, po platformi i ukupno.
	 */
	public static function sum( array $by_day, string $metric, int $days ): array {
		$from = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -' . max( 0, $days - 1 ) . ' days' ) );
		$sum  = array_fill_keys( array_merge( self::PLATFORMS, array( 'all' ) ), 0 );
		foreach ( $by_day as $day => $platforms ) {
			if ( $day < $from ) {
				continue;
			}
			foreach ( $platforms as $platform => $metrics ) {
				$value             = (int) ( $metrics[ $metric ] ?? 0 );
				$sum[ $platform ] += $value;
				$sum['all']       += $value;
			}
		}
		return $sum;
	}
}
