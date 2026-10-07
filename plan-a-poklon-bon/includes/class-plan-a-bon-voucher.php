<?php
/**
 * Bon = WooCommerce kupon (fiksni popust na košaricu, jednokratan) s podacima bona u
 * meta podacima kupona. Ovdje su izrada bona, podaci, datoteke (PDF i PNG) i e-mail.
 *
 * @package Plan_A_Poklon_Bon
 */

defined( 'ABSPATH' ) || exit;

class Plan_A_Bon_Voucher {

	/** Znakovi koda: bez 0, O, 1, I i L (lako se zamijene). */
	const CHARS = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

	const DIR = 'plan-a-poklon-bon';

	/* ------------------------------------------------------------------ */
	/* Izrada                                                               */
	/* ------------------------------------------------------------------ */

	public static function generate_code(): string {
		$max = strlen( self::CHARS ) - 1;
		for ( $attempt = 0; $attempt < 20; $attempt++ ) {
			$code = 'PLANA-';
			for ( $i = 0; $i < 8; $i++ ) {
				$code .= self::CHARS[ random_int( 0, $max ) ] . ( 3 === $i ? '-' : '' );
			}
			if ( ! wc_get_coupon_id_by_code( $code ) ) {
				return $code;
			}
		}
		throw new RuntimeException( 'Nije moguće izraditi jedinstveni kod bona.' );
	}

	/**
	 * Izdaje bon (kupon) i vraća ID kupona.
	 *
	 * @param array $args amount, to, from, message, email, buyer, order_id, item_id, reason, expires (timestamp), parent.
	 */
	public static function create( array $args ): int {
		$amount = round( (float) ( $args['amount'] ?? 0 ), 2 );
		if ( $amount <= 0 ) {
			throw new InvalidArgumentException( 'Iznos bona mora biti veći od nule.' );
		}
		$code    = self::generate_code();
		$expires = ! empty( $args['expires'] ) ? (int) $args['expires'] : self::default_expiry();

		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( 'fixed_cart' );
		$coupon->set_amount( $amount );
		$coupon->set_usage_limit( 1 );
		$coupon->set_individual_use( false );
		$coupon->set_date_expires( $expires );
		$coupon->set_excluded_product_ids( array_filter( array( Plan_A_Bon::product_id( false ) ) ) );
		$coupon->set_description(
			sprintf(
				'Poklon bon za %1$s%2$s',
				(string) ( $args['to'] ?? '' ),
				! empty( $args['order_id'] ) ? ' (narudžba #' . (int) $args['order_id'] . ')' : ( ! empty( $args['reason'] ) ? ' – ' . $args['reason'] : '' )
			)
		);
		$coupon->update_meta_data( '_papb', 1 );
		$coupon->update_meta_data( '_papb_to', (string) ( $args['to'] ?? '' ) );
		$coupon->update_meta_data( '_papb_from', (string) ( $args['from'] ?? '' ) );
		$coupon->update_meta_data( '_papb_message', (string) ( $args['message'] ?? '' ) );
		$coupon->update_meta_data( '_papb_email', (string) ( $args['email'] ?? '' ) );
		$coupon->update_meta_data( '_papb_buyer', (string) ( $args['buyer'] ?? '' ) );
		$coupon->update_meta_data( '_papb_order', (int) ( $args['order_id'] ?? 0 ) );
		$coupon->update_meta_data( '_papb_item', (int) ( $args['item_id'] ?? 0 ) );
		$coupon->update_meta_data( '_papb_reason', (string) ( $args['reason'] ?? '' ) );
		$coupon->update_meta_data( '_papb_parent', (int) ( $args['parent'] ?? 0 ) );
		$coupon->update_meta_data( '_papb_initial', $amount );
		$coupon->update_meta_data( '_papb_issued', time() );
		$coupon->update_meta_data( '_papb_token', wp_generate_password( 32, false ) );
		$coupon->save();

		return (int) $coupon->get_id();
	}

	/**
	 * Kraj dana (lokalno vrijeme) za zadani broj mjeseci od danas.
	 */
	public static function default_expiry(): int {
		$date = new DateTime( 'now', wp_timezone() );
		$date->modify( '+' . (int) Plan_A_Bon_Settings::get( 'months' ) . ' months' );
		$date->setTime( 23, 59, 59 );
		return $date->getTimestamp();
	}

	/* ------------------------------------------------------------------ */
	/* Podaci                                                               */
	/* ------------------------------------------------------------------ */

	public static function is_voucher( int $coupon_id ): bool {
		return $coupon_id > 0 && 'shop_coupon' === get_post_type( $coupon_id ) && (bool) get_post_meta( $coupon_id, '_papb', true );
	}

	/**
	 * ID bona prema kodu (bilo kojim slovima) ili 0.
	 */
	public static function find( string $code ): int {
		$code = trim( $code );
		if ( '' === $code || strlen( $code ) > 40 ) {
			return 0;
		}
		$id = (int) wc_get_coupon_id_by_code( $code );
		return self::is_voucher( $id ) ? $id : 0;
	}

	public static function get( int $coupon_id ): array {
		if ( ! self::is_voucher( $coupon_id ) ) {
			return array();
		}
		$coupon  = new WC_Coupon( $coupon_id );
		$expires = $coupon->get_date_expires();
		$data    = array(
			'id'      => $coupon_id,
			'code'    => strtoupper( $coupon->get_code() ),
			'amount'  => (float) $coupon->get_amount(),
			'initial' => (float) $coupon->get_meta( '_papb_initial' ),
			'to'      => (string) $coupon->get_meta( '_papb_to' ),
			'from'    => (string) $coupon->get_meta( '_papb_from' ),
			'message' => (string) $coupon->get_meta( '_papb_message' ),
			'email'   => (string) $coupon->get_meta( '_papb_email' ),
			'buyer'   => (string) $coupon->get_meta( '_papb_buyer' ),
			'order'   => (int) $coupon->get_meta( '_papb_order' ),
			'reason'  => (string) $coupon->get_meta( '_papb_reason' ),
			'parent'  => (int) $coupon->get_meta( '_papb_parent' ),
			'child'   => (int) $coupon->get_meta( '_papb_child' ),
			'issued'  => (int) $coupon->get_meta( '_papb_issued' ),
			'expires' => $expires ? $expires->getTimestamp() : 0,
			'used'    => (int) $coupon->get_usage_count(),
			'limit'   => (int) $coupon->get_usage_limit(),
			'token'   => (string) $coupon->get_meta( '_papb_token' ),
		);
		$data['status'] = self::status( $data );
		return $data;
	}

	/**
	 * aktivan, iskoristen ili istekao.
	 */
	public static function status( array $v ): string {
		if ( $v['limit'] > 0 && $v['used'] >= $v['limit'] ) {
			return 'iskoristen';
		}
		if ( $v['expires'] && time() > $v['expires'] ) {
			return 'istekao';
		}
		return 'aktivan';
	}

	public static function status_label( string $status ): string {
		$labels = array(
			'aktivan'    => 'aktivan',
			'iskoristen' => 'iskorišten',
			'istekao'    => 'istekao',
		);
		return $labels[ $status ] ?? $status;
	}

	/**
	 * Adresa iz QR koda: /izleti/?bon=KOD.
	 */
	public static function redeem_url( string $code ): string {
		return add_query_arg( 'bon', rawurlencode( $code ), home_url( '/izleti/' ) );
	}

	/**
	 * Iznos kao tekst prema postavkama trgovine: "90,00 €".
	 */
	public static function money( float $amount ): string {
		return trim( html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Iznos za bon: "90 €" ili "92,50 €".
	 */
	public static function money_short( float $amount ): string {
		$text = abs( $amount - round( $amount ) ) < 0.005 ? number_format( $amount, 0, ',', '.' ) : number_format( $amount, 2, ',', '.' );
		return $text . ' €';
	}

	/**
	 * Datum na hrvatskom: "7. listopada 2027.".
	 */
	public static function hr_date( int $timestamp ): string {
		if ( ! $timestamp ) {
			return '';
		}
		$months = array( 1 => 'siječnja', 'veljače', 'ožujka', 'travnja', 'svibnja', 'lipnja', 'srpnja', 'kolovoza', 'rujna', 'listopada', 'studenoga', 'prosinca' );
		$date   = ( new DateTime( '@' . $timestamp ) )->setTimezone( wp_timezone() );
		return (int) $date->format( 'j' ) . '. ' . $months[ (int) $date->format( 'n' ) ] . ' ' . $date->format( 'Y' ) . '.';
	}

	/* ------------------------------------------------------------------ */
	/* Datoteke                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Zaštićena mapa: uploads/plan-a-poklon-bon (zabrana izravnog pristupa za Apache;
	 * nazivi datoteka sadrže 32 nasumična znaka, pa se ne mogu pogoditi ni na Nginxu).
	 */
	public static function dir(): string {
		$uploads = wp_upload_dir( null, false );
		$dir     = trailingslashit( $uploads['basedir'] ) . self::DIR . '/';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( ! file_exists( $dir . '.htaccess' ) ) {
			file_put_contents( $dir . '.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		if ( ! file_exists( $dir . 'index.php' ) ) {
			file_put_contents( $dir . 'index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		return $dir;
	}

	/**
	 * Putanja do PDF-a ili PNG-a bona; izrađuje datoteke ako ne postoje.
	 */
	public static function file( int $coupon_id, string $format, bool $regenerate = false ): string {
		$v = self::get( $coupon_id );
		if ( ! $v || ! in_array( $format, array( 'pdf', 'png' ), true ) ) {
			return '';
		}
		if ( '' === $v['token'] ) {
			$v['token'] = wp_generate_password( 32, false );
			update_post_meta( $coupon_id, '_papb_token', $v['token'] );
		}
		$base = self::dir() . 'bon-' . strtolower( $v['code'] ) . '-' . $v['token'];
		if ( $regenerate || ! file_exists( $base . '.pdf' ) || ! file_exists( $base . '.png' ) ) {
			Plan_A_Bon_Render::files( $v, $base );
		}
		return file_exists( $base . '.' . $format ) ? $base . '.' . $format : '';
	}

	/**
	 * Šalje datoteku pregledniku (nakon provjere prava u pozivatelju).
	 */
	public static function stream( int $coupon_id, string $format ) {
		$path = self::file( $coupon_id, $format );
		if ( '' === $path ) {
			wp_die( esc_html__( 'Datoteka bona nije dostupna.', 'plan-a-poklon-bon' ), '', array( 'response' => 404 ) );
		}
		$v = self::get( $coupon_id );
		nocache_headers();
		header( 'Content-Type: ' . ( 'pdf' === $format ? 'application/pdf' : 'image/png' ) );
		header( 'Content-Disposition: attachment; filename="Poklon-bon-' . $v['code'] . '.' . $format . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* E-mail                                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Šalje bon e-mailom s PDF-om i PNG-om u privitku.
	 *
	 * @param string $type issued (novi bon) ili remainder (ostatak bona).
	 */
	public static function send( int $coupon_id, string $to = '', string $type = 'issued', array $extra = array() ): bool {
		$v  = self::get( $coupon_id );
		$to = '' !== $to ? $to : $v['email'];
		if ( ! $v || ! is_email( $to ) ) {
			return false;
		}
		$replace = array(
			'{kupac}'      => (string) ( $extra['buyer'] ?? $v['buyer'] ),
			'{za}'         => $v['to'],
			'{od}'         => $v['from'],
			'{iznos}'      => self::money( $v['amount'] ),
			'{kod}'        => $v['code'],
			'{vrijedi_do}' => self::hr_date( $v['expires'] ),
			'{stari_kod}'  => (string) ( $extra['old_code'] ?? '' ),
			'{narudzba}'   => (string) ( $extra['order_number'] ?? '' ),
		);
		if ( 'remainder' === $type ) {
			$subject = 'Ostatak tvog poklon bona: ' . $v['code'];
			$text    = "Pozdrav {kupac},\n\nu narudžbi #{narudzba} iskorišten je dio poklon bona {stari_kod}. Ostatak od {iznos} prebacili smo na novi bon s kodom {kod}, koji vrijedi do {vrijedi_do}.\n\nNovi bon je u privitku. Iskoristi ga na isti način: odaberi izlet na srd-plan-a.hr i u košarici upiši kod.\n\nVidimo se na izletu!\nPlan A";
		} else {
			$subject = (string) Plan_A_Bon_Settings::get( 'email_subject' );
			$text    = (string) Plan_A_Bon_Settings::get( 'email_text' );
		}
		$text    = str_replace( '{vrijedi_do}.', '{vrijedi_do}', $text ); // datum već završava točkom
		$text    = strtr( $text, $replace );
		$text    = preg_replace( '/[ \t]+,/', ',', $text ); // "Pozdrav ," kad ime kupca nije poznato.
		$subject = strtr( $subject, $replace );

		$body  = wpautop( esc_html( $text ) );
		$body .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:18px 0;"><tr><td style="background:#12304b;border-radius:10px;padding:14px 20px;color:#ffffff;font-family:Arial,Helvetica,sans-serif;">'
			. '<div style="font-size:12px;letter-spacing:1px;color:#9fd3f0;">KOD BONA</div>'
			. '<div style="font-size:24px;font-weight:bold;letter-spacing:2px;">' . esc_html( $v['code'] ) . '</div>'
			. '<div style="font-size:14px;margin-top:4px;">' . esc_html( self::money( $v['amount'] ) . ' · vrijedi do ' . self::hr_date( $v['expires'] ) ) . '</div>'
			. '</td></tr></table>';

		$attachments = array_filter( array( self::file( $coupon_id, 'pdf' ), self::file( $coupon_id, 'png' ) ) );
		$mailer      = WC()->mailer();
		$message     = $mailer->wrap_message( 'Poklon bon za izlet', $body );
		$sent        = (bool) $mailer->send( $to, $subject, $message, "Content-Type: text/html\r\n", $attachments );
		if ( $sent ) {
			update_post_meta( $coupon_id, '_papb_sent', time() );
		}
		return $sent;
	}
}
