<?php
/**
 * "Javi mi kad bude objavljen": e-mail adrese za izlete iz plana koji još nisu objavljeni.
 *
 * - adresa se sprema uz najavu (meta _paiz_subs), najviše 1000 po izletu,
 * - čim se izlet poveže s objavljenim izletom u WpTravellyju, svakome se pošalje jedan e-mail,
 *   a adrese se odmah brišu; prođe li datum izleta bez objave, adrese se brišu bez slanja,
 * - zaštita: provjera adrese, skriveno polje protiv botova, najviše 6 prijava u 10 minuta po IP adresi.
 *
 * @package Plan_A_Izleti
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Izleti_Plan_Notify {

	const META  = '_paiz_subs';
	const EVENT = 'paiz_plan_notify';
	const DAILY = 'paiz_plan_notify_daily';
	const MAX   = 1000;

	public static function init() {
		add_action( 'wp_ajax_paiz_plan_notify', array( __CLASS__, 'subscribe' ) );
		add_action( 'wp_ajax_nopriv_paiz_plan_notify', array( __CLASS__, 'subscribe' ) );
		add_action( self::EVENT, array( __CLASS__, 'send_due' ) );
		add_action( self::DAILY, array( __CLASS__, 'send_due' ) );
		add_action( 'init', array( __CLASS__, 'schedule_daily' ) );
		add_action( 'save_post_' . Plan_A_Izleti_Data::post_type(), array( __CLASS__, 'soon' ) );
		add_action( 'save_post_' . Plan_A_Izleti_Plan::POST_TYPE, array( __CLASS__, 'soon' ), 20 );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	public static function schedule_daily() {
		if ( ! wp_next_scheduled( self::DAILY ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::DAILY );
		}
	}

	/**
	 * Provjera uskoro nakon spremanja izleta (WpTravelly sprema termine nakon same objave).
	 */
	public static function soon() {
		if ( ! wp_next_scheduled( self::EVENT ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::EVENT );
		}
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::EVENT );
		wp_clear_scheduled_hook( self::DAILY );
	}

	private static function subs( int $entry_id ): array {
		$subs = get_post_meta( $entry_id, self::META, true );
		return is_array( $subs ) ? $subs : array();
	}

	public static function count( int $entry_id ): int {
		return count( self::subs( $entry_id ) );
	}

	/**
	 * Hrvatska množina za "osoba čeka": 1 osoba čeka, 2 osobe čekaju, 5 osoba čeka.
	 */
	public static function waiting_text( int $n ): string {
		$mod10  = $n % 10;
		$mod100 = $n % 100;
		if ( $mod10 >= 2 && $mod10 <= 4 && ( $mod100 < 12 || $mod100 > 14 ) ) {
			return $n . ' osobe čekaju';
		}
		return $n . ' osoba čeka';
	}

	private static function reply( bool $ok, string $message, int $status = 200 ) {
		wp_send_json(
			array(
				'ok'      => $ok,
				'message' => $message,
			),
			$status
		);
	}

	/**
	 * Prijava s obrasca u planu (bez noncea, jer stranice mogu biti u cacheu; zaštita je
	 * provjera izleta i adrese, skriveno polje i ograničenje po IP adresi).
	 */
	public static function subscribe() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$entry = absint( $_POST['entry'] ?? 0 );
		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$trap  = sanitize_text_field( wp_unslash( $_POST['web'] ?? '' ) );
		// phpcs:enable

		if ( '' !== $trap ) {
			self::reply( true, 'Hvala! Javit ćemo ti čim izlet bude objavljen.' );
		}

		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key   = 'paiz_pn_' . md5( $ip . wp_salt( 'nonce' ) );
		$tries = (int) get_transient( $key );
		if ( $tries >= 6 ) {
			self::reply( false, 'Previše pokušaja. Pokušaj ponovno za nekoliko minuta.', 429 );
		}
		set_transient( $key, $tries + 1, 10 * MINUTE_IN_SECONDS );

		if ( ! is_email( $email ) || strlen( $email ) > 190 ) {
			self::reply( false, 'Upiši ispravnu e-mail adresu.', 400 );
		}
		if ( ! $entry || Plan_A_Izleti_Plan::POST_TYPE !== get_post_type( $entry ) || 'publish' !== get_post_status( $entry ) ) {
			self::reply( false, 'Izlet nije pronađen.', 404 );
		}
		$row = Plan_A_Izleti_Plan::row_by_entry( $entry );
		if ( ! $row ) {
			self::reply( false, 'Izlet nije pronađen.', 404 );
		}
		$status = Plan_A_Izleti_Plan::status( $row );
		if ( 'past' === $status ) {
			self::reply( false, 'Ovaj izlet je već održan.', 400 );
		}
		if ( 'soon' !== $status ) {
			self::reply( true, 'Izlet je već objavljen – prijave su otvorene!' );
		}

		$subs  = self::subs( $entry );
		$lower = strtolower( $email );
		if ( ! isset( $subs[ $lower ] ) ) {
			if ( count( $subs ) >= self::MAX ) {
				self::reply( false, 'Trenutno ne možemo primiti više prijava za ovaj izlet.', 400 );
			}
			$subs[ $lower ] = time();
			update_post_meta( $entry, self::META, $subs );
		}
		self::reply( true, 'Hvala! Poslat ćemo ti jedan e-mail čim izlet bude objavljen.' );
	}

	/**
	 * Šalje obavijesti za objavljene izlete i briše adrese za prošle.
	 */
	public static function send_due() {
		if ( get_transient( 'paiz_plan_notify_lock' ) ) {
			return;
		}
		set_transient( 'paiz_plan_notify_lock', 1, 5 * MINUTE_IN_SECONDS );

		$ids = get_posts(
			array(
				'post_type'      => Plan_A_Izleti_Plan::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'meta_key'       => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery
				'no_found_rows'  => true,
			)
		);
		if ( $ids ) {
			Plan_A_Izleti_Plan::flush(); // svježe stanje izleta
		}
		foreach ( $ids as $id ) {
			$id   = (int) $id;
			$subs = self::subs( $id );
			$row  = 'publish' === get_post_status( $id ) ? Plan_A_Izleti_Plan::row_by_entry( $id ) : null;
			if ( ! $subs ) {
				delete_post_meta( $id, self::META );
				continue;
			}
			if ( ! $row ) {
				if ( 'trash' === get_post_status( $id ) || ! get_post_status( $id ) ) {
					delete_post_meta( $id, self::META );
				}
				continue;
			}
			$status = Plan_A_Izleti_Plan::status( $row );
			if ( 'past' === $status ) {
				delete_post_meta( $id, self::META );
			} elseif ( 'open' === $status || 'full' === $status ) {
				// Najprije obriši adrese (da se ništa ne pošalje dvaput), zatim pošalji.
				delete_post_meta( $id, self::META );
				$sent = 0;
				foreach ( array_keys( $subs ) as $email ) {
					if ( self::mail( (string) $email, $row ) ) {
						$sent++;
					}
				}
				update_post_meta( $id, '_paiz_notified', array( 'time' => time(), 'sent' => $sent ) );
			}
		}
		delete_transient( 'paiz_plan_notify_lock' );
	}

	private static function mail( string $email, array $row ): bool {
		if ( ! is_email( $email ) ) {
			return false;
		}
		$title = $row['title'];
		$url   = Plan_A_Izleti_Data::tour_url( $row['tour'] );
		$date  = Plan_A_Izleti_Plan_View::date_range( $row['from'], $row['to'] );
		$site  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		$subject = 'Objavljen je izlet: ' . $title;
		$body    = '<!doctype html><html><body style="margin:0;padding:24px;background:#f3f6f9;font-family:Arial,Helvetica,sans-serif;color:#24323f">'
			. '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;padding:28px">'
			. '<p style="margin:0 0 6px;color:#c96a12;font-size:12px;font-weight:bold;letter-spacing:2px;text-transform:uppercase">Plan izleta</p>'
			. '<h1 style="margin:0 0 12px;color:#12304b;font-size:24px;line-height:1.25">' . esc_html( $title ) . '</h1>'
			. '<p style="margin:0 0 6px;font-size:16px"><strong>' . esc_html( $date ) . '</strong>' . ( $row['guides'] ? ' · Vodiči: ' . esc_html( $row['guides'] ) : '' ) . '</p>'
			. '<p style="margin:0 0 22px;font-size:16px;line-height:1.5">Javljamo ti: izlet je upravo objavljen. ' . ( $row['sold_out'] ? 'Mjesta su već popunjena, ali pogledaj stranicu izleta.' : 'Prijave su otvorene – rezerviraj svoje mjesto na vrijeme.' ) . '</p>'
			. '<p style="margin:0 0 26px"><a href="' . esc_url( $url ) . '" style="display:inline-block;padding:14px 24px;border-radius:999px;background:#e8862a;color:#fff;font-weight:bold;text-decoration:none">Pogledaj izlet i prijavi se</a></p>'
			. '<p style="margin:0;color:#5f6b77;font-size:13px;line-height:1.5">Ovaj e-mail šaljemo samo jednom, jer je za ovu adresu na ' . esc_html( $site ) . ' zatražena obavijest o ovom izletu. Adresu smo nakon slanja obrisali.</p>'
			. '</div></body></html>';

		return (bool) wp_mail( $email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	public static function register_eraser( $erasers ) {
		$erasers['plan-a-izleti-plan'] = array(
			'eraser_friendly_name' => 'Plan izleta – obavijesti o objavi',
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	public static function erase( $email, $page = 1 ) {
		$email   = strtolower( (string) $email );
		$removed = false;
		$ids     = get_posts(
			array(
				'post_type'      => Plan_A_Izleti_Plan::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		foreach ( $ids as $id ) {
			$subs = self::subs( (int) $id );
			if ( isset( $subs[ $email ] ) ) {
				unset( $subs[ $email ] );
				update_post_meta( (int) $id, self::META, $subs );
				$removed = true;
			}
		}
		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
