<?php
/**
 * Administracija: pregled dogovora po izletu (Izleti → Dogovori). Bez imena.
 *
 * @package Plan_A_Izleti
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Izleti_Dogovor_Admin {

	const PAGE = 'plan-a-dogovori';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
	}

	public static function menu() {
		$parent = 'edit.php?post_type=' . Plan_A_Izleti_Data::post_type();
		add_submenu_page( $parent, __( 'Dogovori s ekipom', 'plan-a-izleti' ), __( 'Dogovori', 'plan-a-izleti' ), 'manage_options', self::PAGE, array( __CLASS__, 'render' ) );
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$rows   = Plan_A_Izleti_Dogovor::admin_rows();
		$totals = array(
			'created'     => 0,
			'yes_answers' => 0,
			'booked'      => 0,
			'active'      => 0,
		);
		foreach ( $rows as $row ) {
			foreach ( $totals as $key => $value ) {
				$totals[ $key ] = $value + (int) $row[ $key ];
			}
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Dogovori s ekipom', 'plan-a-izleti' ); ?></h1>
			<p><?php esc_html_e( 'Koliko je dogovora napravljeno gumbom „Predloži ekipi → Napravi dogovor”, koliko je odgovora „Ja sam za!” i koliko je dogovora završilo barem jednom rezervacijom. Imena sudionika se ovdje ne prikazuju.', 'plan-a-izleti' ); ?></p>

			<table class="widefat striped" style="max-width: 980px;">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Izlet', 'plan-a-izleti' ); ?></th>
						<th scope="col" class="num"><?php esc_html_e( 'Stvoreni dogovori', 'plan-a-izleti' ); ?></th>
						<th scope="col" class="num"><?php esc_html_e( 'Odgovori „Ja sam za!”', 'plan-a-izleti' ); ?></th>
						<th scope="col" class="num"><?php esc_html_e( 'Dogovori s rezervacijom', 'plan-a-izleti' ); ?></th>
						<th scope="col" class="num"><?php esc_html_e( 'Aktivni dogovori sada', 'plan-a-izleti' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $rows ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'Još nema dogovora.', 'plan-a-izleti' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $rows as $row ) : ?>
						<?php
						$tour_id = (int) $row['tour_id'];
						$title   = get_the_title( $tour_id );
						$link    = get_edit_post_link( $tour_id );
						?>
						<tr>
							<td>
								<?php if ( $link && '' !== $title ) : ?>
									<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a>
								<?php else : ?>
									<?php echo esc_html( '' !== $title ? $title : sprintf( /* translators: %d: ID izleta */ __( 'Obrisani izlet (ID %d)', 'plan-a-izleti' ), $tour_id ) ); ?>
								<?php endif; ?>
							</td>
							<td class="num"><?php echo esc_html( number_format_i18n( (int) $row['created'] ) ); ?></td>
							<td class="num"><?php echo esc_html( number_format_i18n( (int) $row['yes_answers'] ) ); ?></td>
							<td class="num">
								<?php
								echo esc_html( number_format_i18n( (int) $row['booked'] ) );
								if ( (int) $row['created'] > 0 ) {
									echo ' <span class="description">(' . esc_html( number_format_i18n( 100 * (int) $row['booked'] / (int) $row['created'] ) ) . ' %)</span>';
								}
								?>
							</td>
							<td class="num"><?php echo esc_html( number_format_i18n( (int) $row['active'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
				<?php if ( $rows ) : ?>
					<tfoot>
						<tr>
							<th scope="row"><?php esc_html_e( 'Ukupno', 'plan-a-izleti' ); ?></th>
							<th class="num"><?php echo esc_html( number_format_i18n( $totals['created'] ) ); ?></th>
							<th class="num"><?php echo esc_html( number_format_i18n( $totals['yes_answers'] ) ); ?></th>
							<th class="num"><?php echo esc_html( number_format_i18n( $totals['booked'] ) ); ?></th>
							<th class="num"><?php echo esc_html( number_format_i18n( $totals['active'] ) ); ?></th>
						</tr>
					</tfoot>
				<?php endif; ?>
			</table>

			<h2><?php esc_html_e( 'Kako se broji', 'plan-a-izleti' ); ?></h2>
			<ul style="list-style: disc; padding-left: 20px; max-width: 980px;">
				<li><?php esc_html_e( 'Zbrojevi se čuvaju trajno i ostaju i nakon što se dogovori automatski obrišu (7 dana nakon izleta, a dogovori bez odgovora nakon 3 dana).', 'plan-a-izleti' ); ?></li>
				<li><?php esc_html_e( '„Ja sam za!” je broj odgovora kojima je to trenutni izbor; kad netko promijeni odgovor, broj se ispravi.', 'plan-a-izleti' ); ?></li>
				<li><?php esc_html_e( 'Dogovor „s rezervacijom”: netko je s gumba „Rezerviraj mjesto” u dogovoru u istom pregledniku naručio taj izlet (narudžba dobiva oznaku _plan_a_dogovor).', 'plan-a-izleti' ); ?></li>
				<li><?php esc_html_e( '„Aktivni dogovori sada” su dogovori koji još nisu obrisani.', 'plan-a-izleti' ); ?></li>
			</ul>
		</div>
		<?php
	}
}
