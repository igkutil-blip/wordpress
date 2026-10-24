<?php
/**
 * Slika za dijeljenje kategorije (Facebook, WhatsApp, Viber, X): Rank Math stavlja sliku kategorije u og:image,
 * ali izvornik može biti velik (PNG 1 MB) – WhatsApp/Viber tada preskoče sliku i uzmu drugu (logo iz twitter:image).
 * Ovdje se iz slike kategorije jednom izradi JPG (≈150 KB) i ona ide u og:image i twitter:image: široka slika 1200×630,
 * kvadratna ili uspravna 1200 px bez rezanja (inače boce ostanu bez vrha i dna).
 * Izvorna slika i njezina adresa ostaju iste; nova datoteka je samo za dijeljenje (uploads/domestic-dijeljenje/).
 * Naziv sadrži otisak izvornika – kad se slika kategorije zamijeni, nastaje nova datoteka pa mreže dohvaćaju novu.
 */

defined( 'ABSPATH' ) || exit;

final class Domestic_Kartica_Dijeljenje {

	const SIRINA = 1200;
	const VISINA = 630;
	const MAPA   = 'domestic-dijeljenje';

	public static function init(): void {
		add_filter( 'rank_math/opengraph/facebook/image_array', array( __CLASS__, 'slika' ) );
		add_filter( 'rank_math/opengraph/twitter/image_array', array( __CLASS__, 'slika' ) );
	}

	public static function slika( $att ) {
		if ( ! function_exists( 'is_product_category' ) || ! is_product_category() ) {
			return $att;
		}
		$term = get_queried_object();
		$id   = $term ? (int) get_term_meta( $term->term_id, 'thumbnail_id', true ) : 0;
		$jpg  = $id ? self::jpg( (int) $term->term_id, $id ) : null;
		$vel  = $jpg ? wp_getimagesize( $jpg['put'] ) : false;
		if ( ! $vel ) {
			return $att;
		}
		$alt = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
		return array(
			'url'    => $jpg['url'],
			'width'  => $vel[0],
			'height' => $vel[1],
			'type'   => 'image/jpeg',
			'alt'    => '' !== $alt ? $alt : $term->name,
		);
	}

	/** JPG za dijeljenje {url, put} (izradi ga ako ne postoji). */
	private static function jpg( int $term_id, int $id ): ?array {
		$izvor = get_attached_file( $id );
		if ( ! $izvor || ! file_exists( $izvor ) ) {
			return null;
		}
		$up   = wp_get_upload_dir();
		$dir  = trailingslashit( $up['basedir'] ) . self::MAPA;
		$ime  = 'kategorija-' . $term_id . '-v2-' . substr( md5( $izvor . '|' . filemtime( $izvor ) . '|' . filesize( $izvor ) ), 0, 8 ) . '.jpg';
		$put  = $dir . '/' . $ime;
		$url  = trailingslashit( $up['baseurl'] ) . self::MAPA . '/' . $ime;
		if ( file_exists( $put ) ) {
			return array( 'url' => $url, 'put' => $put );
		}
		if ( ! wp_mkdir_p( $dir ) ) {
			return null;
		}
		$ed = wp_get_image_editor( $izvor );
		if ( is_wp_error( $ed ) ) {
			return null;
		}
		$v = $ed->get_size();
		if ( $v['width'] / max( 1, $v['height'] ) >= 1.5 ) {
			$ed->resize( self::SIRINA, self::VISINA, true );
		} else {
			$ed->resize( self::SIRINA, self::SIRINA, false );
		}
		$ed->set_quality( 82 );
		$r = $ed->save( $put, 'image/jpeg' );
		if ( is_wp_error( $r ) ) {
			return null;
		}
		if ( class_exists( 'Domestic_Slike_Meta' ) ) {
			Domestic_Slike_Meta::ocisti( $put );
		}
		foreach ( (array) glob( $dir . '/kategorija-' . $term_id . '-*.jpg' ) as $stara ) { // starije verzije iste kategorije
			if ( $stara !== $put ) {
				@unlink( $stara ); // phpcs:ignore
			}
		}
		return array( 'url' => $url, 'put' => $put );
	}
}
