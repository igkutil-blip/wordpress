<?php
/**
 * Admin: okvir „Tinder – Tko dolazi” na ekranu izleta (kvačica) i
 * Izleti → Tinder izleti – popis prijavljenih po izletu i zajedničke postavke.
 *
 * @package Plan_A_Tinder
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Tinder_Admin {

	const SLUG = 'plan-a-tinder';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 30 );
		add_action( 'admin_post_pat_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_pat_skip', array( __CLASS__, 'skip' ) );
		add_action( 'admin_post_pat_seed', array( __CLASS__, 'save_seed' ) );
		add_action( 'add_meta_boxes_' . self::cpt(), array( __CLASS__, 'box' ), 110 );
		add_action( 'save_post_' . self::cpt(), array( __CLASS__, 'save_box' ), 20 );
	}

	/** Okvir na ekranu izleta: uključi značajke za ovaj izlet i upiši njegovu WhatsApp grupu. */
	public static function box() {
		add_meta_box( 'pat_box', 'Tinder – Tko dolazi', array( __CLASS__, 'box_html' ), self::cpt(), 'side', 'default' );
	}

	public static function box_html( $post ) {
		wp_nonce_field( 'pat_box', 'pat_box_nonce' );
		$on = (bool) get_post_meta( $post->ID, '_pat_on', true );
		echo '<p><label><input type="checkbox" name="pat_on" value="1"' . checked( $on, true, false ) . '> <strong>Uključi za ovaj izlet</strong></label></p>';
		echo '<p class="description" style="margin-top:0">Brojač muškaraca i žena s rasponom godina iznad rezervacije, spol i godina rođenja u košarici, popis prijavljenih (Izleti → Tinder izleti).</p>';
		echo '<p class="description" style="margin-top:0">WhatsApp gumb vodi u zajedničku grupu Plan A (Izleti → Tinder izleti → Postavke).</p>';
		if ( $on ) {
			$st = Plan_A_Tinder::stats( (int) $post->ID );
			echo '<p style="margin-bottom:0">Sada: <strong>♂ ' . (int) $st['m']['n'] . ' · ♀ ' . (int) $st['z']['n'] . '</strong> (ukupno ' . (int) $st['total'] . ') – <a href="' . esc_url( self::url() . '#pat-' . (int) $post->ID ) . '">popis</a></p>';
			if ( Plan_A_Tinder::seed_active( (int) $post->ID ) ) {
				$seed = Plan_A_Tinder::seed( (int) $post->ID );
				echo '<p style="margin:4px 0 0;color:#b26200">Poticaj uključen: +' . (int) $seed['m'] . ' ♂ · +' . (int) $seed['z'] . ' ♀ – <a href="' . esc_url( self::url() . '#pat-seed-' . (int) $post->ID ) . '">promijeni</a></p>';
			}
		}
	}

	public static function save_box( $post_id ) {
		if ( ! isset( $_POST['pat_box_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pat_box_nonce'] ) ), 'pat_box' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! empty( $_POST['pat_on'] ) ) {
			update_post_meta( $post_id, '_pat_on', '1' );
		} else {
			delete_post_meta( $post_id, '_pat_on' );
		}
		delete_transient( 'pat_stats_' . (int) $post_id );
	}

	private static function cpt(): string {
		return class_exists( 'Plan_A_Prijave_Tour' ) ? Plan_A_Prijave_Tour::CPT : 'ttbm_tour';
	}

	public static function menu() {
		add_submenu_page( 'edit.php?post_type=' . self::cpt(), 'Tinder izleti', 'Tinder izleti', 'edit_posts', self::SLUG, array( __CLASS__, 'page' ) );
	}

	private static function url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'post_type' => self::cpt(), 'page' => self::SLUG ), $args ), admin_url( 'edit.php' ) );
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Nemaš ovlasti.' );
		}
		check_admin_referer( 'pat_save' );
		// Kvačice izleta: svaki prikazani izlet dobiva ili gubi „uključeno” (isto kao kvačica na ekranu izleta).
		$on = array_map( 'absint', (array) ( $_POST['tours'] ?? array() ) );
		foreach ( array_map( 'absint', explode( ',', (string) wp_unslash( $_POST['shown'] ?? '' ) ) ) as $tour ) {
			if ( ! $tour || self::cpt() !== get_post_type( $tour ) ) {
				continue;
			}
			if ( in_array( $tour, $on, true ) ) {
				update_post_meta( $tour, '_pat_on', '1' );
			} else {
				delete_post_meta( $tour, '_pat_on' );
			}
			delete_transient( 'pat_stats_' . $tour );
		}
		update_option(
			Plan_A_Tinder::OPT,
			array(
				'wa'    => Plan_A_Tinder::clean_wa( esc_url_raw( (string) wp_unslash( $_POST['wa'] ?? '' ) ) ),
				'min'   => max( 1, min( 20, absint( $_POST['min'] ?? 3 ) ) ),
				'title' => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ) ?: 'Tko dolazi',
			),
			false
		);
		Plan_A_Tinder::flush();
		wp_safe_redirect( self::url( array( 'saved' => 1 ) ) );
		exit;
	}

	/** Poticaj za jedan izlet (dodani muškarci i žene s rasponom godina, uključeno/isključeno). */
	public static function save_seed() {
		$tour = absint( $_POST['tour'] ?? 0 );
		if ( ! $tour || ! current_user_can( 'edit_post', $tour ) ) {
			wp_die( 'Nemaš ovlasti.' );
		}
		check_admin_referer( 'pat_seed_' . $tour );
		$seed = array( 'on' => empty( $_POST['seed_on'] ) ? 0 : 1 );
		foreach ( array( 'm', 'z', 'm_min', 'm_max', 'z_min', 'z_max' ) as $k ) {
			$seed[ $k ] = max( 0, min( 99, absint( $_POST[ 'seed_' . $k ] ?? 0 ) ) );
		}
		update_post_meta( $tour, '_pat_seed', $seed );
		wp_safe_redirect( self::url( array( 'saved' => 1 ) ) . '#pat-seed-' . $tour );
		exit;
	}

	/** „Ne broji” / „Broji” za jednu osobu (npr. otkazala, a narudžba ostaje). */
	public static function skip() {
		if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Nemaš ovlasti.' );
		}
		check_admin_referer( 'pat_skip' );
		$item_id = absint( $_GET['item'] ?? 0 );
		$i       = absint( $_GET['i'] ?? 0 );
		$item    = $item_id && class_exists( 'WC_Order_Item_Product' ) ? new WC_Order_Item_Product( $item_id ) : null;
		if ( $item && $item->get_id() ) {
			$skip = Plan_A_Tinder::skipped( $item );
			$skip = in_array( $i, $skip, true ) ? array_values( array_diff( $skip, array( $i ) ) ) : array_merge( $skip, array( $i ) );
			$item->update_meta_data( '_pat_x', $skip );
			$item->save();
		}
		Plan_A_Tinder::flush();
		wp_safe_redirect( self::url() . '#pat-' . absint( $_GET['tour'] ?? 0 ) );
		exit;
	}

	public static function page() {
		$s     = Plan_A_Tinder::settings();
		$tours = Plan_A_Tinder::tours();
		$all   = get_posts(
			array(
				'post_type'      => self::cpt(),
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => 200,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$labels = array( 'publish' => 'objavljen', 'draft' => 'skica', 'pending' => 'na čekanju', 'future' => 'zakazan', 'private' => 'privatan' );
		?>
		<div class="wrap">
			<h1>Tinder izleti</h1>
			<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p>Spremljeno.</p></div>
			<?php endif; ?>
			<p>Značajke (brojač „Tko dolazi” iznad rezervacije, spol i godina rođenja u košarici, WhatsApp grupa, popis prijavljenih) dobiva svaki izlet koji uključiš kvačicom u Postavkama ispod (ili na ekranu izleta, u okviru desno <strong>„Tinder – Tko dolazi”</strong>) – npr. Tinder i planinarenje, Tinder i jedrenje. WhatsApp gumb vodi u zajedničku grupu Plan A, istu za sve izlete (poveznica je u postavkama ispod). Prijava i dalje ide u tablicu za agenciju kao za sve izlete.</p>
			<div class="card" style="max-width:none">
				<h2 style="margin-top:0">Uključeni izleti (<?php echo count( $tours ); ?>)</h2>
				<?php if ( ! $tours ) : ?>
					<p>Još nijedan izlet nije uključen – označi ga kvačicom u Postavkama ispod i klikni Spremi.</p>
				<?php else : ?>
					<ul style="margin:0;list-style:disc;padding-left:20px">
						<?php foreach ( $tours as $tour ) : ?>
							<?php $st = Plan_A_Tinder::stats( $tour ); ?>
							<li style="margin:4px 0"><a href="#pat-<?php echo (int) $tour; ?>"><strong><?php echo esc_html( get_the_title( $tour ) ); ?></strong></a>
								<span style="color:#646970">– <?php echo esc_html( $labels[ get_post_status( $tour ) ] ?? (string) get_post_status( $tour ) ); ?> · ♂ <?php echo (int) $st['m']['n']; ?> · ♀ <?php echo (int) $st['z']['n']; ?> · ukupno <?php echo (int) $st['total']; ?></span>
								<?php if ( Plan_A_Tinder::seed_active( $tour ) ) : ?>
									<?php $sh = Plan_A_Tinder::shown( $tour ); ?>
									<span style="color:#b26200">· s poticajem na stranici: ♂ <?php echo (int) $sh['m']['n']; ?> · ♀ <?php echo (int) $sh['z']['n']; ?></span>
								<?php endif; ?>
								· <a href="<?php echo esc_url( get_edit_post_link( $tour ) ?: '' ); ?>">uredi izlet</a></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>

			<?php foreach ( $tours as $tour ) : ?>
				<?php self::tour_table( $tour ); ?>
			<?php endforeach; ?>

			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<h2 style="margin-top:32px">Postavke</h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="pat_save">
					<?php wp_nonce_field( 'pat_save' ); ?>
					<input type="hidden" name="shown" value="<?php echo esc_attr( implode( ',', wp_list_pluck( $all, 'ID' ) ) ); ?>">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">Izleti</th>
							<td>
								<div style="max-height:280px;overflow:auto;border:1px solid #dcdcde;background:#fff;padding:8px 12px;max-width:680px">
									<?php foreach ( $all as $t ) : ?>
										<label style="display:block;margin:4px 0"><input type="checkbox" name="tours[]" value="<?php echo (int) $t->ID; ?>" <?php checked( Plan_A_Tinder::is_tour( (int) $t->ID ) ); ?>> <?php echo esc_html( get_the_title( $t ) ?: '(bez naslova)' ); ?> <span style="color:#646970">– <?php echo esc_html( $labels[ $t->post_status ] ?? $t->post_status ); ?></span></label>
									<?php endforeach; ?>
								</div>
								<p class="description">Označeni izleti dobivaju brojač, polja u košarici i WhatsApp gumb. Izlet može biti i neobjavljen (skica).</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="pat-wa">WhatsApp grupa Plan A</label></th>
							<td><input type="url" id="pat-wa" name="wa" class="regular-text" value="<?php echo esc_attr( $s['wa'] ); ?>" placeholder="https://chat.whatsapp.com/…">
								<p class="description">Zajednička grupa za sve uključene izlete (iz WhatsAppa: Info o grupi → Poveznica za poziv). Kad se grupa napuni, ovdje upiši poveznicu nove grupe – mijenja se odmah na svim izletima. Gumb je na stranici izleta i na stranici „Hvala” nakon prijave. Prazno = bez gumba.</p></td>
						</tr>
						<tr>
							<th scope="row"><label for="pat-title">Naslov iznad brojača</label></th>
							<td><input type="text" id="pat-title" name="title" class="regular-text" value="<?php echo esc_attr( $s['title'] ); ?>" placeholder="Tko dolazi">
								<p class="description">Mali naslov iznad brojača muškaraca i žena, isti na svim izletima (zadano „Tko dolazi”). Nije naziv izleta.</p></td>
						</tr>
						<tr>
							<th scope="row"><label for="pat-min">Raspon godina od</label></th>
							<td><input type="number" id="pat-min" name="min" min="1" max="20" value="<?php echo (int) $s['min']; ?>" style="width:70px"> osoba
								<p class="description">Raspon godina (npr. 32 – 45 god.) prikazuje se tek kad je u skupini (muškarci ili žene) barem toliko osoba – s jednom osobom raspon bi otkrio njezine godine.</p></td>
						</tr>
					</table>
					<?php submit_button( 'Spremi' ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/** Obrazac „Poticaj” u kartici izleta. */
	private static function seed_form( int $tour ) {
		$seed  = Plan_A_Tinder::seed( $tour );
		$shown = Plan_A_Tinder::shown( $tour );
		$num   = static function ( string $k, int $v, int $max, string $ph = '' ) {
			return '<input type="number" name="seed_' . esc_attr( $k ) . '" min="0" max="' . (int) $max . '" value="' . ( $v ? (int) $v : '' ) . '" placeholder="' . esc_attr( $ph ) . '" style="width:64px">';
		};
		?>
		<form id="pat-seed-<?php echo (int) $tour; ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:#fff8e5;border:1px solid #f0c36d;padding:10px 14px;margin:0 0 14px;max-width:760px">
			<input type="hidden" name="action" value="pat_seed">
			<input type="hidden" name="tour" value="<?php echo (int) $tour; ?>">
			<?php wp_nonce_field( 'pat_seed_' . $tour ); ?>
			<p style="margin:0 0 8px"><label><input type="checkbox" name="seed_on" value="1" <?php checked( $seed['on'] ); ?>> <strong>Poticaj – dodaj na brojač na stranici izleta</strong></label></p>
			<p style="margin:0 0 6px">♂ muškaraca: <?php echo $num( 'm', $seed['m'], 99, '0' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				&nbsp; godine od <?php echo $num( 'm_min', $seed['m_min'], 99 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> do <?php echo $num( 'm_max', $seed['m_max'], 99 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
			<p style="margin:0 0 6px">♀ žena: <?php echo $num( 'z', $seed['z'], 99, '0' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				&nbsp; godine od <?php echo $num( 'z_min', $seed['z_min'], 99 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> do <?php echo $num( 'z_max', $seed['z_max'], 99 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
			<p class="description" style="margin:0 0 8px">Pribraja se pravim prijavama samo na stranici izleta (brojač, raspon godina, traka i „slobodno još”). Ne rezervira mjesta, ne ulazi u narudžbe ni u tablicu za agenciju. Kad prijave krenu, makni kvačicu i spremi – brojke ostaju upisane za ponovno uključivanje.</p>
			<p style="margin:0"><button type="submit" class="button">Spremi poticaj</button>
				<?php if ( Plan_A_Tinder::seed_active( $tour ) ) : ?>
					<span style="margin-left:8px;color:#b26200">Na stranici sada: <strong>♂ <?php echo (int) $shown['m']['n']; ?> · ♀ <?php echo (int) $shown['z']['n']; ?></strong> (ukupno <?php echo (int) $shown['total']; ?>)</span>
				<?php else : ?>
					<span style="margin-left:8px;color:#646970">Isključeno – na stranici su samo prave prijave.</span>
				<?php endif; ?>
			</p>
		</form>
		<?php
	}

	private static function tour_table( int $tour ) {
		if ( ! get_post( $tour ) ) {
			return;
		}
		$people = Plan_A_Tinder::people( $tour );
		$st     = Plan_A_Tinder::stats( $tour );
		$seats  = Plan_A_Tinder::seats( $tour );
		$status = array( 'on-hold' => 'čeka uplatu', 'processing' => 'u obradi', 'completed' => 'završeno', 'pending' => 'na čekanju', 'cancelled' => 'otkazano', 'refunded' => 'vraćeno', 'failed' => 'neuspjelo' );
		$range  = static function ( array $g ): string {
			return $g['min'] ? ' (' . ( $g['min'] === $g['max'] ? $g['min'] : $g['min'] . '–' . $g['max'] ) . ' god.)' : '';
		};
		?>
		<div class="card" id="pat-<?php echo (int) $tour; ?>" style="max-width:none;margin-top:18px">
			<h2 style="margin-top:0"><?php echo esc_html( get_the_title( $tour ) ); ?>
								<a class="button" style="margin-left:8px" href="<?php echo esc_url( get_edit_post_link( $tour ) ?: '' ); ?>">Uredi izlet</a>
				<a class="button" href="<?php echo esc_url( get_permalink( $tour ) ); ?>" target="_blank" rel="noopener">Stranica izleta</a></h2>
			<p style="font-size:15px">
				<strong>Broji se: <?php echo (int) $st['total']; ?></strong><?php echo $seats ? ' od ' . (int) $seats . ' mjesta' : ''; ?> ·
				♂ <?php echo (int) $st['m']['n']; ?> muškaraca<?php echo esc_html( $range( $st['m'] ) ); ?> ·
				♀ <?php echo (int) $st['z']['n']; ?> žena<?php echo esc_html( $range( $st['z'] ) ); ?>
				<span style="color:#646970">(ovdje uvijek s rasponom; na stranici raspon tek od <?php echo (int) Plan_A_Tinder::settings()['min']; ?> osobe)</span>
			</p>
			<?php self::seed_form( $tour ); ?>
			<?php if ( ! $people ) : ?>
				<p>Još nema prijava.</p>
			<?php else : ?>
				<table class="widefat striped" style="max-width:1100px">
					<thead><tr><th>#</th><th>Ime i prezime</th><th>Spol</th><th>Godina</th><th>Dob</th><th>Mobitel</th><th>E-mail</th><th>Narudžba</th><th>Prijava</th><th>Brojač</th></tr></thead>
					<tbody>
					<?php
					$no = 0;
					foreach ( $people as $p ) :
						$no++;
						$off = ! $p['counts'];
						?>
						<tr<?php echo $off ? ' style="opacity:.55"' : ''; ?>>
							<td><?php echo (int) $no; ?></td>
							<td><?php echo esc_html( $p['name'] ?: '–' ); ?></td>
							<td><?php echo esc_html( 'm' === $p['spol'] ? '♂ M' : ( 'z' === $p['spol'] ? '♀ Ž' : '–' ) ); ?></td>
							<td><?php echo $p['god'] ? (int) $p['god'] : '–'; ?></td>
							<td><?php echo $p['age'] ? (int) $p['age'] : '–'; ?></td>
							<td><?php echo esc_html( $p['mobitel'] ); ?></td>
							<td><?php echo esc_html( $p['email'] ); ?></td>
							<td><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $p['order'] . '&action=edit' ) ); ?>">#<?php echo (int) $p['order']; ?></a> <span style="color:#646970"><?php echo esc_html( $status[ $p['status'] ] ?? $p['status'] ); ?></span></td>
							<td><?php echo $p['date'] ? esc_html( wp_date( 'j.n.Y. H:i', $p['date'] ) ) : ''; ?></td>
							<td>
								<?php if ( in_array( $p['status'], Plan_A_Tinder::STATUSES, true ) ) : ?>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=pat_skip&tour=' . $tour . '&item=' . $p['item'] . '&i=' . $p['i'] ), 'pat_skip' ) ); ?>"><?php echo $p['skip'] ? 'Ne broji se – vrati' : 'Broji se – isključi'; ?></a>
								<?php else : ?>
									ne broji se
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description">Osoba se ne broji ako je narudžba otkazana ili vraćena. Ako netko otkaže, a narudžba ostaje, klikni „isključi”. Za ranije prijave bez spola i godine piše „–” (ne ulaze u muškarce i žene, ali ulaze u ukupno).</p>
			<?php endif; ?>
		</div>
		<?php
	}
}
