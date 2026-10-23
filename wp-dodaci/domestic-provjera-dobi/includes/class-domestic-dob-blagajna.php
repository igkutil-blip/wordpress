<?php
/**
 * Blagajna: dok punoljetnost nije potvrđena, umjesto obrasca prikazuje se korak provjere, a narudžba se odbija i na poslužitelju
 * (klasična blagajna i blok blagajna). Uz narudžbu se bilježi samo „provjereno”, vrijeme i okruženje. Nakon narudžbe se potvrda briše –
 * svaka nova narudžba traži novu provjeru.
 */

defined( 'ABSPATH' ) || exit;

final class Domestic_Dob_Blagajna {

	public static function init(): void {
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'klasicna' ), 1 );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validacija' ), 10, 2 );
		add_filter( 'render_block', array( __CLASS__, 'blok' ), 10, 2 );
		add_action( 'woocommerce_store_api_cart_errors', array( __CLASS__, 'store_api' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'meta' ) );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( __CLASS__, 'meta' ) );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'ponisti' ) );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'ponisti' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'admin_narudzba' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'css' ) );
	}

	/** Traži li košarica provjeru (uključeno + barem jedan proizvod iz opsega). */
	public static function treba(): bool {
		if ( ! Domestic_Dob_Postavke::ukljuceno() || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}
		$s = Domestic_Dob_Postavke::sve();
		foreach ( WC()->cart->get_cart() as $item ) {
			$id = (int) ( $item['product_id'] ?? 0 );
			if ( $id && ( 'svi' === $s['opseg'] || self::u_kategorijama( $id, $s['kategorije'] ) ) ) {
				return true;
			}
		}
		return (bool) apply_filters( 'domestic_dob_treba', false, WC()->cart );
	}

	private static function u_kategorijama( int $product_id, array $kat ): bool {
		if ( ! $kat ) {
			return false;
		}
		foreach ( wc_get_product_term_ids( $product_id, 'product_cat' ) as $t ) {
			if ( in_array( (int) $t, $kat, true ) || array_intersect( $kat, get_ancestors( (int) $t, 'product_cat', 'taxonomy' ) ) ) {
				return true;
			}
		}
		return false;
	}

	public static function potvrdeno(): ?array {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return null;
		}
		$p = WC()->session->get( Domestic_Dob_Tok::SES );
		if ( ! is_array( $p ) || empty( $p['vrijeme'] ) ) {
			return null;
		}
		return time() - (int) $p['vrijeme'] <= Domestic_Dob_Postavke::sve()['trajanje'] * MINUTE_IN_SECONDS ? $p : null;
	}

	private static function blokirano(): bool {
		return self::treba() && null === self::potvrdeno();
	}

	public static function kartica(): string {
		$okr = 'test' === Domestic_Dob_Postavke::aktivno()['env'] ? '<p class="dsd-test">Testno okruženje (mgradjani-test)</p>' : '';
		return '<div class="dsd-kartica" role="region" aria-label="Provjera punoljetnosti">'
			. '<div class="dsd-ikona" aria-hidden="true">18+</div>'
			. '<h2>Potvrdite punoljetnost</h2>'
			. '<p>Prema Zakonu o trgovini (čl. 11.a) prije kupnje alkoholnih pića moramo provjeriti punoljetnost putem sustava <b>e-Građani</b>, u aplikaciji <b>mGrađani</b>.</p>'
			. '<ol class="dsd-koraci"><li>Kliknite gumb – otvorit će se stranica e-Građana s QR kodom.</li><li>Potvrdite zahtjev u aplikaciji mGrađani na mobitelu.</li><li>Vratit ćemo vas ovdje da dovršite narudžbu.</li></ol>'
			. '<p><a class="button alt dsd-gumb" href="' . esc_url( Domestic_Dob_Tok::start_url() ) . '" rel="nofollow">Potvrdi punoljetnost (mGrađani)</a></p>'
			. '<p class="dsd-mala">Saznajemo samo jeste li punoljetni – ne primamo ime, OIB ni datum rođenja. <a href="' . esc_url( wc_get_cart_url() ) . '">Natrag u košaricu</a></p>'
			. $okr . '</div>';
	}

	/** Klasična blagajna ([woocommerce_checkout]): kartica umjesto obrasca. */
	public static function klasicna(): void {
		if ( ! self::blokirano() ) {
			return;
		}
		echo self::kartica(); // phpcs:ignore WordPress.Security.EscapeOutput -- escapano u kartica()
		echo '<style>form.checkout.woocommerce-checkout,.woocommerce-form-coupon-toggle,.checkout_coupon,.woocommerce-form-login-toggle,.woocommerce-form-login{display:none!important}</style>';
	}

	public static function validacija( $data, $errors ): void {
		if ( self::blokirano() ) {
			$errors->add( 'dsd_provjera', 'Prije narudžbe potrebno je potvrditi punoljetnost putem aplikacije mGrađani. Osvježite stranicu blagajne.' );
		}
	}

	/** Blok blagajna: sadržaj bloka zamjenjuje se karticom. */
	public static function blok( $html, $block ) {
		if ( 'woocommerce/checkout' === ( $block['blockName'] ?? '' ) && ! is_admin() && self::blokirano() ) {
			return self::kartica();
		}
		return $html;
	}

	/** Store API (blok blagajna, aplikacije): greška prije stvaranja narudžbe. */
	public static function store_api( $errors, $cart ): void {
		if ( self::blokirano() ) {
			$errors->add( 'dsd_provjera', 'Prije narudžbe potrebno je potvrditi punoljetnost putem aplikacije mGrađani.' );
		}
	}

	public static function meta( $order ): void {
		$p = self::treba() ? self::potvrdeno() : null;
		if ( ! $p || ! $order instanceof WC_Order ) {
			return;
		}
		$order->update_meta_data( '_dsd_provjereno', 'yes' );
		$order->update_meta_data( '_dsd_vrijeme', gmdate( 'c', (int) $p['vrijeme'] ) );
		$order->update_meta_data( '_dsd_okruzenje', 'prod' === $p['env'] ? 'prod' : 'test' );
	}

	public static function ponisti(): void {
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( Domestic_Dob_Tok::SES, null );
		}
	}

	public static function admin_narudzba( $order ): void {
		if ( 'yes' !== $order->get_meta( '_dsd_provjereno' ) ) {
			return;
		}
		$t = strtotime( (string) $order->get_meta( '_dsd_vrijeme' ) );
		echo '<p><strong>Punoljetnost:</strong> provjerena putem e-Građana' . ( $t ? ' ' . esc_html( wp_date( 'j.n.Y. H:i', $t ) ) : '' ) . ( 'test' === $order->get_meta( '_dsd_okruzenje' ) ? ' (testno okruženje)' : '' ) . '</p>';
	}

	public static function css(): void {
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			wp_enqueue_style( 'domestic-provjera-dobi', DSD_URL . 'assets/provjera.css', array(), DSD_VER );
		}
	}
}
