<?php
/**
 * Članstvo u košarici (WooCommerce plaćanje), kad je uključena "Provjera članstva".
 *
 * - E-mail je prvo polje. Čim je upisan, stranica javlja samo stanje (član / pristupnica
 *   nije potvrđena / nije član) i koja polja treba upisati; osobni podaci člana se nikad
 *   ne šalju u preglednik. Polja koja član ima u pristupnici skriju se i popune na
 *   poslužitelju kad se narudžba šalje.
 * - Tko nije član, ispunjava pristupnicu u košarici (datum rođenja, OIB, izjava …); ostala
 *   polja su ujedno polja narudžbe. Pristupnica se upisuje kao "Čeka potvrdu", a gumb za
 *   potvrdu dolazi u e-mailu o narudžbi.
 * - Za više osoba u prijavi traže se podaci ostalih sudionika (ime, prezime, e-mail; za
 *   nečlanove i pristupnica). Oni dobivaju svoj e-mail za potvrdu.
 * - Članarina se provjerava za godinu izleta; ako nije plaćena, članu stiže e-mail s
 *   2D kodom za članarinu (jednom po godini).
 * - Rezervacije jedrenja se ne diraju.
 *
 * @package Plan_A_Clanstvo
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Clanstvo_Checkout {

	/** Polja narudžbe => polje pristupnice. */
	const MAP = array(
		'billing_first_name' => 'ime',
		'billing_last_name'  => 'prezime',
		'billing_address_1'  => 'adresa',
		'billing_city'       => 'mjesto',
		'billing_postcode'   => 'mjesto',
		'billing_phone'      => 'mobitel',
	);

	const EXTRA = array( 'datum', 'oib', 'roditelj', 'roditelj_kontakt' );

	public static function init() {
		if ( ! (int) Plan_A_Clanstvo_Data::value( 'provjera' ) ) {
			return;
		}
		add_filter( 'woocommerce_billing_fields', array( __CLASS__, 'billing_fields' ), 9999 );
		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'checkout_fields' ), 9999 );
		add_action( 'woocommerce_after_checkout_billing_form', array( __CLASS__, 'buyer_box' ) );
		add_action( 'woocommerce_after_checkout_billing_form', array( __CLASS__, 'others_box' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 40 );
		add_action( 'wp_ajax_pac_member_check', array( __CLASS__, 'ajax' ) );
		add_action( 'wp_ajax_nopriv_pac_member_check', array( __CLASS__, 'ajax' ) );
		add_filter( 'woocommerce_checkout_posted_data', array( __CLASS__, 'fill' ), 5 );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'line_item' ), 10, 4 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'order_meta' ), 10, 2 );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'processed' ), 10, 3 );
		add_filter( 'plan_a_kosarica_hide_customer_details', array( __CLASS__, 'hide_details' ), 10, 2 );
	}

	/* ------------------------------------------------------------------ */
	/* Košarica: izleti, osobe, godina                                     */
	/* ------------------------------------------------------------------ */

	/**
	 * Stavke izleta u košarici: [ključ => [naziv, broj osoba, godina, from, to, first]].
	 * Kupac je osoba 1 jednom po izletu (isti izlet i termin); ostale osobe se broje redom,
	 * i kad je isti izlet u košarici više puta (from–to su brojevi osoba te stavke).
	 */
	private static function cart_tours(): array {
		$out  = array();
		$last = array();
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $out;
		}
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( empty( $item['ttbm_id'] ) || ! empty( $item['paj_rez'] ) ) {
				continue;
			}
			$people = 0;
			foreach ( (array) ( $item['ttbm_ticket_info'] ?? array() ) as $t ) {
				$people += (int) ( $t['ticket_qty'] ?? 0 );
			}
			$ts          = strtotime( (string) ( $item['ttbm_date'] ?? '' ) );
			$product     = $item['data'] ?? null;
			$people      = max( 1, $people ?: (int) ( $item['quantity'] ?? 1 ) );
			$group       = (int) $item['ttbm_id'] . '|' . ( $ts ? gmdate( 'Y-m-d', $ts ) : '' );
			$first       = ! isset( $last[ $group ] );
			$from        = $first ? 2 : $last[ $group ] + 1;
			$to          = $first ? $people : $last[ $group ] + $people;
			$last[ $group ] = $to;
			$out[ $key ] = array(
				'name'   => $product ? wp_strip_all_tags( $product->get_name() ) : get_the_title( (int) $item['ttbm_id'] ),
				'people' => $people,
				'year'   => $ts ? (int) gmdate( 'Y', $ts ) : (int) current_time( 'Y' ),
				'group'  => $group,
				'first'  => $first,
				'from'   => $from,
				'to'     => min( $to, 40 ),
			);
		}
		return $out;
	}

	private static function cart_year(): int {
		$years = wp_list_pluck( self::cart_tours(), 'year' );
		return $years ? (int) min( $years ) : (int) current_time( 'Y' );
	}

	private static function active(): bool {
		return function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() && (bool) self::cart_tours();
	}

	/* ------------------------------------------------------------------ */
	/* Stanje člana (bez osobnih podataka)                                 */
	/* ------------------------------------------------------------------ */

	private static function member( string $email ): ?array {
		$id = Plan_A_Clanstvo_Data::find( 'email', $email );
		return $id ? Plan_A_Clanstvo_Data::get_member( $id ) : null;
	}

	/** Polja narudžbe koja član nema u pristupnici (ta ostaju vidljiva). */
	private static function missing( array $m ): array {
		$miss = array();
		foreach ( self::MAP as $field => $k ) {
			if ( '' === trim( (string) $m[ $k ] ) ) {
				$miss[] = $field;
			}
		}
		if ( ! self::split_place( (string) $m['mjesto'] )[0] ) {
			$miss[] = 'billing_postcode';
		}
		return array_values( array_unique( $miss ) );
	}

	/** "10000 Zagreb" => ['10000', 'Zagreb']. */
	private static function split_place( string $place ): array {
		$place = trim( $place );
		if ( preg_match( '/^(\d{5})\s+(.+)$/u', $place, $m ) ) {
			return array( $m[1], $m[2] );
		}
		return array( '', $place );
	}

	public static function state( string $email, int $year ): array {
		$m = is_email( $email ) ? self::member( $email ) : null;
		if ( ! $m ) {
			return array( 'state' => 'nema' );
		}
		return array(
			'state'   => 'potvrdeno' === $m['status'] ? 'clan' : 'ceka',
			'fee'     => Plan_A_Clanstvo_Data::fee_paid( (int) $m['id'], $year ),
			'year'    => $year,
			'missing' => self::missing( $m ),
		);
	}

	private static function rate_ok(): bool {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'pac_chk_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= 30 ) {
			return false;
		}
		set_transient( $key, $n + 1, 10 * MINUTE_IN_SECONDS );
		return true;
	}

	public static function ajax() {
		check_ajax_referer( 'pac_member_check', 'nonce' );
		if ( ! self::rate_ok() ) {
			wp_send_json_error( 'Previše provjera. Pokušaj za nekoliko minuta.', 429 );
		}
		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$year  = self::cart_year();
		wp_send_json_success( self::state( $email, $year ) );
	}

	/* ------------------------------------------------------------------ */
	/* Polja                                                               */
	/* ------------------------------------------------------------------ */

	public static function billing_fields( $fields ) {
		if ( ! self::active() ) {
			return $fields;
		}
		if ( isset( $fields['billing_email'] ) ) {
			$fields['billing_email']['priority'] = 1;
			$fields['billing_email']['class']    = array( 'form-row-wide' );
			$fields['billing_email']['label']    = __( 'E-mail', 'plan-a-clanstvo' );
			$fields['billing_email']['description'] = __( 'Ako si član Plan A, upiši e-mail iz pristupnice. Ostale podatke upisat ćemo sami.', 'plan-a-clanstvo' );
		}
		return $fields;
	}

	public static function checkout_fields( $fields ) {
		if ( isset( $fields['billing'] ) ) {
			$fields['billing'] = self::billing_fields( $fields['billing'] );
		}
		return $fields;
	}

	private static function input( string $name, string $label, string $type = 'text', array $attr = array(), string $help = '' ): string {
		$id = 'pac-' . sanitize_html_class( str_replace( array( '[', ']' ), '-', $name ) );
		$a  = '';
		foreach ( $attr as $k => $v ) {
			$a .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
		}
		return '<p class="form-row form-row-wide pac-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>'
			. '<span class="woocommerce-input-wrapper"><input class="input-text" type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $a . '></span>'
			. ( $help ? '<span class="description">' . esc_html( $help ) . '</span>' : '' ) . '</p>';
	}

	private static function izjava_box( string $name, string $label ): string {
		return '<details class="pac-izjava"><summary>' . esc_html__( 'Pročitaj Izjavu člana', 'plan-a-clanstvo' ) . '</summary><div class="pac-izjava__text">' . Plan_A_Clanstvo_Form::izjava_html() . '</div></details>'
			. '<p class="form-row form-row-wide pac-check"><label><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"> <span>' . esc_html( $label ) . '</span></label></p>';
	}

	/** Pristupnica kupca (ispod polja narudžbe; vidi se samo nečlanu). */
	public static function buyer_box() {
		if ( ! self::active() ) {
			return;
		}
		echo '<div class="pac-status" id="pac-buyer-status" aria-live="polite" hidden></div>';
		echo '<div class="pac-join" id="pac-buyer-join" hidden>';
		echo '<h3 class="pac-join__h">' . esc_html__( 'Pristupnica u udrugu Plan A', 'plan-a-clanstvo' ) . '</h3>';
		echo '<p class="pac-muted">' . esc_html__( 'Članstvo je uvjet za sudjelovanje na izletima. Uz podatke iznad upiši još ovo; članarina vrijedi kalendarsku godinu.', 'plan-a-clanstvo' ) . '</p>';
		echo self::input( 'pac_datum', __( 'Datum rođenja', 'plan-a-clanstvo' ), 'text', array( 'inputmode' => 'numeric', 'placeholder' => 'npr. 15.3.1990.', 'maxlength' => 12, 'autocomplete' => 'bday', 'data-pac-date' => '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo self::input( 'pac_oib', __( 'OIB', 'plan-a-clanstvo' ), 'text', array( 'inputmode' => 'numeric', 'maxlength' => 11, 'pattern' => '[0-9]{11}' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="pac-minor" id="pac-buyer-minor" hidden>';
		echo '<p class="pac-muted">' . esc_html__( 'Za mlađe od 18 godina potrebna je suglasnost roditelja ili skrbnika.', 'plan-a-clanstvo' ) . '</p>';
		echo self::input( 'pac_roditelj', __( 'Roditelj ili skrbnik', 'plan-a-clanstvo' ), 'text', array( 'maxlength' => 80 ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo self::input( 'pac_roditelj_kontakt', __( 'Kontakt roditelja', 'plan-a-clanstvo' ), 'text', array( 'maxlength' => 80 ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p class="form-row form-row-wide pac-check"><label><input type="checkbox" name="pac_suglasnost" value="1"> <span>' . esc_html__( 'Kao roditelj ili skrbnik dajem suglasnost za učlanjenje.', 'plan-a-clanstvo' ) . '</span></label></p>';
		echo '</div>';
		echo self::izjava_box( 'pac_izjava', __( 'Pročitao/la sam Izjavu člana i u cijelosti je prihvaćam.', 'plan-a-clanstvo' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p class="pac-muted">' . esc_html__( 'Pristupnicu potvrđuješ gumbom u e-mailu o prijavi.', 'plan-a-clanstvo' ) . '</p>';
		echo '</div>';
	}

	/** Ostali sudionici (kad je u prijavi više osoba). */
	public static function others_box() {
		if ( ! self::active() ) {
			return;
		}
		$tours = array_filter( self::cart_tours(), static fn( $t ) => $t['to'] >= $t['from'] );
		if ( ! $tours ) {
			return;
		}
		$groups = count( array_unique( wp_list_pluck( $tours, 'group' ) ) );
		echo '<div class="pac-others" data-pac-others>';
		echo '<h3>' . esc_html__( 'Ostali sudionici', 'plan-a-clanstvo' ) . '</h3>';
		echo '<p class="pac-muted">' . esc_html__( 'Za svaku osobu upiši ime, prezime i e-mail. Ako osoba nije član Plan A, otvorit će se njezina pristupnica; potvrdu će dobiti na svoj e-mail.', 'plan-a-clanstvo' ) . '</p>';
		$shown = array();
		foreach ( $tours as $key => $t ) {
			if ( $groups > 1 && ! isset( $shown[ $t['group'] ] ) ) {
				echo '<h4 class="pac-others__tour">' . esc_html( $t['name'] ) . '</h4>';
			}
			$shown[ $t['group'] ] = 1;
			for ( $i = $t['from']; $i <= $t['to']; $i++ ) {
				$n = 'pac_osobe[' . $key . '][' . $i . ']';
				echo '<fieldset class="pac-person" data-pac-person>';
				echo '<legend>' . esc_html( sprintf( /* translators: %d: redni broj osobe */ __( '%d. osoba', 'plan-a-clanstvo' ), $i ) ) . '</legend>';
				echo self::input( $n . '[email]', __( 'E-mail', 'plan-a-clanstvo' ), 'email', array( 'maxlength' => 190, 'data-pac-email' => '', 'autocomplete' => 'off' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo '<div class="pac-status" data-pac-status aria-live="polite" hidden></div>';
				echo '<div data-pac-name>';
				echo self::input( $n . '[ime]', __( 'Ime', 'plan-a-clanstvo' ), 'text', array( 'maxlength' => 60 ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo self::input( $n . '[prezime]', __( 'Prezime', 'plan-a-clanstvo' ), 'text', array( 'maxlength' => 60 ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo '</div>';
				echo '<div class="pac-join" data-pac-join hidden>';
				echo self::input( $n . '[datum]', __( 'Datum rođenja', 'plan-a-clanstvo' ), 'text', array( 'inputmode' => 'numeric', 'placeholder' => 'npr. 15.3.1990.', 'maxlength' => 12 ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo self::input( $n . '[oib]', __( 'OIB', 'plan-a-clanstvo' ), 'text', array( 'inputmode' => 'numeric', 'maxlength' => 11 ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo self::input( $n . '[adresa]', __( 'Adresa', 'plan-a-clanstvo' ), 'text', array( 'maxlength' => 100, 'placeholder' => 'Ulica i kućni broj' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo self::input( $n . '[mjesto]', __( 'Mjesto, poštanski broj', 'plan-a-clanstvo' ), 'text', array( 'maxlength' => 60, 'placeholder' => 'npr. 10000 Zagreb' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo self::input( $n . '[mobitel]', __( 'Mobitel', 'plan-a-clanstvo' ), 'tel', array( 'maxlength' => 30 ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo '<p class="pac-muted">' . esc_html__( 'Maloljetnu osobu (do 18 godina) prijavljuje roditelj ili skrbnik; podatke roditelja upiši u napomenu narudžbe.', 'plan-a-clanstvo' ) . '</p>';
				echo self::izjava_box( $n . '[izjava]', __( 'Osoba je pročitala Izjavu člana i prihvaća je (potvrdit će je i sama, u svom e-mailu).', 'plan-a-clanstvo' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo '</div>';
				echo '</fieldset>';
			}
		}
		echo '</div>';
	}

	public static function assets() {
		if ( ! self::active() ) {
			return;
		}
		wp_enqueue_style( 'plan-a-clanstvo-checkout', PLAN_A_CLANSTVO_URL . 'assets/css/checkout.css', array(), PLAN_A_CLANSTVO_VERSION );
		wp_enqueue_script( 'plan-a-clanstvo-checkout', PLAN_A_CLANSTVO_URL . 'assets/js/checkout.js', array( 'jquery' ), PLAN_A_CLANSTVO_VERSION, true );
		$s = Plan_A_Clanstvo_Data::get();
		wp_localize_script(
			'plan-a-clanstvo-checkout',
			'pacCheckout',
			array(
				'ajax'   => admin_url( 'admin-ajax.php' ),
				'nonce'  => wp_create_nonce( 'pac_member_check' ),
				'fields' => array_keys( self::MAP ),
				'fee'    => Plan_A_Clanstvo_Data::money( (float) $s['iznos'] ),
				'year'   => self::cart_year(),
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Slanje narudžbe                                                     */
	/* ------------------------------------------------------------------ */

	/** Prazna polja narudžbe popuni iz pristupnice člana (polja su bila skrivena). */
	public static function fill( $data ) {
		if ( ! self::cart_tours() || empty( $data['billing_email'] ) ) {
			return $data;
		}
		$m = self::member( (string) $data['billing_email'] );
		if ( ! $m ) {
			return $data;
		}
		list( $post, $city ) = self::split_place( (string) $m['mjesto'] );
		$from = array(
			'billing_first_name' => $m['ime'],
			'billing_last_name'  => $m['prezime'],
			'billing_address_1'  => $m['adresa'],
			'billing_city'       => $city,
			'billing_postcode'   => $post,
			'billing_phone'      => $m['mobitel'],
		);
		$used = false;
		foreach ( $from as $k => $v ) {
			if ( '' === trim( (string) ( $data[ $k ] ?? '' ) ) && '' !== $v ) {
				$data[ $k ] = $v;
				$used       = true;
			}
		}
		if ( empty( $data['billing_country'] ) ) {
			$data['billing_country'] = 'HR';
		}
		if ( $used ) {
			WC()->session && WC()->session->set( 'pac_autofill', 1 );
		}
		return $data;
	}

	private static function posted( string $k ): string {
		return sanitize_text_field( wp_unslash( $_POST[ $k ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce provjerava nonce plaćanja.
	}

	/** Ostali sudionici iz obrasca: [ključ stavke => [broj => podaci]]. */
	private static function posted_others(): array {
		$raw = isset( $_POST['pac_osobe'] ) && is_array( $_POST['pac_osobe'] ) ? wp_unslash( $_POST['pac_osobe'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- čisti se ispod.
		$out = array();
		foreach ( self::cart_tours() as $key => $t ) {
			for ( $i = $t['from']; $i <= $t['to']; $i++ ) {
				$p = isset( $raw[ $key ][ $i ] ) && is_array( $raw[ $key ][ $i ] ) ? $raw[ $key ][ $i ] : array();
				$v = array();
				foreach ( array( 'ime', 'prezime', 'email', 'datum', 'oib', 'adresa', 'mjesto', 'mobitel' ) as $f ) {
					$v[ $f ] = sanitize_text_field( (string) ( $p[ $f ] ?? '' ) );
				}
				$v['email']           = strtolower( sanitize_email( $v['email'] ) );
				$v['oib']             = preg_replace( '/\D/', '', $v['oib'] );
				$v['izjava']          = ! empty( $p['izjava'] );
				$out[ $key ][ $i ]    = $v;
			}
		}
		return $out;
	}

	private static function check_join( array $v, string $who, $errors, bool $own ) {
		$date = Plan_A_Clanstvo_Data::parse_date( $v['datum'] );
		if ( ! $date || $date > current_time( 'Y-m-d' ) || $date < '1900-01-01' ) {
			$errors->add( 'pac', $who . __( 'upiši datum rođenja, npr. 15.3.1990.', 'plan-a-clanstvo' ) );
		}
		if ( ! Plan_A_Clanstvo_Data::valid_oib( $v['oib'] ) ) {
			$errors->add( 'pac', $who . __( 'OIB nije ispravan (11 znamenki).', 'plan-a-clanstvo' ) );
		}
		if ( $own && $date && Plan_A_Clanstvo_Data::age( $date ) < 18 ) {
			if ( '' === $v['roditelj'] || '' === $v['roditelj_kontakt'] || empty( $v['suglasnost'] ) ) {
				$errors->add( 'pac', $who . __( 'za mlađe od 18 godina upiši roditelja ili skrbnika, njegov kontakt i označi suglasnost.', 'plan-a-clanstvo' ) );
			}
		}
		if ( empty( $v['izjava'] ) ) {
			$errors->add( 'pac', $who . __( 'potvrdi Izjavu člana (kvačica u pristupnici).', 'plan-a-clanstvo' ) );
		}
	}

	public static function validate( $data, $errors ) {
		if ( ! self::cart_tours() ) {
			return;
		}
		if ( ! self::member( (string) ( $data['billing_email'] ?? '' ) ) ) {
			$v = array(
				'datum'            => self::posted( 'pac_datum' ),
				'oib'              => preg_replace( '/\D/', '', self::posted( 'pac_oib' ) ),
				'roditelj'         => self::posted( 'pac_roditelj' ),
				'roditelj_kontakt' => self::posted( 'pac_roditelj_kontakt' ),
				'suglasnost'       => '' !== self::posted( 'pac_suglasnost' ),
				'izjava'           => '' !== self::posted( 'pac_izjava' ),
			);
			self::check_join( $v, __( 'Pristupnica: ', 'plan-a-clanstvo' ), $errors, true );
		}
		foreach ( self::posted_others() as $people ) {
			foreach ( $people as $i => $v ) {
				/* translators: %d: redni broj osobe */
				$who = sprintf( __( '%d. osoba: ', 'plan-a-clanstvo' ), $i );
				if ( '' === $v['ime'] || '' === $v['prezime'] ) {
					$errors->add( 'pac', $who . __( 'upiši ime i prezime.', 'plan-a-clanstvo' ) );
				}
				if ( ! is_email( $v['email'] ) ) {
					$errors->add( 'pac', $who . __( 'upiši ispravan e-mail.', 'plan-a-clanstvo' ) );
					continue;
				}
				if ( ! self::member( $v['email'] ) ) {
					if ( '' === $v['adresa'] || '' === $v['mjesto'] || ! preg_match( '/^[0-9+()\/\-\s]{6,30}$/', $v['mobitel'] ) ) {
						$errors->add( 'pac', $who . __( 'upiši adresu, mjesto s poštanskim brojem i mobitel.', 'plan-a-clanstvo' ) );
					}
					self::check_join( $v, $who, $errors, false );
				}
			}
		}
	}

	/** Osobe uz stavku narudžbe (kupac je prvi). */
	public static function line_item( $item, $cart_key, $values, $order ) {
		if ( empty( $values['ttbm_id'] ) || ! empty( $values['paj_rez'] ) ) {
			return;
		}
		$tours  = self::cart_tours();
		$first  = ! isset( $tours[ $cart_key ] ) || $tours[ $cart_key ]['first'];
		$list   = ! $first ? array() : array(
			array(
				'kupac'   => 1,
				'ime'     => $order->get_billing_first_name(),
				'prezime' => $order->get_billing_last_name(),
				'email'   => strtolower( $order->get_billing_email() ),
				'oib'     => preg_replace( '/\D/', '', self::posted( 'pac_oib' ) ),
				'datum'   => self::posted( 'pac_datum' ),
				'adresa'  => trim( $order->get_billing_address_1() . ' ' . $order->get_billing_address_2() ),
				'mjesto'  => trim( $order->get_billing_postcode() . ' ' . $order->get_billing_city() ),
				'mobitel' => $order->get_billing_phone(),
			),
		);
		$others = self::posted_others();
		foreach ( $others[ $cart_key ] ?? array() as $v ) {
			unset( $v['izjava'] );
			$list[] = $v;
		}
		$item->add_meta_data( '_pac_osobe', $list, true );
		$item->add_meta_data( '_pac_osobe_v', 2, true );
	}

	public static function order_meta( $order, $data ) {
		if ( WC()->session && WC()->session->get( 'pac_autofill' ) ) {
			$order->update_meta_data( '_pac_autofill', 1 );
			WC()->session->set( 'pac_autofill', null );
		}
	}

	/** Pristupnica nove osobe (Čeka potvrdu); vraća ID. */
	private static function join( array $v, int $year ): int {
		$data = array();
		foreach ( array_keys( Plan_A_Clanstvo_Data::FIELDS ) as $k ) {
			$data[ $k ] = sanitize_text_field( (string) ( $v[ $k ] ?? '' ) );
		}
		$data['email'] = strtolower( sanitize_email( $data['email'] ) );
		$data['datum'] = Plan_A_Clanstvo_Data::parse_date( $data['datum'] );
		$data['oib']   = preg_replace( '/\D/', '', $data['oib'] );
		$id            = Plan_A_Clanstvo_Data::save( $data, 'ceka' );
		if ( $id ) {
			update_post_meta( $id, '_pac_fee_year', $year );
			update_post_meta( $id, '_pac_izvor', 'košarica' );
			do_action( 'plan_a_clanstvo_changed', $id );
		}
		return $id;
	}

	/** Članarina za godinu izleta nije plaćena: e-mail s 2D kodom (jednom po godini). */
	private static function fee( array $m, int $year ) {
		if ( Plan_A_Clanstvo_Data::fee_paid( (int) $m['id'], $year ) || get_post_meta( (int) $m['id'], '_pac_fee_mail_' . $year, true ) ) {
			return;
		}
		if ( Plan_A_Clanstvo_Mail::fee_request( (int) $m['id'], $year ) ) {
			update_post_meta( (int) $m['id'], '_pac_fee_mail_' . $year, time() );
		}
	}

	public static function processed( $order_id, $posted, $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( '_paj_rez' ) ) {
			return;
		}
		$year = 0;
		$done = array();
		foreach ( $order->get_items() as $item ) {
			$list = $item->get_meta( '_pac_osobe' );
			if ( ! is_array( $list ) ) {
				continue;
			}
			$ts   = strtotime( (string) $item->get_meta( '_ttbm_date' ) );
			$y    = $ts ? (int) gmdate( 'Y', $ts ) : (int) current_time( 'Y' );
			$year = $year ? min( $year, $y ) : $y;
			foreach ( $list as $n => $p ) {
				$email = strtolower( (string) ( $p['email'] ?? '' ) );
				if ( ! is_email( $email ) || isset( $done[ $email ] ) ) {
					continue;
				}
				$done[ $email ] = 1;
				$m              = self::member( $email );
				if ( ! empty( $p['kupac'] ) || ( 0 === $n && ! $item->get_meta( '_pac_osobe_v' ) ) ) {
					// Kupac: gumb za potvrdu dolazi u e-mailu o narudžbi.
					if ( ! $m ) {
						$p['roditelj']         = self::posted( 'pac_roditelj' );
						$p['roditelj_kontakt'] = self::posted( 'pac_roditelj_kontakt' );
						$id                    = self::join( $p, $y );
						$m                     = $id ? Plan_A_Clanstvo_Data::get_member( $id ) : null;
						$order->update_meta_data( '_pac_nova', 1 );
					}
					if ( $m && 'potvrdeno' !== $m['status'] ) {
						$order->update_meta_data( '_pac_confirm_url', Plan_A_Clanstvo_Data::confirm_url( Plan_A_Clanstvo_Data::new_token( (int) $m['id'] ) ) );
						update_post_meta( (int) $m['id'], '_pac_requested', time() );
						update_post_meta( (int) $m['id'], '_pac_fee_year', $y );
					} elseif ( $m ) {
						self::fee( $m, $y );
					}
					continue;
				}
				// Ostali sudionici: vlastiti e-mail za potvrdu, odnosno za članarinu.
				if ( ! $m ) {
					$id = self::join( $p, $y );
					if ( $id ) {
						Plan_A_Clanstvo_Mail::confirm_request( $id );
					}
				} elseif ( 'potvrdeno' !== $m['status'] ) {
					update_post_meta( (int) $m['id'], '_pac_fee_year', $y );
					Plan_A_Clanstvo_Mail::confirm_request( (int) $m['id'] );
				} else {
					self::fee( $m, $y );
				}
			}
		}
		$order->save_meta_data();
	}

	/** Na stranici "Hvala" ne prikazuj podatke koji su upisani iz pristupnice. */
	public static function hide_details( $hide, $order ) {
		return $hide || ( $order instanceof WC_Order && $order->get_meta( '_pac_autofill' ) );
	}
}
