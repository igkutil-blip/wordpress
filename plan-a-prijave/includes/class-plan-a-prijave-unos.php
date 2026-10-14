<?php
/**
 * „Unos izleta” za kolege (vodiče): stranica s kratkim kodom [plan-a-unos-izleta], pod zajedničkom šifrom,
 * izvan Googlea i predmemorije. Skraćeni obrazac → izlet „Na čekanju” (s istim podacima ttbm_* kao obrazac u
 * adminu) i mail Igoru; Igor pregleda i objavi. Što kolege ne upisuju, dolazi iz predloška
 * („Predložak za nove izlete”, skica koja se nikad ne objavljuje).
 *
 * @package Plan_A_Prijave
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Prijave_Unos {

	const OPT       = 'pap_unos';
	const COOKIE    = 'pap_unos';
	const SHORTCODE = 'plan-a-unos-izleta';
	const TPL_META  = '_pap_template';
	const UNOS_META = '_pap_unos';

	/** Osiguranje (podaci agencije, 14.10.2026.): do 355 € cijene aranžmana 9,95 €, iznad toga 2,8 % cijene zaokruženo na cijeli euro naviše. */
	const INS_LIMIT = 355;
	const INS_PRICE = '9.95';
	const INS_RATE  = 0.028;

	/** Iznos osiguranja za cijenu aranžmana (po osobi). */
	public static function insurance( float $price ): string {
		if ( $price <= self::INS_LIMIT ) {
			return self::INS_PRICE;
		}
		return (string) (int) ceil( round( $price * self::INS_RATE, 4 ) );
	}

	/** Slike: glavna + najmanje 4 (najviše 20) za galeriju, 3:2. */
	const IMG_SLOTS = array( 'main', 'g1', 'g2', 'g3', 'g4' );
	const GAL_MIN   = 4;
	const GAL_MAX   = 20;
	const IMG_W     = 1800;
	const IMG_MIN   = 1200;

	/** Kategorija (taksonomija „Organizator” u kojoj su upisana trajanja) prema broju dana. */
	const DUR_TAX = 'ttbm_tour_org';

	/** Iz predloška u novi izlet (ono što kolege ne upisuju). */
	const FROM_TEMPLATE = array(
		'ttbm_daywise_details', 'ttbm_display_schedule', 'ttbm_why_choose_us_texts', 'ttbm_display_why_choose_us',
		'ttbm_display_get_question', 'ttbm_contact_phone', 'ttbm_contact_email', 'ttbm_contact_text',
		'ttbm_display_related',
	);

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
		add_action( 'template_redirect', array( __CLASS__, 'page_guard' ), 1 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ), 999 );
		add_filter( 'rank_math/frontend/robots', array( __CLASS__, 'robots_rm' ), 999 );
		add_filter( 'rank_math/sitemap/posts_to_exclude', array( __CLASS__, 'sitemap' ), 999 );
		add_action( 'wp_ajax_pap_unos_send', array( __CLASS__, 'send' ) );
		add_action( 'wp_ajax_nopriv_pap_unos_send', array( __CLASS__, 'send' ) );
		add_action( 'wp_ajax_pap_unos_get', array( __CLASS__, 'get' ) );
		add_action( 'wp_ajax_nopriv_pap_unos_get', array( __CLASS__, 'get' ) );
		add_action( 'save_post_page', array( __CLASS__, 'remember_page' ), 10, 2 );
		// Predložak se nikad ne objavljuje.
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'keep_template_draft' ), 30, 2 );
		add_filter( 'display_post_states', array( __CLASS__, 'states' ), 10, 2 );
		if ( is_admin() ) {
			add_action( 'admin_menu', array( __CLASS__, 'menu' ), 100 );
			add_action( 'admin_post_pap_unos_save', array( __CLASS__, 'save_settings' ) );
			add_action( 'admin_post_pap_unos_page', array( __CLASS__, 'create_page' ) );
			add_action( 'admin_post_pap_unos_template', array( __CLASS__, 'open_template' ) );
			add_action( 'admin_notices', array( __CLASS__, 'edit_notice' ) );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Postavke, šifra, stranica                                          */
	/* ------------------------------------------------------------------ */

	public static function opt( string $k, $d = '' ) {
		$o = get_option( self::OPT, array() );
		return is_array( $o ) && isset( $o[ $k ] ) && '' !== $o[ $k ] ? $o[ $k ] : $d;
	}

	private static function set_opt( array $vals ) {
		$o = get_option( self::OPT, array() );
		update_option( self::OPT, array_merge( is_array( $o ) ? $o : array(), $vals ), false );
	}

	/** Kamo ide mail o novom izletu. */
	public static function recipient(): string {
		$to = sanitize_email( (string) self::opt( 'to' ) );
		return is_email( $to ) ? $to : (string) get_option( 'admin_email' );
	}

	/** Stranice s kratkim kodom (zapamćene pri spremanju stranice). */
	public static function page_ids(): array {
		return array_values( array_filter( array_map( 'intval', (array) self::opt( 'pages', array() ) ) ) );
	}

	public static function remember_page( $id, $post ) {
		if ( wp_is_post_revision( $id ) ) {
			return;
		}
		$ids = self::page_ids();
		$has = $post && has_shortcode( (string) $post->post_content, self::SHORTCODE ) && 'trash' !== $post->post_status;
		$ids = $has ? array_values( array_unique( array_merge( $ids, array( (int) $id ) ) ) ) : array_values( array_diff( $ids, array( (int) $id ) ) );
		self::set_opt( array( 'pages' => $ids ) );
	}

	private static function is_unos_page(): bool {
		if ( ! is_singular() ) {
			return false;
		}
		$id = (int) get_queried_object_id();
		if ( in_array( $id, self::page_ids(), true ) ) {
			return true;
		}
		$post = get_post( $id );
		return $post && has_shortcode( (string) $post->post_content, self::SHORTCODE );
	}

	/** Ključ kolačića: mijenja se kad se promijeni šifra (stari ulazi prestanu vrijediti). */
	private static function token(): string {
		$hash = (string) self::opt( 'hash' );
		return '' === $hash ? '' : hash_hmac( 'sha256', 'pap-unos|' . $hash, wp_salt( 'auth' ) );
	}

	public static function allowed(): bool {
		if ( current_user_can( 'edit_others_posts' ) ) {
			return true; // Igor (administrator) uvijek
		}
		$t = self::token();
		return '' !== $t && isset( $_COOKIE[ self::COOKIE ] ) && hash_equals( $t, (string) $_COOKIE[ self::COOKIE ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	}

	private static function ip_key( string $what ): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return 'pap_unos_' . $what . '_' . md5( $ip . wp_salt( 'nonce' ) );
	}

	/** Stranica: bez predmemorije; prijava šifrom (POST → kolačić → povratak na stranicu). */
	public static function page_guard() {
		if ( ! self::is_unos_page() ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}
		$url = get_permalink( (int) get_queried_object_id() );
		if ( isset( $_POST['pap_unos_odjava'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			setcookie( self::COOKIE, '', time() - 3600, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
			wp_safe_redirect( $url );
			exit;
		}
		if ( ! isset( $_POST['pap_unos_pass'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$key   = self::ip_key( 'login' );
		$tries = (int) get_transient( $key );
		$pass  = (string) wp_unslash( $_POST['pap_unos_pass'] ); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
		$hash  = (string) self::opt( 'hash' );
		if ( $tries < 8 && '' !== $hash && '' !== $pass && wp_check_password( $pass, $hash ) ) {
			delete_transient( $key );
			setcookie( self::COOKIE, self::token(), time() + 60 * DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
			wp_safe_redirect( $url );
			exit;
		}
		set_transient( $key, $tries + 1, 15 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'krivo', $tries >= 7 ? 'blok' : '1', $url ) );
		exit;
	}

	public static function robots( $robots ) {
		if ( self::is_unos_page() ) {
			unset( $robots['index'], $robots['follow'] );
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}

	public static function robots_rm( $robots ) {
		if ( self::is_unos_page() ) {
			$robots          = is_array( $robots ) ? $robots : array();
			$robots['index']  = 'noindex';
			$robots['follow'] = 'nofollow';
		}
		return $robots;
	}

	public static function sitemap( $ids ) {
		return array_values( array_unique( array_merge( array_map( 'intval', (array) $ids ), self::page_ids() ) ) );
	}

	/* ------------------------------------------------------------------ */
	/* Predložak                                                           */
	/* ------------------------------------------------------------------ */

	/** Predložak za nove izlete (0 ako još ne postoji). */
	public static function template_id(): int {
		$id = (int) self::opt( 'template' );
		if ( $id && Plan_A_Prijave_Tour::CPT === get_post_type( $id ) && 'trash' !== get_post_status( $id ) ) {
			return $id;
		}
		return 0;
	}

	public static function is_template( int $id ): bool {
		return $id > 0 && (bool) get_post_meta( $id, self::TPL_META, true );
	}

	/** Izvor za ono što kolege ne upisuju: predložak, inače zadnji objavljeni izlet. */
	private static function source_id(): int {
		return self::template_id() ?: Plan_A_Prijave_Tour::template_tour();
	}

	public static function keep_template_draft( $data, $postarr ) {
		$id = (int) ( $postarr['ID'] ?? 0 );
		if ( $id && self::is_template( $id ) && in_array( $data['post_status'] ?? '', array( 'publish', 'future', 'pending', 'private' ), true ) ) {
			$data['post_status'] = 'draft';
		}
		return $data;
	}

	public static function states( $states, $post ) {
		if ( Plan_A_Prijave_Tour::CPT !== $post->post_type ) {
			return $states;
		}
		if ( self::is_template( (int) $post->ID ) ) {
			$states = array( 'pap_tpl' => 'PREDLOŽAK za unos kolega' );
		}
		$u = get_post_meta( $post->ID, self::UNOS_META, true );
		if ( is_array( $u ) && ! empty( $u['by_name'] ) ) {
			$states['pap_unos'] = 'unio ' . $u['by_name'];
		}
		return $states;
	}

	/** Napravi predložak (kopija zadnjeg objavljenog izleta bez termina, slika i prijava) ili otvori postojeći. */
	public static function open_template() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( 'Nemaš dozvolu.' );
		}
		check_admin_referer( 'pap_unos_template' );
		$id = self::template_id();
		if ( ! $id ) {
			$src = Plan_A_Prijave_Tour::template_tour();
			$id  = wp_insert_post(
				array(
					'post_type'    => Plan_A_Prijave_Tour::CPT,
					'post_status'  => 'draft',
					'post_title'   => 'PREDLOŽAK za nove izlete (ne objavljivati)',
					'post_content' => '',
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				wp_die( esc_html( $id->get_error_message() ) );
			}
			update_post_meta( $id, self::TPL_META, 1 );
			if ( $src ) {
				$keys = array_merge(
					Plan_A_Prijave_Tour::DISPLAY_KEYS,
					self::FROM_TEMPLATE,
					array( 'mep_event_faq', 'ttbm_extra_service_data', 'ttbm_service_included_in_price', 'ttbm_service_excluded_in_price', 'ttbm_display_include_service', 'ttbm_display_exclude_service' )
				);
				foreach ( array_unique( $keys ) as $k ) {
					if ( metadata_exists( 'post', $src, $k ) ) {
						update_post_meta( $id, $k, get_post_meta( $src, $k, true ) );
					}
				}
				// Pitanja „Važno za izlet” bez odgovora: odgovore upisuju kolege.
				$faq = array();
				foreach ( Plan_A_Prijave_Tour::rows( $src, 'mep_event_faq' ) as $f ) {
					if ( '' !== trim( (string) ( $f['ttbm_faq_title'] ?? '' ) ) ) {
						$faq[] = array( 'ttbm_faq_title' => $f['ttbm_faq_title'], 'ttbm_faq_content' => '' );
					}
				}
				update_post_meta( $id, 'mep_event_faq', $faq );
			}
			self::set_opt( array( 'template' => (int) $id ) );
		}
		wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . (int) $id ) );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Admin: postavke                                                     */
	/* ------------------------------------------------------------------ */

	public static function menu() {
		add_submenu_page( 'edit.php?post_type=' . Plan_A_Prijave_Tour::CPT, 'Unos izleta (kolege)', 'Unos izleta (kolege)', 'manage_options', 'pap-unos', array( __CLASS__, 'settings_page' ) );
	}

	public static function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Nemaš dozvolu.' );
		}
		check_admin_referer( 'pap_unos_save' );
		$vals = array( 'to' => sanitize_email( (string) wp_unslash( $_POST['to'] ?? '' ) ) );
		$pass = trim( (string) wp_unslash( $_POST['pass'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$msg  = 'spremljeno';
		if ( '' !== $pass ) {
			if ( strlen( $pass ) < 6 ) {
				$msg = 'kratka';
			} else {
				$vals['hash'] = wp_hash_password( $pass );
				$msg          = 'sifra';
			}
		}
		self::set_opt( $vals );
		wp_safe_redirect( add_query_arg( 'pap_msg', $msg, admin_url( 'edit.php?post_type=' . Plan_A_Prijave_Tour::CPT . '&page=pap-unos' ) ) );
		exit;
	}

	public static function create_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Nemaš dozvolu.' );
		}
		check_admin_referer( 'pap_unos_page' );
		$ids = self::page_ids();
		$id  = $ids ? $ids[0] : 0;
		if ( ! $id ) {
			$id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => 'Unos izleta',
					'post_name'    => 'unos-izleta',
					'post_content' => '[' . self::SHORTCODE . ']',
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				wp_die( esc_html( $id->get_error_message() ) );
			}
			self::set_opt( array( 'pages' => array( (int) $id ) ) );
		}
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . Plan_A_Prijave_Tour::CPT . '&page=pap-unos&pap_msg=stranica' ) );
		exit;
	}

	public static function settings_page() {
		$msgs = array(
			'spremljeno' => 'Spremljeno.',
			'sifra'      => 'Nova šifra je spremljena. Kolege se moraju ponovno prijaviti novom šifrom.',
			'kratka'     => 'Šifra nije promijenjena – mora imati barem 6 znakova.',
			'stranica'   => 'Stranica „Unos izleta” je spremna.',
		);
		$m    = sanitize_key( (string) ( $_GET['pap_msg'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$page = self::page_ids();
		$tpl  = self::template_id();
		$wait = get_posts(
			array(
				'post_type'      => Plan_A_Prijave_Tour::CPT,
				'post_status'    => 'pending',
				'posts_per_page' => 50,
				'meta_key'       => self::UNOS_META, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		echo '<div class="wrap"><h1>Unos izleta (kolege)</h1>';
		if ( isset( $msgs[ $m ] ) ) {
			echo '<div class="notice notice-' . ( 'kratka' === $m ? 'error' : 'success' ) . '"><p>' . esc_html( $msgs[ $m ] ) . '</p></div>';
		}
		echo '<p>Kolege na stranici „Unos izleta” upišu izlet i pošalju ga na pregled. Izlet se spremi kao <strong>Na čekanju</strong> (ne vidi se na stranici), a ti dobiješ mail. Pregledaš ga, po potrebi doradiš i klikneš <strong>Objavi</strong>. Ono što kolege ne upisuju (Važno, Zašto s nama, kontakt, osiguranje, uključeno/isključeno kao početni izbor) dolazi iz predloška.</p>';

		echo '<h2>1. Stranica</h2>';
		if ( $page ) {
			$url = get_permalink( $page[0] );
			echo '<p>Adresa za kolege: <a href="' . esc_url( $url ) . '" target="_blank"><strong>' . esc_html( $url ) . '</strong></a> – nije u Googleu ni u izbornicima, ne sprema se u predmemoriju.</p>';
		} else {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="pap_unos_page">';
			wp_nonce_field( 'pap_unos_page' );
			echo '<p>Stranica još ne postoji. <button class="button button-primary">Napravi stranicu „Unos izleta”</button> (ili na bilo koju stranicu stavi kratki kod <code>[' . esc_html( self::SHORTCODE ) . ']</code>).</p></form>';
		}

		echo '<h2>2. Šifra i mail</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="pap_unos_save">';
		wp_nonce_field( 'pap_unos_save' );
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th><label for="pap-pass">Zajednička šifra</label></th><td><input type="text" id="pap-pass" name="pass" class="regular-text" autocomplete="off" placeholder="' . ( self::opt( 'hash' ) ? 'šifra je postavljena – upiši novu za promjenu' : 'upiši šifru (barem 6 znakova)' ) . '">'
			. '<p class="description">' . ( self::opt( 'hash' ) ? '✔ Šifra je postavljena (zbog sigurnosti se ne prikazuje). Nova šifra odjavi sve koji su prijavljeni starom.' : '<strong>Šifra još nije postavljena – bez nje stranici ne može pristupiti nitko osim tebe.</strong>' ) . ' Prijava vrijedi 60 dana na tom uređaju.</p></td></tr>';
		echo '<tr><th><label for="pap-to">Mail o novom izletu ide na</label></th><td><input type="email" id="pap-to" name="to" class="regular-text" value="' . esc_attr( (string) self::opt( 'to' ) ) . '" placeholder="' . esc_attr( (string) get_option( 'admin_email' ) ) . '"><p class="description">Prazno = ' . esc_html( (string) get_option( 'admin_email' ) ) . '</p></td></tr>';
		echo '</table>';
		submit_button( 'Spremi' );
		echo '</form>';

		echo '<h2>3. Predložak za nove izlete</h2><p>Iz predloška se u svaki novi izlet kolega kopiraju: <strong>Važno</strong> (uvjeti, agencija, izjava), <strong>pitanja „Važno za izlet”</strong> (kolege upisuju odgovore, istim redoslijedom), <strong>Zašto s nama i kontakt</strong>, početne kvačice <strong>uključeno / nije uključeno</strong> i naziv usluge <strong>osiguranja</strong> (iznos se računa sam: do ' . (int) self::INS_LIMIT . ' € = 9,95 €, iznad toga 2,8 % cijene zaokruženo na cijeli euro naviše). Predložak se nikad ne objavljuje.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="pap_unos_template">';
		wp_nonce_field( 'pap_unos_template' );
		echo '<p><button class="button">' . ( $tpl ? 'Otvori predložak' : 'Napravi predložak (iz zadnjeg objavljenog izleta)' ) . '</button></p></form>';

		echo '<h2>4. Čeka pregled (' . count( $wait ) . ')</h2>';
		if ( ! $wait ) {
			echo '<p>Nema izleta na čekanju.</p>';
		} else {
			echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>Izlet</th><th>Unio</th><th>Poslano</th></tr></thead><tbody>';
			foreach ( $wait as $w ) {
				$u = (array) get_post_meta( $w->ID, self::UNOS_META, true );
				echo '<tr><td><a href="' . esc_url( (string) get_edit_post_link( $w->ID ) ) . '"><strong>' . esc_html( $w->post_title ) . '</strong></a></td><td>' . esc_html( (string) ( $u['by_name'] ?? '' ) ) . '</td><td>' . esc_html( get_the_date( 'j. n. Y. H:i', $w ) ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
	}

	/** Na ekranu izleta: tko je unio, napomena, upozorenja; na predlošku: upozorenje da se ne objavljuje. */
	public static function edit_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'post' !== $screen->base || Plan_A_Prijave_Tour::CPT !== $screen->post_type ) {
			return;
		}
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $id ) {
			return;
		}
		if ( self::is_template( $id ) ) {
			echo '<div class="notice notice-info"><p><strong>Ovo je predložak za izlete koje unose kolege</strong> – nikad se ne objavljuje (spremi ga gumbom Spremi skicu). Važno, Zašto s nama i kontakt, pitanja „Važno za izlet” (samo naslovi), uključeno/nije uključeno i naziv osiguranja kopiraju se u svaki novi izlet s „Unosa izleta”. Termini, karte, slike i opis ovdje nisu važni.</p></div>';
			return;
		}
		$u = get_post_meta( $id, self::UNOS_META, true );
		if ( ! is_array( $u ) || 'pending' !== get_post_status( $id ) ) {
			return;
		}
		$h = '<strong>Izlet je unio ' . esc_html( (string) ( $u['by_name'] ?? '' ) ) . '</strong> (' . esc_html( (string) ( $u['time'] ?? '' ) ) . ') i čeka tvoj pregled. Kad je sve u redu, klikni <strong>Objavi</strong>.';
		if ( ! empty( $u['note'] ) ) {
			$h .= '<br>Napomena: <em>' . nl2br( esc_html( (string) $u['note'] ) ) . '</em>';
		}
		foreach ( (array) ( $u['warn'] ?? array() ) as $w ) {
			$h .= '<br>⚠ ' . esc_html( (string) $w );
		}
		echo '<div class="notice notice-warning"><p>' . $h . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/* ------------------------------------------------------------------ */
	/* Stranica za kolege                                                  */
	/* ------------------------------------------------------------------ */

	private static function guides(): array {
		return get_posts(
			array(
				'post_type'      => 'ttbm_guide',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}

	private static function terms( string $tax ): array {
		if ( ! taxonomy_exists( $tax ) ) {
			return array();
		}
		$t = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'orderby' => 'name' ) );
		return is_wp_error( $t ) ? array() : $t;
	}

	/** Pitanja „Važno za izlet” iz predloška (samo naslovi, istim redoslijedom). */
	public static function questions(): array {
		$out = array();
		foreach ( Plan_A_Prijave_Tour::rows( self::source_id(), 'mep_event_faq' ) as $f ) {
			$t = trim( wp_strip_all_tags( (string) ( $f['ttbm_faq_title'] ?? '' ) ) );
			if ( '' !== $t ) {
				$out[] = $t;
			}
		}
		return $out;
	}

	private static function icon( $term ): string {
		$key  = 'ttbm_tour_activities' === $term->taxonomy ? 'ttbm_activities_icon' : 'ttbm_feature_icon';
		$icon = (string) get_term_meta( $term->term_id, $key, true );
		$icon = $icon ?: ( 'ttbm_tour_activities' === $term->taxonomy ? 'fas fa-person-hiking' : 'fas fa-check' );
		return '<i class="' . esc_attr( $icon ) . '" aria-hidden="true"></i>';
	}

	private static function assets() {
		wp_enqueue_style( 'pap-unos', PLAN_A_PRIJAVE_URL . 'assets/unos.css', array(), PLAN_A_PRIJAVE_VERSION );
		wp_enqueue_script( 'pap-unos', PLAN_A_PRIJAVE_URL . 'assets/unos.js', array(), PLAN_A_PRIJAVE_VERSION, true );
		wp_localize_script(
			'pap-unos',
			'papUnos',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'w'     => self::IMG_W,
				'min'   => self::IMG_MIN,
				'limit' => self::INS_LIMIT,
			)
		);
		if ( defined( 'TTBM_PLUGIN_URL' ) ) {
			wp_enqueue_style( 'mp_font_awesome', TTBM_PLUGIN_URL . '/assets/all.min.css', array(), '6.7.2' );
		}
		wp_enqueue_style( 'pap-icons' );
	}

	public static function shortcode(): string {
		self::assets();
		if ( ! self::allowed() ) {
			return self::login_form();
		}
		return self::form() . self::sent_list();
	}

	private static function login_form(): string {
		$err = sanitize_key( (string) ( $_GET['krivo'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$h   = '<div class="papu papu-login"><div class="papu-card"><h2>Unos izleta</h2><p>Stranica je samo za vodiče Plan A. Upiši zajedničku šifru.</p>';
		if ( '' === (string) self::opt( 'hash' ) ) {
			$h .= '<p class="papu-err">Šifra još nije postavljena (Plan A → Unos izleta (kolege)).</p>';
		} elseif ( 'blok' === $err ) {
			$h .= '<p class="papu-err">Previše pokušaja. Pokušaj ponovno za 15 minuta.</p>';
		} elseif ( $err ) {
			$h .= '<p class="papu-err">Šifra nije točna.</p>';
		}
		$h .= '<form method="post"><label>Šifra<input type="password" name="pap_unos_pass" autocomplete="current-password" required></label><button type="submit">Uđi</button></form></div></div>';
		return $h;
	}

	private static function field( string $name, string $label, string $input, string $hint = '' ): string {
		return '<div class="papu-f" data-f="' . esc_attr( $name ) . '"><label class="papu-l">' . $label . '</label>' . $input
			. ( '' !== $hint ? '<small class="papu-hint">' . $hint . '</small>' : '' ) . '<span class="papu-e" role="alert"></span></div>';
	}

	private static function rich( string $name, string $placeholder = 'Zalijepi tekst ovdje' ): string {
		return '<div class="papu-rich" contenteditable="true" data-name="' . esc_attr( $name ) . '" data-ph="' . esc_attr( $placeholder ) . '"></div>';
	}

	private static function form(): string {
		$src    = self::source_id();
		$inc    = array_map( 'strval', (array) get_post_meta( $src, 'ttbm_service_included_in_price', true ) );
		$exc    = array_map( 'strval', (array) get_post_meta( $src, 'ttbm_service_excluded_in_price', true ) );
		$guides = self::guides();

		$h  = '<div class="papu"><form class="papu-form" novalidate>';
		$h .= '<div class="papu-top"><button type="submit" class="papu-logout" form="papu-logout">Odjava</button></div>';
		$h .= '<input type="hidden" name="edit_id" value=""><div class="papu-editbar" hidden><span>Ispravljaš izlet <strong></strong> – promijeni što želiš i klikni <strong>Spremi ispravke</strong>.</span><button type="button" class="papu-edit-cancel">Odustani</button></div>';
		$h .= '<p class="papu-intro">Upiši izlet i klikni <strong>Pošalji na pregled</strong>. Izlet se ne objavljuje odmah – Igor ga pregleda i pusti na stranicu. Sva polja su obavezna osim onih gdje piše „opcija”.</p>';

		// Tko unosi.
		$opt = '<option value="">– odaberi –</option>';
		foreach ( $guides as $g ) {
			$opt .= '<option value="' . (int) $g->ID . '">' . esc_html( $g->post_title ) . '</option>';
		}
		$h .= '<section class="papu-sec papu-who">' . self::field( 'who', 'Tko unosi izlet?', '<select name="who" required>' . $opt . '</select>' ) . '</section>';

		// Opis.
		$h .= '<section class="papu-sec"><h3>Opis</h3>';
		$h .= self::field( 'title', 'Naslov izleta', '<input type="text" name="title" maxlength="120" required placeholder="npr. Hvar – Sunce, more i klinovi">' );
		$h .= self::field( 'content', 'Tekst izleta', self::rich( 'content', 'Zalijepi tekst izleta (iz Worda, maila …). Podebljano, odlomci i popisi ostaju, font i veličina se izjednače.' ) );
		$h .= '</section>';

		// Osnovni podaci.
		$loc = '<option value="">– odaberi –</option>';
		foreach ( self::terms( 'ttbm_tour_location' ) as $t ) {
			$loc .= '<option value="' . esc_attr( $t->name ) . '">' . esc_html( $t->name ) . '</option>';
		}
		$h .= '<section class="papu-sec"><h3>Osnovni podaci</h3><div class="papu-grid">';
		$h .= self::field( 'location', 'Lokacija', '<select name="location" required>' . $loc . '</select>' );
		$h .= self::field( 'country2', 'Druga zemlja <small>(nije obavezno)</small>', '<select name="country2">' . str_replace( '– odaberi –', '– nema –', $loc ) . '</select>', 'samo ako izlet ide kroz dvije zemlje' );
		$h .= self::field( 'days', 'Trajanje (broj dana)', '<input type="number" name="days" min="1" max="60" step="1" inputmode="numeric" required>' );
		$h .= '</div></section>';

		// Termin.
		$date = static fn( string $n, bool $req ) => '<span class="papu-date"><input type="text" name="' . $n . '" inputmode="numeric" placeholder="dd.mm.gggg." autocomplete="off"' . ( $req ? ' required' : '' ) . '><button type="button" class="papu-cal" aria-label="Kalendar"><i class="fas fa-calendar-days" aria-hidden="true"></i></button><input type="date" class="papu-native" tabindex="-1" aria-hidden="true"></span>';
		$h .= '<section class="papu-sec"><h3>Termin</h3><div class="papu-grid">';
		$h .= self::field( 'start', 'Početak', $date( 'start', true ) );
		$h .= self::field( 'time', 'Sat polaska', '<input type="text" name="time" inputmode="numeric" placeholder="npr. 06:30" maxlength="5" required>' );
		$h .= '</div><p class="papu-hint">Kraj izleta računa se iz trajanja (broja dana), a prijave su otvorene do početka izleta.</p></section>';

		// Cijena.
		$h .= '<section class="papu-sec"><h3>Cijena i mjesta</h3><div class="papu-grid">';
		$h .= self::field( 'price', 'Cijena po osobi (€)', '<input type="text" name="price" inputmode="decimal" placeholder="npr. 185" required>' );
		$h .= self::field( 'seats', 'Broj mjesta', '<input type="number" name="seats" min="1" max="500" step="1" inputmode="numeric" placeholder="npr. 8" required>' );
		$h .= self::field( 'rate', 'Iznos prve rate (€) <span class="papu-opt">opcija</span>', '<input type="text" name="rate" inputmode="decimal" placeholder="prazno = plaća se odjednom">', 'Ako upišeš, prijava je moguća uplatom prve rate; ostatak se plaća 30 dana prije izleta.' );
		$h .= '</div></section>';

		// Uključeno / nije uključeno.
		$features = self::terms( 'ttbm_tour_features_list' );
		$col      = static function ( string $f, array $on ) use ( $features ): string {
			$o = '';
			foreach ( $features as $t ) {
				$o .= '<label class="papu-chk"><input type="checkbox" name="' . $f . '[]" value="' . esc_attr( $t->name ) . '" data-pair="' . esc_attr( $t->name ) . '"' . checked( in_array( $t->name, $on, true ), true, false ) . '>' . self::icon( $t ) . '<span>' . esc_html( $t->name ) . '</span></label>';
			}
			return $o;
		};
		$h .= '<section class="papu-sec"><h3>Što je uključeno u cijenu</h3><div class="papu-two"><div><h4>Uključeno</h4>' . $col( 'inc', $inc ) . '</div><div><h4>Nije uključeno</h4>' . $col( 'exc', $exc ) . '</div></div></section>';

		// Aktivnosti.
		$h .= '<section class="papu-sec"><h3>Aktivnosti</h3><div class="papu-checks">';
		foreach ( self::terms( 'ttbm_tour_activities' ) as $t ) {
			$h .= '<label class="papu-chk"><input type="checkbox" name="act[]" value="' . (int) $t->term_id . '">' . self::icon( $t ) . '<span>' . esc_html( $t->name ) . '</span></label>';
		}
		$h .= '</div></section>';

		// Važno za izlet.
		$qs = self::questions();
		if ( $qs ) {
			$h .= '<section class="papu-sec"><h3>Važno za izlet</h3><p class="papu-hint">Odgovori na sva pitanja (zalijepi tekst).</p>';
			foreach ( $qs as $i => $q ) {
				$h .= self::field( 'faq' . $i, esc_html( $q ), self::rich( 'faq[' . $i . ']', 'Odgovor' ) );
			}
			$h .= '</section>';
		}

		// Vodiči.
		$h .= '<section class="papu-sec"><h3>Vodiči na izletu</h3>' . self::field( 'guides', 'Tko vodi izlet', '<div class="papu-checks">' . implode( '', array_map( static fn( $g ) => '<label class="papu-chk"><input type="checkbox" name="guides[]" value="' . (int) $g->ID . '"><span>' . esc_html( $g->post_title ) . '</span></label>', $guides ) ) . '</div>' ) . '</section>';

		// Slike.
		$h .= '<section class="papu-sec"><h3>Slike</h3><p class="papu-hint">Glavna slika i najmanje 4 slike za galeriju (gumbom „Dodaj sliku” možeš dodati još). Može bilo kakva slika – u okviru je pomakni mišem ili prstom i klizačem povećaj; spremi se točno ono što vidiš u okviru.</p><div class="papu-imgs">';
		foreach ( self::IMG_SLOTS as $i => $slot ) {
			$h .= self::slot_html( $slot, 'main' === $slot ? 'Glavna slika' : 'Slika ' . $i, false );
		}
		$h .= '</div><p class="papu-addimg-wrap"><button type="button" class="papu-addimg"><i class="fas fa-plus" aria-hidden="true"></i> Dodaj sliku</button> <small class="papu-hint">najviše ' . self::GAL_MAX . ' slika u galeriji</small></p>';
		$h .= '<template class="papu-img-tpl">' . self::slot_html( 'gX', 'Slika', true ) . '</template>';
		$h .= '</section>';

		// Napomena.
		$h .= '<section class="papu-sec">' . self::field( 'note', 'Napomena za Igora <span class="papu-opt">opcija</span>', '<textarea name="note" rows="3" maxlength="2000"></textarea>', 'Vidi je samo Igor.' ) . '</section>';

		$h .= '<input type="text" name="web" class="papu-hp" tabindex="-1" autocomplete="off" aria-hidden="true">';
		$h .= '<div class="papu-send"><button type="submit">Pošalji na pregled</button><p class="papu-status" role="status"></p></div>';
		$h .= '</form><form id="papu-logout" method="post" hidden><input type="hidden" name="pap_unos_odjava" value="1"></form></div>';
		return $h;
	}

	/** Jedan okvir za sliku (dodatni okviri imaju gumb za brisanje). */
	private static function slot_html( string $slot, string $label, bool $removable ): string {
		return '<div class="papu-img" data-slot="' . esc_attr( $slot ) . '"><div class="papu-l"><span class="papu-img-n">' . esc_html( $label ) . '</span>'
			. ( $removable ? '<button type="button" class="papu-delimg" title="Makni ovu sliku" aria-label="Makni ovu sliku">×</button>' : '' ) . '</div>'
			. '<div class="papu-frame"><img alt="" hidden><button type="button" class="papu-pick"><i class="fas fa-image" aria-hidden="true"></i><span>Odaberi sliku</span></button></div>'
			. '<div class="papu-img-tools" hidden><input type="range" class="papu-zoom" min="1" max="3" step="0.01" value="1" aria-label="Povećanje"><button type="button" class="papu-change">Zamijeni</button></div>'
			. '<input type="file" accept="image/*" class="papu-file" hidden><span class="papu-e" role="alert"></span><small class="papu-small" hidden>Slika je mala – na stranici bit će mutnija.</small></div>';
	}

	/** Nazivi okvira: glavna + g1…gN. */
	private static function slots( int $gallery ): array {
		$out = array( 'main' );
		for ( $i = 1; $i <= $gallery; $i++ ) {
			$out[] = 'g' . $i;
		}
		return $out;
	}

	/** Poslani izleti; prikazuju se samo izleti odabranog „Tko unosi”; dok čekaju pregled, mogu se ispraviti. */
	private static function sent_list(): string {
		$list = get_posts(
			array(
				'post_type'      => Plan_A_Prijave_Tour::CPT,
				'post_status'    => array( 'pending', 'publish', 'draft', 'future' ),
				'posts_per_page' => 60,
				'meta_key'       => self::UNOS_META, // phpcs:ignore WordPress.DB.SlowDBQuery
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$st = array( 'pending' => 'čeka pregled', 'publish' => 'objavljen', 'draft' => 'u doradi', 'future' => 'zakazan' );
		$h  = '<div class="papu papu-sent" hidden><h3>Tvoji poslani izleti</h3><p class="papu-sent-none" hidden>Još nisi poslao nijedan izlet.</p><ul>';
		foreach ( $list as $p ) {
			$u  = (array) get_post_meta( $p->ID, self::UNOS_META, true );
			$h .= '<li data-by="' . (int) ( $u['by'] ?? 0 ) . '"><span class="papu-st papu-st-' . esc_attr( $p->post_status ) . '">' . esc_html( $st[ $p->post_status ] ?? $p->post_status ) . '</span> '
				. ( 'publish' === $p->post_status ? '<a href="' . esc_url( (string) get_permalink( $p ) ) . '" target="_blank">' . esc_html( $p->post_title ) . '</a>' : '<span class="papu-sent-t">' . esc_html( $p->post_title ) . '</span>' )
				. ' <small>– ' . esc_html( get_the_date( 'j. n. Y.', $p ) ) . '</small>'
				. ( 'pending' === $p->post_status ? ' <button type="button" class="papu-edit" data-id="' . (int) $p->ID . '"><i class="fas fa-pen" aria-hidden="true"></i> Ispravi</button>' : '' )
				. '</li>';
		}
		return $h . '</ul></div>';
	}

	/* ------------------------------------------------------------------ */
	/* Slanje                                                              */
	/* ------------------------------------------------------------------ */

	private static function fail( string $msg, string $field = '' ) {
		wp_send_json_error( array( 'message' => $msg, 'field' => $field ) );
	}

	private static function txt( string $k, int $max = 200 ): string {
		$v = isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ $k ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
		return function_exists( 'mb_substr' ) ? mb_substr( trim( $v ), 0, $max ) : substr( trim( $v ), 0, $max );
	}

	/** Zalijepljeni tekst: samo odlomci, podebljano, kurziv, popisi, naslovi; bez fontova i boja. */
	public static function rich_clean( string $html ): string {
		$allowed = array(
			'p' => array(), 'br' => array(), 'strong' => array(), 'b' => array(), 'em' => array(), 'i' => array(),
			'ul' => array(), 'ol' => array(), 'li' => array(), 'h3' => array(), 'h4' => array(),
			'a' => array( 'href' => true ),
		);
		$html = preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', '', $html );
		$html = wp_kses( (string) $html, $allowed, array( 'http', 'https', 'mailto', 'tel' ) );
		$html = preg_replace( '#<a\b(?![^>]*\bhref="(https?:|mailto:|tel:))[^>]*>(.*?)</a>#is', '$2', (string) $html ); // poveznica bez valjane adrese → samo tekst
		$html = make_clickable( (string) $html ); // adresa napisana kao tekst (www…, https://…) → poveznica
		$html = str_replace( ' rel="nofollow"', '', (string) $html );
		$html = preg_replace( '#<(\w+)>\s*(&nbsp;|\xC2\xA0)?\s*</\1>#u', '', (string) $html ); // prazne oznake
		$html = Plan_A_Prijave_Text::clean( (string) $html );
		return trim( Plan_A_Prijave_Text::links( (string) $html ) );
	}

	private static function rich_post( $v ): string {
		return self::rich_clean( is_string( $v ) ? wp_unslash( $v ) : '' );
	}

	/** „21.11.2026.” ili „2026-11-21” → Y-m-d. */
	public static function hr_date( string $v ): string {
		$v = trim( $v );
		if ( preg_match( '/^(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})\.?$/', $v, $m ) && checkdate( (int) $m[2], (int) $m[1], (int) $m[3] ) ) {
			return sprintf( '%04d-%02d-%02d', $m[3], $m[2], $m[1] );
		}
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return $v;
		}
		return '';
	}

	/** „6:30”, „6.30”, „0630”, „6” → 06:30. */
	public static function hr_time( string $v ): string {
		$v = trim( str_replace( array( '.', ',', 'h' ), ':', $v ) );
		if ( preg_match( '/^(\d{1,2})(?::?(\d{2}))?$/', $v, $m ) ) {
			$hh = (int) $m[1];
			$mm = isset( $m[2] ) ? (int) $m[2] : 0;
			if ( $hh < 24 && $mm < 60 ) {
				return sprintf( '%02d:%02d', $hh, $mm );
			}
		}
		return '';
	}

	public static function money( string $v ): string {
		$v = str_replace( array( '€', ' ', "\xC2\xA0" ), '', $v );
		$v = str_replace( ',', '.', $v );
		return is_numeric( $v ) && (float) $v > 0 ? (string) round( (float) $v, 2 ) : '';
	}

	private static function hr_money( string $v ): string {
		return number_format( (float) $v, 2, ',', '.' ) . ' €';
	}

	public static function send() {
		nocache_headers();
		if ( ! self::allowed() ) {
			self::fail( 'Prijava je istekla. Osvježi stranicu i upiši šifru.' );
		}
		if ( '' !== self::txt( 'web', 50 ) ) {
			self::fail( 'Nije poslano.' );
		}
		$key   = self::ip_key( 'send' );
		$tries = (int) get_transient( $key );
		if ( $tries >= 20 ) {
			self::fail( 'Previše slanja u kratkom vremenu. Pokušaj ponovno za sat vremena.' );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 120 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions
		}
		// phpcs:disable WordPress.Security.NonceVerification
		$who = absint( $_POST['who'] ?? 0 );
		if ( 'ttbm_guide' !== get_post_type( $who ) ) {
			self::fail( 'Odaberi tko unosi izlet.', 'who' );
		}
		$title = self::txt( 'title', 120 );
		if ( '' === $title ) {
			self::fail( 'Upiši naslov izleta.', 'title' );
		}
		$content = self::rich_post( $_POST['content'] ?? '' );
		if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
			self::fail( 'Zalijepi tekst izleta.', 'content' );
		}
		$loc  = self::txt( 'location', 100 );
		$term = '' !== $loc ? get_term_by( 'name', $loc, 'ttbm_tour_location' ) : null;
		if ( ! $term ) {
			self::fail( 'Odaberi lokaciju.', 'location' );
		}
		$country2 = self::txt( 'country2', 100 );
		if ( '' !== $country2 && ( $country2 === $loc || ! get_term_by( 'name', $country2, 'ttbm_tour_location' ) ) ) {
			$country2 = '';
		}
		$days = absint( $_POST['days'] ?? 0 );
		if ( $days < 1 || $days > 60 ) {
			self::fail( 'Upiši trajanje (broj dana).', 'days' );
		}
		$today = current_time( 'Y-m-d' );
		$start = self::hr_date( self::txt( 'start', 20 ) );
		if ( '' === $start ) {
			self::fail( 'Upiši datum početka (npr. 21.11.2026.).', 'start' );
		}
		if ( $start < $today ) {
			self::fail( 'Početak je u prošlosti.', 'start' );
		}
		$time = self::hr_time( self::txt( 'time', 10 ) );
		if ( '' === $time ) {
			self::fail( 'Upiši sat polaska (npr. 06:30).', 'time' );
		}
		// Kraj prema broju dana (1 dan = isti dan); prijave do početka izleta.
		$end = gmdate( 'Y-m-d', strtotime( $start . ' 12:00:00 +' . ( $days - 1 ) . ' days' ) );
		$reg = $start;
		$price = self::money( self::txt( 'price', 20 ) );
		if ( '' === $price ) {
			self::fail( 'Upiši cijenu po osobi (npr. 185).', 'price' );
		}
		$seats = absint( $_POST['seats'] ?? 0 );
		if ( $seats < 1 || $seats > 500 ) {
			self::fail( 'Upiši broj mjesta.', 'seats' );
		}
		$rate_in = self::txt( 'rate', 20 );
		$rate    = '' === $rate_in ? '' : self::money( $rate_in );
		if ( '' !== $rate_in && ( '' === $rate || (float) $rate >= (float) $price ) ) {
			self::fail( 'Prva rata mora biti manja od cijene (ili ostavi prazno).', 'rate' );
		}
		$faq = array();
		foreach ( self::questions() as $i => $q ) {
			$a = self::rich_post( $_POST['faq'][ $i ] ?? '' );
			if ( '' === trim( wp_strip_all_tags( $a ) ) ) {
				self::fail( 'Upiši odgovor.', 'faq' . $i );
			}
			$faq[] = array( 'ttbm_faq_title' => $q, 'ttbm_faq_content' => $a );
		}
		$guides = array_values( array_filter( array_map( 'absint', (array) ( $_POST['guides'] ?? array() ) ), static fn( $g ) => 'ttbm_guide' === get_post_type( $g ) ) );
		if ( ! $guides ) {
			self::fail( 'Označi barem jednog vodiča.', 'guides' );
		}
		$names = wp_list_pluck( self::terms( 'ttbm_tour_features_list' ), 'name' );
		$inc   = array_values( array_intersect( array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['inc'] ?? array() ) ), $names ) );
		$exc   = array_values( array_diff( array_intersect( array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['exc'] ?? array() ) ), $names ), $inc ) );
		$act   = array_values( array_intersect( array_map( 'absint', (array) ( $_POST['act'] ?? array() ) ), wp_list_pluck( self::terms( 'ttbm_tour_activities' ), 'term_id' ) ) );
		$note  = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['note'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$small = array_map( 'sanitize_key', (array) ( $_POST['small'] ?? array() ) );
		$slots = self::slots( max( self::GAL_MIN, min( self::GAL_MAX, absint( $_POST['gcount'] ?? self::GAL_MIN ) ) ) );
		// Ispravak izleta koji još čeka pregled.
		$edit = absint( $_POST['edit_id'] ?? 0 );
		$prev = array();
		if ( $edit ) {
			if ( ! self::editable( $edit ) ) {
				self::fail( 'Ovaj izlet se više ne može ispravljati (Igor ga je već pregledao). Javi se Igoru.' );
			}
			$prev = self::images_of( $edit );
		}
		$keep = array();
		foreach ( $slots as $slot ) {
			$has = ! empty( $_FILES[ 'img_' . $slot ]['tmp_name'] );
			$k   = absint( $_POST[ 'keep_' . $slot ] ?? 0 );
			if ( ! $has && $edit && $k && in_array( $k, $prev, true ) ) {
				$keep[ $slot ] = $k; // slika ostaje kakva je
				continue;
			}
			if ( ! $has ) {
				self::fail( 'main' === $slot ? 'Dodaj glavnu sliku.' : 'Dodaj sliku (ili makni prazan okvir).', 'img_' . $slot );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification
		set_transient( $key, $tries + 1, HOUR_IN_SECONDS );

		$guide = get_post( $who );
		if ( $edit ) {
			$id = wp_update_post( array( 'ID' => $edit, 'post_title' => $title, 'post_content' => $content ), true );
		} else {
			$id = wp_insert_post(
				array(
					'post_type'    => Plan_A_Prijave_Tour::CPT,
					'post_status'  => 'pending',
					'post_title'   => $title,
					'post_content' => $content,
					'post_author'  => self::author(),
				),
				true
			);
		}
		if ( is_wp_error( $id ) ) {
			self::fail( 'Izlet nije spremljen: ' . $id->get_error_message() );
		}
		$id = (int) $id;

		// Slike (ako ne uspiju: novi izlet se briše, kod ispravka ostaje kakav je bio).
		$imgs  = array();
		$added = array();
		foreach ( $slots as $n => $slot ) {
			if ( isset( $keep[ $slot ] ) ) {
				$imgs[ $slot ] = $keep[ $slot ];
				continue;
			}
			$a = self::save_image( 'img_' . $slot, $id, $title, $n + 1 );
			if ( is_wp_error( $a ) ) {
				foreach ( $added as $old ) {
					wp_delete_attachment( $old, true );
				}
				if ( ! $edit ) {
					wp_delete_post( $id, true );
				}
				self::fail( 'Slika „' . ( 'main' === $slot ? 'glavna' : $slot ) . '” nije spremljena: ' . $a->get_error_message(), 'img_' . $slot );
			}
			$imgs[ $slot ] = (int) $a;
			$added[]       = (int) $a;
		}
		// Zamijenjene slike ne ostaju u medijima.
		foreach ( array_diff( $prev, array_values( $imgs ) ) as $old ) {
			wp_delete_attachment( (int) $old, true );
		}

		$warn  = self::store( $id, compact( 'loc', 'country2', 'term', 'days', 'start', 'time', 'end', 'reg', 'price', 'seats', 'rate', 'faq', 'guides', 'inc', 'exc', 'act', 'imgs' ) );
		$old_u = $edit ? (array) get_post_meta( $id, self::UNOS_META, true ) : array();
		$small = array_values( array_intersect( $small, $slots ) );
		foreach ( (array) ( $old_u['small'] ?? array() ) as $s ) {
			if ( isset( $keep[ $s ] ) && ! in_array( $s, $small, true ) ) {
				$small[] = $s; // zadržana mala slika ostaje mala
			}
		}
		foreach ( $slots as $i => $s ) {
			if ( in_array( $s, $small, true ) ) {
				$warn[] = ( 'main' === $s ? 'Glavna slika' : 'Slika ' . $i ) . ' je mala (manje od ' . self::IMG_MIN . ' px) – na stranici će biti mutnija.';
			}
		}
		update_post_meta(
			$id,
			self::UNOS_META,
			array(
				'by'      => $who,
				'by_name' => $guide ? $guide->post_title : '',
				'note'    => $note,
				'warn'    => $warn,
				'small'   => $small,
				'time'    => $old_u['time'] ?? current_time( 'j. n. Y. H:i' ),
				'edited'  => $edit ? current_time( 'j. n. Y. H:i' ) : '',
			)
		);
		// SEO i ostalo što se radi nakon spremanja (s već upisanim podacima).
		wp_update_post( array( 'ID' => $id ) );
		self::mail( $id, $guide ? $guide->post_title : '', $note, $warn, (bool) $edit );

		wp_send_json_success(
			array(
				'message' => $edit
					? 'Ispravke su spremljene. Izlet „' . $title . '” i dalje čeka Igorov pregled.'
					: 'Hvala! Izlet „' . $title . '” je poslan na pregled. Igor će ga pregledati i objaviti.',
			)
		);
	}

	/** Izlet s unosa koji još čeka pregled (samo takav kolege mogu ispravljati). */
	private static function editable( int $id ): bool {
		return Plan_A_Prijave_Tour::CPT === get_post_type( $id ) && 'pending' === get_post_status( $id ) && is_array( get_post_meta( $id, self::UNOS_META, true ) );
	}

	/** Slike izleta: [glavna, galerija…] (ID-evi). */
	private static function images_of( int $id ): array {
		return array_values( array_filter( array_map( 'intval', array_merge( array( get_post_thumbnail_id( $id ) ), (array) get_post_meta( $id, 'ttbm_gallery_images', true ) ) ) ) );
	}

	/** Podaci izleta za ponovno otvaranje u obrascu (ispravak). */
	public static function get() {
		nocache_headers();
		if ( ! self::allowed() ) {
			self::fail( 'Prijava je istekla. Osvježi stranicu i upiši šifru.' );
		}
		$id = absint( $_POST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! self::editable( $id ) ) {
			self::fail( 'Ovaj izlet se više ne može ispravljati (Igor ga je već pregledao).' );
		}
		$m    = static fn( $k ) => get_post_meta( $id, $k, true );
		$u    = (array) $m( self::UNOS_META );
		$tk   = Plan_A_Prijave_Tour::rows( $id, 'ttbm_ticket_type' );
		$rate = '';
		foreach ( $tk as $t ) {
			if ( Plan_A_Prijave_Seats::is_rate( (string) ( $t['ticket_type_name'] ?? '' ) ) ) {
				$rate = (string) $t['ticket_type_price'];
			}
		}
		$faq = array();
		foreach ( Plan_A_Prijave_Tour::rows( $id, 'mep_event_faq' ) as $f ) {
			$faq[ (string) ( $f['ttbm_faq_title'] ?? '' ) ] = (string) ( $f['ttbm_faq_content'] ?? '' );
		}
		$answers = array();
		foreach ( self::questions() as $i => $q ) {
			$answers[ $i ] = $faq[ $q ] ?? '';
		}
		$imgs = array();
		$ids  = array_merge( array( (int) get_post_thumbnail_id( $id ) ), array_map( 'intval', (array) $m( 'ttbm_gallery_images' ) ) );
		foreach ( self::slots( max( self::GAL_MIN, count( $ids ) - 1 ) ) as $n => $slot ) {
			$a = (int) ( $ids[ $n ] ?? 0 );
			$src = $a ? wp_get_attachment_image_src( $a, 'full' ) : false;
			if ( $src ) {
				$imgs[ $slot ] = array( 'id' => $a, 'url' => $src[0] );
			}
		}
		$start = (string) $m( 'ttbm_travel_start_date' );
		wp_send_json_success(
			array(
				'id'       => $id,
				'title'    => html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ),
				'who'      => (int) ( $u['by'] ?? 0 ),
				'content'  => (string) get_post_field( 'post_content', $id ),
				'location' => (string) $m( 'ttbm_location_name' ),
				'country2' => (string) $m( Plan_A_Prijave_Granice::META2 ),
				'days'     => (string) $m( 'ttbm_travel_duration' ),
				'start'    => $start ? gmdate( 'd.m.Y.', strtotime( $start . ' 12:00' ) ) : '',
				'time'     => (string) $m( 'ttbm_travel_start_time' ),
				'price'    => str_replace( '.', ',', (string) ( $tk[0]['ticket_type_price'] ?? '' ) ),
				'seats'    => (string) ( $tk[0]['ticket_type_qty'] ?? '' ),
				'rate'     => str_replace( '.', ',', $rate ),
				'inc'      => array_values( (array) $m( 'ttbm_service_included_in_price' ) ),
				'exc'      => array_values( (array) $m( 'ttbm_service_excluded_in_price' ) ),
				'act'      => array_values( array_map( 'strval', (array) $m( 'ttbm_tour_activities' ) ) ),
				'guides'   => array_values( array_map( 'strval', (array) $m( 'ttbm_tour_guide' ) ) ),
				'faq'      => $answers,
				'note'     => (string) ( $u['note'] ?? '' ),
				'imgs'     => $imgs,
				'small'    => array_values( (array) ( $u['small'] ?? array() ) ),
			)
		);
	}

	/** Autor izleta: korisnik s adresom primatelja maila, inače prvi administrator. */
	private static function author(): int {
		$u = get_user_by( 'email', self::recipient() );
		if ( $u ) {
			return (int) $u->ID;
		}
		$a = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
		return $a ? (int) $a[0] : 0;
	}

	/** Spremi sliku (već obrezanu u pregledniku na 3:2); za svaki slučaj obreže sredinu ako omjer nije 3:2. */
	private static function save_image( string $field, int $post_id, string $title, int $n ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$f = $_FILES[ $field ] ?? null; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
		if ( ! $f || ! empty( $f['error'] ) || empty( $f['tmp_name'] ) ) {
			return new WP_Error( 'img', 'slika nije stigla' );
		}
		if ( (int) $f['size'] > 12 * MB_IN_BYTES ) {
			return new WP_Error( 'img', 'prevelika datoteka' );
		}
		$info = @getimagesize( $f['tmp_name'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! $info || ! in_array( $info[2], array( IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP ), true ) ) {
			return new WP_Error( 'img', 'nije slika (JPG, PNG ili WebP)' );
		}
		$ext                         = array( IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp' )[ $info[2] ];
		$_FILES[ $field ]['name']    = sanitize_title( $title ) . '-' . $n . '.' . $ext;
		$_FILES[ $field ]['type']    = $info['mime'];
		$id                          = media_handle_upload( $field, $post_id, array( 'post_title' => $title . ( 1 === $n ? '' : ' – ' . $n ) ), array( 'test_form' => false ) );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		// Opis slike (alt) iz naslova izleta – inače bi se pogađao iz imena datoteke.
		update_post_meta( $id, '_wp_attachment_image_alt', 1 === $n ? $title : $title . ' – fotografija ' . ( $n - 1 ) );
		$w = (int) $info[0];
		$h = (int) $info[1];
		if ( $w > 0 && $h > 0 && abs( $w / $h - 1.5 ) > 0.02 ) {
			$file = get_attached_file( $id );
			$ed   = wp_get_image_editor( $file );
			if ( ! is_wp_error( $ed ) ) {
				$cw = min( $w, (int) round( $h * 1.5 ) );
				$ch = (int) round( $cw / 1.5 );
				$ed->crop( (int) ( ( $w - $cw ) / 2 ), (int) ( ( $h - $ch ) / 2 ), $cw, $ch, min( $cw, self::IMG_W ), (int) round( min( $cw, self::IMG_W ) / 1.5 ) );
				$ed->save( $file );
				wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
			}
		}
		return (int) $id;
	}

	/** Upiši podatke izleta (isti ključevi kao obrazac u adminu). Vraća upozorenja za Igora. */
	private static function store( int $id, array $d ): array {
		$warn = array();
		$src  = self::source_id();

		// Iz predloška (postavke prikaza, Važno, Zašto s nama, kontakt).
		foreach ( array_unique( array_merge( Plan_A_Prijave_Tour::DISPLAY_KEYS, self::FROM_TEMPLATE ) ) as $k ) {
			if ( $src && metadata_exists( 'post', $src, $k ) ) {
				update_post_meta( $id, $k, get_post_meta( $src, $k, true ) );
			}
		}

		// Osnovno.
		update_post_meta( $id, 'ttbm_location_name', $d['loc'] );
		update_post_meta( $id, Plan_A_Prijave_Granice::META2, (string) ( $d['country2'] ?? '' ) );
		update_post_meta( $id, 'ttbm_country_name', (string) get_term_meta( $d['term']->term_id, 'ttbm_country_location', true ) );
		update_post_meta( $id, 'ttbm_display_location', 'on' );
		update_post_meta( $id, 'ttbm_travel_duration', (string) $d['days'] );
		update_post_meta( $id, 'ttbm_travel_duration_type', 'day' );
		update_post_meta( $id, 'ttbm_display_duration', 'on' );
		update_post_meta( $id, 'ttbm_travel_duration_night', '' );
		update_post_meta( $id, 'ttbm_display_duration_night', 'off' );
		update_post_meta( $id, 'ttbm_travel_max_people_allow', (string) $d['seats'] );
		update_post_meta( $id, 'ttbm_display_max_people', 'on' );
		update_post_meta( $id, 'ttbm_display_seat_details', 'on' );

		// Termin.
		update_post_meta( $id, 'ttbm_travel_type', 'fixed' );
		update_post_meta( $id, 'ttbm_travel_start_date', $d['start'] );
		update_post_meta( $id, 'ttbm_travel_start_time', $d['time'] );
		update_post_meta( $id, 'ttbm_travel_end_date', $d['end'] );
		update_post_meta( $id, 'ttbm_travel_reg_end_date', $d['reg'] );
		update_post_meta( $id, 'ttbm_particular_dates', array() );

		// Karte: „Uplata” (mjesta) i po potrebi „Uplata prve rate” (dijeli ista mjesta).
		$ticket  = static fn( string $name, string $price, int $qty ) => array(
			'ticket_type_icon'        => '',
			'ticket_type_name'        => $name,
			'ticket_type_price'       => $price,
			'sale_price'              => '',
			'ticket_type_qty'         => (string) $qty,
			'ticket_type_default_qty' => '0',
			'ticket_type_resv_qty'    => '0',
			'ticket_type_qty_type'    => 'inputbox',
			'ticket_type_description' => '',
		);
		$tickets = array( $ticket( 'Uplata', $d['price'], $d['seats'] ) );
		if ( '' !== $d['rate'] ) {
			$tickets[] = $ticket( 'Uplata prve rate', $d['rate'], $d['seats'] );
		}
		update_post_meta( $id, 'ttbm_ticket_type', $tickets );

		// Osiguranje: do 355 € 9,95 €, iznad toga 2,8 % cijene zaokruženo naviše (self::insurance).
		$tpl_s   = Plan_A_Prijave_Tour::rows( $src, 'ttbm_extra_service_data' );
		$ins     = $tpl_s ? $tpl_s[0] : array( 'service_name' => 'Osiguranje od otkaza', 'extra_service_description' => 'opcionalno' );
		$service = array();
		$service[] = array(
			'service_icon'              => (string) ( $ins['service_icon'] ?? '' ),
			'service_name'              => (string) ( $ins['service_name'] ?? 'Osiguranje od otkaza' ),
			'service_price'             => self::insurance( (float) $d['price'] ),
			'service_sale_price'        => '',
			'service_qty'               => (string) Plan_A_Prijave_Editor::SERVICE_QTY,
			'service_qty_type'          => (string) ( $ins['service_qty_type'] ?? 'inputbox' ) ?: 'inputbox',
			'extra_service_description' => (string) ( $ins['extra_service_description'] ?? '' ),
		);
		update_post_meta( $id, 'ttbm_extra_service_data', $service );

		// Uključeno / nije uključeno, aktivnosti.
		update_post_meta( $id, 'ttbm_service_included_in_price', $d['inc'] );
		update_post_meta( $id, 'ttbm_service_excluded_in_price', $d['exc'] );
		update_post_meta( $id, 'ttbm_display_include_service', $d['inc'] ? 'on' : 'off' );
		update_post_meta( $id, 'ttbm_display_exclude_service', $d['exc'] ? 'on' : 'off' );
		update_post_meta( $id, 'ttbm_tour_activities', array_map( 'strval', $d['act'] ) );
		update_post_meta( $id, 'ttbm_display_activities', $d['act'] ? 'on' : 'off' );
		wp_set_object_terms( $id, array_map( 'intval', $d['act'] ), 'ttbm_tour_activities' );

		// Važno za izlet (pitanja iz predloška, odgovori kolege).
		update_post_meta( $id, 'mep_event_faq', $d['faq'] );
		update_post_meta( $id, 'ttbm_display_faq', $d['faq'] ? 'on' : 'off' );
		update_post_meta( $id, 'ttbm_faq_active', $d['faq'] ? 'on' : 'off' );

		// Vodiči, slike, povezani (automatski).
		update_post_meta( $id, 'ttbm_tour_guide', array_map( 'strval', $d['guides'] ) );
		update_post_meta( $id, 'ttbm_display_tour_guide', 'on' );
		set_post_thumbnail( $id, $d['imgs']['main'] );
		$gal = array_values( array_diff_key( $d['imgs'], array( 'main' => 1 ) ) );
		update_post_meta( $id, 'ttbm_gallery_images', $gal );
		update_post_meta( $id, 'ttbm_display_slider', 'on' );
		update_post_meta( $id, 'ttbm_related_tour', array() );

		// Kategorija prema trajanju.
		$cat  = $d['days'] >= 4 ? 'Višednevni izleti' : ( 1 === $d['days'] ? 'Izlet 1 dan' : 'Izlet ' . $d['days'] . ' dana' );
		$term = taxonomy_exists( self::DUR_TAX ) ? get_term_by( 'name', $cat, self::DUR_TAX ) : null;
		if ( $term ) {
			wp_set_object_terms( $id, array( (int) $term->term_id ), self::DUR_TAX );
		}

		Plan_A_Prijave_Tour::apply_defaults( $id );
		Plan_A_Prijave_Tour::refresh_dates( $id );
		return $warn;
	}

	private static function mail( int $id, string $by, string $note, array $warn, bool $edit = false ): bool {
		$to = self::recipient();
		if ( ! is_email( $to ) ) {
			return false;
		}
		$p     = get_post( $id );
		$tk    = Plan_A_Prijave_Tour::rows( $id, 'ttbm_ticket_type' );
		$start = (string) get_post_meta( $id, 'ttbm_travel_start_date', true );
		$end   = (string) get_post_meta( $id, 'ttbm_travel_end_date', true );
		$term  = wp_date( 'j. n. Y.', strtotime( $start . ' 12:00' ) ) . ' u ' . get_post_meta( $id, 'ttbm_travel_start_time', true ) . ( $end && $end !== $start ? ' – ' . wp_date( 'j. n. Y.', strtotime( $end . ' 12:00' ) ) : '' );
		$rows  = array(
			array( 'Izlet', '<strong>' . esc_html( $p->post_title ) . '</strong>' ),
			array( $edit ? 'Ispravio' : 'Unio', esc_html( $by ) ),
			array( 'Termin', esc_html( $term ) ),
			array( 'Lokacija', esc_html( implode( ' + ', Plan_A_Prijave_Granice::countries( $id ) ) . ', ' . get_post_meta( $id, 'ttbm_travel_duration', true ) . ' dana' ) ),
			array( 'Cijena', esc_html( self::hr_money( (string) ( $tk[0]['ticket_type_price'] ?? 0 ) ) . ( isset( $tk[1] ) ? ' (prva rata ' . self::hr_money( (string) $tk[1]['ticket_type_price'] ) . ')' : '' ) ) ),
			array( 'Mjesta', (int) ( $tk[0]['ticket_type_qty'] ?? 0 ) ),
			array( 'Osiguranje', esc_html( self::hr_money( self::insurance( (float) ( $tk[0]['ticket_type_price'] ?? 0 ) ) ) ) . ' po osobi (izračunato)' ),
		);
		$body = Plan_A_Email::card( '', Plan_A_Email::rows( $rows ) );
		if ( '' !== $note ) {
			$body .= Plan_A_Email::card( 'Napomena', Plan_A_Email::p( nl2br( esc_html( $note ) ) ) );
		}
		if ( $warn ) {
			$body .= Plan_A_Email::notice( implode( '<br>', array_map( 'esc_html', $warn ) ) );
		}
		$body .= Plan_A_Email::card(
			'',
			Plan_A_Email::button( 'Pregledaj i objavi', admin_url( 'post.php?action=edit&post=' . $id ) )
			. Plan_A_Email::small( 'Izlet je spremljen kao <strong>Na čekanju</strong> i ne vidi se na stranici dok ga ne objaviš. <a href="' . esc_url( get_preview_post_link( $id ) ) . '">Pogledaj kako izgleda</a> (dok si prijavljen).' )
		);
		$html = Plan_A_Email::render(
			array(
				'title'     => $edit ? 'Ispravljen izlet na pregled' : 'Novi izlet na pregled',
				'subtitle'  => esc_html( $p->post_title ),
				'icon'      => 'plus',
				'label'     => 'Unos izleta',
				'preheader' => $by . ( $edit ? ' je ispravio izlet ' : ' je unio izlet ' ) . $p->post_title,
			),
			$body
		);
		$from = sanitize_email( (string) get_option( 'woocommerce_email_from_address', '' ) ) ?: (string) get_option( 'admin_email' );
		$name = str_replace( array( '"', "\r", "\n" ), '', trim( wp_strip_all_tags( (string) get_option( 'woocommerce_email_from_name', '' ) ) ) ?: 'Plan A' );
		return (bool) wp_mail( $to, ( $edit ? 'Ispravljen izlet na pregled: ' : 'Novi izlet na pregled: ' ) . wp_specialchars_decode( $p->post_title, ENT_QUOTES ) . ' (' . $by . ')', $html, array( 'Content-Type: text/html; charset=UTF-8', 'From: "' . $name . '" <' . $from . '>' ) );
	}
}
