<?php
/**
 * Administracija: Domestic → Kartica proizvoda (postavke + pregled kartica uživo).
 */

defined( 'ABSPATH' ) || exit;

final class Domestic_Kartica_Admin {

	const SLUG = 'domestic-kartica';

	public static function init(): void {
		Domestic_Menu::add( self::SLUG, 'Kartica proizvoda', array( __CLASS__, 'stranica' ), 20 );
		add_action( 'admin_post_dsk_spremi', array( __CLASS__, 'spremi' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function assets( $hook ): void {
		if ( false === strpos( (string) $hook, self::SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){$(".dsk-boja").wpColorPicker();});' );
		wp_enqueue_style( 'domestic-kartica', DSK_URL . 'assets/kartica.css', array(), DSK_VER . '.' . filemtime( DSK_DIR . '/assets/kartica.css' ) );
		wp_add_inline_style( 'domestic-kartica', Domestic_Kartica::css_varijable() . '.dsk-pregled{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:16px;max-width:1000px;margin:12px 0 24px;padding:16px;background:#f0f0f1;border-radius:6px}.dsk-pregled .dsk-kartica{width:auto!important;max-width:none!important;padding:0!important;margin:0!important;flex:none!important}.dsk-red td{padding:4px 8px}' );
	}

	private static function pregled(): void {
		$proizvodi = wc_get_products( array( 'status' => 'publish', 'limit' => 4, 'orderby' => 'date', 'order' => 'DESC', 'visibility' => 'catalog' ) );
		if ( ! $proizvodi ) {
			echo '<p>Nema objavljenih proizvoda za pregled.</p>';
			return;
		}
		global $product, $post;
		$stari_p = $product;
		$stari   = $post;
		$GLOBALS['dsk_pregled'] = true;
		echo '<div class="dsk-pregled">';
		foreach ( $proizvodi as $pr ) {
			$post    = get_post( $pr->get_id() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			$product = $pr; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			setup_postdata( $post );
			include DSK_DIR . '/templates/content-product.php';
		}
		echo '</div>';
		unset( $GLOBALS['dsk_pregled'] );
		$product = $stari_p; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		$post    = $stari; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		wp_reset_postdata();
	}

	public static function stranica(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$s = Domestic_Kartica::postavke();
		echo '<div class="wrap"><h1>Kartica proizvoda</h1>';
		if ( isset( $_GET['dsk_ok'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>Spremljeno.</p></div>';
		}
		echo '<p>Izgled kartice proizvoda u trgovini, kategorijama, povezanim proizvodima i Flatsome blokovima s proizvodima. Pregled ispod pokazuje zadnja 4 proizvoda s trenutnim postavkama (i kad je kartica isključena na stranici).</p>';
		self::pregled();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'dsk_spremi' );
		echo '<input type="hidden" name="action" value="dsk_spremi"><table class="form-table">';
		echo '<tr><th>Nova kartica</th><td><label><input type="checkbox" name="ukljuceno" value="yes" ' . checked( $s['ukljuceno'], 'yes', false ) . '> uključena na stranici</label><p class="description">Isključeno = ostaje izvorna kartica teme (Flatsome).</p></td></tr>';

		echo '<tr><th>Elementi i redoslijed</th><td><table class="dsk-red"><thead><tr><td><b>Prikaz</b></td><td><b>Redni broj</b></td><td></td></tr></thead><tbody>';
		$red = array_flip( $s['redoslijed'] );
		$i   = count( $red );
		foreach ( Domestic_Kartica::ELEMENTI as $k => $naziv ) {
			$poz = isset( $red[ $k ] ) ? $red[ $k ] + 1 : ++$i;
			echo '<tr><td><input type="checkbox" name="el[' . esc_attr( $k ) . ']" value="1" ' . checked( isset( $red[ $k ] ), true, false ) . '></td><td><input type="number" min="1" max="20" class="small-text" name="poz[' . esc_attr( $k ) . ']" value="' . (int) $poz . '"></td><td>' . esc_html( $naziv ) . '</td></tr>';
		}
		echo '</tbody></table><p class="description">Slika je uvijek na vrhu. Marka, zapremnina i alkohol čitaju se iz atributa proizvoda (Marka, Zapremnina, Alkohol).</p></td></tr>';

		echo '<tr><th>Oznake na slici</th><td>';
		foreach ( Domestic_Kartica::OZNAKE as $k => $naziv ) {
			echo '<label style="display:block"><input type="checkbox" name="oznake[]" value="' . esc_attr( $k ) . '" ' . checked( in_array( $k, $s['oznake'], true ), true, false ) . '> ' . esc_html( $naziv ) . '</label>';
		}
		echo '<p>„Novo” vrijedi <input type="number" min="0" max="365" class="small-text" name="novo_dana" value="' . (int) $s['novo_dana'] . '"> dana od dodavanja proizvoda. „Još samo N” kad je na zalihi <input type="number" min="1" max="50" class="small-text" name="malo_do" value="' . (int) $s['malo_do'] . '"> ili manje komada.</p><p class="description">Na kartici najviše 3 oznake, po važnosti: rasprodano, akcija, malo na zalihi, novo, ograničeno izdanje, nagrađeno, omiljeno, izdvajamo, po narudžbi.</p></td></tr>';

		echo '<tr><th>Slika</th><td><select name="omjer">';
		foreach ( array( '1:1' => 'kvadrat 1:1', '4:5' => 'uspravno 4:5', '3:4' => 'uspravno 3:4', '2:3' => 'visoko 2:3', 'izvorni' => 'izvorni omjer slike' ) as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '" ' . selected( $s['omjer'], $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select> &nbsp; <select name="ispuna"><option value="cijela" ' . selected( $s['ispuna'], 'cijela', false ) . '>cijela slika (bez rezanja)</option><option value="ispuni" ' . selected( $s['ispuna'], 'ispuni', false ) . '>ispuni okvir (reže rubove)</option></select>';
		echo ' &nbsp; pozadina iza boce <input class="dsk-boja" name="slika_poz" value="' . esc_attr( $s['slika_poz'] ) . '"></td></tr>';

		echo '<tr><th>Gumb</th><td><input name="gumb_tekst" class="regular-text" value="' . esc_attr( $s['gumb_tekst'] ) . '"><p class="description">Tekst za obične proizvode na stanju (ostali zadržavaju „Odaberi opcije”, „Pročitaj više”…).</p>';
		echo '<label><input type="checkbox" name="brzi" value="yes" ' . checked( $s['brzi'], 'yes', false ) . '> poveznica „Brzi pregled” na slici (Flatsome)</label></td></tr>';

		echo '<tr><th>Raspored trgovine</th><td><select name="raspored"><option value="gore" ' . selected( $s['raspored'], 'gore', false ) . '>filteri iznad artikala, bez bočne trake</option><option value="tema" ' . selected( $s['raspored'], 'tema', false ) . '>kako je postavljeno u Flatsomeu</option></select>'
			. ' &nbsp; artikala u redu <select name="stupci">' . implode( '', array_map( static function ( $n ) use ( $s ) { return '<option value="' . $n . '" ' . selected( (int) $s['stupci'], $n, false ) . '>' . $n . '</option>'; }, array( 3, 4, 5 ) ) ) . '</select>'
			. ' &nbsp; <label><input type="checkbox" name="nedavno" value="yes" ' . checked( $s['nedavno'], 'yes', false ) . '> „Nedavno pregledano” ispod artikala</label>'
			. '<p><label><input type="checkbox" name="trazilica" value="yes" ' . checked( $s['trazilica'], 'yes', false ) . '> pretraga s prijedlozima dok se tipka (sličice; bez obzira na kvačice; marka, kategorija, šifra)</label><br><label><input type="checkbox" name="zaglavlje" value="yes" ' . checked( $s['zaglavlje'], 'yes', false ) . '> novi izgled polja za pretragu i ikone košarice u zaglavlju</label></p>'
			. '<p class="description">Trgovina, kategorije i pretraga: naslov kategorije, filteri Kategorija / Cijena / Poredaj (padajući izbornici), uređene stranice 1, 2, 3. Adrese kategorija ostaju iste.</p></td></tr>';
		echo '<tr><th>Pozadina cijele stranice</th><td><input class="dsk-boja" name="stranica" value="' . esc_attr( $s['stranica'] ) . '"><p class="description">Npr. krem #f6f1e9 za cijelu stranicu i zaglavlje. Prazno = boja iz teme. Vrijedi i kad je nova kartica isključena.</p></td></tr>';
		echo '<tr><th>Fontovi</th><td><select name="fontovi"><option value="cijela" ' . selected( $s['fontovi'], 'cijela', false ) . '>Marcellus + Jost na cijeloj stranici</option><option value="shop" ' . selected( $s['fontovi'], 'shop', false ) . '>samo kartice i stranica proizvoda</option><option value="ne" ' . selected( $s['fontovi'], 'ne', false ) . '>fontovi teme</option></select><p class="description">Učitavaju se s ove stranice (bez Googlea), samo latinica i hrvatska slova, ~40 KB za prvi prikaz.</p></td></tr>';
		echo '<tr><th>Boje</th><td><p>naglasak (gumb, akcija, marka) <input class="dsk-boja" name="naglasak" value="' . esc_attr( $s['naglasak'] ) . '"></p><p>pozadina kartice <input class="dsk-boja" name="pozadina" value="' . esc_attr( $s['pozadina'] ) . '"></p><p>tekst <input class="dsk-boja" name="tekst" value="' . esc_attr( $s['tekst'] ) . '"></p></td></tr>';
		echo '<tr><th>Oblik</th><td>zaobljenje <input type="number" min="0" max="30" class="small-text" name="zaobljenje" value="' . (int) $s['zaobljenje'] . '"> px &nbsp; <label><input type="checkbox" name="okvir" value="yes" ' . checked( $s['okvir'], 'yes', false ) . '> tanki okvir</label> &nbsp; <label><input type="checkbox" name="sjena" value="yes" ' . checked( $s['sjena'], 'yes', false ) . '> sjena</label> &nbsp; poravnanje <select name="poravnanje"><option value="lijevo" ' . selected( $s['poravnanje'], 'lijevo', false ) . '>lijevo</option><option value="sredina" ' . selected( $s['poravnanje'], 'sredina', false ) . '>sredina</option></select></td></tr>';
		echo '<tr><th><label for="dsk-css">Dodatni CSS</label></th><td><textarea id="dsk-css" name="css" rows="6" class="large-text code" placeholder=".dsk-kartica .dsk-naziv{font-size:16px}">' . esc_textarea( $s['css'] ) . '</textarea><p class="description">Klase: .dsk-kartica, .dsk-slika, .dsk-oznaka, .dsk-marka, .dsk-naziv, .dsk-podaci, .dsk-cijena, .dsk-jedinicna, .dsk-gumb.</p></td></tr>';
		echo '</table><p><button class="button button-primary">Spremi</button></p></form></div>';
	}

	public static function spremi(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'dsk_spremi' ) ) {
			wp_die( 'Nema dozvole.' );
		}
		$p   = wp_unslash( $_POST );
		$el  = array_intersect_key( (array) ( $p['el'] ?? array() ), Domestic_Kartica::ELEMENTI );
		$poz = (array) ( $p['poz'] ?? array() );
		$red = array_keys( $el );
		usort(
			$red,
			static function ( $a, $b ) use ( $poz ) {
				return (int) ( $poz[ $a ] ?? 99 ) <=> (int) ( $poz[ $b ] ?? 99 );
			}
		);
		$boja = static function ( $v, $d ) {
			return sanitize_hex_color( (string) $v ) ?: $d;
		};
		$z    = Domestic_Kartica::zadano();
		update_option(
			Domestic_Kartica::OPT,
			array(
				'ukljuceno'  => 'yes' === ( $p['ukljuceno'] ?? '' ) ? 'yes' : 'no',
				'redoslijed' => $red,
				'oznake'     => array_values( array_intersect( (array) ( $p['oznake'] ?? array() ), array_keys( Domestic_Kartica::OZNAKE ) ) ),
				'malo_do'    => min( 50, max( 1, absint( $p['malo_do'] ?? 5 ) ) ),
				'novo_dana'  => min( 365, absint( $p['novo_dana'] ?? 30 ) ),
				'omjer'      => in_array( $p['omjer'] ?? '', array( '1:1', '4:5', '3:4', '2:3', 'izvorni' ), true ) ? $p['omjer'] : '1:1',
				'gumb_tekst' => sanitize_text_field( $p['gumb_tekst'] ?? '' ),
				'brzi'       => 'yes' === ( $p['brzi'] ?? '' ) ? 'yes' : 'no',
				'poravnanje' => 'sredina' === ( $p['poravnanje'] ?? '' ) ? 'sredina' : 'lijevo',
				'naglasak'   => $boja( $p['naglasak'] ?? '', $z['naglasak'] ),
				'pozadina'   => $boja( $p['pozadina'] ?? '', $z['pozadina'] ),
				'ispuna'     => 'ispuni' === ( $p['ispuna'] ?? '' ) ? 'ispuni' : 'cijela',
				'slika_poz'  => $boja( $p['slika_poz'] ?? '', $z['slika_poz'] ),
				'tekst'      => $boja( $p['tekst'] ?? '', $z['tekst'] ),
				'zaobljenje' => min( 30, absint( $p['zaobljenje'] ?? 8 ) ),
				'okvir'      => 'yes' === ( $p['okvir'] ?? '' ) ? 'yes' : 'no',
				'sjena'      => 'yes' === ( $p['sjena'] ?? '' ) ? 'yes' : 'no',
				'fontovi'    => in_array( $p['fontovi'] ?? '', array( 'cijela', 'shop', 'ne' ), true ) ? $p['fontovi'] : 'cijela',
				'stranica'   => (string) sanitize_hex_color( (string) ( $p['stranica'] ?? '' ) ),
				'css'        => wp_strip_all_tags( (string) ( $p['css'] ?? '' ) ),
				'raspored'   => 'tema' === ( $p['raspored'] ?? '' ) ? 'tema' : 'gore',
				'stupci'     => min( 5, max( 3, absint( $p['stupci'] ?? 4 ) ) ),
				'nedavno'    => 'yes' === ( $p['nedavno'] ?? '' ) ? 'yes' : 'no',
				'trazilica'  => 'yes' === ( $p['trazilica'] ?? '' ) ? 'yes' : 'no',
				'zaglavlje'  => 'yes' === ( $p['zaglavlje'] ?? '' ) ? 'yes' : 'no',
			),
			false
		);
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&dsk_ok=1' ) );
		exit;
	}
}
