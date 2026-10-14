<?php
/**
 * Lista čekanja za popunjene izlete.
 *
 * - Kad na terminu nema slobodnih mjesta (ili je izlet zatvoren gumbom „Popunjeno”), umjesto gumba za prijavu
 *   stoji „Prijavi se na listu čekanja” → prozorčić s imenom, prezimenom, mobitelom, e-mailom (nije obavezan),
 *   brojem osoba i privolom. Osoba dobije svoje mjesto na listi.
 * - Mail o novom upisu ide vodičima izleta (e-mailovi iz Članstvo → Postavke → Vodiči), ne Igoru.
 * - Kad se mjesto oslobodi (slobodno > 0, a lista nije prazna), vodiči dobiju mail s prvima na listi.
 * - Upis ide i u tablicu za agenciju ispod sudionika izleta (žuti redovi „LISTA ČEKANJA”, Č1, Č2 …; skripta v21).
 * - Admin: Izleti → Liste čekanja (Javljeno, Obriši). Tko se prijavi na izlet, sam nestane s liste.
 * - Podaci se brišu 30 dana nakon izleta.
 *
 * @package Plan_A_Prijave
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Prijave_Cekanje {

	const TYPE  = 'pap_cekanje';
	const META  = '_pap_w';          // ime, prezime, mobitel, email, osobe, date, time, javljeno
	const QUEUE = 'pap_wait_queue';  // slanje u tablicu: wkey => stavka
	const FREE  = 'pap_wait_free';   // izlet|datum => zadnji broj slobodnih mjesta
	const KEEP  = 30;                // dana nakon izleta
	const MAX   = 4;                 // najviše osoba u jednom upisu

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 30 );
		add_action( 'wp_ajax_pap_wait', array( __CLASS__, 'submit' ) );
		add_action( 'wp_ajax_nopriv_pap_wait', array( __CLASS__, 'submit' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 30 );
		add_action( 'admin_post_pap_wait_do', array( __CLASS__, 'admin_action' ) );
		add_action( 'pap_wait_sheet', array( __CLASS__, 'sheet_send' ) );
		add_action( 'pap_wait_check', array( __CLASS__, 'check' ) );
		add_action( 'pap_wait_hourly', array( __CLASS__, 'hourly' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
		// Mjesta se mogu osloboditi: otkaz narudžbe, više mjesta na karti, „Otvori prijave”.
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'soon_check' ) );
		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $h ) {
			add_action( $h, array( __CLASS__, 'meta_changed' ), 10, 3 );
		}
		// Tko se s liste čekanja prijavi na izlet, više nije na listi.
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'booked' ), 40, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'booked' ), 40, 1 );
	}

	public static function register() {
		register_post_type(
			self::TYPE,
			array(
				'label'           => 'Lista čekanja',
				'public'          => false,
				'show_ui'         => false,
				'rewrite'         => false,
				'query_var'       => false,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
			)
		);
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( 'pap_wait_hourly' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'pap_wait_hourly' );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Podaci                                                              */
	/* ------------------------------------------------------------------ */

	private static function day( $date ): string {
		$ts = strtotime( (string) $date );
		return $ts ? gmdate( 'Y-m-d', $ts ) : '';
	}

	private static function digits( string $phone ): string {
		return (string) preg_replace( '/\D+/', '', $phone );
	}

	public static function data( int $id ): array {
		$d = get_post_meta( $id, self::META, true );
		return is_array( $d ) ? $d : array();
	}

	/**
	 * Upisi za izlet (i termin), redom kako su se upisali.
	 *
	 * @return array<int,array> id => podaci (+ tour)
	 */
	public static function entries( int $tour = 0, string $date = '' ): array {
		$args = array(
			'post_type'   => self::TYPE,
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby'     => 'ID',
			'order'       => 'ASC',
		);
		if ( $tour ) {
			$args['post_parent'] = $tour;
		}
		$out = array();
		foreach ( get_posts( $args ) as $p ) {
			$d = self::data( (int) $p->ID );
			if ( '' !== $date && (string) ( $d['date'] ?? '' ) !== $date ) {
				continue;
			}
			$d['tour']        = (int) $p->post_parent;
			$out[ $p->ID ] = $d;
		}
		return $out;
	}

	public static function count( int $tour, string $date ): int {
		return count( self::entries( $tour, self::day( $date ) ) );
	}

	/** Slobodnih mjesta na terminu (s našim filtrima: zajednička mjesta, „Popunjeno”). */
	public static function available( int $tour, string $date ): int {
		if ( ! class_exists( 'TTBM_Function' ) ) {
			return 1;
		}
		$date = '' !== $date ? $date : (string) current( (array) TTBM_Function::get_date( $tour ) );
		return (int) TTBM_Function::get_total_available( $tour, $date );
	}

	/* ------------------------------------------------------------------ */
	/* Stranica izleta                                                     */
	/* ------------------------------------------------------------------ */

	public static function assets() {
		if ( ! is_singular( Plan_A_Prijave_Tour::CPT ) ) {
			return;
		}
		$v = PLAN_A_PRIJAVE_VERSION . '.' . (int) @filemtime( PLAN_A_PRIJAVE_DIR . 'assets/cekanje.js' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		wp_enqueue_style( 'pap-cekanje', PLAN_A_PRIJAVE_URL . 'assets/cekanje.css', array(), $v );
		wp_enqueue_script( 'pap-cekanje', PLAN_A_PRIJAVE_URL . 'assets/cekanje.js', array(), $v, true );
	}

	/** Blok umjesto gumba za prijavu (poziva se iz book_now_smart.php kad nema slobodnih mjesta). */
	public static function block( int $tour, string $date ): string {
		$day = self::day( $date );
		$n   = self::count( $tour, $day );
		$h   = '<div class="pap-wait">';
		$h  .= '<p class="pap-wait__t">Izlet je popunjen</p>';
		$h  .= '<p class="pap-wait__x">Upiši se na listu čekanja – vodič izleta javit će ti se ako se oslobodi mjesto.</p>';
		$h  .= '<button type="button" class="pap-wait__btn" data-pap-wait="' . (int) $tour . '" data-date="' . esc_attr( $day ) . '" data-url="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-title="' . esc_attr( wp_strip_all_tags( html_entity_decode( get_the_title( $tour ), ENT_QUOTES, 'UTF-8' ) ) ) . '">Prijavi se na listu čekanja</button>';
		if ( $n > 0 ) {
			$h .= '<p class="pap-wait__n">Na listi čekanja: <b>' . $n . '</b></p>';
		}
		return $h . '</div>';
	}

	/* ------------------------------------------------------------------ */
	/* Upis (AJAX)                                                         */
	/* ------------------------------------------------------------------ */

	private static function post( string $k, int $max ): string {
		$v = isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- stranica je u predmemoriji; zaštita: skriveno polje, vrijeme, ograničenje po IP-u
		return function_exists( 'mb_substr' ) ? mb_substr( trim( $v ), 0, $max ) : substr( trim( $v ), 0, $max );
	}

	private static function fail( string $msg, string $field = '' ) {
		wp_send_json_error( array( 'message' => $msg, 'field' => $field ) );
	}

	public static function submit() {
		nocache_headers();
		$js = self::post( 'pw_js', 20 );
		if ( '' !== self::post( 'pw_web', 100 ) || ! preg_match( '/^pa(\d+)$/', $js, $m ) || (int) $m[1] < 3 ) {
			self::fail( 'Upis nije poslan. Osvježi stranicu i pokušaj ponovno.' );
		}
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key   = 'pap_wait_rl_' . md5( $ip . wp_salt( 'nonce' ) );
		$tries = (int) get_transient( $key );
		$all   = (int) get_transient( 'pap_wait_rl_all' );
		if ( $tries >= 5 || $all >= 30 ) {
			self::fail( 'Previše upisa u kratkom vremenu. Pokušaj ponovno kasnije.' );
		}

		$tour = absint( self::post( 'pw_tour', 12 ) );
		$date = self::day( self::post( 'pw_date', 20 ) );
		if ( ! $tour || Plan_A_Prijave_Tour::CPT !== get_post_type( $tour ) || 'publish' !== get_post_status( $tour ) || '' === $date ) {
			self::fail( 'Izlet nije pronađen. Osvježi stranicu.' );
		}
		if ( $date < current_time( 'Y-m-d' ) ) {
			self::fail( 'Izlet je već prošao.' );
		}
		$in = array(
			'ime'     => self::post( 'pw_ime', 40 ),
			'prezime' => self::post( 'pw_prezime', 60 ),
			'mobitel' => self::post( 'pw_mobitel', 30 ),
			'email'   => sanitize_email( self::post( 'pw_email', 190 ) ),
			'osobe'   => max( 1, min( self::MAX, absint( self::post( 'pw_osobe', 2 ) ) ) ),
		);
		foreach ( array( 'ime' => 'Upiši ime.', 'prezime' => 'Upiši prezime.' ) as $k => $msg ) {
			if ( ( function_exists( 'mb_strlen' ) ? mb_strlen( $in[ $k ] ) : strlen( $in[ $k ] ) ) < 2 || preg_match( '#https?://|www\.|@#i', $in[ $k ] ) ) {
				self::fail( $msg, $k );
			}
		}
		if ( ! preg_match( '/^[0-9+()\/\-\s]{6,30}$/', $in['mobitel'] ) || strlen( self::digits( $in['mobitel'] ) ) < 8 ) {
			self::fail( 'Upiši broj mobitela (npr. 091 234 5678).', 'mobitel' );
		}
		if ( '' !== self::post( 'pw_email', 190 ) && ! is_email( $in['email'] ) ) {
			self::fail( 'E-mail nije ispravan (ili ga ostavi praznim).', 'email' );
		}
		if ( '1' !== self::post( 'pw_ok', 2 ) ) {
			self::fail( 'Označi privolu za podatke.', 'ok' );
		}
		if ( self::available( $tour, $date ) > 0 ) {
			wp_send_json_error(
				array(
					'message' => 'Na izletu se upravo oslobodilo mjesto – prijavi se odmah! Osvježi stranicu.',
					'reload'  => 1,
				)
			);
		}
		$list = self::entries( $tour, $date );
		$pos  = 0;
		foreach ( $list as $d ) {
			++$pos;
			if ( substr( self::digits( (string) $d['mobitel'] ), -8 ) === substr( self::digits( $in['mobitel'] ), -8 ) ) {
				wp_send_json_success( array( 'message' => 'Već si na listi čekanja za ovaj izlet – ' . $pos . '. na listi. Ako se oslobodi mjesto, vodič izleta javit će ti se.' ) );
			}
		}

		set_transient( $key, $tries + 1, 10 * MINUTE_IN_SECONDS );
		set_transient( 'pap_wait_rl_all', $all + 1, HOUR_IN_SECONDS );

		$in['date']     = $date;
		$in['time']     = current_time( 'mysql' );
		$in['javljeno'] = '';
		$id             = wp_insert_post(
			array(
				'post_type'   => self::TYPE,
				'post_status' => 'publish',
				'post_parent' => $tour,
				'post_title'  => $in['ime'] . ' ' . $in['prezime'],
			),
			true
		);
		if ( is_wp_error( $id ) || ! $id ) {
			self::fail( 'Upis trenutno nije moguć. Pokušaj ponovno ili nas nazovi.' );
		}
		update_post_meta( $id, self::META, $in );
		$free                          = (array) get_option( self::FREE, array() );
		$free[ $tour . '|' . $date ] = 0;
		update_option( self::FREE, $free, false );

		$pos = count( $list ) + 1;
		self::mail_new( (int) $id, $tour, $pos );
		self::sheet_queue( (int) $id );

		wp_send_json_success(
			array(
				'message' => 'Hvala, ' . $in['ime'] . '! Upisan/a si na listu čekanja – ' . $pos . '. na listi'
					. ( $in['osobe'] > 1 ? ' (' . $in['osobe'] . ' osobe)' : '' ) . '. Ako se oslobodi mjesto, vodič izleta javit će ti se na ' . $in['mobitel'] . '.',
				'n'       => $pos,
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Mailovi vodičima                                                    */
	/* ------------------------------------------------------------------ */

	/** E-mailovi vodiča izleta (imena iz izleta, adrese iz Članstvo → Postavke → Vodiči); bez njih adresa trgovine. */
	public static function guide_mails( int $tour ): array {
		$names = array();
		foreach ( (array) get_post_meta( $tour, 'ttbm_tour_guide', true ) as $g ) {
			$t = (int) $g ? get_the_title( (int) $g ) : '';
			if ( '' !== $t ) {
				// Svaka riječ posebno: naziv vodiča može biti „Kutil Igor” ili „Igor Kutil”.
				$names = array_merge( $names, preg_split( '/\s+/u', trim( html_entity_decode( $t, ENT_QUOTES, 'UTF-8' ) ) ) );
			}
		}
		$to = $names && class_exists( 'Plan_A_Clanstvo_Ops' ) && method_exists( 'Plan_A_Clanstvo_Ops', 'guide_mails' ) ? Plan_A_Clanstvo_Ops::guide_mails( implode( ' + ', $names ) ) : array();
		if ( ! $to ) {
			$to = array( sanitize_email( (string) get_option( 'woocommerce_email_from_address', '' ) ) ?: (string) get_option( 'admin_email' ) );
		}
		return array_values( array_filter( $to, 'is_email' ) );
	}

	private static function headers( string $reply = '' ): array {
		$from = sanitize_email( (string) get_option( 'woocommerce_email_from_address', '' ) ) ?: sanitize_email( (string) get_option( 'admin_email' ) );
		$name = str_replace( array( '"', "\r", "\n" ), '', trim( wp_strip_all_tags( (string) get_option( 'woocommerce_email_from_name', '' ) ) ) ?: 'Plan A' );
		$h    = array( 'Content-Type: text/html; charset=UTF-8', 'From: "' . $name . '" <' . $from . '>' );
		if ( is_email( $reply ) ) {
			$h[] = 'Reply-To: ' . $reply;
		}
		return $h;
	}

	private static function tour_label( int $tour, string $date ): string {
		$ts = strtotime( $date );
		return html_entity_decode( get_the_title( $tour ), ENT_QUOTES, 'UTF-8' ) . ( $ts ? ' (' . gmdate( 'j.n.Y.', $ts ) . ')' : '' );
	}

	private static function phone_link( string $p ): string {
		return '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $p ) ) . '" style="color:' . Plan_A_Email::color( 'accent' ) . ';font-weight:bold;">' . esc_html( $p ) . '</a>';
	}

	/** Cijela lista kao tablica za mail. */
	private static function list_html( array $list, int $mark = 0 ): string {
		$rows = array();
		$i    = 0;
		foreach ( $list as $id => $d ) {
			++$i;
			$name   = esc_html( trim( $d['ime'] . ' ' . $d['prezime'] ) );
			$rows[] = array(
				$i . '.',
				( $id === $mark ? '<b>' . $name . '</b> (novi)' : $name ) . ( (int) $d['osobe'] > 1 ? ' · ' . (int) $d['osobe'] . ' osobe' : '' ) . ( '' !== (string) $d['javljeno'] ? ' · javljeno' : '' ),
				self::phone_link( (string) $d['mobitel'] ),
			);
		}
		return Plan_A_Email::table( array( 'Br.', 'Ime i prezime', 'Mobitel' ), $rows );
	}

	public static function build_new( int $id, int $tour, int $pos ): array {
		$d    = self::data( $id );
		$list = self::entries( $tour, (string) $d['date'] );
		$rows = array(
			array( 'Izlet', esc_html( self::tour_label( $tour, (string) $d['date'] ) ) ),
			array( 'Ime i prezime', esc_html( trim( $d['ime'] . ' ' . $d['prezime'] ) ) ),
			array( 'Mobitel', self::phone_link( (string) $d['mobitel'] ) ),
		);
		if ( '' !== (string) $d['email'] ) {
			$rows[] = array( 'E-mail', '<a href="mailto:' . esc_attr( $d['email'] ) . '" style="color:' . Plan_A_Email::color( 'accent' ) . ';">' . esc_html( $d['email'] ) . '</a>' );
		}
		$rows[] = array( 'Broj osoba', (string) (int) $d['osobe'] );
		$rows[] = array( 'Mjesto na listi', '<b>' . $pos . '.</b>' );
		return array(
			'subject' => 'Lista čekanja: ' . self::tour_label( $tour, (string) $d['date'] ) . ' – ' . trim( $d['ime'] . ' ' . $d['prezime'] ),
			'html'    => Plan_A_Email::render(
				array( 'title' => 'Nova osoba na listi čekanja', 'icon' => 'list', 'label' => 'Plan A · lista čekanja' ),
				Plan_A_Email::card( '', Plan_A_Email::p( 'Izlet je popunjen, a na listu čekanja upisala se nova osoba. Ako se oslobodi mjesto, javi se prvoj osobi na listi (dobit ćeš i mail kad se mjesto oslobodi).' ) )
				. Plan_A_Email::card( 'Novi upis', Plan_A_Email::rows( $rows ) )
				. Plan_A_Email::card( 'Cijela lista čekanja', self::list_html( $list, $id ) . Plan_A_Email::small( 'Lista je i u tablici za agenciju, žuti redovi ispod sudionika izleta. Podaci se brišu 30 dana nakon izleta.' ) )
			),
			'reply'   => (string) $d['email'],
		);
	}

	private static function mail_new( int $id, int $tour, int $pos ) {
		$m = self::build_new( $id, $tour, $pos );
		foreach ( self::guide_mails( $tour ) as $to ) {
			wp_mail( $to, $m['subject'], $m['html'], self::headers( $m['reply'] ) );
		}
	}

	public static function build_free( int $tour, string $date, int $free, array $list ): array {
		$label = self::tour_label( $tour, $date );
		$first = reset( $list );
		$n     = count( $list );
		$few   = $n % 10 >= 2 && $n % 10 <= 4 && ( $n % 100 < 12 || $n % 100 > 14 );
		$intro = 'Na izletu se oslobodilo ' . ( 1 === $free % 10 && 11 !== $free % 100 ? $free . ' mjesto' : $free . ' mjesta' ) . ', a na listi čekanja ' . ( 1 === $n ? 'je 1 osoba' : ( $few ? 'su ' . $n . ' osobe' : 'je ' . $n . ' osoba' ) ) . '. Javi se prvoj osobi na listi (' . esc_html( trim( $first['ime'] . ' ' . $first['prezime'] ) ) . ') da se prijavi na stranici – mjesto nije rezervirano, dobiva ga tko se prvi prijavi.';
		return array(
			'subject' => 'Oslobodilo se mjesto: ' . $label,
			'html'    => Plan_A_Email::render(
				array( 'title' => 'Oslobodilo se mjesto na izletu', 'icon' => 'star', 'label' => 'Plan A · lista čekanja' ),
				Plan_A_Email::card( $label, Plan_A_Email::p( $intro ) )
				. Plan_A_Email::card( 'Lista čekanja', self::list_html( $list ) . Plan_A_Email::button( 'Stranica izleta', (string) get_permalink( $tour ) ) )
			),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Oslobođeno mjesto                                                   */
	/* ------------------------------------------------------------------ */

	public static function soon_check() {
		if ( ! wp_next_scheduled( 'pap_wait_check' ) ) {
			wp_schedule_single_event( time() + 60, 'pap_wait_check' );
		}
	}

	public static function meta_changed( $meta_id, $object_id, $key ) {
		if ( in_array( $key, array( 'ttbm_ticket_type', Plan_A_Prijave_Seats::FULL ), true ) && Plan_A_Prijave_Tour::CPT === get_post_type( (int) $object_id ) ) {
			self::soon_check();
		}
	}

	/** Za svaki termin s listom: mjesto se oslobodilo (prije 0, sada > 0) → mail vodičima. */
	public static function check() {
		$free  = (array) get_option( self::FREE, array() );
		$group = array();
		foreach ( self::entries() as $id => $d ) {
			$group[ $d['tour'] . '|' . $d['date'] ][ $id ] = $d;
		}
		$new = array();
		foreach ( $group as $k => $list ) {
			list( $tour, $date ) = explode( '|', $k );
			if ( $date < current_time( 'Y-m-d' ) || 'publish' !== get_post_status( (int) $tour ) ) {
				continue;
			}
			$now       = max( 0, self::available( (int) $tour, $date ) );
			$new[ $k ] = $now;
			if ( $now > 0 && (int) ( $free[ $k ] ?? 0 ) === 0 ) {
				$m = self::build_free( (int) $tour, $date, $now, $list );
				foreach ( self::guide_mails( (int) $tour ) as $to ) {
					wp_mail( $to, $m['subject'], $m['html'], self::headers() );
				}
			}
		}
		update_option( self::FREE, $new, false );
	}

	/** Tko se s liste prijavio na izlet (isti mobitel ili e-mail), briše se s liste. */
	public static function booked( $order_id ) {
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		if ( ! $order ) {
			return;
		}
		$phone = substr( self::digits( (string) $order->get_billing_phone() ), -8 );
		$email = strtolower( (string) $order->get_billing_email() );
		foreach ( $order->get_items() as $item ) {
			$tour = (int) $item->get_meta( '_ttbm_id' );
			if ( ! $tour ) {
				continue;
			}
			foreach ( self::entries( $tour ) as $id => $d ) {
				$p = substr( self::digits( (string) $d['mobitel'] ), -8 );
				if ( ( strlen( $phone ) === 8 && $p === $phone ) || ( '' !== (string) $d['email'] && strtolower( (string) $d['email'] ) === $email ) ) {
					self::remove( (int) $id );
				}
			}
		}
	}

	public static function remove( int $id ) {
		$p = get_post( $id );
		if ( ! $p || self::TYPE !== $p->post_type ) {
			return;
		}
		$d = self::data( $id );
		self::queue( 'w' . $id, (int) $p->post_parent, (string) ( $d['date'] ?? '' ), null );
		wp_delete_post( $id, true );
	}

	public static function hourly() {
		self::check();
		self::sheet_send();
		// Brisanje 30 dana nakon izleta.
		$cut = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -' . self::KEEP . ' days' ) );
		foreach ( self::entries() as $id => $d ) {
			if ( (string) $d['date'] < $cut ) {
				wp_delete_post( (int) $id, true ); // u tablici ih briše arhiva (30 dana nakon izleta)
			}
		}
	}

	/* ------------------------------------------------------------------ */
	/* Tablica za agenciju                                                 */
	/* ------------------------------------------------------------------ */

	private static function note( array $d ): string {
		$ts = strtotime( (string) $d['time'] );
		$n  = ( $ts ? gmdate( 'j.n.Y. H:i', $ts ) : '' ) . ( (int) $d['osobe'] > 1 ? ' · ' . (int) $d['osobe'] . ' osobe' : '' );
		$j  = strtotime( (string) $d['javljeno'] );
		return $n . ( $j ? ' · javljeno ' . gmdate( 'j.n.', $j ) : '' );
	}

	private static function queue( string $wkey, int $tour, string $date, ?array $d ) {
		if ( ! class_exists( 'Plan_A_Clanstvo_Agency' ) ) {
			return;
		}
		$q          = (array) get_option( self::QUEUE, array() );
		$q[ $wkey ] = array(
			'tour' => $tour,
			'date' => $date,
			'del'  => null === $d,
			'v'    => null === $d ? null : array(
				'ime'      => $d['ime'],
				'prezime'  => $d['prezime'],
				'mobitel'  => $d['mobitel'],
				'email'    => $d['email'],
				'napomena' => self::note( $d ),
			),
		);
		update_option( self::QUEUE, $q, false );
		if ( ! wp_next_scheduled( 'pap_wait_sheet' ) ) {
			wp_schedule_single_event( time() + 5, 'pap_wait_sheet' );
		}
	}

	public static function sheet_queue( int $id ) {
		$p = get_post( $id );
		if ( $p ) {
			$d = self::data( $id );
			self::queue( 'w' . $id, (int) $p->post_parent, (string) $d['date'], $d );
		}
	}

	/** Pošalji red u tablicu (skripta v21+); ako ne uspije, ponavlja se svaki sat. */
	public static function sheet_send(): array {
		$q = (array) get_option( self::QUEUE, array() );
		if ( ! $q || ! class_exists( 'Plan_A_Clanstvo_Agency' ) || ! Plan_A_Clanstvo_Agency::enabled() ) {
			return array( 'ok' => false, 'n' => 0 );
		}
		$items = array();
		foreach ( $q as $wkey => $it ) {
			$x = array(
				'tour' => Plan_A_Clanstvo_Agency::tour( (int) $it['tour'], (string) $it['date'] ),
				'wkey' => (string) $wkey,
			);
			if ( ! empty( $it['del'] ) ) {
				$x['del'] = true;
			} else {
				$x['v'] = $it['v'];
			}
			$items[] = $x;
		}
		$res = Plan_A_Clanstvo_Agency::call(
			array(
				'action' => 'ag_wait',
				'items'  => $items,
			)
		);
		if ( ! empty( $res['ok'] ) ) {
			$now = (array) get_option( self::QUEUE, array() );
			foreach ( $q as $wkey => $it ) {
				if ( isset( $now[ $wkey ] ) && $now[ $wkey ] === $it ) {
					unset( $now[ $wkey ] );
				}
			}
			update_option( self::QUEUE, $now, false );
		}
		return $res;
	}

	/* ------------------------------------------------------------------ */
	/* Admin: Izleti → Liste čekanja                                       */
	/* ------------------------------------------------------------------ */

	public static function menu() {
		$n     = count( self::entries() );
		$label = 'Liste čekanja' . ( $n ? ' <span class="awaiting-mod">' . $n . '</span>' : '' );
		add_submenu_page( 'edit.php?post_type=' . Plan_A_Prijave_Tour::CPT, 'Liste čekanja', $label, 'edit_posts', 'pap-cekanje', array( __CLASS__, 'page' ) );
	}

	private static function action_url( string $do, int $id ): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=pap_wait_do&do=' . $do . '&id=' . $id ), 'pap_wait_' . $id );
	}

	public static function admin_action() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'pap_wait_' . $id );
		if ( ! current_user_can( 'edit_posts' ) || self::TYPE !== get_post_type( $id ) ) {
			wp_die( 'Nema dozvole.' );
		}
		$do = isset( $_GET['do'] ) ? sanitize_key( $_GET['do'] ) : '';
		if ( 'del' === $do ) {
			self::remove( $id );
		} elseif ( 'done' === $do ) {
			$d             = self::data( $id );
			$d['javljeno'] = '' === (string) $d['javljeno'] ? current_time( 'mysql' ) : '';
			update_post_meta( $id, self::META, $d );
			self::sheet_queue( $id );
		}
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . Plan_A_Prijave_Tour::CPT . '&page=pap-cekanje&ok=1' ) );
		exit;
	}

	public static function page() {
		$group = array();
		foreach ( self::entries() as $id => $d ) {
			$group[ $d['date'] . '|' . $d['tour'] ][ $id ] = $d;
		}
		ksort( $group );
		echo '<div class="wrap"><h1>Liste čekanja</h1>';
		echo '<p>Kad je izlet popunjen, na stranici izleta stoji gumb „Prijavi se na listu čekanja”. Vodiči izleta dobiju mail za svaki novi upis i kad se mjesto oslobodi. Upisi su i u tablici za agenciju (žuti redovi ispod sudionika). Tko se prijavi na izlet, sam nestaje s liste; podaci se brišu 30 dana nakon izleta.</p>';
		if ( ! $group ) {
			echo '<p><em>Trenutno nitko nije na listi čekanja.</em></p></div>';
			return;
		}
		$q = (array) get_option( self::QUEUE, array() );
		foreach ( $group as $k => $list ) {
			list( $date, $tour ) = explode( '|', $k );
			$free                = self::available( (int) $tour, $date );
			echo '<h2 style="margin-top:28px">' . esc_html( self::tour_label( (int) $tour, $date ) ) . ' <a class="page-title-action" href="' . esc_url( (string) get_permalink( (int) $tour ) ) . '" target="_blank">Stranica izleta</a></h2>';
			echo '<p>Slobodnih mjesta: <b' . ( $free > 0 ? ' style="color:#1e7d3a"' : '' ) . '>' . (int) $free . '</b></p>';
			echo '<table class="widefat striped" style="max-width:1000px"><thead><tr><th style="width:40px">Br.</th><th>Ime i prezime</th><th>Mobitel</th><th>E-mail</th><th>Osoba</th><th>Upisano</th><th>Javljeno</th><th></th></tr></thead><tbody>';
			$i = 0;
			foreach ( $list as $id => $d ) {
				++$i;
				$ts = strtotime( (string) $d['time'] );
				$j  = strtotime( (string) $d['javljeno'] );
				echo '<tr><td>Č' . (int) $i . '</td><td><b>' . esc_html( trim( $d['ime'] . ' ' . $d['prezime'] ) ) . '</b>' . ( isset( $q[ 'w' . $id ] ) ? ' <small style="color:#b26200">(još nije u tablici)</small>' : '' ) . '</td>';
				echo '<td><a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', (string) $d['mobitel'] ) ) . '">' . esc_html( $d['mobitel'] ) . '</a></td>';
				echo '<td>' . ( '' !== (string) $d['email'] ? '<a href="mailto:' . esc_attr( $d['email'] ) . '">' . esc_html( $d['email'] ) . '</a>' : '–' ) . '</td>';
				echo '<td>' . (int) $d['osobe'] . '</td><td>' . esc_html( $ts ? gmdate( 'j.n.Y. H:i', $ts ) : '' ) . '</td>';
				echo '<td><a class="button button-small" href="' . esc_url( self::action_url( 'done', (int) $id ) ) . '">' . ( $j ? '✓ ' . esc_html( gmdate( 'j.n.', $j ) ) : 'Javljeno' ) . '</a></td>';
				echo '<td><a class="button button-small button-link-delete" href="' . esc_url( self::action_url( 'del', (int) $id ) ) . '" onclick="return confirm(\'Obrisati s liste čekanja?\')">Obriši</a></td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
	}
}
