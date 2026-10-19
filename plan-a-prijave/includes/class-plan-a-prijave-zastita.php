<?php
/**
 * Zaštita prijave od narudžbe od 0 € (narudžba #9115, 19.10.2026.).
 *
 * Uzrok: SpeedyCache stranicu izleta služi iz predmemorije danima, a sigurnosni kod obrasca
 * (ttbm_form_nonce) vrijedi najviše 24 sata. S isteklim kodom WooCommerce ipak doda proizvod
 * izleta u košaricu, ali bez karte, termina i cijene → narudžba 0 € „U obradi / plaćeno”.
 *
 *  1. Istekli kod obrasca izleta zamijeni se svježim prije nego WooCommerce obradi „Rezervirajte”
 *     (kod ovdje štiti samo dodavanje u košaricu; podaci se svejedno provjeravaju).
 *  2. Proizvod izleta ne može u košaricu bez barem jedne karte (npr. izravna poveznica na proizvod).
 *  3. Blagajna ne propušta stavku izleta bez karte (zadnja zaštita).
 *
 * @package Plan_A_Prijave
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Prijave_Zastita {

	public static function init() {
		// WooCommerce obrađuje dodavanje u košaricu na wp_loaded (20).
		add_action( 'wp_loaded', array( __CLASS__, 'fresh_nonce' ), 5 );
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate' ), 99, 2 );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'checkout' ), 5, 2 );
	}

	private static function tickets_posted(): int {
		$qty = isset( $_POST['ticket_qty'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ticket_qty'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return (int) array_sum( $qty );
	}

	public static function fresh_nonce() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		// Obrazac izleta šalje add-to-cart (proizvod izleta), ticket_qty[] i ttbm_form_nonce.
		$product = isset( $_POST['add-to-cart'] ) ? absint( wp_unslash( $_POST['add-to-cart'] ) ) : 0;
		if ( empty( $_POST['ttbm_form_nonce'] ) || ! $product || self::tickets_posted() < 1 || ! self::tour_of_product( $product ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['ttbm_form_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'ttbm_form_nonce' ) ) {
			$_POST['ttbm_form_nonce']    = wp_create_nonce( 'ttbm_form_nonce' );
			$_REQUEST['ttbm_form_nonce'] = $_POST['ttbm_form_nonce'];
		}
		// phpcs:enable
	}

	/** Izlet povezan s proizvodom (link_ttbm_id), 0 = nije proizvod izleta. */
	private static function tour_of_product( int $product_id ): int {
		if ( Plan_A_Prijave_Tour::CPT === get_post_type( $product_id ) ) {
			return $product_id;
		}
		$tour = (int) get_post_meta( $product_id, 'link_ttbm_id', true );
		return $tour && Plan_A_Prijave_Tour::CPT === get_post_type( $tour ) ? $tour : 0;
	}

	public static function validate( $passed, $product_id ) {
		$tour = self::tour_of_product( (int) $product_id );
		if ( ! $passed || ! $tour || self::tickets_posted() > 0 ) {
			return $passed;
		}
		wc_add_notice( sprintf( 'Za prijavu na izlet odaberite termin i broj osoba na <a href="%s">stranici izleta</a>.', esc_url( get_permalink( $tour ) ) ), 'error' );
		return false;
	}

	public static function checkout( $data, $errors ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			$tour = (int) ( $item['ttbm_id'] ?? 0 );
			if ( ! $tour || Plan_A_Prijave_Tour::CPT !== get_post_type( $tour ) || ! empty( $item['paj_rez'] ) ) {
				continue;
			}
			$people = 0;
			foreach ( (array) ( $item['ttbm_ticket_info'] ?? array() ) as $t ) {
				$people += (int) ( $t['ticket_qty'] ?? 0 );
			}
			if ( $people < 1 ) {
				WC()->cart->remove_cart_item( $key );
				$errors->add( 'pap_bez_karte', sprintf( 'Prijava za „%1$s” nije potpuna (nije odabran termin ni broj osoba) pa je maknuta iz košarice. Prijavite se ponovno na <a href="%2$s">stranici izleta</a>.', esc_html( get_the_title( $tour ) ), esc_url( get_permalink( $tour ) ) ) );
			}
		}
	}
}
