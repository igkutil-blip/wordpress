<?php
/**
 * Postavke: Poklon bonovi → Postavke.
 *
 * @package Plan_A_Poklon_Bon
 */

defined( 'ABSPATH' ) || exit;

class Plan_A_Bon_Settings {

	const OPTION = 'plan_a_bon';
	const PAGE   = 'plan-a-bon-postavke';

	public static function defaults(): array {
		return array(
			'amounts'       => '50, 90, 150',
			'min'           => 20,
			'max'           => 1000,
			'months'        => 12,
			'remainder'     => 'keep',
			'photo'         => 0,
			'logo'          => 0,
			'issuer'        => 'Izdaje Adventure Donkey j.d.o.o., turistička agencija, Meksička ulica 11, 10000 Zagreb, OIB 78664134608, u suradnji sa S.R.D. Plan A',
			'whatsapp'      => '095 90 60 556',
			'email_subject' => 'Tvoj poklon bon za izlet',
			'email_text'    => "Pozdrav {kupac},\n\nhvala na kupnji! U privitku je poklon bon za {za} u vrijednosti {iznos}: PDF za ispis i slika za slanje porukom.\n\nKako ga uručiti: isprintaj PDF i stavi ga u čestitku ili pošalji sliku WhatsAppom. Primatelj odabere izlet na srd-plan-a.hr i u košarici upiše kod {kod}. Bon vrijedi do {vrijedi_do}.\n\nVidimo se na izletu!\nPlan A",
		);
	}

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PLAN_A_BON_FILE ), array( __CLASS__, 'links' ) );
		// Postavke smije spremiti i upravitelj trgovine (kao i ostatak izbornika "Poklon bonovi").
		add_filter(
			'option_page_capability_plan_a_bon',
			static function () {
				return 'manage_woocommerce';
			}
		);
	}

	/**
	 * @return array|mixed
	 */
	public static function get( string $key = '' ) {
		$s = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		return '' === $key ? $s : ( $s[ $key ] ?? null );
	}

	/**
	 * Ponuđeni iznosi (kartice na stranici za kupnju).
	 *
	 * @return float[]
	 */
	public static function amounts(): array {
		$out = array();
		foreach ( preg_split( '/[;\s]+|,\s+/', (string) self::get( 'amounts' ) ) as $part ) {
			$value = (float) str_replace( ',', '.', trim( $part ) );
			if ( $value > 0 ) {
				$out[] = round( $value, 2 );
			}
		}
		$out = array_values( array_unique( $out ) );
		sort( $out );
		return array_slice( $out, 0, 6 );
	}

	public static function min(): float {
		return (float) self::get( 'min' );
	}

	public static function max(): float {
		return (float) self::get( 'max' );
	}

	/**
	 * Broj za wa.me (međunarodni oblik bez +): "095 90 60 556" → "385959060556".
	 */
	public static function whatsapp_digits(): string {
		$digits = preg_replace( '/\D+/', '', (string) self::get( 'whatsapp' ) );
		if ( 0 === strpos( $digits, '00' ) ) {
			$digits = substr( $digits, 2 );
		} elseif ( 0 === strpos( $digits, '0' ) ) {
			$digits = '385' . substr( $digits, 1 );
		}
		return $digits;
	}

	public static function register() {
		register_setting(
			'plan_a_bon',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$d     = self::defaults();
		$min   = max( 1, round( (float) str_replace( ',', '.', (string) ( $input['min'] ?? $d['min'] ) ), 2 ) );
		$max   = max( $min, round( (float) str_replace( ',', '.', (string) ( $input['max'] ?? $d['max'] ) ), 2 ) );

		$amounts = array();
		foreach ( preg_split( '/[;\s]+|,\s+/', sanitize_text_field( (string) ( $input['amounts'] ?? '' ) ) ) as $part ) {
			$value = round( (float) str_replace( ',', '.', trim( $part ) ), 2 );
			if ( $value >= $min && $value <= $max ) {
				$amounts[] = $value;
			}
		}
		$amounts = array_values( array_unique( $amounts ) );
		sort( $amounts );

		$text = static function ( $key ) use ( $input, $d ) {
			$value = sanitize_textarea_field( (string) ( $input[ $key ] ?? '' ) );
			return '' !== trim( $value ) ? $value : $d[ $key ];
		};

		return array(
			'amounts'       => $amounts ? implode( ', ', array_map( array( __CLASS__, 'number' ), array_slice( $amounts, 0, 6 ) ) ) : $d['amounts'],
			'min'           => $min,
			'max'           => $max,
			'months'        => min( 60, max( 1, absint( $input['months'] ?? $d['months'] ) ) ),
			'remainder'     => 'lose' === ( $input['remainder'] ?? '' ) ? 'lose' : 'keep',
			'photo'         => absint( $input['photo'] ?? 0 ),
			'logo'          => absint( $input['logo'] ?? 0 ),
			'issuer'        => $text( 'issuer' ),
			'whatsapp'      => sanitize_text_field( (string) ( $input['whatsapp'] ?? '' ) ),
			'email_subject' => sanitize_text_field( (string) ( $input['email_subject'] ?? '' ) ) ?: $d['email_subject'],
			'email_text'    => $text( 'email_text' ),
		);
	}

	/**
	 * Iznos bez nepotrebnih decimala: 90 → "90", 92.5 → "92,5".
	 */
	public static function number( float $value ): string {
		return str_replace( '.', ',', rtrim( rtrim( number_format( $value, 2, '.', '' ), '0' ), '.' ) );
	}

	public static function links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ) . '">Postavke</a>' );
		return $links;
	}

	public static function page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		wp_enqueue_media();
		$s    = self::get();
		$name = self::OPTION;
		?>
		<div class="wrap papb-admin">
			<h1>Poklon bonovi – postavke</h1>
			<?php settings_errors(); ?>
			<p>Stranica za kupnju: stranica s shortcodeom <code>[plan-a-poklon-bon]</code>. QR kod na bonu vodi na <code><?php echo esc_html( Plan_A_Bon_Voucher::redeem_url( 'KOD' ) ); ?></code>.</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'plan_a_bon' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="papb-amounts">Ponuđeni iznosi (€)</label></th>
						<td><input type="text" id="papb-amounts" class="regular-text" name="<?php echo esc_attr( $name ); ?>[amounts]" value="<?php echo esc_attr( $s['amounts'] ); ?>">
						<p class="description">Odvojeni zarezom i razmakom, npr. „50, 90, 150” (najviše 6). Uz njih se uvijek nudi „Drugi iznos”.</p></td>
					</tr>
					<tr>
						<th scope="row">Drugi iznos</th>
						<td><label>najmanje <input type="number" min="1" step="1" class="small-text" name="<?php echo esc_attr( $name ); ?>[min]" value="<?php echo esc_attr( self::number( (float) $s['min'] ) ); ?>"> €</label>
						&nbsp; <label>najviše <input type="number" min="1" step="1" class="small-text" name="<?php echo esc_attr( $name ); ?>[max]" value="<?php echo esc_attr( self::number( (float) $s['max'] ) ); ?>"> €</label></td>
					</tr>
					<tr>
						<th scope="row"><label for="papb-months">Rok valjanosti</label></th>
						<td><input type="number" id="papb-months" min="1" max="60" class="small-text" name="<?php echo esc_attr( $name ); ?>[months]" value="<?php echo esc_attr( (string) $s['months'] ); ?>"> mjeseci od izdavanja</td>
					</tr>
					<tr>
						<th scope="row">Ostatak bona</th>
						<td>
							<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[remainder]" value="keep" <?php checked( 'keep', $s['remainder'] ); ?>> ostaje za sljedeći put</label><br>
							<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[remainder]" value="lose" <?php checked( 'lose', $s['remainder'] ); ?>> propada</label>
							<p class="description">Ako ostaje: nakon plaćene narudžbe u kojoj je bon djelomično iskorišten izdaje se novi bon s ostatkom i istim rokom, a kupac ga dobiva e-mailom.</p>
						</td>
					</tr>
					<?php
					foreach ( array(
						'photo' => array( 'Fotografija na bonu', 'Lijeva strana bona (najbolje položena fotografija s izleta, barem 1600 px).' ),
						'logo'  => array( 'Logotip', 'Prazno = logotip iz Plan A izleti ili teme.' ),
					) as $key => $field ) :
						$id  = (int) $s[ $key ];
						$src = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
						?>
						<tr>
							<th scope="row"><?php echo esc_html( $field[0] ); ?></th>
							<td class="papb-media" data-title="<?php echo esc_attr( $field[0] ); ?>">
								<input type="hidden" name="<?php echo esc_attr( $name . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( (string) $id ); ?>">
								<img src="<?php echo esc_url( (string) $src ); ?>" alt="" style="max-width:220px;max-height:120px;display:<?php echo $src ? 'block' : 'none'; ?>;margin-bottom:8px;border-radius:6px;">
								<button type="button" class="button papb-media__pick">Odaberi</button>
								<button type="button" class="button-link papb-media__clear"<?php echo $id ? '' : ' hidden'; ?>>Ukloni</button>
								<p class="description"><?php echo esc_html( $field[1] ); ?></p>
							</td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<th scope="row"><label for="papb-issuer">Tekst izdavatelja</label></th>
						<td><textarea id="papb-issuer" class="large-text" rows="2" name="<?php echo esc_attr( $name ); ?>[issuer]"><?php echo esc_textarea( $s['issuer'] ); ?></textarea>
						<p class="description">Sitnim slovima na dnu bona.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="papb-wa">Broj za WhatsApp</label></th>
						<td><input type="text" id="papb-wa" class="regular-text" name="<?php echo esc_attr( $name ); ?>[whatsapp]" value="<?php echo esc_attr( $s['whatsapp'] ); ?>" placeholder="095 90 60 556">
						<p class="description">Ispisuje se na bonu u ovom obliku.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="papb-subj">Naslov e-maila</label></th>
						<td><input type="text" id="papb-subj" class="large-text" name="<?php echo esc_attr( $name ); ?>[email_subject]" value="<?php echo esc_attr( $s['email_subject'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="papb-text">Tekst e-maila</label></th>
						<td><textarea id="papb-text" class="large-text" rows="9" name="<?php echo esc_attr( $name ); ?>[email_text]"><?php echo esc_textarea( $s['email_text'] ); ?></textarea>
						<p class="description">Zamjene: {kupac}, {za}, {od}, {iznos}, {kod}, {vrijedi_do}. PDF i slika bona šalju se kao privitak.</p></td>
					</tr>
				</table>
				<?php submit_button( 'Spremi promjene' ); ?>
			</form>
		</div>
		<?php
	}
}
