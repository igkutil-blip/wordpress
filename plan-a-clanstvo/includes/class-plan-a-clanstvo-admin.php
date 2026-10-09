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
		add_action( 'wp_ajax_pac_import_step', array( __CLASS__, 'import_step' ) );
		add_filter( "manage_{$cpt}_posts_columns", array( __CLASS__, 'columns' ) );
		add_action( "manage_{$cpt}_posts_custom_column", array( __CLASS__, 'column' ), 10, 2 );
		add_filter( "manage_edit-{$cpt}_sortable_columns", array( __CLASS__, 'sortable' ) );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'filter_ui' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'query' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_filter( "bulk_actions-edit-{$cpt}", array( __CLASS__, 'bulk_actions' ) );
		add_filter( "handle_bulk_actions-edit-{$cpt}", array( __CLASS__, 'bulk_handle' ), 10, 3 );
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
		add_submenu_page( 'edit.php?post_type=' . Plan_A_Clanstvo_Data::CPT, 'Uvoz članova', 'Uvoz članova', self::CAP, self::SLUG . '-uvoz', array( __CLASS__, 'import_page' ) );
	}

	public static function import_page() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$left  = Plan_A_Clanstvo_Sheets::pending();
		$job   = Plan_A_Clanstvo_Import::job();
		$last  = get_option( Plan_A_Clanstvo_Import::LAST );
		$count = wp_count_posts( Plan_A_Clanstvo_Data::CPT );
		?>
		<div class="wrap">
			<h1>Uvoz članova iz CSV-a</h1>
			<p>Članova na stranici: <strong><?php echo (int) ( $count->publish ?? 0 ); ?></strong><?php echo $left ? ' · još nije poslano u Google tablicu: <strong>' . (int) $left . '</strong>' : ''; ?>.
			<?php if ( is_array( $last ) ) : ?>
				Zadnji uvoz: <?php echo esc_html( Plan_A_Clanstvo_Data::hr_datetime( (string) $last['time'] ) . sprintf( ' – novih %d, ažuriranih %d, preskočenih %d', $last['stats']['new'] ?? 0, $last['stats']['updated'] ?? 0, $last['stats']['skipped'] ?? 0 ) ); ?>.
			<?php endif; ?>
			</p>
			<?php if ( $job ) : ?>
				<div id="pac-import" style="max-width:640px;background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:16px 18px;margin:16px 0" data-nonce="<?php echo esc_attr( wp_create_nonce( 'pac_import_step' ) ); ?>">
					<h2 style="margin-top:0"><?php echo $job['rows'] ? 'Uvoz u tijeku' : 'Slanje u Google tablicu'; ?> – ne zatvaraj ovu stranicu</h2>
					<div style="height:18px;background:#f0f0f1;border-radius:9px;overflow:hidden"><div id="pac-bar" style="height:100%;width:0;background:#2271b1;transition:width .3s"></div></div>
					<p id="pac-status">Počinjem…</p>
					<?php if ( Plan_A_Clanstvo_Sheets::old_script() ) : ?>
						<p style="color:#996800">Tablica je zadnji put odgovorila sporo ili ima staru skriptu, pa se šalje u malim paketima. Za brže slanje u tablicu stavi novu skriptu i objavi je kao „New version”.</p>
					<?php endif; ?>
					<p id="pac-error" style="color:#d63638;font-weight:600"></p>
					<p><button type="button" class="button" id="pac-retry" style="display:none">Nastavi</button>
					<a class="button-link" style="margin-left:10px;color:#b32d2e" href="<?php echo esc_url( self::action_url( 'import_cancel' ) ); ?>" onclick="return confirm('Prekinuti uvoz? Već uvezeni članovi ostaju.')">Prekini uvoz</a></p>
				</div>
				<script>
				(function () {
					var box = document.getElementById('pac-import'), bar = document.getElementById('pac-bar'), st = document.getElementById('pac-status'), er = document.getElementById('pac-error'), btn = document.getElementById('pac-retry'), fails = 0;
					function show(d) {
						var s = d.stats || {};
						if (!window.pacStart && d.phase === 'tablica') window.pacStart = d.pending + 50;
						var pct = d.total ? Math.round(d.pos / d.total * 70) : 0;
						if (d.phase === 'tablica' || (d.pos >= d.total && d.pending)) { var all = Math.max(d.total, window.pacStart || 1); pct = (d.total ? 70 : 0) + Math.round((d.total ? 30 : 100) * (1 - d.pending / all)); }
						bar.style.width = Math.min(100, pct) + '%';
						st.textContent = (d.phase === 'clanovi' ? 'Upisujem članove: ' + d.pos + ' / ' + d.total : 'Šaljem u Google tablicu, preostalo: ' + d.pending) + ' (novih ' + (s.new || 0) + ', ažuriranih ' + (s.updated || 0) + ')';
					}
					function step() {
						er.textContent = ''; btn.style.display = 'none';
						var f = new FormData(); f.append('action', 'pac_import_step'); f.append('nonce', box.dataset.nonce);
						fetch(ajaxurl, { method: 'POST', body: f, credentials: 'same-origin' }).then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); }).then(function (j) {
							if (!j || !j.success) throw new Error(j && j.data ? j.data : 'nepoznata greška');
							var d = j.data; fails = 0; show(d);
							if (d.error) { er.textContent = d.error; btn.style.display = ''; return; }
							if (d.done) { bar.style.width = '100%'; st.textContent = !d.total ? 'Gotovo! Svi članovi su poslani u Google tablicu.' : 'Gotovo! Novih ' + d.stats.new + ', ažuriranih ' + d.stats.updated + ', preskočenih ' + d.stats.skipped + '. E-mailovi nisu slani.'; setTimeout(function () { location.reload(); }, 2500); return; }
							step();
						}).catch(function (e) {
							if (++fails < 4) { setTimeout(step, 2000 * fails); return; }
							er.textContent = 'Greška: ' + e.message + '. Klikni „Nastavi” – uvoz nastavlja gdje je stao.'; btn.style.display = '';
						});
					}
					btn.addEventListener('click', function () { fails = 0; step(); });
					step();
				})();
				</script>
			<?php else : ?>
			<?php if ( '' === Plan_A_Clanstvo_Sheets::url() ) : ?>
				<div class="notice notice-warning"><p>Google tablica još nije povezana (Članovi → Postavke i tablica). Članovi će se uvesti, a u tablicu poslati kad je povežeš.</p></div>
			<?php endif; ?>
			<p><strong>Prije prvog uvoza</strong> u Google tablicu zalijepi novu skriptu i objavi novu verziju (Postavke i tablica → Kopiraj skriptu; u tablici Proširenja → Apps Script; Deploy → Manage deployments → Edit → New version).</p>
			<p>Prvi redak su naslovi stupaca: <code>Datum prijave, Datum potvrde, Ime, Prezime, Datum rođenja, OIB, Adresa, Mjesto, E-mail, Mobitel, Status, Članarina, Iskaznica, Napomena</code> (obavezni su samo Ime i Prezime; razdvojeno zarezom ili točka-zarezom). „Članarina” su godine, npr. <code>2024, 2025</code>.</p>
			<p class="description">Uvoz <strong>ne šalje e-mailove</strong>. Isti član se ne duplira nego ažurira (isti OIB; ili isti e-mail, ime i datum rođenja; ili, kad nema ni OIB-a ni e-maila, isto ime i prezime). Prazna polja u datoteci ne brišu postojeće podatke. Kvačice za članarine i iskaznicu te napomena upisuju se u tablicu samo jednom, a postojeće kvačice se ne skidaju.</p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Uvozim… (može potrajati minutu-dvije)';">
				<input type="hidden" name="action" value="pac_admin">
				<input type="hidden" name="do" value="import">
				<input type="hidden" name="id" value="0">
				<?php wp_nonce_field( 'pac_admin_import_0' ); ?>
				<p><label for="pac-csv"><strong>1. Odaberi datoteku</strong> (npr. uvoz-clanova.csv):</label><br><input type="file" id="pac-csv" name="csv" accept=".csv,text/csv" required></p>
				<p><strong>2.</strong> 
				<button type="submit" class="button button-primary">Uvezi članove</button></p>
			</form>
			<?php if ( $left ) : ?>
				<p><a class="button button-primary" href="<?php echo esc_url( self::action_url( 'pending' ) ); ?>">Pošalji neposlane u tablicu (<?php echo (int) $left; ?>)</a></p>
			<?php endif; ?>
			<h2>Tablica za agenciju: početni popis</h2>
			<p>Jednom, za prijave koje su do sada vođene ručno: CSV sa stupcima <code>Ključ izleta, Izlet, Datum, Godina, Ime, Prezime, OIB, Datum rođenja, Adresa, Mjesto, Mobitel, E-mail, Prijavio/la, Iznos, Osiguranje, Uplaćeno, Otkazano, Napomena, Br. člana</code>. Prvi izlet u datoteci bit će na vrhu. Ponovni uvoz iste datoteke ne duplira redove.</p>
			<?php if ( Plan_A_Clanstvo_Agency::enabled() ) : ?>
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Upisujem… (može potrajati minutu)';">
					<input type="hidden" name="action" value="pac_admin">
					<input type="hidden" name="do" value="agency_import">
					<input type="hidden" name="id" value="0">
					<?php wp_nonce_field( 'pac_admin_agency_import_0' ); ?>
					<input type="file" name="csv" accept=".csv,text/csv" required>
					<button type="submit" class="button">Upiši u tablicu za agenciju</button>
				</form>
			<?php else : ?>
				<p class="description">Najprije u Postavkama upiši poveznicu tablice za agenciju.</p>
			<?php endif; ?>
			<h2>Redoslijed brojeva</h2>
			<p><a class="button" href="<?php echo esc_url( self::action_url( 'renumber' ) ); ?>" onclick="return confirm('Poredati brojeve članova (Br.) po datumu prijave, na stranici i u Google tablici?')">Poredaj brojeve po datumu prijave</a> <span class="description">Br. 1 dobiva član s najranijim datumom prijave. Redovi u tablici se poredaju po broju, a kvačice i napomene idu sa svojim redom.</span></p>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function import_step() {
		check_ajax_referer( 'pac_import_step', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( 'Nemaš ovlasti.', 403 );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 120 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions
		}
		wp_send_json_success( Plan_A_Clanstvo_Import::step() );
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

	public static function bulk_actions( $actions ) {
		unset( $actions['edit'] );
		$actions['pac_resend'] = 'Pošalji e-mail za potvrdu (nepotvrđenima)';
		return $actions;
	}

	public static function bulk_handle( $redirect, $action, $ids ) {
		if ( 'pac_resend' !== $action || ! current_user_can( self::CAP ) ) {
			return $redirect;
		}
		$n = 0;
		foreach ( (array) $ids as $id ) {
			if ( 'potvrdeno' !== get_post_meta( (int) $id, '_pac_status', true ) && Plan_A_Clanstvo_Mail::confirm_request( (int) $id ) ) {
				$n++;
			}
		}
		set_transient( 'pac_notice_' . get_current_user_id(), 'E-mail za potvrdu poslan je na ' . $n . ' adresa.', 60 );
		return $redirect;
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
				if ( $r['ok'] ) {
					$old = (int) ( $r['v'] ?? 0 ) < Plan_A_Clanstvo_Sheets::SCRIPT_VERSION;
					update_option( 'plan_a_clanstvo_script_old', $old ? 1 : 0, false );
					$msg = 'Veza radi. Tablica: ' . ( $r['name'] ?? '' ) . ' (list „Članovi”). ' . ( $old ? 'POZOR: u tablici je STARA skripta – kopiraj novu i objavi je kao „New version” (Deploy → Manage deployments → Edit).' : 'Skripta je nova (v' . (int) $r['v'] . ').' );
				} else {
					$msg = 'Veza ne radi: ' . ( $r['error'] ?? '' );
				}
				break;
			case 'agency':
				$r   = Plan_A_Clanstvo_Agency::cron();
				$msg = $r['ok'] ? 'Tablica za agenciju je osvježena (redova: ' . $r['n'] . ').' : 'Tablica za agenciju: ' . ( $r['error'] ?? 'greška' );
				break;
			case 'agency_import':
				$f = $_FILES['csv'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				if ( ! $f || UPLOAD_ERR_OK !== (int) $f['error'] || ! is_uploaded_file( (string) $f['tmp_name'] ) || ! preg_match( '/\.csv$/i', (string) $f['name'] ) ) {
					$msg = 'Odaberi CSV datoteku.';
					break;
				}
				if ( function_exists( 'set_time_limit' ) ) {
					set_time_limit( 300 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions
				}
				$r   = Plan_A_Clanstvo_Agency::import( (string) $f['tmp_name'] );
				$msg = $r['ok'] ? 'U tablicu za agenciju upisano je izleta: ' . $r['tours'] . ', osoba: ' . $r['n'] . '.' : 'Uvoz u tablicu za agenciju nije uspio: ' . ( $r['error'] ?? '' );
				break;
			case 'pull':
				$r   = Plan_A_Clanstvo_Sheets::pull();
				$msg = $r['ok'] ? 'Kvačice za članarine i iskaznice pročitane su iz tablice (članova: ' . $r['n'] . ').' : 'Čitanje tablice nije uspjelo: ' . $r['error'];
				break;
			case 'resync':
				foreach ( get_posts( array( 'post_type' => Plan_A_Clanstvo_Data::CPT, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) ) as $pid ) {
					update_post_meta( (int) $pid, '_pac_sync', 'pending' );
				}
				Plan_A_Clanstvo_Import::start_sheet();
				wp_safe_redirect( admin_url( 'edit.php?post_type=' . Plan_A_Clanstvo_Data::CPT . '&page=' . self::SLUG . '-uvoz' ) );
				exit;
			case 'pending':
				Plan_A_Clanstvo_Import::start_sheet();
				wp_safe_redirect( admin_url( 'edit.php?post_type=' . Plan_A_Clanstvo_Data::CPT . '&page=' . self::SLUG . '-uvoz' ) );
				exit;
			case 'import':
				$f = $_FILES['csv'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				if ( ! $f || UPLOAD_ERR_OK !== (int) $f['error'] || ! is_uploaded_file( (string) $f['tmp_name'] ) ) {
					$msg = 'Datoteka nije stigla na stranicu' . ( $f && UPLOAD_ERR_OK !== (int) $f['error'] ? ' (greška ' . (int) $f['error'] . ')' : '' ) . '. Odaberi CSV datoteku i pokušaj ponovno.';
					break;
				}
				if ( ! preg_match( '/\.(csv|txt)$/i', (string) $f['name'] ) ) {
					$msg = 'Odabrana datoteka nije CSV (' . sanitize_file_name( (string) $f['name'] ) . ').';
					break;
				}
				$r = Plan_A_Clanstvo_Import::start( (string) $f['tmp_name'] );
				if ( ! $r['ok'] ) {
					$msg = 'Uvoz nije uspio: ' . $r['error'];
					break;
				}
				wp_safe_redirect( admin_url( 'edit.php?post_type=' . Plan_A_Clanstvo_Data::CPT . '&page=' . self::SLUG . '-uvoz' ) );
				exit;
			case 'renumber':
				if ( Plan_A_Clanstvo_Import::job() || Plan_A_Clanstvo_Sheets::pending() ) {
					$msg = 'Najprije pošalji sve članove u tablicu (gumb „Pošalji neposlane u tablicu”), pa onda poredaj brojeve.';
					break;
				}
				$r   = Plan_A_Clanstvo_Data::renumber();
				$msg = $r['ok'] ? ( $r['n'] ? 'Brojevi su poredani po datumu prijave (promijenjeno: ' . $r['n'] . '), na stranici i u tablici.' : 'Brojevi su već poredani po datumu prijave.' ) : 'Brojevi nisu promijenjeni: ' . $r['error'];
				break;
			case 'import_cancel':
				Plan_A_Clanstvo_Import::cancel();
				$msg = 'Uvoz je prekinut. Već uvezeni članovi ostaju.';
				break;
			case 'secret':
				$s           = Plan_A_Clanstvo_Data::get();
				$s['secret'] = wp_generate_password( 32, false );
				Plan_A_Clanstvo_Data::set( $s );
				$msg = 'Napravljen je novi ključ. Kopiraj novu skriptu u tablicu i ponovno je objavi (Deploy → Manage deployments → Edit → New version).';
				break;
			case 'testmail':
				$to  = wp_get_current_user()->user_email;
				$ok  = Plan_A_Clanstvo_Mail::send( $to, 'Plan A – probni e-mail pristupnice', Plan_A_Clanstvo_Mail::wrap( 'Probni e-mail', Plan_A_Clanstvo_Mail::p( 'Ako čitaš ovo, e-mailovi pristupnice stižu.' ) ) );
				$h   = Plan_A_Clanstvo_Mail::headers();
				$msg = $ok ? 'Probni e-mail je predan na slanje na ' . $to . ' (pošiljatelj: ' . substr( $h[1], 6 ) . '). Ako ne stigne za nekoliko minuta, provjeri SMTP postavke stranice.' : 'Slanje nije uspjelo: ' . (string) get_option( 'plan_a_clanstvo_mail_error', '' );
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
		$ag              = esc_url_raw( trim( wp_unslash( $_POST['agency_url'] ?? '' ) ) );
		$s['agency_url'] = preg_match( '#^https://docs\.google\.com/spreadsheets/d/[A-Za-z0-9_-]{20,}#', $ag ) ? $ag : '';
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
		$s['remind_days']   = min( 30, absint( $_POST['remind_days'] ?? 3 ) );
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
				<a class="button" href="<?php echo esc_url( self::action_url( 'testmail' ) ); ?>">Pošalji mi probni e-mail</a>
				<?php
				$paj_last = get_option( 'plan_a_clanstvo_last_mail' );
				if ( is_array( $paj_last ) ) {
					echo '<span class="description" style="margin-left:8px">Zadnji e-mail: ' . esc_html( Plan_A_Clanstvo_Data::hr_datetime( (string) $paj_last['time'] ) . ' → ' . $paj_last['to'] . ( $paj_last['ok'] ? ' (predan na slanje)' : ' – GREŠKA: ' . $paj_last['error'] ) ) . '</span>';
				}
				?>
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
				<a class="button" href="<?php echo esc_url( self::action_url( 'pull' ) ); ?>">Osvježi kvačice iz tablice</a>
				<?php if ( Plan_A_Clanstvo_Agency::enabled() ) : ?>
					<a class="button" href="<?php echo esc_url( self::action_url( 'agency' ) ); ?>">Osvježi tablicu za agenciju</a>
				<?php endif; ?>
				<?php $paj_left = Plan_A_Clanstvo_Sheets::pending(); ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Plan_A_Clanstvo_Data::CPT . '&page=' . self::SLUG . '-uvoz' ) ); ?>">Uvoz članova (CSV)</a>
				<?php if ( $paj_left ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( self::action_url( 'pending' ) ); ?>">Pošalji neposlane u tablicu (<?php echo (int) $paj_left; ?>)</a>
				<?php endif; ?>
				<a class="button-link" style="margin-left:12px" href="<?php echo esc_url( self::action_url( 'secret' ) ); ?>" onclick="return confirm('Napraviti novi ključ? Poslije treba ponovno zalijepiti skriptu u tablicu.')">Novi ključ</a>
			</p>
			<?php $pac_pull = get_option( 'plan_a_clanstvo_pull' ); ?>
			<p class="description">Kvačice „Članarina GGGG” i „Iskaznica uručena” stranica čita iz tablice svaki sat<?php echo is_array( $pac_pull ) ? ' (zadnje čitanje: ' . esc_html( Plan_A_Clanstvo_Data::hr_datetime( (string) $pac_pull['time'] ) ) . ')' : ''; ?>; nakon ručnog označavanja klikni „Osvježi kvačice iz tablice” ako ti treba odmah.</p>
			<p class="description">Smjer je stranica → tablica: promjene u tablici (osim kvačica za članarinu) ne vraćaju se na stranicu. Stupce „Članarina GGGG” i „Iskaznica uručena” stranica ne dira (kvačice se označavaju ručno), a stupac za novu godinu dodaje se sam u siječnju, ispred stupca za iskaznicu. Stupac „Br.” ne briši.</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="pac_admin">
				<input type="hidden" name="do" value="settings">
				<input type="hidden" name="id" value="0">
				<?php wp_nonce_field( 'pac_admin_settings_0' ); ?>
				<table class="form-table">
					<?php $text( 'sheet_url', 'Web app URL tablice', 'npr. https://script.google.com/macros/s/…/exec', 'large-text' ); ?>
					<?php $text( 'agency_url', 'Tablica za agenciju', 'Poveznica Google tablice „Prijave na izlete” (iz adresne trake, https://docs.google.com/spreadsheets/d/…). Tablicu mora moći uređivati isti Google račun koji je objavio skriptu. List „Prijave” napravi se sam.', 'large-text' ); ?>
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
						· jedan podsjetnik nakon <input type="number" min="0" max="30" name="remind_days" value="<?php echo (int) $s['remind_days']; ?>" style="width:70px"> (0 = bez podsjetnika)
						<p class="description">Nepotvrđene pristupnice se nikad ne brišu same; ostaju sa statusom „Čeka potvrdu”.</p>
					</td></tr>
				</table>
				<p><button type="submit" class="button button-primary">Spremi postavke</button></p>
			</form>

		</div>
		<?php
	}
}
