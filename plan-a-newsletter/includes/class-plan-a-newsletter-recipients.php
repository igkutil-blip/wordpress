<?php
/**
 * Primatelji: grupe iz baze članova (Plan A članstvo) i narudžbi izleta (WooCommerce).
 * Popis se slaže tek pri slanju i ostaje samo na stranici.
 *
 * @package Plan_A_Newsletter
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Newsletter_Recipients {

	const GROUPS = array(
		'clanovi'   => 'Svi potvrđeni članovi',
		'clanarina' => 'Članovi s plaćenom članarinom za ovu godinu',
		'izlet'     => 'Sudionici izleta',
		'kupci'     => 'Sudionici izleta u zadnje 2 godine koji nisu članovi',
		'vanjski'   => 'Dodatne adrese (upisane ručno)',
	);

	/** Član se odjavio s e-mailova (isto polje koristi siječanjski e-mail za članarinu). */
	private static function no_mail_key(): string {
		return class_exists( 'Plan_A_Clanstvo_Ops' ) && defined( 'Plan_A_Clanstvo_Ops::NO_JAN' ) ? Plan_A_Clanstvo_Ops::NO_JAN : '_pac_no_jan';
	}

	public static function has_members(): bool {
		return class_exists( 'Plan_A_Clanstvo_Data' );
	}

	public static function has_orders(): bool {
		return function_exists( 'wc_get_orders' );
	}

	public static function norm( string $email ): string {
		$email = strtolower( trim( $email ) );
		return is_email( $email ) ? $email : '';
	}

	/**
	 * Slaganje popisa. $groups: ključevi iz GROUPS; $tour: "ID|Y-m-d"; $extra: slobodan tekst.
	 *
	 * @return array{list: array<string, array{n: string, s: int}>, counts: array<string,int>, skipped: int}
	 */
	public static function build( array $groups, string $tour = '', string $extra = '' ): array {
		$groups  = array_values( array_intersect( array_keys( self::GROUPS ), $groups ) );
		$skip    = self::optout_set();
		$members = self::members();
		$list    = array();
		$counts  = array();
		$skipped = array();
		$add     = static function ( string $group, string $email, string $name, bool $service = false ) use ( &$list, &$counts, &$skipped, $skip ) {
			$email = self::norm( $email );
			if ( '' === $email ) {
				return;
			}
			if ( isset( $skip[ $email ] ) && ! $service ) {
				$skipped[ $email ] = 1;
				return;
			}
			$counts[ $group ] = ( $counts[ $group ] ?? 0 ) + ( isset( $list[ $email ] ) ? 0 : 1 );
			if ( ! isset( $list[ $email ] ) ) {
				$list[ $email ] = array( 'n' => $name, 's' => $service ? 1 : 0 );
			} elseif ( '' === $list[ $email ]['n'] && '' !== $name ) {
				$list[ $email ]['n'] = $name;
			}
		};
		foreach ( $groups as $g ) {
			$counts[ $g ] = 0;
			if ( 'clanovi' === $g || 'clanarina' === $g ) {
				foreach ( $members as $m ) {
					if ( 'clanarina' === $g && ! $m['paid'] ) {
						continue;
					}
					$add( $g, $m['email'], $m['ime'] );
				}
			} elseif ( 'izlet' === $g && '' !== $tour ) {
				// Obavijest o izletu na koji su prijavljeni: ide i onima koji su se odjavili s newslettera.
				foreach ( self::tour_people( $tour ) as $email => $name ) {
					$add( $g, $email, $name, true );
				}
			} elseif ( 'kupci' === $g ) {
				$known = array();
				foreach ( $members as $m ) {
					$known[ $m['email'] ] = 1;
				}
				foreach ( self::customers() as $email => $name ) {
					if ( ! isset( $known[ $email ] ) ) {
						$add( $g, $email, $name );
					}
				}
			} elseif ( 'vanjski' === $g ) {
				foreach ( self::parse( $extra ) as $email ) {
					$add( $g, $email, '' );
				}
			}
		}
		return array(
			'list'    => $list,
			'counts'  => $counts,
			'skipped' => count( array_diff_key( $skipped, $list ) ),
		);
	}

	/** Adrese iz slobodnog teksta (zarez, točka-zarez, razmak ili novi red). */
	public static function parse( string $text ): array {
		$out = array();
		foreach ( preg_split( '/[\s,;<>]+/', $text ) as $p ) {
			$e = self::norm( (string) $p );
			if ( '' !== $e ) {
				$out[ $e ] = $e;
			}
		}
		return array_values( $out );
	}

	/** Odjavljeni s newslettera i članovi odjavljeni sa siječanjskog e-maila (jedna zajednička odjava). */
	public static function optout_set(): array {
		$skip = array();
		foreach ( array_keys( Plan_A_Newsletter::optouts() ) as $e ) {
			$skip[ strtolower( (string) $e ) ] = 1;
		}
		if ( self::has_members() ) {
			$ids = get_posts(
				array(
					'post_type'      => Plan_A_Clanstvo_Data::CPT,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_key'       => self::no_mail_key(), // phpcs:ignore WordPress.DB.SlowDBQuery
					'meta_compare'   => 'EXISTS',
				)
			);
			foreach ( $ids as $id ) {
				$e = self::norm( (string) get_post_meta( $id, '_pac_email', true ) );
				if ( '' !== $e ) {
					$skip[ $e ] = 1;
				}
			}
		}
		return $skip;
	}

	/** Potvrđeni članovi: e-mail, ime, plaćena članarina za ovu godinu. */
	public static function members(): array {
		if ( ! self::has_members() ) {
			return array();
		}
		$year = (int) current_time( 'Y' );
		$ids  = get_posts(
			array(
				'post_type'      => Plan_A_Clanstvo_Data::CPT,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => '_pac_status', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => 'potvrdeno', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		update_meta_cache( 'post', $ids );
		$out = array();
		foreach ( $ids as $id ) {
			$email = self::norm( (string) get_post_meta( $id, '_pac_email', true ) );
			if ( '' === $email ) {
				continue;
			}
			$years = array_map( 'intval', (array) get_post_meta( $id, '_pac_godine', true ) );
			$out[] = array(
				'id'    => (int) $id,
				'email' => $email,
				'ime'   => trim( (string) get_post_meta( $id, '_pac_ime', true ) ),
				'paid'  => in_array( $year, $years, true ) || Plan_A_Clanstvo_Data::fee_paid( (int) $id, $year ),
			);
		}
		return $out;
	}

	/** Narudžbe izleta iz zadnje 2 godine (stranica po stranica). */
	private static function orders( callable $each ) {
		if ( ! self::has_orders() ) {
			return;
		}
		$page = 1;
		do {
			$orders = wc_get_orders(
				array(
					'type'         => 'shop_order',
					'status'       => array( 'wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending' ),
					'date_created' => '>' . ( time() - 2 * YEAR_IN_SECONDS ),
					'limit'        => 100,
					'page'         => $page,
					'orderby'      => 'date',
					'order'        => 'DESC',
				)
			);
			foreach ( $orders as $order ) {
				if ( $order instanceof WC_Order ) {
					$each( $order );
				}
			}
			++$page;
		} while ( count( $orders ) === 100 && $page < 100 );
	}

	/** Kupci izleta (e-mail => ime). */
	public static function customers(): array {
		$out = array();
		self::orders(
			static function ( WC_Order $order ) use ( &$out ) {
				$tour = false;
				foreach ( $order->get_items() as $item ) {
					if ( $item instanceof WC_Order_Item_Product && (int) $item->get_meta( '_ttbm_id' ) ) {
						$tour = true;
						break;
					}
				}
				$e = self::norm( (string) $order->get_billing_email() );
				if ( $tour && '' !== $e && ! isset( $out[ $e ] ) ) {
					$out[ $e ] = trim( (string) $order->get_billing_first_name() );
				}
			}
		);
		return $out;
	}

	/** Svi s prijava na termin izleta: kupac i osobe iz narudžbe (e-mail => ime). */
	public static function tour_people( string $tour ): array {
		list( $tid, $date ) = array_pad( explode( '|', $tour ), 2, '' );
		$tid  = (int) $tid;
		$date = substr( (string) $date, 0, 10 );
		if ( ! $tid || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return array();
		}
		$rows = class_exists( 'Plan_A_Clanstvo_Agency' ) ? (array) get_option( Plan_A_Clanstvo_Agency::ROWS, array() ) : array();
		$out  = array();
		self::orders(
			static function ( WC_Order $order ) use ( &$out, $tid, $date, $rows ) {
				foreach ( $order->get_items() as $item_id => $item ) {
					if ( ! $item instanceof WC_Order_Item_Product || (int) $item->get_meta( '_ttbm_id' ) !== $tid || 0 !== strpos( (string) $item->get_meta( '_ttbm_date' ), $date ) ) {
						continue;
					}
					$e = self::norm( (string) $order->get_billing_email() );
					if ( '' !== $e ) {
						$out[ $e ] = $out[ $e ] ?? trim( (string) $order->get_billing_first_name() );
					}
					// Ostale osobe s iste prijave (iz tablice agencije: član ili e-mail s prijave).
					$prefix = 'o' . $order->get_id() . '-' . $item_id . '-';
					foreach ( $rows as $pkey => $r ) {
						if ( 0 !== strpos( (string) $pkey, $prefix ) ) {
							continue;
						}
						$pe = '';
						$pn = '';
						if ( ! empty( $r['b'] ) && class_exists( 'Plan_A_Clanstvo_Data' ) ) {
							$mid = Plan_A_Clanstvo_Data::find( 'broj', (string) $r['b'] );
							$m   = $mid ? Plan_A_Clanstvo_Data::get_member( $mid ) : null;
							$pe  = $m ? (string) $m['email'] : '';
							$pn  = $m ? (string) $m['ime'] : '';
						} elseif ( ! empty( $r['e'] ) ) {
							$pe = (string) $r['e'];
						}
						$pe = self::norm( $pe );
						if ( '' !== $pe ) {
							$out[ $pe ] = $out[ $pe ] ?? $pn;
						}
					}
				}
			}
		);
		return $out;
	}

	/** Termini za izbor „Sudionici izleta” (od 60 dana unatrag do 8 mjeseci unaprijed). */
	public static function tour_options(): array {
		if ( ! class_exists( 'Plan_A_Izleti_Data' ) || ! method_exists( 'Plan_A_Izleti_Data', 'get_tour_dates' ) ) {
			return array();
		}
		$from = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -60 days' ) );
		$to   = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +240 days' ) );
		$out  = array();
		foreach ( Plan_A_Izleti_Data::get_tour_dates() as $id => $row ) {
			$title = wp_strip_all_tags( html_entity_decode( Plan_A_Izleti_Data::title( (int) $id ), ENT_QUOTES, 'UTF-8' ) );
			foreach ( (array) $row['dates'] as $d ) {
				if ( $d >= $from && $d <= $to ) {
					$out[ $id . '|' . $d ] = array(
						'd' => $d,
						't' => $title,
					);
				}
			}
		}
		uasort( $out, static fn( $a, $b ) => strcmp( $a['d'], $b['d'] ) ?: strcoll( $a['t'], $b['t'] ) );
		$labels = array();
		foreach ( $out as $k => $v ) {
			$labels[ $k ] = gmdate( 'j.n.Y.', strtotime( $v['d'] ) ) . ' · ' . $v['t'];
		}
		return $labels;
	}
}
