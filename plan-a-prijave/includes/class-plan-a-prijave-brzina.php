<?php
/**
 * Brzina stranice (mobitel): prva slika izleta se učitava odmah, posjetitelji ne učitavaju stilove
 * administratorske trake. (srcset za Flatsome banner isproban i odbačen: Flatsome tada preuzme i izvornik i manju sliku.)
 *
 * @package Plan_A_Prijave
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Prijave_Brzina {

	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'preload' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'dequeue' ), 999 );
	}

	/** Izlet: prva slika slidera (ista kao u TTBM_Custom_Slider – „large”) kreće odmah, prije CSS-a i JS-a. */
	public static function preload() {
		if ( ! is_singular( Plan_A_Prijave_Tour::CPT ) ) {
			return;
		}
		$id    = (int) get_queried_object_id();
		$first = (int) get_post_thumbnail_id( $id );
		if ( ! $first ) {
			$ids   = (array) get_post_meta( $id, 'ttbm_gallery_images', true );
			$first = (int) reset( $ids );
		}
		$src = $first ? wp_get_attachment_image_src( $first, 'large' ) : false;
		if ( $src && ! empty( $src[0] ) ) {
			echo '<link rel="preload" as="image" href="' . esc_url( $src[0] ) . '" fetchpriority="high">' . "\n";
		}
	}

	/** Bez administratorske trake (posjetitelji) ne trebaju njezini stilovi ni ikone dashicons (~50 KB). */
	public static function dequeue() {
		if ( is_admin_bar_showing() ) {
			return;
		}
		wp_dequeue_style( 'admin-bar' );
		if ( ! is_user_logged_in() ) {
			wp_dequeue_style( 'dashicons' );
		}
	}
}
