<?php
/**
 * Shortcode [plan-a-izleti].
 *
 * @package Plan_A_Izleti
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Izleti_Shortcode {

	const TAG          = 'plan-a-izleti';
	const DEFAULT_SHOW = 18;
	const MAX_SHOW     = 100;

	/**
	 * Privremeno: dijagnostika je zadano uključena (vidi je samo administrator)
	 * dok se ne provjeri izvor kategorija. Nakon provjere promijeniti u 'no';
	 * do tada se isključuje s [plan-a-izleti debug="no"].
	 */
	const DEBUG_DEFAULT = 'yes';

	/** @var int Brojač za jedinstvene ID-eve kad je shortcode više puta na stranici. */
	private static $instance = 0;

	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		wp_register_style(
			'plan-a-izleti',
			PLAN_A_IZLETI_URL . 'assets/css/plan-a-izleti.css',
			array(),
			PLAN_A_IZLETI_VERSION
		);
		wp_register_script(
			'plan-a-izleti',
			PLAN_A_IZLETI_URL . 'assets/js/plan-a-izleti.js',
			array(),
			PLAN_A_IZLETI_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		// Stilove učitaj u <head> ako je shortcode u sadržaju stranice (izbjegava treptanje).
		$post = get_post();
		if ( is_singular() && $post && has_shortcode( $post->post_content, self::TAG ) ) {
			wp_enqueue_style( 'plan-a-izleti' );
		}
	}

	/**
	 * @param array|string $atts Atributi shortcodea.
	 */
	public static function render( $atts ): string {
		$atts  = shortcode_atts(
			array(
				'show'  => self::DEFAULT_SHOW,
				'debug' => self::DEBUG_DEFAULT,
			),
			$atts,
			self::TAG
		);
		$show  = absint( $atts['show'] );
		$show  = $show > 0 ? min( $show, self::MAX_SHOW ) : self::DEFAULT_SHOW;
		$debug = 'no' !== strtolower( trim( (string) $atts['debug'] ) ) && current_user_can( 'manage_options' );

		if ( ! Plan_A_Izleti_Data::is_source_available() ) {
			if ( current_user_can( 'edit_posts' ) ) {
				return '<p class="paiz-notice">' . esc_html__( 'Plan A izleti: dodatak WpTravelly (i WooCommerce) mora biti aktivan da bi se izleti prikazali. Ovu poruku vide samo urednici.', 'plan-a-izleti' ) . '</p>';
			}
			return '';
		}

		if ( ! wp_style_is( 'plan-a-izleti', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'plan-a-izleti' );
		wp_enqueue_script( 'plan-a-izleti' );

		// Renderiraju se svi izleti: bez odabranog filtra vidi se prvih $show,
		// a kod odabira mjeseca ili kategorije svi odgovarajući (vidi assets/js).
		$tours = Plan_A_Izleti_Data::get_tours();

		self::$instance++;
		$root_id = 'plan-a-izleti-' . self::$instance;

		if ( empty( $tours ) ) {
			$debug_html = $debug ? self::render_debug( $tours, array(), '', array(), $show, array() ) : '';
			return '<div class="paiz" id="' . esc_attr( $root_id ) . '">' . $debug_html . '<p class="paiz-empty">' . esc_html__( 'Trenutno nema najavljenih izleta.', 'plan-a-izleti' ) . '</p></div>';
		}

		$lookups = array();
		foreach ( $tours as $tour ) {
			$lookups[ $tour['id'] ] = Plan_A_Izleti_Categories::lookup( $tour['id'], Plan_A_Izleti_Data::source_id( $tour['id'] ) );
		}
		$source = Plan_A_Izleti_Categories::choose_source( $tours, $lookups );

		$window       = self::month_window();
		$month_counts = array_fill_keys( array_keys( $window ), 0 );

		$cards    = array();
		$term_ids = array();
		foreach ( $tours as $index => $tour ) {
			$cats = Plan_A_Izleti_Categories::ids_for_source( $lookups[ $tour['id'] ], $source );
			// Gumb dobiva samo kategorija s barem jednim budućim izletom.
			if ( '' !== $tour['date'] ) {
				$term_ids = array_merge( $term_ids, $cats );
			}
			// Mjeseci u sljedećih 12 mjeseci u kojima izlet ima termin.
			$months = array_intersect_key( $tour['months'], $window );
			foreach ( array_keys( $months ) as $month ) {
				$month_counts[ $month ]++;
			}
			$cards[] = self::render_card( $tour, $cats, $months, $index >= $show );
		}
		$term_ids     = array_values( array_unique( $term_ids ) );
		$month_counts = array_filter( $month_counts );

		ob_start();
		?>
		<div class="paiz" id="<?php echo esc_attr( $root_id ); ?>" data-paiz data-paiz-show="<?php echo esc_attr( (string) $show ); ?>">
			<?php
			if ( $debug ) {
				echo self::render_debug( $tours, $lookups, $source, $term_ids, $show, $month_counts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
			}
			echo self::render_filter( $term_ids, $source ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
			echo self::render_month_filter( array_intersect_key( $window, $month_counts ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
			?>
			<div class="paiz-grid">
				<?php echo implode( '', $cards ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
			</div>
			<p class="paiz-empty" data-paiz-empty hidden><?php esc_html_e( 'Za odabrani mjesec i kategoriju trenutno nema izleta.', 'plan-a-izleti' ); ?></p>
			<p class="paiz-sr" role="status" aria-live="polite" data-paiz-status data-paiz-status-text="<?php echo esc_attr__( 'Prikazano izleta: %d', 'plan-a-izleti' ); ?>"></p>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Gumbi za kategorije. Prikazuju se samo kategorije koje imaju barem jedan
	 * prikazani izlet s budućim terminom, tako da nijedan gumb ne vodi na praznu mrežu.
	 * Skriveni su dok JavaScript ne proradi (bez JS-a se vide svi izleti).
	 */
	private static function render_filter( array $term_ids, string $source ): string {
		$sources = Plan_A_Izleti_Categories::sources();
		if ( empty( $term_ids ) || ! isset( $sources[ $source ] ) ) {
			return '';
		}
		$terms = get_terms(
			array(
				'taxonomy'   => $sources[ $source ]['taxonomy'],
				'include'    => array_map( 'intval', $term_ids ),
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}

		$html  = '<div class="paiz-filter" role="group" aria-label="' . esc_attr__( 'Filtriraj izlete po kategoriji', 'plan-a-izleti' ) . '" data-paiz-filters data-paiz-group="cat" hidden>';
		$html .= '<button type="button" class="paiz-filter__btn is-active" data-paiz-filter="all" aria-pressed="true">' . esc_html__( 'Sve ture', 'plan-a-izleti' ) . '</button>';
		foreach ( $terms as $term ) {
			$html .= '<button type="button" class="paiz-filter__btn" data-paiz-filter="' . esc_attr( (string) $term->term_id ) . '" aria-pressed="false">' . esc_html( $term->name ) . '</button>';
		}
		$html .= '</div>';

		return $html;
	}

	/**
	 * Red gumba s mjesecima ("Svi mjeseci" + mjeseci s budućim izletima), kronološki.
	 * Kao i gumbi kategorija, skriven je dok JavaScript ne proradi.
	 *
	 * @param array<string, string> $months 'Y-m' => naziv ("Studeni 2026").
	 */
	private static function render_month_filter( array $months ): string {
		if ( empty( $months ) ) {
			return '';
		}
		$html  = '<div class="paiz-filter paiz-filter--months" role="group" aria-label="' . esc_attr__( 'Filtriraj izlete po mjesecu', 'plan-a-izleti' ) . '" data-paiz-filters data-paiz-group="month" hidden>';
		$html .= '<button type="button" class="paiz-filter__btn is-active" data-paiz-filter="all" aria-pressed="true">' . esc_html__( 'Svi mjeseci', 'plan-a-izleti' ) . '</button>';
		foreach ( $months as $key => $label ) {
			$html .= '<button type="button" class="paiz-filter__btn" data-paiz-filter="' . esc_attr( $key ) . '" aria-pressed="false">' . esc_html( $label ) . '</button>';
		}
		return $html . '</div>';
	}

	/**
	 * Tekući mjesec i sljedećih 11 mjeseci: [ '2026-10' => 'Listopad 2026', … ].
	 */
	private static function month_window(): array {
		$names  = array( 1 => 'Siječanj', 'Veljača', 'Ožujak', 'Travanj', 'Svibanj', 'Lipanj', 'Srpanj', 'Kolovoz', 'Rujan', 'Listopad', 'Studeni', 'Prosinac' );
		$year   = (int) current_time( 'Y' );
		$month  = (int) current_time( 'n' );
		$window = array();
		for ( $i = 0; $i < 12; $i++ ) {
			$window[ sprintf( '%04d-%02d', $year, $month ) ] = $names[ $month ] . ' ' . $year;
			if ( ++$month > 12 ) {
				$month = 1;
				$year++;
			}
		}
		return $window;
	}

	/**
	 * Privremeni dijagnostički prikaz za administratore.
	 */
	private static function render_debug( array $tours, array $lookups, string $source, array $button_ids, int $show, array $month_counts ): string {
		$stats   = Plan_A_Izleti_Data::get_stats();
		$sources = Plan_A_Izleti_Categories::sources();

		$html  = '<details class="paiz-debug" open>';
		$html .= '<summary>' . esc_html__( 'Plan A izleti – dijagnostika (vidi samo administrator; isključuje se s debug="no")', 'plan-a-izleti' ) . '</summary>';
		$html .= '<ul>';
		$html .= '<li>' . esc_html( sprintf( 'Objavljenih izleta (%s): %d', Plan_A_Izleti_Data::post_type(), $stats['published'] ) ) . '</li>';
		$html .= '<li>' . esc_html( sprintf( 'S budućim terminom: %d · bez datuma: %d · svi termini prošli (skriveni): %d · greške pri čitanju datuma: %d', $stats['upcoming'], $stats['undated'], $stats['past'], $stats['errors'] ) ) . '</li>';
		$html .= '<li>' . esc_html( sprintf( 'Prikazano u mreži bez filtra (show=%d): %d od %d', $show, min( $show, count( $tours ) ), count( $tours ) ) ) . '</li>';
		$html .= '<li>' . esc_html__( 'Izvor kategorija:', 'plan-a-izleti' ) . ' <strong>' . esc_html( isset( $sources[ $source ] ) ? $sources[ $source ]['label'] : __( 'nijedan izvor nema kategorija za izlete s budućim terminom', 'plan-a-izleti' ) ) . '</strong></li>';
		$html .= '<li>' . esc_html__( 'Gumbi:', 'plan-a-izleti' ) . ' ' . esc_html( isset( $sources[ $source ] ) && $button_ids ? implode( ', ', self::term_names( $button_ids, $sources[ $source ]['taxonomy'] ) ) : '–' ) . '</li>';
		$window = self::month_window();
		$parts  = array();
		foreach ( $month_counts as $month => $count ) {
			$parts[] = sprintf( '%s: %d', $window[ $month ], $count );
		}
		$html .= '<li>' . esc_html__( 'Mjeseci (sljedećih 12, broj izleta s terminom u mjesecu):', 'plan-a-izleti' ) . ' ' . esc_html( $parts ? implode( ' · ', $parts ) : '–' ) . '</li>';
		$html .= '</ul>';

		if ( $tours ) {
			$html .= '<div class="paiz-debug__scroll"><table><thead><tr>';
			foreach ( array( 'Izlet (ID)', 'Datum', 'Aktivnosti – taksonomija', 'Aktivnosti – meta polje', 'ttbm_tour_cat', 'Korišteno za filtar' ) as $heading ) {
				$html .= '<th>' . esc_html( $heading ) . '</th>';
			}
			$html .= '</tr></thead><tbody>';
			foreach ( $tours as $tour ) {
				$lookup   = $lookups[ $tour['id'] ];
				$raw_meta = $lookup['activities_meta_raw'];
				$raw_meta = ( '' === $raw_meta || array() === $raw_meta || null === $raw_meta ) ? '–' : wp_json_encode( $raw_meta, JSON_UNESCAPED_UNICODE );
				$used     = isset( $sources[ $source ] ) ? self::term_names( Plan_A_Izleti_Categories::ids_for_source( $lookup, $source ), $sources[ $source ]['taxonomy'] ) : array();

				$html .= '<tr>';
				$html .= '<td>' . esc_html( get_the_title( $tour['id'] ) . ' (' . $tour['id'] . ')' ) . '</td>';
				$html .= '<td>' . esc_html( '' !== $tour['date'] ? $tour['date'] : 'bez datuma' ) . '</td>';
				$html .= '<td>' . esc_html( self::names_or_dash( $lookup['activities_tax'], Plan_A_Izleti_Categories::ACTIVITY_TAXONOMY ) ) . '</td>';
				$html .= '<td>' . esc_html( self::names_or_dash( $lookup['activities_meta'], Plan_A_Izleti_Categories::ACTIVITY_TAXONOMY ) . ' (sirovo: ' . $raw_meta . ')' ) . '</td>';
				$html .= '<td>' . esc_html( self::names_or_dash( $lookup['tour_cat'], Plan_A_Izleti_Categories::CATEGORY_TAXONOMY ) ) . '</td>';
				$html .= '<td>' . esc_html( $used ? implode( ', ', $used ) : '–' ) . '</td>';
				$html .= '</tr>';
			}
			$html .= '</tbody></table></div>';
		}

		return $html . '</details>';
	}

	private static function term_names( array $ids, string $taxonomy ): array {
		$names = array();
		foreach ( $ids as $id ) {
			$term = get_term( (int) $id, $taxonomy );
			if ( $term instanceof WP_Term ) {
				$names[] = $term->name;
			}
		}
		return $names;
	}

	private static function names_or_dash( array $ids, string $taxonomy ): string {
		$names = self::term_names( $ids, $taxonomy );
		return $names ? implode( ', ', $names ) : '–';
	}

	/**
	 * @param array $months 'Y-m' => prvi termin u tom mjesecu (samo mjeseci iz filtra).
	 * @param bool  $hidden Izlet je izvan ograničenja show (vidi se samo uz filtar).
	 */
	private static function render_card( array $tour, array $cats, array $months, bool $hidden ): string {
		$id        = (int) $tour['id'];
		$source_id = Plan_A_Izleti_Data::source_id( $id );
		$title     = get_the_title( $id );
		$url       = get_permalink( $id );
		$country   = self::get_country( $id, $source_id );
		$duration  = self::get_duration( $id );
		$price     = self::get_price_html( $id, $source_id );
		$image     = self::get_image_html( $id, $title );

		if ( '' !== $tour['date'] ) {
			$date_text = self::format_date( $tour['date'] );
			if ( $tour['more'] ) {
				$date_text .= ' ' . __( '(i drugi termini)', 'plan-a-izleti' );
			}
		} else {
			$date_text = __( 'Termin uskoro', 'plan-a-izleti' );
		}

		// Datum koji kartica prikazuje kad je odabran pojedini mjesec.
		$month_dates = array();
		foreach ( $months as $month => $date ) {
			$text = self::format_date( $date );
			if ( $tour['more'] ) {
				$text .= ' ' . __( '(i drugi termini)', 'plan-a-izleti' );
			}
			$month_dates[ $month ] = array( $date, $text );
		}

		ob_start();
		?>
		<article class="paiz-card<?php echo $tour['sold_out'] ? ' is-sold-out' : ''; ?>" data-paiz-cats="<?php echo esc_attr( implode( ' ', $cats ) ); ?>" data-paiz-months="<?php echo esc_attr( implode( ' ', array_keys( $months ) ) ); ?>" data-paiz-month-dates="<?php echo esc_attr( (string) wp_json_encode( $month_dates ) ); ?>"<?php echo $hidden ? ' hidden' : ''; ?>>
			<div class="paiz-card__media">
				<?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() ili statički HTML. ?>
				<?php if ( $tour['sold_out'] ) : ?>
					<span class="paiz-card__badge"><?php esc_html_e( 'Popunjeno', 'plan-a-izleti' ); ?></span>
				<?php endif; ?>
			</div>
			<div class="paiz-card__body">
				<h3 class="paiz-card__title"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a></h3>
				<ul class="paiz-card__meta">
					<li class="paiz-card__date">
						<?php echo self::icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- statički SVG. ?>
						<span class="paiz-sr"><?php esc_html_e( 'Datum:', 'plan-a-izleti' ); ?></span>
						<?php if ( '' !== $tour['date'] ) : ?>
							<time datetime="<?php echo esc_attr( $tour['date'] ); ?>" data-paiz-date><?php echo esc_html( $date_text ); ?></time>
						<?php else : ?>
							<span><?php echo esc_html( $date_text ); ?></span>
						<?php endif; ?>
					</li>
					<?php if ( '' !== $country ) : ?>
						<li class="paiz-card__country">
							<?php echo self::icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- statički SVG. ?>
							<span class="paiz-sr"><?php esc_html_e( 'Država:', 'plan-a-izleti' ); ?></span>
							<span><?php echo esc_html( $country ); ?></span>
						</li>
					<?php endif; ?>
					<?php if ( '' !== $duration ) : ?>
						<li class="paiz-card__duration">
							<?php echo self::icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- statički SVG. ?>
							<span class="paiz-sr"><?php esc_html_e( 'Trajanje:', 'plan-a-izleti' ); ?></span>
							<span><?php echo esc_html( $duration ); ?></span>
						</li>
					<?php endif; ?>
				</ul>
				<?php if ( '' !== $price ) : ?>
					<p class="paiz-card__price">
						<span class="paiz-card__price-label"><?php esc_html_e( 'Cijena od', 'plan-a-izleti' ); ?></span>
						<span class="paiz-card__price-value"><?php echo wp_kses( $price, self::price_tags() ); ?></span>
					</p>
				<?php endif; ?>
			</div>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Datum na hrvatskom, neovisno o jeziku WordPressa: "12. listopada 2026."
	 */
	public static function format_date( string $ymd ): string {
		$months = array(
			1  => 'siječnja',
			2  => 'veljače',
			3  => 'ožujka',
			4  => 'travnja',
			5  => 'svibnja',
			6  => 'lipnja',
			7  => 'srpnja',
			8  => 'kolovoza',
			9  => 'rujna',
			10 => 'listopada',
			11 => 'studenoga',
			12 => 'prosinca',
		);
		list( $year, $month, $day ) = array_map( 'intval', explode( '-', $ymd ) );
		if ( ! isset( $months[ $month ] ) ) {
			return $ymd;
		}
		return sprintf( '%d. %s %d.', $day, $months[ $month ], $year );
	}

	private static function get_country( int $id, int $source_id ): string {
		$country = '';
		if ( method_exists( 'TTBM_Function', 'get_country' ) ) {
			$country = TTBM_Function::get_country( $source_id );
		}
		if ( ! $country ) {
			$country = get_post_meta( $source_id, 'ttbm_country_name', true );
		}
		$country = is_string( $country ) ? trim( $country ) : '';
		return (string) apply_filters( 'plan_a_izleti_country', $country, $id );
	}

	/**
	 * Trajanje s hrvatskim množinama, npr. "3 dana / 2 noći", "5 sati".
	 * Poštuje WpTravelly postavke prikaza trajanja i noćenja.
	 */
	public static function get_duration( int $id ): string {
		if ( 'off' === get_post_meta( $id, 'ttbm_display_duration', true ) ) {
			return '';
		}

		$value = method_exists( 'TTBM_Function', 'get_duration' )
			? TTBM_Function::get_duration( $id )
			: get_post_meta( $id, 'ttbm_travel_duration', true );
		$type  = get_post_meta( $id, 'ttbm_travel_duration_type', true ) ?: 'day';
		$night = get_post_meta( $id, 'ttbm_travel_duration_night', true );

		$units = array(
			'day'  => array( 'dan', 'dana', 'dana' ),
			'hour' => array( 'sat', 'sata', 'sati' ),
			'min'  => array( 'minuta', 'minute', 'minuta' ),
		);
		$parts = array();

		$text = self::count_with_unit( $value, $units[ $type ] ?? $units['day'] );
		if ( '' !== $text ) {
			$parts[] = $text;
		}
		if ( 'on' === get_post_meta( $id, 'ttbm_display_duration_night', true ) ) {
			$text = self::count_with_unit( $night, array( 'noć', 'noći', 'noći' ) );
			if ( '' !== $text ) {
				$parts[] = $text;
			}
		}

		return (string) apply_filters( 'plan_a_izleti_duration', implode( ' / ', $parts ), $id );
	}

	/**
	 * @param mixed    $value Broj (može biti i decimalni ili tekst poput "2-3").
	 * @param string[] $forms Oblici za 1, 2-4 i 5+ (npr. dan, dana, dana).
	 */
	private static function count_with_unit( $value, array $forms ): string {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( '' === $value || ( is_numeric( $value ) && (float) $value <= 0 ) ) {
			return '';
		}
		if ( ! is_numeric( $value ) ) {
			return $value . ' ' . $forms[2];
		}
		if ( floor( (float) $value ) != (float) $value ) { // phpcs:ignore Universal.Operators.StrictComparisons -- usporedba float vrijednosti.
			return str_replace( '.', ',', (string) (float) $value ) . ' ' . $forms[1];
		}
		$n = (int) $value;
		return $n . ' ' . self::plural( $n, $forms );
	}

	/**
	 * Hrvatska množina: 1, 21, 31… / 2-4, 22-24… / ostalo.
	 */
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

	/**
	 * Početna cijena kako je računa WpTravelly (ručna cijena ili najniža cijena karte).
	 * Vraća HTML iz wc_price() ili prazan string.
	 */
	private static function get_price_html( int $id, int $source_id ): string {
		if ( method_exists( 'TTBM_Function', 'show_start_price' ) && ! TTBM_Function::show_start_price( $id ) ) {
			return '';
		}
		$price = method_exists( 'TTBM_Function', 'get_tour_start_price' )
			? TTBM_Function::get_tour_start_price( $source_id )
			: get_post_meta( $source_id, 'ttbm_travel_start_price', true );
		if ( ! is_numeric( $price ) || (float) $price <= 0 ) {
			return '';
		}
		if ( function_exists( 'wc_price' ) ) {
			return (string) wc_price( (float) $price );
		}
		return esc_html( number_format_i18n( (float) $price, 2 ) );
	}

	/**
	 * Oznake koje wc_price() koristi (wp_kses_post ne dopušta <bdi>).
	 */
	private static function price_tags(): array {
		return array(
			'span' => array( 'class' => true ),
			'bdi'  => array(),
		);
	}

	/**
	 * Istaknuta slika, a ako je nema, slike koje WpTravelly koristi kao zamjenu.
	 */
	private static function get_image_html( int $id, string $title ): string {
		$image_id = (int) get_post_thumbnail_id( $id );
		if ( ! $image_id ) {
			$image_id = (int) get_post_meta( $id, 'ttbm_list_thumbnail', true );
		}
		if ( ! $image_id ) {
			$image_id = (int) get_post_meta( $id, 'mp_thumbnail', true );
		}
		if ( $image_id ) {
			$html = wp_get_attachment_image(
				$image_id,
				'medium_large',
				false,
				array(
					'class'   => 'paiz-card__img',
					'alt'     => $title,
					'loading' => 'lazy',
					'sizes'   => '(max-width: 549px) 100vw, (max-width: 849px) 50vw, 400px',
				)
			);
			if ( $html ) {
				return $html;
			}
		}
		return '<span class="paiz-card__img paiz-card__img--empty" aria-hidden="true"></span>';
	}

	private static function icon( string $name ): string {
		$paths = array(
			'calendar' => '<rect x="3" y="4.5" width="18" height="16" rx="2"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/>',
			'pin'      => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
			'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		);
		return '<svg class="paiz-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}
}
