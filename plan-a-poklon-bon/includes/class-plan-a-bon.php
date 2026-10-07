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
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'item_data' ), 20, 2 );
		add_filter( 'woocommerce_coupon_is_valid_for_product', array( __CLASS__, 'coupon_product' ), 20, 2 );

		// Narudžba.
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_item' ), 20, 3 );
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
		if ( empty( $item['papb'] ) ) {
			return $html;
		}
		$photo = (int) Plan_A_Bon_Settings::get( 'photo' );
		return $photo ? wp_get_attachment_image( $photo, 'medium_large', false, array( 'alt' => '' ) ) : $html;
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
			$used = (float) $coupon_item->get_discount() + (float) $coupon_item->get_discount_tax();
			$rest = round( $v['amount'] - $used, 2 );
			$order->update_meta_data( $key, (string) $rest );
			$order->save();
			if ( $rest < 0.01 ) {
				continue;
			}
			if ( 'keep' !== Plan_A_Bon_Settings::get( 'remainder' ) ) {
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
				$order->add_order_note( 'Ostatak poklon bona nije izdan: ' . $e->getMessage() );
				continue;
			}
			update_post_meta( $coupon_id, '_papb_child', $new );
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
