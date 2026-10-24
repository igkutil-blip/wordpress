<?php
/**
 * Paket slika za jedan proizvod (zip): ambijentalna (naziv = stara glavna slika), studio-…, posluzivanje-…, alt.txt.
 * 1) pripremi(): raspakira samo slike i alt.txt (ostalo se ne čita – upute/opisi izvora nikad ne idu na web), očisti metapodatke,
 *    prepozna proizvod, izreže ikone s kartice posluživanja, pročita „Kako poslužiti” iz alt teksta.
 * 2) uvezi(): ambijentalna na staru adresu glavne slike (isti format; PNG s paletom), studijska i posluživanje kao nove slike,
 *    ikone (nove ili postojeće iste), galerija glavna → studio → posluživanje → ostale, `_dsp_posluzivanje`, napomene za provjeru.
 */

defined( 'ABSPATH' ) || exit;

final class Domestic_Slike_Paket {

	const PREFIKS = 'dss_paket_';
	const SLIKE   = array( 'jpg', 'jpeg', 'png', 'webp' );

	/* ---------- radna mapa ---------- */

	private static function rad( string $token = '' ): string {
		$d = trailingslashit( wp_upload_dir()['basedir'] ) . 'domestic-slike-rad/';
		if ( ! is_dir( $d ) ) {
			wp_mkdir_p( $d );
			file_put_contents( $d . 'index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore
			file_put_contents( $d . '.htaccess', "Require all denied\n" ); // phpcs:ignore
		}
		return $token ? $d . $token . '/' : $d;
	}

	public static function obrisi_rad( string $token ): void {
		$d = self::rad( $token );
		if ( $token && is_dir( $d ) ) {
			foreach ( (array) glob( $d . '*' ) as $f ) {
				wp_delete_file( $f );
			}
			@rmdir( $d ); // phpcs:ignore
		}
		delete_option( self::PREFIKS . $token );
	}

	/** Pripreme starije od 2 dana se brišu (dnevno). */
	public static function ocisti_stare(): void {
		foreach ( (array) glob( self::rad() . '*', GLOB_ONLYDIR ) as $d ) {
			if ( filemtime( $d ) < time() - 2 * DAY_IN_SECONDS ) {
				self::obrisi_rad( basename( $d ) );
			}
		}
	}

	public static function podaci( string $token ) {
		$a = get_option( self::PREFIKS . $token );
		return is_array( $a ) ? $a : null;
	}

	/* ---------- 1. priprema ---------- */

	/** @return array|WP_Error */
	public static function pripremi( string $zip, string $naziv_zipa ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'dss', 'Poslužitelj nema ZipArchive.' );
		}
		$z = new ZipArchive();
		if ( true !== $z->open( $zip ) ) {
			return new WP_Error( 'dss', 'Datoteka nije ispravan zip.' );
		}
		$token = wp_generate_password( 20, false );
		$rad   = self::rad( $token );
		wp_mkdir_p( $rad );
		$slike   = array();
		$alt_txt = '';
		$mapa    = '';
		$ostalo  = array();
		for ( $i = 0; $i < $z->numFiles && $i < 60; $i++ ) {
			$st = $z->statIndex( $i );
			$n  = (string) $st['name'];
			$b  = basename( str_replace( '\\', '/', $n ) );
			if ( '/' === substr( $n, -1 ) || '' === $b || 0 === strpos( $b, '.' ) || false !== strpos( $n, '__MACOSX' ) ) {
				continue;
			}
			$ext = strtolower( pathinfo( $b, PATHINFO_EXTENSION ) );
			if ( 'alt.txt' === strtolower( $b ) && $st['size'] < 50000 ) {
				$alt_txt = (string) $z->getFromIndex( $i );
				$mapa    = basename( dirname( str_replace( '\\', '/', $n ) ) );
			} elseif ( in_array( $ext, self::SLIKE, true ) && $st['size'] < 30 * MB_IN_BYTES ) {
				// slika se odmah ponovno sprema (bez metapodataka); u radnoj mapi pod sigurnim nazivom
				$tmp = $rad . 'izvor-' . count( $slike ) . '.' . $ext;
				file_put_contents( $tmp, $z->getFromIndex( $i ) ); // phpcs:ignore
				$im = Domestic_Slike::ucitaj( $tmp );
				wp_delete_file( $tmp );
				if ( ! $im ) {
					$ostalo[] = $b;
					continue;
				}
				$cist = $rad . 'slika-' . count( $slike ) . '.png';
				imagepng( $im, $cist, 1 ); // bez gubitka; konačni format pri uvozu
				$slike[] = array( 'naziv' => sanitize_file_name( $b ), 'put' => $cist, 'w' => imagesx( $im ), 'h' => imagesy( $im ) );
				imagedestroy( $im );
				if ( '' === $mapa ) {
					$mapa = basename( dirname( str_replace( '\\', '/', $n ) ) );
				}
			} else {
				$ostalo[] = $b; // upute, opisi izvora i sl. – ne čitaju se
			}
		}
		$z->close();
		if ( ! $slike ) {
			self::obrisi_rad( $token );
			return new WP_Error( 'dss', 'U zipu nema slika.' );
		}

		// alt.txt: „datoteka = alt”, NAPOMENA… = napomena za provjeru, STAVKA… = „naziv | količina | opis [| ikona]”
		$alt        = array();
		$napomene   = array();
		$stavke_alt = array();
		foreach ( preg_split( '/\R/u', $alt_txt ) as $red ) {
			if ( ! preg_match( '/^\s*([^=]+?)\s*=\s*(.+)$/u', $red, $m ) ) {
				continue;
			}
			if ( preg_match( '/^NAPOMENA/i', $m[1] ) ) {
				$napomene[] = trim( wp_strip_all_tags( $m[2] ) );
			} elseif ( preg_match( '/^STAVKA/i', $m[1] ) ) {
				$d = array_map( 'trim', explode( '|', wp_strip_all_tags( $m[2] ) ) );
				if ( '' !== $d[0] ) {
					$stavke_alt[] = array(
						'naziv'    => mb_strtoupper( mb_substr( $d[0], 0, 1 ) ) . mb_substr( $d[0], 1 ),
						'kolicina' => $d[1] ?? '',
						'opis'     => $d[2] ?? '',
						'ikona'    => '' !== ( $d[3] ?? '' ) ? sanitize_key( $d[3] ) : self::ikona( $d[0] ),
					);
				}
			} else {
				$alt[ sanitize_file_name( basename( $m[1] ) ) ] = trim( wp_strip_all_tags( $m[2] ) );
			}
		}

		// uloge slika
		$amb    = null;
		$studio = null;
		$posl   = null;
		$dodatne = array();
		$oznaka = null; // „…ambijent…” u nazivu (paket bez starog naziva datoteke)
		foreach ( $slike as $k => $s ) {
			$n = strtolower( $s['naziv'] );
			if ( 0 === strpos( $n, 'studio-' ) || preg_match( '/(^|[-_\s\d])studio([-_.\s]|$)/', $n ) ) {
				$studio = $studio ?? $k;
			} elseif ( 0 === strpos( $n, 'posluzivanje-' ) || false !== strpos( $n, 'posluzivanje' ) || false !== strpos( $n, 'posluživanje' ) ) {
				$posl = $posl ?? $k;
			} else {
				$dodatne[] = $k;
				if ( null === $oznaka && false !== strpos( $n, 'ambijent' ) ) {
					$oznaka = $k;
				}
			}
		}
		$kandidati = self::kandidati( $slike, $dodatne, $mapa );
		// ambijentalna = ona čiji naziv odgovara GLAVNOJ slici prepoznatog proizvoda (druge istog naziva su npr. stražnja strana),
		// zatim bilo kojoj postojećoj slici, inače „…ambijent…” ili prva bez prefiksa
		$glavna = (int) ( $kandidati[0]['glavna'] ?? 0 );
		foreach ( $dodatne as $k ) {
			if ( $glavna && in_array( $glavna, self::prilozi_po_nazivu( $slike[ $k ]['naziv'] ), true ) ) {
				$amb = $k;
				break;
			}
		}
		foreach ( null === $amb ? $dodatne : array() as $k ) {
			if ( self::prilozi_po_nazivu( $slike[ $k ]['naziv'] ) ) {
				$amb = $k;
				break;
			}
		}
		if ( null === $amb && $dodatne ) {
			$amb = $oznaka ?? $dodatne[0];
		}
		$dodatne = array_values( array_diff( $dodatne, array( $amb ) ) );

		// posluživanje: tekst iz alt-a kartice, ikone izrezane s kartice
		$stavke = array();
		$ikone  = array();
		if ( null !== $posl ) {
			$stavke = $stavke_alt ? array_slice( $stavke_alt, 0, 6 ) : self::stavke( (string) ( $alt[ $slike[ $posl ]['naziv'] ] ?? '' ) );
			$ikone  = self::izrezi_ikone( $slike[ $posl ]['put'], $rad );
			foreach ( $ikone as $i => $ik ) {
				$ikone[ $i ]['hash'] = self::hash_ikone( $ik['put'] );
				$ikone[ $i ]['ista'] = self::ista_ikona( $ikone[ $i ]['hash'] ); // ista ilustracija već postoji → ne učitava se ponovno
			}
		}
		$opisi = self::postojeci_opisi( (int) ( $kandidati[0]['id'] ?? 0 ) );
		foreach ( $stavke as $i => $s ) {
			if ( '' === $s['opis'] ) {
				$stavke[ $i ]['opis'] = $opisi[ self::kljuc( $s['naziv'], $s['kolicina'] ) ] ?? '';
			}
		}
		$izvor_ikona = self::isti_set_ikona( $stavke, $kandidati[0]['id'] ?? 0 );

		$a = array(
			'token'     => $token,
			'zip'       => sanitize_file_name( $naziv_zipa ),
			'mapa'      => sanitize_text_field( $mapa ),
			'slike'     => $slike,
			'alt'       => $alt,
			'napomene'  => $napomene,
			'ostalo'    => array_map( 'sanitize_file_name', $ostalo ),
			'amb'       => $amb,
			'studio'    => $studio,
			'posl'      => $posl,
			'dodatne'   => $dodatne,
			'kandidati' => $kandidati,
			'stavke'    => $stavke,
			'ikone'     => $ikone,
			'izvor_ikona' => $izvor_ikona,
			'upozorenja'  => self::upozorenja( $kandidati, $amb, $posl, $stavke, $ikone ),
			'uvezeno'   => false,
		);
		update_option( self::PREFIKS . $token, $a, false );
		return $a;
	}

	public static function za_rest( array $a ): array {
		$r = $a;
		foreach ( $r['slike'] as $k => $s ) {
			unset( $r['slike'][ $k ]['put'] );
		}
		foreach ( $r['ikone'] as $k => $s ) {
			unset( $r['ikone'][ $k ]['put'] );
		}
		return $r;
	}

	/** Prilozi čija datoteka (bez -scaled i nastavka) ima isti naziv kao slika iz paketa. */
	public static function prilozi_po_nazivu( string $naziv ): array {
		global $wpdb;
		$osnova = preg_replace( '/\.[a-z0-9]+$/i', '', $naziv );
		$ids    = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND ( meta_value LIKE %s OR meta_value LIKE %s OR meta_value LIKE %s )",
				'%/' . $wpdb->esc_like( $osnova ) . '.%',
				'%/' . $wpdb->esc_like( $osnova ) . '-scaled.%',
				$wpdb->esc_like( $osnova ) . '.%'
			)
		);
		return array_map( 'intval', $ids );
	}

	/** Izvorni naslov (bez „Privatno:” / „Zaštićeno:” koje WordPress dodaje skrivenim proizvodima). */
	public static function naslov( $p ): string {
		return (string) get_post_field( 'post_title', $p );
	}

	private static function norm( string $s ): string {
		return preg_replace( '/[^a-z0-9]/', '', strtolower( remove_accents( html_entity_decode( $s, ENT_QUOTES, 'UTF-8' ) ) ) );
	}

	/** Prijedlog proizvoda: glavna slika istog naziva (+2) i naziv mape = naziv proizvoda (+3). */
	private static function kandidati( array $slike, array $bez_prefiksa, string $mapa ): array {
		$bod = array();
		foreach ( $bez_prefiksa as $k ) {
			foreach ( self::prilozi_po_nazivu( $slike[ $k ]['naziv'] ) as $pid ) {
				foreach ( get_posts( array( 'post_type' => 'product', 'post_status' => 'any', 'meta_key' => '_thumbnail_id', 'meta_value' => $pid, 'fields' => 'ids', 'numberposts' => 20 ) ) as $p ) { // phpcs:ignore
					$bod[ $p ] = ( $bod[ $p ] ?? 0 ) + 2;
				}
			}
		}
		$nm = self::norm( $mapa );
		if ( strlen( $nm ) > 4 ) {
			foreach ( get_posts( array( 'post_type' => 'product', 'post_status' => 'any', 's' => self::rijec_za_trazenje( $mapa ), 'fields' => 'ids', 'numberposts' => 30 ) ) as $p ) {
				if ( self::norm( self::naslov( $p ) ) === $nm ) {
					$bod[ $p ] = ( $bod[ $p ] ?? 0 ) + 3;
				}
			}
		}
		arsort( $bod );
		$r = array();
		foreach ( $bod as $p => $b ) {
			$r[] = array( 'id' => (int) $p, 'naziv' => self::naslov( $p ), 'bodovi' => $b, 'glavna' => (int) get_post_thumbnail_id( $p ), 'status' => get_post_status( $p ) );
		}
		return $r;
	}

	/** Najdulja riječ iz naziva mape (bez zapremnine i interpunkcije – „Driver’s” s oblim apostrofom ne bi našao „Driver's”). */
	private static function rijec_za_trazenje( string $mapa ): string {
		$r = preg_split( '/[^\p{L}\p{N}]+/u', preg_replace( '/\s+[\d,]+\s*l$/iu', '', $mapa ), -1, PREG_SPLIT_NO_EMPTY );
		usort( $r, static function ( $a, $b ) { return mb_strlen( $b ) <=> mb_strlen( $a ); } );
		return (string) ( $r[0] ?? $mapa );
	}

	/** Drugi proizvodi koji imaju istu sliku kao glavnu. */
	public static function dijele( int $slika, int $osim ): array {
		if ( ! $slika ) {
			return array();
		}
		return array_values( array_diff( array_map( 'intval', get_posts( array( 'post_type' => 'product', 'post_status' => 'any', 'meta_key' => '_thumbnail_id', 'meta_value' => $slika, 'fields' => 'ids', 'numberposts' => 20 ) ) ), array( $osim ) ) ); // phpcs:ignore
	}

	/** „… kako poslužiti: kao aperitiv 0,03 l, uz slasticu 0,03 l i kao digestiv 0,03 l” → stavke. */
	public static function stavke( string $alt ): array {
		if ( ! preg_match( '/kako poslužiti\s*:\s*(.+)$/iu', $alt, $m ) ) {
			return array();
		}
		$r = array();
		// dva zapisa: „kao aperitiv 0,03 l, …” i „0,03 l kao aperitiv, …” – dijeli se iza „l” ili ispred broja,
		// da zarez u „0,03” ne razbije stavku
		foreach ( preg_split( '/(?<=\bl),\s*|(?<=\bl)\s+i\s+|,\s+(?=\d)|\s+i\s+(?=\d+,\d)/u', rtrim( $m[1], '. ' ) ) as $dio ) {
			$dio = trim( $dio );
			if ( '' === $dio ) {
				continue;
			}
			$kol = '';
			if ( preg_match( '/^(\d[\d,]*\s*l)\s+(\D.*)$/u', $dio, $x ) ) {
				$kol = trim( $x[1] );
				$dio = $x[2];
			} elseif ( preg_match( '/^(.*?)\s+(\d[\d,\s+×x.]*\s*l(?:\s*\+.*)?)$/u', $dio, $x ) ) {
				$dio = $x[1];
				$kol = trim( $x[2] );
			}
			$naziv = mb_strtoupper( mb_substr( $dio, 0, 1 ) ) . mb_substr( $dio, 1 );
			$r[]   = array( 'naziv' => $naziv, 'kolicina' => $kol, 'opis' => '', 'ikona' => self::ikona( $naziv ) );
		}
		return array_slice( $r, 0, 6 );
	}

	private static function ikona( string $n ): string {
		$n = mb_strtolower( $n );
		foreach ( array( 'koktel' => 'koktel', 'espresso' => 'kava', 'kav' => 'kava', 'slast' => 'kolac', 'kolač' => 'kolac', 'sladoled' => 'kolac', 'desert' => 'kolac', 'sir' => 'sir', 'rib' => 'riba' ) as $k => $v ) {
			if ( false !== mb_strpos( $n, $k ) ) {
				return $v;
			}
		}
		return 'casa';
	}

	private static function kljuc( string $naziv, string $kol ): string {
		return mb_strtolower( trim( $naziv ) ) . '|' . trim( $kol );
	}

	/** Prve dvije riječi izvornog naslova = marka i vrsta („bogati visnjevac”). */
	private static function marka( int $p ): string {
		return implode( ' ', array_slice( preg_split( '/\s+/', mb_strtolower( remove_accents( self::naslov( $p ) ) ) ), 0, 2 ) );
	}

	/**
	 * Opisi stavki posluživanja s proizvoda ISTE marke i vrste (isti naziv + količina), npr. Višnjevac 1 l ← 0,20 l.
	 * Druge marke se ne gledaju – iste stavke („Kao aperitiv”) na drugoj kartici imaju drugi opis.
	 */
	private static function postojeci_opisi( int $proizvod ): array {
		$r = array();
		if ( ! $proizvod ) {
			return $r;
		}
		$marka = self::marka( $proizvod );
		foreach ( self::proizvodi_s_posluzivanjem() as $pid => $st ) {
			if ( $pid === $proizvod || self::marka( $pid ) !== $marka ) {
				continue;
			}
			foreach ( $st as $s ) {
				$k = self::kljuc( (string) ( $s['naziv'] ?? '' ), (string) ( $s['kolicina'] ?? '' ) );
				if ( ! isset( $r[ $k ] ) && ! empty( $s['opis'] ) ) {
					$r[ $k ] = $s['opis'];
				}
			}
		}
		return $r;
	}

	public static function proizvodi_s_posluzivanjem(): array {
		$r = array();
		foreach ( get_posts( array( 'post_type' => 'product', 'post_status' => 'any', 'meta_key' => '_dsp_posluzivanje', 'fields' => 'ids', 'numberposts' => 500 ) ) as $p ) { // phpcs:ignore
			$st = get_post_meta( $p, '_dsp_posluzivanje', true );
			if ( is_array( $st ) && $st ) {
				$r[ (int) $p ] = $st;
			}
		}
		return $r;
	}

	/**
	 * Ista kartica već postoji (ista marka i vrsta – prve dvije riječi naziva – i isti nazivi stavki s ilustracijama)?
	 * Onda se ikone ne režu ponovno nego se koriste postojeće (npr. Medica 0,20 / 0,70 / 1 l).
	 */
	public static function isti_set_ikona( array $stavke, int $proizvod ): int {
		if ( ! $stavke || ! $proizvod ) {
			return 0;
		}
		$marka  = self::marka( $proizvod );
		$nazivi = array_map( static function ( $s ) { return mb_strtolower( $s['naziv'] ); }, $stavke );
		foreach ( self::proizvodi_s_posluzivanjem() as $pid => $st ) {
			if ( $pid === $proizvod || self::marka( $pid ) !== $marka ) {
				continue;
			}
			$n2 = array_map( static function ( $s ) { return mb_strtolower( (string) ( $s['naziv'] ?? '' ) ); }, $st );
			if ( $n2 === $nazivi && count( array_filter( array_column( $st, 'slika' ) ) ) === count( $st ) ) {
				return $pid;
			}
		}
		return 0;
	}

	/* ---------- ikone s kartice ---------- */

	private static function razlika( $im, int $x, int $y, array $bg ): int {
		$c = imagecolorat( $im, $x, $y );
		return abs( ( ( $c >> 16 ) & 255 ) - $bg[0] ) + abs( ( ( $c >> 8 ) & 255 ) - $bg[1] ) + abs( ( $c & 255 ) - $bg[2] );
	}

	/**
	 * Krugovi ilustracija na kartici (1600 px; ostale veličine razmjerno): x i polumjer iz najšireg reda s 3 široka odsječka,
	 * y = gornji rub kruga (ispod crte naslova) + polumjer. Puni krug ostaje, izvan kruga prozirno (osim onoga što viri – tanjur).
	 */
	public static function izrezi_ikone( string $kartica, string $rad ): array {
		$im = Domestic_Slike::ucitaj( $kartica );
		if ( ! $im ) {
			return array();
		}
		$w  = imagesx( $im );
		$h  = imagesy( $im );
		$s  = $w / 1600;
		$c  = imagecolorat( $im, (int) ( 50 * $s ), (int) ( 628 * $s ) );
		$bg = array( ( $c >> 16 ) & 255, ( $c >> 8 ) & 255, $c & 255 );
		$naj = null;
		for ( $y = (int) ( 540 * $s ); $y < min( $h, (int) ( 740 * $s ) ); $y += 2 ) {
			$seg = array();
			$poc = null;
			$pre = null;
			for ( $x = 0; $x < $w; $x++ ) {
				if ( self::razlika( $im, $x, $y, $bg ) > 8 ) {
					if ( null === $poc ) {
						$poc = $x;
					} elseif ( $x !== $pre + 1 ) {
						$seg[] = array( $poc, $pre );
						$poc   = $x;
					}
					$pre = $x;
				}
			}
			if ( null !== $poc ) {
				$seg[] = array( $poc, $pre );
			}
			usort( $seg, static function ( $a, $b ) { return ( $b[1] - $b[0] ) <=> ( $a[1] - $a[0] ); } );
			$seg = array_slice( $seg, 0, 3 );
			if ( 3 !== count( $seg ) || min( array_map( static function ( $g ) { return $g[1] - $g[0]; }, $seg ) ) <= 250 * $s ) {
				continue;
			}
			$sir = array_sum( array_map( static function ( $g ) { return $g[1] - $g[0]; }, $seg ) );
			if ( ! $naj || $sir > $naj[0] ) {
				usort( $seg, static function ( $a, $b ) { return $a[0] <=> $b[0]; } );
				$naj = array( $sir, $seg );
			}
		}
		if ( ! $naj ) {
			imagedestroy( $im );
			return array();
		}
		$seg = $naj[1];
		$r   = (int) ( max( array_map( static function ( $g ) { return $g[1] - $g[0]; }, $seg ) ) / 2 );
		$cx  = (int) ( ( $seg[0][0] + $seg[0][1] ) / 2 );
		$vrh = null;
		for ( $y = (int) ( 420 * $s ); $y < min( $h, (int) ( 900 * $s ) ); $y++ ) {
			$n = 0;
			for ( $x = $cx - $r; $x < $cx + $r; $x += 3 ) {
				if ( self::razlika( $im, $x, $y, $bg ) > 5 ) {
					$n++;
				}
			}
			if ( $n > 6 ) {
				$vrh = $y;
				break;
			}
		}
		if ( null === $vrh ) {
			imagedestroy( $im );
			return array();
		}
		$cy = $vrh + $r;
		$r -= 1;
		$S  = (int) round( 392 * $s );
		$out = array();
		foreach ( $seg as $i => $g ) {
			$x0 = (int) ( ( $g[0] + $g[1] ) / 2 ) - intdiv( $S, 2 );
			$y0 = $cy - intdiv( $S, 2 );
			$c2 = imagecreatetruecolor( $S, $S );
			imagealphablending( $c2, false );
			imagesavealpha( $c2, true );
			$pol = $S / 2;
			for ( $y = 0; $y < $S; $y++ ) {
				for ( $x = 0; $x < $S; $x++ ) {
					$sx  = min( $w - 1, max( 0, $x0 + $x ) );
					$sy  = min( $h - 1, max( 0, $y0 + $y ) );
					$col = imagecolorat( $im, $sx, $sy );
					$d   = self::razlika( $im, $sx, $sy, $bg );
					$van = $d < 6 ? 0 : ( $d > 16 ? 1 : ( $d - 6 ) / 10 );
					$dist = sqrt( ( $x + .5 - $pol ) ** 2 + ( $y + .5 - $pol ) ** 2 );
					$kr  = max( 0, min( 1, $r - $dist + .5 ) );
					$a   = max( $van, $kr );
					imagesetpixel( $c2, $x, $y, imagecolorallocatealpha( $c2, ( $col >> 16 ) & 255, ( $col >> 8 ) & 255, $col & 255, (int) round( 127 * ( 1 - $a ) ) ) );
				}
			}
			$m = imagecreatetruecolor( 280, 280 );
			imagealphablending( $m, false );
			imagesavealpha( $m, true );
			imagefill( $m, 0, 0, imagecolorallocatealpha( $m, 0, 0, 0, 127 ) );
			imagecopyresampled( $m, $c2, 0, 0, 0, 0, 280, 280, $S, $S );
			$put = $rad . 'ikona-' . $i . '.png';
			imagepng( $m, $put, 9 );
			imagedestroy( $c2 );
			imagedestroy( $m );
			$out[] = array( 'put' => $put, 'x' => (int) ( ( $g[0] + $g[1] ) / 2 ), 'y' => $cy, 'r' => $r );
		}
		imagedestroy( $im );
		return $out;
	}

	/* ---------- iste ilustracije (prosječni otisak 24 × 24 na bijeloj podlozi) ---------- */

	const HASH = '_dss_ikona_hash';

	public static function hash_ikone( string $put ): string {
		$im = Domestic_Slike::ucitaj( $put );
		if ( ! $im ) {
			return '';
		}
		$n = 24;
		$m = imagecreatetruecolor( $n, $n );
		imagefill( $m, 0, 0, imagecolorallocate( $m, 255, 255, 255 ) );
		imagealphablending( $m, true );
		imagecopyresampled( $m, $im, 0, 0, 0, 0, $n, $n, imagesx( $im ), imagesy( $im ) );
		$v = array();
		for ( $y = 0; $y < $n; $y++ ) {
			for ( $x = 0; $x < $n; $x++ ) {
				$c   = imagecolorat( $m, $x, $y );
				$v[] = ( ( ( $c >> 16 ) & 255 ) * 299 + ( ( $c >> 8 ) & 255 ) * 587 + ( $c & 255 ) * 114 ) / 1000;
			}
		}
		imagedestroy( $m );
		imagedestroy( $im );
		$avg = array_sum( $v ) / count( $v );
		$b   = '';
		foreach ( $v as $g ) {
			$b .= $g < $avg ? '1' : '0';
		}
		return $b;
	}

	/** ID već učitane ilustracije s gotovo istim otiskom (≤ 3 % različitih točaka), inače 0. */
	public static function ista_ikona( string $hash ): int {
		if ( '' === $hash ) {
			return 0;
		}
		self::hash_postojecih();
		$naj = 0;
		$min = PHP_INT_MAX;
		foreach ( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => self::HASH ) ) as $id ) { // phpcs:ignore
			$h = (string) get_post_meta( $id, self::HASH, true );
			if ( strlen( $h ) !== strlen( $hash ) ) {
				continue;
			}
			$d = substr_count( $h ^ $hash, "\x01" );
			if ( $d < $min ) {
				$min = $d;
				$naj = (int) $id;
			}
		}
		return $min <= (int) ( strlen( $hash ) * 0.03 ) ? $naj : 0;
	}

	/** Jednom: otisak za ilustracije učitane prije 0.4.0 (posluzivanje-ikona-*.png). */
	private static function hash_postojecih(): void {
		if ( '1' === get_option( 'dss_ikone_hash' ) ) {
			return;
		}
		$ids = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => -1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_wp_attached_file', 'value' => 'posluzivanje-ikona-', 'compare' => 'LIKE' ) ) ) ); // phpcs:ignore
		foreach ( $ids as $id ) {
			$put = get_attached_file( $id );
			if ( $put && file_exists( $put ) && ! get_post_meta( $id, self::HASH, true ) ) {
				update_post_meta( $id, self::HASH, self::hash_ikone( $put ) );
			}
		}
		update_option( 'dss_ikone_hash', '1', false );
	}

	/* ---------- upozorenja za brzi uvoz ---------- */

	/** [ [ 'stop'|'info', tekst ], … ] – „stop” = uvoz paketa traži ručnu odluku. */
	private static function upozorenja( array $kandidati, $amb, $posl, array $stavke, array $ikone ): array {
		$u    = array();
		$prvi = $kandidati[0] ?? null;
		if ( ! $prvi ) {
			$u[] = array( 'stop', 'Proizvod nije prepoznat – odaberite ga ručno.' );
		} else {
			if ( (int) $prvi['bodovi'] < 2 ) { // 2 = stara glavna slika istog naziva, 3 = naziv mape = naziv proizvoda
				$u[] = array( 'stop', 'Proizvod nije sigurno prepoznat: ' . $prvi['naziv'] . '.' );
			}
			if ( isset( $kandidati[1] ) && (int) $kandidati[1]['bodovi'] === (int) $prvi['bodovi'] ) {
				$u[] = array( 'stop', 'Više proizvoda jednako odgovara: ' . $prvi['naziv'] . ', ' . $kandidati[1]['naziv'] . '.' );
			}
			$dij = self::dijele( (int) $prvi['glavna'], (int) $prvi['id'] );
			if ( $dij ) {
				$u[] = array( 'stop', 'Glavna slika je i na drugim proizvodima: ' . implode( ', ', array_map( array( __CLASS__, 'naslov' ), $dij ) ) . '.' );
			}
			if ( 'private' === get_post_status( (int) $prvi['id'] ) ) {
				$u[] = array( 'info', 'Proizvod je skriven – slike se postavljaju, status se ne mijenja.' );
			}
		}
		if ( null === $amb ) {
			$u[] = array( 'stop', 'U paketu nema ambijentalne slike.' );
		}
		if ( null !== $posl && ! $stavke ) {
			$u[] = array( 'stop', 'Kartica posluživanja nema teksta (alt „kako poslužiti: …” ili retke STAVKA).' );
		}
		$bez = array_column( array_filter( $stavke, static function ( $s ) { return '' === $s['opis']; } ), 'naziv' );
		if ( $bez ) {
			$u[] = array( 'info', 'Bez opisa ispod stavke: ' . implode( ', ', $bez ) . '.' );
		}
		if ( null !== $posl && count( $ikone ) !== count( $stavke ) ) {
			$u[] = array( 'info', 'Izrezano ilustracija: ' . count( $ikone ) . ', stavki: ' . count( $stavke ) . '.' );
		}
		return $u;
	}

	/* ---------- 2. uvoz ---------- */

	/**
	 * @param array $o proizvod, cilj (ID slike za ambijentalnu), ikone_izvor (0 = nove, ID proizvoda = njegove ikone),
	 *                 posluzivanje [{naziv,kolicina,opis,ikona}], zadrzi [ID-ovi postojeće galerije], dodatne (bool), napomene (bool),
 *                 bez_ilustracija (bool – ne reže ni ne veže ilustracije s kartice)
	 * @return array|WP_Error
	 */
	public static function uvezi( string $token, array $o ) {
		$a = self::podaci( $token );
		if ( ! $a ) {
			return new WP_Error( 'dss', 'Priprema ne postoji ili je istekla – učitajte zip ponovno.', array( 'status' => 404 ) );
		}
		if ( ! empty( $a['uvezeno'] ) ) {
			return new WP_Error( 'dss', 'Ovaj paket je već uvezen.', array( 'status' => 409 ) );
		}
		$pid = (int) ( $o['proizvod'] ?? ( $a['kandidati'][0]['id'] ?? 0 ) );
		$p   = $pid ? wc_get_product( $pid ) : null;
		if ( ! $p ) {
			return new WP_Error( 'dss', 'Odaberite proizvod.', array( 'status' => 400 ) );
		}
		if ( null === $a['amb'] ) {
			return new WP_Error( 'dss', 'U paketu nema ambijentalne slike (bez prefiksa studio-/posluzivanje-).', array( 'status' => 400 ) );
		}
		$cilj = (int) ( $o['cilj'] ?? $p->get_image_id() );
		$s    = $a['slike'];
		$rez  = array( 'proizvod' => $pid );

		// 1. ambijentalna na staru adresu (ili nova glavna ako proizvod nema glavne slike)
		$amb_alt = $a['alt'][ $s[ $a['amb'] ]['naziv'] ] ?? $p->get_name();
		if ( $cilj ) {
			$z = Domestic_Slike::zamijeni( $cilj, $s[ $a['amb'] ]['put'] );
			if ( is_wp_error( $z ) ) {
				return $z;
			}
			if ( '' === trim( (string) get_post_meta( $cilj, '_wp_attachment_image_alt', true ) ) ) {
				update_post_meta( $cilj, '_wp_attachment_image_alt', $amb_alt ); // prazan alt se popunjava, postojeći ostaje
			}
		} else {
			$cilj = self::nova_iz( $a, $a['amb'], $amb_alt, $p->get_name(), $pid, 'jpg' );
			if ( is_wp_error( $cilj ) ) {
				return $cilj;
			}
		}
		$rez['glavna'] = $cilj;

		// 2. studijska i posluživanje (nove, JPG)
		$nove = array();
		foreach ( array( 'studio' => ' – studijska', 'posl' => ' – kako poslužiti' ) as $u => $dod ) {
			if ( null === $a[ $u ] ) {
				continue;
			}
			$pref = 'studio' === $u ? 'studio-' : 'posluzivanje-';
			$ime  = 0 === stripos( $s[ $a[ $u ] ]['naziv'], $pref ) ? '' : $pref . preg_replace( '/\.[a-z0-9]+$/i', '', wp_basename( (string) get_attached_file( $cilj ) ) ); // pravilo: prefiks + naziv stare glavne slike
			$id   = self::nova_iz( $a, $a[ $u ], $a['alt'][ $s[ $a[ $u ] ]['naziv'] ] ?? ( $p->get_name() . $dod ), $p->get_name() . $dod, $pid, 'jpg', $ime );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			$nove[]      = $id;
			$rez[ $u ]   = $id;
		}
		// dodatne slike iz paketa (npr. medalje, detalji) iza: ako već postoji slika proizvoda istog naziva → zamjena na istoj adresi
		$zamijenjene = array();
		if ( ! isset( $o['dodatne'] ) || $o['dodatne'] ) {
			$svoje = array_merge( array( $cilj ), array_map( 'intval', $p->get_gallery_image_ids() ) );
			foreach ( $a['dodatne'] as $k ) {
				$alt_k = $a['alt'][ $s[ $k ]['naziv'] ] ?? $p->get_name();
				$ista  = array_values( array_intersect( self::prilozi_po_nazivu( $s[ $k ]['naziv'] ), $svoje ) );
				if ( $ista ) {
					$z = Domestic_Slike::zamijeni( $ista[0], $s[ $k ]['put'] );
					if ( is_wp_error( $z ) ) {
						continue;
					}
					if ( '' === trim( (string) get_post_meta( $ista[0], '_wp_attachment_image_alt', true ) ) ) {
						update_post_meta( $ista[0], '_wp_attachment_image_alt', $alt_k );
					}
					$nove[]        = $ista[0];
					$zamijenjene[] = $ista[0];
					continue;
				}
				$id = self::nova_iz( $a, $k, $alt_k, $p->get_name(), $pid, 'jpg' );
				if ( ! is_wp_error( $id ) ) {
					$nove[] = $id;
				}
			}
		}
		$rez['zamijenjene'] = $zamijenjene;

		// 3. posluživanje: tekst + ilustracije
		$stavke = isset( $o['posluzivanje'] ) && is_array( $o['posluzivanje'] ) ? $o['posluzivanje'] : $a['stavke'];
		$izvor  = (int) ( $o['ikone_izvor'] ?? $a['izvor_ikona'] );
		$tude   = $izvor ? get_post_meta( $izvor, '_dsp_posluzivanje', true ) : array();
		$pos    = array();
		$kratko = trim( preg_replace( '/\s+[\d,]+\s*l$/iu', '', $p->get_name() ) );
		foreach ( array_values( $stavke ) as $i => $st ) {
			$naziv = sanitize_text_field( (string) ( $st['naziv'] ?? '' ) );
			if ( '' === $naziv ) {
				continue;
			}
			$slika = 0;
			if ( ! empty( $o['bez_ilustracija'] ) ) {
				$slika = 0; // kartica bez tri ilustracije u krugovima (npr. vina) – na stranici SVG ikone po ključu
			} elseif ( $izvor && is_array( $tude ) ) {
				$slika = (int) ( $tude[ $i ]['slika'] ?? 0 );
			} elseif ( ! empty( $a['ikone'][ $i ]['ista'] ) && wp_attachment_is_image( (int) $a['ikone'][ $i ]['ista'] ) ) {
				$slika = (int) $a['ikone'][ $i ]['ista'];
			} elseif ( ! empty( $a['ikone'][ $i ]['put'] ) && file_exists( $a['ikone'][ $i ]['put'] ) ) {
				$slika = Domestic_Slike::nova( $a['ikone'][ $i ]['put'], 'posluzivanje-ikona-' . sanitize_title( $kratko ) . '-' . sanitize_title( $naziv ) . '.png', $naziv . ' – ' . $kratko, $kratko . ' – ' . mb_strtolower( $naziv ), $pid );
				$slika = is_wp_error( $slika ) ? 0 : $slika;
				if ( $slika && ! empty( $a['ikone'][ $i ]['hash'] ) ) {
					update_post_meta( $slika, self::HASH, $a['ikone'][ $i ]['hash'] );
				}
			}
			$ik    = sanitize_key( (string) ( $st['ikona'] ?? 'casa' ) );
			$pos[] = array(
				'ikona'    => '' !== $ik ? $ik : 'casa',
				'naziv'    => $naziv,
				'kolicina' => sanitize_text_field( (string) ( $st['kolicina'] ?? '' ) ),
				'opis'     => sanitize_text_field( (string) ( $st['opis'] ?? '' ) ),
				'slika'    => $slika,
			);
		}

		// 4. galerija i meta
		$stara  = array_map( 'intval', $p->get_gallery_image_ids() );
		$zadrzi = isset( $o['zadrzi'] ) ? array_map( 'intval', (array) $o['zadrzi'] ) : $stara;
		$zadrzi = array_values( array_diff( array_intersect( $stara, $zadrzi ), array( $cilj ), $nove ) );
		$p->set_image_id( $cilj );
		$p->set_gallery_image_ids( array_merge( $nove, $zadrzi ) );
		if ( $pos ) {
			$p->update_meta_data( '_dsp_posluzivanje', $pos );
		}
		$p->save();
		$rez['galerija']     = array_merge( array( $cilj ), $nove, $zadrzi );
		$rez['posluzivanje'] = $pos;

		// 5. napomene iz paketa → „Za provjeru”
		if ( ( ! isset( $o['napomene'] ) || $o['napomene'] ) && $a['napomene'] ) {
			foreach ( $a['napomene'] as $n ) {
				Domestic_Slike::dodaj_provjeru( $pid, $n, $a['zip'] );
			}
		}

		Domestic_Slike::zapisi( array( 'vrsta' => 'paket', 'zip' => $a['zip'], 'proizvod' => $pid, 'glavna' => $cilj, 'nove' => $nove, 'ikone' => array_column( $pos, 'slika' ) ) );
		self::obrisi_rad( $token );
		update_option( self::PREFIKS . $token, array( 'uvezeno' => true, 'rez' => $rez ), false );
		return $rez;
	}

	/** Nova slika iz pripremljene datoteke (naziv iz paketa, nastavak prema formatu). */
	private static function nova_iz( array $a, int $k, string $alt, string $naslov, int $pid, string $format, string $ime = '' ) {
		$s   = $a['slike'][ $k ];
		$im  = Domestic_Slike::ucitaj( $s['put'] );
		$tmp = $s['put'] . '.' . $format;
		if ( ! $im || ! Domestic_Slike::spremi_cisto( $im, $tmp, $format ) ) {
			return new WP_Error( 'dss', 'Slika ' . $s['naziv'] . ' se ne može spremiti.' );
		}
		imagedestroy( $im );
		// „-scaled” je WordPressov nastavak za smanjeni izvornik – u nazivu nove slike daje „…-scaled-1.jpg”
		$naziv = preg_replace( '/-scaled$/i', '', '' !== $ime ? $ime : preg_replace( '/\.[a-z0-9]+$/i', '', $s['naziv'] ) ) . '.' . $format;
		$id    = Domestic_Slike::nova( $tmp, $naziv, $alt, $naslov, $pid );
		wp_delete_file( $tmp );
		return $id;
	}
}
