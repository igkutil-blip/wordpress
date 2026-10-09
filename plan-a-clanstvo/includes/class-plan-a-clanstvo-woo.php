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
		// Blok "Članstvo Plan A" u e-mailu "Nova narudžba" (dodatak Plan A košarica); uvijek.
		add_filter( 'plan_a_kosarica_member_rows', array( __CLASS__, 'member_rows' ), 10, 4 );
		add_filter( 'plan_a_kosarica_email_notice', array( __CLASS__, 'email_notice' ), 10, 3 );
		if ( ! (int) Plan_A_Clanstvo_Data::value( 'provjera' ) ) {
			return;
		}
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'mark' ), 20, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'mark' ), 20, 1 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'admin_box' ) );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'column' ), 20 );
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'column' ), 20 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'column_value' ), 10, 2 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'column_value' ), 10, 2 );
	}

	/**
	 * Stanje članstva kupca za e-mail vama: pristupnica, članarina za godinu izleta, iskaznica.
	 *
	 * @return array<int, array{0: string, 1: string}>
	 */
	public static function member_rows( $rows, $order, $year, $mode ) {
		if ( ! $order instanceof WC_Order || self::skip( $order ) ) {
			return $rows;
		}
		if ( 'admin' !== $mode ) {
			return (int) Plan_A_Clanstvo_Data::value( 'provjera' ) ? self::customer_rows( $rows, $order, (int) $year, $mode ) : $rows;
		}
		$ok   = static fn( $t ) => '<span style="color:#1e7d3a;font-weight:700;">&#10003; ' . esc_html( $t ) . '</span>';
		$no   = static fn( $t ) => '<span style="color:#b32d2e;font-weight:700;">&#10007; ' . esc_html( $t ) . '</span>';
		$wait = static fn( $t ) => '<span style="color:#b26200;font-weight:700;">' . esc_html( $t ) . '</span>';
		$year = (int) $year;
		$id   = Plan_A_Clanstvo_Data::find( 'email', (string) $order->get_billing_email() );
		if ( ! $id ) {
			// Član bez e-maila u popisu (npr. iz starih tablica članarina): moguće ista osoba po imenu.
			$guess = get_posts(
				array(
					'post_type'      => Plan_A_Clanstvo_Data::CPT,
					'post_status'    => 'any',
					'title'          => trim( $order->get_formatted_billing_full_name() ),
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			$rows[] = array( 'Pristupnica', $no( 'nema pristupnice s ovim e-mailom' ) );
			if ( $guess ) {
				$g      = Plan_A_Clanstvo_Data::get_member( (int) $guess[0] );
				$rows[] = array( 'Moguće', esc_html( 'član istog imena: Br. ' . $g['broj'] . ( '' !== $g['email'] ? ', ' . $g['email'] : ', bez e-maila' ) ) . ' – provjeriti' );
				$id     = (int) $guess[0];
			} else {
				return $rows;
			}
		} else {
			$m      = Plan_A_Clanstvo_Data::get_member( $id );
			$new    = $order->get_meta( '_pac_nova' ) ? 'nova, upisana u košarici – ' : '';
			$rows[] = array( 'Pristupnica', 'potvrdeno' === $m['status'] ? $ok( 'potvrđena (Br. ' . $m['broj'] . ')' ) : $wait( $new . 'čeka potvrdu kupca (Br. ' . $m['broj'] . ')' ) );
		}
		$rows[] = array( 'Članarina ' . $year, Plan_A_Clanstvo_Data::fee_paid( $id, $year ) ? $ok( 'plaćena' ) : $no( 'nije plaćena' ) );
		$rows[] = array( 'Iskaznica', get_post_meta( $id, '_pac_kartica', true ) ? $ok( 'uručena' ) : esc_html( 'nije uručena' ) );
		$pull   = get_option( 'plan_a_clanstvo_pull' );
		if ( is_array( $pull ) ) {
			$rows[] = array( '', '<span style="color:#5f6b77;font-size:13px;">' . esc_html( 'Članarina i iskaznica prema Google tablici, stanje ' . Plan_A_Clanstvo_Data::hr_datetime( (string) $pull['time'] ) . '.' ) . '</span>' );
		}
		return $rows;
	}

	/**
	 * Blok "Članstvo Plan A" za kupca (e-mail i stranica "Hvala"). Gumb za potvrdu samo u
	 * e-mailu (stiže na adresu člana), ne na stranici.
	 */
	private static function customer_rows( array $rows, WC_Order $order, int $year, string $mode ): array {
		$ok   = static fn( $t ) => '<span style="color:#1e7d3a;font-weight:700;">&#10003; ' . esc_html( $t ) . '</span>';
		$wait = static fn( $t ) => '<span style="color:#b26200;font-weight:700;">' . esc_html( $t ) . '</span>';
		$id   = Plan_A_Clanstvo_Data::find( 'email', (string) $order->get_billing_email() );
		$m    = $id ? Plan_A_Clanstvo_Data::get_member( $id ) : null;
		if ( ! $m ) {
			return $rows;
		}
		if ( 'potvrdeno' === $m['status'] ) {
			$rows[] = array( 'Pristupnica', $ok( 'potvrđena' ) );
			$rows[] = array( 'Članarina ' . $year, Plan_A_Clanstvo_Data::fee_paid( (int) $m['id'], $year ) ? $ok( 'plaćena' ) : $wait( 'nije plaćena' ) . ' – ' . esc_html( 'uplatnicu s 2D kodom poslali smo ti posebnim e-mailom.' ) );
			return $rows;
		}
		$url    = (string) $order->get_meta( '_pac_confirm_url' );
		$rows[] = array( 'Pristupnica', $wait( 'čeka tvoju potvrdu' ) );
		if ( 'thankyou' !== $mode && '' !== $url ) {
			$rows[] = array( '', '<a href="' . esc_url( $url ) . '" style="display:inline-block;margin:6px 0 4px;padding:12px 22px;border-radius:10px;background:#1e9e4a;color:#ffffff;font-weight:700;text-decoration:none;">Potvrđujem pristupnicu</a><br><span style="color:#5f6b77;font-size:13px;">Klikom potvrđuješ da si pristupnicu ispunio/la ti i da prihvaćaš Izjavu člana.</span>' );
		} elseif ( 'thankyou' === $mode ) {
			$rows[] = array( '', esc_html( 'Gumb za potvrdu stiže ti u e-mailu o prijavi.' ) );
		}
		$rows[] = array( 'Članarina ' . $year, esc_html( 'uplatnicu s 2D kodom dobivaš nakon potvrde pristupnice.' ) );
		return $rows;
	}

	/**
	 * Narančasti okvir na vrhu e-maila kupcu: pristupnica čeka potvrdu.
	 */
	public static function email_notice( $html, $order, $mode ) {
		if ( 'admin' === $mode || ! $order instanceof WC_Order || self::skip( $order ) || ! (int) Plan_A_Clanstvo_Data::value( 'provjera' ) ) {
			return $html;
		}
		$url = (string) $order->get_meta( '_pac_confirm_url' );
		$id  = Plan_A_Clanstvo_Data::find( 'email', (string) $order->get_billing_email() );
		$m   = $id ? Plan_A_Clanstvo_Data::get_member( $id ) : null;
		if ( '' === $url || ! $m || 'potvrdeno' === $m['status'] ) {
			return $html;
		}
		$days = (int) Plan_A_Clanstvo_Data::value( 'valid_days' );
		return $html
			. '<p style="margin:0 0 8px;color:#b25d00;font-size:13px;font-weight:700;letter-spacing:1px;text-transform:uppercase;">Važno – još jedan korak</p>'
			. '<p style="margin:0 0 10px;color:#12304b;font-size:20px;font-weight:800;line-height:1.3;">Potvrdi svoju pristupnicu u udrugu Plan A</p>'
			. '<p style="margin:0 0 14px;">Prijava na izlet je zaprimljena, ali članstvo je uvjet za sudjelovanje. Pristupnica vrijedi tek kad je potvrdiš klikom na gumb:</p>'
			. '<p style="margin:0 0 14px;text-align:center;"><a href="' . esc_url( $url ) . '" style="display:inline-block;padding:15px 30px;border-radius:12px;background:#1e9e4a;color:#ffffff;font-size:17px;font-weight:800;text-decoration:none;">Potvrđujem pristupnicu</a></p>'
			. '<p style="margin:0;color:#5f6b77;font-size:13px;">Klikom potvrđuješ da si pristupnicu ispunio/la ti i da prihvaćaš Izjavu člana. Poveznica vrijedi ' . (int) $days . ' dana. Nakon potvrde stiže ti e-mail s 2D kodom za članarinu.</p>';
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
