<?php
/**
 * Stranica za kupnju: [plan-a-poklon-bon].
 *
 * @package Plan_A_Poklon_Bon
 */

defined( 'ABSPATH' ) || exit;

class Plan_A_Bon_Shortcode {

	const TAG = 'plan-a-poklon-bon';

	/** @var array Greške zadnjeg slanja obrasca (polje => poruka). */
	private static $errors = array();

	/** @var array Upisane vrijednosti (nakon greške ostaju u obrascu). */
	private static $values = array();

	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
		add_action( 'wp_loaded', array( __CLASS__, 'handle' ), 25 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function assets() {
		wp_register_style( 'plan-a-poklon-bon', PLAN_A_BON_URL . 'assets/css/poklon-bon.css', array(), PLAN_A_BON_VERSION );
		wp_register_script( 'plan-a-poklon-bon', PLAN_A_BON_URL . 'assets/js/poklon-bon.js', array(), PLAN_A_BON_VERSION, array( 'in_footer' => true ) );
		$post = get_post();
		if ( is_singular() && $post && has_shortcode( $post->post_content, self::TAG ) ) {
			wp_enqueue_style( 'plan-a-poklon-bon' );
		}
	}

	/**
	 * Slanje obrasca: sve se provjerava ovdje, na poslužitelju.
	 */
	public static function handle() {
		if ( empty( $_POST['papb_add'] ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		if ( ! isset( $_POST['papb_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['papb_nonce'] ) ), 'papb_add' ) ) {
			self::$errors['form'] = __( 'Obrazac je istekao. Provjeri podatke i pokušaj ponovno.', 'plan-a-poklon-bon' );
		}

		$choice = sanitize_text_field( wp_unslash( $_POST['papb_amount'] ?? '' ) );
		$custom = sanitize_text_field( wp_unslash( $_POST['papb_custom'] ?? '' ) );
		$to     = self::clean_line( wp_unslash( $_POST['papb_to'] ?? '' ) );
		$from   = self::clean_line( wp_unslash( $_POST['papb_from'] ?? '' ) );
		$msg    = self::clean_text( wp_unslash( $_POST['papb_message'] ?? '' ) );

		self::$values = array(
			'replace' => sanitize_key( wp_unslash( $_POST['papb_replace'] ?? '' ) ),
			'amount'  => $choice,
			'custom'  => $custom,
			'to'      => $to,
			'from'    => $from,
			'message' => $msg,
		);

		$amount = 0.0;
		if ( 'custom' === $choice ) {
			$amount = round( (float) str_replace( array( ' ', ',' ), array( '', '.' ), $custom ), 2 );
			if ( $amount < Plan_A_Bon_Settings::min() || $amount > Plan_A_Bon_Settings::max() ) {
				/* translators: 1: najmanji iznos, 2: najveći iznos */
				self::$errors['amount'] = sprintf( __( 'Upiši iznos od %1$s do %2$s.', 'plan-a-poklon-bon' ), Plan_A_Bon_Voucher::money_short( Plan_A_Bon_Settings::min() ), Plan_A_Bon_Voucher::money_short( Plan_A_Bon_Settings::max() ) );
			}
		} else {
			$amount = round( (float) $choice, 2 );
			if ( ! in_array( $amount, Plan_A_Bon_Settings::amounts(), true ) ) {
				self::$errors['amount'] = __( 'Odaberi iznos bona.', 'plan-a-poklon-bon' );
			}
		}
		if ( '' === $to ) {
			self::$errors['to'] = __( 'Upiši za koga je bon.', 'plan-a-poklon-bon' );
		} elseif ( mb_strlen( $to ) > 40 ) {
			self::$errors['to'] = __( 'Ime primatelja može imati najviše 40 znakova.', 'plan-a-poklon-bon' );
		}
		if ( '' === $from ) {
			self::$errors['from'] = __( 'Upiši od koga je bon.', 'plan-a-poklon-bon' );
		} elseif ( mb_strlen( $from ) > 40 ) {
			self::$errors['from'] = __( 'Polje "Od koga" može imati najviše 40 znakova.', 'plan-a-poklon-bon' );
		}
		if ( mb_strlen( $msg ) > 160 ) {
			self::$errors['message'] = __( 'Poruka može imati najviše 160 znakova.', 'plan-a-poklon-bon' );
		}
		if ( self::$errors ) {
			return;
		}

		// "Uredi bon": nova stavka zamjenjuje postojeću (samo stavku bona iz ove košarice).
		$replace = sanitize_key( wp_unslash( $_POST['papb_replace'] ?? '' ) );
		$editing = '' !== $replace && ! empty( WC()->cart->get_cart_item( $replace )['papb'] );
		if ( $editing ) {
			WC()->cart->remove_cart_item( $replace );
		}

		$key = Plan_A_Bon::add_to_cart(
			array(
				'amount'  => $amount,
				'to'      => $to,
				'from'    => $from,
				'message' => $msg,
			)
		);
		if ( ! $key ) {
			self::$errors['form'] = __( 'Bon nije dodan u košaricu. Pokušaj ponovno.', 'plan-a-poklon-bon' );
			return;
		}
		wc_add_notice( $editing ? __( 'Poklon bon je ažuriran.', 'plan-a-poklon-bon' ) : __( 'Poklon bon je dodan u košaricu.', 'plan-a-poklon-bon' ) );
		wp_safe_redirect( wc_get_cart_url() );
		exit;
	}

	private static function clean_line( $value ): string {
		$value = sanitize_text_field( (string) $value );
		return trim( preg_replace( '/\s+/u', ' ', $value ) );
	}

	private static function clean_text( $value ): string {
		$value = sanitize_textarea_field( (string) $value );
		return trim( preg_replace( '/[ \t]+/u', ' ', preg_replace( '/\R+/u', ' ', $value ) ) );
	}

	public static function render(): string {
		if ( ! function_exists( 'WC' ) ) {
			return '';
		}
		$page = get_queried_object_id();
		if ( $page && is_singular() && (int) get_option( Plan_A_Bon::PAGE_OPTION ) !== $page ) {
			update_option( Plan_A_Bon::PAGE_OPTION, $page, false );
		}
		wp_enqueue_style( 'plan-a-poklon-bon' );
		wp_enqueue_script( 'plan-a-poklon-bon' );

		$amounts = Plan_A_Bon_Settings::amounts();
		$min     = Plan_A_Bon_Settings::min();
		$max     = Plan_A_Bon_Settings::max();
		$val     = wp_parse_args(
			self::$values,
			array(
				'amount'  => $amounts ? (string) $amounts[ min( 1, count( $amounts ) - 1 ) ] : 'custom',
				'custom'  => '',
				'to'      => '',
				'from'    => '',
				'message' => '',
			)
		);
		// "Uredi bon" iz košarice: podaci postojeće stavke.
		$edit = sanitize_key( wp_unslash( $_GET['papb_uredi'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- samo čitanje vlastite košarice.
		if ( ! self::$values && '' !== $edit && WC()->cart ) {
			$item = WC()->cart->get_cart_item( $edit );
			if ( ! empty( $item['papb'] ) ) {
				$amount = round( (float) $item['papb']['amount'], 2 );
				$listed = in_array( $amount, $amounts, true );
				$val    = array(
					'replace' => $edit,
					'amount'  => $listed ? (string) $amount : 'custom',
					'custom'  => $listed ? '' : Plan_A_Bon_Settings::number( $amount ),
					'to'      => (string) $item['papb']['to'],
					'from'    => (string) $item['papb']['from'],
					'message' => (string) $item['papb']['message'],
				);
			}
		}
		$replace = (string) ( $val['replace'] ?? '' );
		$err     = self::$errors;
		$photo   = (int) Plan_A_Bon_Settings::get( 'photo' );
		$photo   = $photo ? (string) wp_get_attachment_image_url( $photo, 'large' ) : '';
		$logo    = self::logo_url();
		$colors  = self::colors();
		$preview = (float) ( 'custom' === $val['amount'] ? str_replace( ',', '.', $val['custom'] ) : $val['amount'] );

		ob_start();
		?>
		<div class="papb" style="--papb-navy:<?php echo esc_attr( $colors['navy'] ); ?>;--papb-accent:<?php echo esc_attr( $colors['accent'] ); ?>;--papb-cta:<?php echo esc_attr( $colors['cta'] ); ?>;">
			<header class="papb-head">
				<h2 class="papb-title"><?php esc_html_e( 'Daruj izlet', 'plan-a-poklon-bon' ); ?></h2>
				<p class="papb-lead"><?php esc_html_e( 'Poklon bon za izlet s licenciranim vodičima. Primatelj sam bira izlet i termin.', 'plan-a-poklon-bon' ); ?></p>
			</header>

			<?php if ( ! empty( $err['form'] ) ) : ?>
				<p class="papb-error papb-error--form" role="alert"><?php echo esc_html( $err['form'] ); ?></p>
			<?php endif; ?>

			<form class="papb-layout" method="post" action="" novalidate data-papb-form data-min="<?php echo esc_attr( (string) $min ); ?>" data-max="<?php echo esc_attr( (string) $max ); ?>">
				<div class="papb-form">
					<fieldset class="papb-card papb-amounts">
						<legend class="papb-h3"><?php esc_html_e( 'Iznos bona', 'plan-a-poklon-bon' ); ?></legend>
						<div class="papb-amounts__grid">
							<?php foreach ( $amounts as $amount ) : ?>
								<label class="papb-amount">
									<input type="radio" name="papb_amount" value="<?php echo esc_attr( (string) $amount ); ?>" <?php checked( (string) $amount, (string) $val['amount'] ); ?>>
									<span class="papb-amount__box"><?php echo esc_html( Plan_A_Bon_Voucher::money_short( $amount ) ); ?></span>
								</label>
							<?php endforeach; ?>
							<label class="papb-amount papb-amount--custom">
								<input type="radio" name="papb_amount" value="custom" <?php checked( 'custom', (string) $val['amount'] ); ?>>
								<span class="papb-amount__box"><?php esc_html_e( 'Drugi iznos', 'plan-a-poklon-bon' ); ?></span>
							</label>
						</div>
						<div class="papb-custom" data-papb-custom<?php echo 'custom' === $val['amount'] ? '' : ' hidden'; ?>>
							<label for="papb-custom"><?php esc_html_e( 'Upiši iznos', 'plan-a-poklon-bon' ); ?></label>
							<div class="papb-custom__input">
								<input type="number" id="papb-custom" name="papb_custom" inputmode="decimal" min="<?php echo esc_attr( (string) $min ); ?>" max="<?php echo esc_attr( (string) $max ); ?>" step="1" value="<?php echo esc_attr( $val['custom'] ); ?>" aria-describedby="papb-custom-help">
								<span aria-hidden="true">€</span>
							</div>
							<p class="papb-help" id="papb-custom-help">
								<?php
								/* translators: 1: najmanji iznos, 2: najveći iznos */
								echo esc_html( sprintf( __( 'Od %1$s do %2$s', 'plan-a-poklon-bon' ), Plan_A_Bon_Voucher::money_short( $min ), Plan_A_Bon_Voucher::money_short( $max ) ) );
								?>
							</p>
						</div>
						<?php self::error( $err, 'amount' ); ?>
					</fieldset>

					<div class="papb-card papb-fields">
						<p class="papb-field">
							<label for="papb-to"><?php esc_html_e( 'Za koga je bon', 'plan-a-poklon-bon' ); ?> <span class="papb-req" aria-hidden="true">*</span></label>
							<input type="text" id="papb-to" name="papb_to" maxlength="40" required autocomplete="off" placeholder="<?php esc_attr_e( 'Ime primatelja', 'plan-a-poklon-bon' ); ?>" value="<?php echo esc_attr( $val['to'] ); ?>" data-papb-input="to"<?php echo isset( $err['to'] ) ? ' aria-invalid="true"' : ''; ?>>
							<?php self::error( $err, 'to' ); ?>
						</p>
						<p class="papb-field">
							<label for="papb-from"><?php esc_html_e( 'Od koga', 'plan-a-poklon-bon' ); ?> <span class="papb-req" aria-hidden="true">*</span></label>
							<input type="text" id="papb-from" name="papb_from" maxlength="40" required autocomplete="name" placeholder="<?php esc_attr_e( 'Tvoje ime', 'plan-a-poklon-bon' ); ?>" value="<?php echo esc_attr( $val['from'] ); ?>" data-papb-input="from"<?php echo isset( $err['from'] ) ? ' aria-invalid="true"' : ''; ?>>
							<?php self::error( $err, 'from' ); ?>
						</p>
						<p class="papb-field">
							<label for="papb-message"><?php esc_html_e( 'Poruka primatelju', 'plan-a-poklon-bon' ); ?> <span class="papb-optional"><?php esc_html_e( '(neobavezno)', 'plan-a-poklon-bon' ); ?></span></label>
							<textarea id="papb-message" name="papb_message" maxlength="160" rows="3" placeholder="<?php esc_attr_e( 'Npr. Sretan rođendan! Vidimo se na vrhu.', 'plan-a-poklon-bon' ); ?>" data-papb-input="message" aria-describedby="papb-count"><?php echo esc_textarea( $val['message'] ); ?></textarea>
							<span class="papb-count" id="papb-count" aria-live="polite"><span data-papb-count><?php echo esc_html( (string) mb_strlen( $val['message'] ) ); ?></span>/160</span>
							<?php self::error( $err, 'message' ); ?>
						</p>
					</div>
				</div>

				<aside class="papb-side">
					<p class="papb-h3"><?php esc_html_e( 'Ovako će bon izgledati', 'plan-a-poklon-bon' ); ?></p>
					<?php echo self::ticket( $val, $preview, $photo, $logo ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapano u ticket(). ?>
					<p class="papb-help papb-help--preview"><?php esc_html_e( 'Nakon uplate bon stiže e-mailom kao PDF za ispis i slika za slanje porukom.', 'plan-a-poklon-bon' ); ?></p>

					<?php wp_nonce_field( 'papb_add', 'papb_nonce' ); ?>
					<?php if ( '' !== $replace ) : ?>
						<input type="hidden" name="papb_replace" value="<?php echo esc_attr( $replace ); ?>">
					<?php endif; ?>
					<button type="submit" name="papb_add" value="1" class="papb-btn papb-btn--cta"><?php echo esc_html( '' !== $replace ? __( 'Spremi promjene', 'plan-a-poklon-bon' ) : __( 'Dodaj u košaricu', 'plan-a-poklon-bon' ) ); ?>
						<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
					</button>
				</aside>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Pregled bona kao ulaznica (isti raspored kao PDF/PNG); kod se prije izdavanja ne zna.
	 */
	private static function ticket( array $val, float $amount, string $photo, string $logo ): string {
		$empty = static function ( $value, $sample ) {
			return '' !== $value
				? array( esc_html( $value ), '' )
				: array( esc_html( $sample ), ' is-empty' );
		};
		$to   = $empty( (string) $val['to'], __( 'Ime primatelja', 'plan-a-poklon-bon' ) );
		$from = $empty( (string) $val['from'], __( 'Tvoje ime', 'plan-a-poklon-bon' ) );

		$art = '';
		if ( '' === $photo ) {
			// Ilustracija grebena (iste oblike crta i PDF).
			$colors = array(
				'blue'   => Plan_A_Bon_Render::BLUE,
				'navy'   => Plan_A_Bon_Render::NAVY,
				'orange' => Plan_A_Bon_Render::ORANGE,
				'red'    => Plan_A_Bon_Render::RED,
				'white'  => '#ffffff',
			);
			$art = '<svg class="papb-ticket__art" viewBox="0 0 880 1100" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false"><rect width="880" height="1100" fill="#dcecfa"/><circle cx="581" cy="297" r="88" fill="#f3b064"/>';
			foreach ( Plan_A_Bon_Render::ridge_shapes() as $shape ) {
				$points = array();
				foreach ( array_chunk( $shape[1], 2 ) as $pt ) {
					$points[] = round( $pt[0] * 880, 1 ) . ',' . round( $pt[1] * 1100, 1 );
				}
				$art .= '<polygon points="' . esc_attr( implode( ' ', $points ) ) . '" fill="' . esc_attr( $colors[ $shape[0] ] ) . '"/>';
			}
			$art .= '</svg>';
		}

		$html  = '<div class="papb-ticket-wrap" aria-hidden="true"><div class="papb-ticket">';
		$html .= '<div class="papb-ticket__photo"' . ( '' !== $photo ? ' style="background-image:url(' . esc_url( $photo ) . ')"' : '' ) . '>' . $art;
		$html .= '<span class="papb-ticket__logo">' . ( '' !== $logo ? '<img src="' . esc_url( $logo ) . '" alt="">' : 'Plan A' ) . '</span></div>';
		$html .= '<div class="papb-ticket__main">';
		$html .= '<span class="papb-ticket__label">' . esc_html__( 'Poklon bon za izlet', 'plan-a-poklon-bon' ) . '</span>';
		$html .= '<span class="papb-ticket__amount" data-papb-out="amount">' . esc_html( $amount > 0 ? Plan_A_Bon_Voucher::money_short( $amount ) : '– €' ) . '</span>';
		$html .= '<span class="papb-ticket__line"><span class="papb-ticket__lbl">' . esc_html__( 'Za:', 'plan-a-poklon-bon' ) . '</span> <strong class="papb-ticket__val' . $to[1] . '" data-papb-out="to" data-empty="' . esc_attr__( 'Ime primatelja', 'plan-a-poklon-bon' ) . '">' . $to[0] . '</strong></span>';
		$html .= '<span class="papb-ticket__line papb-ticket__line--from"><span class="papb-ticket__lbl">' . esc_html__( 'Od:', 'plan-a-poklon-bon' ) . '</span> <strong class="papb-ticket__val' . $from[1] . '" data-papb-out="from" data-empty="' . esc_attr__( 'Tvoje ime', 'plan-a-poklon-bon' ) . '">' . $from[0] . '</strong></span>';
		$html .= '<em class="papb-ticket__msg" data-papb-out="message"' . ( '' === (string) $val['message'] ? ' hidden' : '' ) . '>' . esc_html( '' !== (string) $val['message'] ? '„' . $val['message'] . '“' : '' ) . '</em>';
		/* translators: %s: datum */
		$html .= '<span class="papb-ticket__valid">' . esc_html( sprintf( __( 'Vrijedi do: %s', 'plan-a-poklon-bon' ), Plan_A_Bon_Voucher::hr_date( Plan_A_Bon_Voucher::default_expiry() ) ) ) . '</span>';
		$html .= '</div>';
		$html .= '<div class="papb-ticket__stub"><span class="papb-ticket__qr">' . Plan_A_Bon_Render::qr_svg( home_url( '/izleti/' ), '#c9d3de' ) . '</span>';
		$html .= '<span class="papb-ticket__code">PLANA<br>••••-••••</span><span class="papb-ticket__site">' . esc_html( Plan_A_Bon_Render::host() ) . '</span></div>';
		$html .= '</div></div>';
		return $html;
	}

	private static function error( array $errors, string $field ) {
		if ( ! empty( $errors[ $field ] ) ) {
			echo '<span class="papb-error" role="alert">' . esc_html( $errors[ $field ] ) . '</span>';
		}
	}

	private static function logo_url(): string {
		$id = (int) Plan_A_Bon_Settings::get( 'logo' );
		if ( ! $id && class_exists( 'Plan_A_Izleti_Settings' ) ) {
			$id = (int) ( Plan_A_Izleti_Settings::get()['logo_id'] ?? 0 );
		}
		if ( ! $id ) {
			$id = (int) get_theme_mod( 'custom_logo', 0 );
		}
		return $id ? (string) wp_get_attachment_image_url( $id, 'medium' ) : '';
	}

	/**
	 * Boje kao u košarici (Plan A košarica), inače zadane.
	 */
	private static function colors(): array {
		$colors = array(
			'navy'   => '#12304b',
			'accent' => '#1a9ad6',
			'cta'    => '#e8862a',
		);
		if ( class_exists( 'Plan_A_Kosarica_Settings' ) ) {
			foreach ( array_keys( $colors ) as $key ) {
				$value = sanitize_hex_color( (string) Plan_A_Kosarica_Settings::get( $key ) );
				if ( $value ) {
					$colors[ $key ] = $value;
				}
			}
		}
		return $colors;
	}
}
