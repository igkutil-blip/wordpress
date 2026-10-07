<?php
/**
 * Postavke dodatka (Postavke → Plan A izleti): logotip i boja za sliku kartice
 * izleta koja se dijeli gumbom "Predloži ekipi".
 *
 * @package Plan_A_Izleti
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Izleti_Settings {

	const OPTION = 'plan_a_izleti_settings';
	const PAGE   = 'plan-a-izleti';
	const FALLBACK_COLOR = '#1e3a5f';

	public static function init() {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_plan_a_izleti_cards', array( __CLASS__, 'regenerate' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PLAN_A_IZLETI_FILE ), array( __CLASS__, 'action_link' ) );
	}

	/**
	 * Spremljene postavke; logotip bez odabira = logotip teme (Flatsome ili WordPress).
	 *
	 * @return array{logo_id: int, color: string}
	 */
	public static function get(): array {
		$saved = (array) get_option( self::OPTION, array() );
		$logo  = absint( $saved['logo_id'] ?? 0 );
		if ( ! $logo ) {
			$logo = self::theme_logo_id();
		}
		return array(
			'logo_id' => $logo,
			'color'   => (string) ( sanitize_hex_color( (string) ( $saved['color'] ?? '' ) ) ?: '' ),
			'preview' => self::preview_mode( (string) ( $saved['preview'] ?? '' ) ),
		);
	}

	/**
	 * "Pregled prije slanja": first3 (prva 3 puta na uređaju, zadano), always, never.
	 */
	public static function preview_mode( string $value ): string {
		return in_array( $value, array( 'first3', 'always', 'never' ), true ) ? $value : 'first3';
	}

	private static function theme_logo_id(): int {
		$flatsome = get_theme_mod( 'site_logo', '' );
		if ( is_numeric( $flatsome ) && (int) $flatsome > 0 ) {
			return (int) $flatsome;
		}
		if ( is_string( $flatsome ) && '' !== $flatsome ) {
			$id = attachment_url_to_postid( $flatsome );
			if ( $id ) {
				return (int) $id;
			}
		}
		return (int) get_theme_mod( 'custom_logo', 0 );
	}

	/**
	 * Boja donjeg dijela slike: iz postavki, inače prva tamna boja zaglavlja teme
	 * (Flatsome: header_bg, nav_position_bg, color_primary), inače tamnoplava.
	 */
	public static function card_color(): string {
		$saved = self::get()['color'];
		if ( '' !== $saved ) {
			return $saved;
		}
		return self::default_color();
	}

	public static function default_color(): string {
		foreach ( array( 'header_bg', 'nav_position_bg', 'color_primary' ) as $mod ) {
			$color = sanitize_hex_color( (string) get_theme_mod( $mod, '' ) );
			if ( $color && self::is_dark( $color ) ) {
				return $color;
			}
		}
		return self::FALLBACK_COLOR;
	}

	private static function is_dark( string $hex ): bool {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );
		return ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) < 110; // bijeli tekst mora biti čitljiv
	}

	public static function menu() {
		add_options_page( __( 'Plan A izleti', 'plan-a-izleti' ), __( 'Plan A izleti', 'plan-a-izleti' ), 'manage_options', self::PAGE, array( __CLASS__, 'render' ) );
	}

	public static function action_link( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Postavke', 'plan-a-izleti' ) . '</a>' );
		return $links;
	}

	public static function register() {
		register_setting(
			'plan_a_izleti',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$logo  = absint( $input['logo_id'] ?? 0 );
		if ( $logo && ! wp_attachment_is_image( $logo ) ) {
			$logo = 0;
		}
		$color = trim( (string) ( $input['color'] ?? '' ) );
		$color = '' !== $color ? '#' . ltrim( $color, '#' ) : '';
		return array(
			'logo_id' => $logo,
			'color'   => (string) ( sanitize_hex_color( $color ) ?: '' ),
			'preview' => self::preview_mode( (string) ( $input['preview'] ?? '' ) ),
		);
	}

	public static function assets( $hook ) {
		if ( 'settings_page_' . self::PAGE !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_add_inline_script(
			'media-editor',
			"jQuery(function($){var f;$('#paiz-logo-pick').on('click',function(e){e.preventDefault();if(!f){f=wp.media({title:" . wp_json_encode( __( 'Odaberi logotip', 'plan-a-izleti' ) ) . ",library:{type:'image'},multiple:false});f.on('select',function(){var a=f.state().get('selection').first().toJSON();$('#paiz-logo-id').val(a.id);$('#paiz-logo-preview').attr('src',(a.sizes&&a.sizes.medium?a.sizes.medium.url:a.url)).show();});}f.open();});$('#paiz-logo-clear').on('click',function(e){e.preventDefault();$('#paiz-logo-id').val('');$('#paiz-logo-preview').hide();});});"
		);
	}

	/**
	 * "Ponovno izradi slike": briše sve slike kartica; izrađuju se ponovno pri sljedećem prikazu.
	 */
	public static function regenerate() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nemate ovlasti.', 'plan-a-izleti' ) );
		}
		check_admin_referer( 'plan_a_izleti_cards' );
		Plan_A_Izleti_Card::delete_all();
		Plan_A_Izleti_Card::schedule_all();
		wp_safe_redirect( add_query_arg( 'paiz-cards', 'obrisano', admin_url( 'options-general.php?page=' . self::PAGE ) ) );
		exit;
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$saved   = (array) get_option( self::OPTION, array() );
		$logo_id = absint( $saved['logo_id'] ?? 0 );
		$current = self::get();
		$color   = (string) ( $saved['color'] ?? '' );
		$preview = self::preview_url();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Plan A izleti', 'plan-a-izleti' ); ?></h1>
			<?php if ( isset( $_GET['paiz-cards'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- samo poruka. ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Slike kartica su obrisane i ponovno se izrađuju u pozadini (za sve objavljene izlete).', 'plan-a-izleti' ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Slika kartice za „Predloži ekipi”', 'plan-a-izleti' ); ?></h2>
			<p><?php esc_html_e( 'Gumb „Predloži ekipi” uz poruku dijeli sliku kartice izleta (1080 × 1350 px) s nazivom, datumom, mjestom, trajanjem, cijenom, ikonama aktivnosti i logotipom.', 'plan-a-izleti' ); ?></p>
			<?php if ( ! Plan_A_Izleti_Card::supported() ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Na poslužitelju nije dostupna PHP knjižnica GD s FreeTypeom, pa se slika kartice ne može izraditi. Dijeli se istaknuta slika izleta.', 'plan-a-izleti' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'plan_a_izleti' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Logotip', 'plan-a-izleti' ); ?></th>
						<td>
							<input type="hidden" id="paiz-logo-id" name="<?php echo esc_attr( self::OPTION ); ?>[logo_id]" value="<?php echo esc_attr( $logo_id ? (string) $logo_id : '' ); ?>">
							<?php $src = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : ''; ?>
							<img id="paiz-logo-preview" src="<?php echo esc_url( (string) $src ); ?>" alt="" style="max-width:240px;max-height:90px;display:<?php echo $src ? 'block' : 'none'; ?>;margin-bottom:8px;background:#fff;padding:6px;border:1px solid #ddd;">
							<button type="button" class="button" id="paiz-logo-pick"><?php esc_html_e( 'Odaberi logotip', 'plan-a-izleti' ); ?></button>
							<button type="button" class="button-link" id="paiz-logo-clear"><?php esc_html_e( 'Ukloni', 'plan-a-izleti' ); ?></button>
							<p class="description">
								<?php esc_html_e( 'Prikazuje se na dnu slike, na bijeloj podlozi. Bez odabira koristi se logotip teme.', 'plan-a-izleti' ); ?>
								<?php if ( ! $logo_id && $current['logo_id'] ) : ?>
									<?php esc_html_e( '(Trenutno: logotip teme.)', 'plan-a-izleti' ); ?>
								<?php endif; ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="paiz-color"><?php esc_html_e( 'Boja donjeg dijela', 'plan-a-izleti' ); ?></label></th>
						<td>
							<input type="text" id="paiz-color" name="<?php echo esc_attr( self::OPTION ); ?>[color]" value="<?php echo esc_attr( $color ); ?>" placeholder="<?php echo esc_attr( self::default_color() ); ?>" pattern="#?[0-9a-fA-F]{3,6}" class="regular-text" style="max-width:120px;">
							<span style="display:inline-block;width:28px;height:28px;vertical-align:middle;border-radius:4px;background:<?php echo esc_attr( self::card_color() ); ?>;"></span>
							<p class="description"><?php echo esc_html( sprintf( /* translators: %s: boja */ __( 'Prazno = boja zaglavlja teme (sada %s). Tekst je bijel, pa boja mora biti tamna.', 'plan-a-izleti' ), self::default_color() ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Pregled prije slanja', 'plan-a-izleti' ); ?></th>
						<td>
							<fieldset>
								<?php
								$paiz_preview = self::preview_mode( (string) ( $saved['preview'] ?? '' ) );
								foreach ( array(
									'first3' => __( 'prva 3 puta na uređaju (zadano)', 'plan-a-izleti' ),
									'always' => __( 'uvijek', 'plan-a-izleti' ),
									'never'  => __( 'nikad', 'plan-a-izleti' ),
								) as $paiz_value => $paiz_label ) :
									?>
									<label style="display:block;margin:0 0 6px;"><input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>[preview]" value="<?php echo esc_attr( $paiz_value ); ?>" <?php checked( $paiz_preview, $paiz_value ); ?>> <?php echo esc_html( $paiz_label ); ?></label>
								<?php endforeach; ?>
							</fieldset>
							<p class="description"><?php esc_html_e( 'Klik na „Predloži ekipi” najprije prikaže sliku i tekst poruke, a slanje se pokreće gumbom „Pošalji u WhatsApp”.', 'plan-a-izleti' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Spremi promjene', 'plan-a-izleti' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Pregled', 'plan-a-izleti' ); ?></h2>
			<?php if ( $preview ) : ?>
				<p><img src="<?php echo esc_url( $preview ); ?>" alt="" style="width:324px;height:auto;border:1px solid #ddd;"></p>
			<?php else : ?>
				<p><?php esc_html_e( 'Pregled će se prikazati kad se izradi prva slika kartice (otvorite bilo koji izlet).', 'plan-a-izleti' ); ?></p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="plan_a_izleti_cards">
				<?php wp_nonce_field( 'plan_a_izleti_cards' ); ?>
				<?php submit_button( __( 'Ponovno izradi sve slike kartica', 'plan-a-izleti' ), 'secondary', 'submit', false ); ?>
			</form>
			<p class="description"><?php esc_html_e( 'Slike se spremaju u wp-content/uploads/plan-a-izleti/kartice/ (izlet-ID-v3.jpg) i same se ponovno izrađuju kad se promijeni naziv, datum, cijena, slika ili aktivnosti izleta.', 'plan-a-izleti' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Pregled: slika kartice prvog nadolazećeg izleta (izradi je ako treba).
	 */
	private static function preview_url(): string {
		if ( ! Plan_A_Izleti_Card::supported() || ! Plan_A_Izleti_Data::is_source_available() ) {
			return '';
		}
		$tours = Plan_A_Izleti_Data::get_tours();
		if ( ! $tours ) {
			return '';
		}
		$id  = (int) $tours[0]['id'];
		$url = Plan_A_Izleti_Card::url( $id );
		if ( '' === $url && Plan_A_Izleti_Card::generate( $id ) ) {
			$url = Plan_A_Izleti_Card::url( $id );
		}
		return $url;
	}
}
