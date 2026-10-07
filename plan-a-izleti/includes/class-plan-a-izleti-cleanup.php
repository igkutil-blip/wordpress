<?php
/**
 * Jednokratno čišćenje podataka uklonjene funkcije "Dogovor s ekipom" (verzija 1.11.0).
 *
 * Pokreće se jednom po stranici nakon ažuriranja (i pri brisanju dodatka) i uklanja:
 * tablice plan_a_dogovori, plan_a_dogovor_odgovori i plan_a_dogovor_stats, opcije
 * plan_a_izleti_dogovor_db i plan_a_izleti_dogovor_rules, WP Cron zadatak
 * plan_a_izleti_dogovor_cleanup, transiente paiz_rl_* (ograničenje zahtjeva) i oznaku
 * _plan_a_dogovor na narudžbama. Prije svakog brisanja provjerava postoji li podatak.
 *
 * @package Plan_A_Izleti
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Izleti_Cleanup {

	const DONE_OPTION = 'plan_a_izleti_dogovor_removed';

	public static function init() {
		// Nakon registracije ostalih pravila, da flush_rewrite_rules() ukloni pravilo /dogovor/.
		add_action( 'init', array( __CLASS__, 'maybe_run' ), 99 );
	}

	public static function maybe_run() {
		if ( '1' === get_option( self::DONE_OPTION ) ) {
			return;
		}
		$had_rule = self::run();
		update_option( self::DONE_OPTION, '1', true );
		if ( $had_rule ) {
			flush_rewrite_rules( false );
		}
	}

	private static function table_exists( string $table ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}

	/**
	 * @return bool Je li u spremljenim pravilima adresa postojalo pravilo za /dogovor/.
	 */
	public static function run(): bool {
		global $wpdb;

		// Tablice.
		foreach ( array( 'plan_a_dogovori', 'plan_a_dogovor_odgovori', 'plan_a_dogovor_stats' ) as $name ) {
			$table = $wpdb->prefix . $name;
			if ( self::table_exists( $table ) ) {
				$wpdb->query( "DROP TABLE IF EXISTS `$table`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fiksni naziv tablice dodatka.
			}
		}

		// Opcije.
		foreach ( array( 'plan_a_izleti_dogovor_db', 'plan_a_izleti_dogovor_rules' ) as $option ) {
			if ( false !== get_option( $option ) ) {
				delete_option( $option );
			}
		}

		// WP Cron.
		if ( wp_next_scheduled( 'plan_a_izleti_dogovor_cleanup' ) ) {
			wp_clear_scheduled_hook( 'plan_a_izleti_dogovor_cleanup' );
		}

		// Transienti ograničenja zahtjeva (paiz_rl_<hash> i paiz_rl_global_create).
		// S vanjskom predmemorijom objekata nisu u bazi i sami istječu za najviše 1 sat.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$names = (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_paiz_rl_' ) . '%'
			)
		);
		foreach ( $names as $option_name ) {
			delete_transient( substr( $option_name, strlen( '_transient_' ) ) );
		}
		// Zaostali zapisi o isteku bez pripadajućeg transienta.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_timeout_paiz_rl_' ) . '%'
			)
		);

		// Oznaka _plan_a_dogovor na narudžbama (spremišta narudžbi: postmeta i HPOS).
		$meta_tables = array( $wpdb->postmeta, $wpdb->prefix . 'wc_orders_meta' );
		foreach ( $meta_tables as $meta_table ) {
			if ( self::table_exists( $meta_table ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fiksni naziv tablice.
				$wpdb->query( $wpdb->prepare( "DELETE FROM `$meta_table` WHERE meta_key = %s", '_plan_a_dogovor' ) );
			}
		}

		// Pravilo adrese /dogovor/<token>/ u spremljenim pravilima.
		$rules = get_option( 'rewrite_rules' );
		if ( is_array( $rules ) ) {
			foreach ( $rules as $target ) {
				if ( is_string( $target ) && false !== strpos( $target, 'plan_a_dogovor=' ) ) {
					return true;
				}
			}
		}
		return false;
	}
}
