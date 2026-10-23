<?php
/**
 * Pomoćnik probne kopije.
 *
 * - Radi samo kad stranica NIJE živa (domesticspirits.hr / www.domesticspirits.hr); na živoj samo javlja da je isključen.
 * - Mailovi: zadržavaju se i bilježe (zadnjih 50); po želji se preusmjeravaju na jednu adresu s oznakom [PROBA].
 * - Tražilice: noindex na cijeloj kopiji.
 * - REST (domestic-pomocnik/v1, prava install_plugins): status, učitavanje zipa dodatka domestic-*, Domestic postavke (opcije domestic_*).
 */

defined( 'ABSPATH' ) || exit;

final class Domestic_Pomocnik {

	const NS     = 'domestic-pomocnik/v1';
	const ZIVE   = array( 'domesticspirits.hr', 'www.domesticspirits.hr' );
	const OPT    = 'domestic_pomocnik';
	const MAILOVI = 'domestic_pomocnik_mailovi';

	public static function proba(): bool {
		$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		return ! in_array( $host, self::ZIVE, true );
	}

	public static function init(): void {
		if ( ! self::proba() ) {
			add_action( 'admin_notices', array( __CLASS__, 'obavijest_ziva' ) );
			return;
		}
		add_filter( 'pre_wp_mail', array( __CLASS__, 'mail' ), 999, 2 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ), 999 );
		add_filter( 'pre_option_blog_public', '__return_zero' );
		add_action( 'rest_api_init', array( __CLASS__, 'rute' ) );
		add_filter( 'wp_get_attachment_url', array( __CLASS__, 'verzija_url' ), 99, 2 );
		add_filter( 'wp_get_attachment_image_src', array( __CLASS__, 'verzija_src' ), 99, 2 );
		add_filter( 'wp_calculate_image_srcset', array( __CLASS__, 'verzija_srcset' ), 99, 5 );
		add_action( 'admin_bar_menu', array( __CLASS__, 'traka' ), 5 );
		if ( is_admin() ) {
			Domestic_Menu::add( 'domestic-pomocnik', 'Pomoćnik (proba)', array( __CLASS__, 'stranica' ), 90 );
			add_action( 'admin_post_dspm_spremi', array( __CLASS__, 'spremi' ) );
		}
	}

	/* ---------- zamijenjene slike: ?v= da nginx i preglednik ne daju staru kopiju (samo proba) ---------- */

	private static function verzija( int $id ): int {
		$v = get_option( 'domestic_pomocnik_verzije', array() );
		return is_array( $v ) ? (int) ( $v[ $id ] ?? 0 ) : 0;
	}

	private static function dodaj_v( string $url, int $v ): string {
		return $v && false === strpos( $url, 'v=' ) ? add_query_arg( 'v', $v, $url ) : $url;
	}

	public static function verzija_url( $url, $id ) {
		return is_string( $url ) ? self::dodaj_v( $url, self::verzija( (int) $id ) ) : $url;
	}

	public static function verzija_src( $img, $id ) {
		if ( is_array( $img ) && ! empty( $img[0] ) ) {
			$img[0] = self::dodaj_v( $img[0], self::verzija( (int) $id ) );
		}
		return $img;
	}

	public static function verzija_srcset( $sources, $size, $src, $meta, $id ) {
		$v = self::verzija( (int) $id );
		foreach ( (array) $sources as $k => $s ) {
			$sources[ $k ]['url'] = self::dodaj_v( $s['url'], $v );
		}
		return $sources;
	}

	public static function obavijest_ziva(): void {
		if ( current_user_can( 'activate_plugins' ) ) {
			echo '<div class="notice notice-warning"><p><b>Domestic – pomoćnik</b> namijenjen je samo probnoj kopiji i na živoj stranici ne radi ništa. Možete ga isključiti.</p></div>';
		}
	}

	public static function postavke(): array {
		$s = get_option( self::OPT, array() );
		return array_merge( array( 'preusmjeri' => '' ), is_array( $s ) ? $s : array() );
	}

	/* ---------- mailovi ---------- */

	/** Svaki mail se bilježi; šalje se samo na adresu za preusmjeravanje (ako je upisana). */
	public static function mail( $null, $atts ) {
		$to  = is_array( $atts['to'] ) ? implode( ', ', $atts['to'] ) : (string) $atts['to'];
		$log = get_option( self::MAILOVI, array() );
		$log = is_array( $log ) ? $log : array();
		array_unshift( $log, array( 't' => time(), 'to' => $to, 'subject' => (string) $atts['subject'] ) );
		update_option( self::MAILOVI, array_slice( $log, 0, 50 ), false );

		$cilj = self::postavke()['preusmjeri'];
		if ( ! $cilj || ! is_email( $cilj ) ) {
			return true; // zadržan, ne šalje se
		}
		remove_filter( 'pre_wp_mail', array( __CLASS__, 'mail' ), 999 );
		$headers = array_filter(
			(array) $atts['headers'],
			static function ( $h ) {
				return ! preg_match( '/^\s*(cc|bcc)\s*:/i', (string) $h );
			}
		);
		$ok = wp_mail( $cilj, '[PROBA → ' . $to . '] ' . $atts['subject'], $atts['message'], $headers, $atts['attachments'] );
		add_filter( 'pre_wp_mail', array( __CLASS__, 'mail' ), 999, 2 );
		return $ok;
	}

	public static function robots( array $r ): array {
		$r['noindex']  = true;
		$r['nofollow'] = true;
		unset( $r['index'], $r['follow'], $r['max-image-preview'] );
		return $r;
	}

	public static function traka( $bar ): void {
		if ( is_user_logged_in() ) {
			$bar->add_node( array( 'id' => 'domestic-proba', 'title' => 'PROBNA KOPIJA – mailovi zadržani', 'href' => admin_url( 'admin.php?page=domestic-pomocnik' ), 'meta' => array( 'html' => '<style>#wp-admin-bar-domestic-proba>a{background:#b32d2e!important;color:#fff!important}</style>' ) ) );
		}
	}

	/* ---------- REST ---------- */

	public static function rute(): void {
		$perm = static function () {
			return current_user_can( 'install_plugins' );
		};
		register_rest_route( self::NS, '/status', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'status' ), 'permission_callback' => $perm ) );
		register_rest_route( self::NS, '/plugin', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'plugin' ), 'permission_callback' => $perm ) );
		register_rest_route( self::NS, '/opcija/(?P<ime>domestic_[a-z0-9_]+|rank-math-options-titles|rank-math-options-general|woocommerce_email_header_image)', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'opcija_citaj' ), 'permission_callback' => $perm ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'opcija_pisi' ), 'permission_callback' => $perm ),
		) );
		register_rest_route( self::NS, '/tema', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'tema_citaj' ), 'permission_callback' => $perm ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'tema_pisi' ), 'permission_callback' => $perm ),
		) );
		register_rest_route( self::NS, '/medij/(?P<id>\\d+)', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'medij' ), 'permission_callback' => $perm ) );
		register_rest_route( self::NS, '/mailovi', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'mailovi' ), 'permission_callback' => $perm ) );
	}

	public static function status(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$domestic = array();
		foreach ( get_plugins() as $file => $p ) {
			if ( 0 === strpos( $file, 'domestic-' ) ) {
				$domestic[ $file ] = array( 'verzija' => $p['Version'], 'aktivan' => is_plugin_active( $file ) );
			}
		}
		return array(
			'home'        => home_url(),
			'pomocnik'    => DSPM_VER,
			'wp'          => get_bloginfo( 'version' ),
			'wc'          => defined( 'WC_VERSION' ) ? WC_VERSION : null,
			'php'         => PHP_VERSION,
			'stalne_veze' => get_option( 'permalink_structure' ),
			'mailovi'     => self::postavke()['preusmjeri'] ? 'preusmjereni' : 'zadržani',
			'domestic'    => $domestic,
		);
	}

	/** Zip s jednom mapom domestic-* → zamjenjuje postojeći dodatak; activate=1 ga uključuje. */
	public static function plugin( WP_REST_Request $req ) {
		$f = $req->get_file_params()['zip'] ?? null;
		if ( ! $f ) {
			return new WP_Error( 'dspm_zip', 'Nedostaje zip (polje „zip”) ili je veći od dopuštenog (' . ini_get( 'upload_max_filesize' ) . ').', array( 'status' => 400 ) );
		}
		if ( UPLOAD_ERR_OK !== ( $f['error'] ?? 1 ) || ! is_uploaded_file( $f['tmp_name'] ) ) {
			return new WP_Error( 'dspm_zip', 'Učitavanje zipa nije uspjelo (PHP greška ' . (int) ( $f['error'] ?? 0 ) . ').', array( 'status' => 400 ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		WP_Filesystem();
		global $wp_filesystem;

		$tmp = trailingslashit( WP_CONTENT_DIR ) . 'upgrade/dspm-' . wp_generate_password( 10, false );
		$r   = unzip_file( $f['tmp_name'], $tmp );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$mape = array_values( array_diff( scandir( $tmp ), array( '.', '..', '__MACOSX' ) ) );
		$mapa = 1 === count( $mape ) ? $mape[0] : '';
		if ( ! preg_match( '/^domestic-[a-z0-9-]+$/', $mapa ) || ! is_dir( "$tmp/$mapa" ) || ! file_exists( "$tmp/$mapa/$mapa.php" ) ) {
			$wp_filesystem->delete( $tmp, true );
			return new WP_Error( 'dspm_mapa', 'Zip mora imati jednu mapu domestic-<naziv> s datotekom domestic-<naziv>.php.', array( 'status' => 400 ) );
		}
		$cilj = trailingslashit( WP_PLUGIN_DIR ) . $mapa;
		if ( is_dir( $cilj ) ) {
			$wp_filesystem->delete( $cilj, true );
		}
		$r = copy_dir( "$tmp/$mapa", $cilj );
		$wp_filesystem->delete( $tmp, true );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( function_exists( 'opcache_invalidate' ) ) {
			foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $cilj, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
				if ( 'php' === $file->getExtension() ) {
					opcache_invalidate( $file->getPathname(), true );
				}
			}
		}
		wp_clean_plugins_cache();
		$file = "$mapa/$mapa.php";
		$out  = array( 'plugin' => $file, 'verzija' => get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false )['Version'] ?? '' );
		if ( $req->get_param( 'activate' ) && ! is_plugin_active( $file ) ) {
			$a = activate_plugin( $file );
			if ( is_wp_error( $a ) ) {
				return $a;
			}
		}
		$out['aktivan'] = is_plugin_active( $file );
		return $out;
	}

	public static function opcija_citaj( WP_REST_Request $req ) {
		return array( 'ime' => $req['ime'], 'vrijednost' => get_option( $req['ime'], null ) );
	}

	/** Tijelo: {"vrijednost": ...} – zamjenjuje cijelu opciju, ili {"spoji": {...}} – spaja s postojećim nizom. */
	public static function opcija_pisi( WP_REST_Request $req ) {
		$ime  = $req['ime'];
		$json = $req->get_json_params();
		if ( isset( $json['spoji'] ) && is_array( $json['spoji'] ) ) {
			$stara = get_option( $ime, array() );
			$nova  = array_replace_recursive( is_array( $stara ) ? $stara : array(), $json['spoji'] );
		} elseif ( array_key_exists( 'vrijednost', (array) $json ) ) {
			$nova = $json['vrijednost'];
		} else {
			return new WP_Error( 'dspm_opcija', 'Pošalji {"vrijednost": …} ili {"spoji": {…}}.', array( 'status' => 400 ) );
		}
		update_option( $ime, $nova, false );
		return array( 'ime' => $ime, 'vrijednost' => get_option( $ime ) );
	}

	/** Postavke teme (Flatsome: logotipi, širine, boje…) – samo skalarne vrijednosti. */
	public static function tema_citaj(): array {
		$mods = get_theme_mods();
		$out  = array();
		foreach ( (array) $mods as $k => $v ) {
			if ( is_scalar( $v ) || null === $v ) {
				$out[ $k ] = $v;
			}
		}
		ksort( $out );
		return array( 'tema' => get_stylesheet(), 'mods' => $out );
	}

	/** Tijelo: {"mods": {"site_logo": "https://…", "logo_width": 300}} */
	public static function tema_pisi( WP_REST_Request $req ) {
		$mods = $req->get_json_params()['mods'] ?? null;
		if ( ! is_array( $mods ) || ! $mods ) {
			return new WP_Error( 'dspm_tema', 'Pošalji {"mods": {…}}.', array( 'status' => 400 ) );
		}
		foreach ( $mods as $k => $v ) {
			if ( ! preg_match( '/^[a-z0-9_\-]+$/i', (string) $k ) || ! ( is_scalar( $v ) || null === $v ) ) {
				return new WP_Error( 'dspm_tema', 'Neispravan ključ ili vrijednost: ' . $k, array( 'status' => 400 ) );
			}
			if ( null === $v ) {
				remove_theme_mod( $k );
			} else {
				set_theme_mod( $k, $v );
			}
		}
		return self::tema_citaj();
	}

	/**
	 * Zamjena datoteke postojeće slike NA ISTOJ ADRESI (SEO: adresa, naziv datoteke, naslov i alt ostaju).
	 * Multipart polje „datoteka”; ekstenzija mora biti ista kao postojeća. Stare izvedenice se brišu i ponovno izrađuju.
	 */
	public static function medij( WP_REST_Request $req ) {
		$id   = (int) $req['id'];
		$put  = get_attached_file( $id );
		$f    = $req->get_file_params()['datoteka'] ?? null;
		if ( ! $put || 'attachment' !== get_post_type( $id ) || ! wp_attachment_is_image( $id ) ) {
			return new WP_Error( 'dspm_medij', 'Nema slike s tim ID-om.', array( 'status' => 404 ) );
		}
		if ( ! $f || UPLOAD_ERR_OK !== ( $f['error'] ?? 1 ) || ! is_uploaded_file( $f['tmp_name'] ) ) {
			return new WP_Error( 'dspm_medij', 'Nedostaje datoteka (polje „datoteka”).', array( 'status' => 400 ) );
		}
		$stara = strtolower( pathinfo( $put, PATHINFO_EXTENSION ) );
		$nova  = wp_check_filetype_and_ext( $f['tmp_name'], 'x.' . $stara );
		if ( empty( $nova['type'] ) || strtolower( (string) $nova['ext'] ) !== $stara ) {
			return new WP_Error( 'dspm_medij', 'Nova slika mora biti istog formata (.' . $stara . ') da adresa ostane ista.', array( 'status' => 400 ) );
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$meta = wp_get_attachment_metadata( $id );
		$dir  = trailingslashit( dirname( $put ) );
		foreach ( (array) ( $meta['sizes'] ?? array() ) as $v ) {
			if ( ! empty( $v['file'] ) && file_exists( $dir . $v['file'] ) ) {
				wp_delete_file( $dir . $v['file'] );
			}
		}
		if ( ! empty( $meta['original_image'] ) && file_exists( $dir . $meta['original_image'] ) ) {
			wp_delete_file( $dir . $meta['original_image'] );
		}
		if ( ! move_uploaded_file( $f['tmp_name'], $put ) ) {
			return new WP_Error( 'dspm_medij', 'Spremanje datoteke nije uspjelo.', array( 'status' => 500 ) );
		}
		if ( 'site-icon' === get_post_meta( $id, '_wp_attachment_context', true ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-site-icon.php';
			add_filter( 'intermediate_image_sizes_advanced', array( new WP_Site_Icon(), 'additional_sizes' ) ); // 32, 180, 192, 270
		}
		$nm = wp_generate_attachment_metadata( $id, $put );
		wp_update_attachment_metadata( $id, $nm );
		clean_post_cache( $id );
		$ver        = get_option( 'domestic_pomocnik_verzije', array() );
		$ver        = is_array( $ver ) ? $ver : array();
		$ver[ $id ] = time();
		update_option( 'domestic_pomocnik_verzije', $ver, false );
		return array( 'id' => $id, 'url' => wp_get_attachment_url( $id ), 'sirina' => $nm['width'] ?? null, 'visina' => $nm['height'] ?? null, 'velicine' => array_keys( (array) ( $nm['sizes'] ?? array() ) ) );
	}

	public static function mailovi(): array {
		$log = get_option( self::MAILOVI, array() );
		return is_array( $log ) ? $log : array();
	}

	/* ---------- administracija ---------- */

	public static function stranica(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = self::postavke();
		echo '<div class="wrap"><h1>Pomoćnik probne kopije</h1>';
		if ( isset( $_GET['dspm_ok'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>Spremljeno.</p></div>';
		}
		echo '<p>Ova stranica je <b>probna kopija</b> (' . esc_html( home_url() ) . '). Tražilice je ne indeksiraju, a mailovi (narudžbe, kupci, Emilija) se <b>ne šalju</b> – bilježe se ispod.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'dspm_spremi' );
		echo '<input type="hidden" name="action" value="dspm_spremi"><table class="form-table"><tr><th><label for="dspm-p">Preusmjeri mailove na</label></th><td><input id="dspm-p" type="email" name="preusmjeri" class="regular-text" value="' . esc_attr( $s['preusmjeri'] ) . '"><p class="description">Prazno = nijedan mail ne izlazi. Upisana adresa prima sve mailove s oznakom [PROBA → izvorni primatelj].</p></td></tr></table><p><button class="button button-primary">Spremi</button></p></form>';
		echo '<h2>Zadnji mailovi</h2><table class="widefat striped" style="max-width:900px"><thead><tr><th>Vrijeme</th><th>Primatelj</th><th>Naslov</th></tr></thead><tbody>';
		$log = self::mailovi();
		if ( ! $log ) {
			echo '<tr><td colspan="3">Još nema mailova.</td></tr>';
		}
		foreach ( $log as $m ) {
			echo '<tr><td>' . esc_html( wp_date( 'j.n.Y. H:i', (int) $m['t'] ) ) . '</td><td>' . esc_html( $m['to'] ) . '</td><td>' . esc_html( $m['subject'] ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public static function spremi(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'dspm_spremi' ) ) {
			wp_die( 'Nema dozvole.' );
		}
		$e = sanitize_email( wp_unslash( $_POST['preusmjeri'] ?? '' ) );
		update_option( self::OPT, array( 'preusmjeri' => is_email( $e ) ? $e : '' ), false );
		wp_safe_redirect( admin_url( 'admin.php?page=domestic-pomocnik&dspm_ok=1' ) );
		exit;
	}
}
