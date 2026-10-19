<?php
/**
 * Plugin Name: AI Akademija
 * Description: Stranice AI Akademije kao kratki kodovi (naslovnica, programi, tečaj, mentori, za tvrtke, o nama, kontakt, cijene, pitanja, uvjeti, podnožje), font, boje i gumbi koji se preljevaju iz boje u boju.
 * Version: 0.4.0
 * Author: AI Akademija
 * Text Domain: ai-akademija
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'AKA_VERSION', '0.4.0' );
define( 'AKA_FILE', __FILE__ );
define( 'AKA_DIR', plugin_dir_path( __FILE__ ) );
define( 'AKA_URL', plugin_dir_url( __FILE__ ) );

require_once AKA_DIR . 'includes/podaci.php';
require_once AKA_DIR . 'includes/ui.php';
require_once AKA_DIR . 'includes/linkovi.php';
require_once AKA_DIR . 'includes/admin.php';

final class AI_Akademija {

	/** kratki kod => predložak u templates/ */
	const STRANICE = array(
		'ai-akademija-naslovnica' => 'naslovnica',
		'ai-akademija-programi'   => 'programi',
		'ai-akademija-tecaj'      => 'tecaj',
		'ai-akademija-mentori'    => 'mentori',
		'ai-akademija-tvrtke'     => 'tvrtke',
		'ai-akademija-o-nama'     => 'o-nama',
		'ai-akademija-kontakt'    => 'kontakt',
		'ai-akademija-cijene'     => 'cijene',
		'ai-akademija-pitanja'    => 'pitanja',
		'ai-akademija-uvjeti'     => 'uvjeti',
		'ai-akademija-podnozje'   => 'podnozje',
	);

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 20 );
		foreach ( self::STRANICE as $sc => $tpl ) {
			add_shortcode(
				$sc,
				function ( $atts ) use ( $sc, $tpl ) {
					return AI_Akademija::render( $sc, $tpl, (array) $atts );
				}
			);
		}
		add_action( 'admin_post_nopriv_aka_kontakt', array( __CLASS__, 'posalji' ) );
		add_action( 'admin_post_aka_kontakt', array( __CLASS__, 'posalji' ) );
		AKA_Linkovi::init();
		if ( is_admin() ) {
			AKA_Admin::init();
		}
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
		if ( ! $post ) {
			return false;
		}
		foreach ( array_keys( self::STRANICE ) as $sc ) {
			if ( has_shortcode( $post->post_content, $sc ) ) {
				return true;
			}
		}
		return false;
	}

	public static function assets() {
		wp_enqueue_style( 'aka-font', 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap', array(), null );
		wp_enqueue_style( 'aka-global', AKA_URL . 'assets/ai-akademija.css', array( 'aka-font' ), self::ver( 'assets/ai-akademija.css' ) );
		wp_register_style( 'aka-naslovnica', AKA_URL . 'assets/naslovnica.css', array( 'aka-global' ), self::ver( 'assets/naslovnica.css' ) );
		wp_register_style( 'aka-stranice', AKA_URL . 'assets/stranice.css', array( 'aka-naslovnica' ), self::ver( 'assets/stranice.css' ) );
		wp_register_script( 'aka-naslovnica', AKA_URL . 'assets/naslovnica.js', array(), self::ver( 'assets/naslovnica.js' ), true );
		if ( self::uses_shortcode() ) {
			wp_enqueue_style( 'aka-stranice' );
		}
	}

	/**
	 * Izlaz kratkog koda WordPress ne prolazi kroz wpautop, pa mu ne ubacuje <p> i <br>.
	 * [ai-akademija-naslovnica foto="URL"] · [ai-akademija-tecaj slug="uvod-u-ai"] (bez sluga: prema adresi stranice)
	 */
	public static function render( $sc, $tpl, $atts ) {
		wp_enqueue_style( 'aka-stranice' );
		wp_enqueue_script( 'aka-naslovnica' );
		$atts = shortcode_atts( array( 'foto' => '', 'slug' => '' ), $atts, $sc );
		$logo = AKA_URL . 'assets/slike/ai-akademija-znak-480.webp';
		$foto = $atts['foto'];
		if ( 'tecaj' === $tpl ) {
			$slug = $atts['slug'] ? $atts['slug'] : get_post_field( 'post_name', get_the_ID() );
			$c    = AKA_Podaci::tecaj( sanitize_title( $slug ) );
			if ( ! $c ) {
				return current_user_can( 'edit_posts' ) ? '<p><b>AI Akademija:</b> nema tečaja „' . esc_html( $slug ) . '”. Upiši slug="…" (' . esc_html( implode( ', ', array_keys( AKA_Podaci::tecajevi() ) ) ) . ').</p>' : '';
			}
		}
		ob_start();
		include AKA_DIR . 'templates/' . $tpl . '.php';
		// jedan redak: i kad bi netko (tema, dodatak) ponovno pustio wpautop, nema gdje ubaciti <p>/<br>
		return preg_replace( '/>\s+</', '><', preg_replace( '/\s*\n\s*/', ' ', trim( ob_get_clean() ) ) );
	}

	/** Obrazac za kontakt / upit tvrtke. */
	public static function obrazac( $o ) {
		$o      = wp_parse_args( $o, array( 'predmet' => '', 'tvrtka' => false ) );
		$status = isset( $_GET['aka'] ) ? sanitize_key( $_GET['aka'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		// phpcs:disable WordPress.Security.NonceVerification
		$tecaj = isset( $_GET['tecaj'] ) ? AKA_Podaci::tecaj( sanitize_title( wp_unslash( $_GET['tecaj'] ) ) ) : null;
		$paket = isset( $_GET['paket'] ) ? sanitize_title( wp_unslash( $_GET['paket'] ) ) : '';
		$tema  = isset( $_GET['predmet'] ) ? sanitize_key( $_GET['predmet'] ) : '';
		// phpcs:enable
		$poruka  = '';
		$predmet = $o['predmet'];
		if ( $tecaj ) {
			$poruka  = 'Zanima me tečaj „' . $tecaj['naslov'] . '”.';
			$predmet = $predmet ? $predmet : 'Upis: ' . $tecaj['naslov'];
		} elseif ( $paket ) {
			foreach ( AKA_Podaci::paketi() as $pk ) {
				if ( sanitize_title( $pk['naziv'] ) === $paket ) {
					$poruka  = 'Zanima me paket „' . $pk['naziv'] . '” (' . $pk['cijena'] . ' ' . $pk['nap'] . ').';
					$predmet = $predmet ? $predmet : 'Paket: ' . $pk['naziv'];
				}
			}
		} elseif ( 'mentor' === $tema ) {
			$poruka  = 'Želio/željela bih predavati u AI Akademiji. Moje iskustvo: ';
			$predmet = $predmet ? $predmet : 'Prijava za mentora';
		}
		ob_start();
		?>
		<form class="aka-form" id="aka-obrazac" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php if ( 'ok' === $status ) : ?><div class="aka-alert is-ok">Hvala! Poruka je poslana – javit ćemo se uskoro.</div><?php elseif ( 'greska' === $status ) : ?><div class="aka-alert is-err">Poruka nije poslana. Provjeri polja i pokušaj ponovno.</div><?php endif; ?>
			<input type="hidden" name="action" value="aka_kontakt">
			<input type="hidden" name="predmet" value="<?php echo esc_attr( $predmet ); ?>">
			<input type="hidden" name="t" value="<?php echo esc_attr( time() ); ?>">
			<?php wp_nonce_field( 'aka_kontakt', 'aka_n' ); ?>
			<div class="aka-hp" aria-hidden="true"><label>Web <input type="text" name="web" tabindex="-1" autocomplete="off"></label></div>
			<div class="aka-row">
				<label>Ime i prezime<input type="text" name="ime" required maxlength="120" autocomplete="name"></label>
				<label>E-mail<input type="email" name="email" required maxlength="160" autocomplete="email"></label>
			</div>
			<?php if ( $o['tvrtka'] ) : ?>
			<div class="aka-row">
				<label>Tvrtka<input type="text" name="tvrtka" required maxlength="160" autocomplete="organization"></label>
				<label>Broj polaznika<select name="broj"><option>5 – 10</option><option>11 – 30</option><option>31 – 100</option><option>više od 100</option></select></label>
			</div>
			<?php endif; ?>
			<label>Poruka<textarea name="poruka" rows="5" required maxlength="4000"><?php echo esc_textarea( $poruka ); ?></textarea></label>
			<label class="aka-agree"><input type="checkbox" name="privola" value="1" required> Slažem se da se moji podaci koriste za odgovor na upit.</label>
			<button type="submit" class="aka-btn aka-btn-grad">Pošalji poruku<?php echo AKA_Podaci::ikona( 'strelica' ); // phpcs:ignore ?></button>
		</form>
		<?php
		return ob_get_clean();
	}

	public static function posalji() {
		$back = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		$back = remove_query_arg( 'aka', $back );
		$p    = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification
		$ok   = isset( $p['aka_n'] ) && wp_verify_nonce( $p['aka_n'], 'aka_kontakt' );
		$ime  = sanitize_text_field( isset( $p['ime'] ) ? $p['ime'] : '' );
		$mail = sanitize_email( isset( $p['email'] ) ? $p['email'] : '' );
		$txt  = sanitize_textarea_field( isset( $p['poruka'] ) ? $p['poruka'] : '' );
		$bot  = ! empty( $p['web'] ) || ( time() - (int) ( isset( $p['t'] ) ? $p['t'] : 0 ) ) < 3;
		if ( $bot ) {
			wp_safe_redirect( add_query_arg( 'aka', 'ok', $back ) . '#aka-obrazac' ); // botu kažemo „ok”, ali ništa ne šaljemo
			exit;
		}
		if ( ! $ok || ! $ime || ! is_email( $mail ) || ! $txt || empty( $p['privola'] ) ) {
			wp_safe_redirect( add_query_arg( 'aka', 'greska', $back ) . '#aka-obrazac' );
			exit;
		}
		$predmet = sanitize_text_field( isset( $p['predmet'] ) && $p['predmet'] ? $p['predmet'] : 'Poruka s weba' );
		$body    = "Ime: $ime\nE-mail: $mail\n";
		if ( ! empty( $p['tvrtka'] ) ) {
			$body .= 'Tvrtka: ' . sanitize_text_field( $p['tvrtka'] ) . "\nBroj polaznika: " . sanitize_text_field( isset( $p['broj'] ) ? $p['broj'] : '' ) . "\n";
		}
		$body .= "\n$txt\n";
		$sent  = wp_mail( get_option( 'admin_email' ), '[AI Akademija] ' . $predmet, $body, array( 'Reply-To: ' . $ime . ' <' . $mail . '>' ) );
		wp_safe_redirect( add_query_arg( 'aka', $sent ? 'ok' : 'greska', $back ) . '#aka-obrazac' );
		exit;
	}
}

AI_Akademija::init();
