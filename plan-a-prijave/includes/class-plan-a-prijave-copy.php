<?php
/**
 * „Kopiraj izlet”: nova skica s istim sadržajem, kartama i postavkama, bez termina i prijava.
 *
 * @package Plan_A_Prijave
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Prijave_Copy {

	/** Podaci koji se ne kopiraju (veza na proizvod, termini, izračunati datumi, brojači). */
	const SKIP = array(
		'link_wc_product', 'check_if_run_once', 'total_booking', '_edit_lock', '_edit_last', '_wp_old_slug',
		'ttbm_particular_dates', 'ttbm_travel_start_date', 'ttbm_travel_end_date', 'ttbm_travel_reg_end_date',
		'ttbm_upcoming_date', 'ttbm_reg_end_date', 'ttbm_month_list',
	);

	public static function init() {
		add_filter( 'post_row_actions', array( __CLASS__, 'link' ), 20, 2 );
		add_action( 'admin_action_pap_copy', array( __CLASS__, 'copy' ) );
		add_action( 'post_submitbox_misc_actions', array( __CLASS__, 'button' ) );
	}

	private static function url( int $id ): string {
		return wp_nonce_url( admin_url( 'admin.php?action=pap_copy&post=' . $id ), 'pap_copy_' . $id );
	}

	public static function link( $actions, $post ) {
		if ( Plan_A_Prijave_Tour::CPT === $post->post_type && current_user_can( 'edit_posts' ) ) {
			$actions['pap_copy'] = '<a href="' . esc_url( self::url( (int) $post->ID ) ) . '">Kopiraj izlet</a>';
		}
		return $actions;
	}

	public static function button( $post ) {
		if ( $post && Plan_A_Prijave_Tour::CPT === $post->post_type && 'auto-draft' !== $post->post_status ) {
			echo '<div class="misc-pub-section"><a class="button" href="' . esc_url( self::url( (int) $post->ID ) ) . '">Kopiraj izlet (novi termin)</a></div>';
		}
	}

	public static function copy() {
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'pap_copy_' . $id );
		$src = get_post( $id );
		if ( ! $src || Plan_A_Prijave_Tour::CPT !== $src->post_type || ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'Izlet ne postoji.' );
		}
		$new = wp_insert_post(
			array(
				'post_type'    => $src->post_type,
				'post_status'  => 'draft',
				'post_title'   => $src->post_title . ' (kopija)',
				'post_content' => $src->post_content,
				'post_excerpt' => $src->post_excerpt,
				'post_author'  => get_current_user_id(),
			),
			true
		);
		if ( is_wp_error( $new ) ) {
			wp_die( esc_html( $new->get_error_message() ) );
		}
		foreach ( get_post_meta( $id ) as $k => $vals ) {
			if ( in_array( $k, self::SKIP, true ) ) {
				continue;
			}
			delete_post_meta( $new, $k );
			foreach ( $vals as $v ) {
				add_post_meta( $new, $k, maybe_unserialize( $v ) );
			}
		}
		update_post_meta( $new, 'ttbm_particular_dates', array() );
		foreach ( get_object_taxonomies( $src->post_type ) as $tax ) {
			wp_set_object_terms( $new, wp_get_object_terms( $id, $tax, array( 'fields' => 'ids' ) ), $tax );
		}
		wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . (int) $new ) );
		exit;
	}
}
