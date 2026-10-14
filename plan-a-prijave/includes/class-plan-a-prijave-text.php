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
		add_filter( 'the_content', array( __CLASS__, 'content_links' ), 30 );
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

	/**
	 * Brojevi telefona → poveznica za poziv (tel:), riječ „pristupnica” → stranica pristupnice.
	 * Samo u tekstu izvan postojećih poveznica; IBAN, OIB i datumi se ne diraju.
	 */
	public static function links( string $html ): string {
		if ( '' === trim( $html ) ) {
			return $html;
		}
		$join = self::join_url();
		$out  = '';
		$in_a = 0;
		foreach ( preg_split( '/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE ) as $part ) {
			if ( '' !== $part && '<' === $part[0] ) {
				if ( preg_match( '#^<a\b#i', $part ) ) {
					++$in_a;
				} elseif ( preg_match( '#^</a\s*>#i', $part ) ) {
					$in_a = max( 0, $in_a - 1 );
				}
				$out .= $part;
				continue;
			}
			if ( $in_a ) {
				$out .= $part;
				continue;
			}
			$part = self::phones( $part );
			if ( '' !== $join ) {
				$part = preg_replace( '/(?<![\p{L}\d\/-])(pristupnic\p{L}*)(?![\p{L}\d])/iu', '<a href="' . esc_url( $join ) . '">$1</a>', $part );
			}
			$out .= $part;
		}
		return $out;
	}

	/** Adresa stranice pristupnice (stranica „pristupnica”, inače /pristupnica/). */
	public static function join_url(): string {
		static $url = null;
		if ( null === $url ) {
			$p   = get_page_by_path( 'pristupnica' );
			$url = $p && 'publish' === $p->post_status ? (string) get_permalink( $p ) : home_url( '/pristupnica/' );
		}
		return $url;
	}

	/** Hrvatski brojevi (091 520 4508, 095/906-0556, +385 91 …, 01 2345 678) → <a href="tel:+385…">. */
	private static function phones( string $text ): string {
		return (string) preg_replace_callback(
			'/(?<![\p{L}\d+\/.,:-])((?:\+|00)385[ \/-]?\d{1,2}|0\d{1,2})((?:[ \/-]?\d{2,4}){1,3})(?![\p{L}\d\/:-]|[.,]\d)/u',
			static function ( $m ) use ( $text ) {
				$digits = preg_replace( '/\D/', '', $m[0] );
				$nat    = preg_replace( '/^(00385|385)/', '0', $digits );
				if ( strlen( $nat ) < 9 || strlen( $nat ) > 10 || '0' !== $nat[0] ) {
					return $m[0];
				}
				// Bez razmaka samo mobiteli (09…), da OIB ili drugi brojevi ne postanu poveznica.
				if ( ! preg_match( '/[ \/-]/', $m[0] ) && 0 !== strpos( $nat, '09' ) && ! preg_match( '/^(\+|00)/', $m[0] ) ) {
					return $m[0];
				}
				return '<a href="tel:+385' . substr( $nat, 1 ) . '">' . $m[0] . '</a>';
			},
			$text
		);
	}

	/** Opis izleta na stranici: telefoni i pristupnica kao poveznice (i za već upisane izlete). */
	public static function content_links( $content ) {
		if ( is_singular( Plan_A_Prijave_Tour::CPT ) && in_the_loop() ) {
			return self::links( (string) $content );
		}
		return $content;
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

	private static function black_css(): string {
		$areas = array( '.ttbm_overview_section .ttbm_wp_editor', '.ttbm_faq_section .ttbm_faq_content', '.day_wise_details_item_details', '.ttbm_why_choose_us_list' );
		$tags  = array( '', ' p', ' li', ' td', ' th', ' div', ' span', ' strong', ' b', ' em', ' i', ' h3', ' h4', ' h5', ' h6', ' .day_wise_activity_title', ' .day_wise_activity_desc' );
		$sel   = array();
		foreach ( $areas as $a ) {
			foreach ( $tags as $t ) {
				$sel[] = '#ttbm_content ' . $a . $t;
			}
		}
		// Ista veličina za sav upisani tekst (16 px, prored 1.65) na svim širinama; podebljano/kurziv ostaju, naslovi unutar teksta samo podebljani.
		$size = array();
		$head = array();
		foreach ( $areas as $a ) {
			foreach ( array( '', ' p', ' li', ' td', ' th', ' div', ' span', ' strong', ' b', ' em', ' a', ' u', ' .day_wise_activity_title', ' .day_wise_activity_desc' ) as $t ) {
				$size[] = '#ttbm_content ' . $a . $t;
			}
			foreach ( array( ' h1', ' h2', ' h3', ' h4', ' h6' ) as $t ) {
				$head[] = '#ttbm_content ' . $a . $t;
			}
		}
		return implode( ',', $sel ) . '{color:#111827!important}'
			. implode( ',', $size ) . '{font-size:16px!important;line-height:1.65!important}'
			. implode( ',', $head ) . '{font-size:16px!important;line-height:1.65!important;font-weight:700!important;margin:.6em 0 .3em!important}';
	}

	/** Već upisani izleti: na stranici izleta tekst preuzima font i veličinu stranice. */
	public static function front_css() {
		if ( ! is_singular( array( Plan_A_Prijave_Tour::CPT, 'ttbm_guide' ) ) ) {
			return;
		}
		$css = '#ttbm_content [style*="font-family"],#ttbm_content font{font-family:inherit!important}'
			. '#ttbm_content [style*="font-size"],#ttbm_content font[size]{font-size:inherit!important}'
			. '#ttbm_content [style*="line-height"]{line-height:inherit!important}'
			. '#ttbm_content .ttbm_wp_editor [style*="background"],#ttbm_content .ttbm_wp_editor [style*="color"]{background:transparent!important;color:inherit!important}'
			// Sav tekst izleta crn i iste veličine (opis, Važno za izlet, Važno, Zašto s nama); poveznice ostaju u boji poveznica.
			. self::black_css()
			// Popunjen izlet: jasna oznaka umjesto prijave.
			. '#ttbm_content .pap-popunjeno{display:inline-block;padding:6px 14px;border-radius:999px;background:#fde8e8;color:#b32d2e!important;font-weight:700;font-size:15px;white-space:nowrap}';
		wp_register_style( 'pap-text', false, array(), PLAN_A_PRIJAVE_VERSION );
		wp_enqueue_style( 'pap-text' );
		wp_add_inline_style( 'pap-text', $css );
	}
}
