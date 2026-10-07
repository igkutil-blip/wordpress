<?php
/**
 * Administracija: izbornik "Poklon bonovi" s karticama Čekaju uplatu, Izdani bonovi,
 * Ručno izdavanje i Postavke; okvir "Poklon bon" na stranici narudžbe.
 *
 * @package Plan_A_Poklon_Bon
 */

defined( 'ABSPATH' ) || exit;

class Plan_A_Bon_Admin {

	const CAP      = 'manage_woocommerce';
	const PAGE     = 'plan-a-bon';
	const PER_PAGE = 30;

	/** Statusi narudžbi koje čekaju uplatu. */
	const WAITING = array( 'on-hold', 'pending' );

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'backfill' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'admin_post_papb_paid', array( __CLASS__, 'paid' ) );
		add_action( 'admin_post_papb_file', array( __CLASS__, 'download' ) );
		add_action( 'admin_post_papb_resend', array( __CLASS__, 'resend' ) );
		add_action( 'admin_post_papb_issue', array( __CLASS__, 'issue' ) );
		add_action( 'admin_post_papb_csv', array( __CLASS__, 'csv' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ), 20, 2 );
		add_action( 'woocommerce_after_order_itemmeta', array( __CLASS__, 'order_item' ), 10, 2 );
	}

	private static function url( string $tab = '', array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), '' !== $tab ? array( 'tab' => $tab ) : array(), $args ), admin_url( 'admin.php' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Izbornik                                                             */
	/* ------------------------------------------------------------------ */

	public static function menu() {
		$count = current_user_can( self::CAP ) ? count( self::waiting_orders() ) : 0;
		$title = 'Poklon bonovi' . ( $count ? ' <span class="awaiting-mod count-' . $count . '"><span class="pending-count">' . $count . '</span></span>' : '' );
		// Ikona poklona (SVG; WordPress je boja kao ostale ikone izbornika).
		$icon = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M17 7h-2.2A2.5 2.5 0 0 0 10 4.3 2.5 2.5 0 0 0 5.2 7H3a1 1 0 0 0-1 1v2a1 1 0 0 0 1 1h.5v5a1 1 0 0 0 1 1h11a1 1 0 0 0 1-1v-5h.5a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1zM12.5 5.5a1 1 0 0 1 0 2h-1.8l.7-1.4a1 1 0 0 1 1.1-.6zM6.7 6.5a1 1 0 0 1 1.9-.4l.7 1.4H7.5a1 1 0 0 1-.8-1zM3.5 8.5h5.75v1H3.5zm1.5 2.5h4.25v4.5H5zm5.75 4.5V11H15v4.5zm0-6v-1h5.75v1z"/></svg>' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- ikona izbornika.
		add_menu_page( 'Poklon bonovi', $title, self::CAP, self::PAGE, array( __CLASS__, 'page' ), $icon, 56 );
	}

	public static function assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$orders = $screen && in_array( $screen->id, self::order_screens(), true );
		if ( false === strpos( (string) $hook, self::PAGE ) && ! $orders ) {
			return;
		}
		wp_enqueue_script( 'plan-a-poklon-bon-admin', PLAN_A_BON_URL . 'assets/js/admin.js', array( 'jquery' ), PLAN_A_BON_VERSION, true );
		wp_add_inline_style(
			'common',
			'.papb-admin .papb-status{display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600;white-space:nowrap}'
			. '.papb-status--aktivan{background:#e6f4ea;color:#1e7e34}.papb-status--iskoristen{background:#eef1f4;color:#50575e}.papb-status--istekao{background:#fcf0f1;color:#b32d2e}.papb-status--ceka{background:#fff4e5;color:#8a4b00}'
			. '.papb-admin code.papb-code{font-size:13px;font-weight:600;white-space:nowrap}.papb-admin details summary{cursor:pointer;color:#2271b1}.papb-admin details form{margin-top:6px;display:flex;gap:6px;flex-wrap:wrap}'
			. '.papb-admin td small,.papb-box small{color:#646970}.papb-admin .papb-search{display:flex;gap:6px;flex-wrap:wrap;margin:12px 0}'
			. '.papb-admin .button.papb-paid,.papb-box .button.papb-paid{display:inline-flex;align-items:center;min-height:40px;padding:4px 16px;font-size:14px;font-weight:600;background:#1e7e34;border-color:#1e7e34;color:#fff}'
			. '.papb-admin .button.papb-paid:hover,.papb-box .button.papb-paid:hover{background:#176a2b;border-color:#176a2b;color:#fff}'
			. '.papb-admin .papb-actions{display:flex;flex-wrap:wrap;gap:4px 10px}.papb-admin .papb-history{margin:4px 0 0;padding-left:18px;list-style:disc}.papb-admin .papb-history li{margin:2px 0}'
			. '.papb-admin .papb-issued tr.papb-row td{border-top:1px solid #dcdcde}.papb-admin .papb-issued tr.papb-history-row td{padding-top:0;color:#50575e}'
			. '.papb-box .papb-box__item{margin:0 0 10px;padding:0 0 10px;border-bottom:1px solid #f0f0f1}.papb-box .papb-box__item:last-child{border:0;margin:0;padding:0}'
		);
	}

	private static function order_screens(): array {
		$screens = array( 'shop_order' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screens[] = wc_get_page_screen_id( 'shop-order' );
		}
		return array_unique( $screens );
	}

	private static function check( string $nonce_action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Nemate ovlasti.', 'plan-a-poklon-bon' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $nonce_action );
	}

	/**
	 * Poruka nakon radnje (prikazuje se jednom, samo ovom korisniku).
	 */
	private static function flash( string $type, string $text ) {
		set_transient( 'papb_notice_' . get_current_user_id(), array( $type, $text ), 120 );
	}

	public static function notice() {
		$notice = get_transient( 'papb_notice_' . get_current_user_id() );
		if ( ! is_array( $notice ) ) {
			return;
		}
		delete_transient( 'papb_notice_' . get_current_user_id() );
		echo '<div class="notice notice-' . esc_attr( $notice[0] ) . ' is-dismissible"><p>' . esc_html( $notice[1] ) . '</p></div>';
	}

	private static function back( string $fallback ) {
		$to = wp_get_referer();
		wp_safe_redirect( $to ? remove_query_arg( array( 'papb_err' ), $to ) : $fallback );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Narudžbe koje čekaju uplatu                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * @return WC_Order[]
	 */
	public static function waiting_orders(): array {
		$orders = wc_get_orders(
			array(
				'status'     => self::WAITING,
				'limit'      => 200,
				'orderby'    => 'date',
				'order'      => 'DESC',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_papb_has_voucher',
						'value' => '1',
					),
				),
			)
		);
		return array_values(
			array_filter(
				$orders,
				static function ( $order ) {
					return $order instanceof WC_Order && Plan_A_Bon::has_voucher_items( $order ) && ! Plan_A_Bon::order_vouchers( $order );
				}
			)
		);
	}

	/**
	 * Jednokratno: oznaka i za narudžbe bona izrađene prije inačice 1.1.0.
	 */
	public static function backfill() {
		if ( (int) get_option( 'plan_a_bon_backfill', 0 ) >= 1 || ! current_user_can( self::CAP ) ) {
			return;
		}
		update_option( 'plan_a_bon_backfill', 1, false );
		foreach ( wc_get_orders( array( 'limit' => 300, 'orderby' => 'date', 'order' => 'DESC' ) ) as $order ) {
			if ( $order instanceof WC_Order && '' === (string) $order->get_meta( '_papb_has_voucher' ) && Plan_A_Bon::has_voucher_items( $order ) ) {
				$order->update_meta_data( '_papb_has_voucher', 1 );
				$order->save();
			}
		}
	}

	private static function paid_url( WC_Order $order ): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=papb_paid&order=' . $order->get_id() ), 'papb_paid_' . $order->get_id() );
	}

	private static function paid_button( WC_Order $order ): string {
		return '<a class="button papb-paid" href="' . esc_url( self::paid_url( $order ) ) . '" onclick="return confirm(\'' . esc_js( __( 'Jesi li provjerio da je uplata stigla?', 'plan-a-poklon-bon' ) ) . '\');">' . esc_html__( 'Uplata je stigla, pošalji bon', 'plan-a-poklon-bon' ) . '</a>';
	}

	/**
	 * Bonovi u narudžbi kao tekst: "90,00 € za Ana".
	 */
	private static function order_bons( WC_Order $order ): array {
		$out = array();
		foreach ( $order->get_items() as $item ) {
			if ( '' !== (string) $item->get_meta( '_papb_amount' ) ) {
				$out[] = array( (float) $item->get_meta( '_papb_amount' ), (string) $item->get_meta( '_papb_to' ) );
			}
		}
		return $out;
	}

	/**
	 * "Uplata je stigla, pošalji bon": narudžba u obradu, izdavanje, PDF/PNG i e-mail kupcu.
	 */
	public static function paid() {
		$id = absint( $_GET['order'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- provjera u check().
		self::check( 'papb_paid_' . $id );
		$order = wc_get_order( $id );
		if ( ! $order || ! Plan_A_Bon::has_voucher_items( $order ) ) {
			self::flash( 'error', 'Narudžba ne postoji ili nema poklon bon.' );
			self::back( self::url( 'cekaju' ) );
		}
		$started = time();
		$ids     = Plan_A_Bon::mark_paid( $order );
		$order   = wc_get_order( $id );
		if ( ! $ids ) {
			self::flash( 'error', sprintf( 'Bon nije izdan (narudžba #%1$s je u statusu "%2$s"). Pogledajte bilješke narudžbe.', $order->get_order_number(), wc_get_order_status_name( $order->get_status() ) ) );
			self::back( self::url( 'cekaju' ) );
		}
		$codes = array();
		$sent  = true;
		foreach ( $ids as $coupon_id ) {
			$v       = Plan_A_Bon_Voucher::get( $coupon_id );
			$codes[] = $v['code'];
			$sent    = $sent && (int) get_post_meta( $coupon_id, '_papb_sent', true ) >= $started - 5;
		}
		$email = $order->get_billing_email();
		if ( $sent ) {
			self::flash( 'success', sprintf( '%1$s %2$s poslan na %3$s.', count( $codes ) > 1 ? 'Bonovi' : 'Bon', implode( ', ', $codes ), $email ) );
		} else {
			self::flash( 'warning', sprintf( 'Bon %1$s je izdan, ali e-mail na %2$s nije poslan. Pošaljite ga gumbom "Ponovno pošalji" na kartici Izdani bonovi.', implode( ', ', $codes ), $email ) );
		}
		self::back( self::url( 'cekaju' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Stranica s karticama                                                 */
	/* ------------------------------------------------------------------ */

	public static function page() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$tabs = array(
			'cekaju'   => 'Čekaju uplatu',
			'izdani'   => 'Izdani bonovi',
			'rucno'    => 'Ručno izdavanje',
			'postavke' => 'Postavke',
		);
		$tab  = sanitize_key( wp_unslash( $_GET['tab'] ?? 'cekaju' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab  = isset( $tabs[ $tab ] ) ? $tab : 'cekaju';
		$wait = count( self::waiting_orders() );
		?>
		<div class="wrap papb-admin">
			<h1>Poklon bonovi</h1>
			<?php if ( 'postavke' === $tab ) : ?>
				<?php settings_errors(); ?>
			<?php endif; ?>
			<nav class="nav-tab-wrapper" aria-label="Poklon bonovi">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a href="<?php echo esc_url( self::url( $key ) ); ?>" class="nav-tab<?php echo $key === $tab ? ' nav-tab-active' : ''; ?>"<?php echo $key === $tab ? ' aria-current="page"' : ''; ?>>
						<?php echo esc_html( $label ); ?>
						<?php if ( 'cekaju' === $key && $wait ) : ?>
							<span class="awaiting-mod" style="display:inline-block;min-width:18px;height:18px;margin-left:4px;padding:0 5px;border-radius:9px;background:#d63638;color:#fff;font-size:11px;line-height:18px;text-align:center;box-sizing:border-box;"><?php echo esc_html( (string) $wait ); ?></span>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<?php
			switch ( $tab ) {
				case 'izdani':
					self::tab_issued();
					break;
				case 'rucno':
					self::tab_manual();
					break;
				case 'postavke':
					Plan_A_Bon_Settings::form();
					break;
				default:
					self::tab_waiting();
			}
			?>
		</div>
		<?php
	}

	private static function tab_waiting() {
		$orders = self::waiting_orders();
		?>
		<p>Narudžbe poklon bona plaćene uplatnicom koje još nisu označene kao plaćene. Kad uplata stigne na račun, kliknite gumb: narudžba prelazi u "U obradi", bon se izdaje, a PDF i slika šalju se kupcu e-mailom.</p>
		<table class="widefat striped">
			<thead><tr><th>Narudžba</th><th>Kupac</th><th>Iznos za uplatu</th><th>Bon</th><th>Datum narudžbe</th><th></th></tr></thead>
			<tbody>
			<?php if ( ! $orders ) : ?>
				<tr><td colspan="6">Nema narudžbi koje čekaju uplatu.</td></tr>
			<?php endif; ?>
			<?php foreach ( $orders as $order ) : ?>
				<tr>
					<td><a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>"><strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong></a><br><small><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) . ' · ' . $order->get_payment_method_title() ); ?></small></td>
					<td><?php echo esc_html( trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) ); ?><br><small><?php echo esc_html( $order->get_billing_email() ); ?></small></td>
					<td><strong><?php echo esc_html( Plan_A_Bon_Voucher::money( (float) $order->get_total() ) ); ?></strong></td>
					<td>
						<?php foreach ( self::order_bons( $order ) as $bon ) : ?>
							<?php echo esc_html( Plan_A_Bon_Voucher::money( $bon[0] ) . ' za ' . $bon[1] ); ?><br>
						<?php endforeach; ?>
					</td>
					<td><?php echo esc_html( $order->get_date_created() ? wp_date( 'j.n.Y. H:i', $order->get_date_created()->getTimestamp() ) : '' ); ?></td>
					<td><?php echo self::paid_button( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapano u paid_button(). ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Izdani bonovi                                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * ID-evi bonova (najnoviji prvi), uz pretragu po kodu i imenima.
	 *
	 * @return int[]
	 */
	private static function ids( string $search ): array {
		$base = array(
			'post_type'      => 'shop_coupon',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_papb',
					'value' => '1',
				),
			),
		);
		if ( '' === $search ) {
			return array_map( 'intval', get_posts( $base ) );
		}
		$by_code                 = $base;
		$by_code['s']            = $search;
		$by_name                 = $base;
		$by_name['meta_query'][] = array(
			'relation' => 'OR',
			array( 'key' => '_papb_to', 'value' => $search, 'compare' => 'LIKE' ),
			array( 'key' => '_papb_from', 'value' => $search, 'compare' => 'LIKE' ),
			array( 'key' => '_papb_buyer', 'value' => $search, 'compare' => 'LIKE' ),
			array( 'key' => '_papb_email', 'value' => $search, 'compare' => 'LIKE' ),
		);
		$ids = array_unique( array_merge( get_posts( $by_code ), get_posts( $by_name ) ) );
		rsort( $ids );
		return array_map( 'intval', $ids );
	}

	private static function search(): string {
		return isset( $_GET['s'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- pretraga.
	}

	/**
	 * Ostatak bona kao tekst.
	 */
	private static function rest_text( array $v ): string {
		if ( 'iskoristen' !== $v['status'] ) {
			return 'istekao' === $v['status'] ? Plan_A_Bon_Voucher::money( 0 ) : Plan_A_Bon_Voucher::money( $v['amount'] );
		}
		if ( $v['child'] ) {
			$child = Plan_A_Bon_Voucher::get( $v['child'] );
			if ( $child ) {
				return Plan_A_Bon_Voucher::money( $child['amount'] ) . ' → ' . $child['code'];
			}
		}
		return Plan_A_Bon_Voucher::money( 0 );
	}

	/**
	 * Povijest korištenja: datum, narudžba, iskorišteni iznos i novi kod s ostatkom.
	 * S $html = true broj narudžbe je poveznica (escapano ovdje), inače običan tekst (CSV).
	 *
	 * @return string[]
	 */
	private static function history_lines( array $v, bool $html = false ): array {
		$esc   = static function ( $text ) use ( $html ) {
			return $html ? esc_html( $text ) : $text;
		};
		$lines = array();
		foreach ( Plan_A_Bon_Voucher::history( $v['id'] ) as $entry ) {
			$order  = wc_get_order( (int) ( $entry['order'] ?? 0 ) );
			$number = '#' . ( $order ? $order->get_order_number() : (int) $entry['order'] );
			$ref    = $html && $order ? '<a href="' . esc_url( $order->get_edit_order_url() ) . '">narudžba ' . esc_html( $number ) . '</a>' : $esc( 'narudžba ' . $number );
			$line   = $esc( wp_date( 'j.n.Y.', (int) $entry['date'] ) ) . ' · ' . $ref . ' · ' . $esc( 'iskorišteno ' . Plan_A_Bon_Voucher::money( (float) $entry['used'] ) );
			if ( ! empty( $entry['new'] ) ) {
				$new   = Plan_A_Bon_Voucher::get( (int) $entry['new'] );
				$code  = $new ? $new['code'] : '';
				$line .= $esc( ' · ostatak ' . Plan_A_Bon_Voucher::money( (float) $entry['rest'] ) . ' → novi kod ' ) . ( $html ? '<strong>' . esc_html( $code ) . '</strong>' : $code );
			} elseif ( ! empty( $entry['lost'] ) ) {
				$line .= $esc( ' · ostatak ' . Plan_A_Bon_Voucher::money( (float) $entry['rest'] ) . ' propao' );
			}
			$lines[] = $line;
		}
		if ( ! $lines && 'iskoristen' === $v['status'] ) {
			// Bonovi iskorišteni prije inačice 1.1.0: narudžba je zapisana kao "_papb_rest_{ID bona}".
			$orders = wc_get_orders(
				array(
					'limit'      => 1,
					'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'     => '_papb_rest_' . $v['id'],
							'compare' => 'EXISTS',
						),
					),
				)
			);
			$order = $orders ? $orders[0] : null;
			if ( $order instanceof WC_Order ) {
				$rest    = (float) $order->get_meta( '_papb_rest_' . $v['id'] );
				$date    = $order->get_date_paid() ? $order->get_date_paid() : $order->get_date_created();
				$number  = '#' . $order->get_order_number();
				$ref     = $html ? '<a href="' . esc_url( $order->get_edit_order_url() ) . '">narudžba ' . esc_html( $number ) . '</a>' : 'narudžba ' . $number;
				$line    = $esc( $date ? wp_date( 'j.n.Y.', $date->getTimestamp() ) : '' ) . ' · ' . $ref . ' · ' . $esc( 'iskorišteno ' . Plan_A_Bon_Voucher::money( max( 0, $v['amount'] - $rest ) ) );
				$child   = $v['child'] ? Plan_A_Bon_Voucher::get( $v['child'] ) : array();
				if ( $child ) {
					$line .= $esc( ' · ostatak ' . Plan_A_Bon_Voucher::money( $rest ) . ' → novi kod ' ) . ( $html ? '<strong>' . esc_html( $child['code'] ) . '</strong>' : $child['code'] );
				}
				$lines[] = $line;
			} else {
				$lines[] = $esc( 'primijenjen u narudžbi koja još čeka uplatu' );
			}
		}
		return $lines;
	}

	private static function file_url( int $id, string $format, bool $inline = false ): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=papb_file&id=' . $id . '&f=' . $format . ( $inline ? '&inline=1' : '' ) ), 'papb_file_' . $id );
	}

	private static function tab_issued() {
		$search = self::search();
		$ids    = self::ids( $search );
		$total  = count( $ids );
		$paged  = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$pages  = max( 1, (int) ceil( $total / self::PER_PAGE ) );
		$paged  = min( $paged, $pages );
		$ids    = array_slice( $ids, ( $paged - 1 ) * self::PER_PAGE, self::PER_PAGE );
		?>
		<form method="get" class="papb-search">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
			<input type="hidden" name="tab" value="izdani">
			<label class="screen-reader-text" for="papb-s">Pretraga</label>
			<input type="search" id="papb-s" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Kod ili ime" class="regular-text">
			<button type="submit" class="button">Traži</button>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'papb_csv', 's' => rawurlencode( $search ) ), admin_url( 'admin-post.php' ) ), 'papb_csv' ) ); ?>">Izvoz u CSV</a>
			<span class="description" style="align-self:center;"><?php echo esc_html( $total . ' ' . ( 1 === $total % 10 && 11 !== $total % 100 ? 'bon' : 'bonova' ) ); ?></span>
		</form>

		<table class="widefat papb-issued">
			<thead>
				<tr><th>Kod</th><th>Iznos</th><th>Ostatak</th><th>Za koga</th><th>Od koga</th><th>Kupac</th><th>Status</th><th>Vrijedi do</th><th>Radnje</th></tr>
			</thead>
			<tbody>
			<?php if ( ! $ids ) : ?>
				<tr><td colspan="9">Nema bonova.</td></tr>
			<?php endif; ?>
			<?php
			foreach ( $ids as $id ) :
				$v = Plan_A_Bon_Voucher::get( $id );
				if ( ! $v ) {
					continue;
				}
				$order   = $v['order'] ? wc_get_order( $v['order'] ) : false;
				$history = self::history_lines( $v, true );
				?>
				<tr class="papb-row">
					<td><code class="papb-code"><?php echo esc_html( $v['code'] ); ?></code>
						<br><small>izdan <?php echo esc_html( $v['issued'] ? wp_date( 'j.n.Y.', $v['issued'] ) : '' ); ?><?php echo $v['parent'] ? ' · ostatak bona' : ''; ?></small>
					</td>
					<td><?php echo esc_html( Plan_A_Bon_Voucher::money( $v['amount'] ) ); ?></td>
					<td><?php echo esc_html( self::rest_text( $v ) ); ?></td>
					<td><?php echo esc_html( $v['to'] ); ?></td>
					<td><?php echo esc_html( $v['from'] ); ?></td>
					<td><?php echo esc_html( '' !== $v['buyer'] ? $v['buyer'] : '–' ); ?>
						<?php if ( '' !== $v['email'] ) : ?>
							<br><small><?php echo esc_html( $v['email'] ); ?></small>
						<?php endif; ?>
						<?php if ( $order ) : ?>
							<br><small><a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">narudžba #<?php echo esc_html( $order->get_order_number() ); ?></a></small>
						<?php endif; ?>
						<?php if ( '' !== $v['reason'] ) : ?>
							<br><small><?php echo esc_html( ( $v['order'] ? '' : 'Ručno: ' ) . $v['reason'] ); ?></small>
						<?php endif; ?>
					</td>
					<td><span class="papb-status papb-status--<?php echo esc_attr( $v['status'] ); ?>"><?php echo esc_html( Plan_A_Bon_Voucher::status_label( $v['status'] ) ); ?></span></td>
					<td><?php echo esc_html( $v['expires'] ? wp_date( 'j.n.Y.', $v['expires'] ) : '' ); ?></td>
					<td>
						<div class="papb-actions">
							<a href="<?php echo esc_url( self::file_url( $id, 'png', true ) ); ?>" target="_blank" rel="noopener">Pregledaj bon</a>
							<a href="<?php echo esc_url( self::file_url( $id, 'pdf' ) ); ?>">Preuzmi PDF</a>
						</div>
						<details>
							<summary>Ponovno pošalji</summary>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="papb_resend">
								<input type="hidden" name="id" value="<?php echo esc_attr( (string) $id ); ?>">
								<?php wp_nonce_field( 'papb_resend_' . $id ); ?>
								<input type="email" name="email" required value="<?php echo esc_attr( $v['email'] ); ?>" placeholder="e-mail" aria-label="E-mail">
								<button type="submit" class="button button-small">Pošalji</button>
							</form>
						</details>
					</td>
				</tr>
				<tr class="papb-history-row">
					<td></td>
					<td colspan="8">
						<strong>Povijest korištenja:</strong>
						<?php if ( $history ) : ?>
							<ul class="papb-history">
								<?php foreach ( $history as $entry ) : ?>
									<li><?php echo wp_kses( $entry, array( 'a' => array( 'href' => array() ), 'strong' => array() ) ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<small>bon još nije korišten.</small>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $pages > 1 ) : ?>
			<div class="tablenav"><div class="tablenav-pages">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'current' => $paged,
							'total'   => $pages,
						)
					)
				);
				?>
			</div></div>
		<?php endif; ?>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Radnje                                                               */
	/* ------------------------------------------------------------------ */

	public static function download() {
		$id = absint( $_GET['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- provjera u check().
		self::check( 'papb_file_' . $id );
		$format = 'png' === ( $_GET['f'] ?? '' ) ? 'png' : 'pdf'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! Plan_A_Bon_Voucher::is_voucher( $id ) ) {
			wp_die( esc_html__( 'Bon ne postoji.', 'plan-a-poklon-bon' ), '', array( 'response' => 404 ) );
		}
		Plan_A_Bon_Voucher::stream( $id, $format, ! empty( $_GET['inline'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	public static function resend() {
		$id = absint( $_POST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- provjera u check().
		self::check( 'papb_resend_' . $id );
		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$v     = Plan_A_Bon_Voucher::get( $id );
		if ( ! $v || ! is_email( $email ) ) {
			self::flash( 'error', 'Bon nije poslan: upišite ispravnu e-mail adresu.' );
			self::back( self::url( 'izdani' ) );
		}
		$sent = Plan_A_Bon_Voucher::send( $id, $email, 'issued', array( 'buyer' => $v['buyer'] ? (string) strtok( $v['buyer'], ' ' ) : '' ) );
		$order = $v['order'] ? wc_get_order( $v['order'] ) : false;
		if ( $sent && $order ) {
			$order->add_order_note( sprintf( 'Poklon bon %1$s ponovno poslan na %2$s.', $v['code'], $email ) );
		}
		self::flash( $sent ? 'success' : 'error', $sent ? sprintf( 'Bon %1$s poslan na %2$s.', $v['code'], $email ) : 'Bon nije poslan: provjerite slanje e-pošte na stranici.' );
		self::back( self::url( 'izdani' ) );
	}

	public static function csv() {
		self::check( 'papb_csv' );
		$search = self::search();
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="poklon-bonovi-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- BOM za Excel.
		fputcsv( $out, array( 'Kod', 'Iznos', 'Ostatak', 'Za koga', 'Od koga', 'Kupac', 'E-mail', 'Izdan', 'Vrijedi do', 'Status', 'Narudžba', 'Napomena', 'Povijest korištenja' ), ';' );
		foreach ( self::ids( $search ) as $id ) {
			$v = Plan_A_Bon_Voucher::get( $id );
			if ( ! $v ) {
				continue;
			}
			$order = $v['order'] ? wc_get_order( $v['order'] ) : false;
			$row   = array(
				$v['code'],
				number_format( $v['amount'], 2, ',', '' ),
				self::rest_text( $v ),
				$v['to'],
				$v['from'],
				$v['buyer'],
				$v['email'],
				$v['issued'] ? wp_date( 'd.m.Y.', $v['issued'] ) : '',
				$v['expires'] ? wp_date( 'd.m.Y.', $v['expires'] ) : '',
				Plan_A_Bon_Voucher::status_label( $v['status'] ),
				$order ? $order->get_order_number() : '',
				$v['reason'],
				implode( ' | ', self::history_lines( $v ) ),
			);
			// Zaštita od formula u proračunskim tablicama.
			$row = array_map(
				static function ( $cell ) {
					$cell = (string) $cell;
					return '' !== $cell && in_array( $cell[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ? "'" . $cell : $cell;
				},
				$row
			);
			fputcsv( $out, $row, ';' );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Ručno izdavanje                                                      */
	/* ------------------------------------------------------------------ */

	private static function tab_manual() {
		$error = isset( $_GET['papb_err'] ) ? sanitize_text_field( wp_unslash( $_GET['papb_err'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<p>Izdavanje bona bez narudžbe (npr. nagradna igra ili zamjena). Bon je odmah aktivan.</p>
		<?php if ( '' !== $error ) : ?>
			<div class="notice notice-error inline"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="papb_issue">
			<?php wp_nonce_field( 'papb_issue' ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="papb-a">Iznos (€)</label></th><td><input type="number" id="papb-a" name="amount" min="1" max="10000" step="0.01" required class="small-text"></td></tr>
				<tr><th scope="row"><label for="papb-to">Za koga</label></th><td><input type="text" id="papb-to" name="to" maxlength="40" required class="regular-text"></td></tr>
				<tr><th scope="row"><label for="papb-from">Od koga</label></th><td><input type="text" id="papb-from" name="from" maxlength="40" required class="regular-text" value="Plan A"></td></tr>
				<tr><th scope="row"><label for="papb-msg">Poruka</label></th><td><textarea id="papb-msg" name="message" maxlength="160" rows="2" class="large-text"></textarea></td></tr>
				<tr><th scope="row"><label for="papb-reason">Razlog</label></th><td><input type="text" id="papb-reason" name="reason" maxlength="120" required class="regular-text" placeholder="npr. Nagradna igra na Instagramu, listopad 2026."></td></tr>
				<tr><th scope="row"><label for="papb-months">Vrijedi</label></th><td><input type="number" id="papb-months" name="months" min="1" max="60" class="small-text" value="<?php echo esc_attr( (string) Plan_A_Bon_Settings::get( 'months' ) ); ?>"> mjeseci</td></tr>
				<tr><th scope="row"><label for="papb-email">E-mail primatelja bona</label></th><td><input type="email" id="papb-email" name="email" class="regular-text">
					<p><label><input type="checkbox" name="send" value="1" checked> Pošalji bon e-mailom (PDF i slika u privitku)</label></p></td></tr>
			</table>
			<?php submit_button( 'Izdaj bon' ); ?>
		</form>
		<?php
	}

	public static function issue() {
		self::check( 'papb_issue' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- provjereno u check().
		$amount = round( (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['amount'] ?? '' ) ) ), 2 );
		$to     = sanitize_text_field( wp_unslash( $_POST['to'] ?? '' ) );
		$from   = sanitize_text_field( wp_unslash( $_POST['from'] ?? '' ) );
		$msg    = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
		$reason = sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) );
		$email  = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$months = min( 60, max( 1, absint( $_POST['months'] ?? 12 ) ) );
		$send   = ! empty( $_POST['send'] );
		// phpcs:enable

		$error = '';
		if ( $amount <= 0 || $amount > 10000 ) {
			$error = 'Upišite iznos veći od nule.';
		} elseif ( '' === $to || mb_strlen( $to ) > 40 || '' === $from || mb_strlen( $from ) > 40 ) {
			$error = 'Polja "Za koga" i "Od koga" su obavezna (najviše 40 znakova).';
		} elseif ( '' === $reason ) {
			$error = 'Upišite razlog izdavanja.';
		} elseif ( $send && ! is_email( $email ) ) {
			$error = 'Za slanje e-mailom upišite ispravnu e-mail adresu.';
		}
		if ( '' !== $error ) {
			wp_safe_redirect( self::url( 'rucno', array( 'papb_err' => rawurlencode( $error ) ) ) );
			exit;
		}

		$expires = new DateTime( 'now', wp_timezone() );
		$expires->modify( '+' . $months . ' months' );
		$expires->setTime( 23, 59, 59 );
		$user = wp_get_current_user();
		$id   = Plan_A_Bon_Voucher::create(
			array(
				'amount'  => $amount,
				'to'      => $to,
				'from'    => $from,
				'message' => mb_substr( trim( (string) preg_replace( '/\s+/u', ' ', $msg ) ), 0, 160 ),
				'email'   => $email,
				'reason'  => $reason . ' (izdao/la: ' . $user->display_name . ')',
				'expires' => $expires->getTimestamp(),
			)
		);
		$v    = Plan_A_Bon_Voucher::get( $id );
		$sent = $send ? Plan_A_Bon_Voucher::send( $id, $email, 'issued', array( 'buyer' => '' ) ) : false;
		self::flash( 'success', $sent ? sprintf( 'Bon %1$s izdan i poslan na %2$s.', $v['code'], $email ) : sprintf( 'Bon %s je izdan.', $v['code'] ) );
		wp_safe_redirect( self::url( 'izdani' ) );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Narudžba                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Okvir "Poklon bon" na stranici narudžbe (samo za narudžbe s poklon bonom).
	 */
	public static function meta_box( $screen_id, $post_or_order = null ) {
		if ( ! in_array( $screen_id, self::order_screens(), true ) || ! current_user_can( self::CAP ) ) {
			return;
		}
		$order = $post_or_order instanceof WC_Order ? $post_or_order : ( $post_or_order instanceof WP_Post ? wc_get_order( $post_or_order->ID ) : false );
		if ( ! $order || ! Plan_A_Bon::has_voucher_items( $order ) ) {
			return;
		}
		add_meta_box( 'papb-order', 'Poklon bon', array( __CLASS__, 'meta_box_html' ), $screen_id, 'side', 'high' );
	}

	public static function meta_box_html( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			return;
		}
		$ids = Plan_A_Bon::order_vouchers( $order );
		echo '<div class="papb-box">';
		if ( ! $ids ) {
			foreach ( self::order_bons( $order ) as $bon ) {
				echo '<p class="papb-box__item">' . esc_html( Plan_A_Bon_Voucher::money( $bon[0] ) . ' za ' . $bon[1] ) . '<br><span class="papb-status papb-status--ceka" style="display:inline-block;margin-top:4px;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600;">čeka uplatu</span></p>';
			}
			if ( $order->has_status( self::WAITING ) ) {
				echo '<p>' . self::paid_button( $order ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapano u paid_button().
				echo '<p><small>Narudžba prelazi u "U obradi", bon se izdaje i šalje kupcu na ' . esc_html( $order->get_billing_email() ) . '.</small></p>';
			} else {
				echo '<p><small>Bon se izdaje kad narudžba prijeđe u "U obradi" ili "Završeno".</small></p>';
			}
		}
		foreach ( $ids as $id ) {
			$v = Plan_A_Bon_Voucher::get( $id );
			if ( ! $v ) {
				continue;
			}
			echo '<div class="papb-box__item"><strong style="font-size:14px;">' . esc_html( $v['code'] ) . '</strong> <span class="papb-status papb-status--' . esc_attr( $v['status'] ) . '" style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600;">' . esc_html( Plan_A_Bon_Voucher::status_label( $v['status'] ) ) . '</span><br>'
				. esc_html( Plan_A_Bon_Voucher::money( $v['amount'] ) . ' za ' . $v['to'] . ' · vrijedi do ' . wp_date( 'j.n.Y.', $v['expires'] ) ) . '<br>'
				. '<a href="' . esc_url( self::file_url( $id, 'png', true ) ) . '" target="_blank" rel="noopener">Pregledaj bon</a> · <a href="' . esc_url( self::file_url( $id, 'pdf' ) ) . '">Preuzmi PDF</a> · '
				. '<a href="' . esc_url( self::url( 'izdani', array( 's' => $v['code'] ) ) ) . '">Izdani bonovi</a></div>';
		}
		echo '</div>';
	}

	/**
	 * Uz stavku bona u narudžbi: stanje i poveznice.
	 */
	public static function order_item( $item_id, $item ) {
		if ( ! $item instanceof WC_Order_Item_Product || ! current_user_can( self::CAP ) ) {
			return;
		}
		$ids = array_filter( array_map( 'intval', (array) $item->get_meta( '_papb_coupons' ) ) );
		if ( ! $ids ) {
			if ( '' !== (string) $item->get_meta( '_papb_amount' ) ) {
				echo '<p class="description">Poklon bon čeka uplatu (okvir "Poklon bon" desno).</p>';
			}
			return;
		}
		foreach ( $ids as $id ) {
			$v = Plan_A_Bon_Voucher::get( $id );
			if ( ! $v ) {
				continue;
			}
			echo '<p class="papb-admin"><strong>' . esc_html( $v['code'] ) . '</strong> (' . esc_html( Plan_A_Bon_Voucher::status_label( $v['status'] ) ) . ', vrijedi do ' . esc_html( wp_date( 'j.n.Y.', $v['expires'] ) ) . ') – '
				. '<a href="' . esc_url( self::file_url( $id, 'pdf' ) ) . '">Preuzmi PDF</a> · <a href="' . esc_url( self::file_url( $id, 'png', true ) ) . '" target="_blank" rel="noopener">Pregledaj bon</a></p>';
		}
	}
}
