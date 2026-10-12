<?php
/**
 * E-mailovi dodatka (zahtjev, odbijanje, potvrde, podsjetnici). Uplatnice s 2D kodom šalje
 * WooCommerce e-mail "Narudžba na čekanju" s dodatkom Hub3; ovdje se u njega dodaju podaci
 * o rezervaciji (vidi Plan_A_Jedrenje_Booking::wc_email_block()).
 *
 * @package Plan_A_Jedrenje
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Jedrenje_Mail {

	/**
	 * Tablica podataka rezervacije (HTML).
	 */
	public static function details( array $r, bool $with_payment = true ): string {
		$rows = array(
			'Termin'       => Plan_A_Jedrenje_Data::week_long( $r['week'] ),
			'Polazak'      => Plan_A_Jedrenje_Data::value( 'marina' ) . ', subota, ukrcaj od ' . Plan_A_Jedrenje_Data::value( 'embark_time' ) . ' h',
			'Brod'         => Plan_A_Jedrenje_Data::value( 'boat' ) . ', ' . (int) Plan_A_Jedrenje_Data::value( 'cabins' ) . ' kabine, ' . Plan_A_Jedrenje_Data::value( 'skipper' ),
			'Broj osoba'   => (string) $r['persons'],
			'Sadržaj'      => (string) ( $r['concept'] ?? '' ),
			'Ruta'         => $r['route'],
			'Cijena'       => Plan_A_Jedrenje_Data::money( (float) $r['price'] ) . ' za cijeli brod',
		);
		if ( $with_payment && ! empty( $r['deposit'] ) ) {
			if ( ! empty( $r['full'] ) ) {
				$rows['Uplata'] = 'cijeli iznos ' . Plan_A_Jedrenje_Data::money( (float) $r['price'] );
			} else {
				$rows['Akontacija'] = Plan_A_Jedrenje_Data::money( (float) $r['deposit'] ) . ' (' . (int) $r['pct'] . ' %)';
				$rows['Ostatak']    = Plan_A_Jedrenje_Data::money( (float) $r['rest'] ) . ( $r['rest_due'] ? ' do ' . Plan_A_Jedrenje_Data::numeric( $r['rest_due'] ) : '' );
			}
		}
		if ( '' !== (string) $r['note'] ) {
			$rows['Napomena'] = $r['note'];
		}
		$out = array();
		foreach ( array_filter( $rows, 'strlen' ) as $k => $v ) {
			$out[] = array( $k, '<strong>' . esc_html( $v ) . '</strong>' );
		}
		return '<div style="margin:6px 0 16px;">' . Plan_A_Email::rows( $out ) . '</div>';
	}

	public static function organizer_html(): string {
		return '<p style="margin:22px 0 0;color:#8a96a3;font-size:12px;line-height:1.5;">' . esc_html( (string) Plan_A_Jedrenje_Data::value( 'organizer' ) ) . '</p>';
	}

	/**
	 * Cijeli e-mail. $paragraphs su običan tekst (escapira se), $html je već siguran HTML.
	 */
	public static function wrap( string $title, array $paragraphs, string $html = '', array $button = array() ): string {
		// Zajednički Plan A izgled (zaglavlje s logom, ikona, kartica).
		$t    = mb_strtolower( $title );
		$icon = 'mail';
		if ( false !== strpos( $t, 'upozorenje' ) ) {
			$icon = 'warn';
		} elseif ( false !== strpos( $t, 'odgovor' ) || false !== strpos( $t, 'odbij' ) ) {
			$icon = 'info';
		} elseif ( false !== strpos( $t, 'potvrđ' ) || false !== strpos( $t, 'uplaćeno' ) ) {
			$icon = 'check';
		} elseif ( false !== strpos( $t, 'podsjetnik' ) ) {
			$icon = 'list';
		}
		$body = '';
		foreach ( $paragraphs as $p ) {
			$body .= Plan_A_Email::p( nl2br( esc_html( $p ) ) );
		}
		$body .= $html;
		if ( $button ) {
			$body .= Plan_A_Email::button( (string) $button[0], (string) $button[1] );
		}
		return Plan_A_Email::render(
			array(
				'title' => $title,
				'label' => (string) Plan_A_Jedrenje_Data::value( 'title' ),
				'icon'  => $icon,
			),
			$body,
			esc_html( (string) Plan_A_Jedrenje_Data::value( 'organizer' ) )
		);
	}

	/**
	 * Pošiljatelj kao u e-mailovima WooCommercea (npr. info@srd-plan-a.hr), a ne zadani
	 * wordpress@…, koji poslužitelji pošte često odbace.
	 */
	public static function headers(): array {
		$from = sanitize_email( (string) get_option( 'woocommerce_email_from_address', '' ) );
		if ( ! is_email( $from ) ) {
			$from = sanitize_email( (string) get_option( 'admin_email' ) );
		}
		$name = trim( wp_strip_all_tags( (string) get_option( 'woocommerce_email_from_name', '' ) ) ) ?: 'Plan A';
		$name = str_replace( array( '"', "\r", "\n" ), '', $name );
		return array(
			'Content-Type: text/html; charset=UTF-8',
			'From: "' . $name . '" <' . $from . '>',
			'Reply-To: ' . $from,
		);
	}

	public static function send( string $to, string $subject, string $body ): bool {
		if ( ! is_email( $to ) ) {
			return false;
		}
		return (bool) wp_mail( $to, $subject, $body, self::headers() );
	}

	public static function terms_html(): string {
		$lines = Plan_A_Jedrenje_Data::lines( 'cancel_terms' );
		if ( ! $lines ) {
			return '';
		}
		$html = '<p style="margin:18px 0 6px;font-family:' . Plan_A_Email::FONT . ';font-size:14px;font-weight:bold;color:' . Plan_A_Email::color( 'navy' ) . '">Uvjeti otkaza</p><ul style="margin:0;padding-left:18px;color:#5f6b77;font-family:' . Plan_A_Email::FONT . ';font-size:13px;line-height:1.5">';
		foreach ( $lines as $l ) {
			$html .= '<li>' . esc_html( $l ) . '</li>';
		}
		return $html . '</ul>';
	}
}
