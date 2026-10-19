<?php
/**
 * Postavke → AI Akademija: pregled stranica (postoji / nedostaje), izrada stranica koje nedostaju, izbornik.
 */
defined( 'ABSPATH' ) || exit;

final class AKA_Admin {
	const IZBORNIK = 'AI Akademija – glavni izbornik';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_aka_stranice', array( __CLASS__, 'napravi_stranice' ) );
		add_action( 'admin_post_aka_izbornik', array( __CLASS__, 'napravi_izbornik' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( AKA_FILE ), array( __CLASS__, 'action_link' ) );
	}

	public static function action_link( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=ai-akademija' ) ) . '">Stranice i poveznice</a>' );
		return $links;
	}

	public static function menu() {
		add_options_page( 'AI Akademija', 'AI Akademija', 'manage_options', 'ai-akademija', array( __CLASS__, 'page' ) );
	}

	/** Flatsomeov predložak „Page – Full Width” (ili sličan) ako ga tema ima. */
	private static function full_width() {
		foreach ( wp_get_theme()->get_page_templates( null, 'page' ) as $file => $name ) {
			if ( preg_match( '/full\s*width/i', $name ) ) {
				return $file;
			}
		}
		return '';
	}

	public static function page() {
		AKA_Linkovi::obrisi_cache();
		$stanje = AKA_Linkovi::stanje();
		$fali   = count( array_filter( $stanje, function ( $r ) { return ! $r['id'] && ! in_array( $r['kljuc'], array( 'blog', 'privatnost' ), true ); } ) );
		$msg    = isset( $_GET['aka'] ) ? sanitize_key( $_GET['aka'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$loc    = get_registered_nav_menus();
		?>
		<div class="wrap">
			<h1>AI Akademija – stranice i poveznice</h1>
			<?php if ( 'stranice' === $msg ) : ?><div class="notice notice-success"><p>Stranice koje su nedostajale su napravljene.</p></div><?php endif; ?>
			<?php if ( 'izbornik' === $msg ) : ?><div class="notice notice-success"><p>Izbornik „<?php echo esc_html( self::IZBORNIK ); ?>” je postavljen.</p></div><?php endif; ?>
			<p>Poveznice na stranici same traže stranicu po njezinom kratkom kodu – adresa i naslov stranice mogu biti bilo kakvi.
				Stranica koja nedostaje vodi na zadanu adresu (tamo bi bila greška 404), a tečaj bez stranice vodi na Kontakt s upisanim tečajem.</p>
			<table class="widefat striped" style="max-width:980px">
				<thead><tr><th>Stranica</th><th>Kratki kod</th><th>Stanje</th><th>Adresa</th></tr></thead>
				<tbody>
				<?php foreach ( $stanje as $r ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $r['naslov'] ); ?></strong></td>
						<td><code><?php echo esc_html( $r['kod'] ); ?></code></td>
						<td><?php echo $r['id'] ? '<span style="color:#008a20">✔ postoji</span>' : '<span style="color:#d63638">✘ nedostaje</span>'; ?></td>
						<td><?php if ( $r['id'] ) : ?><a href="<?php echo esc_url( $r['url'] ); ?>" target="_blank"><?php echo esc_html( wp_make_link_relative( $r['url'] ) ); ?></a> · <a href="<?php echo esc_url( get_edit_post_link( $r['id'] ) ); ?>">uredi</a><?php else : ?><span style="color:#777"><?php echo esc_html( $r['adresa'] ); ?></span><?php endif; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2>Napravi stranice koje nedostaju</h2>
			<?php if ( $fali ) : ?>
				<p>Napravit će se <?php echo (int) $fali; ?> stranica s pravim kratkim kodom<?php echo self::full_width() ? ', predloškom „' . esc_html( wp_get_theme()->get_page_templates()[ self::full_width() ] ) . '”' : ''; ?>; tečajevi kao podstranice od „Programi”. Postojeće stranice se ne diraju.</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="aka_stranice"><?php wp_nonce_field( 'aka_stranice' ); ?>
					<?php submit_button( 'Napravi ' . $fali . ' stranica', 'primary', 'submit', false ); ?>
				</form>
			<?php else : ?>
				<p>✔ Sve stranice postoje.</p>
			<?php endif; ?>

			<h2>Glavni izbornik</h2>
			<p>Napravi (ili osvježi) izbornik „<?php echo esc_html( self::IZBORNIK ); ?>”: Programi (s tečajevima), Mentori, Za tvrtke, Cijene, Blog (ako je postavljena stranica za objave), Kontakt –
				i postavi ga kao glavni izbornik teme<?php echo isset( $loc['primary_mobile'] ) ? ' (i za mobitel)' : ''; ?>. Tvoji drugi izbornici ostaju, samo više nisu na mjestu glavnog.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Postaviti izbornik „<?php echo esc_js( self::IZBORNIK ); ?>” kao glavni izbornik?');">
				<input type="hidden" name="action" value="aka_izbornik"><?php wp_nonce_field( 'aka_izbornik' ); ?>
				<?php submit_button( 'Postavi glavni izbornik', 'secondary', 'submit', false ); ?>
			</form>
			<p style="color:#777">Mjesta izbornika u temi: <?php echo esc_html( implode( ', ', array_keys( $loc ) ) ? implode( ', ', array_keys( $loc ) ) : '–' ); ?></p>
		</div>
		<?php
	}

	public static function napravi_stranice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Nema ovlasti.' );
		}
		check_admin_referer( 'aka_stranice' );
		AKA_Linkovi::obrisi_cache();
		$tpl = self::full_width();
		$nova = function ( $naslov, $slug, $kod, $parent = 0 ) use ( $tpl ) {
			$id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $naslov,
					'post_name'    => $slug,
					'post_parent'  => $parent,
					'post_content' => $kod,
				)
			);
			if ( $id && ! is_wp_error( $id ) && $tpl ) {
				update_post_meta( $id, '_wp_page_template', $tpl );
			}
			return $id;
		};
		$m = AKA_Linkovi::mapa();
		foreach ( AKA_Linkovi::STRANICE as $kljuc => $s ) {
			if ( ! isset( $m['stranice'][ $kljuc ] ) ) {
				$id = $nova( $s[2], trim( $s[1], '/' ) ? trim( $s[1], '/' ) : 'naslovnica', '[' . $s[0] . ']' );
				if ( 'naslovnica' === $kljuc && $id && ! is_wp_error( $id ) && 'page' !== get_option( 'show_on_front' ) ) {
					update_option( 'show_on_front', 'page' );
					update_option( 'page_on_front', $id );
				}
			}
		}
		AKA_Linkovi::obrisi_cache();
		$m      = AKA_Linkovi::mapa();
		$parent = isset( $m['stranice']['programi'] ) ? $m['stranice']['programi'] : 0;
		foreach ( AKA_Podaci::tecajevi() as $slug => $t ) {
			if ( ! isset( $m['tecajevi'][ $slug ] ) ) {
				$nova( $t['naslov'], $slug, '[ai-akademija-tecaj slug="' . $slug . '"]', $parent );
			}
		}
		AKA_Linkovi::obrisi_cache();
		wp_safe_redirect( admin_url( 'options-general.php?page=ai-akademija&aka=stranice' ) );
		exit;
	}

	public static function napravi_izbornik() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Nema ovlasti.' );
		}
		check_admin_referer( 'aka_izbornik' );
		AKA_Linkovi::obrisi_cache();
		$obj     = wp_get_nav_menu_object( self::IZBORNIK );
		$menu_id = $obj ? $obj->term_id : wp_create_nav_menu( self::IZBORNIK );
		if ( is_wp_error( $menu_id ) ) {
			wp_die( esc_html( $menu_id->get_error_message() ) );
		}
		foreach ( (array) wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'any' ) ) as $item ) {
			wp_delete_post( $item->ID, true );
		}
		$m   = AKA_Linkovi::mapa();
		$add = function ( $naslov, $url, $parent = 0, $page_id = 0 ) use ( $menu_id ) {
			$args = array( 'menu-item-title' => $naslov, 'menu-item-status' => 'publish', 'menu-item-parent-id' => $parent );
			if ( $page_id ) {
				$args += array( 'menu-item-object' => 'page', 'menu-item-object-id' => $page_id, 'menu-item-type' => 'post_type' );
			} else {
				$args += array( 'menu-item-url' => $url, 'menu-item-type' => 'custom' );
			}
			return wp_update_nav_menu_item( $menu_id, 0, $args );
		};
		$pid  = function ( $k ) use ( $m ) {
			return isset( $m['stranice'][ $k ] ) ? $m['stranice'][ $k ] : 0;
		};
		$prog = $add( 'Programi', AKA_Linkovi::stranica( 'programi' ), 0, $pid( 'programi' ) );
		foreach ( AKA_Podaci::tecajevi() as $slug => $t ) {
			$add( $t['naslov'], AKA_Linkovi::tecaj( $slug ), $prog, isset( $m['tecajevi'][ $slug ] ) ? $m['tecajevi'][ $slug ] : 0 );
		}
		foreach ( array( 'mentori' => 'Mentori', 'tvrtke' => 'Za tvrtke', 'cijene' => 'Cijene' ) as $k => $n ) {
			$add( $n, AKA_Linkovi::stranica( $k ), 0, $pid( $k ) );
		}
		if ( get_option( 'page_for_posts' ) ) {
			$add( 'Blog', '', 0, (int) get_option( 'page_for_posts' ) );
		}
		$add( 'Kontakt', AKA_Linkovi::stranica( 'kontakt' ), 0, $pid( 'kontakt' ) );
		$loc = get_theme_mod( 'nav_menu_locations', array() );
		foreach ( array( 'primary', 'primary_mobile' ) as $l ) {
			if ( isset( get_registered_nav_menus()[ $l ] ) ) {
				$loc[ $l ] = $menu_id;
			}
		}
		set_theme_mod( 'nav_menu_locations', $loc );
		wp_safe_redirect( admin_url( 'options-general.php?page=ai-akademija&aka=izbornik' ) );
		exit;
	}
}
