<?php
/**
 * Alternativni tekst (alt) slika: iz naziva stranice ili izleta gdje se slika koristi,
 * inače iz roditelja ili naziva datoteke. Postojeći (ručni) alt se ne dira.
 *
 * @package Plan_A_Seo
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Seo_Images {

	const AUTO = '_paseo_alt'; // slika: alt je složio ovaj dodatak (za „Vrati”)
	const ALT  = '_wp_attachment_image_alt';

	/** Vrste sadržaja u kojima se traže slike. */
	const IN = array( 'page', 'post', 'ttbm_tour', 'ttbm_guide', 'product' );

	public static function init() {
		add_action( 'add_attachment', array( __CLASS__, 'on_upload' ) );
		add_action( 'wp_after_insert_post', array( __CLASS__, 'on_save' ), 30, 2 );
		// Na stranici: slika bez alta dobije naziv stranice (i dok se postojeće ne popune).
		add_filter( 'wp_get_attachment_image_attributes', array( __CLASS__, 'attr' ), 20, 2 );
		add_filter( 'wp_content_img_tag', array( __CLASS__, 'content_img' ), 20, 3 );
	}

	/* ---------------- Gdje se slika koristi ---------------- */

	/** ID-jevi slika u sadržaju i podacima jednog zapisa, redom pojavljivanja. */
	public static function ids_in( WP_Post $post ): array {
		$ids  = array();
		$thumb = (int) get_post_thumbnail_id( $post->ID );
		if ( $thumb ) {
			$ids[] = $thumb;
		}
		$c = (string) $post->post_content;
		if ( preg_match_all( '/wp-image-(\d+)|\[(?:ux_image|ux_banner|section|ux_image_box|featured_box|team_member)\b[^\]]*?\b(?:id|bg|img)="(\d+)"|\bids="([\d,\s]+)"/', $c, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $x ) {
				foreach ( array_filter( array( $x[1] ?? '', $x[2] ?? '', $x[3] ?? '' ) ) as $v ) {
					foreach ( preg_split( '/[,\s]+/', (string) $v ) as $i ) {
						if ( (int) $i ) {
							$ids[] = (int) $i;
						}
					}
				}
			}
		}
		if ( 'ttbm_tour' === $post->post_type ) {
			foreach ( (array) get_post_meta( $post->ID, 'ttbm_gallery_images', true ) as $i ) {
				if ( (int) $i ) {
					$ids[] = (int) $i;
				}
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/** Prijedlog alta za sliku u zapisu: 1. slika = naziv, ostale „naziv – fotografija N”. */
	public static function alt_in( WP_Post $post, int $n ): string {
		$t = Plan_A_Seo_Text::clean( $post->post_title );
		$t = (string) preg_replace( '/\s*[-–|]\s*Plan A\s*$/u', '', $t );
		if ( Plan_A_Seo_Text::len( $t ) > 60 ) {
			$h = Plan_A_Seo_Text::head( $t );
			$t = Plan_A_Seo_Text::len( $h ) >= 15 ? $h : Plan_A_Seo_Text::cut_words( $t, 60 );
		}
		if ( '' === $t ) {
			return '';
		}
		return $n <= 1 ? $t : $t . ' – fotografija ' . $n;
	}

	/** Alt iz same slike: roditelj, naslov slike ili naziv datoteke. */
	public static function alt_own( int $att ): string {
		$a      = get_post( $att );
		$parent = $a && $a->post_parent ? get_post( $a->post_parent ) : null;
		if ( $parent && in_array( $parent->post_type, self::IN, true ) && 'publish' === $parent->post_status ) {
			return self::alt_in( $parent, 1 );
		}
		$file  = wp_basename( (string) get_attached_file( $att ) );
		$guess = self::guess( ( $a ? (string) $a->post_title : '' ) . ' ' . $file );
		if ( '' !== $guess ) {
			return $guess;
		}
		$name = $a ? Plan_A_Seo_Text::from_filename( (string) $a->post_title ) : '';
		if ( '' === $name || 'plan a' === mb_strtolower( $name ) ) {
			$name = Plan_A_Seo_Text::from_filename( $file );
		}
		return $name;
	}

	/** Riječi koje ne govore ništa o slici. */
	const STOP = array( 'plan', 'izleti', 'izlet', 'planinarenje', 'planinarski', 'planinarska', 'avantura', 'avanture', 'scaled', 'foto', 'slika', 'image', 'jpeg', 'with', 'the', 'and', 'udruga', 'udruge', 'hrvatska', 'slovenija', 'austrija', 'italija', 'ferrata', 'ferrate', 'ferata', 'ferate', 'planina', 'planine', 'zimsko', 'zimski', 'zimska', 'vrhova', 'vrhovi', 'jedrenje', 'penjanje', 'pocetnike', 'oprema', 'naslovna', 'dana', 'staza', 'tura', 'ture' );

	/** Naziv izleta ili članka na koji upućuje naziv datoteke („premuziceva-5-vrhova” → „Premužićeva staza”). */
	public static function guess( string $name ): string {
		static $titles = null;
		if ( null === $titles ) {
			$titles = array();
			foreach ( get_posts( array( 'post_type' => array( 'ttbm_tour', 'post', 'page' ), 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC' ) ) as $p ) {
				$t = Plan_A_Seo_Text::clean( $p->post_title );
				if ( '' !== $t && ! in_array( (int) $p->ID, Plan_A_Seo_Index::noindex_ids(), true ) ) {
					$titles[] = array( $t, ' ' . mb_strtolower( remove_accents( $t ) ) . ' ' );
				}
			}
		}
		$words = array();
		foreach ( preg_split( '/[^a-z]+/', mb_strtolower( remove_accents( $name ) ) ) as $w ) {
			if ( strlen( $w ) >= 4 && ! in_array( $w, self::STOP, true ) ) {
				$words[ $w ] = true;
			}
		}
		$best = '';
		$top  = 0;
		foreach ( $titles as $t ) {
			$score = 0;
			foreach ( array_keys( $words ) as $w ) {
				if ( preg_match( '/[^a-z]' . preg_quote( $w, '/' ) . '/', $t[1] ) ) {
					$score += strlen( $w ) >= 6 ? 2 : 1;
				}
			}
			if ( $score > $top ) {
				$top  = $score;
				$best = $t[0];
			}
		}
		if ( $top < 2 ) {
			return '';
		}
		$head = Plan_A_Seo_Text::head( $best );
		$head = Plan_A_Seo_Text::len( $head ) >= 10 && 'plan a' !== mb_strtolower( $head ) ? $head : $best;
		return Plan_A_Seo_Text::cut_words( $head, 70 ) . ' – fotografija';
	}

	/** Mapa slika => [alt, odakle] za sve objavljene zapise (prvo mjesto gdje se slika koristi). */
	public static function usage(): array {
		$map = array();
		$ids = get_posts(
			array(
				'post_type'      => self::IN,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'DESC', // novije stranice imaju prednost
			)
		);
		$skip = Plan_A_Seo_Index::noindex_ids();
		foreach ( $ids as $pid ) {
			if ( in_array( (int) $pid, $skip, true ) ) {
				continue;
			}
			$post = get_post( $pid );
			$n    = 0;
			foreach ( self::ids_in( $post ) as $att ) {
				++$n;
				if ( ! isset( $map[ $att ] ) ) {
					$map[ $att ] = array( self::alt_in( $post, $n ), (int) $pid );
				}
			}
		}
		return $map;
	}

	/** Slike bez alta (ID-jevi). */
	public static function missing(): array {
		global $wpdb;
		return array_map(
			'intval',
			$wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_wp_attachment_image_alt'
				 WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%' AND ( m.meta_value IS NULL OR TRIM(m.meta_value) = '' ) ORDER BY p.ID"
			)
		);
	}

	/** Upiši alt za zadane slike (samo prazne). Vraća broj upisanih. */
	public static function fill( array $atts, array $usage ): int {
		$n = 0;
		foreach ( $atts as $att ) {
			if ( '' !== trim( (string) get_post_meta( $att, self::ALT, true ) ) ) {
				continue;
			}
			$alt = $usage[ $att ][0] ?? '';
			if ( '' === $alt ) {
				$alt = self::alt_own( $att );
			}
			if ( '' === $alt ) {
				$alt = 'Plan A planinarenje – fotografija';
			}
			update_post_meta( $att, self::ALT, wp_slash( $alt ) );
			update_post_meta( $att, self::AUTO, 1 );
			++$n;
		}
		return $n;
	}

	/** Vrati: obriši altove koje je složio dodatak (a nisu se od tada mijenjali – ne može se znati, pa sve s oznakom). */
	public static function undo_all(): int {
		global $wpdb;
		$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", self::AUTO ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $ids as $id ) {
			delete_post_meta( $id, self::ALT );
			delete_post_meta( $id, self::AUTO );
		}
		return count( $ids );
	}

	/* ---------------- Automatski za nove ---------------- */

	public static function on_upload( $att ) {
		if ( ! wp_attachment_is_image( $att ) || '' !== trim( (string) get_post_meta( $att, self::ALT, true ) ) ) {
			return;
		}
		$alt = self::alt_own( (int) $att );
		if ( '' !== $alt ) {
			update_post_meta( $att, self::ALT, wp_slash( $alt ) );
			update_post_meta( $att, self::AUTO, 1 );
		}
	}

	/** Spremanje stranice: slike u njoj bez alta dobiju naziv stranice. */
	public static function on_save( $id, $post ) {
		if ( ! $post || 'publish' !== $post->post_status || ! in_array( $post->post_type, self::IN, true ) || wp_is_post_revision( $id ) ) {
			return;
		}
		$n = 0;
		foreach ( self::ids_in( $post ) as $att ) {
			++$n;
			if ( 'attachment' === get_post_type( $att ) && '' === trim( (string) get_post_meta( $att, self::ALT, true ) ) ) {
				update_post_meta( $att, self::ALT, wp_slash( self::alt_in( $post, $n ) ) );
				update_post_meta( $att, self::AUTO, 1 );
			}
		}
	}

	/* ---------------- Na stranici ---------------- */

	private static function page_title(): string {
		$id = is_singular() ? (int) get_queried_object_id() : 0;
		$t  = $id ? Plan_A_Seo_Text::clean( get_the_title( $id ) ) : Plan_A_Seo_Text::clean( get_bloginfo( 'name' ) );
		return (string) preg_replace( '/\s*[-–|]\s*Plan A\s*$/u', '', $t );
	}

	public static function attr( $attr, $att ) {
		if ( is_admin() || ! is_array( $attr ) || '' !== trim( (string) ( $attr['alt'] ?? '' ) ) ) {
			return $attr;
		}
		$alt         = $att instanceof WP_Post ? self::alt_own( $att->ID ) : '';
		$attr['alt'] = '' !== $alt ? $alt : self::page_title();
		return $attr;
	}

	public static function content_img( $tag, $context = '', $att = 0 ) {
		if ( is_admin() || ! is_string( $tag ) || preg_match( '/\balt\s*=\s*("[^"]+"|\'[^\']+\')/i', $tag ) ) {
			return $tag;
		}
		$alt = $att ? (string) get_post_meta( (int) $att, self::ALT, true ) : '';
		$alt = '' !== trim( $alt ) ? $alt : self::page_title();
		$tag = preg_replace( '/\salt\s*=\s*(""|\'\')/i', '', $tag );
		return preg_replace( '/^<img\b/i', '<img alt="' . esc_attr( $alt ) . '"', (string) $tag );
	}
}
