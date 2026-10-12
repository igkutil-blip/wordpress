<?php
/**
 * Zamjena za WpTravelly kad on nije uključen (faza 3).
 *
 * U mapi ttbm/ je skraćena kopija WpTravellyja 2.3.4 (GPL, mage-people.com): samo ono što
 * Plan A koristi – vrste sadržaja, stranica izleta (predložak „smart”), prijava u
 * WooCommerce košaricu, zapisi o prodanim mjestima i upiti. Klase, kuke, podaci i tekstovi
 * (domena tour-booking-manager, pa vrijede postojeći prijevodi) imaju ista imena kao u
 * WpTravellyju, pa naši dodaci i stari podaci rade bez promjena.
 *
 * Dok je WpTravelly uključen, ovo se ne učitava (on ima prednost i radi kao dosad).
 *
 * @package Plan_A_Prijave
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Prijave_Ttbm {

	/** Je li naša zamjena aktivna (WpTravelly isključen). */
	private static $active = false;

	public static function init() {
		// WpTravelly se učitava kad i ostali dodaci (prije plugins_loaded), pa tada znamo je li tu.
		add_action( 'plugins_loaded', array( __CLASS__, 'boot' ), 0 );
	}

	public static function active(): bool {
		return self::$active;
	}

	public static function boot() {
		if ( class_exists( 'TTBM_Function' ) || defined( 'TTBM_PLUGIN_DIR' ) ) {
			return;
		}
		self::$active = true;
		define( 'TTBM_PLUGIN_DIR', PLAN_A_PRIJAVE_DIR . 'ttbm' );
		define( 'TTBM_PLUGIN_URL', untrailingslashit( PLAN_A_PRIJAVE_URL ) . '/ttbm' );
		define( 'TTBM_PLUGIN_VERSION', '2.3.0' );

		$inc = TTBM_PLUGIN_DIR . '/inc/';
		require_once $inc . 'TTBM_Global_Function.php';
		require_once $inc . 'TTBM_Global_Style.php';
		require_once $inc . 'TTBM_Custom_Layout.php';
		require_once $inc . 'TTBM_Custom_Slider.php';
		require_once $inc . 'TTBM_Function.php';
		require_once $inc . 'TTBM_Query.php';
		require_once $inc . 'TTBM_Layout.php';
		require_once $inc . 'TTBM_Frontend.php';
		require_once $inc . 'TTBM_Filter_Pagination.php';
		require_once $inc . 'TTBM_Details_Layout.php';
		require_once $inc . 'TTBM_Booking.php';
		$adm = TTBM_PLUGIN_DIR . '/admin/';
		require_once $adm . 'TTBM_Admin.php';
		require_once $adm . 'TTBM_CPT.php';
		require_once $adm . 'TTBM_Taxonomy.php';
		require_once $adm . 'TTBM_Get_Enquiry.php';
		if ( TTBM_Global_Function::has_woocommerce() ) {
			require_once $adm . 'TTBM_Hidden_Product.php';
			require_once $inc . 'TTBM_Woocommerce.php';
		}

		add_action( 'init', array( __CLASS__, 'textdomain' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 90 );
		add_action( 'wp_head', array( __CLASS__, 'js_constant' ), 5 );
		add_action( 'admin_head', array( __CLASS__, 'js_constant' ), 5 );
		add_filter( 'woocommerce_add_to_cart_redirect', array( __CLASS__, 'add_to_cart_redirect' ) );
		add_action( 'widgets_init', array( __CLASS__, 'sidebar' ) );
		add_action( 'init', array( __CLASS__, 'shortcodes' ), 20 );
		// Jednom dnevno osvježi izračunate datume izleta (ttbm_upcoming_date …), kao WpTravelly.
		add_action( 'init', array( 'TTBM_Function', 'update_all_upcoming_date_month' ), 99 );
	}

	/** WpTravellyjevi kratki kodovi za popise izleta → naš popis; ostali (hoteli i sl.) ne ispisuju ništa. */
	const LIST_SHORTCODES  = array( 'travel-list', 'wptravelly-tour-list', 'ttbm-tour-list', 'ttbm-search-result' );
	const OTHER_SHORTCODES = array(
		'travel-location-list', 'ttbm-activity_browse', 'ttbm-booking-confirmation', 'ttbm-feature-hotel', 'ttbm-hotel-list',
		'ttbm-hotel-location-list', 'ttbm-hotel-map', 'ttbm-hotel-rooms', 'ttbm-hotel-slider', 'ttbm-popular-hotel',
		'ttbm-registration', 'ttbm-related', 'ttbm-texonomy-display', 'ttbm-top-attractions', 'ttbm-top-filter',
		'ttbm-top-search', 'wptravelly-hotel-list', 'wptravelly-hotel-search', 'wptravelly-hotel-search-list',
	);

	/** Da stare stranice s WpTravellyjevim kratkim kodovima ne prikazuju goli tekst „[travel-list …]”. */
	public static function shortcodes() {
		foreach ( self::LIST_SHORTCODES as $tag ) {
			if ( ! shortcode_exists( $tag ) ) {
				add_shortcode( $tag, static fn() => shortcode_exists( 'plan-a-izleti' ) ? do_shortcode( '[plan-a-izleti]' ) : '' );
			}
		}
		foreach ( self::OTHER_SHORTCODES as $tag ) {
			if ( ! shortcode_exists( $tag ) ) {
				add_shortcode( $tag, '__return_empty_string' );
			}
		}
	}

	/** Bočna traka stranice izleta (da widgeti u njoj ne postanu „neaktivni”). */
	public static function sidebar() {
		register_sidebar(
			array(
				'name'          => esc_html__( 'Tour Booking Details Page Sidebar', 'tour-booking-manager' ),
				'id'            => 'ttbm_details_sidebar',
				'description'   => esc_html__( 'Widgets in this area will be shown on tour booking details page sidebar.', 'tour-booking-manager' ),
				'before_widget' => '<div id="%1$s" class="ttbm_default_widget ttbm_sidebar_widget %2$s">',
				'after_widget'  => '</div>',
				'before_title'  => '<h4 class="ttbm_title_style_3">',
				'after_title'   => '</h4>',
			)
		);
	}

	public static function textdomain() {
		// Prijevodi WpTravellyja (Loco, wp-content/languages) vrijede i bez njega.
		load_plugin_textdomain( 'tour-booking-manager', false, 'tour-booking-manager/languages/' );
	}

	/** CSS i JS samo na stranici izleta (isti kao WpTravellyjevi). */
	public static function assets() {
		if ( ! is_singular( 'ttbm_tour' ) ) {
			return;
		}
		$url = TTBM_PLUGIN_URL . '/assets/';
		$dir = TTBM_PLUGIN_DIR . '/assets/';
		$v   = static fn( $f ) => (string) filemtime( $dir . $f );

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'jquery-ui-core' );
		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_script( 'jquery-ui-accordion' );
		wp_enqueue_script( 'jquery-ui-autocomplete' );
		wp_enqueue_style( 'mp_jquery_ui', $url . 'jquery-ui.min.css', array(), '1.13.2' );
		wp_enqueue_style( 'mp_font_awesome', $url . 'all.min.css', array(), '6.7.2' );
		wp_enqueue_style( 'mp_select_2', $url . 'select_2/select2.min.css', array(), '4.0.13' );
		wp_enqueue_script( 'mp_select_2', $url . 'select_2/select2.min.js', array( 'jquery' ), '4.0.13', true );
		wp_enqueue_style( 'mp_owl_carousel', $url . 'owl_carousel/owl.carousel.min.css', array(), '2.3.4' );
		wp_enqueue_script( 'mp_owl_carousel', $url . 'owl_carousel/owl.carousel.min.js', array(), '2.3.4', true );
		wp_enqueue_style( 'ttbm_plugin_global', $url . 'mp_style/ttbm_plugin_global.css', array(), $v( 'mp_style/ttbm_plugin_global.css' ) );
		wp_enqueue_script( 'ttbm_plugin_global', $url . 'mp_style/ttbm_plugin_global.js', array( 'jquery' ), $v( 'mp_style/ttbm_plugin_global.js' ), true );
		wp_enqueue_script( 'moment' );
		wp_enqueue_style( 'ttbm_date_range_picker', $url . 'date_range_picker/date_range_picker.min.css', array(), '1' );
		wp_enqueue_script( 'ttbm_date_range_picker_js', $url . 'date_range_picker/date_range_picker.js', array( 'jquery', 'moment' ), '1', true );
		wp_enqueue_style( 'ttbm_registration', $url . 'frontend/ttbm_registration.css', array(), $v( 'frontend/ttbm_registration.css' ) );
		wp_enqueue_style( 'ttbm_smart_booking', $url . 'frontend/ttbm_smart_booking.css', array( 'ttbm_registration' ), $v( 'frontend/ttbm_smart_booking.css' ) );
		wp_enqueue_style( 'ttbm_tour_list_modern', $url . 'frontend/ttbm_tour_list_modern.css', array( 'ttbm_registration' ), $v( 'frontend/ttbm_tour_list_modern.css' ) );
		wp_enqueue_style( 'ttbm_hotel_lists', $url . 'frontend/ttbm_hotel_lists.css', array( 'ttbm_registration' ), $v( 'frontend/ttbm_hotel_lists.css' ) );
		wp_enqueue_style( 'ttbm_details', $url . 'frontend/ttbm_details.css', array( 'ttbm_hotel_lists' ), $v( 'frontend/ttbm_details.css' ) );
		wp_enqueue_style( 'ttbm_travello_details', $url . 'frontend/ttbm_travello_details.css', array( 'ttbm_registration', 'ttbm_details' ), $v( 'frontend/ttbm_travello_details.css' ) );
		wp_enqueue_style( 'mage-icons', $url . 'mage-icon/css/mage-icon.css', array(), TTBM_PLUGIN_VERSION );
		wp_enqueue_script( 'ttbm_registration', $url . 'frontend/ttbm_registration.js', array( 'jquery', 'jquery-ui-autocomplete', 'ttbm_date_range_picker_js' ), $v( 'frontend/ttbm_registration.js' ), true );
		wp_enqueue_script( 'ttbm_price_calculation', $url . 'frontend/ttbm_price_calculation.js', array( 'jquery' ), $v( 'frontend/ttbm_price_calculation.js' ), true );
		wp_enqueue_script( 'ttbm_smart_booking', $url . 'frontend/ttbm_smart_booking.js', array( 'jquery', 'ttbm_registration', 'ttbm_price_calculation' ), $v( 'frontend/ttbm_smart_booking.js' ), true );
		wp_enqueue_script( 'ttbm_script', $url . 'frontend/ttbm_script.js', array( 'jquery' ), $v( 'frontend/ttbm_script.js' ), true );
		wp_enqueue_script( 'ttbm_shortcode', $url . 'frontend/ttbm_shortcode.js', array( 'jquery' ), $v( 'frontend/ttbm_shortcode.js' ), true );
		wp_enqueue_script( 'ttbm-confirm-btn', $url . 'frontend/ttbm-confirm-btn.js', array( 'jquery' ), $v( 'frontend/ttbm-confirm-btn.js' ), true );

		$i18n = array(
			'load_more'    => esc_html__( 'Load more', 'tour-booking-manager' ),
			'show_less'    => esc_html__( 'Show less', 'tour-booking-manager' ),
			'select_date'  => esc_html__( 'Please Select Date', 'tour-booking-manager' ),
			'select_time'  => esc_html__( 'Please Select Time', 'tour-booking-manager' ),
			'select_ticket' => esc_html__( 'Please Select Ticket Type', 'tour-booking-manager' ),
			'link_copied'  => esc_html__( 'Link copied to clipboard.', 'tour-booking-manager' ),
		);
		$ajax = array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'ttbm_frontend_nonce' ),
		);
		wp_localize_script( 'ttbm_registration', 'ttbm_i18n', $i18n );
		wp_localize_script( 'ttbm_registration', 'ttbm_ajax', $ajax );
		wp_localize_script( 'ttbm_price_calculation', 'ttbm_price_calc_vars', $ajax );
		wp_localize_script( 'ttbm_script', 'ttbm_i18n', $i18n );
		wp_localize_script( 'ttbm_script', 'ttbm_ajax', $ajax + array( 'wishlist_url' => '' ) );
	}

	/**
	 * Nakon „Rezervirajte” ravno na blagajnu (ili u košaricu), kao WpTravelly
	 * (TTBM_Payment_Settings, postavka ttbm_payment_cart_redirect, zadano checkout).
	 */
	public static function add_to_cart_redirect( $url ) {
		if ( ! isset( $_POST['ttbm_form_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ttbm_form_nonce'] ) ), 'ttbm_form_nonce' ) ) {
			return $url;
		}
		$mode = TTBM_Global_Function::get_settings( 'ttbm_payment_settings', 'ttbm_payment_cart_redirect', 'checkout' );
		if ( 'cart' === $mode && function_exists( 'wc_get_cart_url' ) ) {
			return wc_get_cart_url();
		}
		return function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : $url;
	}

	/** Globalne JS varijable koje WpTravellyjev JS očekuje (valuta, adresa za AJAX). */
	public static function js_constant() {
		$enquiry_page = is_admin() && isset( $_GET['page'] ) && 'ttbm_get_enquiry' === $_GET['page']; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $enquiry_page && ( is_admin() || ! is_singular( 'ttbm_tour' ) ) ) {
			return;
		}
		$wc   = function_exists( 'get_woocommerce_currency_symbol' );
		$vars = array(
			'ttbm_currency_symbol'              => $wc ? get_woocommerce_currency_symbol() : '', // kao WpTravelly: HTML entitet (npr. &euro;)
			'ttbm_currency_position'            => $wc ? (string) get_option( 'woocommerce_currency_pos' ) : '',
			'ttbm_currency_decimal'             => $wc ? wc_get_price_decimal_separator() : '',
			'ttbm_currency_thousands_separator' => $wc ? wc_get_price_thousand_separator() : '',
			'ttbm_num_of_decimal'               => $wc ? (string) get_option( 'woocommerce_price_num_decimals', 2 ) : '',
			'ttbm_ajax_url'                     => admin_url( 'admin-ajax.php' ),
			'ttbm_site_url'                     => ' ' . get_site_url(),
			'ttbm_empty_image_url'              => TTBM_PLUGIN_URL . '/assets/images/no_image.png',
			'ttbm_date_format'                  => (string) TTBM_Global_Function::get_settings( 'ttbm_global_settings', 'date_format', 'D d M , yy' ),
			'ttbm_date_format_without_year'     => (string) TTBM_Global_Function::get_settings( 'ttbm_global_settings', 'date_format_without_year', 'D d M' ),
		);
		echo "<script>\n";
		foreach ( $vars as $k => $v ) {
			echo 'let ' . esc_js( $k ) . ' = ' . wp_json_encode( $v ) . ";\n";
		}
		echo 'let ttbm_ajax_is_logged_in = ' . ( is_user_logged_in() ? 'true' : 'false' ) . ";\n</script>\n";
	}
}
