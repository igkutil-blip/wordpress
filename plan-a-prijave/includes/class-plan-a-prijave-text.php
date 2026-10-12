<?php
/**
 * Tekst izleta uvijek u fontu i veličini stranice: iz zalijepljenog teksta (Word, mail, web)
 * miču se font, veličina, prored, boja i pozadina. Podebljano, kurziv (i kao font-weight/style), popisi, naslovi,
 * poveznice i poravnanje ostaju. Za već upisane izlete isto radi CSS na stranici izleta.
 *
 * @package Plan_A_Prijave
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Prijave_Text {

	/** Svojstva iz atributa style koja se miču. */
	const DROP = '/^(font(-family|-size|-variant|-stretch)?|line-height|letter-spacing|word-spacing|color|background(-color|-image)?|text-indent|text-transform|margin(-\w+)?|padding(-\w+)?|mso-[\w-]+|white-space)$/i';

	public static function init() {
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'post_data' ), 20, 2 );
		add_filter( 'tiny_mce_before_init', array( __CLASS__, 'tinymce' ), 20, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'front_css' ), 40 );
	}

	/** Očisti HTML (opis izleta, blokovi Važno, opis vodiča). */
	public static function clean( string $html ): string {
		if ( '' === trim( $html ) ) {
			return $html;
		}
		// <font> i Wordove oznake: makni oznaku, zadrži tekst.
		$html = preg_replace( '#</?(font|o:p)\b[^>]*>#i', '', $html );
		// Atribut style: zadrži samo dopuštena svojstva (npr. text-align).
		$html = preg_replace_callback(
			'#\sstyle\s*=\s*("([^"]*)"|\'([^\']*)\')#i',
			static function ( $m ) {
				$css  = '' !== ( $m[2] ?? '' ) ? $m[2] : ( $m[3] ?? '' );
				$keep = array();
				foreach ( explode( ';', html_entity_decode( $css, ENT_QUOTES ) ) as $decl ) {
					$parts = explode( ':', $decl, 2 );
					if ( 2 !== count( $parts ) ) {
						continue;
					}
					$prop = strtolower( trim( $parts[0] ) );
					if ( '' === $prop || preg_match( self::DROP, $prop ) ) {
						continue;
					}
					$keep[] = $prop . ': ' . trim( $parts[1] );
				}
				return $keep ? ' style="' . esc_attr( implode( '; ', $keep ) ) . ';"' : '';
			},
			$html
		);
		// Wordove klase (MsoNormal …) i prazni <span> bez atributa.
		$html = preg_replace( '#\sclass\s*=\s*("|\')\s*Mso[^"\']*\1#i', '', $html );
		for ( $i = 0; $i < 3; $i++ ) {
			$html = preg_replace( '#<span>(.*?)</span>#is', '$1', $html );
		}
		return $html;
	}

	/** Opis izleta i vodiča pri spremanju. */
	public static function post_data( $data, $postarr ) {
		$type = $data['post_type'] ?? '';
		if ( ( Plan_A_Prijave_Tour::CPT === $type || 'ttbm_guide' === $type ) && ! empty( $data['post_content'] ) ) {
			$data['post_content'] = wp_slash( self::clean( wp_unslash( $data['post_content'] ) ) );
		}
		return $data;
	}

	/** Lijepljenje u uređivaču izleta: bez fontova, veličina i boja. */
	public static function tinymce( $init, $editor_id = '' ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && in_array( $screen->post_type, array( Plan_A_Prijave_Tour::CPT, 'ttbm_guide' ), true ) ) {
			$init['paste_remove_styles']            = true;
			$init['paste_remove_styles_if_webkit']  = true;
			$init['paste_webkit_styles']            = 'none';
			$init['paste_retain_style_properties']  = 'none';
		}
		return $init;
	}

	/** Već upisani izleti: na stranici izleta tekst preuzima font i veličinu stranice. */
	public static function front_css() {
		if ( ! is_singular( array( Plan_A_Prijave_Tour::CPT, 'ttbm_guide' ) ) ) {
			return;
		}
		$css = '#ttbm_content [style*="font-family"],#ttbm_content font{font-family:inherit!important}'
			. '#ttbm_content [style*="font-size"],#ttbm_content font[size]{font-size:inherit!important}'
			. '#ttbm_content [style*="line-height"]{line-height:inherit!important}'
			. '#ttbm_content .ttbm_wp_editor [style*="background"],#ttbm_content .ttbm_wp_editor [style*="color"]{background:transparent!important;color:inherit!important}';
		wp_register_style( 'pap-text', false, array(), PLAN_A_PRIJAVE_VERSION );
		wp_enqueue_style( 'pap-text' );
		wp_add_inline_style( 'pap-text', $css );
	}
}
