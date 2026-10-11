<?php
/**
 * Izgled maila, slanje u paketima i odjava.
 *
 * @package Plan_A_Newsletter
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Newsletter_Mail {

	const NAVY   = '#12304b';
	const ORANGE = '#e8862a';
	const TEXT   = '#24323f';
	const MUTED  = '#5f6b77';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'unsubscribe' ), 1 );
	}

	/* ---------- Izgled ---------- */

	/** Cijeli mail za jednog primatelja. */
	public static function render( int $id, string $name, string $email ): string {
		$post    = get_post( $id );
		$subject = $post ? (string) $post->post_title : '';
		$body    = $post ? (string) $post->post_content : '';
		$body    = self::personalize( wpautop( $body ), $name );
		$tours   = Plan_A_Newsletter::meta( $id, 'tours', '' ) ? self::tours_block() : '';
		if ( false !== strpos( $body, '{izleti}' ) ) {
			$body  = str_replace( array( '<p>{izleti}</p>', '{izleti}' ), $tours, $body );
			$tours = '';
		}
		$body  = self::style( $body ) . $tours;
		$plain = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $body ) ) );
		$pre   = function_exists( 'mb_substr' ) ? mb_substr( $plain, 0, 140 ) : substr( $plain, 0, 140 );
		$logo  = Plan_A_Newsletter::logo();
		$unsub = '' !== $email ? self::unsub_url( $email ) : '#';
		$head  = '' !== $logo
			? '<img src="' . esc_url( $logo ) . '" alt="Plan A" height="44" style="display:block;height:44px;width:auto;max-width:220px;border:0">'
			: '<span style="color:#fff;font-size:22px;font-weight:bold;letter-spacing:2px">PLAN A</span>';

		return '<!doctype html><html lang="hr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . esc_html( $subject ) . '</title>'
			. '<style>a{color:#1a7fc1}img{max-width:100%;height:auto}@media (max-width:620px){.pan-pad{padding:22px 18px!important}.pan-tour-img{width:96px!important}}</style></head>'
			. '<body style="margin:0;padding:0;background:#f3f6f9;font-family:Arial,Helvetica,sans-serif;color:' . self::TEXT . '">'
			. '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:#f3f6f9">' . esc_html( $pre ) . '</div>'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f6f9"><tr><td align="center" style="padding:24px 12px">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden">'
			. '<tr><td style="background:' . self::NAVY . ';padding:18px 24px">' . $head . '</td></tr>'
			. '<tr><td class="pan-pad" style="padding:28px 28px 8px">'
			. '<h1 style="margin:0 0 18px;color:' . self::NAVY . ';font-size:24px;line-height:1.3">' . esc_html( $subject ) . '</h1>'
			. $body
			. '</td></tr>'
			. '<tr><td class="pan-pad" style="padding:8px 28px 26px">'
			. '<p style="margin:18px 0 0;padding-top:16px;border-top:1px solid #e3e8ee;color:#8a96a3;font-size:12px;line-height:1.6">'
			. 'S.R.D. Plan A · Celovečka 60b, Zagreb · <a href="mailto:info@srd-plan-a.hr" style="color:#8a96a3">info@srd-plan-a.hr</a> · 095 90 60 556<br>'
			. 'Ovaj e-mail primaš kao član udruge Plan A ili sudionik naših izleta. <a href="' . esc_url( $unsub ) . '" style="color:#8a96a3">Odjava od obavijesti</a></p>'
			. '</td></tr></table></td></tr></table></body></html>';
	}

	/** {ime} → ime primatelja; bez imena se izbaci i razmak ispred. */
	public static function personalize( string $html, string $name ): string {
		$name = esc_html( trim( $name ) );
		if ( '' === $name ) {
			return str_replace( array( ' {ime}', '{ime}' ), '', $html );
		}
		return str_replace( '{ime}', $name, $html );
	}

	/** Stilovi u samim oznakama (dio programa za poštu ne čita <style>). */
	public static function style( string $html ): string {
		$map = array(
			'p'          => 'margin:0 0 14px;font-size:16px;line-height:1.6;color:' . self::TEXT,
			'h2'         => 'margin:24px 0 10px;font-size:20px;line-height:1.3;color:' . self::NAVY,
			'h3'         => 'margin:20px 0 8px;font-size:17px;line-height:1.3;color:' . self::NAVY,
			'h4'         => 'margin:18px 0 8px;font-size:16px;line-height:1.3;color:' . self::NAVY,
			'ul'         => 'margin:0 0 14px;padding-left:22px',
			'ol'         => 'margin:0 0 14px;padding-left:22px',
			'li'         => 'margin:0 0 6px;font-size:16px;line-height:1.55;color:' . self::TEXT,
			'blockquote' => 'margin:0 0 14px;padding:12px 16px;background:#fdf1e4;border-left:4px solid ' . self::ORANGE . ';border-radius:8px',
			'img'        => 'display:block;max-width:100%;height:auto;border:0;border-radius:10px;margin:6px 0 14px',
			'a'          => 'color:#1a7fc1;font-weight:bold',
		);
		foreach ( $map as $tag => $css ) {
			$html = preg_replace_callback(
				'/<' . $tag . '(\s[^>]*)?>/i',
				static function ( $m ) use ( $css ) {
					$attrs = $m[1] ?? '';
					if ( preg_match( '/\sstyle\s*=\s*"([^"]*)"/i', $attrs, $s ) ) {
						$attrs = str_replace( $s[0], ' style="' . $css . ';' . $s[1] . '"', $attrs );
					} else {
						$attrs .= ' style="' . $css . '"';
					}
					return '<' . strtolower( explode( ' ', trim( $m[0], '<>' ) )[0] ) . $attrs . '>';
				},
				$html
			);
		}
		return $html;
	}

	/** Gumb u mailu. */
	public static function button( string $label, string $url ): string {
		return '<a href="' . esc_url( $url ) . '" style="display:inline-block;padding:10px 18px;border-radius:10px;background:' . self::ORANGE . ';color:#ffffff;font-size:14px;font-weight:bold;text-decoration:none">' . esc_html( $label ) . '</a>';
	}

	/** Nadolazeći izleti (jedan termin po izletu, najviše 6, bez rasprodanih). */
	public static function tours_block(): string {
		if ( ! class_exists( 'Plan_A_Izleti_Data' ) || ! method_exists( 'Plan_A_Izleti_Data', 'get_tour_dates' ) ) {
			return '';
		}
		$today = current_time( 'Y-m-d' );
		$until = gmdate( 'Y-m-d', strtotime( $today . ' +' . max( 7, (int) Plan_A_Newsletter::setting( 'days' ) ) . ' days' ) );
		$list  = array();
		foreach ( Plan_A_Izleti_Data::get_tour_dates() as $id => $row ) {
			if ( ! empty( $row['sold_out'] ) ) {
				continue;
			}
			foreach ( (array) $row['dates'] as $d ) {
				if ( $d >= $today && $d <= $until ) {
					$list[] = array( (int) $id, $d );
					break;
				}
			}
		}
		if ( ! $list ) {
			return '';
		}
		usort( $list, static fn( $a, $b ) => strcmp( $a[1], $b[1] ) );
		$list = array_slice( $list, 0, (int) apply_filters( 'plan_a_newsletter_max_tours', 6 ) );
		$out  = '<h2 style="margin:28px 0 12px;font-size:20px;line-height:1.3;color:' . self::NAVY . '">Nadolazeći izleti</h2>';
		foreach ( $list as $t ) {
			list( $id, $d ) = $t;
			$title = wp_strip_all_tags( html_entity_decode( Plan_A_Izleti_Data::title( $id ), ENT_QUOTES, 'UTF-8' ) );
			$url   = method_exists( 'Plan_A_Izleti_Data', 'tour_url' ) ? Plan_A_Izleti_Data::tour_url( $id ) : get_permalink( $id );
			$img   = '';
			if ( class_exists( 'Plan_A_Izleti_Shortcode' ) && method_exists( 'Plan_A_Izleti_Shortcode', 'image_id' ) ) {
				$img = (string) wp_get_attachment_image_url( Plan_A_Izleti_Shortcode::image_id( $id ), 'medium' );
			}
			$img   = '' !== $img ? $img : (string) get_the_post_thumbnail_url( $id, 'medium' );
			$price = '';
			if ( class_exists( 'Plan_A_Izleti_Shortcode' ) && method_exists( 'Plan_A_Izleti_Shortcode', 'get_price_html' ) ) {
				$src   = method_exists( 'Plan_A_Izleti_Data', 'source_id' ) ? Plan_A_Izleti_Data::source_id( $id ) : $id;
				$price = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( html_entity_decode( Plan_A_Izleti_Shortcode::get_price_html( $id, $src ), ENT_QUOTES, 'UTF-8' ) ) ) );
			}
			$out .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 12px;border:1px solid #e3e8ee;border-radius:12px;border-collapse:separate"><tr>'
				. ( '' !== $img ? '<td class="pan-tour-img" width="130" valign="top" style="width:130px;padding:10px 0 10px 10px"><a href="' . esc_url( $url ) . '"><img src="' . esc_url( $img ) . '" alt="" width="130" style="display:block;width:100%;height:auto;border:0;border-radius:8px"></a></td>' : '' )
				. '<td valign="top" style="padding:12px 14px">'
				. '<p style="margin:0 0 4px;color:' . self::ORANGE . ';font-size:13px;font-weight:bold">' . esc_html( gmdate( 'j.n.Y.', strtotime( $d ) ) ) . '</p>'
				. '<p style="margin:0 0 6px;font-size:16px;line-height:1.35;font-weight:bold"><a href="' . esc_url( $url ) . '" style="color:' . self::NAVY . ';text-decoration:none">' . esc_html( $title ) . '</a></p>'
				. ( '' !== $price ? '<p style="margin:0 0 10px;color:' . self::MUTED . ';font-size:14px">' . esc_html( $price ) . '</p>' : '' )
				. self::button( 'Pogledaj izlet', $url )
				. '</td></tr></table>';
		}
		return $out;
	}

	/* ---------- Slanje ---------- */

	public static function headers( string $email = '' ): array {
		$s    = Plan_A_Newsletter::settings();
		$from = sanitize_email( (string) $s['from_email'] );
		if ( ! is_email( $from ) ) {
			$from = sanitize_email( (string) get_option( 'admin_email' ) );
		}
		$name = str_replace( array( '"', "\r", "\n" ), '', trim( (string) $s['from_name'] ) ) ?: 'Plan A';
		$h    = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: "' . $name . '" <' . $from . '>',
			'Reply-To: ' . $from,
		);
		if ( '' !== $email ) {
			$h[] = 'List-Unsubscribe: <' . self::unsub_url( $email, true ) . '>, <mailto:' . $from . '?subject=Odjava>';
			$h[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
		}
		return $h;
	}

	public static function send_one( int $id, string $email, string $name ): bool {
		$post = get_post( $id );
		if ( ! $post || ! is_email( $email ) ) {
			return false;
		}
		return (bool) wp_mail( $email, wp_specialchars_decode( (string) $post->post_title, ENT_QUOTES ), self::render( $id, $name, $email ), self::headers( $email ) );
	}

	/** Probni mail (naslov s oznakom). */
	public static function send_test( int $id, string $email, string $name ): bool {
		$post = get_post( $id );
		if ( ! $post || ! is_email( $email ) ) {
			return false;
		}
		return (bool) wp_mail( $email, '[PROBA] ' . wp_specialchars_decode( (string) $post->post_title, ENT_QUOTES ), self::render( $id, $name, $email ), self::headers( $email ) );
	}

	/** Složi popis primatelja i kreni sa slanjem. */
	public static function start( int $id ): int {
		$r     = Plan_A_Newsletter_Recipients::build( (array) Plan_A_Newsletter::meta( $id, 'groups', array() ), (string) Plan_A_Newsletter::meta( $id, 'tour', '' ), (string) Plan_A_Newsletter::meta( $id, 'extra', '' ) );
		$queue = array();
		foreach ( $r['list'] as $e => $v ) {
			$queue[] = array( $e, (string) $v['n'] );
		}
		update_post_meta( $id, '_pan_queue', $queue );
		update_post_meta( $id, '_pan_total', count( $queue ) );
		update_post_meta( $id, '_pan_sent', 0 );
		update_post_meta( $id, '_pan_failed', array() );
		update_post_meta( $id, '_pan_skipped', (int) $r['skipped'] );
		update_post_meta( $id, '_pan_started', current_time( 'mysql' ) );
		update_post_meta( $id, '_pan_status', $queue ? 'sending' : 'sent' );
		if ( ! $queue ) {
			update_post_meta( $id, '_pan_finished', current_time( 'mysql' ) );
		}
		return count( $queue );
	}

	/** Pošalji do $max mailova; vraća broj pokušaja. */
	public static function send_batch( int $id, int $max ): int {
		$queue = (array) get_post_meta( $id, '_pan_queue', true );
		if ( ! $queue ) {
			self::finish( $id );
			return 0;
		}
		$sent   = (int) get_post_meta( $id, '_pan_sent', true );
		$failed = (array) get_post_meta( $id, '_pan_failed', true );
		$n      = 0;
		while ( $queue && $n < $max ) {
			if ( 'sending' !== Plan_A_Newsletter::status( $id ) ) {
				break; // pauzirano ili prekinuto u međuvremenu
			}
			list( $email, $name ) = array_pad( (array) array_shift( $queue ), 2, '' );
			++$n;
			if ( self::send_one( $id, (string) $email, (string) $name ) ) {
				++$sent;
			} else {
				$failed[] = (string) $email;
			}
			update_post_meta( $id, '_pan_queue', $queue );
			update_post_meta( $id, '_pan_sent', $sent );
			update_post_meta( $id, '_pan_failed', array_slice( $failed, -200 ) );
		}
		if ( ! $queue ) {
			self::finish( $id );
		}
		return $n;
	}

	/** Gotovo: stanje i kratko izvješće onome tko je poslao. */
	private static function finish( int $id ) {
		if ( 'sending' !== Plan_A_Newsletter::status( $id ) ) {
			return;
		}
		update_post_meta( $id, '_pan_status', 'sent' );
		update_post_meta( $id, '_pan_finished', current_time( 'mysql' ) );
		$user = get_userdata( (int) Plan_A_Newsletter::meta( $id, 'by', 0 ) );
		$to   = $user ? (string) $user->user_email : (string) get_option( 'admin_email' );
		$sent = (int) get_post_meta( $id, '_pan_sent', true );
		$fail = (array) get_post_meta( $id, '_pan_failed', true );
		$html = '<p>Newsletter <strong>' . esc_html( get_post_field( 'post_title', $id ) ) . '</strong> je poslan.</p>'
			. '<p>Poslano: <strong>' . $sent . '</strong> od ' . (int) get_post_meta( $id, '_pan_total', true ) . ' mailova.'
			. ( $fail ? '<br>Nije poslano (' . count( $fail ) . '): ' . esc_html( implode( ', ', array_slice( $fail, 0, 30 ) ) ) : '' ) . '</p>';
		wp_mail( $to, 'Newsletter je poslan: ' . wp_specialchars_decode( get_post_field( 'post_title', $id ), ENT_QUOTES ), $html, self::headers() );
	}

	/* ---------- Odjava ---------- */

	private static function key( string $email ): string {
		return substr( hash_hmac( 'sha256', 'pan-odjava|' . strtolower( $email ), wp_salt( 'auth' ) ), 0, 24 );
	}

	private static function b64( string $s ): string {
		return rtrim( strtr( base64_encode( $s ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}

	public static function unsub_url( string $email, bool $one_click = false ): string {
		$email = strtolower( trim( $email ) );
		return add_query_arg(
			array_filter(
				array(
					'pan_odjava' => self::b64( $email ),
					'k'          => self::key( $email ),
					'jk'         => $one_click ? 1 : 0,
				)
			),
			home_url( '/' )
		);
	}

	/** Odjava i ponovna prijava. Zajednička s odjavom sa siječanjskog e-maila za članarinu. */
	public static function set_optout( string $email, bool $out ) {
		$email = Plan_A_Newsletter_Recipients::norm( $email );
		if ( '' === $email ) {
			return;
		}
		$list = Plan_A_Newsletter::optouts();
		if ( $out ) {
			$list[ $email ] = current_time( 'mysql' );
		} else {
			unset( $list[ $email ] );
		}
		update_option( Plan_A_Newsletter::OPTOUT, $list, false );
		if ( Plan_A_Newsletter_Recipients::has_members() ) {
			$key = class_exists( 'Plan_A_Clanstvo_Ops' ) && defined( 'Plan_A_Clanstvo_Ops::NO_JAN' ) ? Plan_A_Clanstvo_Ops::NO_JAN : '_pac_no_jan';
			$ids = get_posts(
				array(
					'post_type'      => Plan_A_Clanstvo_Data::CPT,
					'post_status'    => 'any',
					'posts_per_page' => 20,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_key'       => '_pac_email', // phpcs:ignore WordPress.DB.SlowDBQuery
					'meta_value'     => $email, // phpcs:ignore WordPress.DB.SlowDBQuery
				)
			);
			foreach ( $ids as $mid ) {
				if ( $out ) {
					update_post_meta( $mid, $key, current_time( 'mysql' ) );
				} else {
					delete_post_meta( $mid, $key );
				}
			}
		}
	}

	public static function unsubscribe() {
		if ( ! isset( $_GET['pan_odjava'], $_GET['k'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$raw   = sanitize_text_field( wp_unslash( $_GET['pan_odjava'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$email = strtolower( (string) base64_decode( strtr( $raw, '-_', '+/' ), true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		$ok    = is_email( $email ) && hash_equals( self::key( $email ), sanitize_text_field( wp_unslash( $_GET['k'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		nocache_headers();
		$post = 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! $ok ) {
			if ( $post ) {
				status_header( 400 );
				exit;
			}
			wp_die( 'Poveznica za odjavu nije ispravna. Javite nam se na info@srd-plan-a.hr i odjavit ćemo vas.', 'Plan A', array( 'response' => 400 ) );
		}
		// Odjava jednim klikom iz programa za poštu (Gmail, Apple Mail …).
		if ( $post && ! empty( $_POST['List-Unsubscribe'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			self::set_optout( $email, true );
			status_header( 200 );
			echo 'OK';
			exit;
		}
		$url   = self::unsub_url( $email );
		$style = '<style>.pan-b{display:inline-block;margin-top:8px;padding:12px 22px;border:0;border-radius:10px;background:#e8862a;color:#fff;font-size:16px;font-weight:bold;cursor:pointer}.pan-l{background:#eaf3fc;color:#12304b}</style>';
		if ( $post && isset( $_POST['pan_akcija'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$out = 'odjava' === sanitize_key( wp_unslash( $_POST['pan_akcija'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			self::set_optout( $email, $out );
			$text = $out
				? '<h1>Odjavljeni ste</h1><p>Na adresu <strong>' . esc_html( $email ) . '</strong> više nećemo slati newsletter ni podsjetnik za članarinu. Članstvo i prijave na izlete ostaju kakvi jesu, a obavijesti o izletu na koji ste prijavljeni i dalje stižu.</p>'
					. '<form method="post" action="' . esc_url( $url ) . '"><input type="hidden" name="pan_akcija" value="prijava"><button class="pan-b pan-l" type="submit">Predomislio/la sam se – vrati me na popis</button></form>'
				: '<h1>Ponovno ste na popisu</h1><p>Na adresu <strong>' . esc_html( $email ) . '</strong> opet ćemo slati obavijesti o izletima i programima. Hvala!</p>';
		} else {
			$in   = isset( Plan_A_Newsletter::optouts()[ $email ] );
			$text = $in
				? '<h1>Već ste odjavljeni</h1><p>Adresa <strong>' . esc_html( $email ) . '</strong> nije na popisu za obavijesti.</p><form method="post" action="' . esc_url( $url ) . '"><input type="hidden" name="pan_akcija" value="prijava"><button class="pan-b pan-l" type="submit">Vrati me na popis</button></form>'
				: '<h1>Odjava od obavijesti</h1><p>Želite li da na adresu <strong>' . esc_html( $email ) . '</strong> više ne šaljemo newsletter i podsjetnik za članarinu?</p><form method="post" action="' . esc_url( $url ) . '"><input type="hidden" name="pan_akcija" value="odjava"><button class="pan-b" type="submit">Da, odjavi me</button></form>';
		}
		wp_die( $style . $text . '<p style="margin-top:22px"><a href="' . esc_url( home_url( '/' ) ) . '">Plan A</a></p>', 'Plan A', array( 'response' => 200 ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
