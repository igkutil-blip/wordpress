<?php
/**
 * Postavke → Plan A košarica.
 *
 * @package Plan_A_Kosarica
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Kosarica_Settings {

	const OPTION = 'plan_a_kosarica';
	const PAGE   = 'plan-a-kosarica';

	public static function defaults(): array {
		return array(
			'enabled'   => 1,
			'steps'     => 1,
			'more_url'  => '/izleti/',
			'whatsapp'  => '',
			'help_text' => 'Trebaš pomoć s rezervacijom?',
			'cta'       => '#e8862a',
			'accent'    => '#1a9ad6',
			'navy'      => '#12304b',
			// Završna stranica narudžbe i e-mailovi.
			'order'      => 1,
			'step1'      => 'Platite uplatom: skenirajte 2D kod u aplikaciji svoje banke ili upišite podatke ručno',
			'step2'      => 'Kad uplata stigne, dobit ćete potvrdu e-mailom',
			'step3'      => 'Prije izleta poslat ćemo vam upute i popis opreme',
			'step1_paid' => 'Plaćanje je uspješno',
			'deadline'   => '',
			'email_logo' => 0,
		);
	}

	public static function get( string $key = '' ) {
		$values = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		return '' === $key ? $values : ( $values[ $key ] ?? null );
	}

	public static function init() {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PLAN_A_KOSARICA_FILE ), array( __CLASS__, 'action_link' ) );
	}

	public static function menu() {
		add_options_page( 'Plan A košarica', 'Plan A košarica', 'manage_options', self::PAGE, array( __CLASS__, 'render' ) );
	}

	public static function action_link( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ) . '">Postavke</a>' );
		return $links;
	}

	public static function register() {
		register_setting(
			'plan_a_kosarica',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	public static function sanitize( $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$color    = static function ( $value, $fallback ) {
			$value = trim( (string) $value );
			$value = '' !== $value ? '#' . ltrim( $value, '#' ) : '';
			return sanitize_hex_color( $value ) ?: $fallback;
		};
		return array(
			'enabled'   => empty( $input['enabled'] ) ? 0 : 1,
			'steps'     => empty( $input['steps'] ) ? 0 : 1,
			'more_url'  => self::clean_url( (string) ( $input['more_url'] ?? '' ) ),
			'whatsapp'  => preg_replace( '/[^0-9]/', '', (string) ( $input['whatsapp'] ?? '' ) ),
			'help_text' => sanitize_text_field( (string) ( $input['help_text'] ?? $defaults['help_text'] ) ),
			'cta'       => $color( $input['cta'] ?? '', $defaults['cta'] ),
			'accent'    => $color( $input['accent'] ?? '', $defaults['accent'] ),
			'navy'      => $color( $input['navy'] ?? '', $defaults['navy'] ),
			'order'      => empty( $input['order'] ) ? 0 : 1,
			'step1'      => self::text( $input['step1'] ?? '', $defaults['step1'] ),
			'step2'      => self::text( $input['step2'] ?? '', $defaults['step2'] ),
			'step3'      => self::text( $input['step3'] ?? '', $defaults['step3'] ),
			'step1_paid' => self::text( $input['step1_paid'] ?? '', $defaults['step1_paid'] ),
			'deadline'   => sanitize_text_field( (string) ( $input['deadline'] ?? '' ) ),
			'email_logo' => absint( $input['email_logo'] ?? 0 ),
		);
	}

	private static function text( $value, string $fallback ): string {
		$value = sanitize_text_field( (string) $value );
		return '' !== $value ? $value : $fallback;
	}

	/**
	 * Relativna adresa (/izleti/) ili puna http(s) adresa.
	 */
	private static function clean_url( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		if ( '/' === $url[0] && '/' !== ( $url[1] ?? '' ) ) {
			return '/' . ltrim( (string) wp_parse_url( esc_url_raw( home_url( $url ) ), PHP_URL_PATH ), '/' ) . ( wp_parse_url( $url, PHP_URL_QUERY ) ? '?' . wp_parse_url( $url, PHP_URL_QUERY ) : '' );
		}
		return esc_url_raw( $url, array( 'http', 'https' ) );
	}

	/**
	 * Stranica košarice ili plaćanja koristi WooCommerce blokove? Tada se ne koriste
	 * klasični predlošci WooCommercea, pa ni ovaj izgled.
	 */
	private static function block_pages(): array {
		$found = array();
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return $found;
		}
		foreach ( array(
			'cart'     => array( 'Košarica', 'woocommerce/cart' ),
			'checkout' => array( 'Plaćanje', 'woocommerce/checkout' ),
		) as $page => $info ) {
			$id = wc_get_page_id( $page );
			if ( $id > 0 && has_block( $info[1], get_post( $id ) ) ) {
				$found[ $page ] = $info[0];
			}
		}
		return $found;
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s    = self::get();
		$name = self::OPTION;
		?>
		<div class="wrap">
			<h1>Plan A košarica</h1>
			<p>Novi izgled košarice i stranice za plaćanje. Svi podaci iz košarice (izlet, mjesto, datum i vrijeme, karte, dodatne usluge, kuponi, porezi, načini plaćanja i polja drugih dodataka) ostaju; mijenja se samo prikaz.</p>
			<?php if ( ! class_exists( 'WooCommerce' ) ) : ?>
				<div class="notice notice-warning inline"><p>WooCommerce nije aktivan.</p></div>
			<?php endif; ?>
			<?php foreach ( self::block_pages() as $label ) : ?>
				<div class="notice notice-warning inline"><p>
					<?php echo esc_html( sprintf( 'Stranica „%s” koristi WooCommerce blokove, pa se novi izgled na njoj ne prikazuje. Zamijenite sadržaj stranice klasičnim shortcodeom ([woocommerce_cart] za košaricu, [woocommerce_checkout] za plaćanje).', $label ) ); ?>
				</p></div>
			<?php endforeach; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'plan_a_kosarica' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Novi izgled</th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( $s['enabled'] ); ?>> Uključen</label>
						<p class="description">Isključivanjem se odmah vraća izgled teme.</p></td>
					</tr>
					<tr>
						<th scope="row">Koraci</th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[steps]" value="1" <?php checked( $s['steps'] ); ?>> Prikaži korake Košarica → Podaci → Plaćanje</label>
						<p class="description">Koraci teme Flatsome iznad košarice i plaćanja se tada skrivaju, da ne budu dvaput.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="paka-more">„Pogledaj još izleta”</label></th>
						<td><input type="text" id="paka-more" class="regular-text" name="<?php echo esc_attr( $name ); ?>[more_url]" value="<?php echo esc_attr( $s['more_url'] ); ?>" placeholder="/izleti/">
						<p class="description">Adresa stranice s izletima. Prazno = poveznica se ne prikazuje.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="paka-wa">WhatsApp za pomoć</label></th>
						<td><input type="text" id="paka-wa" class="regular-text" name="<?php echo esc_attr( $name ); ?>[whatsapp]" value="<?php echo esc_attr( $s['whatsapp'] ); ?>" placeholder="385911234567">
						<p class="description">Broj s pozivnim brojem države, bez + i razmaka (npr. 385911234567). Prazno = „Javi nam se” se ne prikazuje.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="paka-help">Tekst pomoći</label></th>
						<td><input type="text" id="paka-help" class="regular-text" name="<?php echo esc_attr( $name ); ?>[help_text]" value="<?php echo esc_attr( $s['help_text'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row">Boje</th>
						<td>
							<?php
							foreach ( array(
								'cta'    => 'Glavni gumb (Nastavi na plaćanje, Naruči)',
								'accent' => 'Naglasak (aktivni korak, poveznice)',
								'navy'   => 'Naslovi i iznosi',
							) as $key => $label ) :
								?>
								<p><input type="text" name="<?php echo esc_attr( $name . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>" style="width:100px;">
								<span style="display:inline-block;width:22px;height:22px;vertical-align:middle;border-radius:4px;background:<?php echo esc_attr( $s[ $key ] ); ?>"></span>
								<?php echo esc_html( $label ); ?></p>
							<?php endforeach; ?>
						</td>
					</tr>
				</table>

				<h2>Završna stranica narudžbe i e-mailovi</h2>
				<p>Stranica „Narudžba zaprimljena” i e-mailovi kupcu (prijava zaprimljena, uplata zaprimljena) u istom stilu. 2D kod i podatke za plaćanje i dalje ispisuje vaš Hub3 dodatak (ili bankovni prijenos); ovdje se mijenja samo okvir oko njih.</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Novi izgled</th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[order]" value="1" <?php checked( $s['order'] ); ?>> Završna stranica i e-mailovi kupcu</label></td>
					</tr>
					<?php
					foreach ( array(
						'step1'      => '„Što sada?” – 1. korak',
						'step2'      => '„Što sada?” – 2. korak',
						'step3'      => '„Što sada?” – 3. korak',
						'step1_paid' => '1. korak kad je narudžba plaćena (npr. karticom)',
					) as $key => $label ) :
						?>
						<tr>
							<th scope="row"><label for="paka-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
							<td><input type="text" id="paka-<?php echo esc_attr( $key ); ?>" class="large-text" name="<?php echo esc_attr( $name . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>"></td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<th scope="row"><label for="paka-deadline">Rok plaćanja</label></th>
						<td><input type="text" id="paka-deadline" class="regular-text" name="<?php echo esc_attr( $name ); ?>[deadline]" value="<?php echo esc_attr( $s['deadline'] ); ?>" placeholder="npr. 3 dana od prijave">
						<p class="description">Prikazuje se uz podatke za plaćanje. Prazno = rok se ne prikazuje.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="paka-logo">Logotip u e-mailu (ID slike)</label></th>
						<td><input type="number" min="0" id="paka-logo" class="small-text" name="<?php echo esc_attr( $name ); ?>[email_logo]" value="<?php echo esc_attr( $s['email_logo'] ? (string) $s['email_logo'] : '' ); ?>">
						<?php $paka_logo = Plan_A_Kosarica_Order::logo_url(); ?>
						<?php if ( $paka_logo ) : ?>
							<span style="display:inline-block;margin-left:10px;padding:6px 10px;background:<?php echo esc_attr( $s['navy'] ); ?>;border-radius:6px;vertical-align:middle;"><img src="<?php echo esc_url( $paka_logo ); ?>" alt="" style="max-height:36px;vertical-align:middle;"></span>
						<?php endif; ?>
						<p class="description">Prazno = logotip iz Plan A izleti / teme / postavki e-mailova WooCommercea. ID slike vidi se u Medijima (adresa „post=…”).</p></td>
					</tr>
				</table>
				<?php submit_button( 'Spremi promjene' ); ?>
			</form>

			<h2>Probni e-mail</h2>
			<?php if ( isset( $_GET['paka-test'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- samo poruka. ?>
				<?php $paka_ok = 'ok' === sanitize_key( wp_unslash( $_GET['paka-test'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice <?php echo $paka_ok ? 'notice-success' : 'notice-error'; ?> inline"><p>
					<?php echo esc_html( $paka_ok ? 'Probni e-mailovi poslani su na ' . get_option( 'admin_email' ) . '.' : 'Probni e-mail nije poslan: nema nijedne narudžbe ili slanje e-pošte nije uspjelo.' ); ?>
				</p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="plan_a_kosarica_test_email">
				<?php wp_nonce_field( 'plan_a_kosarica_test_email' ); ?>
				<p>Šalje na <strong><?php echo esc_html( get_option( 'admin_email' ) ); ?></strong> oba e-maila (prijava zaprimljena i uplata zaprimljena) za zadnju narudžbu s izletom.</p>
				<?php submit_button( 'Pošalji probni e-mail', 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}
}
