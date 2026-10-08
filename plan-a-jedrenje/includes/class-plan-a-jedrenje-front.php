<?php
/**
 * Shortcode [plan-a-jedrenje] (rezervacijski blok) i AJAX za kalendar i zahtjev.
 *
 * Stanja tjedana i nonce uvijek se dohvaćaju svježe (admin-ajax.php, bez predmemorije),
 * pa blok radi i kad je sama stranica u predmemoriji. Sve se provjerava na poslužitelju.
 *
 * @package Plan_A_Jedrenje
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Jedrenje_Front {

	const TAG = 'plan-a-jedrenje';

	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		foreach ( array( 'paj_weeks', 'paj_request' ) as $action ) {
			add_action( 'wp_ajax_' . $action, array( __CLASS__, $action ) );
			add_action( 'wp_ajax_nopriv_' . $action, array( __CLASS__, $action ) );
		}
	}

	public static function assets() {
		wp_register_style( 'plan-a-jedrenje', PLAN_A_JEDRENJE_URL . 'assets/css/jedrenje.css', array(), PLAN_A_JEDRENJE_VERSION );
		wp_register_script( 'plan-a-jedrenje', PLAN_A_JEDRENJE_URL . 'assets/js/jedrenje.js', array(), PLAN_A_JEDRENJE_VERSION, array( 'in_footer' => true ) );
		$post = get_post();
		if ( is_singular() && $post && has_shortcode( $post->post_content, self::TAG ) ) {
			wp_enqueue_style( 'plan-a-jedrenje' );
			if ( wp_style_is( 'plan-a-izleti', 'registered' ) ) {
				wp_enqueue_style( 'plan-a-izleti' );
			}
		}
	}

	/**
	 * Podaci za skriptu (bez osobnih podataka).
	 */
	private static function config(): array {
		$s = Plan_A_Jedrenje_Data::get();
		return array(
			'ajax'        => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'paj_request' ),
			'weeks'       => Plan_A_Jedrenje_Data::public_weeks(),
			'min'         => Plan_A_Jedrenje_Data::min_persons(),
			'max'         => Plan_A_Jedrenje_Data::max_persons(),
			'pct'         => (int) $s['deposit_pct'],
			'depositDays' => (int) $s['deposit_days'],
			'restDays'    => (int) $s['rest_days'],
			'title'       => (string) $s['title'],
			'boat'        => (string) $s['boat'],
			'marina'      => (string) $s['marina'],
			'embark'      => (string) $s['embark_time'],
			'url'         => add_query_arg(
				array(
					'utm_source'   => 'whatsapp',
					'utm_medium'   => 'share',
					'utm_campaign' => 'predlozi_ekipi',
				),
				(string) get_permalink()
			),
			'today'       => Plan_A_Jedrenje_Data::today(),
		);
	}

	private static function icon( string $name ): string {
		$paths = array(
			'boat'  => '<path d="M12 3v13M12 4l7 10h-7M12 7l-5 7h5"/><path d="M3 17h18l-2 3H5z"/>',
			'pin'   => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
			'users' => '<circle cx="9" cy="7" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><path d="M16 4.5a3 3 0 0 1 0 5.8M18 14.5c1.8.9 3 2.9 3 5.5"/>',
			'check' => '<path d="m5 12.5 4.5 4.5L19 7"/>',
			'left'  => '<path d="m15 6-6 6 6 6"/>',
			'right' => '<path d="m9 6 6 6-6 6"/>',
		);
		return '<svg class="pajd-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}

	private static function share_button( string $url ): string {
		$s    = Plan_A_Jedrenje_Data::get();
		$text = "Hej, predlažem da idemo zajedno na jedrenje ⛵\n*" . $s['title'] . "*\n🛥️ " . $s['boat'] . ', ' . $s['marina'] . "\nIdeš i ti? Javi pa rezerviramo zajedno 👇";
		$wa   = 'https://wa.me/?text=' . rawurlencode( $text . "\n" . $url );
		$icon = '<svg class="paiz-wa-icon" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true" focusable="false"><path d="M17.47 14.38c-.3-.15-1.75-.86-2.02-.96-.27-.1-.47-.15-.67.15-.2.3-.77.96-.94 1.16-.17.2-.35.22-.64.07-.3-.15-1.25-.46-2.38-1.47-.88-.79-1.47-1.76-1.65-2.06-.17-.3-.02-.46.13-.6.13-.14.3-.35.45-.52.15-.18.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.61-.92-2.2-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.06 2.88 1.21 3.08.15.2 2.1 3.2 5.08 4.48.71.31 1.26.49 1.69.63.71.22 1.36.19 1.87.12.57-.09 1.75-.72 2-1.41.25-.69.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35z"/><path d="M12.04 2C6.5 2 2 6.48 2 12c0 1.77.46 3.5 1.34 5.02L2 22l5.12-1.34A10.03 10.03 0 0 0 12.04 22C17.56 22 22 17.52 22 12S17.56 2 12.04 2zm0 18.3c-1.5 0-2.97-.4-4.25-1.16l-.3-.18-3.04.8.81-2.96-.2-.31A8.26 8.26 0 0 1 3.77 12c0-4.56 3.71-8.27 8.27-8.27 4.56 0 8.25 3.71 8.25 8.27 0 4.57-3.7 8.3-8.25 8.3z"/></svg>';
		// Isti gumb i ista skripta kao "Predloži ekipi" u dodatku Plan A izleti (podaci se čitaju pri kliku).
		return '<div class="paiz-share-wrap pajd-share"><a class="paiz-share-btn" href="' . esc_attr( $wa ) . '" target="_blank" rel="noopener noreferrer" data-paiz-share data-pajd-share'
			. ' data-paiz-share-title="' . esc_attr( $s['title'] ) . '" data-paiz-share-text="' . esc_attr( $text ) . '" data-paiz-share-url="' . esc_url( $url ) . '"'
			. ' aria-label="Predloži ekipi (WhatsApp)">' . $icon . '<span>Predloži ekipi</span></a></div>';
	}

	/**
	 * Tko vas vodi, važno znati i kontakt.
	 */
	private static function about(): string {
		$leaders   = Plan_A_Jedrenje_Data::lines( 'leaders' );
		$important = Plan_A_Jedrenje_Data::lines( 'important' );
		$name      = trim( (string) Plan_A_Jedrenje_Data::value( 'contact_name' ) );
		$phone     = trim( (string) Plan_A_Jedrenje_Data::value( 'contact_phone' ) );
		$tel       = preg_replace( '/[^0-9+]/', '', $phone );
		if ( ! $leaders && ! $important && '' === $tel ) {
			return '';
		}
		$html = '<section class="pajd-card pajd-about" aria-label="Vodstvo i važne informacije"><div class="pajd-about__grid">';
		if ( $leaders ) {
			$html .= '<div><h3 class="pajd-h3">Tko vas vodi</h3>';
			foreach ( $leaders as $line ) {
				$parts = explode( ':', $line, 2 );
				$html .= 2 === count( $parts ) && strlen( $parts[0] ) < 20
					? '<p><strong>' . esc_html( $parts[0] ) . ':</strong>' . esc_html( $parts[1] ) . '</p>'
					: '<p>' . esc_html( $line ) . '</p>';
			}
			$html .= '</div>';
		}
		if ( $important ) {
			$html .= '<div><h3 class="pajd-h3">Važno znati</h3><ul class="pajd-important">';
			foreach ( $important as $line ) {
				$html .= '<li>' . esc_html( $line ) . '</li>';
			}
			$html .= '</ul></div>';
		}
		$html .= '</div>';
		if ( strlen( $tel ) >= 6 ) {
			$html .= '<p class="pajd-contact">Pitanja i dogovor: ' . ( '' !== $name ? esc_html( $name ) . ', ' : '' )
				. '<a href="' . esc_attr( 'tel:' . $tel ) . '">' . esc_html( $phone ) . '</a></p>';
		}
		return $html . '</section>';
	}

	public static function render(): string {
		if ( ! function_exists( 'WC' ) ) {
			return '';
		}
		$post = get_post();
		if ( $post && (int) get_option( Plan_A_Jedrenje_Booking::PAGE_OPTION ) !== (int) $post->ID && 'publish' === $post->post_status ) {
			update_option( Plan_A_Jedrenje_Booking::PAGE_OPTION, (int) $post->ID, false );
		}
		wp_enqueue_style( 'plan-a-jedrenje' );
		wp_enqueue_script( 'plan-a-jedrenje' );
		if ( wp_script_is( 'plan-a-izleti', 'registered' ) ) {
			wp_enqueue_style( 'plan-a-izleti' );
			wp_enqueue_script( 'plan-a-izleti' );
		}

		$s      = Plan_A_Jedrenje_Data::get();
		$config = self::config();
		$prices = array_column( $config['weeks'], 'price' );
		$from   = $prices ? min( $prices ) : (float) $s['base_price'];
		$min    = Plan_A_Jedrenje_Data::min_persons();
		$max    = Plan_A_Jedrenje_Data::max_persons();
		$routes   = Plan_A_Jedrenje_Data::items( 'routes' );
		$concepts = Plan_A_Jedrenje_Data::items( 'concepts' );
		$terms  = Plan_A_Jedrenje_Data::lines( 'cancel_terms' );

		ob_start();
		?>
		<div class="pajd" data-pajd>
			<script type="application/json" data-pajd-config><?php echo wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ); ?></script>

			<?php
			$slider = Plan_A_Jedrenje_Slider::render( Plan_A_Jedrenje_Slider::image_ids( Plan_A_Jedrenje_Slider::tour_id() ) );
			echo $slider; // phpcs:ignore WordPress.Security.EscapeOutput -- escapirano u render().
			?>

			<section class="pajd-card pajd-intro">
				<p class="pajd-kicker"><?php echo '' === $slider ? 'Rezervacija' : 'Rezervacija · što dobivaš'; ?></p>
				<?php if ( '' === $slider ) : // naziv je već na slajderu ?>
					<h2 class="pajd-title"><?php echo esc_html( $s['title'] ); ?></h2>
				<?php endif; ?>
				<ul class="pajd-facts">
					<li><?php echo self::icon( 'boat' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><strong><?php echo esc_html( $s['boat'] ); ?></strong> · <?php echo (int) $s['cabins']; ?> kabine · najviše <?php echo (int) $max; ?> gostiju i <?php echo esc_html( $s['skipper'] ); ?></span></li>
					<li><?php echo self::icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><strong><?php echo esc_html( $s['marina'] ); ?></strong> · subota do subote, ukrcaj od <?php echo esc_html( $s['embark_time'] ); ?>, iskrcaj do <?php echo esc_html( $s['disembark_time'] ); ?></span></li>
					<li><?php echo self::icon( 'users' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span>Ekipa od <strong><?php echo (int) $min; ?> do <?php echo (int) $max; ?> osoba</strong></span></li>
				</ul>
				<p class="pajd-from">od <strong><?php echo esc_html( Plan_A_Jedrenje_Data::money( $from ) ); ?></strong> <span>za cijeli brod</span>
					<?php if ( (float) $s['base_regular'] > $from ) : ?>
						<del><?php echo esc_html( Plan_A_Jedrenje_Data::money( (float) $s['base_regular'] ) ); ?></del>
					<?php endif; ?>
				</p>
				<?php if ( $concepts || $routes ) : ?>
					<ul class="pajd-offer">
						<?php if ( $concepts ) : ?>
							<li><strong>Sadržaj po želji:</strong> <?php echo esc_html( implode( ' · ', array_column( $concepts, 'title' ) ) ); ?></li>
						<?php endif; ?>
						<?php if ( $routes ) : ?>
							<li><strong>Rute:</strong> <?php echo esc_html( implode( ' · ', array_column( $routes, 'title' ) ) ); ?></li>
						<?php endif; ?>
					</ul>
				<?php endif; ?>
				<div class="pajd-incl">
					<p class="pajd-incl__title">Uključeno</p>
					<ul class="pajd-checks">
						<?php foreach ( Plan_A_Jedrenje_Data::lines( 'included' ) as $line ) : ?>
							<li><?php echo self::icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( $line ); ?></li>
						<?php endforeach; ?>
					</ul>
					<?php $ex = Plan_A_Jedrenje_Data::lines( 'excluded' ); ?>
					<?php if ( $ex ) : ?>
						<p class="pajd-excl"><strong>Nije uključeno:</strong> <?php echo esc_html( implode( '; ', $ex ) ); ?>.</p>
					<?php endif; ?>
				</div>
			</section>

			<section class="pajd-card pajd-cal" id="pajd-kalendar" aria-labelledby="pajd-cal-title">
				<div class="pajd-cal__head">
					<h3 class="pajd-h3" id="pajd-cal-title">Odaberi tjedan</h3>
					<ul class="pajd-legend" aria-label="Oznake">
						<li><span class="pajd-dot pajd-dot--free"></span>Slobodno</li>
						<li><span class="pajd-dot pajd-dot--request"></span>Na upitu</li>
						<li><span class="pajd-dot pajd-dot--booked"></span>Zauzeto</li>
					</ul>
				</div>
				<div class="pajd-months" data-pajd-months>
					<p class="pajd-loading">Učitavam slobodne tjedne…</p>
				</div>
				<noscript><p class="pajd-msg">Za rezervaciju uključi JavaScript ili nam se javi e-mailom.</p></noscript>
				<p class="pajd-cal__note">Svi tjedni su od subote do subote, cijena je za cijeli brod. Tjedan „Na upitu” još možeš zatražiti.</p>
			</section>

			<section class="pajd-card pajd-book" data-pajd-book hidden aria-labelledby="pajd-book-title">
				<h3 class="pajd-h3" id="pajd-book-title">Tvoj tjedan</h3>
				<dl class="pajd-sum" data-pajd-sum></dl>
				<div class="pajd-price">
					<p class="pajd-price__main"><span data-pajd-price></span> <span class="pajd-price__unit">za cijeli brod</span> <del data-pajd-regular hidden></del></p>
					<p class="pajd-price__pp" data-pajd-pp></p>
					<p class="pajd-price__note">Cijena je za cijeli brod. Najmanji broj sudionika je <?php echo (int) $min; ?>.</p>
				</div>

				<form class="pajd-form" data-pajd-form novalidate>
					<input type="hidden" name="week" value="">
					<p class="pajd-trap" aria-hidden="true"><label>Web <input type="text" name="web" tabindex="-1" autocomplete="off"></label></p>

					<div class="pajd-grid">
						<div class="pajd-field">
							<label for="pajd-persons">Broj osoba</label>
							<select id="pajd-persons" name="persons" data-pajd-persons>
								<?php for ( $n = $min; $n <= $max; $n++ ) : ?>
									<option value="<?php echo (int) $n; ?>" <?php selected( $n, $max ); ?>><?php echo (int) $n; ?></option>
								<?php endfor; ?>
							</select>
						</div>
					</div>

					<?php if ( $concepts ) : ?>
						<fieldset class="pajd-routes pajd-concepts">
							<legend>Što želite raditi? <span class="pajd-opt">(može više)</span></legend>
							<?php foreach ( $concepts as $c ) : ?>
								<label class="pajd-route pajd-route--multi"><input type="checkbox" name="concept[]" value="<?php echo esc_attr( $c['title'] ); ?>"><span><strong><?php echo esc_html( $c['title'] ); ?></strong><?php if ( $c['text'] ) : ?><small><?php echo esc_html( implode( ' ', $c['text'] ) ); ?></small><?php endif; ?></span></label>
							<?php endforeach; ?>
							<p class="pajd-hint">Sadržaje možete kombinirati, a program složimo zajedno.</p>
						</fieldset>
					<?php endif; ?>

					<fieldset class="pajd-routes">
						<legend>Željena ruta</legend>
						<?php foreach ( $routes as $i => $route ) : ?>
							<?php $days = array_slice( $route['text'], 1 ); ?>
							<div class="pajd-route-wrap">
								<label class="pajd-route"><input type="radio" name="route" value="<?php echo esc_attr( $route['title'] ); ?>" <?php checked( 0, $i ); ?>><span><strong><?php echo esc_html( $route['title'] ); ?></strong><?php if ( $route['text'] ) : ?><small><?php echo esc_html( $route['text'][0] ); ?></small><?php endif; ?></span></label>
								<?php if ( $days ) : ?>
									<details class="pajd-days">
										<summary>Plan po danima</summary>
										<ul>
											<?php foreach ( $days as $d ) : ?>
												<?php $parts = explode( ':', $d, 2 ); ?>
												<li><?php if ( 2 === count( $parts ) && strlen( $parts[0] ) < 20 ) : ?><strong><?php echo esc_html( $parts[0] ); ?></strong><?php echo esc_html( ':' . $parts[1] ); ?><?php else : ?><?php echo esc_html( $d ); ?><?php endif; ?></li>
											<?php endforeach; ?>
										</ul>
										<p class="pajd-days__note">Konačnu rutu svaki dan određuje skiper prema vremenskoj prognozi i stanju mora.</p>
									</details>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</fieldset>

					<div class="pajd-field">
						<label for="pajd-note">Napomena <span class="pajd-opt">(neobavezno)</span></label>
						<textarea id="pajd-note" name="note" rows="3" maxlength="1000" placeholder="npr. iskustvo ekipe, posebne želje"></textarea>
					</div>

					<p class="pajd-sub">Tvoji podaci</p>
					<div class="pajd-grid pajd-grid--2">
						<div class="pajd-field"><label for="pajd-first">Ime</label><input id="pajd-first" name="first" type="text" autocomplete="given-name" required maxlength="60"></div>
						<div class="pajd-field"><label for="pajd-last">Prezime</label><input id="pajd-last" name="last" type="text" autocomplete="family-name" required maxlength="60"></div>
						<div class="pajd-field"><label for="pajd-email">E-mail</label><input id="pajd-email" name="email" type="email" autocomplete="email" inputmode="email" required maxlength="190"></div>
						<div class="pajd-field"><label for="pajd-phone">Mobitel</label><input id="pajd-phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" required maxlength="30"></div>
						<div class="pajd-field pajd-field--wide"><label for="pajd-address">Ulica i kućni broj</label><input id="pajd-address" name="address" type="text" autocomplete="address-line1" required maxlength="100"></div>
						<div class="pajd-field"><label for="pajd-postcode">Poštanski broj</label><input id="pajd-postcode" name="postcode" type="text" autocomplete="postal-code" inputmode="numeric" required maxlength="10"></div>
						<div class="pajd-field"><label for="pajd-city">Mjesto</label><input id="pajd-city" name="city" type="text" autocomplete="address-level2" required maxlength="60"></div>
					</div>

					<?php if ( $terms ) : ?>
						<details class="pajd-terms">
							<summary>Uvjeti otkaza</summary>
							<ul>
								<?php foreach ( $terms as $t ) : ?>
									<li><?php echo esc_html( $t ); ?></li>
								<?php endforeach; ?>
							</ul>
						</details>
					<?php endif; ?>
					<label class="pajd-check"><input type="checkbox" name="terms" value="1" required> <span>Prihvaćam uvjete otkaza i obradu podataka za ovu rezervaciju.</span></label>

					<p class="pajd-msg" data-pajd-msg role="alert" hidden></p>
					<button type="submit" class="pajd-btn pajd-btn--cta" data-pajd-submit>Pošalji zahtjev za rezervaciju</button>
					<p class="pajd-after">Zahtjev ne obvezuje na plaćanje: najprije provjeravamo je li brod slobodan u charter bazi i javljamo se u roku 48 sati s uplatnicom za akontaciju.</p>
				</form>

				<div class="pajd-done" data-pajd-done hidden tabindex="-1"></div>
			</section>

			<?php echo self::about(); // phpcs:ignore WordPress.Security.EscapeOutput -- escapirano u about(). ?>

			<?php echo self::share_button( $config['url'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escapirano u share_button(). ?>

			<p class="pajd-organizer"><?php echo esc_html( $s['organizer'] ); ?></p>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/* ---------------------------------------------------------------------
	 * AJAX
	 * ------------------------------------------------------------------- */

	public static function paj_weeks() {
		nocache_headers();
		wp_send_json_success(
			array(
				'weeks' => Plan_A_Jedrenje_Data::public_weeks(),
				'nonce' => wp_create_nonce( 'paj_request' ),
			)
		);
	}

	private static function fail( string $message, string $field = '' ) {
		wp_send_json_error(
			array(
				'message' => $message,
				'field'   => $field,
			)
		);
	}

	private static function text( string $key, int $max ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce se provjerava u request().
		$value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
	}

	public static function paj_request() {
		nocache_headers();
		if ( ! check_ajax_referer( 'paj_request', 'nonce', false ) ) {
			self::fail( 'Stranica je bila dugo otvorena. Osvježi je i pošalji zahtjev ponovno.' );
		}
		if ( '' !== self::text( 'web', 50 ) ) {
			self::fail( 'Zahtjev nije poslan.' );
		}

		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key   = 'paj_rl_' . md5( $ip . wp_salt( 'nonce' ) );
		$tries = (int) get_transient( $key );
		if ( $tries >= 5 ) {
			self::fail( 'Previše zahtjeva u kratkom vremenu. Pokušaj ponovno za desetak minuta ili nam se javi e-mailom.' );
		}

		$week = Plan_A_Jedrenje_Data::valid_date( self::text( 'week', 10 ) );
		if ( ! $week || ! Plan_A_Jedrenje_Data::is_season_week( $week ) ) {
			self::fail( 'Odaberi tjedan u kalendaru.', 'week' );
		}
		$state = Plan_A_Jedrenje_Data::state( $week );
		if ( 'booked' === $state || 'past' === $state ) {
			self::fail( 'Ovaj tjedan više nije slobodan. Odaberi drugi tjedan.', 'week' );
		}

		$persons = (int) self::text( 'persons', 3 );
		if ( $persons < Plan_A_Jedrenje_Data::min_persons() || $persons > Plan_A_Jedrenje_Data::max_persons() ) {
			self::fail( 'Broj osoba mora biti od ' . Plan_A_Jedrenje_Data::min_persons() . ' do ' . Plan_A_Jedrenje_Data::max_persons() . '.', 'persons' );
		}
		$route  = self::text( 'route', 200 );
		$routes = Plan_A_Jedrenje_Data::titles( 'routes' );
		if ( $routes && ! in_array( $route, $routes, true ) ) {
			self::fail( 'Odaberi rutu.', 'route' );
		}
		// Sadržaji (može više, neobavezno): samo nazivi iz postavki.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- provjereno gore.
		$picked   = isset( $_POST['concept'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['concept'] ) ) : array();
		$concepts = Plan_A_Jedrenje_Data::titles( 'concepts' );
		if ( array_diff( $picked, $concepts ) ) {
			self::fail( 'Odabrani sadržaj više ne postoji. Osvježi stranicu.', 'concept[]' );
		}
		$concept = implode( ', ', array_values( array_intersect( $concepts, $picked ) ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- provjereno gore.
		$note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		$note = function_exists( 'mb_substr' ) ? mb_substr( $note, 0, 1000 ) : substr( $note, 0, 1000 );

		$in = array(
			'week'     => $week,
			'persons'  => $persons,
			'route'    => $route,
			'concept'  => $concept,
			'note'     => $note,
			'first'    => self::text( 'first', 60 ),
			'last'     => self::text( 'last', 60 ),
			'email'    => sanitize_email( self::text( 'email', 190 ) ),
			'phone'    => self::text( 'phone', 30 ),
			'address'  => self::text( 'address', 100 ),
			'postcode' => self::text( 'postcode', 10 ),
			'city'     => self::text( 'city', 60 ),
		);
		$labels = array(
			'first'    => 'ime',
			'last'     => 'prezime',
			'address'  => 'ulicu i kućni broj',
			'postcode' => 'poštanski broj',
			'city'     => 'mjesto',
		);
		foreach ( $labels as $field => $label ) {
			if ( '' === $in[ $field ] ) {
				self::fail( 'Upiši ' . $label . '.', $field );
			}
		}
		if ( ! is_email( $in['email'] ) ) {
			self::fail( 'Upiši ispravnu e-mail adresu.', 'email' );
		}
		if ( ! preg_match( '/^[0-9+()\/\-\s]{6,30}$/', $in['phone'] ) ) {
			self::fail( 'Upiši broj mobitela.', 'phone' );
		}
		if ( empty( $_POST['terms'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			self::fail( 'Za slanje zahtjeva prihvati uvjete otkaza.', 'terms' );
		}

		set_transient( $key, $tries + 1, 10 * MINUTE_IN_SECONDS );
		$id = Plan_A_Jedrenje_Booking::create_request( $in );
		if ( is_wp_error( $id ) ) {
			self::fail( $id->get_error_message(), 'week' );
		}
		$r = Plan_A_Jedrenje_Booking::get( (int) $id );
		wp_send_json_success(
			array(
				'title'   => 'Zahtjev je zaprimljen!',
				'message' => 'Potvrdit ćemo slobodan brod u roku 48 sati. Poslali smo ti e-mail s podacima na ' . $r['email'] . '.',
				'week'    => Plan_A_Jedrenje_Data::week_long( $week ),
				'price'   => Plan_A_Jedrenje_Data::money( (float) $r['price'] ) . ' za cijeli brod',
			)
		);
	}
}
