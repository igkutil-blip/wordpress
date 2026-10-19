<?php
/**
 * Admin: Izleti → Tinder i planinarenje – postavke (izleti, WhatsApp grupa) i popis prijavljenih.
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
	}

	private static function cpt(): string {
		return class_exists( 'Plan_A_Prijave_Tour' ) ? Plan_A_Prijave_Tour::CPT : 'ttbm_tour';
	}

	public static function menu() {
		add_submenu_page( 'edit.php?post_type=' . self::cpt(), 'Tinder i planinarenje', 'Tinder i planinarenje', 'edit_posts', self::SLUG, array( __CLASS__, 'page' ) );
	}

	private static function url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'post_type' => self::cpt(), 'page' => self::SLUG ), $args ), admin_url( 'edit.php' ) );
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Nemaš ovlasti.' );
		}
		check_admin_referer( 'pat_save' );
		$tours = array_values( array_filter( array_map( 'absint', (array) ( $_POST['tours'] ?? array() ) ) ) );
		$wa    = esc_url_raw( trim( (string) wp_unslash( $_POST['wa'] ?? '' ) ) );
		update_option(
			Plan_A_Tinder::OPT,
			array(
				'tours' => $tours,
				'wa'    => preg_match( '#^https://(chat\.whatsapp\.com|wa\.me)/#i', $wa ) ? strtok( $wa, '?' ) : '',
				'min'   => max( 1, min( 20, absint( $_POST['min'] ?? 3 ) ) ),
				'title' => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ) ?: 'Tko dolazi',
			),
			false
		);
		Plan_A_Tinder::flush();
		wp_safe_redirect( self::url( array( 'saved' => 1 ) ) );
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
		$tours = get_posts(
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
			<h1>Tinder i planinarenje</h1>
			<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p>Spremljeno.</p></div>
			<?php endif; ?>
			<p>Vrijedi samo za izlete označene ispod: na stranici izleta brojač „Tko dolazi” (muškarci i žene, raspon godina) i gumb za WhatsApp grupu, u košarici spol i godina rođenja za svaku osobu, a ispod je popis prijavljenih. Prijava i dalje ide u tablicu za agenciju kao za sve izlete.</p>

			<?php foreach ( array_map( 'intval', (array) $s['tours'] ) as $tour ) : ?>
				<?php self::tour_table( $tour ); ?>
			<?php endforeach; ?>

			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<h2 style="margin-top:32px">Postavke</h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="pat_save">
					<?php wp_nonce_field( 'pat_save' ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">Izleti</th>
							<td>
								<div style="max-height:260px;overflow:auto;border:1px solid #dcdcde;background:#fff;padding:8px 12px;max-width:640px">
									<?php foreach ( $tours as $t ) : ?>
										<label style="display:block;margin:4px 0"><input type="checkbox" name="tours[]" value="<?php echo (int) $t->ID; ?>" <?php checked( in_array( (int) $t->ID, array_map( 'intval', (array) $s['tours'] ), true ) ); ?>> <?php echo esc_html( get_the_title( $t ) ?: '(bez naslova)' ); ?> <span style="color:#646970">– <?php echo esc_html( $labels[ $t->post_status ] ?? $t->post_status ); ?>, #<?php echo (int) $t->ID; ?></span></label>
									<?php endforeach; ?>
								</div>
								<p class="description">Izlet može ostati neobjavljen (skica) dok ga uređuješ – kao administrator ga vidiš s brojačem.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="pat-wa">WhatsApp grupa</label></th>
							<td><input type="url" id="pat-wa" name="wa" class="regular-text" value="<?php echo esc_attr( $s['wa'] ); ?>" placeholder="https://chat.whatsapp.com/…">
								<p class="description">Poveznica za pridruživanje grupi (iz WhatsAppa: Info o grupi → Poveznica za poziv). Gumb je na stranici izleta i na stranici „Hvala” nakon prijave. Prazno = bez gumba.</p></td>
						</tr>
						<tr>
							<th scope="row"><label for="pat-title">Naslov kartice</label></th>
							<td><input type="text" id="pat-title" name="title" class="regular-text" value="<?php echo esc_attr( $s['title'] ); ?>"></td>
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
