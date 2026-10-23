<?php
/**
 * Administracija: kartica „Domestic” na proizvodu, Domestic → Proizvodi – provjera, Domestic → Stranica proizvoda.
 */

defined( 'ABSPATH' ) || exit;

final class Domestic_Proizvod_Admin {

	/** Podaci iz opisa koje alat zna prebaciti u atribute (oznaka u opisu → naziv atributa). */
	const IZ_OPISA = array(
		'vrsta'                  => 'Vrsta',
		'geografsko podrijetlo'  => 'Podrijetlo',
		'podrijetlo'             => 'Podrijetlo',
		'zapremnina boce'        => 'Zapremnina',
		'zapremnina'             => 'Zapremnina',
		'alkohol'                => 'Alkohol',
		'temperatura serviranja' => 'Temperatura',
		'gastro preporuka'       => 'Gastro preporuka',
		'pakiranje'              => 'Pakiranje',
		'ambalaza'               => 'Ambalaža',
		'na trziste stavlja'     => 'Na tržište stavlja',
		'proizvodac'             => 'Proizvođač',
	);

	public static function init(): void {
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		Domestic_Menu::add( 'domestic-proizvodi', 'Proizvodi – provjera', array( __CLASS__, 'page_check' ), 10 );
		Domestic_Menu::add( 'domestic-stranica-proizvoda', 'Stranica proizvoda', array( __CLASS__, 'page_settings' ), 80 );
		add_action( 'admin_post_dsp_save_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_dsp_extract', array( __CLASS__, 'apply_extract' ) );
		Domestic_Menu::add( 'domestic-tekstovi', 'Tekstovi', array( __CLASS__, 'page_tekst' ), 70 );
		add_action( 'admin_post_dsp_tekst', array( __CLASS__, 'save_tekst' ) );
	}

	public static function assets( $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen || ( 'product' !== $screen->id && false === strpos( (string) $hook, 'domestic' ) ) ) {
			return;
		}
		$v = DSP_VER . '.' . filemtime( DSP_DIR . '/assets/admin.js' );
		wp_enqueue_media();
		wp_enqueue_style( 'domestic-proizvod-admin', DSP_URL . 'assets/admin.css', array(), $v );
		wp_enqueue_script( 'domestic-proizvod-admin', DSP_URL . 'assets/admin.js', array( 'jquery' ), $v, true );
	}

	/* ---------- kartica „Domestic” ---------- */

	public static function tab( $tabs ) {
		$tabs['domestic'] = array(
			'label'    => 'Domestic',
			'target'   => 'dsp_panel',
			'class'    => array(),
			'priority' => 15,
		);
		return $tabs;
	}

	public static function panel(): void {
		global $product_object;
		$p = $product_object instanceof WC_Product ? $product_object : wc_get_product( get_the_ID() );
		if ( ! $p ) {
			return;
		}
		$m   = Domestic_Proizvod_Data::meta( $p );
		$svi = 'svi' === Domestic_Proizvod_Data::settings()['nacin'];
		$on  = Domestic_Proizvod_Data::is_on( $p );
		$raw = get_post_meta( $p->get_id(), '_dsp_posluzivanje', true );
		$pos = is_array( $raw ) ? $raw : array();
		$raw = get_post_meta( $p->get_id(), '_dsp_nagrade', true );
		$nag = is_array( $raw ) ? $raw : array();
		wp_nonce_field( 'dsp_save', 'dsp_nonce' );
		?>
		<div id="dsp_panel" class="panel woocommerce_options_panel hidden dsp-admin">
			<div class="dsp-a-sec">
				<label class="dsp-a-switch"><input type="checkbox" name="dsp_on" value="yes" <?php checked( $on ); ?>> <b>Prikaži ovaj proizvod u novom izgledu</b></label>
				<p class="dsp-a-help"><?php echo $svi ? 'Novi izgled je uključen za sve proizvode – ovdje ga možeš isključiti za ovaj proizvod.' : 'Novi izgled je uključen samo za označene proizvode (Domestic → Stranica proizvoda).'; ?>
				Marka, vrsta, zapremnina, alkohol, podrijetlo i temperatura upisuju se kao <b>atributi</b> (kartica Atributi).</p>
			</div>

			<div class="dsp-a-sec">
				<h4>Naslov i opis</h4>
				<p class="dsp-a-row"><label for="dsp_eyebrow">Mali natpis iznad naslova</label><input type="text" id="dsp_eyebrow" name="dsp_eyebrow" value="<?php echo esc_attr( $m['eyebrow'] ); ?>" placeholder="npr. Hrvatski liker od lješnjaka"></p>
				<p class="dsp-a-row"><label for="dsp_naslov">Naslov opisa</label><input type="text" id="dsp_naslov" name="dsp_naslov" value="<?php echo esc_attr( $m['naslov'] ); ?>" placeholder="npr. Lješnjak u glavnoj ulozi."></p>
				<p class="dsp-a-row"><label for="dsp_razlozi_naslov">Naslov okvira uz opis</label><input type="text" id="dsp_razlozi_naslov" name="dsp_razlozi_naslov" value="<?php echo esc_attr( $m['razlozi_naslov'] ); ?>" placeholder="npr. Zašto odabrati Avellanu?"></p>
				<p class="dsp-a-row"><label for="dsp_razlozi">Razlozi (svaki u svom redu)</label><textarea id="dsp_razlozi" name="dsp_razlozi" rows="3"><?php echo esc_textarea( implode( "\n", $m['razlozi'] ) ); ?></textarea></p>
				<p class="dsp-a-row"><label for="dsp_zakljucak">Završna rečenica okvira</label><input type="text" id="dsp_zakljucak" name="dsp_zakljucak" value="<?php echo esc_attr( $m['zakljucak'] ); ?>"></p>
				<p class="dsp-a-help">Bez razloga okvir pokazuje „Ukratko o proizvodu” iz atributa (vrsta, podrijetlo, gastro preporuka).</p>
			</div>

			<div class="dsp-a-sec">
				<h4>Kako poslužiti</h4>
				<div class="dsp-a-rep" data-name="dsp_pos">
					<?php foreach ( array_merge( $pos, array( array() ) ) as $i => $r ) : ?>
						<div class="dsp-a-item<?php echo $r ? '' : ' dsp-a-tpl'; ?>">
							<select name="dsp_pos[ikona][]">
								<?php foreach ( Domestic_Proizvod_Data::IKONE as $k => $t ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $r['ikona'] ?? '', $k ); ?>><?php echo esc_html( $t ); ?></option>
								<?php endforeach; ?>
							</select>
							<input type="text" name="dsp_pos[naziv][]" value="<?php echo esc_attr( $r['naziv'] ?? '' ); ?>" placeholder="Naziv (U čašici)">
							<input type="text" name="dsp_pos[kolicina][]" value="<?php echo esc_attr( $r['kolicina'] ?? '' ); ?>" placeholder="Količina (0,05 l)" class="dsp-a-short">
							<input type="text" name="dsp_pos[opis][]" value="<?php echo esc_attr( $r['opis'] ?? '' ); ?>" placeholder="Kratki opis">
							<input type="number" min="0" name="dsp_pos[slika][]" value="<?php echo ! empty( $r['slika'] ) ? (int) $r['slika'] : ''; ?>" placeholder="ID ilustracije" class="dsp-a-short" title="ID slike iz Medija – ilustracija s kartice posluživanja (umjesto ikone)">
							<button type="button" class="button dsp-a-del" aria-label="Ukloni">×</button>
						</div>
					<?php endforeach; ?>
					<button type="button" class="button dsp-a-add">+ Dodaj način posluživanja</button>
				</div>
			</div>

			<div class="dsp-a-sec">
				<h4>Nagrade</h4>
				<div class="dsp-a-rep" data-name="dsp_nag">
					<?php foreach ( array_merge( $nag, array( array() ) ) as $r ) : ?>
						<div class="dsp-a-item dsp-a-award<?php echo $r ? '' : ' dsp-a-tpl'; ?>">
							<input type="hidden" name="dsp_nag[slika][]" value="<?php echo esc_attr( $r['slika'] ?? '' ); ?>" class="dsp-a-img-id">
							<button type="button" class="dsp-a-img" title="Odaberi sliku medalje"><?php echo ! empty( $r['slika'] ) ? wp_get_attachment_image( (int) $r['slika'], 'thumbnail' ) : '<span>+ slika</span>'; ?></button>
							<div class="dsp-a-col">
								<input type="text" name="dsp_nag[naslov][]" value="<?php echo esc_attr( $r['naslov'] ?? '' ); ?>" placeholder="Naziv nagrade (Zlatna medalja)">
								<input type="text" name="dsp_nag[mjesto][]" value="<?php echo esc_attr( $r['mjesto'] ?? '' ); ?>" placeholder="Gdje i kada (Dionizijana, 2020.)">
								<textarea name="dsp_nag[tekst][]" rows="2" placeholder="Opis (nije obavezno)"><?php echo esc_textarea( $r['tekst'] ?? '' ); ?></textarea>
							</div>
							<button type="button" class="button dsp-a-del" aria-label="Ukloni">×</button>
						</div>
					<?php endforeach; ?>
					<button type="button" class="button dsp-a-add">+ Dodaj nagradu</button>
				</div>
			</div>

			<div class="dsp-a-sec">
				<h4>Ostalo</h4>
				<label class="dsp-a-switch"><input type="checkbox" name="dsp_omiljeno" value="yes" <?php checked( $m['omiljeno'] ); ?>> Oznaka „Omiljeno” na proizvodu</label>
				<p class="dsp-a-row"><label for="dsp_sidrena">Sidrena cijena na dan 2. 5. 2025. (€)</label><input type="text" id="dsp_sidrena" name="dsp_sidrena" value="<?php echo esc_attr( $m['sidrena'] ); ?>" class="dsp-a-short" placeholder="12,60"></p>
				<p class="dsp-a-help">Oznake „Nagrađeno” (ima nagradu), „Novo” (prvih <?php echo (int) Domestic_Proizvod_Data::settings()['novo_dana']; ?> dana) i „Akcija” postavljaju se same. Kod akcije se sama ispisuje najniža cijena u zadnjih 30 dana.</p>
			</div>
		</div>
		<?php
	}

	public static function save( WC_Product $p ): void {
		if ( empty( $_POST['dsp_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['dsp_nonce'] ) ), 'dsp_save' ) ) {
			return;
		}
		$post = wp_unslash( $_POST );
		$p->update_meta_data( '_dsp_on', ! empty( $post['dsp_on'] ) ? 'yes' : 'no' );
		$p->update_meta_data( '_dsp_omiljeno', ! empty( $post['dsp_omiljeno'] ) ? 'yes' : 'no' );
		foreach ( array( 'eyebrow', 'naslov', 'razlozi_naslov', 'zakljucak' ) as $k ) {
			$p->update_meta_data( '_dsp_' . $k, sanitize_text_field( $post[ 'dsp_' . $k ] ?? '' ) );
		}
		$sid = trim( sanitize_text_field( $post['dsp_sidrena'] ?? '' ) );
		$p->update_meta_data( '_dsp_sidrena', preg_match( '/^\d+([.,]\d{1,2})?$/', $sid ) ? $sid : '' );
		$lines = array_filter( array_map( 'sanitize_text_field', preg_split( '/\R/', (string) ( $post['dsp_razlozi'] ?? '' ) ) ) );
		$p->update_meta_data( '_dsp_razlozi', array_values( $lines ) );

		$pos = array();
		$src = $post['dsp_pos'] ?? array();
		foreach ( (array) ( $src['naziv'] ?? array() ) as $i => $naziv ) {
			$naziv = sanitize_text_field( $naziv );
			if ( '' === $naziv ) {
				continue;
			}
			$ik    = sanitize_key( $src['ikona'][ $i ] ?? 'casa' );
			$pos[] = array(
				'ikona'    => isset( Domestic_Proizvod_Data::IKONE[ $ik ] ) ? $ik : 'casa',
				'naziv'    => $naziv,
				'kolicina' => sanitize_text_field( $src['kolicina'][ $i ] ?? '' ),
				'opis'     => sanitize_text_field( $src['opis'][ $i ] ?? '' ),
				'slika'    => absint( $src['slika'][ $i ] ?? 0 ),
			);
		}
		$p->update_meta_data( '_dsp_posluzivanje', $pos );

		$nag = array();
		$src = $post['dsp_nag'] ?? array();
		foreach ( (array) ( $src['naslov'] ?? array() ) as $i => $naslov ) {
			$naslov = sanitize_text_field( $naslov );
			if ( '' === $naslov ) {
				continue;
			}
			$nag[] = array(
				'slika'  => absint( $src['slika'][ $i ] ?? 0 ),
				'naslov' => $naslov,
				'mjesto' => sanitize_text_field( $src['mjesto'][ $i ] ?? '' ),
				'tekst'  => sanitize_textarea_field( $src['tekst'][ $i ] ?? '' ),
			);
		}
		$p->update_meta_data( '_dsp_nagrade', $nag );
	}

	/* ---------- Domestic → Proizvodi – provjera ---------- */

	/** Što proizvodu nedostaje za potpun prikaz. */
	public static function missing( WC_Product $p ): array {
		$miss = array();
		$m    = Domestic_Proizvod_Data::meta( $p );
		if ( ! $p->get_image_id() ) {
			$miss[] = 'slika';
		}
		if ( ! ( method_exists( $p, 'get_global_unique_id' ) && $p->get_global_unique_id() ) ) {
			$miss[] = 'EAN';
		}
		foreach ( array( 'marka' => 'marka', 'vrsta' => 'vrsta', 'zapremnina' => 'zapremnina', 'podrijetlo' => 'podrijetlo' ) as $k => $t ) {
			if ( '' === Domestic_Proizvod_Data::attr( $p, $k ) ) {
				$miss[] = $t;
			}
		}
		if ( '' === Domestic_Proizvod_Data::attr( $p, 'alkohol' ) && '' === Domestic_Proizvod_Data::attr( $p, 'temperatura' ) ) {
			$miss[] = 'alkohol ili temperatura';
		}
		if ( ! trim( wp_strip_all_tags( $p->get_short_description() ) ) ) {
			$miss[] = 'kratki opis';
		}
		if ( ! trim( wp_strip_all_tags( $p->get_description() ) ) ) {
			$miss[] = 'opis';
		}
		if ( ! $m['posluzivanje'] ) {
			$miss[] = 'posluživanje';
		}
		return $miss;
	}

	public static function page_check(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( isset( $_GET['extract'] ) ) {
			self::page_extract( absint( $_GET['extract'] ) );
			return;
		}
		$only = ! empty( $_GET['nedostaje'] );
		$ids  = wc_get_products( array( 'limit' => 500, 'status' => array( 'publish', 'private', 'draft' ), 'orderby' => 'title', 'order' => 'ASC', 'return' => 'ids', 'type' => array( 'simple', 'variable' ) ) );
		$base = admin_url( 'admin.php?page=domestic-proizvodi' );
		$rows = '';
		$n    = 0;
		$ok   = 0;
		foreach ( $ids as $id ) {
			$p    = wc_get_product( $id );
			$miss = self::missing( $p );
			$on   = Domestic_Proizvod_Data::is_on( $p );
			$n++;
			if ( ! $miss ) {
				$ok++;
			}
			if ( $only && ! $miss ) {
				continue;
			}
			$has_text = (bool) self::parse_description( $p->get_description() );
			$rows    .= '<tr><td class="dsp-a-thumb">' . $p->get_image( array( 48, 48 ) ) . '</td>'
				. '<td><a href="' . esc_url( get_edit_post_link( $id ) ) . '"><b>' . esc_html( $p->get_name() ) . '</b></a><br><small>' . esc_html( wp_strip_all_tags( wc_price( (float) $p->get_price() ) ) ) . ' · ' . esc_html( get_post_status( $id ) ) . '</small></td>'
				. '<td>' . ( $on ? '<span class="dsp-a-ok">da</span>' : '<span class="dsp-a-no">ne</span>' ) . '</td>'
				. '<td>' . ( $miss ? '<span class="dsp-a-miss">' . esc_html( implode( ', ', $miss ) ) . '</span>' : '<span class="dsp-a-ok">sve je upisano</span>' ) . '</td>'
				. '<td class="dsp-a-actions"><a class="button button-small" href="' . esc_url( get_edit_post_link( $id ) ) . '">Uredi</a> <a class="button button-small" href="' . esc_url( get_permalink( $id ) ) . '" target="_blank">Pogledaj</a>'
				. ( $has_text ? ' <a class="button button-small" href="' . esc_url( add_query_arg( 'extract', $id, $base ) ) . '">Podaci iz opisa →</a>' : '' ) . '</td></tr>';
		}
		echo '<div class="wrap dsp-admin"><h1>Proizvodi – provjera</h1>';
		self::notice();
		echo '<p>Za svaki proizvod piše što još nedostaje za potpun prikaz (slika, EAN, atributi, opisi, posluživanje). <b>' . (int) $ok . ' od ' . (int) $n . '</b> proizvoda ima sve upisano.</p>';
		echo '<p><a href="' . esc_url( $base ) . '" class="button' . ( $only ? '' : ' button-primary' ) . '">Svi</a> <a href="' . esc_url( add_query_arg( 'nedostaje', 1, $base ) ) . '" class="button' . ( $only ? ' button-primary' : '' ) . '">Samo s nedostacima</a></p>';
		echo '<table class="widefat striped dsp-a-table"><thead><tr><th></th><th>Proizvod</th><th>Novi izgled</th><th>Nedostaje</th><th></th></tr></thead><tbody>' . ( $rows ? $rows : '<tr><td colspan="5">Nema proizvoda.</td></tr>' ) . '</tbody></table></div>';
	}

	private static function notice(): void {
		if ( isset( $_GET['dsp_msg'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['dsp_msg'] ) ) ) . '</p></div>';
		}
	}

	/* ---------- podaci iz opisa → atributi ---------- */

	/** „Naziv: vrijednost” iz opisa (redovi teksta ili tablica). */
	public static function parse_description( string $html ): array {
		$out  = array();
		$text = preg_replace( '#</(tr|p|div|li|h\d)>|<br\s*/?>#i', "\n", $html );
		$text = preg_replace( '#</t[hd]>\s*<t[hd][^>]*>#i', ': ', $text );
		$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' );
		foreach ( preg_split( '/\R/', $text ) as $line ) {
			if ( ! preg_match( '/^\s*([^:]{2,40}):\s*(.+?)\s*$/u', $line, $mm ) ) {
				continue;
			}
			$k = Domestic_Proizvod_Data::norm( $mm[1] );
			if ( 0 === strpos( $k, 'naziv' ) ) {
				continue;
			}
			if ( 'ean kod' === $k || 'ean' === $k ) {
				$out['__ean'] = array( 'EAN', trim( $mm[2] ) );
				continue;
			}
			if ( isset( self::IZ_OPISA[ $k ] ) ) {
				$out[ self::IZ_OPISA[ $k ] ] = array( self::IZ_OPISA[ $k ], trim( $mm[2] ) );
			}
		}
		return $out;
	}

	private static function page_extract( int $id ): void {
		$p = wc_get_product( $id );
		if ( ! $p ) {
			echo '<div class="wrap"><p>Nema proizvoda.</p></div>';
			return;
		}
		$found    = self::parse_description( $p->get_description() );
		$existing = array();
		foreach ( Domestic_Proizvod_Data::attributes( $p ) as $a ) {
			$existing[ Domestic_Proizvod_Data::norm( $a[0] ) ] = $a[1];
		}
		$ean_now = method_exists( $p, 'get_global_unique_id' ) ? $p->get_global_unique_id() : '';
		echo '<div class="wrap dsp-admin"><h1>Podaci iz opisa: ' . esc_html( $p->get_name() ) . '</h1>';
		echo '<p>Alat je u opisu pronašao ove podatke. Označeni se upisuju kao atributi proizvoda (vidljivi u tablici „Podaci”); opis se ne mijenja. Postojeći atributi s istim nazivom se ne diraju.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'dsp_extract_' . $id );
		echo '<input type="hidden" name="action" value="dsp_extract"><input type="hidden" name="id" value="' . (int) $id . '">';
		echo '<table class="widefat striped dsp-a-table"><thead><tr><th style="width:30px"></th><th>Atribut</th><th>Vrijednost iz opisa</th><th>Sada</th></tr></thead><tbody>';
		foreach ( $found as $key => $f ) {
			$now  = '__ean' === $key ? $ean_now : ( $existing[ Domestic_Proizvod_Data::norm( $f[0] ) ] ?? '' );
			$dis  = '' !== $now;
			echo '<tr><td><input type="checkbox" name="pick[]" value="' . esc_attr( $key ) . '" ' . checked( ! $dis, true, false ) . disabled( $dis, true, false ) . '></td><td><b>' . esc_html( $f[0] ) . '</b></td><td>' . esc_html( $f[1] ) . '</td><td>' . ( $dis ? esc_html( $now ) : '<span class="dsp-a-no">–</span>' ) . '</td></tr>';
		}
		echo '</tbody></table><p><button class="button button-primary">Upiši označeno</button> <a class="button" href="' . esc_url( admin_url( 'admin.php?page=domestic-proizvodi' ) ) . '">Natrag</a></p></form></div>';
	}

	public static function apply_extract(): void {
		$id = absint( $_POST['id'] ?? 0 );
		if ( ! current_user_can( 'edit_product', $id ) || ! check_admin_referer( 'dsp_extract_' . $id ) ) {
			wp_die( 'Nema dozvole.' );
		}
		$p     = wc_get_product( $id );
		$found = self::parse_description( $p->get_description() );
		$pick  = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['pick'] ?? array() ) );
		$attrs = $p->get_attributes();
		$names = array();
		foreach ( $attrs as $a ) {
			$names[ Domestic_Proizvod_Data::norm( wc_attribute_label( $a->get_name(), $p ) ) ] = true;
		}
		$n = 0;
		foreach ( $pick as $key ) {
			if ( ! isset( $found[ $key ] ) ) {
				continue;
			}
			if ( '__ean' === $key ) {
				if ( method_exists( $p, 'set_global_unique_id' ) && ! $p->get_global_unique_id() ) {
					try {
						$p->set_global_unique_id( preg_replace( '/\D/', '', $found[ $key ][1] ) );
						$n++;
					} catch ( Exception $e ) { // EAN već postoji na drugom proizvodu
						continue;
					}
				}
				continue;
			}
			list( $label, $val ) = $found[ $key ];
			if ( isset( $names[ Domestic_Proizvod_Data::norm( $label ) ] ) ) {
				continue;
			}
			$a = new WC_Product_Attribute();
			$a->set_name( $label );
			$a->set_options( array( $val ) );
			$a->set_visible( true );
			$a->set_variation( false );
			$a->set_position( count( $attrs ) );
			$attrs[ sanitize_title( $label ) ] = $a;
			$n++;
		}
		$p->set_attributes( $attrs );
		$p->save();
		wp_safe_redirect( add_query_arg( 'dsp_msg', rawurlencode( $p->get_name() . ': upisano ' . $n . ' podataka.' ), admin_url( 'admin.php?page=domestic-proizvodi' ) ) );
		exit;
	}

	/* ---------- Domestic → Stranica proizvoda ---------- */

	public static function page_settings(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$s = Domestic_Proizvod_Data::settings();
		echo '<div class="wrap dsp-admin"><h1>Stranica proizvoda</h1>';
		self::notice();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'dsp_settings' );
		echo '<input type="hidden" name="action" value="dsp_save_settings"><table class="form-table">';
		echo '<tr><th>Novi izgled</th><td><label><input type="radio" name="nacin" value="oznaceni" ' . checked( $s['nacin'], 'oznaceni', false ) . '> samo za proizvode označene na kartici „Domestic”</label><br><label><input type="radio" name="nacin" value="svi" ' . checked( $s['nacin'], 'svi', false ) . '> za sve proizvode (pojedini se mogu isključiti)</label></td></tr>';
		echo '<tr><th><label for="rok">Rok slanja</label></th><td><input id="rok" name="rok" class="regular-text" value="' . esc_attr( $s['rok'] ) . '"><p class="description">Piše uz „Na stanju · šaljemo u …”.</p></td></tr>';
		echo '<tr><th><label for="od">Besplatna dostava od (€)</label></th><td><input id="od" name="besplatno_od" class="small-text" value="' . esc_attr( $s['besplatno_od'] ) . '"> <input name="podrucje" class="regular-text" value="' . esc_attr( $s['podrucje'] ) . '" placeholder="područje, npr. Zagrebačke županije"><p class="description">0 = ne prikazuje se.</p></td></tr>';
		echo '<tr><th><label for="novo">Oznaka „Novo”</label></th><td><input id="novo" name="novo_dana" class="small-text" value="' . esc_attr( $s['novo_dana'] ) . '"> dana nakon dodavanja proizvoda (0 = bez oznake)</td></tr>';
		echo '<tr><th><label for="slike">Oznake slika</label></th><td><input id="slike" name="slike" class="large-text" value="' . esc_attr( $s['slike'] ) . '"><p class="description">Redom za 1., 2., 3. sliku proizvoda, odvojeno s |. Prva je glavna slika, ostale iz galerije.</p></td></tr>';
		echo '<tr><th><label for="dostava_url">Uvjeti dostave</label></th><td><input id="dostava_url" name="dostava_url" class="large-text" value="' . esc_attr( $s['dostava_url'] ) . '" placeholder="https://…/dostava/"><p class="description">Poveznica „Uvjeti dostave” ispod okvira za kupnju (prazno = bez poveznice).</p></td></tr>';
		echo '</table><p><button class="button button-primary">Spremi</button></p></form>';
		echo '<h2>Za Flatsome UX Builder</h2><p>Dijelovi stranice dostupni su i kao kratki kodovi, ako se raspored želi složiti ručno: <code>[domestic_proizvod dio="galerija"]</code>, <code>[domestic_proizvod dio="kupnja"]</code>, <code>[domestic_proizvod dio="kartice"]</code>, <code>[domestic_proizvod dio="povezani"]</code> ili cijela stranica <code>[domestic_proizvod]</code>.</p></div>';
	}

	public static function save_settings(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'dsp_settings' ) ) {
			wp_die( 'Nema dozvole.' );
		}
		$post = wp_unslash( $_POST );
		update_option(
			Domestic_Proizvod_Data::OPT,
			array(
				'nacin'        => 'svi' === ( $post['nacin'] ?? '' ) ? 'svi' : 'oznaceni',
				'rok'          => sanitize_text_field( $post['rok'] ?? '' ),
				'besplatno_od' => (string) max( 0, (float) str_replace( ',', '.', (string) ( $post['besplatno_od'] ?? '0' ) ) ),
				'podrucje'     => sanitize_text_field( $post['podrucje'] ?? '' ),
				'novo_dana'    => (string) max( 0, absint( $post['novo_dana'] ?? 0 ) ),
				'slike'        => sanitize_text_field( $post['slike'] ?? '' ),
				'dostava_url'  => esc_url_raw( trim( (string) ( $post['dostava_url'] ?? '' ) ) ),
			)
		);
		wp_safe_redirect( add_query_arg( 'dsp_msg', rawurlencode( 'Spremljeno.' ), admin_url( 'admin.php?page=domestic-stranica-proizvoda' ) ) );
		exit;
	}

	/* ---------- Tekstovi: jednobrazan prikaz ---------- */

	public static function page_tekst(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$s = Domestic_Proizvod_Tekst::postavke();
		echo '<div class="wrap dsp-admin"><h1>Tekstovi</h1>';
		self::notice();
		echo '<p>Nazivi, kategorije i opisi proizvoda uređuju se <b>samo pri prikazu</b> u trgovini: ovdje u administraciji ostaju kako su upisani, a kupci vide jednobrazan i pravopisno ispravan tekst. Adrese stranica se ne mijenjaju.</p>';
		echo '<details><summary><b>Pravila koja se primjenjuju</b></summary><ul style="list-style:disc;margin-left:20px">'
			. '<li>Zapremnine: 0,75L · 0,75 L · 0.75l → <code>0,75 l</code>; 1,00L → <code>1 l</code>; 4x0,05L → <code>4 × 0,05 l</code></li>'
			. '<li>Navodnici "…" → <code>„…”</code>; crtica između riječi → <code>–</code>; zvjezdica na kraju naziva se uklanja</li>'
			. '<li>Alkohol: 20 vol. % · 20%vol → <code>20 % vol.</code>; temperatura: 8-10°C → <code>8 – 10 °C</code></li>'
			. '<li>„sa” samo ispred s, z, š, ž (sa sirom, s voćem); „biti će” → „bit će”; razmaci (dvostruki, prije zareza, iza zareza)</li>'
			. '<li>Podaci: jednake oznake (Naziv vina / Naziv likera → Naziv, NAPOMENA → Napomena), vrijednost počinje velikim slovom, bez točke na kraju</li>'
			. '<li>Sorte malim slovom (Malvazija istarska, Pinot sivi), Rosé, Cuvée, ispravci tipfelera iz opisa (vrunsko, dugačije, đem …)</li></ul></details>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'dsp_tekst' );
		echo '<input type="hidden" name="action" value="dsp_tekst"><table class="form-table">';
		echo '<tr><th>Uređivanje prikaza</th><td><label><input type="checkbox" name="ukljuceno" value="yes" ' . checked( $s['ukljuceno'], 'yes', false ) . '> uključeno</label></td></tr>';
		echo '<tr><th><label for="rjecnik">Vlastiti ispravci</label></th><td><textarea id="rjecnik" name="rjecnik" rows="6" class="large-text code" placeholder="pogrešno => ispravno">' . esc_textarea( $s['rjecnik'] ) . '</textarea><p class="description">Jedan po retku, npr. <code>Excluzive => Exclusive</code>. Vrijedi za nazive i opise (cijele riječi, točno ovako napisane).</p></td></tr>';
		echo '</table><p><button class="button button-primary">Spremi</button></p></form>';

		$rows = '';
		$n    = 0;
		foreach ( wc_get_products( array( 'limit' => -1, 'status' => array( 'publish', 'private' ), 'orderby' => 'title', 'order' => 'ASC' ) ) as $p ) {
			$izvor = $p->get_name( 'edit' );
			$novo  = Domestic_Proizvod_Tekst::naziv( $izvor );
			if ( $novo !== $izvor ) {
				$n++;
				$rows .= '<tr><td><a href="' . esc_url( get_edit_post_link( $p->get_id() ) ) . '">' . esc_html( $izvor ) . '</a></td><td><b>' . esc_html( $novo ) . '</b></td></tr>';
			}
		}
		echo '<h2>Nazivi proizvoda (' . (int) $n . ' se prikazuje drukčije)</h2><table class="widefat striped" style="max-width:1000px"><thead><tr><th>Upisano</th><th>Prikazuje se</th></tr></thead><tbody>' . ( $rows ? $rows : '<tr><td colspan="2">Svi nazivi su već jednobrazni.</td></tr>' ) . '</tbody></table>';
		$rows = '';
		foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) ) as $t ) {
			$novo = Domestic_Proizvod_Tekst::kategorija( $t->name );
			if ( $novo !== $t->name ) {
				$rows .= '<tr><td>' . esc_html( $t->name ) . '</td><td><b>' . esc_html( $novo ) . '</b></td></tr>';
			}
		}
		echo '<h2>Kategorije</h2><table class="widefat striped" style="max-width:1000px"><tbody>' . ( $rows ? $rows : '<tr><td>Bez promjena.</td></tr>' ) . '</tbody></table></div>';
	}

	public static function save_tekst(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'dsp_tekst' ) ) {
			wp_die( 'Nema dozvole.' );
		}
		update_option(
			Domestic_Proizvod_Tekst::OPT,
			array(
				'ukljuceno' => 'yes' === ( $_POST['ukljuceno'] ?? '' ) ? 'yes' : 'no',
				'rjecnik'   => sanitize_textarea_field( wp_unslash( $_POST['rjecnik'] ?? '' ) ),
			),
			false
		);
		wp_safe_redirect( add_query_arg( 'dsp_msg', rawurlencode( 'Spremljeno.' ), admin_url( 'admin.php?page=domestic-tekstovi' ) ) );
		exit;
	}
}
