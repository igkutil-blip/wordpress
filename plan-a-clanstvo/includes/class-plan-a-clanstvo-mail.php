<?php
/**
 * E-mailovi: zahtjev za potvrdu (i podsjetnik) te potvrda s podacima za članarinu.
 *
 * @package Plan_A_Clanstvo
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Clanstvo_Mail {

	/** Ikona u krugu po vrsti maila (natpis iznad naslova). */
	const ICON_BY_LABEL = array(
		'Plan A · pristupnica'    => 'mail',
		'Plan A · članarina'      => 'euro',
		'Plan A · uplata'         => 'euro',
		'Plan A · izleti'         => 'x',
		'Plan A · dnevni pregled' => 'list',
		'Plan A · popis za vodiča' => 'list',
		'Plan A · stanje sustava' => 'warn',
		'Plan A · blagajna'       => 'list',
	);

	/**
	 * Cijeli mail u zajedničkom Plan A izgledu (zaglavlje s logom, naslov, kartice).
	 * $inner: običan HTML (ide u jednu karticu) ili kartice (Plan_A_Email::card/payment …).
	 */
	public static function wrap( string $title, string $inner, string $label = 'Plan A · pristupnica', string $icon = '', string $subtitle = '' ): string {
		return Plan_A_Email::render(
			array(
				'title'    => $title,
				'label'    => $label,
				'icon'     => $icon ?: ( self::ICON_BY_LABEL[ $label ] ?? 'info' ),
				'subtitle' => $subtitle,
			),
			$inner
		);
	}

	public static function p( string $text ): string {
		return Plan_A_Email::p( $text );
	}

	public static function button( string $label, string $url ): string {
		return Plan_A_Email::button( $label, $url );
	}

	/* ---------------- Izvještaji (pregled, popis za vodiča, članarina): bez širokih tablica, čitljivo na mobitelu ---------------- */

	const R_MUTED = 'color:#5f6b77;';

	/** Naslov odjeljka izvještaja (s brojem). */
	public static function r_head( string $title, int $count = -1 ): string {
		return '<p style="margin:22px 0 6px;color:#12304b;font-size:17px;line-height:1.35;font-weight:bold;">' . esc_html( $title ) . ( $count >= 0 ? ' <span style="color:#8a96a3;font-weight:normal;">(' . $count . ')</span>' : '' ) . '</p>';
	}

	/** Oznaka (upozorenje): cijela u jednom komadu, prelama se samo između oznaka. */
	public static function r_chip( string $text, bool $strong = false ): string {
		return '<span style="display:inline-block;white-space:nowrap;margin:0 6px 6px 0;padding:3px 10px;border-radius:999px;font-size:13px;line-height:1.4;font-weight:bold;'
			. ( $strong ? 'background:#c0392b;color:#ffffff;' : 'background:#fdebd3;color:#a34f00;' ) . '">' . esc_html( $text ) . '</span>';
	}

	/** Brojke u jednakim stupcima: [[oznaka, vrijednost, dodatak (HTML)], …]. */
	public static function r_stats( array $stats ): string {
		$w = (int) floor( 100 / max( 1, count( $stats ) ) );
		$h = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:6px 0 4px;background:#f3f6f9;border-radius:10px;"><tr>';
		foreach ( $stats as $st ) {
			$h .= '<td width="' . $w . '%" align="center" style="width:' . $w . '%;padding:8px 4px;vertical-align:top;">'
				. '<div style="color:#12304b;font-size:22px;line-height:1.2;font-weight:bold;">' . esc_html( (string) $st[1] ) . '</div>'
				. '<div style="' . self::R_MUTED . 'font-size:12px;line-height:1.3;">' . esc_html( (string) $st[0] ) . '</div>'
				. ( ! empty( $st[2] ) ? '<div style="' . self::R_MUTED . 'font-size:12px;line-height:1.3;margin-top:2px;">' . $st[2] . '</div>' : '' )
				. '</td>';
		}
		return $h . '</tr></table>';
	}

	/**
	 * Jedna stavka izvještaja: naslov (lijevo) i vrijednost (desno, ne prelama se), ispod podnaslov
	 * i ostalo (HTML). Odvojena crtom.
	 */
	public static function r_item( string $title, string $sub = '', string $right = '', string $body = '' ): string {
		return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-bottom:1px solid #e3e8ee;"><tr><td style="padding:10px 0;vertical-align:top;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
			. '<td style="vertical-align:top;color:#12304b;font-size:16px;line-height:1.35;font-weight:bold;">' . $title . '</td>'
			. ( '' !== $right ? '<td align="right" style="vertical-align:top;padding-left:10px;white-space:nowrap;font-size:15px;line-height:1.35;font-weight:bold;color:#24323f;">' . $right . '</td>' : '' )
			. '</tr></table>'
			. ( '' !== $sub ? '<div style="' . self::R_MUTED . 'font-size:14px;line-height:1.45;margin-top:2px;">' . $sub . '</div>' : '' )
			. ( '' !== $body ? '<div style="margin-top:6px;">' . $body . '</div>' : '' )
			. '</td></tr></table>';
	}

	/** Kartica za uplatu članarine: 2D kod (u samom mailu), uplatnica i podaci. */
	public static function pay_card( int $id, int $year ): string {
		$m    = Plan_A_Clanstvo_Data::get_member( $id );
		$pay  = Plan_A_Clanstvo_Hub3::payment( $year );
		$code = Plan_A_Clanstvo_Hub3::for_member( $id, $year );
		$src  = $code ? ( $code['path'] ? self::embed( $code['path'], 'pac2d' ) : $code['url'] ) : '';
		$s    = Plan_A_Clanstvo_Data::get();
		$d    = array(
			'title'     => 'Članarina za ' . (int) $year . '.',
			'code'      => $src,
			'iznos'     => (float) $pay['iznos'],
			'platitelj' => $m ? array( trim( $m['ime'] . ' ' . $m['prezime'] ), $m['adresa'] ?? '', $m['mjesto'] ?? '' ) : array(),
			'primatelj' => array( $pay['primatelj'], $s['adresa'] ?? '', $s['mjesto'] ?? '' ),
			'iban'      => $pay['iban'],
			'model'     => $pay['model'],
			'poziv'     => $pay['poziv'],
			'opis'      => $pay['opis'],
		);
		$d['slip'] = self::slip( $d, $code ? (string) $code['path'] : '' );
		return Plan_A_Email::payment( $d );
	}

	/**
	 * Prava uplatnica HUB-3A (slika kao u mailu narudžbe) ugrađena u mail; datoteka se briše
	 * nakon slanja. Vraća src ili '' (tada mail ima uplatnicu u HTML-u).
	 */
	public static function slip( array $d, string $code_path ): string {
		if ( ! method_exists( 'Plan_A_Email', 'slip_image' ) ) {
			return '';
		}
		$file = trailingslashit( get_temp_dir() ) . 'pac-uplatnica-' . wp_generate_password( 10, false ) . '.jpg';
		if ( ! Plan_A_Email::slip_image( $d, $code_path, $file ) ) {
			return '';
		}
		self::$temp[] = $file;
		return self::embed( $file, 'pacslip' );
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

	/** Slike ugrađene u mail (cid => putanja), samo za sljedeće slanje. */
	private static $embed = array();

	/** Privremene datoteke (uplatnica) koje se brišu nakon slanja. */
	private static $temp = array();

	/**
	 * Slika u samom mailu (src="cid:…"): vidi se i kad datoteka na stranici više ne postoji.
	 * Vraća vrijednost za src.
	 */
	public static function embed( string $path, string $cid ): string {
		self::$embed[ $cid ] = $path;
		return 'cid:' . $cid;
	}

	public static function add_embedded( $phpmailer ) {
		foreach ( self::$embed as $cid => $path ) {
			if ( is_readable( $path ) ) {
				$phpmailer->addEmbeddedImage( $path, $cid, basename( $path ), 'base64', preg_match( '/\.jpe?g$/i', $path ) ? 'image/jpeg' : 'image/png' );
			}
		}
	}

	public static function cleanup() {
		foreach ( self::$temp as $file ) {
			wp_delete_file( $file );
		}
		self::$temp = array();
	}

	public static function send( string $to, string $subject, string $html, array $attachments = array(), array $extra = array() ): bool {
		if ( ! is_email( $to ) ) {
			self::$embed = array();
			self::cleanup();
			return false;
		}
		if ( self::$embed ) {
			add_action( 'phpmailer_init', array( __CLASS__, 'add_embedded' ) );
		}
		$ok = (bool) wp_mail( $to, $subject, $html, array_merge( self::headers(), $extra ), $attachments );
		remove_action( 'phpmailer_init', array( __CLASS__, 'add_embedded' ) );
		self::$embed = array();
		self::cleanup();
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
		return self::send( $m['email'], ( $reminder ? 'Podsjetnik: ' : '' ) . 'Potvrdi pristupnicu u Plan A', self::wrap( 'Potvrdi svoju pristupnicu', $inner, 'Plan A · pristupnica', 'mail' ) );
	}

	/**
	 * Podaci za uplatu članarine (HTML, za e-mail i stranicu).
	 */
	public static function payment_html( int $id, string $img_style = 'display:block;width:100%;max-width:420px;height:auto;margin:0 auto 14px', int $year = 0 ): string {
		$year = $year ?: self::fee_year( $id );
		$pay  = Plan_A_Clanstvo_Hub3::payment( $year );
		$code = Plan_A_Clanstvo_Hub3::for_member( $id, $year );
		$rows = array(
			'Iznos'          => Plan_A_Clanstvo_Data::money( $pay['iznos'] ),
			'Primatelj'      => $pay['primatelj'] . ', ' . $pay['adresa'],
			'IBAN'           => $pay['iban'],
			'Model i poziv'  => $pay['model'] . ' ' . $pay['poziv'],
			'Opis plaćanja'  => $pay['opis'],
		);
		$html = '<div style="background:#f3f8fc;border-radius:14px;padding:18px 18px 10px;margin:6px 0 16px">';
		$html .= '<p style="margin:0 0 12px;color:#12304b;font-size:17px;font-weight:bold">Članarina za ' . (int) $year . '.</p>';
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

	/**
	 * Godina članarine: tekuća, ili kasnija ako je član upisan za izlet u toj godini.
	 */
	public static function fee_year( int $id ): int {
		return max( (int) Plan_A_Clanstvo_Data::year(), (int) get_post_meta( $id, '_pac_fee_year', true ) );
	}

	/**
	 * Članarina za godinu izleta nije plaćena: e-mail s 2D kodom.
	 */
	public static function fee_request( int $id, int $year ): bool {
		$m = Plan_A_Clanstvo_Data::get_member( $id );
		if ( ! $m || ! is_email( $m['email'] ) ) {
			return false;
		}
		$code  = Plan_A_Clanstvo_Hub3::for_member( $id, $year );
		$inner = self::p( 'Pozdrav ' . esc_html( $m['ime'] ) . ',' )
			. self::p( 'hvala na prijavi na izlet! Članarina u udruzi Plan A za <strong>' . (int) $year . '.</strong> još nije plaćena. Uplati je na račun udruge (ne agencije), najjednostavnije skeniranjem 2D koda.' );
		$inner = Plan_A_Email::card( '', $inner ) . self::pay_card( $id, $year )
			. Plan_A_Email::card( '', Plan_A_Email::small( 'Članarina vrijedi kalendarsku godinu. Ako si je već platio/la, zanemari ovu poruku.' ) );
		$files = array(); // 2D kod je ugrađen u mail (pay_card)
		return self::send( $m['email'], 'Članarina Plan A za ' . (int) $year . '.', self::wrap( 'Članarina za ' . (int) $year . '.', $inner, 'Plan A · članarina' ), $files );
	}

	/**
	 * Siječanj: članarina za novu godinu (svim potvrđenim članovima koji je još nisu platili).
	 */
	public static function new_year_fee( int $id, int $year ): bool {
		$m = Plan_A_Clanstvo_Data::get_member( $id );
		if ( ! $m || ! is_email( $m['email'] ) ) {
			return false;
		}
		$code  = Plan_A_Clanstvo_Hub3::for_member( $id, $year );
		$inner = self::p( 'Pozdrav ' . esc_html( $m['ime'] ) . ',' )
			. self::p( 'želimo ti sretnu Novu godinu i hvala što si s nama! Počinje članska ' . (int) $year . '. godina u udruzi Plan A, pa ti šaljemo podatke za članarinu za <strong>' . (int) $year . '.</strong> Najjednostavnije je platiti skeniranjem 2D koda.' )
			. self::p( 'Članarina vrijedi do kraja kalendarske godine.' );
		$inner = Plan_A_Email::card( '', $inner ) . self::pay_card( $id, $year )
			. Plan_A_Email::card(
				'',
				self::button( 'Pogledaj izlete', home_url( '/izleti/' ) )
				. Plan_A_Email::small( 'Ako si članarinu već platio/la, zanemari ovu poruku. Ako više ne želiš biti član, javi nam se na info@srd-plan-a.hr.' )
				. Plan_A_Email::small( 'Ne želiš ovaj e-mail početkom godine? <a href="' . esc_url( Plan_A_Clanstvo_Ops::jan_url( $id ) ) . '" style="color:#5f6b77">Odjavi se s popisa za ovaj e-mail</a>.' )
			);
		$files = array(); // 2D kod je ugrađen u mail (pay_card)
		return self::send( $m['email'], 'Članarina Plan A za ' . (int) $year . '.', self::wrap( 'Članarina za ' . (int) $year . '.', $inner, 'Plan A · članarina' ), $files, array( 'List-Unsubscribe: <' . Plan_A_Clanstvo_Ops::jan_url( $id ) . '>' ) );
	}

	public static function confirmed( int $id ): bool {
		$m = Plan_A_Clanstvo_Data::get_member( $id );
		if ( ! $m ) {
			return false;
		}
		$code  = Plan_A_Clanstvo_Hub3::for_member( $id, self::fee_year( $id ) );
		$inner = self::p( 'Pozdrav ' . esc_html( $m['ime'] ) . ',' )
			. self::p( 'tvoja pristupnica je <strong>potvrđena</strong>. Dobrodošao/la u Plan A!' )
			. self::p( 'Članarina vrijedi za kalendarsku godinu. Člansku iskaznicu preuzimaš na prvom susretu s nama.' );
		$inner = Plan_A_Email::card( '', $inner ) . self::pay_card( $id, self::fee_year( $id ) )
			. Plan_A_Email::card( '', self::button( 'Pogledaj izlete', home_url( '/izleti/' ) ) );
		$files = array(); // 2D kod je ugrađen u mail (pay_card)
		return self::send( $m['email'], 'Pristupnica je potvrđena – dobrodošli u Plan A', self::wrap( 'Pristupnica je potvrđena', $inner, 'Plan A · pristupnica', 'check' ), $files );
	}
}
