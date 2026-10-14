<?php
/**
 * Kratki video „Kako se prijaviti na izlet” ispod gumba Rezervirajte: klik otvara prozorčić
 * kao poruka na WhatsAppu (assets/pa-video.js|css, video u assets/video/).
 *
 * @package Plan_A_Prijave
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Prijave_Video {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function assets() {
		if ( ! is_singular( Plan_A_Prijave_Tour::CPT ) ) {
			return;
		}
		$v = PLAN_A_PRIJAVE_VERSION . '.' . (int) filemtime( PLAN_A_PRIJAVE_DIR . 'assets/pa-video.js' );
		wp_enqueue_style( 'pa-video', PLAN_A_PRIJAVE_URL . 'assets/pa-video.css', array(), $v );
		wp_enqueue_script( 'pa-video', PLAN_A_PRIJAVE_URL . 'assets/pa-video.js', array(), $v, true );
	}

	/** Poveznica ispod gumba Rezervirajte. */
	public static function button(): string {
		$base = PLAN_A_PRIJAVE_URL . 'assets/video/prijava-na-izlet';
		return '<button type="button" class="pa-vid-link" data-pa-video="' . esc_url( $base . '.mp4?v=' . PLAN_A_PRIJAVE_VERSION ) . '" data-pa-poster="' . esc_url( $base . '.jpg' ) . '"'
			. ' data-pa-avatar="' . esc_url( (string) get_site_icon_url( 96 ) ) . '" data-pa-title="Kako se prijaviti na izlet"'
			. ' data-pa-text="Pozdrav! Evo kako se u minuti prijaviš na izlet:">'
			. '<span class="pa-vid-link__play" aria-hidden="true">▶</span><span>Pogledaj kako se prijaviti <small>(1 min)</small></span></button>';
	}
}
