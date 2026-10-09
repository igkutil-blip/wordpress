<?php
/**
 * Veza s Google tablicom: stranica šalje retke skripti u tablici (Apps Script, web-aplikacija).
 * Smjer je samo stranica → tablica. Neuspjelo slanje ponavlja se svaki sat.
 *
 * @package Plan_A_Clanstvo
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Clanstvo_Sheets {

	/** Verzija skripte u google-tablica.gs (SCRIPT_VERSION). Starija skripta je spora za pakete. */
	const SCRIPT_VERSION = 3;

	public static function init() {
		add_action( 'plan_a_clanstvo_changed', array( __CLASS__, 'send' ) );
	}

	public static function script(): string {
		$code = (string) file_get_contents( PLAN_A_CLANSTVO_DIR . 'includes/google-tablica.gs' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return str_replace( '{{SECRET}}', Plan_A_Clanstvo_Data::secret(), $code );
	}

	public static function url(): string {
		$url = trim( (string) Plan_A_Clanstvo_Data::value( 'sheet_url' ) );
		return preg_match( '#^https://script\.google(usercontent)?\.com/#', $url ) ? $url : '';
	}

	/**
	 * @return array{ok: bool, error?: string, name?: string, n?: int}
	 */
	public static function post( array $payload, int $timeout = 20 ): array {
		$url = self::url();
		if ( '' === $url ) {
			return array(
				'ok'    => false,
				'error' => 'Adresa Google tablice nije upisana.',
			);
		}
		$payload['secret'] = Plan_A_Clanstvo_Data::secret();
		$res               = wp_remote_post(
			$url,
			array(
				'headers'     => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'        => wp_json_encode( $payload ),
				'timeout'     => $timeout,
				'redirection' => 0,
			)
		);
		// Google izvrši skriptu na POST i odgovori preusmjeravanjem na adresu s rezultatom,
		// koju treba otvoriti kao GET. (Automatsko praćenje bi ponovno poslalo cijeli paket
		// kao POST, na što Google za veće pakete odgovara s HTTP 400.)
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( ! is_wp_error( $res ) && in_array( $code, array( 301, 302, 303, 307, 308 ), true ) ) {
			$loc = (string) wp_remote_retrieve_header( $res, 'location' );
			if ( ! preg_match( '#^https://[a-z0-9.-]*google(usercontent)?\.com/#i', $loc ) ) {
				return array(
					'ok'    => false,
					'error' => 'Tablica je preusmjerila na neočekivanu adresu.',
				);
			}
			$res = wp_remote_get(
				$loc,
				array(
					'timeout'     => $timeout,
					'redirection' => 3,
				)
			);
		}
		if ( is_wp_error( $res ) ) {
			return array(
				'ok'    => false,
				'error' => $res->get_error_message(),
			);
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $data ) ) {
			return array(
				'ok'    => false,
				'error' => 'Tablica nije odgovorila (HTTP ' . wp_remote_retrieve_response_code( $res ) . '). Je li skripta objavljena kao web-aplikacija s pristupom "Bilo tko"?',
			);
		}
		return $data + array( 'ok' => false );
	}

	public static function row( array $m ): array {
		$parent = trim( $m['roditelj'] . ( '' !== $m['roditelj_kontakt'] ? ', ' . $m['roditelj_kontakt'] : '' ), ', ' );
		return array(
			'broj'     => $m['broj'],
			'prijava'  => Plan_A_Clanstvo_Data::hr_datetime( $m['created'] ),
			'ime'      => $m['ime'],
			'prezime'  => $m['prezime'],
			'datum'    => Plan_A_Clanstvo_Data::hr_date( $m['datum'] ),
			'oib'      => $m['oib'],
			'adresa'   => $m['adresa'],
			'mjesto'   => $m['mjesto'],
			'email'    => $m['email'],
			'mobitel'  => $m['mobitel'],
			'roditelj' => $parent,
			'status'   => 'potvrdeno' === $m['status'] ? 'Potvrđeno' : 'Čeka potvrdu',
			'potvrda'  => Plan_A_Clanstvo_Data::hr_datetime( $m['confirmed'] ),
		);
	}

	public static function send( $id ): bool {
		$m = Plan_A_Clanstvo_Data::get_member( (int) $id );
		if ( ! $m ) {
			return false;
		}
		$res = self::post(
			array(
				'action' => 'upsert',
				'row'       => self::row( $m ),
				'godine'    => $m['godine'],
				'iskaznica' => $m['iskaznica'],
				'napomena'  => $m['napomena'],
			)
		);
		update_post_meta( (int) $id, '_pac_sync', $res['ok'] ? 'ok' : 'pending' );
		if ( $res['ok'] ) {
			self::sent( (int) $id );
		}
		return (bool) $res['ok'];
	}

	/** Godine, iskaznica i napomena iz uvoza šalju se samo jednom; dalje se vode ručno u tablici. */
	private static function sent( int $id ) {
		update_post_meta( $id, '_pac_sync', 'ok' );
		delete_post_meta( $id, '_pac_godine' );
		delete_post_meta( $id, '_pac_iskaznica' );
		delete_post_meta( $id, '_pac_napomena' );
	}

	public static function pending(): int {
		return count(
			get_posts(
				array(
					'post_type'      => Plan_A_Clanstvo_Data::CPT,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_key'       => '_pac_sync', // phpcs:ignore WordPress.DB.SlowDBQuery
					'meta_value'     => 'pending', // phpcs:ignore WordPress.DB.SlowDBQuery
				)
			)
		);
	}

	public static function send_delete( int $broj ) {
		$res = self::post(
			array(
				'action' => 'delete',
				'broj'   => $broj,
			)
		);
		if ( ! $res['ok'] ) {
			$q   = (array) get_option( 'plan_a_clanstvo_delete_queue', array() );
			$q[] = $broj;
			update_option( 'plan_a_clanstvo_delete_queue', array_values( array_unique( array_map( 'intval', $q ) ) ), false );
		}
	}

	/**
	 * Šalje članove u paketima. $only_pending = samo oni koji nisu stigli u tablicu.
	 * $seconds = najdulje trajanje (ostatak ide sljedeći put ili satnim ponavljanjem).
	 *
	 * @return array{ok: bool, n: int, error?: string}
	 */
	public static function bulk( bool $only_pending = true, int $limit = 50, int $seconds = 0 ): array {
		$start = time();
		$args = array(
			'post_type'      => Plan_A_Clanstvo_Data::CPT,
			'post_status'    => 'any',
			'posts_per_page' => $limit,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		);
		if ( $only_pending ) {
			$args['meta_key']   = '_pac_sync'; // phpcs:ignore WordPress.DB.SlowDBQuery
			$args['meta_value'] = 'pending'; // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		$total = 0;
		$page  = 1;
		do {
			$args['paged'] = $page;
			$ids           = get_posts( $args );
			if ( ! $ids ) {
				break;
			}
			$rows = array();
			foreach ( $ids as $id ) {
				$m = Plan_A_Clanstvo_Data::get_member( (int) $id );
				if ( $m ) {
					$rows[] = array(
						'row'       => self::row( $m ),
						'godine'    => $m['godine'],
						'iskaznica' => $m['iskaznica'],
						'napomena'  => $m['napomena'],
					);
				}
			}
			$res = self::post(
				array(
					'action' => 'bulk',
					'rows'   => $rows,
				),
				90
			);
			if ( ! $res['ok'] ) {
				return array(
					'ok'    => false,
					'n'     => $total,
					'error' => $res['error'] ?? '',
				);
			}
			update_option( 'plan_a_clanstvo_script_old', (int) ( $res['v'] ?? 0 ) < self::SCRIPT_VERSION ? 1 : 0, false );
			foreach ( $ids as $id ) {
				self::sent( (int) $id );
			}
			$total += count( $ids );
			// Kod "samo neposlanih" poslani više nisu u upitu, pa se uvijek uzima prva stranica.
			$page = $only_pending ? 1 : $page + 1;
		} while ( count( $ids ) === $limit && $total < 5000 && ( ! $seconds || time() - $start < $seconds ) );
		return array(
			'ok' => true,
			'n'  => $total,
		);
	}

	/** Je li u tablici stara skripta (prema zadnjem odgovoru). */
	public static function old_script(): bool {
		return (bool) get_option( 'plan_a_clanstvo_script_old', 0 );
	}

	public static function retry() {
		if ( '' === self::url() ) {
			return;
		}
		$q = (array) get_option( 'plan_a_clanstvo_delete_queue', array() );
		if ( $q ) {
			delete_option( 'plan_a_clanstvo_delete_queue' );
			foreach ( $q as $broj ) {
				self::send_delete( (int) $broj );
			}
		}
		self::bulk( true, 50, 120 );
	}
}
