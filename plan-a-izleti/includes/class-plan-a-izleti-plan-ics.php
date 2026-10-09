<?php
/**
 * Kalendar (iCalendar, .ics) plana izleta: pretplata za Google, Apple i Outlook kalendar
 * (/?plan_a_ics=plan) i pojedini izlet (/?plan_a_ics=<ključ retka>).
 *
 * @package Plan_A_Izleti
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Izleti_Plan_Ics {

	const PARAM = 'plan_a_ics';

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'output' ), 1 );
	}

	public static function url( string $which = 'plan' ): string {
		return add_query_arg( self::PARAM, rawurlencode( $which ), home_url( '/' ) );
	}

	/**
	 * Isti kalendar kao pretplata: webcal:// (Apple, Outlook) i poveznica za Google kalendar.
	 */
	public static function webcal_url(): string {
		return preg_replace( '#^https?://#', 'webcal://', self::url() );
	}

	public static function google_url(): string {
		return 'https://calendar.google.com/calendar/render?cid=' . rawurlencode( self::webcal_url() );
	}

	public static function output() {
		if ( ! isset( $_GET[ self::PARAM ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$which = sanitize_key( wp_unslash( $_GET[ self::PARAM ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$today = current_time( 'Y-m-d' );
		$since = gmdate( 'Y-m-d', strtotime( $today . ' -90 days' ) );
		$rows  = array();

		foreach ( Plan_A_Izleti_Plan::rows() as $row ) {
			if ( 'plan' === $which ? $row['to'] >= $since : $row['key'] === $which ) {
				$rows[] = $row;
			}
		}
		if ( 'plan' !== $which && ! $rows ) {
			status_header( 404 );
			nocache_headers();
			echo 'Izlet nije pronađen.';
			exit;
		}

		$host  = wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'plan-a';
		$name  = 'Plan izleta – ' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$stamp = gmdate( 'Ymd\THis\Z' );
		$lines = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Plan A//Plan izleta//HR',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'X-WR-CALNAME:' . self::text( $name ),
			'X-WR-TIMEZONE:Europe/Zagreb',
			'REFRESH-INTERVAL;VALUE=DURATION:PT6H',
			'X-PUBLISHED-TTL:PT6H',
		);
		foreach ( $rows as $row ) {
			if ( Plan_A_Izleti_Plan::long_row( $row ) ) {
				continue; // npr. sezona jedrenja: događaj od više mjeseci nije koristan u kalendaru
			}
			$status = Plan_A_Izleti_Plan::status( $row, $today );
			$url    = $row['tour'] ? Plan_A_Izleti_Data::tour_url( $row['tour'] ) : '';
			$desc   = array();
			if ( $row['guides'] ) {
				$desc[] = 'Vodiči: ' . $row['guides'];
			}
			if ( $row['note'] ) {
				$desc[] = $row['note'];
			}
			$desc[] = array(
				'open' => 'Prijave su otvorene: ' . $url,
				'full' => 'Popunjeno. Više: ' . $url,
				'soon' => 'Izlet još nije objavljen. Prijave uskoro na ' . home_url( '/' ),
				'past' => $url ? 'Više: ' . $url : '',
			)[ $status ];

			$lines[] = 'BEGIN:VEVENT';
			$lines[] = 'UID:' . $row['key'] . '@' . $host;
			$lines[] = 'DTSTAMP:' . $stamp;
			$lines[] = 'DTSTART;VALUE=DATE:' . str_replace( '-', '', $row['from'] );
			$lines[] = 'DTEND;VALUE=DATE:' . gmdate( 'Ymd', strtotime( $row['to'] . ' +1 day' ) );
			$lines[] = 'SUMMARY:' . self::text( $row['title'] . ( 'soon' === $status ? ' (uskoro)' : '' ) );
			$lines[] = 'DESCRIPTION:' . self::text( implode( "\n", array_filter( $desc ) ) );
			if ( $url ) {
				$lines[] = 'URL:' . esc_url_raw( $url );
			}
			$lines[] = 'STATUS:' . ( 'soon' === $status ? 'TENTATIVE' : 'CONFIRMED' );
			$lines[] = 'TRANSP:TRANSPARENT';
			$lines[] = 'END:VEVENT';
		}
		$lines[] = 'END:VCALENDAR';

		$file = 'plan' === $which ? 'plan-izleta.ics' : ( sanitize_title( $rows[0]['title'] ) ?: 'izlet' ) . '.ics';
		status_header( 200 );
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: ' . ( 'plan' === $which ? 'inline' : 'attachment' ) . '; filename="' . $file . '"' );
		header( 'Cache-Control: public, max-age=1800' );
		header( 'X-Content-Type-Options: nosniff' );
		echo implode( "\r\n", array_map( array( __CLASS__, 'fold' ), $lines ) ) . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- iCalendar, vrijednosti escapirane u text().
		exit;
	}

	/**
	 * Tekstna vrijednost prema RFC 5545 (\ ; , i novi red).
	 */
	private static function text( string $value ): string {
		$value = wp_strip_all_tags( html_entity_decode( $value, ENT_QUOTES, 'UTF-8' ) );
		return str_replace( array( '\\', ';', ',', "\r\n", "\n" ), array( '\\\\', '\;', '\,', '\n', '\n' ), $value );
	}

	/**
	 * Redak najviše 75 okteta, bez lomljenja UTF-8 znakova.
	 */
	private static function fold( string $line ): string {
		$out = '';
		$max = 75;
		while ( strlen( $line ) > $max ) {
			$cut = $max;
			while ( $cut > 0 && ( ord( $line[ $cut ] ) & 0xC0 ) === 0x80 ) {
				$cut--;
			}
			$out .= substr( $line, 0, $cut ) . "\r\n ";
			$line = substr( $line, $cut );
			$max  = 74; // nastavak počinje razmakom
		}
		return $out . $line;
	}
}
