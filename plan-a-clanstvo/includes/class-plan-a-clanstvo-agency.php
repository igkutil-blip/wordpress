<?php
/**
 * Tablica za agenciju "Prijave na izlete" (posebna Google tablica, list "Prijave").
 *
 * - Svaki objavljeni izlet (termin) dobiva blok na vrhu lista.
 * - Svaka osoba iz narudžbe upisuje se jednom (ključ narudžba-stavka-osoba); obrisani red
 *   se ne vraća.
 * - Uplaćeno, Pristupnica, Članarina (za godinu izleta) i Iskaznica osvježavaju se: uplata
 *   iz narudžbe, članarina i iskaznica iz tablice članova.
 * - Agencija označava Uplaćeno i Otkazao te upisuje zamjene (vidi apply_sheet). Podatke za
 *   zamjene i ručno dodane osobe skripta uzima iz tablice članova.
 * - Rezervacije jedrenja se preskaču.
 *
 * Tablicu vodi ista skripta kao tablicu članova (SpreadsheetApp.openById).
 *
 * @package Plan_A_Clanstvo
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Clanstvo_Agency {

	const ROWS  = 'plan_a_clanstvo_ag_rows';  // ključ osobe => [b: broj člana, o: narudžba, y: godina, d: datum]
	const TOURS = 'plan_a_clanstvo_ag_tours'; // ključ bloka => 1 (već poslan)
	const DIRTY = 'plan_a_clanstvo_ag_dirty'; // narudžbe kojima treba osvježiti stanje
	const META  = '_pac_ag';                  // narudžba: pending | sent
	const PAID  = '_pac_ag_paid';             // narudžba: završena kvačicom agencije
	const PREV  = '_pac_ag_prev';             // narudžba: status prije otkazivanja iz tablice
	const NAMES = '_pac_ag_names';            // narudžba: ključ osobe => zadnje ime u tablici
	const GONE  = '_pac_ag_gone';             // narudžba: ključ osobe => ime (označeno Otkazao)
	const R1    = '_pac_ag_r1';               // narudžba: prva rata potvrđena, poslan mail s ostatkom
	const R2    = '_pac_ag_r2';               // narudžba: poslan podsjetnik za drugu ratu
	const R1T   = '_pac_ag_r1t';              // narudžba: kad je označena prva rata
	const R1S   = '_pac_ag_r1s';              // narudžba: poslan mail s ostatkom (nakon sat vremena)

	public static function init() {
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'queue' ), 30, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'queue' ), 30, 1 );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'changed' ), 30, 1 );
		add_action( 'transition_post_status', array( __CLASS__, 'tour_published' ), 20, 3 );
		add_action( 'pac_ag_run', array( __CLASS__, 'run' ) );
		foreach ( array( 'customer_on_hold_order', 'customer_processing_order', 'customer_completed_order', 'customer_cancelled_order' ) as $email ) {
			add_filter( 'woocommerce_email_enabled_' . $email, array( __CLASS__, 'quiet' ), 99, 2 );
		}
	}

	/** ID tablice za agenciju iz upisane poveznice (…/spreadsheets/d/ID/…). */
	public static function id(): string {
		$url = (string) Plan_A_Clanstvo_Data::value( 'agency_url' );
		return preg_match( '#/spreadsheets/d/([A-Za-z0-9_-]{20,})#', $url, $m ) ? $m[1] : '';
	}

	public static function enabled(): bool {
		return '' !== self::id() && '' !== Plan_A_Clanstvo_Sheets::url();
	}

	private static function post( array $payload ): array {
		$payload['ag'] = self::id();
		return Plan_A_Clanstvo_Sheets::post( $payload, 90 );
	}

	/** Poziv skripte za tablicu agencije (popis za vodiča, pregled, bilješke). */
	public static function call( array $payload ): array {
		return self::enabled() ? self::post( $payload ) : array( 'ok' => false, 'error' => 'Tablica za agenciju nije povezana.' );
	}

	private static function soon() {
		if ( self::enabled() && ! wp_next_scheduled( 'pac_ag_run' ) ) {
			wp_schedule_single_event( time() + 5, 'pac_ag_run' );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Izleti                                                              */
	/* ------------------------------------------------------------------ */

	private static function is_sailing( int $tour_id, string $title ): bool {
		return (bool) apply_filters( 'plan_a_clanstvo_agency_skip_tour', (bool) preg_match( '/jedren/i', $title ), $tour_id );
	}

	/** Blok izleta: ključ, datum, naslov, tekst naslovnog reda, godina. */
	public static function tour( int $tour_id, string $start, string $end = '' ): array {
		$ts    = strtotime( $start );
		$date  = $ts ? gmdate( 'Y-m-d', $ts ) : '';
		$title = class_exists( 'Plan_A_Izleti_Data' ) ? Plan_A_Izleti_Data::title( $tour_id ) : get_the_title( $tour_id );
		$title = wp_strip_all_tags( html_entity_decode( (string) $title, ENT_QUOTES, 'UTF-8' ) );
		$when  = $ts ? gmdate( 'j.n.Y.', $ts ) : '';
		$ets   = '' !== $end ? strtotime( $end ) : false;
		if ( $ts && $ets && gmdate( 'Y-m-d', $ets ) !== $date ) {
			$when .= ' – ' . gmdate( 'j.n.Y.', $ets );
		}
		$price = '';
		if ( class_exists( 'Plan_A_Izleti_Shortcode' ) && method_exists( 'Plan_A_Izleti_Shortcode', 'get_price_html' ) ) {
			$src   = class_exists( 'Plan_A_Izleti_Data' ) && method_exists( 'Plan_A_Izleti_Data', 'source_id' ) ? Plan_A_Izleti_Data::source_id( $tour_id ) : $tour_id;
			$price = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( html_entity_decode( Plan_A_Izleti_Shortcode::get_price_html( $tour_id, $src ), ENT_QUOTES, 'UTF-8' ) ) ) );
		}
		$base = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $title ) : strtoupper( $title );
		$base = implode( ' · ', array_filter( array( $base, $when, '' !== $price ? 'kotizacija ' . $price : '' ), 'strlen' ) );
		return array(
			'key'   => $date . '_' . $tour_id,
			'date'  => $date,
			'title' => $title,
			'base'  => $base,
			'year'  => $ts ? (int) gmdate( 'Y', $ts ) : (int) gmdate( 'Y' ),
		);
	}

	/** Budući termini objavljenih izleta (najviše 3 po izletu, u sljedećih 120 dana). */
	public static function upcoming(): array {
		if ( ! class_exists( 'Plan_A_Izleti_Data' ) || ! method_exists( 'Plan_A_Izleti_Data', 'get_tour_dates' ) ) {
			return array();
		}
		$today = current_time( 'Y-m-d' );
		$until = gmdate( 'Y-m-d', strtotime( $today . ' +120 days' ) );
		$out   = array();
		foreach ( Plan_A_Izleti_Data::get_tour_dates() as $id => $row ) {
			$n = 0;
			foreach ( (array) $row['dates'] as $date ) {
				if ( $date < $today || $date > $until || $n >= 3 ) {
					continue;
				}
				$t = self::tour( (int) $id, $date );
				if ( ! self::is_sailing( (int) $id, $t['title'] ) ) {
					$out[] = $t;
					++$n;
				}
			}
		}
		// Kasniji termin prvi u paketu, raniji zadnji: svaki novi blok ide na vrh, pa je na kraju najbliži gore.
		usort( $out, static fn( $a, $b ) => strcmp( $b['date'], $a['date'] ) );
		return $out;
	}

	public static function tour_published( $new, $old, $post ) {
		if ( 'publish' === $new && $post && 'ttbm_tour' === $post->post_type ) {
			self::soon();
		}
	}

	/** Pošalji blokove za izlete koji ih još nemaju. */
	public static function send_tours(): array {
		$sent  = (array) get_option( self::TOURS, array() );
		$tours = array_values( array_filter( self::upcoming(), static fn( $t ) => empty( $sent[ $t['key'] ] ) ) );
		if ( ! $tours ) {
			return array( 'ok' => true, 'n' => 0 );
		}
		$tours = array_reverse( $tours ); // od najdaljeg prema najbližem
		$res   = self::post( array( 'action' => 'ag_tours', 'tours' => $tours ) );
		if ( $res['ok'] ) {
			foreach ( $tours as $t ) {
				$sent[ $t['key'] ] = 1;
			}
			update_option( self::TOURS, $sent, false );
		}
		return $res;
	}

	/* ------------------------------------------------------------------ */
	/* Osobe iz narudžbe                                                   */
	/* ------------------------------------------------------------------ */

	private static function skip( $order ): bool {
		return ! $order instanceof WC_Order || $order->get_meta( '_paj_rez' );
	}

	/** Podaci osobe za tablicu: iz pristupnice ako je pronađena, inače iz narudžbe. */
	private static function person_values( array $p, ?array $m ): array {
		$v = array(
			'ime'      => $p['ime'] ?? '',
			'prezime'  => $p['prezime'] ?? '',
			'oib'      => $p['oib'] ?? '',
			'datum'    => $p['datum'] ?? '',
			'adresa'   => $p['adresa'] ?? '',
			'mjesto'   => $p['mjesto'] ?? '',
			'mobitel'  => $p['mobitel'] ?? '',
			'email'    => $p['email'] ?? '',
			'prijavio' => $p['prijavio'] ?? '',
		);
		if ( $m ) {
			foreach ( array( 'ime', 'prezime', 'oib', 'adresa', 'mjesto', 'mobitel', 'email' ) as $k ) {
				if ( '' !== $m[ $k ] ) {
					$v[ $k ] = $m[ $k ];
				}
			}
			if ( '' !== $m['datum'] ) {
				$v['datum'] = Plan_A_Clanstvo_Data::hr_date( $m['datum'] );
			}
		}
		return $v;
	}

	/** Stanje za stupce koji se osvježavaju. */
	private static function status( ?array $m, int $year ): array {
		if ( ! $m ) {
			return array( 'pristupnica' => 'nema', 'clanarina' => false, 'iskaznica' => false );
		}
		return array(
			'pristupnica' => 'potvrdeno' === $m['status'] ? 'potvrđena' : 'nije potvrđena',
			'clanarina'   => Plan_A_Clanstvo_Data::fee_paid( (int) $m['id'], $year ),
			'iskaznica'   => (bool) get_post_meta( (int) $m['id'], '_pac_kartica', true ),
		);
	}

	private static function member_by_email( string $email ): ?array {
		$id = Plan_A_Clanstvo_Data::find( 'email', $email );
		return $id ? Plan_A_Clanstvo_Data::get_member( $id ) : null;
	}

	/**
	 * Osobe stavke: iz košarice (meta _pac_osobe: kupac i ostali sudionici) ili kupac i
	 * prazni redovi "N. osoba" za ostale.
	 */
	private static function persons( WC_Order $order, WC_Order_Item_Product $item, int $count ): array {
		$buyer = trim( $order->get_formatted_billing_full_name() );
		$list  = $item->get_meta( '_pac_osobe' );
		if ( ! is_array( $list ) || ! $list ) {
			$list = array(
				array(
					'ime'     => $order->get_billing_first_name(),
					'prezime' => $order->get_billing_last_name(),
					'adresa'  => trim( $order->get_billing_address_1() . ' ' . $order->get_billing_address_2() ),
					'mjesto'  => trim( $order->get_billing_postcode() . ' ' . $order->get_billing_city() ),
					'mobitel' => $order->get_billing_phone(),
					'email'   => $order->get_billing_email(),
				),
			);
			for ( $i = 2; $i <= $count; $i++ ) {
				$list[] = array( 'ime' => $i . '. osoba', 'prezime' => '(upisati)', 'ph' => 1 );
			}
		}
		$v2 = (bool) $item->get_meta( '_pac_osobe_v' );
		foreach ( $list as $i => &$p ) {
			$p          = (array) $p;
			$p['kupac'] = $v2 ? ! empty( $p['kupac'] ) : 0 === $i;
			if ( ! $p['kupac'] && empty( $p['prijavio'] ) ) {
				$p['prijavio'] = $buyer;
			}
		}
		return $list;
	}

	/** Stavka je prva rata (naziv karte „Uplata prve rate”). */
	public static function first_rate( WC_Order_Item_Product $item ): bool {
		foreach ( (array) $item->get_meta( '_ttbm_ticket_info' ) as $t ) {
			$name = (string) ( $t['ticket_name'] ?? $t['ticket_type_name'] ?? '' );
			if ( preg_match( '/prve\s+rate/iu', $name ) ) {
				return true;
			}
		}
		return false;
	}

	/** Puna cijena po osobi za izlet (prva karta koja nije prva rata). */
	private static function full_price( int $tour_id ): float {
		if ( ! class_exists( 'TTBM_Function' ) || ! method_exists( 'TTBM_Function', 'get_ticket_type' ) ) {
			return 0.0;
		}
		foreach ( (array) TTBM_Function::get_ticket_type( $tour_id ) as $t ) {
			if ( ! preg_match( '/prve\s+rate/iu', (string) ( $t['ticket_type_name'] ?? '' ) ) ) {
				return (float) ( $t['ticket_price'] ?? 0 );
			}
		}
		return 0.0;
	}

	/** Ostatak za drugu ratu: puna cijena (po osobi × osobe) minus uplaćena prva rata. */
	public static function balance( WC_Order $order ): float {
		$sum = 0.0;
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product || ! self::first_rate( $item ) ) {
				continue;
			}
			$qty = 0;
			foreach ( (array) $item->get_meta( '_ttbm_ticket_info' ) as $t ) {
				$qty += (int) ( $t['ticket_qty'] ?? 0 );
			}
			$qty  = max( 1, $qty ?: (int) $item->get_quantity() );
			$paid = (float) $item->get_total() + (float) $item->get_total_tax();
			$sum += max( 0.0, self::full_price( (int) $item->get_meta( '_ttbm_id' ) ) * $qty - $paid );
		}
		return round( $sum, 2 );
	}

	/** Najraniji datum izleta s prvom ratom (Y-m-d) ili ''. */
	private static function rate_tour_date( WC_Order $order ): string {
		$first = '';
		foreach ( $order->get_items() as $item ) {
			if ( $item instanceof WC_Order_Item_Product && self::first_rate( $item ) ) {
				$ts = strtotime( (string) $item->get_meta( '_ttbm_date' ) );
				if ( $ts && ( '' === $first || gmdate( 'Y-m-d', $ts ) < $first ) ) {
					$first = gmdate( 'Y-m-d', $ts );
				}
			}
		}
		return $first;
	}

	/**
	 * Mail s ostatkom za drugu ratu i uplatnicom. $kind: first (nakon prve rate) ili reminder
	 * (30 dana prije izleta).
	 */
	public static function rate_mail( WC_Order $order, string $kind ): bool {
		$to = sanitize_email( (string) $order->get_billing_email() );
		$amount = self::balance( $order );
		if ( ! is_email( $to ) || $amount <= 0 ) {
			return false;
		}
		$tour  = self::rate_tour_date( $order );
		$due   = '' !== $tour ? strtotime( $tour ) - 30 * DAY_IN_SECONDS : 0;
		$when  = $due ? 'do ' . gmdate( 'j.n.Y.', $due ) : 'što prije';
		$code  = Plan_A_Clanstvo_Hub3::for_order( $order, $amount );
		if ( ! $code ) {
			// Bez točnog koda kupac dobiva iznos i poziv na broj, bez 2D koda.
			$code = null;
		}
		$name  = trim( $order->get_billing_first_name() ) ?: 'poštovani';
		$money = number_format( $amount, 2, ',', '.' ) . ' €';
		$rows  = array(
			'Iznos'         => $money,
			'Primatelj'     => Plan_A_Clanstvo_Data::value( 'primatelj' ) . ', ' . Plan_A_Clanstvo_Data::value( 'adresa' ) . ', ' . Plan_A_Clanstvo_Data::value( 'mjesto' ),
			'IBAN'          => Plan_A_Clanstvo_Data::value( 'iban' ),
			'Poziv na broj' => (string) $order->get_order_number(),
			'Opis plaćanja' => 'Druga rata, narudžba ' . $order->get_order_number(),
		);
		$table = '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:15px">';
		foreach ( $rows as $k => $v ) {
			$table .= '<tr><td style="padding:5px 10px 5px 0;color:#5f6b77;white-space:nowrap">' . esc_html( $k ) . '</td><td style="padding:5px 0;font-weight:bold">' . esc_html( $v ) . '</td></tr>';
		}
		$table .= '</table>';
		$img    = $code && $code['url'] ? '<img src="' . esc_url( $code['url'] ) . '" alt="2D kod za uplatu druge rate" style="display:block;width:100%;max-width:420px;height:auto;margin:0 auto 14px">' : '';
		$intro  = 'first' === $kind
			? 'Primili smo <strong>prvu ratu</strong> za tvoju prijavu. Hvala!'
			: 'podsjećamo te da <strong>druga rata</strong> za tvoj izlet još nije uplaćena.';
		$inner  = Plan_A_Clanstvo_Mail::p( 'Pozdrav ' . esc_html( $name ) . ',' )
			. Plan_A_Clanstvo_Mail::p( $intro . ' Ostatak uplate je <strong>' . esc_html( $money ) . '</strong>, a treba biti uplaćen ' . esc_html( $when ) . ' (30 dana prije izleta).' )
			. '<div style="background:#f3f8fc;border-radius:14px;padding:18px 18px 10px;margin:6px 0 16px">' . $img . $table . '</div>'
			. Plan_A_Clanstvo_Mail::p( '<span style="color:#5f6b77;font-size:14px">Ako si ostatak već uplatio/la, zanemari ovu poruku. Pitanja: info@srd-plan-a.hr.</span>' );
		$subj   = 'first' === $kind ? 'Prva rata je primljena – druga rata ' . $when : 'Podsjetnik: druga rata ' . $when;
		$files  = $code && $code['path'] ? array( $code['path'] ) : array();
		$ok     = Plan_A_Clanstvo_Mail::send( $to, $subj, Plan_A_Clanstvo_Mail::wrap( 'Druga rata', $inner, 'Plan A · uplata' ), $files );
		foreach ( $files as $f ) {
			wp_delete_file( $f );
		}
		if ( $ok ) {
			$order->add_order_note( ( 'first' === $kind ? 'Kupcu je poslan mail s ostatkom za drugu ratu' : 'Kupcu je poslan podsjetnik za drugu ratu' ) . ' (' . $money . ').' );
			// Ako je prva rata uplaćena u zadnjih 35 dana prije izleta, podsjetnik nije potreban.
			if ( 'first' === $kind && '' !== $tour && current_time( 'Y-m-d' ) >= gmdate( 'Y-m-d', strtotime( $tour ) - 35 * DAY_IN_SECONDS ) ) {
				$order->update_meta_data( self::R2, current_time( 'mysql' ) );
				$order->save_meta_data();
			}
		}
		return $ok;
	}

	/**
	 * Mail s ostatkom za prvu ratu: nakon sat vremena od kvačice „Uplaćeno”, ako u tom roku nije
	 * stigla i „2. rata” (tada je narudžba već završena i mail je o završetku).
	 */
	public static function rate_first_due(): int {
		$n = 0;
		foreach ( wc_get_orders( array( 'status' => array( 'processing' ), 'limit' => 200 ) ) as $order ) {
			$t = (int) $order->get_meta( self::R1T );
			if ( ! $t || '' !== (string) $order->get_meta( self::R1S ) || time() - $t < HOUR_IN_SECONDS ) {
				continue;
			}
			$order->update_meta_data( self::R1S, current_time( 'mysql' ) );
			$order->save_meta_data();
			$n += self::rate_mail( $order, 'first' ) ? 1 : 0;
		}
		return $n;
	}

	/** Svaki sat: mail s ostatkom za prvu ratu (nakon sat vremena) i podsjetnik za drugu ratu 35 dana prije izleta. */
	public static function rate_reminders(): int {
		$n     = self::rate_first_due();
		$today = current_time( 'Y-m-d' );
		foreach ( wc_get_orders( array( 'status' => array( 'processing' ), 'limit' => 200 ) ) as $order ) {
			if ( '' === (string) $order->get_meta( self::R1S ) || '' !== (string) $order->get_meta( self::R2 ) || ! $order->get_meta( self::META ) ) {
				continue;
			}
			$tour = self::rate_tour_date( $order );
			if ( '' === $tour || $tour < $today || gmdate( 'Y-m-d', strtotime( $tour ) - 35 * DAY_IN_SECONDS ) > $today ) {
				continue;
			}
			$order->update_meta_data( self::R2, current_time( 'mysql' ) );
			$order->save_meta_data();
			$n += self::rate_mail( $order, 'reminder' ) ? 1 : 0;
		}
		return $n;
	}

	/** Retci za tablicu iz jedne narudžbe. */
	public static function order_rows( WC_Order $order ): array {
		$rows = array();
		$map  = (array) get_option( self::ROWS, array() );
		foreach ( $order->get_items() as $item_id => $item ) {
			if ( ! $item instanceof WC_Order_Item_Product || ! (int) $item->get_meta( '_ttbm_id' ) ) {
				continue;
			}
			$tour_id = (int) $item->get_meta( '_ttbm_id' );
			$tour    = self::tour( $tour_id, (string) $item->get_meta( '_ttbm_date' ), (string) $item->get_meta( '_ttbm_end_date' ) );
			if ( self::is_sailing( $tour_id, $tour['title'] ) ) {
				continue;
			}
			$rate = self::first_rate( $item );
			$count = 0;
			foreach ( (array) $item->get_meta( '_ttbm_ticket_info' ) as $t ) {
				$count += (int) ( $t['ticket_qty'] ?? 0 );
			}
			$count = max( 1, $count ?: (int) $item->get_quantity() );
			$ins   = false;
			foreach ( (array) $item->get_meta( '_ttbm_service_info' ) as $sv ) {
				if ( preg_match( '/osigur/i', (string) ( $sv['service_name'] ?? '' ) ) && (int) ( $sv['service_qty'] ?? 1 ) > 0 ) {
					$ins = true;
				}
			}
			foreach ( self::persons( $order, $item, $count ) as $n => $p ) {
				$m = ! empty( $p['kupac'] ) ? self::member_by_email( (string) $order->get_billing_email() ) : null;
				if ( ! $m && ! empty( $p['email'] ) ) {
					$m = self::member_by_email( (string) $p['email'] );
				}
				if ( ! $m && ! empty( $p['oib'] ) ) {
					$id = Plan_A_Clanstvo_Data::find( 'oib', (string) $p['oib'] );
					$m  = $id ? Plan_A_Clanstvo_Data::get_member( $id ) : null;
				}
				$pkey = 'o' . $order->get_id() . '-' . $item_id . '-' . ( $n + 1 );
				$v    = self::person_values( $p, $m ) + ( empty( $p['ph'] ) ? self::status( $m, $tour['year'] ) : array() );
				$v   += array(
					'iznos'      => 0 === $n ? html_entity_decode( wp_strip_all_tags( wc_price( (float) $item->get_total() + (float) $item->get_total_tax(), array( 'currency' => $order->get_currency() ) ) ), ENT_QUOTES, 'UTF-8' ) : '',
					'osiguranje' => $ins && 0 === $n,
					'uplaceno'   => $order->is_paid(),
					'narudzba'   => '#' . $order->get_order_number(),
					'otkazano'   => $order->has_status( array( 'cancelled', 'refunded', 'failed' ) ),
					'rate'       => $rate,
					'rata2'      => false,
				);
				$rows[]       = array(
					'tour' => $tour,
					'pkey' => $pkey,
					'v'    => $v,
				);
				$map[ $pkey ] = array(
					'b'  => $m ? (int) $m['broj'] : 0,
					'e'  => $m ? '' : strtolower( (string) ( $p['email'] ?? '' ) ),
					'h'  => empty( $p['ph'] ) ? 0 : 1,
					'rt' => $rate ? 1 : 0,
					'o' => $order->get_id(),
					'y' => $tour['year'],
					'd' => $tour['date'],
				);
			}
		}
		update_option( self::ROWS, $map, false );
		return $rows;
	}

	public static function queue( $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		if ( self::skip( $order ) ) {
			return;
		}
		if ( ! $order->get_meta( self::META ) ) {
			Plan_A_Clanstvo_Ops::log( 'nova', $order->get_id(), Plan_A_Clanstvo_Ops::describe( $order ) );
		}
		$order->update_meta_data( self::META, 'pending' );
		$order->save_meta_data();
		self::soon();
	}

	public static function changed( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( self::skip( $order ) ) {
			return;
		}
		if ( ! $order->get_meta( self::META ) && 'checkout-draft' !== $order->get_status() ) {
			self::queue( $order ); // narudžba napravljena u adminu ili bez košarice
			return;
		}
		$dirty                = (array) get_option( self::DIRTY, array() );
		$dirty[ $order->get_id() ] = 1;
		update_option( self::DIRTY, $dirty, false );
		self::soon();
	}

	/* ------------------------------------------------------------------ */
	/* Slanje                                                               */
	/* ------------------------------------------------------------------ */

	public static function send_orders(): array {
		$orders = wc_get_orders(
			array(
				'limit'      => 20,
				'meta_key'   => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value' => 'pending', // phpcs:ignore WordPress.DB.SlowDBQuery
				'orderby'    => 'date',
				'order'      => 'ASC',
			)
		);
		$n = 0;
		foreach ( $orders as $order ) {
			$rows = self::order_rows( $order );
			$res  = $rows ? self::post( array( 'action' => 'ag_add', 'rows' => $rows ) ) : array( 'ok' => true );
			if ( ! $res['ok'] ) {
				return array( 'ok' => false, 'n' => $n, 'error' => $res['error'] ?? '' );
			}
			$order->update_meta_data( self::META, 'sent' );
			$order->save_meta_data();
			++$n;
		}
		return array( 'ok' => true, 'n' => $n );
	}

	/** Stanje redova (uplata, pristupnica, članarina, iskaznica). $orders = samo te narudžbe. */
	public static function send_status( array $orders = array(), array $members = array() ): array {
		$map   = (array) get_option( self::ROWS, array() );
		$from  = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -10 days' ) );
		$items = array();
		$cache = array();
		foreach ( $map as $pkey => $r ) {
			if ( $orders && ! in_array( (int) ( $r['o'] ?? 0 ), $orders, true ) ) {
				continue;
			}
			if ( $members && ! in_array( (int) ( $r['b'] ?? 0 ), $members, true ) ) {
				continue;
			}
			if ( ! $orders && strcmp( (string) $r['d'], substr( $from, 0, strlen( (string) $r['d'] ) ) ) < 0 ) {
				continue; // prošli izleti se više ne osvježavaju
			}
			$m = null;
			if ( ! empty( $r['b'] ) ) {
				$id = Plan_A_Clanstvo_Data::find( 'broj', (string) $r['b'] );
				$m  = $id ? Plan_A_Clanstvo_Data::get_member( $id ) : null;
			} elseif ( ! empty( $r['e'] ) ) {
				$m = self::member_by_email( (string) $r['e'] ); // u međuvremenu ispunio pristupnicu
			}
			$st   = self::status( $m, (int) $r['y'] );
			$it   = array(
				'k'  => $pkey,
				'rt' => ! empty( $r['rt'] ),
				'u' => null,
				'p' => ! empty( $r['h'] ) ? null : ( $m || ! empty( $r['e'] ) || ! empty( $r['o'] ) ? $st['pristupnica'] : null ),
				'c' => $m ? $st['clanarina'] : null,
				'i' => $m ? $st['iskaznica'] : null,
				'x' => false,
			);
			if ( ! empty( $r['o'] ) ) {
				$oid = (int) $r['o'];
				if ( ! isset( $cache[ $oid ] ) ) {
					$o             = wc_get_order( $oid );
					$cache[ $oid ] = $o ? array( $o->is_paid(), $o->has_status( array( 'cancelled', 'refunded', 'failed' ) ) ) : array( null, false );
				}
				$it['u'] = $cache[ $oid ][0] ? true : null; // neplaćenu ne diraj: kvačicu stavlja agencija
				$it['x'] = $cache[ $oid ][1];
			}
			$items[] = $it;
		}
		if ( ! $items ) {
			return array( 'ok' => true, 'n' => 0 );
		}
		$n = 0;
		foreach ( array_chunk( $items, 200 ) as $chunk ) {
			$res = self::post( array( 'action' => 'ag_status', 'items' => $chunk ) );
			if ( ! $res['ok'] ) {
				return array( 'ok' => false, 'n' => $n, 'error' => $res['error'] ?? '' );
			}
			$n += count( $chunk );
		}
		return array( 'ok' => true, 'n' => $n );
	}

	/** Nove narudžbe, novi izleti i promijenjene narudžbe (odmah nakon narudžbe). */
	public static function run() {
		if ( ! self::enabled() ) {
			return;
		}
		self::send_tours();
		self::send_orders();
		$dirty = array_map( 'intval', array_keys( (array) get_option( self::DIRTY, array() ) ) );
		if ( $dirty ) {
			$res = self::send_status( $dirty );
			if ( $res['ok'] ) {
				delete_option( self::DIRTY );
			}
		}
	}

	/** Svaki sat (nakon čitanja kvačica iz tablice članova). */
	public static function cron(): array {
		if ( ! self::enabled() ) {
			return array( 'ok' => false, 'error' => 'Tablica za agenciju nije povezana.' );
		}
		self::refresh_rates_once();
		self::pull_sheet();
		self::run();
		return self::send_status();
	}

	/**
	 * Jednokratno (verzija 1.13): otvorene narudžbe s prvom ratom dobiju oznaku izleta s dvije
	 * rate, pa tablica doda stupac „2. rata” za njih.
	 */
	public static function refresh_rates_once() {
		if ( get_option( 'plan_a_clanstvo_rates_v13' ) ) {
			return;
		}
		foreach ( wc_get_orders( array( 'limit' => 500, 'meta_key' => self::META, 'meta_value' => 'sent', 'status' => array( 'pending', 'on-hold', 'processing' ) ) ) as $order ) { // phpcs:ignore WordPress.DB.SlowDBQuery
			foreach ( $order->get_items() as $item ) {
				if ( $item instanceof WC_Order_Item_Product && self::first_rate( $item ) ) {
					self::order_rows( $order );
					break;
				}
			}
		}
		update_option( 'plan_a_clanstvo_rates_v13', 1, false );
	}

	/* ------------------------------------------------------------------ */
	/* Kvačice agencije: Uplaćeno, Otkazao, zamjena osobe                   */
	/* ------------------------------------------------------------------ */

	/** Narudžbe kojima se status mijenja bez e-maila kupcu. */
	private static $quiet = array();

	public static function quiet( $enabled, $order ) {
		return $order instanceof WC_Order && isset( self::$quiet[ $order->get_id() ] ) ? false : $enabled;
	}

	private static function set_status( WC_Order $order, string $status, string $note, bool $quiet ) {
		if ( $quiet ) {
			self::$quiet[ $order->get_id() ] = 1;
		}
		$order->update_status( $status, $note );
		unset( self::$quiet[ $order->get_id() ] );
	}

	/**
	 * Stanje iz tablice: orders [{o: ID, paid: plaćeni svi koji idu, cancel: otkazali svi,
	 * rows: [{k: ključ osobe, n: ime u tablici, f: ime s prijave, x: otkazao}]}].
	 *
	 * - Uplaćeno kod svih koji idu → "Završeno" (kupac dobiva e-mail). Maknuta kvačica →
	 *   "Na čekanju" bez e-maila, samo ako je narudžbu završila agencija.
	 * - Otkazali svi → "Otkazano" (e-mail samo vama). Maknuta kvačica → prijašnji status bez
	 *   e-maila. Pojedinačna otkazivanja i zamjene zapisuju se u bilješke narudžbe.
	 * - Kartično plaćene narudžbe ostaju kakve jesu, a otkazane na stranici se ne diraju.
	 */
	public static function apply_sheet( array $orders ): int {
		$n = 0;
		foreach ( array_slice( $orders, 0, 1000 ) as $it ) {
			$order = is_array( $it ) ? wc_get_order( (int) ( $it['o'] ?? 0 ) ) : null;
			if ( self::skip( $order ) || ! $order->get_meta( self::META ) || $order->has_status( array( 'failed', 'checkout-draft', 'trash' ) ) ) {
				continue;
			}
			$done = self::sheet_people( $order, array_filter( (array) ( $it['rows'] ?? array() ), 'is_array' ), ! empty( $it['cancel'] ) );
			if ( $order->has_status( array( 'cancelled', 'refunded' ) ) ) {
				$n += $done ? 1 : 0;
				continue;
			}
			$paid  = ! empty( $it['paid'] );
			$rate  = ! empty( $it['rate'] );
			$rate2 = ! $rate || ! empty( $it['rate2'] ); // druga rata uplaćena (ili nema rata)
			if ( $paid && $rate2 ) {
				if ( ! $order->has_status( 'completed' ) ) {
					$order->update_meta_data( self::PAID, current_time( 'mysql' ) );
					$order->update_status( 'completed', 'Uplatu je potvrdila agencija (kvačica „Uplaćeno” i „2. rata” u tablici „Prijave na izlete”).' );
					$done = true;
				}
			} elseif ( $paid && $rate ) {
				// Prva rata: narudžba je u obradi, a kupcu ide mail s ostatkom (jednom).
				// Mail s ostatkom šalje se tek nakon sat vremena (rate_first_due), da stigne i kvačica „2. rata”.
				if ( '' === (string) $order->get_meta( self::R1 ) && ! $order->has_status( array( 'processing', 'completed' ) ) ) {
					$order->update_meta_data( self::R1, current_time( 'mysql' ) );
					$order->update_meta_data( self::R1T, time() );
					$order->save_meta_data();
					self::set_status( $order, 'processing', 'Prvu ratu je potvrdila agencija (kvačica „Uplaćeno”). Druga rata se uplaćuje 30 dana prije izleta.', true );
					$done = true;
				}
			} elseif ( ! $paid && $order->has_status( 'completed' ) && '' !== (string) $order->get_meta( self::PAID ) ) {
				$order->delete_meta_data( self::PAID );
				self::set_status( $order, $rate ? 'processing' : 'on-hold', $rate ? 'Agencija je maknula kvačicu „Uplaćeno” ili „2. rata” – narudžba je vraćena (kupcu nije poslan e-mail).' : 'Agencija je maknula kvačicu „Uplaćeno” – narudžba je vraćena na čekanje (kupcu nije poslan e-mail).', true );
				$done = true;
			} elseif ( ! $paid && '' !== (string) $order->get_meta( self::R1 ) && $order->has_status( 'processing' ) ) {
				$order->delete_meta_data( self::R1 );
				self::set_status( $order, 'on-hold', 'Agencija je maknula kvačicu „Uplaćeno” (prva rata) – narudžba je vraćena na čekanje (kupcu nije poslan e-mail).', true );
				$done = true;
			}
			$n += $done ? 1 : 0;
		}
		return $n;
	}

	/** Zamjene i otkazivanja osoba (bilješke) te otkazivanje cijele narudžbe. */
	private static function sheet_people( WC_Order $order, array $rows, bool $all ): bool {
		$names = $order->get_meta( self::NAMES );
		$names = is_array( $names ) ? $names : array();
		$was   = $order->get_meta( self::GONE );
		$first = ! is_array( $was );
		$was   = $first ? array() : $was;
		$gone  = array();
		$notes = array();
		$new   = false;
		foreach ( $rows as $r ) {
			$k    = sanitize_key( (string) ( $r['k'] ?? '' ) );
			$name = sanitize_text_field( (string) ( $r['n'] ?? '' ) );
			if ( '' === $k ) {
				continue;
			}
			$prev = (string) ( $names[ $k ] ?? sanitize_text_field( (string) ( $r['f'] ?? '' ) ) );
			if ( '' !== $name && '' !== $prev && $prev !== $name ) {
				$notes[] = preg_match( '/^\d+\. osoba/', $prev )
					? sprintf( 'Upisan sudionik (tablica agencije): %s.', $name )
					: sprintf( 'Zamjena (tablica agencije): %1$s umjesto %2$s.', $name, $prev );
				if ( ! preg_match( '/^\d+\. osoba/', $prev ) ) {
					Plan_A_Clanstvo_Ops::log( 'zamjena', $order->get_id(), '#' . $order->get_order_number() . ': ' . $name . ' umjesto ' . $prev );
				}
			}
			if ( '' !== $name ) {
				$names[ $k ] = $name;
			}
			if ( ! empty( $r['x'] ) ) {
				$gone[ $k ] = $name;
				// Prvi put: kod već otkazane narudžbe to nije novo otkazivanje.
				if ( empty( $was[ $k ] ) && ! ( $first && $order->has_status( array( 'cancelled', 'refunded' ) ) ) ) {
					$notes[] = sprintf( 'Otkazao/la (tablica agencije): %s.', $name );
					Plan_A_Clanstvo_Ops::log( 'osoba_x', $order->get_id(), '#' . $order->get_order_number() . ': ' . $name );
					$new     = true;
				}
			} elseif ( ! empty( $was[ $k ] ) ) {
				$notes[] = sprintf( 'Više nije otkazan/a (tablica agencije): %s.', $name );
			}
		}
		$order->update_meta_data( self::NAMES, $names );
		$order->update_meta_data( self::GONE, $gone );
		$order->save_meta_data();
		foreach ( $notes as $note ) {
			$order->add_order_note( $note );
		}
		$prev = (string) $order->get_meta( self::PREV );
		if ( $all && $rows && $new && ! $order->has_status( array( 'cancelled', 'refunded' ) ) ) {
			$from = $order->get_status();
			$order->update_meta_data( self::PREV, $from );
			self::set_status( $order, 'cancelled', 'Otkazali su svi sudionici (kvačica „Otkazao” u tablici agencije).', true );
			// WooCommerce sam javlja vama samo za otkazivanje iz "Na čekanju" ili "U obradi".
			if ( ! in_array( $from, array( 'pending', 'on-hold', 'processing' ), true ) ) {
				$mails = WC()->mailer()->get_emails();
				if ( isset( $mails['WC_Email_Cancelled_Order'] ) ) {
					$mails['WC_Email_Cancelled_Order']->trigger( $order->get_id(), $order );
				}
			}
			return true;
		}
		if ( ! $all && '' !== $prev && $order->has_status( 'cancelled' ) ) {
			$order->delete_meta_data( self::PREV );
			self::set_status( $order, $prev, 'Agencija je maknula kvačicu „Otkazao” – narudžba je vraćena (kupcu nije poslan e-mail).', true );
			return true;
		}
		return (bool) $notes;
	}

	/** Svaki sat: pročitaj kvačice iz tablice (ako javljanje iz tablice nije stiglo). */
	public static function pull_sheet(): array {
		$res = self::post( array( 'action' => 'ag_paid' ) );
		if ( ! $res['ok'] || ! isset( $res['orders'] ) || ! is_array( $res['orders'] ) ) {
			return array( 'ok' => false, 'n' => 0, 'error' => $res['error'] ?? '' );
		}
		return array( 'ok' => true, 'n' => self::apply_sheet( $res['orders'] ) );
	}

	/* ------------------------------------------------------------------ */
	/* Početni popis (stara tablica)                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * CSV: Ključ izleta, Izlet, Datum, Godina, Ime, Prezime, OIB, Datum rođenja, Adresa,
	 * Mjesto, Mobitel, E-mail, Prijavio/la, Iznos, Osiguranje, Uplaćeno, Otkazano,
	 * Napomena, Br. člana. Prvi izlet u datoteci bit će na vrhu.
	 */
	public static function import( string $file ): array {
		if ( ! self::enabled() ) {
			return array( 'ok' => false, 'error' => 'najprije upiši poveznicu tablice za agenciju i spremi postavke.' );
		}
		$fh = fopen( $file, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $fh ) {
			return array( 'ok' => false, 'error' => 'datoteka nije pročitana.' );
		}
		$head = fgetcsv( $fh, 0, ',', '"', '' );
		$head = array_map( static fn( $h ) => strtolower( remove_accents( trim( (string) preg_replace( '/^\xEF\xBB\xBF/', '', (string) $h ) ) ) ), (array) $head );
		$col  = array_flip( $head );
		$get  = static fn( $r, $k ) => isset( $col[ $k ] ) ? trim( (string) ( $r[ $col[ $k ] ] ?? '' ) ) : '';
		if ( ! isset( $col['kljuc izleta'], $col['ime'] ) ) {
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			return array( 'ok' => false, 'error' => 'nedostaju stupci „Ključ izleta” i „Ime”.' );
		}
		$tours = array();
		$rows  = array();
		$map   = (array) get_option( self::ROWS, array() );
		$i     = 0;
		$yes   = static fn( $v ) => in_array( strtolower( remove_accents( $v ) ), array( 'da', '1', 'x', 'true' ), true );
		while ( ( $r = fgetcsv( $fh, 0, ',', '"', '' ) ) !== false ) {
			$key = 'old-' . sanitize_key( $get( $r, 'kljuc izleta' ) );
			if ( 'old-' === $key ) {
				continue;
			}
			$date = preg_replace( '/[^0-9-]/', '', $get( $r, 'datum' ) );
			$year = (int) $get( $r, 'godina' );
			if ( ! isset( $tours[ $key ] ) ) {
				$title          = sanitize_text_field( $get( $r, 'izlet' ) );
				$tours[ $key ]  = array(
					'key'   => $key,
					'date'  => $date,
					'title' => preg_replace( '/\s·.*$/u', '', $title ),
					'base'  => $title,
					'year'  => $year,
				);
			}
			if ( '' === $get( $r, 'ime' ) . $get( $r, 'prezime' ) ) {
				continue;
			}
			++$i;
			// Član: po OIB-u, e-mailu ili broju.
			$id = 0;
			foreach ( array( 'oib' => 'oib', 'email' => 'e-mail', 'broj' => 'br. clana' ) as $f => $h ) {
				if ( ! $id && '' !== $get( $r, $h ) ) {
					$id = Plan_A_Clanstvo_Data::find( $f, $get( $r, $h ) );
				}
			}
			$m = $id ? Plan_A_Clanstvo_Data::get_member( $id ) : null;
			$pkey = $key . '-' . $i;
			$v    = array();
			foreach ( array( 'ime' => 'ime', 'prezime' => 'prezime', 'oib' => 'oib', 'datum' => 'datum rodenja', 'adresa' => 'adresa', 'mjesto' => 'mjesto', 'mobitel' => 'mobitel', 'email' => 'e-mail', 'prijavio' => 'prijavio/la', 'iznos' => 'iznos', 'napomena' => 'napomena' ) as $k => $h ) {
				$v[ $k ] = sanitize_text_field( $get( $r, $h ) );
			}
			$v           += self::status( $m, $year );
			$v['osiguranje'] = $yes( $get( $r, 'osiguranje' ) );
			$v['uplaceno']   = $yes( $get( $r, 'uplaceno' ) );
			$v['otkazano']   = $yes( $get( $r, 'otkazano' ) );
			$rows[]          = array(
				'tour' => $tours[ $key ],
				'pkey' => $pkey,
				'v'    => $v,
			);
			$map[ $pkey ]    = array(
				'b' => $m ? (int) $m['broj'] : 0,
				'e' => '',
				'o' => 0,
				'y' => $year,
				'd' => $date,
			);
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$res = self::post( array( 'action' => 'ag_tours', 'tours' => array_values( array_reverse( $tours ) ) ) );
		if ( ! $res['ok'] ) {
			return $res;
		}
		$n = 0;
		foreach ( array_chunk( $rows, 40 ) as $chunk ) {
			$res = self::post( array( 'action' => 'ag_add', 'rows' => $chunk ) );
			if ( ! $res['ok'] ) {
				update_option( self::ROWS, $map, false );
				return array( 'ok' => false, 'error' => ( $res['error'] ?? '' ) . ' (upisano ' . $n . ' osoba; ponovni uvoz nastavlja bez dupliranja)' );
			}
			$n += count( $chunk );
		}
		update_option( self::ROWS, $map, false );
		return array(
			'ok'    => true,
			'tours' => count( $tours ),
			'n'     => $n,
		);
	}
}
