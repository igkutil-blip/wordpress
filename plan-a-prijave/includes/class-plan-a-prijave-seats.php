<?php
/**
 * Zajednička mjesta izleta: sve karte izleta (puna uplata, „Uplata prve rate” …) troše
 * ista mjesta. Broj mjesta je zbroj mjesta karata bez „prve rate”; karta za prvu ratu
 * nema svoja mjesta. Radi preko WpTravellyjevih filtara, pa vrijedi i s WpTravellyjem
 * i s našom kopijom.
 *
 * @package Plan_A_Prijave
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Prijave_Seats {

	private static $pool = array();

	public static function init() {
		add_filter( 'ttbm_get_total_seat_filter', array( __CLASS__, 'total_seat' ), 20, 2 );
		add_filter( 'ttbm_get_total_reserve_filter', array( __CLASS__, 'total_reserve' ), 20, 2 );
		add_filter( 'ttbm_ticket_capacity', array( __CLASS__, 'ticket_capacity' ), 20, 4 );
		add_filter( 'ttbm_total_available_qty', array( __CLASS__, 'total_available' ), 20, 3 );
		add_filter( 'ttbm_ticket_availability_info', array( __CLASS__, 'availability_info' ), 20, 2 );
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate' ), 95, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'script' ), 30 );
		// Gumb „Popunjeno” u popisu izleta: nema slobodnih mjesta, mjesta karata ostaju upisana.
		add_filter( 'ttbm_total_available_qty', array( __CLASS__, 'full_available' ), 99, 2 );
		add_filter( 'ttbm_ticket_capacity', array( __CLASS__, 'full_capacity' ), 99, 4 );
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'full_validate' ), 90, 2 );
		// Popis izleta (plan-a-izleti): oznaka „Popunjeno” i izlet na kraj popisa.
		add_filter( 'plan_a_izleti_sold_out', array( __CLASS__, 'full_list' ), 20, 2 );
	}

	public static function full_list( $sold_out, $id ) {
		return $sold_out || self::is_full( (int) $id );
	}

	const FULL = '_pap_full';

	/** Je li izlet ručno zatvoren gumbom „Popunjeno”. */
	public static function is_full( int $tour_id ): bool {
		return $tour_id > 0 && (bool) get_post_meta( $tour_id, self::FULL, true );
	}

	public static function full_available( $total, $tour_id ) {
		return self::is_full( (int) $tour_id ) ? 0 : $total;
	}

	/** Kapacitet karte = već prodano (slobodno 0). */
	public static function full_capacity( $cap, $tour_id, $date = '', $name = '' ) {
		return self::is_full( (int) $tour_id ) ? (int) TTBM_Function::get_total_sold( (int) $tour_id, $date, (string) $name ) : $cap;
	}

	public static function full_validate( $passed, $product_id ) {
		$tour_id = isset( $_POST['ttbm_id'] ) ? absint( wp_unslash( $_POST['ttbm_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $tour_id ) {
			$tour_id = absint( get_post_meta( (int) $product_id, 'link_ttbm_id', true ) );
		}
		if ( $passed && self::is_full( $tour_id ) ) {
			wc_add_notice( 'Izlet je popunjen – prijave više nisu moguće.', 'error' );
			return false;
		}
		return $passed;
	}

	/** Karta za prvu ratu (dogovor: naziv sadrži „prve rate”). */
	public static function is_rate( string $name ): bool {
		return false !== mb_stripos( $name, 'prve rate' );
	}

	/**
	 * Zajednička mjesta izleta ili null ako izlet ima samo jednu kartu (ništa se ne mijenja).
	 *
	 * @return array{qty:int,resv:int,resv_of:array<string,int>}|null
	 */
	public static function pool( int $tour_id ): ?array {
		if ( array_key_exists( $tour_id, self::$pool ) ) {
			return self::$pool[ $tour_id ];
		}
		$rows = get_post_meta( $tour_id, 'ttbm_ticket_type', true );
		$rows = is_array( $rows ) ? array_values( array_filter( $rows, 'is_array' ) ) : array();
		if ( count( $rows ) < 2 ) {
			return self::$pool[ $tour_id ] = null;
		}
		$qty  = 0;
		$resv = 0;
		$of   = array();
		foreach ( $rows as $t ) {
			$name        = (string) ( $t['ticket_type_name'] ?? '' );
			$r           = max( 0, (int) ( $t['ticket_type_resv_qty'] ?? 0 ) );
			$of[ $name ] = self::is_rate( $name ) ? 0 : $r;
			if ( ! self::is_rate( $name ) ) {
				$qty  += max( 0, (int) ( $t['ticket_type_qty'] ?? 0 ) );
				$resv += $r;
			}
		}
		if ( $qty <= 0 ) { // samo karte za prvu ratu – ne diramo
			return self::$pool[ $tour_id ] = null;
		}
		return self::$pool[ $tour_id ] = array(
			'qty'     => $qty,
			'resv'    => $resv,
			'resv_of' => $of,
		);
	}

	/** Prodano na terminu, sve karte zajedno. */
	private static function sold_all( int $tour_id, $date ): int {
		return (int) TTBM_Function::get_total_sold( $tour_id, $date, '' );
	}

	/** Slobodnih zajedničkih mjesta na terminu. */
	public static function free( int $tour_id, $date = '' ): int {
		$p = self::pool( $tour_id );
		return $p ? max( 0, $p['qty'] - $p['resv'] - self::sold_all( $tour_id, $date ) ) : 0;
	}

	public static function total_seat( $total, $tour_id ) {
		$p = self::pool( (int) $tour_id );
		return $p ? $p['qty'] : $total;
	}

	public static function total_reserve( $reserve, $tour_id ) {
		$p = self::pool( (int) $tour_id );
		return $p ? $p['resv'] : $reserve;
	}

	/** Kapacitet karte tako da njezino „slobodno” bude jednako zajedničkim slobodnim mjestima. */
	public static function ticket_capacity( $cap, $tour_id, $date = '', $name = '' ) {
		$tour_id = (int) $tour_id;
		$p       = self::pool( $tour_id );
		if ( ! $p ) {
			return $cap;
		}
		$own = (int) TTBM_Function::get_total_sold( $tour_id, $date, (string) $name );
		return self::free( $tour_id, $date ) + $own + (int) ( $p['resv_of'][ (string) $name ] ?? 0 );
	}

	public static function total_available( $total, $tour_id, $date = '' ) {
		return self::pool( (int) $tour_id ) ? self::free( (int) $tour_id, $date ) : $total;
	}

	public static function availability_info( $info, $tour_id ) {
		if ( self::pool( (int) $tour_id ) && is_array( $info ) ) {
			foreach ( $info as $k => $row ) {
				$info[ $k ]['shared_capacity_enabled'] = true;
			}
		}
		return $info;
	}

	/** Zbroj svih karata u jednoj prijavi (i onih već u košarici) ne smije prijeći slobodna mjesta. */
	public static function validate( $passed, $product_id ) {
		// phpcs:disable WordPress.Security.NonceVerification -- WpTravelly je već provjerio nonce (ttbm_form_nonce) prije nas.
		if ( ! $passed || empty( $_POST['ticket_qty'] ) || ! class_exists( 'TTBM_Function' ) ) {
			return $passed;
		}
		$tour_id = isset( $_POST['ttbm_id'] ) ? absint( wp_unslash( $_POST['ttbm_id'] ) ) : 0;
		if ( ! $tour_id ) {
			$tour_id = absint( get_post_meta( (int) $product_id, 'link_ttbm_id', true ) );
		}
		if ( ! $tour_id || Plan_A_Prijave_Tour::CPT !== get_post_type( $tour_id ) || ! self::pool( $tour_id ) ) {
			return $passed;
		}
		$date = isset( $_POST['ttbm_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['ttbm_start_date'] ) ) : '';
		$want = array_sum( array_map( 'absint', (array) wp_unslash( $_POST['ticket_qty'] ) ) );
		// phpcs:enable
		if ( $want < 1 ) {
			return $passed;
		}
		$in_cart = 0;
		if ( function_exists( 'WC' ) && WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $item ) {
				if ( (int) ( $item['ttbm_id'] ?? 0 ) !== $tour_id || substr( (string) ( $item['ttbm_date'] ?? '' ), 0, 10 ) !== substr( $date, 0, 10 ) ) {
					continue;
				}
				foreach ( (array) ( $item['ttbm_ticket_info'] ?? array() ) as $t ) {
					$in_cart += (int) ( $t['ticket_qty'] ?? 0 );
				}
			}
		}
		$free = self::free( $tour_id, $date ) - $in_cart;
		if ( $want > $free ) {
			wc_add_notice(
				$free > 0
					? sprintf( 'Na izletu je slobodno još %d mjesta (zajedno za sve vrste uplate). Smanji broj osoba.', $free )
					: 'Na izletu više nema slobodnih mjesta.',
				'error'
			);
			return false;
		}
		return $passed;
	}

	/** Na stranici izleta: odabir na svim kartama zajedno ne može prijeći slobodna mjesta. */
	public static function script() {
		if ( ! is_singular( Plan_A_Prijave_Tour::CPT ) || ! self::pool( (int) get_queried_object_id() ) ) {
			return;
		}
		$js = <<<'JS'
jQuery(function ($) {
	var sel = '.ttbm_smart_ticket_card input.formControl[data-price], .ttbm_ticket_row input.formControl[data-price]';
	function sync() {
		var pool = parseInt($('#ttbm_total_available').last().text(), 10);
		if (isNaN(pool)) { return; }
		var ins = $(sel);
		ins.each(function () {
			var me = this, others = 0;
			ins.each(function () { if (this !== me) { others += parseInt(this.value, 10) || 0; } });
			var max = Math.max(0, pool - others), v = parseInt(me.value, 10) || 0;
			me.setAttribute('max', max);
			if (v > max) { me.value = max; $(me).trigger('change'); }
			$(me).closest('.qtyIncDec').find('.incQty').toggleClass('mpDisabled', (parseInt(me.value, 10) || 0) >= max);
		});
	}
	$(document).on('change input', sel, function () { setTimeout(sync, 0); });
	$(document).on('click', '.incQty, .decQty', function () { setTimeout(sync, 0); });
	$(document).ajaxComplete(function () { setTimeout(sync, 0); });
	sync();
});
JS;
		wp_register_script( 'pap-seats', '', array( 'jquery' ), PLAN_A_PRIJAVE_VERSION, true );
		wp_enqueue_script( 'pap-seats' );
		wp_add_inline_script( 'pap-seats', $js );
	}
}
