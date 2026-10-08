<?php
/**
 * Plan izleta: najave izleta (i onih koji još nisu objavljeni) i spajanje s izletima iz WpTravellyja.
 *
 * - Najava je zaseban zapis (post type paiz_plan): naziv, datum od–do, vodiči, napomena i
 *   neobavezno izlet na webu. Bez odabira izlet se pronalazi sam, po datumu početka
 *   (a kad je više izleta tog dana, po sličnosti naziva).
 * - Objavljeni izleti koji nemaju najavu dolaze u plan sami, sa svim svojim terminima.
 * - Status retka: Prijave otvorene, Popunjeno, Uskoro (još nije objavljen) ili Održano.
 *
 * @package Plan_A_Izleti
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Izleti_Plan {

	const POST_TYPE = 'paiz_plan';
	const CACHE_KEY = 'plan_a_izleti_plan';
	const NO_LINK   = -1;

	/** Izlet s više termina od ovoga (npr. svakodnevni) ne dolazi u plan sam, samo preko najave. */
	const MAX_AUTO_DATES = 24;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'flush' ) );
		add_action( 'deleted_post', array( __CLASS__, 'flush' ) );
		add_action( 'trashed_post', array( __CLASS__, 'flush' ) );
		add_action( 'save_post_' . Plan_A_Izleti_Data::post_type(), array( __CLASS__, 'flush' ) );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'flush' ) );

		if ( is_admin() ) {
			add_action( 'add_meta_boxes_' . self::POST_TYPE, array( __CLASS__, 'meta_box' ) );
			add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save' ), 5, 2 );
			add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
			add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
			add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', array( __CLASS__, 'sortable' ) );
			add_action( 'pre_get_posts', array( __CLASS__, 'admin_order' ) );
			add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
			add_action( 'admin_post_paiz_plan_import', array( __CLASS__, 'import' ) );
			add_filter( 'enter_title_here', array( __CLASS__, 'title_placeholder' ), 10, 2 );
		}
	}

	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'               => 'Plan izleta',
					'singular_name'      => 'Najava izleta',
					'menu_name'          => 'Plan izleta',
					'all_items'          => 'Svi izleti u planu',
					'add_new'            => 'Dodaj izlet',
					'add_new_item'       => 'Dodaj izlet u plan',
					'edit_item'          => 'Uredi izlet u planu',
					'new_item'           => 'Novi izlet u planu',
					'search_items'       => 'Traži',
					'not_found'          => 'Plan je prazan.',
					'not_found_in_trash' => 'Nema ničega u smeću.',
					'featured_image'     => 'Slika izleta',
					'set_featured_image' => 'Odaberi sliku (dok izlet nije objavljen)',
					'remove_featured_image' => 'Ukloni sliku',
					'use_featured_image' => 'Koristi kao sliku izleta',
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_position'   => 26,
				'menu_icon'       => 'dashicons-calendar-alt',
				'supports'        => array( 'title', 'thumbnail' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'rewrite'         => false,
				'query_var'       => false,
			)
		);
	}

	public static function flush() {
		delete_transient( self::CACHE_KEY );
	}

	/* ---------------------------------------------------------------------
	 * Podaci
	 * ------------------------------------------------------------------- */

	private static function valid_date( $value ): string {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return '';
		}
		return $value;
	}

	/**
	 * Podaci najave.
	 *
	 * @return array{from: string, to: string, guides: string, note: string, tour: int}
	 */
	public static function entry( int $id ): array {
		$from = self::valid_date( get_post_meta( $id, '_paiz_from', true ) );
		$to   = self::valid_date( get_post_meta( $id, '_paiz_to', true ) );
		return array(
			'from'   => $from,
			'to'     => ( $to && $to >= $from ) ? $to : $from,
			'guides' => (string) get_post_meta( $id, '_paiz_guides', true ),
			'note'   => (string) get_post_meta( $id, '_paiz_note', true ),
			'tour'   => (int) get_post_meta( $id, '_paiz_tour', true ),
		);
	}

	/**
	 * Naziv kao običan tekst (bez HTML-a i entiteta); escapira se tek pri ispisu.
	 */
	public static function plain_title( $post ): string {
		return trim( wp_strip_all_tags( html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) ) );
	}

	private static function normalize( string $text ): string {
		$text = remove_accents( wp_strip_all_tags( $text ) );
		$text = strtolower( preg_replace( '/[^A-Za-z0-9 ]+/', ' ', $text ) );
		return trim( preg_replace( '/\s+/', ' ', $text ) );
	}

	/**
	 * Zadnji dan izleta: početak + trajanje u danima − 1 (WpTravelly ne sprema datum završetka).
	 */
	private static function tour_end( int $tour_id, string $from ): string {
		$type  = get_post_meta( $tour_id, 'ttbm_travel_duration_type', true ) ?: 'day';
		$value = get_post_meta( $tour_id, 'ttbm_travel_duration', true );
		$days  = ( 'day' === $type && is_numeric( $value ) ) ? (int) ceil( (float) $value ) : 1;
		if ( $days <= 1 ) {
			return $from;
		}
		return gmdate( 'Y-m-d', strtotime( $from . ' +' . ( min( $days, 60 ) - 1 ) . ' days' ) );
	}

	/**
	 * Svi retci plana od 1. siječnja prošle godine nadalje, složeni po datumu.
	 *
	 * @return array[] Retci: key, entry (ID najave ili 0), tour (ID izleta ili 0), from, to,
	 *                 title, guides, note, sold_out, auto (izlet povezan sam).
	 */
	public static function rows(): array {
		$today = current_time( 'Y-m-d' );
		$cache = get_transient( self::CACHE_KEY );
		if ( is_array( $cache ) && ( $cache['day'] ?? '' ) === $today && isset( $cache['rows'] ) ) {
			return $cache['rows'];
		}

		$since = ( (int) substr( $today, 0, 4 ) - 1 ) . '-01-01';
		$tours = Plan_A_Izleti_Data::get_tour_dates();

		// Termini objavljenih izleta po danu: [ 'Y-m-d' => [ tour_id, … ] ].
		$by_day = array();
		foreach ( $tours as $tour_id => $tour ) {
			foreach ( $tour['dates'] as $date ) {
				if ( $date >= $since ) {
					$by_day[ $date ][] = $tour_id;
				}
			}
		}

		$claimed = array(); // "tour_id|Y-m-d" već prikazani preko najave.
		$rows    = array();

		$entries = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'publish',
				'posts_per_page'   => 1000,
				'no_found_rows'    => true,
				'suppress_filters' => false,
				'meta_key'         => '_paiz_from', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'       => $since, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_compare'     => '>=',
			)
		);
		update_meta_cache( 'post', wp_list_pluck( $entries, 'ID' ) );

		foreach ( $entries as $post ) {
			$e = self::entry( $post->ID );
			if ( ! $e['from'] ) {
				continue;
			}
			$tour_id = 0;
			$auto    = false;
			if ( $e['tour'] > 0 && isset( $tours[ $e['tour'] ] ) ) {
				$tour_id = $e['tour'];
			} elseif ( 0 === $e['tour'] && ! empty( $by_day[ $e['from'] ] ) ) {
				$tour_id = self::best_match( $post->post_title, $by_day[ $e['from'] ] );
				$auto    = (bool) $tour_id;
			}
			if ( $tour_id ) {
				$claimed[ $tour_id . '|' . $e['from'] ] = true;
			}
			$rows[] = array(
				'key'      => 'e' . $post->ID,
				'entry'    => $post->ID,
				'tour'     => $tour_id,
				'from'     => $e['from'],
				'to'       => $e['to'],
				'title'    => self::plain_title( $post ),
				'guides'   => $e['guides'],
				'note'     => $e['note'],
				'sold_out' => $tour_id ? $tours[ $tour_id ]['sold_out'] : false,
				'auto'     => $auto,
			);
		}

		// Objavljeni izleti bez najave dolaze u plan sami.
		foreach ( $tours as $tour_id => $tour ) {
			$dates = array_values(
				array_filter(
					$tour['dates'],
					static function ( $d ) use ( $since ) {
						return $d >= $since;
					}
				)
			);
			if ( count( $dates ) > self::MAX_AUTO_DATES ) {
				continue;
			}
			foreach ( $dates as $date ) {
				if ( isset( $claimed[ $tour_id . '|' . $date ] ) ) {
					continue;
				}
				$rows[] = array(
					'key'      => 't' . $tour_id . '-' . str_replace( '-', '', $date ),
					'entry'    => 0,
					'tour'     => $tour_id,
					'from'     => $date,
					'to'       => self::tour_end( $tour_id, $date ),
					'title'    => self::plain_title( $tour_id ),
					'guides'   => '',
					'note'     => '',
					'sold_out' => $tour['sold_out'],
					'auto'     => true,
				);
			}
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				return strcmp( $a['from'], $b['from'] ) ?: strcmp( $a['to'], $b['to'] ) ?: strcmp( $a['title'], $b['title'] );
			}
		);

		set_transient(
			self::CACHE_KEY,
			array(
				'day'  => $today,
				'rows' => $rows,
			),
			HOUR_IN_SECONDS
		);
		return $rows;
	}

	/**
	 * Izlet istog dana s nazivom sličnim najavi (zajednička riječ ili sličnost od barem 50 %);
	 * od više takvih najsličniji. Tako se ne poveže drugi izlet koji je slučajno istog dana.
	 *
	 * @param int[] $tour_ids Izleti s terminom tog dana.
	 */
	private static function best_match( string $title, array $tour_ids ): int {
		$title = self::normalize( $title );
		$words = self::words( $title );
		$best  = 0;
		$score = 0.0;
		foreach ( $tour_ids as $tour_id ) {
			$name = self::normalize( self::plain_title( $tour_id ) );
			similar_text( $title, $name, $percent );
			$common = array_intersect( $words, self::words( $name ) );
			if ( ! $common && $percent < 50 ) {
				continue;
			}
			$percent += 25 * count( $common );
			if ( $percent > $score ) {
				$score = $percent;
				$best  = (int) $tour_id;
			}
		}
		return $best;
	}

	/**
	 * Značajne riječi naziva (prvih 5 slova, da se poklope oblici: ferrata/ferrate).
	 */
	private static function words( string $normalized ): array {
		$out = array();
		foreach ( explode( ' ', $normalized ) as $word ) {
			if ( strlen( $word ) >= 4 && ! in_array( $word, array( 'dana', 'dvodnevni', 'izlet', 'tura', 'vikend' ), true ) ) {
				$out[] = substr( $word, 0, 5 );
			}
		}
		return array_unique( $out );
	}

	/**
	 * Status retka: past, open, full ili soon.
	 */
	public static function status( array $row, string $today = '' ): string {
		$today = $today ?: current_time( 'Y-m-d' );
		if ( $row['to'] < $today ) {
			return 'past';
		}
		if ( $row['tour'] ) {
			return $row['sold_out'] ? 'full' : 'open';
		}
		return 'soon';
	}

	public static function row_by_entry( int $entry_id ): ?array {
		foreach ( self::rows() as $row ) {
			if ( $row['entry'] === $entry_id ) {
				return $row;
			}
		}
		return null;
	}

	/* ---------------------------------------------------------------------
	 * Administracija
	 * ------------------------------------------------------------------- */

	public static function title_placeholder( $text, $post ) {
		return ( $post && self::POST_TYPE === $post->post_type ) ? 'Naziv izleta, npr. Vrhovi Dinare' : $text;
	}

	public static function meta_box() {
		add_meta_box( 'paiz-plan', 'Termin i vodiči', array( __CLASS__, 'meta_box_html' ), self::POST_TYPE, 'normal', 'high' );
	}

	/**
	 * Objavljeni izleti za izbornik "Izlet na webu".
	 */
	private static function tour_options(): array {
		$ids = get_posts(
			array(
				'post_type'      => Plan_A_Izleti_Data::post_type(),
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		$out = array();
		foreach ( $ids as $id ) {
			$out[ (int) $id ] = self::plain_title( $id );
		}
		return $out;
	}

	public static function meta_box_html( $post ) {
		$e = self::entry( $post->ID );
		wp_nonce_field( 'paiz_plan_save', 'paiz_plan_nonce' );
		$row  = 'publish' === $post->post_status ? self::row_by_entry( (int) $post->ID ) : null;
		$subs = Plan_A_Izleti_Plan_Notify::count( (int) $post->ID );
		?>
		<style>
			.paiz-plan-box p{margin:0 0 14px}.paiz-plan-box label{display:block;font-weight:600;margin-bottom:4px}
			.paiz-plan-box .paiz-row{display:flex;flex-wrap:wrap;gap:16px}.paiz-plan-box .paiz-row p{flex:1 1 180px}
			.paiz-plan-box input[type=text],.paiz-plan-box select{width:100%;max-width:520px}
			.paiz-plan-box .paiz-state{padding:10px 12px;border-left:4px solid #2271b1;background:#f0f6fc}
		</style>
		<div class="paiz-plan-box">
			<div class="paiz-row">
				<p><label for="paiz_from">Datum početka</label><input type="date" id="paiz_from" name="paiz_from" value="<?php echo esc_attr( $e['from'] ); ?>" required></p>
				<p><label for="paiz_to">Datum završetka <span style="font-weight:400">(za višednevne)</span></label><input type="date" id="paiz_to" name="paiz_to" value="<?php echo esc_attr( $e['to'] !== $e['from'] ? $e['to'] : '' ); ?>"></p>
			</div>
			<p><label for="paiz_guides">Vodiči</label><input type="text" id="paiz_guides" name="paiz_guides" value="<?php echo esc_attr( $e['guides'] ); ?>" placeholder="npr. Igor + Krešo" maxlength="120"></p>
			<p><label for="paiz_note">Kratka napomena <span style="font-weight:400">(neobavezno, vidi se u planu)</span></label><input type="text" id="paiz_note" name="paiz_note" value="<?php echo esc_attr( $e['note'] ); ?>" placeholder="npr. termin okviran, ovisi o vremenu" maxlength="120"></p>
			<p>
				<label for="paiz_tour">Izlet na webu</label>
				<select id="paiz_tour" name="paiz_tour">
					<option value="0" <?php selected( $e['tour'], 0 ); ?>>Automatski – izlet s istim datumom početka i sličnim nazivom</option>
					<option value="<?php echo (int) self::NO_LINK; ?>" <?php selected( $e['tour'], self::NO_LINK ); ?>>Ne povezuj (izlet neće biti objavljen)</option>
					<?php foreach ( self::tour_options() as $id => $title ) : ?>
						<option value="<?php echo (int) $id; ?>" <?php selected( $e['tour'], $id ); ?>><?php echo esc_html( $title ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<?php if ( $row ) : ?>
				<p class="paiz-state">
					<?php if ( $row['tour'] ) : ?>
						Na webu: <a href="<?php echo esc_url( get_permalink( $row['tour'] ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( self::plain_title( $row['tour'] ) ); ?></a><?php echo $row['auto'] ? ' (pronađen automatski)' : ''; ?>.
					<?php else : ?>
						Još nije objavljen – u planu piše „Uskoro”. Kad u WpTravellyju objaviš izlet s istim datumom početka i sličnim nazivom, sam će se povezati (ili ga odaberi gore).
					<?php endif; ?>
					<?php if ( $subs ) : ?>
						<br><strong><?php echo esc_html( Plan_A_Izleti_Plan_Notify::waiting_text( $subs ) ); ?></strong> e-mail kad izlet bude objavljen (šalje se sam).
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['paiz_plan_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['paiz_plan_nonce'] ) ), 'paiz_plan_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$from = self::valid_date( sanitize_text_field( wp_unslash( $_POST['paiz_from'] ?? '' ) ) );
		$to   = self::valid_date( sanitize_text_field( wp_unslash( $_POST['paiz_to'] ?? '' ) ) );
		update_post_meta( $post_id, '_paiz_from', $from );
		update_post_meta( $post_id, '_paiz_to', ( $to && $to > $from ) ? $to : '' );
		update_post_meta( $post_id, '_paiz_guides', sanitize_text_field( wp_unslash( $_POST['paiz_guides'] ?? '' ) ) );
		update_post_meta( $post_id, '_paiz_note', sanitize_text_field( wp_unslash( $_POST['paiz_note'] ?? '' ) ) );
		$tour = (int) ( $_POST['paiz_tour'] ?? 0 );
		if ( $tour > 0 && get_post_type( $tour ) !== Plan_A_Izleti_Data::post_type() ) {
			$tour = 0;
		}
		update_post_meta( $post_id, '_paiz_tour', max( self::NO_LINK, $tour ) );
		self::flush();
	}

	public static function columns( $columns ) {
		return array(
			'cb'          => $columns['cb'] ?? '',
			'title'       => 'Izlet',
			'paiz_date'   => 'Termin',
			'paiz_guides' => 'Vodiči',
			'paiz_web'    => 'Na webu',
			'paiz_subs'   => 'Čeka obavijest',
		);
	}

	public static function column( $column, $post_id ) {
		$e = self::entry( (int) $post_id );
		switch ( $column ) {
			case 'paiz_date':
				echo esc_html( Plan_A_Izleti_Plan_View::date_range( $e['from'], $e['to'] ) );
				break;
			case 'paiz_guides':
				echo esc_html( $e['guides'] );
				break;
			case 'paiz_web':
				$row = self::row_by_entry( (int) $post_id );
				if ( $row && $row['tour'] ) {
					echo '<a href="' . esc_url( get_permalink( $row['tour'] ) ) . '" target="_blank" rel="noopener">' . esc_html( self::plain_title( $row['tour'] ) ) . '</a>';
				} elseif ( $row && 'past' === self::status( $row ) ) {
					echo 'Održano';
				} else {
					echo '<span style="color:#b26200">Uskoro</span>';
				}
				break;
			case 'paiz_subs':
				$n = Plan_A_Izleti_Plan_Notify::count( (int) $post_id );
				echo $n ? (int) $n : '–';
				break;
		}
	}

	public static function sortable( $columns ) {
		$columns['paiz_date'] = 'paiz_date';
		return $columns;
	}

	/**
	 * Popis u administraciji: zadano po datumu, od najbližeg.
	 */
	public static function admin_order( $query ) {
		if ( ! $query->is_main_query() || self::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}
		$orderby = $query->get( 'orderby' );
		if ( ! $orderby || 'paiz_date' === $orderby ) {
			$query->set( 'meta_key', '_paiz_from' );
			$query->set( 'orderby', 'meta_value' );
			if ( ! $query->get( 'order' ) || ! $orderby ) {
				$query->set( 'order', 'ASC' );
			}
		}
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . self::POST_TYPE,
			'Uvoz popisa izleta',
			'Uvoz popisa',
			'edit_posts',
			'paiz-plan-import',
			array( __CLASS__, 'import_page' )
		);
	}

	public static function import_page() {
		$done = isset( $_GET['uvezeno'] ) ? absint( $_GET['uvezeno'] ) : null; // phpcs:ignore WordPress.Security.NonceVerification
		$skip = isset( $_GET['preskoceno'] ) ? absint( $_GET['preskoceno'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<div class="wrap">
			<h1>Uvoz popisa izleta</h1>
			<?php if ( null !== $done ) : ?>
				<div class="notice notice-success"><p>Dodano izleta: <strong><?php echo (int) $done; ?></strong><?php echo $skip ? ', preskočeno (već postoje ili bez datuma): ' . (int) $skip : ''; ?>.</p></div>
			<?php endif; ?>
			<p>Zalijepi popis kakav je bio na stranici plana. Svaki izlet je datum (ili raspon datuma), zatim naziv, a po želji redak „Vodiči: …”. Brojevi ispred datuma i nazivi mjeseci se zanemaruju.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'paiz_plan_import' ); ?>
				<input type="hidden" name="action" value="paiz_plan_import">
				<textarea name="paiz_list" rows="18" class="large-text code" placeholder="Studeni&#10;1. 8.11.2026.&#10;Ferrata za početnike 16&#10;Vodiči: Igor&#10;&#10;2. 14.11.2026.-15.11.2026.&#10;Vrhovi Dinare&#10;Vodiči: Krešo"></textarea>
				<?php submit_button( 'Uvezi izlete' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Iz zalijepljenog teksta: [ [from, to, title, guides], … ].
	 */
	public static function parse_list( string $text ): array {
		$items   = array();
		$current = null;
		$date    = '(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})\.?';
		foreach ( preg_split( '/\R/u', $text ) as $line ) {
			$line = trim( preg_replace( '/\s+/u', ' ', $line ) );
			if ( '' === $line ) {
				continue;
			}
			if ( preg_match( '/^(?:\d{1,3}\.\s+)?' . $date . '(?:\s*[-–—]\s*' . $date . ')?\s*(.*)$/u', $line, $m ) ) {
				if ( $current ) {
					$items[] = $current;
				}
				$from    = sprintf( '%04d-%02d-%02d', $m[3], $m[2], $m[1] );
				$to      = ! empty( $m[6] ) ? sprintf( '%04d-%02d-%02d', $m[6], $m[5], $m[4] ) : '';
				$current = array(
					'from'   => self::valid_date( $from ),
					'to'     => self::valid_date( $to ),
					'title'  => trim( $m[7] ?? '' ),
					'guides' => '',
				);
				continue;
			}
			if ( ! $current ) {
				continue; // naslovi mjeseci i drugi tekst prije prvog datuma
			}
			if ( preg_match( '/^vodi[čc]i?\s*:\s*(.+)$/iu', $line, $m ) ) {
				$current['guides'] = trim( $m[1] );
			} elseif ( '' === $current['title'] ) {
				$current['title'] = $line;
			}
		}
		if ( $current ) {
			$items[] = $current;
		}
		return $items;
	}

	public static function import() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'Nemaš ovlasti.' );
		}
		check_admin_referer( 'paiz_plan_import' );
		$text  = sanitize_textarea_field( wp_unslash( $_POST['paiz_list'] ?? '' ) );
		$done  = 0;
		$skip  = 0;
		foreach ( self::parse_list( $text ) as $item ) {
			if ( ! $item['from'] || '' === $item['title'] ) {
				$skip++;
				continue;
			}
			$exists = get_posts(
				array(
					'post_type'      => self::POST_TYPE,
					'post_status'    => array( 'publish', 'draft', 'pending', 'future' ),
					'title'          => $item['title'],
					'meta_key'       => '_paiz_from', // phpcs:ignore WordPress.DB.SlowDBQuery
					'meta_value'     => $item['from'], // phpcs:ignore WordPress.DB.SlowDBQuery
					'fields'         => 'ids',
					'posts_per_page' => 1,
				)
			);
			if ( $exists ) {
				$skip++;
				continue;
			}
			$id = wp_insert_post(
				array(
					'post_type'   => self::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => sanitize_text_field( $item['title'] ),
				)
			);
			if ( ! $id || is_wp_error( $id ) ) {
				$skip++;
				continue;
			}
			update_post_meta( $id, '_paiz_from', $item['from'] );
			update_post_meta( $id, '_paiz_to', ( $item['to'] && $item['to'] > $item['from'] ) ? $item['to'] : '' );
			update_post_meta( $id, '_paiz_guides', sanitize_text_field( $item['guides'] ) );
			update_post_meta( $id, '_paiz_note', '' );
			update_post_meta( $id, '_paiz_tour', 0 );
			$done++;
		}
		self::flush();
		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'  => self::POST_TYPE,
					'page'       => 'paiz-plan-import',
					'uvezeno'    => $done,
					'preskoceno' => $skip,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}
}
