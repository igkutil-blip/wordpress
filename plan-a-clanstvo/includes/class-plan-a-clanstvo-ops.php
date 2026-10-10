<?php
/**
 * Automatika koja radi sama (svaki sat, uz ostale poslove dodatka):
 * - podsjetnik za uplatu narudžbe (5 dana bez uplate, ili 3 dana prije izleta);
 * - dnevni pregled u 7:00 (vama, agenciji i blagajniku; blagajnik dobiva i kopiju podsjetnika);
 * - popis sudionika vodičima dan prije izleta u 8:00 (PDF);
 * - siječanj: e-mail s uplatnicom za članarinu nove godine svim potvrđenim članovima koji se nisu
 *   odjavili (poveznica za odjavu je u e-mailu) i mjesečni popis neplaćenih za blagajnika
 *   (siječanj–travanj, samo članovi na popisima izleta);
 * - upozorenje kad veza s Google tablicom ne radi.
 *
 * @package Plan_A_Clanstvo
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Clanstvo_Ops {

	const LOG    = 'plan_a_clanstvo_log';    // događaji za dnevni pregled
	const STATE  = 'plan_a_clanstvo_ops';    // što je već poslano (dan, mjesec, godina)
	const HEALTH = 'plan_a_clanstvo_health'; // stanje veze s tablicom
	const REMIND = '_pac_remind';            // narudžba: kad je poslan podsjetnik
	const NO_JAN = '_pac_no_jan';            // član: odjavio se sa siječanjskog e-maila

	const REMIND_DAYS = 5;  // podsjetnik nakon toliko dana bez uplate
	const REMIND_TOUR = 3;  // … ili toliko dana prije izleta
	const DAILY_HOUR  = 7;
	const GUIDE_HOUR  = 8;
	const FULL_SEATS  = 3;  // "skoro pun" kad je slobodno toliko mjesta ili manje
	const NOBODY_DAYS = 10; // "nitko nije platio" za izlete u sljedećih toliko dana
	const JAN_DAY     = 2;  // siječanjski e-mail za članarinu
	const JAN_BATCH   = 50; // e-mailova na sat

	/** Narudžba kojoj se upravo šalje podsjetnik (naslov i obavijest u e-mailu). */
	private static $reminding = 0;

	public static function init() {
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'status_changed' ), 40, 4 );
		add_filter( 'woocommerce_email_subject_customer_on_hold_order', array( __CLASS__, 'reminder_subject' ), 30, 2 );
		add_filter( 'plan_a_kosarica_email_notice', array( __CLASS__, 'reminder_notice' ), 5, 3 );
		add_filter( 'woocommerce_email_headers', array( __CLASS__, 'reminder_copy' ), 30, 3 );
		add_action( 'template_redirect', array( __CLASS__, 'unsubscribe' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Postavke                                                             */
	/* ------------------------------------------------------------------ */

	private static function mail( string $key ): string {
		$m = sanitize_email( (string) Plan_A_Clanstvo_Data::value( $key ) );
		return is_email( $m ) ? $m : '';
	}

	/** Vama: upisani e-mail ili adresa trgovine. */
	public static function admin_mail(): string {
		return self::mail( 'mail_admin' ) ?: sanitize_email( (string) get_option( 'woocommerce_email_from_address', get_option( 'admin_email' ) ) );
	}

	/** Vodiči iz postavki: retci "Ime, nadimak = e-mail" → [normalizirano ime => e-mail]. */
	public static function guides(): array {
		$out = array();
		foreach ( preg_split( '/\R/', (string) Plan_A_Clanstvo_Data::value( 'guides' ) ) as $line ) {
			if ( ! preg_match( '/^(.+?)\s*[=:]\s*(\S+@\S+)\s*$/u', trim( $line ), $m ) || ! is_email( $m[2] ) ) {
				continue;
			}
			foreach ( explode( ',', $m[1] ) as $name ) {
				$k = self::norm( $name );
				if ( '' !== $k ) {
					$out[ $k ] = $m[2];
				}
			}
		}
		return $out;
	}

	private static function norm( string $t ): string {
		return trim( (string) preg_replace( '/[^a-z0-9]+/', ' ', strtolower( remove_accents( str_replace( array( 'đ', 'Đ' ), 'd', $t ) ) ) ) );
	}

	/** E-mailovi vodiča iz teksta "Igor + Krešo" (ime ili prva riječ imena). */
	public static function guide_mails( string $text ): array {
		$map  = self::guides();
		$mail = array();
		foreach ( preg_split( '/\s*(?:\+|,|&|\/|\bi\b)\s*/u', $text ) as $name ) {
			$k = self::norm( $name );
			if ( '' === $k ) {
				continue;
			}
			$first = explode( ' ', $k )[0];
			foreach ( $map as $key => $email ) {
				if ( $key === $k || $key === $first || explode( ' ', $key )[0] === $first ) {
					$mail[ $email ] = $email;
					break;
				}
			}
		}
		return array_values( $mail );
	}

	private static function state(): array {
		return (array) get_option( self::STATE, array() );
	}

	private static function save_state( array $s ) {
		update_option( self::STATE, $s, false );
	}

	/* ------------------------------------------------------------------ */
	/* Dnevnik događaja                                                     */
	/* ------------------------------------------------------------------ */

	public static function log( string $type, int $order, string $text ) {
		$log   = (array) get_option( self::LOG, array() );
		$log[] = array( 't' => time(), 'k' => $type, 'o' => $order, 'x' => $text );
		$from  = time() - 8 * DAY_IN_SECONDS;
		$log   = array_values( array_filter( $log, static fn( $e ) => (int) $e['t'] > $from ) );
		update_option( self::LOG, array_slice( $log, -500 ), false );
	}

	/** Kratki opis narudžbe: "#123 Ime Prezime – Izlet 14.11. (2 osobe)". */
	public static function describe( WC_Order $order ): string {
		$parts = array();
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product || ! (int) $item->get_meta( '_ttbm_id' ) ) {
				continue;
			}
			$ts    = strtotime( (string) $item->get_meta( '_ttbm_date' ) );
			$qty   = 0;
			foreach ( (array) $item->get_meta( '_ttbm_ticket_info' ) as $t ) {
				$qty += (int) ( $t['ticket_qty'] ?? 0 );
			}
			$qty     = max( 1, $qty ?: (int) $item->get_quantity() );
			$title   = class_exists( 'Plan_A_Izleti_Data' ) ? Plan_A_Izleti_Data::title( (int) $item->get_meta( '_ttbm_id' ) ) : $item->get_name();
			$parts[] = wp_strip_all_tags( html_entity_decode( (string) $title, ENT_QUOTES, 'UTF-8' ) ) . ( $ts ? ' ' . gmdate( 'j.n.', $ts ) : '' ) . ' (' . $qty . ( 1 === $qty ? ' osoba' : ( $qty < 5 ? ' osobe' : ' osoba' ) ) . ')';
		}
		return '#' . $order->get_order_number() . ' ' . trim( $order->get_formatted_billing_full_name() ) . ( $parts ? ' – ' . implode( ', ', $parts ) : '' );
	}

	public static function status_changed( $id, $from, $to, $order ) {
		if ( ! $order instanceof WC_Order || ! $order->get_meta( Plan_A_Clanstvo_Agency::META ) || $order->get_meta( '_paj_rez' ) ) {
			return;
		}
		$paid = array( 'processing', 'completed' );
		if ( 'completed' === $to ) {
			self::log( 'uplata', (int) $id, 'Cijelokupni iznos uplaćen: ' . self::describe( $order ) );
		} elseif ( 'processing' === $to && ! in_array( $from, $paid, true ) ) {
			self::log( 'uplata', (int) $id, ( Plan_A_Clanstvo_Agency::has_rate( $order ) ? 'Prva rata uplaćena: ' : 'Uplaćeno: ' ) . self::describe( $order ) );
		} elseif ( 'cancelled' === $to ) {
			self::log( 'otkaz', (int) $id, self::describe( $order ) );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Svaki sat                                                            */
	/* ------------------------------------------------------------------ */

	public static function hourly() {
		foreach ( array( 'reminders', 'rate_reminders', 'daily', 'guide_lists', 'january', 'treasurer' ) as $job ) {
			try {
				self::$job();
			} catch ( Throwable $e ) {
				update_option( 'plan_a_clanstvo_ops_error', current_time( 'mysql' ) . ' ' . $job . ': ' . $e->getMessage(), false );
			}
		}
	}

	/* ------------------------------------------------------------------ */
	/* 1. Podsjetnik za uplatu                                              */
	/* ------------------------------------------------------------------ */

	/** Prvi dan izleta u narudžbi (Y-m-d) ili ''. */
	private static function tour_date( WC_Order $order ): string {
		$first = '';
		foreach ( $order->get_items() as $item ) {
			$ts = strtotime( (string) $item->get_meta( '_ttbm_date' ) );
			if ( $ts && (int) $item->get_meta( '_ttbm_id' ) && ( '' === $first || gmdate( 'Y-m-d', $ts ) < $first ) ) {
				$first = gmdate( 'Y-m-d', $ts );
			}
		}
		return $first;
	}

	public static function reminders(): int {
		$mails = WC()->mailer()->get_emails();
		$email = $mails['WC_Email_Customer_On_Hold_Order'] ?? null;
		if ( ! $email || ! $email->is_enabled() ) {
			return 0;
		}
		$today  = current_time( 'Y-m-d' );
		$orders = wc_get_orders(
			array(
				'status'       => array( 'on-hold' ),
				'limit'        => 200,
				'date_created' => '>' . ( time() - 120 * DAY_IN_SECONDS ),
			)
		);
		$n     = 0;
		$notes = array();
		foreach ( $orders as $order ) {
			if ( $n >= 20 || ! $order->get_meta( Plan_A_Clanstvo_Agency::META ) || $order->get_meta( '_paj_rez' ) || $order->get_meta( self::REMIND ) ) {
				continue;
			}
			$tour = self::tour_date( $order );
			if ( '' === $tour || $tour < $today ) {
				continue;
			}
			$created = $order->get_date_created();
			$age     = $created ? ( time() - $created->getTimestamp() ) / DAY_IN_SECONDS : 0;
			$left    = ( strtotime( $tour ) - strtotime( $today ) ) / DAY_IN_SECONDS;
			if ( $age < self::REMIND_DAYS && ! ( $left <= self::REMIND_TOUR && $age >= 1 ) ) {
				continue;
			}
			$order->update_meta_data( self::REMIND, current_time( 'mysql' ) );
			$order->save_meta_data();
			self::$reminding = $order->get_id();
			$email->trigger( $order->get_id(), $order );
			self::$reminding = 0;
			$order->add_order_note( 'Kupcu je poslan podsjetnik za uplatu (automatski).' );
			self::log( 'podsjetnik', $order->get_id(), self::describe( $order ) );
			$notes[] = array( 'o' => $order->get_id(), 't' => 'podsjetnik poslan ' . current_time( 'j.n.' ) );
			++$n;
		}
		if ( $notes && Plan_A_Clanstvo_Agency::enabled() ) {
			Plan_A_Clanstvo_Agency::call( array( 'action' => 'ag_note', 'items' => $notes ) );
		}
		return $n;
	}

	public static function rate_reminders() {
		if ( Plan_A_Clanstvo_Agency::enabled() ) {
			Plan_A_Clanstvo_Agency::rate_reminders();
		}
	}

	public static function reminder_subject( $subject, $order ) {
		return self::$reminding && $order instanceof WC_Order && $order->get_id() === self::$reminding
			/* translators: %s: broj narudžbe */
			? sprintf( 'Podsjetnik: uplata za prijavu #%s', $order->get_order_number() )
			: $subject;
	}

	/** Kopija podsjetnika blagajniku (skrivena kopija). */
	public static function reminder_copy( $headers, $id, $order ) {
		$copy = self::mail( 'mail_kreso' );
		if ( 'customer_on_hold_order' !== $id || '' === $copy || ! self::$reminding || ! $order instanceof WC_Order || $order->get_id() !== self::$reminding ) {
			return $headers;
		}
		return rtrim( (string) $headers ) . "\r\nBcc: " . $copy . "\r\n";
	}

	public static function reminder_notice( $html, $order, $mode ) {
		if ( ! self::$reminding || ! $order instanceof WC_Order || $order->get_id() !== self::$reminding ) {
			return $html;
		}
		$box = '<p style="margin:0 0 6px;font-size:18px;font-weight:bold;color:#b85c00">Podsjetnik: uplata još nije zaprimljena</p>'
			. '<p style="margin:0">Hvala na prijavi! Uplatu za ovu prijavu još nismo zaprimili. Podaci za uplatu i 2D kod su niže, isti kao u prvom e-mailu. Ako ste već platili, zanemarite ovu poruku – uplata ponekad stiže dan-dva.</p>';
		return $box . ( '' !== trim( (string) $html ) ? '<hr style="border:0;border-top:1px solid #f0c28f;margin:16px 0">' . $html : '' );
	}

	/* ------------------------------------------------------------------ */
	/* 2. Dnevni pregled                                                    */
	/* ------------------------------------------------------------------ */

	public static function daily() {
		$s = self::state();
		if ( (int) current_time( 'G' ) < self::DAILY_HOUR || ( $s['daily'] ?? '' ) === current_time( 'Y-m-d' ) ) {
			return;
		}
		$s['daily'] = current_time( 'Y-m-d' );
		self::save_state( $s );
		self::summary( false );
	}

	/** Slobodna mjesta u terminu ili null (izlet bez ograničenja / nepoznato). */
	private static function free_seats( int $tour_id, string $date ): ?int {
		if ( ! $tour_id || ! class_exists( 'TTBM_Function' ) || ! method_exists( 'TTBM_Function', 'get_total_available' ) || ! method_exists( 'TTBM_Function', 'get_total_seat' ) ) {
			return null;
		}
		try {
			if ( (int) TTBM_Function::get_total_seat( $tour_id ) <= 0 ) {
				return null;
			}
			return max( 0, (int) TTBM_Function::get_total_available( $tour_id, $date ) );
		} catch ( Throwable $e ) {
			return null;
		}
	}

	/** Pošalji pregled ($test: samo vama, i kad nema novosti). Vraća broj primatelja. */
	public static function summary( bool $test ): int {
		$s     = self::state();
		$since = (int) ( $s['summary_t'] ?? ( time() - DAY_IN_SECONDS ) );
		if ( ! $test ) {
			$s['summary_t'] = time();
			self::save_state( $s );
		}
		$events = array_values( array_filter( (array) get_option( self::LOG, array() ), static fn( $e ) => (int) $e['t'] > ( $test ? time() - DAY_IN_SECONDS : $since ) ) );
		$tours  = array();
		if ( Plan_A_Clanstvo_Agency::enabled() ) {
			$today = current_time( 'Y-m-d' );
			$res   = Plan_A_Clanstvo_Agency::call( array( 'action' => 'ag_summary', 'from' => $today, 'to' => gmdate( 'Y-m-d', strtotime( $today . ' +30 days' ) ) ) );
			foreach ( (array) ( $res['blocks'] ?? array() ) as $b ) {
				$tour_id = (int) substr( strrchr( (string) $b['key'], '_' ) ?: '', 1 );
				$days    = (int) round( ( strtotime( (string) $b['date'] ) - strtotime( $today ) ) / DAY_IN_SECONDS );
				$free    = self::free_seats( $tour_id, (string) $b['date'] );
				$flags   = array();
				if ( null !== $free && $free <= self::FULL_SEATS ) {
					$flags[] = 0 === $free ? 'POPUNJEN' : 'skoro pun';
				}
				if ( (int) $b['n'] > 0 && 0 === (int) $b['paid'] && $days < self::NOBODY_DAYS ) {
					$flags[] = 'nitko nije platio';
				}
				if ( (int) ( $b['rate'] ?? 0 ) > (int) ( $b['rate2'] ?? 0 ) && $days <= 30 ) {
					$flags[] = '2. rata nije uplaćena';
				}
				if ( (int) $b['n'] > (int) ( $b['ug'] ?? 0 ) && $days <= 30 ) {
					$flags[] = 'ugovor nije poslan';
				}
				if ( (int) ( $b['polN'] ?? 0 ) > (int) ( $b['pol'] ?? 0 ) && $days <= 30 ) {
					$flags[] = 'polica nije izdana';
				}
				$tours[] = array( 'b' => $b, 'free' => $free, 'flags' => $flags, 'days' => $days );
			}
			usort( $tours, static fn( $a, $b ) => strcmp( (string) $a['b']['date'], (string) $b['b']['date'] ) );
		}
		$flagged = array_filter( $tours, static fn( $t ) => $t['flags'] );
		if ( ! $test && ! $events && ! $flagged ) {
			return 0;
		}
		$to = $test ? array( self::admin_mail() ) : array_filter( array_unique( array( self::admin_mail(), self::mail( 'mail_agency' ), self::mail( 'mail_kreso' ) ) ) );
		$html = self::summary_html( $events, $tours, self::rate_owed() );
		$subj = ( $test ? 'PROBA – ' : '' ) . 'Plan A – pregled za ' . current_time( 'j.n.Y.' );
		$n    = 0;
		foreach ( $to as $mail ) {
			$n += Plan_A_Clanstvo_Mail::send( $mail, $subj, $html ) ? 1 : 0;
		}
		return $n;
	}

	/**
	 * Narudžbe s uplaćenom prvom ratom kojima druga rata još nije uplaćena, a izlet nije prošao:
	 * [name, izlet, datum, iznos, e-mail]. Iznos je ostatak (puna cijena minus prva rata).
	 */
	public static function rate_owed(): array {
		$today = current_time( 'Y-m-d' );
		$out   = array();
		foreach ( wc_get_orders( array( 'status' => array( 'processing' ), 'limit' => 300 ) ) as $order ) {
			if ( '' === (string) $order->get_meta( Plan_A_Clanstvo_Agency::R1 ) || ! $order->get_meta( Plan_A_Clanstvo_Agency::META ) ) {
				continue;
			}
			$tour = Plan_A_Clanstvo_Agency::rate_tour_date( $order );
			if ( '' === $tour || $tour < $today ) {
				continue;
			}
			$title = '';
			foreach ( $order->get_items() as $item ) {
				if ( $item instanceof WC_Order_Item_Product && Plan_A_Clanstvo_Agency::first_rate( $item ) ) {
					$title = wp_strip_all_tags( html_entity_decode( (string) $item->get_name(), ENT_QUOTES, 'UTF-8' ) );
					break;
				}
			}
			$out[] = array(
				'name'  => trim( $order->get_formatted_billing_full_name() ),
				'izlet' => $title,
				'datum' => gmdate( 'j.n.Y.', strtotime( $tour ) ),
				'iznos' => number_format( Plan_A_Clanstvo_Agency::balance( $order ), 2, ',', '.' ) . ' €',
				'email' => (string) $order->get_billing_email(),
				'nr'    => '#' . $order->get_order_number(),
			);
		}
		usort( $out, static fn( $a, $b ) => strcmp( $a['datum'], $b['datum'] ) );
		return $out;
	}

	private static function rates_table( array $rates ): string {
		$t = '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:14px"><tr style="background:#e8eef4;color:#12304b"><th align="left" style="padding:6px">Narudžba</th><th align="left" style="padding:6px">Izlet</th><th align="left" style="padding:6px">Datum</th><th align="left" style="padding:6px">Druga rata</th></tr>';
		foreach ( $rates as $r ) {
			$t .= '<tr style="border-bottom:1px solid #e3e8ee"><td style="padding:6px">' . esc_html( $r['nr'] . ' ' . $r['name'] ) . '</td><td style="padding:6px">' . esc_html( $r['izlet'] ) . '</td><td style="padding:6px">' . esc_html( $r['datum'] ) . '</td><td style="padding:6px;font-weight:bold">' . esc_html( $r['iznos'] ) . '</td></tr>';
		}
		return $t . '</table>';
	}

	private static function summary_html( array $events, array $tours, array $rates = array() ): string {
		$names = array(
			'nova'       => 'Nove prijave',
			'uplata'     => 'Uplate (prva rata ili cijeli iznos)',
			'otkaz'      => 'Otkazane narudžbe',
			'osoba_x'    => 'Otkazali (pojedinačno)',
			'zamjena'    => 'Zamjene',
			'podsjetnik' => 'Poslani podsjetnici za uplatu',
		);
		$by = array();
		foreach ( $events as $e ) {
			$by[ (string) $e['k'] ][] = (string) $e['x'];
		}
		$inner = '';
		if ( ! $events ) {
			$inner .= Plan_A_Clanstvo_Mail::p( 'Od jučer nema novih prijava, uplata ni otkazivanja.' );
		}
		foreach ( $names as $k => $label ) {
			if ( empty( $by[ $k ] ) ) {
				continue;
			}
			$inner .= '<p style="margin:18px 0 6px;color:#12304b;font-size:17px;font-weight:bold">' . esc_html( $label ) . ' (' . count( $by[ $k ] ) . ')</p><ul style="margin:0 0 8px;padding-left:20px;font-size:15px;line-height:1.5">';
			foreach ( $by[ $k ] as $x ) {
				$inner .= '<li>' . esc_html( $x ) . '</li>';
			}
			$inner .= '</ul>';
		}
		if ( $rates ) {
			$inner .= '<p style="margin:22px 0 8px;color:#12304b;font-size:17px;font-weight:bold">Druga rata još nije uplaćena (' . count( $rates ) . ')</p>' . self::rates_table( $rates );
		}
		if ( $tours ) {
			$inner .= '<p style="margin:22px 0 8px;color:#12304b;font-size:17px;font-weight:bold">Izleti u sljedećih 30 dana</p>'
				. '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:14px">'
				. '<tr style="background:#e8eef4;color:#12304b"><th align="left" style="padding:6px">Izlet</th><th style="padding:6px">Prijavljeno</th><th style="padding:6px">Uplaćeno</th><th style="padding:6px">Slobodno</th></tr>';
			foreach ( $tours as $t ) {
				$b     = $t['b'];
				$title = implode( ' · ', array_slice( explode( ' · ', (string) $b['base'] ), 0, 2 ) );
				$flag  = $t['flags'] ? ' <span style="display:inline-block;padding:1px 8px;border-radius:999px;background:#fdebd3;color:#b85c00;font-weight:bold;font-size:12px">' . esc_html( implode( ' · ', $t['flags'] ) ) . '</span>' : '';
				$inner .= '<tr style="border-bottom:1px solid #e3e8ee"><td style="padding:6px">' . esc_html( $title ) . $flag . '</td>'
					. '<td align="center" style="padding:6px">' . (int) $b['n'] . ( (int) $b['x'] ? ' <span style="color:#8a96a3">(+' . (int) $b['x'] . ' otk.)</span>' : '' ) . '<br><span style="font-size:12px;color:#5f6b77">ugovor ' . (int) ( $b['ug'] ?? 0 ) . ' od ' . (int) $b['n'] . ( (int) ( $b['polN'] ?? 0 ) ? ' · polica ' . (int) ( $b['pol'] ?? 0 ) . ' od ' . (int) $b['polN'] : '' ) . '</span></td>'
					. '<td align="center" style="padding:6px">' . (int) $b['paid'] . ( (int) ( $b['rate'] ?? 0 ) ? '<br><span style="font-size:12px;color:#5f6b77">2. rata: ' . (int) $b['rate2'] . ' od ' . (int) $b['rate'] . '</span>' : '' ) . '</td>'
					. '<td align="center" style="padding:6px">' . ( null === $t['free'] ? '–' : (int) $t['free'] ) . '</td></tr>';
			}
			$inner .= '</table>';
		}
		$inner .= Plan_A_Clanstvo_Mail::p( '<span style="color:#5f6b77;font-size:13px">Pregled stiže svaki dan u 7 sati kad ima novosti ili izlet treba pažnju. Brojke su iz tablice „Prijave na izlete”.</span>' );
		return Plan_A_Clanstvo_Mail::wrap( 'Pregled za ' . current_time( 'j.n.Y.' ), $inner, 'Plan A · dnevni pregled' );
	}

	/* ------------------------------------------------------------------ */
	/* 3. Popis sudionika za vodiča                                         */
	/* ------------------------------------------------------------------ */

	/** Vodiči termina iz Plana izleta ("Igor + Krešo") ili ''. */
	private static function guides_for( int $tour_id, string $date ): string {
		if ( ! class_exists( 'Plan_A_Izleti_Plan' ) || ! method_exists( 'Plan_A_Izleti_Plan', 'rows' ) ) {
			return '';
		}
		foreach ( Plan_A_Izleti_Plan::rows() as $row ) {
			if ( (int) $row['tour'] === $tour_id && (string) $row['from'] === $date && '' !== (string) $row['guides'] ) {
				return (string) $row['guides'];
			}
		}
		return '';
	}

	public static function guide_lists() {
		if ( (int) current_time( 'G' ) < self::GUIDE_HOUR || ! Plan_A_Clanstvo_Agency::enabled() ) {
			return;
		}
		$s    = self::state();
		$date = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +1 day' ) );
		$sent = (array) ( $s['guides'] ?? array() );
		if ( ! empty( $sent[ $date ] ) ) {
			return;
		}
		$sent[ $date ] = 1;
		$s['guides']   = array_slice( $sent, -30, null, true );
		self::save_state( $s );
		self::send_lists( $date, false );
	}

	/** Popisi za sve izlete s datumom $date ($test: samo vama). Vraća broj poslanih e-mailova. */
	public static function send_lists( string $date, bool $test ): int {
		$res = Plan_A_Clanstvo_Agency::call( array( 'action' => 'ag_list', 'date' => $date ) );
		$n   = 0;
		foreach ( (array) ( $res['blocks'] ?? array() ) as $b ) {
			$tour_id = (int) substr( strrchr( (string) $b['key'], '_' ) ?: '', 1 );
			$guides  = self::guides_for( $tour_id, $date );
			$to      = $test ? array() : self::guide_mails( $guides );
			$to[]    = self::admin_mail();
			$mail    = self::list_mail( $b, $guides, $date );
			foreach ( array_unique( array_filter( $to ) ) as $addr ) {
				$n += Plan_A_Clanstvo_Mail::send( $addr, ( $test ? 'PROBA – ' : '' ) . $mail['subject'], $mail['html'], $mail['files'] ) ? 1 : 0;
			}
			foreach ( $mail['files'] as $f ) {
				wp_delete_file( $f );
			}
		}
		return $n;
	}

	private static function list_mail( array $b, string $guides, string $date ): array {
		$title = implode( ' · ', array_slice( explode( ' · ', (string) $b['base'] ), 0, 2 ) );
		$go    = array();
		$gone  = array();
		$todo  = array();
		foreach ( (array) $b['rows'] as $r ) {
			$is   = ! ( 'nema' === $r['pristupnica'] || '' === $r['pristupnica'] );
			$pris = 'potvrđena' === $r['pristupnica'] ? 'da' : ( $is ? 'nepotvrđena' : 'NE' );
			$fee  = $r['clanarina'] ? 'da' : 'NE';
			$name = trim( $r['ime'] . ' ' . $r['prezime'] );
			$paidtxt = is_bool( $r['rata2'] ?? null ) ? ( $r['uplaceno'] ? ( true === $r['rata2'] ? 'da' : '1. rata' ) : 'NE' ) : ( $r['uplaceno'] ? 'da' : 'NE' );
			$row  = array( $r['br'], $name, $r['mobitel'], $paidtxt, $pris, $fee, $is ? ( ! empty( $r['iskaznica'] ) ? 'da' : 'ne' ) : '–', $r['osiguranje'] ? 'da' : '' );
			if ( $r['otkazao'] ) {
				$gone[] = $row;
				continue;
			}
			$go[]  = $row;
			$need  = array();
			if ( 'NE' === $pris ) {
				$need[] = 'ispuniti pristupnicu';
			} elseif ( 'nepotvrđena' === $pris ) {
				$need[] = 'potvrditi pristupnicu (poveznica u e-mailu)';
			}
			if ( 'NE' === $fee ) {
				$need[] = 'platiti članarinu';
			}
			if ( is_bool( $r['rata2'] ?? null ) && false === $r['rata2'] ) {
				$need[] = 'platiti drugu ratu';
			}
			if ( $need ) {
				$todo[] = $name . ' – ' . implode( ' i ', $need );
			}
		}
		$unpaid = count( array_filter( $go, static fn( $r ) => 'NE' === $r[3] ) );
		$nopris = count( array_filter( $go, static fn( $r ) => 'da' !== $r[4] ) );
		$nofee  = count( array_filter( $go, static fn( $r ) => 'NE' === $r[5] ) );
		$sub    = 'Vodiči: ' . ( '' !== $guides ? $guides : '–' ) . ' · sudionika: ' . count( $go ) . ( $unpaid ? ' · nije platilo izlet: ' . $unpaid : '' ) . ( $nopris ? ' · bez pristupnice: ' . $nopris : '' ) . ( $nofee ? ' · bez članarine: ' . $nofee : '' ) . ' · stanje ' . current_time( 'j.n.Y. H:i' );
		$cols   = array( array( 'Br.', 55, 'center' ), array( 'Ime i prezime', 260, 'left' ), array( 'Mobitel', 170, 'left' ), array( 'Uplaćeno', 110, 'center' ), array( 'Pristupnica', 150, 'center' ), array( 'Članarina', 120, 'center' ), array( 'Iskaznica', 120, 'center' ), array( 'Osig.', 95, 'center' ) );
		$files  = array();
		$pdf    = Plan_A_Clanstvo_Pdf::table( $title, $sub, $cols, $go, $gone, 'Popis je iz tablice „Prijave na izlete”. Za promjene se javite agenciji.', $todo );
		if ( '' !== $pdf ) {
			$dir  = trailingslashit( get_temp_dir() );
			$file = $dir . 'popis-' . sanitize_file_name( remove_accents( strtolower( (string) preg_replace( '/\s+/', '-', explode( ' · ', (string) $b['base'] )[0] ) ) ) ) . '-' . $date . '.pdf';
			if ( false !== file_put_contents( $file, $pdf ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
				$files[] = $file;
			}
		}
		$table = '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:14px"><tr style="background:#e8eef4;color:#12304b">';
		foreach ( $cols as $c ) {
			$table .= '<th align="left" style="padding:6px">' . esc_html( $c[0] ) . '</th>';
		}
		$table .= '</tr>';
		foreach ( array_merge( $go, $gone ) as $i => $r ) {
			$x      = $i >= count( $go );
			$table .= '<tr style="border-bottom:1px solid #e3e8ee;' . ( $x ? 'color:#8a96a3;text-decoration:line-through' : '' ) . '">';
			foreach ( $r as $k => $v ) {
				$v      = 2 === $k && '' !== $v && ! $x ? '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $v ) ) . '" style="color:#1a73b8">' . esc_html( $v ) . '</a>' : esc_html( $v );
				$table .= '<td style="padding:6px' . ( 2 === $k ? ';white-space:nowrap' : '' ) . ( $k >= 3 && $k <= 5 && ! $x && 'NE' === $r[ $k ] ? ';color:#b32d2e;font-weight:bold' : '' ) . ( 4 === $k && ! $x && 'nepotvrđena' === $r[4] ? ';color:#b26200;font-weight:bold' : '' ) . '">' . $v . '</td>';
			}
			$table .= '</tr>';
		}
		$table .= '</table>';
		$fix = '';
		if ( $todo ) {
			$fix = '<div style="background:#fff4e8;border:2px solid #e8862a;border-radius:12px;padding:14px 16px;margin:16px 0"><p style="margin:0 0 6px;color:#b85c00;font-size:16px;font-weight:bold">Na izletu treba riješiti</p><ul style="margin:0;padding-left:20px;font-size:15px;line-height:1.5">';
			foreach ( $todo as $t ) {
				$fix .= '<li>' . esc_html( $t ) . '</li>';
			}
			$fix .= '</ul><p style="margin:8px 0 0;font-size:13px;color:#5f6b77">Pristupnica: srd-plan-a.hr/pristupnica · članarina se uplaćuje na račun udruge.</p></div>';
		}
		$inner  = Plan_A_Clanstvo_Mail::p( esc_html( $sub ) )
			. $fix
			. $table
			. Plan_A_Clanstvo_Mail::p( '<span style="color:#5f6b77;font-size:13px">' . ( $files ? 'Popis za print je u privitku (PDF). ' : '' ) . 'Precrtani su otkazali. Popis je iz tablice „Prijave na izlete”; za promjene se javite agenciji.</span>' );
		return array(
			'subject' => 'Popis za sutra: ' . $title,
			'html'    => Plan_A_Clanstvo_Mail::wrap( $title, $inner, 'Plan A · popis za vodiča' ),
			'files'   => $files,
		);
	}

	/** Proba: popis za prvi sljedeći izlet iz tablice, samo vama. */
	public static function test_list(): int {
		$today = current_time( 'Y-m-d' );
		$res   = Plan_A_Clanstvo_Agency::call( array( 'action' => 'ag_summary', 'from' => $today, 'to' => gmdate( 'Y-m-d', strtotime( $today . ' +90 days' ) ) ) );
		$dates = array_map( static fn( $b ) => (string) $b['date'], (array) ( $res['blocks'] ?? array() ) );
		sort( $dates );
		return $dates ? self::send_lists( $dates[0], true ) : 0;
	}

	/* ------------------------------------------------------------------ */
	/* 4. Članarina u siječnju i popis neplaćenih za blagajnika             */
	/* ------------------------------------------------------------------ */

	private static function confirmed_ids(): array {
		return get_posts(
			array(
				'post_type'      => Plan_A_Clanstvo_Data::CPT,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => '_pac_status', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => 'potvrdeno', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
	}

	public static function january( bool $force = false ) {
		$year = (int) current_time( 'Y' );
		$s    = self::state();
		$day = 1 === (int) current_time( 'n' ) && (int) current_time( 'j' ) >= self::JAN_DAY && (int) current_time( 'G' ) >= self::DAILY_HOUR;
		if ( ( ! $force && ! $day ) || empty( Plan_A_Clanstvo_Data::value( 'jan_fee' ) ) || (int) ( $s['jan_done'] ?? 0 ) === $year ) {
			return;
		}
		$sent = 0;
		$left = 0;
		foreach ( self::confirmed_ids() as $id ) {
			if ( (int) get_post_meta( $id, '_pac_jan', true ) === $year || get_post_meta( $id, self::NO_JAN, true ) ) {
				continue;
			}
			if ( $sent >= self::JAN_BATCH ) {
				++$left;
				continue;
			}
			update_post_meta( $id, '_pac_jan', $year );
			if ( Plan_A_Clanstvo_Data::fee_paid( (int) $id, $year ) ) {
				continue;
			}
			$sent += Plan_A_Clanstvo_Mail::new_year_fee( (int) $id, $year ) ? 1 : 0;
		}
		$s['jan_sent'] = (int) ( ( (int) ( $s['jan_year'] ?? 0 ) === $year ? $s['jan_sent'] ?? 0 : 0 ) ) + $sent;
		$s['jan_year'] = $year;
		if ( ! $left ) {
			$s['jan_done'] = $year;
			Plan_A_Clanstvo_Mail::send( self::admin_mail(), 'Članarina ' . $year . '. – e-mailovi su poslani', Plan_A_Clanstvo_Mail::wrap( 'Članarina ' . $year . '.', Plan_A_Clanstvo_Mail::p( 'Članovima je poslano ' . (int) $s['jan_sent'] . ' e-mailova s uplatnicom i 2D kodom za članarinu ' . $year . '. Oni koji su već platili ili su se odjavili s popisa za ovaj e-mail nisu ga dobili.' ), 'Plan A · članarina' ) );
		}
		self::save_state( $s );
	}

	/**
	 * Članovi koji su sada na popisima za izlete (od danas nadalje, bez otkazanih): iz tablice
	 * agencije (s zamjenama i ručno dodanima), a za svaki slučaj i iz narudžbi na stranici.
	 */
	public static function tour_member_ids(): array {
		$today = current_time( 'Y-m-d' );
		$ids   = array();
		$res   = Plan_A_Clanstvo_Agency::call( array( 'action' => 'ag_people', 'from' => $today ) );
		foreach ( (array) ( $res['people'] ?? array() ) as $p ) {
			$id = 0;
			if ( '' !== (string) ( $p['oib'] ?? '' ) ) {
				$id = Plan_A_Clanstvo_Data::find( 'oib', (string) $p['oib'] );
			}
			if ( ! $id && '' !== (string) ( $p['email'] ?? '' ) ) {
				$id = Plan_A_Clanstvo_Data::find( 'email', (string) $p['email'] );
			}
			if ( $id ) {
				$ids[ $id ] = $id;
			}
		}
		foreach ( (array) get_option( Plan_A_Clanstvo_Agency::ROWS, array() ) as $r ) {
			if ( ! empty( $r['b'] ) && strcmp( (string) ( $r['d'] ?? '' ), $today ) >= 0 ) {
				$id = Plan_A_Clanstvo_Data::find( 'broj', (string) $r['b'] );
				if ( $id ) {
					$ids[ $id ] = $id;
				}
			}
		}
		return array_values( $ids );
	}

	/* ---------- Odjava sa siječanjskog e-maila ---------- */

	private static function jan_key( int $id ): string {
		return substr( hash_hmac( 'sha256', 'pac-jan|' . $id, wp_salt( 'auth' ) ), 0, 24 );
	}

	/** Poveznica za odjavu (ili ponovnu prijavu) za siječanjski e-mail. */
	public static function jan_url( int $id, bool $back = false ): string {
		return add_query_arg( array_filter( array( 'pac_odjava' => $id, 'k' => self::jan_key( $id ), 'natrag' => $back ? 1 : 0 ) ), home_url( '/' ) );
	}

	public static function unsubscribe() {
		if ( ! isset( $_GET['pac_odjava'], $_GET['k'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$id = absint( $_GET['pac_odjava'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$ok = $id && Plan_A_Clanstvo_Data::CPT === get_post_type( $id ) && hash_equals( self::jan_key( $id ), sanitize_text_field( wp_unslash( $_GET['k'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		nocache_headers();
		if ( ! $ok ) {
			wp_die( 'Poveznica nije ispravna. Javite nam se na info@srd-plan-a.hr.', 'Plan A', array( 'response' => 400 ) );
		}
		$back = ! empty( $_GET['natrag'] ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( $back ) {
			delete_post_meta( $id, self::NO_JAN );
			$text = '<h1>Ponovno ste na popisu</h1><p>Početkom godine opet ćete dobiti e-mail s podacima za članarinu.</p>';
		} else {
			update_post_meta( $id, self::NO_JAN, current_time( 'mysql' ) );
			$text = '<h1>Odjavljeni ste</h1><p>Više nećete dobivati e-mail s podacima za članarinu početkom godine. Članstvo i prijave na izlete ostaju kakvi jesu.</p><p><a href="' . esc_url( self::jan_url( $id, true ) ) . '">Predomislili ste se? Vratite se na popis.</a></p>';
		}
		wp_die( $text . '<p><a href="' . esc_url( home_url( '/' ) ) . '">Plan A</a></p>', 'Plan A', array( 'response' => 200 ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/** Siječanj–travanj, prvi dan u mjesecu: članovi na popisima izleta koji nisu platili tekuću godinu. */
	public static function treasurer( bool $test = false ): int {
		$year  = (int) current_time( 'Y' );
		$month = current_time( 'Y-m' );
		$s     = self::state();
		if ( ! $test && ( (int) current_time( 'n' ) > 4 || (int) current_time( 'G' ) < self::DAILY_HOUR || ( $s['treasurer'] ?? '' ) === $month ) ) {
			return 0;
		}
		if ( ! $test ) {
			$s['treasurer'] = $month;
			self::save_state( $s );
		}
		$rows = array();
		foreach ( self::tour_member_ids() as $id ) {
			if ( Plan_A_Clanstvo_Data::fee_paid( (int) $id, $year ) ) {
				continue;
			}
			$m = Plan_A_Clanstvo_Data::get_member( (int) $id );
			if ( $m ) {
				$rows[] = $m;
			}
		}
		usort( $rows, static fn( $a, $b ) => strcoll( $a['prezime'] . ' ' . $a['ime'], $b['prezime'] . ' ' . $b['ime'] ) );
		$to = $test ? self::admin_mail() : ( self::mail( 'mail_kreso' ) ?: self::admin_mail() );
		$t  = '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:14px"><tr style="background:#e8eef4;color:#12304b"><th align="left" style="padding:6px">Br.</th><th align="left" style="padding:6px">Ime i prezime</th><th align="left" style="padding:6px">E-mail</th><th align="left" style="padding:6px">Mobitel</th></tr>';
		foreach ( $rows as $m ) {
			$t .= '<tr style="border-bottom:1px solid #e3e8ee"><td style="padding:6px">' . (int) $m['broj'] . '</td><td style="padding:6px">' . esc_html( $m['ime'] . ' ' . $m['prezime'] ) . '</td><td style="padding:6px">' . esc_html( $m['email'] ) . '</td><td style="padding:6px">' . esc_html( $m['mobitel'] ) . '</td></tr>';
		}
		$t    .= '</table>';
		$inner = Plan_A_Clanstvo_Mail::p( $rows ? 'Od članova koji su sada prijavljeni na izlete članarinu za ' . $year . '. još nije platilo njih <strong>' . count( $rows ) . '</strong>:' : 'Svi članovi koji su sada prijavljeni na izlete platili su članarinu za ' . $year . '.' )
			. ( $rows ? $t : '' )
			. ( self::rate_owed() ? Plan_A_Clanstvo_Mail::p( '<strong>Druga rata još nije uplaćena</strong> (prva rata uplaćena, izlet nije prošao):' ) . self::rates_table( self::rate_owed() ) : '' )
			. Plan_A_Clanstvo_Mail::p( '<span style="color:#5f6b77;font-size:13px">Na popisu su samo članovi koji su prijavljeni na izlete od danas nadalje (bez otkazanih); plaćanje se čita iz kvačica „Članarina ' . $year . '” u tablici članova. Stiže prvog u mjesecu, od siječnja do travnja.</span>' );
		return Plan_A_Clanstvo_Mail::send( $to, ( $test ? 'PROBA – ' : '' ) . 'Neplaćena članarina ' . $year . '. (prijavljeni na izlete) – ' . count( $rows ), Plan_A_Clanstvo_Mail::wrap( 'Neplaćena članarina ' . $year . '.', $inner, 'Plan A · članarina' ) ) ? 1 : 0;
	}

	/* ------------------------------------------------------------------ */
	/* 5. Veza s tablicom                                                   */
	/* ------------------------------------------------------------------ */

	/** Poziva se nakon svakog razgovora s tablicom. */
	public static function health( array $res ) {
		$h   = (array) get_option( self::HEALTH, array() );
		$now = time();
		$err = (string) ( $res['error'] ?? '' );
		if ( 'Adresa Google tablice nije upisana.' === $err ) {
			return;
		}
		$v = (int) ( $res['v'] ?? 0 );
		if ( $v ) {
			$h['v'] = $v;
		}
		if ( ! empty( $res['ok'] ) ) {
			if ( ! empty( $h['alerted'] ) ) {
				self::alert( 'Veza s Google tablicom opet radi', 'Veza s Google tablicom ponovno radi (' . current_time( 'j.n.Y. H:i' ) . '). Ništa ne treba raditi.' );
			}
			$h['fails']   = 0;
			$h['first']   = 0;
			$h['alerted'] = 0;
			$h['ok']      = $now;
			if ( $v && $v < Plan_A_Clanstvo_Sheets::SCRIPT_VERSION && (int) ( $h['old'] ?? 0 ) !== $v ) {
				$h['old'] = $v;
				self::alert( 'U Google tablici je stara skripta', 'Stranica radi sa skriptom v' . Plan_A_Clanstvo_Sheets::SCRIPT_VERSION . ', a u tablici je v' . $v . '. Nove mogućnosti neće raditi dok je ne zamijenite: Članovi → Postavke i tablica → Kopiraj skriptu, u tablici članova Proširenja → Apps Script, zalijepi i spremi, pa Deploy → Manage deployments → ✏️ → New version → Deploy.' );
			}
		} else {
			$h['fails'] = (int) ( $h['fails'] ?? 0 ) + 1;
			$h['first'] = (int) ( $h['first'] ?? 0 ) ?: $now;
			$h['error'] = $err;
			$h['err_t'] = $now;
			if ( $h['fails'] >= 2 && $now - $h['first'] >= 10 * MINUTE_IN_SECONDS && empty( $h['alerted'] ) ) {
				$h['alerted'] = 1;
				self::alert( 'Veza s Google tablicom ne radi', 'Stranica od ' . wp_date( 'j.n.Y. H:i', $h['first'] ) . ' ne može razgovarati s Google tablicom (' . $h['fails'] . ' pokušaja). Zadnja greška: ' . esc_html( $err ) . '<br><br>Što provjeriti: Članovi → Postavke i tablica → Provjeri vezu. Ako piše da skripta nije objavljena ili da je ključ pogrešan, ponovno kopirajte skriptu i objavite novu verziju (Deploy → Manage deployments → ✏️ → New version). Dok veza ne radi, prijave se ne gube: šalju se same kad veza proradi.' );
			}
		}
		update_option( self::HEALTH, $h, false );
	}

	private static function alert( string $title, string $text ) {
		Plan_A_Clanstvo_Mail::send( self::admin_mail(), 'Plan A: ' . $title, Plan_A_Clanstvo_Mail::wrap( $title, Plan_A_Clanstvo_Mail::p( $text ), 'Plan A · stanje sustava' ) );
	}

	/** Okvir sa stanjem za stranicu postavki. */
	public static function status_html(): string {
		$h    = (array) get_option( self::HEALTH, array() );
		$s    = self::state();
		$pull = get_option( 'plan_a_clanstvo_pull' );
		$ok   = empty( $h['fails'] );
		$rows = array(
			'Veza s tablicom'      => $ok ? '<span style="color:#1e7d3a;font-weight:bold">radi</span>' . ( ! empty( $h['ok'] ) ? ' (zadnje ' . esc_html( wp_date( 'j.n. H:i', (int) $h['ok'] ) ) . ')' : '' ) : '<span style="color:#b32d2e;font-weight:bold">ne radi</span> od ' . esc_html( wp_date( 'j.n. H:i', (int) $h['first'] ) ) . ': ' . esc_html( (string) ( $h['error'] ?? '' ) ),
			'Skripta u tablici'    => ! empty( $h['v'] ) ? 'v' . (int) $h['v'] . ( (int) $h['v'] < Plan_A_Clanstvo_Sheets::SCRIPT_VERSION ? ' <span style="color:#b32d2e;font-weight:bold">(stara – treba v' . Plan_A_Clanstvo_Sheets::SCRIPT_VERSION . ')</span>' : ' (nova)' ) : 'još nepoznato – klikni Provjeri vezu',
			'Kvačice iz tablice'   => is_array( $pull ) ? 'zadnje čitanje ' . esc_html( Plan_A_Clanstvo_Data::hr_datetime( (string) $pull['time'] ) ) : '–',
			'Dnevni pregled'       => ! empty( $s['daily'] ) ? 'zadnji ' . esc_html( Plan_A_Clanstvo_Data::hr_date( (string) $s['daily'] ) ) : 'još nije slan',
			'Članarina u siječnju' => ! empty( $s['jan_year'] ) ? (int) $s['jan_year'] . ': poslano ' . (int) ( $s['jan_sent'] ?? 0 ) . ( (int) ( $s['jan_done'] ?? 0 ) === (int) $s['jan_year'] ? ' (gotovo)' : ' (u tijeku, ' . self::JAN_BATCH . ' na sat)' ) : 'šalje se ' . self::JAN_DAY . '. siječnja',
		);
		$off = get_posts( array( 'post_type' => Plan_A_Clanstvo_Data::CPT, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true, 'meta_key' => self::NO_JAN ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$rows['Odjavljeni sa siječanjskog e-maila'] = (string) count( $off );
		$err = (string) get_option( 'plan_a_clanstvo_ops_error', '' );
		if ( '' !== $err ) {
			$rows['Zadnja greška automatike'] = esc_html( $err );
		}
		$html = '<table class="widefat striped" style="max-width:900px"><tbody>';
		foreach ( $rows as $k => $v ) {
			$html .= '<tr><th style="width:220px">' . esc_html( $k ) . '</th><td>' . $v . '</td></tr>';
		}
		return $html . '</tbody></table>';
	}
}
