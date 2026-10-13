<?php
/**
 * Primjerci svih mailova (samo na kopiji): po jedan primjerak svakog maila koji šalju Plan A
 * dodaci i WooCommerce. Mailovi ionako idu na adresu iz „Plan A – mailovi kopije”.
 * Svaki primjerak je u zasebnom try/catch, rezultat se vraća kao popis.
 *
 * @package Plan_A_Pomocnik
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Pomocnik_Samples {

	/** Prava tablica za agenciju – nikad ne smije biti upisana na kopiji. */
	const REAL_AGENCY = '1z53LwhYgw87';

	private static $out = array();

	/** Ako je zadano, šalje se samo primjerak čiji naziv sadrži ovaj tekst. */
	public static $only = '';

	private static function run( string $id, callable $fn ) {
		if ( '' !== self::$only && false === mb_stripos( $id, self::$only ) ) {
			return;
		}
		$sent = 0;
		$cb   = static function ( $atts ) use ( &$sent ) {
			++$sent;
			return $atts;
		};
		add_filter( 'wp_mail', $cb, 1 );
		try {
			$r   = $fn();
			$msg = is_string( $r ) ? $r : '';
		} catch ( Throwable $e ) {
			$msg = 'GREŠKA: ' . $e->getMessage();
		}
		remove_filter( 'wp_mail', $cb, 1 );
		self::$out[] = array(
			'mail'    => $id,
			'poslano' => $sent,
			'napomena' => $msg,
		);
	}

	private static function call_private( string $class, string $method, array $args ) {
		$m = new ReflectionMethod( $class, $method );
		$m->setAccessible( true );
		return $m->invokeArgs( null, $args );
	}

	private static function wc_mail( string $class ) {
		$mails = WC()->mailer()->get_emails();
		if ( empty( $mails[ $class ] ) ) {
			throw new Exception( 'nema maila ' . $class );
		}
		return $mails[ $class ];
	}

	/**
	 * @param string $group all | wc | clanstvo | jedrenje | ostalo
	 * @param array  $p     order (probna narudžba izleta), rate_order (narudžba s prvom ratom), member (ID probnog člana)
	 */
	public static function send( string $group, array $p ): array {
		self::$out = array();
		$all       = 'all' === $group;
		$order_id  = (int) ( $p['order'] ?? 0 );
		$order     = $order_id ? wc_get_order( $order_id ) : null;
		$admin     = (string) get_option( 'admin_email' );

		/* ---------------- WooCommerce (košarica) ---------------- */
		if ( ( $all || 'wc' === $group ) && class_exists( 'WooCommerce' ) ) {
			if ( ! $order ) {
				self::$out[] = array( 'mail' => 'WooCommerce', 'poslano' => 0, 'napomena' => 'nema probne narudžbe (order)' );
			} else {
				foreach (
					array(
						'WC_Email_Customer_On_Hold_Order'    => 'Prijava zaprimljena (kupac, s uplatnicom)',
						'WC_Email_Customer_Processing_Order' => 'U obradi (kupac)',
						'WC_Email_Customer_Completed_Order'  => 'Cjelokupni iznos uplaćen (kupac)',
						'WC_Email_New_Order'                 => 'Nova prijava (nama)',
						'WC_Email_Cancelled_Order'           => 'Otkazana narudžba (nama, WooCommerce)',
						'WC_Email_Customer_Invoice'          => 'Račun / podaci za plaćanje (kupac, ručno iz narudžbe)',
					) as $class => $label
				) {
					self::run(
						$label,
						static function () use ( $class, $order ) {
							$e = self::wc_mail( $class );
							// WooCommerce mail „Nova narudžba” šalje samo jednom po narudžbi: za primjerak dopusti ponovno slanje.
							add_filter( 'woocommerce_new_order_email_allows_resend', '__return_true' );
							$e->trigger( $order->get_id(), $order );
							remove_filter( 'woocommerce_new_order_email_allows_resend', '__return_true' );
						}
					);
				}
				self::run(
					'Povrat novca (kupac)',
					static function () use ( $order ) {
						self::wc_mail( 'WC_Email_Customer_Refunded_Order' )->trigger( $order->get_id(), false );
					}
				);
				self::run(
					'Napomena kupcu (kupac)',
					static function () use ( $order ) {
						self::wc_mail( 'WC_Email_Customer_Note' )->trigger(
							array(
								'order_id'      => $order->get_id(),
								'customer_note' => 'Ovo je primjer napomene koju pišemo kupcu iz narudžbe.',
							)
						);
					}
				);
				if ( class_exists( 'Plan_A_Clanstvo_Ops' ) ) {
					self::run(
						'Podsjetnik za uplatu (kupac + skrivena kopija Kreši)',
						static function () use ( $order ) {
							$r = new ReflectionProperty( 'Plan_A_Clanstvo_Ops', 'reminding' );
							$r->setAccessible( true );
							$r->setValue( null, $order->get_id() );
							try {
								self::wc_mail( 'WC_Email_Customer_On_Hold_Order' )->trigger( $order->get_id(), $order );
							} finally {
								$r->setValue( null, 0 );
							}
						}
					);
				}
			}
			$user = get_user_by( 'email', $admin ) ?: wp_get_current_user();
			if ( $user && $user->ID ) {
				self::run(
					'Nova lozinka (kupac)',
					static function () use ( $user ) {
						self::wc_mail( 'WC_Email_Customer_Reset_Password' )->trigger( $user->user_login, 'primjer-kljuca' );
					}
				);
				self::run(
					'Novi korisnički račun (kupac)',
					static function () use ( $user ) {
						self::wc_mail( 'WC_Email_Customer_New_Account' )->trigger( $user->ID, '', false );
					}
				);
			}
			if ( class_exists( 'Plan_A_Bon' ) && $order ) {
				self::run(
					'Poklon bon čeka uplatu (nama) – primjer teksta',
					static function () use ( $admin, $order ) {
						$mailer = WC()->mailer();
						$body   = '<p>Narudžba #' . esc_html( $order->get_order_number() ) . ' (Izravna bankovna transakcija) s poklon bonom čeka uplatu.</p><p><strong>Kupac:</strong> ' . esc_html( $order->get_formatted_billing_full_name() ) . '<br><strong>Iznos za uplatu:</strong> 50,00 €<br><strong>Bon:</strong> Poklon bon 50 €</p><p>Kad uplata stigne, otvorite karticu "Čekaju uplatu" i kliknite "Uplata je stigla, pošalji bon".</p>';
						$mailer->send( $admin, 'Novi poklon bon čeka uplatu', $mailer->wrap_message( 'Novi poklon bon čeka uplatu', $body ), "Content-Type: text/html\r\n" );
					}
				);
			}
		}

		/* ---------------- Članstvo, agencija, izvještaji ---------------- */
		if ( ( $all || 'clanstvo' === $group ) && class_exists( 'Plan_A_Clanstvo_Mail' ) ) {
			$s = Plan_A_Clanstvo_Data::get();
			if ( false !== strpos( (string) ( $s['agency_url'] ?? '' ), self::REAL_AGENCY ) ) {
				return array( array( 'mail' => 'članstvo', 'poslano' => 0, 'napomena' => 'STOP: upisana je prava tablica za agenciju' ) );
			}
			$member = (int) ( $p['member'] ?? 0 );
			if ( ! $member && ! empty( $p['member_email'] ) ) {
				$member = (int) Plan_A_Clanstvo_Data::find( 'email', (string) $p['member_email'] );
			}
			$year   = (int) current_time( 'Y' );
			if ( $member ) {
				$mm = Plan_A_Clanstvo_Data::get_member( $member );
				if ( $mm && 'potvrdeno' !== ( $mm['status'] ?? '' ) ) {
					self::run( 'Potvrdi pristupnicu (član)', static fn() => Plan_A_Clanstvo_Mail::confirm_request( $member, false ) ? '' : 'nije poslano' );
					self::run( 'Podsjetnik: potvrdi pristupnicu (član)', static fn() => Plan_A_Clanstvo_Mail::confirm_request( $member, true ) ? '' : 'nije poslano' );
				} else {
					// Potvrđenom članu se ne šalje pravi zahtjev za potvrdu: isti tekst, poveznica je samo primjer.
					$url = home_url( '/pristupnica?potvrda=PRIMJER' );
					self::run(
						'Potvrdi pristupnicu (član) – primjer teksta',
						static fn() => Plan_A_Clanstvo_Mail::send(
							(string) ( $mm['email'] ?? get_option( 'admin_email' ) ),
							'Potvrdi pristupnicu u Plan A',
							Plan_A_Clanstvo_Mail::wrap(
								'Potvrdi svoju pristupnicu',
								Plan_A_Clanstvo_Mail::p( 'Pozdrav ' . esc_html( (string) ( $mm['ime'] ?? '' ) ) . ',' )
								. Plan_A_Clanstvo_Mail::p( 'hvala na pristupnici u udrugu Plan A! Još samo jedan korak: klikni gumb i potvrdi da si je ispunio/la ti.' )
								. Plan_A_Clanstvo_Mail::button( 'Potvrđujem pristupnicu', $url )
								. Plan_A_Email::small( 'Klikom potvrđuješ i da prihvaćaš Izjavu člana. Poveznica vrijedi ' . (int) Plan_A_Clanstvo_Data::value( 'valid_days' ) . ' dana. Ako gumb ne radi, kopiraj ovu adresu u preglednik:<br><a href="' . esc_url( $url ) . '" style="color:#1a73b8;word-break:break-all">' . esc_html( $url ) . '</a>' )
								. Plan_A_Email::small( 'Ako pristupnicu nisi ispunio/la ti, javi nam se na info@srd-plan-a.hr.' ),
								'Plan A · pristupnica',
								'mail'
							)
						) ? '' : 'nije poslano'
					);
				}
				self::run( 'Pristupnica je potvrđena (član, s uplatnicom)', static fn() => Plan_A_Clanstvo_Mail::confirmed( $member ) ? '' : 'nije poslano' );
				self::run( 'Članarina – prijava na izlet (član, s uplatnicom)', static fn() => Plan_A_Clanstvo_Mail::fee_request( $member, $year ) ? '' : 'nije poslano' );
				self::run( 'Članarina – siječanj (član, s uplatnicom)', static fn() => Plan_A_Clanstvo_Mail::new_year_fee( $member, $year + 1 ) ? '' : 'nije poslano' );
			} else {
				self::$out[] = array( 'mail' => 'pristupnica / članarina', 'poslano' => 0, 'napomena' => 'nema probnog člana (member)' );
			}
			$rate_id = (int) ( $p['rate_order'] ?? 0 );
			$rate    = $rate_id ? wc_get_order( $rate_id ) : null;
			if ( $rate ) {
				self::run( 'Uplaćena prva rata (kupac, s uplatnicom)', static fn() => Plan_A_Clanstvo_Agency::rate_mail( $rate, 'first' ) ? '' : 'nije poslano (ostatak 0?)' );
				self::run( 'Podsjetnik: druga rata (kupac, s uplatnicom)', static fn() => Plan_A_Clanstvo_Agency::rate_mail( $rate, 'reminder' ) ? '' : 'nije poslano' );
			}
			if ( $order ) {
				self::run( 'Prijava je otkazana (kupac)', static fn() => self::call_private( 'Plan_A_Clanstvo_Agency', 'cancel_mail', array( $order, array(), true ) ) );
				self::run( 'Otkazan sudionik (kupac + kopija nama)', static fn() => self::call_private( 'Plan_A_Clanstvo_Agency', 'cancel_mail', array( $order, array( 'Primjer Sudionik' ), true ) ) );
			}
			self::run( 'Dnevni pregled (nama, agenciji, Kreši)', static fn() => Plan_A_Clanstvo_Ops::summary( true ) ? '' : 'nema podataka za pregled' );
			self::run( 'Popis za vodiča (dan prije izleta)', static fn() => Plan_A_Clanstvo_Ops::test_list() ? '' : 'nema izleta za popis' );
			self::run( 'Neplaćena članarina (Kreši, 1. u mjesecu)', static fn() => Plan_A_Clanstvo_Ops::treasurer( true ) ? '' : 'nema neplaćenih' );
			self::run( 'Upozorenje: veza s tablicom (nama)', static fn() => self::call_private( 'Plan_A_Clanstvo_Ops', 'alert', array( 'Veza s Google tablicom ne radi (PRIMJER)', 'Ovo je samo primjer upozorenja koje stiže kad web ne može doći do Google tablice.' ) ) );
			self::run(
				'Siječanj: e-mailovi o članarini su poslani (nama)',
				static fn() => Plan_A_Clanstvo_Mail::send( Plan_A_Clanstvo_Ops::admin_mail(), 'Članarina ' . ( $year + 1 ) . '. – e-mailovi su poslani', Plan_A_Clanstvo_Mail::wrap( 'Članarina ' . ( $year + 1 ) . '.', Plan_A_Clanstvo_Mail::p( 'PRIMJER: poslano je 123 e-mailova s podacima za članarinu.' ), 'Plan A · članarina', 'check' ) ) ? '' : 'nije poslano'
			);
		}

		/* ---------------- Jedrenje ---------------- */
		if ( ( $all || 'jedrenje' === $group ) && class_exists( 'Plan_A_Jedrenje_Mail' ) ) {
			$list = Plan_A_Jedrenje_Booking::by_state( array( 'zahtjev', 'potvrdeno', 'rezervirano', 'placeno', 'odbijeno' ), 1 );
			$r    = $list ? reset( $list ) : null;
			if ( ! $r ) {
				$r = array(
					'week'     => gmdate( 'Y-m-d', strtotime( 'next saturday +60 days' ) ),
					'persons'  => 6,
					'concept'  => 'Jedrenje s planinarenjem',
					'route'    => 'Kornati',
					'price'    => 2400,
					'deposit'  => 720,
					'full'     => 0,
					'pct'      => 30,
					'rest'     => 1680,
					'rest_due' => gmdate( 'Y-m-d', strtotime( '+30 days' ) ),
					'note'     => '',
					'first'    => 'Primjer',
					'last'     => 'Kupac',
					'email'    => $admin,
					'phone'    => '091 000 0000',
				);
			}
			$to   = $admin;
			$name = trim( ( $r['first'] ?? '' ) . ' ' . ( $r['last'] ?? '' ) );
			$week = Plan_A_Jedrenje_Data::week_label( $r['week'] );
			self::run( 'Jedrenje: zahtjev zaprimljen (kupac)', static fn() => Plan_A_Jedrenje_Mail::send( $to, 'Zahtjev je zaprimljen – ' . Plan_A_Jedrenje_Data::value( 'title' ) . ', ' . $week, Plan_A_Jedrenje_Mail::wrap( 'Zahtjev je zaprimljen', array( 'Bok ' . $r['first'] . '!', 'Zahtjev je zaprimljen, potvrdit ćemo slobodan brod u roku 48 sati.', (string) Plan_A_Jedrenje_Data::value( 'mail_request' ) ), Plan_A_Jedrenje_Mail::details( $r ) . Plan_A_Jedrenje_Mail::terms_html() ) ) ? '' : 'nije poslano' );
			self::run( 'Jedrenje: novi zahtjev (nama)', static fn() => Plan_A_Jedrenje_Mail::send( $to, 'Novi zahtjev za jedrenje: ' . $week . ' (' . $name . ')', Plan_A_Jedrenje_Mail::wrap( 'Novi zahtjev za jedrenje', array( $name . ', ' . ( $r['email'] ?? '' ) . ', ' . ( $r['phone'] ?? '' ), 'Provjeri brod u charter bazi pa u administraciji klikni "Brod je slobodan, pošalji uplatnicu" ili "Odbij".' ), Plan_A_Jedrenje_Mail::details( $r ), array( 'Otvori zahtjeve', admin_url( 'admin.php?page=plan-a-jedrenje&tab=zahtjevi' ) ) ) ) ? '' : 'nije poslano' );
			self::run( 'Jedrenje: brod nije slobodan (kupac)', static fn() => Plan_A_Jedrenje_Mail::send( $to, 'Odgovor na zahtjev za jedrenje – ' . $week, Plan_A_Jedrenje_Mail::wrap( 'Brod u ovom tjednu nije slobodan', array( 'Bok ' . $r['first'] . '!', 'PRIMJER poruke: nažalost, brod u tom tjednu više nije slobodan.' ), Plan_A_Jedrenje_Mail::details( $r, false ), array( 'Odaberi drugi tjedan', home_url( '/' ) ) ) ) ? '' : 'nije poslano' );
			self::run( 'Jedrenje: rezervacija potvrđena (kupac)', static fn() => Plan_A_Jedrenje_Mail::send( $to, 'Rezervacija je potvrđena – ' . $week, Plan_A_Jedrenje_Mail::wrap( 'Rezervacija je potvrđena', array( 'Bok ' . $r['first'] . '!', (string) Plan_A_Jedrenje_Data::value( 'mail_booked' ), 'Ostatak: ' . Plan_A_Jedrenje_Data::money( (float) $r['rest'] ) . '.' ), Plan_A_Jedrenje_Mail::details( $r ) . Plan_A_Jedrenje_Mail::terms_html() ) ) ? '' : 'nije poslano' );
			self::run( 'Jedrenje: sve je uplaćeno (kupac)', static fn() => Plan_A_Jedrenje_Mail::send( $to, 'Sve je uplaćeno – ' . $week, Plan_A_Jedrenje_Mail::wrap( 'Sve je uplaćeno', array( 'Bok ' . $r['first'] . '!', (string) Plan_A_Jedrenje_Data::value( 'mail_paid' ) ), Plan_A_Jedrenje_Mail::details( $r, false ) . Plan_A_Jedrenje_Mail::terms_html() ) ) ? '' : 'nije poslano' );
			self::run( 'Jedrenje: tjedan je već rezerviran (nama)', static fn() => Plan_A_Jedrenje_Mail::send( $to, 'UPOZORENJE: dvostruka uplata za tjedan ' . $week, Plan_A_Jedrenje_Mail::wrap( 'Tjedan je već rezerviran', array( 'PRIMJER: stigla je uplata od ' . $name . ', ali je tjedan već rezerviran za drugu ekipu. Javi se kupcu.' ), Plan_A_Jedrenje_Mail::details( $r ) ) ) ? '' : 'nije poslano' );
			self::run( 'Jedrenje: podsjetnik za uplate (nama)', static fn() => Plan_A_Jedrenje_Mail::send( $to, 'Jedrenje: podsjetnik za uplate', Plan_A_Jedrenje_Mail::wrap( 'Podsjetnik za uplate', array( 'PRIMJER: Akontacija nije uplaćena u roku: ' . $name . ', ' . $week . '.' ), '', array( 'Otvori rezervacije', admin_url( 'admin.php?page=plan-a-jedrenje&tab=rezervacije' ) ) ) ) ? '' : 'nije poslano' );
		}

		/* ---------------- Ostalo: newsletter, plan izleta, upiti, kopija ---------------- */
		if ( $all || 'ostalo' === $group ) {
			if ( class_exists( 'Plan_A_Newsletter_Mail' ) ) {
				$nl = get_posts( array( 'post_type' => 'pan_newsletter', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
				if ( $nl ) {
					self::run( 'Newsletter (pretplatnik)', static fn() => Plan_A_Newsletter_Mail::send_test( (int) $nl[0], $admin, 'Igor' ) ? '' : 'nije poslano' );
				} else {
					self::$out[] = array( 'mail' => 'Newsletter', 'poslano' => 0, 'napomena' => 'nema nijednog newslettera' );
				}
			}
			if ( class_exists( 'Plan_A_Izleti_Plan_Notify' ) && class_exists( 'Plan_A_Izleti_Plan' ) ) {
				$rows = Plan_A_Izleti_Plan::rows();
				$row  = $rows ? reset( $rows ) : null;
				if ( $row ) {
					self::run( 'Objavljen je izlet (pretplatnik plana)', static fn() => self::call_private( 'Plan_A_Izleti_Plan_Notify', 'mail', array( $admin, $row ) ) ? '' : 'nije poslano' );
				}
			}
			if ( class_exists( 'Plan_A_Prijave_Enquiry' ) ) {
				foreach ( array( 'enquiry_admin' => 'Novi upit s izleta (nama)', 'enquiry_customer' => 'Hvala na upitu (kupac)', 'enquiry_reply' => 'Odgovor na upit (kupac)' ) as $type => $label ) {
					self::run(
						$label,
						static function () use ( $type, $admin ) {
							Plan_A_Prijave_Enquiry::send(
								array(
									'type'    => $type,
									'to'      => $admin,
									'name'    => 'Primjer Kupac',
									'email'   => $admin,
									'subject' => 'enquiry_reply' === $type ? 'Re: Pitanje o izletu' : 'Pitanje o izletu',
									'message' => 'enquiry_reply' === $type ? "Pozdrav!\n\nHvala na upitu. Na izletu ima još mjesta, a oprema se može posuditi kod nas.\n\nPlan A" : "Pozdrav, zanima me treba li mi vlastita oprema za ferate?\nHvala!",
								)
							);
						}
					);
				}
			}
			self::run( 'Probni mail s kopije (pomoćnik)', static fn() => Plan_A_Pomocnik::mail_test()['ok'] ? '' : 'nije poslano' );
		}
		return self::$out;
	}
}
