<?php
/**
 * Članovi (pristupnice), postavke i pomoćne funkcije.
 *
 * Svaka pristupnica je objava vrste pac_clan (vidljiva samo u administraciji). Podaci su u
 * meta poljima _pac_*; ime i prezime je naslov objave.
 *
 * @package Plan_A_Clanstvo
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Clanstvo_Data {

	const CPT    = 'pac_clan';
	const OPTION = 'plan_a_clanstvo';
	const CRON   = 'plan_a_clanstvo_cron';
	const PAGE   = 'plan_a_clanstvo_page';

	/** Polja pristupnice: ključ => naziv (redom kao u obrascu i tablici). */
	const FIELDS = array(
		'ime'              => 'Ime',
		'prezime'          => 'Prezime',
		'datum'            => 'Datum rođenja',
		'oib'              => 'OIB',
		'adresa'           => 'Adresa',
		'mjesto'           => 'Mjesto, poštanski broj',
		'email'            => 'E-mail',
		'mobitel'          => 'Broj mobitela',
		'roditelj'         => 'Roditelj ili skrbnik',
		'roditelj_kontakt' => 'Kontakt roditelja',
	);

	/** @var array|null */
	private static $settings = null;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( self::CRON, array( __CLASS__, 'cron' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'before_delete' ) );
		add_action(
			'wp_mail_failed',
			static function ( $error ) {
				update_option( 'plan_a_clanstvo_mail_error', is_wp_error( $error ) ? $error->get_error_message() : 'nepoznata greška', false );
			}
		);
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'erasers' ) );
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CRON );
		}
	}

	public static function register() {
		register_post_type(
			self::CPT,
			array(
				'labels'              => array(
					'name'          => 'Članovi',
					'singular_name' => 'Član',
					'menu_name'     => 'Članovi',
					'all_items'     => 'Svi članovi',
					'edit_item'     => 'Član',
					'search_items'  => 'Traži članove',
					'not_found'     => 'Nema pristupnica.',
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_icon'           => 'dashicons-id',
				'menu_position'       => 57,
				'supports'            => array( 'title' ),
				'capability_type'     => 'post',
				// Podatke (i OIB) vide samo administratori.
				'capabilities'        => array(
					'edit_posts'             => 'manage_options',
					'edit_others_posts'      => 'manage_options',
					'edit_published_posts'   => 'manage_options',
					'edit_private_posts'     => 'manage_options',
					'delete_posts'           => 'manage_options',
					'delete_others_posts'    => 'manage_options',
					'delete_published_posts' => 'manage_options',
					'delete_private_posts'   => 'manage_options',
					'publish_posts'          => 'manage_options',
					'read_private_posts'     => 'manage_options',
					'create_posts'           => 'do_not_allow',
				),
				'map_meta_cap'        => true,
				'exclude_from_search' => true,
				'show_in_rest'        => false,
				'rewrite'             => false,
				'query_var'           => false,
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Postavke
	 * ------------------------------------------------------------------- */

	public static function defaults(): array {
		$izjava = file_exists( PLAN_A_CLANSTVO_DIR . 'includes/izjava.txt' ) ? (string) file_get_contents( PLAN_A_CLANSTVO_DIR . 'includes/izjava.txt' ) : '';
		return array(
			'secret'        => '',
			'sheet_url'     => '',
			'agency_url'    => '',
			'iznos'         => 15,
			'primatelj'     => 'S.R.D. Plan A',
			'adresa'        => 'Celovečka 60b',
			'mjesto'        => '10040 Zagreb',
			'iban'          => 'HR3324840081135383203',
			'model'         => '00',
			'poziv'         => '1-{godina}',
			'opis'          => 'Članarina Plan A {godina}',
			'barcode'       => 'osobni', // osobni | slika | ne
			'barcode_slika' => 'https://srd-plan-a.hr/wp-content/uploads/2024/12/unnamed.png',
			'izjava'        => $izjava,
			'provjera'      => 0, // uključi nakon uvoza postojećih članova
			'valid_days'    => 7,
			'remind_days'   => 3,
			'from_name'     => 'Plan A',
			'naslov'        => 'Dobrodošli u zajednicu Plan A!',
			'uvod'          => 'Plan A je zajednica ljubitelja prirode, planina i aktivnog boravka na otvorenom: mreža ljudi okupljenih oko planina, a ne samo popis imena onih koji dođu na isti izlet.',
			'pogodnosti'    => "pripadnost zajednici koja se redovito okuplja i druži, dijeli iskustva i planove izvan same ture\npopust na opremu u Iglu sportu\npravovremene obavijesti o edukacijama, terminima i programima Plan A",
			'kontakt'       => 'Igor, 095 90 60 556 · info@srd-plan-a.hr',
		);
	}

	public static function get(): array {
		if ( null === self::$settings ) {
			$saved          = get_option( self::OPTION, array() );
			self::$settings = array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
			if ( '' === trim( (string) self::$settings['izjava'] ) ) {
				self::$settings['izjava'] = self::defaults()['izjava'];
			}
		}
		return self::$settings;
	}

	public static function value( string $key ) {
		$s = self::get();
		return $s[ $key ] ?? null;
	}

	public static function set( array $values ) {
		update_option( self::OPTION, $values, false );
		self::$settings = null;
	}

	public static function secret(): string {
		$s = self::get();
		if ( strlen( (string) $s['secret'] ) < 24 ) {
			$s['secret'] = wp_generate_password( 32, false );
			self::set( $s );
		}
		return (string) $s['secret'];
	}

	/** Tekst s {godina} za tekuću ili zadanu godinu. */
	public static function fill( string $text, int $year = 0 ): string {
		return str_replace( '{godina}', (string) ( $year ?: self::year() ), $text );
	}

	public static function year(): int {
		return (int) current_time( 'Y' );
	}

	public static function page_url(): string {
		$id = (int) get_option( self::PAGE );
		if ( ! $id || 'publish' !== get_post_status( $id ) || ! has_shortcode( (string) get_post_field( 'post_content', $id ), 'plan-a-pristupnica' ) ) {
			// Objavljena stranica sa shortcodeom (npr. /pristupnica/), i prije nego je itko otvori.
			global $wpdb;
			$id = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'page' AND post_content LIKE '%[plan-a-pristupnica%' ORDER BY ID DESC LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( $id ) {
				update_option( self::PAGE, $id, false );
			}
		}
		return $id ? (string) get_permalink( $id ) : home_url( '/pristupnica/' );
	}

	/* ---------------------------------------------------------------------
	 * Provjere i oblikovanje
	 * ------------------------------------------------------------------- */

	/** OIB: 11 znamenki, kontrolna znamenka po ISO 7064 (MOD 11,10). */
	public static function valid_oib( string $oib ): bool {
		if ( ! preg_match( '/^\d{11}$/', $oib ) ) {
			return false;
		}
		$a = 10;
		for ( $i = 0; $i < 10; $i++ ) {
			$a = ( $a + (int) $oib[ $i ] ) % 10;
			$a = ( 0 === $a ? 10 : $a ) * 2 % 11;
		}
		$check = 11 - $a;
		return ( 10 === $check ? 0 : $check ) === (int) $oib[10];
	}

	/** "15.3.1990." ili "1990-03-15" → "1990-03-15"; inače prazno. */
	public static function parse_date( string $value ): string {
		$value = trim( $value );
		if ( preg_match( '/^(\d{1,2})\s*[.\/-]\s*(\d{1,2})\s*[.\/-]\s*(\d{4})\s*\.?$/', $value, $m ) ) {
			$value = sprintf( '%04d-%02d-%02d', $m[3], $m[2], $m[1] );
		}
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return '';
		}
		return $value;
	}

	public static function hr_date( string $ymd ): string {
		return $ymd ? gmdate( 'j.n.Y.', strtotime( substr( $ymd, 0, 10 ) . ' 12:00:00 UTC' ) ) : '';
	}

	public static function hr_datetime( string $ymdhis ): string {
		return $ymdhis ? gmdate( 'j.n.Y. H:i', strtotime( $ymdhis . ' UTC' ) ) : '';
	}

	public static function age( string $ymd ): int {
		if ( ! $ymd ) {
			return 99;
		}
		list( $y, $m, $d ) = array_map( 'intval', explode( '-', $ymd ) );
		$today             = current_time( 'Y-m-d' );
		list( $ty, $tm, $td ) = array_map( 'intval', explode( '-', $today ) );
		return $ty - $y - ( ( $tm < $m || ( $tm === $m && $td < $d ) ) ? 1 : 0 );
	}

	public static function money( float $amount ): string {
		return number_format( $amount, 2, ',', '.' ) . ' €';
	}

	/* ---------------------------------------------------------------------
	 * Članovi
	 * ------------------------------------------------------------------- */

	public static function get_member( int $id ): ?array {
		$post = get_post( $id );
		if ( ! $post || self::CPT !== $post->post_type ) {
			return null;
		}
		$m = array( 'id' => $id );
		foreach ( array_keys( self::FIELDS ) as $k ) {
			$m[ $k ] = (string) get_post_meta( $id, '_pac_' . $k, true );
		}
		$m['broj']      = (int) get_post_meta( $id, '_pac_broj', true );
		$m['status']    = (string) get_post_meta( $id, '_pac_status', true ) ?: 'ceka';
		$m['created']   = (string) get_post_meta( $id, '_pac_created', true );
		$m['confirmed'] = (string) get_post_meta( $id, '_pac_confirmed', true );
		$m['godine']    = array_values( array_filter( array_map( 'intval', (array) get_post_meta( $id, '_pac_godine', true ) ) ) );
		$m['iskaznica'] = (bool) get_post_meta( $id, '_pac_iskaznica', true );
		$m['napomena']  = (string) get_post_meta( $id, '_pac_napomena', true );
		return $m;
	}

	/**
	 * Sljedeći broj člana (zaključano preko add_option da dvije prijave ne dobiju isti broj).
	 */
	public static function next_number(): int {
		for ( $i = 0; $i < 20; $i++ ) {
			$n = (int) get_option( 'plan_a_clanstvo_broj', 0 ) + 1;
			if ( add_option( 'plan_a_clanstvo_broj_' . $n, 1, '', false ) ) {
				update_option( 'plan_a_clanstvo_broj', $n, false );
				delete_option( 'plan_a_clanstvo_broj_' . $n );
				return $n;
			}
			usleep( 50000 );
		}
		return (int) get_option( 'plan_a_clanstvo_broj', 0 ) + 1;
	}

	/**
	 * Nova pristupnica ili ažuriranje postojeće (isti OIB ili zadani $existing). Vraća ID.
	 */
	public static function save( array $data, string $status = 'ceka', string $created = '', int $existing = 0 ): int {
		$existing = $existing && self::CPT === get_post_type( $existing ) ? $existing : self::find_same( $data );
		$title    = trim( $data['ime'] . ' ' . $data['prezime'] );
		if ( $existing ) {
			$id = $existing;
			wp_update_post(
				array(
					'ID'         => $id,
					'post_title' => $title,
				)
			);
		} else {
			$id = (int) wp_insert_post(
				array(
					'post_type'   => self::CPT,
					'post_status' => 'publish',
					'post_title'  => $title,
				)
			);
			if ( ! $id ) {
				return 0;
			}
			update_post_meta( $id, '_pac_broj', self::next_number() );
			update_post_meta( $id, '_pac_created', $created ?: current_time( 'mysql' ) );
		}
		foreach ( array_keys( self::FIELDS ) as $k ) {
			if ( isset( $data[ $k ] ) ) {
				update_post_meta( $id, '_pac_' . $k, 'email' === $k ? strtolower( $data[ $k ] ) : $data[ $k ] );
			}
		}
		// Već potvrđeni član ostaje potvrđen i kad ponovno pošalje pristupnicu.
		if ( 'potvrdeno' !== get_post_meta( $id, '_pac_status', true ) || 'potvrdeno' === $status ) {
			update_post_meta( $id, '_pac_status', $status );
		}
		return $id;
	}

	/**
	 * Brojevi članova redom po datumu prijave (isti datum: dosadašnji redoslijed).
	 * Tablica se prenumerira prva; ako to ne uspije, na stranici se ništa ne mijenja.
	 *
	 * @return array{ok: bool, n: int, error?: string}
	 */
	public static function renumber(): array {
		$list = array();
		foreach ( get_posts( array( 'post_type' => self::CPT, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) ) as $id ) {
			$list[] = array(
				'id'      => (int) $id,
				'created' => (string) get_post_meta( $id, '_pac_created', true ),
				'broj'    => (int) get_post_meta( $id, '_pac_broj', true ),
			);
		}
		usort(
			$list,
			static function ( $a, $b ) {
				return array( $a['created'], $a['broj'], $a['id'] ) <=> array( $b['created'], $b['broj'], $b['id'] );
			}
		);
		$map = array();
		foreach ( $list as $i => $m ) {
			if ( $m['broj'] !== $i + 1 ) {
				$map[ (string) $m['broj'] ] = $i + 1;
			}
		}
		if ( $map && '' !== Plan_A_Clanstvo_Sheets::url() ) {
			$res = Plan_A_Clanstvo_Sheets::post(
				array(
					'action' => 'renumber',
					'map'    => (object) $map,
				),
				90
			);
			if ( ! $res['ok'] ) {
				return array(
					'ok'    => false,
					'n'     => 0,
					'error' => 'Nepoznata radnja.' === ( $res['error'] ?? '' ) ? 'u tablici je stara skripta. Kopiraj novu skriptu i objavi je kao „New version”, pa pokušaj ponovno.' : (string) ( $res['error'] ?? '' ),
				);
			}
		}
		foreach ( $list as $i => $m ) {
			if ( $m['broj'] !== $i + 1 ) {
				update_post_meta( $m['id'], '_pac_broj', $i + 1 );
			}
		}
		update_option( 'plan_a_clanstvo_broj', count( $list ), false );
		delete_option( 'plan_a_clanstvo_delete_queue' ); // stari brojevi više ne vrijede
		return array(
			'ok' => true,
			'n'  => count( $map ),
		);
	}

	/**
	 * Ista osoba: isti OIB, ili (ako je OIB krivo upisan) isti e-mail, ime i datum rođenja.
	 * Roditelj s jednim e-mailom za više djece ostaje više članova (različito ime ili datum).
	 */
	public static function find_same( array $data ): int {
		if ( ! empty( $data['oib'] ) ) {
			$id = self::find( 'oib', (string) $data['oib'] );
			if ( $id ) {
				return $id;
			}
		}
		if ( empty( $data['email'] ) || empty( $data['datum'] ) || empty( $data['ime'] ) ) {
			return 0;
		}
		$ids = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'any',
				'posts_per_page' => 20,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'   => '_pac_email',
						'value' => strtolower( trim( (string) $data['email'] ) ),
					),
					array(
						'key'   => '_pac_datum',
						'value' => (string) $data['datum'],
					),
				),
			)
		);
		$norm = static function ( $t ) {
			$t = remove_accents( function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $t ) ) : strtolower( trim( (string) $t ) ) );
			return preg_replace( '/[^a-z]/', '', $t );
		};
		foreach ( $ids as $id ) {
			if ( $norm( get_post_meta( $id, '_pac_ime', true ) ) === $norm( $data['ime'] ) ) {
				return (int) $id;
			}
		}
		return 0;
	}

	public static function find( string $field, string $value ): int {
		$value = 'email' === $field ? strtolower( trim( $value ) ) : trim( $value );
		if ( '' === $value ) {
			return 0;
		}
		$ids = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => '_pac_' . $field, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => $value, // phpcs:ignore WordPress.DB.SlowDBQuery
				'orderby'        => 'ID',
				'order'          => 'DESC',
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	/** Je li e-mail potvrđenog člana. */
	/** Je li članarina za godinu označena u tablici (prema zadnjem čitanju tablice). */
	public static function fee_paid( int $id, int $year ): bool {
		return in_array( $year, array_map( 'intval', (array) get_post_meta( $id, '_pac_placeno', true ) ), true );
	}

	public static function is_member( string $email ): bool {
		$email = strtolower( trim( $email ) );
		if ( ! is_email( $email ) ) {
			return false;
		}
		$ids = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'   => '_pac_email',
						'value' => $email,
					),
					array(
						'key'   => '_pac_status',
						'value' => 'potvrdeno',
					),
				),
			)
		);
		return (bool) $ids;
	}

	/* ---------------------------------------------------------------------
	 * Poveznica za potvrdu
	 * ------------------------------------------------------------------- */

	/** Nova poveznica (stara prestaje vrijediti). */
	public static function new_token( int $id ): string {
		$token = bin2hex( random_bytes( 20 ) );
		update_post_meta( $id, '_pac_token', hash( 'sha256', $token ) );
		update_post_meta( $id, '_pac_token_time', time() );
		return $token;
	}

	public static function confirm_url( string $token ): string {
		return add_query_arg( 'potvrda', rawurlencode( $token ), self::page_url() );
	}

	/**
	 * Član po poveznici: [ id, stanje ] gdje je stanje ok | istekla | nema.
	 */
	public static function by_token( string $token ): array {
		if ( ! preg_match( '/^[a-f0-9]{40}$/', $token ) ) {
			return array( 0, 'nema' );
		}
		$id = self::find( 'token', hash( 'sha256', $token ) );
		if ( ! $id ) {
			return array( 0, 'nema' );
		}
		$age = time() - (int) get_post_meta( $id, '_pac_token_time', true );
		if ( 'potvrdeno' !== get_post_meta( $id, '_pac_status', true ) && $age > DAY_IN_SECONDS * max( 1, (int) self::value( 'valid_days' ) ) ) {
			return array( $id, 'istekla' );
		}
		return array( $id, 'ok' );
	}

	/** Potvrda; true ako je upravo potvrđena (false ako je već bila). */
	public static function confirm( int $id ): bool {
		if ( 'potvrdeno' === get_post_meta( $id, '_pac_status', true ) ) {
			return false;
		}
		update_post_meta( $id, '_pac_status', 'potvrdeno' );
		update_post_meta( $id, '_pac_confirmed', current_time( 'mysql' ) );
		do_action( 'plan_a_clanstvo_changed', $id );
		return true;
	}

	/* ---------------------------------------------------------------------
	 * Cron: podsjetnik i brisanje nepotvrđenih
	 * ------------------------------------------------------------------- */

	public static function cron() {
		$s   = self::get();
		$ids = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'any',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => '_pac_status', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => 'ceka', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		foreach ( $ids as $id ) {
			// Rokovi teku od e-maila za potvrdu; tko ga još nije dobio (npr. uvezeni), ostaje netaknut.
			$sent = (int) get_post_meta( $id, '_pac_requested', true );
			if ( ! $sent ) {
				continue;
			}
			$age = time() - $sent;
			// Nepotvrđeni se nikad ne brišu; dobiju samo jedan podsjetnik (0 = bez podsjetnika).
			if ( (int) $s['remind_days'] > 0 && $age > DAY_IN_SECONDS * (int) $s['remind_days'] && ! get_post_meta( $id, '_pac_reminded', true ) ) {
				update_post_meta( $id, '_pac_reminded', 1 );
				Plan_A_Clanstvo_Mail::confirm_request( (int) $id, true );
			}
		}
		Plan_A_Clanstvo_Sheets::retry();
	}

	public static function delete( int $id ) {
		wp_delete_post( $id, true ); // slika koda i red u tablici: before_delete()
	}

	public static function before_delete( $id ) {
		if ( self::CPT !== get_post_type( $id ) ) {
			return;
		}
		Plan_A_Clanstvo_Hub3::delete( (int) $id );
		$broj = (int) get_post_meta( $id, '_pac_broj', true );
		if ( $broj ) {
			Plan_A_Clanstvo_Sheets::send_delete( $broj );
		}
	}

	/* ---------------------------------------------------------------------
	 * GDPR: izvoz i brisanje po e-mailu
	 * ------------------------------------------------------------------- */

	public static function exporters( $exporters ) {
		$exporters['plan-a-clanstvo'] = array(
			'exporter_friendly_name' => 'Plan A članstvo',
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	public static function erasers( $erasers ) {
		$erasers['plan-a-clanstvo'] = array(
			'eraser_friendly_name' => 'Plan A članstvo',
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	public static function export( $email ) {
		$id   = self::find( 'email', (string) $email );
		$data = array();
		if ( $id ) {
			$m   = self::get_member( $id );
			$out = array();
			foreach ( self::FIELDS as $k => $label ) {
				if ( '' !== $m[ $k ] ) {
					$out[] = array(
						'name'  => $label,
						'value' => 'datum' === $k ? self::hr_date( $m[ $k ] ) : $m[ $k ],
					);
				}
			}
			$out[] = array(
				'name'  => 'Datum prijave',
				'value' => self::hr_datetime( $m['created'] ),
			);
			$out[] = array(
				'name'  => 'Potvrđeno',
				'value' => $m['confirmed'] ? self::hr_datetime( $m['confirmed'] ) : 'ne',
			);
			$data[] = array(
				'group_id'    => 'plan-a-clanstvo',
				'group_label' => 'Pristupnica Plan A',
				'item_id'     => 'pac-' . $id,
				'data'        => $out,
			);
		}
		return array(
			'data' => $data,
			'done' => true,
		);
	}

	public static function erase( $email ) {
		$removed = false;
		while ( $id = self::find( 'email', (string) $email ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
			self::delete( $id );
			$removed = true;
		}
		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
