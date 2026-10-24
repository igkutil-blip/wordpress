<?php
/**
 * Domestic → Slike proizvoda: Uvoz paketa (učitaj zipove → pregled → Uvezi), Zamjena slike na istoj adresi, Za provjeru, Dnevnik.
 */

defined( 'ABSPATH' ) || exit;

final class Domestic_Slike_Admin {

	const STR = 'domestic-slike';

	public static function init(): void {
		Domestic_Menu::add( self::STR, 'Slike proizvoda', array( __CLASS__, 'stranica' ), 25 );
		foreach ( array( 'ucitaj', 'uvezi', 'odbaci', 'zamijeni', 'rijeseno' ) as $a ) {
			add_action( 'admin_post_dss_' . $a, array( __CLASS__, $a ) );
		}
	}

	private static function provjeri( string $nonce ): void {
		if ( ! current_user_can( Domestic_Menu::CAP ) || ! current_user_can( 'upload_files' ) ) {
			wp_die( 'Nemate ovlasti.' );
		}
		check_admin_referer( $nonce );
	}

	private static function poruka( string $tekst, string $vrsta = 'success' ): void {
		$p   = get_transient( 'dss_poruke_' . get_current_user_id() );
		$p   = is_array( $p ) ? $p : array();
		$p[] = array( $vrsta, $tekst );
		set_transient( 'dss_poruke_' . get_current_user_id(), $p, 300 );
	}

	private static function natrag( string $tab = 'uvoz' ): void {
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::STR . '&tab=' . $tab ) );
		exit;
	}

	/* ---------- radnje ---------- */

	public static function ucitaj(): void {
		self::provjeri( 'dss_ucitaj' );
		$f = $_FILES['zip'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! $f || empty( $f['name'] ) ) {
			self::poruka( 'Odaberite barem jedan zip.', 'error' );
			self::natrag();
		}
		foreach ( (array) $f['name'] as $i => $ime ) {
			$tmp = is_array( $f['tmp_name'] ) ? $f['tmp_name'][ $i ] : $f['tmp_name'];
			$err = is_array( $f['error'] ) ? $f['error'][ $i ] : $f['error'];
			if ( UPLOAD_ERR_OK !== (int) $err || ! is_uploaded_file( $tmp ) ) {
				self::poruka( esc_html( $ime ) . ': učitavanje nije uspjelo.', 'error' );
				continue;
			}
			$a = Domestic_Slike_Paket::pripremi( $tmp, (string) $ime );
			if ( is_wp_error( $a ) ) {
				self::poruka( esc_html( $ime ) . ': ' . esc_html( $a->get_error_message() ), 'error' );
			}
		}
		self::natrag();
	}

	public static function uvezi(): void {
		self::provjeri( 'dss_uvezi' );
		$t  = sanitize_key( wp_unslash( $_POST['token'] ?? '' ) );
		$st = array();
		foreach ( (array) ( $_POST['pos'] ?? array() ) as $r ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$r    = wp_unslash( $r );
			$st[] = array( 'naziv' => sanitize_text_field( $r['naziv'] ?? '' ), 'kolicina' => sanitize_text_field( $r['kolicina'] ?? '' ), 'opis' => sanitize_text_field( $r['opis'] ?? '' ), 'ikona' => sanitize_key( $r['ikona'] ?? 'casa' ) );
		}
		$o = array(
			'proizvod'     => absint( $_POST['proizvod'] ?? 0 ),
			'cilj'         => absint( $_POST['cilj'] ?? 0 ),
			'ikone_izvor'  => absint( $_POST['ikone_izvor'] ?? 0 ),
			'posluzivanje' => $st,
			'dodatne'      => ! empty( $_POST['dodatne'] ),
			'napomene'     => ! empty( $_POST['napomene'] ),
		);
		// galerija: ako je odabran drugi proizvod od predloženog, zadržava se cijela njegova galerija
		if ( absint( $_POST['predlozeni'] ?? 0 ) === $o['proizvod'] ) {
			$o['zadrzi'] = array_map( 'absint', (array) ( $_POST['zadrzi'] ?? array() ) );
		}
		$r = Domestic_Slike_Paket::uvezi( $t, $o );
		if ( is_wp_error( $r ) ) {
			self::poruka( esc_html( $r->get_error_message() ), 'error' );
		} else {
			self::poruka( 'Uvezeno: <a href="' . esc_url( get_permalink( $r['proizvod'] ) ) . '" target="_blank">' . esc_html( get_the_title( $r['proizvod'] ) ) . '</a> – galerija ' . esc_html( implode( ', ', $r['galerija'] ) ) . '.' );
		}
		self::natrag();
	}

	public static function odbaci(): void {
		self::provjeri( 'dss_uvezi' );
		Domestic_Slike_Paket::obrisi_rad( sanitize_key( wp_unslash( $_POST['token'] ?? '' ) ) );
		self::poruka( 'Paket odbačen.' );
		self::natrag();
	}

	public static function zamijeni(): void {
		self::provjeri( 'dss_zamijeni' );
		$id = absint( $_POST['slika'] ?? 0 );
		$f  = $_FILES['datoteka'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! $f || UPLOAD_ERR_OK !== (int) $f['error'] || ! is_uploaded_file( $f['tmp_name'] ) ) {
			self::poruka( 'Odaberite novu sliku.', 'error' );
			self::natrag( 'zamjena' );
		}
		$r = Domestic_Slike::zamijeni( $id, $f['tmp_name'] );
		if ( is_wp_error( $r ) ) {
			self::poruka( esc_html( $r->get_error_message() ), 'error' );
		} else {
			Domestic_Slike::zapisi( array( 'vrsta' => 'zamjena', 'slika' => $id ) );
			self::poruka( 'Slika ' . $id . ' zamijenjena na istoj adresi: ' . esc_html( wp_basename( get_attached_file( $id ) ) ) . ' (' . size_format( $r['velicina'] ) . '). Na živoj stranici nakon zamjene očistite nginx predmemoriju i Cloudflare.' );
		}
		self::natrag( 'zamjena' );
	}

	public static function rijeseno(): void {
		self::provjeri( 'dss_rijeseno' );
		$pid = absint( $_POST['proizvod'] ?? 0 );
		$i   = absint( $_POST['i'] ?? 0 );
		$p   = get_post_meta( $pid, Domestic_Slike::PROVJERA, true );
		if ( is_array( $p ) && isset( $p[ $i ] ) ) {
			$p[ $i ]['rijeseno'] = time();
			$p[ $i ]['odgovor']  = sanitize_textarea_field( wp_unslash( $_POST['odgovor'] ?? '' ) );
			update_post_meta( $pid, Domestic_Slike::PROVJERA, $p );
		}
		self::natrag( 'provjera' );
	}

	/* ---------- prikaz ---------- */

	/** Mala sličica kao data: adresa (radna mapa nije javna). */
	private static function sl( string $put, int $max = 200 ): string {
		$im = file_exists( $put ) ? Domestic_Slike::ucitaj( $put ) : false;
		if ( ! $im ) {
			return '';
		}
		$w = imagesx( $im );
		$h = imagesy( $im );
		$k = $max / max( $w, $h );
		$m = imagecreatetruecolor( max( 1, (int) ( $w * $k ) ), max( 1, (int) ( $h * $k ) ) );
		imagefill( $m, 0, 0, imagecolorallocate( $m, 247, 243, 235 ) );
		imagealphablending( $m, true );
		imagecopyresampled( $m, $im, 0, 0, 0, 0, imagesx( $m ), imagesy( $m ), $w, $h );
		ob_start();
		imagejpeg( $m, null, 80 );
		$d = ob_get_clean();
		imagedestroy( $im );
		imagedestroy( $m );
		return '<img src="data:image/jpeg;base64,' . base64_encode( $d ) . '" alt="">';
	}

	public static function stranica(): void {
		$tab  = sanitize_key( $_GET['tab'] ?? 'uvoz' ); // phpcs:ignore WordPress.Security.NonceVerification
		$tabs = array( 'uvoz' => 'Uvoz paketa', 'zamjena' => 'Zamjena slike', 'provjera' => 'Za provjeru', 'dnevnik' => 'Dnevnik' );
		echo '<div class="wrap dss"><h1>Slike proizvoda</h1>' . self::stil();
		foreach ( (array) get_transient( 'dss_poruke_' . get_current_user_id() ) as $p ) {
			if ( is_array( $p ) ) {
				echo '<div class="notice notice-' . esc_attr( $p[0] ) . ' is-dismissible"><p>' . wp_kses_post( $p[1] ) . '</p></div>';
			}
		}
		delete_transient( 'dss_poruke_' . get_current_user_id() );
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $k => $n ) {
			echo '<a class="nav-tab' . ( $k === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . self::STR . '&tab=' . $k ) ) . '">' . esc_html( $n ) . '</a>';
		}
		echo '</nav>';
		$m = 'tab_' . ( isset( $tabs[ $tab ] ) ? $tab : 'uvoz' );
		self::$m();
		echo '</div>';
	}

	private static function stil(): string {
		return '<style>.dss .dss-paket{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 18px;margin:18px 0;max-width:1100px}
.dss .dss-slike{display:flex;gap:12px;flex-wrap:wrap;margin:10px 0}.dss .dss-slike figure{margin:0;text-align:center;font-size:12px;color:#50575e}
.dss .dss-slike img{display:block;width:150px;height:150px;object-fit:contain;background:#f7f3eb;border:1px solid #e2d6c3;border-radius:6px;margin-bottom:4px}
.dss .dss-ik img{width:72px;height:72px;background:#f7f3eb;border-radius:50%}.dss .dss-napomena{background:#fcf9e8;border-left:4px solid #dba617;padding:8px 12px;margin:10px 0}
.dss .dss-upoz{color:#b32d2e;font-weight:600}.dss table.dss-pos input{width:100%}.dss .dss-gal label{display:inline-block;margin:0 10px 6px 0;text-align:center;font-size:11px}
.dss .dss-gal img{display:block;width:70px;height:70px;object-fit:contain;background:#f7f3eb;border:1px solid #e2d6c3}</style>';
	}

	private static function tab_uvoz(): void {
		echo '<p>Paket = zip s mapom proizvoda: ambijentalna slika (naziv kao stara glavna slika), <code>studio-…</code>, <code>posluzivanje-…</code> i <code>alt.txt</code>. '
			. 'Ambijentalna ide <b>na staru adresu glavne slike</b> (isti naziv i format; adrese se ne mijenjaju), studijska i posluživanje kao nove slike, ikone se izrežu s kartice posluživanja. '
			. 'Sve slike se ponovno spremaju bez metapodataka; ostale datoteke iz zipa (upute i sl.) se ne čitaju.</p>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="dss_ucitaj">';
		wp_nonce_field( 'dss_ucitaj' );
		echo '<p><input type="file" name="zip[]" accept=".zip" multiple required> <button class="button button-primary">Učitaj i pregledaj</button></p></form>';
		global $wpdb;
		$tok = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id", $wpdb->esc_like( Domestic_Slike_Paket::PREFIKS ) . '%' ) );
		foreach ( $tok as $o ) {
			$a = get_option( $o );
			if ( is_array( $a ) && empty( $a['uvezeno'] ) && ! empty( $a['token'] ) ) {
				self::paket( $a );
			}
		}
	}

	private static function paket( array $a ): void {
		$pred  = $a['kandidati'][0] ?? null;
		$pid   = $pred ? (int) $pred['id'] : 0;
		$p     = $pid ? wc_get_product( $pid ) : null;
		$glav  = $p ? (int) $p->get_image_id() : 0;
		$s     = $a['slike'];
		echo '<div class="dss-paket"><h2 style="margin-top:0">' . esc_html( $a['mapa'] ? $a['mapa'] : $a['zip'] ) . ' <small style="font-weight:400;color:#646970">' . esc_html( $a['zip'] ) . '</small></h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="dss_uvezi"><input type="hidden" name="token" value="' . esc_attr( $a['token'] ) . '"><input type="hidden" name="predlozeni" value="' . (int) $pid . '">';
		wp_nonce_field( 'dss_uvezi' );
		foreach ( (array) ( $a['upozorenja'] ?? array() ) as $u ) {
			echo '<div class="notice ' . ( 'stop' === $u[0] ? 'notice-warning' : 'notice-info' ) . ' inline"><p>' . ( 'stop' === $u[0] ? '<strong>Provjerite prije uvoza:</strong> ' : '' ) . esc_html( $u[1] ) . '</p></div>';
		}

		echo '<div class="dss-slike">';
		$uloge = array( 'amb' => 'Ambijentalna → stara adresa', 'studio' => 'Studijska (nova)', 'posl' => 'Kako poslužiti (nova)' );
		foreach ( $uloge as $u => $n ) {
			if ( null !== $a[ $u ] ) {
				echo '<figure>' . self::sl( $s[ $a[ $u ] ]['put'] ) . esc_html( $n ) . '<br><code>' . esc_html( $s[ $a[ $u ] ]['naziv'] ) . '</code></figure>'; // phpcs:ignore
			} else {
				echo '<figure class="dss-upoz">' . esc_html( $n ) . ': nema u paketu</figure>';
			}
		}
		$svoje = $p ? array_merge( array( (int) $p->get_image_id() ), array_map( 'intval', $p->get_gallery_image_ids() ) ) : array();
		foreach ( $a['dodatne'] as $k ) {
			$ista = array_intersect( Domestic_Slike_Paket::prilozi_po_nazivu( $s[ $k ]['naziv'] ), $svoje );
			$opis = $ista ? 'Dodatna → zamjena slike ' . (int) reset( $ista ) . ' na istoj adresi' : 'Dodatna (nova, iza)';
			echo '<figure>' . self::sl( $s[ $k ]['put'] ) . esc_html( $opis ) . '<br><code>' . esc_html( $s[ $k ]['naziv'] ) . '</code></figure>'; // phpcs:ignore
		}
		echo '</div>';
		if ( $a['ostalo'] ) {
			echo '<p class="description">Nije učitano (nisu slike): ' . esc_html( implode( ', ', $a['ostalo'] ) ) . '</p>';
		}

		// proizvod i stara adresa
		echo '<table class="form-table"><tr><th>Proizvod</th><td><select name="proizvod" class="dss-proizvod" style="max-width:420px">';
		$svi = get_posts( array( 'post_type' => 'product', 'post_status' => array( 'publish', 'private', 'draft' ), 'numberposts' => 1000, 'orderby' => 'title', 'order' => 'ASC' ) );
		echo '<option value="0">— odaberite —</option>';
		foreach ( $svi as $x ) {
			echo '<option value="' . (int) $x->ID . '" data-glavna="' . (int) get_post_thumbnail_id( $x ) . '"' . selected( $x->ID, $pid, false ) . '>' . esc_html( $x->post_title . ( 'publish' !== $x->post_status ? ' (' . $x->post_status . ')' : '' ) ) . '</option>';
		}
		echo '</select>';
		if ( count( $a['kandidati'] ) > 1 ) {
			echo '<p class="description">Prepoznato i: ' . esc_html( implode( ', ', array_map( static function ( $k ) { return $k['naziv']; }, array_slice( $a['kandidati'], 1 ) ) ) ) . '</p>';
		}
		if ( $pred && 'publish' !== $pred['status'] ) {
			echo '<p class="dss-upoz">Proizvod nije objavljen (' . esc_html( $pred['status'] ) . ').</p>';
		}
		echo '</td></tr><tr><th>Ambijentalna ide u sliku (ID)</th><td><input type="number" name="cilj" class="dss-cilj small-text" value="' . (int) $glav . '"> ';
		if ( $glav ) {
			echo '<code>' . esc_html( wp_basename( (string) get_attached_file( $glav ) ) ) . '</code> – trenutna glavna slika';
			$dij = Domestic_Slike_Paket::dijele( $glav, $pid );
			if ( $dij ) {
				echo '<p class="dss-upoz">Ova slika je glavna i kod: ' . esc_html( implode( ', ', array_map( 'get_the_title', $dij ) ) ) . ' – zamjena će se vidjeti i tamo. Za zasebnu sliku upišite ID druge stare slike tog proizvoda.</p>';
			}
		} else {
			echo '<span class="description">proizvod nema glavnu sliku – ambijentalna će biti nova slika (0)</span>';
		}
		echo '</td></tr>';

		// galerija koja ostaje
		$gal = $p ? $p->get_gallery_image_ids() : array();
		if ( $gal ) {
			echo '<tr><th>Postojeća galerija</th><td class="dss-gal"><p class="description">Označene ostaju iza novih slika (npr. medalje).</p>';
			foreach ( $gal as $g ) {
				echo '<label><input type="checkbox" name="zadrzi[]" value="' . (int) $g . '" checked>' . wp_get_attachment_image( $g, 'thumbnail' ) . (int) $g . '</label>';
			}
			echo '</td></tr>';
		}

		// posluživanje
		echo '<tr><th>Kako poslužiti</th><td>';
		$izv = (int) $a['izvor_ikona'];
		echo '<p>Ilustracije: <select name="ikone_izvor"><option value="0">nove – izrezane s kartice</option>';
		foreach ( Domestic_Slike_Paket::proizvodi_s_posluzivanjem() as $x => $st ) {
			if ( count( array_filter( array_column( $st, 'slika' ) ) ) ) {
				echo '<option value="' . (int) $x . '"' . selected( $x, $izv, false ) . '>iste kao: ' . esc_html( get_the_title( $x ) ) . '</option>';
			}
		}
		echo '</select>' . ( $izv ? ' <span class="description">ista kartica već postoji – ikone se ne učitavaju ponovno</span>' : '' ) . '</p>';
		$ik_nazivi = class_exists( 'Domestic_Proizvod_Data' ) ? Domestic_Proizvod_Data::IKONE : array( 'casa' => 'Čaša', 'kava' => 'Kava', 'kolac' => 'Slastica', 'koktel' => 'Koktel' );
		echo '<table class="widefat dss-pos"><thead><tr><th style="width:84px">Ilustracija</th><th>Naziv</th><th style="width:110px">Količina</th><th>Opis (mali tekst)</th><th style="width:110px">Ikona</th></tr></thead><tbody>';
		$red = $a['stavke'] ? $a['stavke'] : array_fill( 0, 3, array( 'naziv' => '', 'kolicina' => '', 'opis' => '', 'ikona' => 'casa' ) );
		foreach ( $red as $i => $st ) {
			echo '<tr><td class="dss-ik">' . ( ! empty( $a['ikone'][ $i ]['put'] ) ? self::sl( $a['ikone'][ $i ]['put'], 90 ) : '' ) . '</td>' // phpcs:ignore
				. '<td><input name="pos[' . (int) $i . '][naziv]" value="' . esc_attr( $st['naziv'] ) . '"></td>'
				. '<td><input name="pos[' . (int) $i . '][kolicina]" value="' . esc_attr( $st['kolicina'] ) . '"></td>'
				. '<td><input name="pos[' . (int) $i . '][opis]" value="' . esc_attr( $st['opis'] ) . '" placeholder="npr. Mala čašica prije obroka."></td><td><select name="pos[' . (int) $i . '][ikona]">';
			foreach ( $ik_nazivi as $k => $n ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( $k, $st['ikona'], false ) . '>' . esc_html( $n ) . '</option>';
			}
			echo '</select></td></tr>';
		}
		echo '</tbody></table>';
		if ( ! $a['ikone'] && null !== $a['posl'] ) {
			echo '<p class="dss-upoz">Krugovi ilustracija na kartici nisu nađeni – ikone se neće izrezati (kartica drugačijeg rasporeda).</p>';
		}
		echo '<p class="description">Naziv i količina pročitani iz alt teksta kartice; opis preuzet s drugih proizvoda s istom stavkom – provjerite prema kartici.</p></td></tr>';

		if ( $a['napomene'] ) {
			echo '<tr><th>Napomene iz paketa</th><td>';
			foreach ( $a['napomene'] as $n ) {
				echo '<div class="dss-napomena">' . esc_html( $n ) . '</div>';
			}
			echo '<label><input type="checkbox" name="napomene" value="1" checked> upiši u „Za provjeru” uz proizvod</label></td></tr>';
		}
		echo '</table>';
		if ( $a['dodatne'] ) {
			echo '<p><label><input type="checkbox" name="dodatne" value="1" checked> dodaj i dodatne slike iz paketa u galeriju</label></p>';
		} else {
			echo '<input type="hidden" name="dodatne" value="1">';
		}
		echo '<p><button class="button button-primary">Uvezi</button> <button class="button" formaction="' . esc_url( admin_url( 'admin-post.php' ) ) . '" name="action" value="dss_odbaci">Odbaci</button></p></form></div>';
		// promjena proizvoda → ID glavne slike odabranog proizvoda
		echo '<script>document.querySelectorAll(".dss-proizvod").forEach(function(s){s.addEventListener("change",function(){var f=s.closest("form"),o=s.options[s.selectedIndex];f.querySelector(".dss-cilj").value=o.dataset.glavna||0;});});</script>';
	}

	private static function tab_zamjena(): void {
		echo '<p>Zamjena datoteke postojeće slike <b>na istoj adresi</b> (naziv datoteke, naslov i alt ostaju; SEO). Nova slika se sprema u formatu stare (PNG fotografija s paletom 256 boja – višestruko manja), bez metapodataka; izvedenice se izrađuju ponovno. ID slike: Mediji → slika → u adresi <code>item=…</code>.</p>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="dss_zamijeni">';
		wp_nonce_field( 'dss_zamijeni' );
		echo '<table class="form-table"><tr><th><label for="dss-id">ID slike</label></th><td><input id="dss-id" type="number" name="slika" class="small-text" required></td></tr><tr><th>Nova slika</th><td><input type="file" name="datoteka" accept="image/*" required></td></tr></table>';
		echo '<p><button class="button button-primary">Zamijeni na istoj adresi</button></p></form>';
	}

	private static function tab_provjera(): void {
		echo '<p>Napomene iz paketa (autorske količine posluživanja, medalje bez slike i sl.) – za provjeru s vlasnikom. Kad se riješi, upišite odgovor.</p>';
		$ids = get_posts( array( 'post_type' => 'product', 'post_status' => 'any', 'meta_key' => Domestic_Slike::PROVJERA, 'fields' => 'ids', 'numberposts' => 500 ) ); // phpcs:ignore
		$ima = false;
		echo '<table class="widefat striped" style="max-width:1100px"><thead><tr><th style="width:220px">Proizvod</th><th>Napomena</th><th style="width:300px">Odgovor</th></tr></thead><tbody>';
		foreach ( $ids as $pid ) {
			foreach ( (array) get_post_meta( $pid, Domestic_Slike::PROVJERA, true ) as $i => $n ) {
				$ima = true;
				echo '<tr><td><a href="' . esc_url( get_edit_post_link( $pid ) ) . '">' . esc_html( get_the_title( $pid ) ) . '</a><br><small>' . esc_html( wp_date( 'j.n.Y.', (int) $n['t'] ) . ' · ' . $n['izvor'] ) . '</small></td><td>' . esc_html( $n['tekst'] ) . '</td><td>';
				if ( ! empty( $n['rijeseno'] ) ) {
					echo '✓ ' . esc_html( $n['odgovor'] ? $n['odgovor'] : 'riješeno' );
				} else {
					echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="dss_rijeseno"><input type="hidden" name="proizvod" value="' . (int) $pid . '"><input type="hidden" name="i" value="' . (int) $i . '">';
					wp_nonce_field( 'dss_rijeseno' );
					echo '<input name="odgovor" placeholder="odgovor / potvrđeno" style="width:68%"> <button class="button">Riješeno</button></form>';
				}
				echo '</td></tr>';
			}
		}
		if ( ! $ima ) {
			echo '<tr><td colspan="3">Nema napomena.</td></tr>';
		}
		echo '</tbody></table>';
	}

	private static function tab_dnevnik(): void {
		$d = get_option( Domestic_Slike::DNEVNIK, array() );
		echo '<table class="widefat striped" style="max-width:1100px"><thead><tr><th style="width:140px">Vrijeme</th><th>Radnja</th></tr></thead><tbody>';
		if ( ! $d ) {
			echo '<tr><td colspan="2">Još ništa.</td></tr>';
		}
		foreach ( (array) $d as $z ) {
			$t = 'paket' === ( $z['vrsta'] ?? '' )
				? 'Paket ' . ( $z['zip'] ?? '' ) . ' → ' . get_the_title( (int) $z['proizvod'] ) . ' (glavna ' . (int) $z['glavna'] . ', nove ' . implode( ', ', (array) $z['nove'] ) . ', ikone ' . implode( ', ', array_filter( (array) $z['ikone'] ) ) . ')'
				: 'Zamjena slike ' . (int) ( $z['slika'] ?? 0 ) . ' na istoj adresi';
			echo '<tr><td>' . esc_html( wp_date( 'j.n.Y. H:i', (int) $z['t'] ) ) . '</td><td>' . esc_html( $t ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
}
