<?php
/**
 * Obrazac za uređivanje izleta na hrvatskom. Sprema iste podatke kao WpTravelly, pa
 * WpTravellyjeva stranica izleta, prijava i naši dodaci rade bez promjena. Dok je
 * WpTravelly uključen, njegov obrazac se skriva (bez njegovog obrasca on ništa ne sprema).
 *
 * @package Plan_A_Prijave
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Prijave_Editor {

	const NONCE  = 'pap_nonce';
	const NOTICE = 'pap_editor_notice_';

	/** Količina usluge: praktički neograničeno. */
	const SERVICE_QTY = 9999;

	/** Ikonica pojma (isti podatak kao WpTravelly) i zadana ikonica. */
	const ICON_META    = array(
		'ttbm_tour_activities'    => 'ttbm_activities_icon',
		'ttbm_tour_features_list' => 'ttbm_feature_icon',
	);
	const ICON_DEFAULT = array(
		'ttbm_tour_activities'    => 'fas fa-person-hiking',
		'ttbm_tour_features_list' => 'fas fa-check',
	);

	public static function init() {
		add_action( 'init', array( __CLASS__, 'supports' ), 99 );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ), 999 );
		add_filter( 'get_user_option_screen_layout_' . Plan_A_Prijave_Tour::CPT, static fn() => 2, 999 ); // dva stupca
		add_action( 'add_meta_boxes_' . Plan_A_Prijave_Tour::CPT, array( __CLASS__, 'boxes' ), 100 );
		add_action( 'save_post_' . Plan_A_Prijave_Tour::CPT, array( __CLASS__, 'save' ), 20, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'no_ttbm_autosave' ), 100 );
		add_action( 'wp_ajax_ttbm_autosave_tour', array( __CLASS__, 'block_ttbm_autosave' ), 1 );
		add_action( 'admin_init', array( __CLASS__, 'default_icons' ) );
		add_action( 'init', array( __CLASS__, 'icons_style' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'icons_front' ) );
		add_action( 'wp_ajax_pap_add_term', array( __CLASS__, 'ajax_add_term' ) );
		add_action( 'wp_ajax_pap_term_icon', array( __CLASS__, 'ajax_term_icon' ) );
		add_action( 'wp_ajax_pap_save_guide', array( __CLASS__, 'ajax_save_guide' ) );
	}

	/**
	 * WpTravellyjevo spremanje u pozadini šalje samo njegov obrazac. Bez njega bi obrisalo
	 * karte, termine i ostalo, pa se na ovom zaslonu isključuje.
	 */
	public static function no_ttbm_autosave() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && Plan_A_Prijave_Tour::CPT === $screen->post_type ) {
			// Skripte WpTravellyjevog obrasca: bez njega bi zaustavile spremanje ili preimenovale naslov.
			foreach ( array( 'ttbm-autosave', 'ttbm_admin_settings', 'ttbm_admin_script' ) as $h ) {
				wp_dequeue_script( $h );
			}
			wp_dequeue_style( 'ttbm-autosave' );
		}
	}

	public static function block_ttbm_autosave() {
		$id = isset( $_POST['post_ID'] ) ? absint( $_POST['post_ID'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $id || Plan_A_Prijave_Tour::CPT === get_post_type( $id ) ) {
			wp_send_json_error( array( 'message' => 'Izlet se sprema gumbom Spremi (obrazac Plan A).' ) );
		}
	}

	/** WpTravelly makne naslov i opis (ima ih u svom obrascu); vraćamo WordPressove. */
	public static function supports() {
		if ( is_admin() ) {
			add_post_type_support( Plan_A_Prijave_Tour::CPT, array( 'title', 'editor', 'thumbnail', 'excerpt' ) );
		}
	}

	/** Bez WpTravellyjevog izgleda ekrana (skriva desni stupac): običan WordPressov ekran. */
	public static function body_class( $classes ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && Plan_A_Prijave_Tour::CPT === $screen->post_type ) {
			$classes = str_replace( array( 'ttbm-modern-edit-page', 'ttbm-tour-edit-page' ), '', (string) $classes );
		}
		return $classes;
	}

	public static function boxes( $post ) {
		$cpt = Plan_A_Prijave_Tour::CPT;
		remove_meta_box( 'ttbm_meta_box_panel', $cpt, 'normal' );
		remove_meta_box( 'ttbm_tour_activitiesdiv', $cpt, 'side' );
		// Standardni okviri koje WpTravelly skriva (objava, slika, kategorije, oznake).
		add_meta_box( 'submitdiv', 'Objava', 'post_submit_meta_box', $cpt, 'side', 'high' );
		add_meta_box( 'postimagediv', 'Glavna slika izleta', 'post_thumbnail_meta_box', $cpt, 'side', 'low' );
		foreach ( array( 'ttbm_tour_cat' => 'Kategorija', 'ttbm_tour_org' => 'Organizator' ) as $tax => $label ) {
			if ( taxonomy_exists( $tax ) ) {
				add_meta_box( $tax . 'div', $label, 'post_categories_meta_box', $cpt, 'side', 'default', array( 'taxonomy' => $tax ) );
			}
		}
		add_meta_box( 'pap_editor', 'Izlet – podaci, termini i prijava', array( __CLASS__, 'render' ), Plan_A_Prijave_Tour::CPT, 'normal', 'high' );
	}

	public static function assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || Plan_A_Prijave_Tour::CPT !== $screen->post_type || 'post' !== $screen->base ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_editor();
		wp_enqueue_style( 'pap-editor', PLAN_A_PRIJAVE_URL . 'assets/editor.css', array(), PLAN_A_PRIJAVE_VERSION );
		wp_enqueue_script( 'pap-icons', PLAN_A_PRIJAVE_URL . 'assets/icons.js', array(), PLAN_A_PRIJAVE_VERSION, true );
		wp_enqueue_script( 'pap-editor', PLAN_A_PRIJAVE_URL . 'assets/editor.js', array( 'jquery', 'jquery-ui-sortable', 'wp-util', 'pap-icons' ), PLAN_A_PRIJAVE_VERSION, true );
		// Ikonice (Font Awesome 6) – ista datoteka i oznaka kao kod WpTravellyja i naše kopije.
		if ( defined( 'TTBM_PLUGIN_URL' ) ) {
			wp_enqueue_style( 'mp_font_awesome', TTBM_PLUGIN_URL . '/assets/all.min.css', array(), '6.7.2' );
			wp_enqueue_style( 'mage-icons', TTBM_PLUGIN_URL . '/assets/mage-icon/css/mage-icon.css', array(), '1.0.0' ); // starije ikonice „mi mi-…”
		}
		wp_enqueue_style( 'pap-icons' );
		wp_localize_script(
			'pap-editor',
			'papEd',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'pap_ajax' ),
			)
		);
	}

	/** Prijedlog ikonice po nazivu pojma (prvi pogodak; dio riječi, bez obzira na velika slova). */
	const ICON_GUESS = array(
		'planinar'   => 'fas fa-person-hiking',
		'alpin'      => 'pap-ic pap-ic-alpinizam',
		'ferat'      => 'pap-ic pap-ic-ferata',
		'ferrat'     => 'pap-ic pap-ic-ferata',
		'penjanj'    => 'pap-ic pap-ic-penjanje',
		'zimsk'      => 'pap-ic pap-ic-zimski',
		'skij'       => 'fas fa-person-skiing',
		'bicik'      => 'fas fa-person-biking',
		'jedren'     => 'fas fa-sailboat',
		'kajak'      => 'pap-ic pap-ic-kajak',
		'ronj'       => 'fas fa-fish',
		'plivan'     => 'fas fa-person-swimming',
		'piljar'     => 'pap-ic pap-ic-spilja',
		'povijest'   => 'pap-ic pap-ic-povijest',
		'kultur'     => 'fas fa-landmark',
		'osigur'     => 'fas fa-shield-halved',
		'prijevoz'   => 'fas fa-van-shuttle',
		'autobus'    => 'fas fa-bus',
		'trajekt'    => 'fas fa-ship',
		'smješt'     => 'fas fa-bed',
		'noćen'      => 'fas fa-bed',
		'hrana'      => 'fas fa-utensils',
		'ručak'      => 'fas fa-utensils',
		'večer'      => 'fas fa-utensils',
		'doručak'    => 'fas fa-mug-hot',
		'piće'       => 'fas fa-wine-glass',
		'skiper'     => 'fas fa-anchor',
		'vodič'      => 'fas fa-compass',
		'oprema'     => 'pap-ic pap-ic-kaciga',
		'ulaznic'    => 'fas fa-ticket',
		'karta'      => 'fas fa-ticket',
		'ljekar'     => 'fas fa-kit-medical',
		'fotograf'   => 'fas fa-camera',
	);

	/** Naše aktivnosti: ikonica se postavi i ako već postoji neka druga (dogovoreno s Igorom, jednom). */
	const ICON_SET = array(
		'alpinizam'         => 'pap-ic pap-ic-alpinizam',
		'ferrate'           => 'pap-ic pap-ic-ferata',
		'ferate'            => 'pap-ic pap-ic-ferata',
		'kajak'             => 'pap-ic pap-ic-kajak',
		'povijest'          => 'pap-ic pap-ic-povijest',
		'sportsko penjanje' => 'pap-ic pap-ic-penjanje',
		'špiljarenje'       => 'pap-ic pap-ic-spilja',
		'zimski usponi'     => 'pap-ic pap-ic-zimski',
		'biciklizam'        => 'fas fa-person-biking',
		'jedrenje'          => 'fas fa-sailboat',
		'planinarenje'      => 'fas fa-person-hiking',
	);

	/** Stavke uključeno/isključeno: isto, jednom (Igor: bolja ikonica za opremu). */
	const FEATURE_SET = array(
		'oprema' => 'pap-ic pap-ic-kaciga',
	);

	/** Vlastite ikonice (SVG) – i u administraciji i na stranici, gdje god se ispisuju ikonice pojmova. */
	public static function icons_style() {
		wp_register_style( 'pap-icons', PLAN_A_PRIJAVE_URL . 'assets/pap-icons.css', array(), PLAN_A_PRIJAVE_VERSION );
	}

	public static function icons_front() {
		wp_enqueue_style( 'pap-icons' );
	}

	/** Jednom (i nakon nadogradnje popisa): pojmovima bez ikonice dodaj odgovarajuću. Ostale postojeće ikonice se ne diraju. */
	public static function default_icons() {
		$done = (int) get_option( 'pap_default_icons' );
		if ( 3 <= $done || ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		foreach ( self::ICON_META as $tax => $key ) {
			if ( ! taxonomy_exists( $tax ) ) {
				return; // pojmovi još nisu registrirani – probaj kasnije
			}
		}
		foreach ( self::ICON_META as $tax => $key ) {
			foreach ( self::terms( $tax ) as $t ) {
				$name = mb_strtolower( trim( $t->name ) );
				// Svaki popis samo jednom (aktivnosti od 0.5.2, oprema od 0.5.3) – kasnije promjene ostaju.
				$set = 'ttbm_tour_activities' === $tax ? ( $done < 2 ? self::ICON_SET : array() ) : self::FEATURE_SET;
				if ( isset( $set[ $name ] ) ) {
					update_term_meta( $t->term_id, $key, $set[ $name ] );
					continue;
				}
				if ( '' !== (string) get_term_meta( $t->term_id, $key, true ) ) {
					continue;
				}
				foreach ( self::ICON_GUESS as $w => $icon ) {
					if ( false !== mb_strpos( $name, $w ) ) {
						update_term_meta( $t->term_id, $key, $icon );
						break;
					}
				}
			}
		}
		update_option( 'pap_default_icons', 3, false );
	}

	/* ------------------------------------------------------------------ */
	/* Dodavanje pojmova i vodiča bez napuštanja izleta (AJAX)             */
	/* ------------------------------------------------------------------ */

	private static function ajax_check( string $cap ) {
		check_ajax_referer( 'pap_ajax', 'nonce' );
		if ( ! current_user_can( $cap ) ) {
			wp_send_json_error( array( 'message' => 'Nemaš dozvolu za ovo.' ), 403 );
		}
	}

	private static function ajax_tax(): string {
		$tax = sanitize_key( wp_unslash( $_POST['tax'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( self::ICON_META[ $tax ] ) || ! taxonomy_exists( $tax ) ) {
			wp_send_json_error( array( 'message' => 'Nepoznat popis.' ), 400 );
		}
		return $tax;
	}

	private static function icon( $v ): string {
		$v = trim( sanitize_text_field( (string) $v ) );
		return preg_match( '/^[a-z0-9 -]{1,80}$/', $v ) ? $v : '';
	}

	public static function ajax_add_term() {
		self::ajax_check( 'edit_posts' );
		$tax = self::ajax_tax();
		if ( ! current_user_can( get_taxonomy( $tax )->cap->edit_terms ) ) {
			wp_send_json_error( array( 'message' => 'Nemaš dozvolu za dodavanje.' ), 403 );
		}
		$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$icon = self::icon( wp_unslash( $_POST['icon'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' === $name ) {
			wp_send_json_error( array( 'message' => 'Upiši naziv.' ), 400 );
		}
		$term = get_term_by( 'name', $name, $tax );
		$old  = (bool) $term;
		if ( ! $term ) {
			$r = wp_insert_term( $name, $tax );
			if ( is_wp_error( $r ) ) {
				wp_send_json_error( array( 'message' => $r->get_error_message() ), 400 );
			}
			$term = get_term( (int) $r['term_id'], $tax );
		}
		if ( '' !== $icon && ( ! $old || '' === (string) get_term_meta( $term->term_id, self::ICON_META[ $tax ], true ) ) ) {
			update_term_meta( $term->term_id, self::ICON_META[ $tax ], $icon );
		}
		$html = array();
		foreach ( 'ttbm_tour_activities' === $tax ? array( 'act' ) : array( 'inc', 'exc' ) as $f ) {
			$html[ $f ] = self::term_check( $term, $f, 'act' === $f ? (string) $term->term_id : $term->name, false );
		}
		wp_send_json_success(
			array(
				'id'     => (int) $term->term_id,
				'exists' => $old,
				'html'   => $html,
			)
		);
	}

	public static function ajax_term_icon() {
		self::ajax_check( 'edit_posts' );
		$tax  = self::ajax_tax();
		$id   = absint( $_POST['term'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$icon = self::icon( wp_unslash( $_POST['icon'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! current_user_can( get_taxonomy( $tax )->cap->edit_terms ) || ! get_term( $id, $tax ) || '' === $icon ) {
			wp_send_json_error( array( 'message' => 'Ikonica nije spremljena.' ), 400 );
		}
		update_term_meta( $id, self::ICON_META[ $tax ], $icon );
		wp_send_json_success( array( 'icon' => $icon ) );
	}

	public static function ajax_save_guide() {
		$type = get_post_type_object( 'ttbm_guide' );
		self::ajax_check( $type ? $type->cap->publish_posts : 'do_not_allow' );
		$id   = absint( $_POST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$desc = wp_kses_post( wp_unslash( $_POST['desc'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$img  = absint( $_POST['img'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' === $name ) {
			wp_send_json_error( array( 'message' => 'Upiši ime vodiča.' ), 400 );
		}
		if ( $id && ( 'ttbm_guide' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) ) {
			wp_send_json_error( array( 'message' => 'Vodič ne postoji.' ), 400 );
		}
		$data = array(
			'post_type'    => 'ttbm_guide',
			'post_title'   => $name,
			'post_content' => $desc,
			'post_status'  => 'publish',
		);
		if ( $id ) {
			$data['ID'] = $id;
		}
		$r = $id ? wp_update_post( $data, true ) : wp_insert_post( $data, true );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( array( 'message' => $r->get_error_message() ), 400 );
		}
		if ( $img && wp_attachment_is_image( $img ) ) {
			set_post_thumbnail( $r, $img );
		} elseif ( ! $img ) {
			delete_post_thumbnail( $r );
		}
		wp_send_json_success( array( 'id' => (int) $r, 'html' => self::guide_card( get_post( $r ), true ) ) );
	}

	/* ------------------------------------------------------------------ */
	/* Prikaz                                                              */
	/* ------------------------------------------------------------------ */

	private static function e( $v ): string {
		return esc_attr( (string) $v );
	}

	private static function terms( string $tax ): array {
		$t = get_terms(
			array(
				'taxonomy'   => $tax,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		return is_wp_error( $t ) ? array() : $t;
	}

	/** Kartica (lijevi izbornik) u kojoj je pojedini dio obrasca. */
	const TAB_OF = array(
		'osnovno'    => 'opis',
		'termini'    => 'termini',
		'karte'      => 'cijene',
		'usluge'     => 'cijene',
		'ukljuceno'  => 'sadrzaj',
		'aktivnosti' => 'sadrzaj',
		'vazno'      => 'vazno',
		'faq'        => 'izlet',
		'zasto'      => 'kontakt',
		'kontakt'    => 'kontakt',
		'vodici'     => 'vodici',
		'galerija'   => 'galerija',
		'povezani'   => 'povezani',
	);

	/** Kartice: ključ => [naziv, dashicon, kratki opis]. */
	const TABS = array(
		'opis'     => array( 'Opis', 'dashicons-edit-page', 'Naslov, opis i osnovni podaci' ),
		'termini'  => array( 'Termini', 'dashicons-calendar-alt', 'Datumi i prijave do' ),
		'cijene'   => array( 'Cijene i usluge', 'dashicons-tickets-alt', 'Karte, rate i osiguranje' ),
		'sadrzaj'  => array( 'Uključeno i aktivnosti', 'dashicons-yes-alt', 'Što je u cijeni, aktivnosti' ),
		'izlet'    => array( 'Važno za izlet', 'dashicons-flag', 'Na stranici prvo, na otvaranje' ),
		'vazno'    => array( 'Važno', 'dashicons-info-outline', 'Uvjeti, agencija, izjava' ),
		'kontakt'  => array( 'Zašto s nama i kontakt', 'dashicons-email-alt', 'Razlozi, okvir za upit' ),
		'vodici'   => array( 'Vodiči', 'dashicons-groups', 'Tko vodi izlet, slike i opisi' ),
		'galerija' => array( 'Galerija', 'dashicons-format-gallery', 'Slike na vrhu stranice' ),
		'povezani' => array( 'Povezani izleti', 'dashicons-admin-links', '„Odaberi sljedeću avanturu”' ),
		'ostalo'   => array( 'Ostalo (SEO …)', 'dashicons-admin-generic', 'Okviri drugih dodataka' ),
	);

	private static function section( string $id, string $title, string $hint = '' ) {
		$tab = self::TAB_OF[ $id ] ?? 'opis';
		echo '<section class="pap-sec" id="pap-' . esc_attr( $id ) . '" data-tab="' . esc_attr( $tab ) . '"><h3>' . esc_html( $title ) . '</h3>' . ( '' !== $hint ? '<p class="pap-hint">' . wp_kses_post( $hint ) . '</p>' : '' );
	}

	public static function render( $post ) {
		$id     = (int) $post->ID;
		$m      = static fn( $k, $d = '' ) => Plan_A_Prijave_Tour::meta( $id, $k, $d );
		$booked = Plan_A_Prijave_Tour::booked( $id );

		echo '<div class="pap">';
		wp_nonce_field( self::NONCE, self::NONCE );
		echo '<nav class="pap-nav" aria-label="Dijelovi izleta">';
		foreach ( self::TABS as $k => $t ) {
			echo '<a href="#' . esc_attr( $k ) . '" data-tab="' . esc_attr( $k ) . '"><span class="dashicons ' . esc_attr( $t[1] ) . '"></span><span class="pap-nav-t"><b>' . esc_html( $t[0] ) . '</b><small>' . esc_html( $t[2] ) . '</small></span></a>';
		}
		echo '</nav>';

		/* Osnovno */
		self::section( 'osnovno', 'Osnovni podaci', 'Prikazuju se u karticama ispod galerije (lokacija, trajanje, najviše osoba, slobodna mjesta). Glavna slika i kategorija su u desnom stupcu.' );
		$loc = (string) $m( 'ttbm_location_name' );
		echo '<div class="pap-grid">';
		// Lokacija (zemlja): padajući izbornik postojećih + nova (pojam se stvara pri spremanju).
		$locs = wp_list_pluck( self::terms( 'ttbm_tour_location' ), 'name' );
		if ( '' !== $loc && ! in_array( $loc, $locs, true ) ) {
			$locs[] = $loc;
		}
		natcasesort( $locs );
		echo '<label>Lokacija (zemlja)<select name="pap[location]" class="pap-loc"><option value="">— bez lokacije —</option>';
		foreach ( $locs as $l ) {
			echo '<option value="' . self::e( $l ) . '"' . selected( $loc, $l, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '<option value="__new__">+ Dodaj novu lokaciju…</option></select>'
			. '<span class="pap-loc-new" hidden><input type="text" class="pap-loc-name" placeholder="npr. Španjolska"><button type="button" class="button pap-loc-add">Dodaj</button></span></label>';
		echo '<label>Trajanje<span class="pap-inline"><input type="number" min="0" step="0.5" name="pap[duration]" value="' . self::e( $m( 'ttbm_travel_duration' ) ) . '"><select name="pap[duration_type]">';
		foreach ( array( 'day' => 'dana', 'hour' => 'sati', 'min' => 'minuta' ) as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $m( 'ttbm_travel_duration_type', 'day' ), $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></span></label>';
		echo '<label>Noćenja<input type="number" min="0" name="pap[nights]" value="' . self::e( $m( 'ttbm_travel_duration_night' ) ) . '"><small>prazno = ne prikazuje se</small></label>';
		echo '<label>Najviše osoba<input type="number" min="0" name="pap[max_people]" value="' . self::e( $m( 'ttbm_travel_max_people_allow' ) ) . '"><small>informacija na stranici; stvarna mjesta su kod karata</small></label>';
		echo '</div>';
		echo '<label class="pap-check"><input type="checkbox" name="pap[seats]" value="1"' . checked( 'off' !== $m( 'ttbm_display_seat_details', 'on' ), true, false ) . '> Na stranici prikaži slobodna mjesta (npr. 3/25)</label>';
		echo '</section>';

		/* Termini */
		$type = (string) $m( 'ttbm_travel_type', 'particular' );
		$type = 'fixed' === $type ? 'fixed' : 'particular';
		self::section( 'termini', 'Termini', 'Termin na koji već postoje prijave ne može se promijeniti ni obrisati (prijave bi ostale na krivom datumu). Ako se izlet seli, javi pa ćemo prijave prebaciti.' );
		echo '<p class="pap-radio"><label><input type="radio" name="pap[travel_type]" value="particular"' . checked( $type, 'particular', false ) . '> Jedan ili više datuma</label> <label><input type="radio" name="pap[travel_type]" value="fixed"' . checked( $type, 'fixed', false ) . '> Jedan termin od–do</label></p>';
		echo '<div class="pap-type pap-type-particular"' . ( 'fixed' === $type ? ' hidden' : '' ) . '><table class="pap-table"><thead><tr><th>Datum polaska</th><th>Datum povratka</th><th>Sat polaska</th><th>Prijave</th><th></th></tr></thead><tbody data-rows="dates">';
		$rows = Plan_A_Prijave_Tour::rows( $id, 'ttbm_particular_dates' );
		if ( 'fixed' === $type ) {
			$rows = array();
		}
		foreach ( $rows as $i => $r ) {
			$d    = substr( (string) ( $r['ttbm_particular_start_date'] ?? '' ), 0, 10 );
			$n    = (int) ( $booked['dates'][ $d ] ?? 0 );
			$lock = $n > 0;
			echo self::date_row( 'r' . $i, $d, substr( (string) ( $r['ttbm_particular_end_date'] ?? '' ), 0, 10 ), (string) ( $r['ttbm_particular_start_time'] ?? '' ), $n, $lock ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</tbody></table><p><button type="button" class="button pap-add" data-tpl="pap-tpl-date" data-to="dates">+ Dodaj datum</button></p></div>';
		$fd   = substr( (string) $m( 'ttbm_travel_start_date' ), 0, 10 );
		$flck = 'fixed' === $type && $fd && ! empty( $booked['dates'][ $fd ] );
		echo '<div class="pap-type pap-type-fixed"' . ( 'fixed' !== $type ? ' hidden' : '' ) . '><div class="pap-grid">'
			. '<label>Početak<input type="date" name="pap[fixed][start]" value="' . self::e( $fd ) . '"' . ( $flck ? ' readonly' : '' ) . '></label>'
			. '<label>Sat polaska<input type="time" name="pap[fixed][time]" value="' . self::e( $m( 'ttbm_travel_start_time' ) ) . '"></label>'
			. '<label>Kraj<input type="date" name="pap[fixed][end]" value="' . self::e( substr( (string) $m( 'ttbm_travel_end_date' ), 0, 10 ) ) . '"></label>'
			. '<label>Prijave do<input type="date" name="pap[fixed][reg_end]" value="' . self::e( substr( (string) $m( 'ttbm_travel_reg_end_date' ), 0, 10 ) ) . '"><small>prazno = do početka</small></label>'
			. '</div>' . ( $flck ? '<p class="pap-lock">Na ovaj termin ima prijava (' . (int) $booked['dates'][ $fd ] . '), pa se početak ne može mijenjati.</p>' : '' ) . '</div>';
		echo '</section>';

		/* Karte */
		self::section( 'karte', 'Karte i cijene', 'Broj mjesta upisuješ samo kod karte s punom cijenom (npr. „Uplata”). Izlet s <strong>dvije rate</strong> ima i kartu čiji naziv sadrži <strong>„prve rate”</strong> (npr. „Uplata prve rate”) – ona nema svoja mjesta, nego troši <strong>ista mjesta</strong> kao puna uplata. Ako je 8 mjesta, zajedno se može prijaviti najviše 8 osoba, bez obzira na to plaćaju li sve ili prvu ratu. Karta na koju je netko prijavljen ne može se preimenovati ni obrisati.' );
		echo '<table class="pap-table"><thead><tr><th>Naziv karte</th><th>Cijena (€)</th><th>Mjesta</th><th>Rezervirano</th><th>Opis (nije obavezno)</th><th>Prodano</th><th></th></tr></thead><tbody data-rows="tickets">';
		foreach ( Plan_A_Prijave_Tour::rows( $id, 'ttbm_ticket_type' ) as $i => $t ) {
			$name = (string) ( $t['ticket_type_name'] ?? '' );
			echo self::ticket_row( 't' . $i, $t, (int) ( $booked['tickets'][ $name ] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</tbody></table><p class="pap-pool">Mjesta na izletu: <strong class="pap-pool-n">–</strong> <span class="pap-hint">– zajednička za sve karte (puna uplata i prva rata)</span></p>';
		echo '<p><button type="button" class="button pap-add" data-tpl="pap-tpl-ticket" data-to="tickets">+ Dodaj kartu</button> <button type="button" class="button pap-add-rate">+ Dodaj kartu „Uplata prve rate”</button></p>';
		echo '</section>';

		/* Usluge */
		self::section( 'usluge', 'Dodatne usluge', 'Npr. „Osiguranje od otkaza (opcionalno)”. Usluga nema ograničenu količinu – može je uzeti svaki prijavljeni (koliko osoba, toliko osiguranja).' );
		echo '<table class="pap-table"><thead><tr><th>Naziv usluge</th><th>Cijena (€)</th><th>Opis (nije obavezno)</th><th>Prodano</th><th></th></tr></thead><tbody data-rows="services">';
		foreach ( Plan_A_Prijave_Tour::rows( $id, 'ttbm_extra_service_data' ) as $i => $s ) {
			$name = (string) ( $s['service_name'] ?? '' );
			echo self::service_row( 's' . $i, $s, (int) ( $booked['services'][ $name ] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</tbody></table><p><button type="button" class="button pap-add" data-tpl="pap-tpl-service" data-to="services">+ Dodaj uslugu</button></p></section>';

		/* Uključeno / isključeno */
		$inc = array_map( 'strval', (array) $m( 'ttbm_service_included_in_price', array() ) );
		$exc = array_map( 'strval', (array) $m( 'ttbm_service_excluded_in_price', array() ) );
		self::section( 'ukljuceno', 'Uključeno / isključeno', 'Označi što je uključeno u cijenu (kvačica na stranici), a što nije (križić). Novu stavku dodaj ispod popisa; ikonicu promijeniš klikom na nju.' );
		$features = self::terms( 'ttbm_tour_features_list' );
		echo '<div class="pap-two"><div><h4>Uključeno</h4><div class="pap-checks" data-terms="inc">';
		foreach ( $features as $t ) {
			echo self::term_check( $t, 'inc', $t->name, in_array( $t->name, $inc, true ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div>' . self::term_add( 'ttbm_tour_features_list', 'inc', 'Nova stavka (uključeno)' ) . '</div><div><h4>Nije uključeno</h4><div class="pap-checks" data-terms="exc">'; // phpcs:ignore WordPress.Security.EscapeOutput
		foreach ( $features as $t ) {
			echo self::term_check( $t, 'exc', $t->name, in_array( $t->name, $exc, true ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div>' . self::term_add( 'ttbm_tour_features_list', 'exc', 'Nova stavka (nije uključeno)' ) . '</div></div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput

		/* Aktivnosti */
		$act = array_map( 'strval', (array) $m( 'ttbm_tour_activities', array() ) );
		self::section( 'aktivnosti', 'Aktivnosti', 'Prikazuju se na stranici izleta (s ikonicom) i služe za filtar na popisu izleta. Ikonicu promijeniš klikom na nju.' );
		echo '<div class="pap-checks" data-terms="act">';
		foreach ( self::terms( 'ttbm_tour_activities' ) as $t ) {
			echo self::term_check( $t, 'act', (string) $t->term_id, in_array( (string) $t->term_id, $act, true ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div>' . self::term_add( 'ttbm_tour_activities', 'act', 'Nova aktivnost' ) . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput

		/* Važno */
		self::section( 'vazno', 'Važno', 'Blokovi na otvaranje (npr. Uvjeti sudjelovanja, organizator putovanja/agencija, Izjava polaznika) – na stranici ispod „Važno za izlet”, svi zatvoreni. Redoslijed mijenjaš strelicama.' );
		echo '<div class="pap-blocks" data-rows="days">';
		foreach ( Plan_A_Prijave_Tour::rows( $id, 'ttbm_daywise_details' ) as $i => $d ) {
			echo self::block_row( 'days', 'd' . $i, (string) ( $d['ttbm_day_title'] ?? '' ), (string) ( $d['ttbm_day_content'] ?? '' ), (string) ( $d['ttbm_day_time'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div><p><button type="button" class="button pap-add-block" data-to="days">+ Dodaj blok</button></p></section>';

		/* FAQ */
		self::section( 'faq', 'Važno za izlet', 'Blokovi na otvaranje pod naslovom „Važno za izlet” – na stranici izleta su prvi, iznad bloka „Važno”. Redoslijed mijenjaš strelicama.' );
		echo '<div class="pap-blocks" data-rows="faq">';
		foreach ( Plan_A_Prijave_Tour::rows( $id, 'mep_event_faq' ) as $i => $f ) {
			echo self::block_row( 'faq', 'f' . $i, (string) ( $f['ttbm_faq_title'] ?? '' ), (string) ( $f['ttbm_faq_content'] ?? '' ), '' ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div><p><button type="button" class="button pap-add-block" data-to="faq">+ Dodaj blok</button></p></section>';

		/* Zašto s nama */
		$why = (array) $m( 'ttbm_why_choose_us_texts', array() );
		self::section( 'zasto', 'Zašto planinariti s nama', 'Jedan razlog po polju. Prazna polja se ne spremaju.' );
		echo '<div class="pap-list" data-rows="why">';
		foreach ( array_merge( $why, array( '' ) ) as $i => $w ) {
			echo '<div class="pap-li"><textarea name="pap[why][]" rows="2">' . esc_textarea( (string) $w ) . '</textarea><span class="pap-tools"><button type="button" class="pap-up" title="Gore">↑</button><button type="button" class="pap-down" title="Dolje">↓</button><button type="button" class="pap-del" title="Obriši">×</button></span></div>';
		}
		echo '</div><p><button type="button" class="button pap-add-why">+ Dodaj razlog</button></p></section>';

		/* Kontakt */
		self::section( 'kontakt', 'Kontakt' );
		echo '<label class="pap-check"><input type="checkbox" name="pap[question]" value="1"' . checked( 'off' !== $m( 'ttbm_display_get_question', 'on' ), true, false ) . '> Prikaži okvir „Za sva pitanja?” s gumbom „Pošaljite upit”</label>';
		echo '<div class="pap-grid"><label>Telefon<input type="text" name="pap[phone]" value="' . self::e( $m( 'ttbm_contact_phone' ) ) . '"></label><label>E-mail<input type="email" name="pap[email]" value="' . self::e( $m( 'ttbm_contact_email' ) ) . '"></label></div>';
		echo '<label>Tekst u okviru<textarea name="pap[contact_text]" rows="2">' . esc_textarea( (string) $m( 'ttbm_contact_text' ) ) . '</textarea></label>';
		echo '</section>';

		/* Vodiči */
		$guides = array_map( 'strval', (array) $m( 'ttbm_tour_guide', array() ) );
		$all_g  = get_posts(
			array(
				'post_type'      => 'ttbm_guide',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		self::section( 'vodici', 'Vodiči na izletu', 'Označi tko vodi izlet – na stranici se prikazuju u okviru „Upoznajte vodiče” (slika, ime i opis). Novog vodiča dodaj gumbom ispod; promjena slike ili opisa vrijedi za sve izlete tog vodiča.' );
		echo '<div class="pap-guides" data-rows="guides">';
		foreach ( $all_g as $g ) {
			echo self::guide_card( $g, in_array( (string) $g->ID, $guides, true ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div>' . ( $all_g ? '' : '<p class="pap-hint pap-guides-empty">Još nema upisanih vodiča.</p>' );
		echo '<p><button type="button" class="button pap-guide-new">+ Dodaj vodiča</button></p>';
		echo '<div class="pap-guide-form" hidden><h4>Novi vodič</h4><input type="hidden" class="pap-gf-id" value="0"><input type="hidden" class="pap-gf-img" value="0">'
			. '<div class="pap-gf-row"><div class="pap-gf-photo"><span class="pap-gf-prev"></span><button type="button" class="button pap-gf-pick">Odaberi sliku</button><button type="button" class="button-link pap-gf-noimg">Makni sliku</button></div>'
			. '<div class="pap-gf-fields"><label>Ime i prezime<input type="text" class="pap-gf-name"></label><label for="pap-gf-desc">Opis</label><textarea class="pap-gf-desc" id="pap-gf-desc" rows="8" placeholder="npr. licencirani planinarski vodič, iskustvo, zanimljivosti"></textarea></div></div>'
			. '<p><button type="button" class="button button-primary pap-gf-save">Spremi vodiča</button> <button type="button" class="button pap-gf-cancel">Odustani</button> <span class="pap-gf-msg"></span></p></div>';
		echo '</section>';

		/* Galerija */
		$gal = array_map( 'intval', (array) $m( 'ttbm_gallery_images', array() ) );
		self::section( 'galerija', 'Galerija', 'Slike na vrhu stranice izleta (velika slika i četiri male). Redoslijed mijenjaš povlačenjem.' );
		echo '<input type="hidden" name="pap[gallery]" value="' . self::e( implode( ',', $gal ) ) . '" class="pap-gallery-ids"><ul class="pap-gallery">';
		foreach ( $gal as $a ) {
			$src = wp_get_attachment_image_url( $a, 'thumbnail' );
			if ( $src ) {
				echo '<li data-id="' . (int) $a . '"><img src="' . esc_url( $src ) . '" alt=""><button type="button" class="pap-gdel" title="Makni">×</button></li>';
			}
		}
		echo '</ul><p><button type="button" class="button pap-gallery-add">Dodaj slike</button> <span class="pap-hint">' . count( $gal ) . ' slika</span></p></section>';

		/* Povezani */
		$rel  = array_map( 'strval', (array) $m( 'ttbm_related_tour', array() ) );
		$all  = get_posts(
			array(
				'post_type'      => Plan_A_Prijave_Tour::CPT,
				'post_status'    => array( 'publish', 'future', 'draft', 'private' ),
				'posts_per_page' => 300,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post__not_in'   => array( $id ),
			)
		);
		self::section( 'povezani', 'Odaberi sljedeću avanturu', 'Izleti na dnu stranice. Ako ne označiš nijedan, stranica sama odabere slične izlete.' );
		echo '<label class="pap-check"><input type="checkbox" name="pap[related_show]" value="1"' . checked( 'off' !== $m( 'ttbm_display_related', 'on' ), true, false ) . '> Prikaži na stranici</label><div class="pap-checks pap-scroll">';
		foreach ( $all as $p ) {
			echo '<label><input type="checkbox" name="pap[related][]" value="' . (int) $p->ID . '"' . checked( in_array( (string) $p->ID, $rel, true ), true, false ) . '> ' . esc_html( $p->post_title ) . ( 'publish' !== $p->post_status ? ' <em>(' . esc_html( $p->post_status ) . ')</em>' : '' ) . '</label>';
		}
		echo '</div></section>';

		self::templates();
		echo '</div>';
	}

	private static function tools( bool $del = true ): string {
		return '<td class="pap-tools"><button type="button" class="pap-up" title="Gore">↑</button><button type="button" class="pap-down" title="Dolje">↓</button>' . ( $del ? '<button type="button" class="pap-del" title="Obriši">×</button>' : '' ) . '</td>';
	}

	/** Kvačica za pojam (aktivnost, uključeno/isključeno) s ikonicom koja se mijenja klikom. */
	private static function term_check( $t, string $field, string $value, bool $on ): string {
		$tax  = $t->taxonomy;
		$icon = (string) get_term_meta( $t->term_id, self::ICON_META[ $tax ], true );
		return '<label class="pap-term"><input type="checkbox" name="pap[' . esc_attr( $field ) . '][]" value="' . self::e( $value ) . '"' . checked( $on, true, false ) . '>'
			. '<button type="button" class="pap-ico' . ( '' === $icon ? ' is-empty' : '' ) . '" data-term="' . (int) $t->term_id . '" data-tax="' . esc_attr( $tax ) . '" title="Promijeni ikonicu"><i class="' . esc_attr( $icon ?: self::ICON_DEFAULT[ $tax ] ) . '"></i></button>'
			. '<span>' . esc_html( $t->name ) . '</span></label>';
	}

	/** Redak za dodavanje novog pojma: ikonica + naziv + gumb. */
	private static function term_add( string $tax, string $field, string $placeholder ): string {
		return '<div class="pap-addterm" data-tax="' . esc_attr( $tax ) . '" data-field="' . esc_attr( $field ) . '">'
			. '<button type="button" class="pap-ico pap-ico-new" data-icon="' . esc_attr( self::ICON_DEFAULT[ $tax ] ) . '" title="Odaberi ikonicu"><i class="' . esc_attr( self::ICON_DEFAULT[ $tax ] ) . '"></i></button>'
			. '<input type="text" class="pap-addterm-name" placeholder="' . esc_attr( $placeholder ) . '">'
			. '<button type="button" class="button pap-addterm-btn">+ Dodaj</button></div>';
	}

	/** Kartica vodiča: slika, ime, kvačica „vodi ovaj izlet”, gumb Uredi. */
	private static function guide_card( $g, bool $on ): string {
		$img = (int) get_post_thumbnail_id( $g );
		$src = $img ? wp_get_attachment_image_url( $img, 'thumbnail' ) : '';
		$txt = wp_strip_all_tags( (string) $g->post_content );
		return '<div class="pap-guide" data-id="' . (int) $g->ID . '" data-img="' . $img . '" data-name="' . self::e( $g->post_title ) . '" data-desc="' . self::e( (string) $g->post_content ) . '">'
			. '<label><input type="checkbox" name="pap[guides][]" value="' . (int) $g->ID . '"' . checked( $on, true, false ) . '>'
			. '<span class="pap-guide-img">' . ( $src ? '<img src="' . esc_url( $src ) . '" alt="">' : '<span class="dashicons dashicons-admin-users"></span>' ) . '</span>'
			. '<b>' . esc_html( $g->post_title ) . '</b><small>' . esc_html( wp_trim_words( $txt, 14, ' …' ) ) . '</small></label>'
			. '<button type="button" class="button-link pap-guide-edit">Uredi</button></div>';
	}

	private static function date_row( string $k, string $start, string $end, string $time, int $n, bool $lock ): string {
		$ro = $lock ? ' readonly' : '';
		return '<tr' . ( $lock ? ' class="pap-locked"' : '' ) . '><td><input type="date" name="pap[dates][' . esc_attr( $k ) . '][start]" value="' . self::e( $start ) . '"' . $ro . '></td>'
			. '<td><input type="date" name="pap[dates][' . esc_attr( $k ) . '][end]" value="' . self::e( $end ) . '"></td>'
			. '<td><input type="time" name="pap[dates][' . esc_attr( $k ) . '][time]" value="' . self::e( $time ) . '"></td>'
			. '<td>' . ( $n ? '<strong>' . $n . '</strong>' : '–' ) . '</td>' . self::tools( ! $lock ) . '</tr>';
	}

	private static function ticket_row( string $k, array $t, int $sold ): string {
		$f    = 'pap[tickets][' . $k . ']';
		$keep = '';
		foreach ( array( 'ticket_type_icon', 'sale_price', 'ticket_type_default_qty', 'ticket_type_qty_type' ) as $h ) {
			$keep .= '<input type="hidden" name="' . esc_attr( $f . '[' . $h . ']' ) . '" value="' . self::e( $t[ $h ] ?? '' ) . '">';
		}
		$price = $t['ticket_type_price'] ?? ( $t['ticket_price'] ?? '' );
		$rate  = Plan_A_Prijave_Seats::is_rate( (string) ( $t['ticket_type_name'] ?? '' ) );
		$cls   = trim( ( $sold ? 'pap-locked' : '' ) . ( $rate ? ' pap-rate' : '' ) );
		return '<tr' . ( '' !== $cls ? ' class="' . $cls . '"' : '' ) . '><td><input type="text" name="' . esc_attr( $f ) . '[name]" value="' . self::e( $t['ticket_type_name'] ?? '' ) . '"' . ( $sold ? ' readonly' : '' ) . ' required>' . $keep . '</td>'
			. '<td><input type="number" min="0" step="0.01" name="' . esc_attr( $f ) . '[price]" value="' . self::e( $price ) . '" required></td>'
			. '<td class="pap-seat"><input type="number" min="0" name="' . esc_attr( $f ) . '[qty]" value="' . self::e( $t['ticket_type_qty'] ?? '' ) . '"' . ( $rate ? '' : ' required' ) . '><span class="pap-shared">zajednička</span></td>'
			. '<td class="pap-seat"><input type="number" min="0" name="' . esc_attr( $f ) . '[resv]" value="' . self::e( $t['ticket_type_resv_qty'] ?? 0 ) . '"><span class="pap-shared">–</span></td>'
			. '<td><input type="text" name="' . esc_attr( $f ) . '[desc]" value="' . self::e( $t['ticket_type_description'] ?? '' ) . '"></td>'
			. '<td>' . ( $sold ? '<strong>' . $sold . '</strong>' : '–' ) . '</td>' . self::tools( ! $sold ) . '</tr>';
	}

	private static function service_row( string $k, array $s, int $sold ): string {
		$f    = 'pap[services][' . $k . ']';
		$keep = '';
		foreach ( array( 'service_icon', 'service_sale_price', 'service_qty_type' ) as $h ) {
			$keep .= '<input type="hidden" name="' . esc_attr( $f . '[' . $h . ']' ) . '" value="' . self::e( $s[ $h ] ?? '' ) . '">';
		}
		return '<tr' . ( $sold ? ' class="pap-locked"' : '' ) . '><td><input type="text" name="' . esc_attr( $f ) . '[name]" value="' . self::e( $s['service_name'] ?? '' ) . '"' . ( $sold ? ' readonly' : '' ) . ' required>' . $keep . '</td>'
			. '<td><input type="number" min="0" step="0.01" name="' . esc_attr( $f ) . '[price]" value="' . self::e( $s['service_price'] ?? '' ) . '" required></td>'
			. '<td><input type="text" name="' . esc_attr( $f ) . '[desc]" value="' . self::e( $s['extra_service_description'] ?? '' ) . '"></td>'
			. '<td>' . ( $sold ? '<strong>' . $sold . '</strong>' : '–' ) . '</td>' . self::tools( ! $sold ) . '</tr>';
	}

	private static function block_row( string $group, string $k, string $title, string $content, string $time ): string {
		$f = 'pap[' . $group . '][' . $k . ']';
		return '<div class="pap-block"><div class="pap-block-head"><input type="text" name="' . esc_attr( $f ) . '[title]" value="' . self::e( $title ) . '" placeholder="Naslov">'
			. '<input type="hidden" name="' . esc_attr( $f ) . '[time]" value="' . self::e( $time ) . '">'
			. '<span class="pap-tools"><button type="button" class="pap-up" title="Gore">↑</button><button type="button" class="pap-down" title="Dolje">↓</button><button type="button" class="pap-del" title="Obriši">×</button></span></div>'
			. '<textarea class="pap-rich" id="' . esc_attr( 'pap-' . $group . '-' . $k ) . '" name="' . esc_attr( $f ) . '[content]" rows="8">' . esc_textarea( $content ) . '</textarea></div>';
	}

	/** Predlošci za nove redove (JS zamijeni __K__ jedinstvenim ključem). */
	private static function templates() {
		echo '<script type="text/html" id="tmpl-pap-tpl-date">' . self::date_row( '__K__', '', '', '', 0, false ) . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<script type="text/html" id="tmpl-pap-tpl-ticket">' . self::ticket_row( '__K__', array( 'ticket_type_qty_type' => 'inputbox', 'ticket_type_resv_qty' => 0 ), 0 ) . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<script type="text/html" id="tmpl-pap-tpl-service">' . self::service_row( '__K__', array( 'service_qty_type' => 'inputbox' ), 0 ) . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<script type="text/html" id="tmpl-pap-tpl-days">' . self::block_row( 'days', '__K__', '', '', '' ) . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<script type="text/html" id="tmpl-pap-tpl-faq">' . self::block_row( 'faq', '__K__', '', '', '' ) . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/* ------------------------------------------------------------------ */
	/* Spremanje                                                           */
	/* ------------------------------------------------------------------ */

	private static function on( bool $v ): string {
		return $v ? 'on' : 'off';
	}

	private static function date( $v ): string {
		$v  = substr( sanitize_text_field( (string) $v ), 0, 10 );
		$ts = $v ? strtotime( $v ) : false;
		return $ts ? gmdate( 'Y-m-d', $ts ) : '';
	}

	private static function money( $v ): string {
		$v = str_replace( ',', '.', trim( (string) $v ) );
		return is_numeric( $v ) && (float) $v >= 0 ? (string) ( 0 + $v ) : '';
	}

	private static function names( string $csv, string $tax ): array {
		$out = array();
		foreach ( array_filter( array_map( 'trim', explode( ',', $csv ) ) ) as $n ) {
			$n    = sanitize_text_field( $n );
			$term = get_term_by( 'name', $n, $tax );
			if ( ! $term ) {
				$r    = wp_insert_term( $n, $tax );
				$term = is_wp_error( $r ) ? null : get_term( (int) $r['term_id'], $tax );
			}
			if ( $term && ! is_wp_error( $term ) ) {
				$out[] = $term;
			}
		}
		return $out;
	}

	public static function save( $id, $post ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $id ) || ! current_user_can( 'edit_post', $id ) ) {
			return;
		}
		$p      = isset( $_POST['pap'] ) && is_array( $_POST['pap'] ) ? wp_unslash( $_POST['pap'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$id     = (int) $id;
		$booked = Plan_A_Prijave_Tour::booked( $id );
		$notes  = array();
		$new    = ! metadata_exists( 'post', $id, 'ttbm_type' );

		/* Osnovno */
		$loc = sanitize_text_field( (string) ( $p['location'] ?? '' ) );
		if ( '__new__' === $loc ) { // odabrano „Dodaj novu”, a ništa upisano – ostaje stara
			$loc = (string) Plan_A_Prijave_Tour::meta( $id, 'ttbm_location_name' );
		}
		if ( '' !== $loc && ! get_term_by( 'name', $loc, 'ttbm_tour_location' ) ) {
			wp_insert_term( $loc, 'ttbm_tour_location' );
		}
		$term = '' !== $loc ? get_term_by( 'name', $loc, 'ttbm_tour_location' ) : null;
		update_post_meta( $id, 'ttbm_location_name', $loc );
		update_post_meta( $id, 'ttbm_country_name', $term ? (string) get_term_meta( $term->term_id, 'ttbm_country_location', true ) : '' );
		update_post_meta( $id, 'ttbm_display_location', self::on( '' !== $loc ) );
		$dur = sanitize_text_field( (string) ( $p['duration'] ?? '' ) );
		update_post_meta( $id, 'ttbm_travel_duration', $dur );
		update_post_meta( $id, 'ttbm_travel_duration_type', in_array( $p['duration_type'] ?? '', array( 'day', 'hour', 'min' ), true ) ? $p['duration_type'] : 'day' );
		update_post_meta( $id, 'ttbm_display_duration', self::on( '' !== $dur ) );
		$nights = sanitize_text_field( (string) ( $p['nights'] ?? '' ) );
		update_post_meta( $id, 'ttbm_travel_duration_night', $nights );
		update_post_meta( $id, 'ttbm_display_duration_night', self::on( '' !== $nights && (int) $nights > 0 ) );
		$max = sanitize_text_field( (string) ( $p['max_people'] ?? '' ) );
		update_post_meta( $id, 'ttbm_travel_max_people_allow', $max );
		update_post_meta( $id, 'ttbm_display_max_people', self::on( '' !== $max ) );
		update_post_meta( $id, 'ttbm_display_seat_details', self::on( ! empty( $p['seats'] ) ) );

		/* Termini */
		$type = 'fixed' === ( $p['travel_type'] ?? '' ) ? 'fixed' : 'particular';
		$old  = Plan_A_Prijave_Tour::dates( $id );
		$was  = 'fixed' === Plan_A_Prijave_Tour::meta( $id, 'ttbm_travel_type', 'particular' ) ? 'fixed' : 'particular';
		if ( $was !== $type && array_intersect( $old, array_keys( $booked['dates'] ) ) ) {
			$type    = $was; // vrsta termina s prijavama se ne mijenja
			$notes[] = 'Izlet ima prijave, pa vrsta termina (jedan termin / više datuma) nije promijenjena.';
		}
		if ( 'particular' === $type ) {
			$rows = array();
			$seen = array();
			foreach ( (array) ( $p['dates'] ?? array() ) as $r ) {
				$s = self::date( $r['start'] ?? '' );
				if ( '' === $s || isset( $seen[ $s ] ) ) {
					continue;
				}
				$seen[ $s ] = 1;
				$e          = self::date( $r['end'] ?? '' );
				$rows[]     = array(
					'ttbm_particular_start_date' => $s,
					'ttbm_particular_end_date'   => $e && $e >= $s ? $e : '',
					'ttbm_particular_start_time' => preg_match( '/^\d{2}:\d{2}$/', (string) ( $r['time'] ?? '' ) ) ? (string) $r['time'] : '',
				);
			}
			// Termin s prijavama se ne smije izgubiti.
			foreach ( $old as $d ) {
				if ( ! empty( $booked['dates'][ $d ] ) && ! isset( $seen[ $d ] ) ) {
					$rows[] = array(
						'ttbm_particular_start_date' => $d,
						'ttbm_particular_end_date'   => '',
						'ttbm_particular_start_time' => '',
					);
					$notes[] = 'Termin ' . gmdate( 'j.n.Y.', strtotime( $d ) ) . ' ima prijave i nije obrisan.';
				}
			}
			usort( $rows, static fn( $a, $b ) => strcmp( $a['ttbm_particular_start_date'], $b['ttbm_particular_start_date'] ) );
			update_post_meta( $id, 'ttbm_particular_dates', $rows );
			update_post_meta( $id, 'ttbm_travel_reg_end_date', gmdate( 'Y-m-d', strtotime( ' +500 day' ) ) ); // kao WpTravelly
		} else {
			$f     = (array) ( $p['fixed'] ?? array() );
			$start = self::date( $f['start'] ?? '' );
			$prev  = substr( (string) Plan_A_Prijave_Tour::meta( $id, 'ttbm_travel_start_date' ), 0, 10 );
			if ( $prev && $prev !== $start && ! empty( $booked['dates'][ $prev ] ) ) {
				$start   = $prev;
				$notes[] = 'Početak termina ima prijave i nije promijenjen.';
			}
			$end = self::date( $f['end'] ?? '' );
			$reg = self::date( $f['reg_end'] ?? '' );
			update_post_meta( $id, 'ttbm_travel_start_date', $start );
			update_post_meta( $id, 'ttbm_travel_start_time', preg_match( '/^\d{2}:\d{2}$/', (string) ( $f['time'] ?? '' ) ) ? (string) $f['time'] : '' );
			update_post_meta( $id, 'ttbm_travel_end_date', $end && $end >= $start ? $end : $start );
			update_post_meta( $id, 'ttbm_travel_reg_end_date', $reg ?: $start );
		}
		update_post_meta( $id, 'ttbm_travel_type', $type );

		/* Karte */
		$tickets = array();
		$kept    = array();
		foreach ( (array) ( $p['tickets'] ?? array() ) as $t ) {
			$name  = sanitize_text_field( (string) ( $t['name'] ?? '' ) );
			$price = self::money( $t['price'] ?? '' );
			if ( '' === $name || '' === $price ) {
				continue;
			}
			$kept[ $name ] = 1;
			$tickets[]     = array(
				'ticket_type_icon'        => sanitize_text_field( (string) ( $t['ticket_type_icon'] ?? '' ) ),
				'ticket_type_name'        => $name,
				'ticket_type_price'       => $price,
				'sale_price'              => self::money( $t['sale_price'] ?? '' ),
				'ticket_type_qty'         => (string) absint( $t['qty'] ?? 0 ),
				'ticket_type_default_qty' => (string) absint( $t['ticket_type_default_qty'] ?? 0 ),
				'ticket_type_resv_qty'    => (string) absint( $t['resv'] ?? 0 ),
				'ticket_type_qty_type'    => sanitize_text_field( (string) ( $t['ticket_type_qty_type'] ?? '' ) ) ?: 'inputbox',
				'ticket_type_description' => sanitize_text_field( (string) ( $t['desc'] ?? '' ) ),
			);
		}
		foreach ( Plan_A_Prijave_Tour::rows( $id, 'ttbm_ticket_type' ) as $t ) {
			$name = (string) ( $t['ticket_type_name'] ?? '' );
			if ( '' !== $name && ! empty( $booked['tickets'][ $name ] ) && ! isset( $kept[ $name ] ) ) {
				$tickets[] = $t;
				$notes[]   = 'Karta „' . $name . '” ima prijave i nije obrisana.';
			}
		}
		// Karta za prvu ratu nema svoja mjesta: upisuje se broj zajedničkih mjesta (zbroj punih karata).
		$pool = 0;
		foreach ( $tickets as $t ) {
			$pool += Plan_A_Prijave_Seats::is_rate( $t['ticket_type_name'] ) ? 0 : (int) $t['ticket_type_qty'];
		}
		foreach ( $tickets as $i => $t ) {
			if ( Plan_A_Prijave_Seats::is_rate( $t['ticket_type_name'] ) ) {
				$tickets[ $i ]['ticket_type_qty']      = (string) $pool;
				$tickets[ $i ]['ticket_type_resv_qty'] = '0';
			}
		}
		update_post_meta( $id, 'ttbm_ticket_type', $tickets );

		/* Usluge */
		$services = array();
		$kept     = array();
		foreach ( (array) ( $p['services'] ?? array() ) as $s ) {
			$name  = sanitize_text_field( (string) ( $s['name'] ?? '' ) );
			$price = self::money( $s['price'] ?? '' );
			$qty   = self::SERVICE_QTY; // usluga nije ograničena (osiguranje: koliko osoba, toliko osiguranja)
			if ( '' === $name || '' === $price ) {
				continue;
			}
			$kept[ $name ] = 1;
			$services[]    = array(
				'service_icon'              => sanitize_text_field( (string) ( $s['service_icon'] ?? '' ) ),
				'service_name'              => $name,
				'service_price'             => $price,
				'service_sale_price'        => self::money( $s['service_sale_price'] ?? '' ),
				'service_qty'               => (string) $qty,
				'service_qty_type'          => sanitize_text_field( (string) ( $s['service_qty_type'] ?? '' ) ) ?: 'inputbox',
				'extra_service_description' => sanitize_text_field( (string) ( $s['desc'] ?? '' ) ),
			);
		}
		foreach ( Plan_A_Prijave_Tour::rows( $id, 'ttbm_extra_service_data' ) as $s ) {
			$name = (string) ( $s['service_name'] ?? '' );
			if ( '' !== $name && ! empty( $booked['services'][ $name ] ) && ! isset( $kept[ $name ] ) ) {
				$services[] = $s;
				$notes[]    = 'Usluga „' . $name . '” ima prijave i nije obrisana.';
			}
		}
		update_post_meta( $id, 'ttbm_extra_service_data', $services );

		/* Uključeno / isključeno */
		foreach ( array( 'inc' => 'included', 'exc' => 'excluded' ) as $k => $w ) {
			$list = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', (array) ( $p[ $k ] ?? array() ) ) ) ) );
			foreach ( self::names( (string) ( $p[ $k . '_new' ] ?? '' ), 'ttbm_tour_features_list' ) as $t ) {
				$list[] = $t->name;
			}
			$list = array_values( array_unique( $list ) );
			update_post_meta( $id, 'ttbm_service_' . $w . '_in_price', $list );
			update_post_meta( $id, 'ttbm_display_' . ( 'inc' === $k ? 'include' : 'exclude' ) . '_service', self::on( (bool) $list ) );
		}

		/* Aktivnosti (podatak izleta i pojmovi, kao WpTravelly) */
		$act = array_map( 'absint', (array) ( $p['act'] ?? array() ) );
		foreach ( self::names( (string) ( $p['act_new'] ?? '' ), 'ttbm_tour_activities' ) as $t ) {
			$act[] = (int) $t->term_id;
		}
		$act = array_values( array_unique( array_filter( $act ) ) );
		update_post_meta( $id, 'ttbm_tour_activities', array_map( 'strval', $act ) );
		update_post_meta( $id, 'ttbm_display_activities', self::on( (bool) $act ) );
		wp_set_object_terms( $id, $act, 'ttbm_tour_activities' );

		/* Važno i FAQ */
		$days = array();
		foreach ( (array) ( $p['days'] ?? array() ) as $d ) {
			$title   = sanitize_text_field( (string) ( $d['title'] ?? '' ) );
			$content = Plan_A_Prijave_Text::clean( wp_kses_post( (string) ( $d['content'] ?? '' ) ) );
			if ( '' === $title && '' === trim( wp_strip_all_tags( $content ) ) ) {
				continue;
			}
			$days[] = array(
				'ttbm_day_title'   => $title,
				'ttbm_day_time'    => sanitize_text_field( (string) ( $d['time'] ?? '' ) ),
				'ttbm_day_content' => $content,
			);
		}
		update_post_meta( $id, 'ttbm_daywise_details', $days );
		update_post_meta( $id, 'ttbm_display_schedule', self::on( (bool) $days ) );
		$faq = array();
		foreach ( (array) ( $p['faq'] ?? array() ) as $f ) {
			$title   = sanitize_text_field( (string) ( $f['title'] ?? '' ) );
			$content = Plan_A_Prijave_Text::clean( wp_kses_post( (string) ( $f['content'] ?? '' ) ) );
			if ( '' === $title ) {
				continue;
			}
			$faq[] = array(
				'ttbm_faq_title'   => $title,
				'ttbm_faq_content' => $content,
			);
		}
		update_post_meta( $id, 'mep_event_faq', $faq );
		update_post_meta( $id, 'ttbm_display_faq', self::on( (bool) $faq ) );
		update_post_meta( $id, 'ttbm_faq_active', self::on( (bool) $faq ) );

		/* Zašto s nama */
		$why = array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $p['why'] ?? array() ) ) ) );
		update_post_meta( $id, 'ttbm_why_choose_us_texts', $why );
		update_post_meta( $id, 'ttbm_display_why_choose_us', self::on( (bool) $why ) );

		/* Kontakt i vodiči */
		update_post_meta( $id, 'ttbm_display_get_question', self::on( ! empty( $p['question'] ) ) );
		update_post_meta( $id, 'ttbm_contact_phone', sanitize_text_field( (string) ( $p['phone'] ?? '' ) ) );
		update_post_meta( $id, 'ttbm_contact_email', sanitize_email( (string) ( $p['email'] ?? '' ) ) );
		update_post_meta( $id, 'ttbm_contact_text', sanitize_text_field( (string) ( $p['contact_text'] ?? '' ) ) );
		$guides = array_values( array_map( 'strval', array_filter( array_map( 'absint', (array) ( $p['guides'] ?? array() ) ) ) ) );
		update_post_meta( $id, 'ttbm_tour_guide', $guides );
		update_post_meta( $id, 'ttbm_display_tour_guide', self::on( (bool) $guides ) );

		/* Galerija */
		$gal = array_values( array_filter( array_map( 'absint', explode( ',', (string) ( $p['gallery'] ?? '' ) ) ) ) );
		update_post_meta( $id, 'ttbm_gallery_images', $gal );
		update_post_meta( $id, 'ttbm_display_slider', self::on( (bool) $gal ) );

		/* Povezani */
		update_post_meta( $id, 'ttbm_related_tour', array_values( array_map( 'strval', array_filter( array_map( 'absint', (array) ( $p['related'] ?? array() ) ) ) ) ) );
		update_post_meta( $id, 'ttbm_display_related', self::on( ! empty( $p['related_show'] ) ) );

		if ( $new ) {
			Plan_A_Prijave_Tour::apply_defaults( $id );
		}
		Plan_A_Prijave_Tour::refresh_dates( $id );
		if ( $notes ) {
			set_transient( self::NOTICE . get_current_user_id(), $notes, 120 );
		}
	}

	public static function notices() {
		$notes = get_transient( self::NOTICE . get_current_user_id() );
		if ( ! $notes ) {
			return;
		}
		delete_transient( self::NOTICE . get_current_user_id() );
		echo '<div class="notice notice-warning"><p>' . implode( '<br>', array_map( 'esc_html', (array) $notes ) ) . '</p></div>';
	}
}
