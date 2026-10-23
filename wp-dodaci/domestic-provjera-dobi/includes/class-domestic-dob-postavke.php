<?php
/**
 * Postavke provjere dobi: okruženje (test/produkcija), pristupni podaci, opseg proizvoda, povratna adresa.
 */

defined( 'ABSPATH' ) || exit;

final class Domestic_Dob_Postavke {

	const OPT = 'domestic_provjera_dobi';

	/** Javne adrese IDP-a (iz .well-known/openid-configuration). */
	const IDP = array(
		'test' => 'https://mgradjani-test.gov.hr/idp',
		'prod' => 'https://mgradjani.gov.hr/idp',
	);

	/** Povratna adresa registrirana kod ministarstva (relativno na početnu adresu stranice). */
	const CALLBACK = '/age-verification/callback';

	public static function zadano_okruzenje( string $env ): array {
		$base = self::IDP[ $env ];
		return array(
			'client_id' => '',
			'issuer'    => $base,
			'authorize' => $base . '/authorize',
			'token'     => $base . '/token',
			'jwks'      => $base . '/jwks',
		);
	}

	public static function sve(): array {
		$s = get_option( self::OPT, array() );
		$s = is_array( $s ) ? $s : array();
		$d = array(
			'ukljuceno'   => 'no',
			'okruzenje'   => 'test',
			'opseg'       => 'svi',
			'kategorije'  => array(),
			'redirect'    => '',
			'trajanje'    => 60,
			'dijagnostika' => 'no',
		);
		$s = array_merge( $d, $s );
		foreach ( array( 'test', 'prod' ) as $env ) {
			$s[ $env ] = array_merge( self::zadano_okruzenje( $env ), is_array( $s[ $env ] ?? null ) ? $s[ $env ] : array() );
		}
		$s['kategorije'] = array_map( 'absint', (array) $s['kategorije'] );
		$s['trajanje']   = max( 5, min( 240, (int) $s['trajanje'] ) );
		return $s;
	}

	/** Pristupni podaci aktivnog okruženja (+ ključ 'env'). */
	public static function aktivno(): array {
		$s   = self::sve();
		$env = 'prod' === $s['okruzenje'] ? 'prod' : 'test';
		return array_merge( $s[ $env ], array( 'env' => $env ) );
	}

	public static function podeseno(): bool {
		$a = self::aktivno();
		foreach ( array( 'client_id', 'issuer', 'authorize', 'token', 'jwks' ) as $k ) {
			if ( '' === trim( (string) $a[ $k ] ) ) {
				return false;
			}
		}
		return true;
	}

	public static function ukljuceno(): bool {
		return 'yes' === self::sve()['ukljuceno'] && self::podeseno();
	}

	/** Točna povratna adresa koja ide u zahtjev i mora biti znak po znak ista kao prijavljena. */
	public static function redirect_uri(): string {
		$r = trim( (string) self::sve()['redirect'] );
		return '' !== $r ? $r : home_url( self::CALLBACK );
	}

	public static function log( string $poruka, string $razina = 'info' ): void {
		if ( 'info' === $razina && 'yes' !== self::sve()['dijagnostika'] ) {
			return;
		}
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log( $razina, $poruka, array( 'source' => 'domestic-provjera-dobi' ) );
		}
	}

	/** Nepovratni otisak za zapisnik (nikad sam token, kod ili verifier). */
	public static function otisak( string $v ): string {
		return 'sha256:' . substr( hash( 'sha256', $v ), 0, 12 );
	}
}
