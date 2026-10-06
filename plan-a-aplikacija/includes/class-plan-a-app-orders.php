<?php
/**
 * Rezervacije izleta napravljene iz instalirane aplikacije.
 *
 * Dok je stranica otvorena kao aplikacija, app.js postavlja kolačić sesije
 * `plan_a_app_src` (android|ios|other); u običnom pregledniku ga briše.
 * Kad kupac klikne konačni gumb za plaćanje i WooCommerce stvori narudžbu,
 * narudžbi se dodaje meta `_plan_a_app_source` s platformom. Rezervacija
 * izleta je narudžba s barem jednom stavkom koju je WpTravelly označio s
 * `_ttbm_id`.
 *
 * @package Plan_A_Aplikacija
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_App_Orders {

	const COOKIE    = 'plan_a_app_src';
	const META      = '_plan_a_app_source';
	const CONFIRMED = array( 'processing', 'completed', 'on-hold' );

	public static function init() {
		// Klasično plaćanje (narudžba stvorena klikom na gumb za plaćanje).
		add_action( 'woocommerce_checkout_order_created', array( __CLASS__, 'mark' ) );
		// Blokovsko plaćanje (WooCommerce Blocks / Store API).
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'mark' ) );
		// Promjena statusa (npr. plaćeno) mijenja zbroj u statistici.
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'flush' ) );
		// Oznaka na stranici narudžbe u administraciji.
		add_action( 'woocommerce_admin_order_data_after_order_details', array( __CLASS__, 'admin_note' ) );
	}

	public static function flush() {
		delete_transient( 'plan_a_app_orders_30' );
	}

	private static function source_from_cookie(): string {
		$value = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		return in_array( $value, Plan_A_App_Stats::PLATFORMS, true ) ? $value : '';
	}

	/**
	 * @param WC_Order|int $order
	 */
	public static function mark( $order ) {
		if ( ! Plan_A_App_Settings::get( 'stats' ) || ( is_admin() && ! wp_doing_ajax() ) ) {
			return;
		}
		$order = is_numeric( $order ) && function_exists( 'wc_get_order' ) ? wc_get_order( $order ) : $order;
		if ( ! is_object( $order ) || ! method_exists( $order, 'update_meta_data' ) || $order->get_meta( self::META ) ) {
			return;
		}
		$source = self::source_from_cookie();
		if ( '' === $source ) {
			return;
		}
		$order->update_meta_data( self::META, $source );
		$order->save();
		self::flush();
		if ( self::has_tour( $order ) ) {
			Plan_A_App_Stats::increment( $source, 'booking' );
		}
	}

	public static function has_tour( $order ): bool {
		foreach ( $order->get_items() as $item ) {
			if ( $item->get_meta( '_ttbm_id' ) ) {
				return true;
			}
		}
		return false;
	}

	private static function tour_names( $order ): array {
		$names = array();
		foreach ( $order->get_items() as $item ) {
			$tour_id = (int) $item->get_meta( '_ttbm_id' );
			if ( $tour_id ) {
				$names[] = get_the_title( $tour_id ) ?: $item->get_name();
			}
		}
		return array_values( array_unique( $names ) );
	}

	public static function admin_note( $order ) {
		$source = is_object( $order ) ? (string) $order->get_meta( self::META ) : '';
		if ( '' === $source ) {
			return;
		}
		$labels = self::labels();
		echo '<p class="form-field form-field-wide"><strong>' . esc_html__( 'Izvor:', 'plan-a-aplikacija' ) . '</strong> '
			. esc_html( sprintf( /* translators: %s: platforma */ __( 'aplikacija Plan A (%s)', 'plan-a-aplikacija' ), $labels[ $source ] ?? $source ) ) . '</p>';
	}

	public static function labels(): array {
		return array(
			'android' => 'Android',
			'ios'     => 'iPhone',
			'other'   => __( 'Ostalo', 'plan-a-aplikacija' ),
		);
	}

	/**
	 * Rezervacije izleta u zadnjih $days dana: sve (cijela stranica) i one iz aplikacije.
	 * Rezultat se čuva 10 minuta da kartica ne opterećuje bazu.
	 */
	public static function summary( int $days = 30 ): array {
		$cache_key = 'plan_a_app_orders_' . $days;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$empty = array_fill_keys( array_merge( Plan_A_App_Stats::PLATFORMS, array( 'all' ) ), 0 );
		$out   = array(
			'site_confirmed' => 0,
			'app_placed'     => $empty,
			'app_confirmed'  => $empty,
			'app_revenue'    => $empty,
			'recent'         => array(),
			'available'      => function_exists( 'wc_get_orders' ),
		);
		if ( ! $out['available'] ) {
			return $out;
		}

		$orders = wc_get_orders(
			array(
				'type'         => 'shop_order',
				'status'       => array_keys( wc_get_order_statuses() ),
				'date_created' => '>=' . ( time() - $days * DAY_IN_SECONDS ),
				'limit'        => 2000,
				'orderby'      => 'date',
				'order'        => 'DESC',
			)
		);

		foreach ( (array) $orders as $order ) {
			if ( ! is_object( $order ) || ! self::has_tour( $order ) ) {
				continue;
			}
			$confirmed = in_array( $order->get_status(), self::CONFIRMED, true );
			if ( $confirmed ) {
				$out['site_confirmed']++;
			}
			$source = (string) $order->get_meta( self::META );
			if ( ! isset( $empty[ $source ] ) || 'all' === $source ) {
				continue;
			}
			foreach ( array( $source, 'all' ) as $key ) {
				$out['app_placed'][ $key ]++;
				if ( $confirmed ) {
					$out['app_confirmed'][ $key ]++;
					$out['app_revenue'][ $key ] += (float) $order->get_total();
				}
			}
			if ( count( $out['recent'] ) < 15 ) {
				$created         = $order->get_date_created();
				$out['recent'][] = array(
					'id'       => $order->get_id(),
					'number'   => $order->get_order_number(),
					'url'      => method_exists( $order, 'get_edit_order_url' ) ? $order->get_edit_order_url() : admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ),
					'date'     => $created ? $created->getTimestamp() : 0,
					'source'   => $source,
					'status'   => function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $order->get_status() ) : $order->get_status(),
					'total'    => (float) $order->get_total(),
					'currency' => $order->get_currency(),
					'tours'    => self::tour_names( $order ),
				);
			}
		}

		set_transient( $cache_key, $out, 10 * MINUTE_IN_SECONDS );
		return $out;
	}
}
