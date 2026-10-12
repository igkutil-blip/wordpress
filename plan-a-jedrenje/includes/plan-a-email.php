<?php
/**
 * Plan A – zajednički izgled svih e-mailova (kupcima, članovima i nama). Ista datoteka je
 * u svakom Plan A dodatku koji šalje mailove; učitava se prva koja stigne (class_exists),
 * pa sve verzije moraju biti iste (konstanta V).
 *
 * Izgled kao e-mail narudžbe (Plan A košarica): tamnoplavo zaglavlje s logom, bijela kartica
 * s ikonom i naslovom, kartice sa sadržajem, podnožje. Tablice i stilovi u elementima
 * (programi za e-poštu), širina do 600 px.
 *
 * @package Plan_A
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Plan_A_Email' ) ) {

	final class Plan_A_Email {

		const V = '1.0.1';

		const FONT  = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
		const TEXT  = '#24323f';
		const MUTED = '#5f6b77';
		const BG    = '#f3f6f9';
		const GREEN = '#1e9e4a';
		const RED   = '#c0392b';

		/** Ikone u krugu ispod zaglavlja. */
		const ICONS = array(
			'check' => '&#10003;',
			'plus'  => '&#43;',
			'info'  => 'i',
			'warn'  => '!',
			'x'     => '&#215;',
			'mail'  => '@',
			'list'  => '&#9776;',
			'euro'  => '&#8364;',
			'star'  => '&#9733;',
			'card'  => '&#9998;',
		);

		/** Boje (iz postavki Plan A košarice, ako je uključena). */
		public static function color( string $key ): string {
			$def = array(
				'navy'   => '#12304b',
				'accent' => '#1a9ad6',
				'cta'    => '#e8862a',
			);
			if ( class_exists( 'Plan_A_Kosarica_Settings' ) ) {
				$v = (string) ( Plan_A_Kosarica_Settings::get()[ $key ] ?? '' );
				if ( preg_match( '/^#[0-9a-f]{3,6}$/i', $v ) ) {
					return $v;
				}
			}
			return $def[ $key ] ?? '#12304b';
		}

		/** Logo za zaglavlje (kao u e-mailu narudžbe). */
		public static function logo(): string {
			if ( class_exists( 'Plan_A_Kosarica_Order' ) && method_exists( 'Plan_A_Kosarica_Order', 'logo_url' ) ) {
				$url = Plan_A_Kosarica_Order::logo_url();
				if ( '' !== $url ) {
					return $url;
				}
			}
			$id = 0;
			if ( class_exists( 'Plan_A_Izleti_Settings' ) ) {
				$id = (int) ( Plan_A_Izleti_Settings::get()['logo_id'] ?? 0 );
			}
			$id = $id ?: (int) get_theme_mod( 'custom_logo', 0 );
			$url = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
			return $url ? (string) $url : (string) get_option( 'woocommerce_email_header_image', '' );
		}

		private static function site(): string {
			return wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		}

		/* ---------------- Dijelovi ---------------- */

		public static function p( string $html, string $style = '' ): string {
			return '<p style="margin:0 0 12px;color:' . self::TEXT . ';font-family:' . self::FONT . ';font-size:15px;line-height:1.6;' . $style . '">' . $html . '</p>';
		}

		public static function small( string $html ): string {
			return self::p( $html, 'font-size:13px;color:' . self::MUTED . ';' );
		}

		public static function h2( string $text ): string {
			return '<h2 style="margin:0 0 12px;color:' . self::color( 'navy' ) . ';font-family:' . self::FONT . ';font-size:19px;line-height:1.3;font-weight:700;">' . esc_html( $text ) . '</h2>';
		}

		/** Bijela kartica s naslovom (naslov nije obavezan). */
		public static function card( string $title, string $html ): string {
			return '<tr><td style="padding:14px 0 0;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td class="pae-card" style="background:#ffffff;border-radius:14px;padding:22px 24px;font-family:' . self::FONT . ';color:' . self::TEXT . ';font-size:15px;line-height:1.6;">'
				. ( '' !== $title ? self::h2( $title ) : '' ) . $html . '</td></tr></table></td></tr>';
		}

		/** Retci „oznaka – vrijednost” (vrijednost je HTML; prazna oznaka = cijeli red). */
		public static function rows( array $rows ): string {
			$l = 'padding:5px 12px 5px 0;color:' . self::MUTED . ';font-family:' . self::FONT . ';font-size:14px;line-height:1.45;vertical-align:top;white-space:nowrap;width:1%;';
			$v = 'padding:5px 0;color:' . self::TEXT . ';font-family:' . self::FONT . ';font-size:15px;line-height:1.45;vertical-align:top;';
			$h = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">';
			foreach ( $rows as $k => $row ) {
				if ( ! is_array( $row ) ) {
					$row = array( (string) $k, (string) $row );
				}
				$h .= '' === (string) $row[0]
					? '<tr><td colspan="2" style="' . $v . '">' . $row[1] . '</td></tr>'
					: '<tr><td style="' . $l . '">' . esc_html( (string) $row[0] ) . '</td><td style="' . $v . '">' . $row[1] . '</td></tr>';
			}
			return $h . '</table>';
		}

		/** Tablica s naslovima stupaca (izvještaji). $head = nazivi, $rows = retci (HTML ćelije). */
		public static function table( array $head, array $rows ): string {
			$th = 'padding:7px 8px;background:#e8eef4;color:' . self::color( 'navy' ) . ';font-family:' . self::FONT . ';font-size:13px;text-align:left;font-weight:700;';
			$td = 'padding:7px 8px;border-bottom:1px solid #e3e8ee;color:' . self::TEXT . ';font-family:' . self::FONT . ';font-size:14px;line-height:1.4;vertical-align:top;';
			$h  = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;"><tr>';
			foreach ( $head as $c ) {
				$h .= '<th style="' . $th . '">' . esc_html( (string) $c ) . '</th>';
			}
			$h .= '</tr>';
			foreach ( $rows as $r ) {
				$h .= '<tr>';
				foreach ( (array) $r as $c ) {
					$h .= '<td style="' . $td . '">' . $c . '</td>';
				}
				$h .= '</tr>';
			}
			return $h . '</table>';
		}

		public static function button( string $label, string $url, string $color = '' ): string {
			$color = $color ?: self::color( 'cta' );
			return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="center" style="padding:10px 0 14px;"><a href="' . esc_url( $url ) . '" style="display:inline-block;padding:14px 28px;border-radius:10px;background:' . esc_attr( $color ) . ';color:#ffffff;font-family:' . self::FONT . ';font-size:16px;font-weight:700;text-decoration:none;">' . esc_html( $label ) . '</a></td></tr></table>';
		}

		/** Istaknuta obavijest (narančasti okvir). */
		public static function notice( string $html ): string {
			return '<tr><td style="padding:14px 0 0;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="background:#fff4e8;border:2px solid ' . self::color( 'cta' ) . ';border-radius:14px;padding:18px 22px;font-family:' . self::FONT . ';color:' . self::TEXT . ';font-size:15px;line-height:1.55;">' . $html . '</td></tr></table></td></tr>';
		}

		/**
		 * Uplatnica (HUB-3A) u HTML-u. $d: iznos (float), platitelj [ime, adresa, mjesto],
		 * primatelj [ime, adresa, mjesto], iban, model (npr. HR00), poziv, opis.
		 */
		public static function slip( array $d ): string {
			$red  = '#d9534f';
			$f    = 'font-family:' . self::FONT . ';';
			$lab  = 'display:block;color:' . $red . ';font-size:9px;line-height:1.2;text-transform:uppercase;letter-spacing:.3px;margin-bottom:2px;' . $f;
			$val  = 'color:#1d2327;font-size:12.5px;line-height:1.35;font-family:Courier New,Courier,monospace;';
			$cell = 'border:1px solid ' . $red . ';padding:4px 6px;vertical-align:top;';
			$amt  = number_format( (float) ( $d['iznos'] ?? 0 ), 2, ',', '.' );
			$who  = static function ( array $p ) use ( $val ): string {
				return '<span style="' . $val . '">' . implode( '<br>', array_map( 'esc_html', array_filter( array_map( 'strval', $p ) ) ) ) . '</span>';
			};
			return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;background:#fff;border:2px solid ' . $red . ';">'
				. '<tr><td colspan="2" style="padding:5px 6px;color:' . $red . ';font-size:11px;font-weight:700;text-align:right;' . $f . '">NALOG ZA NACIONALNA PLAĆANJA · HUB-3A</td></tr>'
				. '<tr><td style="' . $cell . 'width:50%;" rowspan="2"><span style="' . $lab . '">Platitelj (naziv/ime i adresa)</span>' . $who( (array) ( $d['platitelj'] ?? array() ) ) . '</td>'
				. '<td style="' . $cell . '"><span style="' . $lab . '">Valuta · Iznos</span><span style="' . $val . 'font-weight:700;font-size:15px;">EUR = ' . esc_html( $amt ) . '</span></td></tr>'
				. '<tr><td style="' . $cell . '"><span style="' . $lab . '">IBAN primatelja</span><span style="' . $val . '">' . esc_html( (string) ( $d['iban'] ?? '' ) ) . '</span></td></tr>'
				. '<tr><td style="' . $cell . '" rowspan="2"><span style="' . $lab . '">Primatelj (naziv i adresa)</span>' . $who( (array) ( $d['primatelj'] ?? array() ) ) . '</td>'
				. '<td style="' . $cell . '"><span style="' . $lab . '">Model · Poziv na broj primatelja</span><span style="' . $val . '">' . esc_html( trim( ( $d['model'] ?? 'HR00' ) . ' ' . ( $d['poziv'] ?? '' ) ) ) . '</span></td></tr>'
				. '<tr><td style="' . $cell . '"><span style="' . $lab . '">Opis plaćanja</span><span style="' . $val . '">' . esc_html( (string) ( $d['opis'] ?? '' ) ) . '</span></td></tr>'
				. '</table>';
		}

		/**
		 * Kartica „Podaci za plaćanje”: 2D kod (src slike, npr. cid:…), uplatnica i podaci.
		 * $d kao za slip() + 'code' (src) + 'rok' (tekst) + 'title'.
		 */
		public static function payment( array $d ): string {
			$navy = self::color( 'navy' );
			$h    = '';
			if ( ! empty( $d['code'] ) ) {
				$h .= self::p( '<strong style="color:' . $navy . ';">Skenirajte i platite</strong>', 'text-align:center;' )
					. '<div style="text-align:center;margin:0 0 16px;"><img src="' . esc_attr( (string) $d['code'] ) . '" alt="2D kod za plaćanje" width="420" style="display:block;width:100%;max-width:420px;height:auto;margin:0 auto;border:0;"></div>';
			}
			$h .= self::p( '<strong style="color:' . $navy . ';">Uplatnica</strong>', 'text-align:center;margin-top:4px;' ) . self::slip( $d );
			$prim = (array) ( $d['primatelj'] ?? array() );
			$h   .= '<div style="height:14px;line-height:14px;font-size:0;">&nbsp;</div>' . self::rows(
				array_filter(
					array(
						array( 'Iznos', '<strong>' . esc_html( number_format( (float) ( $d['iznos'] ?? 0 ), 2, ',', '.' ) ) . ' €</strong>' ),
						array( 'Primatelj', esc_html( implode( ', ', array_filter( array_map( 'strval', $prim ) ) ) ) ),
						array( 'IBAN', esc_html( (string) ( $d['iban'] ?? '' ) ) ),
						array( 'Model i poziv na broj', esc_html( trim( ( $d['model'] ?? 'HR00' ) . ' ' . ( $d['poziv'] ?? '' ) ) ) ),
						array( 'Opis plaćanja', esc_html( (string) ( $d['opis'] ?? '' ) ) ),
						! empty( $d['rok'] ) ? array( 'Rok plaćanja', '<strong>' . esc_html( (string) $d['rok'] ) . '</strong>' ) : null,
					)
				)
			);
			return self::card( (string) ( $d['title'] ?? 'Podaci za plaćanje' ), $h );
		}

		/**
		 * Ikona u krugu kao slika (uvijek okrugla, u svakom programu za poštu). Bez slika ostaje
		 * znak u alt tekstu.
		 */
		public static function icon_html( string $icon ): string {
			$icon = isset( self::ICONS[ $icon ] ) ? $icon : 'info';
			$src  = plugins_url( 'mail-icons/' . $icon . '.png', __FILE__ );
			return '<table role="presentation" align="center" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto 12px;"><tr><td align="center" width="56" height="56" style="width:56px;height:56px;">'
				. '<img src="' . esc_url( $src ) . '" width="56" height="56" alt="' . esc_attr( html_entity_decode( self::ICONS[ $icon ], ENT_QUOTES, 'UTF-8' ) ) . '" style="display:block;width:56px;height:56px;border:0;margin:0 auto;"></td></tr></table>';
		}

		/* ---------------- Cijeli mail ---------------- */

		/**
		 * Početak maila do (uključivo) kartice s naslovom.
		 * $a: title, subtitle (HTML), icon (ključ iz ICONS ili ''), color (boja kruga), label (mali natpis iznad naslova), preheader.
		 */
		public static function open( array $a ): string {
			$navy  = self::color( 'navy' );
			$title = (string) ( $a['title'] ?? '' );
			$icon  = (string) ( $a['icon'] ?? 'info' );
			$color = (string) ( $a['color'] ?? ( 'check' === $icon ? self::GREEN : ( 'x' === $icon ? self::RED : self::color( 'accent' ) ) ) );
			$logo  = self::logo();
			$site  = self::site();
			$h  = '<!DOCTYPE html><html lang="hr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="x-apple-disable-message-reformatting"><meta http-equiv="Content-Language" content="hr"><title>' . esc_html( $site ) . '</title>'
				. '<style>body{margin:0;padding:0;background:' . self::BG . ';}img{border:0;max-width:100%;height:auto;}@media only screen and (max-width:620px){.pae-wrap{width:100%!important;}.pae-card{padding:18px 16px!important;}.pae-h1{font-size:22px!important;}}</style></head>'
				. '<body lang="hr" style="margin:0;padding:0;background:' . self::BG . ';">'
				. '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . esc_html( (string) ( $a['preheader'] ?? $title ) ) . '</div>'
				. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . self::BG . ';"><tr><td align="center" style="padding:24px 8px;">'
				. '<table role="presentation" class="pae-wrap" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;">'
				// Zaglavlje s logom.
				. '<tr><td align="center" style="background:' . $navy . ';border-radius:14px 14px 0 0;padding:22px 24px;">'
				. ( $logo ? '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( $site ) . '" height="48" style="display:block;height:48px;width:auto;max-width:260px;margin:0 auto;border:0;">' : '<span style="color:#ffffff;font-family:' . self::FONT . ';font-size:24px;font-weight:800;">' . esc_html( $site ) . '</span>' )
				. '</td></tr>'
				// Naslov.
				. '<tr><td style="background:#ffffff;border-radius:0 0 14px 14px;padding:26px 24px 22px;text-align:center;">';
			if ( '' !== $icon && isset( self::ICONS[ $icon ] ) ) {
				$h .= self::icon_html( $icon );
			}
			if ( ! empty( $a['label'] ) ) {
				$h .= '<p style="margin:0 0 6px;color:#c96a12;font-family:' . self::FONT . ';font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;">' . esc_html( (string) $a['label'] ) . '</p>';
			}
			$h .= '<h1 class="pae-h1" style="margin:0 0 8px;color:' . $navy . ';font-family:' . self::FONT . ';font-size:24px;line-height:1.3;font-weight:800;text-align:center;">' . esc_html( $title ) . '</h1>';
			if ( ! empty( $a['subtitle'] ) ) {
				$h .= '<p style="margin:0;color:' . self::MUTED . ';font-family:' . self::FONT . ';font-size:15px;line-height:1.5;">' . $a['subtitle'] . '</p>';
			}
			return $h . '</td></tr><tr><td style="height:2px;line-height:2px;font-size:0;">&nbsp;</td></tr>';
		}

		/** Kraj maila: podnožje s podacima udruge i poveznicom na stranicu. */
		public static function close( string $extra = '' ): string {
			$accent = self::color( 'accent' );
			$footer = (string) get_option( 'woocommerce_email_footer_text', '' );
			$footer = '' !== $footer ? (string) apply_filters( 'woocommerce_email_footer_text', $footer, null ) : 'S.R.D. Plan A · Celovečka 60b, Zagreb · info@srd-plan-a.hr · 095 90 60 556';
			return '<tr><td align="center" style="padding:18px 24px 8px;color:' . self::MUTED . ';font-family:' . self::FONT . ';font-size:13px;line-height:1.5;">'
				. ( '' !== $extra ? '<p style="margin:0 0 8px;">' . $extra . '</p>' : '' )
				. wp_kses_post( wpautop( wptexturize( $footer ) ) )
				. '<a href="' . esc_url( home_url( '/' ) ) . '" style="color:' . $accent . ';">' . esc_html( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) . '</a>'
				. '</td></tr></table></td></tr></table></body></html>';
		}

		/**
		 * Cijeli mail. $body = kartice (card(), notice(), payment() …) ili običan HTML
		 * (tada ide u jednu karticu).
		 */
		public static function render( array $a, string $body, string $footer_extra = '' ): string {
			if ( '' !== $body && 0 !== strpos( ltrim( $body ), '<tr' ) ) {
				$body = self::card( '', $body );
			}
			return self::open( $a ) . $body . self::close( $footer_extra );
		}
	}
}
