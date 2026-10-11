<?php
/**
 * Sučelje u adminu: popis newslettera, pisanje, proba, slanje, postavke i odjave.
 *
 * @package Plan_A_Newsletter
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Newsletter_Admin {

	const SLUG = 'plan-a-newsletter';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_pan_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_pan_control', array( __CLASS__, 'control' ) );
		add_action( 'admin_post_pan_preview', array( __CLASS__, 'preview' ) );
		add_action( 'admin_post_pan_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_pan_optout', array( __CLASS__, 'optout' ) );
		add_action( 'wp_ajax_pan_count', array( __CLASS__, 'count' ) );
	}

	public static function menu() {
		$cap = Plan_A_Newsletter::cap();
		add_menu_page( 'Newsletter', 'Newsletter', $cap, self::SLUG, array( __CLASS__, 'page_list' ), 'dashicons-email-alt', 58 );
		add_submenu_page( self::SLUG, 'Newsletteri', 'Svi newsletteri', $cap, self::SLUG, array( __CLASS__, 'page_list' ) );
		add_submenu_page( self::SLUG, 'Novi newsletter', 'Novi newsletter', $cap, self::SLUG . '-edit', array( __CLASS__, 'page_edit' ) );
		add_submenu_page( self::SLUG, 'Odjavljeni', 'Odjavljeni', $cap, self::SLUG . '-odjave', array( __CLASS__, 'page_optouts' ) );
		add_submenu_page( self::SLUG, 'Postavke newslettera', 'Postavke', 'manage_options', self::SLUG . '-postavke', array( __CLASS__, 'page_settings' ) );
	}

	private static function url( string $page = '', array $args = array() ): string {
		return add_query_arg( $args, admin_url( 'admin.php?page=' . self::SLUG . ( '' !== $page ? '-' . $page : '' ) ) );
	}

	private static function check( string $action ) {
		if ( ! current_user_can( Plan_A_Newsletter::cap() ) ) {
			wp_die( 'Nemaš ovlasti za slanje newslettera.' );
		}
		check_admin_referer( $action );
	}

	private static function css() {
		echo '<style>
.pan-wrap{max-width:1100px}.pan-card{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:18px 20px;margin:16px 0}
.pan-card h2{margin:0 0 12px;font-size:16px}.pan-muted{color:#646970}.pan-badge{display:inline-block;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600;background:#f0f0f1}
.pan-st-sending{background:#e8f4fb;color:#0a4b78}.pan-st-sent{background:#e6f6ea;color:#1d6b2f}.pan-st-scheduled{background:#fdf1e4;color:#8a4a0b}.pan-st-paused{background:#fcf0f1;color:#8a2424}.pan-st-cancelled{background:#f0f0f1;color:#50575e}
.pan-groups label{display:block;margin:0 0 8px}.pan-groups small{display:block;margin-left:24px;color:#646970}.pan-count{font-weight:600;color:#0a4b78}
.pan-bar{height:10px;background:#f0f0f1;border-radius:999px;overflow:hidden;margin:8px 0}.pan-bar i{display:block;height:100%;background:#2e7d32}
.pan-actions .button{margin:0 6px 6px 0}.pan-send{background:#e8862a!important;border-color:#c96a12!important;color:#fff!important}
.pan-grid{display:grid;grid-template-columns:minmax(0,3fr) minmax(0,2fr);gap:16px}.pan-grid>div{min-width:0}.pan-groups select,.pan-groups textarea{width:100%}@media(max-width:960px){.pan-grid{grid-template-columns:1fr}}
</style>';
	}

	/* ---------- Popis ---------- */

	public static function page_list() {
		if ( Plan_A_Newsletter::by_status( 'sending' ) ) {
			Plan_A_Newsletter::tick(); // pomogne kad stranica ima malo posjeta (WP-Cron)
		}
		$posts = get_posts(
			array(
				'post_type'      => Plan_A_Newsletter::CPT,
				'post_status'    => 'any',
				'posts_per_page' => 100,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		self::css();
		echo '<div class="wrap pan-wrap"><h1 class="wp-heading-inline">Newsletteri</h1> <a class="page-title-action" href="' . esc_url( self::url( 'edit' ) ) . '">Novi newsletter</a>';
		self::notice();
		$rate = (int) Plan_A_Newsletter::setting( 'rate' );
		echo '<p class="pan-muted">Mailovi se šalju polako, najviše ' . (int) $rate . ' na sat (ravnomjerno svakih 5 minuta), da ih hosting ne blokira i da ne završe u spamu.</p>';
		if ( ! $posts ) {
			echo '<div class="pan-card"><p>Još nema newslettera. <a href="' . esc_url( self::url( 'edit' ) ) . '">Napiši prvi.</a></p></div></div>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>Naslov</th><th>Stanje</th><th>Poslano</th><th>Datum</th><th></th></tr></thead><tbody>';
		foreach ( $posts as $p ) {
			$id    = (int) $p->ID;
			$st    = Plan_A_Newsletter::status( $id );
			$total = (int) Plan_A_Newsletter::meta( $id, 'total', 0 );
			$sent  = (int) Plan_A_Newsletter::meta( $id, 'sent', 0 );
			$when  = 'scheduled' === $st ? 'zakazano: ' . wp_date( 'j.n.Y. H:i', (int) Plan_A_Newsletter::meta( $id, 'at', 0 ) ) : ( (string) Plan_A_Newsletter::meta( $id, 'started', '' ) ?: get_the_modified_date( 'Y-m-d H:i', $p ) );
			echo '<tr><td><strong><a href="' . esc_url( self::url( 'edit', array( 'id' => $id ) ) ) . '">' . esc_html( $p->post_title ?: '(bez naslova)' ) . '</a></strong></td>'
				. '<td><span class="pan-badge pan-st-' . esc_attr( $st ) . '">' . esc_html( Plan_A_Newsletter::STATUS[ $st ] ?? $st ) . '</span></td>'
				. '<td>' . ( $total ? (int) $sent . ' / ' . (int) $total : '–' ) . '</td>'
				. '<td>' . esc_html( $when ) . '</td>'
				. '<td><a href="' . esc_url( self::url( 'edit', array( 'id' => $id ) ) ) . '">' . ( in_array( $st, array( 'draft', 'scheduled' ), true ) ? 'Uredi' : 'Otvori' ) . '</a> · '
				. '<a href="' . esc_url( self::preview_url( $id ) ) . '" target="_blank">Pregled</a> · '
				. '<a href="' . esc_url( self::control_url( $id, 'copy' ) ) . '">Kopiraj</a>'
				. ( 'draft' === $st ? ' · <a href="' . esc_url( self::control_url( $id, 'delete' ) ) . '" onclick="return confirm(\'Obrisati ovu skicu?\')" style="color:#b32d2e">Obriši</a>' : '' )
				. '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	private static function notice() {
		$msg = isset( $_GET['pan_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['pan_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' !== $msg ) {
			$err = ! empty( $_GET['pan_err'] ); // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-' . ( $err ? 'error' : 'success' ) . ' is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}
	}

	private static function control_url( int $id, string $do ): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=pan_control&id=' . $id . '&do=' . $do ), 'pan_control_' . $id );
	}

	private static function preview_url( int $id ): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=pan_preview&id=' . $id ), 'pan_preview_' . $id );
	}

	/* ---------- Pisanje ---------- */

	public static function page_edit() {
		$id   = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$post = $id ? get_post( $id ) : null;
		if ( $id && ( ! $post || Plan_A_Newsletter::CPT !== $post->post_type ) ) {
			wp_die( 'Newsletter ne postoji.' );
		}
		$st = $id ? Plan_A_Newsletter::status( $id ) : 'draft';
		self::css();
		echo '<div class="wrap pan-wrap"><h1>' . ( $id ? 'Newsletter' : 'Novi newsletter' ) . ( $id ? ' <span class="pan-badge pan-st-' . esc_attr( $st ) . '">' . esc_html( Plan_A_Newsletter::STATUS[ $st ] ?? $st ) . '</span>' : '' ) . '</h1>';
		self::notice();
		if ( $id && ! in_array( $st, array( 'draft', 'scheduled' ), true ) ) {
			self::progress( $id, $st );
			echo '</div>';
			return;
		}
		$groups = (array) ( $id ? Plan_A_Newsletter::meta( $id, 'groups', array() ) : array() );
		$tour   = $id ? (string) Plan_A_Newsletter::meta( $id, 'tour', '' ) : '';
		$extra  = $id ? (string) Plan_A_Newsletter::meta( $id, 'extra', '' ) : '';
		$tours  = $id ? (bool) Plan_A_Newsletter::meta( $id, 'tours', '' ) : true;
		$at     = $id ? (int) Plan_A_Newsletter::meta( $id, 'at', 0 ) : 0;
		$me     = wp_get_current_user();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" id="pan-form">';
		wp_nonce_field( 'pan_save' );
		echo '<input type="hidden" name="action" value="pan_save"><input type="hidden" name="id" value="' . (int) $id . '">';
		if ( 'scheduled' === $st ) {
			echo '<div class="notice notice-warning inline"><p>Zakazano za <strong>' . esc_html( wp_date( 'j.n.Y. H:i', $at ) ) . '</strong>. Do tada ga možeš mijenjati. <a href="' . esc_url( self::control_url( $id, 'unschedule' ) ) . '">Otkaži zakazivanje</a></p></div>';
		}
		echo '<div class="pan-grid"><div>';
		echo '<div class="pan-card"><h2>Naslov maila</h2><input type="text" name="title" class="large-text" required maxlength="150" value="' . esc_attr( $post ? $post->post_title : '' ) . '" placeholder="npr. Zimski program Plan A je objavljen"></div>';
		echo '<div class="pan-card"><h2>Tekst</h2><p class="pan-muted">Upiši <code>{ime}</code> i zamijenit će se imenom primatelja (npr. „Bok {ime},” → „Bok Ana,”). Gdje ime nije poznato, piše samo „Bok,”.</p>';
		wp_editor(
			$post ? $post->post_content : "Bok {ime},\n\n",
			'pan_body',
			array(
				'textarea_name' => 'body',
				'textarea_rows' => 18,
				'media_buttons' => true,
				'teeny'         => false,
			)
		);
		echo '<p><label><input type="checkbox" name="tours" value="1"' . checked( $tours, true, false ) . '> Na kraj dodaj <strong>nadolazeće izlete</strong> (sljedećih ' . (int) Plan_A_Newsletter::setting( 'days' ) . ' dana, najviše 6, sa slikom, datumom, cijenom i gumbom)</label><br><span class="pan-muted">Želiš ih na drugom mjestu u tekstu? Na to mjesto upiši <code>{izleti}</code>.</span></p></div>';
		echo '</div><div>';

		// Primatelji.
		echo '<div class="pan-card pan-groups"><h2>Kome šaljemo</h2>';
		$desc = array(
			'clanovi'   => 'Svi s potvrđenom pristupnicom.',
			'clanarina' => 'Članovi kojima je označena članarina za ' . (int) current_time( 'Y' ) . '.',
			'izlet'     => 'Kupci i osobe s prijava na jedan termin. Ide i odjavljenima, jer je to obavijest o izletu na koji su prijavljeni.',
			'kupci'     => 'Kupci izleta iz zadnje 2 godine koji nemaju potvrđenu pristupnicu.',
			'vanjski'   => 'Adrese koje nisu u bazi.',
		);
		foreach ( Plan_A_Newsletter_Recipients::GROUPS as $k => $label ) {
			echo '<label><input type="checkbox" name="groups[]" value="' . esc_attr( $k ) . '"' . checked( in_array( $k, $groups, true ), true, false ) . ' class="pan-g"> <strong>' . esc_html( $label ) . '</strong> <span class="pan-count" data-g="' . esc_attr( $k ) . '"></span><small>' . esc_html( $desc[ $k ] ) . '</small></label>';
			if ( 'izlet' === $k ) {
				echo '<p class="pan-tour" style="margin:0 0 10px 24px"><select name="tour" style="max-width:100%"><option value="">— odaberi termin —</option>';
				foreach ( Plan_A_Newsletter_Recipients::tour_options() as $v => $l ) {
					echo '<option value="' . esc_attr( $v ) . '"' . selected( $tour, $v, false ) . '>' . esc_html( $l ) . '</option>';
				}
				echo '</select></p>';
			}
			if ( 'vanjski' === $k ) {
				echo '<p style="margin:0 0 10px 24px"><textarea name="extra" rows="4" class="large-text" placeholder="ana@primjer.hr, marko@primjer.hr">' . esc_textarea( $extra ) . '</textarea><span class="pan-muted">Odvoji zarezom ili novim redom.</span></p>';
			}
		}
		echo '<p><button type="button" class="button" id="pan-calc">Izračunaj primatelje</button> <span id="pan-total" class="pan-count"></span></p>';
		echo '<p class="pan-muted">Isti e-mail dobiva samo jedan mail, i kad je u više grupa. Odjavljeni se preskaču.</p></div>';

		// Proba i slanje.
		echo '<div class="pan-card"><h2>Proba</h2><p><input type="email" name="test_to" class="regular-text" value="' . esc_attr( $me->user_email ) . '"></p>'
			. '<p class="pan-actions"><button type="submit" name="do" value="save" class="button">Spremi skicu</button>'
			. '<button type="submit" name="do" value="preview" class="button" formtarget="_blank">Pregled</button>'
			. '<button type="submit" name="do" value="test" class="button">Pošalji probni mail</button></p></div>';
		echo '<div class="pan-card"><h2>Slanje</h2><p class="pan-actions"><button type="submit" name="do" value="send" class="button button-primary pan-send" id="pan-send">Pošalji newsletter</button></p>'
			. '<p class="pan-muted">Ili zakaži: <input type="datetime-local" name="at" value="' . esc_attr( $at ? wp_date( 'Y-m-d\TH:i', $at ) : '' ) . '"> <button type="submit" name="do" value="schedule" class="button">Zakaži</button></p>'
			. '<p class="pan-muted">Šalje se najviše ' . (int) Plan_A_Newsletter::setting( 'rate' ) . ' mailova na sat. Kad sve ode, dobit ćeš kratko izvješće na svoj e-mail.</p></div>';
		echo '</div></div></form></div>';
		self::script();
	}

	private static function script() {
		$nonce = wp_create_nonce( 'pan_count' );
		$rate  = max( 1, (int) Plan_A_Newsletter::setting( 'rate' ) );
		?>
<script>
(function () {
	var form = document.getElementById('pan-form'), total = null, busy = false;
	function data() {
		var fd = new FormData(form);
		fd.set('action', 'pan_count');
		fd.set('_ajax_nonce', '<?php echo esc_js( $nonce ); ?>');
		return fd;
	}
	function calc(then) {
		if (busy) { return; }
		busy = true;
		document.getElementById('pan-total').textContent = 'računam…';
		fetch(ajaxurl, { method: 'POST', body: data(), credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (r) {
			busy = false;
			if (!r || !r.success) { document.getElementById('pan-total').textContent = 'greška'; return; }
			total = r.data.total;
			document.querySelectorAll('.pan-count[data-g]').forEach(function (el) {
				var n = r.data.counts[el.getAttribute('data-g')];
				el.textContent = (n === undefined) ? '' : '(' + n + ')';
			});
			var h = Math.ceil(total / <?php echo (int) $rate; ?>);
			document.getElementById('pan-total').textContent = 'Ukupno: ' + total + ' primatelja' + (r.data.skipped ? ', ' + r.data.skipped + ' odjavljenih preskočeno' : '') + (total ? ' · slanje traje oko ' + h + ' h' : '');
			if (then) { then(); }
		}).catch(function () { busy = false; document.getElementById('pan-total').textContent = 'greška'; });
	}
	document.getElementById('pan-calc').addEventListener('click', function () { calc(); });
	form.querySelectorAll('.pan-g, select[name=tour]').forEach(function (el) { el.addEventListener('change', function () { total = null; }); });
	var go = false;
	form.addEventListener('submit', function (e) {
		var b = e.submitter;
		if (!b || (b.value !== 'send' && b.value !== 'schedule') || go) { return; }
		e.preventDefault();
		calc(function () {
			if (!total) { alert('Nema primatelja. Označi barem jednu grupu.'); return; }
			var n = total % 10, nn = total % 100, word = (n === 1 && nn !== 11) ? 'adresu' : (n >= 2 && n <= 4 && (nn < 12 || nn > 14)) ? 'adrese' : 'adresa';
			var msg = b.value === 'schedule' ? 'Zakazati slanje na ' + total + ' ' + word + '?' : 'Poslati newsletter sada na ' + total + ' ' + word + '?';
			if (confirm(msg)) {
				go = true;
				var h = document.createElement('input'); h.type = 'hidden'; h.name = 'do'; h.value = b.value; form.appendChild(h);
				if (window.tinymce) { tinymce.triggerSave(); }
				form.submit();
			}
		});
	});
})();
</script>
		<?php
	}

	/** Stanje slanja (bez uređivanja). */
	private static function progress( int $id, string $st ) {
		$total = (int) Plan_A_Newsletter::meta( $id, 'total', 0 );
		$sent  = (int) Plan_A_Newsletter::meta( $id, 'sent', 0 );
		$fail  = (array) Plan_A_Newsletter::meta( $id, 'failed', array() );
		$left  = count( (array) Plan_A_Newsletter::meta( $id, 'queue', array() ) );
		$pct   = $total ? (int) round( 100 * ( $total - $left ) / $total ) : 100;
		$rate  = max( 1, (int) Plan_A_Newsletter::setting( 'rate' ) );
		echo '<div class="pan-card"><h2>' . esc_html( get_post_field( 'post_title', $id ) ) . '</h2>'
			. '<div class="pan-bar"><i style="width:' . (int) $pct . '%"></i></div>'
			. '<p>Poslano <strong>' . (int) $sent . '</strong> od ' . (int) $total . ( $left && 'sending' === $st ? ' · preostalo ' . (int) $left . ', još oko ' . (int) ceil( $left / $rate ) . ' h' : '' ) . '</p>'
			. ( (int) Plan_A_Newsletter::meta( $id, 'skipped', 0 ) ? '<p class="pan-muted">Odjavljenih preskočeno: ' . (int) Plan_A_Newsletter::meta( $id, 'skipped', 0 ) . '</p>' : '' )
			. ( $fail ? '<p style="color:#b32d2e">Nije poslano (' . count( $fail ) . '): ' . esc_html( implode( ', ', array_slice( $fail, 0, 50 ) ) ) . '</p>' : '' )
			. '<p class="pan-muted">Počelo: ' . esc_html( (string) Plan_A_Newsletter::meta( $id, 'started', '' ) ) . ( 'sent' === $st ? ' · završeno: ' . esc_html( (string) Plan_A_Newsletter::meta( $id, 'finished', '' ) ) : '' ) . '</p>'
			. '<p class="pan-actions">';
		if ( 'sending' === $st ) {
			echo '<a class="button" href="' . esc_url( self::control_url( $id, 'pause' ) ) . '">Pauziraj</a>';
		}
		if ( 'paused' === $st ) {
			echo '<a class="button button-primary" href="' . esc_url( self::control_url( $id, 'resume' ) ) . '">Nastavi slanje</a>';
		}
		if ( in_array( $st, array( 'sending', 'paused' ), true ) ) {
			echo '<a class="button" href="' . esc_url( self::control_url( $id, 'cancel' ) ) . '" onclick="return confirm(\'Prekinuti slanje? Preostali neće dobiti mail.\')">Prekini</a>';
		}
		echo '<a class="button" href="' . esc_url( self::preview_url( $id ) ) . '" target="_blank">Pregled</a>'
			. '<a class="button" href="' . esc_url( self::control_url( $id, 'copy' ) ) . '">Kopiraj kao novi</a></p></div>';
	}

	/* ---------- Radnje ---------- */

	public static function save() {
		self::check( 'pan_save' );
		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$do   = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : 'save';
		$st   = $id ? Plan_A_Newsletter::status( $id ) : 'draft';
		if ( $id && ( Plan_A_Newsletter::CPT !== get_post_type( $id ) || ! in_array( $st, array( 'draft', 'scheduled' ), true ) ) ) {
			wp_die( 'Ovaj newsletter se više ne može mijenjati.' );
		}
		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$body  = isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : '';
		$data  = array(
			'post_type'    => Plan_A_Newsletter::CPT,
			'post_status'  => 'private',
			'post_title'   => $title,
			'post_content' => $body,
		);
		if ( $id ) {
			$data['ID'] = $id;
			wp_update_post( $data );
		} else {
			$id = (int) wp_insert_post( $data );
			update_post_meta( $id, '_pan_status', 'draft' );
		}
		$groups = isset( $_POST['groups'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['groups'] ) ) : array();
		update_post_meta( $id, '_pan_groups', array_values( array_intersect( array_keys( Plan_A_Newsletter_Recipients::GROUPS ), $groups ) ) );
		update_post_meta( $id, '_pan_tour', isset( $_POST['tour'] ) ? sanitize_text_field( wp_unslash( $_POST['tour'] ) ) : '' );
		update_post_meta( $id, '_pan_extra', isset( $_POST['extra'] ) ? sanitize_textarea_field( wp_unslash( $_POST['extra'] ) ) : '' );
		update_post_meta( $id, '_pan_tours', empty( $_POST['tours'] ) ? '' : 1 );

		$back = static function ( string $msg, bool $err = false ) use ( $id ) {
			wp_safe_redirect( self::url( 'edit', array_filter( array( 'id' => $id, 'pan_msg' => $msg, 'pan_err' => $err ? 1 : 0 ) ) ) );
			exit;
		};
		if ( 'preview' === $do ) {
			self::output( $id );
		}
		if ( 'test' === $do ) {
			$to = isset( $_POST['test_to'] ) ? sanitize_email( wp_unslash( $_POST['test_to'] ) ) : '';
			$me = wp_get_current_user();
			$ok = Plan_A_Newsletter_Mail::send_test( $id, $to, (string) $me->first_name );
			$back( $ok ? 'Probni mail je poslan na ' . $to . '.' : 'Probni mail nije poslan (provjeri adresu i postavke pošte).', ! $ok );
		}
		if ( in_array( $do, array( 'send', 'schedule' ), true ) ) {
			if ( '' === $title || '' === trim( wp_strip_all_tags( $body ) ) ) {
				$back( 'Upiši naslov i tekst prije slanja.', true );
			}
			if ( ! get_post_meta( $id, '_pan_groups', true ) ) {
				$back( 'Označi barem jednu grupu primatelja.', true );
			}
			update_post_meta( $id, '_pan_by', get_current_user_id() );
			if ( 'schedule' === $do ) {
				$raw = isset( $_POST['at'] ) ? sanitize_text_field( wp_unslash( $_POST['at'] ) ) : '';
				$ts  = '' !== $raw ? ( new DateTime( $raw, wp_timezone() ) )->getTimestamp() : 0;
				if ( $ts <= time() ) {
					$back( 'Odaberi datum i sat u budućnosti.', true );
				}
				update_post_meta( $id, '_pan_at', $ts );
				update_post_meta( $id, '_pan_status', 'scheduled' );
				$back( 'Newsletter je zakazan za ' . wp_date( 'j.n.Y. H:i', $ts ) . '.' );
			}
			$n = Plan_A_Newsletter_Mail::start( $id );
			Plan_A_Newsletter::tick();
			wp_safe_redirect( self::url( 'edit', array( 'id' => $id, 'pan_msg' => 'Slanje je počelo: ' . $n . ' primatelja.' ) ) );
			exit;
		}
		$back( 'Spremljeno.' );
	}

	public static function control() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		self::check( 'pan_control_' . $id );
		if ( Plan_A_Newsletter::CPT !== get_post_type( $id ) ) {
			wp_die( 'Newsletter ne postoji.' );
		}
		$do  = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$st  = Plan_A_Newsletter::status( $id );
		$msg = '';
		if ( 'pause' === $do && 'sending' === $st ) {
			update_post_meta( $id, '_pan_status', 'paused' );
			$msg = 'Slanje je pauzirano.';
		} elseif ( 'resume' === $do && 'paused' === $st ) {
			update_post_meta( $id, '_pan_status', 'sending' );
			$msg = 'Slanje se nastavlja.';
		} elseif ( 'cancel' === $do && in_array( $st, array( 'sending', 'paused' ), true ) ) {
			update_post_meta( $id, '_pan_status', 'cancelled' );
			update_post_meta( $id, '_pan_finished', current_time( 'mysql' ) );
			$msg = 'Slanje je prekinuto.';
		} elseif ( 'unschedule' === $do && 'scheduled' === $st ) {
			update_post_meta( $id, '_pan_status', 'draft' );
			$msg = 'Zakazivanje je otkazano, newsletter je opet skica.';
		} elseif ( 'delete' === $do && 'draft' === $st ) {
			wp_delete_post( $id, true );
			wp_safe_redirect( self::url( '', array( 'pan_msg' => 'Skica je obrisana.' ) ) );
			exit;
		} elseif ( 'copy' === $do ) {
			$p   = get_post( $id );
			$new = (int) wp_insert_post(
				array(
					'post_type'    => Plan_A_Newsletter::CPT,
					'post_status'  => 'private',
					'post_title'   => $p->post_title,
					'post_content' => $p->post_content,
				)
			);
			update_post_meta( $new, '_pan_status', 'draft' );
			foreach ( array( 'groups', 'tour', 'extra', 'tours' ) as $k ) {
				update_post_meta( $new, '_pan_' . $k, get_post_meta( $id, '_pan_' . $k, true ) );
			}
			wp_safe_redirect( self::url( 'edit', array( 'id' => $new, 'pan_msg' => 'Napravljena je kopija. Uredi je i pošalji.' ) ) );
			exit;
		}
		wp_safe_redirect( self::url( 'edit', array_filter( array( 'id' => $id, 'pan_msg' => $msg ) ) ) );
		exit;
	}

	public static function preview() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		self::check( 'pan_preview_' . $id );
		self::output( $id );
	}

	/** Pregled maila u pregledniku (s imenom onoga tko gleda). */
	private static function output( int $id ) {
		if ( Plan_A_Newsletter::CPT !== get_post_type( $id ) ) {
			wp_die( 'Newsletter ne postoji.' );
		}
		$me = wp_get_current_user();
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		echo Plan_A_Newsletter_Mail::render( $id, (string) $me->first_name, '' ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	public static function count() {
		check_ajax_referer( 'pan_count' );
		if ( ! current_user_can( Plan_A_Newsletter::cap() ) ) {
			wp_send_json_error();
		}
		$groups = isset( $_POST['groups'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['groups'] ) ) : array();
		$r      = Plan_A_Newsletter_Recipients::build(
			$groups,
			isset( $_POST['tour'] ) ? sanitize_text_field( wp_unslash( $_POST['tour'] ) ) : '',
			isset( $_POST['extra'] ) ? sanitize_textarea_field( wp_unslash( $_POST['extra'] ) ) : ''
		);
		wp_send_json_success(
			array(
				'total'   => count( $r['list'] ),
				'counts'  => $r['counts'],
				'skipped' => $r['skipped'],
			)
		);
	}

	/* ---------- Odjavljeni ---------- */

	public static function page_optouts() {
		$list = Plan_A_Newsletter::optouts();
		arsort( $list );
		self::css();
		echo '<div class="wrap pan-wrap"><h1>Odjavljeni</h1>';
		self::notice();
		echo '<p class="pan-muted">Ove adrese ne dobivaju newsletter ni siječanjski podsjetnik za članarinu (zajednička odjava). Članovi odjavljeni sa siječanjskog e-maila također se preskaču. Obavijesti o izletu na koji su prijavljeni i dalje dobivaju.</p>';
		echo '<div class="pan-card"><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'pan_optout' );
		echo '<input type="hidden" name="action" value="pan_optout"><input type="hidden" name="do" value="add"><input type="email" name="email" class="regular-text" placeholder="adresa@primjer.hr" required> <button class="button">Dodaj u odjavljene</button> <span class="pan-muted">(npr. netko je zatražio odjavu telefonom)</span></form></div>';
		if ( ! $list ) {
			echo '<p>Nitko se nije odjavio.</p></div>';
			return;
		}
		echo '<table class="widefat striped" style="max-width:700px"><thead><tr><th>E-mail</th><th>Odjavljen</th><th></th></tr></thead><tbody>';
		foreach ( $list as $email => $when ) {
			echo '<tr><td>' . esc_html( $email ) . '</td><td>' . esc_html( (string) $when ) . '</td><td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:0">';
			wp_nonce_field( 'pan_optout' );
			echo '<input type="hidden" name="action" value="pan_optout"><input type="hidden" name="do" value="remove"><input type="hidden" name="email" value="' . esc_attr( $email ) . '"><button class="button-link" onclick="return confirm(\'Vratiti ovu adresu na popis? Učini to samo ako je osoba to tražila.\')">Vrati na popis</button></form></td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public static function optout() {
		self::check( 'pan_optout' );
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$add   = isset( $_POST['do'] ) && 'add' === $_POST['do'];
		Plan_A_Newsletter_Mail::set_optout( $email, $add );
		wp_safe_redirect( self::url( 'odjave', array( 'pan_msg' => $add ? 'Adresa je odjavljena.' : 'Adresa je vraćena na popis.' ) ) );
		exit;
	}

	/* ---------- Postavke ---------- */

	public static function page_settings() {
		$s = Plan_A_Newsletter::settings();
		self::css();
		echo '<div class="wrap pan-wrap"><h1>Postavke newslettera</h1>';
		self::notice();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'pan_settings' );
		echo '<input type="hidden" name="action" value="pan_settings"><table class="form-table">'
			. '<tr><th>Adresa pošiljatelja</th><td><input type="email" name="from_email" class="regular-text" value="' . esc_attr( $s['from_email'] ) . '"><p class="description">Na nju stižu i odgovori. Adresa mora biti na domeni srd-plan-a.hr (inače mailovi idu u spam).</p></td></tr>'
			. '<tr><th>Ime pošiljatelja</th><td><input type="text" name="from_name" class="regular-text" value="' . esc_attr( $s['from_name'] ) . '"></td></tr>'
			. '<tr><th>Mailova na sat</th><td><input type="number" name="rate" min="5" max="500" value="' . (int) $s['rate'] . '"><p class="description">Šalju se ravnomjerno, svakih 5 minuta po dvanaestina. 40 na sat = 610 mailova za oko 16 sati. Ako hosting dopušta više, može se povećati.</p></td></tr>'
			. '<tr><th>Nadolazeći izleti</th><td><input type="number" name="days" min="7" max="365" value="' . (int) $s['days'] . '"> dana unaprijed</td></tr>'
			. '<tr><th>Logo u zaglavlju</th><td><input type="url" name="logo" class="large-text" value="' . esc_attr( $s['logo'] ) . '" placeholder="' . esc_attr( Plan_A_Newsletter::logo() ) . '"><p class="description">Prazno = logo teme. Najbolje svijetli logo (zaglavlje maila je tamnoplavo).</p></td></tr>'
			. '</table><p><button class="button button-primary">Spremi</button></p></form></div>';
	}

	public static function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Nemaš ovlasti.' );
		}
		check_admin_referer( 'pan_settings' );
		update_option(
			Plan_A_Newsletter::OPTION,
			array(
				'from_email' => isset( $_POST['from_email'] ) ? sanitize_email( wp_unslash( $_POST['from_email'] ) ) : '',
				'from_name'  => isset( $_POST['from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['from_name'] ) ) : '',
				'rate'       => isset( $_POST['rate'] ) ? max( 5, min( 500, absint( $_POST['rate'] ) ) ) : 40,
				'days'       => isset( $_POST['days'] ) ? max( 7, min( 365, absint( $_POST['days'] ) ) ) : 60,
				'logo'       => isset( $_POST['logo'] ) ? esc_url_raw( wp_unslash( $_POST['logo'] ) ) : '',
			),
			false
		);
		wp_safe_redirect( self::url( 'postavke', array( 'pan_msg' => 'Postavke su spremljene.' ) ) );
		exit;
	}
}
