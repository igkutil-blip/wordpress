<?php
/**
 * Strukturirani podaci (JSON-LD) za Google i AI pretraživače:
 * - udruga: puni naziv, adresa, kontakt, društvene mreže;
 * - izlet: događaj (Event) za svaki nadolazeći termin s cijenom i slobodnim mjestima,
 *   te pitanja i odgovori (FAQPage) iz „Važno za izlet”.
 * Uz Rank Math podaci idu u njegov @graph; bez njega dodatak ispisuje svoj.
 *
 * @package Plan_A_Seo
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Seo_Schema {

	const ORG = array(
		'legalName'     => 'S.R.D. Plan A',
		'alternateName' => 'Plan A – planinarenje i avantura',
		'email'         => 'info@srd-plan-a.hr',
		'telephone'     => '+385 95 90 60 556',
		'street'        => 'Celovečka 60b',
		'postal'        => '10040',
		'city'          => 'Zagreb',
		'founded'       => '2023',
		'sameAs'        => array(
			'https://www.facebook.com/PlaninarenjeAvantura',
			'https://www.instagram.com/planinarenje_avantura',
			'https://www.linkedin.com/company/105569195/',
		),
		'description'   => 'Sportsko-rekreacijsko društvo Plan A iz Zagreba organizira planinarske izlete, ferrate, zimske uspone, tečajeve i jedrenje uz licencirane planinske vodiče Saveza gorskih vodiča Hrvatske (SGVH) i UIMLA-e.',
	);

	/** Vodiči (po adresi vodiča): sažetak sa stranice Vodiči – za llms.txt i opis za Google. */
	const GUIDES = array(
		'kresimir-gracin' => 'Gorski vodič (GV), međunarodni planinski vodič UIMLA IML i planinski vodič (PV) pri Savezu gorskih vodiča Hrvatske. Instruktor i mentor u školovanju novih profesionalnih vodiča i dugogodišnji tajnik SGVH. Vodi jednodnevne i višednevne ture različitih kondicijskih i tehničkih težina; licencirani vodič kajak tura (ACA level 2).',
		'igor-kutil'      => 'Planinski vodič (PV), međunarodni planinski vodič UIMLA IML i turističko-planinski vodič (TPV), profesionalni skiper (C kategorija, Yacht Master Category A). Specijaliziran za ture koje spajaju planinarenje i more, ferrate, sportsko penjanje i jedrenje; privatne ture i programi po mjeri.',
		'videk-ivan'      => 'Planinski vodič (PV), međunarodni planinski vodič UIMLA IML i turističko-planinski vodič (TPV), s tečajem prve pomoći u divljini (Wilderness First Aid). Trail trkač i penjač, diplomirani povjesničar – na izletima dijeli povijesne zanimljivosti; organizator gradske avanture Cajtnot u Zagrebu. Jednodnevni i višednevni izleti, trail avanture i kratki sportski penjački smjerovi.',
		'sonja-papes'     => 'Turističko-planinska vodičica (TPV). Vodi s naglaskom na doživljaj puta, miran tempo i povezanost s prirodom; grupne i privatne ture prilagođene različitim razinama kondicije.',
	);

	/** Kratko za Google (do 158 znakova). */
	const GUIDES_SHORT = array(
		'kresimir-gracin' => 'Krešimir Gracin – gorski vodič (GV), UIMLA IML i planinski vodič, instruktor SGVH. Jednodnevne i višednevne ture te kajak ture s udrugom Plan A.',
		'igor-kutil'      => 'Igor Kutil – planinski vodič (PV, UIMLA IML) i skiper. Ferrate, planinarenje i jedrenje, ture koje spajaju planine i more s udrugom Plan A.',
		'videk-ivan'      => 'Ivan Videk – planinski vodič (PV, UIMLA IML) i povjesničar. Planinarski izleti, trail avanture i penjanje uz povijesne priče, s udrugom Plan A.',
		'sonja-papes'     => 'Sonja Papeš – turističko-planinska vodičica (TPV) udruge Plan A. Izleti mirnog tempa s naglaskom na doživljaj puta, grupne i privatne ture.',
	);

	public static function guide_short( WP_Post $g ): string {
		return self::GUIDES_SHORT[ $g->post_name ] ?? '';
	}

	public static function guide_text( WP_Post $g ): string {
		return self::GUIDES[ $g->post_name ] ?? '';
	}

	public static function init() {
		add_filter( 'rank_math/json_ld', array( __CLASS__, 'rank_math' ), 99, 2 );
		add_action( 'wp_head', array( __CLASS__, 'own' ), 99 );
	}

	private static function org_id(): string {
		return home_url( '/#organization' );
	}

	/** Dodaci podacima o udruzi. */
	public static function org_extra(): array {
		$o = self::ORG;
		return array(
			'legalName'     => $o['legalName'],
			'alternateName' => $o['alternateName'],
			'description'   => $o['description'],
			'email'         => $o['email'],
			'telephone'     => $o['telephone'],
			'address'       => array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $o['street'],
				'postalCode'      => $o['postal'],
				'addressLocality' => $o['city'],
				'addressCountry'  => 'HR',
			),
			'foundingDate'  => $o['founded'],
			'areaServed'    => 'HR',
			'sameAs'        => $o['sameAs'],
		);
	}

	public static function rank_math( $data, $jsonld = null ) {
		$data = is_array( $data ) ? $data : array();
		foreach ( $data as $k => $e ) {
			$t = (array) ( $e['@type'] ?? array() );
			if ( isset( $e['@id'] ) && false !== strpos( (string) $e['@id'], '#organization' ) || array_intersect( $t, array( 'Organization', 'SportsClub', 'NGO' ) ) ) {
				$data[ $k ] = array_merge( self::org_extra(), $e );
				$data[ $k ]['sameAs'] = array_values( array_unique( array_merge( (array) ( $e['sameAs'] ?? array() ), self::ORG['sameAs'] ) ) );
				unset( $data[ $k ]['openingHours'] ); // udruga nema radno vrijeme ureda
			}
		}
		foreach ( self::tour_entities() as $k => $e ) {
			$data[ $k ] = $e;
		}
		return $data;
	}

	/** Bez Rank Matha: vlastiti JSON-LD. */
	public static function own() {
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			return;
		}
		$graph = array_values( self::tour_entities() );
		if ( is_front_page() ) {
			$graph[] = array_merge( array( '@type' => array( 'SportsClub', 'Organization' ), '@id' => self::org_id(), 'name' => 'Plan A', 'url' => home_url( '/' ) ), self::org_extra() );
		}
		if ( $graph ) {
			echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
		}
	}

	/* ---------------- Izlet ---------------- */

	/** Termini izleta: [[start Y-m-d (H:i), end], …] od danas. */
	public static function terms( int $id ): array {
		$out = array();
		if ( 'particular' === (string) get_post_meta( $id, 'ttbm_travel_type', true ) ) {
			foreach ( (array) get_post_meta( $id, 'ttbm_particular_dates', true ) as $r ) {
				if ( is_array( $r ) && ! empty( $r['ttbm_particular_start_date'] ) ) {
					$out[] = array( (string) $r['ttbm_particular_start_date'], (string) ( $r['ttbm_particular_end_date'] ?? '' ), (string) ( $r['ttbm_particular_start_time'] ?? '' ) );
				}
			}
		} elseif ( get_post_meta( $id, 'ttbm_travel_start_date', true ) ) {
			$out[] = array( (string) get_post_meta( $id, 'ttbm_travel_start_date', true ), (string) get_post_meta( $id, 'ttbm_travel_end_date', true ), (string) get_post_meta( $id, 'ttbm_travel_start_date_time', true ) );
		}
		$today = current_time( 'Y-m-d' );
		$out   = array_values( array_filter( $out, static fn( $t ) => substr( $t[0], 0, 10 ) >= $today ) );
		usort( $out, static fn( $a, $b ) => strcmp( $a[0], $b[0] ) );
		return array_slice( $out, 0, 3 );
	}

	/** Najniža puna cijena (bez karte za prvu ratu). */
	public static function price( int $id ): float {
		$full = array();
		$any  = array();
		foreach ( (array) get_post_meta( $id, 'ttbm_ticket_type', true ) as $t ) {
			$p = is_array( $t ) ? (float) ( $t['ticket_type_price'] ?? ( $t['ticket_price'] ?? 0 ) ) : 0;
			if ( $p <= 0 ) {
				continue;
			}
			$any[] = $p;
			if ( false === mb_stripos( (string) ( $t['ticket_type_name'] ?? '' ), 'prve rate' ) ) {
				$full[] = $p;
			}
		}
		$list = $full ?: $any;
		return $list ? (float) min( $list ) : 0.0;
	}

	/** Slobodna mjesta u terminu ili null (nepoznato). */
	public static function free( int $id, string $date ): ?int {
		if ( ! class_exists( 'TTBM_Function' ) || ! method_exists( 'TTBM_Function', 'get_total_available' ) ) {
			return null;
		}
		try {
			if ( method_exists( 'TTBM_Function', 'get_total_seat' ) && (int) TTBM_Function::get_total_seat( $id ) <= 0 ) {
				return null;
			}
			return max( 0, (int) TTBM_Function::get_total_available( $id, substr( $date, 0, 10 ) ) );
		} catch ( Throwable $e ) {
			return null;
		}
	}

	private static function iso( string $d, string $time = '' ): string {
		$d = trim( $d );
		if ( '' === $d ) {
			return '';
		}
		if ( strlen( $d ) <= 10 && preg_match( '/^\d{1,2}:\d{2}/', $time ) ) {
			$d .= ' ' . substr( $time, 0, 5 );
		}
		$ts = strtotime( $d );
		if ( ! $ts ) {
			return '';
		}
		return strlen( $d ) > 10 ? wp_date( 'c', $ts - (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ) : gmdate( 'Y-m-d', $ts );
	}

	/** Država kao ISO kod (Google to traži), inače naziv. */
	public static function country( string $place ): string {
		$map = array( 'hrvatska' => 'HR', 'slovenija' => 'SI', 'austrija' => 'AT', 'italija' => 'IT', 'bosna i hercegovina' => 'BA', 'bih' => 'BA', 'crna gora' => 'ME', 'albanija' => 'AL', 'njemačka' => 'DE', 'švicarska' => 'CH', 'francuska' => 'FR', 'mađarska' => 'HU', 'srbija' => 'RS', 'sjeverna makedonija' => 'MK', 'grčka' => 'GR', 'španjolska' => 'ES', 'slovačka' => 'SK', 'poljska' => 'PL', 'češka' => 'CZ', 'rumunjska' => 'RO', 'bugarska' => 'BG', 'nepal' => 'NP', 'maroko' => 'MA' );
		$k   = mb_strtolower( trim( $place ) );
		return $map[ $k ] ?? ( '' !== $place ? $place : 'HR' );
	}

	public static function tour_entities(): array {
		if ( ! is_singular( 'ttbm_tour' ) ) {
			return array();
		}
		$id   = (int) get_queried_object_id();
		$post = get_post( $id );
		if ( ! $post ) {
			return array();
		}
		$url   = get_permalink( $id );
		$name  = Plan_A_Seo_Text::clean( $post->post_title );
		$desc  = (string) get_post_meta( $id, 'rank_math_description', true );
		$desc  = '' !== $desc ? $desc : Plan_A_Seo_Text::cut( Plan_A_Seo_Text::lead( $post ), 300 );
		$img   = get_the_post_thumbnail_url( $id, 'full' );
		$place = Plan_A_Seo_Text::clean( (string) get_post_meta( $id, 'ttbm_location_name', true ) );
		$price = self::price( $id );
		$out   = array();
		foreach ( self::terms( $id ) as $i => $t ) {
			$free  = self::free( $id, $t[0] );
			$event = array(
				'@type'               => 'Event',
				'@id'                 => $url . '#termin-' . ( $i + 1 ),
				'name'                => $name,
				'description'         => $desc,
				'url'                 => $url,
				'startDate'           => self::iso( $t[0], $t[2] ),
				'eventStatus'         => 'https://schema.org/EventScheduled',
				'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
				'location'            => array(
					'@type'   => 'Place',
					'name'    => '' !== $place ? $place : $name,
					'address' => array( '@type' => 'PostalAddress', 'addressCountry' => self::country( $place ) ),
				),
				'organizer'           => array( '@type' => 'Organization', '@id' => self::org_id(), 'name' => 'Plan A', 'url' => home_url( '/' ) ),
				'inLanguage'          => 'hr',
			);
			$end = self::iso( $t[1] );
			if ( '' !== $end ) {
				$event['endDate'] = $end;
			}
			if ( $img ) {
				$event['image'] = array( $img );
			}
			if ( $price > 0 ) {
				$event['offers'] = array(
					'@type'         => 'Offer',
					'price'         => number_format( $price, 2, '.', '' ),
					'priceCurrency' => 'EUR',
					'url'           => $url,
					'availability'  => null !== $free && $free <= 0 ? 'https://schema.org/SoldOut' : 'https://schema.org/InStock',
					'validFrom'     => get_post_time( 'c', true, $id ),
				);
				if ( null !== $free ) {
					$event['remainingAttendeeCapacity'] = $free;
				}
			}
			$out[ 'PlanAEvent' . $i ] = $event;
		}
		// Pitanja i odgovori.
		$faq = array();
		foreach ( (array) get_post_meta( $id, 'mep_event_faq', true ) as $f ) {
			$q = is_array( $f ) ? Plan_A_Seo_Text::clean( (string) ( $f['ttbm_faq_title'] ?? '' ) ) : '';
			$a = is_array( $f ) ? Plan_A_Seo_Text::clean( Plan_A_Seo_Text::strip_codes( (string) ( $f['ttbm_faq_content'] ?? '' ) ) ) : '';
			if ( '' !== $q && '' !== $a ) {
				$faq[] = array( '@type' => 'Question', 'name' => $q, 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => Plan_A_Seo_Text::cut( $a, 1000 ) ) );
			}
		}
		if ( $faq ) {
			$out['PlanAFaq'] = array( '@type' => 'FAQPage', '@id' => $url . '#pitanja', 'mainEntity' => $faq, 'inLanguage' => 'hr' );
		}
		return $out;
	}
}
