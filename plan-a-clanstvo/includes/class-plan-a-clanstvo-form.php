<?php
/**
 * Shortcode [plan-a-pristupnica]: obrazac, stranica nakon slanja i potvrda iz e-maila.
 *
 * Obrazac se šalje na istu stranicu (POST se ne sprema u predmemoriju). Poveznica iz e-maila
 * (?potvrda=…) otvara stranicu koja potvrdu šalje sama (POST), pa je ne potvrđuju programi
 * za provjeru poveznica u pošti; bez JavaScripta posjetitelj klikne gumb.
 *
 * @package Plan_A_Clanstvo
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Clanstvo_Form {

	const TAG = 'plan-a-pristupnica';

	/** @var array Greške i upisane vrijednosti kad slanje ne uspije. */
	private static $errors = array();
	private static $values = array();

	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function assets() {
		wp_register_style( 'plan-a-clanstvo', PLAN_A_CLANSTVO_URL . 'assets/css/clanstvo.css', array(), PLAN_A_CLANSTVO_VERSION );
		wp_register_script( 'plan-a-clanstvo', PLAN_A_CLANSTVO_URL . 'assets/js/clanstvo.js', array(), PLAN_A_CLANSTVO_VERSION, array( 'in_footer' => true ) );
	}

	private static function post( string $key, int $max = 200 ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- javni obrazac; zaštita: skriveno polje, ograničenje po IP-u.
		$v = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		return function_exists( 'mb_substr' ) ? mb_substr( $v, 0, $max ) : substr( $v, 0, $max );
	}

	private static function here(): string {
		$id = get_queried_object_id();
		return $id ? (string) get_permalink( $id ) : Plan_A_Clanstvo_Data::page_url();
	}

	public static function handle() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( isset( $_GET['potvrda'] ) || isset( $_GET['pristupnica'] ) ) {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
			nocache_headers();
		}
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['pac_action'] ) ) {
			return;
		}
		$action = sanitize_key( wp_unslash( $_POST['pac_action'] ) );
		// phpcs:enable
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		if ( 'prijava' === $action ) {
			self::submit();
		} elseif ( 'potvrdi' === $action || 'nova' === $action ) {
			$token = self::post( 'token', 64 );
			list( $id, $state ) = Plan_A_Clanstvo_Data::by_token( $token );
			if ( 'potvrdi' === $action && $id && 'ok' === $state ) {
				if ( Plan_A_Clanstvo_Data::confirm( $id ) ) {
					Plan_A_Clanstvo_Mail::confirmed( $id );
				}
				wp_safe_redirect( add_query_arg( array( 'potvrda' => $token, 'gotovo' => 1 ), self::here() ) );
				exit;
			}
			if ( 'nova' === $action && $id && self::rate_ok( 'nova' ) ) {
				Plan_A_Clanstvo_Mail::confirm_request( $id );
				wp_safe_redirect( add_query_arg( 'pristupnica', 'nova', self::here() ) );
				exit;
			}
			wp_safe_redirect( add_query_arg( 'potvrda', $token, self::here() ) );
			exit;
		}
	}

	private static function rate_ok( string $what ): bool {
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key   = 'pac_rl_' . $what . '_' . md5( $ip . wp_salt( 'nonce' ) );
		$tries = (int) get_transient( $key );
		if ( $tries >= 5 ) {
			return false;
		}
		set_transient( $key, $tries + 1, 10 * MINUTE_IN_SECONDS );
		return true;
	}

	private static function submit() {
		$v = array();
		foreach ( array_keys( Plan_A_Clanstvo_Data::FIELDS ) as $k ) {
			$v[ $k ] = self::post( $k, 'email' === $k ? 190 : 120 );
		}
		$v['oib']     = preg_replace( '/\D/', '', $v['oib'] );
		$v['email']   = sanitize_email( $v['email'] );
		self::$values = $v;
		$e            = array();

		if ( '' !== self::post( 'web', 50 ) ) {
			$e['form'] = 'Pristupnica nije poslana.';
		}
		$need = array(
			'ime'     => 'Upiši ime.',
			'prezime' => 'Upiši prezime.',
			'adresa'  => 'Upiši adresu (ulicu i kućni broj).',
			'mjesto'  => 'Upiši mjesto i poštanski broj.',
		);
		foreach ( $need as $k => $msg ) {
			if ( '' === $v[ $k ] ) {
				$e[ $k ] = $msg;
			}
		}
		$date = Plan_A_Clanstvo_Data::parse_date( $v['datum'] );
		if ( ! $date || $date > current_time( 'Y-m-d' ) || $date < '1900-01-01' ) {
			$e['datum'] = 'Upiši datum rođenja, npr. 15.3.1990.';
		}
		if ( ! Plan_A_Clanstvo_Data::valid_oib( $v['oib'] ) ) {
			$e['oib'] = 'OIB nije ispravan. Provjeri 11 znamenki.';
		}
		if ( ! is_email( $v['email'] ) ) {
			$e['email'] = 'Upiši ispravnu e-mail adresu.';
		}
		if ( ! preg_match( '/^[0-9+()\/\-\s]{6,30}$/', $v['mobitel'] ) ) {
			$e['mobitel'] = 'Upiši broj mobitela.';
		}
		$minor = $date && Plan_A_Clanstvo_Data::age( $date ) < 18;
		if ( $minor ) {
			if ( '' === $v['roditelj'] ) {
				$e['roditelj'] = 'Za mlađe od 18 godina upiši ime i prezime roditelja ili skrbnika.';
			}
			if ( '' === $v['roditelj_kontakt'] ) {
				$e['roditelj_kontakt'] = 'Upiši mobitel ili e-mail roditelja ili skrbnika.';
			}
			if ( empty( $_POST['suglasnost'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$e['suglasnost'] = 'Potrebna je suglasnost roditelja ili skrbnika.';
			}
		} else {
			$v['roditelj']         = '';
			$v['roditelj_kontakt'] = '';
		}
		if ( empty( $_POST['izjava'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$e['izjava'] = 'Za slanje pristupnice potvrdi da prihvaćaš Izjavu člana.';
		}
		if ( ! $e && ! self::rate_ok( 'prijava' ) ) {
			$e['form'] = 'Previše pristupnica u kratkom vremenu. Pokušaj ponovno za desetak minuta.';
		}
		if ( $e ) {
			self::$errors = $e;
			return;
		}
		$v['datum'] = $date;
		$id         = Plan_A_Clanstvo_Data::save( $v );
		if ( ! $id ) {
			self::$errors = array( 'form' => 'Pristupnica nije spremljena. Pokušaj ponovno.' );
			return;
		}
		$member = Plan_A_Clanstvo_Data::get_member( $id );
		do_action( 'plan_a_clanstvo_changed', $id );
		if ( 'potvrdeno' !== $member['status'] ) {
			Plan_A_Clanstvo_Mail::confirm_request( $id );
		}
		$key = wp_generate_password( 16, false );
		set_transient(
			'pac_sent_' . $key,
			array(
				'ime'   => $v['ime'],
				'email' => $v['email'],
				'vec'   => 'potvrdeno' === $member['status'],
			),
			30 * MINUTE_IN_SECONDS
		);
		wp_safe_redirect( add_query_arg( array( 'pristupnica' => 'poslana', 'r' => $key ), self::here() ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Prikaz
	 * ------------------------------------------------------------------- */

	private static function icon( string $name ): string {
		$paths = array(
			'check' => '<path d="m5 12.5 4.5 4.5L19 7"/>',
			'mail'  => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6.5 8.5-6.5"/>',
			'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		);
		return '<svg viewBox="0 0 24 24" width="44" height="44" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}

	private static function box( string $kind, string $icon, string $title, string $body ): string {
		return '<div class="pacl-box pacl-box--' . esc_attr( $kind ) . '" role="status" tabindex="-1" data-pacl-focus>'
			. '<div class="pacl-box__icon">' . self::icon( $icon ) . '</div>'
			. '<h2 class="pacl-box__title">' . esc_html( $title ) . '</h2>'
			. $body . '</div>';
	}

	private static function mask( string $email ): string {
		$parts = explode( '@', $email );
		if ( 2 !== count( $parts ) ) {
			return '';
		}
		return mb_substr( $parts[0], 0, 2 ) . str_repeat( '•', max( 1, min( 6, mb_strlen( $parts[0] ) - 2 ) ) ) . '@' . $parts[1];
	}

	public static function render(): string {
		$post = get_post();
		if ( $post && 'publish' === $post->post_status && (int) get_option( Plan_A_Clanstvo_Data::PAGE ) !== (int) $post->ID ) {
			update_option( Plan_A_Clanstvo_Data::PAGE, (int) $post->ID, false );
		}
		wp_enqueue_style( 'plan-a-clanstvo' );
		wp_enqueue_script( 'plan-a-clanstvo' );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$token = isset( $_GET['potvrda'] ) ? sanitize_text_field( wp_unslash( $_GET['potvrda'] ) ) : '';
		$state = isset( $_GET['pristupnica'] ) ? sanitize_key( wp_unslash( $_GET['pristupnica'] ) ) : '';
		// phpcs:enable
		$html = '';
		if ( '' !== $token ) {
			$html = self::confirm_view( $token );
		} elseif ( 'poslana' === $state ) {
			$html = self::sent_view();
		} elseif ( 'nova' === $state ) {
			$html = self::box( 'info', 'mail', 'Poslali smo novu poveznicu', '<p>Otvori e-mail od Plan A i klikni <strong>Potvrđujem pristupnicu</strong>. Ako ga ne vidiš, pogledaj i mapu Neželjena pošta (Spam).</p>' );
		}
		return '<div class="pacl" data-pacl>' . ( '' !== $html ? $html : self::form() ) . '</div>';
	}

	private static function sent_view(): string {
		$key  = isset( $_GET['r'] ) ? sanitize_key( wp_unslash( $_GET['r'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$data = $key ? get_transient( 'pac_sent_' . $key ) : false;
		if ( ! is_array( $data ) ) {
			return self::box( 'info', 'mail', 'Pristupnica je poslana', '<p>Provjeri e-mail i klikni <strong>Potvrđujem pristupnicu</strong>.</p>' );
		}
		if ( ! empty( $data['vec'] ) ) {
			return self::box( 'ok', 'check', 'Već si naš član, ' . $data['ime'] . '!', '<p>Tvoja pristupnica je već potvrđena, a podatke smo osvježili. Vidimo se na izletu!</p><p><a class="pacl-btn" href="' . esc_url( home_url( '/izleti/' ) ) . '">Pogledaj izlete</a></p>' );
		}
		return self::box(
			'info',
			'mail',
			'Hvala, ' . $data['ime'] . '! Još samo jedan korak.',
			'<ol class="pacl-steps"><li>Otvori e-mail koji smo poslali na <strong>' . esc_html( self::mask( (string) $data['email'] ) ) . '</strong>.</li>'
			. '<li>Klikni narančasti gumb <strong>Potvrđujem pristupnicu</strong>.</li>'
			. '<li>Otvorit će se stranica s porukom <strong>Pristupnica je potvrđena</strong>.</li></ol>'
			. '<p class="pacl-muted">Ne vidiš e-mail? Pričekaj minutu i pogledaj mapu Neželjena pošta (Spam) ili Promocije.</p>'
		);
	}

	private static function confirm_view( string $token ): string {
		list( $id, $state ) = Plan_A_Clanstvo_Data::by_token( $token );
		$m                  = $id ? Plan_A_Clanstvo_Data::get_member( $id ) : null;
		if ( ! $m || 'nema' === $state ) {
			return self::box( 'warn', 'clock', 'Poveznica ne vrijedi', '<p>Ova poveznica više ne vrijedi ili je nepotpuna. Ako si poslao/la pristupnicu više puta, vrijedi poveznica iz najnovijeg e-maila.</p><p><a class="pacl-btn" href="' . esc_url( Plan_A_Clanstvo_Data::page_url() ) . '">Ispuni pristupnicu</a></p>' );
		}
		if ( 'potvrdeno' === $m['status'] ) {
			$fresh = ! empty( $_GET['gotovo'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$body  = '<p class="pacl-lead">' . ( $fresh ? 'Hvala! Dobrodošao/la u Plan A, ' : 'Tvoja pristupnica je već potvrđena, ' ) . '<strong>' . esc_html( $m['ime'] ) . '</strong>.</p>'
				. '<p>Poslali smo ti e-mail s potvrdom. Članarina vrijedi za kalendarsku godinu, a člansku iskaznicu preuzimaš na prvom susretu s nama.</p>'
				. Plan_A_Clanstvo_Mail::payment_html( $id, 'display:block;width:100%;max-width:420px;height:auto;margin:0 auto 14px;image-rendering:pixelated' )
				. '<p><a class="pacl-btn" href="' . esc_url( home_url( '/izleti/' ) ) . '">Pogledaj izlete</a></p>';
			return self::box( 'ok', 'check', 'Pristupnica je potvrđena!', $body );
		}
		if ( 'istekla' === $state ) {
			return self::box(
				'warn',
				'clock',
				'Poveznica je istekla',
				'<p>Poveznica vrijedi ' . (int) Plan_A_Clanstvo_Data::value( 'valid_days' ) . ' dana. Pošalji si novu jednim klikom:</p>'
				. '<form method="post" class="pacl-inline"><input type="hidden" name="pac_action" value="nova"><input type="hidden" name="token" value="' . esc_attr( $token ) . '">'
				. '<button type="submit" class="pacl-btn">Pošalji mi novu poveznicu</button></form>'
			);
		}
		// Potvrda se šalje sama (JS); bez JS-a gumb.
		return self::box(
			'info',
			'check',
			'Potvrđujem pristupnicu…',
			'<form method="post" class="pacl-inline" data-pacl-auto><input type="hidden" name="pac_action" value="potvrdi"><input type="hidden" name="token" value="' . esc_attr( $token ) . '">'
			. '<p>' . esc_html( $m['ime'] ) . ', još trenutak.</p>'
			. '<button type="submit" class="pacl-btn">Potvrđujem pristupnicu</button></form>'
		);
	}

	private static function field( string $key, string $type, array $attrs = array(), string $help = '' ): string {
		$id    = 'pacl-' . $key;
		$err   = self::$errors[ $key ] ?? '';
		$value = self::$values[ $key ] ?? '';
		$a     = '';
		foreach ( $attrs as $k => $v ) {
			$a .= ' ' . $k . '="' . esc_attr( $v ) . '"';
		}
		return '<div class="pacl-field' . ( $err ? ' has-error' : '' ) . '"><label for="' . esc_attr( $id ) . '">' . esc_html( Plan_A_Clanstvo_Data::FIELDS[ $key ] ) . '</label>'
			. '<input id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '" type="' . esc_attr( $type ) . '" value="' . esc_attr( $value ) . '"' . $a . ( $err ? ' aria-invalid="true" aria-describedby="' . esc_attr( $id ) . '-err"' : '' ) . '>'
			. ( $help ? '<small class="pacl-help">' . esc_html( $help ) . '</small>' : '' )
			. ( $err ? '<small class="pacl-err" id="' . esc_attr( $id ) . '-err">' . esc_html( $err ) . '</small>' : '' )
			. '</div>';
	}

	private static function izjava_html(): string {
		$lines = array_values( array_filter( array_map( 'trim', preg_split( '/\R/u', (string) Plan_A_Clanstvo_Data::value( 'izjava' ) ) ) ) );
		$html  = '';
		foreach ( $lines as $l ) {
			$short = mb_strlen( $l ) < 60 && ! preg_match( '/[.,:;]$/u', $l );
			$html .= $short ? '<h4>' . esc_html( $l ) . '</h4>' : '<p>' . esc_html( $l ) . '</p>';
		}
		return $html;
	}

	private static function form(): string {
		$e    = self::$errors;
		$html = '<form method="post" class="pacl-form" novalidate data-pacl-form>';
		if ( $e ) {
			$html .= '<div class="pacl-alert" role="alert" tabindex="-1" data-pacl-focus>' . esc_html( $e['form'] ?? 'Provjeri označena polja.' ) . '</div>';
		}
		$html .= '<input type="hidden" name="pac_action" value="prijava">'
			. '<p class="pacl-trap" aria-hidden="true"><label>Web <input type="text" name="web" tabindex="-1" autocomplete="off"></label></p>'
			. '<div class="pacl-grid">'
			. self::field( 'ime', 'text', array( 'autocomplete' => 'given-name', 'required' => 'required', 'maxlength' => 60 ) )
			. self::field( 'prezime', 'text', array( 'autocomplete' => 'family-name', 'required' => 'required', 'maxlength' => 60 ) )
			. self::field( 'datum', 'text', array( 'inputmode' => 'numeric', 'placeholder' => 'npr. 15.3.1990.', 'required' => 'required', 'maxlength' => 12, 'autocomplete' => 'bday', 'data-pacl-date' => '' ) )
			. self::field( 'oib', 'text', array( 'inputmode' => 'numeric', 'pattern' => '[0-9]{11}', 'required' => 'required', 'maxlength' => 11, 'data-pacl-oib' => '' ) )
			. self::field( 'adresa', 'text', array( 'autocomplete' => 'street-address', 'placeholder' => 'Ulica i kućni broj', 'required' => 'required', 'maxlength' => 100 ) )
			. self::field( 'mjesto', 'text', array( 'autocomplete' => 'address-level2', 'placeholder' => 'npr. 10000 Zagreb', 'required' => 'required', 'maxlength' => 60 ) )
			. self::field( 'email', 'email', array( 'autocomplete' => 'email', 'inputmode' => 'email', 'required' => 'required', 'maxlength' => 190 ), 'Na ovu adresu stiže e-mail za potvrdu.' )
			. self::field( 'mobitel', 'tel', array( 'autocomplete' => 'tel', 'inputmode' => 'tel', 'required' => 'required', 'maxlength' => 30 ) )
			. '</div>';

		$sug   = ! empty( $_POST['suglasnost'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$html .= '<fieldset class="pacl-minor" data-pacl-minor><legend>Za mlađe od 18 godina</legend>'
			. '<p class="pacl-muted">Za mlađe od 14 godina pristupnicu ispunjava roditelj ili skrbnik. Od 14 do 18 godina potrebna je suglasnost roditelja ili skrbnika.</p>'
			. '<div class="pacl-grid">'
			. self::field( 'roditelj', 'text', array( 'maxlength' => 100 ) )
			. self::field( 'roditelj_kontakt', 'text', array( 'maxlength' => 120, 'placeholder' => 'mobitel ili e-mail' ) )
			. '</div>'
			. '<label class="pacl-check' . ( isset( $e['suglasnost'] ) ? ' has-error' : '' ) . '"><input type="checkbox" name="suglasnost" value="1"' . checked( $sug, true, false ) . '> <span>Kao roditelj ili skrbnik dajem suglasnost za učlanjenje.</span></label>'
			. ( isset( $e['suglasnost'] ) ? '<small class="pacl-err">' . esc_html( $e['suglasnost'] ) . '</small>' : '' )
			. '</fieldset>';

		$iz    = ! empty( $_POST['izjava'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$html .= '<details class="pacl-izjava"><summary>Pročitaj Izjavu člana</summary><div class="pacl-izjava__text">' . self::izjava_html() . '</div></details>'
			. '<label class="pacl-check' . ( isset( $e['izjava'] ) ? ' has-error' : '' ) . '"><input type="checkbox" name="izjava" value="1" required' . checked( $iz, true, false ) . '> <span>Pročitao/la sam Izjavu člana i u cijelosti je prihvaćam.</span></label>'
			. ( isset( $e['izjava'] ) ? '<small class="pacl-err">' . esc_html( $e['izjava'] ) . '</small>' : '' )
			. '<button type="submit" class="pacl-btn pacl-btn--big">Pošalji pristupnicu</button>'
			. '<p class="pacl-muted pacl-after">Nakon slanja stiže e-mail s gumbom <strong>Potvrđujem pristupnicu</strong>. Pristupnica vrijedi tek kad je potvrdiš.</p>'
			. '</form>';
		return $html;
	}
}
