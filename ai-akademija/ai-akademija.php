<?php
/**
 * Plugin Name: AI Akademija
 * Description: Naslovnica (kratki kod [ai-akademija-naslovnica]), font, boje i gumbi koji se preljevaju iz boje u boju za cijelu stranicu.
 * Version: 0.2.0
 * Author: AI Akademija
 * Text Domain: ai-akademija
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'AKA_VERSION', '0.2.0' );
define( 'AKA_FILE', __FILE__ );
define( 'AKA_DIR', plugin_dir_path( __FILE__ ) );
define( 'AKA_URL', plugin_dir_url( __FILE__ ) );

final class AI_Akademija {
	const SC = 'ai-akademija-naslovnica';

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 20 );
		add_shortcode( self::SC, array( __CLASS__, 'naslovnica' ) );
	}

	private static function ver( $file ) {
		$path = AKA_DIR . $file;
		return AKA_VERSION . ( file_exists( $path ) ? '.' . filemtime( $path ) : '' );
	}

	private static function uses_shortcode() {
		if ( ! is_singular() ) {
			return false;
		}
		$post = get_post();
		return $post && has_shortcode( $post->post_content, self::SC );
	}

	public static function assets() {
		wp_enqueue_style( 'aka-font', 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap', array(), null );
		wp_enqueue_style( 'aka-global', AKA_URL . 'assets/ai-akademija.css', array( 'aka-font' ), self::ver( 'assets/ai-akademija.css' ) );
		wp_register_style( 'aka-naslovnica', AKA_URL . 'assets/naslovnica.css', array( 'aka-global' ), self::ver( 'assets/naslovnica.css' ) );
		wp_register_script( 'aka-naslovnica', AKA_URL . 'assets/naslovnica.js', array(), self::ver( 'assets/naslovnica.js' ), true );
		if ( self::uses_shortcode() ) {
			wp_enqueue_style( 'aka-naslovnica' );
		}
	}

	/**
	 * [ai-akademija-naslovnica foto="URL fotografije (nije obavezno)"]
	 * Izlaz kratkog koda WordPress ne prolazi kroz wpautop, pa mu ne ubacuje <p> i <br>.
	 */
	public static function naslovnica( $atts ) {
		$atts = shortcode_atts( array( 'foto' => '' ), $atts, self::SC );
		wp_enqueue_style( 'aka-naslovnica' );
		wp_enqueue_script( 'aka-naslovnica' );
		$logo = AKA_URL . 'assets/slike/ai-akademija-znak-480.webp';
		$foto = $atts['foto'];
		ob_start();
		include AKA_DIR . 'templates/naslovnica.php';
		// jedan redak: i kad bi netko (tema, dodatak) ponovno pustio wpautop, nema gdje ubaciti <p>/<br>
		return preg_replace( '/>\s+</', '><', preg_replace( '/\s*\n\s*/', ' ', trim( ob_get_clean() ) ) );
	}
}

AI_Akademija::init();
