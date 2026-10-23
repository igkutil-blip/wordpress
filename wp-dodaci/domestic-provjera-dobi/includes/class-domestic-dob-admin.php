<?php
/**
 * Administracija: Domestic → Provjera dobi (postavke, stanje, test veze s e-Građanima).
 */

defined( 'ABSPATH' ) || exit;

final class Domestic_Dob_Admin {

	const SLUG = 'domestic-provjera-dobi';

	public static function init(): void {
		Domestic_Menu::add( self::SLUG, 'Provjera dobi', array( __CLASS__, 'stranica' ), 30 );
		add_action( 'admin_post_dsd_spremi', array( __CLASS__, 'spremi' ) );
		add_action( 'admin_post_dsd_test', array( __CLASS__, 'test' ) );
		add_action( 'admin_notices', array( __CLASS__, 'upozorenja' ) );
	}

	private static function url( array $args = array() ): string {
		return add_query_arg( $args, admin_url( 'admin.php?page=' . self::SLUG ) );
	}

	/** Upozorenje kad je provjera uključena, a drugi dodatak za istu stvar je također aktivan. */
	public static function upozorenja(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! Domestic_Dob_Postavke::ukljuceno() ) {
			return;
		}
		if ( is_plugin_active( 'it-podrska-age-gate/it-podrska-age-gate.php' ) ) {
			echo '<div class="notice notice-warning"><p><b>Provjera dobi:</b> uključen je i dodatak „IT-podrška Age Gate (NIAS)”. Kupci bi provjeru prolazili dvaput – isključite jedan od njih.</p></div>';
		}
	}

	private static function stanje(): array {
		$s   = Domestic_Dob_Postavke::sve();
		$a   = Domestic_Dob_Postavke::aktivno();
		$red = Domestic_Dob_Postavke::redirect_uri();
		return array(
			array( 'yes' === $s['ukljuceno'], 'Provjera na blagajni', 'yes' === $s['ukljuceno'] ? 'uključena' : 'isključena' ),
			array( true, 'Okruženje', 'prod' === $a['env'] ? 'produkcija (mgradjani.gov.hr)' : 'test (mgradjani-test.gov.hr)' ),
			array( '' !== $a['client_id'], 'client_id', '' !== $a['client_id'] ? 'upisan' : 'nije upisan' ),
			array( (bool) get_option( 'permalink_structure' ), 'Stalne veze', get_option( 'permalink_structure' ) ? 'ispravno' : '„Obično” – povratna adresa ne radi' ),
			array( 0 === strpos( $red, 'https://' ), 'HTTPS', 0 === strpos( $red, 'https://' ) ? 'da' : 'povratna adresa mora biti https' ),
			array( function_exists( 'openssl_verify' ), 'PHP openssl', function_exists( 'openssl_verify' ) ? 'da' : 'nedostaje' ),
			array( ! is_plugin_active( 'it-podrska-age-gate/it-podrska-age-gate.php' ), 'Drugi dodatak (IT-podrška NIAS)', is_plugin_active( 'it-podrska-age-gate/it-podrska-age-gate.php' ) ? 'aktivan – isključiti prije uključivanja ovog' : 'nije aktivan' ),
		);
	}

	public static function stranica(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$s = Domestic_Dob_Postavke::sve();
		echo '<div class="wrap"><h1>Provjera dobi (mGrađani)</h1>';
		if ( isset( $_GET['dsd_msg'] ) ) {
			$err = ! empty( $_GET['dsd_err'] );
			echo '<div class="notice notice-' . ( $err ? 'error' : 'success' ) . ' is-dismissible"><p>' . nl2br( esc_html( sanitize_textarea_field( wp_unslash( $_GET['dsd_msg'] ) ) ) ) . '</p></div>';
		}
		echo '<p>Provjera punoljetnosti kupca putem sustava e-Građani prije kupnje alkoholnih pića (čl. 11.a Zakona o trgovini, NN 59/26). Kupac na blagajni klikne gumb, potvrdi zahtjev u aplikaciji mGrađani i vrati se dovršiti narudžbu. Trgovina saznaje samo DA/NE – ne prima ime, OIB ni datum rođenja. Svaka narudžba traži novu provjeru.</p>';

		echo '<h2>Stanje</h2><table class="widefat striped" style="max-width:760px"><tbody>';
		foreach ( self::stanje() as $r ) {
			echo '<tr><td style="width:34px">' . ( $r[0] ? '✅' : '⚠️' ) . '</td><td>' . esc_html( $r[1] ) . '</td><td>' . esc_html( $r[2] ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p><b>Povratna adresa (redirect URI) prijavljena ministarstvu mora biti znak po znak ista:</b><br><code style="font-size:14px">' . esc_html( Domestic_Dob_Postavke::redirect_uri() ) . '</code></p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
		wp_nonce_field( 'dsd_test' );
		echo '<input type="hidden" name="action" value="dsd_test"><button class="button">Test veze s e-Građanima</button></form> <span class="description">dohvaća OpenID konfiguraciju i ključeve (JWKS) aktivnog okruženja i uspoređuje ih s upisanima.</span>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'dsd_spremi' );
		echo '<input type="hidden" name="action" value="dsd_spremi"><table class="form-table">';
		echo '<tr><th>Provjera na blagajni</th><td><label><input type="checkbox" name="ukljuceno" value="yes" ' . checked( $s['ukljuceno'], 'yes', false ) . '> uključena</label><p class="description">Radi tek kad je za aktivno okruženje upisan client_id.</p></td></tr>';
		echo '<tr><th>Okruženje</th><td><label><input type="radio" name="okruzenje" value="test" ' . checked( $s['okruzenje'], 'test', false ) . '> test</label> &nbsp; <label><input type="radio" name="okruzenje" value="prod" ' . checked( $s['okruzenje'], 'prod', false ) . '> produkcija</label><p class="description">Produkciju uključiti tek kad ministarstvo potvrdi produkcijsku spremnost.</p></td></tr>';
		foreach ( array( 'test' => 'Test', 'prod' => 'Produkcija' ) as $env => $naziv ) {
			$e = $s[ $env ];
			echo '<tr><th>' . esc_html( $naziv ) . ' – client_id</th><td><input name="' . esc_attr( $env ) . '[client_id]" class="regular-text code" value="' . esc_attr( $e['client_id'] ) . '" autocomplete="off">';
			echo '<details style="margin-top:8px"><summary>Adrese (zadano iz ' . esc_html( Domestic_Dob_Postavke::IDP[ $env ] ) . ')</summary>';
			foreach ( array( 'issuer' => 'issuer (iss)', 'authorize' => 'authorization endpoint', 'token' => 'token endpoint', 'jwks' => 'JWKS' ) as $k => $l ) {
				echo '<p><label>' . esc_html( $l ) . '<br><input name="' . esc_attr( $env . '[' . $k . ']' ) . '" class="large-text code" value="' . esc_attr( $e[ $k ] ) . '"></label></p>';
			}
			echo '</details></td></tr>';
		}
		echo '<tr><th>Za koje proizvode</th><td><label><input type="radio" name="opseg" value="svi" ' . checked( $s['opseg'], 'svi', false ) . '> svaka narudžba</label><br><label><input type="radio" name="opseg" value="kategorije" ' . checked( $s['opseg'], 'kategorije', false ) . '> samo kad je u košarici proizvod iz označenih kategorija (i njihovih podkategorija):</label>';
		echo '<div style="max-height:220px;overflow:auto;border:1px solid #dcdcde;padding:8px 12px;margin-top:6px;background:#fff;max-width:520px">';
		foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => 0 ) ) as $t ) {
			echo '<label style="display:block"><input type="checkbox" name="kategorije[]" value="' . (int) $t->term_id . '" ' . checked( in_array( (int) $t->term_id, $s['kategorije'], true ), true, false ) . '> ' . esc_html( $t->name ) . '</label>';
		}
		echo '</div></td></tr>';
		echo '<tr><th><label for="dsd-traj">Potvrda vrijedi</label></th><td><input id="dsd-traj" name="trajanje" type="number" min="5" max="240" class="small-text" value="' . (int) $s['trajanje'] . '"> minuta (za jednu narudžbu; nakon narudžbe se uvijek briše)</td></tr>';
		echo '<tr><th><label for="dsd-red">Povratna adresa ručno</label></th><td><input id="dsd-red" name="redirect" class="large-text code" value="' . esc_attr( $s['redirect'] ) . '" placeholder="' . esc_attr( home_url( Domestic_Dob_Postavke::CALLBACK ) ) . '"><p class="description">Prazno = automatski. Upisati samo ako je prijavljena adresa drugačija (npr. s www).</p></td></tr>';
		echo '<tr><th>Dijagnostika</th><td><label><input type="checkbox" name="dijagnostika" value="yes" ' . checked( $s['dijagnostika'], 'yes', false ) . '> bilježi tijek u WooCommerce → Status → Zapisnici (izvor <code>domestic-provjera-dobi</code>)</label><p class="description">Tokeni, kodovi i code_verifier se nikad ne zapisuju. Greške se bilježe uvijek.</p></td></tr>';
		echo '</table><p><button class="button button-primary">Spremi</button></p></form></div>';
	}

	private static function https_url( $v, string $zadano ): string {
		$v = esc_url_raw( trim( (string) $v ), array( 'https' ) );
		return '' !== $v ? untrailingslashit( $v ) : $zadano;
	}

	public static function spremi(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'dsd_spremi' ) ) {
			wp_die( 'Nema dozvole.' );
		}
		$p   = wp_unslash( $_POST );
		$nov = array(
			'ukljuceno'    => 'yes' === ( $p['ukljuceno'] ?? '' ) ? 'yes' : 'no',
			'okruzenje'    => 'prod' === ( $p['okruzenje'] ?? '' ) ? 'prod' : 'test',
			'opseg'        => 'kategorije' === ( $p['opseg'] ?? '' ) ? 'kategorije' : 'svi',
			'kategorije'   => array_values( array_filter( array_map( 'absint', (array) ( $p['kategorije'] ?? array() ) ) ) ),
			'trajanje'     => max( 5, min( 240, absint( $p['trajanje'] ?? 60 ) ) ),
			'redirect'     => esc_url_raw( trim( (string) ( $p['redirect'] ?? '' ) ), array( 'https', 'http' ) ),
			'dijagnostika' => 'yes' === ( $p['dijagnostika'] ?? '' ) ? 'yes' : 'no',
		);
		foreach ( array( 'test', 'prod' ) as $env ) {
			$z   = Domestic_Dob_Postavke::zadano_okruzenje( $env );
			$in  = is_array( $p[ $env ] ?? null ) ? $p[ $env ] : array();
			$nov[ $env ] = array( 'client_id' => sanitize_text_field( $in['client_id'] ?? '' ) );
			foreach ( array( 'issuer', 'authorize', 'token', 'jwks' ) as $k ) {
				$nov[ $env ][ $k ] = self::https_url( $in[ $k ] ?? '', $z[ $k ] );
			}
		}
		update_option( Domestic_Dob_Postavke::OPT, $nov, false );
		$msg = 'Spremljeno.';
		if ( 'yes' === $nov['ukljuceno'] && '' === $nov[ $nov['okruzenje'] ]['client_id'] ) {
			$msg .= ' Provjera neće raditi dok se ne upiše client_id za odabrano okruženje.';
		}
		wp_safe_redirect( self::url( array( 'dsd_msg' => rawurlencode( $msg ) ) ) );
		exit;
	}

	public static function test(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'dsd_test' ) ) {
			wp_die( 'Nema dozvole.' );
		}
		$a   = Domestic_Dob_Postavke::aktivno();
		$out = array( 'Okruženje: ' . $a['env'] );
		$err = false;
		$r   = wp_remote_get( $a['issuer'] . '/.well-known/openid-configuration', array( 'timeout' => 10 ) );
		$w   = is_wp_error( $r ) ? null : json_decode( (string) wp_remote_retrieve_body( $r ), true );
		if ( ! is_array( $w ) ) {
			$out[] = '✗ OpenID konfiguracija nije dostupna' . ( is_wp_error( $r ) ? ': ' . $r->get_error_message() : '.' );
			$err   = true;
		} else {
			$par = array( 'issuer' => 'issuer', 'authorize' => 'authorization_endpoint', 'token' => 'token_endpoint', 'jwks' => 'jwks_uri' );
			foreach ( $par as $k => $wk ) {
				$ok    = isset( $w[ $wk ] ) && untrailingslashit( (string) $w[ $wk ] ) === $a[ $k ];
				$out[] = ( $ok ? '✓ ' : '✗ ' ) . $wk . ( $ok ? '' : ' – na IDP-u: ' . ( $w[ $wk ] ?? '?' ) . ', upisano: ' . $a[ $k ] );
				$err   = $err || ! $ok;
			}
			$sc    = in_array( 'age.alcohol', (array) ( $w['scopes_supported'] ?? array() ), true );
			$pk    = in_array( 'S256', (array) ( $w['code_challenge_methods_supported'] ?? array() ), true );
			$out[] = ( $sc ? '✓' : '✗' ) . ' scope age.alcohol podržan';
			$out[] = ( $pk ? '✓' : '✗' ) . ' PKCE S256 podržan';
			$err   = $err || ! $sc || ! $pk;
		}
		$k = Domestic_Dob_Oidc::jwks( $a, true );
		if ( is_wp_error( $k ) ) {
			$out[] = '✗ ' . $k->get_error_message();
			$err   = true;
		} else {
			$dobri = array_filter( array_map( array( 'Domestic_Dob_Oidc', 'jwk_pem' ), $k ) );
			$out[] = ( $dobri ? '✓' : '✗' ) . ' JWKS: ' . count( $k ) . ' RSA ključ(eva), ispravnih (≥ 2048 bita): ' . count( $dobri );
			$err   = $err || ! $dobri;
		}
		$out[] = ( '' !== $a['client_id'] ? '✓' : '✗' ) . ' client_id ' . ( '' !== $a['client_id'] ? 'upisan' : 'nije upisan' );
		$err   = $err || '' === $a['client_id'];
		wp_safe_redirect( self::url( array( 'dsd_msg' => rawurlencode( implode( "\n", $out ) ), 'dsd_err' => $err ? 1 : 0 ) ) );
		exit;
	}
}
