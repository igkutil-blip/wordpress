<?php
/**
 * Prikaz bloga: popis priča (naslov, filter po regiji, istaknuta priča, mreža kartica, stranice, poziv u trgovinu)
 * i jedna priča (naslov, slika, uređen tekst, „U našoj ponudi” preko cijele širine, još priča).
 * Sadržaj iz baze se samo čisti za prikaz (stari Flatsome klizači, razbacane slike, umetnuti fontovi).
 */

defined( 'ABSPATH' ) || exit;

final class Domestic_Blog_Prikaz {

	const STRELICA = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14m-5-5 5 5-5 5"/></svg>';

	/* ---------- popis priča ---------- */

	public static function arhiva(): void {
		$s     = Domestic_Blog::postavke();
		$blog  = self::blog_url();
		$kat   = is_category() ? get_queried_object() : null;
		$prva  = is_home() && ! is_paged();
		$put   = '<a href="' . esc_url( home_url( '/' ) ) . '">Početna</a>';
		$put .= '<span>/</span>' . ( is_home() ? '<span aria-current="page">' . esc_html( self::naziv_bloga() ) . '</span>' : '<a href="' . esc_url( $blog ) . '">' . esc_html( self::naziv_bloga() ) . '</a>' );
		if ( $kat ) {
			$h1   = self::kratko( $kat->name );
			$uvod = trim( wp_strip_all_tags( term_description( $kat ) ) );
		} elseif ( is_home() ) {
			$h1   = $s['naslov'];
			$uvod = $s['uvod'];
		} else {
			$h1   = wp_strip_all_tags( get_the_archive_title() );
			$uvod = '';
		}

		echo '<div class="dsb dsb-arhiva"><div class="dsb-sirina">';
		echo '<header class="dsb-vrh"><nav class="dsb-put" aria-label="Putanja">' . $put . '</nav>' // phpcs:ignore WordPress.Security.EscapeOutput
			. '<p class="dsb-nadnaslov">' . esc_html( $kat ? 'Priče naših partnera' : $s['nadnaslov'] ) . '</p>'
			. '<h1>' . esc_html( $h1 ) . '</h1>'
			. ( '' !== $uvod ? '<p class="dsb-uvod">' . esc_html( $uvod ) . '</p>' : '' ) . '</header>';
		echo self::filtri( $kat ? (int) $kat->term_id : 0 ); // phpcs:ignore WordPress.Security.EscapeOutput

		if ( have_posts() ) {
			$i = 0;
			echo '<div class="dsb-mreza">';
			while ( have_posts() ) {
				the_post();
				echo self::kartica( get_post(), $prva && 0 === $i ); // phpcs:ignore WordPress.Security.EscapeOutput
				++$i;
			}
			echo '</div>';
			$str = paginate_links( array( 'type' => 'list', 'prev_text' => '‹<span class="screen-reader-text"> Prethodna</span>', 'next_text' => '›<span class="screen-reader-text"> Sljedeća</span>', 'mid_size' => 1 ) );
			if ( $str ) {
				echo '<nav class="dsb-stranice" aria-label="Stranice">' . $str . '</nav>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
		} else {
			echo '<p class="dsb-prazno">Ovdje još nema priča. <a href="' . esc_url( $blog ) . '">Pogledajte sve priče</a>.</p>';
		}
		echo '</div>' . self::poziv() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/** Tablete kategorija s brojem priča (regije pa ostalo). */
	private static function filtri( int $sad ): string {
		$skrivene = array_map( 'intval', (array) Domestic_Blog::postavke()['skrivene'] );
		$kat      = get_categories( array( 'hide_empty' => true, 'exclude' => array_merge( array( (int) get_option( 'default_category' ) ), $skrivene ) ) );
		usort(
			$kat,
			static function ( $a, $b ) {
				$va = 0 === stripos( $a->name, 'Vinarije' ) ? 0 : 1;
				$vb = 0 === stripos( $b->name, 'Vinarije' ) ? 0 : 1;
				return $va <=> $vb ?: strcmp( $a->name, $b->name );
			}
		);
		$ukupno = (int) wp_count_posts( 'post' )->publish;
		$h      = '<a class="dsb-tableta' . ( $sad ? '' : ' dsb-sad' ) . '" href="' . esc_url( self::blog_url() ) . '"' . ( $sad ? '' : ' aria-current="page"' ) . '>Sve <span>' . $ukupno . '</span></a>';
		foreach ( $kat as $k ) {
			$on = $sad === (int) $k->term_id;
			$h .= '<a class="dsb-tableta' . ( $on ? ' dsb-sad' : '' ) . '" href="' . esc_url( get_category_link( $k ) ) . '"' . ( $on ? ' aria-current="page"' : '' ) . '>' . esc_html( self::kratko( $k->name ) ) . ' <span>' . (int) $k->count . '</span></a>';
		}
		return '<nav class="dsb-filtri" aria-label="Regije i vrste">' . $h . '</nav>';
	}

	/** Kartica priče (istaknuta = prva na stranici objava, vodoravno). */
	public static function kartica( WP_Post $p, bool $istaknuta = false ): string {
		$url  = get_permalink( $p );
		$slik = self::slika( $p, $istaknuta ? 'full' : 'large', $istaknuta ? '(max-width: 900px) 100vw, 680px' : '(max-width: 600px) 100vw, (max-width: 900px) 50vw, 400px' );
		$kat  = self::kategorija( $p );
		$n    = count( self::ponuda( $p->post_content )['ids'] );
		$dno  = ( $n ? '<span class="dsb-k-broj">' . esc_html( $n . ' ' . self::mn( $n, 'proizvod', 'proizvoda', 'proizvoda' ) ) . ' u ponudi</span>' : '<span></span>' )
			. '<span class="dsb-k-vise">Pročitaj priču' . self::STRELICA . '</span>';
		return '<article class="dsb-kartica' . ( $istaknuta ? ' dsb-istaknuta' : '' ) . '">'
			. '<a class="dsb-k-slika" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' . $slik . '</a>'
			. '<div class="dsb-k-tijelo">'
			. ( $kat ? '<p class="dsb-k-kat">' . esc_html( self::kratko( $kat->name ) ) . '</p>' : '' )
			. '<h2 class="dsb-k-naslov"><a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $p ) ) . '</a></h2>'
			. '<p class="dsb-k-uvod">' . esc_html( self::sazetak( $p, $istaknuta ? 40 : 24 ) ) . '</p>'
			. '<a class="dsb-k-dno" href="' . esc_url( $url ) . '" aria-label="Pročitaj priču: ' . esc_attr( get_the_title( $p ) ) . '">' . $dno . '</a>'
			. '</div></article>';
	}

	private static function poziv(): string {
		$s    = Domestic_Blog::postavke();
		$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
		return '<section class="dsb-poziv"><div class="dsb-sirina"><p class="dsb-nadnaslov">Domestic Spirits</p><h2>' . esc_html( $s['cta_naslov'] ) . '</h2>'
			. '<p>' . esc_html( $s['cta_tekst'] ) . '</p><a class="dsb-gumb dsb-gumb-svijetli" href="' . esc_url( $shop ) . '">U trgovinu' . self::STRELICA . '</a></div></section>';
	}

	/* ---------- jedna priča ---------- */

	public static function objava(): void {
		while ( have_posts() ) {
			the_post();
			$p     = get_post();
			$raw   = $p->post_content;
			$pon   = self::ponuda( $raw );
			$min   = max( 1, (int) ceil( self::rijeci( $raw ) / 200 ) );
			$n     = count( $pon['ids'] );
			$kat   = array_filter( get_the_category( $p->ID ), array( __CLASS__, 'vidljiva' ) );
			$blog  = self::blog_url();
			$meta  = array( $min . ' min čitanja' );
			if ( $n ) {
				$meta[] = $n . ' ' . self::mn( $n, 'proizvod', 'proizvoda', 'proizvoda' ) . ' u ponudi';
			}
			if ( 'yes' === Domestic_Blog::postavke()['datum'] ) {
				array_unshift( $meta, get_the_date( 'j. F Y.' ) );
			}
			$tab = '';
			foreach ( $kat as $k ) {
				$tab .= '<a class="dsb-tableta" href="' . esc_url( get_category_link( $k ) ) . '">' . esc_html( self::kratko( $k->name ) ) . '</a>';
			}

			echo '<div class="dsb dsb-objava"><article id="post-' . (int) $p->ID . '" class="dsb-clanak">';
			echo '<header class="dsb-o-vrh dsb-sirina"><nav class="dsb-put" aria-label="Putanja"><a href="' . esc_url( home_url( '/' ) ) . '">Početna</a><span>/</span><a href="' . esc_url( $blog ) . '">' . esc_html( self::naziv_bloga() ) . '</a></nav>'
				. ( $tab ? '<div class="dsb-o-kat">' . $tab . '</div>' : '' ) // phpcs:ignore WordPress.Security.EscapeOutput
				. '<h1>' . esc_html( get_the_title() ) . '</h1>'
				. '<p class="dsb-o-meta">' . esc_html( implode( ' · ', $meta ) ) . ( $n ? ' · <a href="#dsb-ponuda">Pogledaj ponudu ↓</a>' : '' ) . '</p></header>';
			$sl = self::slika( $p, 'full', '(max-width: 1140px) 100vw, 1100px', true );
			if ( $sl ) {
				echo '<figure class="dsb-o-slika dsb-sirina">' . $sl . '</figure>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '<div class="dsb-o-sadrzaj">' . self::vrati_galerije( apply_filters( 'the_content', self::ocisti( $pon['ostatak'], $p ) ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</article>';

			if ( $pon['kod'] ) {
				$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
				echo '<section id="dsb-ponuda" class="dsb-ponuda"><div class="dsb-sirina"><p class="dsb-nadnaslov">Naručite kod nas</p><h2>U našoj ponudi</h2>'
					. '<p class="dsb-ponuda-uvod">Vina i pića iz ove priče dostavljamo diljem Hrvatske.</p>'
					. do_shortcode( $pon['kod'] ) // phpcs:ignore WordPress.Security.EscapeOutput
					. '<p class="dsb-sredina"><a class="dsb-gumb dsb-gumb-obrub" href="' . esc_url( $shop ) . '">Svi proizvodi' . self::STRELICA . '</a></p></div></section>';
			}
			echo self::jos( $p, $kat ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</div>';
		}
	}

	/** Još tri priče: najprije iz iste kategorije, zatim najnovije. */
	private static function jos( WP_Post $p, array $kat ): string {
		$ids = array();
		if ( $kat ) {
			$ids = get_posts( array( 'category__in' => wp_list_pluck( $kat, 'term_id' ), 'post__not_in' => array( $p->ID ), 'numberposts' => 3, 'fields' => 'ids', 'orderby' => 'rand' ) );
		}
		if ( count( $ids ) < 3 ) {
			$ids = array_merge( $ids, get_posts( array( 'post__not_in' => array_merge( array( $p->ID ), $ids ), 'numberposts' => 3 - count( $ids ), 'fields' => 'ids' ) ) );
		}
		if ( ! $ids ) {
			return '';
		}
		$h = '';
		foreach ( $ids as $id ) {
			$h .= self::kartica( get_post( $id ) );
		}
		return '<section class="dsb-jos dsb-sirina"><h2>Još priča</h2><div class="dsb-mreza">' . $h . '</div>'
			. '<p class="dsb-sredina"><a class="dsb-gumb dsb-gumb-obrub" href="' . esc_url( self::blog_url() ) . '">Sve priče' . self::STRELICA . '</a></p></section>';
	}

	/* ---------- sadržaj ---------- */

	/** Odvaja „U našoj ponudi” + [products] s kraja teksta (prikazuje se preko cijele širine). */
	public static function ponuda( string $c ): array {
		$kod = '';
		$ids = array();
		if ( preg_match( '~<h2>\s*U našoj ponudi\s*</h2>\s*(\[products\b[^\]]*\])~u', $c, $m ) ) {
			$kod = $m[1];
			$c   = str_replace( $m[0], '', $c );
			if ( preg_match( '~ids="([\d,\s]+)"~', $kod, $x ) ) {
				$ids = array_filter( array_map( 'intval', explode( ',', $x[1] ) ) );
			}
		}
		return array( 'kod' => $kod, 'ids' => $ids, 'ostatak' => $c );
	}

	/** Čišćenje starih objava za prikaz: klizači i nizovi slika → galerija, bez umetnutih fontova, bez naslova-duplikata. */
	public static function ocisti( string $c, WP_Post $p ): string {
		// Flatsome klizač slika → galerija
		$c = preg_replace_callback(
			'~\[ux_slider[^\]]*\](.*?)\[/ux_slider\]~s',
			static function ( $m ) {
				preg_match_all( '~\[ux_image[^\]]*\bid="(\d+)"~', $m[1], $x );
				return self::galerija( array_map( 'intval', $x[1] ) );
			},
			$c
		);
		// dvije ili više slika zaredom → galerija
		$c = preg_replace_callback(
			'~(?:<img\b[^>]*>\s*(?:&nbsp;)?\s*){2,}~',
			static function ( $m ) {
				preg_match_all( '~<img\b[^>]*>~', $m[0], $x );
				$ids = array();
				foreach ( $x[0] as $img ) {
					$ids[] = preg_match( '~wp-image-(\d+)~', $img, $y ) ? (int) $y[1] : $img;
				}
				return self::galerija( $ids );
			},
			$c
		);
		// stari blok proizvoda unutar teksta → isti izgled kao ostali proizvodi
		$c = preg_replace_callback(
			'~\[ux_products\b[^\]]*\bids="([\d,\s]+)"[^\]]*\]~',
			static function ( $m ) {
				return '<div class="dsb-siroko">[products ids="' . esc_attr( preg_replace( '~\s+~', '', $m[1] ) ) . '" orderby="post__in" columns="4"]</div>';
			},
			$c
		);
		// prvi naslov VELIKIM SLOVIMA (npr. „VINARIJA LAGENA”) ponavlja naslov stranice
		$c = preg_replace_callback(
			'~<h[2-4][^>]*>(.*?)</h[2-4]>~su',
			static function ( $m ) {
				$t = trim( wp_strip_all_tags( $m[1] ) );
				return '' !== $t && mb_strtoupper( $t ) === $t ? '' : $m[0];
			},
			$c,
			1
		);
		$c = preg_replace( '~\s(?:style|align)="[^"]*"~i', '', $c );
		$c = preg_replace( '~<span>(.*?)</span>~s', '$1', $c );
		$c = preg_replace( '~^\s*&nbsp;\s*$~m', '', $c );
		// tablica „Za stolom”: oznake stupaca za prikaz na mobitelu
		$c = preg_replace_callback(
			'~<table class="dsb-stol">.*?</table>~s',
			static function ( $m ) {
				if ( ! preg_match( '~<thead>(.*?)</thead>~s', $m[0], $h ) || ! preg_match_all( '~<th>(.*?)</th>~s', $h[1], $o ) ) {
					return $m[0];
				}
				$oznake = array_slice( $o[1], 1 );
				return preg_replace_callback(
					'~<tr>(<th>.*?</th>)(.*?)</tr>~s',
					static function ( $r ) use ( $oznake ) {
						$i = 0;
						return '<tr>' . $r[1] . preg_replace_callback(
							'~<td>~',
							static function () use ( $oznake, &$i ) {
								return '<td data-l="' . esc_attr( wp_strip_all_tags( $oznake[ $i++ ] ?? '' ) ) . '">';
							},
							$r[2]
						) . '</tr>';
					},
					$m[0]
				);
			},
			$c
		);
		return $c;
	}

	/** @var string[] galerije spremljene dok the_content ne prođe (wpautop bi ih razbio u odlomke) */
	private static $galerije = array();

	/** Vraća galerije na mjesto oznaka nakon the_content. */
	public static function vrati_galerije( string $html ): string {
		foreach ( self::$galerije as $i => $g ) {
			$html = preg_replace( '~(?:<p>\s*)?DSBGALERIJA' . $i . 'X(?:\s*</p>)?~', $g, $html );
		}
		self::$galerije = array();
		return $html;
	}

	/** @param array<int|string> $slike ID priloga ili gotov <img>. */
	private static function galerija( array $slike ): string {
		$g = self::galerija_html( $slike );
		if ( '' === $g ) {
			return '';
		}
		self::$galerije[] = $g;
		return "\n\nDSBGALERIJA" . ( count( self::$galerije ) - 1 ) . "X\n\n";
	}

	private static function galerija_html( array $slike ): string {
		$h = '';
		foreach ( $slike as $s ) {
			$h .= is_int( $s ) ? wp_get_attachment_image( $s, 'large', false, array( 'loading' => 'lazy', 'sizes' => '(max-width: 600px) 50vw, 320px' ) ) : $s;
		}
		return '' === $h ? '' : '<div class="dsb-galerija dsb-g-' . min( 4, count( $slike ) ) . '">' . $h . '</div>';
	}

	/* ---------- pomoćno ---------- */

	private static function slika( WP_Post $p, string $vel, string $sizes, bool $prva = false ): string {
		$id = (int) get_post_thumbnail_id( $p );
		if ( ! $id && preg_match( '~(?:\[ux_image[^\]]*\bid="|wp-image-)(\d+)~', $p->post_content, $m ) ) {
			$id = (int) $m[1];
		}
		if ( ! $id ) {
			return $prva ? '' : '<span class="dsb-bez-slike" aria-hidden="true"></span>';
		}
		$alt = get_post_meta( $id, '_wp_attachment_image_alt', true );
		return wp_get_attachment_image( $id, $vel, false, array( 'class' => 'dsb-img', 'sizes' => $sizes, 'alt' => $alt ? $alt : get_the_title( $p ), 'loading' => $prva ? 'eager' : 'lazy', 'fetchpriority' => $prva ? 'high' : 'auto' ) );
	}

	private static function sazetak( WP_Post $p, int $rijeci ): string {
		$t = has_excerpt( $p ) ? $p->post_excerpt : self::ocisti( self::ponuda( $p->post_content )['ostatak'], $p );
		$t = preg_replace( array( '~<table.*?</table>~s', '~DSBGALERIJA\d+X~' ), '', $t );
		self::$galerije = array();
		$t = html_entity_decode( wp_strip_all_tags( strip_shortcodes( $t ) ), ENT_QUOTES, 'UTF-8' );
		return wp_trim_words( $t, $rijeci, '…' );
	}

	private static function rijeci( string $c ): int {
		$t = trim( wp_strip_all_tags( strip_shortcodes( $c ) ) );
		return '' === $t ? 0 : count( preg_split( '~\s+~u', $t ) );
	}

	private static function kategorija( WP_Post $p ) {
		$k = array_values( array_filter( get_the_category( $p->ID ), array( __CLASS__, 'vidljiva' ) ) );
		return $k[0] ?? null;
	}

	public static function vidljiva( $k ): bool {
		$skrivene = array_map( 'intval', (array) Domestic_Blog::postavke()['skrivene'] );
		return (int) $k->term_id !== (int) get_option( 'default_category' ) && ! in_array( (int) $k->term_id, $skrivene, true );
	}

	/** „Vinarije Istra i Kvarner” → „Istra i Kvarner”. */
	public static function kratko( string $n ): string {
		$n = trim( preg_replace( '~^Vinarije\s+~u', '', $n ) );
		return mb_strtoupper( mb_substr( $n, 0, 1 ) ) . mb_substr( $n, 1 );
	}

	private static function blog_url(): string {
		$id = (int) get_option( 'page_for_posts' );
		return $id ? get_permalink( $id ) : home_url( '/' );
	}

	private static function naziv_bloga(): string {
		$id = (int) get_option( 'page_for_posts' );
		return $id ? get_the_title( $id ) : 'Blog';
	}

	private static function mn( int $n, string $j, string $d, string $m ): string {
		if ( 1 === $n % 10 && 11 !== $n % 100 ) {
			return $j;
		}
		return $n % 10 >= 2 && $n % 10 <= 4 && ( $n % 100 < 12 || $n % 100 > 14 ) ? $d : $m;
	}
}
