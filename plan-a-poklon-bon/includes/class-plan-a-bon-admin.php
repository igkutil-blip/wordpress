<?php
/**
 * Administracija: izbornik "Poklon bonovi" (popis, pretraga, CSV, ponovno slanje, PDF,
 * ručno izdavanje) i poveznice na bon uz stavku narudžbe.
 *
 * @package Plan_A_Poklon_Bon
 */

defined( 'ABSPATH' ) || exit;

class Plan_A_Bon_Admin {

	const CAP       = 'manage_woocommerce';
	const PAGE      = 'plan-a-bon';
	const PAGE_NEW  = 'plan-a-bon-izdaj';
	const PER_PAGE  = 30;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_papb_file', array( __CLASS__, 'download' ) );
		add_action( 'admin_post_papb_resend', array( __CLASS__, 'resend' ) );
		add_action( 'admin_post_papb_issue', array( __CLASS__, 'issue' ) );
		add_action( 'admin_post_papb_csv', array( __CLASS__, 'csv' ) );
		add_action( 'woocommerce_after_order_itemmeta', array( __CLASS__, 'order_item' ), 10, 2 );
	}

	public static function menu() {
		add_menu_page( 'Poklon bonovi', 'Poklon bonovi', self::CAP, self::PAGE, array( __CLASS__, 'list_page' ), 'dashicons-tickets-alt', 56 );
		add_submenu_page( self::PAGE, 'Poklon bonovi', 'Svi bonovi', self::CAP, self::PAGE, array( __CLASS__, 'list_page' ) );
		add_submenu_page( self::PAGE, 'Izdaj poklon bon', 'Izdaj bon', self::CAP, self::PAGE_NEW, array( __CLASS__, 'new_page' ) );
		add_submenu_page( self::PAGE, 'Poklon bonovi – postavke', 'Postavke', self::CAP, Plan_A_Bon_Settings::PAGE, array( 'Plan_A_Bon_Settings', 'page' ) );
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'plan-a-bon' ) ) {
			return;
		}
		wp_enqueue_script( 'plan-a-poklon-bon-admin', PLAN_A_BON_URL . 'assets/js/admin.js', array( 'jquery' ), PLAN_A_BON_VERSION, true );
		wp_add_inline_style(
			'common',
			'.papb-admin .papb-status{display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600}'
			. '.papb-admin .papb-status--aktivan{background:#e6f4ea;color:#1e7e34}.papb-admin .papb-status--iskoristen{background:#eef1f4;color:#50575e}.papb-admin .papb-status--istekao{background:#fcf0f1;color:#b32d2e}'
			. '.papb-admin code.papb-code{font-size:13px;font-weight:600;white-space:nowrap}.papb-admin details summary{cursor:pointer;color:#2271b1}.papb-admin details form{margin-top:6px;display:flex;gap:6px;flex-wrap:wrap}'
			. '.papb-admin td small{color:#646970}.papb-admin .papb-search{display:flex;gap:6px;flex-wrap:wrap;margin:12px 0}'
		);
	}

	private static function check( string $nonce_action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Nemate ovlasti.', 'plan-a-poklon-bon' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $nonce_action );
	}

	private static function back( array $args ) {
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=' . self::PAGE ) ) );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Upit                                                                 */
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
		$by_code      = $base;
		$by_code['s'] = $search;
		$by_name      = $base;
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
	 * Ostatak bona kao tekst za popis.
	 */
	private static function rest_text( array $v ): string {
		if ( 'iskoristen' !== $v['status'] ) {
			return 'istekao' === $v['status'] ? '0' : Plan_A_Bon_Voucher::money( $v['amount'] );
		}
		if ( $v['child'] ) {
			$child = Plan_A_Bon_Voucher::get( $v['child'] );
			if ( $child ) {
				return Plan_A_Bon_Voucher::money( $child['amount'] ) . ' → ' . $child['code'];
			}
		}
		return Plan_A_Bon_Voucher::money( 0 );
	}

	private static function file_url( int $id, string $format ): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=papb_file&id=' . $id . '&f=' . $format ), 'papb_file_' . $id );
	}

	/* ------------------------------------------------------------------ */
	/* Popis                                                                */
	/* ------------------------------------------------------------------ */

	public static function list_page() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$search = self::search();
		$ids    = self::ids( $search );
		$total  = count( $ids );
		$paged  = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$pages  = max( 1, (int) ceil( $total / self::PER_PAGE ) );
		$paged  = min( $paged, $pages );
		$ids    = array_slice( $ids, ( $paged - 1 ) * self::PER_PAGE, self::PER_PAGE );
		$msg    = isset( $_GET['papb_msg'] ) ? sanitize_key( wp_unslash( $_GET['papb_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notes  = array(
			'sent'     => array( 'success', 'Bon je ponovno poslan.' ),
			'notsent'  => array( 'error', 'Bon nije poslan: provjerite e-mail adresu i slanje e-pošte na stranici.' ),
			'issued'   => array( 'success', 'Bon je izdan.' ),
			'issuedsent' => array( 'success', 'Bon je izdan i poslan e-mailom.' ),
		);
		?>
		<div class="wrap papb-admin">
			<h1 class="wp-heading-inline">Poklon bonovi</h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_NEW ) ); ?>" class="page-title-action">Izdaj bon</a>
			<hr class="wp-header-end">
			<?php if ( isset( $notes[ $msg ] ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notes[ $msg ][0] ); ?> is-dismissible"><p><?php echo esc_html( $notes[ $msg ][1] ); ?></p></div>
			<?php endif; ?>

			<form method="get" class="papb-search">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
				<label class="screen-reader-text" for="papb-s">Pretraga</label>
				<input type="search" id="papb-s" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Kod ili ime" class="regular-text">
				<button type="submit" class="button">Traži</button>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'papb_csv', 's' => rawurlencode( $search ) ), admin_url( 'admin-post.php' ) ), 'papb_csv' ) ); ?>">Izvoz u CSV</a>
				<span class="description" style="align-self:center;"><?php echo esc_html( $total . ' ' . ( 1 === $total % 10 && 11 !== $total % 100 ? 'bon' : 'bonova' ) ); ?></span>
			</form>

			<table class="widefat striped">
				<thead>
					<tr>
						<th>Kod</th><th>Iznos</th><th>Ostatak</th><th>Za koga</th><th>Od koga</th><th>Kupac</th><th>Izdan</th><th>Vrijedi do</th><th>Status</th><th>Narudžba</th><th>Radnje</th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $ids ) : ?>
					<tr><td colspan="11">Nema bonova.</td></tr>
				<?php endif; ?>
				<?php
				foreach ( $ids as $id ) :
					$v = Plan_A_Bon_Voucher::get( $id );
					if ( ! $v ) {
						continue;
					}
					$order = $v['order'] ? wc_get_order( $v['order'] ) : false;
					?>
					<tr>
						<td><code class="papb-code"><?php echo esc_html( $v['code'] ); ?></code>
							<?php if ( $v['parent'] ) : ?>
								<br><small>ostatak bona</small>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( Plan_A_Bon_Voucher::money( $v['amount'] ) ); ?></td>
						<td><?php echo esc_html( self::rest_text( $v ) ); ?></td>
						<td><?php echo esc_html( $v['to'] ); ?></td>
						<td><?php echo esc_html( $v['from'] ); ?></td>
						<td><?php echo esc_html( '' !== $v['buyer'] ? $v['buyer'] : '–' ); ?>
							<?php if ( '' !== $v['email'] ) : ?>
								<br><small><?php echo esc_html( $v['email'] ); ?></small>
							<?php endif; ?>
							<?php if ( '' !== $v['reason'] ) : ?>
								<br><small><?php echo esc_html( ( $v['order'] ? '' : 'Ručno: ' ) . $v['reason'] ); ?></small>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $v['issued'] ? wp_date( 'j.n.Y.', $v['issued'] ) : '' ); ?></td>
						<td><?php echo esc_html( $v['expires'] ? wp_date( 'j.n.Y.', $v['expires'] ) : '' ); ?></td>
						<td><span class="papb-status papb-status--<?php echo esc_attr( $v['status'] ); ?>"><?php echo esc_html( Plan_A_Bon_Voucher::status_label( $v['status'] ) ); ?></span></td>
						<td>
							<?php if ( $order ) : ?>
								<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></a>
							<?php else : ?>
								–
							<?php endif; ?>
						</td>
						<td>
							<a href="<?php echo esc_url( self::file_url( $id, 'pdf' ) ); ?>">Preuzmi PDF</a> ·
							<a href="<?php echo esc_url( self::file_url( $id, 'png' ) ); ?>">PNG</a>
							<details>
								<summary>Ponovno pošalji bon</summary>
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
		</div>
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
		Plan_A_Bon_Voucher::stream( $id, $format );
	}

	public static function resend() {
		$id = absint( $_POST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- provjera u check().
		self::check( 'papb_resend_' . $id );
		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$v     = Plan_A_Bon_Voucher::get( $id );
		if ( ! $v || ! is_email( $email ) ) {
			self::back( array( 'papb_msg' => 'notsent' ) );
		}
		$sent = Plan_A_Bon_Voucher::send( $id, $email, 'issued', array( 'buyer' => $v['buyer'] ? strtok( $v['buyer'], ' ' ) : '' ) );
		if ( $sent && $v['order'] && ( $order = wc_get_order( $v['order'] ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.Found
			$order->add_order_note( sprintf( 'Poklon bon %1$s ponovno poslan na %2$s.', $v['code'], $email ) );
		}
		self::back( array( 'papb_msg' => $sent ? 'sent' : 'notsent' ) );
	}

	public static function csv() {
		self::check( 'papb_csv' );
		$search = self::search();
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="poklon-bonovi-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- BOM za Excel.
		fputcsv( $out, array( 'Kod', 'Iznos', 'Ostatak', 'Za koga', 'Od koga', 'Kupac', 'E-mail', 'Izdan', 'Vrijedi do', 'Status', 'Narudžba', 'Napomena' ), ';' );
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

	public static function new_page() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$error = isset( $_GET['papb_err'] ) ? sanitize_text_field( wp_unslash( $_GET['papb_err'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap papb-admin">
			<h1>Izdaj poklon bon</h1>
			<p>Ručno izdavanje bez narudžbe (npr. nagradna igra ili zamjena). Bon je odmah aktivan.</p>
			<?php if ( '' !== $error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
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
		</div>
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
			wp_safe_redirect( add_query_arg( 'papb_err', rawurlencode( $error ), admin_url( 'admin.php?page=' . self::PAGE_NEW ) ) );
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
				'message' => mb_substr( trim( preg_replace( '/\s+/u', ' ', $msg ) ), 0, 160 ),
				'email'   => $email,
				'reason'  => $reason . ' (izdao/la: ' . $user->display_name . ')',
				'expires' => $expires->getTimestamp(),
			)
		);
		$sent = $send ? Plan_A_Bon_Voucher::send( $id, $email, 'issued', array( 'buyer' => '' ) ) : false;
		self::back( array( 'papb_msg' => $sent ? 'issuedsent' : 'issued' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Narudžba                                                             */
	/* ------------------------------------------------------------------ */

	public static function order_item( $item_id, $item ) {
		if ( ! $item instanceof WC_Order_Item_Product || ! current_user_can( self::CAP ) ) {
			return;
		}
		$ids = array_filter( array_map( 'intval', (array) $item->get_meta( '_papb_coupons' ) ) );
		if ( ! $ids ) {
			if ( '' !== (string) $item->get_meta( '_papb_amount' ) ) {
				echo '<p class="description">Poklon bon bit će izdan kad narudžba prijeđe u status "U obradi" ili "Završeno".</p>';
			}
			return;
		}
		foreach ( $ids as $id ) {
			$v = Plan_A_Bon_Voucher::get( $id );
			if ( ! $v ) {
				continue;
			}
			echo '<p class="papb-admin"><strong>' . esc_html( $v['code'] ) . '</strong> (' . esc_html( Plan_A_Bon_Voucher::status_label( $v['status'] ) ) . ', vrijedi do ' . esc_html( wp_date( 'j.n.Y.', $v['expires'] ) ) . ') – '
				. '<a href="' . esc_url( self::file_url( $id, 'pdf' ) ) . '">Preuzmi PDF</a> · <a href="' . esc_url( self::file_url( $id, 'png' ) ) . '">PNG</a> · '
				. '<a href="' . esc_url( add_query_arg( array( 'page' => self::PAGE, 's' => $v['code'] ), admin_url( 'admin.php' ) ) ) . '">Poklon bonovi</a></p>';
		}
	}
}
