<?php
/**
 * Novi izgled košarice i plaćanja.
 *
 * Zamjenjuje WooCommerce predloške cart/cart.php, cart/cart-totals.php,
 * cart/proceed-to-checkout-button.php, checkout/form-checkout.php i
 * checkout/review-order.php (i one iz teme, npr. Flatsome). Predlošci zadržavaju
 * sve kuke, filtre i oznake (klase, id-eve, imena polja) na koje se oslanjaju
 * WooCommerceove skripte (cart.js, checkout.js) i drugi dodaci.
 *
 * Podaci WpTravellyja iz košarice (mjesto, datum i vrijeme, završni datum, hotel,
 * karte, dodatne usluge) prikazuju se strukturirano umjesto bloka "Booking Details";
 * kuke WpTravellyja za dodatke (ttbm_before_cart_item_display, ttbm_show_cart_item)
 * i podaci drugih dodataka i dalje se ispisuju.
 *
 * @package Plan_A_Kosarica
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Kosarica {

	const TEMPLATES = array(
		'cart/cart.php',
		'cart/cart-totals.php',
		'cart/proceed-to-checkout-button.php',
		'checkout/form-checkout.php',
		'checkout/review-order.php',
	);

	public static function init() {
		if ( ! class_exists( 'WooCommerce' ) || ! Plan_A_Kosarica_Settings::get( 'enabled' ) ) {
			return;
		}
		add_filter( 'woocommerce_locate_template', array( __CLASS__, 'locate_template' ), 99, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 30 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'woocommerce_review_order_before_payment', array( __CLASS__, 'payment_heading' ) );
		add_filter( 'gettext_woocommerce', array( __CLASS__, 'headings' ), 10, 2 );
		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'order_fields' ), 99 );
	}

	/**
	 * Naslovi dijelova obrasca na plaćanju (prema izvornom tekstu WooCommercea,
	 * pa vrijedi i uz hrvatski prijevod).
	 */
	public static function headings( $translation, $text ) {
		static $map = array(
			'Billing details'                 => 'Podaci kupca',
			'Billing &amp; Shipping'          => 'Podaci kupca',
			'Additional information'          => 'Napomena',
		);
		if ( isset( $map[ $text ] ) && ! is_admin() && did_action( 'wp' ) && function_exists( 'is_checkout' ) && is_checkout() ) {
			return $map[ $text ];
		}
		return $translation;
	}

	public static function locate_template( $template, $template_name ) {
		if ( in_array( $template_name, self::TEMPLATES, true ) ) {
			$ours = PLAN_A_KOSARICA_DIR . 'templates/' . $template_name;
			if ( file_exists( $ours ) ) {
				return $ours;
			}
		}
		return $template;
	}

	private static function active_page(): bool {
		if ( ! function_exists( 'is_cart' ) ) {
			return false;
		}
		return is_cart() || ( is_checkout() && ! is_wc_endpoint_url( 'order-received' ) && ! is_wc_endpoint_url( 'order-pay' ) );
	}

	public static function assets() {
		if ( ! self::active_page() ) {
			return;
		}
		wp_enqueue_style( 'plan-a-kosarica', PLAN_A_KOSARICA_URL . 'assets/css/kosarica.css', array(), PLAN_A_KOSARICA_VERSION );
		$s = Plan_A_Kosarica_Settings::get();
		wp_add_inline_style(
			'plan-a-kosarica',
			sprintf( ':root{--paka-cta:%s;--paka-accent:%s;--paka-navy:%s;}', esc_attr( $s['cta'] ), esc_attr( $s['accent'] ), esc_attr( $s['navy'] ) )
		);
		wp_enqueue_script( 'plan-a-kosarica', PLAN_A_KOSARICA_URL . 'assets/js/kosarica.js', array(), PLAN_A_KOSARICA_VERSION, array( 'in_footer' => true ) );
	}

	public static function body_class( $classes ) {
		if ( self::active_page() ) {
			$classes[] = 'paka-on';
			if ( Plan_A_Kosarica_Settings::get( 'steps' ) ) {
				$classes[] = 'paka-steps-on';
			}
		}
		return $classes;
	}

	public static function payment_heading() {
		echo '<h2 class="paka-h2 paka-pay-heading">' . esc_html__( 'Način plaćanja', 'plan-a-kosarica' ) . '</h2>';
	}

	/* ------------------------------------------------------------------ */
	/* Dijelovi stranice                                                    */
	/* ------------------------------------------------------------------ */

	/**
	 * Koraci Košarica → Podaci → Plaćanje.
	 *
	 * @param int $current 1 = košarica, 2 = podaci (plaćanje), 3 = plaćanje.
	 */
	public static function steps( int $current, bool $finished = false ) {
		if ( ! Plan_A_Kosarica_Settings::get( 'steps' ) ) {
			return;
		}
		$steps = array(
			1 => array( __( 'Košarica', 'plan-a-kosarica' ), wc_get_cart_url() ),
			2 => array( __( 'Podaci', 'plan-a-kosarica' ), 1 === $current ? wc_get_checkout_url() : '#customer_details' ),
			3 => array( __( 'Plaćanje', 'plan-a-kosarica' ), 1 === $current ? wc_get_checkout_url() : '#payment' ),
		);
		if ( $finished ) {
			// Završna stranica narudžbe: Košarica > Podaci > Prijava završena (bez poveznica natrag).
			$steps[1][1] = '';
			$steps[2][1] = '';
			$steps[3]    = array( __( 'Prijava završena', 'plan-a-kosarica' ), '' );
			$current     = 3;
		}
		echo '<ol class="paka-steps" data-paka-steps aria-label="' . esc_attr__( 'Koraci rezervacije', 'plan-a-kosarica' ) . '">';
		foreach ( $steps as $number => $step ) {
			$state = $number < $current ? 'is-done' : ( $number === $current ? 'is-current' : '' );
			if ( $finished ) {
				$state = 3 === $number ? 'is-current is-finished' : 'is-done';
			}
			$tag   = '' !== $step[1] ? 'a href="' . esc_url( $step[1] ) . '"' : 'span class="paka-step__link"';
			$close = '' !== $step[1] ? 'a' : 'span';
			echo '<li class="paka-step ' . esc_attr( $state ) . '" data-paka-step="' . esc_attr( (string) $number ) . '"' . ( $number === $current ? ' aria-current="step"' : '' ) . '>'
				. '<' . $tag . '><span class="paka-step__num" aria-hidden="true">' . ( $finished && 3 === $number ? '&#10003;' : esc_html( (string) $number ) ) . '</span>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
				. '<span class="paka-step__label">' . esc_html( $step[0] ) . '</span></' . $close . '></li>';
		}
		echo '</ol>';
	}

	/**
	 * Adresa popisa izleta iz postavki (zadano /izleti/).
	 */
	public static function more_url(): string {
		$url = trim( (string) Plan_A_Kosarica_Settings::get( 'more_url' ) );
		$url = '' !== $url ? $url : '/izleti/';
		return 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ? home_url( $url ) : $url;
	}

	/**
	 * Gumb teme "Nastavite kupnju" (Flatsome, klasa button-continue-shopping) u akcijama
	 * košarice zamjenjuje se sekundarnim gumbom novog izgleda koji vodi na popis izleta.
	 */
	public static function cart_actions( string $html ): string {
		$button = '<a class="button-continue-shopping paka-btn paka-btn--secondary" href="' . esc_url( self::more_url() ) . '">'
			. self::icon( 'back' ) . '<span>' . esc_html__( 'Nastavi s odabirom izleta', 'plan-a-kosarica' ) . '</span></a>';
		return (string) preg_replace_callback(
			'#<a\b[^>]*\bclass=(["\'])[^"\']*\bbutton-continue-shopping\b[^"\']*\1[^>]*>.*?</a>#is',
			static function () use ( $button ) {
				return $button;
			},
			$html,
			1
		);
	}

	/**
	 * Kraći tekst u polju "Napomene uz narudžbu".
	 */
	public static function order_fields( $fields ) {
		if ( isset( $fields['order']['order_comments'] ) && self::active_page() ) {
			$fields['order']['order_comments']['placeholder'] = __( 'Napomene o vašoj narudžbi', 'plan-a-kosarica' );
		}
		return $fields;
	}

	/**
	 * "Pogledaj još izleta" i "Trebaš pomoć? Javi nam se" (WhatsApp).
	 */
	public static function help_links( bool $more ) {
		$s    = Plan_A_Kosarica_Settings::get();
		$html = '';
		if ( $more && '' !== trim( (string) $s['more_url'] ) ) {
			$url   = self::more_url();
			$html .= '<p class="paka-more"><a href="' . esc_url( $url ) . '">' . self::icon( 'back' ) . '<span>' . esc_html__( 'Pogledaj još izleta', 'plan-a-kosarica' ) . '</span></a></p>';
		}
		if ( '' !== $s['whatsapp'] ) {
			$wa    = 'https://wa.me/' . rawurlencode( $s['whatsapp'] ) . '?text=' . rawurlencode( __( 'Pozdrav, trebam pomoć s rezervacijom.', 'plan-a-kosarica' ) );
			$html .= '<p class="paka-help"><span>' . esc_html( $s['help_text'] ) . '</span> '
				. '<a href="' . esc_url( $wa ) . '" target="_blank" rel="noopener noreferrer">' . self::icon( 'whatsapp' ) . '<span>' . esc_html__( 'Javi nam se', 'plan-a-kosarica' ) . '</span></a></p>';
		}
		if ( '' !== $html ) {
			echo '<div class="paka-links">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		}
	}

	/* ------------------------------------------------------------------ */
	/* Podaci stavke                                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * ID izleta (WpTravelly) iz stavke košarice ili 0.
	 */
	public static function tour_id( array $cart_item ): int {
		$id = isset( $cart_item['ttbm_id'] ) ? (int) $cart_item['ttbm_id'] : 0;
		if ( ! $id || ! class_exists( 'TTBM_Function' ) ) {
			return 0;
		}
		if ( method_exists( 'TTBM_Function', 'post_id_multi_language' ) ) {
			$id = (int) TTBM_Function::post_id_multi_language( $id );
		}
		$type = method_exists( 'TTBM_Function', 'get_cpt_name' ) ? TTBM_Function::get_cpt_name() : 'ttbm_tour';
		return get_post_type( $id ) === $type ? $id : 0;
	}

	private static function price( int $tour_id, $amount ): string {
		if ( class_exists( 'TTBM_Global_Function' ) && method_exists( 'TTBM_Global_Function', 'wc_price' ) ) {
			return (string) TTBM_Global_Function::wc_price( $tour_id, $amount );
		}
		return (string) wc_price( (float) $amount );
	}

	/**
	 * Datum (i vrijeme ako postoji) iz WpTravellyja: 'Y-m-d' ili 'Y-m-d H:i'.
	 *
	 * @return array{date: string, time: string}
	 */
	private static function date_parts( string $value ): array {
		$value = trim( $value );
		$ts    = '' !== $value ? strtotime( $value ) : false;
		if ( ! $ts ) {
			return array(
				'date' => $value,
				'time' => '',
			);
		}
		$has_time = (bool) preg_match( '/\d{1,2}:\d{2}/', $value );
		return array(
			'date' => wp_date( 'j. n. Y.', $ts, new DateTimeZone( 'UTC' ) ),
			'time' => $has_time ? wp_date( 'H:i', $ts, new DateTimeZone( 'UTC' ) ) : '',
		);
	}

	/**
	 * Strukturirani podaci izleta iz stavke košarice (isti podaci koje WpTravelly
	 * prikazuje u bloku "Booking Details").
	 */
	public static function tour( array $cart_item ): array {
		$tour_id = self::tour_id( $cart_item );
		$info    = array(
			'id'       => $tour_id,
			'location' => '',
			'date'     => '',
			'time'     => '',
			'end_date' => '',
			'end_time' => '',
			'hotel'    => array(),
			'tickets'  => array(),
			'services' => array(),
			'people'   => 0,
		);
		if ( ! $tour_id ) {
			return $info;
		}

		$get = static function ( $key, $default = '' ) use ( $tour_id ) {
			if ( class_exists( 'TTBM_Global_Function' ) && method_exists( 'TTBM_Global_Function', 'get_post_info' ) ) {
				return TTBM_Global_Function::get_post_info( $tour_id, $key, $default );
			}
			$value = get_post_meta( $tour_id, $key, true );
			return '' === $value ? $default : $value;
		};

		if ( 'off' !== $get( 'ttbm_display_location', 'on' ) ) {
			// Mjesto kao u WpTravellyju; ako ga nema, država izleta.
			$info['location'] = trim( (string) $get( 'ttbm_location_name' ) );
			if ( '' === $info['location'] && method_exists( 'TTBM_Function', 'get_country' ) ) {
				$info['location'] = trim( (string) TTBM_Function::get_country( $tour_id ) );
			}
			if ( '' === $info['location'] && class_exists( 'Plan_A_Izleti_Shortcode' ) && method_exists( 'Plan_A_Izleti_Shortcode', 'get_country' ) ) {
				$country          = Plan_A_Izleti_Shortcode::get_country( $tour_id, $tour_id ); // isti izvor kao na karticama izleta
				$info['location'] = trim( (string) ( $country['value'] ?? '' ) );
			}
			foreach ( array( 'ttbm_country_name', 'ttbm_full_location_name' ) as $key ) {
				if ( '' === $info['location'] ) {
					$info['location'] = trim( (string) $get( $key ) );
				}
			}
		}

		$start             = self::date_parts( (string) ( $cart_item['ttbm_date'] ?? '' ) );
		$end               = self::date_parts( (string) ( $cart_item['ttbm_end_date'] ?? '' ) );
		$info['date']      = $start['date'];
		$info['time']      = $start['time'];
		$info['end_date']  = $end['date'];
		$info['end_time']  = $end['time'];

		$hotel = $cart_item['ttbm_hotel_info'] ?? array();
		if ( is_array( $hotel ) && ! empty( $hotel ) ) {
			$info['hotel'] = array(
				'name'     => ! empty( $hotel['hotel_id'] ) ? get_the_title( (int) $hotel['hotel_id'] ) : '',
				'checkin'  => (string) ( $hotel['ttbm_checkin_date'] ?? '' ),
				'checkout' => (string) ( $hotel['ttbm_checkout_date'] ?? '' ),
				'days'     => (int) ( $hotel['ttbm_hotel_num_of_day'] ?? 0 ),
			);
		}

		$days = $info['hotel'] ? max( 1, $info['hotel']['days'] ) : 1;
		foreach ( (array) ( $cart_item['ttbm_ticket_info'] ?? array() ) as $ticket ) {
			if ( ! is_array( $ticket ) ) {
				continue;
			}
			$qty               = (int) ( $ticket['ticket_qty'] ?? 0 );
			$price             = (float) ( $ticket['ticket_price'] ?? 0 );
			$info['tickets'][] = array(
				'name'  => (string) ( $ticket['ticket_name'] ?? '' ),
				'qty'   => $qty,
				'price' => self::price( $tour_id, $price ),
				'total' => self::price( $tour_id, $price * $qty * $days ),
				'days'  => $info['hotel'] ? $days : 0,
			);
			$info['people']   += $qty;
		}
		foreach ( (array) ( $cart_item['ttbm_extra_service_info'] ?? array() ) as $service ) {
			if ( ! is_array( $service ) ) {
				continue;
			}
			$qty                = (int) ( $service['service_qty'] ?? 0 );
			$price              = (float) ( $service['service_price'] ?? 0 );
			$info['services'][] = array(
				'name'  => (string) ( $service['service_name'] ?? '' ),
				'qty'   => $qty,
				'price' => self::price( $tour_id, $price ),
				'total' => self::price( $tour_id, $price * $qty ),
			);
		}
		return $info;
	}

	/**
	 * Ostali podaci stavke (varijacije i podaci drugih dodataka), kao
	 * wc_get_formatted_cart_item_data(). Blok "Booking Details" WpTravellyja se
	 * izostavlja kad su njegovi podaci prikazani strukturirano ($structured).
	 */
	public static function item_data( array $cart_item, bool $structured ): string {
		$item_data = array();
		if ( $cart_item['data']->is_type( 'variation' ) && is_array( $cart_item['variation'] ) ) {
			foreach ( $cart_item['variation'] as $name => $value ) {
				$taxonomy = wc_attribute_taxonomy_name( str_replace( 'attribute_pa_', '', urldecode( $name ) ) );
				if ( taxonomy_exists( $taxonomy ) ) {
					$term  = get_term_by( 'slug', $value, $taxonomy );
					$value = ! is_wp_error( $term ) && $term ? $term->name : $value;
					$label = wc_attribute_label( $taxonomy );
				} else {
					$value = apply_filters( 'woocommerce_variation_option_name', $value, null, $taxonomy, $cart_item['data'] );
					$label = wc_attribute_label( str_replace( 'attribute_', '', $name ), $cart_item['data'] );
				}
				if ( '' === $value || wc_is_attribute_in_product_name( $value, $cart_item['data']->get_name() ) ) {
					continue;
				}
				$item_data[] = array(
					'key'   => $label,
					'value' => $value,
				);
			}
		}

		$item_data = (array) apply_filters( 'woocommerce_get_item_data', $item_data, $cart_item );

		if ( $structured ) {
			$booking   = trim( __( 'Booking Details ', 'tour-booking-manager' ) );
			$item_data = array_filter(
				$item_data,
				static function ( $data ) use ( $booking ) {
					$key = trim( wp_strip_all_tags( (string) ( $data['key'] ?? '' ) ) );
					return $key !== $booking && 'Booking Details' !== $key;
				}
			);
		}

		foreach ( $item_data as $key => $data ) {
			$shown = ! empty( $data['display'] ) ? $data['display'] : ( $data['value'] ?? '' );
			// Prazna oznaka (npr. "Booking Details" koju WpTravelly dodaje i stavkama koje nisu izleti).
			if ( ! empty( $data['hidden'] ) || '' === trim( wp_strip_all_tags( (string) $shown ) ) ) {
				unset( $item_data[ $key ] );
				continue;
			}
			$item_data[ $key ]['key']     = ! empty( $data['key'] ) ? $data['key'] : ( $data['name'] ?? '' );
			$item_data[ $key ]['display'] = ! empty( $data['display'] ) ? $data['display'] : ( $data['value'] ?? '' );
		}
		if ( ! $item_data ) {
			return '';
		}
		ob_start();
		wc_get_template( 'cart/cart-item-data.php', array( 'item_data' => $item_data ) );
		return (string) ob_get_clean();
	}

	/**
	 * Izlaz kuka WpTravellyja za dodatke (npr. podaci o sudionicima).
	 */
	public static function tour_hooks( array $cart_item, int $tour_id ): string {
		ob_start();
		do_action( 'ttbm_before_cart_item_display', $cart_item, $tour_id );
		do_action( 'ttbm_show_cart_item', $cart_item, $tour_id );
		$html = trim( (string) ob_get_clean() );
		return '' !== $html ? '<div class="paka-addons">' . $html . '</div>' : '';
	}

	/**
	 * Mjesto, datum, vrijeme (i hotel) izleta kao popis s ikonama.
	 */
	public static function meta_list( array $tour, bool $people ): string {
		$rows = array();
		if ( '' !== $tour['location'] ) {
			$rows[] = array( 'pin', __( 'Mjesto', 'plan-a-kosarica' ), $tour['location'] );
		}
		if ( '' !== $tour['date'] ) {
			$text = $tour['date'];
			if ( '' !== $tour['end_date'] && $tour['end_date'] !== $tour['date'] ) {
				$text .= ' – ' . $tour['end_date'];
			}
			$rows[] = array( 'calendar', __( 'Datum', 'plan-a-kosarica' ), $text );
		}
		if ( '' !== $tour['time'] ) {
			$text   = $tour['time'] . ( '' !== $tour['end_time'] && $tour['end_time'] !== $tour['time'] && ( '' === $tour['end_date'] || $tour['end_date'] === $tour['date'] ) ? ' – ' . $tour['end_time'] : '' );
			$rows[] = array( 'clock', __( 'Vrijeme', 'plan-a-kosarica' ), $text );
		}
		if ( $tour['hotel'] ) {
			if ( '' !== $tour['hotel']['name'] ) {
				$rows[] = array( 'bed', __( 'Smještaj', 'plan-a-kosarica' ), $tour['hotel']['name'] );
			}
			$dates = trim( $tour['hotel']['checkin'] . ' – ' . $tour['hotel']['checkout'], ' –' );
			if ( '' !== $dates ) {
				/* translators: %d: broj dana */
				$rows[] = array( 'calendar', __( 'Boravak', 'plan-a-kosarica' ), $dates . ( $tour['hotel']['days'] ? ' (' . sprintf( _n( '%d dan', '%d dana', $tour['hotel']['days'], 'plan-a-kosarica' ), $tour['hotel']['days'] ) . ')' : '' ) );
			}
		}
		if ( $people && $tour['people'] > 0 ) {
			$rows[] = array( 'people', __( 'Sudionici', 'plan-a-kosarica' ), self::people( $tour['people'] ) );
		}
		if ( ! $rows ) {
			return '';
		}
		$html = '<ul class="paka-meta">';
		foreach ( $rows as $row ) {
			$html .= '<li>' . self::icon( $row[0] ) . '<span class="screen-reader-text">' . esc_html( $row[1] ) . ': </span><span>' . esc_html( $row[2] ) . '</span></li>';
		}
		return $html . '</ul>';
	}

	public static function people( int $count ): string {
		/* translators: %d: broj sudionika */
		return sprintf( _n( '%d sudionik', '%d sudionika', $count, 'plan-a-kosarica' ), $count );
	}

	/**
	 * Retci sažetka za stavku: "2 × Odrasli" … iznos.
	 *
	 * @return array<int, array{0: string, 1: string}>
	 */
	public static function summary_lines( array $cart_item, string $cart_item_key ): array {
		return self::lines( $cart_item, $cart_item_key );
	}

	/**
	 * Naziv stavke kako ga prikazuje košarica (filtar woocommerce_cart_item_name).
	 */
	public static function item_name( array $cart_item, string $cart_item_key ): string {
		$product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
		return wp_strip_all_tags( apply_filters( 'woocommerce_cart_item_name', $product->get_name(), $cart_item, $cart_item_key ) );
	}

	private static function lines( array $cart_item, string $cart_item_key ): array {
		$tour  = self::tour( $cart_item );
		$lines = array();
		$product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
		$name    = wp_strip_all_tags( apply_filters( 'woocommerce_cart_item_name', $product->get_name(), $cart_item, $cart_item_key ) );
		if ( $tour['id'] && $tour['tickets'] ) {
			$title = $name;
			foreach ( $tour['tickets'] as $ticket ) {
				$label   = $ticket['qty'] . ' × ' . ( '' !== $ticket['name'] ? $ticket['name'] : $title ) . ' × ' . wp_strip_all_tags( $ticket['price'] ) . ( $ticket['days'] ? ' × ' . $ticket['days'] : '' );
				$lines[] = array( $label, $ticket['total'] );
			}
			foreach ( $tour['services'] as $service ) {
				$lines[] = array( $service['qty'] . ' × ' . $service['name'] . ' × ' . wp_strip_all_tags( $service['price'] ), $service['total'] );
			}
			return $lines;
		}
		$lines[] = array( $cart_item['quantity'] . ' × ' . $name, apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $product, $cart_item['quantity'] ), $cart_item, $cart_item_key ) );
		return $lines;
	}

	/**
	 * Ima li u zbroju još nešto osim stavki (kupon, naknada, porez, dostava)?
	 * Tada se prikazuje i međuzbroj.
	 */
	public static function has_adjustments(): bool {
		$cart = WC()->cart;
		return (bool) $cart->get_coupons() || (bool) $cart->get_fees() || ( wc_tax_enabled() && ! $cart->display_prices_including_tax() && $cart->get_taxes_total() > 0 ) || $cart->needs_shipping();
	}

	/**
	 * Slika izleta za karticu (WpTravelly košarici daje sliku kao pozadinu preko JS-a).
	 */
	public static function tour_image( int $tour_id, string $fallback ): string {
		$image_id = (int) get_post_thumbnail_id( $tour_id );
		if ( ! $image_id ) {
			$image_id = (int) get_post_meta( $tour_id, 'ttbm_list_thumbnail', true );
		}
		if ( $image_id ) {
			$html = wp_get_attachment_image( $image_id, 'medium_large', false, array( 'class' => 'paka-item__img', 'alt' => '', 'sizes' => '(max-width: 600px) 40vw, 240px' ) );
			if ( $html ) {
				return $html;
			}
		}
		return $fallback;
	}

	public static function icon( string $name ): string {
		$paths = array(
			'pin'      => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
			'calendar' => '<rect x="3" y="4.5" width="18" height="16" rx="2"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/>',
			'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
			'people'   => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M21.5 20a6.5 6.5 0 0 0-4-6"/>',
			'bed'      => '<path d="M3 18V7M3 13h18v5M21 13v-1a3 3 0 0 0-3-3h-7v4"/><circle cx="7" cy="10.5" r="1.5"/>',
			'trash'    => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
			'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
			'back'     => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
			'tag'      => '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="8.5" r="1.5"/>',
			'info'     => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.5"/>',
		);
		if ( 'whatsapp' === $name ) {
			return '<svg class="paka-icon paka-icon--wa" viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true" focusable="false"><path d="M17.47 14.38c-.3-.15-1.75-.86-2.02-.96-.27-.1-.47-.15-.67.15-.2.3-.77.96-.94 1.16-.17.2-.35.22-.64.07-.3-.15-1.25-.46-2.38-1.47-.88-.79-1.47-1.76-1.65-2.06-.17-.3-.02-.46.13-.6.13-.14.3-.35.45-.52.15-.18.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.61-.92-2.2-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.06 2.88 1.21 3.08.15.2 2.1 3.2 5.08 4.48.71.31 1.26.49 1.69.63.71.22 1.36.19 1.87.12.57-.09 1.75-.72 2-1.41.25-.69.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35z"/><path d="M12.04 2C6.5 2 2 6.48 2 12c0 1.77.46 3.5 1.34 5.02L2 22l5.12-1.34A10.03 10.03 0 0 0 12.04 22C17.56 22 22 17.52 22 12S17.56 2 12.04 2zm0 18.3c-1.5 0-2.97-.4-4.25-1.16l-.3-.18-3.04.8.81-2.96-.2-.31A8.26 8.26 0 0 1 3.77 12c0-4.56 3.71-8.27 8.27-8.27 4.56 0 8.25 3.71 8.25 8.27 0 4.57-3.7 8.3-8.25 8.3z"/></svg>';
		}
		return '<svg class="paka-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( $paths[ $name ] ?? '' ) . '</svg>';
	}
}
