<?php
/**
 * Shortcode [plan-a-plan-izleta]: plan izleta po mjesecima sa statusom, prijavom,
 * obaviješću "Javi mi kad bude objavljen" i dodavanjem u kalendar.
 *
 * Atributi:
 *   godina="2026"      samo izleti te godine (zadano: svi nadolazeći)
 *   odrzani="no"       bez popisa održanih izleta ove godine
 *   slike="yes"        uz svaki izlet njegova slika (zadano bez slika, s blokom datuma)
 *   izleti_url="/izleti/"  gumb "Svi izleti s opisima" ispod plana (prazno = bez gumba)
 *
 * @package Plan_A_Izleti
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Izleti_Plan_View {

	const TAG = 'plan-a-plan-izleta';

	const MONTHS = array( 1 => 'Siječanj', 'Veljača', 'Ožujak', 'Travanj', 'Svibanj', 'Lipanj', 'Srpanj', 'Kolovoz', 'Rujan', 'Listopad', 'Studeni', 'Prosinac' );
	const MONTHS_GEN = array( 1 => 'siječnja', 'veljače', 'ožujka', 'travnja', 'svibnja', 'lipnja', 'srpnja', 'kolovoza', 'rujna', 'listopada', 'studenoga', 'prosinca' );
	const MONTHS_SHORT = array( 1 => 'sij', 'velj', 'ožu', 'tra', 'svi', 'lip', 'srp', 'kol', 'ruj', 'lis', 'stu', 'pro' );
	const DAYS = array( 'ned', 'pon', 'uto', 'sri', 'čet', 'pet', 'sub' );

	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function assets() {
		wp_register_style( 'plan-a-plan', PLAN_A_IZLETI_URL . 'assets/css/plan-a-plan.css', array(), PLAN_A_IZLETI_VERSION );
		wp_register_script( 'plan-a-plan', PLAN_A_IZLETI_URL . 'assets/js/plan-a-plan.js', array(), PLAN_A_IZLETI_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		$post = get_post();
		if ( is_singular() && $post && has_shortcode( $post->post_content, self::TAG ) ) {
			wp_enqueue_style( 'plan-a-plan' );
		}
	}

	private static function parts( string $ymd ): array {
		return array_map( 'intval', explode( '-', $ymd ) );
	}

	/**
	 * Raspon na hrvatskom: "8. studenoga 2026.", "14.–15. studenoga 2026.",
	 * "30. studenoga – 1. prosinca 2026.", "31. prosinca 2026. – 2. siječnja 2027."
	 */
	public static function date_range( string $from, string $to ): string {
		if ( ! $from ) {
			return '';
		}
		list( $y1, $m1, $d1 ) = self::parts( $from );
		list( $y2, $m2, $d2 ) = self::parts( $to ?: $from );
		if ( $from === $to || ! $to ) {
			return sprintf( '%d. %s %d.', $d1, self::MONTHS_GEN[ $m1 ], $y1 );
		}
		if ( $y1 === $y2 && $m1 === $m2 ) {
			return sprintf( '%d.–%d. %s %d.', $d1, $d2, self::MONTHS_GEN[ $m1 ], $y1 );
		}
		if ( $y1 === $y2 ) {
			return sprintf( '%d. %s – %d. %s %d.', $d1, self::MONTHS_GEN[ $m1 ], $d2, self::MONTHS_GEN[ $m2 ], $y1 );
		}
		return sprintf( '%d. %s %d. – %d. %s %d.', $d1, self::MONTHS_GEN[ $m1 ], $y1, $d2, self::MONTHS_GEN[ $m2 ], $y2 );
	}

	private static function weekday( string $ymd ): string {
		return self::DAYS[ (int) gmdate( 'w', strtotime( $ymd . ' 12:00:00 UTC' ) ) ];
	}

	private static function days( array $row ): int {
		return (int) round( ( strtotime( $row['to'] ) - strtotime( $row['from'] ) ) / DAY_IN_SECONDS ) + 1;
	}

	/**
	 * Blok s datumom: dan u tjednu, dan(i) i mjesec.
	 */
	private static function date_block( array $row ): string {
		list( , $m1, $d1 ) = self::parts( $row['from'] );
		list( , $m2, $d2 ) = self::parts( $row['to'] );
		$multi = $row['to'] !== $row['from'];
		$wd    = self::weekday( $row['from'] ) . ( $multi ? '–' . self::weekday( $row['to'] ) : '' );
		$day   = $d1 . ( $multi ? '–' . $d2 : '' );
		$month = self::MONTHS_SHORT[ $m1 ] . ( $m1 !== $m2 ? '–' . self::MONTHS_SHORT[ $m2 ] : '' );
		return '<div class="papl-date" aria-hidden="true"><span class="papl-date__wd">' . esc_html( $wd ) . '</span>'
			. '<span class="papl-date__d' . ( $multi ? ' papl-date__d--multi' : '' ) . '">' . esc_html( $day ) . '</span>'
			. '<span class="papl-date__m">' . esc_html( $month ) . '</span></div>';
	}

	/**
	 * Slika retka: vlastita slika najave, inače slika objavljenog izleta, inače ilustracija.
	 * Preko slike je oznaka s datumom.
	 */
	private static function media( array $row ): string {
		$image_id = $row['entry'] ? (int) get_post_thumbnail_id( $row['entry'] ) : 0;
		if ( ! $image_id && $row['tour'] ) {
			$image_id = Plan_A_Izleti_Shortcode::image_id( $row['tour'] );
		}
		$img = $image_id ? wp_get_attachment_image(
			$image_id,
			'medium_large',
			false,
			array(
				'class'   => 'papl-media__img',
				'alt'     => '',
				'loading' => 'lazy',
				'sizes'   => '(max-width: 599px) 120px, 220px',
			)
		) : '';
		if ( ! $img ) {
			$img = '<span class="papl-media__empty" aria-hidden="true"><svg viewBox="0 0 120 60" width="120" height="60" fill="none"><path d="M0 60 30 22l14 16 22-30 54 52z" fill="rgba(255,255,255,.18)"/><path d="M0 60l40-26 18 12 26-20 36 34z" fill="rgba(255,255,255,.28)"/></svg></span>';
		}
		$url  = $row['tour'] ? get_permalink( $row['tour'] ) : '';
		$open = $url ? '<a class="papl-media" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' : '<div class="papl-media">';
		return $open . $img . self::date_block( $row ) . ( $url ? '</a>' : '</div>' );
	}

	private static function icon( string $name ): string {
		$paths = array(
			'calendar' => '<rect x="3" y="4.5" width="18" height="16" rx="2"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/>',
			'add'      => '<rect x="3" y="4.5" width="18" height="16" rx="2"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4M12 12.5v5M9.5 15h5"/>',
			'bell'     => '<path d="M6 16V11a6 6 0 1 1 12 0v5l2 2H4z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
			'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
			'user'     => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.6 3.1-6 7-6s7 2.4 7 6"/>',
			'chevron'  => '<path d="m6 9 6 6 6-6"/>',
		);
		return '<svg class="papl-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}

	private static function row_html( array $row, string $status, string $today, bool $images = false ): string {
		$url    = $row['tour'] ? get_permalink( $row['tour'] ) : '';
		$range  = self::date_range( $row['from'], $row['to'] );
		$title  = esc_html( $row['title'] );
		$labels = array(
			'open' => 'Prijave otvorene',
			'full' => 'Popunjeno',
			'soon' => 'Uskoro',
			'past' => 'Održano',
		);

		$meta = array();
		if ( $row['guides'] ) {
			$meta[] = '<span>' . self::icon( 'user' ) . 'Vodiči: ' . esc_html( $row['guides'] ) . '</span>';
		}
		$duration = $row['tour'] ? Plan_A_Izleti_Shortcode::get_duration( $row['tour'] ) : '';
		if ( ! $duration && self::days( $row ) > 1 ) {
			$duration = self::days( $row ) . ' dana';
		}
		if ( $duration && 'past' !== $status ) {
			$meta[] = '<span>' . esc_html( $duration ) . '</span>';
		}
		if ( $row['tour'] && 'open' === $status ) {
			$price = Plan_A_Izleti_Shortcode::get_price_html( $row['tour'], Plan_A_Izleti_Data::source_id( $row['tour'] ) );
			if ( $price ) {
				$meta[] = '<span class="papl-price">od ' . wp_kses( $price, array( 'span' => array( 'class' => true ), 'bdi' => array() ) ) . '</span>';
			}
		}

		$html  = '<li class="papl-row is-' . esc_attr( $status ) . '" id="izlet-' . esc_attr( $row['key'] ) . '">';
		$html .= $images ? self::media( $row ) : self::date_block( $row );
		$html .= '<div class="papl-main">';
		$html .= '<h4 class="papl-title">' . ( $url ? '<a href="' . esc_url( $url ) . '">' . $title . '</a>' : $title ) . '</h4>';
		$html .= '<p class="papl-when">' . esc_html( $range ) . '</p>';
		if ( $meta ) {
			$html .= '<p class="papl-meta">' . implode( '', $meta ) . '</p>';
		}
		if ( $row['note'] && 'past' !== $status ) {
			$html .= '<p class="papl-note">' . esc_html( $row['note'] ) . '</p>';
		}
		$html .= '</div>';

		$html .= '<div class="papl-side">';
		$html .= '<span class="papl-badge papl-badge--' . esc_attr( $status ) . '">' . esc_html( $labels[ $status ] ) . '</span>';
		if ( 'past' !== $status ) {
			$html .= '<div class="papl-actions">';
			if ( 'open' === $status ) {
				$html .= '<a class="papl-btn papl-btn--cta" href="' . esc_url( $url ) . '">Prijavi se' . self::icon( 'arrow' ) . '</a>';
			} elseif ( 'full' === $status ) {
				$html .= '<a class="papl-btn papl-btn--ghost" href="' . esc_url( $url ) . '">Pogledaj izlet</a>';
			} elseif ( $row['entry'] ) {
				$form_id = 'papl-n-' . $row['entry'];
				$html   .= '<button type="button" class="papl-btn papl-btn--ghost" data-papl-notify aria-expanded="false" aria-controls="' . esc_attr( $form_id ) . '">' . self::icon( 'bell' ) . 'Javi mi kad bude objavljen</button>';
			}
			$html .= '<a class="papl-ics" href="' . esc_url( Plan_A_Izleti_Plan_Ics::url( $row['key'] ) ) . '" title="Dodaj u svoj kalendar" aria-label="' . esc_attr( 'Dodaj u kalendar: ' . $row['title'] ) . '">' . self::icon( 'add' ) . '</a>';
			$html .= '</div>';
		}
		$html .= '</div>';

		if ( 'soon' === $status && $row['entry'] ) {
			$form_id = 'papl-n-' . $row['entry'];
			$privacy = get_privacy_policy_url();
			$html   .= '<form class="papl-notify" id="' . esc_attr( $form_id ) . '" data-papl-form hidden novalidate>'
				. '<input type="hidden" name="entry" value="' . (int) $row['entry'] . '">'
				. '<p class="papl-notify__trap" aria-hidden="true"><label>Web <input type="text" name="web" tabindex="-1" autocomplete="off"></label></p>'
				. '<label class="papl-notify__label" for="' . esc_attr( $form_id ) . '-e">Tvoj e-mail – javit ćemo ti čim se otvore prijave za „' . $title . '”</label>'
				. '<div class="papl-notify__row"><input type="email" id="' . esc_attr( $form_id ) . '-e" name="email" autocomplete="email" inputmode="email" placeholder="ime@primjer.hr" required maxlength="190">'
				. '<button type="submit" class="papl-btn papl-btn--cta">Javi mi</button></div>'
				. '<p class="papl-notify__msg" role="status" aria-live="polite"></p>'
				. '<p class="papl-notify__small">Šaljemo samo jedan e-mail, a adresu odmah zatim brišemo.' . ( $privacy ? ' <a href="' . esc_url( $privacy ) . '">Zaštita podataka</a>' : '' ) . '</p>'
				. '</form>';
		}

		return $html . '</li>';
	}

	public static function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'godina'     => '',
				'odrzani'    => 'yes',
				'izleti_url' => '/izleti/',
				'slike'      => 'no',
			),
			$atts,
			self::TAG
		);

		wp_enqueue_style( 'plan-a-plan' );
		wp_enqueue_script( 'plan-a-plan' );
		wp_add_inline_script( 'plan-a-plan', 'window.planAPlan=' . wp_json_encode( array( 'ajax' => admin_url( 'admin-ajax.php' ) ) ) . ';', 'before' );

		$images = 'yes' === $atts['slike'];
		$today  = current_time( 'Y-m-d' );
		$year  = preg_match( '/^\d{4}$/', (string) $atts['godina'] ) ? (string) $atts['godina'] : '';
		$this_year = $year ?: substr( $today, 0, 4 );

		$upcoming = array();
		$past     = array();
		foreach ( Plan_A_Izleti_Plan::rows() as $row ) {
			if ( $year && substr( $row['from'], 0, 4 ) !== $year && substr( $row['to'], 0, 4 ) !== $year ) {
				continue;
			}
			$status = Plan_A_Izleti_Plan::status( $row, $today );
			if ( 'past' === $status ) {
				if ( substr( $row['from'], 0, 4 ) === $this_year ) {
					$past[] = $row;
				}
				continue;
			}
			$upcoming[ substr( $row['from'], 0, 7 ) ][] = array( $row, $status );
		}

		$total = 0;
		$open  = 0;
		foreach ( $upcoming as $items ) {
			foreach ( $items as $item ) {
				$total++;
				$open += 'open' === $item[1] ? 1 : 0;
			}
		}

		ob_start();
		?>
		<?php $font = self::theme_font(); ?>
		<div class="papl<?php echo $images ? ' papl--images' : ''; ?>" data-papl<?php echo $font ? ' style="font-family:' . esc_attr( $font ) . '"' : ''; ?>>
			<div class="papl-top">
				<p class="papl-summary">
					<?php if ( $total ) : ?>
						U planu je još <strong><?php echo (int) $total; ?></strong> <?php echo esc_html( self::plural( $total, array( 'izlet', 'izleta', 'izleta' ) ) ); ?><?php echo $open ? ', a za <strong>' . (int) $open . '</strong> su otvorene prijave' : ''; ?>.
					<?php else : ?>
						Plan za sljedeće razdoblje uskoro.
					<?php endif; ?>
				</p>
				<details class="papl-cal">
					<summary class="papl-btn papl-btn--ghost"><?php echo self::icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>Plan u mom kalendaru<?php echo self::icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></summary>
					<div class="papl-cal__menu">
						<p class="papl-cal__hint">Svi izleti iz plana stižu u tvoj kalendar, a novi se dodaju sami.</p>
						<a href="<?php echo esc_url( Plan_A_Izleti_Plan_Ics::google_url() ); ?>" target="_blank" rel="noopener">Google kalendar (Android)</a>
						<a href="<?php echo esc_url( Plan_A_Izleti_Plan_Ics::webcal_url(), array( 'webcal', 'http', 'https' ) ); ?>">iPhone, Mac ili Outlook</a>
						<a href="<?php echo esc_url( Plan_A_Izleti_Plan_Ics::url() ); ?>" download="plan-izleta.ics">Preuzmi datoteku (.ics)</a>
					</div>
				</details>
			</div>

			<?php if ( count( $upcoming ) > 1 ) : ?>
				<nav class="papl-months" aria-label="Mjeseci u planu">
					<?php foreach ( $upcoming as $ym => $items ) : ?>
						<?php list( $y, $m ) = array_map( 'intval', explode( '-', $ym ) ); ?>
						<a href="#plan-<?php echo esc_attr( $ym ); ?>"><?php echo esc_html( self::MONTHS[ $m ] . ( (string) $y !== substr( $today, 0, 4 ) ? ' ' . $y : '' ) ); ?> <span><?php echo (int) count( $items ); ?></span></a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>

			<?php foreach ( $upcoming as $ym => $items ) : ?>
				<?php list( $y, $m ) = array_map( 'intval', explode( '-', $ym ) ); ?>
				<section class="papl-month" id="plan-<?php echo esc_attr( $ym ); ?>">
					<h3 class="papl-month__title"><?php echo esc_html( self::MONTHS[ $m ] ); ?> <span class="papl-month__year"><?php echo (int) $y; ?>.</span></h3>
					<ul class="papl-list">
						<?php
						foreach ( $items as $item ) {
							echo self::row_html( $item[0], $item[1], $today, $images ); // phpcs:ignore WordPress.Security.EscapeOutput -- escapirano u row_html().
						}
						?>
					</ul>
				</section>
			<?php endforeach; ?>

			<?php if ( 'no' !== $atts['odrzani'] && $past ) : ?>
				<details class="papl-past">
					<summary><?php echo esc_html( 'Održani izleti u ' . $this_year . '. (' . count( $past ) . ')' ); ?><?php echo self::icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></summary>
					<ul class="papl-list papl-list--past">
						<?php
						foreach ( array_reverse( $past ) as $row ) {
							echo self::row_html( $row, 'past', $today, $images ); // phpcs:ignore WordPress.Security.EscapeOutput
						}
						?>
					</ul>
				</details>
			<?php endif; ?>

			<?php if ( '' !== trim( (string) $atts['izleti_url'] ) ) : ?>
				<p class="papl-more"><a class="papl-btn papl-btn--primary" href="<?php echo esc_url( home_url( wp_parse_url( $atts['izleti_url'], PHP_URL_PATH ) ?: '/izleti/' ) ); ?>">Svi izleti s opisima i prijavama<?php echo self::icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a></p>
			<?php endif; ?>

			<?php if ( current_user_can( 'edit_posts' ) ) : ?>
				<p class="papl-admin"><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Plan_A_Izleti_Plan::POST_TYPE ) ); ?>">Uredi plan izleta</a> (vidi samo administrator)<br><span data-papl-diag></span></p>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Font teksta iz postavki teme Flatsome (Typography → Text), npr. "Jost".
	 * Upisuje se izravno na plan, pa vrijedi i kad je shortcode u bloku s oblikovanim
	 * tekstom (<pre>), kojem tema daje font pisaćeg stroja, i bez JavaScripta.
	 */
	private static function theme_font(): string {
		$font = '';
		// Flatsome: Typography → Text, a ako nije spremljen, font naslova.
		foreach ( array( 'type_texts', 'type_text', 'type_headings' ) as $mod ) {
			$value = get_theme_mod( $mod );
			if ( is_array( $value ) && ! empty( $value['font-family'] ) && ! preg_match( '/mono|courier/i', (string) $value['font-family'] ) ) {
				$font = (string) $value['font-family'];
				break;
			}
		}
		$font = (string) apply_filters( 'plan_a_izleti_plan_font', $font );
		$font = trim( preg_replace( '/[^A-Za-z0-9 \\-]/', '', $font ) );
		if ( preg_match( '/mono|courier/i', $font ) ) {
			$font = '';
		}
		// Uvijek normalan font, nikad font pisaćeg stroja (Lato je zadani font teme Flatsome).
		return ( $font ? '"' . $font . '", ' : '' ) . '"Lato", "Helvetica Neue", Arial, sans-serif';
	}

	private static function plural( int $n, array $forms ): string {
		$mod10  = $n % 10;
		$mod100 = $n % 100;
		if ( 1 === $mod10 && 11 !== $mod100 ) {
			return $forms[0];
		}
		if ( $mod10 >= 2 && $mod10 <= 4 && ( $mod100 < 12 || $mod100 > 14 ) ) {
			return $forms[1];
		}
		return $forms[2];
	}
}
