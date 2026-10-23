<?php
/**
 * Tok provjere: /age-verification/start (odlazak na mGrađani) i /age-verification/callback (povratak).
 *
 * Povratak ide na adresu prijavljenu ministarstvu (https://domesticspirits.hr/age-verification/callback), kao obična
 * stranica a ne REST ruta – tako je WooCommerce sesija kupca dostupna. code_verifier i nonce čuvaju se samo na poslužitelju,
 * u transientu pod sha256(state); transakcija je jednokratna, istječe za 15 minuta i vezana je na sesiju koja ju je pokrenula.
 */

defined( 'ABSPATH' ) || exit;

final class Domestic_Dob_Tok {

	const QV  = 'dsd_korak';
	const TTL = 900;
	const SES = 'dsd_provjera';

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'rewrite' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'obradi' ), 1 );
		add_action( 'init', array( __CLASS__, 'flush_ako_treba' ), 99 );
	}

	public static function rewrite(): void {
		add_rewrite_rule( '^age-verification/(start|callback)/?$', 'index.php?' . self::QV . '=$matches[1]', 'top' );
	}

	public static function flush_ako_treba(): void {
		if ( get_option( 'dsd_rewrite' ) !== DSD_VER ) {
			flush_rewrite_rules( false );
			update_option( 'dsd_rewrite', DSD_VER, false );
		}
	}

	public static function query_vars( array $v ): array {
		$v[] = self::QV;
		return $v;
	}

	public static function start_url(): string {
		return wp_nonce_url( home_url( '/age-verification/start' ), 'dsd_start', '_dsd' );
	}

	public static function obradi(): void {
		$korak = get_query_var( self::QV );
		if ( 'start' !== $korak && 'callback' !== $korak ) {
			return;
		}
		nocache_headers();
		if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
			status_header( 405 );
			exit;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			self::natrag( 'Provjera trenutno nije dostupna. Pokušajte ponovno.', 'error' );
		}
		'start' === $korak ? self::start() : self::callback();
	}

	private static function sesija(): string {
		$s = WC()->session;
		if ( ! $s->has_session() ) {
			$s->set_customer_session_cookie( true );
		}
		return (string) $s->get_customer_id();
	}

	private static function start(): void {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_dsd'] ?? '' ) ), 'dsd_start' ) ) {
			self::natrag( 'Sigurnosna provjera nije uspjela. Pokušajte ponovno.', 'error' );
		}
		if ( ! Domestic_Dob_Postavke::podeseno() ) {
			Domestic_Dob_Postavke::log( 'Start: dodatak nije podešen (client_id/endpointi).', 'error' );
			self::natrag( 'Provjera punoljetnosti trenutno nije dostupna. Molimo kontaktirajte nas.', 'error' );
		}
		$cfg      = Domestic_Dob_Postavke::aktivno();
		$state    = Domestic_Dob_Oidc::slucajno();
		$nonce    = Domestic_Dob_Oidc::slucajno();
		$verifier = Domestic_Dob_Oidc::verifier();
		$redirect = Domestic_Dob_Postavke::redirect_uri();

		set_transient(
			self::tx_kljuc( $state ),
			array(
				'verifier' => $verifier,
				'nonce'    => $nonce,
				'sesija'   => self::sesija(),
				'vrijeme'  => time(),
				'env'      => $cfg['env'],
				'redirect' => $redirect,
			),
			self::TTL
		);
		Domestic_Dob_Postavke::log( 'Start: ' . $cfg['env'] . ', state ' . Domestic_Dob_Postavke::otisak( $state ) );
		wp_redirect( Domestic_Dob_Oidc::authorize_url( $cfg, $redirect, $state, $nonce, Domestic_Dob_Oidc::challenge( $verifier ) ) ); // phpcs:ignore WordPress.Security.SafeRedirect -- vanjski IDP
		exit;
	}

	private static function callback(): void {
		// Parametri iz sirovog upita: WordPress na rewrite ruti briše $_GET['error'] (javni query var), a IDP tako javlja odbijanje.
		parse_str( (string) ( $_SERVER['QUERY_STRING'] ?? '' ), $get );
		$state =is_string( $get['state'] ?? null ) ? $get['state'] : '';
		if ( '' === $state ) {
			self::natrag( 'Povratak s provjere nije ispravan. Pokušajte ponovno.', 'error' );
		}
		// Jednokratno: transakcija se briše odmah, prije bilo čega drugog.
		$key = self::tx_kljuc( $state );
		$tx  = get_transient( $key );
		delete_transient( $key );
		if ( ! is_array( $tx ) || time() - (int) $tx['vrijeme'] > self::TTL ) {
			Domestic_Dob_Postavke::log( 'Callback: nepoznat, iskorišten ili istekao state ' . Domestic_Dob_Postavke::otisak( $state ), 'warning' );
			self::natrag( 'Provjera je istekla ili je već iskorištena. Pokrenite je ponovno.', 'error' );
		}
		if ( ! hash_equals( (string) $tx['sesija'], self::sesija() ) ) {
			Domestic_Dob_Postavke::log( 'Callback: state ne pripada ovoj sesiji.', 'warning' );
			self::natrag( 'Provjera nije pokrenuta u ovom pregledniku. Pokrenite je ponovno.', 'error' );
		}
		if ( ! empty( $get['error'] ) ) {
			$err = sanitize_key( (string) $get['error'] );
			Domestic_Dob_Postavke::log( 'Callback: IDP greška ' . $err, 'warning' );
			self::natrag( 'access_denied' === $err ? 'Provjera punoljetnosti je otkazana u aplikaciji mGrađani. Bez nje nije moguće naručiti alkoholna pića.' : 'Provjera punoljetnosti nije uspjela. Pokušajte ponovno.', 'error' );
		}
		$code = is_string( $get['code'] ?? null ) ? $get['code'] : '';
		if ( '' === $code ) {
			self::natrag( 'Povratak s provjere nije ispravan. Pokušajte ponovno.', 'error' );
		}

		$cfg = Domestic_Dob_Postavke::sve()[ $tx['env'] ];
		$cfg['env'] = $tx['env'];
		$jwt = Domestic_Dob_Oidc::razmjena( $cfg, $code, (string) $tx['verifier'], (string) $tx['redirect'] );
		$c   = is_wp_error( $jwt ) ? $jwt : Domestic_Dob_Oidc::provjeri( $jwt, $cfg, (string) $tx['nonce'] );
		if ( is_wp_error( $c ) ) {
			Domestic_Dob_Postavke::log( 'Callback: ' . $c->get_error_code() . ' – ' . $c->get_error_message(), 'error' );
			self::natrag( 'Provjera punoljetnosti nije uspjela. Pokušajte ponovno.', 'error' );
		}
		$odluka = Domestic_Dob_Oidc::odluka( $c );
		unset( $c ); // sub i ostali claimovi se ne čuvaju
		if ( true === $odluka ) {
			WC()->session->set( self::SES, array( 'vrijeme' => time(), 'env' => $tx['env'] ) );
			Domestic_Dob_Postavke::log( 'Callback: punoljetnost potvrđena (' . $tx['env'] . ').' );
			self::natrag( 'Punoljetnost je potvrđena. Možete dovršiti narudžbu.', 'success' );
		}
		WC()->session->set( self::SES, null );
		Domestic_Dob_Postavke::log( 'Callback: ' . ( false === $odluka ? 'kupac nije punoljetan' : 'nejasan odgovor age.alcohol' ) . '.' );
		self::natrag( false === $odluka ? 'Prema podacima sustava e-Građani niste punoljetni. Alkoholna pića prodajemo samo punoljetnim osobama.' : 'Provjera punoljetnosti nije uspjela. Pokušajte ponovno.', 'error' );
	}

	private static function tx_kljuc( string $state ): string {
		return 'dsd_tx_' . substr( hash( 'sha256', $state ), 0, 40 );
	}

	/** Poruka kupcu i povratak na blagajnu. */
	private static function natrag( string $poruka, string $vrsta ): void {
		if ( function_exists( 'wc_add_notice' ) && function_exists( 'WC' ) && WC()->session ) {
			wc_add_notice( $poruka, $vrsta );
		}
		wp_safe_redirect( function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/' ) );
		exit;
	}
}
