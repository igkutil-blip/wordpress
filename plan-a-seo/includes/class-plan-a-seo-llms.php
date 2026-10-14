<?php
/**
 * /llms.txt – sažetak stranice za AI pretraživače i asistente (ChatGPT, Claude, Perplexity,
 * Google AI): tko smo, nadolazeći izleti (termin, trajanje, cijena, slobodna mjesta),
 * važne stranice i članci, kontakt. Slaže se sam iz stranice (spremljeno 6 sati ili do
 * prve promjene sadržaja).
 *
 * @package Plan_A_Seo
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Seo_Llms {

	const CACHE = 'plan_a_seo_llms';

	public static function init() {
		add_action( 'parse_request', array( __CLASS__, 'serve' ), 0 );
		add_action( 'save_post', array( __CLASS__, 'flush' ) );
		add_filter( 'robots_txt', array( __CLASS__, 'robots' ), 20 );
	}

	public static function flush() {
		delete_transient( self::CACHE );
	}

	public static function serve() {
		$path = trim( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
		if ( ( '' !== $home ? $home . '/' : '' ) . 'llms.txt' !== $path ) {
			return;
		}
		$txt = get_transient( self::CACHE );
		if ( ! is_string( $txt ) || '' === $txt ) {
			$txt = self::build();
			set_transient( self::CACHE, $txt, 6 * HOUR_IN_SECONDS );
		}
		status_header( 200 );
		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' ); // sažetak za AI, ne zasebna stranica u Googleu
		echo $txt; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- čisti tekst
		exit;
	}

	/** U robots.txt: poveznica na sažetak (komentar, ne smeta pretraživačima). */
	public static function robots( $txt ) {
		return rtrim( (string) $txt ) . "\n\n# Sažetak za AI pretraživače: " . home_url( '/llms.txt' ) . "\n";
	}

	private static function line( string $s ): string {
		return trim( (string) preg_replace( '/\s+/u', ' ', Plan_A_Seo_Text::clean( $s ) ) );
	}

	/** @var array<string,bool> Opisi koji su već upisani (isti opis na dvije stavke = drugoj se složi njezin). */
	private static $seen = array();

	/**
	 * Opis stavke: SEO opis iz Rank Matha, osim kad je prazan, predložak, isti kao kod druge stavke ili počinje
	 * vodičima/telefonom/cijenom – tada opis složen iz teksta stranice (isti kao za Google).
	 */
	private static function desc( int $id ): string {
		static $done = array();
		if ( isset( $done[ $id ] ) && self::$seen ) {
			return $done[ $id ]; // isti izlet u više termina – isti opis
		}
		return $done[ $id ] = self::desc_new( $id );
	}

	private static function desc_new( int $id ): string {
		$p = get_post( $id );
		if ( ! $p ) {
			return '';
		}
		$d    = self::line( (string) get_post_meta( $id, 'rank_math_description', true ) );
		$norm = Plan_A_Seo_Text::norm( $d );
		if ( '' === $d || false !== strpos( $d, '%' ) || isset( self::$seen[ $norm ] ) || ! Plan_A_Seo_Text::good_lead( $d, $p->post_title ) ) {
			$d    = self::line( Plan_A_Seo_Meta::description_for( $p ) );
			$norm = Plan_A_Seo_Text::norm( $d );
			if ( isset( self::$seen[ $norm ] ) ) {
				return '';
			}
		}
		self::$seen[ $norm ] = true;
		return $d;
	}

	/** Vodič: licence iz opisa vodiča (npr. „Planinski vodič – PV, UIMLA vodič – IML”). */
	private static function guide( WP_Post $g ): string {
		$t = self::line( wp_strip_all_tags( (string) $g->post_content ) );
		$t = trim( (string) preg_replace( '/\s*Upoznajte vodiča.*$/iu', '', $t ) );
		return 'licencirani vodič udruge Plan A' . ( '' !== $t ? ' (' . rtrim( Plan_A_Seo_Text::cut( $t, 220 ), '.' ) . ')' : '' ) . '.';
	}

	/** Adresa stranice po slugu ili adresi iz dodatka Custom Permalinks; '' ako je nema. */
	private static function page_url( string $slug ): string {
		$p = get_page_by_path( $slug );
		if ( ! $p ) {
			global $wpdb;
			$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'custom_permalink' AND meta_value IN (%s, %s) LIMIT 1", $slug, $slug . '/' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$p  = $id ? get_post( $id ) : null;
		}
		return $p && 'publish' === $p->post_status ? (string) get_permalink( $p ) : '';
	}

	private static function money( float $p ): string {
		return ( 0.0 === fmod( $p, 1.0 ) ? (string) (int) $p : number_format( $p, 2, ',', '.' ) ) . ' €';
	}

	public static function build(): string {
		self::$seen = array();
		$o   = Plan_A_Seo_Schema::ORG;
		$out = array();
		$out[] = '# Plan A – planinarenje i avantura (' . $o['legalName'] . ')';
		$out[] = '';
		$out[] = '> ' . $o['description'] . ' Izleti se objavljuju i prijavljuju na ' . home_url( '/' ) . ', a plaćaju uplatom na račun agencije organizatora (Adventure Donkey j.d.o.o.). Na izletima je potrebno članstvo u udruzi (pristupnica i godišnja članarina).';
		$out[] = '';
		$join  = self::page_url( 'pristupnica' );
		$priv  = self::page_url( 'planinarske-i-jedrilicarske-ture-po-vasoj-mjeri' );
		$sail  = self::page_url( 'jedrenje' );
		$out[] = '## Ukratko';
		$out[] = '';
		$out[] = '- Osnovano: ' . $o['founded'] . '. u Zagrebu (sportsko-rekreacijsko društvo, udruga).';
		$out[] = '- Vodiči: licencirani planinski vodiči Saveza gorskih vodiča Hrvatske (SGVH) i UIMLA-e – planinski vodič (PV), UIMLA vodič (IML), gorski vodič (GV).';
		$out[] = '- Za koga: za početnike i iskusne planinare; uz izlete vodimo tečajeve zimskog planinarenja, ferrata i lavinske obuke.';
		$out[] = '- Gdje: Hrvatska (Velebit, Dinara, Hvar …), Slovenija (Julijske Alpe) i Alpe (Mont Blanc, Monte Rosa).';
		$out[] = '- Kako izgleda izlet: polazak iz Zagreba (najčešće parking Shopping centra Prečko), prijevoz kombijima, male grupe uz vodiča.';
		$out[] = '- Oprema: opremu za ferrate (kaciga, pojas, set za ferratu, karabineri) članovi mogu besplatno posuditi od udruge.';
		$out[] = '- Prijava i plaćanje: online na ' . home_url( '/' ) . '; plaćanje uplatom na račun agencije Adventure Donkey j.d.o.o.; uz prijavu se može uzeti osiguranje od otkaza.';
		$out[] = '- Članstvo: potrebno za sudjelovanje na izletima – pristupnica online' . ( $join ? ' (' . $join . ')' : '' ) . ', godišnja članarina 15 € za kalendarsku godinu; članovi imaju popust na opremu u Iglu sportu.';
		$out[] = '- Privatne ture i jedrenje: privatne planinarske ture po dogovoru' . ( $priv ? ' (' . $priv . ')' : '' ) . ' i jedrenje Jadranom sa skiperom' . ( $sail ? ' (' . $sail . ')' : '' ) . '.';
		$out[] = '';
		$out[] = '- Sjedište: ' . $o['street'] . ', ' . $o['postal'] . ' ' . $o['city'] . ', Hrvatska';
		$out[] = '- Kontakt: ' . $o['email'] . ', ' . $o['telephone'];
		$out[] = '- Vođenje: licencirani planinski vodiči; izlete organizira turistička agencija Adventure Donkey j.d.o.o. u suradnji s udrugom';
		$out[] = '- Jezik: hrvatski';
		$out[] = '';

		// Nadolazeći izleti.
		$tours = get_posts( array( 'post_type' => 'ttbm_tour', 'post_status' => 'publish', 'posts_per_page' => 100 ) );
		$rows  = array();
		foreach ( $tours as $t ) {
			foreach ( Plan_A_Seo_Schema::terms( $t->ID ) as $term ) {
				$d     = substr( $term[0], 0, 10 );
				$e     = substr( (string) $term[1], 0, 10 );
				$when  = gmdate( 'j.n.Y.', strtotime( $d ) ) . ( $e && $e > $d ? ' – ' . gmdate( 'j.n.Y.', strtotime( $e ) ) : '' );
				$facts = array( 'termin ' . $when );
				$place = self::line( (string) get_post_meta( $t->ID, 'ttbm_location_name', true ) );
				if ( '' !== $place ) {
					$facts[] = $place;
				}
				$price = Plan_A_Seo_Schema::price( $t->ID );
				if ( $price > 0 ) {
					$facts[] = 'cijena od ' . self::money( $price );
				}
				$free = Plan_A_Seo_Schema::free( $t->ID, $d );
				if ( null !== $free ) {
					$facts[] = $free > 0 ? 'slobodnih mjesta: ' . $free : 'popunjeno';
				}
				$rows[ $d . '-' . $t->ID ] = '- [' . self::line( $t->post_title ) . '](' . get_permalink( $t ) . '): ' . implode( ', ', $facts ) . '. ' . self::desc( $t->ID );
			}
		}
		ksort( $rows );
		$out[] = '## Nadolazeći izleti';
		$out[] = '';
		$out   = array_merge( $out, $rows ? array_values( $rows ) : array( '- Trenutno nema objavljenih termina. Svi izleti: ' . home_url( '/izleti/' ) ) );
		$out[] = '';

		// Važne stranice.
		$skip  = array_merge( Plan_A_Seo_Index::noindex_ids(), array_map( 'intval', array( get_option( 'woocommerce_cart_page_id' ), get_option( 'woocommerce_checkout_page_id' ), get_option( 'woocommerce_myaccount_page_id' ) ) ) );
		$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => 100, 'orderby' => 'menu_order title', 'order' => 'ASC' ) );
		$out[] = '## Stranice';
		$out[] = '';
		foreach ( $pages as $p ) {
			if ( in_array( (int) $p->ID, $skip, true ) || post_password_required( $p ) ) {
				continue;
			}
			$d     = self::desc( $p->ID );
			$out[] = '- [' . self::line( $p->post_title ) . '](' . get_permalink( $p ) . ')' . ( '' !== $d ? ': ' . $d : '' );
		}
		$out[] = '';

		// Vodiči.
		$guides = get_posts( array( 'post_type' => 'ttbm_guide', 'post_status' => 'publish', 'posts_per_page' => 50 ) );
		if ( $guides ) {
			$out[] = '## Vodiči';
			$out[] = '';
			foreach ( $guides as $g ) {
				$out[] = '- [' . self::line( $g->post_title ) . '](' . get_permalink( $g ) . '): ' . self::guide( $g );
			}
			$out[] = '';
		}

		// Članci.
		$posts = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 60 ) );
		if ( $posts ) {
			$out[] = '## Članci (blog)';
			$out[] = '';
			foreach ( $posts as $p ) {
				$d     = self::desc( $p->ID );
				$out[] = '- [' . self::line( $p->post_title ) . '](' . get_permalink( $p ) . ')' . ( '' !== $d ? ': ' . $d : '' );
			}
			$out[] = '';
		}
		$out[] = '## Kontakt';
		$out[] = '';
		$out[] = '- E-mail: ' . $o['email'];
		$out[] = '- Telefon: ' . $o['telephone'];
		foreach ( $o['sameAs'] as $s ) {
			$out[] = '- ' . $s;
		}
		$out[] = '';
		$out[] = '_Ažurirano: ' . current_time( 'j.n.Y. H:i' ) . '_';
		return implode( "\n", $out ) . "\n";
	}
}
