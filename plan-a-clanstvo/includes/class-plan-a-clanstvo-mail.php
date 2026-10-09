<?php
/**
 * E-mailovi: zahtjev za potvrdu (i podsjetnik) te potvrda s podacima za članarinu.
 *
 * @package Plan_A_Clanstvo
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Clanstvo_Mail {

	public static function wrap( string $title, string $inner ): string {
		return '<!doctype html><html lang="hr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>'
			. '<body style="margin:0;padding:24px 12px;background:#f3f6f9;font-family:Arial,Helvetica,sans-serif;color:#24323f">'
			. '<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:16px;padding:28px 24px">'
			. '<p style="margin:0 0 6px;color:#c96a12;font-size:12px;font-weight:bold;letter-spacing:2px;text-transform:uppercase">Plan A · pristupnica</p>'
			. '<h1 style="margin:0 0 16px;color:#12304b;font-size:24px;line-height:1.25">' . esc_html( $title ) . '</h1>'
			. $inner
			. '<p style="margin:24px 0 0;color:#8a96a3;font-size:12px;line-height:1.5">S.R.D. Plan A · Celovečka 60b, Zagreb · info@srd-plan-a.hr · 095 90 60 556</p>'
			. '</div></body></html>';
	}

	public static function p( string $text ): string {
		return '<p style="margin:0 0 14px;font-size:16px;line-height:1.55">' . $text . '</p>';
	}

	public static function button( string $label, string $url ): string {
		return '<p style="margin:22px 0 22px;text-align:center"><a href="' . esc_url( $url ) . '" style="display:inline-block;padding:16px 30px;border-radius:999px;background:#e8862a;color:#fff;font-size:18px;font-weight:bold;text-decoration:none">' . esc_html( $label ) . '</a></p>';
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

	public static function send( string $to, string $subject, string $html, array $attachments = array() ): bool {
		if ( ! is_email( $to ) ) {
			return false;
		}
		$ok = (bool) wp_mail( $to, $subject, $html, self::headers(), $attachments );
		update_option( 'plan_a_clanstvo_last_mail', array( 'time' => current_time( 'mysql' ), 'to' => $to, 'ok' => $ok, 'error' => $ok ? '' : (string) get_option( 'plan_a_clanstvo_mail_error', '' ) ), false );
		return $ok;
	}

	/**
	 * Zahtjev za potvrdu (nova poveznica svaki put).
	 */
	public static function confirm_request( int $id, bool $reminder = false ): bool {
		$m = Plan_A_Clanstvo_Data::get_member( $id );
		if ( ! $m || 'potvrdeno' === $m['status'] ) {
			return false;
		}
		$url   = Plan_A_Clanstvo_Data::confirm_url( Plan_A_Clanstvo_Data::new_token( $id ) );
		if ( ! $reminder ) {
			update_post_meta( $id, '_pac_requested', time() ); // od sada teku rokovi za podsjetnik i brisanje
			delete_post_meta( $id, '_pac_reminded' );
		}
		$days  = (int) Plan_A_Clanstvo_Data::value( 'valid_days' );
		$inner = self::p( 'Pozdrav ' . esc_html( $m['ime'] ) . ',' )
			. self::p( $reminder
				? 'tvoja pristupnica u udrugu Plan A još nije potvrđena. Potvrdi je jednim klikom:'
				: 'hvala na pristupnici u udrugu Plan A! Još samo jedan korak: klikni gumb i potvrdi da si je ispunio/la ti.' )
			. self::button( 'Potvrđujem pristupnicu', $url )
			. self::p( '<span style="color:#5f6b77;font-size:14px">Klikom potvrđuješ i da prihvaćaš Izjavu člana. Poveznica vrijedi ' . (int) $days . ' dana. Ako gumb ne radi, kopiraj ovu adresu u preglednik:<br><a href="' . esc_url( $url ) . '" style="color:#1a73b8;word-break:break-all">' . esc_html( $url ) . '</a></span>' )
			. self::p( '<span style="color:#5f6b77;font-size:14px">Ako pristupnicu nisi ispunio/la ti, javi nam se na info@srd-plan-a.hr.</span>' );
		return self::send( $m['email'], ( $reminder ? 'Podsjetnik: ' : '' ) . 'Potvrdi pristupnicu u Plan A', self::wrap( $reminder ? 'Potvrdi svoju pristupnicu' : 'Potvrdi svoju pristupnicu', $inner ) );
	}

	/**
	 * Podaci za uplatu članarine (HTML, za e-mail i stranicu).
	 */
	public static function payment_html( int $id, string $img_style = 'display:block;width:100%;max-width:420px;height:auto;margin:0 auto 14px' ): string {
		$pay  = Plan_A_Clanstvo_Hub3::payment();
		$code = Plan_A_Clanstvo_Hub3::for_member( $id );
		$rows = array(
			'Iznos'          => Plan_A_Clanstvo_Data::money( $pay['iznos'] ),
			'Primatelj'      => $pay['primatelj'] . ', ' . $pay['adresa'],
			'IBAN'           => $pay['iban'],
			'Model i poziv'  => $pay['model'] . ' ' . $pay['poziv'],
			'Opis plaćanja'  => $pay['opis'],
		);
		$html = '<div style="background:#f3f8fc;border-radius:14px;padding:18px 18px 10px;margin:6px 0 16px">';
		$html .= '<p style="margin:0 0 12px;color:#12304b;font-size:17px;font-weight:bold">Članarina za ' . (int) Plan_A_Clanstvo_Data::year() . '.</p>';
		if ( $code ) {
			$html .= '<p style="margin:0 0 8px;font-size:14px;color:#5f6b77">Skeniraj kod u mobilnom bankarstvu ili aplikaciji FotoNalog:</p>'
				. '<img src="' . esc_url( $code['url'] ) . '" alt="2D kod za uplatu članarine" style="' . esc_attr( $img_style ) . '">';
		}
		$html .= '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:15px">';
		foreach ( $rows as $k => $v ) {
			$html .= '<tr><td style="padding:5px 10px 5px 0;color:#5f6b77;white-space:nowrap;vertical-align:top">' . esc_html( $k ) . '</td><td style="padding:5px 0;color:#24323f;font-weight:bold">' . esc_html( $v ) . '</td></tr>';
		}
		return $html . '</table></div>';
	}

	public static function confirmed( int $id ): bool {
		$m = Plan_A_Clanstvo_Data::get_member( $id );
		if ( ! $m ) {
			return false;
		}
		$code  = Plan_A_Clanstvo_Hub3::for_member( $id );
		$inner = self::p( 'Pozdrav ' . esc_html( $m['ime'] ) . ',' )
			. self::p( 'tvoja pristupnica je <strong>potvrđena</strong>. Dobrodošao/la u Plan A!' )
			. self::p( 'Članarina vrijedi za kalendarsku godinu. Člansku iskaznicu preuzimaš na prvom susretu s nama.' )
			. self::payment_html( $id )
			. self::button( 'Pogledaj izlete', home_url( '/izleti/' ) );
		$files = $code && $code['path'] ? array( $code['path'] ) : array();
		return self::send( $m['email'], 'Pristupnica je potvrđena – dobrodošli u Plan A', self::wrap( 'Pristupnica je potvrđena', $inner ), $files );
	}
}
