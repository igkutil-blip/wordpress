<?php
/**
 * Administracija: popis članova (stupci, filtar, pretraga), kartica člana, postavke,
 * Google tablica i izvoz.
 *
 * @package Plan_A_Clanstvo
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Clanstvo_Admin {

	const CAP  = 'manage_options';
	const SLUG = 'plan-a-clanstvo';

	public static function init() {
		$cpt = Plan_A_Clanstvo_Data::CPT;
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_pac_admin', array( __CLASS__, 'action' ) );
		add_filter( "manage_{$cpt}_posts_columns", array( __CLASS__, 'columns' ) );
		add_action( "manage_{$cpt}_posts_custom_column", array( __CLASS__, 'column' ), 10, 2 );
		add_filter( "manage_edit-{$cpt}_sortable_columns", array( __CLASS__, 'sortable' ) );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'filter_ui' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'query' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_action( 'add_meta_boxes_' . $cpt, array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . $cpt, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PLAN_A_CLANSTVO_FILE ), array( __CLASS__, 'links' ) );
	}

	public static function links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">Postavke</a>' );
		return $links;
	}

	public static function url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'post_type' => Plan_A_Clanstvo_Data::CPT, 'page' => self::SLUG ), $args ), admin_url( 'edit.php' ) );
	}

	private static function action_url( string $do, int $id = 0 ): string {
		return wp_nonce_url( add_query_arg( array( 'action' => 'pac_admin', 'do' => $do, 'id' => $id ), admin_url( 'admin-post.php' ) ), 'pac_admin_' . $do . '_' . $id );
	}

	public static function menu() {
		add_submenu_page( 'edit.php?post_type=' . Plan_A_Clanstvo_Data::CPT, 'Postavke i Google tablica', 'Postavke i tablica', self::CAP, self::SLUG, array( __CLASS__, 'page' ) );
	}

	/* ---------------------------------------------------------------------
	 * Popis
	 * ------------------------------------------------------------------- */

	public static function columns( $cols ) {
		return array(
			'cb'        => $cols['cb'] ?? '',
			'pac_broj'  => 'Br.',
			'title'     => 'Ime i prezime',
			'pac_email' => 'E-mail',
			'pac_mob'   => 'Mobitel',
			'pac_stat'  => 'Status',
			'pac_prij'  => 'Prijava',
			'pac_potv'  => 'Potvrda',
			'pac_sync'  => 'Tablica',
		);
	}

	public static function column( $col, $id ) {
		$m = Plan_A_Clanstvo_Data::get_member( (int) $id );
		if ( ! $m ) {
			return;
		}
		switch ( $col ) {
			case 'pac_broj':
				echo (int) $m['broj'];
				break;
			case 'pac_email':
				echo '<a href="mailto:' . esc_attr( $m['email'] ) . '">' . esc_html( $m['email'] ) . '</a>';
				break;
			case 'pac_mob':
				echo esc_html( $m['mobitel'] );
				break;
			case 'pac_stat':
				echo 'potvrdeno' === $m['status'] ? '<span style="color:#1e7d3a;font-weight:600">✔ Potvrđeno</span>' : '<span style="color:#b26a00;font-weight:600">⏳ Čeka potvrdu</span>';
				if ( '' !== $m['roditelj'] ) {
					echo '<br><small>maloljetnik</small>';
				}
				break;
			case 'pac_prij':
				echo esc_html( Plan_A_Clanstvo_Data::hr_datetime( $m['created'] ) );
				break;
			case 'pac_potv':
				echo esc_html( Plan_A_Clanstvo_Data::hr_datetime( $m['confirmed'] ) ?: '–' );
				break;
			case 'pac_sync':
				$sync = (string) get_post_meta( (int) $id, '_pac_sync', true );
				echo 'ok' === $sync ? '✔' : ( 'pending' === $sync ? '<span title="Ponovno slanje svaki sat">⏳</span>' : '–' );
				break;
		}
	}

	public static function sortable( $cols ) {
		$cols['pac_broj'] = 'pac_broj';
		$cols['pac_prij'] = 'pac_prij';
		return $cols;
	}

	public static function filter_ui( $post_type ) {
		if ( Plan_A_Clanstvo_Data::CPT !== $post_type ) {
			return;
		}
		$cur = isset( $_GET['pac_status'] ) ? sanitize_key( wp_unslash( $_GET['pac_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<select name="pac_status"><option value="">Svi statusi</option>'
			. '<option value="potvrdeno"' . selected( $cur, 'potvrdeno', false ) . '>Potvrđeno</option>'
			. '<option value="ceka"' . selected( $cur, 'ceka', false ) . '>Čeka potvrdu</option></select>';
	}

	public static function query( $q ) {
		if ( ! is_admin() || ! $q->is_main_query() || Plan_A_Clanstvo_Data::CPT !== $q->get( 'post_type' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$meta = array();
		if ( ! empty( $_GET['pac_status'] ) ) {
			$meta[] = array(
				'key'   => '_pac_status',
				'value' => sanitize_key( wp_unslash( $_GET['pac_status'] ) ),
			);
		}
		$s = trim( (string) $q->get( 's' ) );
		if ( '' !== $s && ( false !== strpos( $s, '@' ) || preg_match( '/^\d{4,}$/', $s ) ) ) {
			// Pretraga po e-mailu, OIB-u ili mobitelu.
			$q->set( 's', '' );
			$meta[] = array(
				'relation' => 'OR',
				array(
					'key'     => '_pac_email',
					'value'   => strtolower( $s ),
					'compare' => 'LIKE',
				),
				array(
					'key'   => '_pac_oib',
					'value' => $s,
				),
				array(
					'key'     => '_pac_mobitel',
					'value'   => $s,
					'compare' => 'LIKE',
				),
			);
		}
		if ( $meta ) {
			$q->set( 'meta_query', $meta );
		}
		$orderby = $q->get( 'orderby' );
		if ( 'pac_broj' === $orderby || '' === $orderby || ( empty( $_GET['orderby'] ) ) ) {
			$q->set( 'meta_key', '_pac_broj' );
			$q->set( 'orderby', 'meta_value_num' );
			if ( empty( $_GET['order'] ) ) {
				$q->set( 'order', 'DESC' );
			}
		} elseif ( 'pac_prij' === $orderby ) {
			$q->set( 'meta_key', '_pac_created' );
			$q->set( 'orderby', 'meta_value' );
		}
		// phpcs:enable
	}

	public static function row_actions( $actions, $post ) {
		if ( Plan_A_Clanstvo_Data::CPT !== $post->post_type ) {
			return $actions;
		}
		unset( $actions['inline hide-if-no-js'], $actions['view'] );
		$status = (string) get_post_meta( $post->ID, '_pac_status', true );
		if ( 'potvrdeno' !== $status ) {
			$actions['pac_resend']  = '<a href="' . esc_url( self::action_url( 'resend', $post->ID ) ) . '">Pošalji ponovno e-mail za potvrdu</a>';
			$actions['pac_confirm'] = '<a href="' . esc_url( self::action_url( 'confirm', $post->ID ) ) . '" onclick="return confirm(\'Potvrditi pristupnicu ručno (bez klika člana)?\')">Potvrdi ručno</a>';
		} else {
			$actions['pac_pay'] = '<a href="' . esc_url( self::action_url( 'paymail', $post->ID ) ) . '">Pošalji uplatnicu za članarinu</a>';
		}
		return $actions;
	}

	/* ---------------------------------------------------------------------
	 * Kartica člana
	 * ------------------------------------------------------------------- */

	public static function meta_boxes( $post ) {
		remove_meta_box( 'submitdiv', Plan_A_Clanstvo_Data::CPT, 'side' );
		add_meta_box( 'pac_data', 'Pristupnica', array( __CLASS__, 'box' ), Plan_A_Clanstvo_Data::CPT, 'normal', 'high' );
		add_meta_box( 'pac_side', 'Status', array( __CLASS__, 'side' ), Plan_A_Clanstvo_Data::CPT, 'side', 'high' );
	}

	public static function box( $post ) {
		$m = Plan_A_Clanstvo_Data::get_member( $post->ID );
		wp_nonce_field( 'pac_save_' . $post->ID, 'pac_nonce' );
		echo '<table class="form-table"><tbody>';
		foreach ( Plan_A_Clanstvo_Data::FIELDS as $k => $label ) {
			$v = 'datum' === $k ? Plan_A_Clanstvo_Data::hr_date( $m[ $k ] ) : $m[ $k ];
			echo '<tr><th><label for="pac-f-' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td><input type="text" class="regular-text" id="pac-f-' . esc_attr( $k ) . '" name="pac[' . esc_attr( $k ) . ']" value="' . esc_attr( $v ) . '"></td></tr>';
		}
		echo '</tbody></table><p class="description">Promjene se nakon spremanja same upisuju i u Google tablicu.</p>';
	}

	public static function side( $post ) {
		$m    = Plan_A_Clanstvo_Data::get_member( $post->ID );
		$sync = (string) get_post_meta( $post->ID, '_pac_sync', true );
		echo '<p><strong>Br. člana:</strong> ' . (int) $m['broj'] . '</p>'
			. '<p><strong>Status:</strong> ' . ( 'potvrdeno' === $m['status'] ? '<span style="color:#1e7d3a">✔ Potvrđeno</span>' : '<span style="color:#b26a00">⏳ Čeka potvrdu</span>' ) . '</p>'
			. '<p><strong>Prijava:</strong> ' . esc_html( Plan_A_Clanstvo_Data::hr_datetime( $m['created'] ) ) . '</p>'
			. '<p><strong>Potvrda:</strong> ' . esc_html( Plan_A_Clanstvo_Data::hr_datetime( $m['confirmed'] ) ?: '–' ) . '</p>'
			. '<p><strong>Google tablica:</strong> ' . ( 'ok' === $sync ? 'upisano' : ( 'pending' === $sync ? 'čeka slanje (ponavlja se svaki sat)' : '–' ) ) . '</p>'
			. '<p><button type="submit" class="button button-primary">Spremi</button></p><hr>';
		if ( 'potvrdeno' !== $m['status'] ) {
			echo '<p><a class="button" href="' . esc_url( self::action_url( 'resend', $post->ID ) ) . '">Pošalji ponovno e-mail za potvrdu</a></p>'
				. '<p><a class="button" href="' . esc_url( self::action_url( 'confirm', $post->ID ) ) . '" onclick="return confirm(\'Potvrditi pristupnicu ručno?\')">Potvrdi ručno</a></p>';
		} else {
			echo '<p><a class="button" href="' . esc_url( self::action_url( 'paymail', $post->ID ) ) . '">Pošalji uplatnicu za članarinu ' . (int) Plan_A_Clanstvo_Data::year() . '.</a></p>';
		}
		echo '<p><a class="submitdelete" style="color:#b32d2e" href="' . esc_url( get_delete_post_link( $post->ID, '', true ) ) . '" onclick="return confirm(\'Trajno obrisati člana (i red u tablici)?\')">Obriši člana</a></p>';
	}

	public static function save( $id, $post ) {
		if ( ! isset( $_POST['pac_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pac_nonce'] ) ), 'pac_save_' . $id ) || ! current_user_can( self::CAP ) ) {
			return;
		}
		$in = isset( $_POST['pac'] ) ? (array) wp_unslash( $_POST['pac'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- čisti se ispod.
		foreach ( array_keys( Plan_A_Clanstvo_Data::FIELDS ) as $k ) {
			if ( ! isset( $in[ $k ] ) ) {
				continue;
			}
			$v = sanitize_text_field( (string) $in[ $k ] );
			if ( 'datum' === $k ) {
				$v = Plan_A_Clanstvo_Data::parse_date( $v ) ?: (string) get_post_meta( $id, '_pac_datum', true );
			} elseif ( 'email' === $k ) {
				$v = strtolower( sanitize_email( $v ) );
			} elseif ( 'oib' === $k ) {
				$v = preg_replace( '/\D/', '', $v );
			}
			update_post_meta( $id, '_pac_' . $k, $v );
		}
		$title = trim( sanitize_text_field( (string) ( $in['ime'] ?? '' ) ) . ' ' . sanitize_text_field( (string) ( $in['prezime'] ?? '' ) ) );
		if ( '' !== $title && $title !== $post->post_title ) {
			remove_action( 'save_post_' . Plan_A_Clanstvo_Data::CPT, array( __CLASS__, 'save' ), 10 );
			wp_update_post(
				array(
					'ID'         => $id,
					'post_title' => $title,
				)
			);
		}
		do_action( 'plan_a_clanstvo_changed', $id );
	}

	/* ---------------------------------------------------------------------
	 * Radnje
	 * ------------------------------------------------------------------- */

	public static function action() {
		$do = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : ( isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification
		$id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		check_admin_referer( 'pac_admin_' . $do . '_' . $id );
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Nemaš ovlasti.' );
		}
		$back = wp_get_referer() ?: self::url();
		$msg  = '';
		switch ( $do ) {
			case 'resend':
				$msg = Plan_A_Clanstvo_Mail::confirm_request( $id ) ? 'E-mail za potvrdu je poslan.' : 'E-mail nije poslan.';
				break;
			case 'confirm':
				if ( Plan_A_Clanstvo_Data::confirm( $id ) ) {
					Plan_A_Clanstvo_Mail::confirmed( $id );
				}
				$msg = 'Pristupnica je potvrđena i članu je poslan e-mail s uplatnicom.';
				break;
			case 'paymail':
				$msg = Plan_A_Clanstvo_Mail::confirmed( $id ) ? 'Uplatnica za članarinu je poslana.' : 'E-mail nije poslan.';
				break;
			case 'settings':
				self::save_settings();
				$msg = 'Postavke su spremljene.';
				break;
			case 'ping':
				$r   = Plan_A_Clanstvo_Sheets::post( array( 'action' => 'ping' ) );
				$msg = $r['ok'] ? 'Veza radi. Tablica: ' . ( $r['name'] ?? '' ) . ' (list „Članovi”).' : 'Veza ne radi: ' . ( $r['error'] ?? '' );
				break;
			case 'resync':
				$r   = Plan_A_Clanstvo_Sheets::bulk( false );
				$msg = $r['ok'] ? 'U tablicu je poslano članova: ' . $r['n'] . '.' : 'Slanje nije uspjelo: ' . ( $r['error'] ?? '' );
				break;
			case 'secret':
				$s           = Plan_A_Clanstvo_Data::get();
				$s['secret'] = wp_generate_password( 32, false );
				Plan_A_Clanstvo_Data::set( $s );
				$msg = 'Napravljen je novi ključ. Kopiraj novu skriptu u tablicu i ponovno je objavi (Deploy → Manage deployments → Edit → New version).';
				break;
			case 'export':
				self::export();
				exit;
		}
		set_transient( 'pac_notice_' . get_current_user_id(), $msg, 60 );
		wp_safe_redirect( $back );
		exit;
	}

	public static function notices() {
		$msg = get_transient( 'pac_notice_' . get_current_user_id() );
		if ( $msg ) {
			delete_transient( 'pac_notice_' . get_current_user_id() );
			echo '<div class="notice notice-info is-dismissible pac-notice"><p>' . esc_html( $msg ) . '</p></div>';
		}
	}

	private static function save_settings() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- provjereno u action().
		$s = Plan_A_Clanstvo_Data::get();
		foreach ( array( 'primatelj', 'adresa', 'mjesto', 'poziv', 'opis', 'from_name', 'naslov', 'kontakt' ) as $k ) {
			$s[ $k ] = sanitize_text_field( wp_unslash( $_POST[ $k ] ?? '' ) ) ?: Plan_A_Clanstvo_Data::defaults()[ $k ];
		}
		$url            = esc_url_raw( trim( wp_unslash( $_POST['sheet_url'] ?? '' ) ) );
		$s['sheet_url'] = preg_match( '#^https://script\.google(usercontent)?\.com/#', $url ) ? $url : '';
		$iban           = strtoupper( preg_replace( '/\s+/', '', sanitize_text_field( wp_unslash( $_POST['iban'] ?? '' ) ) ) );
		$s['iban']      = preg_match( '/^HR\d{19}$/', $iban ) ? $iban : $s['iban'];
		$s['model']     = preg_match( '/^\d{2}$/', (string) ( $_POST['model'] ?? '' ) ) ? (string) $_POST['model'] : '00'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$s['iznos']     = max( 0, round( (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['iznos'] ?? '15' ) ) ), 2 ) );
		$s['barcode']   = in_array( $_POST['barcode'] ?? '', array( 'osobni', 'slika', 'ne' ), true ) ? (string) $_POST['barcode'] : 'osobni'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$s['barcode_slika'] = esc_url_raw( wp_unslash( $_POST['barcode_slika'] ?? '' ) );
		$s['izjava']        = sanitize_textarea_field( wp_unslash( $_POST['izjava'] ?? '' ) );
		$s['uvod']          = sanitize_textarea_field( wp_unslash( $_POST['uvod'] ?? '' ) );
		$s['pogodnosti']    = sanitize_textarea_field( wp_unslash( $_POST['pogodnosti'] ?? '' ) );
		$s['provjera']      = empty( $_POST['provjera'] ) ? 0 : 1;
		$s['valid_days']    = max( 1, min( 60, absint( $_POST['valid_days'] ?? 7 ) ) );
		$s['remind_days']   = max( 1, min( 30, absint( $_POST['remind_days'] ?? 3 ) ) );
		$s['delete_days']   = max( 7, min( 365, absint( $_POST['delete_days'] ?? 30 ) ) );
		// phpcs:enable
		Plan_A_Clanstvo_Data::set( $s );
	}

	private static function export() {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=clanovi-plan-a-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- BOM za Excel.
		fputcsv( $out, array_merge( array( 'Br.', 'Datum prijave' ), array_values( Plan_A_Clanstvo_Data::FIELDS ), array( 'Status', 'Datum potvrde' ) ), ';' );
		$ids = get_posts(
			array(
				'post_type'      => Plan_A_Clanstvo_Data::CPT,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => '_pac_broj', // phpcs:ignore WordPress.DB.SlowDBQuery
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
			)
		);
		foreach ( $ids as $id ) {
			$m   = Plan_A_Clanstvo_Data::get_member( (int) $id );
			$row = array( $m['broj'], Plan_A_Clanstvo_Data::hr_datetime( $m['created'] ) );
			foreach ( array_keys( Plan_A_Clanstvo_Data::FIELDS ) as $k ) {
				$v     = 'datum' === $k ? Plan_A_Clanstvo_Data::hr_date( $m[ $k ] ) : $m[ $k ];
				$row[] = preg_match( '/^[=+\-@]/', $v ) ? "'" . $v : $v; // zaštita od formula u Excelu
			}
			$row[] = 'potvrdeno' === $m['status'] ? 'Potvrđeno' : 'Čeka potvrdu';
			$row[] = Plan_A_Clanstvo_Data::hr_datetime( $m['confirmed'] );
			fputcsv( $out, $row, ';' );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/* ---------------------------------------------------------------------
	 * Stranica postavki
	 * ------------------------------------------------------------------- */

	public static function page() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$s     = Plan_A_Clanstvo_Data::get();
		$text  = static function ( $k, $label, $help = '', $cls = 'regular-text' ) use ( $s ) {
			echo '<tr><th><label for="pac-' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td><input type="text" class="' . esc_attr( $cls ) . '" id="pac-' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" value="' . esc_attr( (string) $s[ $k ] ) . '">' . ( $help ? '<p class="description">' . wp_kses_post( $help ) . '</p>' : '' ) . '</td></tr>';
		};
		$page  = (int) get_option( Plan_A_Clanstvo_Data::PAGE );
		$count = wp_count_posts( Plan_A_Clanstvo_Data::CPT );
		?>
		<div class="wrap">
			<h1>Plan A članstvo</h1>
			<p>Pristupnica se prikazuje shortcodeom <code>[plan-a-pristupnica]</code> (u Flatsome HTML bloku). <?php echo $page ? 'Stranica: <a href="' . esc_url( get_permalink( $page ) ) . '" target="_blank">' . esc_html( get_the_title( $page ) ) . '</a>.' : 'Shortcode još nije ni na jednoj stranici.'; ?> Članova: <?php echo (int) ( $count->publish ?? 0 ); ?>.</p>
			<p>
				<a class="button" href="<?php echo esc_url( self::action_url( 'export' ) ); ?>">Preuzmi popis (CSV za Excel)</a>
			</p>

			<h2>1. Google tablica</h2>
			<ol>
				<li>U Google Driveu napravi novu praznu tablicu (npr. „Članovi Plan A”).</li>
				<li>U tablici otvori <strong>Proširenja → Apps Script</strong>, obriši sve što piše i zalijepi skriptu ispod (gumb „Kopiraj skriptu”). Klikni ikonu diskete (Spremi).</li>
				<li>Gore desno klikni <strong>Deploy → New deployment</strong>, kotačić → <strong>Web app</strong>. „Execute as”: <strong>Me</strong>, „Who has access”: <strong>Anyone</strong>. Klikni Deploy i dopusti pristup svom Google računu.</li>
				<li>Kopiraj <strong>Web app URL</strong> (završava na /exec), zalijepi ga u polje ispod, spremi i klikni <strong>Provjeri vezu</strong>.</li>
				<li>Klikni <strong>Pošalji sve članove u tablicu</strong>. Dalje se tablica puni sama.</li>
			</ol>
			<p><textarea id="pac-script" class="large-text code" rows="8" readonly><?php echo esc_textarea( Plan_A_Clanstvo_Sheets::script() ); ?></textarea></p>
			<p>
				<button type="button" class="button" onclick="var t=document.getElementById('pac-script');t.select();navigator.clipboard?navigator.clipboard.writeText(t.value):document.execCommand('copy');this.textContent='Kopirano ✔';">Kopiraj skriptu</button>
				<a class="button" href="<?php echo esc_url( self::action_url( 'ping' ) ); ?>">Provjeri vezu</a>
				<a class="button" href="<?php echo esc_url( self::action_url( 'resync' ) ); ?>">Pošalji sve članove u tablicu</a>
				<a class="button-link" style="margin-left:12px" href="<?php echo esc_url( self::action_url( 'secret' ) ); ?>" onclick="return confirm('Napraviti novi ključ? Poslije treba ponovno zalijepiti skriptu u tablicu.')">Novi ključ</a>
			</p>
			<p class="description">Smjer je stranica → tablica: promjene u tablici (osim kvačica za članarinu) ne vraćaju se na stranicu. Stupce „Članarina GGGG” stranica ne dira, a stupac za novu godinu dodaje se sam u siječnju. Stupac „Br.” ne briši.</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="pac_admin">
				<input type="hidden" name="do" value="settings">
				<input type="hidden" name="id" value="0">
				<?php wp_nonce_field( 'pac_admin_settings_0' ); ?>
				<table class="form-table">
					<?php $text( 'sheet_url', 'Web app URL tablice', 'npr. https://script.google.com/macros/s/…/exec', 'large-text' ); ?>
				</table>

				<h2>2. Članarina i 2D kod</h2>
				<table class="form-table">
					<?php
					$text( 'iznos', 'Iznos (€)', '', 'small-text' );
					$text( 'primatelj', 'Primatelj' );
					$text( 'adresa', 'Adresa primatelja' );
					$text( 'mjesto', 'Poštanski broj i mjesto' );
					$text( 'iban', 'IBAN udruge', 'Račun udruge Plan A (ne agencije).' );
					$text( 'model', 'Model', 'Bez „HR”, npr. 00.', 'small-text' );
					$text( 'poziv', 'Poziv na broj', '{godina} se zamjenjuje tekućom godinom, npr. 1-{godina} → 1-' . (int) Plan_A_Clanstvo_Data::year() . '.' );
					$text( 'opis', 'Opis plaćanja', 'Najviše 35 znakova. {godina} = tekuća godina.' );
					?>
					<tr><th>2D kod u e-mailu</th><td>
						<label><input type="radio" name="barcode" value="osobni" <?php checked( $s['barcode'], 'osobni' ); ?>> Osobni kod za svakog člana (ime platitelja, poziv na broj i opis za tekuću godinu) – preporučeno</label><br>
						<label><input type="radio" name="barcode" value="slika" <?php checked( $s['barcode'], 'slika' ); ?>> Ista slika za sve (adresa slike ispod)</label><br>
						<label><input type="radio" name="barcode" value="ne" <?php checked( $s['barcode'], 'ne' ); ?>> Bez 2D koda, samo podaci za uplatu</label>
					</td></tr>
					<?php $text( 'barcode_slika', 'Slika 2D koda', 'Koristi se za „Ista slika za sve” i kao rezerva ako servis za osobni kod ne radi.', 'large-text' ); ?>
				</table>

				<h2>3. Pristupnica</h2>
				<table class="form-table">
					<?php $text( 'naslov', 'Naslov stranice', '', 'large-text' ); ?>
					<tr><th><label for="pac-uvod">Uvod</label></th><td><textarea id="pac-uvod" name="uvod" class="large-text" rows="3"><?php echo esc_textarea( (string) $s['uvod'] ); ?></textarea></td></tr>
					<tr><th><label for="pac-pogodnosti">Članstvo donosi</label></th><td><textarea id="pac-pogodnosti" name="pogodnosti" class="large-text" rows="4"><?php echo esc_textarea( (string) $s['pogodnosti'] ); ?></textarea><p class="description">Jedna pogodnost u retku.</p></td></tr>
					<?php $text( 'kontakt', 'Kontakt na dnu', 'npr. Igor, 095 90 60 556 · info@srd-plan-a.hr', 'large-text' ); ?>
					<tr><th><label for="pac-izjava">Izjava člana</label></th><td><textarea id="pac-izjava" name="izjava" class="large-text" rows="10"><?php echo esc_textarea( (string) $s['izjava'] ); ?></textarea><p class="description">Svaki odlomak u svom retku. Kratki redak bez točke na kraju prikazuje se kao podnaslov.</p></td></tr>
					<tr><th>Prijava na izlet</th><td><label><input type="checkbox" name="provjera" value="1" <?php checked( (int) $s['provjera'], 1 ); ?>> Provjeri članstvo po e-mailu kupca (napomena na stranici „Hvala” i u e-mailu, stupac „Član” u narudžbama)</label></td></tr>
					<tr><th>Rokovi (dana)</th><td>
						poveznica vrijedi <input type="number" min="1" max="60" name="valid_days" value="<?php echo (int) $s['valid_days']; ?>" style="width:70px">
						· podsjetnik nakon <input type="number" min="1" max="30" name="remind_days" value="<?php echo (int) $s['remind_days']; ?>" style="width:70px">
						· nepotvrđene se brišu nakon <input type="number" min="7" max="365" name="delete_days" value="<?php echo (int) $s['delete_days']; ?>" style="width:70px">
					</td></tr>
				</table>
				<p><button type="submit" class="button button-primary">Spremi postavke</button></p>
			</form>
		</div>
		<?php
	}
}
