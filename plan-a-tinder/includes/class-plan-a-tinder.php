<?php
/**
 * Postavke i brojanje prijavljenih (muški/ženski, raspon godina) za odabrane izlete.
 *
 * Izlet dobiva značajke kvačicom u okviru „Tinder – Tko dolazi” na ekranu izleta (meta _pat_on). WhatsApp grupa je jedna,
 * zajednička grupa Plan A za sve izlete (postavke; kad se napuni, upiše se poveznica nove grupe). Popis izleta iz 1.0.0 (opcija tours) prenosi se jednom u te kvačice.
 *
 * Podaci o osobi spremaju se uz stavku narudžbe (meta _pat_osobe: [['spol' => 'm'|'z', 'god' => 1990], …]),
 * istim redom kao popis sudionika košarice (_pac_osobe). Osoba se ne broji ako je narudžba otkazana,
 * vraćena ili neuspjela, ili ako ju je administrator u popisu označio „Ne broji” (meta _pat_x).
 *
 * Poticaj (meta izleta _pat_seed): administrator može dodati muškarce i žene s rasponom godina koji se na stranici
 * izleta pribrajaju pravim prijavama (shown()) dok je poticaj uključen. Ne rezervira mjesta u WooCommerceu.
 *
 * @package Plan_A_Tinder
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Tinder {

	const OPT      = 'plan_a_tinder';
	const STATUSES = array( 'on-hold', 'processing', 'completed', 'pending' );

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'migrate' ) );
		foreach ( array( 'woocommerce_order_status_changed', 'woocommerce_checkout_order_processed', 'woocommerce_delete_order', 'woocommerce_trash_order', 'woocommerce_update_order' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'flush' ) );
		}
	}

	public static function settings(): array {
		$s = get_option( self::OPT, array() );
		return wp_parse_args(
			is_array( $s ) ? $s : array(),
			array(
				'wa'    => '',
				'min'   => 3,
				'title' => 'Tko dolazi',
			)
		);
	}

	/** Je li izlet označen (kvačica na ekranu izleta). */
	public static function is_tour( int $tour ): bool {
		return $tour > 0 && (bool) get_post_meta( $tour, '_pat_on', true );
	}

	/** Svi označeni izleti (najnoviji prvi). */
	public static function tours(): array {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => 'any',
					'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
					'posts_per_page' => 100,
					'fields'         => 'ids',
					'meta_key'       => '_pat_on', // phpcs:ignore WordPress.DB.SlowDBQuery
					'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
					'orderby'        => 'date',
					'order'          => 'DESC',
				)
			)
		);
	}

	/** Jednom: izleti odabrani u 1.0.0 (opcija tours) dobivaju kvačicu na ekranu izleta. */
	public static function migrate() {
		$s = get_option( self::OPT, array() );
		if ( ! is_array( $s ) || empty( $s['tours'] ) ) {
			return;
		}
		foreach ( array_map( 'intval', (array) $s['tours'] ) as $tour ) {
			if ( $tour && get_post( $tour ) ) {
				update_post_meta( $tour, '_pat_on', '1' );
			}
		}
		unset( $s['tours'] );
		update_option( self::OPT, $s, false );
	}

	public static function flush() {
		foreach ( self::tours() as $tour ) {
			delete_transient( 'pat_stats_' . (int) $tour );
		}
	}

	/** Očisti WhatsApp poveznicu (bez dodataka za praćenje ?s=…&p=…); neispravna = ''. */
	public static function clean_wa( string $url ): string {
		$url = trim( $url );
		return preg_match( '#^https://(chat\.whatsapp\.com|wa\.me)/#i', $url ) ? (string) strtok( $url, '?' ) : '';
	}

	/** Zajednička WhatsApp grupa Plan A (postavke) – ista za sve uključene izlete. */
	public static function wa_url( int $tour = 0 ): string {
		return self::clean_wa( (string) self::settings()['wa'] );
	}

	/** Redni brojevi osoba stavke koje administrator ne broji. */
	public static function skipped( $item ): array {
		$x = $item->get_meta( '_pat_x' );
		return is_array( $x ) ? array_values( array_map( 'intval', $x ) ) : array();
	}

	/**
	 * Godina rođenja osobe iz pristupnice: datum upisan u košarici (novi član) ili zapis člana u Plan A članstvu (po e-mailu).
	 * 0 = nepoznato.
	 */
	public static function birth_year( array $p, string $fallback_email = '' ): int {
		$date = (string) ( $p['datum'] ?? '' );
		if ( class_exists( 'Plan_A_Clanstvo_Data' ) ) {
			$date = Plan_A_Clanstvo_Data::parse_date( $date );
			if ( '' === $date ) {
				$email = strtolower( trim( (string) ( $p['email'] ?? '' ) ) ) ?: strtolower( trim( $fallback_email ) );
				$id    = is_email( $email ) ? Plan_A_Clanstvo_Data::find( 'email', $email ) : 0;
				$m     = $id ? Plan_A_Clanstvo_Data::get_member( $id ) : null;
				$date  = $m ? Plan_A_Clanstvo_Data::parse_date( (string) ( $m['datum'] ?? '' ) ) : '';
			}
		} elseif ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$date = '';
		}
		$year = $date ? (int) substr( $date, 0, 4 ) : 0;
		return $year >= 1920 && $year <= (int) current_time( 'Y' ) ? $year : 0;
	}

	/** Godina izleta iz datuma termina u stavci (za računanje dobi). */
	private static function tour_year( $item ): int {
		$ts = strtotime( (string) $item->get_meta( '_ttbm_date' ) );
		return $ts ? (int) gmdate( 'Y', $ts ) : (int) current_time( 'Y' );
	}

	/**
	 * Sve prijavljene osobe izleta (za popis u adminu) – svaka osoba s imenom, spolom, godinom, dobi i stanjem.
	 */
	public static function people( int $tour ): array {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}
		$rows   = array();
		$orders = wc_get_orders(
			array(
				'limit'   => -1,
				'status'  => array_merge( self::STATUSES, array( 'cancelled', 'refunded', 'failed' ) ),
				'orderby' => 'date',
				'order'   => 'ASC',
			)
		);
		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}
			foreach ( $order->get_items() as $item_id => $item ) {
				if ( ! $item instanceof WC_Order_Item_Product || (int) $item->get_meta( '_ttbm_id' ) !== $tour ) {
					continue;
				}
				$ours  = (array) $item->get_meta( '_pat_osobe' );
				$names = (array) $item->get_meta( '_pac_osobe' );
				$skip  = self::skipped( $item );
				$count = max( count( $ours ), count( $names ), 1 );
				$year  = self::tour_year( $item );
				for ( $i = 0; $i < $count; $i++ ) {
					$o    = (array) ( $ours[ $i ] ?? array() );
					$n    = (array) ( $names[ $i ] ?? array() );
					$god  = (int) ( $o['god'] ?? 0 ) ?: self::birth_year( $n, 0 === $i ? $order->get_billing_email() : '' );
					$name = trim( (string) ( $n['ime'] ?? '' ) . ' ' . (string) ( $n['prezime'] ?? '' ) );
					if ( '' === $name && 0 === $i ) {
						$name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
					}
					$rows[] = array(
						'order'   => $order->get_id(),
						'item'    => (int) $item_id,
						'i'       => $i,
						'name'    => $name,
						'email'   => (string) ( $n['email'] ?? ( 0 === $i ? $order->get_billing_email() : '' ) ),
						'mobitel' => (string) ( $n['mobitel'] ?? ( 0 === $i ? $order->get_billing_phone() : '' ) ),
						'spol'    => in_array( $o['spol'] ?? '', array( 'm', 'z' ), true ) ? $o['spol'] : '',
						'god'     => $god,
						'age'     => $god ? $year - $god : 0,
						'status'  => $order->get_status(),
						'date'    => $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0,
						'skip'    => in_array( $i, $skip, true ),
						'counts'  => in_array( $order->get_status(), self::STATUSES, true ) && ! in_array( $i, $skip, true ),
					);
				}
			}
		}
		return $rows;
	}

	/**
	 * Brojke za stranicu izleta: ['m' => ['n' => 7, 'min' => 32, 'max' => 45], 'z' => …, 'total' => 13].
	 * Spremaju se dok se ne promijeni neka narudžba (najviše 6 sati).
	 */
	public static function stats( int $tour ): array {
		$cached = get_transient( 'pat_stats_' . $tour );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$out = array(
			'm'     => array( 'n' => 0, 'ages' => array() ),
			'z'     => array( 'n' => 0, 'ages' => array() ),
			'total' => 0,
		);
		foreach ( self::people( $tour ) as $p ) {
			if ( ! $p['counts'] ) {
				continue;
			}
			$out['total']++;
			if ( '' !== $p['spol'] ) {
				$out[ $p['spol'] ]['n']++;
				if ( $p['age'] > 0 && $p['age'] < 110 ) {
					$out[ $p['spol'] ]['ages'][] = $p['age'];
				}
			}
		}
		foreach ( array( 'm', 'z' ) as $k ) {
			$ages              = $out[ $k ]['ages'];
			$out[ $k ]['min']  = $ages ? min( $ages ) : 0;
			$out[ $k ]['max']  = $ages ? max( $ages ) : 0;
			$out[ $k ]['ages'] = count( $ages );
		}
		set_transient( 'pat_stats_' . $tour, $out, 6 * HOUR_IN_SECONDS );
		return $out;
	}

	/** Poticaj izleta: ['on' => bool, 'm' => 2, 'm_min' => 28, 'm_max' => 40, 'z' => …]. */
	public static function seed( int $tour ): array {
		$s   = get_post_meta( $tour, '_pat_seed', true );
		$s   = is_array( $s ) ? $s : array();
		$out = array( 'on' => ! empty( $s['on'] ) );
		foreach ( array( 'm', 'z' ) as $k ) {
			$out[ $k ]          = max( 0, min( 99, (int) ( $s[ $k ] ?? 0 ) ) );
			$lo                 = max( 0, min( 99, (int) ( $s[ $k . '_min' ] ?? 0 ) ) );
			$hi                 = max( 0, min( 99, (int) ( $s[ $k . '_max' ] ?? 0 ) ) );
			$out[ $k . '_min' ] = $lo && $hi ? min( $lo, $hi ) : ( $lo ?: $hi );
			$out[ $k . '_max' ] = $lo && $hi ? max( $lo, $hi ) : ( $lo ?: $hi );
		}
		return $out;
	}

	/** Je li poticaj uključen i ima li ikoga za dodati. */
	public static function seed_active( int $tour ): bool {
		$s = self::seed( $tour );
		return $s['on'] && ( $s['m'] || $s['z'] );
	}

	/**
	 * Brojke za stranicu izleta: prave prijave + poticaj (ako je uključen). Godine poticaja šire raspon
	 * i broje se kao osobe s upisanom godinom (za prag prikaza raspona).
	 */
	public static function shown( int $tour ): array {
		$st         = self::stats( $tour );
		$st['real'] = $st['total'];
		if ( ! self::seed_active( $tour ) ) {
			return $st;
		}
		$seed = self::seed( $tour );
		foreach ( array( 'm', 'z' ) as $k ) {
			$n = $seed[ $k ];
			if ( ! $n ) {
				continue;
			}
			$st[ $k ]['n'] += $n;
			$st['total']   += $n;
			if ( $seed[ $k . '_min' ] ) {
				$st[ $k ]['min']   = $st[ $k ]['min'] ? min( $st[ $k ]['min'], $seed[ $k . '_min' ] ) : $seed[ $k . '_min' ];
				$st[ $k ]['max']   = max( $st[ $k ]['max'], $seed[ $k . '_max' ] );
				// Raspon koji je administrator upisao prikazuje se uvijek (prag „od N osoba” štiti samo prave prijave).
				$st[ $k ]['ages'] = max( $st[ $k ]['ages'] + $n, (int) self::settings()['min'] );
			}
		}
		return $st;
	}

	/** Ukupno mjesta izleta (karte bez „prve rate”), 0 = nepoznato. */
	public static function seats( int $tour ): int {
		$total = 0;
		foreach ( (array) get_post_meta( $tour, 'ttbm_ticket_type', true ) as $t ) {
			if ( is_array( $t ) && false === stripos( (string) ( $t['ticket_type_name'] ?? '' ), 'prve rate' ) ) {
				$total += (int) ( $t['ticket_type_qty'] ?? 0 );
			}
		}
		return $total;
	}
}
