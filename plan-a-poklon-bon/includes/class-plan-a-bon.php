<?php
/**
 * Proizvod "Poklon bon", stavka u košarici i narudžbi, izdavanje bona nakon uplate i ostatak bona.
 *
 * @package Plan_A_Poklon_Bon
 */

defined( 'ABSPATH' ) || exit;

class Plan_A_Bon {

	const PRODUCT_OPTION = 'plan_a_bon_product';
	const PAGE_OPTION    = 'plan_a_bon_page';

	/** Statusi u kojima je narudžba plaćena i bon se izdaje (nikad "Na čekanju"). */
	const PAID = array( 'processing', 'completed' );

	/** @var bool Bon se u košaricu dodaje samo preko stranice za kupnju. */
	private static $adding = false;

	/** @var array Podaci bona po objektu proizvoda u košarici (za sliku na naplati). */
	private static $objects = array();

	const THUMB_DIR = 'plan-a-poklon-bon-slike';

	public static function activate() {
		if ( class_exists( 'WooCommerce' ) ) {
			self::product_id( true );
			Plan_A_Bon_Voucher::dir();
		}
	}

	public static function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>Plan A poklon bon treba WooCommerce.</p></div>';
				}
			);
			return;
		}

		Plan_A_Bon_Redeem::init();
		Plan_A_Bon_Shortcode::init();
		Plan_A_Bon_Admin::init();

		add_action( 'admin_init', array( __CLASS__, 'ensure_product' ) );
		add_action( 'template_redirect', array( __CLASS__, 'product_page_redirect' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'sitemap' ), 10, 2 );

		// Košarica.
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'add_validation' ), 5, 2 );
		add_action( 'woocommerce_cart_loaded_from_session', array( __CLASS__, 'check_cart' ), 20 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'prices' ), 20 );
		add_filter( 'woocommerce_cart_item_name', array( __CLASS__, 'cart_item_name' ), 20, 2 );
		add_filter( 'woocommerce_cart_item_permalink', array( __CLASS__, 'cart_item_permalink' ), 20, 2 );
		add_filter( 'woocommerce_cart_item_thumbnail', array( __CLASS__, 'cart_item_thumbnail' ), 20, 2 );
		add_filter( 'woocommerce_product_get_image', array( __CLASS__, 'product_image' ), 20, 2 );
		add_filter( 'woocommerce_cart_item_class', array( __CLASS__, 'cart_item_class' ), 20, 2 );
		add_action( 'woocommerce_after_cart_item_name', array( __CLASS__, 'edit_link' ), 20, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'cart_assets' ) );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'item_data' ), 20, 2 );
		add_filter( 'woocommerce_coupon_is_valid_for_product', array( __CLASS__, 'coupon_product' ), 20, 2 );

		// Narudžba.
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_item' ), 20, 3 );
		add_action( 'woocommerce_checkout_order_created', array( __CLASS__, 'flag_order' ) );
		add_action( 'woocommerce_order_item_meta_start', array( __CLASS__, 'order_item_image' ), 20, 4 );
		add_filter( 'woocommerce_display_item_meta', array( __CLASS__, 'item_meta_lines' ), 20, 3 );
		add_action( 'woocommerce_order_status_on-hold', array( __CLASS__, 'notify_admin' ), 20 );
		foreach ( self::PAID as $status ) {
			add_action( 'woocommerce_order_status_' . $status, array( __CLASS__, 'order_paid' ), 5 );
		}
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'order_downloads' ), 20 );
		add_action( 'woocommerce_view_order', array( __CLASS__, 'order_downloads' ), 20 );
		add_action( 'template_redirect', array( __CLASS__, 'customer_download' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'order_assets' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Proizvod                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * ID skrivenog virtualnog proizvoda "Poklon bon"; po potrebi ga izrađuje.
	 */
	public static function product_id( bool $create = true ): int {
		$id      = (int) get_option( self::PRODUCT_OPTION, 0 );
		$product = $id ? wc_get_product( $id ) : null;
		if ( $product && 'trash' !== $product->get_status() ) {
			return $id;
		}
		if ( ! $create ) {
			return $id;
		}
		$product = new WC_Product_Simple();
		$product->set_name( 'Poklon bon' );
		$product->set_slug( 'poklon-bon-proizvod' );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		$product->set_sold_individually( true );
		$product->set_manage_stock( false );
		$product->set_stock_status( 'instock' );
		$product->set_tax_status( 'none' );
		$product->set_reviews_allowed( false );
		$product->set_regular_price( '0' );
		$product->set_short_description( 'Poklon bon za izlet. Kupuje se na stranici Poklon bon (dodatak Plan A poklon bon).' );
		$id = (int) $product->save();
		update_option( self::PRODUCT_OPTION, $id, false );
		return $id;
	}

	public static function ensure_product() {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			self::product_id( true );
		}
	}

	public static function is_voucher_product( $product ): bool {
		$id = $product instanceof WC_Product ? $product->get_id() : (int) $product;
		return $id > 0 && $id === self::product_id( false );
	}

	/**
	 * Stranica proizvoda se ne prikazuje: preusmjerenje na stranicu za kupnju bona.
	 */
	public static function product_page_redirect() {
		if ( is_singular( 'product' ) && self::is_voucher_product( get_queried_object_id() ) ) {
			wp_safe_redirect( self::shop_url(), 302 );
			exit;
		}
	}

	public static function shop_url(): string {
		$page = (int) get_option( self::PAGE_OPTION, 0 );
		return $page && 'publish' === get_post_status( $page ) ? (string) get_permalink( $page ) : home_url( '/' );
	}

	public static function sitemap( $args, $post_type ) {
		if ( 'product' === $post_type ) {
			$args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? array() ), array( self::product_id( false ) ) );
		}
		return $args;
	}

	/* ------------------------------------------------------------------ */
	/* Košarica                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Dodaje bon u košaricu (podaci su već provjereni na poslužitelju).
	 *
	 * @param array $data amount, to, from, message.
	 */
	public static function add_to_cart( array $data ) {
		self::$adding = true;
		try {
			$key = WC()->cart->add_to_cart(
				self::product_id( true ),
				1,
				0,
				array(),
				array(
					'papb' => array(
						'amount'  => round( (float) $data['amount'], 2 ),
						'to'      => (string) $data['to'],
						'from'    => (string) $data['from'],
						'message' => (string) $data['message'],
						'uid'     => wp_generate_password( 12, false ), // svaki bon je zasebna stavka
					),
				)
			);
		} catch ( Exception $e ) {
			$key = false;
		}
		self::$adding = false;
		return $key;
	}

	public static function add_validation( $passed, $product_id ) {
		if ( self::is_voucher_product( (int) $product_id ) && ! self::$adding ) {
			wc_add_notice( __( 'Poklon bon kupuje se na stranici Poklon bon.', 'plan-a-poklon-bon' ), 'error' );
			return false;
		}
		return $passed;
	}

	/**
	 * Iznos je uvijek iz podataka spremljenih na poslužitelju (sesija košarice); stavka bona
	 * bez valjanih podataka (npr. promijenjene granice u postavkama) uklanja se iz košarice.
	 */
	public static function check_cart( $cart ) {
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( ! isset( $item['data'] ) || ! self::is_voucher_product( $item['data'] ) ) {
				continue;
			}
			$amount = (float) ( $item['papb']['amount'] ?? 0 );
			if ( ! self::valid_amount( $amount ) || '' === (string) ( $item['papb']['to'] ?? '' ) ) {
				unset( $cart->cart_contents[ $key ] );
			}
		}
	}

	public static function valid_amount( float $amount ): bool {
		$amount = round( $amount, 2 );
		if ( in_array( $amount, Plan_A_Bon_Settings::amounts(), true ) ) {
			return true;
		}
		return $amount >= Plan_A_Bon_Settings::min() && $amount <= Plan_A_Bon_Settings::max();
	}

	public static function prices( $cart ) {
		if ( ! $cart || ( is_admin() && ! wp_doing_ajax() ) ) {
			return;
		}
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( ! empty( $item['papb']['amount'] ) && self::is_voucher_product( $item['data'] ) ) {
				$cart->cart_contents[ $key ]['data']->set_price( (float) $item['papb']['amount'] );
				self::$objects[ spl_object_id( $item['data'] ) ] = $item['papb'];
			}
		}
	}

	public static function item_title( float $amount, string $to ): string {
		/* translators: 1: iznos, 2: ime primatelja */
		return sprintf( __( 'Poklon bon %1$s za %2$s', 'plan-a-poklon-bon' ), Plan_A_Bon_Voucher::money( $amount ), $to );
	}

	public static function cart_item_name( $name, $item ) {
		if ( ! empty( $item['papb'] ) ) {
			return esc_html( self::item_title( (float) $item['papb']['amount'], (string) $item['papb']['to'] ) );
		}
		return $name;
	}

	public static function cart_item_permalink( $link, $item ) {
		return ! empty( $item['papb'] ) ? '' : $link;
	}

	public static function cart_item_thumbnail( $html, $item ) {
		return ! empty( $item['papb'] ) ? self::thumb_img( $item['papb'] ) : $html;
	}

	/**
	 * Slika proizvoda na naplati (dodatak za košaricu koristi sliku proizvoda): slika bona.
	 */
	public static function product_image( $html, $product ) {
		if ( $product instanceof WC_Product && isset( self::$objects[ spl_object_id( $product ) ] ) && self::is_voucher_product( $product ) ) {
			return self::thumb_img( self::$objects[ spl_object_id( $product ) ] );
		}
		return $html;
	}

	public static function cart_item_class( $class, $item ) {
		return ! empty( $item['papb'] ) ? $class . ' papb-cart-item' : $class;
	}

	/**
	 * Smanjena slika bona (iznos, za koga, od koga; bez koda). Javna datoteka s nepogodivim
	 * nazivom (HMAC), pa radi i u e-mailu.
	 */
	public static function thumb_url( array $d ): string {
		$amount = round( (float) ( $d['amount'] ?? 0 ), 2 );
		$to     = (string) ( $d['to'] ?? '' );
		$from   = (string) ( $d['from'] ?? '' );
		$key    = hash_hmac( 'sha256', implode( '|', array( $amount, $to, $from, Plan_A_Bon_Render::VERSION, (int) Plan_A_Bon_Settings::get( 'photo' ), Plan_A_Bon_Render::logo_id() ) ), wp_salt( 'auth' ) );
		$name   = substr( $key, 0, 40 ) . '.png';
		$up     = wp_upload_dir( null, false );
		$dir    = trailingslashit( $up['basedir'] ) . self::THUMB_DIR . '/';
		if ( ! file_exists( $dir . $name ) ) {
			if ( ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
				file_put_contents( $dir . 'index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
			if ( ! Plan_A_Bon_Render::thumb(
				array(
					'amount'  => $amount,
					'to'      => $to,
					'from'    => $from,
					'message' => '',
				),
				$dir . $name
			) ) {
				return '';
			}
		}
		return trailingslashit( $up['baseurl'] ) . self::THUMB_DIR . '/' . $name;
	}

	public static function thumb_img( array $d, string $style = '' ): string {
		$url = self::thumb_url( $d );
		if ( '' === $url ) {
			return '';
		}
		return '<img class="papb-thumb paka-item__img" src="' . esc_url( $url ) . '" width="600" height="300" alt="' . esc_attr( self::item_title( (float) $d['amount'], (string) $d['to'] ) ) . '"' . ( '' !== $style ? ' style="' . esc_attr( $style ) . '"' : '' ) . '>';
	}

	/**
	 * "Uredi bon" ispod stavke u košarici.
	 */
	public static function edit_link( $item, $key ) {
		if ( empty( $item['papb'] ) ) {
			return;
		}
		echo '<a class="papb-edit" href="' . esc_url( add_query_arg( 'papb_uredi', rawurlencode( (string) $key ), self::shop_url() ) ) . '">'
			. '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 20h4L19 9l-4-4L4 16v4zM14 6l4 4"/></svg>'
			. esc_html__( 'Uredi bon', 'plan-a-poklon-bon' ) . '</a>';
	}

	public static function cart_assets() {
		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) && WC()->cart && ! WC()->cart->is_empty() ) {
			foreach ( WC()->cart->get_cart() as $item ) {
				if ( ! empty( $item['papb'] ) ) {
					wp_enqueue_style( 'plan-a-poklon-bon', PLAN_A_BON_URL . 'assets/css/poklon-bon.css', array(), PLAN_A_BON_VERSION );
					return;
				}
			}
		}
	}

	public static function item_data( $data, $item ) {
		if ( empty( $item['papb'] ) ) {
			return $data;
		}
		$data[] = array(
			'key'   => __( 'Od', 'plan-a-poklon-bon' ),
			'value' => esc_html( (string) $item['papb']['from'] ),
		);
		if ( '' !== (string) $item['papb']['message'] ) {
			$data[] = array(
				'key'   => __( 'Poruka', 'plan-a-poklon-bon' ),
				'value' => esc_html( (string) $item['papb']['message'] ),
			);
		}
		return $data;
	}

	/**
	 * Nijedan kupon ne umanjuje cijenu bona (postotni i popusti na proizvod).
	 */
	public static function coupon_product( $valid, $product ) {
		return $product && self::is_voucher_product( $product ) ? false : $valid;
	}

	/* ------------------------------------------------------------------ */
	/* Narudžba                                                             */
	/* ------------------------------------------------------------------ */

	public static function order_item( $item, $cart_item_key, $values ) {
		if ( empty( $values['papb'] ) ) {
			return;
		}
		$v = $values['papb'];
		$item->set_name( self::item_title( (float) $v['amount'], (string) $v['to'] ) );
		$item->add_meta_data( '_papb_amount', round( (float) $v['amount'], 2 ), true );
		$item->add_meta_data( '_papb_to', (string) $v['to'], true );
		$item->add_meta_data( '_papb_from', (string) $v['from'], true );
		$item->add_meta_data( '_papb_message', (string) $v['message'], true );
		$item->add_meta_data( __( 'Od', 'plan-a-poklon-bon' ), (string) $v['from'], true );
		if ( '' !== (string) $v['message'] ) {
			$item->add_meta_data( __( 'Poruka', 'plan-a-poklon-bon' ), (string) $v['message'], true );
		}
	}

	/**
	 * Oznaka narudžbe s poklon bonom (popis "Čekaju uplatu" i broj u izborniku).
	 */
	public static function flag_order( $order ) {
		if ( $order instanceof WC_Order && self::has_voucher_items( $order ) ) {
			$order->update_meta_data( '_papb_has_voucher', 1 );
			$order->save();
		}
	}

	/**
	 * Slika bona uz stavku na završnoj stranici, u "Moj račun" i u e-mailovima.
	 */
	public static function order_item_image( $item_id, $item, $order, $plain_text = false ) {
		if ( $plain_text || ! $item instanceof WC_Order_Item_Product || '' === (string) $item->get_meta( '_papb_amount' ) ) {
			return;
		}
		$img = self::thumb_img(
			array(
				'amount' => (float) $item->get_meta( '_papb_amount' ),
				'to'     => (string) $item->get_meta( '_papb_to' ),
				'from'   => (string) $item->get_meta( '_papb_from' ),
			),
			'display:block;width:100%;max-width:320px;height:auto;margin:8px 0 6px;border:0;border-radius:8px;'
		);
		self::mute_tour_meta();
		// Iz baze: e-mail o uplati koristi kopiju stavke učitanu prije izdavanja bona.
		$issued = (bool) array_filter( (array) wc_get_order_item_meta( (int) $item_id, '_papb_coupons', true ) );
		echo '<div class="papb-item-image">' . $img // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- izgrađeno i escapano u thumb_img().
			. '<span style="display:block;color:#6b7785;font-size:13px;">' . esc_html( $issued ? __( 'Bon je poslan e-mailom kupcu.', 'plan-a-poklon-bon' ) : __( 'Bon šaljemo e-mailom čim stigne uplata.', 'plan-a-poklon-bon' ) ) . '</span></div>';
	}

	/**
	 * WpTravelly ispisuje uz svaku stavku prazan naslov "Order Details"; za stavku bona se
	 * njegova kuka privremeno uklanja i odmah nakon stavke vraća.
	 */
	private static function mute_tour_meta() {
		global $wp_filter;
		if ( empty( $wp_filter['woocommerce_order_item_meta_end'] ) ) {
			return;
		}
		$removed = array();
		foreach ( $wp_filter['woocommerce_order_item_meta_end']->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$fn = $callback['function'];
				if ( is_array( $fn ) && is_object( $fn[0] ) && 'display_order_meta' === $fn[1] && 0 === strpos( get_class( $fn[0] ), 'TTBM' ) ) {
					remove_action( 'woocommerce_order_item_meta_end', $fn, $priority );
					$removed[] = array( $fn, $priority, $callback['accepted_args'] );
				}
			}
		}
		if ( $removed ) {
			$restore = static function () use ( $removed, &$restore ) {
				remove_action( 'woocommerce_order_item_meta_end', $restore, PHP_INT_MAX );
				foreach ( $removed as $r ) {
					add_action( 'woocommerce_order_item_meta_end', $r[0], $r[1], $r[2] );
				}
			};
			add_action( 'woocommerce_order_item_meta_end', $restore, PHP_INT_MAX );
		}
	}

	/**
	 * Podaci stavke bona ("Od", "Poruka", "Kod bona") svaki u svom redu i kad ih drugi
	 * predložak ispisuje odvojene zarezom.
	 */
	public static function item_meta_lines( $html, $item, $args ) {
		if ( ! $item instanceof WC_Order_Item_Product || '' === (string) $item->get_meta( '_papb_amount' ) || ', ' !== ( $args['separator'] ?? '' ) ) {
			return $html;
		}
		$lines = array();
		foreach ( $item->get_formatted_meta_data() as $meta ) {
			$lines[] = $args['label_before'] . wp_kses_post( $meta->display_key ) . $args['label_after'] . wp_strip_all_tags( (string) $meta->display_value );
		}
		return $lines ? $args['before'] . implode( '<br>', $lines ) . $args['after'] : '';
	}

	/**
	 * Nova narudžba bona plaćena uplatnicom: e-mail administratoru "Novi poklon bon čeka uplatu".
	 */
	public static function notify_admin( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! self::has_voucher_items( $order ) || '' !== (string) $order->get_meta( '_papb_admin_notified' ) ) {
			return;
		}
		$order->update_meta_data( '_papb_admin_notified', time() );
		$order->update_meta_data( '_papb_has_voucher', 1 );
		$order->save();

		$bons = array();
		foreach ( $order->get_items() as $item ) {
			if ( '' !== (string) $item->get_meta( '_papb_amount' ) ) {
				$bons[] = self::item_title( (float) $item->get_meta( '_papb_amount' ), (string) $item->get_meta( '_papb_to' ) );
			}
		}
		$link  = admin_url( 'admin.php?page=' . Plan_A_Bon_Admin::PAGE . '&tab=cekaju' );
		$buyer = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$body  = '<p>' . esc_html( sprintf( 'Narudžba #%1$s (%2$s) s poklon bonom čeka uplatu.', $order->get_order_number(), $order->get_payment_method_title() ) ) . '</p>'
			. '<p><strong>Kupac:</strong> ' . esc_html( $buyer ) . ' (' . esc_html( $order->get_billing_email() ) . ')<br>'
			. '<strong>Iznos za uplatu:</strong> ' . esc_html( Plan_A_Bon_Voucher::money( (float) $order->get_total() ) ) . '<br>'
			. '<strong>Bon:</strong> ' . esc_html( implode( ', ', $bons ) ) . '</p>'
			. '<p>Kad uplata stigne, otvorite karticu "Čekaju uplatu" i kliknite "Uplata je stigla, pošalji bon":</p>'
			. '<p><a href="' . esc_url( $link ) . '" style="display:inline-block;padding:12px 20px;border-radius:8px;background:#1b2d4a;color:#ffffff;text-decoration:none;font-weight:bold;">Čekaju uplatu</a></p>';
		$mailer = WC()->mailer();
		$mailer->send( get_option( 'admin_email' ), 'Novi poklon bon čeka uplatu', $mailer->wrap_message( 'Novi poklon bon čeka uplatu', $body ), "Content-Type: text/html\r\n" );
	}

	/**
	 * "Uplata je stigla, pošalji bon": narudžba postaje plaćena (U obradi), a bon se izdaje i šalje
	 * kroz kuku statusa (order_paid). Vraća izdane ID-eve bonova.
	 *
	 * @return int[]
	 */
	public static function mark_paid( WC_Order $order ): array {
		if ( $order->has_status( array( 'on-hold', 'pending' ) ) ) {
			$user = wp_get_current_user();
			$order->add_order_note( sprintf( 'Uplata potvrđena u izborniku Poklon bonovi (%s).', $user->display_name ) );
			if ( ! $order->payment_complete() ) {
				$order->update_status( 'processing' );
			}
			$order = wc_get_order( $order->get_id() );
		}
		if ( $order->has_status( self::PAID ) ) {
			self::order_paid( $order->get_id() ); // ako je izdavanje propalo ranije, pokušaj ponovno
		}
		return self::order_vouchers( wc_get_order( $order->get_id() ) );
	}

	/**
	 * Narudžba je plaćena (Processing / Completed): izdaj bonove i ostatke iskorištenih bonova.
	 */
	public static function order_paid( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->has_status( self::PAID ) ) {
			return;
		}
		// Zaključavanje: dva istovremena okidanja (npr. povratni poziv banke i administrator) ne izdaju bon dvaput.
		$lock = 'plan_a_bon_lock_' . (int) $order_id;
		if ( ! add_option( $lock, time(), '', 'no' ) ) {
			if ( time() - (int) get_option( $lock ) < 300 ) {
				return;
			}
			update_option( $lock, time(), false );
		}
		try {
			self::issue( $order );
			self::remainders( $order );
		} finally {
			delete_option( $lock );
		}
	}

	private static function issue( WC_Order $order ) {
		$product_id = self::product_id( false );
		foreach ( $order->get_items() as $item_id => $item ) {
			if ( (int) $item->get_product_id() !== $product_id || '' === (string) $item->get_meta( '_papb_amount' ) ) {
				continue;
			}
			$issued = array_filter( array_map( 'intval', (array) $item->get_meta( '_papb_coupons' ) ) );
			$qty    = max( 1, (int) $item->get_quantity() );
			$codes  = array();
			while ( count( $issued ) < $qty ) {
				try {
					$coupon_id = Plan_A_Bon_Voucher::create(
						array(
							'amount'   => (float) $item->get_meta( '_papb_amount' ),
							'to'       => (string) $item->get_meta( '_papb_to' ),
							'from'     => (string) $item->get_meta( '_papb_from' ),
							'message'  => (string) $item->get_meta( '_papb_message' ),
							'email'    => (string) $order->get_billing_email(),
							'buyer'    => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
							'order_id' => $order->get_id(),
							'item_id'  => (int) $item_id,
						)
					);
				} catch ( Exception $e ) {
					$order->add_order_note( 'Poklon bon nije izdan: ' . $e->getMessage() );
					break;
				}
				$issued[] = $coupon_id;
				// Spremi odmah, prije slanja e-maila, da se bon ne izda ponovno.
				$item->update_meta_data( '_papb_coupons', $issued );
				$item->save();
				$v       = Plan_A_Bon_Voucher::get( $coupon_id );
				$codes[] = $v['code'];
				$sent    = Plan_A_Bon_Voucher::send( $coupon_id, '', 'issued', array( 'buyer' => $order->get_billing_first_name() ) );
				$order->add_order_note( sprintf( 'Izdan poklon bon %1$s (%2$s, vrijedi do %3$s)%4$s.', $v['code'], Plan_A_Bon_Voucher::money( $v['amount'] ), Plan_A_Bon_Voucher::hr_date( $v['expires'] ), $sent ? ' i poslan kupcu' : '; e-mail nije poslan' ) );
			}
			if ( $codes ) {
				$all = array();
				foreach ( $issued as $id ) {
					$v     = Plan_A_Bon_Voucher::get( $id );
					$all[] = $v ? $v['code'] : '';
				}
				$item->update_meta_data( __( 'Kod bona', 'plan-a-poklon-bon' ), implode( ', ', array_filter( $all ) ) );
				$item->save();
			}
		}
	}

	/**
	 * Djelomično iskorišten bon: ostatak postaje novi bon (ako je tako u postavkama).
	 */
	private static function remainders( WC_Order $order ) {
		foreach ( $order->get_items( 'coupon' ) as $coupon_item ) {
			$coupon_id = Plan_A_Bon_Voucher::find( (string) $coupon_item->get_code() );
			$key       = '_papb_rest_' . $coupon_id;
			if ( ! $coupon_id || '' !== (string) $order->get_meta( $key ) ) {
				continue;
			}
			$v    = Plan_A_Bon_Voucher::get( $coupon_id );
			$used = round( (float) $coupon_item->get_discount() + (float) $coupon_item->get_discount_tax(), 2 );
			$rest = round( $v['amount'] - $used, 2 );
			$order->update_meta_data( $key, (string) $rest );
			$order->save();
			$entry = array(
				'date'  => time(),
				'order' => $order->get_id(),
				'used'  => $used,
				'rest'  => max( 0, $rest ),
				'new'   => 0,
				'lost'  => false,
			);
			if ( $rest < 0.01 ) {
				Plan_A_Bon_Voucher::add_history( $coupon_id, $entry );
				continue;
			}
			if ( 'keep' !== Plan_A_Bon_Settings::get( 'remainder' ) ) {
				$entry['lost'] = true;
				Plan_A_Bon_Voucher::add_history( $coupon_id, $entry );
				$order->add_order_note( sprintf( 'Poklon bon %1$s djelomično iskorišten; ostatak od %2$s propada (postavka "Ostatak bona").', $v['code'], Plan_A_Bon_Voucher::money( $rest ) ) );
				continue;
			}
			try {
				$new = Plan_A_Bon_Voucher::create(
					array(
						'amount'   => $rest,
						'to'       => $v['to'],
						'from'     => $v['from'],
						'message'  => $v['message'],
						'email'    => (string) $order->get_billing_email(),
						'buyer'    => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
						'order_id' => $order->get_id(),
						'reason'   => 'Ostatak bona ' . $v['code'],
						'expires'  => $v['expires'],
						'parent'   => $coupon_id,
					)
				);
			} catch ( Exception $e ) {
				Plan_A_Bon_Voucher::add_history( $coupon_id, $entry );
				$order->add_order_note( 'Ostatak poklon bona nije izdan: ' . $e->getMessage() );
				continue;
			}
			update_post_meta( $coupon_id, '_papb_child', $new );
			$entry['new'] = $new;
			Plan_A_Bon_Voucher::add_history( $coupon_id, $entry );
			$nv   = Plan_A_Bon_Voucher::get( $new );
			$sent = Plan_A_Bon_Voucher::send(
				$new,
				'',
				'remainder',
				array(
					'buyer'        => $order->get_billing_first_name(),
					'old_code'     => $v['code'],
					'order_number' => $order->get_order_number(),
				)
			);
			$order->add_order_note( sprintf( 'Ostatak poklon bona %1$s (%2$s) prebačen na novi bon %3$s%4$s.', $v['code'], Plan_A_Bon_Voucher::money( $rest ), $nv['code'], $sent ? ' i poslan kupcu' : '; e-mail nije poslan' ) );
		}
	}

	/**
	 * ID-evi bonova izdanih za narudžbu.
	 *
	 * @return int[]
	 */
	public static function order_vouchers( WC_Order $order ): array {
		$ids = array();
		foreach ( $order->get_items() as $item ) {
			$ids = array_merge( $ids, array_filter( array_map( 'intval', (array) $item->get_meta( '_papb_coupons' ) ) ) );
		}
		return $ids;
	}

	public static function has_voucher_items( WC_Order $order ): bool {
		$product_id = self::product_id( false );
		foreach ( $order->get_items() as $item ) {
			if ( (int) $item->get_product_id() === $product_id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Završna stranica i "Moj račun": preuzimanje bona (PDF i slika).
	 */
	public static function order_downloads( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! self::has_voucher_items( $order ) ) {
			return;
		}
		$ids = self::order_vouchers( $order );
		echo '<section class="papb-order">';
		echo '<h2 class="papb-order__title">' . esc_html__( 'Poklon bon', 'plan-a-poklon-bon' ) . '</h2>';
		if ( ! $ids ) {
			echo '<p class="papb-order__note">' . esc_html__( 'Poklon bon izdajemo čim zaprimimo uplatu: tada ga šaljemo e-mailom (PDF za ispis i slika za slanje porukom), a moći ćete ga preuzeti i ovdje.', 'plan-a-poklon-bon' ) . '</p>';
		}
		foreach ( $ids as $id ) {
			$v = Plan_A_Bon_Voucher::get( $id );
			if ( ! $v ) {
				continue;
			}
			$link = static function ( $format ) use ( $order, $id ) {
				return add_query_arg(
					array(
						'papb_bon' => $id,
						'papb_key' => $order->get_order_key(),
						'papb_f'   => $format,
					),
					home_url( '/' )
				);
			};
			echo '<div class="papb-order__item">';
			echo '<p class="papb-order__name">' . esc_html( self::item_title( (float) $v['amount'], $v['to'] ) ) . '</p>';
			echo '<p class="papb-order__code">' . esc_html__( 'Kod:', 'plan-a-poklon-bon' ) . ' <strong>' . esc_html( $v['code'] ) . '</strong> · ' . esc_html__( 'vrijedi do', 'plan-a-poklon-bon' ) . ' ' . esc_html( Plan_A_Bon_Voucher::hr_date( $v['expires'] ) ) . '</p>';
			echo '<p class="papb-order__links"><a class="papb-btn papb-btn--primary" href="' . esc_url( $link( 'pdf' ) ) . '">' . esc_html__( 'Preuzmi PDF', 'plan-a-poklon-bon' ) . '</a> ';
			echo '<a class="papb-btn papb-btn--secondary" href="' . esc_url( $link( 'png' ) ) . '">' . esc_html__( 'Preuzmi sliku za WhatsApp', 'plan-a-poklon-bon' ) . '</a></p>';
			echo '</div>';
		}
		echo '</section>';
	}

	/**
	 * Preuzimanje za kupca: provjera ključa narudžbe (kao na završnoj stranici).
	 */
	public static function customer_download() {
		if ( empty( $_GET['papb_bon'] ) || empty( $_GET['papb_key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- zaštita ključem narudžbe.
			return;
		}
		$id     = absint( $_GET['papb_bon'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key    = sanitize_text_field( wp_unslash( $_GET['papb_key'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$format = 'png' === ( $_GET['papb_f'] ?? '' ) ? 'png' : 'pdf'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$v      = Plan_A_Bon_Voucher::get( $id );
		$order  = $v ? wc_get_order( $v['order'] ) : false;
		if ( ! $order || ! hash_equals( (string) $order->get_order_key(), $key ) || ! in_array( $id, self::order_vouchers( $order ), true ) ) {
			wp_die( esc_html__( 'Poveznica za preuzimanje bona nije ispravna.', 'plan-a-poklon-bon' ), '', array( 'response' => 403 ) );
		}
		Plan_A_Bon_Voucher::stream( $id, $format );
	}

	public static function order_assets() {
		if ( function_exists( 'is_wc_endpoint_url' ) && ( is_wc_endpoint_url( 'order-received' ) || is_wc_endpoint_url( 'view-order' ) ) ) {
			wp_enqueue_style( 'plan-a-poklon-bon', PLAN_A_BON_URL . 'assets/css/poklon-bon.css', array(), PLAN_A_BON_VERSION );
		}
	}
}
