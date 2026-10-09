<?php
/**
 * Provjera članstva pri prijavi na izlet (WooCommerce narudžba).
 *
 * E-mail kupca uspoređuje se s potvrđenim pristupnicama. Ako ga nema, prijava se ne blokira:
 * na stranici "Hvala" i u e-mailu s uplatnicom kupac dobiva poveznicu na pristupnicu, a uz
 * narudžbu u administraciji piše "Nije član". Članstvo se ne otkriva dok se upisuje e-mail.
 *
 * @package Plan_A_Clanstvo
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Clanstvo_Woo {

	const META = '_pac_clan';

	public static function init() {
		if ( ! (int) Plan_A_Clanstvo_Data::value( 'provjera' ) ) {
			return;
		}
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'mark' ), 20, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'mark' ), 20, 1 );
		add_action( 'woocommerce_before_thankyou', array( __CLASS__, 'thankyou' ), 5 );
		add_action( 'woocommerce_email_order_meta', array( __CLASS__, 'email' ), 30, 4 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'admin_box' ) );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'column' ), 20 );
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'column' ), 20 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'column_value' ), 10, 2 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'column_value' ), 10, 2 );
	}

	/** Narudžbe na koje se provjera ne odnosi (npr. rezervacija jedrenja). */
	private static function skip( $order ): bool {
		return ! $order || $order->get_meta( '_paj_rez' );
	}

	public static function mark( $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		if ( self::skip( $order ) ) {
			return;
		}
		$order->update_meta_data( self::META, Plan_A_Clanstvo_Data::is_member( (string) $order->get_billing_email() ) ? 'da' : 'ne' );
		$order->save_meta_data();
	}

	private static function status( $order ): string {
		if ( self::skip( $order ) ) {
			return '';
		}
		$v = (string) $order->get_meta( self::META );
		if ( 'ne' === $v && Plan_A_Clanstvo_Data::is_member( (string) $order->get_billing_email() ) ) {
			// U međuvremenu ispunio pristupnicu.
			$order->update_meta_data( self::META, 'da' );
			$order->save_meta_data();
			return 'da';
		}
		return $v;
	}

	private static function notice(): string {
		$url = Plan_A_Clanstvo_Data::page_url();
		return '<div style="margin:0 0 18px;padding:14px 16px;border-left:4px solid #e8862a;border-radius:0 12px 12px 0;background:#fff4e8;color:#24323f;font-size:15px;line-height:1.5">'
			. '<strong>Uvjet za izlet je članstvo u udruzi Plan A.</strong> Na ovu e-mail adresu još nemamo potvrđenu pristupnicu. '
			. 'Ispuni je prije izleta (2 minute): <a href="' . esc_url( $url ) . '" style="color:#1a73b8;font-weight:bold">Pristupnica</a>.'
			. ' Ako si već član s drugom e-mail adresom, ne trebaš ništa raditi.</div>';
	}

	public static function thankyou( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order && 'ne' === self::status( $order ) ) {
			echo self::notice(); // phpcs:ignore WordPress.Security.EscapeOutput -- escapirano u notice().
		}
	}

	public static function email( $order, $sent_to_admin, $plain_text = false, $email = null ) {
		if ( $sent_to_admin || ! $order instanceof WC_Order || 'ne' !== self::status( $order ) ) {
			return;
		}
		if ( $plain_text ) {
			echo "\nUvjet za izlet je članstvo u udruzi Plan A. Ispuni pristupnicu: " . esc_url_raw( Plan_A_Clanstvo_Data::page_url() ) . "\n";
			return;
		}
		echo self::notice(); // phpcs:ignore WordPress.Security.EscapeOutput -- escapirano u notice().
	}

	public static function admin_box( $order ) {
		$v = self::status( $order );
		if ( '' === $v ) {
			return;
		}
		echo '<p><strong>Član Plan A:</strong> ' . ( 'da' === $v ? '<span style="color:#1e7d3a">da</span>' : '<span style="color:#b32d2e">nije pronađen (po e-mailu)</span>' ) . '</p>';
	}

	public static function column( $cols ) {
		$out = array();
		foreach ( $cols as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'order_status' === $k ) {
				$out['pac_clan'] = 'Član';
			}
		}
		if ( ! isset( $out['pac_clan'] ) ) {
			$out['pac_clan'] = 'Član';
		}
		return $out;
	}

	public static function column_value( $column, $order ) {
		if ( 'pac_clan' !== $column ) {
			return;
		}
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		$v     = $order ? self::status( $order ) : '';
		echo 'da' === $v ? '<span style="color:#1e7d3a">✔ da</span>' : ( 'ne' === $v ? '<span style="color:#b32d2e">✘ ne</span>' : '–' );
	}
}
