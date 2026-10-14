<?php
/**
 * Administracija izleta kad je WpTravelly isključen: hrvatski nazivi u izborniku, pregledan
 * popis izleta (slika, termin, mjesta, prodano, cijena) sa zbirnim karticama i upiti na hrvatskom.
 *
 * @package Plan_A_Prijave
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Prijave_Admin {

	public static function init() {
		// Nakon Plan_A_Prijave_Ttbm::boot (plugins_loaded, 0), kad znamo radi li zamjena.
		add_action( 'plugins_loaded', array( __CLASS__, 'boot' ), 5 );
	}

	public static function boot() {
		if ( ! Plan_A_Prijave_Ttbm::active() ) {
			return; // Dok je WpTravelly uključen, njegovi zasloni ostaju kakvi jesu.
		}
		add_filter( 'register_post_type_args', array( __CLASS__, 'labels' ), 20, 2 );
		add_filter( 'register_taxonomy_args', array( __CLASS__, 'tax_labels' ), 20, 2 );
		add_filter( 'gettext_tour-booking-manager', array( __CLASS__, 'hr' ), 20, 2 );
		add_filter( 'manage_ttbm_tour_posts_columns', array( __CLASS__, 'columns' ), 20 );
		add_action( 'manage_ttbm_tour_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'summary' ) );
		add_action( 'admin_head-edit.php', array( __CLASS__, 'list_css' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 99 );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 20, 2 );
		add_action( 'admin_post_pap_status', array( __CLASS__, 'set_status' ) );
		add_action( 'admin_post_pap_full', array( __CLASS__, 'set_full' ) );
		add_filter( 'removable_query_args', static fn( $a ) => array_merge( (array) $a, array( 'pap_done' ) ) );
	}

	/* ---------- Nazivi ---------- */

	public static function labels( $args, $type ) {
		if ( 'ttbm_tour' === $type ) {
			$args['labels'] = array_merge(
				(array) ( $args['labels'] ?? array() ),
				array(
					'all_items'          => 'Svi izleti',
					'add_new'            => 'Dodaj izlet',
					'add_new_item'       => 'Novi izlet',
					'edit_item'          => 'Uredi izlet',
					'new_item'           => 'Novi izlet',
					'view_item'          => 'Pogledaj izlet',
					'view_items'         => 'Pogledaj izlete',
					'search_items'       => 'Traži izlete',
					'not_found'          => 'Nema izleta',
					'not_found_in_trash' => 'Nema izleta u smeću',
					'featured_image'     => 'Glavna slika izleta',
					'set_featured_image' => 'Postavi glavnu sliku',
					'remove_featured_image' => 'Makni glavnu sliku',
					'use_featured_image' => 'Koristi kao glavnu sliku',
					'items_list'         => 'Popis izleta',
				)
			);
		} elseif ( 'ttbm_guide' === $type ) {
			$args['label']  = 'Vodiči';
			$args['labels'] = array(
				'name'          => 'Vodiči',
				'singular_name' => 'Vodič',
				'all_items'     => 'Vodiči',
				'add_new'       => 'Dodaj vodiča',
				'add_new_item'  => 'Novi vodič',
				'edit_item'     => 'Uredi vodiča',
				'search_items'  => 'Traži vodiče',
				'not_found'     => 'Nema vodiča',
			);
		} elseif ( in_array( $type, array( 'ttbm_ticket_types', 'ttbm_hotel', 'ttbm_places' ), true ) ) {
			$args['show_in_menu'] = false; // ne koristimo
		}
		return $args;
	}

	/** Okviri u desnom stupcu izleta i stranice taksonomija na hrvatskom. */
	public static function tax_labels( $args, $tax ) {
		$names = array(
			'ttbm_tour_cat'           => array( 'Kategorija', 'Kategorije', 'kategoriju' ),
			'ttbm_tour_org'           => array( 'Organizator', 'Organizatori', 'organizatora' ),
			'ttbm_tour_tag'           => array( 'Oznaka', 'Oznake', 'oznaku' ),
			'ttbm_tour_location'      => array( 'Lokacija', 'Lokacije', 'lokaciju' ),
			'ttbm_tour_features_list' => array( 'Uključeno / isključeno', 'Uključeno / isključeno', 'stavku' ),
			'ttbm_tour_activities'    => array( 'Aktivnost', 'Aktivnosti', 'aktivnost' ),
		);
		if ( isset( $names[ $tax ] ) ) {
			list( $one, $many, $acc ) = $names[ $tax ];
			$args['labels'] = array(
				'name'                       => $many,
				'singular_name'              => $one,
				'menu_name'                  => $many,
				'all_items'                  => 'Sve',
				'edit_item'                  => 'Uredi ' . $acc,
				'view_item'                  => 'Pogledaj',
				'update_item'                => 'Spremi',
				'add_new_item'               => 'Dodaj ' . $acc,
				'new_item_name'              => 'Novi naziv',
				'search_items'               => 'Traži',
				'popular_items'              => 'Najčešće',
				'separate_items_with_commas' => 'Odvoji zarezom',
				'add_or_remove_items'        => 'Dodaj ili makni',
				'choose_from_most_used'      => 'Odaberi među najčešćima',
				'most_used'                  => 'Najčešće',
				'not_found'                  => 'Nema',
				'no_terms'                   => 'Nema',
				'back_to_items'              => '← Natrag',
			);
		}
		return $args;
	}

	public static function menu() {
		global $submenu;
		$key = 'edit.php?post_type=ttbm_tour';
		foreach ( (array) ( $submenu[ $key ] ?? array() ) as $i => $item ) {
			if ( 'ttbm_get_enquiry' === ( $item[2] ?? '' ) ) {
				$submenu[ $key ][ $i ][0] = 'Upiti'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			}
		}
	}

	/** WpTravellyjevi engleski tekstovi upita (admin, obrazac na stranici, mail kupcu) na hrvatskom. */
	const HR = array(
		'Enquiries'                  => 'Upiti',
		'Enquiry'                    => 'Upiti',
		'Get Enquiry'                => 'Upiti',
		'Enquiry List'               => 'Popis upita',
		' Enquiry List'              => 'Popis upita',
		' Settings'                  => 'Postavke',
		'Settings'                   => 'Postavke',
		'Reply'                      => 'Odgovori',
		'View Enquiry'               => 'Pregled upita',
		'View'                       => 'Pregled',
		'Delete'                     => 'Obriši',
		'Total'                      => 'Ukupno',
		'New'                        => 'Novi',
		'Viewed'                     => 'Pregledano',
		'Replied'                    => 'Odgovoreno',
		'Subject'                    => 'Naslov',
		'Subject:'                   => 'Naslov:',
		'Name'                       => 'Ime',
		'Name:'                      => 'Ime i prezime:',
		'Email'                      => 'E-mail',
		'Email:'                     => 'E-mail:',
		'Message'                    => 'Poruka',
		'Message:'                   => 'Poruka:',
		'Status'                     => 'Stanje',
		'Date'                       => 'Datum',
		'Actions'                    => 'Radnje',
		'From:'                      => 'Od:',
		'To:'                        => 'Za:',
		'To'                         => 'Za',
		'Search'                     => 'Traži',
		'Search enquiries…'          => 'Traži upite…',
		'Send Message'               => 'Pošalji',
		'Website'                    => 'Web stranica',
		'Your Name'                  => 'Ime i prezime',
		'Your Email'                 => 'E-mail',
		'Your Message'               => 'Vaše pitanje',
		'Email Configuration'        => 'Postavke e-maila',
		'From Email'                 => 'E-mail pošiljatelja',
		'From Name'                  => 'Ime pošiljatelja',
		'Save Settings'              => 'Spremi postavke',
		'Reset'                      => 'Vrati',
		'Settings saved successfully.' => 'Postavke su spremljene.',
		'No enquiries found.'        => 'Nema upita.',
		'Enquiry submitted successfully.' => 'Upit je poslan. Javit ćemo vam se uskoro.',
		'Failed to submit enquiry. Please try again.' => 'Upit nije poslan. Pokušajte ponovno.',
		'Please complete all required fields with valid information.' => 'Ispunite sva polja ispravno.',
		'Message sent successfully!' => 'Poruka je poslana.',
		'Failed to send email.'      => 'Slanje e-maila nije uspjelo.',
		'Enquiry deleted successfully.' => 'Upit je obrisan.',
		'New Enquiry Received'       => 'Novi upit',
		'Thank you for contacting us!' => 'Hvala na upitu!',
		'Thank you for your enquiry - %s' => 'Hvala na upitu – %s',
		'We have received your enquiry and will get back to you as soon as possible.' => 'Primili smo vaš upit i javit ćemo vam se što prije.',
		'Your Enquiry Details:'      => 'Vaš upit:',
		'Dear %s,'                   => 'Poštovani/a %s,',
		'This is an automated confirmation email. Please do not reply to this email.' => 'Ovo je automatska potvrda, ne odgovarajte na ovaj e-mail.',
		'Enquiries and replies are sent from this address. Defaults to admin email.' => 'S ove adrese šalju se upiti i odgovori. Zadano: e-mail administratora.',
		'Sender name shown in enquiry emails. Defaults to site name.' => 'Ime pošiljatelja u mailovima upita. Zadano: ime stranice.',
	);

	/** Na stranici izleta samo obrazac „Pošaljite upit” (ostali tekstovi već su prevedeni). */
	const FRONT = array( 'Name:', 'Email:', 'Subject:', 'Message:', 'Send Message', 'Website', 'Your Name', 'Your Email', 'Your Message', 'Subject' );

	/**
	 * Stranica izleta: natpisi koji nisu prevedeni ni u Loco prijevodu (languages/plugins/tour-booking-manager-hr.mo).
	 * Vrijedi samo kad prijevoda nema – Igorovi prijevodi u Locu imaju prednost.
	 */
	const FRONT_HR = array(
		'Select a date to view tickets and pricing.' => 'Odaberite datum za prikaz cijene i slobodnih mjesta.',
		'Select Date First'                          => 'Prvo odaberite datum',
		'Select Date'                                => 'Odaberite datum',
		'Tour booking'                               => 'Prijava na izlet',
		'Decrease quantity'                          => 'Smanji broj',
		'Increase quantity'                          => 'Povećaj broj',
		'DAY'                                        => 'DAN',
		'DAYS'                                       => 'DANA',
		'NIGHT'                                      => 'NOĆ',
		'NIGHTS'                                     => 'NOĆI',
		'HOUR'                                       => 'SAT',
		'HOURS'                                      => 'SATI',
		'MINUTE'                                     => 'MINUTA',
		'MINUTES'                                    => 'MINUTA',
		'Hour '                                      => 'sat',
		'Hours '                                     => 'sati',
		'Minute '                                    => 'minuta',
		'Minutes '                                   => 'minuta',
		'Night '                                     => 'noć',
		'Nights '                                    => 'noći',
		'Arrival Day'                                => 'Dan dolaska',
		'Final Day'                                  => 'Zadnji dan',
		'Leisure Day'                                => 'Slobodan dan',
		'Planned Activities'                         => 'Aktivnosti na izletu',
		'Load more'                                  => 'Učitaj još',
		'Show less'                                  => 'Prikaži manje',
		'Please Select Date'                         => 'Odaberite datum',
		'Please Select Time'                         => 'Odaberite vrijeme',
		'Please Select Ticket Type'                  => 'Odaberite broj osoba',
		'Link copied to clipboard.'                  => 'Poveznica je kopirana.',
		'Available Today'                            => 'Dostupno danas',
		'Previous guide'                             => 'Prethodni vodič',
		'Next guide'                                 => 'Sljedeći vodič',
		'view more '                                 => 'više ',
		'Language'                                   => 'Jezik',
		'Age Limit'                                  => 'Dobna granica',
		'Quantity'                                   => 'Količina',
		'Availability'                               => 'Dostupnost',
		'ticket left'                                => 'mjesto slobodno',
		'tickets left'                               => 'mjesta slobodno',
		'%1$d of %2$d sold'                          => '%1$d od %2$d prijavljeno',
		'Confirm Dates'                              => 'Potvrdi datume',
		'child'                                      => 'dijete',
		'Max People'                                 => 'Maksimalno osoba',
	);

	public static function hr( $translation, $text ) {
		if ( ( ! is_admin() || wp_doing_ajax() ) && $translation === $text && isset( self::FRONT_HR[ $text ] ) ) {
			return self::FRONT_HR[ $text ];
		}
		if ( ! isset( self::HR[ $text ] ) ) {
			return $translation;
		}
		return ( is_admin() || in_array( $text, self::FRONT, true ) ) ? self::HR[ $text ] : $translation;
	}

	/* ---------- Popis izleta ---------- */

	/** Kod izleta samo naš „Kopiraj izlet” (WpTravellyjev „Duplicate” kopira i termine). */
	public static function row_actions( $actions, $post ) {
		if ( $post && 'ttbm_tour' === $post->post_type ) {
			unset( $actions['duplicate'] );
		}
		return $actions;
	}

	public static function columns( $cols ) {
		$out = array( 'cb' => $cols['cb'] ?? '<input type="checkbox" />' );
		$out['pap_img']    = '<span class="screen-reader-text">Slika</span>';
		$out['title']      = 'Izlet';
		$out['pap_date']   = 'Termin';
		$out['pap_seats']  = 'Slobodno';
		$out['pap_sold']   = 'Prijavljeno';
		$out['pap_price']  = 'Cijena od';
		$out['pap_status'] = 'Stanje';
		return $out;
	}

	private static function next_date( int $id ): string {
		$dates = Plan_A_Prijave_Tour::dates( $id );
		$today = current_time( 'Y-m-d' );
		foreach ( $dates as $d ) {
			if ( $d >= $today ) {
				return $d;
			}
		}
		return $dates ? (string) end( $dates ) : '';
	}

	public static function column( $col, $id ) {
		$id = (int) $id;
		switch ( $col ) {
			case 'pap_img':
				$img = get_the_post_thumbnail( $id, array( 64, 64 ) );
				echo $img ? $img : '<span class="pap-noimg"></span>'; // phpcs:ignore WordPress.Security.EscapeOutput
				break;
			case 'pap_date':
				$d = self::next_date( $id );
				if ( '' === $d ) {
					echo '–';
					break;
				}
				$past = $d < current_time( 'Y-m-d' );
				echo '<span class="pap-date' . ( $past ? ' is-past' : '' ) . '">' . esc_html( wp_date( 'j. n. Y.', strtotime( $d ) ) ) . '</span>';
				echo $past ? '<br><small>prošao</small>' : '';
				break;
			case 'pap_seats':
				$total = (int) TTBM_Function::get_total_seat( $id );
				$free  = (int) TTBM_Function::get_total_available( $id );
				if ( $total < 1 ) {
					echo '–';
					break;
				}
				$cls = $free < 1 ? 'is-full' : ( $free <= 3 ? 'is-low' : '' );
				echo '<span class="pap-seats ' . esc_attr( $cls ) . '"><b>' . (int) $free . '</b> / ' . (int) $total . '</span>' . ( $free < 1 ? '<br><small>popunjeno</small>' : '' );
				break;
			case 'pap_sold':
				$n = array_sum( Plan_A_Prijave_Tour::booked( $id )['tickets'] );
				echo $n ? '<b>' . (int) $n . '</b>' : '–';
				break;
			case 'pap_price':
				$p = TTBM_Function::get_tour_start_price( $id );
				echo '' !== (string) $p && function_exists( 'wc_price' ) ? wp_kses_post( wc_price( (float) $p ) ) : '–';
				break;
			case 'pap_status':
				$st = get_post_status( $id );
				$l  = array(
					'publish' => 'Objavljen',
					'draft'   => 'Skica',
					'future'  => 'Zakazan',
					'pending' => 'Na pregledu',
					'private' => 'Privatan',
				);
				echo '<span class="pap-st is-' . esc_attr( $st ) . '">' . esc_html( $l[ $st ] ?? $st ) . '</span>';
				if ( Plan_A_Prijave_Seats::is_full( (int) $id ) ) {
					echo ' <span class="pap-st is-full">Popunjeno</span>';
				}
				if ( current_user_can( 'edit_post', $id ) ) {
					$to   = 'publish' === $st ? 'draft' : 'publish';
					$link = wp_nonce_url( admin_url( 'admin-post.php?action=pap_status&id=' . $id . '&to=' . $to ), 'pap_status_' . $id );
					echo '<span class="pap-quick">';
					if ( ! Plan_A_Prijave_Unos::is_template( (int) $id ) ) { // predložak se ne objavljuje
						echo '<a class="button button-small" href="' . esc_url( $link ) . '">' . ( 'draft' === $to ? 'U skicu' : 'Objavi' ) . '</a>';
					}
					// Zatvori / otvori prijave (izlet se prikazuje kao popunjen).
					if ( in_array( $st, array( 'publish', 'future' ), true ) ) {
						$full = Plan_A_Prijave_Seats::is_full( (int) $id );
						$fl   = wp_nonce_url( admin_url( 'admin-post.php?action=pap_full&id=' . $id . '&to=' . ( $full ? 0 : 1 ) ), 'pap_full_' . $id );
						echo '<a class="button button-small ' . ( $full ? 'pap-open' : 'pap-full' ) . '" href="' . esc_url( $fl ) . '"' . ( $full ? '' : ' onclick="return confirm(\'Zatvoriti prijave? Izlet će na stranici pisati kao popunjen. Može se vratiti gumbom Otvori prijave.\')"' ) . '>' . ( $full ? 'Otvori prijave' : 'Popunjeno' ) . '</a>';
					}
				}
				if ( current_user_can( 'delete_post', $id ) ) {
					echo '<a class="button button-small pap-trash" href="' . esc_url( get_delete_post_link( $id ) ) . '" onclick="return confirm(\'Premjestiti izlet u smeće? Može se vratiti iz Smeća.\')">Smeće</a>';
				}
				echo current_user_can( 'edit_post', $id ) ? '</span>' : '';
				break;
		}
	}

	/** Gumb „U skicu” / „Objavi” u popisu izleta. */
	public static function set_status() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$to = isset( $_GET['to'] ) && 'publish' === $_GET['to'] ? 'publish' : 'draft';
		check_admin_referer( 'pap_status_' . $id );
		if ( ! $id || 'ttbm_tour' !== get_post_type( $id ) || ! current_user_can( 'publish' === $to ? 'publish_post' : 'edit_post', $id ) ) {
			wp_die( 'Nemaš ovlasti za ovaj izlet.' );
		}
		wp_update_post( array( 'ID' => $id, 'post_status' => $to ) );
		$back = wp_get_referer() ?: admin_url( 'edit.php?post_type=ttbm_tour' );
		wp_safe_redirect( add_query_arg( 'pap_done', $to, remove_query_arg( 'pap_done', $back ) ) );
		exit;
	}

	/** Gumb „Popunjeno” / „Otvori prijave”. */
	public static function set_full() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'pap_full_' . $id );
		if ( ! $id || 'ttbm_tour' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
			wp_die( 'Nemaš dozvolu.' );
		}
		if ( ! empty( $_GET['to'] ) ) {
			update_post_meta( $id, Plan_A_Prijave_Seats::FULL, current_time( 'mysql' ) );
		} else {
			delete_post_meta( $id, Plan_A_Prijave_Seats::FULL );
		}
		clean_post_cache( $id );
		// Popis izleta i predmemorija stranice: kao da je izlet spremljen (plan-a-izleti, SpeedyCache, SEO).
		if ( class_exists( 'Plan_A_Izleti_Data' ) && method_exists( 'Plan_A_Izleti_Data', 'flush_cache' ) ) {
			Plan_A_Izleti_Data::flush_cache();
		}
		wp_update_post( array( 'ID' => $id ) );
		do_action( 'plan_a_prijave_full_changed', $id, ! empty( $_GET['to'] ) );
		$back = wp_get_referer() ?: admin_url( 'edit.php?post_type=ttbm_tour' );
		wp_safe_redirect( add_query_arg( 'pap_done', empty( $_GET['to'] ) ? 'open' : 'full', remove_query_arg( 'pap_done', $back ) ) );
		exit;
	}

	/** Zbirne kartice iznad popisa izleta. */
	public static function summary() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-ttbm_tour' !== $screen->id ) {
			return;
		}
		$ids   = get_posts(
			array(
				'post_type'      => 'ttbm_tour',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$today = current_time( 'Y-m-d' );
		$up    = 0;
		$sold  = 0;
		$free  = 0;
		$full  = 0;
		foreach ( $ids as $id ) {
			$d = self::next_date( (int) $id );
			if ( '' === $d || $d < $today ) {
				continue;
			}
			++$up;
			$sold += array_sum( Plan_A_Prijave_Tour::booked( (int) $id )['tickets'] );
			$f     = (int) TTBM_Function::get_total_available( (int) $id );
			$free += $f;
			$full += ( (int) TTBM_Function::get_total_seat( (int) $id ) > 0 && $f < 1 ) ? 1 : 0;
		}
		$cards = array(
			array( 'Objavljeni izleti', count( $ids ), 'dashicons-admin-site-alt2' ),
			array( 'Nadolazeći', $up, 'dashicons-calendar-alt' ),
			array( 'Prijavljeno (nadolazeći)', $sold, 'dashicons-groups' ),
			array( 'Slobodnih mjesta', $free, 'dashicons-tickets-alt' ),
			array( 'Popunjeni', $full, 'dashicons-lock' ),
		);
		if ( isset( $_GET['pap_done'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$done = sanitize_key( wp_unslash( $_GET['pap_done'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$msgs = array(
				'publish' => 'Izlet je objavljen.',
				'full'    => 'Prijave su zatvorene – izlet na stranici piše kao popunjen.',
				'open'    => 'Prijave su ponovno otvorene.',
			);
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msgs[ $done ] ?? 'Izlet je premješten u skice (više se ne vidi na stranici).' ) . '</p></div>';
		}
		echo '<div class="pap-cards">';
		foreach ( $cards as $c ) {
			echo '<div class="pap-card"><span class="dashicons ' . esc_attr( $c[2] ) . '"></span><div><b>' . (int) $c[1] . '</b><small>' . esc_html( $c[0] ) . '</small></div></div>';
		}
		echo '</div>';
	}

	public static function list_css() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-ttbm_tour' !== $screen->id ) {
			return;
		}
		echo '<style>
.pap-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin:14px 0 6px}
.pap-card{display:flex;gap:12px;align-items:center;padding:14px 16px;background:#fff;border:1px solid #e3e8ee;border-radius:12px}
.pap-card .dashicons{width:38px;height:38px;font-size:22px;line-height:38px;text-align:center;border-radius:10px;background:#e8f4fb;color:#1e7fc4}
.pap-card b{display:block;font-size:22px;line-height:1.1;color:#12304b}.pap-card small{color:#5f6b77}
.wp-list-table .column-pap_img{width:72px}.wp-list-table .column-pap_img img,.pap-noimg{width:64px;height:64px;object-fit:cover;border-radius:8px;display:block;background:#f4f7fa}
.wp-list-table .column-title{width:34%}.wp-list-table .column-title .row-title{font-size:14px}
.pap-date.is-past,.pap-seats.is-full{color:#b32d2e}.pap-seats.is-low{color:#b26200}
.pap-st{display:inline-block;padding:2px 9px;border-radius:999px;font-size:12px;font-weight:600;background:#f0f0f1;color:#50575e}
.pap-st.is-full{background:#fde8e8;color:#b32d2e}.pap-quick .pap-full{color:#b32d2e;border-color:#e3b4b4}.pap-quick .pap-open{color:#1d6b33;border-color:#9fd3ad}.pap-st.is-publish{background:#e6f5ea;color:#1d6b33}.pap-st.is-draft{background:#fdf1e4;color:#8a4a0b}.pap-st.is-future{background:#e8f4fb;color:#1e7fc4}
.wp-list-table td{vertical-align:middle}
.pap-quick{display:flex;gap:6px;margin-top:8px;flex-wrap:wrap}.pap-quick .button-small{min-height:26px;line-height:24px}
.pap-quick .pap-trash{color:#b32d2e;border-color:#e3b4b4}
.wp-list-table .column-pap_status{width:150px}
</style>';
	}
}
