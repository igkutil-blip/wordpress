<?php
/**
 * Upiti s izleta („Pošaljite upit”): umjesto WpTravellyjevih mailova na engleskom šalju se
 * mailovi na hrvatskom u zajedničkom Plan A izgledu (nama, kupcu potvrda, odgovor kupcu).
 *
 * @package Plan_A_Prijave
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Prijave_Enquiry {

	public static function init() {
		add_filter( 'ttbm_enquiry_use_default', '__return_false', 20 );
		add_action( 'ttbm_enquiry_email', array( __CLASS__, 'send' ), 10, 1 );
	}

	private static function headers( string $reply = '' ): array {
		$from = sanitize_email( (string) get_option( 'woocommerce_email_from_address', '' ) ) ?: sanitize_email( (string) get_option( 'admin_email' ) );
		$name = str_replace( array( '"', "\r", "\n" ), '', trim( wp_strip_all_tags( (string) get_option( 'woocommerce_email_from_name', '' ) ) ) ?: 'Plan A' );
		return array(
			'Content-Type: text/html; charset=UTF-8',
			'From: "' . $name . '" <' . $from . '>',
			'Reply-To: ' . ( is_email( $reply ) ? $reply : $from ),
		);
	}

	/** Sastavi mail (i za primjerke). */
	public static function build( array $c ): array {
		$type    = (string) ( $c['type'] ?? '' );
		$subject = sanitize_text_field( (string) ( $c['subject'] ?? '' ) );
		$name    = sanitize_text_field( (string) ( $c['name'] ?? '' ) );
		$email   = sanitize_email( (string) ( $c['email'] ?? '' ) );
		$msg     = wpautop( esc_html( (string) ( $c['message'] ?? '' ) ) );
		$rows    = Plan_A_Email::rows(
			array(
				array( 'Ime', esc_html( $name ) ),
				array( 'E-mail', '<a href="mailto:' . esc_attr( $email ) . '" style="color:' . Plan_A_Email::color( 'accent' ) . ';">' . esc_html( $email ) . '</a>' ),
				array( 'Naslov', esc_html( $subject ) ),
			)
		);
		if ( 'enquiry_admin' === $type ) {
			return array(
				'to'      => (string) ( $c['to'] ?? '' ),
				'subject' => 'Novi upit: ' . $subject,
				'reply'   => $email,
				'html'    => Plan_A_Email::render(
					array( 'title' => 'Novi upit s izleta', 'icon' => 'mail', 'label' => 'Plan A · upit' ),
					Plan_A_Email::card( 'Pošiljatelj', $rows ) . Plan_A_Email::card( 'Poruka', Plan_A_Email::p( $msg ) )
					. Plan_A_Email::card( '', Plan_A_Email::button( 'Odgovori na upit', admin_url( 'edit.php?post_type=ttbm_tour&page=ttbm_get_enquiry' ) ) . Plan_A_Email::small( 'Odgovor pošalji iz administracije (Izleti → Upiti) ili samo odgovori na ovaj mail.' ) )
				),
			);
		}
		if ( 'enquiry_customer' === $type ) {
			return array(
				'to'      => $email,
				'subject' => 'Hvala na upitu – ' . $subject,
				'reply'   => '',
				'html'    => Plan_A_Email::render(
					array( 'title' => 'Hvala na upitu!', 'icon' => 'check', 'label' => 'Plan A · upit' ),
					Plan_A_Email::card( '', Plan_A_Email::p( 'Pozdrav ' . esc_html( $name ) . ',' ) . Plan_A_Email::p( 'primili smo tvoj upit i javit ćemo ti se što prije.' ) )
					. Plan_A_Email::card( 'Tvoj upit', $rows . '<div style="height:8px;"></div>' . Plan_A_Email::p( $msg ) )
				),
			);
		}
		// Odgovor na upit (iz administracije).
		return array(
			'to'      => (string) ( $c['to'] ?? '' ),
			'subject' => $subject,
			'reply'   => '',
			'html'    => Plan_A_Email::render(
				array( 'title' => $subject ?: 'Odgovor na upit', 'icon' => 'mail', 'label' => 'Plan A · odgovor na upit' ),
				Plan_A_Email::card( '', Plan_A_Email::p( wpautop( wp_kses_post( (string) ( $c['message'] ?? '' ) ) ) ) )
			),
		);
	}

	public static function send( $c ) {
		if ( ! is_array( $c ) || empty( $c['type'] ) ) {
			return;
		}
		$m = self::build( $c );
		if ( is_email( $m['to'] ) ) {
			wp_mail( $m['to'], $m['subject'], $m['html'], self::headers( $m['reply'] ) );
		}
	}
}
