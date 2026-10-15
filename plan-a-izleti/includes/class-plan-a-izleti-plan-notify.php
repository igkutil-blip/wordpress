<?php
/**
 * "Javi mi kad bude objavljen": e-mail adrese za izlete iz plana koji još nisu objavljeni.
 *
 * - adresa se sprema uz najavu (meta _paiz_subs), najviše 1000 po izletu,
 * - čim se izlet poveže s objavljenim izletom u WpTravellyju, svakome se pošalje jedan e-mail,
 *   a adrese se odmah brišu; prođe li datum izleta bez objave, adrese se brišu bez slanja,
 * - zaštita: provjera adrese, skriveno polje protiv botova, najviše 6 prijava u 10 minuta po IP adresi,
 * - vodiči izleta (Članstvo → Postavke → Vodiči) dobiju mail sa svim predbilježbama čim se netko upiše;
 *   jednom nakon 1.23.0 svi vodiči dobiju mail s trenutnim predbilježbama.
 *
 * @package Plan_A_Izleti
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Izleti_Plan_Notify {

	const META  = '_paiz_subs';
	const EVENT = 'paiz_plan_notify';
	const DAILY = 'paiz_plan_notify_daily';
	const MAX   = 1000;
	const FIRST = 'paiz_plan_guides_first';

	public static function init() {
		add_action( 'wp_ajax_paiz_plan_notify', array( __CLASS__, 'subscribe' ) );
		add_action( 'wp_ajax_nopriv_paiz_plan_notify', array( __CLASS__, 'subscribe' ) );
		add_action( self::EVENT, array( __CLASS__, 'send_due' ) );
		add_action( self::DAILY, array( __CLASS__, 'send_due' ) );
		add_action( 'init', array( __CLASS__, 'schedule_daily' ) );
		add_action( 'init', array( __CLASS__, 'schedule_first' ), 30 );
		add_action( self::FIRST, array( __CLASS__, 'mail_guides_all' ) );
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

	/** Jednom (nova verzija): vodičima svih najava s predbilježbama pošalji trenutni popis. */
	public static function schedule_first() {
		if ( get_option( self::FIRST ) || ! add_option( self::FIRST, time(), '', false ) ) {
			return;
		}
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::FIRST );
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::EVENT );
		wp_clear_scheduled_hook( self::DAILY );
		wp_clear_scheduled_hook( self::FIRST );
	}

	private static function subs( int $entry_id ): array {
		$subs = get_post_meta( $entry_id, self::META, true );
		return is_array( $subs ) ? $subs : array();
	}

	/** Adrese koje čekaju obavijest: e-mail => vrijeme upisa (samo za prikaz u adminu). */
	public static function emails( int $entry_id ): array {
		return self::subs( $entry_id );
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
			self::mail_guides( $entry, $row, $lower );
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
		// Zajednički Plan A izgled (zaglavlje s logom, naslov, kartica).
		$body    = Plan_A_Email::render(
			array(
				'title'    => $title,
				'label'    => 'Plan izleta',
				'icon'     => 'star',
				'subtitle' => '<strong>' . esc_html( $date ) . '</strong>' . ( $row['guides'] ? ' · Vodiči: ' . esc_html( $row['guides'] ) : '' ),
			),
			Plan_A_Email::p( 'Javljamo ti: izlet je upravo objavljen. ' . ( $row['sold_out'] ? 'Mjesta su već popunjena, ali pogledaj stranicu izleta.' : 'Prijave su otvorene – rezerviraj svoje mjesto na vrijeme.' ) )
			. Plan_A_Email::button( 'Pogledaj izlet i prijavi se', $url )
			. Plan_A_Email::small( 'Ovaj e-mail šaljemo samo jednom, jer je za ovu adresu na ' . esc_html( $site ) . ' zatražena obavijest o ovom izletu. Adresu smo nakon slanja obrisali.' )
		);
		$from    = sanitize_email( (string) get_option( 'woocommerce_email_from_address', '' ) ) ?: sanitize_email( (string) get_option( 'admin_email' ) );
		$fname   = str_replace( array( '"', "\r", "\n" ), '', trim( wp_strip_all_tags( (string) get_option( 'woocommerce_email_from_name', '' ) ) ) ?: 'Plan A' );

		return (bool) wp_mail( $email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8', 'From: "' . $fname . '" <' . $from . '>' ) );
	}

	/** E-mailovi vodiča najave (tekst „Igor + Krešo”, adrese iz Članstvo → Postavke → Vodiči); bez njih adresa trgovine. */
	public static function guide_mails( string $guides ): array {
		$to = '' !== trim( $guides ) && class_exists( 'Plan_A_Clanstvo_Ops' ) && method_exists( 'Plan_A_Clanstvo_Ops', 'guide_mails' ) ? Plan_A_Clanstvo_Ops::guide_mails( $guides ) : array();
		if ( ! $to ) {
			$to = array( sanitize_email( (string) get_option( 'woocommerce_email_from_address', '' ) ) ?: (string) get_option( 'admin_email' ) );
		}
		return array_values( array_filter( $to, 'is_email' ) );
	}

	/** Mail vodičima: svi predbilježeni za izlet (novi, ako ga ima, podebljan). */
	public static function guide_mail( int $entry, array $row, string $new = '' ): array {
		$subs = self::subs( $entry );
		asort( $subs );
		$date  = Plan_A_Izleti_Plan_View::date_range( $row['from'], $row['to'] );
		$label = wp_specialchars_decode( (string) $row['title'], ENT_QUOTES ) . ' (' . $date . ')';
		$link  = 'color:' . Plan_A_Email::color( 'accent' ) . ';';
		$rows  = array();
		$i     = 0;
		foreach ( $subs as $email => $time ) {
			++$i;
			$a      = '<a href="mailto:' . esc_attr( $email ) . '" style="' . $link . '">' . esc_html( $email ) . '</a>';
			$rows[] = array( $i . '.', $email === $new ? '<b>' . $a . '</b> (novi)' : $a, $time ? esc_html( wp_date( 'j.n.Y. H:i', (int) $time ) ) : '' );
		}
		$n     = count( $subs );
		$intro = $new
			? 'Za ovaj izlet iz plana upisala se nova osoba („Javi mi kad bude objavljen”). Ispod su svi koji čekaju obavijest, da ih imaš na jednom mjestu.'
			: 'Šaljemo ti popis svih koji su za ovaj izlet iz plana kliknuli „Javi mi kad bude objavljen”. Od sada ćeš dobiti mail sa cijelim popisom svaki put kad se netko novi upiše.';
		return array(
			'subject' => ( $new ? 'Nova predbilježba: ' : 'Predbilježbe: ' ) . $label . ' – ' . self::waiting_text( $n ),
			'html'    => Plan_A_Email::render(
				array(
					'title'    => $new ? 'Nova predbilježba za izlet' : 'Predbilježbe za izlet',
					'icon'     => 'list',
					'label'    => 'Plan A · plan izleta',
					'subtitle' => '<strong>' . esc_html( wp_specialchars_decode( (string) $row['title'], ENT_QUOTES ) ) . '</strong> · ' . esc_html( $date ) . ( $row['guides'] ? ' · Vodiči: ' . esc_html( $row['guides'] ) : '' ),
				),
				Plan_A_Email::card( '', Plan_A_Email::p( $intro ) )
				. Plan_A_Email::card( 'Svi koji čekaju obavijest (' . $n . ')', Plan_A_Email::table( array( 'Br.', 'E-mail', 'Upisan/a' ), $rows ) )
				. Plan_A_Email::small( 'Kad objaviš izlet (isti datum početka i sličan naziv), svima na popisu automatski ide jedan e-mail i popis se briše. Popis je i u adminu: Plan izleta → Svi izleti u planu → ovaj izlet.' )
			),
		);
	}

	private static function mail_guides( int $entry, array $row, string $new = '' ): int {
		if ( ! self::subs( $entry ) ) {
			return 0;
		}
		$m    = self::guide_mail( $entry, $row, $new );
		$from = sanitize_email( (string) get_option( 'woocommerce_email_from_address', '' ) ) ?: sanitize_email( (string) get_option( 'admin_email' ) );
		$name = str_replace( array( '"', "\r", "\n" ), '', trim( wp_strip_all_tags( (string) get_option( 'woocommerce_email_from_name', '' ) ) ) ?: 'Plan A' );
		$h    = array( 'Content-Type: text/html; charset=UTF-8', 'From: "' . $name . '" <' . $from . '>' );
		if ( $new && is_email( $new ) ) {
			$h[] = 'Reply-To: ' . $new;
		}
		$sent = 0;
		foreach ( self::guide_mails( (string) $row['guides'] ) as $to ) {
			$sent += wp_mail( $to, $m['subject'], $m['html'], $h ) ? 1 : 0;
		}
		return $sent;
	}

	/** Jednokratno: trenutni popis predbilježbi svim vodičima (samo za izlete koji još nisu objavljeni). */
	public static function mail_guides_all(): int {
		$ids = get_posts(
			array(
				'post_type'      => Plan_A_Izleti_Plan::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'meta_key'       => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery
				'no_found_rows'  => true,
			)
		);
		Plan_A_Izleti_Plan::flush();
		$sent = 0;
		foreach ( $ids as $id ) {
			$row = Plan_A_Izleti_Plan::row_by_entry( (int) $id );
			if ( $row && 'soon' === Plan_A_Izleti_Plan::status( $row ) ) {
				$sent += self::mail_guides( (int) $id, $row );
			}
		}
		return $sent;
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
