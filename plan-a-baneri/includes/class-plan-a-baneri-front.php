<?php
/**
 * Kratki kod [plan-a-naslovnica] – vrh naslovnice.
 *
 * Odbrojavanje, „još N dana” i skrivanje isteklih dijelova radi JS u pregledniku (stranica može biti u predmemoriji danima).
 *
 * @package Plan_A_Baneri
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Baneri_Front {

	const MONTHS = array( 'sij', 'velj', 'ožu', 'tra', 'svi', 'lip', 'srp', 'kol', 'ruj', 'lis', 'stu', 'pro' );

	// Ikone kao SVG (emoji WordPress pretvara u slike s vanjskog poslužitelja).
	const ICON_STAR  = '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M12 2.5l2.9 5.9 6.5.9-4.7 4.6 1.1 6.5L12 17.3l-5.8 3.1 1.1-6.5L2.6 9.3l6.5-.9z"/></svg>';
	const ICON_HEART = '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M12 21s-7.5-4.6-9.6-9.2C.9 8.4 3 4.5 6.7 4.5c2.1 0 3.6 1.1 5.3 3.1 1.7-2 3.2-3.1 5.3-3.1 3.7 0 5.8 3.9 4.3 7.3C19.5 16.4 12 21 12 21z"/></svg>';

	public static function init() {
		add_shortcode( 'plan-a-naslovnica', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_head', array( __CLASS__, 'preload' ), 2 );
	}

	private static function on_page(): bool {
		$post = is_singular() ? get_post() : null;
		return $post && has_shortcode( (string) $post->post_content, 'plan-a-naslovnica' );
	}

	public static function assets() {
		if ( ! self::on_page() ) {
			return;
		}
		wp_enqueue_style( 'plan-a-baneri', PLAN_A_BANERI_URL . 'assets/naslovnica.css', array(), PLAN_A_BANERI_VERSION );
		wp_enqueue_script( 'plan-a-baneri', PLAN_A_BANERI_URL . 'assets/naslovnica.js', array(), PLAN_A_BANERI_VERSION, true );
	}

	/** Velika slika je prvo što se vidi – preglednik je počne učitavati odmah. */
	public static function preload() {
		if ( ! self::on_page() ) {
			return;
		}
		$img = self::hero_image();
		if ( $img['src'] ) {
			echo '<link rel="preload" as="image" href="' . esc_url( $img['src'] ) . '"' . ( $img['srcset'] ? ' imagesrcset="' . esc_attr( $img['srcset'] ) . '" imagesizes="100vw"' : '' ) . ' fetchpriority="high">' . "\n";
		}
	}

	private static function hero_image(): array {
		$s   = Plan_A_Baneri::get();
		$id  = (int) $s['image'];
		$src = '';
		if ( ! $id && '' !== $s['image_url'] ) {
			$id = (int) attachment_url_to_postid( (string) $s['image_url'] );
			$src = (string) $s['image_url'];
		}
		if ( ! $id && '' === $src ) {
			foreach ( Plan_A_Baneri::tours() as $t ) {
				if ( $t['image'] ) {
					$id = (int) $t['image'];
					break;
				}
			}
		}
		if ( $id ) {
			$src = (string) wp_get_attachment_image_url( $id, 'full' );
		}
		return array(
			'id'     => $id,
			'src'    => $src,
			'srcset' => $id ? (string) wp_get_attachment_image_srcset( $id, 'full' ) : '',
			'alt'    => $id ? (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) : '',
		);
	}

	private static function plural( int $n, array $f ): string {
		$m10  = $n % 10;
		$m100 = $n % 100;
		if ( 1 === $m10 && 11 !== $m100 ) {
			return $f[0];
		}
		return ( $m10 >= 2 && $m10 <= 4 && ( $m100 < 12 || $m100 > 14 ) ) ? $f[1] : $f[2];
	}

	/** „21.–22.11.2026.”, „31.12.2026.–1.1.2027.” ili „29.11.2026.” */
	private static function range( string $a, string $b, bool $year = true ): string {
		$x = strtotime( $a );
		$y = strtotime( $b );
		$f = static function ( $ts, $with_month, $with_year ) {
			return gmdate( 'j', $ts ) . '.' . ( $with_month ? gmdate( 'n', $ts ) . '.' : '' ) . ( $with_year ? gmdate( 'Y', $ts ) . '.' : '' );
		};
		if ( $a === $b ) {
			return $f( $x, true, $year );
		}
		if ( gmdate( 'Y', $x ) !== gmdate( 'Y', $y ) ) {
			return $f( $x, true, $year ) . '–' . $f( $y, true, $year );
		}
		if ( gmdate( 'n', $x ) !== gmdate( 'n', $y ) ) {
			return $f( $x, true, false ) . '–' . $f( $y, true, $year );
		}
		return $f( $x, false, false ) . '–' . $f( $y, true, $year );
	}

	private static function seats_text( int $n ): string {
		return $n . ' ' . self::plural( $n, array( 'mjesto', 'mjesta', 'mjesta' ) );
	}

	private static function price( array $t, string $cls = '' ): string {
		if ( '' === $t['price'] ) {
			return '';
		}
		return '<span class="' . esc_attr( $cls ) . '">od <b>' . wp_kses( $t['price'], array( 'span' => array( 'class' => true ), 'bdi' => array() ) ) . '</b></span>';
	}

	public static function render(): string {
		$s     = Plan_A_Baneri::get();
		$tours = Plan_A_Baneri::tours();
		$today = current_time( 'Y-m-d' );
		$limit = gmdate( 'Y-m-d', strtotime( $today . ' +' . max( 7, (int) $s['cards_days'] ) . ' days' ) );
		$soon  = array_values( array_filter( $tours, static fn( $t ) => $t['date'] <= $limit ) );
		$img   = self::hero_image();
		$style = '--pab-top:' . (int) $s['top'] . 'px;--pab-top-mob:' . (int) $s['top_mob'] . 'px';

		$h  = '<div class="pab" style="' . esc_attr( $style ) . '">';
		$h .= '<section class="pab-hero">';
		if ( $img['src'] ) {
			$h .= '<img class="pab-hero__bg" src="' . esc_url( $img['src'] ) . '"' . ( $img['srcset'] ? ' srcset="' . esc_attr( $img['srcset'] ) . '" sizes="100vw"' : '' ) . ' alt="' . esc_attr( $img['alt'] ) . '" fetchpriority="high" decoding="async">';
		}
		$h .= '<div class="pab-hero__in"><div class="pab-hero__text">';
		$kicker = strtr(
			(string) $s['kicker'],
			array(
				'{broj}' => (string) count( $soon ),
				'{dana}' => (string) (int) $s['cards_days'],
			)
		);
		if ( '' !== trim( $kicker ) && ( $soon || false === strpos( (string) $s['kicker'], '{broj}' ) ) ) {
			$h .= '<span class="pab-kick"><i aria-hidden="true"></i>' . esc_html( $kicker ) . '</span>';
		}
		$h .= '<h1 class="pab-title">' . nl2br( esc_html( (string) $s['title'] ) ) . '</h1>';
		if ( '' !== trim( (string) $s['sub'] ) ) {
			$h .= '<p class="pab-sub">' . esc_html( (string) $s['sub'] ) . '</p>';
		}
		$h .= '<div class="pab-ctas">';
		if ( '' !== $s['btn1'] ) {
			$h .= '<a class="pab-btn pab-btn--o" href="' . esc_url( self::url( (string) $s['btn1_url'] ) ) . '">' . esc_html( (string) $s['btn1'] ) . ' <span aria-hidden="true">→</span></a>';
		}
		if ( '' !== $s['btn2'] ) {
			$h .= '<a class="pab-btn pab-btn--g" href="' . esc_url( self::url( (string) $s['btn2_url'] ) ) . '">' . esc_html( (string) $s['btn2'] ) . '</a>';
		}
		$h .= '</div>';
		$trust = array_filter( array_map( 'trim', preg_split( '/\R/u', (string) $s['trust'] ) ) );
		if ( $trust ) {
			$h .= '<ul class="pab-trust">';
			foreach ( $trust as $t ) {
				$h .= '<li>' . esc_html( $t ) . '</li>';
			}
			$h .= '</ul>';
		}
		$h .= '</div>';
		$h .= ! empty( $s['next_on'] ) ? self::next_card( $tours, max( 1, min( 6, (int) $s['next_count'] ) ) ) : '';
		$h .= '</div>';
		$h .= self::ribbons( $s, $tours );
		$h .= '</section>';
		if ( ! empty( $s['cards_on'] ) ) {
			$h .= self::cards( $s, $tours, $soon );
		}
		return $h . '</div>';
	}

	private static function url( string $url ): string {
		return 0 === strpos( $url, '/' ) ? home_url( $url ) : $url;
	}

	/** Kartica „Sljedeći polazak” – do N najbližih izleta koji nisu popunjeni, prelistavanje strelicama. */
	private static function next_card( array $tours, int $count ): string {
		$list = array_slice( array_values( array_filter( $tours, static fn( $t ) => ! $t['sold_out'] ) ), 0, $count );
		if ( ! $list ) {
			return '';
		}
		$h = '<div class="pab-next" data-pab-next><div class="pab-next__slides">';
		foreach ( $list as $i => $t ) {
			$img  = $t['image'] ? wp_get_attachment_image( $t['image'], 'medium_large', false, array( 'class' => 'pab-next__img', 'loading' => 0 === $i ? 'eager' : 'lazy', 'alt' => '' ) ) : '';
			$meta = array_filter( array( self::range( $t['date'], $t['end'] ), $t['days'], $t['country'] ) );
			$h   .= '<article class="pab-next__slide"' . ( $i ? ' hidden' : '' ) . ' data-start="' . (int) $t['start'] . '">';
			$h   .= '<a class="pab-next__media" href="' . esc_url( $t['url'] ) . '" tabindex="-1">' . $img . '<span class="pab-next__tag">Sljedeći polazak</span></a>';
			$h   .= '<div class="pab-next__b"><h3><a href="' . esc_url( $t['url'] ) . '">' . esc_html( $t['title'] ) . '</a></h3>';
			$h   .= '<p class="pab-next__meta">' . esc_html( implode( ' · ', $meta ) ) . '</p>';
			$h   .= '<div class="pab-cd" aria-label="Do polaska"><div><b data-cd="d">–</b><small>dana</small></div><div><b data-cd="h">–</b><small>sati</small></div><div><b data-cd="m">–</b><small>min</small></div><div><b data-cd="s">–</b><small>sek</small></div></div>';
			if ( null !== $t['free'] && $t['seats'] > 0 ) {
				$pct = max( 4, min( 100, round( 100 * ( $t['seats'] - min( $t['free'], $t['seats'] ) ) / $t['seats'] ) ) );
				$h  .= '<div class="pab-seats"><span>Popunjenost</span><span>još <b' . ( $t['free'] <= 3 ? ' class="is-low"' : '' ) . '>' . esc_html( self::seats_text( (int) $t['free'] ) ) . '</b></span></div>';
				$h  .= '<div class="pab-bar" aria-hidden="true"><i style="width:' . (int) $pct . '%"></i></div>';
			}
			$h .= '<div class="pab-next__f">' . self::price( $t, 'pab-price' ) . '<a class="pab-btn pab-btn--o pab-btn--s" href="' . esc_url( $t['url'] ) . '">Prijavi se <span aria-hidden="true">→</span></a></div>';
			$h .= '</div></article>';
		}
		$h .= '</div>';
		if ( count( $list ) > 1 ) {
			$h .= '<div class="pab-next__nav"><button type="button" data-dir="-1" aria-label="Prethodni izlet">‹</button><button type="button" data-dir="1" aria-label="Sljedeći izlet">›</button></div>';
			$h .= '<div class="pab-dots" aria-hidden="true">' . str_repeat( '<i></i>', count( $list ) ) . '</div>';
		}
		return $h . '</div>';
	}

	/** Traka akcije (npr. članarina 5 €) i traka Tinder izleta. */
	private static function ribbons( array $s, array $tours ): string {
		$items = array();
		$until = (string) $s['promo_until'];
		if ( ! empty( $s['promo_on'] ) && ( '' === $until || $until >= current_time( 'Y-m-d' ) ) ) {
			$fee   = Plan_A_Baneri::fee();
			$end   = '' !== $until ? strtotime( $until . ' 23:59:59 ' . wp_timezone_string() ) : 0;
			$left  = $end ? max( 0, (int) ceil( ( $end - time() ) / DAY_IN_SECONDS ) ) : 0;
			$vars  = array(
				'{iznos}' => $fee,
				'{do}'    => '' !== $until ? gmdate( 'j.n.Y.', strtotime( $until ) ) : '',
				'{dana}'  => '<span data-pab-left>' . $left . ' ' . self::plural( $left, array( 'dan', 'dana', 'dana' ) ) . '</span>',
			);
			$title = strtr( esc_html( (string) $s['promo_title'] ), array_map( 'esc_html', array_slice( $vars, 0, 2, true ) ) );
			$sub   = strtr( esc_html( (string) $s['promo_sub'] ), array( '{iznos}' => esc_html( $fee ), '{do}' => esc_html( $vars['{do}'] ), '{dana}' => $vars['{dana}'] ) );
			$url   = '' !== $s['promo_url'] ? self::url( (string) $s['promo_url'] ) : Plan_A_Baneri::join_url();
			if ( false === strpos( (string) $s['promo_title'], '{iznos}' ) || '' !== $fee ) {
				$items[] = '<a class="pab-rib pab-rib--m" href="' . esc_url( $url ) . '"' . ( $end ? ' data-until="' . (int) $end . '"' : '' ) . '><span class="pab-rib__ic" aria-hidden="true">' . ( '' !== trim( (string) $s['promo_icon'] ) ? esc_html( (string) $s['promo_icon'] ) : self::ICON_STAR ) . '</span>'
					. '<span class="pab-rib__t"><strong>' . $title . '</strong>' . ( '' !== trim( (string) $s['promo_sub'] ) ? '<small>' . $sub . '</small>' : '' ) . '</span>'
					. '<span class="pab-rib__go">' . esc_html( (string) $s['promo_btn'] ) . '</span></a>';
			}
		}
		if ( ! empty( $s['tinder_on'] ) ) {
			foreach ( $tours as $t ) {
				if ( null === $t['tinder'] || $t['sold_out'] ) {
					continue;
				}
				$parts   = preg_split( '/\s+[–-]\s+/u', $t['title'], 2 );
				$name    = $parts[0];
				$where   = $parts[1] ?? $t['country'];
				$items[] = '<a class="pab-rib pab-rib--t" href="' . esc_url( $t['url'] ) . '" data-start="' . (int) $t['start'] . '"><span class="pab-rib__ic" aria-hidden="true">' . self::ICON_HEART . '</span>'
					. '<span class="pab-rib__t"><strong>' . esc_html( $name ) . ' <span class="pab-dsk">· ' . esc_html( self::range( $t['date'], $t['end'], false ) ) . '</span></strong>'
					. '<span class="pab-mf"><span>♂ ' . (int) $t['tinder']['m'] . '</span><span>♀ ' . (int) $t['tinder']['z'] . '</span><small class="pab-dsk">prijavljeno · ' . esc_html( $where ) . '</small></span></span>'
					. '<span class="pab-rib__go">' . esc_html( (string) $s['tinder_btn'] ) . '</span></a>';
				break;
			}
		}
		if ( ! $items ) {
			return '';
		}
		return '<div class="pab-ribs' . ( 1 === count( $items ) ? ' pab-ribs--one' : '' ) . '">' . implode( '', $items ) . '</div>';
	}

	/** Kartice sljedećih izleta (najmanje 4 ako ih ima, i iza razdoblja). */
	private static function cards( array $s, array $tours, array $soon ): string {
		$max  = max( 2, min( 20, (int) $s['cards_max'] ) );
		$list = count( $soon ) >= 4 ? $soon : array_slice( $tours, 0, 4 );
		$list = array_slice( $list, 0, $max );
		if ( ! $list ) {
			return '';
		}
		$all = '' !== $s['btn1_url'] ? self::url( (string) $s['btn1_url'] ) : home_url( '/izleti/' );
		$h   = '<section class="pab-strip"><div class="pab-strip__h"><div><h2>' . esc_html( (string) $s['cards_title'] ) . '</h2>'
			. ( '' !== trim( (string) $s['cards_sub'] ) ? '<p>' . esc_html( (string) $s['cards_sub'] ) . '</p>' : '' ) . '</div>'
			. '<div class="pab-strip__r"><a href="' . esc_url( $all ) . '">' . esc_html( (string) $s['cards_all'] ) . ' →</a>'
			. '<span class="pab-arr"><button type="button" data-dir="-1" aria-label="Natrag">‹</button><button type="button" data-dir="1" aria-label="Naprijed">›</button></span></div></div>';
		$h  .= '<div class="pab-cards">';
		foreach ( $list as $t ) {
			$ts    = strtotime( $t['date'] );
			$badge = '';
			if ( $t['sold_out'] ) {
				$badge = '<span class="pab-badge pab-badge--full">Popunjeno · lista čekanja</span>';
			} elseif ( null !== $t['tinder'] ) {
				$badge = '<span class="pab-badge pab-badge--t">♂ ' . (int) $t['tinder']['m'] . ' · ♀ ' . (int) $t['tinder']['z'] . '</span>';
			} elseif ( null !== $t['free'] && $t['free'] <= 5 ) {
				$badge = '<span class="pab-badge' . ( $t['free'] <= 3 ? ' pab-badge--low' : '' ) . '">Još ' . esc_html( self::seats_text( (int) $t['free'] ) ) . '</span>';
			}
			$meta = array_filter( array( $t['days'], $t['country'] ) );
			$h   .= '<a class="pab-card' . ( $t['sold_out'] ? ' is-full' : '' ) . '" href="' . esc_url( $t['url'] ) . '" data-start="' . (int) $t['start'] . '">'
				. '<span class="pab-card__img">' . ( $t['image'] ? wp_get_attachment_image( $t['image'], 'medium_large', false, array( 'loading' => 'lazy', 'alt' => '' ) ) : '' )
				. '<span class="pab-card__d"><b>' . esc_html( gmdate( 'j', $ts ) ) . '</b><small>' . esc_html( self::MONTHS[ (int) gmdate( 'n', $ts ) - 1 ] ) . '</small></span>' . $badge . '</span>'
				. '<span class="pab-card__b"><span class="pab-card__t">' . esc_html( $t['title'] ) . '</span>'
				. '<span class="pab-card__m"><span>' . esc_html( implode( ' · ', $meta ) ) . '</span>' . self::price( $t ) . '</span></span></a>';
		}
		return $h . '</div></section>';
	}
}
