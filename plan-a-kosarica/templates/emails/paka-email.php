<?php
/**
 * Zajednički HTML e-mail kupcu (Plan A košarica): isti sadržaj i redoslijed kao
 * završna stranica narudžbe. Tablični raspored i stilovi u elementima (za programe
 * za e-poštu), širina do 600 px.
 *
 * $paka_mode: 'received' (prijava zaprimljena, s podacima za plaćanje), 'paid'
 * (uplata zaprimljena, bez bloka za plaćanje) ili 'admin' (nova narudžba, e-mail vama:
 * kupac s poveznicama, članstvo i gumb za narudžbu, bez plaćanja i dijeljenja).
 *
 * 2D kod i podatke za plaćanje ispisuje postojeći dodatak (Hub3 / bankovni prijenos)
 * na kukama woocommerce_email_before_order_table i woocommerce_email_after_order_table;
 * njihov izlaz se ovdje prikazuje nepromijenjen.
 *
 * @package Plan_A_Kosarica
 *
 * @var WC_Order $order
 * @var string   $paka_mode
 * @var bool     $sent_to_admin
 * @var bool     $plain_text
 * @var WC_Email $email
 * @var string   $additional_content
 */

defined( 'ABSPATH' ) || exit;

Plan_A_Kosarica_Order::rendering( true );

$paka_s      = Plan_A_Kosarica_Settings::get();
$paka_navy   = $paka_s['navy'];
$paka_cta    = $paka_s['cta'];
$paka_font   = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
$paka_text   = '#24323f';
$paka_muted  = '#5f6b77';
$paka_paid   = 'paid' === $paka_mode;
$paka_admin  = 'admin' === $paka_mode;
$paka_first  = trim( (string) $order->get_billing_first_name() );
$paka_logo   = Plan_A_Kosarica_Order::logo_url();
$paka_site   = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

// Izlaz dodataka za plaćanje (Hub3, bankovni prijenos) na standardnim kukama e-maila.
// (U e-mailu vama nisu potrebni.)
$paka_before = $paka_admin ? '' : Plan_A_Kosarica_Order::capture( 'woocommerce_email_before_order_table', $order, $sent_to_admin, $plain_text, $email );
$paka_after  = $paka_admin ? '' : Plan_A_Kosarica_Order::capture( 'woocommerce_email_after_order_table', $order, $sent_to_admin, $plain_text, $email );
$paka_pay    = $paka_paid || $paka_admin ? '' : trim( $paka_before . $paka_after );
$paka_extra  = $paka_paid ? trim( $paka_before . $paka_after ) : '';

// Ostale kuke e-maila (strukturirani podaci, meta podaci narudžbe, dodaci); WooCommerceove
// tablice narudžbe i adresa zamijenjene su blokovima ispod.
$paka_removed_details = remove_action( 'woocommerce_email_order_details', array( WC()->mailer(), 'order_details' ), 10 );
$paka_hooks           = Plan_A_Kosarica_Order::capture( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );
if ( $paka_removed_details ) {
	add_action( 'woocommerce_email_order_details', array( WC()->mailer(), 'order_details' ), 10, 4 );
}
$paka_hooks .= Plan_A_Kosarica_Order::capture( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );
$paka_removed_cd = remove_action( 'woocommerce_email_customer_details', array( WC()->mailer(), 'customer_details' ), 10 );
$paka_removed_ea = remove_action( 'woocommerce_email_customer_details', array( WC()->mailer(), 'email_addresses' ), 20 );
$paka_hooks     .= Plan_A_Kosarica_Order::capture( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );
if ( $paka_removed_cd ) {
	add_action( 'woocommerce_email_customer_details', array( WC()->mailer(), 'customer_details' ), 10, 3 );
}
if ( $paka_removed_ea ) {
	add_action( 'woocommerce_email_customer_details', array( WC()->mailer(), 'email_addresses' ), 20, 3 );
}

$paka_sum   = Plan_A_Kosarica_Order::summary( $order );
$paka_tour  = Plan_A_Kosarica_Order::first_tour_id( $order );
$paka_share = $paka_tour && ! $paka_admin ? Plan_A_Kosarica_Order::share_url( $paka_tour ) : '';
$paka_name  = trim( $order->get_formatted_billing_full_name() );

/**
 * Retci bloka "Članstvo Plan A" [oznaka, vrijednost (HTML)]; puni ih dodatak Plan A članstvo.
 * $year je godina početka izleta (članarina vrijedi kalendarsku godinu).
 */
$paka_member = (array) apply_filters( 'plan_a_kosarica_member_rows', array(), $order, Plan_A_Kosarica_Order::tour_year( $order ), $paka_mode );

if ( $paka_admin ) {
	/* translators: %s: broj narudžbe */
	$paka_heading = sprintf( __( 'Nova prijava #%s', 'plan-a-kosarica' ), $order->get_order_number() );
} elseif ( $paka_paid ) {
	$paka_heading = __( 'Uplata je zaprimljena, vidimo se na izletu', 'plan-a-kosarica' );
} else {
	$paka_heading = '' !== $paka_first
		/* translators: %s: ime kupca */
		? sprintf( __( 'Hvala, %s! Vaša prijava je zaprimljena.', 'plan-a-kosarica' ), $paka_first )
		: __( 'Hvala! Vaša prijava je zaprimljena.', 'plan-a-kosarica' );
}

$paka_card  = 'background:#ffffff;border-radius:14px;padding:22px 24px;';
$paka_h2    = 'margin:0 0 12px;color:' . $paka_navy . ';font-family:' . $paka_font . ';font-size:19px;line-height:1.3;font-weight:700;';
$paka_p     = 'margin:0 0 8px;color:' . $paka_text . ';font-family:' . $paka_font . ';font-size:15px;line-height:1.55;';
$paka_label = 'padding:5px 12px 5px 0;color:' . $paka_muted . ';font-family:' . $paka_font . ';font-size:14px;line-height:1.45;vertical-align:top;white-space:nowrap;width:1%;';
$paka_value = 'padding:5px 0;color:' . $paka_text . ';font-family:' . $paka_font . ';font-size:15px;line-height:1.45;vertical-align:top;';

$paka_card_open  = '<tr><td style="padding:14px 0 0;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td class="paka-mail-card" style="' . $paka_card . '">';
$paka_card_close = '</td></tr></table></td></tr>';

$paka_rows = static function ( array $rows ) use ( $paka_label, $paka_value ): string {
	$html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">';
	foreach ( $rows as $row ) {
		if ( '' === $row[0] ) {
			$html .= '<tr><td colspan="2" style="' . $paka_value . '">' . wp_kses_post( $row[1] ) . '</td></tr>';
		} else {
			$html .= '<tr><td style="' . $paka_label . '">' . esc_html( $row[0] ) . '</td><td style="' . $paka_value . '">' . wp_kses_post( $row[1] ) . '</td></tr>';
		}
	}
	return $html . '</table>';
};
?>
<!DOCTYPE html>
<html lang="hr">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
<title><?php echo esc_html( $paka_site ); ?></title>
<style>
	body { margin: 0; padding: 0; background: #f3f6f9; }
	img { border: 0; max-width: 100%; height: auto; }
	.paka-mail-pay img { display: block; margin: 0 auto 12px; max-width: 100% !important; height: auto !important; }
	.paka-mail-pay table { width: 100% !important; }
	.paka-mail-pay .hub3-title { display: none !important; } /* naslov dodatka; blok već ima naslov */
	.paka-mail-pay .hub3-table td { padding: 4px 8px 4px 0; vertical-align: top; text-align: left; }
	@media only screen and (max-width: 620px) {
		.paka-mail-wrap { width: 100% !important; }
		.paka-mail-card { padding: 18px 16px !important; }
		.paka-mail-h1 { font-size: 22px !important; }
	}
</style>
</head>
<body style="margin:0;padding:0;background:#f3f6f9;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;"><?php echo esc_html( $paka_heading ); ?></div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f3f6f9;">
	<tr>
		<td align="center" style="padding:24px 0;">
			<table role="presentation" class="paka-mail-wrap" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;">

				<!-- Zaglavlje -->
				<tr>
					<td align="center" style="background:<?php echo esc_attr( $paka_navy ); ?>;border-radius:14px 14px 0 0;padding:22px 24px;">
						<?php if ( $paka_logo ) : ?>
							<img src="<?php echo esc_url( $paka_logo ); ?>" alt="<?php echo esc_attr( $paka_site ); ?>" height="48" style="display:block;height:48px;width:auto;max-width:260px;margin:0 auto;border:0;">
						<?php else : ?>
							<span style="color:#ffffff;font-family:<?php echo esc_attr( $paka_font ); ?>;font-size:24px;font-weight:800;"><?php echo esc_html( $paka_site ); ?></span>
						<?php endif; ?>
					</td>
				</tr>

				<!-- Naslov -->
				<tr>
					<td style="background:#ffffff;border-radius:0 0 14px 14px;padding:26px 24px 22px;text-align:center;">
						<table role="presentation" align="center" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto 12px;">
							<tr><td align="center" width="56" height="56" style="width:56px;height:56px;border-radius:28px;background:<?php echo $paka_admin ? esc_attr( $paka_s['accent'] ) : '#1e9e4a'; ?>;color:#ffffff;font-family:<?php echo esc_attr( $paka_font ); ?>;font-size:30px;font-weight:700;line-height:56px;text-align:center;"><?php echo $paka_admin ? '&#43;' : '&#10003;'; ?></td></tr>
						</table>
						<h1 class="paka-mail-h1" style="margin:0 0 8px;color:<?php echo esc_attr( $paka_navy ); ?>;font-family:<?php echo esc_attr( $paka_font ); ?>;font-size:24px;line-height:1.3;font-weight:800;text-align:center;"><?php echo esc_html( $paka_heading ); ?></h1>
						<p style="margin:0;color:<?php echo esc_attr( $paka_muted ); ?>;font-family:<?php echo esc_attr( $paka_font ); ?>;font-size:15px;line-height:1.5;">
							<?php if ( $paka_admin ) : ?>
								<strong style="color:<?php echo esc_attr( $paka_text ); ?>;"><?php echo esc_html( '' !== $paka_name ? $paka_name : $order->get_billing_email() ); ?></strong>
							<?php else : ?>
								<?php esc_html_e( 'Broj narudžbe:', 'plan-a-kosarica' ); ?> <strong style="color:<?php echo esc_attr( $paka_text ); ?>;"><?php echo esc_html( $order->get_order_number() ); ?></strong>
							<?php endif; ?>
							<?php if ( $order->get_date_created() ) : ?>
								&middot; <?php echo esc_html( Plan_A_Kosarica_Order::hr_date( $order->get_date_created()->getOffsetTimestamp() ) ); ?>
							<?php endif; ?>
						</p>
					</td>
				</tr>
				<tr><td style="height:16px;line-height:16px;font-size:0;">&nbsp;</td></tr>

				<?php if ( 'received' === $paka_mode ) : ?>
					<!-- Što sada? -->
					<?php echo $paka_card_open; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<h2 style="<?php echo esc_attr( $paka_h2 ); ?>"><?php esc_html_e( 'Što sada?', 'plan-a-kosarica' ); ?></h2>
						<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
							<?php foreach ( Plan_A_Kosarica_Order::next_steps( $order ) as $paka_i => $paka_step ) : ?>
								<?php $paka_done = 0 === $paka_i && Plan_A_Kosarica_Order::is_paid( $order ); ?>
								<tr>
									<td width="36" style="width:36px;padding:4px 10px 8px 0;vertical-align:top;">
										<div style="width:28px;height:28px;border-radius:14px;background:<?php echo $paka_done ? '#1e9e4a' : esc_attr( $paka_s['accent'] ); ?>;color:#ffffff;font-family:<?php echo esc_attr( $paka_font ); ?>;font-size:14px;font-weight:700;line-height:28px;text-align:center;"><?php echo $paka_done ? '&#10003;' : esc_html( (string) ( $paka_i + 1 ) ); ?></div>
									</td>
									<td style="<?php echo esc_attr( $paka_value ); ?>padding-bottom:8px;"><?php echo esc_html( $paka_step ); ?></td>
								</tr>
							<?php endforeach; ?>
						</table>
					<?php echo $paka_card_close; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

					<?php if ( '' !== $paka_pay ) : ?>
						<!-- Podaci za plaćanje (izlaz dodatka Hub3 / bankovni prijenos, nepromijenjen) -->
						<?php echo $paka_card_open; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<h2 style="<?php echo esc_attr( $paka_h2 ); ?>"><?php esc_html_e( 'Podaci za plaćanje', 'plan-a-kosarica' ); ?></h2>
							<p style="<?php echo esc_attr( $paka_p ); ?>text-align:center;font-weight:700;color:<?php echo esc_attr( $paka_navy ); ?>;"><?php esc_html_e( 'Skenirajte i platite', 'plan-a-kosarica' ); ?></p>
							<?php list( $paka_code, $paka_pay_rest ) = Plan_A_Kosarica_Order::payment_parts( $order, $paka_pay, 'woocommerce_email_after_order_table' ); ?>
							<div class="paka-mail-pay" style="<?php echo esc_attr( $paka_p ); ?>">
								<?php if ( '' !== $paka_code ) : ?>
									<div style="text-align:center;margin:0 0 16px;"><?php echo $paka_code; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- slika 2D koda dodatka za uplatnicu, nepromijenjena. ?></div>
								<?php endif; ?>
								<?php echo $paka_pay_rest; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- izlaz dodatka za plaćanje, nepromijenjen. ?>
							</div>
							<?php if ( ! Plan_A_Kosarica_Order::has_text_payment_data( $paka_pay_rest ) ) : ?>
								<p style="<?php echo esc_attr( $paka_p ); ?>">
									<?php esc_html_e( 'Ako se 2D kod ne prikazuje, sve podatke za ručnu uplatu pogledajte na stranici svoje narudžbe:', 'plan-a-kosarica' ); ?>
									<a href="<?php echo esc_url( $order->get_checkout_order_received_url() ); ?>" style="color:<?php echo esc_attr( $paka_s['accent'] ); ?>;"><?php esc_html_e( 'podaci za plaćanje', 'plan-a-kosarica' ); ?></a>
								</p>
							<?php endif; ?>
							<?php if ( '' !== $paka_s['deadline'] ) : ?>
								<p style="<?php echo esc_attr( $paka_p ); ?>"><strong><?php esc_html_e( 'Rok plaćanja:', 'plan-a-kosarica' ); ?></strong> <?php echo esc_html( $paka_s['deadline'] ); ?></p>
							<?php endif; ?>
						<?php echo $paka_card_close; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endif; ?>
				<?php endif; ?>

				<!-- Vaš izlet -->
				<?php echo $paka_card_open; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<h2 style="<?php echo esc_attr( $paka_h2 ); ?>"><?php echo esc_html( $paka_tour ? ( $paka_admin ? __( 'Izlet', 'plan-a-kosarica' ) : __( 'Vaš izlet', 'plan-a-kosarica' ) ) : __( 'Detalji narudžbe', 'plan-a-kosarica' ) ); ?></h2>
					<?php foreach ( $paka_sum['items'] as $paka_item_rows ) : ?>
						<?php echo $paka_rows( $paka_item_rows ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
						<div style="height:10px;line-height:10px;font-size:0;">&nbsp;</div>
					<?php endforeach; ?>
					<?php
					$paka_totals = $paka_sum['extra'];
					$paka_totals[] = array( __( 'Ukupno', 'plan-a-kosarica' ), '<strong style="color:' . esc_attr( $paka_navy ) . ';font-size:18px;">' . wp_kses_post( $paka_sum['total'] ) . '</strong>' );
					if ( $order->get_payment_method_title() ) {
						$paka_totals[] = array( __( 'Način plaćanja', 'plan-a-kosarica' ), esc_html( wp_strip_all_tags( $order->get_payment_method_title() ) ) );
					}
					if ( $paka_admin ) {
						$paka_totals[] = array( __( 'Plaćeno', 'plan-a-kosarica' ), $order->is_paid() ? '<span style="color:#1e7d3a;font-weight:700;">&#10003; ' . esc_html__( 'da', 'plan-a-kosarica' ) . '</span>' : '<span style="color:#b26200;font-weight:700;">' . esc_html__( 'čeka uplatu', 'plan-a-kosarica' ) . '</span>' );
					}
					if ( '' !== $paka_sum['note'] ) {
						$paka_totals[] = array( __( 'Bilješka', 'plan-a-kosarica' ), nl2br( esc_html( $paka_sum['note'] ) ) );
					}
					?>
					<div style="border-top:1px solid #e3e8ee;padding-top:8px;">
						<?php echo $paka_rows( $paka_totals ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
					</div>
				<?php echo $paka_card_close; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

				<!-- Vaši podaci -->
				<?php echo $paka_card_open; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<h2 style="<?php echo esc_attr( $paka_h2 ); ?>"><?php echo esc_html( $paka_admin ? __( 'Kupac', 'plan-a-kosarica' ) : __( 'Vaši podaci', 'plan-a-kosarica' ) ); ?></h2>
					<p style="<?php echo esc_attr( $paka_p ); ?>font-style:normal;">
						<?php echo wp_kses_post( $order->get_formatted_billing_address( esc_html__( 'N/A', 'woocommerce' ) ) ); ?>
						<?php if ( $order->get_billing_phone() ) : ?>
							<br><?php echo $paka_admin ? '<a href="tel:' . esc_attr( preg_replace( '/[^\d+]/', '', $order->get_billing_phone() ) ) . '" style="color:' . esc_attr( $paka_s['accent'] ) . ';">' . esc_html( $order->get_billing_phone() ) . '</a>' : esc_html( $order->get_billing_phone() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endif; ?>
						<?php if ( $order->get_billing_email() ) : ?>
							<br><?php echo $paka_admin ? '<a href="mailto:' . esc_attr( $order->get_billing_email() ) . '" style="color:' . esc_attr( $paka_s['accent'] ) . ';">' . esc_html( $order->get_billing_email() ) . '</a>' : esc_html( $order->get_billing_email() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endif; ?>
					</p>
				<?php echo $paka_card_close; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

				<?php if ( $paka_member ) : ?>
					<!-- Članstvo Plan A (dodatak Plan A članstvo) -->
					<?php echo $paka_card_open; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<h2 style="<?php echo esc_attr( $paka_h2 ); ?>"><?php esc_html_e( 'Članstvo Plan A', 'plan-a-kosarica' ); ?></h2>
						<?php echo $paka_rows( $paka_member ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
					<?php echo $paka_card_close; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>

				<?php if ( $paka_admin ) : ?>
					<tr>
						<td align="center" style="padding:18px 16px 6px;">
							<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>" style="display:inline-block;padding:14px 28px;border-radius:10px;background:<?php echo esc_attr( $paka_cta ); ?>;color:#ffffff;font-family:<?php echo esc_attr( $paka_font ); ?>;font-size:16px;font-weight:700;text-decoration:none;"><?php esc_html_e( 'Otvori narudžbu', 'plan-a-kosarica' ); ?></a>
						</td>
					</tr>
				<?php endif; ?>

				<?php if ( Plan_A_Kosarica_Order::has_content( $paka_extra . $paka_hooks ) ) : ?>
					<!-- Izlaz drugih dodataka -->
					<?php echo $paka_card_open; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<div style="<?php echo esc_attr( $paka_p ); ?>"><?php echo $paka_extra . $paka_hooks; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- izlaz kuka drugih dodataka. ?></div>
					<?php echo $paka_card_close; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>

				<?php if ( $additional_content && ! $paka_admin ) : ?>
					<?php echo $paka_card_open; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<div style="<?php echo esc_attr( $paka_p ); ?>"><?php echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) ); ?></div>
					<?php echo $paka_card_close; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>

				<?php if ( '' !== $paka_share ) : ?>
					<!-- Predloži ekipi -->
					<tr>
						<td align="center" style="padding:6px 16px 18px;">
							<p style="<?php echo esc_attr( $paka_p ); ?>text-align:center;font-weight:700;color:<?php echo esc_attr( $paka_navy ); ?>;"><?php esc_html_e( 'Povedite prijatelje na isti izlet', 'plan-a-kosarica' ); ?></p>
							<a href="<?php echo esc_attr( $paka_share ); ?>" style="display:inline-block;padding:13px 26px;border:2px solid #25d366;border-radius:10px;background:#ffffff;color:#128c7e;font-family:<?php echo esc_attr( $paka_font ); ?>;font-size:16px;font-weight:700;text-decoration:none;"><?php esc_html_e( 'Predloži ekipi', 'plan-a-kosarica' ); ?></a>
						</td>
					</tr>
				<?php endif; ?>

				<!-- Podnožje -->
				<tr>
					<td align="center" style="padding:10px 24px 8px;color:<?php echo esc_attr( $paka_muted ); ?>;font-family:<?php echo esc_attr( $paka_font ); ?>;font-size:13px;line-height:1.5;">
						<?php
						$paka_footer = (string) get_option( 'woocommerce_email_footer_text', '' );
						$paka_footer = '' !== $paka_footer ? (string) apply_filters( 'woocommerce_email_footer_text', $paka_footer, $email ) : $paka_site; // Standardni filtar popunjava {site_title}, {store_address} …
						echo wp_kses_post( wpautop( wptexturize( $paka_footer ) ) );
						?>
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="color:<?php echo esc_attr( $paka_s['accent'] ); ?>;"><?php echo esc_html( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></a>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
</body>
</html>
<?php
Plan_A_Kosarica_Order::rendering( false );
