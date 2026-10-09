<?php
/**
 * Administracija: izbornik "Jedrenje" s karticama Zahtjevi, Rezervacije, Kalendar, Cjenik i Postavke.
 *
 * @package Plan_A_Jedrenje
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Jedrenje_Admin {

	const PAGE = 'plan-a-jedrenje';
	const CAP  = 'manage_woocommerce';

	const TABS = array(
		'zahtjevi'    => 'Zahtjevi',
		'rezervacije' => 'Rezervacije',
		'kalendar'    => 'Kalendar',
		'cjenik'      => 'Cjenik',
		'postavke'    => 'Postavke',
	);

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_paj_admin', array( __CLASS__, 'action' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PLAN_A_JEDRENJE_FILE ), array( __CLASS__, 'links' ) );
	}

	/**
	 * Odabir fotografija iz medija (samo na kartici Postavke).
	 */
	public static function assets( $hook ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- samo odabir kartice.
		if ( false === strpos( (string) $hook, self::PAGE ) || 'postavke' !== sanitize_key( wp_unslash( $_GET['tab'] ?? '' ) ) ) {
			return;
		}
		wp_enqueue_media();
		wp_add_inline_script(
			'media-editor',
			"jQuery(function($){var box=$('#paj-photos');if(!box.length)return;var input=$('#paj-gallery-images'),list=box.find('.paj-photos__list'),frame;"
			. "function draw(sel){list.empty();sel.forEach(function(a){var u=(a.sizes&&a.sizes.thumbnail?a.sizes.thumbnail.url:a.url);list.append($('<img>').attr({src:u,alt:'',width:72,height:72}).css({objectFit:'cover',borderRadius:'6px'}));});}"
			. "box.on('click','.paj-photos__pick',function(e){e.preventDefault();if(!frame){frame=wp.media({title:'Fotografije za slajder jedrenja',button:{text:'Koristi ove fotografije'},library:{type:'image'},multiple:'add'});"
			. "frame.on('open',function(){var s=frame.state().get('selection');s.reset();(input.val()||'').split(',').forEach(function(id){id=parseInt(id,10);if(id){var a=wp.media.attachment(id);a.fetch();s.add(a);}});});"
			. "frame.on('select',function(){var sel=frame.state().get('selection').toJSON();input.val(sel.map(function(a){return a.id;}).join(','));draw(sel);});}frame.open();});"
			. "box.on('click','.paj-photos__clear',function(e){e.preventDefault();input.val('');list.empty();});});"
		);
	}

	public static function links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url( 'postavke' ) ) . '">Postavke</a>' );
		return $links;
	}

	public static function url( string $tab = 'zahtjevi', array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::PAGE, 'tab' => $tab ), $args ), admin_url( 'admin.php' ) );
	}

	public static function menu() {
		$count = Plan_A_Jedrenje_Booking::count_new();
		$badge = $count ? ' <span class="awaiting-mod count-' . (int) $count . '"><span class="pending-count">' . (int) $count . '</span></span>' : '';
		$icon  = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#a7aaad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v13M12 4l7 10h-7M12 7l-5 7h5"/><path d="M3 17h18l-2 3H5z"/></svg>' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		add_menu_page( 'Jedrenje', 'Jedrenje' . $badge, self::CAP, self::PAGE, array( __CLASS__, 'page' ), $icon, 56 );
	}

	/* ---------------------------------------------------------------------
	 * Akcije
	 * ------------------------------------------------------------------- */

	private static function notice( string $text, bool $ok = true ) {
		set_transient( 'paj_notice_' . get_current_user_id(), array( $text, $ok ), 60 );
	}

	public static function action() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Nemaš ovlasti.' );
		}
		check_admin_referer( 'paj_admin' );
		$do  = sanitize_key( wp_unslash( $_POST['do'] ?? '' ) );
		$id  = absint( $_POST['rez'] ?? 0 );
		$tab = sanitize_key( wp_unslash( $_POST['tab'] ?? 'zahtjevi' ) );
		$res = true;

		switch ( $do ) {
			case 'confirm':
				$res = Plan_A_Jedrenje_Booking::confirm( $id );
				$ok  = 'Uplatnica je poslana kupcu. Rezervacija je u kartici Rezervacije.';
				$tab = is_wp_error( $res ) ? 'zahtjevi' : 'rezervacije';
				break;
			case 'reject':
				$message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
				$res     = Plan_A_Jedrenje_Booking::reject( $id, $message ?: (string) Plan_A_Jedrenje_Data::value( 'mail_reject' ) );
				$ok      = 'Zahtjev je odbijen i kupcu je poslana poruka.';
				break;
			case 'paid':
				$res = Plan_A_Jedrenje_Booking::mark_paid( $id, sanitize_key( wp_unslash( $_POST['part'] ?? '' ) ) );
				$ok  = 'Uplata je označena, kupcu je poslana potvrda.';
				break;
			case 'rest':
				$res = Plan_A_Jedrenje_Booking::send_rest( $id );
				$ok  = 'Uplatnica za ostatak je poslana kupcu.';
				break;
			case 'resend':
				$res = Plan_A_Jedrenje_Booking::resend( $id, sanitize_key( wp_unslash( $_POST['part'] ?? '' ) ) );
				$ok  = 'Uplatnica je ponovno poslana.';
				break;
			case 'cancel':
				$res = Plan_A_Jedrenje_Booking::cancel( $id );
				$ok  = 'Rezervacija je otkazana i tjedan je oslobođen.';
				break;
			case 'calendar':
				self::save_calendar();
				$ok = 'Kalendar je spremljen.';
				break;
			case 'prices':
				self::save_prices();
				$ok = 'Cjenik je spremljen.';
				break;
			case 'settings':
				self::save_settings();
				$ok = 'Postavke su spremljene.';
				break;
			default:
				$res = new WP_Error( 'do', 'Nepoznata radnja.' );
				$ok  = '';
		}
		if ( is_wp_error( $res ) ) {
			self::notice( $res->get_error_message(), false );
		} else {
			self::notice( $ok );
		}
		wp_safe_redirect( self::url( isset( self::TABS[ $tab ] ) ? $tab : 'zahtjevi' ) );
		exit;
	}

	private static function money_in( $value ): float {
		$value = str_replace( array( ' ', '€', '.' ), '', (string) $value );
		$value = str_replace( ',', '.', $value );
		return is_numeric( $value ) ? max( 0, round( (float) $value, 2 ) ) : 0.0;
	}

	private static function save_calendar() {
		$weeks  = Plan_A_Jedrenje_Data::week_overrides();
		$closed = (array) ( $_POST['closed'] ?? array() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput -- provjereno u action(), čisti se ispod.
		$asked  = (array) ( $_POST['request'] ?? array() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput -- samo provjera je li polje poslano.
		$prices = (array) ( $_POST['price'] ?? array() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput
		$shown  = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['weeks'] ?? array() ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		foreach ( $shown as $week ) {
			$week = Plan_A_Jedrenje_Data::valid_date( $week );
			if ( ! $week ) {
				continue;
			}
			$entry = array();
			if ( ! empty( $closed[ $week ] ) ) {
				$entry['closed'] = 1;
			}
			if ( ! empty( $asked[ $week ] ) ) {
				$entry['request'] = 1;
			}
			$price = self::money_in( wp_unslash( $prices[ $week ] ?? '' ) );
			if ( $price > 0 ) {
				$entry['price'] = $price;
			}
			if ( $entry ) {
				$weeks[ $week ] = $entry;
			} else {
				unset( $weeks[ $week ] );
			}
		}
		ksort( $weeks );
		update_option( Plan_A_Jedrenje_Data::WEEKS, $weeks, false );
	}

	private static function save_prices() {
		$s                 = Plan_A_Jedrenje_Data::get();
		$s['base_price']   = self::money_in( wp_unslash( $_POST['base_price'] ?? '' ) ) ?: 5600; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$s['base_regular'] = self::money_in( wp_unslash( $_POST['base_regular'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		Plan_A_Jedrenje_Data::set( $s );

		$rows    = (array) wp_unslash( $_POST['periods'] ?? array() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput
		$periods = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! empty( $row['delete'] ) ) {
				continue;
			}
			$from  = Plan_A_Jedrenje_Data::valid_date( sanitize_text_field( $row['from'] ?? '' ) );
			$to    = Plan_A_Jedrenje_Data::valid_date( sanitize_text_field( $row['to'] ?? '' ) );
			$price = self::money_in( $row['price'] ?? '' );
			if ( ! $from || ! $to || $price <= 0 ) {
				continue;
			}
			if ( $to < $from ) {
				list( $from, $to ) = array( $to, $from );
			}
			$periods[] = array(
				'name'    => sanitize_text_field( $row['name'] ?? '' ) ?: 'Razdoblje',
				'from'    => $from,
				'to'      => $to,
				'price'   => $price,
				'regular' => self::money_in( $row['regular'] ?? '' ),
			);
		}
		usort(
			$periods,
			static function ( $a, $b ) {
				return strcmp( $a['from'], $b['from'] );
			}
		);
		update_option( Plan_A_Jedrenje_Data::PERIODS, $periods, false );
	}

	private static function save_settings() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- provjereno u action().
		$s    = Plan_A_Jedrenje_Data::get();
		$text = array( 'title', 'boat', 'skipper', 'marina', 'organizer' );
		foreach ( $text as $k ) {
			$s[ $k ] = sanitize_text_field( wp_unslash( $_POST[ $k ] ?? '' ) ) ?: Plan_A_Jedrenje_Data::defaults()[ $k ];
		}
		foreach ( array( 'contact_name', 'contact_phone' ) as $k ) { // smiju biti prazni
			$s[ $k ] = sanitize_text_field( wp_unslash( $_POST[ $k ] ?? '' ) );
		}
		foreach ( array( 'embark_time', 'disembark_time' ) as $k ) {
			$v       = sanitize_text_field( wp_unslash( $_POST[ $k ] ?? '' ) );
			$s[ $k ] = preg_match( '/^([01]?\d|2[0-3])[:.][0-5]\d$/', $v ) ? str_replace( '.', ':', $v ) : Plan_A_Jedrenje_Data::defaults()[ $k ];
		}
		foreach ( array( 'included', 'excluded', 'routes', 'concepts', 'leaders', 'important', 'cancel_terms', 'mail_request', 'mail_confirm', 'mail_reject', 'mail_booked', 'mail_paid' ) as $k ) {
			$s[ $k ] = sanitize_textarea_field( wp_unslash( $_POST[ $k ] ?? '' ) );
		}
		if ( '' === trim( $s['routes'] ) ) {
			$s['routes'] = Plan_A_Jedrenje_Data::defaults()['routes'];
		}
		$s['cabins']       = max( 1, min( 20, absint( $_POST['cabins'] ?? 4 ) ) );
		$s['max_persons']  = max( 1, min( 20, absint( $_POST['max_persons'] ?? 8 ) ) );
		$s['min_persons']  = max( 1, min( $s['max_persons'], absint( $_POST['min_persons'] ?? 5 ) ) );
		$s['deposit_pct']  = max( 1, min( 100, absint( $_POST['deposit_pct'] ?? 30 ) ) );
		$s['deposit_days'] = max( 1, min( 60, absint( $_POST['deposit_days'] ?? 5 ) ) );
		$s['rest_days']    = max( 0, min( 180, absint( $_POST['rest_days'] ?? 30 ) ) );
		$s['lead_days']    = max( 1, min( 90, absint( $_POST['lead_days'] ?? 7 ) ) );
		$s['gallery_tour'] = max( -1, (int) ( $_POST['gallery_tour'] ?? 0 ) );
		$s['gallery_images'] = implode( ',', array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['gallery_images'] ?? '' ) ) ) ) ) );
		$s['list_tour']    = (int) ( $_POST['list_tour'] ?? 0 ) < 0 ? -1 : 0;
		delete_transient( 'paj_gallery_tour' );
		$email             = sanitize_email( wp_unslash( $_POST['admin_email'] ?? '' ) );
		$s['admin_email']  = is_email( $email ) ? $email : '';

		$seasons = array();
		foreach ( (array) wp_unslash( $_POST['seasons'] ?? array() ) as $year => $row ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$year  = absint( $year );
			$first = Plan_A_Jedrenje_Data::valid_date( sanitize_text_field( $row['first'] ?? '' ) );
			$last  = Plan_A_Jedrenje_Data::valid_date( sanitize_text_field( $row['last'] ?? '' ) );
			if ( $year && ( $first || $last ) ) {
				$seasons[ $year ] = array(
					'first' => $first,
					'last'  => $last,
				);
			}
		}
		$s['seasons'] = $seasons;
		Plan_A_Jedrenje_Data::set( $s );
		// phpcs:enable
	}

	/* ---------------------------------------------------------------------
	 * Stranica
	 * ------------------------------------------------------------------- */

	private static function form_open( string $do, string $tab, int $rez = 0, string $extra = '' ): string {
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="paj-inline"' . $extra . '>'
			. wp_nonce_field( 'paj_admin', '_wpnonce', true, false )
			. '<input type="hidden" name="action" value="paj_admin"><input type="hidden" name="do" value="' . esc_attr( $do ) . '">'
			. '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">'
			. ( $rez ? '<input type="hidden" name="rez" value="' . (int) $rez . '">' : '' );
	}

	private static function button( string $do, string $label, string $tab, int $rez, string $class = 'button', array $hidden = array(), string $confirm = '' ): string {
		$html = self::form_open( $do, $tab, $rez, $confirm ? ' onsubmit="return confirm(' . esc_attr( wp_json_encode( $confirm ) ) . ');"' : '' );
		foreach ( $hidden as $k => $v ) {
			$html .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		return $html . '<button type="submit" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form>';
	}

	public static function page() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$tab    = sanitize_key( wp_unslash( $_GET['tab'] ?? 'zahtjevi' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$tab    = isset( self::TABS[ $tab ] ) ? $tab : 'zahtjevi';
		$notice = get_transient( 'paj_notice_' . get_current_user_id() );
		delete_transient( 'paj_notice_' . get_current_user_id() );
		$new = Plan_A_Jedrenje_Booking::count_new();
		?>
		<div class="wrap paj-admin">
			<style>
				.paj-admin .paj-inline{display:inline-block;margin:0 6px 6px 0}
				.paj-admin .paj-cards{display:grid;gap:14px;max-width:1100px}
				.paj-admin .paj-rcard{background:#fff;border:1px solid #dcdcde;border-left:4px solid #2271b1;border-radius:6px;padding:14px 16px}
				.paj-admin .paj-rcard.is-potvrdeno{border-left-color:#dba617}.paj-admin .paj-rcard.is-rezervirano{border-left-color:#00a32a}.paj-admin .paj-rcard.is-placeno{border-left-color:#00a32a;background:#f6fbf6}
				.paj-admin .paj-rcard.is-odbijeno,.paj-admin .paj-rcard.is-otkazano{border-left-color:#a7aaad;opacity:.8}
				.paj-admin .paj-rcard h3{margin:0 0 6px;font-size:16px}
				.paj-admin .paj-meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:4px 18px;margin:8px 0 10px}
				.paj-admin .paj-meta div span{color:#646970}
				.paj-admin .paj-pay{display:grid;gap:8px;margin:10px 0;padding:10px 12px;background:#f6f7f7;border-radius:6px}
				.paj-admin .paj-pay p{margin:0}
				.paj-admin .paj-ok{color:#008a20;font-weight:600}.paj-admin .paj-wait{color:#996800;font-weight:600}.paj-admin .paj-late{color:#d63638;font-weight:600}
				.paj-admin .paj-badge{display:inline-block;padding:2px 8px;border-radius:10px;background:#f0f0f1;font-size:12px;font-weight:600}
				.paj-admin table.paj-table{max-width:1100px}
				.paj-admin table.paj-table input[type=text]{width:120px}
				.paj-admin .paj-st-free{color:#2271b1}.paj-admin .paj-st-request{color:#996800;font-weight:600}.paj-admin .paj-st-booked{color:#d63638;font-weight:600}.paj-admin .paj-st-past{color:#a7aaad}
				.paj-admin .paj-log{margin:6px 0 0;color:#646970;font-size:12px}
				.paj-admin details summary{cursor:pointer}
				.paj-admin .paj-form-table th{width:240px}
				.paj-admin textarea.large-text{min-height:90px}
			</style>
			<h1>Jedrenje</h1>
			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo $notice[1] ? 'success' : 'error'; ?> is-dismissible"><p><?php echo esc_html( $notice[0] ); ?></p></div>
			<?php endif; ?>
			<nav class="nav-tab-wrapper">
				<?php foreach ( self::TABS as $key => $label ) : ?>
					<a href="<?php echo esc_url( self::url( $key ) ); ?>" class="nav-tab<?php echo $key === $tab ? ' nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?><?php echo 'zahtjevi' === $key && $new ? ' <span class="awaiting-mod" style="display:inline-block;background:#d63638;color:#fff;border-radius:9px;padding:0 6px;font-size:11px;line-height:17px;">' . (int) $new . '</span>' : ''; ?></a>
				<?php endforeach; ?>
			</nav>
			<div style="margin-top:16px">
				<?php
				switch ( $tab ) {
					case 'rezervacije':
						self::tab_reservations();
						break;
					case 'kalendar':
						self::tab_calendar();
						break;
					case 'cjenik':
						self::tab_prices();
						break;
					case 'postavke':
						self::tab_settings();
						break;
					default:
						self::tab_requests();
				}
				?>
			</div>
		</div>
		<?php
	}

	private static function contact( array $r ): string {
		return esc_html( Plan_A_Jedrenje_Booking::name( $r ) ) . ' · <a href="mailto:' . esc_attr( $r['email'] ) . '">' . esc_html( $r['email'] ) . '</a> · <a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $r['phone'] ) ) . '">' . esc_html( $r['phone'] ) . '</a>'
			. '<br><span style="color:#646970">' . esc_html( $r['address'] . ', ' . $r['postcode'] . ' ' . $r['city'] ) . '</span>';
	}

	private static function meta( array $r ): string {
		$rows = array(
			'Termin'     => Plan_A_Jedrenje_Data::week_long( $r['week'] ),
			'Broj osoba' => (string) $r['persons'],
			'Sadržaj'    => (string) ( $r['concept'] ?? '' ),
			'Ruta'       => $r['route'],
			'Cijena'     => Plan_A_Jedrenje_Data::money( (float) $r['price'] ) . ' za cijeli brod',
			'Zaprimljeno' => $r['created'] ? wp_date( 'j. n. Y. H:i', (int) $r['created'] ) : '',
		);
		$html = '<div class="paj-meta">';
		foreach ( array_filter( $rows, 'strlen' ) as $k => $v ) {
			$html .= '<div><span>' . esc_html( $k ) . ':</span> <strong>' . esc_html( $v ) . '</strong></div>';
		}
		$html .= '</div>';
		if ( '' !== (string) $r['note'] ) {
			$html .= '<p><span style="color:#646970">Napomena:</span> ' . esc_html( $r['note'] ) . '</p>';
		}
		return $html;
	}

	private static function log_html( array $r ): string {
		if ( empty( $r['log'] ) ) {
			return '';
		}
		$html = '<details class="paj-log"><summary>Povijest (' . count( $r['log'] ) . ')</summary><ul>';
		foreach ( array_reverse( $r['log'] ) as $entry ) {
			$html .= '<li>' . esc_html( wp_date( 'j. n. Y. H:i', (int) $entry[0] ) . ' – ' . $entry[1] ) . '</li>';
		}
		return $html . '</ul></details>';
	}

	private static function tab_requests() {
		$list = Plan_A_Jedrenje_Booking::by_state( array( 'zahtjev' ) );
		usort(
			$list,
			static function ( $a, $b ) {
				return $a['created'] <=> $b['created'];
			}
		);
		if ( ! $list ) {
			echo '<p>Nema novih zahtjeva. Novi zahtjev stiže i e-mailom na ' . esc_html( Plan_A_Jedrenje_Data::admin_email() ) . '.</p>';
			return;
		}
		echo '<p>Provjeri brod u charter bazi, zatim klikni „Brod je slobodan, pošalji uplatnicu” (kupac dobiva uplatnicu s 2D kodom za akontaciju) ili „Odbij”.</p><div class="paj-cards">';
		foreach ( $list as $r ) {
			$state  = Plan_A_Jedrenje_Data::state( $r['week'] );
			$others = array_filter(
				Plan_A_Jedrenje_Booking::for_week( $r['week'], array( 'zahtjev', 'potvrdeno', 'rezervirano', 'placeno' ) ),
				static function ( $o ) use ( $r ) {
					return $o['id'] !== $r['id'];
				}
			);
			echo '<div class="paj-rcard is-zahtjev"><h3>' . esc_html( Plan_A_Jedrenje_Data::week_label( $r['week'] ) . ' ' . substr( $r['week'], 0, 4 ) . '.' ) . '</h3>';
			echo '<p>' . self::contact( $r ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escapirano u contact().
			echo self::meta( $r ); // phpcs:ignore WordPress.Security.EscapeOutput
			if ( $others ) {
				echo '<p class="paj-late">Za isti tjedan: ';
				foreach ( $others as $o ) {
					echo esc_html( Plan_A_Jedrenje_Booking::name( $o ) . ' (' . Plan_A_Jedrenje_Booking::STATES[ $o['state'] ] . ') ' );
				}
				echo '</p>';
			}
			if ( 'booked' === $state ) {
				echo '<p class="paj-late">Tjedan je zauzet ili zatvoren u kalendaru.</p>';
			}
			$plan = Plan_A_Jedrenje_Data::payment_plan( $r['week'], (float) $r['price'] );
			echo '<p>' . esc_html( $plan['full'] ? 'Kupac će dobiti uplatnicu za cijeli iznos ' . Plan_A_Jedrenje_Data::money( (float) $r['price'] ) . ' (do ukrcaja je manje od ' . (int) Plan_A_Jedrenje_Data::value( 'rest_days' ) . ' dana).' : 'Kupac će dobiti uplatnicu za akontaciju ' . Plan_A_Jedrenje_Data::money( $plan['deposit'] ) . ' (' . $plan['pct'] . ' %), rok ' . (int) Plan_A_Jedrenje_Data::value( 'deposit_days' ) . ' dana.' ) . '</p>';
			echo self::button( 'confirm', 'Brod je slobodan, pošalji uplatnicu', 'zahtjevi', $r['id'], 'button button-primary', array(), 'Poslati kupcu uplatnicu za ovaj tjedan?' ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<details style="margin-top:6px"><summary class="button">Odbij</summary><div style="margin-top:8px">'
				. self::form_open( 'reject', 'zahtjevi', $r['id'] ) // phpcs:ignore WordPress.Security.EscapeOutput
				. '<p><label>Poruka kupcu (npr. prijedlog drugog tjedna)<br><textarea name="message" rows="4" class="large-text">' . esc_textarea( (string) Plan_A_Jedrenje_Data::value( 'mail_reject' ) ) . '</textarea></label></p>'
				. '<button type="submit" class="button">Odbij i pošalji poruku</button></form></div></details>';
			echo self::log_html( $r ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</div>';
		}
		echo '</div>';
	}

	private static function order_link( int $order_id ): string {
		$order = $order_id ? wc_get_order( $order_id ) : null;
		if ( ! $order ) {
			return '';
		}
		return '<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . (int) $order_id . '</a> <span class="paj-badge">' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</span>';
	}

	private static function payment_rows( array $r ): string {
		$today = Plan_A_Jedrenje_Data::today();
		$html  = '<div class="paj-pay">';
		$first = $r['full'] ? 'cijelo' : 'akontacija';
		$parts = $r['full'] ? array( 'cijelo' ) : array( 'akontacija', 'ostatak' );
		foreach ( $parts as $part ) {
			$amount   = Plan_A_Jedrenje_Booking::part_amount( $r, $part );
			$due      = 'ostatak' === $part ? $r['rest_due'] : $r['deposit_due'];
			$order_id = (int) ( $r['orders'][ $part ] ?? 0 );
			$order    = $order_id ? wc_get_order( $order_id ) : null;
			$line     = '<strong>' . esc_html( Plan_A_Jedrenje_Booking::part_label( $r, $part ) . ': ' . Plan_A_Jedrenje_Data::money( $amount ) ) . '</strong>';
			if ( ! empty( $r['paid'][ $part ] ) ) {
				$line .= ' – <span class="paj-ok">plaćeno ' . esc_html( wp_date( 'j. n. Y.', (int) $r['paid'][ $part ] ) ) . '</span>';
			} elseif ( $order ) {
				$late  = $due && $due < $today;
				$line .= ' – <span class="' . ( $late ? 'paj-late' : 'paj-wait' ) . '">' . esc_html( ( $late ? 'rok je istekao ' : 'čeka uplatu do ' ) . Plan_A_Jedrenje_Data::numeric( $due ) ) . '</span>';
			} elseif ( 'ostatak' === $part ) {
				$line .= ' – rok ' . esc_html( Plan_A_Jedrenje_Data::numeric( $due ) ) . ', uplatnica još nije poslana';
			}
			if ( $order ) {
				$line .= ' · narudžba ' . self::order_link( $order_id );
			}
			$buttons = '';
			if ( empty( $r['paid'][ $part ] ) ) {
				if ( $order && ! $order->is_paid() && ! $order->has_status( array( 'cancelled', 'failed' ) ) ) {
					$buttons .= self::button( 'paid', 'ostatak' === $part ? 'Uplata ostatka je stigla' : ( 'cijelo' === $part ? 'Uplata je stigla' : 'Uplata akontacije je stigla' ), 'rezervacije', $r['id'], 'button button-primary', array( 'part' => $part ), 'Označiti da je uplata stigla? Kupac dobiva potvrdu.' );
					$buttons .= self::button( 'resend', 'Pošalji uplatnicu ponovno', 'rezervacije', $r['id'], 'button', array( 'part' => $part ) );
				} elseif ( 'ostatak' === $part && 'rezervirano' === $r['state'] ) {
					$buttons .= self::button( 'rest', 'Pošalji uplatnicu za ostatak', 'rezervacije', $r['id'], 'button button-primary', array(), 'Poslati kupcu uplatnicu za ostatak?' );
				}
			}
			$html .= '<p>' . $line . '</p>' . ( $buttons ? '<div>' . $buttons . '</div>' : '' );
		}
		unset( $first );
		return $html . '</div>';
	}

	private static function tab_reservations() {
		$active = Plan_A_Jedrenje_Booking::by_state( array( 'potvrdeno', 'rezervirano', 'placeno' ) );
		$today  = Plan_A_Jedrenje_Data::today();
		$soon   = array_filter(
			$active,
			static function ( $r ) use ( $today ) {
				return Plan_A_Jedrenje_Data::add_days( $r['week'], 7 ) >= $today;
			}
		);
		usort(
			$soon,
			static function ( $a, $b ) {
				return strcmp( $a['week'], $b['week'] );
			}
		);
		if ( ! $soon ) {
			echo '<p>Nema potvrđenih rezervacija.</p>';
		} else {
			echo '<div class="paj-cards">';
			foreach ( $soon as $r ) {
				echo '<div class="paj-rcard is-' . esc_attr( $r['state'] ) . '"><h3>' . esc_html( Plan_A_Jedrenje_Data::week_label( $r['week'] ) . ' ' . substr( $r['week'], 0, 4 ) . '.' ) . ' <span class="paj-badge">' . esc_html( Plan_A_Jedrenje_Booking::STATES[ $r['state'] ] ) . '</span></h3>';
				echo '<p>' . self::contact( $r ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
				echo self::meta( $r ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo self::payment_rows( $r ); // phpcs:ignore WordPress.Security.EscapeOutput
				if ( 'placeno' !== $r['state'] ) {
					echo self::button( 'cancel', 'Otkaži rezervaciju i oslobodi tjedan', 'rezervacije', $r['id'], 'button-link-delete button-link', array(), 'Otkazati rezervaciju? Neplaćene narudžbe se otkazuju, a tjedan postaje slobodan. Kupcu se ne šalje e-mail.' ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
				echo self::log_html( $r ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo '</div>';
			}
			echo '</div>';
		}

		$closed = array_merge(
			array_diff_key( $active, $soon ),
			Plan_A_Jedrenje_Booking::by_state( array( 'odbijeno', 'otkazano' ), 100 )
		);
		if ( $closed ) {
			echo '<details style="margin-top:24px"><summary><strong>Prošle, odbijene i otkazane (' . count( $closed ) . ')</strong></summary><div class="paj-cards" style="margin-top:12px">';
			foreach ( $closed as $r ) {
				echo '<div class="paj-rcard is-' . esc_attr( $r['state'] ) . '"><h3>' . esc_html( Plan_A_Jedrenje_Data::week_label( $r['week'] ) . ' ' . substr( $r['week'], 0, 4 ) . '.' ) . ' <span class="paj-badge">' . esc_html( Plan_A_Jedrenje_Booking::STATES[ $r['state'] ] ?? $r['state'] ) . '</span></h3>';
				echo '<p>' . self::contact( $r ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
				echo self::meta( $r ) . self::log_html( $r ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo '</div>';
			}
			echo '</div></details>';
		}
	}

	private static function tab_calendar() {
		$over  = Plan_A_Jedrenje_Data::week_overrides();
		$today = Plan_A_Jedrenje_Data::today();
		$names = array(
			'free'    => 'Slobodno',
			'request' => 'Na upitu',
			'booked'  => 'Zauzeto',
			'past'    => 'Prošlo',
		);
		echo '<p>Sezona se podešava u kartici Postavke, cijene razdoblja u kartici Cjenik. Ovdje možeš tjedan ručno zatvoriti (npr. brod je zauzet izvan weba), označiti ga „Na upitu” (kupci ga vide narančasto i još mogu poslati zahtjev) ili mu upisati drugu cijenu, koja ima prednost. Ako su označena oba, vrijedi „zatvoren”.</p>';
		echo self::form_open( 'calendar', 'kalendar' ); // phpcs:ignore WordPress.Security.EscapeOutput
		foreach ( Plan_A_Jedrenje_Data::years() as $year ) {
			list( $first, $last ) = Plan_A_Jedrenje_Data::season( $year );
			$weeks = Plan_A_Jedrenje_Data::season_weeks( $year );
			if ( ! $weeks || end( $weeks ) <= $today ) {
				continue; // sezona je prošla
			}
			echo '<h2>Sezona ' . (int) $year . '. <small style="font-weight:400">(' . esc_html( Plan_A_Jedrenje_Data::numeric( $first ) . ' – ' . Plan_A_Jedrenje_Data::numeric( $last ) ) . ')</small></h2>';
			echo '<table class="widefat striped paj-table"><thead><tr><th>Tjedan</th><th>Cijena (za cijeli brod)</th><th>Odakle</th><th>Stanje</th><th>Ručno stanje</th><th>Ručna cijena</th></tr></thead><tbody>';
			foreach ( $weeks as $week ) {
				$price  = Plan_A_Jedrenje_Data::price( $week );
				$state  = Plan_A_Jedrenje_Data::state( $week );
				$closed = ! empty( $over[ $week ]['closed'] );
				$asked  = ! empty( $over[ $week ]['request'] );
				$who    = Plan_A_Jedrenje_Booking::for_week( $week, array( 'zahtjev', 'potvrdeno', 'rezervirano', 'placeno' ) );
				$label  = $closed ? 'Zatvoreno ručno' : $names[ $state ] . ( $asked && 'request' === $state && ! Plan_A_Jedrenje_Booking::for_week( $week, array( 'zahtjev', 'potvrdeno' ) ) ? ' (ručno)' : '' );
				if ( $who ) {
					$label .= ' – ' . implode( ', ', array_map( array( 'Plan_A_Jedrenje_Booking', 'name' ), $who ) );
				}
				$past = $week <= $today;
				echo '<tr' . ( $past ? ' style="opacity:.55"' : '' ) . '><td><strong>' . esc_html( Plan_A_Jedrenje_Data::week_label( $week ) ) . '</strong><input type="hidden" name="weeks[]" value="' . esc_attr( $week ) . '"></td>'
					. '<td>' . esc_html( Plan_A_Jedrenje_Data::money( $price['price'] ) ) . ( $price['regular'] ? ' <del>' . esc_html( Plan_A_Jedrenje_Data::money( $price['regular'] ) ) . '</del>' : '' ) . '</td>'
					. '<td>' . esc_html( $price['label'] ) . '</td>'
					. '<td class="paj-st-' . esc_attr( $closed ? 'booked' : $state ) . '">' . esc_html( $label ) . '</td>'
					. '<td><label style="display:block"><input type="checkbox" name="closed[' . esc_attr( $week ) . ']" value="1"' . checked( $closed, true, false ) . '> zatvoren</label>'
					. '<label style="display:block;margin-top:4px"><input type="checkbox" name="request[' . esc_attr( $week ) . ']" value="1"' . checked( $asked, true, false ) . '> na upitu</label></td>'
					. '<td><input type="text" name="price[' . esc_attr( $week ) . ']" value="' . esc_attr( isset( $over[ $week ]['price'] ) ? (string) $over[ $week ]['price'] : '' ) . '" placeholder="npr. 5800" inputmode="decimal"> €</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '<p><button type="submit" class="button button-primary">Spremi kalendar</button></p></form>';
	}

	private static function tab_prices() {
		$s       = Plan_A_Jedrenje_Data::get();
		$periods = Plan_A_Jedrenje_Data::periods();
		echo self::form_open( 'prices', 'cjenik' ); // phpcs:ignore WordPress.Security.EscapeOutput
		?>
		<table class="form-table paj-form-table">
			<tr><th><label for="paj-base">Osnovna cijena tjedna (za cijeli brod)</label></th><td><input id="paj-base" type="text" name="base_price" value="<?php echo esc_attr( (string) $s['base_price'] ); ?>" inputmode="decimal"> € <p class="description">Vrijedi za tjedan koji ne pada ni u jedno razdoblje.</p></td></tr>
			<tr><th><label for="paj-reg">Redovna cijena (precrtana, neobavezno)</label></th><td><input id="paj-reg" type="text" name="base_regular" value="<?php echo esc_attr( $s['base_regular'] ? (string) $s['base_regular'] : '' ); ?>" inputmode="decimal"> € <p class="description">Npr. 700 € po osobi × 8 = 5600; prikazuje se precrtano samo ako je veća od cijene.</p></td></tr>
		</table>
		<h2>Razdoblja</h2>
		<p>Tjedan dobiva cijenu razdoblja u koje pada subota ukrcaja. Datumi kao 15.5.2027. Ručna cijena u kartici Kalendar ima prednost.</p>
		<table class="widefat striped paj-table">
			<thead><tr><th>Naziv</th><th>Od</th><th>Do</th><th>Cijena tjedna (€)</th><th>Redovna precrtana (€)</th><th>Obriši</th></tr></thead>
			<tbody>
				<?php
				$rows = array_merge( $periods, array_fill( 0, 3, array() ) );
				foreach ( $rows as $i => $p ) :
					?>
					<tr>
						<td><input type="text" name="periods[<?php echo (int) $i; ?>][name]" value="<?php echo esc_attr( $p['name'] ?? '' ); ?>" placeholder="npr. Špica" style="width:150px"></td>
						<td><input type="text" name="periods[<?php echo (int) $i; ?>][from]" value="<?php echo esc_attr( ! empty( $p['from'] ) ? gmdate( 'j.n.Y.', strtotime( $p['from'] ) ) : '' ); ?>" placeholder="1.7.2027."></td>
						<td><input type="text" name="periods[<?php echo (int) $i; ?>][to]" value="<?php echo esc_attr( ! empty( $p['to'] ) ? gmdate( 'j.n.Y.', strtotime( $p['to'] ) ) : '' ); ?>" placeholder="31.8.2027."></td>
						<td><input type="text" name="periods[<?php echo (int) $i; ?>][price]" value="<?php echo esc_attr( isset( $p['price'] ) ? (string) $p['price'] : '' ); ?>" inputmode="decimal"></td>
						<td><input type="text" name="periods[<?php echo (int) $i; ?>][regular]" value="<?php echo esc_attr( ! empty( $p['regular'] ) ? (string) $p['regular'] : '' ); ?>" inputmode="decimal"></td>
						<td><?php if ( $p ) : ?><label><input type="checkbox" name="periods[<?php echo (int) $i; ?>][delete]" value="1"> obriši</label><?php endif; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description">Za više razdoblja spremi pa se pojave nova prazna polja.</p>
		<p><button type="submit" class="button button-primary">Spremi cjenik</button></p>
		</form>
		<?php
	}

	private static function tab_settings() {
		$s = Plan_A_Jedrenje_Data::get();
		echo self::form_open( 'settings', 'postavke' ); // phpcs:ignore WordPress.Security.EscapeOutput
		$text = static function ( $key, $label, $help = '' ) use ( $s ) {
			echo '<tr><th><label for="paj-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input type="text" class="regular-text" id="paj-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) $s[ $key ] ) . '">' . ( $help ? '<p class="description">' . esc_html( $help ) . '</p>' : '' ) . '</td></tr>';
		};
		$num  = static function ( $key, $label, $help = '' ) use ( $s ) {
			echo '<tr><th><label for="paj-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input type="number" min="0" id="paj-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) $s[ $key ] ) . '" style="width:90px">' . ( $help ? '<p class="description">' . esc_html( $help ) . '</p>' : '' ) . '</td></tr>';
		};
		$area = static function ( $key, $label, $help = '' ) use ( $s ) {
			echo '<tr><th><label for="paj-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><textarea class="large-text" rows="5" id="paj-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">' . esc_textarea( (string) $s[ $key ] ) . '</textarea>' . ( $help ? '<p class="description">' . esc_html( $help ) . '</p>' : '' ) . '</td></tr>';
		};
		?>
		<h2>Program</h2>
		<table class="form-table paj-form-table">
			<?php
			$text( 'title', 'Naziv programa' );
			$text( 'boat', 'Brod' );
			$num( 'cabins', 'Broj kabina' );
			$text( 'skipper', 'Posada', 'npr. skiper' );
			$text( 'marina', 'Polazak i povratak', 'npr. ACI marina Split' );
			$text( 'embark_time', 'Ukrcaj (subota)', 'npr. 17:00' );
			$text( 'disembark_time', 'Iskrcaj (subota)', 'npr. 09:00' );
			$area( 'included', 'Uključeno', 'Jedna stavka u retku; prikazuje se s kvačicama.' );
			$area( 'excluded', 'Nije uključeno', 'Jedna stavka u retku; prikazuje se sitnije.' );
			$area( 'concepts', 'Sadržaji (kupac bira, može više)', 'Svaki sadržaj u svom bloku: prvi redak naziv, ispod kratki opis; između sadržaja prazan redak. Prazno = bez odabira.' );
			$area( 'routes', 'Ponuđene rute', 'Svaka ruta u svom bloku: prvi redak naziv, drugi kratki opis, zatim plan po danima ("Subota: …"); između ruta prazan redak.' );
			$area( 'leaders', 'Tko vas vodi', 'Jedan odlomak u retku. "Na moru: …" ističe riječi prije dvotočke.' );
			$area( 'important', 'Važno znati', 'Jedna stavka u retku.' );
			$text( 'contact_name', 'Kontakt: ime', 'Prikazuje se kao „Pitanja i dogovor: Igor, 095 …”.' );
			$text( 'contact_phone', 'Kontakt: telefon' );
			$num( 'min_persons', 'Najmanji broj sudionika' );
			$num( 'max_persons', 'Najveći broj sudionika' );
			?>
		</table>
		<h2>Fotografije</h2>
		<table class="form-table paj-form-table">
			<tr><th><label for="paj-gallery">Slajder s fotografijama</label></th><td>
				<select id="paj-gallery" name="gallery_tour">
					<option value="0" <?php selected( (int) $s['gallery_tour'], 0 ); ?>>Automatski – izlet s „jedrenje” u nazivu</option>
					<option value="-1" <?php selected( (int) $s['gallery_tour'], -1 ); ?>>Ne prikazuj slajder</option>
					<?php
					$tours = get_posts(
						array(
							'post_type'      => class_exists( 'TTBM_Function' ) ? TTBM_Function::get_cpt_name() : 'ttbm_tour',
							'post_status'    => Plan_A_Jedrenje_Slider::STATUSES,
							'posts_per_page' => 300,
							'orderby'        => 'title',
							'order'          => 'ASC',
						)
					);
					foreach ( $tours as $t ) :
						?>
						<option value="<?php echo (int) $t->ID; ?>" <?php selected( (int) $s['gallery_tour'], (int) $t->ID ); ?>><?php echo esc_html( wp_strip_all_tags( get_the_title( $t ) ) . ( 'publish' === $t->post_status ? '' : ' (isključen)' ) . ' (' . count( Plan_A_Jedrenje_Slider::image_ids( (int) $t->ID ) ) . ' slika)' ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description">Ako nisu odabrane vlastite fotografije (ispod), slajder uzima istaknutu sliku i galeriju odabranog izleta (WpTravelly → izlet → Gallery), i kad je izlet isključen (skica). Izlet iz galerije: <?php $paj_t = Plan_A_Jedrenje_Slider::tour_id(); echo esc_html( $paj_t ? wp_strip_all_tags( get_the_title( $paj_t ) ) . ', ' . count( Plan_A_Jedrenje_Slider::image_ids( $paj_t ) ) . ' slika' : 'nema slika' ); ?>. Slajder možeš staviti i drugdje: [plan-a-jedrenje-slike].</p>
			</td></tr>
			<tr><th>Vlastite fotografije</th><td id="paj-photos">
				<?php $paj_own = Plan_A_Jedrenje_Slider::own_ids(); ?>
				<input type="hidden" id="paj-gallery-images" name="gallery_images" value="<?php echo esc_attr( implode( ',', $paj_own ) ); ?>">
				<div class="paj-photos__list" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px">
					<?php foreach ( $paj_own as $paj_id ) : ?>
						<?php echo wp_get_attachment_image( $paj_id, 'thumbnail', false, array( 'style' => 'width:72px;height:72px;object-fit:cover;border-radius:6px' ) ); ?>
					<?php endforeach; ?>
				</div>
				<button type="button" class="button paj-photos__pick">Odaberi fotografije iz medija</button>
				<button type="button" class="button-link paj-photos__clear" style="margin-left:8px">Ukloni sve</button>
				<p class="description">Odabrane fotografije imaju prednost pred galerijom izleta (najviše 40). Nakon odabira klikni „Spremi postavke”.</p>
			</td></tr>
		</table>
		<h2>Popis i plan izleta</h2>
		<table class="form-table paj-form-table">
			<tr><th><label for="paj-list">Jedrenje u popisu i planu izleta</label></th><td>
				<select id="paj-list" name="list_tour">
					<option value="0" <?php selected( (int) $s['list_tour'] >= 0 ); ?>>Prikaži kao zasebnu karticu i redak</option>
					<option value="-1" <?php selected( (int) $s['list_tour'], -1 ); ?>>Ne prikazuj</option>
				</select>
				<p class="description">Stranica s rezervacijom<?php $paj_p = Plan_A_Jedrenje_Izleti::page_url(); echo $paj_p ? ' (' . esc_html( $paj_p ) . ')' : ''; ?> dolazi u popis izleta (dodatak Plan A izleti) kao zasebna kartica sa slobodnim tjednima i cijenom „od … za cijeli brod”, a u plan izleta kao jedan redak za cijelu sezonu. Izleti iz WpTravellyja ostaju kakvi jesu.</p>
			</td></tr>
		</table>
		<h2>Sezona</h2>
		<p>Prva subota sezone (prvi ukrcaj) i zadnja subota sezone (zadnji iskrcaj), za svaku godinu posebno. Prazno = od sredine svibnja do sredine listopada. Datumi kao 15.5.2027.</p>
		<table class="form-table paj-form-table">
			<?php
			$y = (int) substr( Plan_A_Jedrenje_Data::today(), 0, 4 );
			for ( $year = $y; $year <= $y + 2; $year++ ) :
				list( $first, $last ) = Plan_A_Jedrenje_Data::season( $year );
				$saved = (array) ( $s['seasons'][ $year ] ?? array() );
				?>
				<tr><th><?php echo (int) $year; ?>.</th><td>
					prva subota <input type="text" name="seasons[<?php echo (int) $year; ?>][first]" value="<?php echo esc_attr( ! empty( $saved['first'] ) ? gmdate( 'j.n.Y.', strtotime( $saved['first'] ) ) : '' ); ?>" placeholder="<?php echo esc_attr( gmdate( 'j.n.Y.', strtotime( $first ) ) ); ?>" style="width:120px">
					zadnja subota <input type="text" name="seasons[<?php echo (int) $year; ?>][last]" value="<?php echo esc_attr( ! empty( $saved['last'] ) ? gmdate( 'j.n.Y.', strtotime( $saved['last'] ) ) : '' ); ?>" placeholder="<?php echo esc_attr( gmdate( 'j.n.Y.', strtotime( $last ) ) ); ?>" style="width:120px">
					<span class="description">sada: <?php echo esc_html( Plan_A_Jedrenje_Data::numeric( $first ) . ' – ' . Plan_A_Jedrenje_Data::numeric( $last ) . ' (' . count( Plan_A_Jedrenje_Data::season_weeks( $year ) ) . ' tjedana)' ); ?></span>
				</td></tr>
			<?php endfor; ?>
		</table>
		<h2>Plaćanje</h2>
		<table class="form-table paj-form-table">
			<?php
			$num( 'deposit_pct', 'Akontacija (%)' );
			$num( 'deposit_days', 'Rok za akontaciju (dana od potvrde)' );
			$num( 'rest_days', 'Rok za ostatak (dana prije ukrcaja)', 'Ako je do ukrcaja manje od ovoga, odmah se traži cijeli iznos.' );
			$num( 'lead_days', 'Najmanje dana do ukrcaja', 'Tjedni koji počinju prije toga ne nude se u kalendaru (brod treba stići potvrditi).' );
			$text( 'admin_email', 'E-mail za zahtjeve i podsjetnike', 'Prazno = e-mail administratora stranice.' );
			?>
		</table>
		<h2>Tekstovi</h2>
		<table class="form-table paj-form-table">
			<?php
			$area( 'mail_request', 'E-mail: zahtjev je zaprimljen' );
			$area( 'mail_confirm', 'E-mail s uplatnicom: uvod' );
			$area( 'mail_reject', 'E-mail: odbijanje (zadana poruka)' );
			$area( 'mail_booked', 'E-mail: akontacija plaćena' );
			$area( 'mail_paid', 'E-mail: sve plaćeno' );
			$area( 'cancel_terms', 'Uvjeti otkaza', 'Jedan uvjet u retku; prikazuju se u bloku i e-mailovima.' );
			$text( 'organizer', 'Organizator', 'Prikazuje se sitnim slovima u bloku i na svim e-mailovima.' );
			?>
		</table>
		<p><button type="submit" class="button button-primary">Spremi postavke</button></p>
		</form>
		<?php
	}
}
