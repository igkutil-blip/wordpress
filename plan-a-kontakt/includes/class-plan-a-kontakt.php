<?php
/**
 * Obrazac [plan-a-kontakt]: prikaz, slanje (admin-ajax, bez predmemorije), e-mail nama i potvrda pošiljatelju.
 *
 * Atributi:
 *   predmet="…"  stalna tema (npr. anketa u objavi); tada se izlet ne bira
 *   izlet="ne"   bez izbora izleta (inače popis nadolazećih izleta, nije obavezan)
 *   telefon="ne" bez polja za mobitel
 *   gumb="…"     natpis gumba (zadano „Pošalji upit”)
 *   poruka="…"   natpis polja za poruku (zadano „Poruka”)
 *   okvir="da"   bijela kartica oko obrasca (kad stranica nema svoju)
 *
 * @package Plan_A_Kontakt
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Kontakt {

	const ACTION = 'pakon_send';

	public static function init() {
		add_shortcode( 'plan-a-kontakt', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'send' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( __CLASS__, 'send' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		wp_register_style( 'plan-a-kontakt', PLAN_A_KONTAKT_URL . 'assets/kontakt.css', array(), PLAN_A_KONTAKT_VERSION );
		wp_register_script( 'plan-a-kontakt', PLAN_A_KONTAKT_URL . 'assets/kontakt.js', array(), PLAN_A_KONTAKT_VERSION, true );
	}

	/* ---------------- Pomoćno ---------------- */

	private static function cut( string $s, int $max ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $s, 0, $max ) : substr( $s, 0, $max );
	}

	private static function len( string $s ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $s ) : strlen( $s );
	}

	/** Potpis teme iz atributa (da se tema ne može podmetnuti pri slanju). */
	private static function sig( string $subject ): string {
		return substr( hash_hmac( 'sha256', 'pakon|' . $subject, wp_salt( 'auth' ) ), 0, 20 );
	}

	/** Nadolazeći objavljeni izleti: [id => „Naziv (d. m. Y.)”], po datumu. */
	public static function tours(): array {
		$cached = get_transient( 'plan_a_kontakt_tours' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$today = current_time( 'Y-m-d' );
		$list  = array();
		$ids   = get_posts(
			array(
				'post_type'      => 'ttbm_tour',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		foreach ( $ids as $id ) {
			$dates = array();
			if ( 'particular' === (string) get_post_meta( $id, 'ttbm_travel_type', true ) ) {
				foreach ( (array) get_post_meta( $id, 'ttbm_particular_dates', true ) as $r ) {
					if ( is_array( $r ) && ! empty( $r['ttbm_particular_start_date'] ) ) {
						$dates[] = substr( (string) $r['ttbm_particular_start_date'], 0, 10 );
					}
				}
			} elseif ( get_post_meta( $id, 'ttbm_travel_start_date', true ) ) {
				$dates[] = substr( (string) get_post_meta( $id, 'ttbm_travel_start_date', true ), 0, 10 );
			}
			$dates = array_filter( $dates, static fn( $d ) => $d >= $today );
			if ( ! $dates ) {
				continue;
			}
			$first          = min( $dates );
			$ts             = strtotime( $first );
			$list[ $first . '|' . $id ] = array( (int) $id, html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ) . ( $ts ? ' (' . wp_date( 'j. n. Y.', $ts ) . ')' : '' ) );
		}
		ksort( $list );
		$out = array();
		foreach ( $list as $row ) {
			$out[ $row[0] ] = $row[1];
		}
		set_transient( 'plan_a_kontakt_tours', $out, HOUR_IN_SECONDS );
		return $out;
	}

	/* ---------------- Prikaz ---------------- */

	public static function shortcode( $atts ): string {
		$a = shortcode_atts(
			array(
				'predmet' => '',
				'izlet'   => '',
				'telefon' => 'da',
				'gumb'    => 'Pošalji upit',
				'poruka'  => 'Poruka',
				'okvir'   => 'ne',
			),
			$atts,
			'plan-a-kontakt'
		);
		$no      = static fn( $v ) => in_array( strtolower( trim( (string) $v ) ), array( 'ne', 'no', '0', 'false' ), true );
		$subject = self::cut( trim( wp_strip_all_tags( (string) $a['predmet'] ) ), 150 );
		$tours   = ( '' === $subject && ! $no( $a['izlet'] ) ) ? self::tours() : array();
		// ?izlet=123 u adresi: izlet je već odabran.
		$picked = isset( $_GET['izlet'] ) ? absint( $_GET['izlet'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		wp_enqueue_style( 'plan-a-kontakt' );
		wp_enqueue_script( 'plan-a-kontakt' );

		static $n = 0;
		++$n;
		$p  = 'pakon' . $n . '-';
		$h  = '<div class="pakon' . ( $no( $a['okvir'] ) ? '' : ' pakon-okvir' ) . '">';
		$h .= '<form class="pakon-form" method="post" action="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" novalidate>';
		$h .= '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		$h .= '<input type="hidden" name="pakon_page" value="' . esc_attr( (string) get_queried_object_id() ) . '">';
		$h .= '<input type="hidden" name="pakon_js" value="">';
		if ( '' !== $subject ) {
			$h .= '<input type="hidden" name="pakon_subject" value="' . esc_attr( $subject ) . '">';
			$h .= '<input type="hidden" name="pakon_sig" value="' . esc_attr( self::sig( $subject ) ) . '">';
		}
		// Skriveno polje za robote.
		$h .= '<div class="pakon-hp" aria-hidden="true"><label>Web stranica <input type="text" name="pakon_web" value="" tabindex="-1" autocomplete="off"></label></div>';

		$field = static function ( string $name, string $label, string $input, bool $req ) use ( $p ): string {
			return '<p class="pakon-polje" data-field="' . esc_attr( $name ) . '"><label for="' . esc_attr( $p . $name ) . '">' . esc_html( $label ) . ( $req ? '' : ' <span class="pakon-neobavezno">(nije obavezno)</span>' ) . '</label>'
				. $input . '<span class="pakon-greska" role="alert"></span></p>';
		};
		$h .= $field( 'name', 'Ime i prezime', '<input type="text" id="' . $p . 'name" name="pakon_name" maxlength="60" autocomplete="name" required>', true );
		$h .= $field( 'email', 'E-mail', '<input type="email" id="' . $p . 'email" name="pakon_email" maxlength="190" autocomplete="email" required>', true );
		if ( ! $no( $a['telefon'] ) ) {
			$h .= $field( 'phone', 'Mobitel', '<input type="tel" id="' . $p . 'phone" name="pakon_phone" maxlength="30" autocomplete="tel">', false );
		}
		if ( $tours ) {
			$opt = '<option value="">– opći upit –</option>';
			foreach ( $tours as $id => $title ) {
				$opt .= '<option value="' . (int) $id . '"' . selected( $picked, $id, false ) . '>' . esc_html( $title ) . '</option>';
			}
			$h .= $field( 'tour', 'Izlet', '<select id="' . $p . 'tour" name="pakon_tour">' . $opt . '</select>', false );
		}
		$h .= $field( 'message', (string) $a['poruka'], '<textarea id="' . $p . 'message" name="pakon_message" rows="5" maxlength="3000" required></textarea>', true );

		$privacy = get_privacy_policy_url();
		$h .= '<p class="pakon-napomena">Podatke koristimo samo za odgovor na tvoju poruku.' . ( $privacy ? ' <a href="' . esc_url( $privacy ) . '">Zaštita privatnosti</a>' : '' ) . '</p>';
		$h .= '<p class="pakon-gumb"><button type="submit">' . esc_html( (string) $a['gumb'] ) . '</button></p>';
		$h .= '<p class="pakon-poruka" role="status" aria-live="polite"></p>';
		$h .= '<noscript><p class="pakon-napomena">Za slanje obrasca uključi JavaScript ili nam piši na ' . esc_html( Plan_A_Kontakt_Admin::recipient() ) . '.</p></noscript>';
		$h .= '</form></div>';
		return $h;
	}

	/* ---------------- Slanje ---------------- */

	private static function fail( string $message, string $field = '' ) {
		wp_send_json_error( array( 'message' => $message, 'field' => $field ) );
	}

	private static function post( string $key, int $max, bool $multiline = false ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- javni obrazac na stranicama iz predmemorije; zaštita je ispod.
		$raw = isset( $_POST[ $key ] ) ? wp_unslash( (string) $_POST[ $key ] ) : '';
		$v   = $multiline ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
		return self::cut( trim( $v ), $max );
	}

	/** Broj poveznica u tekstu. */
	private static function links( string $s ): int {
		return (int) preg_match_all( '#https?://|www\.|\[url|<a\s#i', $s );
	}

	public static function send() {
		nocache_headers();
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			self::fail( 'Poruka nije poslana.' );
		}
		// Roboti: popunjeno skriveno polje ili bez JavaScripta / prebrzo (manje od 3 s).
		$js = self::post( 'pakon_js', 20 );
		if ( '' !== self::post( 'pakon_web', 100 ) || ! preg_match( '/^pa(\d+)$/', $js, $m ) || (int) $m[1] < 3 ) {
			self::fail( 'Poruka nije poslana. Osvježi stranicu i pokušaj ponovno.' );
		}

		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key   = 'pakon_rl_' . md5( $ip . wp_salt( 'nonce' ) );
		$tries = (int) get_transient( $key );
		$all   = (int) get_transient( 'pakon_rl_all' );
		if ( $tries >= 5 || $all >= 40 ) {
			self::fail( 'Previše poruka u kratkom vremenu. Pokušaj ponovno kasnije ili nam piši na ' . Plan_A_Kontakt_Admin::recipient() . '.' );
		}

		$in = array(
			'name'    => self::post( 'pakon_name', 60 ),
			'email'   => sanitize_email( self::post( 'pakon_email', 190 ) ),
			'phone'   => self::post( 'pakon_phone', 30 ),
			'message' => self::post( 'pakon_message', 3000, true ),
			'tour'    => absint( self::post( 'pakon_tour', 12 ) ),
			'subject' => self::post( 'pakon_subject', 150 ),
			'page'    => absint( self::post( 'pakon_page', 12 ) ),
		);
		if ( self::len( $in['name'] ) < 2 ) {
			self::fail( 'Upiši ime i prezime.', 'name' );
		}
		if ( self::links( $in['name'] ) || false !== strpos( $in['name'], '@' ) ) {
			self::fail( 'Upiši samo ime i prezime.', 'name' );
		}
		if ( ! is_email( $in['email'] ) ) {
			self::fail( 'Upiši ispravnu e-mail adresu.', 'email' );
		}
		if ( '' !== $in['phone'] && ! preg_match( '/^[0-9+()\/\-\s]{6,30}$/', $in['phone'] ) ) {
			self::fail( 'Upiši broj mobitela (samo brojke).', 'phone' );
		}
		if ( self::len( $in['message'] ) < 5 ) {
			self::fail( 'Napiši poruku.', 'message' );
		}
		if ( self::links( $in['message'] ) > 2 ) {
			self::fail( 'Poruka ima previše poveznica.', 'message' );
		}
		if ( '' !== $in['subject'] && ! hash_equals( self::sig( $in['subject'] ), self::post( 'pakon_sig', 40 ) ) ) {
			$in['subject'] = '';
		}
		if ( $in['tour'] && ( 'ttbm_tour' !== get_post_type( $in['tour'] ) || 'publish' !== get_post_status( $in['tour'] ) ) ) {
			$in['tour'] = 0;
		}
		if ( $in['page'] && 'publish' !== get_post_status( $in['page'] ) ) {
			$in['page'] = 0;
		}

		set_transient( $key, $tries + 1, 10 * MINUTE_IN_SECONDS );
		set_transient( 'pakon_rl_all', $all + 1, HOUR_IN_SECONDS );

		$id = Plan_A_Kontakt_Admin::store( $in );
		$ok = self::mail_us( $in, $id );
		if ( $id ) {
			update_post_meta( $id, '_pakon_mail', $ok ? 1 : 0 );
		}
		if ( ! $ok && ! $id ) {
			self::fail( 'Poruku trenutno ne možemo primiti. Piši nam na ' . Plan_A_Kontakt_Admin::recipient() . '.' );
		}
		if ( Plan_A_Kontakt_Admin::opt( 'confirm' ) ) {
			self::mail_sender( $in );
		}
		wp_send_json_success(
			array(
				'message' => 'Hvala, ' . self::first_name( $in['name'] ) . '! Poruka je poslana. Javit ćemo ti se na ' . $in['email'] . '.',
			)
		);
	}

	private static function first_name( string $name ): string {
		return (string) ( preg_split( '/\s+/u', trim( $name ) )[0] ?? $name );
	}

	/** Tema poruke za naslov maila i popis. */
	public static function topic( array $in ): string {
		if ( '' !== $in['subject'] ) {
			return $in['subject'];
		}
		if ( $in['tour'] ) {
			return html_entity_decode( get_the_title( $in['tour'] ), ENT_QUOTES, 'UTF-8' );
		}
		return 'Opći upit';
	}

	/** Zaglavlja: pošiljatelj kao WooCommerceovi mailovi; odgovor ide pošiljatelju poruke. */
	private static function headers( string $reply_name = '', string $reply_email = '' ): array {
		$from = sanitize_email( (string) get_option( 'woocommerce_email_from_address', '' ) );
		if ( ! is_email( $from ) ) {
			$from = sanitize_email( (string) get_option( 'admin_email' ) );
		}
		$name = trim( wp_strip_all_tags( (string) get_option( 'woocommerce_email_from_name', '' ) ) ) ?: 'Plan A';
		$name = str_replace( array( '"', "\r", "\n" ), '', $name );
		$h    = array( 'Content-Type: text/html; charset=UTF-8', 'From: "' . $name . '" <' . $from . '>' );
		if ( is_email( $reply_email ) ) {
			$rn  = str_replace( array( '"', "\r", "\n", '<', '>' ), '', $reply_name );
			$h[] = 'Reply-To: ' . ( '' !== $rn ? '"' . $rn . '" ' : '' ) . '<' . $reply_email . '>';
		} else {
			$h[] = 'Reply-To: ' . $from;
		}
		return $h;
	}

	public static function mail_us( array $in, int $id = 0 ): bool {
		$to = Plan_A_Kontakt_Admin::recipient();
		if ( ! is_email( $to ) ) {
			return false;
		}
		$topic = self::topic( $in );
		$rows  = array(
			array( 'Ime', esc_html( $in['name'] ) ),
			array( 'E-mail', '<a href="mailto:' . esc_attr( $in['email'] ) . '">' . esc_html( $in['email'] ) . '</a>' ),
		);
		if ( '' !== $in['phone'] ) {
			$tel    = preg_replace( '/[^0-9+]/', '', $in['phone'] );
			$rows[] = array( 'Mobitel', '<a href="tel:' . esc_attr( $tel ) . '">' . esc_html( $in['phone'] ) . '</a>' );
		}
		if ( $in['tour'] ) {
			$rows[] = array( 'Izlet', '<a href="' . esc_url( get_permalink( $in['tour'] ) ) . '">' . esc_html( get_the_title( $in['tour'] ) ) . '</a>' );
		}
		if ( '' !== $in['subject'] ) {
			$rows[] = array( 'Tema', esc_html( $in['subject'] ) );
		}
		if ( $in['page'] ) {
			$rows[] = array( 'Poslano sa', '<a href="' . esc_url( get_permalink( $in['page'] ) ) . '">' . esc_html( get_the_title( $in['page'] ) ) . '</a>' );
		}
		$reply = 'mailto:' . rawurlencode( $in['email'] ) . '?subject=' . rawurlencode( 'Re: ' . $topic );
		$body  = Plan_A_Email::card( 'Poruka', Plan_A_Email::p( nl2br( esc_html( $in['message'] ) ) ) )
			. Plan_A_Email::card( 'Pošiljatelj', Plan_A_Email::rows( $rows ) . Plan_A_Email::button( 'Odgovori', $reply ) )
			. ( $id ? Plan_A_Email::card( '', Plan_A_Email::small( 'Poruka je spremljena i u adminu: <a href="' . esc_url( admin_url( 'post.php?post=' . $id . '&action=edit' ) ) . '">Poruke</a>. Na ovaj mail možeš i izravno odgovoriti.' ) ) : '' );
		$html  = Plan_A_Email::render(
			array(
				'title'     => 'Nova poruka s weba',
				'subtitle'  => esc_html( $topic ),
				'icon'      => 'mail',
				'label'     => 'Kontakt',
				'preheader' => $in['name'] . ': ' . self::cut( $in['message'], 90 ),
			),
			$body
		);
		$subject = 'Upit s weba: ' . $topic . ' – ' . $in['name'];
		return (bool) wp_mail( $to, $subject, $html, self::headers( $in['name'], $in['email'] ) );
	}

	/** Kratka potvrda pošiljatelju (bez teksta njegove poruke, da obrazac ne posluži za spam drugima). */
	public static function mail_sender( array $in ): bool {
		$text  = trim( (string) Plan_A_Kontakt_Admin::opt( 'confirm_text' ) );
		$parts = array_filter( array_map( 'trim', preg_split( '/\n\s*\n/', $text ) ) );
		$body  = Plan_A_Email::p( 'Bok ' . esc_html( self::first_name( $in['name'] ) ) . ',' );
		foreach ( $parts as $par ) {
			$body .= Plan_A_Email::p( nl2br( esc_html( $par ) ) );
		}
		if ( $in['tour'] ) {
			$body .= Plan_A_Email::p( 'Izlet: <a href="' . esc_url( get_permalink( $in['tour'] ) ) . '">' . esc_html( get_the_title( $in['tour'] ) ) . '</a>' );
		}
		$body .= Plan_A_Email::button( 'Pogledaj izlete', home_url( '/' ) );
		$html  = Plan_A_Email::render(
			array(
				'title'     => 'Hvala na poruci!',
				'subtitle'  => 'Primili smo tvoju poruku.',
				'icon'      => 'check',
				'preheader' => 'Primili smo tvoju poruku i javit ćemo ti se uskoro.',
			),
			$body
		);
		return (bool) wp_mail( $in['email'], 'Primili smo tvoju poruku – Plan A', $html, self::headers() );
	}
}
