<?php
/**
 * Admin: Poruke (spremljene poruke s obrasca, samo za čitanje), Postavke (primatelj, potvrda,
 * koliko se dugo čuvaju poruke) i dnevno brisanje starih poruka.
 *
 * @package Plan_A_Kontakt
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Kontakt_Admin {

	const TYPE = 'plan_a_poruka';
	const OPT  = 'plan_a_kontakt';
	const CRON = 'plan_a_kontakt_cleanup';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( self::CRON, array( __CLASS__, 'cleanup' ) );
		add_action( 'save_post_ttbm_tour', array( __CLASS__, 'flush_tours' ) );
		add_action( 'deleted_post', array( __CLASS__, 'flush_tours' ) );
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'settings' ) );
		add_action( 'add_meta_boxes_' . self::TYPE, array( __CLASS__, 'boxes' ) );
		add_action( 'load-post.php', array( __CLASS__, 'mark_read' ) );
		add_filter( 'manage_' . self::TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 99, 2 );
		add_filter( 'plugin_action_links_plan-a-kontakt/plan-a-kontakt.php', array( __CLASS__, 'plugin_links' ) );
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	public static function activate() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON );
	}

	public static function flush_tours() {
		delete_transient( 'plan_a_kontakt_tours' );
	}

	/* ---------------- Postavke ---------------- */

	public static function defaults(): array {
		return array(
			'to'           => '',
			'confirm'      => 1,
			'confirm_text' => "primili smo tvoju poruku i javit ćemo ti se e-mailom, najčešće isti ili sljedeći dan.\n\nNa terenu smo ponekad bez signala, pa ako je hitno, javi nam se i porukom na WhatsApp.",
			'keep'         => 24,
		);
	}

	public static function opt( string $key ) {
		$o = wp_parse_args( (array) get_option( self::OPT, array() ), self::defaults() );
		return $o[ $key ] ?? null;
	}

	/** Kamo stižu poruke: postavka, inače adresa pošiljatelja WooCommerceovih mailova, inače admin. */
	public static function recipient(): string {
		foreach ( array( (string) self::opt( 'to' ), (string) get_option( 'woocommerce_email_from_address', '' ), (string) get_option( 'admin_email' ) ) as $e ) {
			$e = sanitize_email( $e );
			if ( is_email( $e ) ) {
				return $e;
			}
		}
		return '';
	}

	public static function settings() {
		register_setting(
			'plan_a_kontakt',
			self::OPT,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	public static function sanitize( $in ): array {
		$in  = is_array( $in ) ? $in : array();
		$to  = sanitize_email( (string) ( $in['to'] ?? '' ) );
		$txt = sanitize_textarea_field( (string) ( $in['confirm_text'] ?? '' ) );
		return array(
			'to'           => is_email( $to ) ? $to : '',
			'confirm'      => empty( $in['confirm'] ) ? 0 : 1,
			'confirm_text' => '' !== trim( $txt ) ? $txt : self::defaults()['confirm_text'],
			'keep'         => max( 1, min( 120, absint( $in['keep'] ?? 24 ) ) ),
		);
	}

	public static function plugin_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . self::TYPE . '&page=plan-a-kontakt' ) ) . '">Postavke</a>' );
		return $links;
	}

	/* ---------------- Poruke ---------------- */

	public static function register() {
		register_post_type(
			self::TYPE,
			array(
				'labels'              => array(
					'name'               => 'Poruke',
					'singular_name'      => 'Poruka',
					'menu_name'          => 'Poruke',
					'all_items'          => 'Sve poruke',
					'edit_item'          => 'Poruka',
					'search_items'       => 'Traži poruke',
					'not_found'          => 'Nema poruka.',
					'not_found_in_trash' => 'Nema poruka u smeću.',
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'menu_position'       => 26,
				'menu_icon'           => 'dashicons-email-alt',
				'supports'            => array( 'title' ),
				'capability_type'     => 'page',
				'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'        => true,
				'rewrite'             => false,
				'query_var'           => false,
			)
		);
	}

	/** Spremi poruku (privatno, nije vidljivo na stranici). Vraća ID ili 0. */
	public static function store( array $in ): int {
		$id = wp_insert_post(
			array(
				'post_type'    => self::TYPE,
				'post_status'  => 'private',
				'post_title'   => $in['name'] . ' – ' . Plan_A_Kontakt::topic( $in ),
				'post_content' => $in['message'],
				'post_author'  => 0,
				'meta_input'   => array(
					'_pakon_email'   => $in['email'],
					'_pakon_phone'   => $in['phone'],
					'_pakon_tour'    => (int) $in['tour'],
					'_pakon_subject' => $in['subject'],
					'_pakon_page'    => (int) $in['page'],
					'_pakon_new'     => 1,
				),
			),
			true
		);
		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	private static function unread(): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_pakon_new' WHERE p.post_type = %s AND p.post_status = 'private'", self::TYPE ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public static function menu() {
		global $menu;
		$n = self::unread();
		if ( $n && is_array( $menu ) ) {
			foreach ( $menu as $i => $item ) {
				if ( 'edit.php?post_type=' . self::TYPE === ( $item[2] ?? '' ) ) {
					$menu[ $i ][0] .= ' <span class="awaiting-mod"><span class="pending-count">' . $n . '</span></span>'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
				}
			}
		}
		add_submenu_page( 'edit.php?post_type=' . self::TYPE, 'Plan A kontakt – postavke', 'Postavke', 'manage_options', 'plan-a-kontakt', array( __CLASS__, 'page' ) );
	}

	public static function mark_read() {
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $id && self::TYPE === get_post_type( $id ) ) {
			delete_post_meta( $id, '_pakon_new' );
		}
	}

	public static function boxes( $post ) {
		remove_meta_box( 'submitdiv', self::TYPE, 'side' );
		remove_meta_box( 'slugdiv', self::TYPE, 'normal' );
		add_meta_box( 'pakon_msg', 'Poruka', array( __CLASS__, 'box' ), self::TYPE, 'normal', 'high' );
		add_meta_box( 'pakon_del', 'Radnje', array( __CLASS__, 'box_actions' ), self::TYPE, 'side', 'high' );
	}

	private static function details( WP_Post $p ): array {
		$email = (string) get_post_meta( $p->ID, '_pakon_email', true );
		$phone = (string) get_post_meta( $p->ID, '_pakon_phone', true );
		$tour  = (int) get_post_meta( $p->ID, '_pakon_tour', true );
		$subj  = (string) get_post_meta( $p->ID, '_pakon_subject', true );
		$page  = (int) get_post_meta( $p->ID, '_pakon_page', true );
		$mail  = get_post_meta( $p->ID, '_pakon_mail', true );
		$rows  = array(
			'Primljeno' => esc_html( get_the_date( 'j. n. Y. \u H:i', $p ) ),
			'E-mail'    => '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>',
		);
		if ( '' !== $phone ) {
			$rows['Mobitel'] = '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a>';
		}
		if ( $tour ) {
			$rows['Izlet'] = '<a href="' . esc_url( (string) get_permalink( $tour ) ) . '" target="_blank">' . esc_html( get_the_title( $tour ) ) . '</a>';
		}
		if ( '' !== $subj ) {
			$rows['Tema'] = esc_html( $subj );
		}
		if ( $page ) {
			$rows['Poslano sa'] = '<a href="' . esc_url( (string) get_permalink( $page ) ) . '" target="_blank">' . esc_html( get_the_title( $page ) ) . '</a>';
		}
		if ( '' !== (string) $mail ) {
			$rows['Mail nama'] = $mail ? 'poslan' : '<strong style="color:#b32d2e">nije poslan</strong> (poruka je samo ovdje)';
		}
		return $rows;
	}

	public static function box( WP_Post $p ) {
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( self::details( $p ) as $k => $v ) {
			echo '<tr><th scope="row" style="padding:6px 10px 6px 0;width:110px">' . esc_html( $k ) . '</th><td style="padding:6px 0">' . wp_kses_post( $v ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<div style="margin-top:12px;padding:14px 16px;background:#f6f7f7;border-radius:6px;font-size:15px;line-height:1.6;white-space:pre-wrap">' . esc_html( $p->post_content ) . '</div>';
	}

	public static function box_actions( WP_Post $p ) {
		$email = (string) get_post_meta( $p->ID, '_pakon_email', true );
		$topic = (string) preg_replace( '/^.*? – /u', '', $p->post_title );
		echo '<p><a class="button button-primary" href="mailto:' . esc_attr( rawurlencode( $email ) ) . '?subject=' . esc_attr( rawurlencode( 'Re: ' . $topic ) ) . '">Odgovori e-mailom</a></p>';
		if ( current_user_can( 'delete_post', $p->ID ) ) {
			echo '<p><a class="submitdelete" style="color:#b32d2e" href="' . esc_url( get_delete_post_link( $p->ID ) ) . '">Premjesti u smeće</a></p>';
		}
		echo '<p><a href="' . esc_url( admin_url( 'edit.php?post_type=' . self::TYPE ) ) . '">&larr; Sve poruke</a></p>';
	}

	public static function columns( $cols ): array {
		return array(
			'cb'           => $cols['cb'] ?? '',
			'title'        => 'Pošiljatelj – tema',
			'pakon_email'  => 'E-mail / mobitel',
			'pakon_text'   => 'Poruka',
			'pakon_date'   => 'Primljeno',
		);
	}

	public static function column( $col, $id ) {
		$p = get_post( $id );
		switch ( $col ) {
			case 'pakon_email':
				$e = (string) get_post_meta( $id, '_pakon_email', true );
				echo '<a href="mailto:' . esc_attr( $e ) . '">' . esc_html( $e ) . '</a><br>' . esc_html( (string) get_post_meta( $id, '_pakon_phone', true ) );
				break;
			case 'pakon_text':
				echo esc_html( wp_trim_words( (string) $p->post_content, 22, '…' ) );
				break;
			case 'pakon_date':
				echo esc_html( get_the_date( 'j. n. Y. H:i', $p ) );
				if ( get_post_meta( $id, '_pakon_new', true ) ) {
					echo '<br><strong style="color:#d63638">nova</strong>';
				}
				if ( '0' === (string) get_post_meta( $id, '_pakon_mail', true ) ) {
					echo '<br><span style="color:#b32d2e">mail nije poslan</span>';
				}
				break;
		}
	}

	public static function row_actions( $actions, $post ) {
		if ( self::TYPE !== $post->post_type ) {
			return $actions;
		}
		$keep = array_intersect_key( $actions, array( 'trash' => 1, 'untrash' => 1, 'delete' => 1 ) );
		$e    = (string) get_post_meta( $post->ID, '_pakon_email', true );
		return array( 'open' => '<a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">Otvori</a>', 'reply' => '<a href="mailto:' . esc_attr( $e ) . '">Odgovori</a>' ) + $keep;
	}

	/** Poruke starije od zadanog broja mjeseci idu trajno van (zaštita podataka). */
	public static function cleanup() {
		$months = (int) self::opt( 'keep' );
		$ids    = get_posts(
			array(
				'post_type'      => self::TYPE,
				'post_status'    => array( 'private', 'trash' ),
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'date_query'     => array( array( 'before' => gmdate( 'Y-m-d H:i:s', strtotime( '-' . max( 1, $months ) . ' months' ) ), 'column' => 'post_date_gmt' ) ),
			)
		);
		foreach ( $ids as $id ) {
			wp_delete_post( (int) $id, true );
		}
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$o = wp_parse_args( (array) get_option( self::OPT, array() ), self::defaults() );
		?>
		<div class="wrap">
			<h1>Plan A kontakt – postavke</h1>
			<p>Obrazac se na stranicu stavlja kratkim kodom <code>[plan-a-kontakt]</code> (Flatsome: element „Text” ili HTML blok).
				Primjeri: <code>[plan-a-kontakt okvir="da"]</code> (s bijelom karticom), <code>[plan-a-kontakt predmet="Zanimanje za izlet s boravkom u divljini" gumb="Pošalji"]</code> (stalna tema, bez izbora izleta), <code>[plan-a-kontakt izlet="ne" telefon="ne"]</code>.
				Poveznica <code>/kontakt/?izlet=ID</code> unaprijed odabere izlet.</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'plan_a_kontakt' ); ?>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="pakon-to">Poruke stižu na</label></th>
						<td><input type="email" id="pakon-to" class="regular-text" name="<?php echo esc_attr( self::OPT ); ?>[to]" value="<?php echo esc_attr( (string) $o['to'] ); ?>" placeholder="<?php echo esc_attr( (string) get_option( 'woocommerce_email_from_address', get_option( 'admin_email' ) ) ); ?>">
						<p class="description">Prazno = adresa pošiljatelja WooCommerceovih mailova (sada <?php echo esc_html( self::recipient() ); ?>). Na poruku se odgovara izravno iz maila (odgovor ide pošiljatelju).</p></td></tr>
					<tr><th scope="row">Potvrda pošiljatelju</th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPT ); ?>[confirm]" value="1" <?php checked( ! empty( $o['confirm'] ) ); ?>> pošalji kratki mail „Hvala na poruci”</label>
						<p><textarea class="large-text" rows="4" name="<?php echo esc_attr( self::OPT ); ?>[confirm_text]"><?php echo esc_textarea( (string) $o['confirm_text'] ); ?></textarea></p>
						<p class="description">Mail počinje s „Bok Ime,”. Prazan red = novi odlomak. Tekst poruke pošiljatelja se ne ponavlja (da obrazac ne posluži za slanje spama drugima).</p></td></tr>
					<tr><th scope="row"><label for="pakon-keep">Čuvanje poruka</label></th>
						<td><input type="number" id="pakon-keep" min="1" max="120" style="width:80px" name="<?php echo esc_attr( self::OPT ); ?>[keep]" value="<?php echo (int) $o['keep']; ?>"> mjeseci
						<p class="description">Starije poruke se automatski brišu (zaštita osobnih podataka).</p></td></tr>
				</table>
				<?php submit_button( 'Spremi' ); ?>
			</form>
		</div>
		<?php
	}
}
