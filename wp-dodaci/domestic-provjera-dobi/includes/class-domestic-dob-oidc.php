<?php
/**
 * OIDC prema e-Građani/mGrađani: PKCE (S256), zahtjev za autorizaciju, razmjena koda, JWKS i provjera ID tokena (RS256).
 * Bez vanjskih biblioteka. Svaka nejasnoća vraća grešku – kupnja se propušta samo na izričito „da”.
 */

defined( 'ABSPATH' ) || exit;

final class Domestic_Dob_Oidc {

	const TOLERANCIJA = 60;

	public static function b64url( string $raw ): string {
		return rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' );
	}

	public static function b64url_dec( string $s ) {
		$s = strtr( $s, '-_', '+/' );
		return base64_decode( $s . str_repeat( '=', ( 4 - strlen( $s ) % 4 ) % 4 ), true );
	}

	public static function slucajno( int $bajtova = 32 ): string {
		return self::b64url( random_bytes( $bajtova ) );
	}

	/** code_verifier: 64 znaka iz [A-Za-z0-9-_] (RFC 7636 dopušta 43–128). */
	public static function verifier(): string {
		return self::slucajno( 48 );
	}

	public static function challenge( string $verifier ): string {
		return self::b64url( hash( 'sha256', $verifier, true ) );
	}

	public static function authorize_url( array $cfg, string $redirect, string $state, string $nonce, string $challenge ): string {
		$q = array(
			'response_type'         => 'code',
			'client_id'             => $cfg['client_id'],
			'redirect_uri'          => $redirect,
			'scope'                 => 'age.alcohol',
			'state'                 => $state,
			'nonce'                 => $nonce,
			'code_challenge'        => $challenge,
			'code_challenge_method' => 'S256',
		);
		$sep = false === strpos( $cfg['authorize'], '?' ) ? '?' : '&';
		// http_build_query kodira i redirect_uri (add_query_arg to ne radi).
		return $cfg['authorize'] . $sep . http_build_query( $q, '', '&', PHP_QUERY_RFC3986 );
	}

	/** Razmjena koda za ID token – samo s poslužitelja, s code_verifierom. */
	public static function razmjena( array $cfg, string $code, string $verifier, string $redirect ) {
		$r = wp_remote_post(
			$cfg['token'],
			array(
				'timeout' => 15,
				'headers' => array( 'Accept' => 'application/json' ),
				'body'    => array(
					'grant_type'    => 'authorization_code',
					'code'          => $code,
					'redirect_uri'  => $redirect,
					'client_id'     => $cfg['client_id'],
					'code_verifier' => $verifier,
				),
			)
		);
		if ( is_wp_error( $r ) ) {
			return new WP_Error( 'dsd_token_veza', 'Token endpoint nije dostupan: ' . $r->get_error_message() );
		}
		$kod  = (int) wp_remote_retrieve_response_code( $r );
		$body = json_decode( (string) wp_remote_retrieve_body( $r ), true );
		if ( 200 !== $kod || ! is_array( $body ) ) {
			$err = is_array( $body ) ? (string) ( $body['error'] ?? '' ) : '';
			return new WP_Error( 'dsd_token_odgovor', 'Token endpoint je vratio ' . $kod . ( $err ? ' (' . $err . ')' : '' ) );
		}
		if ( empty( $body['id_token'] ) || ! is_string( $body['id_token'] ) ) {
			return new WP_Error( 'dsd_token_prazan', 'Odgovor nema id_token.' );
		}
		return $body['id_token'];
	}

	/** JWKS s predmemorijom (1 h); $svjeze zaobilazi predmemoriju. */
	public static function jwks( array $cfg, bool $svjeze = false ) {
		$key = 'dsd_jwks_' . md5( $cfg['jwks'] );
		if ( ! $svjeze ) {
			$c = get_transient( $key );
			if ( is_array( $c ) ) {
				return $c;
			}
		}
		$r = wp_remote_get( $cfg['jwks'], array( 'timeout' => 10, 'headers' => array( 'Accept' => 'application/json' ) ) );
		if ( is_wp_error( $r ) ) {
			return new WP_Error( 'dsd_jwks_veza', 'JWKS nije dostupan: ' . $r->get_error_message() );
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $r ), true );
		if ( 200 !== (int) wp_remote_retrieve_response_code( $r ) || empty( $body['keys'] ) || ! is_array( $body['keys'] ) ) {
			return new WP_Error( 'dsd_jwks_odgovor', 'JWKS odgovor nije ispravan.' );
		}
		$keys = array();
		foreach ( $body['keys'] as $k ) {
			if ( ! is_array( $k ) || 'RSA' !== ( $k['kty'] ?? '' ) || empty( $k['n'] ) || empty( $k['e'] ) ) {
				continue;
			}
			if ( isset( $k['use'] ) && 'sig' !== $k['use'] ) {
				continue;
			}
			if ( isset( $k['alg'] ) && 'RS256' !== $k['alg'] ) {
				continue;
			}
			$keys[] = $k;
		}
		if ( ! $keys ) {
			return new WP_Error( 'dsd_jwks_prazan', 'JWKS nema RSA ključ za potpis.' );
		}
		set_transient( $key, $keys, HOUR_IN_SECONDS );
		return $keys;
	}

	/** RSA JWK (n, e) → PEM javni ključ (ASN.1 DER SubjectPublicKeyInfo). */
	public static function jwk_pem( array $jwk ) {
		$n = self::b64url_dec( (string) $jwk['n'] );
		$e = self::b64url_dec( (string) $jwk['e'] );
		if ( false === $n || false === $e || strlen( ltrim( $n, "\0" ) ) < 256 ) {
			return false; // manje od 2048 bita ne prihvaćamo
		}
		$int = static function ( string $b ): string {
			$b = ltrim( $b, "\0" );
			if ( ord( $b[0] ) > 0x7f ) {
				$b = "\0" . $b;
			}
			return self::der( "\x02", $b );
		};
		$rsa  = self::der( "\x30", $int( $n ) . $int( $e ) );
		$alg  = self::der( "\x30", "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01" . "\x05\x00" );
		$spki = self::der( "\x30", $alg . self::der( "\x03", "\0" . $rsa ) );
		return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $spki ), 64, "\n" ) . "-----END PUBLIC KEY-----\n";
	}

	private static function der( string $tag, string $sadrzaj ): string {
		$len = strlen( $sadrzaj );
		if ( $len < 0x80 ) {
			return $tag . chr( $len ) . $sadrzaj;
		}
		$l = ltrim( pack( 'N', $len ), "\0" );
		return $tag . chr( 0x80 | strlen( $l ) ) . $l . $sadrzaj;
	}

	/**
	 * Provjera ID tokena. Potpis se provjerava prije nego što se pročita ijedan claim.
	 *
	 * @return array|WP_Error claimovi
	 */
	public static function provjeri( string $jwt, array $cfg, string $nonce ) {
		$dijelovi = explode( '.', $jwt );
		if ( 3 !== count( $dijelovi ) ) {
			return new WP_Error( 'dsd_jwt_oblik', 'ID token nije JWT.' );
		}
		$header = json_decode( (string) self::b64url_dec( $dijelovi[0] ), true );
		$sig    = self::b64url_dec( $dijelovi[2] );
		if ( ! is_array( $header ) || 'RS256' !== ( $header['alg'] ?? '' ) || false === $sig || '' === $sig ) {
			return new WP_Error( 'dsd_jwt_alg', 'Dopušten je samo RS256.' );
		}
		$kid  = isset( $header['kid'] ) ? (string) $header['kid'] : null;
		$kljuc = self::kljuc( $cfg, $kid, false );
		if ( null === $kljuc && null !== $kid ) {
			$kljuc = self::kljuc( $cfg, $kid, true ); // rotacija ključeva
		}
		if ( is_wp_error( $kljuc ) ) {
			return $kljuc;
		}
		if ( null === $kljuc ) {
			return new WP_Error( 'dsd_jwt_kid', 'Nepoznat ključ potpisa.' );
		}
		$pem = self::jwk_pem( $kljuc );
		if ( ! $pem || 1 !== openssl_verify( $dijelovi[0] . '.' . $dijelovi[1], $sig, $pem, OPENSSL_ALGO_SHA256 ) ) {
			return new WP_Error( 'dsd_jwt_potpis', 'Potpis ID tokena nije ispravan.' );
		}

		$c = json_decode( (string) self::b64url_dec( $dijelovi[1] ), true );
		if ( ! is_array( $c ) ) {
			return new WP_Error( 'dsd_jwt_claims', 'Claimovi nisu čitljivi.' );
		}
		$sad = time();
		if ( ! is_string( $c['iss'] ?? null ) || ! hash_equals( rtrim( $cfg['issuer'], '/' ), rtrim( $c['iss'], '/' ) ) ) {
			return new WP_Error( 'dsd_jwt_iss', 'Pogrešan izdavatelj (iss).' );
		}
		$aud = $c['aud'] ?? null;
		$aud = is_string( $aud ) ? array( $aud ) : ( is_array( $aud ) ? $aud : array() );
		$ima = false;
		foreach ( $aud as $a ) {
			$ima = $ima || ( is_string( $a ) && hash_equals( $cfg['client_id'], $a ) );
		}
		if ( ! $ima ) {
			return new WP_Error( 'dsd_jwt_aud', 'Token nije izdan za ovu trgovinu (aud).' );
		}
		if ( count( $aud ) > 1 && ( ! is_string( $c['azp'] ?? null ) || ! hash_equals( $cfg['client_id'], $c['azp'] ) ) ) {
			return new WP_Error( 'dsd_jwt_azp', 'Višestruki aud bez ispravnog azp.' );
		}
		if ( ! is_numeric( $c['exp'] ?? null ) || (int) $c['exp'] < $sad - self::TOLERANCIJA ) {
			return new WP_Error( 'dsd_jwt_exp', 'Token je istekao ili nema exp.' );
		}
		if ( isset( $c['iat'] ) && ( ! is_numeric( $c['iat'] ) || (int) $c['iat'] > $sad + self::TOLERANCIJA ) ) {
			return new WP_Error( 'dsd_jwt_iat', 'Token je izdan u budućnosti (iat).' );
		}
		if ( isset( $c['nbf'] ) && ( ! is_numeric( $c['nbf'] ) || (int) $c['nbf'] > $sad + self::TOLERANCIJA ) ) {
			return new WP_Error( 'dsd_jwt_nbf', 'Token još ne vrijedi (nbf).' );
		}
		if ( ! is_string( $c['nonce'] ?? null ) || ! hash_equals( $nonce, $c['nonce'] ) ) {
			return new WP_Error( 'dsd_jwt_nonce', 'Nonce se ne podudara.' );
		}
		return $c;
	}

	/** @return array|null|WP_Error */
	private static function kljuc( array $cfg, ?string $kid, bool $svjeze ) {
		$keys = self::jwks( $cfg, $svjeze );
		if ( is_wp_error( $keys ) ) {
			return $keys;
		}
		if ( null === $kid ) {
			return 1 === count( $keys ) ? $keys[0] : null; // bez kid samo ako je izbor jednoznačan
		}
		foreach ( $keys as $k ) {
			if ( isset( $k['kid'] ) && hash_equals( (string) $k['kid'], $kid ) ) {
				return $k;
			}
		}
		return null;
	}

	/** Odluka iz claima age.alcohol: true / false / null (nejasno = blokada). */
	public static function odluka( array $c ): ?bool {
		$v = array_key_exists( 'age.alcohol', $c ) ? $c['age.alcohol'] : ( is_array( $c['age'] ?? null ) && array_key_exists( 'alcohol', $c['age'] ) ? $c['age']['alcohol'] : null );
		if ( true === $v || 1 === $v || 'true' === $v || '1' === $v ) {
			return true;
		}
		if ( false === $v || 0 === $v || 'false' === $v || '0' === $v ) {
			return false;
		}
		return null;
	}
}
