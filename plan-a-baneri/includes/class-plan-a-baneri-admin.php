<?php
/**
 * Postavke → Plan A naslovnica.
 *
 * @package Plan_A_Baneri
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Baneri_Admin {

	const SLUG = 'plan-a-naslovnica';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_pab_save', array( __CLASS__, 'save' ) );
		add_filter( 'plugin_action_links_plan-a-baneri/plan-a-baneri.php', array( __CLASS__, 'links' ) );
	}

	public static function links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::SLUG ) ) . '">Postavke</a>' );
		return $links;
	}

	public static function menu() {
		$hook = add_options_page( 'Plan A naslovnica', 'Plan A naslovnica', 'manage_options', self::SLUG, array( __CLASS__, 'page' ) );
		add_action( 'admin_print_scripts-' . $hook, array( __CLASS__, 'media' ) );
	}

	public static function media() {
		wp_enqueue_media();
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Nemaš ovlasti.' );
		}
		check_admin_referer( 'pab_save' );
		$d   = Plan_A_Baneri::defaults();
		$in  = wp_unslash( $_POST );
		$out = array();
		foreach ( $d as $k => $v ) {
			if ( is_int( $v ) && in_array( $k, array( 'next_on', 'promo_on', 'tinder_on', 'cards_on' ), true ) ) {
				$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
			} elseif ( is_int( $v ) ) {
				$out[ $k ] = absint( $in[ $k ] ?? $v );
			} elseif ( in_array( $k, array( 'title', 'trust' ), true ) ) {
				$out[ $k ] = sanitize_textarea_field( (string) ( $in[ $k ] ?? '' ) );
			} elseif ( in_array( $k, array( 'btn1_url', 'btn2_url', 'promo_url', 'image_url' ), true ) ) {
				$url       = trim( (string) ( $in[ $k ] ?? '' ) );
				$out[ $k ] = 0 === strpos( $url, '/' ) ? '/' . ltrim( sanitize_text_field( $url ), '/' ) : esc_url_raw( $url );
			} else {
				$out[ $k ] = sanitize_text_field( (string) ( $in[ $k ] ?? '' ) );
			}
		}
		$out['promo_until'] = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $out['promo_until'] ) ? $out['promo_until'] : '';
		$out['next_count']  = max( 1, min( 6, $out['next_count'] ) );
		$out['cards_days']  = max( 7, min( 365, $out['cards_days'] ) );
		$out['cards_max']   = max( 2, min( 20, $out['cards_max'] ) );
		$out['top']         = min( 300, $out['top'] );
		$out['top_mob']     = min( 300, $out['top_mob'] );
		update_option( Plan_A_Baneri::OPT, $out, false );
		Plan_A_Baneri::flush();
		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::SLUG . '&saved=1' ) );
		exit;
	}

	public static function page() {
		$s    = Plan_A_Baneri::get();
		$text = static function ( string $k, string $label, string $help = '', string $cls = 'regular-text', string $type = 'text' ) use ( $s ) {
			echo '<tr><th scope="row"><label for="pab-' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td><input type="' . esc_attr( $type ) . '" id="pab-' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" class="' . esc_attr( $cls ) . '" value="' . esc_attr( (string) $s[ $k ] ) . '">'
				. ( '' !== $help ? '<p class="description">' . wp_kses( $help, array( 'code' => array(), 'strong' => array() ) ) . '</p>' : '' ) . '</td></tr>';
		};
		$area = static function ( string $k, string $label, string $help = '', int $rows = 3 ) use ( $s ) {
			echo '<tr><th scope="row"><label for="pab-' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td><textarea id="pab-' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" rows="' . (int) $rows . '" class="large-text">' . esc_textarea( (string) $s[ $k ] ) . '</textarea>'
				. ( '' !== $help ? '<p class="description">' . wp_kses( $help, array( 'code' => array() ) ) . '</p>' : '' ) . '</td></tr>';
		};
		$check = static function ( string $k, string $label, string $text ) use ( $s ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td><label><input type="checkbox" name="' . esc_attr( $k ) . '" value="1"' . checked( ! empty( $s[ $k ] ), true, false ) . '> ' . esc_html( $text ) . '</label></td></tr>';
		};
		$img = (int) $s['image'] ? (string) wp_get_attachment_image_url( (int) $s['image'], 'medium' ) : (string) $s['image_url'];
		?>
		<div class="wrap">
			<h1>Plan A naslovnica</h1>
			<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p>Spremljeno. Ako je uključen SpeedyCache, očisti predmemoriju da se promjena odmah vidi.</p></div>
			<?php endif; ?>
			<?php if ( ! Plan_A_Baneri::ready() ) : ?>
				<div class="notice notice-warning"><p>Dodatak <strong>Plan A izleti</strong> nije uključen – izleti (sljedeći polazak, kartice, Tinder) neće se prikazati.</p></div>
			<?php endif; ?>
			<p>Na naslovnici umjesto slidera stavi kratki kod <code>[plan-a-naslovnica]</code> (u Flatsomeu: element <em>Shortcode</em> ili HTML blok s tim kodom, u redu pune širine na vrhu stranice). Izleti, datumi, cijene, slobodna mjesta i brojač Tinder izleta pune se sami.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="pab_save">
				<?php wp_nonce_field( 'pab_save' ); ?>
				<h2>1. Velika slika, poruka i gumbi</h2>
				<table class="form-table" role="presentation">
					<tr><th scope="row">Velika slika</th><td>
						<input type="hidden" name="image" id="pab-image" value="<?php echo (int) $s['image']; ?>">
						<input type="hidden" name="image_url" id="pab-image_url" value="<?php echo esc_attr( (string) $s['image_url'] ); ?>">
						<div id="pab-prev" style="margin-bottom:8px"><?php echo $img ? '<img src="' . esc_url( $img ) . '" style="max-width:320px;height:auto;border-radius:6px">' : '<em>Nije odabrana – koristi se slika najbližeg izleta.</em>'; ?></div>
						<button type="button" class="button" id="pab-pick">Odaberi sliku</button> <button type="button" class="button-link" id="pab-clear">Makni</button>
						<p class="description">Široka fotografija (najbolje 2400×1350 JPG, do ~400 KB). Lijeva strana se zatamni zbog teksta.</p>
					</td></tr>
					<?php
					$text( 'kicker', 'Natpis iznad naslova', '<code>{broj}</code> = broj izleta u razdoblju kartica, <code>{dana}</code> = to razdoblje. Prazno = bez natpisa.', 'large-text' );
					$area( 'title', 'Naslov', 'Novi red = prijelom naslova.', 2 );
					$area( 'sub', 'Podnaslov', '', 2 );
					$text( 'btn1', 'Glavni gumb (narančasti)' );
					$text( 'btn1_url', 'Poveznica glavnog gumba', 'npr. <code>/izleti/</code>' );
					$text( 'btn2', 'Drugi gumb (prozirni)', 'Prazno = bez drugog gumba.' );
					$text( 'btn2_url', 'Poveznica drugog gumba' );
					$area( 'trust', 'Kvačice ispod gumba', 'Jedan red = jedna kvačica.', 3 );
					$text( 'top', 'Razmak od vrha – računalo (px)', 'Za prozirno zaglavlje teme preko slike (zadano 130).', 'small-text', 'number' );
					$text( 'top_mob', 'Razmak od vrha – mobitel (px)', 'Zadano 92.', 'small-text', 'number' );
					?>
				</table>
				<h2>2. Sljedeći polazak</h2>
				<table class="form-table" role="presentation">
					<?php
					$check( 'next_on', 'Prikaži', 'Kartica s odbrojavanjem do polaska, slobodnim mjestima i gumbom Prijavi se' );
					$text( 'next_count', 'Broj izleta za prelistavanje', 'Najbliži izleti koji nisu popunjeni (1–6).', 'small-text', 'number' );
					?>
				</table>
				<h2>3. Traka akcije (npr. članarina)</h2>
				<table class="form-table" role="presentation">
					<?php
					$check( 'promo_on', 'Prikaži', 'Tamnoplava traka na dnu slike' );
					$text( 'promo_title', 'Naslov', '<code>{iznos}</code> = članarina za ovu godinu iz Plan A članstva (sada ' . esc_html( Plan_A_Baneri::fee() ?: '–' ) . '), <code>{do}</code> = datum do kada vrijedi.', 'large-text' );
					$text( 'promo_sub', 'Podnaslov', '<code>{dana}</code> = još koliko dana (mijenja se samo).', 'large-text' );
					$text( 'promo_btn', 'Gumb' );
					$text( 'promo_url', 'Poveznica', 'Prazno = stranica pristupnice.' );
					$text( 'promo_until', 'Vrijedi do (uključivo)', 'Nakon tog dana traka sama nestaje. Prazno = bez roka.', 'regular-text', 'date' );
					$text( 'promo_icon', 'Ikona (emoji)', 'Prazno = zvjezdica.', 'small-text' );
					?>
				</table>
				<h2>4. Tinder izlet</h2>
				<table class="form-table" role="presentation">
					<?php
					$check( 'tinder_on', 'Prikaži', 'Ružičasta traka najbližeg izleta uključenog u Izleti → Tinder izleti (brojač ♂/♀ s poticajem); nestaje kad izlet prođe ili se popuni' );
					$text( 'tinder_btn', 'Gumb' );
					?>
				</table>
				<h2>5. Kartice sljedećih izleta</h2>
				<table class="form-table" role="presentation">
					<?php
					$check( 'cards_on', 'Prikaži', 'Kartice ispod slike (na mobitelu se listaju prstom)' );
					$text( 'cards_title', 'Naslov' );
					$text( 'cards_sub', 'Podnaslov', '', 'large-text' );
					$text( 'cards_days', 'Razdoblje (dana unaprijed)', 'Izleti do toliko dana od danas; ako ih je manje od 4, dodaju se sljedeći.', 'small-text', 'number' );
					$text( 'cards_max', 'Najviše kartica', '', 'small-text', 'number' );
					$text( 'cards_all', 'Poveznica „Svi izleti”', 'Vodi na poveznicu glavnog gumba.' );
					?>
				</table>
				<?php submit_button( 'Spremi' ); ?>
			</form>
		</div>
		<script>
		(function ($) {
			var frame;
			$('#pab-pick').on('click', function (e) {
				e.preventDefault();
				if (!frame) {
					frame = wp.media({ title: 'Velika slika naslovnice', library: { type: 'image' }, multiple: false });
					frame.on('select', function () {
						var a = frame.state().get('selection').first().toJSON();
						$('#pab-image').val(a.id); $('#pab-image_url').val('');
						$('#pab-prev').html('<img src="' + (a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url) + '" style="max-width:320px;height:auto;border-radius:6px">');
					});
				}
				frame.open();
			});
			$('#pab-clear').on('click', function () { $('#pab-image').val(0); $('#pab-image_url').val(''); $('#pab-prev').html('<em>Nije odabrana – koristi se slika najbližeg izleta.</em>'); });
		})(jQuery);
		</script>
		<?php
	}
}
