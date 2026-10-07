<?php
/**
 * Privatni dogovor s ekipom: "Napravi dogovor" stvara stranicu s nasumičnom
 * poveznicom (/dogovor/<token>/) na kojoj prijatelji označe idu li na izlet.
 *
 * - Nije vezano uz korisnički račun; radi za odjavljene posjetitelje.
 * - Trajno se sprema samo upisano ime (najviše 30 znakova) i odabir. Uz odgovor se
 *   čuva i hash nasumičnog ključa kojim isti uređaj može promijeniti svoj odgovor.
 * - IP adresa se koristi samo za ograničenje broja zahtjeva, kao hash u transientu
 *   koji istječe za 1 sat.
 * - Dogovori i odgovori brišu se 7 dana nakon termina izleta, a dogovori bez
 *   odgovora nakon 3 dana (WP Cron). Zbirna statistika po izletu (bez imena) ostaje.
 * - Stranice dogovora i REST rute šalju nocache zaglavlja i DONOTCACHEPAGE.
 *
 * @package Plan_A_Izleti
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Izleti_Dogovor {

	const DB_VERSION   = '1';
	const RULES        = '1';
	const QUERY_VAR    = 'plan_a_dogovor';
	const SLUG         = 'dogovor';
	const NS           = 'plan-a-izleti/v1';
	const NONCE        = 'plan_a_dogovor';
	const COOKIE       = 'plan_a_dogovor';
	const CRON         = 'plan_a_izleti_dogovor_cleanup';
	const TOKEN_REGEX  = '[A-Za-z0-9_-]{32}';
	const MAX_ANSWERS  = 20;
	const MAX_NAME     = 30;
	const CHOICES      = array( 'yes', 'maybe', 'no' );
	const KEEP_DAYS    = 7;  // dana nakon termina izleta
	const EMPTY_DAYS   = 3;  // dogovor bez ijednog odgovora
	const UNDATED_DAYS = 90; // dogovor za izlet bez datuma

	/** Ograničenja po hashu IP adrese, u 1 satu. */
	const LIMITS = array(
		'nonce'  => 120,
		'create' => 10,
		'answer' => 40,
		'read'   => 240,
	);

	/** Najviše novih dogovora na cijeloj stranici u 1 satu (zaštita od preplavljivanja). */
	const GLOBAL_CREATE_LIMIT = 300;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 5 );
		add_action( 'init', array( __CLASS__, 'rewrite' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'render_page' ), 1 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'rest_nocache' ), 10, 3 );
		add_action( self::CRON, array( __CLASS__, 'cleanup' ) );
		add_action( 'woocommerce_checkout_order_created', array( __CLASS__, 'mark_order' ) );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'mark_order' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Baza podataka                                                        */
	/* ------------------------------------------------------------------ */

	public static function table( string $name ): string {
		global $wpdb;
		$tables = array(
			'dogovori' => 'plan_a_dogovori',
			'odgovori' => 'plan_a_dogovor_odgovori',
			'stats'    => 'plan_a_dogovor_stats',
		);
		return $wpdb->prefix . $tables[ $name ];
	}

	public static function maybe_install() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
		if ( get_option( 'plan_a_izleti_dogovor_db' ) === self::DB_VERSION ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$collate = $wpdb->get_charset_collate();
		dbDelta(
			'CREATE TABLE ' . self::table( 'dogovori' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			token char(32) NOT NULL,
			tour_id bigint(20) unsigned NOT NULL,
			tour_date date DEFAULT NULL,
			created datetime NOT NULL,
			booked int(10) unsigned NOT NULL DEFAULT 0,
			first_booked datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token (token),
			KEY tour_id (tour_id),
			KEY tour_date (tour_date)
			) $collate;"
		);
		dbDelta(
			'CREATE TABLE ' . self::table( 'odgovori' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			dogovor_id bigint(20) unsigned NOT NULL,
			name varchar(30) NOT NULL,
			choice varchar(5) NOT NULL,
			edit_hash char(64) NOT NULL,
			created datetime NOT NULL,
			updated datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY dogovor_id (dogovor_id)
			) $collate;"
		);
		dbDelta(
			'CREATE TABLE ' . self::table( 'stats' ) . " (
			tour_id bigint(20) unsigned NOT NULL,
			created int(10) unsigned NOT NULL DEFAULT 0,
			yes_answers int(10) unsigned NOT NULL DEFAULT 0,
			booked int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (tour_id)
			) $collate;"
		);
		update_option( 'plan_a_izleti_dogovor_db', self::DB_VERSION, false );
	}

	/**
	 * Uklanjanje (uninstall.php): tablice, opcije i zakazani zadatak.
	 */
	public static function uninstall() {
		global $wpdb;
		foreach ( array( 'dogovori', 'odgovori', 'stats' ) as $name ) {
			$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table( $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- tablica dodatka.
		}
		delete_option( 'plan_a_izleti_dogovor_db' );
		delete_option( 'plan_a_izleti_dogovor_rules' );
		wp_clear_scheduled_hook( self::CRON );
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON );
		delete_option( 'plan_a_izleti_dogovor_rules' );
		flush_rewrite_rules( false );
	}

	private static function stat( int $tour_id, string $column, int $delta ) {
		global $wpdb;
		if ( ! in_array( $column, array( 'created', 'yes_answers', 'booked' ), true ) || ! $tour_id ) {
			return;
		}
		$table = self::table( 'stats' );
		if ( $delta > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tablica i stupac s popisa dopuštenih.
			$wpdb->query( $wpdb->prepare( "INSERT INTO $table (tour_id, $column) VALUES (%d, %d) ON DUPLICATE KEY UPDATE $column = $column + %d", $tour_id, $delta, $delta ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tablica i stupac s popisa dopuštenih.
			$wpdb->query( $wpdb->prepare( "UPDATE $table SET $column = IF($column > %d, $column - %d, 0) WHERE tour_id = %d", -$delta, -$delta, $tour_id ) );
		}
	}

	private static function find( string $token ) {
		global $wpdb;
		if ( ! preg_match( '/^' . self::TOKEN_REGEX . '$/', $token ) ) {
			return null;
		}
		$table = self::table( 'dogovori' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tablica dodatka.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE token = %s", $token ) );
		return $row ?: null;
	}

	private static function new_token(): string {
		return rtrim( strtr( base64_encode( random_bytes( 24 ) ), '+/', '-_' ), '=' ); // 32 znaka, 192 bita.
	}

	/* ------------------------------------------------------------------ */
	/* Adrese                                                               */
	/* ------------------------------------------------------------------ */

	public static function rewrite() {
		add_rewrite_rule( '^' . self::SLUG . '/(' . self::TOKEN_REGEX . ')/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
		if ( get_option( 'plan_a_izleti_dogovor_rules' ) !== self::RULES ) {
			flush_rewrite_rules( false );
			update_option( 'plan_a_izleti_dogovor_rules', self::RULES, true );
		}
	}

	public static function query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public static function url( string $token ): string {
		if ( get_option( 'permalink_structure' ) ) {
			return home_url( '/' . self::SLUG . '/' . $token . '/' );
		}
		return add_query_arg( self::QUERY_VAR, $token, home_url( '/' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Zaštita: ograničenje zahtjeva, honeypot, nonce                       */
	/* ------------------------------------------------------------------ */

	private static function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return (string) apply_filters( 'plan_a_izleti_client_ip', $ip );
	}

	/**
	 * Brojač po hashu IP adrese u transientu (1 sat). Sama IP adresa se ne sprema.
	 */
	private static function rate_ok( string $action ): bool {
		$max = (int) ( self::LIMITS[ $action ] ?? 60 );
		$key = 'paiz_rl_' . substr( hash_hmac( 'sha256', $action . '|' . self::client_ip(), wp_salt( 'nonce' ) ), 0, 32 );
		$hits = (int) get_transient( $key );
		if ( $hits >= $max ) {
			return false;
		}
		set_transient( $key, $hits + 1, HOUR_IN_SECONDS );
		return true;
	}

	private static function error( string $code, string $message, int $status ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	/**
	 * Zajedničke provjere za POST: honeypot, nonce (dohvaćen svježim GET /nonce) i ograničenje.
	 *
	 * @return true|WP_Error
	 */
	private static function guard( WP_REST_Request $request, string $action ) {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( '' !== trim( (string) $request->get_param( 'website' ) ) ) {
			return self::error( 'paiz_spam', __( 'Zahtjev nije prihvaćen.', 'plan-a-izleti' ), 400 );
		}
		if ( ! wp_verify_nonce( (string) $request->get_param( 'nonce' ), self::NONCE ) ) {
			return self::error( 'paiz_nonce', __( 'Sesija je istekla. Osvježi stranicu i pokušaj ponovno.', 'plan-a-izleti' ), 403 );
		}
		if ( ! self::rate_ok( $action ) ) {
			return self::error( 'paiz_rate', __( 'Previše zahtjeva. Pokušaj ponovno za sat vremena.', 'plan-a-izleti' ), 429 );
		}
		return true;
	}

	/* ------------------------------------------------------------------ */
	/* REST                                                                 */
	/* ------------------------------------------------------------------ */

	public static function routes() {
		register_rest_route(
			self::NS,
			'/nonce',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_nonce' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::NS,
			'/dogovori',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_create' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'tour_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/dogovori/(?P<token>' . self::TOKEN_REGEX . ')',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_read' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::NS,
			'/dogovori/(?P<token>' . self::TOKEN_REGEX . ')/odgovor',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_answer' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Svi odgovori ruta dodatka: bez predmemorije.
	 */
	public static function rest_nocache( $response, $server, $request ) {
		if ( $response instanceof WP_REST_Response && 0 === strpos( (string) $request->get_route(), '/' . self::NS . '/' ) ) {
			foreach ( wp_get_nocache_headers() as $name => $value ) {
				$response->header( $name, $value );
			}
			$response->header( 'X-Robots-Tag', 'noindex, nofollow' );
		}
		return $response;
	}

	public static function rest_nonce() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! self::rate_ok( 'nonce' ) ) {
			return self::error( 'paiz_rate', __( 'Previše zahtjeva. Pokušaj ponovno za sat vremena.', 'plan-a-izleti' ), 429 );
		}
		return array( 'nonce' => wp_create_nonce( self::NONCE ) );
	}

	public static function rest_create( WP_REST_Request $request ) {
		$guard = self::guard( $request, 'create' );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}
		$tour_id = absint( $request->get_param( 'tour_id' ) );
		$post    = $tour_id ? get_post( $tour_id ) : null;
		if ( ! $post || Plan_A_Izleti_Data::post_type() !== $post->post_type || 'publish' !== $post->post_status || post_password_required( $post ) ) {
			return self::error( 'paiz_tour', __( 'Izlet nije pronađen.', 'plan-a-izleti' ), 404 );
		}
		$state = Plan_A_Izleti_Data::live_state( $tour_id );
		if ( $state['ended'] ) {
			return self::error( 'paiz_ended', __( 'Ovaj izlet je završio.', 'plan-a-izleti' ), 410 );
		}

		// Zaštita od preplavljivanja s više adresa: ukupno ograničenje za cijelu stranicu.
		$global = (int) get_transient( 'paiz_rl_global_create' );
		if ( $global >= self::GLOBAL_CREATE_LIMIT ) {
			return self::error( 'paiz_rate', __( 'Previše zahtjeva. Pokušaj ponovno za sat vremena.', 'plan-a-izleti' ), 429 );
		}
		set_transient( 'paiz_rl_global_create', $global + 1, HOUR_IN_SECONDS );

		global $wpdb;
		$token = '';
		for ( $i = 0; $i < 3 && '' === $token; $i++ ) {
			$candidate = self::new_token();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$ok = $wpdb->insert(
				self::table( 'dogovori' ),
				array(
					'token'     => $candidate,
					'tour_id'   => $tour_id,
					'tour_date' => '' !== $state['next'] ? $state['next'] : null,
					'created'   => current_time( 'mysql', true ),
				),
				array( '%s', '%d', '%s', '%s' )
			);
			if ( $ok ) {
				$token = $candidate;
			}
		}
		if ( '' === $token ) {
			return self::error( 'paiz_db', __( 'Dogovor trenutno nije moguće napraviti. Pokušaj ponovno.', 'plan-a-izleti' ), 500 );
		}
		self::stat( Plan_A_Izleti_Data::source_id( $tour_id ), 'created', 1 );

		return array(
			'token' => $token,
			'url'   => self::url( $token ),
		);
	}

	public static function rest_read( WP_REST_Request $request ) {
		if ( ! self::rate_ok( 'read' ) ) {
			return self::error( 'paiz_rate', __( 'Previše zahtjeva. Pokušaj ponovno za sat vremena.', 'plan-a-izleti' ), 429 );
		}
		$row = self::find( (string) $request['token'] );
		if ( ! $row ) {
			return self::error( 'paiz_missing', __( 'Dogovor ne postoji ili je istekao.', 'plan-a-izleti' ), 404 );
		}
		return array( 'groups' => self::groups( (int) $row->id ) );
	}

	public static function rest_answer( WP_REST_Request $request ) {
		$guard = self::guard( $request, 'answer' );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}
		$row = self::find( (string) $request['token'] );
		if ( ! $row ) {
			return self::error( 'paiz_missing', __( 'Dogovor ne postoji ili je istekao.', 'plan-a-izleti' ), 404 );
		}
		if ( self::is_ended( $row ) ) {
			return self::error( 'paiz_ended', __( 'Ovaj izlet je završio.', 'plan-a-izleti' ), 410 );
		}

		$name = self::clean_name( (string) $request->get_param( 'name' ) );
		if ( '' === $name ) {
			return self::error( 'paiz_name', __( 'Upiši ime ili nadimak.', 'plan-a-izleti' ), 400 );
		}
		$choice = (string) $request->get_param( 'choice' );
		if ( ! in_array( $choice, self::CHOICES, true ) ) {
			return self::error( 'paiz_choice', __( 'Odaberi jedan od odgovora.', 'plan-a-izleti' ), 400 );
		}

		global $wpdb;
		$table   = self::table( 'odgovori' );
		$key     = (string) $request->get_param( 'key' );
		$now     = current_time( 'mysql', true );
		$tour_id = Plan_A_Izleti_Data::source_id( (int) $row->tour_id );
		$current = null;

		if ( preg_match( '/^[A-Za-z0-9]{32}$/', $key ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tablica dodatka.
			$current = $wpdb->get_row( $wpdb->prepare( "SELECT id, choice FROM $table WHERE dogovor_id = %d AND edit_hash = %s", (int) $row->id, hash( 'sha256', $key ) ) );
		}

		if ( $current ) {
			// Isti uređaj mijenja svoj odgovor.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$table,
				array(
					'name'    => $name,
					'choice'  => $choice,
					'updated' => $now,
				),
				array( 'id' => (int) $current->id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
			if ( 'yes' === $choice && 'yes' !== $current->choice ) {
				self::stat( $tour_id, 'yes_answers', 1 );
			} elseif ( 'yes' !== $choice && 'yes' === $current->choice ) {
				self::stat( $tour_id, 'yes_answers', -1 );
			}
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tablica dodatka.
			$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE dogovor_id = %d", (int) $row->id ) );
			if ( $count >= self::MAX_ANSWERS ) {
				/* translators: %d: najveći broj odgovora */
				return self::error( 'paiz_full', sprintf( __( 'Dogovor je pun (najviše %d odgovora).', 'plan-a-izleti' ), self::MAX_ANSWERS ), 409 );
			}
			$key = wp_generate_password( 32, false, false );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$table,
				array(
					'dogovor_id' => (int) $row->id,
					'name'       => $name,
					'choice'     => $choice,
					'edit_hash'  => hash( 'sha256', $key ),
					'created'    => $now,
					'updated'    => $now,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s' )
			);
			if ( 'yes' === $choice ) {
				self::stat( $tour_id, 'yes_answers', 1 );
			}
		}

		return array(
			'key'    => $key,
			'name'   => $name,
			'choice' => $choice,
			'groups' => self::groups( (int) $row->id ),
		);
	}

	private static function clean_name( string $name ): string {
		$name = sanitize_text_field( $name );
		$name = trim( preg_replace( '/\s+/u', ' ', $name ) );
		return mb_substr( $name, 0, self::MAX_NAME );
	}

	/**
	 * Odgovori grupirani po izboru (bez ključeva i datuma).
	 *
	 * @return array<string, string[]>
	 */
	private static function groups( int $dogovor_id ): array {
		global $wpdb;
		$table = self::table( 'odgovori' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tablica dodatka.
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT name, choice FROM $table WHERE dogovor_id = %d ORDER BY created ASC, id ASC", $dogovor_id ) );
		$groups = array_fill_keys( self::CHOICES, array() );
		foreach ( (array) $rows as $answer ) {
			if ( isset( $groups[ $answer->choice ] ) ) {
				$groups[ $answer->choice ][] = (string) $answer->name;
			}
		}
		return $groups;
	}

	public static function labels(): array {
		return array(
			'yes'   => __( 'Ja sam za!', 'plan-a-izleti' ),
			'maybe' => __( 'Možda', 'plan-a-izleti' ),
			'no'    => __( 'Ne mogu taj datum', 'plan-a-izleti' ),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Stranica dogovora                                                    */
	/* ------------------------------------------------------------------ */

	private static function is_ended( $row ): bool {
		$post = get_post( (int) $row->tour_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return true;
		}
		if ( ! empty( $row->tour_date ) ) {
			return $row->tour_date < current_time( 'Y-m-d' );
		}
		return Plan_A_Izleti_Data::live_state( (int) $row->tour_id )['ended'];
	}

	public static function render_page() {
		$token = (string) get_query_var( self::QUERY_VAR );
		if ( '' === $token ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		add_filter(
			'wp_robots',
			static function () {
				return array(
					'noindex'  => true,
					'nofollow' => true,
				);
			},
			999
		);

		$row  = self::find( $token );
		$post = $row ? get_post( (int) $row->tour_id ) : null;

		// Izlet još nema datum: preuzmi ga čim ga dobije (za brisanje nakon izleta).
		if ( $row && empty( $row->tour_date ) && $post ) {
			$state = Plan_A_Izleti_Data::live_state( (int) $row->tour_id );
			if ( '' !== $state['next'] ) {
				global $wpdb;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->update( self::table( 'dogovori' ), array( 'tour_date' => $state['next'] ), array( 'id' => (int) $row->id ), array( '%s' ), array( '%d' ) );
				$row->tour_date = $state['next'];
			}
		}

		$title = $row && $post
			/* translators: %s: naziv izleta */
			? sprintf( __( 'Dogovor: %s', 'plan-a-izleti' ), wp_strip_all_tags( get_the_title( $post ) ) )
			: __( 'Dogovor nije pronađen', 'plan-a-izleti' );
		add_filter(
			'pre_get_document_title',
			static function () use ( $title ) {
				return $title . ' – ' . get_bloginfo( 'name' );
			},
			999
		);
		add_filter(
			'body_class',
			static function ( $classes ) {
				$classes[] = 'plan-a-dogovor-page';
				return $classes;
			}
		);

		status_header( $row && $post ? 200 : 404 );
		wp_enqueue_style( 'plan-a-izleti' );
		$content = $row && $post ? self::page_html( $row, $post ) : self::missing_html();

		if ( $row && $post ) {
			wp_enqueue_script( 'plan-a-dogovor', PLAN_A_IZLETI_URL . 'assets/js/plan-a-dogovor.js', array(), PLAN_A_IZLETI_VERSION, array( 'in_footer' => true ) );
			wp_add_inline_script(
				'plan-a-dogovor',
				'window.planADogovor = ' . wp_json_encode(
					array(
						'rest'    => esc_url_raw( rest_url( self::NS . '/' ) ),
						'token'   => $row->token,
						'maxName' => self::MAX_NAME,
						'labels'  => self::labels(),
						'i18n'    => array(
							'nobody'  => __( 'Još nitko.', 'plan-a-izleti' ),
							'name'    => __( 'Upiši ime ili nadimak.', 'plan-a-izleti' ),
							'saving'  => __( 'Spremam…', 'plan-a-izleti' ),
							/* translators: %s: odabrani odgovor */
							'saved'   => __( 'Spremljeno: %s', 'plan-a-izleti' ),
							'error'   => __( 'Spremanje nije uspjelo. Provjeri vezu i pokušaj ponovno.', 'plan-a-izleti' ),
							'yourAns' => __( 'Tvoj odgovor', 'plan-a-izleti' ),
						),
					)
				) . ';',
				'before'
			);
		}

		self::output_document( $content );
		exit;
	}

	/**
	 * Ispis kroz zaglavlje i podnožje teme (klasična tema ili blok tema).
	 */
	private static function output_document( string $content ) {
		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			// Kao template-canvas.php: dijelovi teme renderiraju se prije wp_head(),
			// da se njihove skripte i stilovi (npr. moduli navigacije) ispišu u zaglavlju.
			ob_start();
			block_template_part( 'header' );
			$header = (string) ob_get_clean();
			ob_start();
			block_template_part( 'footer' );
			$footer = (string) ob_get_clean();
			?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( wp_get_document_title() ); ?></title>
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="wp-site-blocks">
<?php echo $header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML teme. ?>
<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
<?php echo $footer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML teme. ?>
</div>
<?php wp_footer(); ?>
</body>
</html>
			<?php
			return;
		}
		get_header();
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
		get_footer();
	}

	private static function missing_html(): string {
		return '<main class="paiz paiz-dogovor"><div class="paiz-dogovor__inner">'
			. '<h1 class="paiz-dogovor__title">' . esc_html__( 'Dogovor nije pronađen', 'plan-a-izleti' ) . '</h1>'
			. '<p>' . esc_html__( 'Dogovor ne postoji ili je istekao. Dogovori se automatski brišu nakon izleta.', 'plan-a-izleti' ) . '</p>'
			. '<p><a class="paiz-btn paiz-btn--all" href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Na naslovnicu', 'plan-a-izleti' ) . '</a></p>'
			. '</div></main>';
	}

	private static function page_html( $row, WP_Post $post ): string {
		$id        = (int) $post->ID;
		$source_id = Plan_A_Izleti_Data::source_id( $id );
		$state     = Plan_A_Izleti_Data::live_state( $id );
		$ended     = self::is_ended( $row );
		$title     = get_the_title( $id );
		$tour_url  = get_permalink( $id );
		$dogovor   = self::url( $row->token );

		// Datum: termin za koji je dogovor napravljen (ili sljedeći ako ga dotad nije bilo).
		$date = ! empty( $row->tour_date ) ? (string) $row->tour_date : $state['next'];
		if ( '' !== $date ) {
			$date_text = Plan_A_Izleti_Shortcode::format_date( $date );
			if ( $state['more'] && $date === $state['next'] ) {
				$date_text .= ' ' . __( '(i drugi termini)', 'plan-a-izleti' );
			}
		} else {
			$date_text = __( 'Termin uskoro', 'plan-a-izleti' );
		}

		$country = Plan_A_Izleti_Shortcode::get_country( $id, $source_id );
		$place   = '';
		if ( ! $country['hidden'] ) {
			$place = '' !== $country['raw']['ttbm_location_name'] ? $country['raw']['ttbm_location_name'] : $country['value'];
		}
		$price = Plan_A_Izleti_Shortcode::get_price_html( $id, $source_id );
		if ( '' !== $price ) {
			$price = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $price ), ENT_QUOTES, 'UTF-8' ) ) );
		}

		$image_id = Plan_A_Izleti_Shortcode::image_id( $id );
		$image    = $image_id ? wp_get_attachment_image(
			$image_id,
			'medium_large',
			false,
			array(
				'class' => 'paiz-dogovor__img',
				'alt'   => $title,
				'sizes' => '(max-width: 599px) 100vw, 320px',
			)
		) : '';

		$labels = self::labels();
		$groups = self::groups( (int) $row->id );

		/* translators: %1$s: naziv izleta, %2$s: poveznica na dogovor */
		$wa_text = sprintf( __( 'Idemo zajedno na %1$s? Označi se ovdje: %2$s', 'plan-a-izleti' ), wp_strip_all_tags( html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ) ), $dogovor );
		$wa_url  = 'https://wa.me/?text=' . rawurlencode( $wa_text );

		ob_start();
		?>
		<main class="paiz paiz-dogovor" data-paiz-dogovor>
			<div class="paiz-dogovor__inner">
				<p class="paiz-dogovor__notice" role="note">
					<strong><?php esc_html_e( 'Ovo nije rezervacija.', 'plan-a-izleti' ); ?></strong>
					<?php esc_html_e( 'Mjesta nisu osigurana dok se svatko ne prijavi i plati.', 'plan-a-izleti' ); ?>
				</p>

				<p class="paiz-dogovor__kicker"><?php esc_html_e( 'Dogovor za izlet', 'plan-a-izleti' ); ?></p>

				<article class="paiz-dogovor__tour">
					<div class="paiz-dogovor__media">
						<?php echo $image ? $image : '<span class="paiz-dogovor__img paiz-card__img--empty" aria-hidden="true"></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image(). ?>
					</div>
					<div class="paiz-dogovor__info">
						<h1 class="paiz-dogovor__title"><a href="<?php echo esc_url( $tour_url ); ?>"><?php echo esc_html( $title ); ?></a></h1>
						<ul class="paiz-card__meta paiz-dogovor__meta">
							<li><?php echo self::icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="paiz-sr"><?php esc_html_e( 'Datum:', 'plan-a-izleti' ); ?></span><span><?php echo esc_html( $date_text ); ?></span></li>
							<?php if ( '' !== $place ) : ?>
								<li><?php echo self::icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="paiz-sr"><?php esc_html_e( 'Mjesto:', 'plan-a-izleti' ); ?></span><span><?php echo esc_html( $place ); ?></span></li>
							<?php endif; ?>
							<?php if ( '' !== $price ) : ?>
								<li><?php echo self::icon( 'price' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="paiz-sr"><?php esc_html_e( 'Cijena:', 'plan-a-izleti' ); ?></span><span><?php echo esc_html( sprintf( /* translators: %s: cijena */ __( 'od %s', 'plan-a-izleti' ), $price ) ); ?></span></li>
							<?php endif; ?>
							<?php if ( ! $ended && null !== $state['available'] ) : ?>
								<li><?php echo self::icon( 'seat' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( sprintf( /* translators: %d: broj slobodnih mjesta */ __( 'Slobodnih mjesta: %d', 'plan-a-izleti' ), $state['available'] ) ); ?></span></li>
							<?php endif; ?>
						</ul>
					</div>
				</article>

				<?php if ( $ended ) : ?>
					<p class="paiz-dogovor__ended"><?php esc_html_e( 'Ovaj izlet je završio.', 'plan-a-izleti' ); ?></p>
				<?php else : ?>
					<section class="paiz-dogovor__box" aria-labelledby="paiz-dogovor-q">
						<h2 class="paiz-dogovor__h2" id="paiz-dogovor-q"><?php esc_html_e( 'Ideš li?', 'plan-a-izleti' ); ?></h2>
						<form class="paiz-dogovor__form" data-paiz-dogovor-form novalidate>
							<label class="paiz-dogovor__label" for="paiz-dogovor-name"><?php esc_html_e( 'Tvoje ime ili nadimak', 'plan-a-izleti' ); ?></label>
							<input class="paiz-dogovor__input" id="paiz-dogovor-name" name="name" type="text" maxlength="<?php echo esc_attr( (string) self::MAX_NAME ); ?>" autocomplete="nickname" required>
							<div class="paiz-dogovor__hp" aria-hidden="true">
								<label for="paiz-dogovor-website"><?php esc_html_e( 'Ne ispunjavaj ovo polje', 'plan-a-izleti' ); ?></label>
								<input id="paiz-dogovor-website" name="website" type="text" tabindex="-1" autocomplete="off">
							</div>
							<div class="paiz-dogovor__choices" role="group" aria-label="<?php esc_attr_e( 'Tvoj odgovor', 'plan-a-izleti' ); ?>">
								<?php foreach ( $labels as $value => $label ) : ?>
									<button type="submit" class="paiz-dogovor__choice paiz-dogovor__choice--<?php echo esc_attr( $value ); ?>" name="choice" value="<?php echo esc_attr( $value ); ?>" aria-pressed="false"><?php echo esc_html( $label ); ?></button>
								<?php endforeach; ?>
							</div>
							<p class="paiz-dogovor__status" data-paiz-dogovor-status role="status" aria-live="polite"></p>
							<noscript><p class="paiz-dogovor__status"><?php esc_html_e( 'Za označavanje je potreban JavaScript.', 'plan-a-izleti' ); ?></p></noscript>
						</form>
						<p class="paiz-dogovor__privacy"><?php esc_html_e( 'Upisano ime vide samo osobe s ovom poveznicom. Podaci se brišu automatski nakon izleta.', 'plan-a-izleti' ); ?></p>
					</section>
				<?php endif; ?>

				<section class="paiz-dogovor__answers" aria-labelledby="paiz-dogovor-a">
					<h2 class="paiz-dogovor__h2" id="paiz-dogovor-a"><?php esc_html_e( 'Tko ide', 'plan-a-izleti' ); ?></h2>
					<div class="paiz-dogovor__groups" data-paiz-dogovor-groups>
						<?php echo self::groups_html( $groups ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
					</div>
				</section>

				<?php if ( ! $ended ) : ?>
					<div class="paiz-dogovor__actions">
						<div class="paiz-share-wrap">
							<a class="paiz-share-btn" href="<?php echo esc_attr( $wa_url ); ?>" target="_blank" rel="noopener noreferrer">
								<?php echo self::icon( 'whatsapp' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<span><?php esc_html_e( 'Podijeli dogovor u WhatsApp', 'plan-a-izleti' ); ?></span>
							</a>
						</div>
						<?php if ( $state['sold_out'] ) : ?>
							<p class="paiz-dogovor__soldout"><?php esc_html_e( 'Izlet je popunjen.', 'plan-a-izleti' ); ?></p>
						<?php else : ?>
							<a class="paiz-btn paiz-btn--all paiz-dogovor__book" href="<?php echo esc_url( add_query_arg( 'dogovor', $row->token, $tour_url ) ); ?>">
								<span><?php esc_html_e( 'Rezerviraj mjesto', 'plan-a-izleti' ); ?></span><?php echo self::icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</main>
		<?php
		return (string) ob_get_clean();
	}

	private static function groups_html( array $groups ): string {
		$html = '';
		foreach ( self::labels() as $choice => $label ) {
			$names = $groups[ $choice ] ?? array();
			$html .= '<div class="paiz-dogovor__group paiz-dogovor__group--' . esc_attr( $choice ) . '" data-choice="' . esc_attr( $choice ) . '">'
				. '<h3 class="paiz-dogovor__group-title">' . esc_html( $label ) . ' <span class="paiz-dogovor__count">(' . count( $names ) . ')</span></h3>';
			if ( $names ) {
				$html .= '<ul class="paiz-dogovor__names">';
				foreach ( $names as $name ) {
					$html .= '<li>' . esc_html( $name ) . '</li>';
				}
				$html .= '</ul>';
			} else {
				$html .= '<p class="paiz-dogovor__nobody">' . esc_html__( 'Još nitko.', 'plan-a-izleti' ) . '</p>';
			}
			$html .= '</div>';
		}
		return $html;
	}

	private static function icon( string $name ): string {
		$paths = array(
			'calendar' => '<rect x="3" y="4.5" width="18" height="16" rx="2"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/>',
			'pin'      => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
			'price'    => '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="8.5" r="1.5"/>',
			'seat'     => '<circle cx="9" cy="7" r="3"/><path d="M3 20v-1a6 6 0 0 1 12 0v1M16 4a3 3 0 0 1 0 6M21 20v-1a6 6 0 0 0-4-5.6"/>',
			'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		);
		if ( 'whatsapp' === $name ) {
			return Plan_A_Izleti_Share::whatsapp_icon( 20 );
		}
		return '<svg class="paiz-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}

	/* ------------------------------------------------------------------ */
	/* Čišćenje (WP Cron, jednom dnevno)                                    */
	/* ------------------------------------------------------------------ */

	public static function cleanup() {
		global $wpdb;
		$dogovori = self::table( 'dogovori' );
		$odgovori = self::table( 'odgovori' );
		$today    = current_time( 'Y-m-d' );
		$after    = gmdate( 'Y-m-d', strtotime( $today . ' -' . self::KEEP_DAYS . ' days' ) );
		$empty    = gmdate( 'Y-m-d H:i:s', time() - self::EMPTY_DAYS * DAY_IN_SECONDS );
		$undated  = gmdate( 'Y-m-d H:i:s', time() - self::UNDATED_DAYS * DAY_IN_SECONDS );

		do {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tablice dodatka.
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT d.id FROM $dogovori d
					WHERE ( d.tour_date IS NOT NULL AND d.tour_date < %s )
					OR ( d.tour_date IS NULL AND d.created < %s )
					OR ( d.created < %s AND NOT EXISTS ( SELECT 1 FROM $odgovori o WHERE o.dogovor_id = d.id ) )
					LIMIT 500",
					$after,
					$undated,
					$empty
				)
			);
			$ids = array_map( 'intval', (array) $ids );
			if ( $ids ) {
				$in = implode( ',', $ids );
				$wpdb->query( "DELETE FROM $odgovori WHERE dogovor_id IN ($in)" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- samo cijeli brojevi.
				$wpdb->query( "DELETE FROM $dogovori WHERE id IN ($in)" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- samo cijeli brojevi.
			}
			// phpcs:enable
		} while ( count( $ids ) === 500 );
	}

	/* ------------------------------------------------------------------ */
	/* Rezervacije iz dogovora                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Gumb "Rezerviraj mjesto" vodi na izlet s ?dogovor=TOKEN; assets/js taj token
	 * sprema u kolačić `plan_a_dogovor` (7 dana). Kad se iz tog preglednika naruči
	 * isti izlet, narudžba dobiva oznaku i dogovor se broji kao "s rezervacijom".
	 *
	 * @param WC_Order|int $order
	 */
	public static function mark_order( $order ) {
		$token = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		if ( '' === $token ) {
			return;
		}
		$order = is_numeric( $order ) && function_exists( 'wc_get_order' ) ? wc_get_order( $order ) : $order;
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_items' ) || $order->get_meta( '_plan_a_dogovor' ) ) {
			return;
		}
		$row = self::find( $token );
		if ( ! $row ) {
			return;
		}
		$source = Plan_A_Izleti_Data::source_id( (int) $row->tour_id );
		$match  = false;
		foreach ( $order->get_items() as $item ) {
			$tour = (int) $item->get_meta( '_ttbm_id' );
			if ( $tour && ( $tour === (int) $row->tour_id || Plan_A_Izleti_Data::source_id( $tour ) === $source ) ) {
				$match = true;
				break;
			}
		}
		if ( ! $match ) {
			return;
		}
		$order->update_meta_data( '_plan_a_dogovor', $token );
		$order->save();

		global $wpdb;
		$table = self::table( 'dogovori' );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tablica dodatka.
		$wpdb->query( $wpdb->prepare( "UPDATE $table SET booked = booked + 1 WHERE id = %d", (int) $row->id ) );
		$first = $wpdb->query( $wpdb->prepare( "UPDATE $table SET first_booked = %s WHERE id = %d AND first_booked IS NULL", current_time( 'mysql', true ), (int) $row->id ) );
		// phpcs:enable
		if ( 1 === (int) $first ) {
			self::stat( $source, 'booked', 1 );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Podaci za administraciju (bez imena)                                 */
	/* ------------------------------------------------------------------ */

	public static function admin_rows(): array {
		global $wpdb;
		$stats    = self::table( 'stats' );
		$dogovori = self::table( 'dogovori' );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tablice dodatka, bez korisničkog unosa.
		$rows   = (array) $wpdb->get_results( "SELECT tour_id, created, yes_answers, booked FROM $stats ORDER BY created DESC", ARRAY_A );
		$active = (array) $wpdb->get_results( "SELECT tour_id, COUNT(*) AS n FROM $dogovori GROUP BY tour_id", ARRAY_A );
		// phpcs:enable
		$active_by_source = array();
		foreach ( $active as $item ) {
			$source                      = Plan_A_Izleti_Data::source_id( (int) $item['tour_id'] );
			$active_by_source[ $source ] = ( $active_by_source[ $source ] ?? 0 ) + (int) $item['n'];
		}
		foreach ( $rows as &$row ) {
			$row['active'] = $active_by_source[ (int) $row['tour_id'] ] ?? 0;
		}
		return $rows;
	}
}
