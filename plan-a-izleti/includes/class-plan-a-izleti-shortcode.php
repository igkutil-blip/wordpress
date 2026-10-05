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
	 * Dijagnostika (vidi je samo administrator) uključuje se s [plan-a-izleti debug="yes"].
	 */
	const DEBUG_DEFAULT = 'no';

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
				'intro' => 'no',
			),
			$atts,
			self::TAG
		);
		$show  = absint( $atts['show'] );
		$show  = $show > 0 ? min( $show, self::MAX_SHOW ) : self::DEFAULT_SHOW;
		$debug = 'yes' === strtolower( trim( (string) $atts['debug'] ) ) && current_user_can( 'manage_options' );
		$intro = 'yes' === strtolower( trim( (string) $atts['intro'] ) );

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
			$cards[] = self::render_card( $tour, $cats, $months, $index >= $show, self::first_category_name( $lookups[ $tour['id'] ], $source ) );
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
			if ( $intro ) {
				echo self::render_intro(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
			}
			echo self::render_filters( self::filter_terms( $term_ids, $source ), array_intersect_key( $window, $month_counts ), $root_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
			?>
			<p class="paiz-count" role="status" aria-live="polite" data-paiz-status data-paiz-status-text="<?php echo esc_attr__( 'Prikazano izleta: %d', 'plan-a-izleti' ); ?>">
				<?php echo esc_html( sprintf( __( 'Prikazano izleta: %d', 'plan-a-izleti' ), min( $show, count( $tours ) ) ) ); ?>
			</p>
			<div class="paiz-grid">
				<?php echo implode( '', $cards ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
			</div>
			<p class="paiz-empty" data-paiz-empty hidden><?php esc_html_e( 'Za odabrani mjesec i kategoriju trenutno nema izleta.', 'plan-a-izleti' ); ?></p>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Kategorije za izbornik "Vrsta izleta": samo one s barem jednim budućim izletom.
	 *
	 * @return WP_Term[]
	 */
	private static function filter_terms( array $term_ids, string $source ): array {
		$sources = Plan_A_Izleti_Categories::sources();
		if ( empty( $term_ids ) || ! isset( $sources[ $source ] ) ) {
			return array();
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
		return is_wp_error( $terms ) ? array() : $terms;
	}

	/**
	 * Naziv prve kategorije izleta (za oznaku na slici kartice).
	 */
	private static function first_category_name( array $lookup, string $source ): string {
		$sources = Plan_A_Izleti_Categories::sources();
		if ( ! isset( $sources[ $source ] ) ) {
			return '';
		}
		$ids = 'activities' === $source ? array_merge( $lookup['activities_tax'], $lookup['activities_meta'] ) : $lookup['tour_cat'];
		foreach ( $ids as $id ) {
			$term = get_term( (int) $id, $sources[ $source ]['taxonomy'] );
			if ( $term instanceof WP_Term ) {
				return $term->name;
			}
		}
		return '';
	}

	/**
	 * Uvod iznad izbornika (intro="yes").
	 */
	private static function render_intro(): string {
		return '<div class="paiz-intro">'
			. '<p class="paiz-intro__kicker">' . esc_html__( 'KRENI S PLANOM A', 'plan-a-izleti' ) . '</p>'
			. '<h2 class="paiz-intro__title">' . esc_html__( 'Pronađi svoj izlet.', 'plan-a-izleti' ) . '</h2>'
			. '<p class="paiz-intro__subtitle">' . esc_html__( 'Odaberi aktivnost i termin izleta.', 'plan-a-izleti' ) . '</p>'
			. '</div>';
	}

	/**
	 * Dva izbornika ("Vrsta izleta" i "Termin") i njihovi paneli s opcijama.
	 * Skriveni su dok JavaScript ne proradi (bez JS-a se vidi prvih N izleta).
	 *
	 * @param WP_Term[]             $terms  Kategorije s budućim izletima.
	 * @param array<string, string> $months 'Y-m' => puni naziv ("Studeni 2026").
	 */
	private static function render_filters( array $terms, array $months, string $root_id ): string {
		$groups = array();
		if ( $terms ) {
			$options = array( array( 'all', __( 'Svi izleti', 'plan-a-izleti' ), '' ) );
			foreach ( $terms as $term ) {
				$options[] = array( (string) $term->term_id, $term->name, '' );
			}
			$groups['cat'] = array(
				'label'   => __( 'Vrsta izleta', 'plan-a-izleti' ),
				'title'   => __( 'Što želiš doživjeti?', 'plan-a-izleti' ),
				'icon'    => 'mountain',
				'options' => $options,
			);
		}
		if ( $months ) {
			$options = array( array( 'all', __( 'Svi datumi', 'plan-a-izleti' ), '' ) );
			foreach ( $months as $key => $label ) {
				$options[] = array( $key, self::short_month_label( $key ) . '.', $label );
			}
			$groups['month'] = array(
				'label'   => __( 'Termin', 'plan-a-izleti' ),
				'title'   => __( 'Kada želiš putovati?', 'plan-a-izleti' ),
				'icon'    => 'calendar',
				'options' => $options,
			);
		}
		if ( ! $groups ) {
			return '';
		}

		$toggles = '';
		$panels  = '';
		foreach ( $groups as $name => $group ) {
			$panel_id = $root_id . '-panel-' . $name;
			$label_id = $root_id . '-label-' . $name;

			$toggles .= '<button type="button" class="paiz-select paiz-select--' . esc_attr( $name ) . '" data-paiz-toggle="' . esc_attr( $name ) . '" aria-expanded="false" aria-controls="' . esc_attr( $panel_id ) . '">'
				. '<span class="paiz-select__icon">' . self::icon( $group['icon'], 20 ) . '</span>'
				. '<span class="paiz-select__text">'
				. '<span class="paiz-select__label" id="' . esc_attr( $label_id ) . '">' . esc_html( $group['label'] ) . '</span>'
				. '<span class="paiz-select__value" data-paiz-value>' . esc_html( $group['options'][0][1] ) . '</span>'
				. '</span>'
				. '<span class="paiz-select__arrow">' . self::icon( 'chevron', 18 ) . '</span>'
				. '</button>';

			$panels .= '<div class="paiz-panel paiz-panel--' . esc_attr( $name ) . '" id="' . esc_attr( $panel_id ) . '" data-paiz-panel="' . esc_attr( $name ) . '" role="group" aria-labelledby="' . esc_attr( $label_id ) . '" hidden>'
				. '<p class="paiz-panel__title">' . esc_html( $group['title'] ) . '</p>'
				. '<div class="paiz-panel__options">';
			foreach ( $group['options'] as $i => $option ) {
				list( $value, $text, $full ) = $option;
				$panels .= '<button type="button" class="paiz-option' . ( 0 === $i ? ' is-active' : '' ) . '" data-paiz-filter="' . esc_attr( $value ) . '" aria-pressed="' . ( 0 === $i ? 'true' : 'false' ) . '"'
					. ( '' !== $full ? ' aria-label="' . esc_attr( $full ) . '"' : '' ) . '>'
					. esc_html( $text ) . '</button>';
			}
			$panels .= '</div></div>';
		}

		return '<div class="paiz-filters' . ( 1 === count( $groups ) ? ' paiz-filters--single' : '' ) . '" data-paiz-filters hidden>'
			. '<div class="paiz-selects">' . $toggles . '</div>'
			. $panels
			. '</div>';
	}

	/**
	 * Kratki naziv mjeseca, npr. '2026-11' => 'Stu 2026'.
	 */
	private static function short_month_label( string $key ): string {
		$short = array( 1 => 'Sij', 'Velj', 'Ožu', 'Tra', 'Svib', 'Lip', 'Srp', 'Kol', 'Ruj', 'Lis', 'Stu', 'Pro' );
		list( $year, $month ) = array_map( 'intval', explode( '-', $key ) );
		return ( $short[ $month ] ?? '' ) . ' ' . $year;
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
			foreach ( array( 'Izlet (ID)', 'Datum', 'Aktivnosti – taksonomija', 'Aktivnosti – meta polje', 'ttbm_tour_cat', 'Korišteno za filtar', 'Država (izvor)' ) as $heading ) {
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
				$html .= '<td>' . esc_html( self::country_debug( $tour['id'] ) ) . '</td>';
				$html .= '</tr>';
			}
			$html .= '</tbody></table></div>';
		}

		return $html . '</details>';
	}

	/**
	 * Država i svi mogući izvori za dijagnostiku.
	 */
	private static function country_debug( int $id ): string {
		$country = self::get_country( $id, Plan_A_Izleti_Data::source_id( $id ) );
		$text    = '' !== $country['value'] ? $country['value'] . ' ← ' . $country['source'] : ( $country['hidden'] ? 'skriveno (ttbm_display_location = off)' : '–' );
		$parts   = array();
		foreach ( $country['raw'] as $key => $value ) {
			$parts[] = $key . ': ' . ( '' !== $value ? $value : '–' );
		}
		return $text . ' (' . implode( '; ', $parts ) . ')';
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
	private static function render_card( array $tour, array $cats, array $months, bool $hidden, string $category ): string {
		$id        = (int) $tour['id'];
		$source_id = Plan_A_Izleti_Data::source_id( $id );
		$title     = get_the_title( $id );
		$url       = get_permalink( $id );
		$country   = self::get_country( $id, $source_id )['value'];
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
				<?php if ( '' !== $category ) : ?>
					<span class="paiz-card__category"><?php echo esc_html( $category ); ?></span>
				<?php endif; ?>
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
					<?php if ( '' !== $duration ) : ?>
						<li class="paiz-card__duration">
							<?php echo self::icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- statički SVG. ?>
							<span class="paiz-sr"><?php esc_html_e( 'Trajanje:', 'plan-a-izleti' ); ?></span>
							<span><?php echo esc_html( $duration ); ?></span>
						</li>
					<?php endif; ?>
					<?php if ( '' !== $country ) : ?>
						<li class="paiz-card__country">
							<?php echo self::icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- statički SVG. ?>
							<span class="paiz-sr"><?php esc_html_e( 'Država:', 'plan-a-izleti' ); ?></span>
							<span><?php echo esc_html( $country ); ?></span>
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

	/**
	 * Država izleta, iz istog izvora koji koristi WpTravelly na karticama popisa.
	 *
	 * Svi WpTravelly predlošci popisa (templates/list/*.php) uključuju
	 * templates/layout/location.php, koji ispisuje TTBM_Function::get_full_location():
	 * meta polje `ttbm_location_name` (naziv termina taksonomije `ttbm_tour_location`)
	 * + term meta `ttbm_country_location` tog termina ("Grad, Država"), a ne prikazuje
	 * ništa ako je `ttbm_display_location` = off.
	 *
	 * Redoslijed izvora:
	 * 1. term meta `ttbm_country_location` lokacije (TTBM_Function::get_country()) – prava država,
	 * 2. TTBM_Function::get_full_location() – točno ono što WpTravelly ispisuje
	 *    (npr. kad je lokacija upisana kao "Slovenija" bez posebne države),
	 * 3. post meta `ttbm_country_name` (kopija države koju WpTravelly sprema pri spremanju izleta),
	 * 4. post meta `ttbm_full_location_name` (rezerva koju koristi WpTravelly predložak travello).
	 *
	 * @return array{value: string, source: string, hidden: bool, raw: array<string, string>}
	 */
	private static function get_country( int $id, int $source_id ): array {
		$meta = static function ( $key ) use ( $id, $source_id ) {
			$value = get_post_meta( $id, $key, true );
			if ( ( '' === $value || false === $value ) && $source_id !== $id ) {
				$value = get_post_meta( $source_id, $key, true );
			}
			return is_scalar( $value ) ? trim( (string) $value ) : '';
		};

		$raw = array(
			'ttbm_location_name'      => $meta( 'ttbm_location_name' ),
			'ttbm_country_location'   => method_exists( 'TTBM_Function', 'get_country' ) ? trim( (string) TTBM_Function::get_country( $id ) ) : '',
			'get_full_location()'     => method_exists( 'TTBM_Function', 'get_full_location' ) ? trim( (string) TTBM_Function::get_full_location( $id ) ) : '',
			'ttbm_country_name'       => $meta( 'ttbm_country_name' ),
			'ttbm_full_location_name' => $meta( 'ttbm_full_location_name' ),
			'ttbm_display_location'   => $meta( 'ttbm_display_location' ),
		);

		$value  = '';
		$source = '';
		foreach ( array( 'ttbm_country_location', 'get_full_location()', 'ttbm_country_name', 'ttbm_full_location_name' ) as $key ) {
			if ( '' !== $raw[ $key ] ) {
				$value  = $raw[ $key ];
				$source = $key;
				break;
			}
		}

		// Kao WpTravelly: lokacija se ne prikazuje ako je u izletu isključena.
		$hidden = 'off' === $raw['ttbm_display_location'];
		if ( $hidden ) {
			$value = '';
		}

		return array(
			'value'  => (string) apply_filters( 'plan_a_izleti_country', $value, $id ),
			'source' => $source,
			'hidden' => $hidden,
			'raw'    => $raw,
		);
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

	private static function icon( string $name, int $size = 16 ): string {
		$paths = array(
			'calendar' => '<rect x="3" y="4.5" width="18" height="16" rx="2"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/>',
			'pin'      => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
			'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
			'mountain' => '<path d="M2.5 20 9 8.5l4 6.5 2.5-3.5 6 8.5z"/><path d="m7 12 2 1.5 2-1.5"/>',
			'chevron'  => '<path d="m6 9 6 6 6-6"/>',
		);
		return '<svg class="paiz-icon" viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}
}
