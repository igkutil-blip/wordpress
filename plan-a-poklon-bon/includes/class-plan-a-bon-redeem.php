<?php
/**
 * Korištenje bona: poveznica /izleti/?bon=KOD (kod se pamti u sesiji i primjenjuje u
 * košarici), poruke na hrvatskom i ograničenje broja pokušaja unosa koda.
 *
 * Polje za kod već postoji u košarici ("Imaš kod za popust?") i na naplati (kupon
 * WooCommercea), pa dodatak ne dodaje novo polje.
 *
 * @package Plan_A_Poklon_Bon
 */

defined( 'ABSPATH' ) || exit;

class Plan_A_Bon_Redeem {

	const SESSION_CODE   = 'papb_pending';
	const SESSION_BANNER = 'papb_banner';

	/** Najviše neuspjelih pokušaja u razdoblju (po IP adresi). */
	const LIMIT  = 10;
	const WINDOW = 900; // 15 minuta

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'from_link' ), 5 );
		add_action( 'template_redirect', array( __CLASS__, 'on_cart_pages' ), 20 );
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'after_add' ), 30 );
		add_action( 'wp_footer', array( __CLASS__, 'banner' ) );
		add_filter( 'woocommerce_coupon_is_valid', array( __CLASS__, 'throttle' ), 5, 2 );
		add_filter( 'woocommerce_coupon_error', array( __CLASS__, 'messages' ), 20, 3 );
		add_filter( 'woocommerce_cart_totals_coupon_label', array( __CLASS__, 'label' ), 20, 2 );
		add_filter( 'woocommerce_coupon_message', array( __CLASS__, 'success' ), 20, 3 );

		// Bon se iskorištava kao WooCommerce kupon: bez uključenih kupona nema polja za kod u
		// košarici ni na naplati, a kod se ne može primijeniti. Zato su kuponi uvijek uključeni.
		add_filter( 'woocommerce_coupons_enabled', '__return_true', 99 );
		add_filter( 'gettext', array( __CLASS__, 'field_texts' ), 20, 3 );
	}

	/**
	 * Poruka nakon primjene bona: "Poklon bon PLANA-… je primijenjen."
	 */
	public static function success( $msg, $msg_code, $coupon ) {
		if ( WC_Coupon::WC_COUPON_SUCCESS === (int) $msg_code && $coupon instanceof WC_Coupon && Plan_A_Bon_Voucher::is_voucher( (int) $coupon->get_id() ) ) {
			/* translators: %s: kod bona */
			return sprintf( __( 'Poklon bon %s je primijenjen i iznos je umanjen.', 'plan-a-poklon-bon' ), strtoupper( $coupon->get_code() ) );
		}
		return $msg;
	}

	/**
	 * Jasniji natpisi polja za kod u košarici (dodatak za košaricu) i na naplati.
	 */
	public static function field_texts( $translation, $text, $domain ) {
		static $map = array(
			'plan-a-kosarica' => array(
				'Imaš kod za popust?' => 'Imaš poklon bon ili kod za popust?',
				'Upiši kod'           => 'Kod s bona',
				'Upiši kod i iznos za uplatu odmah će se umanjiti.' => 'Upiši kod s poklon bona i iznos za uplatu odmah će se umanjiti.',
			),
			'woocommerce'     => array(
				'Have a coupon?'                => 'Imaš poklon bon ili kod za popust?',
				'Click here to enter your code' => 'Upiši kod',
				'Coupon code'                   => 'Kod s bona',
				'Apply coupon'                  => 'Iskoristi',
				'If you have a coupon code, please apply it below.' => 'Upiši kod s poklon bona ili kod za popust.',
			),
		);
		if ( ! isset( $map[ $domain ][ $text ] ) || is_admin() || ! did_action( 'wp' ) || ! function_exists( 'is_cart' ) || ! ( is_cart() || is_checkout() ) ) {
			return $translation;
		}
		return $map[ $domain ][ $text ];
	}

	/**
	 * U sažetku košarice: "Poklon bon PLANA-XXXX-XXXX" umjesto "Kupon: plana-xxxx-xxxx".
	 */
	public static function label( $label, $coupon ) {
		if ( $coupon instanceof WC_Coupon && Plan_A_Bon_Voucher::is_voucher( (int) $coupon->get_id() ) ) {
			/* translators: %s: kod bona */
			return sprintf( __( 'Poklon bon %s', 'plan-a-poklon-bon' ), strtoupper( $coupon->get_code() ) );
		}
		return $label;
	}

	/* ------------------------------------------------------------------ */
	/* /izleti/?bon=KOD                                                     */
	/* ------------------------------------------------------------------ */

	public static function from_link() {
		if ( ! isset( $_GET['bon'] ) || is_admin() || ! function_exists( 'WC' ) || ! WC()->session ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- javna poveznica iz QR koda, samo čitanje koda.
			return;
		}
		$code = strtoupper( trim( sanitize_text_field( wp_unslash( $_GET['bon'] ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code = substr( preg_replace( '/[^A-Z0-9\-]/', '', $code ), 0, 40 );

		if ( ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}

		if ( self::locked() ) {
			$banner = array( 'error', __( 'Previše neuspjelih pokušaja unosa koda. Pokušaj ponovno za 15 minuta.', 'plan-a-poklon-bon' ) );
		} else {
			$id = '' !== $code ? Plan_A_Bon_Voucher::find( $code ) : 0;
			$v  = $id ? Plan_A_Bon_Voucher::get( $id ) : array();
			if ( ! $v ) {
				self::fail();
				/* translators: %s: kod */
				$banner = array( 'error', sprintf( __( 'Poklon bon s kodom %s ne postoji. Provjeri je li kod točno upisan.', 'plan-a-poklon-bon' ), $code ) );
			} elseif ( 'iskoristen' === $v['status'] ) {
				/* translators: %s: kod */
				$banner = array( 'error', sprintf( __( 'Poklon bon %s je već iskorišten.', 'plan-a-poklon-bon' ), $v['code'] ) );
			} elseif ( 'istekao' === $v['status'] ) {
				/* translators: 1: kod, 2: datum */
				$banner = array( 'error', sprintf( __( 'Poklon bon %1$s je istekao %2$s', 'plan-a-poklon-bon' ), $v['code'], Plan_A_Bon_Voucher::hr_date( $v['expires'] ) ) );
			} else {
				WC()->session->set( self::SESSION_CODE, $v['code'] );
				self::apply_pending();
				$banner = array( 'success', __( 'Poklon bon je spremljen i bit će primijenjen u košarici', 'plan-a-poklon-bon' ) );
			}
		}
		WC()->session->set( self::SESSION_BANNER, $banner );

		// Ukloni kod iz adrese (osvježavanje stranice ga ne obrađuje ponovno).
		wp_safe_redirect( remove_query_arg( 'bon' ), 303 );
		exit;
	}

	public static function on_cart_pages() {
		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) {
			self::apply_pending();
		}
	}

	public static function after_add() {
		self::apply_pending();
	}

	/**
	 * Primjenjuje zapamćeni kod kad je u košarici nešto za što se bon može iskoristiti.
	 */
	public static function apply_pending() {
		if ( ! WC()->session || ! WC()->cart ) {
			return;
		}
		$code = (string) WC()->session->get( self::SESSION_CODE, '' );
		if ( '' === $code || WC()->cart->is_empty() ) {
			return;
		}
		if ( WC()->cart->has_discount( wc_format_coupon_code( $code ) ) ) {
			WC()->session->set( self::SESSION_CODE, null );
			return;
		}
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( isset( $item['data'] ) && Plan_A_Bon::is_voucher_product( $item['data'] ) ) {
				return; // Bon se ne može iskoristiti za kupnju bona; čeka dok bon nije u košarici.
			}
		}
		WC()->session->set( self::SESSION_CODE, null );
		WC()->cart->apply_coupon( $code );
	}

	/**
	 * Obavijest na stranici na koju vodi poveznica (stranica izleta nema WooCommerce obavijesti).
	 */
	public static function banner() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}
		$banner = WC()->session->get( self::SESSION_BANNER );
		if ( ! is_array( $banner ) || 2 !== count( $banner ) ) {
			return;
		}
		WC()->session->set( self::SESSION_BANNER, null );
		$ok = 'success' === $banner[0];
		?>
		<div class="papb-toast <?php echo $ok ? 'is-ok' : 'is-error'; ?>" role="<?php echo $ok ? 'status' : 'alert'; ?>" style="position:fixed;left:16px;right:16px;bottom:16px;z-index:99999;max-width:560px;margin:0 auto;display:flex;align-items:flex-start;gap:12px;padding:14px 16px;border-radius:12px;background:<?php echo $ok ? '#12304b' : '#8a1c1c'; ?>;color:#fff;box-shadow:0 10px 30px rgba(0,0,0,.25);font-size:16px;line-height:1.4;">
			<span aria-hidden="true" style="flex:0 0 auto;font-size:20px;line-height:1.1;"><?php echo $ok ? '🎁' : '!'; ?></span>
			<span style="flex:1 1 auto;"><?php echo esc_html( $banner[1] ); ?></span>
			<button type="button" onclick="this.parentNode.remove()" aria-label="<?php esc_attr_e( 'Zatvori', 'plan-a-poklon-bon' ); ?>" style="flex:0 0 auto;min-width:32px;min-height:32px;margin:-4px -4px 0 0;padding:0;border:0;background:transparent;color:#fff;font-size:24px;line-height:1;cursor:pointer;">&times;</button>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Ograničenje pokušaja                                                 */
	/* ------------------------------------------------------------------ */

	private static function key(): string {
		$ip = (string) apply_filters( 'plan_a_bon_client_ip', isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
		return 'papb_rl_' . md5( $ip . wp_salt( 'nonce' ) );
	}

	public static function locked(): bool {
		return (int) get_transient( self::key() ) >= self::LIMIT;
	}

	public static function fail() {
		$key   = self::key();
		$count = (int) get_transient( $key ) + 1;
		set_transient( $key, $count, self::WINDOW );
	}

	/**
	 * Unosi koda: obrazac u košarici, AJAX na košarici i naplati.
	 */
	private static function applying(): bool {
		// phpcs:disable WordPress.Security.NonceVerification -- samo provjera vrste zahtjeva; WooCommerce provjerava nonce.
		return ( isset( $_GET['wc-ajax'] ) && 'apply_coupon' === $_GET['wc-ajax'] ) || isset( $_POST['apply_coupon'] );
		// phpcs:enable
	}

	/**
	 * Nakon previše neuspjelih pokušaja ni ispravan kod se ne prihvaća (ne može se pogađati).
	 */
	public static function throttle( $valid, $coupon ) {
		if ( $valid && self::applying() && self::locked() ) {
			throw new Exception( esc_html__( 'Previše neuspjelih pokušaja unosa koda. Pokušaj ponovno za 15 minuta.', 'plan-a-poklon-bon' ) );
		}
		return $valid;
	}

	/* ------------------------------------------------------------------ */
	/* Poruke                                                               */
	/* ------------------------------------------------------------------ */

	public static function messages( $err, $err_code, $coupon ) {
		$code = $coupon instanceof WC_Coupon ? strtoupper( $coupon->get_code() ) : '';
		$id   = $coupon instanceof WC_Coupon ? (int) $coupon->get_id() : 0;
		$bon  = $id && Plan_A_Bon_Voucher::is_voucher( $id );

		switch ( (int) $err_code ) {
			case WC_Coupon::E_WC_COUPON_NOT_EXIST:
				if ( self::applying() ) {
					self::fail();
				}
				/* translators: %s: kod */
				return sprintf( __( 'Kod %s ne postoji. Provjeri je li točno upisan.', 'plan-a-poklon-bon' ), $code );
			case WC_Coupon::E_WC_COUPON_EXPIRED:
				if ( $bon ) {
					$v = Plan_A_Bon_Voucher::get( $id );
					/* translators: 1: kod, 2: datum */
					return sprintf( __( 'Poklon bon %1$s je istekao %2$s', 'plan-a-poklon-bon' ), $code, Plan_A_Bon_Voucher::hr_date( $v['expires'] ) );
				}
				/* translators: %s: kod */
				return sprintf( __( 'Kod %s je istekao.', 'plan-a-poklon-bon' ), $code );
			case WC_Coupon::E_WC_COUPON_USAGE_LIMIT_REACHED:
			case WC_Coupon::E_WC_COUPON_USAGE_LIMIT_COUPON_STUCK:
			case WC_Coupon::E_WC_COUPON_USAGE_LIMIT_COUPON_STUCK_GUEST:
				if ( $bon ) {
					/* translators: %s: kod */
					return sprintf( __( 'Poklon bon %s je već iskorišten.', 'plan-a-poklon-bon' ), $code );
				}
				break;
			case WC_Coupon::E_WC_COUPON_EXCLUDED_PRODUCTS:
			case WC_Coupon::E_WC_COUPON_NOT_APPLICABLE:
				if ( $bon ) {
					return __( 'Poklon bon ne može se iskoristiti za kupnju novog poklon bona. Bon kupi u zasebnoj narudžbi, a ovaj kod iskoristi za izlet.', 'plan-a-poklon-bon' );
				}
				break;
			case WC_Coupon::E_WC_COUPON_ALREADY_APPLIED:
				if ( $bon ) {
					/* translators: %s: kod */
					return sprintf( __( 'Poklon bon %s je već primijenjen u košarici.', 'plan-a-poklon-bon' ), $code );
				}
				break;
		}
		return $err;
	}
}
