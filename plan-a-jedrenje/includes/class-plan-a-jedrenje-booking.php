<?php
/**
 * Rezervacije: zahtjev → potvrda (narudžba s uplatnicom za akontaciju) → uplata akontacije
 * (tjedan zauzet) → uplatnica za ostatak → sve plaćeno.
 *
 * Rezervacija je zapis (post type paj_rezervacija) s meta poljima:
 *   _paj_week   subota ukrcaja 'Y-m-d'
 *   _paj_state  zahtjev | odbijeno | potvrdeno | rezervirano | placeno | otkazano
 *   _paj_token  tajni ključ za plaćanje preko košarice
 *   _paj_data   ostali podaci (vidi get())
 *
 * Narudžbe su obične WooCommerce narudžbe (virman, "Na čekanju"), pa uplatnicu s 2D kodom
 * izrađuje i šalje dodatak Hub3, a poklon bon se može iskoristiti preko košarice.
 *
 * @package Plan_A_Jedrenje
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Jedrenje_Booking {

	const POST_TYPE      = 'paj_rezervacija';
	const PRODUCT_OPTION = 'plan_a_jedrenje_product';
	const PAGE_OPTION    = 'plan_a_jedrenje_page';
	const CRON           = 'plan_a_jedrenje_daily';

	const PARTS = array(
		'akontacija' => 'Akontacija',
		'ostatak'    => 'Ostatak',
		'cijelo'     => 'Cijeli iznos',
	);

	const STATES = array(
		'zahtjev'     => 'Novi zahtjev',
		'potvrdeno'   => 'Čeka akontaciju',
		'rezervirano' => 'Akontacija plaćena',
		'placeno'     => 'Sve plaćeno',
		'odbijeno'    => 'Odbijeno',
		'otkazano'    => 'Otkazano',
	);

	/** @var bool Dodavanje u košaricu iz ovog dodatka. */
	private static $adding = false;

	/** @var array|null Rezervacija čiji se e-mail upravo izrađuje (tekstovi Plan A košarice). */
	private static $mailing = null;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
		add_action( self::CRON, array( __CLASS__, 'daily' ) );
		add_action( 'admin_init', array( __CLASS__, 'ensure_product' ) );
		add_action( 'template_redirect', array( __CLASS__, 'product_page_redirect' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'sitemap' ), 10, 2 );

		// Plaćanje preko košarice (npr. s poklon bonom).
		add_action( 'wp_loaded', array( __CLASS__, 'pay_link' ), 30 );
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'add_validation' ), 5, 2 );
		add_action( 'woocommerce_cart_loaded_from_session', array( __CLASS__, 'check_cart' ), 20 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'cart_prices' ), 20 );
		add_filter( 'woocommerce_cart_item_name', array( __CLASS__, 'cart_item_name' ), 20, 2 );
		add_filter( 'woocommerce_cart_item_permalink', array( __CLASS__, 'cart_item_permalink' ), 20, 2 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'cart_item_data' ), 20, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'checkout_line_item' ), 20, 3 );
		add_action( 'woocommerce_checkout_order_created', array( __CLASS__, 'checkout_order_created' ), 20 );

		// Narudžbe i e-mailovi.
		foreach ( array( 'processing', 'completed' ) as $status ) {
			add_action( 'woocommerce_order_status_' . $status, array( __CLASS__, 'order_paid' ), 20 );
		}
		add_filter( 'woocommerce_email_enabled_customer_processing_order', array( __CLASS__, 'mute_wc_email' ), 20, 2 );
		add_filter( 'woocommerce_email_enabled_customer_completed_order', array( __CLASS__, 'mute_wc_email' ), 20, 2 );
		add_filter( 'woocommerce_email_subject_customer_on_hold_order', array( __CLASS__, 'wc_email_subject' ), 99, 2 );
		add_filter( 'woocommerce_email_heading_customer_on_hold_order', array( __CLASS__, 'wc_email_heading' ), 99, 2 );
		add_action( 'woocommerce_email_before_order_table', array( __CLASS__, 'wc_email_block' ), 5, 4 );
		add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'wc_email_organizer' ), 30, 4 );
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'thankyou_note' ), 5 );
		// E-mail Plan A košarice pisan je za izlete; za uplatnice jedrenja tekstovi se prilagođavaju
		// samo dok se izrađuje taj e-mail (košarica se ne mijenja).
		add_filter( 'option_plan_a_kosarica', array( __CLASS__, 'kosarica_texts' ), 99 );
		add_filter( 'gettext_plan-a-kosarica', array( __CLASS__, 'kosarica_gettext' ), 99, 3 );
		add_filter( 'wp_mail', array( __CLASS__, 'mail_done' ), 1 );
	}

	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array( 'name' => 'Rezervacije jedrenja' ),
				'public'       => false,
				'show_ui'      => false,
				'rewrite'      => false,
				'query_var'    => false,
				'supports'     => array( 'title' ),
				'can_export'   => true,
				'map_meta_cap' => true,
			)
		);
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	/* ---------------------------------------------------------------------
	 * Proizvod
	 * ------------------------------------------------------------------- */

	public static function product_id( bool $create = true ): int {
		$id      = (int) get_option( self::PRODUCT_OPTION, 0 );
		$product = $id && function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
		if ( $product && 'trash' !== $product->get_status() ) {
			return $id;
		}
		if ( ! $create || ! class_exists( 'WC_Product_Simple' ) ) {
			return $id;
		}
		$product = new WC_Product_Simple();
		$product->set_name( 'Tjedan jedrenja' );
		$product->set_slug( 'tjedan-jedrenja-uplata' );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		$product->set_sold_individually( true );
		$product->set_manage_stock( false );
		$product->set_stock_status( 'instock' );
		$product->set_tax_status( 'none' );
		$product->set_reviews_allowed( false );
		$product->set_regular_price( '0' );
		$product->set_short_description( 'Uplata za tjedan jedrenja (dodatak Plan A jedrenje). Ne kupuje se izravno.' );
		$id = (int) $product->save();
		update_option( self::PRODUCT_OPTION, $id, false );
		return $id;
	}

	public static function ensure_product() {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			self::product_id( true );
		}
	}

	public static function is_product( $product ): bool {
		$id = $product instanceof WC_Product ? $product->get_id() : (int) $product;
		return $id > 0 && $id === self::product_id( false );
	}

	public static function page_url(): string {
		$page = (int) get_option( self::PAGE_OPTION, 0 );
		return $page && 'publish' === get_post_status( $page ) ? (string) get_permalink( $page ) : home_url( '/' );
	}

	public static function product_page_redirect() {
		if ( function_exists( 'is_singular' ) && is_singular( 'product' ) && self::is_product( get_queried_object_id() ) ) {
			wp_safe_redirect( self::page_url(), 302 );
			exit;
		}
	}

	public static function sitemap( $args, $post_type ) {
		if ( 'product' === $post_type ) {
			$args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? array() ), array( self::product_id( false ) ) );
		}
		return $args;
	}

	/* ---------------------------------------------------------------------
	 * Rezervacije
	 * ------------------------------------------------------------------- */

	public static function get( int $id ): ?array {
		if ( ! $id || self::POST_TYPE !== get_post_type( $id ) ) {
			return null;
		}
		$data = get_post_meta( $id, '_paj_data', true );
		$data = is_array( $data ) ? $data : array();
		return array_merge(
			array(
				'persons'     => 0,
				'route'       => '',
				'concept'     => '',
				'note'        => '',
				'first'       => '',
				'last'        => '',
				'email'       => '',
				'phone'       => '',
				'address'     => '',
				'postcode'    => '',
				'city'        => '',
				'price'       => 0.0,
				'regular'     => 0.0,
				'pct'         => 0,
				'deposit'     => 0.0,
				'rest'        => 0.0,
				'full'        => false,
				'rest_due'    => '',
				'deposit_due' => '',
				'orders'      => array(),
				'paid'        => array(),
				'reminded'    => array(),
				'log'         => array(),
				'created'     => 0,
			),
			$data,
			array(
				'id'    => $id,
				'week'  => (string) get_post_meta( $id, '_paj_week', true ),
				'state' => (string) get_post_meta( $id, '_paj_state', true ),
				'token' => (string) get_post_meta( $id, '_paj_token', true ),
			)
		);
	}

	private static function save( array $r ) {
		$id = (int) $r['id'];
		update_post_meta( $id, '_paj_week', $r['week'] );
		update_post_meta( $id, '_paj_state', $r['state'] );
		$data = $r;
		unset( $data['id'], $data['week'], $data['state'], $data['token'] );
		update_post_meta( $id, '_paj_data', $data );
		Plan_A_Jedrenje_Data::flush_states();
	}

	private static function log( array &$r, string $text ) {
		$r['log'][] = array( time(), $text );
	}

	public static function name( array $r ): string {
		return trim( $r['first'] . ' ' . $r['last'] );
	}

	public static function by_state( array $states, int $limit = -1 ): array {
		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => $limit,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'     => '_paj_state',
						'value'   => $states,
						'compare' => 'IN',
					),
				),
			)
		);
		return array_filter( array_map( array( __CLASS__, 'get' ), array_map( 'intval', $ids ) ) );
	}

	public static function for_week( string $week, array $states ): array {
		return array_values(
			array_filter(
				self::by_state( $states ),
				static function ( $r ) use ( $week ) {
					return $r['week'] === $week;
				}
			)
		);
	}

	public static function count_new(): int {
		return count( self::by_state( array( 'zahtjev' ) ) );
	}

	/**
	 * Zaključavanje tjedna dok se mijenja stanje (zaštita od dvostruke rezervacije).
	 * add_option je atomaran: drugi zahtjev za isti tjedan čeka ili odustaje.
	 */
	private static function lock( string $week ): bool {
		$key = 'paj_lock_' . $week;
		for ( $i = 0; $i < 20; $i++ ) {
			if ( add_option( $key, time(), '', 'no' ) ) {
				return true;
			}
			$since = (int) get_option( $key );
			if ( $since && $since < time() - 30 ) {
				delete_option( $key ); // zaostala brava
				continue;
			}
			usleep( 150000 );
		}
		return false;
	}

	private static function unlock( string $week ) {
		delete_option( 'paj_lock_' . $week );
	}

	/**
	 * Novi zahtjev (podaci su već provjereni u Plan_A_Jedrenje_Front::request()).
	 *
	 * @return int|WP_Error
	 */
	public static function create_request( array $in ) {
		$week = $in['week'];
		if ( ! self::lock( $week ) ) {
			return new WP_Error( 'busy', 'Sustav je trenutno zauzet. Pokušaj ponovno za nekoliko sekundi.' );
		}
		Plan_A_Jedrenje_Data::flush_states();
		$state = Plan_A_Jedrenje_Data::state( $week );
		if ( 'booked' === $state || 'past' === $state ) {
			self::unlock( $week );
			return new WP_Error( 'taken', 'Ovaj tjedan više nije slobodan. Odaberi drugi tjedan.' );
		}
		foreach ( self::for_week( $week, array( 'zahtjev', 'potvrdeno' ) ) as $other ) {
			if ( strtolower( $other['email'] ) === strtolower( $in['email'] ) ) {
				self::unlock( $week );
				return new WP_Error( 'dupe', 'Za ovaj tjedan već imamo tvoj zahtjev. Javit ćemo ti se uskoro.' );
			}
		}

		$price = Plan_A_Jedrenje_Data::price( $week );
		$plan  = Plan_A_Jedrenje_Data::payment_plan( $week, $price['price'] );
		$id    = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => trim( $in['first'] . ' ' . $in['last'] ) . ' – ' . Plan_A_Jedrenje_Data::numeric( $week ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			self::unlock( $week );
			return new WP_Error( 'save', 'Zahtjev nije spremljen. Pokušaj ponovno.' );
		}
		update_post_meta( $id, '_paj_token', wp_generate_password( 32, false ) );
		$r = array_merge(
			self::get( $id ),
			array(
				'week'     => $week,
				'state'    => 'zahtjev',
				'persons'  => (int) $in['persons'],
				'route'    => $in['route'],
				'concept'  => $in['concept'] ?? '',
				'note'     => $in['note'],
				'first'    => $in['first'],
				'last'     => $in['last'],
				'email'    => $in['email'],
				'phone'    => $in['phone'],
				'address'  => $in['address'],
				'postcode' => $in['postcode'],
				'city'     => $in['city'],
				'price'    => $price['price'],
				'regular'  => $price['regular'],
				'pct'      => $plan['pct'],
				'deposit'  => $plan['deposit'],
				'rest'     => $plan['rest'],
				'full'     => $plan['full'],
				'rest_due' => $plan['rest_due'],
				'created'  => time(),
			)
		);
		self::log( $r, 'Zahtjev zaprimljen s web stranice.' );
		self::save( $r );
		self::unlock( $week );

		// Kupcu.
		Plan_A_Jedrenje_Mail::send(
			$r['email'],
			'Zahtjev je zaprimljen – ' . Plan_A_Jedrenje_Data::value( 'title' ) . ', ' . Plan_A_Jedrenje_Data::week_label( $week ),
			Plan_A_Jedrenje_Mail::wrap(
				'Zahtjev je zaprimljen',
				array( 'Bok ' . $r['first'] . '!', 'Zahtjev je zaprimljen, potvrdit ćemo slobodan brod u roku 48 sati.', (string) Plan_A_Jedrenje_Data::value( 'mail_request' ) ),
				Plan_A_Jedrenje_Mail::details( $r ) . Plan_A_Jedrenje_Mail::terms_html()
			)
		);
		// Administratoru.
		Plan_A_Jedrenje_Mail::send(
			Plan_A_Jedrenje_Data::admin_email(),
			'Novi zahtjev za jedrenje: ' . Plan_A_Jedrenje_Data::week_label( $week ) . ' (' . self::name( $r ) . ')',
			Plan_A_Jedrenje_Mail::wrap(
				'Novi zahtjev za jedrenje',
				array( self::name( $r ) . ', ' . $r['email'] . ', ' . $r['phone'], 'Provjeri brod u charter bazi pa u administraciji klikni "Brod je slobodan, pošalji uplatnicu" ili "Odbij".' ),
				Plan_A_Jedrenje_Mail::details( $r ),
				array( 'Otvori zahtjeve', admin_url( 'admin.php?page=plan-a-jedrenje&tab=zahtjevi' ) )
			)
		);
		return $id;
	}

	/**
	 * "Brod je slobodan, pošalji uplatnicu": narudžba za akontaciju (ili cijeli iznos).
	 *
	 * @return true|WP_Error
	 */
	public static function confirm( int $id ) {
		$r = self::get( $id );
		if ( ! $r || 'zahtjev' !== $r['state'] ) {
			return new WP_Error( 'state', 'Zahtjev nije pronađen ili je već obrađen.' );
		}
		if ( ! function_exists( 'wc_create_order' ) ) {
			return new WP_Error( 'wc', 'WooCommerce nije aktivan.' );
		}
		if ( ! self::lock( $r['week'] ) ) {
			return new WP_Error( 'busy', 'Tjedan se upravo mijenja. Pokušaj ponovno.' );
		}
		Plan_A_Jedrenje_Data::flush_states();
		$over = Plan_A_Jedrenje_Data::week_overrides()[ $r['week'] ] ?? array();
		if ( ! empty( $over['closed'] ) ) {
			self::unlock( $r['week'] );
			return new WP_Error( 'closed', 'Tjedan je ručno zatvoren u kalendaru.' );
		}
		$others = array_filter(
			self::for_week( $r['week'], array( 'potvrdeno', 'rezervirano', 'placeno' ) ),
			static function ( $o ) use ( $id ) {
				return $o['id'] !== $id;
			}
		);
		if ( $others ) {
			$o = reset( $others );
			self::unlock( $r['week'] );
			return new WP_Error( 'double', sprintf( 'Za ovaj tjedan već je poslana uplatnica ili je rezerviran (%s, %s). Najprije otkaži tu rezervaciju.', self::name( $o ), self::STATES[ $o['state'] ] ) );
		}

		// Cijena ostaje ona iz zahtjeva; akontacija i rokovi računaju se na dan potvrde.
		$plan             = Plan_A_Jedrenje_Data::payment_plan( $r['week'], (float) $r['price'] );
		$r['pct']         = $plan['pct'];
		$r['deposit']     = $plan['deposit'];
		$r['rest']        = $plan['rest'];
		$r['full']        = $plan['full'];
		$r['rest_due']    = $plan['rest_due'];
		$r['deposit_due'] = Plan_A_Jedrenje_Data::add_days( Plan_A_Jedrenje_Data::today(), max( 1, (int) Plan_A_Jedrenje_Data::value( 'deposit_days' ) ) );
		$r['state']       = 'potvrdeno';
		self::save( $r );
		self::unlock( $r['week'] );

		$part     = $r['full'] ? 'cijelo' : 'akontacija';
		$order_id = self::create_order( $r, $part );
		$r        = self::get( $id );
		if ( is_wp_error( $order_id ) ) {
			$r['state'] = 'zahtjev';
			self::save( $r );
			return $order_id;
		}
		self::log( $r, 'Brod potvrđen; poslana uplatnica (' . self::part_label( $r, $part ) . ', narudžba #' . $order_id . ').' );
		self::save( $r );
		return true;
	}

	public static function part_label( array $r, string $part ): string {
		if ( 'akontacija' === $part ) {
			return 'Akontacija ' . (int) $r['pct'] . ' %';
		}
		return self::PARTS[ $part ] ?? $part;
	}

	public static function part_amount( array $r, string $part ): float {
		if ( 'akontacija' === $part ) {
			return (float) $r['deposit'];
		}
		if ( 'ostatak' === $part ) {
			return (float) $r['rest'];
		}
		return (float) $r['price'];
	}

	/**
	 * WooCommerce narudžba (virman, "Na čekanju") za dio uplate. Status "Na čekanju"
	 * pokreće e-mail s uplatnicom i 2D kodom (dodatak Hub3).
	 *
	 * @return int|WP_Error
	 */
	public static function create_order( array $r, string $part ) {
		$amount = round( self::part_amount( $r, $part ), 2 );
		if ( $amount <= 0 ) {
			return new WP_Error( 'amount', 'Iznos za uplatu nije ispravan.' );
		}
		try {
			$order = wc_create_order( array( 'created_via' => 'plan-a-jedrenje' ) );
			if ( is_wp_error( $order ) ) {
				return $order;
			}
			$product = wc_get_product( self::product_id( true ) );
			$item    = new WC_Order_Item_Product();
			if ( $product ) {
				$item->set_product( $product );
			}
			$item->set_name( self::item_title( $r, $part ) );
			$item->set_quantity( 1 );
			$item->set_subtotal( $amount );
			$item->set_total( $amount );
			self::item_meta( $item, $r, $part );
			$order->add_item( $item );

			$order->set_billing_first_name( $r['first'] );
			$order->set_billing_last_name( $r['last'] );
			$order->set_billing_email( $r['email'] );
			$order->set_billing_phone( $r['phone'] );
			$order->set_billing_address_1( $r['address'] );
			$order->set_billing_postcode( $r['postcode'] );
			$order->set_billing_city( $r['city'] );
			$order->set_billing_country( 'HR' );
			$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
			$order->set_payment_method( 'bacs' );
			$order->set_payment_method_title( isset( $gateways['bacs'] ) ? $gateways['bacs']->get_title() : 'Uplata na račun' );
			$order->update_meta_data( '_paj_rez', (int) $r['id'] );
			$order->update_meta_data( '_paj_part', $part );
			$order->calculate_totals( false );
			$order->save();
			$order->update_status( 'on-hold', 'Plan A jedrenje: ' . self::part_label( $r, $part ) . ' – čeka uplatu.' );
		} catch ( Exception $e ) {
			return new WP_Error( 'order', 'Narudžba nije izrađena: ' . $e->getMessage() );
		}

		$fresh                   = self::get( (int) $r['id'] );
		$fresh['orders'][ $part ] = $order->get_id();
		self::save( $fresh );
		return $order->get_id();
	}

	public static function item_title( array $r, string $part ): string {
		return self::part_label( $r, $part ) . ': ' . Plan_A_Jedrenje_Data::value( 'title' ) . ', ' . Plan_A_Jedrenje_Data::week_label( $r['week'] ) . ' ' . substr( $r['week'], 0, 4 ) . '.';
	}

	private static function item_meta( $item, array $r, string $part ) {
		$item->add_meta_data( '_paj_rez', (int) $r['id'], true );
		$item->add_meta_data( '_paj_part', $part, true );
		$item->add_meta_data( 'Termin', Plan_A_Jedrenje_Data::week_long( $r['week'] ), true );
		$item->add_meta_data( 'Brod', Plan_A_Jedrenje_Data::value( 'boat' ) . ', ' . $r['persons'] . ' osoba', true );
		$item->add_meta_data( 'Cijena', Plan_A_Jedrenje_Data::money( (float) $r['price'] ) . ' za cijeli brod', true );
	}

	/**
	 * @return true|WP_Error
	 */
	public static function reject( int $id, string $message ) {
		$r = self::get( $id );
		if ( ! $r || 'zahtjev' !== $r['state'] ) {
			return new WP_Error( 'state', 'Zahtjev nije pronađen ili je već obrađen.' );
		}
		$r['state'] = 'odbijeno';
		self::log( $r, 'Odbijeno. Poruka kupcu: ' . $message );
		self::save( $r );
		Plan_A_Jedrenje_Mail::send(
			$r['email'],
			'Odgovor na zahtjev za jedrenje – ' . Plan_A_Jedrenje_Data::week_label( $r['week'] ),
			Plan_A_Jedrenje_Mail::wrap(
				'Brod u ovom tjednu nije slobodan',
				array( 'Bok ' . $r['first'] . '!', $message ),
				Plan_A_Jedrenje_Mail::details( $r, false ),
				array( 'Odaberi drugi tjedan', self::page_url() )
			)
		);
		return true;
	}

	/**
	 * Administrator označava uplatu: narudžba prelazi u "U obradi", ostalo radi order_paid().
	 *
	 * @return true|WP_Error
	 */
	public static function mark_paid( int $id, string $part ) {
		$r        = self::get( $id );
		$order_id = (int) ( $r['orders'][ $part ] ?? 0 );
		$order    = $order_id ? wc_get_order( $order_id ) : null;
		if ( ! $r || ! $order ) {
			return new WP_Error( 'order', 'Narudžba za ovu uplatu nije pronađena.' );
		}
		if ( $order->is_paid() ) {
			self::order_paid( $order_id );
			return true;
		}
		$order->update_status( 'processing', 'Plan A jedrenje: uplata je stigla (označio administrator).' );
		return true;
	}

	/**
	 * @return true|WP_Error
	 */
	public static function send_rest( int $id ) {
		$r = self::get( $id );
		if ( ! $r || 'rezervirano' !== $r['state'] || $r['rest'] <= 0 ) {
			return new WP_Error( 'state', 'Uplatnica za ostatak može se poslati kad je akontacija plaćena.' );
		}
		$old = (int) ( $r['orders']['ostatak'] ?? 0 );
		$old = $old ? wc_get_order( $old ) : null;
		if ( $old && ! $old->has_status( array( 'cancelled', 'failed' ) ) ) {
			return new WP_Error( 'exists', 'Uplatnica za ostatak je već poslana (narudžba #' . $old->get_id() . '). Za ponovno slanje koristi "Pošalji ponovno".' );
		}
		$order_id = self::create_order( $r, 'ostatak' );
		if ( is_wp_error( $order_id ) ) {
			return $order_id;
		}
		$r = self::get( $id );
		self::log( $r, 'Poslana uplatnica za ostatak (narudžba #' . $order_id . ').' );
		self::save( $r );
		return true;
	}

	/**
	 * Ponovno šalje e-mail s uplatnicom za neplaćenu narudžbu.
	 *
	 * @return true|WP_Error
	 */
	public static function resend( int $id, string $part ) {
		$r        = self::get( $id );
		$order_id = (int) ( $r['orders'][ $part ] ?? 0 );
		$order    = $order_id ? wc_get_order( $order_id ) : null;
		if ( ! $order || $order->is_paid() ) {
			return new WP_Error( 'order', 'Nema neplaćene narudžbe za ponovno slanje.' );
		}
		$emails = WC()->mailer()->get_emails();
		if ( empty( $emails['WC_Email_Customer_On_Hold_Order'] ) ) {
			return new WP_Error( 'mail', 'E-mail WooCommercea "Narudžba na čekanju" nije dostupan.' );
		}
		$emails['WC_Email_Customer_On_Hold_Order']->trigger( $order_id, $order );
		$r = self::get( $id );
		self::log( $r, 'Uplatnica ponovno poslana (narudžba #' . $order_id . ').' );
		self::save( $r );
		return true;
	}

	/**
	 * Otkazivanje: tjedan se oslobađa, neplaćene narudžbe se otkazuju.
	 */
	public static function cancel( int $id, string $why = '' ) {
		$r = self::get( $id );
		if ( ! $r || in_array( $r['state'], array( 'odbijeno', 'otkazano' ), true ) ) {
			return new WP_Error( 'state', 'Rezervacija nije pronađena ili je već zatvorena.' );
		}
		foreach ( (array) $r['orders'] as $order_id ) {
			$order = wc_get_order( (int) $order_id );
			if ( $order && ! $order->is_paid() && ! $order->has_status( array( 'cancelled', 'refunded', 'failed' ) ) ) {
				$order->update_status( 'cancelled', 'Plan A jedrenje: rezervacija je otkazana.' );
			}
		}
		$r          = self::get( $id );
		$r['state'] = 'otkazano';
		self::log( $r, 'Otkazano, tjedan je oslobođen.' . ( $why ? ' ' . $why : '' ) );
		self::save( $r );
		return true;
	}

	/**
	 * Uplata stigla (narudžba "U obradi" ili "Završeno").
	 */
	public static function order_paid( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$rez  = (int) $order->get_meta( '_paj_rez' );
		$part = (string) $order->get_meta( '_paj_part' );
		$r    = self::get( $rez );
		if ( ! $r || ! isset( self::PARTS[ $part ] ) || ! empty( $r['paid'][ $part ] ) ) {
			return;
		}
		if ( (int) ( $r['orders'][ $part ] ?? 0 ) !== (int) $order->get_id() ) {
			$r['orders'][ $part ] = (int) $order->get_id();
		}

		if ( 'ostatak' !== $part ) {
			// Akontacija ili cijeli iznos: tjedan postaje zauzet, ako ga nije uzeo netko drugi.
			self::lock( $r['week'] );
			$taken = array_filter(
				self::for_week( $r['week'], array( 'rezervirano', 'placeno' ) ),
				static function ( $o ) use ( $rez ) {
					return $o['id'] !== $rez;
				}
			);
			if ( $taken ) {
				self::unlock( $r['week'] );
				$r['paid'][ $part ] = time();
				self::log( $r, 'UPOZORENJE: uplata je stigla, ali tjedan je već rezerviran za drugu ekipu. Riješi ručno.' );
				self::save( $r );
				$order->add_order_note( 'Plan A jedrenje: tjedan je već rezerviran za drugu ekipu – provjeri!' );
				Plan_A_Jedrenje_Mail::send(
					Plan_A_Jedrenje_Data::admin_email(),
					'UPOZORENJE: dvostruka uplata za tjedan ' . Plan_A_Jedrenje_Data::week_label( $r['week'] ),
					Plan_A_Jedrenje_Mail::wrap( 'Tjedan je već rezerviran', array( 'Stigla je uplata od ' . self::name( $r ) . ' (narudžba #' . $order->get_id() . '), ali je tjedan već rezerviran za drugu ekipu. Javi se kupcu.' ), Plan_A_Jedrenje_Mail::details( $r ) )
				);
				return;
			}
			$r['paid'][ $part ] = time();
			$r['state']         = 'cijelo' === $part ? 'placeno' : 'rezervirano';
			self::log( $r, ( 'cijelo' === $part ? 'Plaćen cijeli iznos' : 'Plaćena akontacija' ) . ' (narudžba #' . $order->get_id() . '). Tjedan je zauzet.' );
			self::save( $r );
			self::unlock( $r['week'] );
		} else {
			$r['paid']['ostatak'] = time();
			$r['state']           = 'placeno';
			self::log( $r, 'Plaćen ostatak (narudžba #' . $order->get_id() . ').' );
			self::save( $r );
		}

		if ( 'akontacija' === $part ) {
			Plan_A_Jedrenje_Mail::send(
				$r['email'],
				'Rezervacija je potvrđena – ' . Plan_A_Jedrenje_Data::week_label( $r['week'] ),
				Plan_A_Jedrenje_Mail::wrap(
					'Rezervacija je potvrđena',
					array(
						'Bok ' . $r['first'] . '!',
						(string) Plan_A_Jedrenje_Data::value( 'mail_booked' ),
						'Ostatak: ' . Plan_A_Jedrenje_Data::money( (float) $r['rest'] ) . ( $r['rest_due'] ? ', rok uplate ' . rtrim( Plan_A_Jedrenje_Data::long( $r['rest_due'] ), '.' ) : '' ) . '.',
					),
					Plan_A_Jedrenje_Mail::details( $r ) . Plan_A_Jedrenje_Mail::terms_html()
				)
			);
		} else {
			Plan_A_Jedrenje_Mail::send(
				$r['email'],
				'Sve je uplaćeno – ' . Plan_A_Jedrenje_Data::week_label( $r['week'] ),
				Plan_A_Jedrenje_Mail::wrap(
					'cijelo' === $part ? 'Rezervacija je potvrđena i plaćena' : 'Sve je uplaćeno',
					array( 'Bok ' . $r['first'] . '!', (string) Plan_A_Jedrenje_Data::value( 'mail_paid' ) ),
					Plan_A_Jedrenje_Mail::details( $r, false ) . Plan_A_Jedrenje_Mail::terms_html()
				)
			);
		}
	}

	/* ---------------------------------------------------------------------
	 * E-mailovi WooCommercea za narudžbe jedrenja
	 * ------------------------------------------------------------------- */

	private static function order_rez( $order ): ?array {
		if ( ! $order instanceof WC_Order ) {
			return null;
		}
		$rez = (int) $order->get_meta( '_paj_rez' );
		return $rez ? self::get( $rez ) : null;
	}

	/** Umjesto WooCommerceovih e-mailova "U obradi" i "Završeno" šalje se vlastita potvrda. */
	public static function mute_wc_email( $enabled, $order ) {
		return self::order_rez( $order ) ? false : $enabled;
	}

	public static function wc_email_subject( $subject, $order ) {
		$r = self::order_rez( $order );
		if ( ! $r ) {
			return $subject;
		}
		// Naslov se izrađuje neposredno prije sadržaja e-maila: od sada do slanja vrijede tekstovi jedrenja.
		self::$mailing = array_merge( $r, array( 'part' => (string) $order->get_meta( '_paj_part' ) ) );
		$part = (string) $order->get_meta( '_paj_part' );
		$week = Plan_A_Jedrenje_Data::week_label( $r['week'] );
		if ( 'ostatak' === $part ) {
			return 'Uplatnica za ostatak – jedrenje ' . $week;
		}
		return 'Brod je slobodan – uplatnica za ' . ( 'cijelo' === $part ? 'cijeli iznos' : 'akontaciju' ) . ' (' . $week . ')';
	}

	public static function wc_email_heading( $heading, $order ) {
		$r = self::order_rez( $order );
		if ( ! $r ) {
			return $heading;
		}
		return 'ostatak' === (string) $order->get_meta( '_paj_part' ) ? 'Uplatnica za ostatak' : 'Brod je slobodan!';
	}

	/**
	 * Podaci o rezervaciji i rok uplate u e-mailu s uplatnicom (iznad tablice narudžbe).
	 */
	public static function wc_email_block( $order, $sent_to_admin, $plain_text = false, $email = null ) {
		$r = self::order_rez( $order );
		if ( ! $r || $sent_to_admin || ! $order->has_status( 'on-hold' ) ) {
			return;
		}
		$part   = (string) $order->get_meta( '_paj_part' );
		$due    = 'ostatak' === $part ? $r['rest_due'] : $r['deposit_due'];
		$amount = (float) $order->get_total();
		$pay    = self::pay_url( $r, $part );
		$intro  = 'ostatak' === $part
			? 'Vrijeme je za uplatu ostatka za tjedan jedrenja. Uplatnica s 2D kodom je ispod.'
			: (string) Plan_A_Jedrenje_Data::value( 'mail_confirm' );
		$line   = 'Uplati ' . Plan_A_Jedrenje_Data::money( $amount ) . ( $due ? ' najkasnije do ' . Plan_A_Jedrenje_Data::long( $due ) : '' ) . ' prema uplatnici ispod (skeniraj 2D kod u aplikaciji banke).';
		// Narudžba iz košarice već je mogla iskoristiti bon; poveznica se nudi samo uz uplatnicu administratora.
		$bon    = 'checkout' !== $order->get_created_via();

		if ( $plain_text ) {
			echo "\n" . esc_html( $intro ) . "\n" . esc_html( $line ) . "\n" . ( $bon ? esc_html( 'Imaš poklon bon? Plati preko košarice: ' . $pay ) . "\n" : '' ) . "\n";
			return;
		}
		echo '<div style="margin:0 0 24px">'
			. '<p style="margin:0 0 12px;font-size:16px;line-height:1.5">' . esc_html( $intro ) . '</p>'
			. '<p style="margin:0 0 16px;padding:12px 14px;border-radius:10px;background:#fdf1e4;color:#7a3f06;font-size:16px;line-height:1.5"><strong>' . esc_html( $line ) . '</strong></p>'
			. Plan_A_Jedrenje_Mail::details( $r, 'ostatak' !== $part ) // phpcs:ignore WordPress.Security.EscapeOutput -- escapirano u details().
			. ( $bon ? '<p style="margin:0 0 6px;font-size:14px;line-height:1.5;color:#5f6b77">Imaš poklon bon? <a href="' . esc_url( $pay ) . '" style="color:#1a9ad6;font-weight:bold">Plati preko košarice i upiši kod s bona</a> – iznos za uplatu odmah će se umanjiti.</p>' : '' )
			. Plan_A_Jedrenje_Mail::terms_html() // phpcs:ignore WordPress.Security.EscapeOutput
			. '</div>';
	}

	public static function wc_email_organizer( $order, $sent_to_admin, $plain_text = false, $email = null ) {
		if ( ! self::order_rez( $order ) ) {
			return;
		}
		if ( $plain_text ) {
			echo "\n" . esc_html( (string) Plan_A_Jedrenje_Data::value( 'organizer' ) ) . "\n";
			return;
		}
		echo Plan_A_Jedrenje_Mail::organizer_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- escapirano.
	}

	public static function kosarica_texts( $value ) {
		if ( ! self::$mailing || ! is_array( $value ) ) {
			return $value;
		}
		$r    = self::$mailing;
		$rest = 'ostatak' === $r['part'];
		$due  = $rest ? $r['rest_due'] : $r['deposit_due'];
		$value['step1']    = 'Uplati ' . ( $rest ? 'ostatak' : ( 'cijelo' === $r['part'] ? 'cijeli iznos' : 'akontaciju' ) ) . ' prema uplatnici: skeniraj 2D kod u aplikaciji banke ili upiši podatke ručno';
		$value['step2']    = $rest ? 'Kad uplata stigne, dobit ćeš potvrdu e-mailom' : 'Kad uplata stigne, rezervacija je konačna i tjedan je tvoj';
		$value['step3']    = 'Prije polaska poslat ćemo ti upute za ukrcaj i popis stvari za ponijeti';
		$value['deadline'] = $due ? Plan_A_Jedrenje_Data::long( $due ) : '';
		return $value;
	}

	public static function kosarica_gettext( $translation, $text, $domain ) {
		if ( ! self::$mailing ) {
			return $translation;
		}
		$rest = 'ostatak' === self::$mailing['part'];
		$map  = array(
			'Hvala, %s! Vaša prijava je zaprimljena.' => $rest ? 'Bok %s, vrijeme je za uplatu ostatka.' : 'Bok %s, brod je slobodan!',
			'Hvala! Vaša prijava je zaprimljena.'     => $rest ? 'Vrijeme je za uplatu ostatka.' : 'Brod je slobodan!',
			'Vaš izlet'                               => 'Tvoje jedrenje',
		);
		return $map[ $text ] ?? $translation;
	}

	public static function mail_done( $args ) {
		self::$mailing = null;
		return $args;
	}

	public static function thankyou_note( $order_id ) {
		$order = wc_get_order( $order_id );
		$r     = self::order_rez( $order );
		if ( ! $r ) {
			return;
		}
		echo '<p class="paj-thanks" style="margin:0 0 16px;padding:12px 14px;border-radius:12px;background:#e8f4fb;color:#12304b">'
			. esc_html( 'Uplata za ' . Plan_A_Jedrenje_Data::value( 'title' ) . ', ' . Plan_A_Jedrenje_Data::week_label( $r['week'] ) . '. Uplatnicu s 2D kodom poslali smo i e-mailom.' )
			. '<br><small>' . esc_html( (string) Plan_A_Jedrenje_Data::value( 'organizer' ) ) . '</small></p>';
	}

	/* ---------------------------------------------------------------------
	 * Plaćanje preko košarice (poklon bon)
	 * ------------------------------------------------------------------- */

	public static function pay_url( array $r, string $part ): string {
		return add_query_arg(
			array(
				'paj_plati' => $r['token'],
				'dio'       => $part,
			),
			home_url( '/' )
		);
	}

	/**
	 * Smije li se dio uplate sada platiti preko košarice.
	 */
	private static function payable( array $r, string $part ): bool {
		if ( ! empty( $r['paid'][ $part ] ) ) {
			return false;
		}
		if ( 'ostatak' === $part ) {
			return 'rezervirano' === $r['state'] && $r['rest'] > 0;
		}
		return 'potvrdeno' === $r['state'] && ( 'cijelo' === $part ) === (bool) $r['full'];
	}

	public static function pay_link() {
		if ( empty( $_GET['paj_plati'] ) || ! function_exists( 'WC' ) || ! WC()->cart ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$token = preg_replace( '/[^A-Za-z0-9]/', '', (string) wp_unslash( $_GET['paj_plati'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$part  = sanitize_key( wp_unslash( $_GET['dio'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$ids   = strlen( $token ) === 32 ? get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_paj_token', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => $token, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		) : array();
		$r     = $ids ? self::get( (int) $ids[0] ) : null;
		// Novi posjetitelj još nema sesiju WooCommercea; bez nje se obavijest ne bi prikazala.
		if ( WC()->session && ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
		if ( ! $r || ! hash_equals( $r['token'], $token ) || ! isset( self::PARTS[ $part ] ) || ! self::payable( $r, $part ) ) {
			wc_add_notice( 'Ova poveznica za uplatu više ne vrijedi (uplata je možda već zaprimljena). Javi nam se ako trebaš pomoć.', 'error' );
			wp_safe_redirect( wc_get_cart_url() );
			exit;
		}
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( ! empty( $item['paj'] ) ) {
				WC()->cart->remove_cart_item( $key );
			}
		}
		self::$adding = true;
		WC()->cart->add_to_cart(
			self::product_id( true ),
			1,
			0,
			array(),
			array(
				'paj' => array(
					'rez'    => (int) $r['id'],
					'part'   => $part,
					'amount' => round( self::part_amount( $r, $part ), 2 ),
				),
			)
		);
		self::$adding = false;
		// Podaci za naplatu iz rezervacije (kupac ih ne mora ponovno upisivati).
		if ( WC()->customer ) {
			$c = WC()->customer;
			if ( '' === (string) $c->get_billing_email() ) {
				$c->set_billing_first_name( $r['first'] );
				$c->set_billing_last_name( $r['last'] );
				$c->set_billing_email( $r['email'] );
				$c->set_billing_phone( $r['phone'] );
				$c->set_billing_address_1( $r['address'] );
				$c->set_billing_postcode( $r['postcode'] );
				$c->set_billing_city( $r['city'] );
				$c->set_billing_country( 'HR' );
				$c->save();
			}
		}
		wc_add_notice( 'U košarici je uplata za jedrenje. Ako imaš poklon bon, upiši kod i iznos će se umanjiti, pa nastavi na plaćanje.', 'success' );
		wp_safe_redirect( wc_get_cart_url() );
		exit;
	}

	public static function add_validation( $passed, $product_id ) {
		if ( self::is_product( (int) $product_id ) && ! self::$adding ) {
			if ( WC()->session && ! WC()->session->has_session() ) {
				WC()->session->set_customer_session_cookie( true );
			}
			wc_add_notice( 'Tjedan jedrenja rezervira se na stranici jedrenja.', 'error' );
			return false;
		}
		return $passed;
	}

	/**
	 * Iznos i pravo plaćanja uvijek se provjeravaju na poslužitelju.
	 */
	public static function check_cart( $cart ) {
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( ! isset( $item['data'] ) || ! self::is_product( $item['data'] ) ) {
				continue;
			}
			$r = self::get( (int) ( $item['paj']['rez'] ?? 0 ) );
			$p = (string) ( $item['paj']['part'] ?? '' );
			if ( ! $r || ! self::payable( $r, $p ) || abs( self::part_amount( $r, $p ) - (float) $item['paj']['amount'] ) > 0.001 ) {
				unset( $cart->cart_contents[ $key ] );
			}
		}
	}

	public static function cart_prices( $cart ) {
		if ( ! $cart || ( is_admin() && ! wp_doing_ajax() ) ) {
			return;
		}
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( ! empty( $item['paj'] ) && self::is_product( $item['data'] ) ) {
				$cart->cart_contents[ $key ]['data']->set_price( (float) $item['paj']['amount'] );
			}
		}
	}

	public static function cart_item_name( $name, $item ) {
		if ( empty( $item['paj'] ) ) {
			return $name;
		}
		$r = self::get( (int) $item['paj']['rez'] );
		return $r ? esc_html( self::item_title( $r, (string) $item['paj']['part'] ) ) : $name;
	}

	public static function cart_item_permalink( $link, $item ) {
		return empty( $item['paj'] ) ? $link : '';
	}

	public static function cart_item_data( $data, $item ) {
		if ( empty( $item['paj'] ) ) {
			return $data;
		}
		$r = self::get( (int) $item['paj']['rez'] );
		if ( ! $r ) {
			return $data;
		}
		$data[] = array(
			'key'   => 'Termin',
			'value' => esc_html( Plan_A_Jedrenje_Data::week_long( $r['week'] ) ),
		);
		$data[] = array(
			'key'   => 'Brod',
			'value' => esc_html( Plan_A_Jedrenje_Data::value( 'boat' ) . ', ' . $r['persons'] . ' osoba' ),
		);
		$data[] = array(
			'key'   => 'Cijena',
			'value' => esc_html( Plan_A_Jedrenje_Data::money( (float) $r['price'] ) . ' za cijeli brod' ),
		);
		return $data;
	}

	public static function checkout_line_item( $item, $cart_item_key, $values ) {
		if ( empty( $values['paj'] ) ) {
			return;
		}
		$r = self::get( (int) $values['paj']['rez'] );
		if ( ! $r ) {
			return;
		}
		$part = (string) $values['paj']['part'];
		$item->set_name( self::item_title( $r, $part ) );
		self::item_meta( $item, $r, $part );
	}

	/**
	 * Narudžba iz košarice zamjenjuje narudžbu koju je izradio administrator (ta se otkazuje).
	 */
	public static function checkout_order_created( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		foreach ( $order->get_items() as $item ) {
			$rez  = (int) $item->get_meta( '_paj_rez' );
			$part = (string) $item->get_meta( '_paj_part' );
			$r    = $rez ? self::get( $rez ) : null;
			if ( ! $r || ! isset( self::PARTS[ $part ] ) ) {
				continue;
			}
			$order->update_meta_data( '_paj_rez', $rez );
			$order->update_meta_data( '_paj_part', $part );
			$order->save();

			$old_id = (int) ( $r['orders'][ $part ] ?? 0 );
			$old    = $old_id && $old_id !== $order->get_id() ? wc_get_order( $old_id ) : null;
			if ( $old && ! $old->is_paid() && ! $old->has_status( array( 'cancelled', 'refunded', 'failed' ) ) ) {
				$old->update_status( 'cancelled', 'Plan A jedrenje: zamijenjeno narudžbom #' . $order->get_id() . ' (plaćanje preko košarice).' );
			}
			$r                    = self::get( $rez );
			$r['orders'][ $part ] = $order->get_id();
			self::log( $r, self::part_label( $r, $part ) . ': kupac plaća preko košarice (narudžba #' . $order->get_id() . ').' );
			self::save( $r );
			break;
		}
	}

	/* ---------------------------------------------------------------------
	 * Podsjetnici
	 * ------------------------------------------------------------------- */

	public static function daily() {
		$today = Plan_A_Jedrenje_Data::today();
		$lines = array();
		foreach ( self::by_state( array( 'potvrdeno', 'rezervirano' ) ) as $r ) {
			$changed = false;
			if ( 'potvrdeno' === $r['state'] && $r['deposit_due'] && $r['deposit_due'] < $today && empty( $r['reminded']['deposit'] ) ) {
				$lines[]                    = 'Akontacija nije uplaćena u roku (' . Plan_A_Jedrenje_Data::numeric( $r['deposit_due'] ) . '): ' . self::name( $r ) . ', ' . Plan_A_Jedrenje_Data::week_label( $r['week'] ) . '. Tjedan je i dalje "Na upitu" – javi se kupcu ili otkaži rezervaciju.';
				$r['reminded']['deposit'] = $today;
				$changed                    = true;
			}
			if ( 'rezervirano' === $r['state'] && $r['rest_due'] ) {
				$sent = ! empty( $r['orders']['ostatak'] );
				if ( ! $sent && $r['rest_due'] <= Plan_A_Jedrenje_Data::add_days( $today, 7 ) && empty( $r['reminded']['rest'] ) ) {
					$lines[]                 = 'Rok za ostatak je ' . Plan_A_Jedrenje_Data::numeric( $r['rest_due'] ) . ', a uplatnica još nije poslana: ' . self::name( $r ) . ', ' . Plan_A_Jedrenje_Data::week_label( $r['week'] ) . '.';
					$r['reminded']['rest'] = $today;
					$changed                 = true;
				}
				if ( $sent && $r['rest_due'] < $today && empty( $r['reminded']['rest_late'] ) ) {
					$lines[]                      = 'Ostatak nije uplaćen u roku (' . Plan_A_Jedrenje_Data::numeric( $r['rest_due'] ) . '): ' . self::name( $r ) . ', ' . Plan_A_Jedrenje_Data::week_label( $r['week'] ) . '.';
					$r['reminded']['rest_late'] = $today;
					$changed                      = true;
				}
			}
			if ( $changed ) {
				self::save( $r );
			}
		}
		if ( $lines ) {
			Plan_A_Jedrenje_Mail::send(
				Plan_A_Jedrenje_Data::admin_email(),
				'Jedrenje: podsjetnik za uplate',
				Plan_A_Jedrenje_Mail::wrap( 'Podsjetnik za uplate', $lines, '', array( 'Otvori rezervacije', admin_url( 'admin.php?page=plan-a-jedrenje&tab=rezervacije' ) ) )
			);
		}
	}
}
