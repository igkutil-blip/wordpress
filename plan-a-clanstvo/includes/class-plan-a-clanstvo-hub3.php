<?php
/**
 * 2D kod (HUB3, PDF417) za članarinu: osobni kod za svakog člana na račun udruge.
 * Kod izrađuje isti servis koji koristi dodatak WSB HUB3 (hub3.bigfish.software).
 *
 * @package Plan_A_Clanstvo
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Clanstvo_Hub3 {

	const API = 'https://hub3.bigfish.software/api/v2/barcode';

	private static function dir(): array {
		$up   = wp_upload_dir();
		$path = trailingslashit( $up['basedir'] ) . 'plan-a-clanstvo';
		$url  = trailingslashit( $up['baseurl'] ) . 'plan-a-clanstvo';
		if ( ! is_dir( $path ) ) {
			wp_mkdir_p( $path );
			// Bez popisa mape; slike se otvaraju samo s točnim (nasumičnim) nazivom.
			file_put_contents( $path . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		return array( $path, $url );
	}

	private static function cut( string $text, int $max ): string {
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
	}

	/**
	 * Podaci za uplatu (za e-mail i stranicu potvrde).
	 */
	public static function payment( int $year = 0 ): array {
		$s = Plan_A_Clanstvo_Data::get();
		return array(
			'iznos'     => (float) $s['iznos'],
			'primatelj' => (string) $s['primatelj'],
			'adresa'    => trim( $s['adresa'] . ', ' . $s['mjesto'], ', ' ),
			'iban'      => (string) $s['iban'],
			'model'     => 'HR' . preg_replace( '/\D/', '', (string) $s['model'] ),
			'poziv'     => Plan_A_Clanstvo_Data::fill( (string) $s['poziv'], $year ),
			'opis'      => Plan_A_Clanstvo_Data::fill( (string) $s['opis'], $year ),
		);
	}

	/**
	 * 2D kod za ostatak narudžbe (druga rata): iznos po narudžbi, poziv na broj = broj narudžbe.
	 *
	 * @return array{url: string, path: string}|null
	 */
	public static function for_order( WC_Order $order, float $amount ): ?array {
		$s = Plan_A_Clanstvo_Data::get();
		// Nikad ne šalje zajednički kod (slika ili rezerva): on nosi iznos članarine, ne ostatka.
		if ( 'ne' === $s['barcode'] || 'slika' === $s['barcode'] || $amount <= 0 ) {
			return null;
		}
		list( $dir, $base ) = self::dir();
		$body = array(
			'renderer' => 'image',
			'options'  => array(
				'format'  => 'png',
				'color'   => '#000000',
				'padding' => 10,
				'scale'   => 3,
				'ratio'   => 3,
			),
			'data'     => array(
				'amount'      => (int) round( $amount * 100 ),
				'currency'    => 'EUR',
				'sender'      => array(
					'name'   => self::cut( trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ), 30 ),
					'street' => self::cut( trim( $order->get_billing_address_1() ), 27 ),
					'place'  => self::cut( trim( $order->get_billing_postcode() . ' ' . $order->get_billing_city() ), 27 ),
				),
				'receiver'    => array(
					'name'      => self::cut( $s['primatelj'], 25 ),
					'street'    => self::cut( $s['adresa'], 25 ),
					'place'     => self::cut( $s['mjesto'], 27 ),
					'iban'      => preg_replace( '/\s+/', '', (string) $s['iban'] ),
					'model'     => preg_replace( '/\D/', '', (string) $s['model'] ),
					'reference' => self::cut( (string) $order->get_order_number(), 22 ),
				),
				'purpose'     => 'OTHR',
				'description' => self::cut( 'Druga rata ' . $order->get_order_number(), 35 ),
			),
		);
		$res = wp_remote_post(
			self::API,
			array(
				'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'    => wp_json_encode( $body ),
				'timeout' => 15,
			)
		);
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) || '' === wp_remote_retrieve_body( $res ) ) {
			return null;
		}
		$file = 'druga-rata-' . $order->get_order_number() . '-' . wp_generate_password( 12, false ) . '.png';
		file_put_contents( $dir . '/' . $file, wp_remote_retrieve_body( $res ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return array(
			'url'  => $base . '/' . $file,
			'path' => $dir . '/' . $file,
		);
	}

	/**
	 * URL i putanja 2D koda za člana (izrađuje ga ako ga još nema za tu godinu).
	 *
	 * @return array{url: string, path: string}|null
	 */
	public static function for_member( int $id, int $year = 0 ): ?array {
		$s    = Plan_A_Clanstvo_Data::get();
		$year = $year ?: Plan_A_Clanstvo_Data::year();
		if ( 'ne' === $s['barcode'] ) {
			return null;
		}
		if ( 'slika' === $s['barcode'] ) {
			$url = esc_url_raw( (string) $s['barcode_slika'] );
			return $url ? array(
				'url'  => $url,
				'path' => '',
			) : null;
		}
		list( $dir, $base ) = self::dir();
		$file = (string) get_post_meta( $id, '_pac_barcode', true );
		if ( $file && (int) get_post_meta( $id, '_pac_barcode_year', true ) === $year && file_exists( $dir . '/' . $file ) ) {
			return array(
				'url'  => $base . '/' . $file,
				'path' => $dir . '/' . $file,
			);
		}
		$m = Plan_A_Clanstvo_Data::get_member( $id );
		if ( ! $m ) {
			return null;
		}
		$pay  = self::payment( $year );
		$body = array(
			'renderer' => 'image',
			'options'  => array(
				'format'  => 'png',
				'color'   => '#000000',
				'padding' => 10,
				'scale'   => 3,
				'ratio'   => 3,
			),
			'data'     => array(
				'amount'      => (int) round( $pay['iznos'] * 100 ),
				'currency'    => 'EUR',
				'sender'      => array(
					'name'   => self::cut( $m['ime'] . ' ' . $m['prezime'], 30 ),
					'street' => self::cut( $m['adresa'], 27 ),
					'place'  => self::cut( $m['mjesto'], 27 ),
				),
				'receiver'    => array(
					'name'      => self::cut( $s['primatelj'], 25 ),
					'street'    => self::cut( $s['adresa'], 25 ),
					'place'     => self::cut( $s['mjesto'], 27 ),
					'iban'      => preg_replace( '/\s+/', '', (string) $s['iban'] ),
					'model'     => preg_replace( '/\D/', '', (string) $s['model'] ),
					'reference' => self::cut( $pay['poziv'], 22 ),
				),
				'purpose'     => 'OTHR',
				'description' => self::cut( $pay['opis'], 35 ),
			),
		);
		$res = wp_remote_post(
			self::API,
			array(
				'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'    => wp_json_encode( $body ),
				'timeout' => 15,
			)
		);
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) || '' === wp_remote_retrieve_body( $res ) ) {
			// Ako servis ne radi, šalje se slika sa stranice (isti račun udruge).
			$url = esc_url_raw( (string) $s['barcode_slika'] );
			return $url ? array(
				'url'  => $url,
				'path' => '',
			) : null;
		}
		self::delete( $id );
		$file = 'clanarina-' . $year . '-' . wp_generate_password( 20, false ) . '.png';
		file_put_contents( $dir . '/' . $file, wp_remote_retrieve_body( $res ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		update_post_meta( $id, '_pac_barcode', $file );
		update_post_meta( $id, '_pac_barcode_year', $year );
		return array(
			'url'  => $base . '/' . $file,
			'path' => $dir . '/' . $file,
		);
	}

	public static function delete( int $id ) {
		$file = (string) get_post_meta( $id, '_pac_barcode', true );
		if ( $file && preg_match( '/^[A-Za-z0-9_.-]+$/', $file ) ) {
			list( $dir ) = self::dir();
			if ( file_exists( $dir . '/' . $file ) ) {
				unlink( $dir . '/' . $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
		}
		delete_post_meta( $id, '_pac_barcode' );
		delete_post_meta( $id, '_pac_barcode_year' );
	}
}
