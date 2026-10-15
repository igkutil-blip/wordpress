<?php
/**
 * Najava izleta (predbilježba) za izlete iz Plana izleta koji još nisu objavljeni.
 *
 * - Za svaki redak Plana izleta (plan-a-izleti) koji još nije povezan s objavljenim izletom
 *   sustav sam objavi stranicu izleta-najave: naslov, termin, vodiči i napomena iz plana,
 *   generička naslovna slika (Dolomiti), bez karata. Stranica je noindex.
 * - Umjesto „Rezervirajte” je okvir „Izlet još nije objavljen” s gumbom „Predbilježi se”
 *   (ime, prezime, mobitel, e-mail obavezan, broj osoba, privola). Mail o predbilježbi ide
 *   vodičima izleta iz plana (kao kod liste čekanja).
 * - Kad se redak plana poveže s pravim (objavljenim) izletom: svaka predbilježena osoba dobije
 *   mail s poveznicom na prijavu, stranica najave se briše, a njezina adresa preusmjerava na
 *   pravi izlet. Prođe li datum bez objave, podaci se brišu bez slanja.
 * - Na Planu izleta takav redak ima oznaku „Predbilježba”; na popisu izleta najava se vidi kao izlet.
 *
 * @package Plan_A_Prijave
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Prijave_Najava {

	const META   = '_pap_najava';        // na izletu-najavi: ID retka plana (paiz_plan)
	const GUIDES = '_pap_najava_guides'; // na izletu-najavi: vodiči iz plana (tekst)
	const TYPE   = 'pap_predbiljezba';
	const DATA   = '_pap_p';             // ime, prezime, mobitel, email, osobe, time
	const REDIR  = 'pap_najava_redirects'; // slug => ID pravog izleta (0 = popis izleta)
	const IMAGE  = '/wp-content/uploads/2026/01/dolomiti-izlet-plan-a-3.jpeg';
	const MAX    = 4;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
		add_action( 'pap_najava_sync', array( __CLASS__, 'sync' ) );
		add_action( 'save_post_paiz_plan', array( __CLASS__, 'soon' ) );
		add_action( 'trashed_post', array( __CLASS__, 'soon' ) );
		add_action( 'save_post_' . Plan_A_Prijave_Tour::CPT, array( __CLASS__, 'tour_saved' ), 30 );
		add_filter( 'plan_a_izleti_plan_tours', array( __CLASS__, 'without_najave' ) );
		add_filter( 'plan_a_izleti_plan_najava_url', array( __CLASS__, 'plan_url' ), 10, 2 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ), 99 );
		add_filter( 'rank_math/frontend/robots', array( __CLASS__, 'rank_math_robots' ), 99 );
		add_filter( 'display_post_states', array( __CLASS__, 'states' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'redirect' ), 1 );
		add_action( 'wp_ajax_pap_najava', array( __CLASS__, 'submit' ) );
		add_action( 'wp_ajax_nopriv_pap_najava', array( __CLASS__, 'submit' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 31 );
		add_action( 'admin_post_pap_najava_do', array( __CLASS__, 'admin_action' ) );
	}

	public static function register() {
		register_post_type(
			self::TYPE,
			array(
				'label'     => 'Predbilježbe',
				'public'    => false,
				'show_ui'   => false,
				'rewrite'   => false,
				'query_var' => false,
				'supports'  => array( 'title' ),
			)
		);
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( 'pap_najava_sync' ) ) {
			wp_schedule_event( time() + 120, 'hourly', 'pap_najava_sync' );
		}
		// Nova verzija: plan izleta (predmemorija za danas) računa se iznova bez najava.
		if ( get_option( 'pap_najava_ver' ) !== PLAN_A_PRIJAVE_VERSION ) {
			update_option( 'pap_najava_ver', PLAN_A_PRIJAVE_VERSION, false );
			if ( class_exists( 'Plan_A_Izleti_Plan' ) ) {
				Plan_A_Izleti_Plan::flush();
			}
			self::soon();
		}
	}

	/** Usklađivanje za pola minute (poseban argument: WordPress inače odbija isti događaj blizu satnog). */
	public static function soon() {
		if ( ! get_transient( 'pap_najava_soon' ) ) {
			set_transient( 'pap_najava_soon', 1, 45 );
			wp_schedule_single_event( time() + 30, 'pap_najava_sync', array( 'odmah' ) );
		}
	}

	public static function tour_saved( $post_id ) {
		if ( ! self::is( (int) $post_id ) && ! wp_is_post_revision( $post_id ) ) {
			self::soon(); // pravi izlet: možda je upravo objavljen izlet iz plana
		}
	}

	public static function is( int $id ): bool {
		return $id > 0 && (bool) get_post_meta( $id, self::META, true );
	}

	/* ------------------------------------------------------------------ */
	/* Usklađivanje s planom                                               */
	/* ------------------------------------------------------------------ */

	/** Sve najave: ID izleta => ID retka plana. */
	public static function all(): array {
		$ids = get_posts(
			array(
				'post_type'   => Plan_A_Prijave_Tour::CPT,
				'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_key'    => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		$out = array();
		foreach ( $ids as $id ) {
			$out[ (int) $id ] = (int) get_post_meta( $id, self::META, true );
		}
		return $out;
	}

	/** Najava za redak plana (ID izleta-najave ili 0). */
	public static function for_entry( int $entry ): int {
		return (int) array_search( $entry, self::all(), true );
	}

	public static function without_najave( $tours ) {
		foreach ( array_keys( (array) $tours ) as $id ) {
			if ( self::is( (int) $id ) ) {
				unset( $tours[ $id ] );
			}
		}
		return $tours;
	}

	public static function plan_url( $url, $row ) {
		$id = self::for_entry( (int) ( $row['entry'] ?? 0 ) );
		return $id && 'publish' === get_post_status( $id ) ? (string) get_permalink( $id ) : $url;
	}

	public static function sync( $why = '' ) {
		if ( ! class_exists( 'Plan_A_Izleti_Plan' ) || ! method_exists( 'Plan_A_Izleti_Plan', 'rows' ) ) {
			return;
		}
		Plan_A_Izleti_Plan::flush();
		$today = current_time( 'Y-m-d' );
		$rows  = array();
		foreach ( Plan_A_Izleti_Plan::rows() as $r ) {
			if ( ! empty( $r['entry'] ) ) {
				$rows[ (int) $r['entry'] ] = $r;
			}
		}
		$have = self::all();

		// Postojeće najave: objavljen pravi izlet, nestao redak ili prošao datum.
		foreach ( $have as $id => $entry ) {
			$r = $rows[ $entry ] ?? null;
			if ( $r && ! empty( $r['tour'] ) && ! self::is( (int) $r['tour'] ) ) {
				self::published( $id, (int) $r['tour'] );
			} elseif ( ! $r || 'publish' !== get_post_status( $entry ) || (string) $r['from'] < $today ) {
				self::expired( $id );
			}
		}

		// Novi i promijenjeni retci bez objavljenog izleta.
		$have = self::all();
		foreach ( $rows as $entry => $r ) {
			if ( ! empty( $r['tour'] ) || (string) $r['from'] < $today || 'publish' !== get_post_status( $entry ) ) {
				continue;
			}
			self::upsert( (int) array_search( $entry, $have, true ), $entry, $r );
		}
	}

	/** Napravi ili osvježi stranicu najave za redak plana. */
	private static function upsert( int $id, int $entry, array $r ) {
		$title = wp_strip_all_tags( (string) $r['title'] );
		$from  = (string) $r['from'];
		$to    = (string) ( $r['to'] ?: $from );
		$days  = max( 1, (int) round( ( strtotime( $to ) - strtotime( $from ) ) / DAY_IN_SECONDS ) + 1 );
		$note  = trim( (string) $r['note'] );
		$sig   = md5( wp_json_encode( array( $title, $from, $to, (string) $r['guides'], $note ) ) );
		if ( $id && get_post_meta( $id, '_pap_najava_sig', true ) === $sig ) {
			return;
		}
		$content  = '<p><strong>Izlet je u planu, a detaljan program uskoro objavljujemo.</strong></p>';
		$content .= $note ? '<p>' . esc_html( $note ) . '</p>' : '';
		$content .= '<p>Predbilježi se i javit ćemo ti e-mailom čim izlet bude objavljen, pa se možeš prijaviti među prvima.</p>';
		$post     = array(
			'post_type'    => Plan_A_Prijave_Tour::CPT,
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => $content,
		);
		if ( $id ) {
			$post['ID'] = $id;
			wp_update_post( $post );
		} else {
			$id = (int) wp_insert_post( $post );
			if ( ! $id ) {
				return;
			}
			update_post_meta( $id, self::META, $entry );
			// Postavke prikaza i „Zašto s nama” iz zadnjeg pravog izleta.
			$src = Plan_A_Prijave_Tour::template_tour( $id );
			foreach ( array_merge( Plan_A_Prijave_Tour::DISPLAY_KEYS, array( 'ttbm_why_choose_us_texts', 'ttbm_contact_phone', 'ttbm_contact_email', 'ttbm_contact_text' ) ) as $k ) {
				if ( $src && ! self::is( $src ) && metadata_exists( 'post', $src, $k ) ) {
					update_post_meta( $id, $k, get_post_meta( $src, $k, true ) );
				}
			}
			foreach ( array( 'ttbm_display_faq', 'ttbm_faq_active', 'ttbm_display_schedule', 'ttbm_display_include_service', 'ttbm_display_exclude_service', 'ttbm_display_activities', 'ttbm_display_location', 'ttbm_display_max_people', 'ttbm_display_seat_details', 'ttbm_display_price_start', 'ttbm_display_enquiry' ) as $k ) {
				update_post_meta( $id, $k, 'off' );
			}
			update_post_meta( $id, 'ttbm_theme_file', 'smart.php' );
			update_post_meta( $id, 'ttbm_display_slider', 'on' );
			update_post_meta( $id, 'ttbm_gallery_images', array() );
			update_post_meta( $id, 'ttbm_display_registration', 'on' );
			update_post_meta( $id, 'ttbm_display_related', 'on' );
			update_post_meta( $id, 'ttbm_ticket_type', array() );
			update_post_meta( $id, 'ttbm_extra_service_data', array() );
			update_post_meta( $id, 'rank_math_robots', array( 'noindex', 'nofollow' ) );
			$img = attachment_url_to_postid( home_url( self::IMAGE ) );
			if ( $img ) {
				set_post_thumbnail( $id, $img );
			}
		}
		update_post_meta( $id, 'ttbm_travel_type', 'fixed' );
		update_post_meta( $id, 'ttbm_travel_start_date', $from );
		update_post_meta( $id, 'ttbm_travel_end_date', $to );
		update_post_meta( $id, 'ttbm_travel_reg_end_date', $from );
		update_post_meta( $id, 'ttbm_travel_duration', (string) $days );
		update_post_meta( $id, 'ttbm_travel_duration_type', 'day' );
		update_post_meta( $id, 'ttbm_display_duration', 'on' );
		update_post_meta( $id, self::GUIDES, (string) $r['guides'] );
		$g = self::guide_ids( (string) $r['guides'] );
		update_post_meta( $id, 'ttbm_tour_guide', array_map( 'strval', $g ) );
		update_post_meta( $id, 'ttbm_display_tour_guide', $g ? 'on' : 'off' );
		update_post_meta( $id, '_pap_najava_sig', $sig );
		if ( class_exists( 'Plan_A_Izleti_Data' ) && method_exists( 'Plan_A_Izleti_Data', 'flush_cache' ) ) {
			Plan_A_Izleti_Data::flush_cache();
		}
	}

	/** Vodiči iz teksta plana („Igor + Krešo”) → vodiči u Plan A → Vodiči (prva 4 slova imena ili prezimena). */
	private static function guide_ids( string $text ): array {
		$norm  = static fn( $t ) => trim( (string) preg_replace( '/[^a-z ]+/', ' ', strtolower( remove_accents( str_replace( array( 'đ', 'Đ' ), 'd', $t ) ) ) ) );
		$names = array_filter( array_map( $norm, preg_split( '/\s*(?:\+|,|&|\/|\bi\b)\s*/u', $text ) ) );
		$out   = array();
		foreach ( get_posts( array( 'post_type' => 'ttbm_guide', 'post_status' => 'publish', 'numberposts' => 100 ) ) as $g ) {
			$words = array_filter( explode( ' ', $norm( $g->post_title ) ) );
			foreach ( $names as $n ) {
				$first = substr( explode( ' ', $n )[0], 0, 4 );
				foreach ( $words as $w ) {
					if ( strlen( $first ) >= 3 && 0 === strpos( $w, $first ) ) {
						$out[ $g->ID ] = (int) $g->ID;
					}
				}
			}
		}
		return array_values( $out );
	}

	/** Pravi izlet je objavljen: mail predbilježenima, najava se briše, adresa vodi na pravi izlet. */
	private static function published( int $id, int $tour ) {
		$list = self::entries( $id );
		foreach ( $list as $pid => $d ) {
			if ( is_email( (string) $d['email'] ) ) {
				$m = self::build_published( $tour, $d );
				wp_mail( $d['email'], $m['subject'], $m['html'], self::headers() );
			}
			wp_delete_post( (int) $pid, true );
		}
		self::remove( $id, $tour );
	}

	/** Datum prošao bez objave ili redak obrisan: podaci se brišu bez slanja. */
	private static function expired( int $id ) {
		foreach ( array_keys( self::entries( $id ) ) as $pid ) {
			wp_delete_post( (int) $pid, true );
		}
		self::remove( $id, 0 );
	}

	private static function remove( int $id, int $target ) {
		$slug = (string) get_post_field( 'post_name', $id );
		if ( '' !== $slug ) {
			$map          = (array) get_option( self::REDIR, array() );
			$map[ $slug ] = $target;
			update_option( self::REDIR, array_slice( $map, -300, null, true ), false );
		}
		wp_delete_post( $id, true );
		if ( class_exists( 'Plan_A_Izleti_Data' ) && method_exists( 'Plan_A_Izleti_Data', 'flush_cache' ) ) {
			Plan_A_Izleti_Data::flush_cache();
		}
	}

	/** Stara adresa najave → pravi izlet (ili popis izleta). */
	public static function redirect() {
		if ( ! is_404() ) {
			return;
		}
		$path = trim( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$slug = sanitize_title( basename( $path ) );
		$map  = (array) get_option( self::REDIR, array() );
		if ( '' === $slug || ! array_key_exists( $slug, $map ) ) {
			return;
		}
		$t   = (int) $map[ $slug ];
		$url = $t && 'publish' === get_post_status( $t ) ? get_permalink( $t ) : home_url( '/izleti/' );
		wp_safe_redirect( $url, 301 );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Stranica najave                                                     */
	/* ------------------------------------------------------------------ */

	public static function robots( $robots ) {
		if ( is_singular( Plan_A_Prijave_Tour::CPT ) && self::is( (int) get_queried_object_id() ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
			unset( $robots['index'], $robots['follow'] );
		}
		return $robots;
	}

	public static function rank_math_robots( $robots ) {
		if ( is_singular( Plan_A_Prijave_Tour::CPT ) && self::is( (int) get_queried_object_id() ) ) {
			$robots['index']  = 'noindex';
			$robots['follow'] = 'nofollow';
		}
		return $robots;
	}

	public static function states( $states, $post ) {
		if ( $post && Plan_A_Prijave_Tour::CPT === $post->post_type && self::is( (int) $post->ID ) ) {
			$states['pap_najava'] = 'NAJAVA (automatski iz Plana izleta)';
		}
		return $states;
	}

	/** Okvir umjesto prijave (smart.php). */
	public static function block( int $tour ): string {
		$n   = count( self::entries( $tour ) );
		$h   = '<div class="pap-wait pap-wait--najava">';
		$h  .= '<p class="pap-wait__t">Izlet još nije objavljen</p>';
		$h  .= '<p class="pap-wait__x">Predbilježi se – javit ćemo ti e-mailom čim izlet bude objavljen, pa se možeš prijaviti među prvima.</p>';
		$h  .= '<button type="button" class="pap-wait__btn" data-pap-wait="' . (int) $tour . '" data-pap-mode="najava" data-date="' . esc_attr( (string) get_post_meta( $tour, 'ttbm_travel_start_date', true ) ) . '" data-url="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-title="' . esc_attr( wp_strip_all_tags( html_entity_decode( get_the_title( $tour ), ENT_QUOTES, 'UTF-8' ) ) ) . '">Predbilježi se</button>';
		if ( $n > 0 ) {
			$h .= '<p class="pap-wait__n">Predbilježeno: <b>' . $n . '</b></p>';
		}
		return $h . '</div>';
	}

	/* ------------------------------------------------------------------ */
	/* Predbilježbe                                                        */
	/* ------------------------------------------------------------------ */

	public static function entries( int $tour = 0 ): array {
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
			$d = get_post_meta( $p->ID, self::DATA, true );
			if ( is_array( $d ) ) {
				$d['tour']     = (int) $p->post_parent;
				$out[ $p->ID ] = $d;
			}
		}
		return $out;
	}

	private static function post( string $k, int $max ): string {
		$v = isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- stranica u predmemoriji; zaštita kao kod liste čekanja
		return function_exists( 'mb_substr' ) ? mb_substr( trim( $v ), 0, $max ) : substr( trim( $v ), 0, $max );
	}

	private static function fail( string $msg, string $field = '' ) {
		wp_send_json_error( array( 'message' => $msg, 'field' => $field ) );
	}

	public static function submit() {
		nocache_headers();
		$js = self::post( 'pw_js', 20 );
		if ( '' !== self::post( 'pw_web', 100 ) || ! preg_match( '/^pa(\d+)$/', $js, $m ) || (int) $m[1] < 3 ) {
			self::fail( 'Predbilježba nije poslana. Osvježi stranicu i pokušaj ponovno.' );
		}
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key   = 'pap_najava_rl_' . md5( $ip . wp_salt( 'nonce' ) );
		$tries = (int) get_transient( $key );
		$all   = (int) get_transient( 'pap_najava_rl_all' );
		if ( $tries >= 5 || $all >= 40 ) {
			self::fail( 'Previše upisa u kratkom vremenu. Pokušaj ponovno kasnije.' );
		}
		$tour = absint( self::post( 'pw_tour', 12 ) );
		if ( ! $tour || ! self::is( $tour ) || 'publish' !== get_post_status( $tour ) ) {
			self::fail( 'Izlet je u međuvremenu objavljen ili maknut. Osvježi stranicu.' );
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
		if ( ! preg_match( '/^[0-9+()\/\-\s]{6,30}$/', $in['mobitel'] ) || strlen( preg_replace( '/\D+/', '', $in['mobitel'] ) ) < 8 ) {
			self::fail( 'Upiši broj mobitela (npr. 091 234 5678).', 'mobitel' );
		}
		if ( ! is_email( $in['email'] ) ) {
			self::fail( 'Upiši e-mail – na njega ti javljamo kad izlet bude objavljen.', 'email' );
		}
		if ( '1' !== self::post( 'pw_ok', 2 ) ) {
			self::fail( 'Označi privolu za podatke.', 'ok' );
		}
		$list = self::entries( $tour );
		$pos  = 0;
		$tel  = substr( preg_replace( '/\D+/', '', $in['mobitel'] ), -8 );
		foreach ( $list as $d ) {
			++$pos;
			if ( substr( preg_replace( '/\D+/', '', (string) $d['mobitel'] ), -8 ) === $tel || strtolower( (string) $d['email'] ) === strtolower( $in['email'] ) ) {
				wp_send_json_success( array( 'message' => 'Već si predbilježen/a za ovaj izlet. Javit ćemo ti na ' . $d['email'] . ' čim bude objavljen.' ) );
			}
		}
		set_transient( $key, $tries + 1, 10 * MINUTE_IN_SECONDS );
		set_transient( 'pap_najava_rl_all', $all + 1, HOUR_IN_SECONDS );

		$in['time'] = current_time( 'mysql' );
		$id         = wp_insert_post(
			array(
				'post_type'   => self::TYPE,
				'post_status' => 'publish',
				'post_parent' => $tour,
				'post_title'  => $in['ime'] . ' ' . $in['prezime'],
			),
			true
		);
		if ( is_wp_error( $id ) || ! $id ) {
			self::fail( 'Predbilježba trenutno nije moguća. Pokušaj ponovno ili nas nazovi.' );
		}
		update_post_meta( $id, self::DATA, $in );
		$n = count( $list ) + 1;
		self::mail_new( (int) $id, $tour, $n );

		wp_send_json_success(
			array(
				'message' => 'Hvala, ' . $in['ime'] . '! Čim izlet bude objavljen, poslat ćemo ti e-mail na ' . $in['email'] . ' s poveznicom za prijavu.',
				'n'       => $n,
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Mailovi                                                             */
	/* ------------------------------------------------------------------ */

	private static function headers( string $reply = '' ): array {
		$from = sanitize_email( (string) get_option( 'woocommerce_email_from_address', '' ) ) ?: sanitize_email( (string) get_option( 'admin_email' ) );
		$name = str_replace( array( '"', "\r", "\n" ), '', trim( wp_strip_all_tags( (string) get_option( 'woocommerce_email_from_name', '' ) ) ) ?: 'Plan A' );
		$h    = array( 'Content-Type: text/html; charset=UTF-8', 'From: "' . $name . '" <' . $from . '>' );
		if ( is_email( $reply ) ) {
			$h[] = 'Reply-To: ' . $reply;
		}
		return $h;
	}

	private static function label( int $tour ): string {
		$from = (string) get_post_meta( $tour, 'ttbm_travel_start_date', true );
		$ts   = strtotime( $from );
		return html_entity_decode( get_the_title( $tour ), ENT_QUOTES, 'UTF-8' ) . ( $ts ? ' (' . gmdate( 'j.n.Y.', $ts ) . ')' : '' );
	}

	/** E-mailovi vodiča iz plana (Članstvo → Postavke → Vodiči); bez njih adresa trgovine. */
	public static function guide_mails( int $tour ): array {
		$text = (string) get_post_meta( $tour, self::GUIDES, true );
		$to   = '' !== $text && class_exists( 'Plan_A_Clanstvo_Ops' ) && method_exists( 'Plan_A_Clanstvo_Ops', 'guide_mails' ) ? Plan_A_Clanstvo_Ops::guide_mails( $text ) : array();
		if ( ! $to && class_exists( 'Plan_A_Prijave_Cekanje' ) ) {
			$to = Plan_A_Prijave_Cekanje::guide_mails( $tour ); // vodiči na stranici ili adresa trgovine
		}
		return array_values( array_filter( $to, 'is_email' ) );
	}

	public static function build_new( int $id, int $tour, int $n ): array {
		$d    = (array) get_post_meta( $id, self::DATA, true );
		$tel  = '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', (string) $d['mobitel'] ) ) . '" style="color:' . Plan_A_Email::color( 'accent' ) . ';font-weight:bold;">' . esc_html( $d['mobitel'] ) . '</a>';
		$rows = array(
			array( 'Izlet', esc_html( self::label( $tour ) ) ),
			array( 'Ime i prezime', esc_html( trim( $d['ime'] . ' ' . $d['prezime'] ) ) ),
			array( 'Mobitel', $tel ),
			array( 'E-mail', '<a href="mailto:' . esc_attr( $d['email'] ) . '" style="color:' . Plan_A_Email::color( 'accent' ) . ';">' . esc_html( $d['email'] ) . '</a>' ),
			array( 'Broj osoba', (string) (int) $d['osobe'] ),
			array( 'Predbilježenih ukupno', '<b>' . $n . '</b>' ),
		);
		return array(
			'subject' => 'Predbilježba: ' . self::label( $tour ) . ' – ' . trim( $d['ime'] . ' ' . $d['prezime'] ),
			'html'    => Plan_A_Email::render(
				array( 'title' => 'Nova predbilježba za izlet', 'icon' => 'list', 'label' => 'Plan A · predbilježba' ),
				Plan_A_Email::card( '', Plan_A_Email::p( 'Izlet iz Plana izleta još nije objavljen, a netko se već predbilježio. Kad objaviš izlet, svi predbilježeni sami dobiju e-mail s poveznicom za prijavu.' ) )
				. Plan_A_Email::card( 'Predbilježba', Plan_A_Email::rows( $rows ) )
			),
			'reply'   => (string) $d['email'],
		);
	}

	private static function mail_new( int $id, int $tour, int $n ) {
		$m = self::build_new( $id, $tour, $n );
		foreach ( self::guide_mails( $tour ) as $to ) {
			wp_mail( $to, $m['subject'], $m['html'], self::headers( $m['reply'] ) );
		}
	}

	public static function build_published( int $tour, array $d ): array {
		$title = html_entity_decode( get_the_title( $tour ), ENT_QUOTES, 'UTF-8' );
		return array(
			'subject' => 'Objavljen je izlet: ' . $title . ' – prijave su otvorene',
			'html'    => Plan_A_Email::render(
				array( 'title' => 'Izlet je objavljen!', 'icon' => 'star', 'label' => 'Plan A · predbilježba' ),
				Plan_A_Email::card( '', Plan_A_Email::p( 'Pozdrav ' . esc_html( (string) $d['ime'] ) . ',' ) . Plan_A_Email::p( 'izlet <b>' . esc_html( $title ) . '</b> za koji si se predbilježio/la sada je objavljen i prijave su otvorene. Broj mjesta je ograničen – prijavi se na stranici izleta.' ) . Plan_A_Email::button( 'Prijavi se na izlet', (string) get_permalink( $tour ) ) . Plan_A_Email::small( 'Ovo je jedini e-mail koji ti šaljemo za ovu predbilježbu; tvoji podaci su sada obrisani.' ) )
			),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Admin: Izleti → Predbilježbe                                        */
	/* ------------------------------------------------------------------ */

	public static function menu() {
		$n = count( self::entries() );
		add_submenu_page( 'edit.php?post_type=' . Plan_A_Prijave_Tour::CPT, 'Predbilježbe', 'Predbilježbe' . ( $n ? ' <span class="awaiting-mod">' . $n . '</span>' : '' ), 'edit_posts', 'pap-najava', array( __CLASS__, 'page' ) );
	}

	public static function admin_action() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'pap_najava_' . $id );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'Nema dozvole.' );
		}
		$do = isset( $_GET['do'] ) ? sanitize_key( $_GET['do'] ) : '';
		if ( 'del' === $do && self::TYPE === get_post_type( $id ) ) {
			wp_delete_post( $id, true );
		} elseif ( 'sync' === $do ) {
			self::sync();
		}
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . Plan_A_Prijave_Tour::CPT . '&page=pap-najava' ) );
		exit;
	}

	public static function page() {
		echo '<div class="wrap"><h1>Predbilježbe (najave izleta)</h1>';
		echo '<p>Za svaki izlet iz Plana izleta koji još nije objavljen sustav sam objavi stranicu najave (generička slika, oznaka „Predbilježba” na Planu izleta). Kad u Planu izleta izlet povežeš s objavljenim izletom (ili ga objaviš s istim nazivom i datumom), svi predbilježeni dobiju e-mail s poveznicom za prijavu, a najava se briše. Mail o novoj predbilježbi ide vodičima iz plana.</p>';
		echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=pap_najava_do&do=sync&id=0' ), 'pap_najava_0' ) ) . '">Uskladi s Planom izleta sada</a></p>';
		$all = self::all();
		if ( ! $all ) {
			echo '<p><em>Nema najava – svi izleti iz plana su objavljeni (ili je plan prazan).</em></p></div>';
			return;
		}
		$order = array();
		foreach ( array_keys( $all ) as $id ) {
			$order[ $id ] = (string) get_post_meta( $id, 'ttbm_travel_start_date', true );
		}
		asort( $order );
		foreach ( array_keys( $order ) as $id ) {
			$list = self::entries( (int) $id );
			echo '<h2 style="margin-top:26px">' . esc_html( self::label( (int) $id ) ) . ' <a class="page-title-action" href="' . esc_url( (string) get_permalink( $id ) ) . '" target="_blank">Stranica najave</a></h2>';
			if ( ! $list ) {
				echo '<p><em>Još nitko nije predbilježen.</em></p>';
				continue;
			}
			echo '<table class="widefat striped" style="max-width:1000px"><thead><tr><th style="width:40px">Br.</th><th>Ime i prezime</th><th>Mobitel</th><th>E-mail</th><th>Osoba</th><th>Upisano</th><th></th></tr></thead><tbody>';
			$i = 0;
			foreach ( $list as $pid => $d ) {
				++$i;
				$ts  = strtotime( (string) $d['time'] );
				$del = wp_nonce_url( admin_url( 'admin-post.php?action=pap_najava_do&do=del&id=' . $pid ), 'pap_najava_' . $pid );
				echo '<tr><td>' . (int) $i . '.</td><td><b>' . esc_html( trim( $d['ime'] . ' ' . $d['prezime'] ) ) . '</b></td>';
				echo '<td><a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', (string) $d['mobitel'] ) ) . '">' . esc_html( $d['mobitel'] ) . '</a></td>';
				echo '<td><a href="mailto:' . esc_attr( $d['email'] ) . '">' . esc_html( $d['email'] ) . '</a></td><td>' . (int) $d['osobe'] . '</td>';
				echo '<td>' . esc_html( $ts ? gmdate( 'j.n.Y. H:i', $ts ) : '' ) . '</td>';
				echo '<td><a class="button button-small button-link-delete" href="' . esc_url( $del ) . '" onclick="return confirm(\'Obrisati predbilježbu?\')">Obriši</a></td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
	}
}
