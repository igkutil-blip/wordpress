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
			'Koncept'      => (string) ( $r['concept'] ?? '' ),
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
		$html = '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:0 0 18px;font-size:15px;">';
		foreach ( array_filter( $rows, 'strlen' ) as $k => $v ) {
			$html .= '<tr><td style="padding:7px 10px 7px 0;color:#5f6b77;vertical-align:top;white-space:nowrap;border-bottom:1px solid #edf1f5;">' . esc_html( $k ) . '</td>'
				. '<td style="padding:7px 0;color:#24323f;font-weight:bold;border-bottom:1px solid #edf1f5;">' . esc_html( $v ) . '</td></tr>';
		}
		return $html . '</table>';
	}

	public static function organizer_html(): string {
		return '<p style="margin:22px 0 0;color:#8a96a3;font-size:12px;line-height:1.5;">' . esc_html( (string) Plan_A_Jedrenje_Data::value( 'organizer' ) ) . '</p>';
	}

	/**
	 * Cijeli e-mail. $paragraphs su običan tekst (escapira se), $html je već siguran HTML.
	 */
	public static function wrap( string $title, array $paragraphs, string $html = '', array $button = array() ): string {
		$body = '<!doctype html><html><body style="margin:0;padding:24px 12px;background:#f3f6f9;font-family:Arial,Helvetica,sans-serif;color:#24323f">'
			. '<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:16px;padding:28px 24px">'
			. '<p style="margin:0 0 6px;color:#c96a12;font-size:12px;font-weight:bold;letter-spacing:2px;text-transform:uppercase">' . esc_html( (string) Plan_A_Jedrenje_Data::value( 'title' ) ) . '</p>'
			. '<h1 style="margin:0 0 16px;color:#12304b;font-size:24px;line-height:1.25">' . esc_html( $title ) . '</h1>';
		foreach ( $paragraphs as $p ) {
			$body .= '<p style="margin:0 0 14px;font-size:16px;line-height:1.55">' . nl2br( esc_html( $p ) ) . '</p>';
		}
		$body .= $html;
		if ( $button ) {
			$body .= '<p style="margin:6px 0 10px"><a href="' . esc_url( $button[1] ) . '" style="display:inline-block;padding:14px 24px;border-radius:999px;background:#e8862a;color:#fff;font-weight:bold;text-decoration:none">' . esc_html( $button[0] ) . '</a></p>';
		}
		return $body . self::organizer_html() . '</div></body></html>';
	}

	public static function send( string $to, string $subject, string $body ): bool {
		if ( ! is_email( $to ) ) {
			return false;
		}
		return (bool) wp_mail( $to, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	public static function terms_html(): string {
		$lines = Plan_A_Jedrenje_Data::lines( 'cancel_terms' );
		if ( ! $lines ) {
			return '';
		}
		$html = '<p style="margin:18px 0 6px;font-size:14px;font-weight:bold;color:#12304b">Uvjeti otkaza</p><ul style="margin:0;padding-left:18px;color:#5f6b77;font-size:13px;line-height:1.5">';
		foreach ( $lines as $l ) {
			$html .= '<li>' . esc_html( $l ) . '</li>';
		}
		return $html . '</ul>';
	}
}
