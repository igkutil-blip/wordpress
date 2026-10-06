<?php
/**
 * Stranica postavki: Postavke > Plan A aplikacija.
 *
 * @package Plan_A_Aplikacija
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_App_Admin {

	const PAGE = 'plan-a-aplikacija';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PLAN_A_APP_FILE ), array( __CLASS__, 'action_link' ) );
	}

	public static function menu() {
		add_options_page( __( 'Plan A aplikacija', 'plan-a-aplikacija' ), __( 'Plan A aplikacija', 'plan-a-aplikacija' ), 'manage_options', self::PAGE, array( __CLASS__, 'render' ) );
	}

	public static function action_link( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Postavke', 'plan-a-aplikacija' ) . '</a>' );
		return $links;
	}

	public static function register() {
		register_setting(
			'plan_a_app',
			Plan_A_App_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'Plan_A_App_Settings', 'sanitize' ),
				'default'           => Plan_A_App_Settings::defaults(),
			)
		);
	}

	public static function assets( $hook ) {
		if ( 'settings_page_' . self::PAGE !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'plan-a-aplikacija-admin', PLAN_A_APP_URL . 'assets/admin.js', array( 'jquery', 'wp-color-picker' ), PLAN_A_APP_VERSION, true );
		wp_localize_script(
			'plan-a-aplikacija-admin',
			'planAAppAdmin',
			array(
				'title'  => __( 'Odaberite logotip', 'plan-a-aplikacija' ),
				'button' => __( 'Koristi ovaj logotip', 'plan-a-aplikacija' ),
			)
		);
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s     = Plan_A_App_Settings::get();
		$name  = Plan_A_App_Settings::OPTION;
		$icons = Plan_A_App_Icons::get();
		$logo  = $s['logo_id'] ? wp_get_attachment_image_url( (int) $s['logo_id'], 'medium' ) : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Plan A aplikacija', 'plan-a-aplikacija' ); ?></h1>
			<p><?php esc_html_e( 'Pretvara stranicu u web aplikaciju koju posjetitelji mogu instalirati na mobitel. Košarica, plaćanje i korisnički račun nikad se ne spremaju u predmemoriju.', 'plan-a-aplikacija' ); ?></p>

			<?php self::status_notices( $icons ); ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'plan_a_app' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Aplikacija', 'plan-a-aplikacija' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( $s['enabled'] ); ?>> <?php esc_html_e( 'Uključena', 'plan-a-aplikacija' ); ?></label>
							<p class="description"><?php esc_html_e( 'Kad je isključena, service worker se kod posjetitelja sam uklanja i briše predmemoriju.', 'plan-a-aplikacija' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Logotip (ikona aplikacije)', 'plan-a-aplikacija' ); ?></th>
						<td>
							<input type="hidden" id="plan-a-logo-id" name="<?php echo esc_attr( $name ); ?>[logo_id]" value="<?php echo esc_attr( (string) $s['logo_id'] ); ?>">
							<div id="plan-a-logo-preview" style="margin-bottom:8px;">
								<?php if ( $logo ) : ?>
									<img src="<?php echo esc_url( $logo ); ?>" alt="" style="max-width:160px;max-height:160px;background:#f0f0f1;padding:8px;">
								<?php endif; ?>
							</div>
							<button type="button" class="button" id="plan-a-logo-select"><?php esc_html_e( 'Odaberi logotip', 'plan-a-aplikacija' ); ?></button>
							<button type="button" class="button-link-delete" id="plan-a-logo-remove" <?php echo $logo ? '' : 'hidden'; ?>><?php esc_html_e( 'Ukloni', 'plan-a-aplikacija' ); ?></button>
							<p class="description"><?php esc_html_e( 'Kvadratna PNG slika, najmanje 512 × 512 px. Iz nje se generiraju ikone 192 px, 512 px, maskable 512 px i ikona za iPhone (180 px).', 'plan-a-aplikacija' ); ?></p>
							<?php if ( $icons['icons'] ) : ?>
								<p style="display:flex;gap:12px;align-items:flex-end;margin-top:12px;">
									<?php foreach ( array( '192' => 64, 'maskable-512' => 96, 'apple-180' => 64 ) as $key => $px ) : ?>
										<?php if ( ! empty( $icons['icons'][ $key ] ) ) : ?>
											<span style="text-align:center;font-size:11px;">
												<img src="<?php echo esc_url( $icons['icons'][ $key ] ); ?>" alt="" width="<?php echo esc_attr( (string) $px ); ?>" height="<?php echo esc_attr( (string) $px ); ?>" style="display:block;border:1px solid #ddd;<?php echo 'maskable-512' === $key ? 'border-radius:50%;' : 'border-radius:12px;'; ?>"><?php echo esc_html( $key ); ?>
											</span>
										<?php endif; ?>
									<?php endforeach; ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="plan-a-color"><?php esc_html_e( 'Boja aplikacije', 'plan-a-aplikacija' ); ?></label></th>
						<td>
							<input type="text" id="plan-a-color" class="plan-a-color" name="<?php echo esc_attr( $name ); ?>[theme_color]" value="<?php echo esc_attr( $s['theme_color'] ); ?>" data-default-color="<?php echo esc_attr( Plan_A_App_Settings::default_theme_color() ); ?>">
							<p class="description"><?php esc_html_e( 'Boja trake preglednika i aplikacije. Zadano: boja zaglavlja teme Flatsome.', 'plan-a-aplikacija' ); ?></p>
						</td>
					</tr>
					<?php
					$fields = array(
						'start_url'   => __( 'Početna adresa aplikacije', 'plan-a-aplikacija' ),
						'nav_izleti'  => __( 'Navigacija: Izleti', 'plan-a-aplikacija' ),
						'nav_plan'    => __( 'Navigacija: Plan izleta', 'plan-a-aplikacija' ),
						'nav_cart'    => __( 'Navigacija: Košarica', 'plan-a-aplikacija' ),
						'nav_contact' => __( 'Navigacija: Kontakt', 'plan-a-aplikacija' ),
					);
					foreach ( $fields as $key => $label ) :
						?>
						<tr>
							<th scope="row"><label for="plan-a-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
							<td><input type="text" class="regular-text code" id="plan-a-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $name . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>" placeholder="<?php echo esc_attr( Plan_A_App_Settings::defaults()[ $key ] ); ?>"></td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Poziv na instalaciju', 'plan-a-aplikacija' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[install_prompt]" value="1" <?php checked( $s['install_prompt'] ); ?>> <?php esc_html_e( 'Prikaži traku „Instaliraj aplikaciju Plan A” na mobitelu', 'plan-a-aplikacija' ); ?></label>
							<fieldset style="margin:10px 0 0 24px;">
								<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[prompt_every]" value="1" <?php checked( (int) $s['prompt_every'], 1 ); ?>> <?php esc_html_e( 'Pri svakoj posjeti („Ne sada” skriva traku do sljedeće posjete)', 'plan-a-aplikacija' ); ?></label><br>
								<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[prompt_every]" value="0" <?php checked( (int) $s['prompt_every'], 0 ); ?>> <?php esc_html_e( 'Od druge posjete („Ne sada” skriva traku na 30 dana)', 'plan-a-aplikacija' ); ?></label>
							</fieldset>
							<p class="description"><?php esc_html_e( 'Traka se ne prikazuje u već instaliranoj aplikaciji ni na košarici i plaćanju. Na iPhoneu se prikazuje uputa Podijeli > Dodaj na početni zaslon.', 'plan-a-aplikacija' ); ?></p>
						</td>
					</tr>
				</table>
				<p class="description"><?php esc_html_e( 'Adrese upišite kao putanju na ovoj stranici, npr. /izleti/.', 'plan-a-aplikacija' ); ?></p>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	private static function status_notices( array $icons ) {
		$notices = array();
		if ( ! is_ssl() && 'localhost' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
			$notices[] = array( 'error', __( 'Stranica ne koristi HTTPS. Service worker i instalacija rade samo preko HTTPS-a.', 'plan-a-aplikacija' ) );
		}
		if ( $icons['error'] ) {
			$notices[] = array( 'error', $icons['error'] );
		}
		if ( ! Plan_A_App_Settings::get( 'logo_id' ) ) {
			$notices[] = array( 'warning', get_site_icon_url( 512 ) ? __( 'Logotip nije odabran: koristi se ikona stranice (Izgled > Prilagodba > Identitet stranice). Za najbolji izgled odaberite logotip.', 'plan-a-aplikacija' ) : __( 'Odaberite logotip. Bez ikone aplikacija se ne može instalirati.', 'plan-a-aplikacija' ) );
		}
		foreach ( $notices as $notice ) {
			echo '<div class="notice notice-' . esc_attr( $notice[0] ) . ' inline"><p>' . esc_html( $notice[1] ) . '</p></div>';
		}
	}
}
