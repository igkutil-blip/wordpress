<?php
/**
 * Stranica izleta (kartica „Tko dolazi” i WhatsApp grupa), košarica (spol i godina rođenja za svaku osobu)
 * i stranica „Hvala” (WhatsApp grupa). Radi samo za izlete s kvačicom u okviru „Tinder – Tko dolazi”.
 *
 * @package Plan_A_Tinder
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Tinder_Front {

	public static function init() {
		add_action( 'ttbm_smart_registration_controls', array( __CLASS__, 'card' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 50 );
		add_action( 'woocommerce_after_checkout_billing_form', array( __CLASS__, 'checkout_box' ), 30 );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate' ), 20, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'line_item' ), 20, 4 );
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'thankyou' ), 5 );
	}

	public static function assets() {
		$tour = is_singular() ? (int) get_queried_object_id() : 0;
		if ( Plan_A_Tinder::is_tour( $tour ) || ( function_exists( 'is_checkout' ) && is_checkout() && self::cart_items() ) || ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) ) {
			wp_enqueue_style( 'plan-a-tinder', PLAN_A_TINDER_URL . 'assets/tinder.css', array(), PLAN_A_TINDER_VERSION );
		}
		if ( Plan_A_Tinder::is_tour( $tour ) && Plan_A_Tinder::seed_active( $tour ) ) {
			wp_enqueue_script( 'plan-a-tinder', PLAN_A_TINDER_URL . 'assets/tinder.js', array(), PLAN_A_TINDER_VERSION, true );
		}
	}

	private static function wa_button( string $text, int $tour ): string {
		$url = Plan_A_Tinder::wa_url( $tour );
		if ( '' === $url ) {
			return '';
		}
		return '<a class="pat-wa" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">'
			. '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.2-.4.7-1.4.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.9 11.9 0 0 0 4.6 4c1.7.7 2.4.8 3.2.7a2.8 2.8 0 0 0 1.8-1.3 2.3 2.3 0 0 0 .2-1.3c-.1-.1-.2-.2-.5-.3z"/></svg>'
			. esc_html( $text ) . '</a>';
	}

	/** Dob „(32 – 45 god.)” – samo kad je u skupini dovoljno osoba s upisanom godinom (inače se iz raspona vide nečije godine). */
	private static function range( array $g, int $min ): string {
		if ( $g['ages'] < max( 1, $min ) || ! $g['min'] ) {
			return '';
		}
		return '(' . ( $g['min'] === $g['max'] ? $g['min'] : $g['min'] . ' – ' . $g['max'] ) . ' god.)';
	}

	private static function plural( int $n, array $f ): string {
		$m10  = $n % 10;
		$m100 = $n % 100;
		if ( 1 === $m10 && 11 !== $m100 ) {
			return $f[0];
		}
		return ( $m10 >= 2 && $m10 <= 4 && ( $m100 < 12 || $m100 > 14 ) ) ? $f[1] : $f[2];
	}

	/** Kartica na stranici izleta (iznad odabira termina u kućici za prijavu). */
	public static function card( $tour = 0 ) {
		$tour = (int) ( $tour ?: get_the_ID() );
		if ( ! Plan_A_Tinder::is_tour( $tour ) ) {
			return;
		}
		$s     = Plan_A_Tinder::settings();
		$st    = Plan_A_Tinder::shown( $tour );
		$seats = Plan_A_Tinder::seats( $tour );
		$m     = (int) $st['m']['n'];
		$z     = (int) $st['z']['n'];
		$all   = (int) $st['total'];
		if ( $seats && $all >= $seats && $seats > (int) $st['real'] ) {
			$all = $seats - 1; // poticaj ne smije prikazati „popunjeno” dok ima pravih slobodnih mjesta
		}
		$base  = max( $seats, $all, 1 );
		$minus = Plan_A_Tinder::seed_active( $tour ) ? (int) $st['total'] - (int) $st['real'] : 0; // poticaj: toliko se oduzme od prikaza slobodnih mjesta
		echo '<div class="pat-card" title="' . esc_attr__( 'Prikazujemo samo broj prijavljenih i raspon godina, bez imena i ostalih podataka.', 'plan-a-tinder' ) . '" aria-label="' . esc_attr( $s['title'] ) . '"' . ( $minus > 0 ? ' data-pat-minus="' . (int) $minus . '"' : '' ) . '>';
		echo '<p class="pat-card__h">' . esc_html( $s['title'] ) . '</p>';
		echo '<p class="pat-card__total">' . ( $all ? esc_html( $all . ' ' . self::plural( $all, array( 'prijavljena osoba', 'prijavljene osobe', 'prijavljenih' ) ) ) : esc_html__( 'Budi prva prijava', 'plan-a-tinder' ) ) . '</p>';
		echo '<div class="pat-card__g">';
		foreach ( array( array( 'm', '♂', $m, self::plural( $m, array( 'muškarac', 'muškarca', 'muškaraca' ) ) ), array( 'z', '♀', $z, self::plural( $z, array( 'žena', 'žene', 'žena' ) ) ) ) as $b ) {
			$range = self::range( $st[ $b[0] ], (int) $s['min'] );
			echo '<div class="pat-box pat-box--' . esc_attr( $b[0] ) . '"><span class="pat-box__ic" aria-hidden="true">' . esc_html( $b[1] ) . '</span>'
				. '<span class="pat-box__n">' . (int) $b[2] . '</span><span class="pat-box__l">' . esc_html( $b[3] ) . '</span>'
				. ( '' !== $range ? '<span class="pat-box__a">' . esc_html( $range ) . '</span>' : '' ) . '</div>';
		}
		echo '</div>';
		if ( $seats ) {
			$free = max( 0, $seats - $all );
			if ( class_exists( 'TTBM_Function' ) && method_exists( 'TTBM_Function', 'get_total_available' ) ) {
				// Isti broj kao „slobodna mjesta” izleta (stvarna mjesta − poticaj), najmanje 1 dok ima stvarnih.
				$real = max( 0, (int) TTBM_Function::get_total_available( $tour ) );
				$free = $real ? max( 1, $real - $minus ) : 0;
			}
			echo '<div class="pat-bar" aria-hidden="true"><i class="pat-bar__m" style="width:' . esc_attr( (string) round( 100 * $m / $base, 1 ) ) . '%"></i><i class="pat-bar__z" style="width:' . esc_attr( (string) round( 100 * $z / $base, 1 ) ) . '%"></i></div>';
			echo '<p class="pat-free"><span>' . esc_html( max( 0, $seats - $free ) . ' od ' . $seats . ' mjesta' ) . '</span><span>' . ( $free ? 'slobodno još <b>' . (int) $free . '</b>' : '<b>popunjeno</b>' ) . '</span></p>';
		}
		echo self::wa_button( 'WhatsApp grupa Plana A', $tour ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p class="pat-note">' . esc_html__( 'Prikazujemo samo broj prijavljenih i raspon godina, bez imena i ostalih podataka. Brojke se mijenjaju same sa svakom prijavom.', 'plan-a-tinder' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Stavke košarice odabranih izleta s brojem osoba i rednim brojem prve osobe
	 * (isti redoslijed kao „Ostali sudionici” iz Plan A članstva: prva stavka termina počinje kupcem).
	 */
	private static function cart_items(): array {
		$out  = array();
		$last = array();
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $out;
		}
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			$tour = (int) ( $item['ttbm_id'] ?? 0 );
			if ( ! $tour ) {
				continue;
			}
			$people = 0;
			foreach ( (array) ( $item['ttbm_ticket_info'] ?? array() ) as $t ) {
				$people += (int) ( $t['ticket_qty'] ?? 0 );
			}
			$people = max( 1, $people ?: (int) ( $item['quantity'] ?? 1 ) );
			$ts     = strtotime( (string) ( $item['ttbm_date'] ?? '' ) );
			$group  = $tour . '|' . ( $ts ? gmdate( 'Y-m-d', $ts ) : '' );
			$first  = ! isset( $last[ $group ] );
			$from   = $first ? 1 : $last[ $group ] + 1;
			$last[ $group ] = $from + $people - 1;
			if ( Plan_A_Tinder::is_tour( $tour ) ) {
				$out[ $key ] = array(
					'name'   => get_the_title( $tour ),
					'people' => min( $people, 40 ),
					'from'   => $from,
					'first'  => $first,
					'year'   => $ts ? (int) gmdate( 'Y', $ts ) : (int) current_time( 'Y' ),
				);
			}
		}
		return $out;
	}

	private static function posted(): array {
		$raw = isset( $_POST['pat'] ) && is_array( $_POST['pat'] ) ? wp_unslash( $_POST['pat'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- čisti se ispod; WooCommerce provjerava nonce blagajne.
		$out = array();
		foreach ( $raw as $key => $people ) {
			foreach ( (array) $people as $i => $p ) {
				$spol = sanitize_key( (string) ( $p['spol'] ?? '' ) );
				$out[ sanitize_key( (string) $key ) ][ (int) $i ] = array(
					'spol' => in_array( $spol, array( 'm', 'z' ), true ) ? $spol : '',
					'god'  => (int) preg_replace( '/\D/', '', (string) ( $p['god'] ?? '' ) ),
				);
			}
		}
		return $out;
	}

	public static function checkout_box() {
		$items = self::cart_items();
		if ( ! $items ) {
			return;
		}
		$posted = self::posted();
		echo '<div class="pat-checkout">';
		echo '<h3>' . esc_html__( 'Za brojač „Tko dolazi”', 'plan-a-tinder' ) . '</h3>';
		echo '<p class="pat-muted">' . esc_html__( 'Na stranici izleta prikazujemo samo koliko je prijavljenih muškaraca i žena i raspon godina, bez imena. Za svaku osobu odaberi spol (godinu rođenja uzimamo iz pristupnice člana).', 'plan-a-tinder' ) . '</p>';
		foreach ( $items as $key => $it ) {
			if ( count( $items ) > 1 ) {
				echo '<h4 class="pat-checkout__tour">' . esc_html( $it['name'] ) . '</h4>';
			}
			for ( $i = 0; $i < $it['people']; $i++ ) {
				$no   = $it['from'] + $i;
				$name = 'pat[' . $key . '][' . $i . ']';
				$v    = $posted[ $key ][ $i ] ?? array( 'spol' => '', 'god' => 0 );
				$who  = 1 === $no && $it['first'] ? __( '1. osoba (ti)', 'plan-a-tinder' ) : sprintf( /* translators: %d: redni broj osobe */ __( '%d. osoba', 'plan-a-tinder' ), $no );
				echo '<fieldset class="pat-person"><legend>' . esc_html( $who ) . '</legend>';
				echo '<span class="pat-sex" role="radiogroup" aria-label="' . esc_attr__( 'Spol', 'plan-a-tinder' ) . '">';
				foreach ( array( 'm' => 'Muško', 'z' => 'Žensko' ) as $val => $label ) {
					echo '<label><input type="radio" name="' . esc_attr( $name . '[spol]' ) . '" value="' . esc_attr( $val ) . '"' . checked( $v['spol'], $val, false ) . ' required> ' . esc_html( $label ) . '</label>';
				}
				echo '</span>';
				echo '</fieldset>';
			}
		}
		echo '</div>';
	}

	public static function validate( $data, $errors ) {
		$items  = self::cart_items();
		$posted = self::posted();
		foreach ( $items as $key => $it ) {
			for ( $i = 0; $i < $it['people']; $i++ ) {
				$v   = $posted[ $key ][ $i ] ?? array( 'spol' => '', 'god' => 0 );
				$who = sprintf( '%d. osoba: ', $it['from'] + $i );
				if ( '' === $v['spol'] ) {
					$errors->add( 'pat', $who . __( 'odaberi spol (za brojač na stranici izleta).', 'plan-a-tinder' ) );
				}
			}
		}
	}

	public static function line_item( $item, $cart_key, $values, $order ) {
		$items = self::cart_items();
		if ( ! isset( $items[ $cart_key ] ) ) {
			return;
		}
		$posted = self::posted();
		$names  = (array) $item->get_meta( '_pac_osobe' ); // Plan A članstvo upisuje osobe ranije (prioritet 10)
		$list   = array();
		for ( $i = 0; $i < $items[ $cart_key ]['people']; $i++ ) {
			$v        = $posted[ $cart_key ][ $i ] ?? array( 'spol' => '', 'god' => 0 );
			$v['god'] = Plan_A_Tinder::birth_year( (array) ( $names[ $i ] ?? array() ) );
			$list[]   = $v;
		}
		$item->add_meta_data( '_pat_osobe', $list, true );
	}

	/** Stranica „Hvala”: gumb za WhatsApp grupu izleta. */
	public static function thankyou( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		foreach ( $order->get_items() as $item ) {
			$tour = $item instanceof WC_Order_Item_Product ? (int) $item->get_meta( '_ttbm_id' ) : 0;
			if ( Plan_A_Tinder::is_tour( $tour ) && '' !== Plan_A_Tinder::wa_url( $tour ) ) {
				echo '<div class="pat-thanks"><p><strong>' . esc_html__( 'Pridruži se WhatsApp grupi Plan A', 'plan-a-tinder' ) . '</strong><br>' . esc_html__( 'U grupi objavljujemo izlete i druženja i dogovaramo sve prije polaska.', 'plan-a-tinder' ) . '</p>' . self::wa_button( 'WhatsApp grupa Plana A', $tour ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
				return;
			}
		}
	}
}
