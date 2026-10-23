<?php
/**
 * Postavke i kuke: vlastiti predlošci za blog (stranica objava, kategorije, oznake, objava), CSS, broj objava po stranici,
 * hrvatski natpisi umjesto Flatsomeovih engleskih. Ništa se ne sprema u objave – samo prikaz.
 */

defined( 'ABSPATH' ) || exit;

final class Domestic_Blog {

	const OPCIJA = 'domestic_blog';

	public static function zadano(): array {
		return array(
			'ukljuceno'   => 'yes',
			'nadnaslov'   => 'Priče naših partnera',
			'naslov'      => 'Vinarije i proizvođači',
			'uvod'        => 'Iza svake boce u našem shopu stoji obitelj, vinograd ili mala destilerija. Upoznajte hrvatske vinare i proizvođače s kojima surađujemo – i saznajte uz što njihova vina i pića najbolje pristaju.',
			'skrivene'    => array(),
			'datum'       => 'no',
			'po_stranici' => 13,
			'cta_naslov'  => 'Sva domaća vina i pića na jednom mjestu',
			'cta_tekst'   => 'Naručite izravno od nas – pažljivo zapakirano i dostavljeno na vaša vrata.',
		);
	}

	public static function postavke(): array {
		$s = get_option( self::OPCIJA, array() );
		return array_merge( self::zadano(), is_array( $s ) ? $s : array() );
	}

	public static function init(): void {
		if ( 'yes' !== self::postavke()['ukljuceno'] ) {
			return;
		}
		add_filter( 'template_include', array( __CLASS__, 'predlozak' ), 99 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'stil' ), 40 );
		add_action( 'pre_get_posts', array( __CLASS__, 'po_stranici' ) );
		add_filter( 'body_class', array( __CLASS__, 'klasa' ) );
		add_filter( 'gettext', array( __CLASS__, 'prijevod' ), 20, 3 );
	}

	/** Popis priča: stranica objava, kategorije, oznake, autor, datum (samo za objave). */
	public static function je_popis(): bool {
		return ! is_admin() && ( is_home() || is_category() || is_tag() || is_author() || is_date() ) && ! is_front_page();
	}

	public static function je_blog(): bool {
		return self::je_popis() || is_singular( 'post' );
	}

	public static function predlozak( $t ) {
		if ( is_singular( 'post' ) ) {
			return DSB_DIR . '/templates/objava.php';
		}
		return self::je_popis() ? DSB_DIR . '/templates/arhiva.php' : $t;
	}

	public static function stil(): void {
		if ( self::je_blog() ) {
			wp_enqueue_style( 'domestic-blog', DSB_URL . 'assets/blog.css', array(), DSB_VER . '.' . filemtime( DSB_DIR . '/assets/blog.css' ) );
		}
	}

	public static function po_stranici( WP_Query $q ): void {
		if ( is_admin() || ! $q->is_main_query() ) {
			return;
		}
		if ( $q->is_home() ) {
			$q->set( 'posts_per_page', max( 1, (int) self::postavke()['po_stranici'] ) ); // prva je istaknuta, ostale u mreži 3 u redu
		} elseif ( $q->is_category() || $q->is_tag() ) {
			$q->set( 'posts_per_page', 12 );
		}
	}

	public static function klasa( array $c ): array {
		if ( self::je_blog() ) {
			$c[] = 'dsb-blog';
		}
		return $c;
	}

	/** Flatsome nema hrvatski prijevod za blog – ostatak (pretraga, dodaci) na hrvatskom. */
	const PRIJEVODI = array(
		'Continue reading <span class="meta-nav">&rarr;</span>' => 'Pročitaj više <span class="meta-nav">&rarr;</span>',
		'Continue reading %s'                                    => 'Pročitaj više %s',
		'Continue reading'                                       => 'Pročitaj više',
		'Read more'                                              => 'Pročitaj više',
		'Posted on %s'                                           => 'Objavljeno %s',
		'Posted on'                                              => 'Objavljeno',
		'by %s'                                                  => 'autor: %s',
		'Posted in %1$s'                                         => 'Kategorija: %1$s',
		'Posted in'                                              => 'Kategorija:',
		'Category Archives: %s'                                  => 'Kategorija: %s',
		'Tag Archives: %s'                                       => 'Oznaka: %s',
		'Leave a comment'                                        => 'Ostavite komentar',
		'Older posts'                                            => 'Starije objave',
		'Newer posts'                                            => 'Novije objave',
		'Search Results for: %s'                                 => 'Rezultati pretrage: %s',
		'This entry was posted in %1$s. Bookmark the <a href="%3$s" title="Permalink to %4$s" rel="bookmark">permalink</a>.' => 'Kategorija: %1$s.',
		'This entry was posted in %1$s and tagged %2$s. Bookmark the <a href="%3$s" title="Permalink to %4$s" rel="bookmark">permalink</a>.' => 'Kategorija: %1$s. Oznake: %2$s.',
	);

	public static function prijevod( $tr, $tekst, $domena ) {
		if ( 'flatsome' !== $domena || $tr !== $tekst ) {
			return $tr;
		}
		return self::PRIJEVODI[ $tekst ] ?? $tr;
	}
}
