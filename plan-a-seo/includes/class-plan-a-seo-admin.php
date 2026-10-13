<?php
/**
 * Alati → Plan A SEO: postavke, stanje, pregled promjena (bez upisa), upis u serijama i
 * „Vrati”. Isto preko REST-a (/wp-json/plan-a-seo/v1/…, samo administrator).
 *
 * @package Plan_A_Seo
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Seo_Admin {

	const BATCH = array( 'meta' => 15, 'tours' => 10, 'images' => 150 );

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_plan_a_seo_save', array( __CLASS__, 'save' ) );
		add_action( 'wp_ajax_plan_a_seo_run', array( __CLASS__, 'ajax' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function menu() {
		add_management_page( 'Plan A SEO', 'Plan A SEO', 'manage_options', 'plan-a-seo', array( __CLASS__, 'page' ) );
	}

	/* ---------------- Rad ---------------- */

	/** Jedna serija: vraća [gotovo, sljedeći offset, promijenjeno, ukupno]. */
	public static function run( string $step, int $offset ): array {
		$n = 0;
		if ( 'meta' === $step ) {
			$ids  = Plan_A_Seo_Meta::posts();
			$dups = array(
				'rank_math_title'       => Plan_A_Seo_Meta::duplicates( 'rank_math_title' ),
				'rank_math_description' => Plan_A_Seo_Meta::duplicates( 'rank_math_description' ),
			);
			foreach ( array_slice( $ids, $offset, self::BATCH['meta'] ) as $id ) {
				$n += Plan_A_Seo_Meta::apply( get_post( $id ), $dups );
			}
			$total = count( $ids );
			$next  = $offset + self::BATCH['meta'];
		} elseif ( 'tours' === $step ) {
			$ids = get_posts( array( 'post_type' => 'ttbm_tour', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ) );
			foreach ( array_slice( $ids, $offset, self::BATCH['tours'] ) as $id ) {
				$p = get_post( $id );
				if ( class_exists( 'Plan_A_Prijave_Seo' ) ) {
					Plan_A_Prijave_Seo::fill( $id, $p );
				}
				Plan_A_Seo_Images::on_save( $id, $p ); // galerija izleta
				++$n;
			}
			$total = count( $ids );
			$next  = $offset + self::BATCH['tours'];
		} elseif ( 'images' === $step ) {
			if ( 0 === $offset ) {
				delete_transient( 'plan_a_seo_usage' );
			}
			$usage = get_transient( 'plan_a_seo_usage' );
			if ( ! is_array( $usage ) ) {
				$usage = Plan_A_Seo_Images::usage();
				set_transient( 'plan_a_seo_usage', $usage, HOUR_IN_SECONDS );
			}
			$missing = Plan_A_Seo_Images::missing(); // upisane više nisu na popisu, pa uvijek od početka
			$n       = Plan_A_Seo_Images::fill( array_slice( $missing, 0, self::BATCH['images'] ), $usage );
			$total   = count( $missing );
			$next    = $offset + $n;
			return array( $n < self::BATCH['images'] || 0 === $n, $next, $n, $total );
		} else {
			return array( true, 0, 0, 0 );
		}
		Plan_A_Seo_Llms::flush();
		return array( $next >= $total, $next, $n, $total );
	}

	/** Vrati sve što je dodatak upisao. */
	public static function undo(): array {
		global $wpdb;
		$m   = 0;
		$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", Plan_A_Seo_Meta::PREV ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $ids as $id ) {
			$m += Plan_A_Seo_Meta::undo( $id );
		}
		Plan_A_Seo_Llms::flush();
		return array( 'polja' => $m, 'slike' => Plan_A_Seo_Images::undo_all() );
	}

	/** Pregled bez upisa. */
	public static function preview( int $limit = 500 ): array {
		$dups = array(
			'rank_math_title'       => Plan_A_Seo_Meta::duplicates( 'rank_math_title' ),
			'rank_math_description' => Plan_A_Seo_Meta::duplicates( 'rank_math_description' ),
		);
		$rows = array();
		foreach ( Plan_A_Seo_Meta::posts() as $id ) {
			$p = get_post( $id );
			foreach ( Plan_A_Seo_Meta::plan( $p, $dups ) as $k => $c ) {
				$rows[] = array(
					'id'     => $id,
					'vrsta'  => $p->post_type,
					'naslov' => $p->post_title,
					'url'    => get_permalink( $p ),
					'polje'  => str_replace( array( 'rank_math_', 'focus_keyword', 'title', 'description' ), array( '', 'ključna riječ', 'SEO naslov', 'opis' ), $k ),
					'sada'   => $c[0],
					'novo'   => $c[1],
					'razlog' => $c[2],
				);
				if ( count( $rows ) >= $limit ) {
					break 2;
				}
			}
		}
		return $rows;
	}

	public static function stats(): array {
		global $wpdb;
		$img = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return array(
			'stranice_objave_vodici' => count( Plan_A_Seo_Meta::posts() ),
			'slika'                  => $img,
			'slika_bez_alta'         => count( Plan_A_Seo_Images::missing() ),
			'noindex'                => Plan_A_Seo_Index::noindex_ids(),
			'skriveni_proizvodi'     => count( Plan_A_Seo_Index::hidden_products() ),
		);
	}

	/* ---------------- REST (za provjeru na kopiji) ---------------- */

	public static function routes() {
		$ok = static fn() => current_user_can( 'manage_options' );
		register_rest_route( 'plan-a-seo/v1', '/stats', array( 'methods' => 'GET', 'callback' => static fn() => self::stats(), 'permission_callback' => $ok ) );
		register_rest_route( 'plan-a-seo/v1', '/preview', array( 'methods' => 'GET', 'callback' => static fn() => self::preview(), 'permission_callback' => $ok ) );
		register_rest_route( 'plan-a-seo/v1', '/images-preview', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'images_preview' ), 'permission_callback' => $ok ) );
		register_rest_route( 'plan-a-seo/v1', '/llms', array( 'methods' => 'GET', 'callback' => static fn() => array( 'txt' => Plan_A_Seo_Llms::build() ), 'permission_callback' => $ok ) );
		register_rest_route(
			'plan-a-seo/v1',
			'/run',
			array(
				'methods'             => 'POST',
				'callback'            => static function ( WP_REST_Request $r ) {
					list( $done, $next, $n, $total ) = self::run( (string) $r['step'], (int) $r['offset'] );
					return compact( 'done', 'next', 'n', 'total' );
				},
				'permission_callback' => $ok,
			)
		);
		register_rest_route( 'plan-a-seo/v1', '/undo', array( 'methods' => 'POST', 'callback' => static fn() => self::undo(), 'permission_callback' => $ok ) );
	}

	public static function images_preview() {
		$usage = Plan_A_Seo_Images::usage();
		$out   = array();
		foreach ( array_slice( Plan_A_Seo_Images::missing(), 0, 400 ) as $att ) {
			$alt   = $usage[ $att ][0] ?? '';
			$out[] = array( 'id' => $att, 'datoteka' => wp_basename( (string) get_attached_file( $att ) ), 'alt' => '' !== $alt ? $alt : Plan_A_Seo_Images::alt_own( $att ), 'izvor' => '' !== $alt ? 'stranica ' . $usage[ $att ][1] : 'roditelj/naziv' );
		}
		return $out;
	}

	/* ---------------- Stranica ---------------- */

	public static function ajax() {
		check_ajax_referer( 'plan_a_seo' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}
		$step = sanitize_key( (string) ( $_POST['step'] ?? '' ) );
		if ( 'undo' === $step ) {
			wp_send_json_success( self::undo() );
		}
		list( $done, $next, $n, $total ) = self::run( $step, (int) ( $_POST['offset'] ?? 0 ) );
		wp_send_json_success( compact( 'done', 'next', 'n', 'total' ) );
	}

	public static function save() {
		check_admin_referer( 'plan_a_seo_save' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Nije dopušteno.' );
		}
		$o            = Plan_A_Seo::opts();
		$o['suffix']  = sanitize_text_field( wp_unslash( (string) ( $_POST['suffix'] ?? '' ) ) );
		$o['noindex'] = sanitize_textarea_field( wp_unslash( (string) ( $_POST['noindex'] ?? '' ) ) );
		update_option( Plan_A_Seo::OPT, $o, false );
		Plan_A_Seo_Llms::flush();
		wp_safe_redirect( admin_url( 'tools.php?page=plan-a-seo&spremljeno=1' ) );
		exit;
	}

	public static function page() {
		$o = Plan_A_Seo::opts();
		$s = self::stats();
		echo '<div class="wrap"><h1>Plan A SEO</h1>';
		if ( isset( $_GET['spremljeno'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success"><p>Spremljeno.</p></div>';
		}
		echo '<p>Dodatak sam popunjava SEO (Rank Math): ključnu riječ, SEO naslov i opis za Google za stranice, objave i vodiče (izlete puni Plan A prijave), te alt slika. Ručno upisano se ne dira, osim kad je opis samo naslov, ostatak predloška ili je naslov/opis prepisan s druge stranice. Svaka nova stranica, objava i slika popunjava se sama pri spremanju ili učitavanju.</p>';
		echo '<table class="widefat striped" style="max-width:760px"><tbody>'
			. '<tr><th>Stranice, objave i vodiči (u Googleu)</th><td>' . (int) $s['stranice_objave_vodici'] . '</td></tr>'
			. '<tr><th>Slike</th><td>' . (int) $s['slika'] . ' (bez alta: <b>' . (int) $s['slika_bez_alta'] . '</b>)</td></tr>'
			. '<tr><th>Stranice s noindexom (izvan sitemapa)</th><td>' . count( $s['noindex'] ) . '</td></tr>'
			. '<tr><th>Skriveni proizvodi izleta (noindex, izvan sitemapa)</th><td>' . (int) $s['skriveni_proizvodi'] . '</td></tr>'
			. '<tr><th>Sažetak za AI</th><td><a href="' . esc_url( home_url( '/llms.txt' ) ) . '" target="_blank">' . esc_html( home_url( '/llms.txt' ) ) . '</a></td></tr>'
			. '</tbody></table>';

		echo '<h2>Popuni postojeće</h2><p>Upisuje u serijama (stranicu ne zatvarajte dok ne piše „Gotovo”). „Vrati” vraća sve što je dodatak upisao.</p><p>'
			. '<button class="button button-primary" data-step="meta">1. Stranice, objave, vodiči</button> '
			. '<button class="button button-primary" data-step="tours">2. Izleti</button> '
			. '<button class="button button-primary" data-step="images">3. Alt slika</button> '
			. '<button class="button" data-step="undo">Vrati sve</button></p><pre id="paseo-log" style="background:#fff;border:1px solid #ccd0d4;padding:10px;max-width:760px;min-height:40px;white-space:pre-wrap"></pre>';
		$nonce = wp_create_nonce( 'plan_a_seo' );
		echo "<script>(function(){var log=document.getElementById('paseo-log');function go(step,off){var f=new FormData();f.append('action','plan_a_seo_run');f.append('_ajax_nonce','" . esc_js( $nonce ) . "');f.append('step',step);f.append('offset',off);return fetch(ajaxurl,{method:'POST',body:f,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(r){if(!r.success){log.textContent+='\\nGreška.';return;}var d=r.data;if(step==='undo'){log.textContent+='\\nVraćeno: '+d.polja+' polja, '+d.slike+' slika. Gotovo.';return;}log.textContent+='\\n'+step+': '+Math.min(d.next,d.total)+' / '+d.total+(d.n?' (promijenjeno '+d.n+')':'');if(!d.done){return go(step,d.next);}log.textContent+='\\nGotovo.';});}document.querySelectorAll('[data-step]').forEach(function(b){b.addEventListener('click',function(e){e.preventDefault();var s=b.getAttribute('data-step');if(s==='undo'&&!confirm('Vratiti sve što je dodatak upisao?')){return;}log.textContent='Radim…';go(s,0);});});})();</script>";

		echo '<h2>Postavke</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'plan_a_seo_save' );
		echo '<input type="hidden" name="action" value="plan_a_seo_save"><table class="form-table"><tbody>'
			. '<tr><th>Završetak SEO naslova</th><td><input type="text" class="regular-text" name="suffix" value="' . esc_attr( $o['suffix'] ) . '"><p class="description">Dodaje se na naslov stranice ako stane u 60 znakova (inače „ – Plan A”). Izleti: „ – Plan A planinarski izleti”.</p></td></tr>'
			. '<tr><th>Ne u Google (noindex)</th><td><textarea name="noindex" rows="6" class="large-text code">' . esc_textarea( $o['noindex'] ) . '</textarea><p class="description">Adrese stranica (dio iza srd-plan-a.hr/) ili ID-jevi, odvojeni razmakom ili novim redom. Te stranice dobiju noindex i izlaze iz sitemapa.</p></td></tr>'
			. '</tbody></table>';
		submit_button( 'Spremi' );
		echo '</form>';

		$rows = self::preview( 300 );
		echo '<h2>Pregled: što bi se promijenilo (' . count( $rows ) . ')</h2>';
		if ( $rows ) {
			echo '<table class="widefat striped"><thead><tr><th>Stranica</th><th>Polje</th><th>Sada</th><th>Novo</th><th>Razlog</th></tr></thead><tbody>';
			foreach ( $rows as $r ) {
				echo '<tr><td><a href="' . esc_url( get_edit_post_link( $r['id'] ) ) . '">' . esc_html( $r['naslov'] ) . '</a></td><td>' . esc_html( $r['polje'] ) . '</td><td>' . esc_html( $r['sada'] ) . '</td><td>' . esc_html( $r['novo'] ) . '</td><td>' . esc_html( $r['razlog'] ) . '</td></tr>';
			}
			echo '</tbody></table>';
		} else {
			echo '<p>Sve je popunjeno.</p>';
		}
		echo '</div>';
	}
}
