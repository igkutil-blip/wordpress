<?php
/**
 * Shortcode [plan-a-kalkulator]: obrazac i mjesto za rezultat. Računa skripta u pregledniku.
 *
 * Atributi (svi neobavezni):
 *   km="12"      duljina rute u km
 *   uspon="800"  ukupni uspon u m
 *   spust="800"  ukupni spust u m (prazno = kao uspon)
 *   naslov="no"  bez naslova i uvoda (npr. na stranici izleta)
 *
 * @package Plan_A_Kalkulator
 */

defined( 'ABSPATH' ) || exit;

class Plan_A_Kalkulator {

	const TAG = 'plan-a-kalkulator';

	/** @var int Broj kalkulatora na stranici (jedinstveni id-jevi). */
	private static $count = 0;

	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function assets() {
		wp_register_style( 'plan-a-kalkulator', PLAN_A_KALK_URL . 'assets/css/kalkulator.css', array(), PLAN_A_KALK_VERSION );
		wp_register_script( 'plan-a-kalkulator', PLAN_A_KALK_URL . 'assets/js/kalkulator.js', array(), PLAN_A_KALK_VERSION, array( 'in_footer' => true ) );
		$post = get_post();
		if ( is_singular() && $post && has_shortcode( $post->post_content, self::TAG ) ) {
			wp_enqueue_style( 'plan-a-kalkulator' );
		}
	}

	/**
	 * Broj iz atributa ("12,5" ili "12.5"), ograničen na razuman raspon; prazno ako nije broj.
	 */
	private static function num( $value, float $max ): string {
		$value = str_replace( ',', '.', trim( (string) $value ) );
		if ( '' === $value || ! is_numeric( $value ) ) {
			return '';
		}
		$value = max( 0, min( $max, (float) $value ) );
		return str_replace( '.', ',', (string) round( $value, 1 ) );
	}

	/**
	 * Skupina gumba za odabir (radio).
	 *
	 * @param string $name    Naziv polja.
	 * @param string $legend  Naslov skupine.
	 * @param array  $options vrijednost => array( naslov, opis ).
	 * @param string $checked Zadana vrijednost.
	 * @param string $class   Dodatna klasa (izgled).
	 */
	private static function choice( string $name, string $legend, array $options, string $checked, string $class = '' ): string {
		$id   = 'pakl' . self::$count . '-' . $name;
		$html = '<fieldset class="pakl-choice ' . esc_attr( $class ) . '"><legend class="pakl-label">' . esc_html( $legend ) . '</legend><div class="pakl-choice__grid">';
		foreach ( $options as $value => $text ) {
			$html .= '<label class="pakl-option"><input type="radio" name="' . esc_attr( $id ) . '" data-pakl-field="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . checked( $checked, $value, false ) . '>'
				. '<span class="pakl-option__box"><span class="pakl-option__title">' . esc_html( $text[0] ) . '</span>'
				. ( ! empty( $text[1] ) ? '<span class="pakl-option__hint">' . esc_html( $text[1] ) . '</span>' : '' )
				. '</span></label>';
		}
		return $html . '</div></fieldset>';
	}

	/**
	 * Polje za broj (tekst s decimalnom tipkovnicom, da prima i zarez).
	 */
	private static function field( string $name, string $label, string $unit, string $value, string $help = '', string $placeholder = '' ): string {
		$id   = 'pakl' . self::$count . '-' . $name;
		$html = '<div class="pakl-field"><label class="pakl-label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>'
			. '<div class="pakl-input"><input type="text" inputmode="decimal" autocomplete="off" id="' . esc_attr( $id ) . '" data-pakl-field="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"'
			. ( $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '' )
			. ( $help ? ' aria-describedby="' . esc_attr( $id ) . '-help"' : '' ) . '>'
			. '<span class="pakl-input__unit" aria-hidden="true">' . esc_html( $unit ) . '</span></div>';
		if ( $help ) {
			$html .= '<p class="pakl-help" id="' . esc_attr( $id ) . '-help">' . esc_html( $help ) . '</p>';
		}
		return $html . '</div>';
	}

	private static function icon( string $name ): string {
		$paths = array(
			'route'  => '<path d="M3 20l5-9 4 5 3-4 6 8z"/><path d="M14.5 6.5a2 2 0 1 0 0-.01"/>',
			'trail'  => '<path d="M4 20c3-2 4-5 8-6s5-4 8-8"/><path d="M4 20h4M16 6h4v4"/>',
			'people' => '<circle cx="9" cy="7" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><path d="M16 4.5a3 3 0 0 1 0 5.8M18 14.5c1.8.9 3 2.9 3 5.5"/>',
			'sun'    => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
		);
		return '<svg class="pakl-icon" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}

	public static function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'km'     => '10',
				'uspon'  => '600',
				'spust'  => '',
				'naslov' => 'yes',
			),
			$atts,
			self::TAG
		);

		++self::$count;
		wp_enqueue_style( 'plan-a-kalkulator' );
		wp_enqueue_script( 'plan-a-kalkulator' );

		$km    = self::num( $atts['km'], 200 ) ?: '10';
		$uspon = self::num( $atts['uspon'], 9000 ) ?: '0';
		$spust = self::num( $atts['spust'], 9000 );

		ob_start();
		?>
		<div class="pakl" data-pakl>
			<?php if ( 'no' !== $atts['naslov'] ) : ?>
				<header class="pakl-head">
					<p class="pakl-kicker">Planinarski kalkulator</p>
					<h2 class="pakl-title">Koliko će trajati tura?</h2>
					<p class="pakl-lead">Upiši duljinu i visinsku razliku rute. Dobit ćeš procjenu vremena hoda s odmorima, povratka, vode i energije – prilagođenu kondiciji, stazi i vremenu.</p>
				</header>
			<?php endif; ?>

			<noscript><p class="pakl-noscript">Kalkulator radi u pregledniku: uključi JavaScript da vidiš procjenu.</p></noscript>

			<div class="pakl-wrap">
			<form class="pakl-layout" data-pakl-form novalidate>
				<div class="pakl-form">
					<section class="pakl-card">
						<h3 class="pakl-h3"><?php echo self::icon( 'route' ); // phpcs:ignore WordPress.Security.EscapeOutput -- statični SVG. ?>Ruta</h3>
						<div class="pakl-fields pakl-fields--3">
							<?php
							echo self::field( 'km', 'Duljina', 'km', $km ); // phpcs:ignore WordPress.Security.EscapeOutput -- escapirano u field().
							echo self::field( 'uspon', 'Ukupni uspon', 'm', $uspon ); // phpcs:ignore WordPress.Security.EscapeOutput
							echo self::field( 'spust', 'Ukupni spust', 'm', $spust, 'Prazno = kao uspon (kružna ili povratna ruta).', 'kao uspon' ); // phpcs:ignore WordPress.Security.EscapeOutput
							?>
						</div>
						<p class="pakl-msg" data-pakl-msg hidden></p>
					</section>

					<section class="pakl-card">
						<h3 class="pakl-h3"><?php echo self::icon( 'trail' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>Staza</h3>
						<?php
						echo self::choice( // phpcs:ignore WordPress.Security.EscapeOutput -- escapirano u choice().
							'teren',
							'Kakva je podloga?',
							array(
								'cesta'     => array( 'Cesta, makadam', 'široki put, ravna podloga' ),
								'staza'     => array( 'Planinarska staza', 'uređena, markirana' ),
								'kamenjar'  => array( 'Kamenjar, korijenje', 'krš, neravno, sklisko' ),
								'zahtjevno' => array( 'Zahtjevno', 'sipar, osigurani put, strmo' ),
								'snijeg'    => array( 'Snijeg, bespuće', 'gaz kroz snijeg, bez staze' ),
							),
							'staza',
							'pakl-choice--cards'
						);
						?>
					</section>

					<section class="pakl-card">
						<h3 class="pakl-h3"><?php echo self::icon( 'people' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>Tko hoda</h3>
						<?php
						echo self::choice( // phpcs:ignore WordPress.Security.EscapeOutput
							'kondicija',
							'Kondicija',
							array(
								'slaba'    => array( 'Slabija', 'rijetko hodam' ),
								'prosjek'  => array( 'Prosječna', 'povremeno u planini' ),
								'dobra'    => array( 'Dobra', 'redovito na turama' ),
								'odlicna'  => array( 'Odlična', 'brz hod, treniran' ),
							),
							'prosjek',
							'pakl-choice--4'
						);
						echo self::choice( // phpcs:ignore WordPress.Security.EscapeOutput
							'grupa',
							'Hodam',
							array(
								'sam'    => array( 'Sam ili u paru', '' ),
								'mala'   => array( 'Manja grupa', 'do 10 osoba' ),
								'velika' => array( 'Veća grupa', 'više od 10' ),
							),
							'sam'
						);
						?>
						<div class="pakl-fields pakl-fields--2">
							<?php
							echo self::field( 'tezina', 'Tvoja težina', 'kg', '75' ); // phpcs:ignore WordPress.Security.EscapeOutput
							echo self::field( 'ruksak', 'Ruksak', 'kg', '7', 'Za jednodnevnu turu obično 5–8 kg.' ); // phpcs:ignore WordPress.Security.EscapeOutput
							?>
						</div>
					</section>

					<section class="pakl-card">
						<h3 class="pakl-h3"><?php echo self::icon( 'sun' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>Uvjeti i plan</h3>
						<div class="pakl-fields pakl-fields--3">
							<?php echo self::field( 'temp', 'Temperatura', '°C', '20' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<div class="pakl-field">
								<label class="pakl-label" for="pakl<?php echo (int) self::$count; ?>-datum">Datum ture</label>
								<div class="pakl-input"><input type="date" id="pakl<?php echo (int) self::$count; ?>-datum" data-pakl-field="datum" value="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>"></div>
							</div>
							<div class="pakl-field">
								<label class="pakl-label" for="pakl<?php echo (int) self::$count; ?>-polazak">Polazak</label>
								<div class="pakl-input"><input type="time" id="pakl<?php echo (int) self::$count; ?>-polazak" data-pakl-field="polazak" value="08:00" step="300"></div>
							</div>
						</div>
						<?php
						echo self::choice( // phpcs:ignore WordPress.Security.EscapeOutput
							'sunce',
							'Sunce',
							array(
								'hlad'  => array( 'Hlad, šuma', '' ),
								'djel'  => array( 'Djelomično', '' ),
								'jako'  => array( 'Otvoreno, jako sunce', '' ),
							),
							'djel'
						);
						echo self::choice( // phpcs:ignore WordPress.Security.EscapeOutput
							'vjetar',
							'Vjetar',
							array(
								'slab'    => array( 'Slab', '' ),
								'umjeren' => array( 'Umjeren', '' ),
								'jak'     => array( 'Jak, bura', '' ),
							),
							'slab'
						);
						echo self::choice( // phpcs:ignore WordPress.Security.EscapeOutput
							'odmori',
							'Odmori',
							array(
								'kratki'     => array( 'Kratki', '5 min na sat' ),
								'uobicajeni' => array( 'Uobičajeni', '10 min na sat + ručak' ),
								'opusteni'   => array( 'Opušteno', '15 min na sat + dulji odmor' ),
							),
							'uobicajeni'
						);
						?>
					</section>
				</div>

				<aside class="pakl-result" data-pakl-result aria-labelledby="pakl<?php echo (int) self::$count; ?>-res">
					<div class="pakl-total">
						<p class="pakl-total__label" id="pakl<?php echo (int) self::$count; ?>-res">Ukupno s odmorima</p>
						<p class="pakl-total__value" data-pakl-out="total" aria-live="polite">–</p>
						<p class="pakl-total__range" data-pakl-out="range"></p>
						<div class="pakl-split">
							<p><span>Hod</span><strong data-pakl-out="walk">–</strong></p>
							<p><span>Odmori</span><strong data-pakl-out="breaks">–</strong></p>
							<p><span>Povratak oko</span><strong data-pakl-out="back">–</strong></p>
						</div>
					</div>

					<div class="pakl-stats">
						<div class="pakl-stat pakl-stat--water">
							<p class="pakl-stat__label">Voda</p>
							<p class="pakl-stat__value" data-pakl-out="water">–</p>
							<p class="pakl-stat__hint" data-pakl-out="water-hint"></p>
						</div>
						<div class="pakl-stat pakl-stat--food">
							<p class="pakl-stat__label">Energija</p>
							<p class="pakl-stat__value" data-pakl-out="kcal">–</p>
							<p class="pakl-stat__hint" data-pakl-out="food-hint"></p>
						</div>
					</div>

					<ul class="pakl-notes" data-pakl-notes hidden></ul>

					<details class="pakl-how">
						<summary>Kako računamo?</summary>
						<div class="pakl-how__body">
							<p><strong>Vrijeme hoda</strong> po alpskom standardu DIN 33466 (planinarski savezi u Alpama): 4 km na sat po ravnom, 300 m uspona i 500 m spusta na sat; veće od dvaju vremena plus pola manjeg. Zatim se prilagođava kondiciji, grupi, podlozi, ruksaku, vrućini i vjetru.</p>
							<p><strong>Odmori</strong> se dodaju na vrijeme hoda, a <strong>povratak</strong> je polazak plus ukupno vrijeme. Raspon (oko −10 % do +15 %) pokazuje koliko se stvarne ture obično razlikuju od procjene.</p>
							<p><strong>Energija</strong> prema mjerenjima potrošnje pri hodu uzbrdo i nizbrdo (Minetti i sur.), za tvoju težinu i ruksak, s dodatkom za podlogu i osnovnu potrošnju tijela za cijelo trajanje ture.</p>
							<p><strong>Voda</strong> prema procjeni znojenja iz napora, temperature i sunca; preporuka je nadoknaditi oko 80 % gubitka. Na vrućini piti i elektrolite.</p>
							<p class="pakl-how__note">Procjena je okvirna i ne zamjenjuje opis staze, vremensku prognozu ni upute vodiča.</p>
						</div>
					</details>
				</aside>
			</form>
			</div>

			<a class="pakl-bar" href="#pakl<?php echo (int) self::$count; ?>-res" data-pakl-bar hidden>
				<span data-pakl-out="bar"></span>
				<span class="pakl-bar__more">Detalji</span>
			</a>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
