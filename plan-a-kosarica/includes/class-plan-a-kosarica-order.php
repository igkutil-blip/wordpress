<?php
/**
 * Završna stranica narudžbe ("order received") i e-mailovi kupcu u stilu košarice.
 *
 * 2D kod (HUB3) i podatke za plaćanje i dalje ispisuje postojeći dodatak (Hub3 ili
 * bankovni prijenos) kroz standardne kuke WooCommercea; ovdje se njihov izlaz hvata
 * nepromijenjen i stavlja u blok "Podaci za plaćanje". Ništa se ne generira niti mijenja.
 *
 * Zamijenjeni predlošci: checkout/thankyou.php, emails/customer-on-hold-order.php,
 * emails/customer-processing-order.php, emails/customer-completed-order.php (HTML).
 *
 * @package Plan_A_Kosarica
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Kosarica_Order {

	const TEMPLATES = array(
		'checkout/thankyou.php',
		'emails/customer-on-hold-order.php',
		'emails/customer-processing-order.php',
		'emails/customer-completed-order.php',
	);

	/** @var bool Upravo se iscrtava naša stranica ili e-mail (za prijevode). */
	private static $rendering = false;

	public static function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		add_action( 'admin_post_plan_a_kosarica_test_email', array( __CLASS__, 'test_email' ) );
		if ( ! Plan_A_Kosarica_Settings::get( 'order' ) ) {
			return;
		}
		add_filter( 'woocommerce_locate_template', array( __CLASS__, 'locate_template' ), 99, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 30 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'gettext', array( __CLASS__, 'translate' ), 20, 3 );
		add_filter( 'woocommerce_email_subject_customer_on_hold_order', array( __CLASS__, 'subject_received' ), 20, 2 );
		add_filter( 'woocommerce_email_subject_customer_processing_order', array( __CLASS__, 'subject_paid' ), 20, 2 );
		add_filter( 'woocommerce_email_subject_customer_completed_order', array( __CLASS__, 'subject_paid' ), 20, 2 );
	}

	public static function locate_template( $template, $template_name ) {
		if ( in_array( $template_name, self::TEMPLATES, true ) ) {
			$ours = PLAN_A_KOSARICA_DIR . 'templates/' . $template_name;
			if ( file_exists( $ours ) ) {
				return $ours;
			}
		}
		return $template;
	}

	private static function is_received_page(): bool {
		return function_exists( 'is_wc_endpoint_url' ) && is_checkout() && is_wc_endpoint_url( 'order-received' );
	}

	public static function assets() {
		if ( ! self::is_received_page() ) {
			return;
		}
		wp_enqueue_style( 'plan-a-kosarica', PLAN_A_KOSARICA_URL . 'assets/css/kosarica.css', array(), PLAN_A_KOSARICA_VERSION );
		$s = Plan_A_Kosarica_Settings::get();
		wp_add_inline_style( 'plan-a-kosarica', sprintf( ':root{--paka-cta:%s;--paka-accent:%s;--paka-navy:%s;}', esc_attr( $s['cta'] ), esc_attr( $s['accent'] ), esc_attr( $s['navy'] ) ) );
		wp_enqueue_script( 'plan-a-kosarica', PLAN_A_KOSARICA_URL . 'assets/js/kosarica.js', array(), PLAN_A_KOSARICA_VERSION, array( 'in_footer' => true ) );
		// "Predloži ekipi" iz dodatka Plan A izleti.
		if ( wp_style_is( 'plan-a-izleti', 'registered' ) ) {
			wp_enqueue_style( 'plan-a-izleti' );
		}
		if ( wp_script_is( 'plan-a-izleti', 'registered' ) ) {
			wp_enqueue_script( 'plan-a-izleti' );
		}
	}

	public static function body_class( $classes ) {
		if ( self::is_received_page() ) {
			$classes[] = 'paka-on';
			$classes[] = 'paka-received';
			if ( Plan_A_Kosarica_Settings::get( 'steps' ) ) {
				$classes[] = 'paka-steps-on';
			}
		}
		return $classes;
	}

	/**
	 * Engleski natpisi WooCommercea na završnoj stranici i u e-mailovima (npr. podaci
	 * bankovnog prijenosa). Mijenja se samo tekst koji nije već preveden.
	 */
	public static function translate( $translation, $text, $domain ) {
		static $map = array(
			'Our bank details'                => 'Podaci za uplatu',
			'Bank'                            => 'Banka',
			'Account number'                  => 'Broj računa',
			'Sort code'                       => 'Šifra banke',
			'BSB'                             => 'BSB',
			'Order details'                   => 'Detalji narudžbe',
			'Order number:'                   => 'Broj narudžbe:',
			'Order number'                    => 'Broj narudžbe',
			'Date:'                           => 'Datum:',
			'Total:'                          => 'Ukupno:',
			'Payment method:'                 => 'Način plaćanja:',
			'Note:'                           => 'Napomena:',
			'Billing address'                 => 'Adresa',
			'Thank you. Your order has been received.' => 'Hvala, vaša prijava je zaprimljena.',
			'Pay'                             => 'Plati',
			'My account'                      => 'Moj račun',
		);
		if ( $translation !== $text || ! isset( $map[ $text ] ) || ! in_array( $domain, array( 'woocommerce', 'default' ), true ) ) {
			return $translation;
		}
		if ( self::$rendering || ( did_action( 'wp' ) && self::is_received_page() ) ) {
			return $map[ $text ];
		}
		return $translation;
	}

	public static function subject_received( $subject, $order ) {
		if ( ! $order instanceof WC_Order ) {
			return $subject;
		}
		/* translators: %s: broj narudžbe */
		return sprintf( __( 'Vaša prijava je zaprimljena (narudžba #%s)', 'plan-a-kosarica' ), $order->get_order_number() );
	}

	public static function subject_paid( $subject, $order ) {
		return $order instanceof WC_Order ? __( 'Uplata je zaprimljena, vidimo se na izletu', 'plan-a-kosarica' ) : $subject;
	}

	/* ------------------------------------------------------------------ */
	/* Podaci                                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Datum na hrvatskom: "7. listopada 2026."
	 */
	public static function hr_date( $timestamp ): string {
		$months = array( 1 => 'siječnja', 'veljače', 'ožujka', 'travnja', 'svibnja', 'lipnja', 'srpnja', 'kolovoza', 'rujna', 'listopada', 'studenoga', 'prosinca' );
		$ts     = (int) $timestamp;
		return gmdate( 'j', $ts ) . '. ' . $months[ (int) gmdate( 'n', $ts ) ] . ' ' . gmdate( 'Y', $ts ) . '.';
	}

	/**
	 * Datum (i vrijeme) termina iz WpTravellyja: "29. studenoga 2026., 6:00 do 20:00".
	 */
	public static function tour_date_text( string $start, string $end ): string {
		$start = trim( $start );
		$ts    = '' !== $start ? strtotime( $start ) : false;
		if ( ! $ts ) {
			return $start;
		}
		$text     = self::hr_date( $ts );
		$has_time = (bool) preg_match( '/\d{1,2}:\d{2}/', $start );
		$end_ts   = '' !== trim( $end ) ? strtotime( $end ) : false;
		if ( $end_ts && gmdate( 'Y-m-d', $end_ts ) !== gmdate( 'Y-m-d', $ts ) ) {
			// Višednevni izlet.
			$text .= ( $has_time ? ' ' . gmdate( 'G:i', $ts ) : '' ) . ' – ' . self::hr_date( $end_ts );
			return $text;
		}
		if ( $has_time ) {
			$text = rtrim( $text, '.' ) . '., ' . gmdate( 'G:i', $ts );
			if ( $end_ts && preg_match( '/\d{1,2}:\d{2}/', $end ) && gmdate( 'G:i', $end_ts ) !== gmdate( 'G:i', $ts ) ) {
				$text .= ' ' . __( 'do', 'plan-a-kosarica' ) . ' ' . gmdate( 'G:i', $end_ts );
			}
		}
		return $text;
	}

	public static function is_paid( WC_Order $order ): bool {
		return $order->is_paid();
	}

	/**
	 * Stavka narudžbe kao "stavka košarice" za Plan_A_Kosarica::tour().
	 */
	private static function item_as_cart( WC_Order_Item_Product $item ): array {
		return array(
			'ttbm_id'                 => (int) $item->get_meta( '_ttbm_id' ),
			'ttbm_date'               => (string) $item->get_meta( '_ttbm_date' ),
			'ttbm_end_date'           => (string) $item->get_meta( '_ttbm_end_date' ),
			'ttbm_hotel_info'         => (array) $item->get_meta( '_ttbm_hotel_info' ),
			'ttbm_ticket_info'        => (array) $item->get_meta( '_ttbm_ticket_info' ),
			'ttbm_extra_service_info' => (array) $item->get_meta( '_ttbm_service_info' ),
		);
	}

	/**
	 * Prvi izlet u narudžbi (za "Predloži ekipi").
	 */
	public static function first_tour_id( WC_Order $order ): int {
		foreach ( $order->get_items() as $item ) {
			if ( $item instanceof WC_Order_Item_Product ) {
				$id = Plan_A_Kosarica::tour_id( self::item_as_cart( $item ) );
				if ( $id ) {
					return $id;
				}
			}
		}
		return 0;
	}

	/**
	 * Blok "Vaš izlet": retci [oznaka, vrijednost (HTML)] po stavci, pa zajednički
	 * retci (dodatno, popust, ukupno, bilješka). Ukupno se prikazuje samo jednom.
	 *
	 * @return array{items: array<int, array<int, array{0: string, 1: string}>>, extra: array, total: string, note: string}
	 */
	public static function summary( WC_Order $order ): array {
		$items = array();
		$extra = array();

		foreach ( $order->get_items() as $item_id => $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$rows = array();
			$cart = self::item_as_cart( $item );
			$tour = Plan_A_Kosarica::tour( $cart );
			if ( $tour['id'] ) {
				$rows[] = array( __( 'Izlet', 'plan-a-kosarica' ), esc_html( $item->get_name() ) );
				$date   = self::tour_date_text( $cart['ttbm_date'], $cart['ttbm_end_date'] );
				if ( '' !== $date ) {
					$rows[] = array( __( 'Datum', 'plan-a-kosarica' ), esc_html( $date ) );
				}
				if ( '' !== $tour['location'] ) {
					$rows[] = array( __( 'Lokacija', 'plan-a-kosarica' ), esc_html( $tour['location'] ) );
				}
				if ( $tour['hotel'] ) {
					if ( '' !== $tour['hotel']['name'] ) {
						$rows[] = array( __( 'Smještaj', 'plan-a-kosarica' ), esc_html( $tour['hotel']['name'] ) );
					}
					$stay = trim( $tour['hotel']['checkin'] . ' – ' . $tour['hotel']['checkout'], ' –' );
					if ( '' !== $stay ) {
						$rows[] = array( __( 'Boravak', 'plan-a-kosarica' ), esc_html( $stay ) );
					}
				}
				if ( $tour['people'] > 0 ) {
					$rows[] = array( __( 'Broj osoba', 'plan-a-kosarica' ), esc_html( (string) $tour['people'] ) );
				}
				$multi = count( $tour['tickets'] ) > 1;
				foreach ( $tour['tickets'] as $ticket ) {
					$label  = $multi && '' !== $ticket['name'] ? __( 'Cijena', 'plan-a-kosarica' ) . ' (' . $ticket['name'] . ')' : __( 'Cijena', 'plan-a-kosarica' );
					$value  = esc_html( (string) $ticket['qty'] ) . ' × ' . wp_kses_post( $ticket['price'] ) . ( $ticket['days'] ? ' × ' . esc_html( (string) $ticket['days'] ) : '' ) . ' = ' . wp_kses_post( $ticket['total'] );
					$rows[] = array( $label, $value );
				}
				foreach ( $tour['services'] as $service ) {
					$value   = 1 === $service['qty']
						? esc_html( $service['name'] ) . ' ' . wp_kses_post( $service['total'] )
						: esc_html( $service['name'] ) . ' ' . esc_html( (string) $service['qty'] ) . ' × ' . wp_kses_post( $service['price'] ) . ' = ' . wp_kses_post( $service['total'] );
					$extra[] = array( __( 'Dodatno', 'plan-a-kosarica' ), $value );
				}
			} else {
				$rows[] = array( __( 'Proizvod', 'plan-a-kosarica' ), esc_html( $item->get_name() ) );
				$meta   = wc_display_item_meta( $item, array( 'echo' => false, 'before' => '', 'after' => '', 'separator' => ', ' ) );
				if ( '' !== trim( wp_strip_all_tags( (string) $meta ) ) ) {
					$rows[] = array( __( 'Detalji', 'plan-a-kosarica' ), wp_kses_post( $meta ) );
				}
				$rows[] = array( __( 'Cijena', 'plan-a-kosarica' ), esc_html( (string) $item->get_quantity() ) . ' × ' . wp_kses_post( wc_price( (float) $order->get_item_subtotal( $item, false, true ), array( 'currency' => $order->get_currency() ) ) ) . ' = ' . wp_kses_post( $order->get_formatted_line_subtotal( $item ) ) );
			}

			// Podaci drugih dodataka uz stavku.
			ob_start();
			do_action( 'woocommerce_order_item_meta_start', $item_id, $item, $order, false );
			do_action( 'woocommerce_order_item_meta_end', $item_id, $item, $order, false );
			$hooks = trim( (string) ob_get_clean() );
			// Prazan blok bez podataka (npr. samo naslov "Order Details" iz WpTravellyja) se ne prikazuje.
			$hooks_text = trim( wp_strip_all_tags( (string) preg_replace( '#<h[1-6][^>]*>.*?</h[1-6]>#is', '', $hooks ) ) );
			if ( '' !== $hooks && '' !== $hooks_text ) {
				$rows[] = array( '', $hooks );
			}
			$items[] = $rows;
		}

		foreach ( $order->get_fees() as $fee ) {
			$extra[] = array( __( 'Dodatno', 'plan-a-kosarica' ), esc_html( $fee->get_name() ) . ' ' . wp_kses_post( wc_price( (float) $fee->get_total(), array( 'currency' => $order->get_currency() ) ) ) );
		}
		if ( (float) $order->get_shipping_total() > 0 ) {
			$extra[] = array( __( 'Dostava', 'plan-a-kosarica' ), wp_kses_post( $order->get_shipping_to_display() ) );
		}
		if ( (float) $order->get_total_discount() > 0 ) {
			$extra[] = array( __( 'Popust', 'plan-a-kosarica' ), '−' . wp_kses_post( wc_price( (float) $order->get_total_discount(), array( 'currency' => $order->get_currency() ) ) ) . ( $order->get_coupon_codes() ? ' (' . esc_html( implode( ', ', $order->get_coupon_codes() ) ) . ')' : '' ) );
		}

		return array(
			'items' => $items,
			'extra' => $extra,
			'total' => (string) $order->get_formatted_order_total(),
			'note'  => trim( (string) $order->get_customer_note() ),
		);
	}

	/**
	 * Koraci "Što sada?".
	 *
	 * @return string[]
	 */
	public static function next_steps( WC_Order $order ): array {
		$s = Plan_A_Kosarica_Settings::get();
		return array( self::is_paid( $order ) ? $s['step1_paid'] : $s['step1'], $s['step2'], $s['step3'] );
	}

	/**
	 * Izlaz kuka (npr. Hub3 / bankovni prijenos) kao HTML, nepromijenjen.
	 */
	public static function capture( string $hook, ...$args ): string {
		ob_start();
		do_action( $hook, ...$args );
		return trim( (string) ob_get_clean() );
	}

	/**
	 * Ima li u tekstu podataka za plaćanje IBAN (tj. jesu li podaci i kao tekst)?
	 */
	public static function has_text_payment_data( string $html ): bool {
		// Oznake se zamjenjuju razmakom da se "IBAN" iz ćelije tablice ne spoji s brojem računa.
		return (bool) preg_match( '/\bHR\s?\d{2}(?:\s?\d){17}\b/i', wp_strip_all_tags( (string) preg_replace( '/<[^>]*>/', ' ', $html ) ) );
	}

	/**
	 * Ima li izlaz kuke vidljiv sadržaj (tekst ili sliku); prazni omotači se ne prikazuju.
	 */
	public static function has_content( string $html ): bool {
		return '' !== trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ), " \t\n\r\0\x0B\xC2\xA0" ) || (bool) preg_match( '/<(img|svg|canvas|iframe)\b/i', $html );
	}

	public static function logo_url(): string {
		$id = (int) Plan_A_Kosarica_Settings::get( 'email_logo' );
		if ( ! $id && class_exists( 'Plan_A_Izleti_Settings' ) ) {
			$id = (int) ( Plan_A_Izleti_Settings::get()['logo_id'] ?? 0 );
		}
		if ( ! $id ) {
			$id = (int) get_theme_mod( 'custom_logo', 0 );
		}
		if ( $id ) {
			$url = wp_get_attachment_image_url( $id, 'medium' );
			if ( $url ) {
				return (string) $url;
			}
		}
		return (string) get_option( 'woocommerce_email_header_image', '' );
	}

	/**
	 * "Predloži ekipi" za e-mail: poveznica na wa.me s istom porukom kao na stranici.
	 */
	public static function share_url( int $tour_id ): string {
		if ( ! $tour_id || ! class_exists( 'Plan_A_Izleti_Share' ) ) {
			return '';
		}
		$data = Plan_A_Izleti_Share::data_without_card( $tour_id );
		return 'https://wa.me/?text=' . rawurlencode( Plan_A_Izleti_Share::message( $data ) . "\n" . $data['url'] );
	}

	/**
	 * Za predloške e-mailova: uključuje prijevode WooCommerceovih natpisa.
	 */
	public static function rendering( bool $on ) {
		self::$rendering = $on;
	}

	/* ------------------------------------------------------------------ */
	/* Probni e-mail                                                        */
	/* ------------------------------------------------------------------ */

	public static function test_email() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Nemate ovlasti.' );
		}
		check_admin_referer( 'plan_a_kosarica_test_email' );

		$order = null;
		foreach ( wc_get_orders( array( 'limit' => 30, 'orderby' => 'date', 'order' => 'DESC', 'type' => 'shop_order' ) ) as $candidate ) {
			if ( self::first_tour_id( $candidate ) ) {
				$order = $candidate;
				break;
			}
			$order = $order ?: $candidate;
		}

		$ok = false;
		if ( $order ) {
			$to     = (string) get_option( 'admin_email' );
			$emails = WC()->mailer()->get_emails();
			foreach ( array( 'WC_Email_Customer_On_Hold_Order', 'WC_Email_Customer_Processing_Order' ) as $class ) {
				if ( empty( $emails[ $class ] ) ) {
					continue;
				}
				$email         = $emails[ $class ];
				$email->object = $order;
				$email->placeholders['{order_date}']   = wc_format_datetime( $order->get_date_created() );
				$email->placeholders['{order_number}'] = $order->get_order_number();
				$email->setup_locale();
				$sent = $email->send( $to, '[Proba] ' . $email->get_subject(), $email->get_content(), $email->get_headers(), array() );
				$email->restore_locale();
				$ok = $ok || $sent;
			}
		}
		wp_safe_redirect( add_query_arg( 'paka-test', $ok ? 'ok' : 'greska', admin_url( 'options-general.php?page=' . Plan_A_Kosarica_Settings::PAGE ) ) );
		exit;
	}
}
