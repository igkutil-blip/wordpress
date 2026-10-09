<?php
/**
 * Uvoz članova iz CSV-a (npr. stari popis pristupnica i tablice članarina).
 * Uvoz NE šalje e-mailove. Isti član (OIB, ili e-mail + ime + datum rođenja,
 * ili samo ime i prezime kad nema ni OIB-a ni e-maila) se ažurira, ne duplira.
 *
 * @package Plan_A_Clanstvo
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Clanstvo_Import {

	const MAX_BYTES = 2097152; // 2 MB

	/** Naslov stupca u CSV-u (malim slovima, bez naglasaka) => ključ. */
	const COLUMNS = array(
		'datum prijave'          => 'created',
		'datum potvrde'          => 'confirmed',
		'ime'                    => 'ime',
		'prezime'                => 'prezime',
		'datum rodenja'          => 'datum',
		'oib'                    => 'oib',
		'adresa'                 => 'adresa',
		'mjesto, postanski broj' => 'mjesto',
		'mjesto'                 => 'mjesto',
		'e-mail'                 => 'email',
		'email'                  => 'email',
		'mobitel'                => 'mobitel',
		'roditelj ili skrbnik'   => 'roditelj',
		'status'                 => 'status',
		'clanarina'              => 'godine',
		'iskaznica'              => 'iskaznica',
		'iskaznica urucena'      => 'iskaznica',
		'napomena'               => 'napomena',
	);

	const JOB  = 'plan_a_clanstvo_import';
	const LAST = 'plan_a_clanstvo_import_last';

	/**
	 * Čita CSV i sprema ga kao posao uvoza; sami redovi obrađuju se u dijelovima (step()),
	 * da uvoz ne prekine vremensko ograničenje poslužitelja.
	 *
	 * @return array{ok: bool, error?: string, total?: int}
	 */
	public static function start( string $file ): array {
		if ( ! is_readable( $file ) || filesize( $file ) > self::MAX_BYTES ) {
			return array(
				'ok'    => false,
				'error' => 'Datoteka nije pročitana ili je veća od 2 MB.',
			);
		}
		$text = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$text = (string) preg_replace( '/^\xEF\xBB\xBF/', '', $text );
		if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $text, 'UTF-8' ) ) {
			$text = mb_convert_encoding( $text, 'UTF-8', 'Windows-1250' );
		}
		$first = strtok( $text, "\n" );
		$sep   = substr_count( (string) $first, ';' ) > substr_count( (string) $first, ',' ) ? ';' : ',';
		$fh    = fopen( 'php://temp', 'w+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fwrite( $fh, $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		rewind( $fh );

		$head = fgetcsv( $fh, 0, $sep, '"', '' );
		$map  = array();
		foreach ( (array) $head as $i => $h ) {
			$k = strtolower( remove_accents( trim( (string) $h ) ) );
			if ( isset( self::COLUMNS[ $k ] ) && ! in_array( self::COLUMNS[ $k ], $map, true ) ) {
				$map[ $i ] = self::COLUMNS[ $k ];
			}
		}
		if ( ! in_array( 'ime', $map, true ) || ! in_array( 'prezime', $map, true ) ) {
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			return array(
				'ok'    => false,
				'error' => 'U prvom retku nedostaju stupci „Ime” i „Prezime” (pronađeno: ' . implode( ', ', array_map( 'sanitize_text_field', array_slice( (array) $head, 0, 6 ) ) ) . ').',
			);
		}
		$rows = array();
		while ( ( $cells = fgetcsv( $fh, 0, $sep, '"', '' ) ) !== false ) {
			$r = array();
			foreach ( $map as $i => $k ) {
				$r[ $k ] = trim( (string) ( $cells[ $i ] ?? '' ) );
			}
			if ( implode( '', $r ) !== '' ) {
				$rows[] = $r;
			}
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $rows ) {
			return array(
				'ok'    => false,
				'error' => 'U datoteci nema redaka s članovima.',
			);
		}
		update_option(
			self::JOB,
			array(
				'rows'  => $rows,
				'pos'   => 0,
				'names' => self::names(),
				'stats' => array(
					'new'     => 0,
					'updated' => 0,
					'skipped' => 0,
					'ceka'    => 0,
				),
				'sheet' => 0,
				'error' => '',
			),
			false
		);
		return array(
			'ok'    => true,
			'total' => count( $rows ),
		);
	}

	public static function job(): ?array {
		$job = get_option( self::JOB );
		return is_array( $job ) && isset( $job['rows'] ) ? $job : null;
	}

	public static function cancel() {
		delete_option( self::JOB );
	}

	/**
	 * Sljedeći dio uvoza: najprije članovi (po 40 redaka), zatim slanje u tablicu (po 25).
	 *
	 * @return array{done: bool, phase: string, pos: int, total: int, pending: int, stats: array, error: string}
	 */
	public static function step(): array {
		$job = self::job();
		if ( ! $job ) {
			return array(
				'done'    => true,
				'phase'   => 'gotovo',
				'pos'     => 0,
				'total'   => 0,
				'pending' => Plan_A_Clanstvo_Sheets::pending(),
				'stats'   => (array) ( get_option( self::LAST )['stats'] ?? array() ),
				'error'   => '',
			);
		}
		$total = count( $job['rows'] );
		$start = microtime( true );
		$err   = '';
		if ( $job['pos'] < $total ) {
			wp_defer_term_counting( true );
			while ( $job['pos'] < $total && microtime( true ) - $start < 15 ) {
				$res = self::row( $job['rows'][ $job['pos'] ], $job['names'] );
				++$job['pos'];
				if ( ! $res ) {
					++$job['stats']['skipped'];
				} else {
					++$job['stats'][ $res[1] ? 'new' : 'updated' ];
					if ( 'ceka' === $res[2] ) {
						++$job['stats']['ceka'];
					}
				}
				if ( 0 === $job['pos'] % 40 ) {
					break;
				}
			}
			wp_defer_term_counting( false );
			$phase = 'clanovi';
		} elseif ( '' !== Plan_A_Clanstvo_Sheets::url() && Plan_A_Clanstvo_Sheets::pending() ) {
			$r = Plan_A_Clanstvo_Sheets::bulk( true, 50, 10 );
			$job['sheet'] += $r['n'];
			if ( ! $r['ok'] ) {
				$err = 'Slanje u Google tablicu nije uspjelo: ' . ( $r['error'] ?? '' );
			}
			$phase = 'tablica';
		} else {
			$phase = 'gotovo';
		}
		$pending = Plan_A_Clanstvo_Sheets::pending();
		$done    = 'gotovo' === $phase || ( $job['pos'] >= $total && ( '' === Plan_A_Clanstvo_Sheets::url() || ! $pending ) );
		if ( $done ) {
			update_option(
				self::LAST,
				array(
					'time'  => current_time( 'mysql' ),
					'total' => $total,
					'stats' => $job['stats'],
				),
				false
			);
			self::cancel();
		} else {
			$job['error'] = $err;
			update_option( self::JOB, $job, false );
		}
		return array(
			'done'    => $done,
			'phase'   => $phase,
			'pos'     => (int) $job['pos'],
			'total'   => $total,
			'pending' => $pending,
			'stats'   => $job['stats'],
			'error'   => $err,
		);
	}

	/**
	 * Jedan red. Vraća [id, novi?, status] ili null ako je red preskočen.
	 */
	private static function row( array $r, array &$names ): ?array {
		$data = array();
		foreach ( array_keys( Plan_A_Clanstvo_Data::FIELDS ) as $k ) {
			$data[ $k ] = sanitize_text_field( $r[ $k ] ?? '' );
		}
		if ( '' === $data['ime'] || '' === $data['prezime'] ) {
			return null;
		}
		$data['oib']   = preg_replace( '/\D/', '', $data['oib'] );
		$data['oib']   = Plan_A_Clanstvo_Data::valid_oib( $data['oib'] ) ? $data['oib'] : '';
		$data['email'] = strtolower( sanitize_email( $data['email'] ) );
		$data['datum'] = '' !== $data['datum'] ? Plan_A_Clanstvo_Data::parse_date( $data['datum'] ) : '';
		// Prazna polja ne brišu ono što član već ima.
		$data = array_filter( $data, 'strlen' );

		$status    = 0 === strpos( strtolower( remove_accents( $r['status'] ?? '' ) ), 'potvr' ) ? 'potvrdeno' : 'ceka';
		$created   = self::datetime( $r['created'] ?? '' );
		$confirmed = self::datetime( $r['confirmed'] ?? '' );
		$key       = self::norm( $data['ime'] . ' ' . $data['prezime'] );

		$id = Plan_A_Clanstvo_Data::find_same( $data );
		if ( ! $id && empty( $data['oib'] ) && empty( $data['email'] ) && isset( $names[ $key ] ) ) {
			$id = $names[ $key ]; // samo ime i prezime (npr. iz tablice članarina)
		}
		$is_new = ! $id;
		$id     = Plan_A_Clanstvo_Data::save( $data, $status, $created, (int) $id );
		if ( ! $id ) {
			return null;
		}
		$names[ $key ] = $names[ $key ] ?? $id;
		if ( 'potvrdeno' === $status && ! get_post_meta( $id, '_pac_confirmed', true ) ) {
			update_post_meta( $id, '_pac_confirmed', $confirmed ?: ( $created ?: current_time( 'mysql' ) ) );
		}
		if ( $is_new && $created ) {
			update_post_meta( $id, '_pac_created', $created );
		}
		preg_match_all( '/20\d\d/', $r['godine'] ?? '', $y );
		$years = array_values( array_unique( array_map( 'intval', $y[0] ) ) );
		if ( $years ) {
			$old = array_map( 'intval', (array) get_post_meta( $id, '_pac_godine', true ) );
			update_post_meta( $id, '_pac_godine', array_values( array_unique( array_filter( array_merge( $old, $years ) ) ) ) );
		}
		$card = strtolower( remove_accents( $r['iskaznica'] ?? '' ) );
		if ( '' !== $card && ! in_array( $card, array( 'ne', '0', 'false' ), true ) ) {
			update_post_meta( $id, '_pac_iskaznica', 1 );
		}
		if ( '' !== ( $r['napomena'] ?? '' ) ) {
			update_post_meta( $id, '_pac_napomena', sanitize_text_field( $r['napomena'] ) );
		}
		update_post_meta( $id, '_pac_sync', 'pending' );
		return array( $id, $is_new, (string) get_post_meta( $id, '_pac_status', true ) );
	}

	/** "29.12.2024. 21:17" → "2024-12-29 21:17:00". */
	private static function datetime( string $v ): string {
		if ( preg_match( '/^(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})\.?(?:\s+(\d{1,2}):(\d{2}))?/', trim( $v ), $m ) && checkdate( (int) $m[2], (int) $m[1], (int) $m[3] ) ) {
			return sprintf( '%04d-%02d-%02d %02d:%02d:00', $m[3], $m[2], $m[1], $m[4] ?? 12, $m[5] ?? 0 );
		}
		return '';
	}

	private static function norm( string $t ): string {
		$t = remove_accents( function_exists( 'mb_strtolower' ) ? mb_strtolower( $t ) : strtolower( $t ) );
		return (string) preg_replace( '/[^a-z]/', '', $t );
	}

	/** Postojeći članovi bez OIB-a i e-maila, po imenu i prezimenu. */
	private static function names(): array {
		$out = array();
		$ids = get_posts(
			array(
				'post_type'      => Plan_A_Clanstvo_Data::CPT,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		foreach ( $ids as $id ) {
			if ( '' === (string) get_post_meta( $id, '_pac_oib', true ) && '' === (string) get_post_meta( $id, '_pac_email', true ) ) {
				$out[ self::norm( get_post_meta( $id, '_pac_ime', true ) . ' ' . get_post_meta( $id, '_pac_prezime', true ) ) ] = (int) $id;
			}
		}
		return $out;
	}
}
