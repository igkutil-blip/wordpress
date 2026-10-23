<?php
/**
 * Domestic → Blog: naslov i uvod popisa priča, skrivene kategorije, datum, poziv u trgovinu.
 */

defined( 'ABSPATH' ) || exit;

final class Domestic_Blog_Admin {

	public static function init(): void {
		Domestic_Menu::add( 'domestic-blog', 'Blog', array( __CLASS__, 'stranica' ), 40 );
		add_action( 'admin_post_domestic_blog', array( __CLASS__, 'spremi' ) );
	}

	public static function spremi(): void {
		if ( ! current_user_can( Domestic_Menu::CAP ) ) {
			wp_die( 'Nemate ovlasti.' );
		}
		check_admin_referer( 'domestic_blog' );
		$z = Domestic_Blog::zadano();
		$s = array();
		foreach ( array( 'nadnaslov', 'naslov', 'cta_naslov', 'cta_tekst' ) as $k ) {
			$s[ $k ] = sanitize_text_field( wp_unslash( $_POST[ $k ] ?? $z[ $k ] ) );
		}
		$s['uvod']        = sanitize_textarea_field( wp_unslash( $_POST['uvod'] ?? '' ) );
		$s['ukljuceno']   = empty( $_POST['ukljuceno'] ) ? 'no' : 'yes';
		$s['datum']       = empty( $_POST['datum'] ) ? 'no' : 'yes';
		$s['po_stranici'] = max( 4, min( 40, (int) ( $_POST['po_stranici'] ?? 13 ) ) );
		$s['skrivene']    = array_map( 'intval', (array) ( $_POST['skrivene'] ?? array() ) );
		update_option( Domestic_Blog::OPCIJA, $s );
		wp_safe_redirect( add_query_arg( 'spremljeno', 1, admin_url( 'admin.php?page=domestic-blog' ) ) );
		exit;
	}

	public static function stranica(): void {
		$s = Domestic_Blog::postavke();
		echo '<div class="wrap"><h1>Blog</h1>';
		if ( isset( $_GET['spremljeno'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success"><p>Spremljeno.</p></div>';
		}
		echo '<p>Izgled bloga (stranica objava, kategorije i objave) u stilu shopa. Samo prikaz – tekstovi objava i adrese se ne mijenjaju. Uvod kategorije = opis kategorije (Objave → Kategorije).</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="domestic_blog">';
		wp_nonce_field( 'domestic_blog' );
		echo '<table class="form-table">';
		echo '<tr><th>Uključeno</th><td><label><input type="checkbox" name="ukljuceno" value="1"' . checked( 'yes', $s['ukljuceno'], false ) . '> novi izgled bloga</label></td></tr>';
		foreach ( array( 'nadnaslov' => 'Nadnaslov', 'naslov' => 'Naslov (H1)', 'cta_naslov' => 'Poziv u trgovinu – naslov', 'cta_tekst' => 'Poziv u trgovinu – tekst' ) as $k => $n ) {
			echo '<tr><th><label for="dsb-' . esc_attr( $k ) . '">' . esc_html( $n ) . '</label></th><td><input class="large-text" id="dsb-' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" value="' . esc_attr( $s[ $k ] ) . '"></td></tr>';
		}
		echo '<tr><th><label for="dsb-uvod">Uvod</label></th><td><textarea class="large-text" rows="3" id="dsb-uvod" name="uvod">' . esc_textarea( $s['uvod'] ) . '</textarea></td></tr>';
		echo '<tr><th><label for="dsb-ps">Priča na stranici</label></th><td><input type="number" min="4" max="40" id="dsb-ps" name="po_stranici" value="' . (int) $s['po_stranici'] . '"> <span class="description">prva je istaknuta, ostale 3 u redu (13 = 1 + 12)</span></td></tr>';
		echo '<tr><th>Datum objave</th><td><label><input type="checkbox" name="datum" value="1"' . checked( 'yes', $s['datum'], false ) . '> prikaži datum na priči</label></td></tr>';
		echo '<tr><th>Skrivene kategorije</th><td>';
		foreach ( get_categories( array( 'hide_empty' => false ) ) as $k ) {
			echo '<label style="display:inline-block;margin:0 16px 6px 0"><input type="checkbox" name="skrivene[]" value="' . (int) $k->term_id . '"' . checked( in_array( (int) $k->term_id, array_map( 'intval', (array) $s['skrivene'] ), true ), true, false ) . '> ' . esc_html( $k->name ) . ' (' . (int) $k->count . ')</label>';
		}
		echo '<p class="description">Ne prikazuju se u filteru ni na karticama (priče ostaju vidljive).</p></td></tr>';
		echo '</table>';
		submit_button( 'Spremi' );
		echo '</form></div>';
	}
}
