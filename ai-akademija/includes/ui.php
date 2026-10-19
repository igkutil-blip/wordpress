<?php
/**
 * Zajednički dijelovi stranica: zaglavlje stranice, kartica tečaja, poziv na akciju, pitanja.
 */
defined( 'ABSPATH' ) || exit;

final class AKA_UI {

	/** „/programi/”, „/kontakt/?tecaj=x” … → prava adresa stranice (traži se po kratkom kodu). */
	public static function url( $path ) {
		if ( '' === $path || '#' === $path[0] || preg_match( '#^(https?:|mailto:|tel:)#', $path ) ) {
			return $path;
		}
		return AKA_Linkovi::iz_putanje( $path );
	}

	/** Naslov s istaknutim (prelijevajućim) dijelom: „Tekst [[istaknuto]]”. */
	public static function naslov( $text ) {
		$text = esc_html( $text );
		return preg_replace( '/\[\[(.+?)\]\]/', '<span class="aka-grad-text">$1</span>', $text );
	}

	public static function gumb( $tekst, $url, $vrsta = 'grad' ) {
		$ikona = 'grad' === $vrsta ? AKA_Podaci::ikona( 'strelica' ) : '';
		return '<a class="aka-btn aka-btn-' . esc_attr( $vrsta ) . '" href="' . esc_url( self::url( $url ) ) . '">' . esc_html( $tekst ) . $ikona . '</a>';
	}

	/** Zaglavlje podstranice. */
	public static function hero( $kicker, $naslov, $uvod, $gumbi = '' ) {
		ob_start();
		?>
		<section class="aka-phero">
			<div class="aka-wrap">
				<span class="aka-badge"><?php echo esc_html( $kicker ); ?></span>
				<h1><?php echo self::naslov( $naslov ); // phpcs:ignore ?></h1>
				<?php if ( $uvod ) : ?><p class="aka-lead"><?php echo esc_html( $uvod ); ?></p><?php endif; ?>
				<?php if ( $gumbi ) : ?><div class="aka-actions"><?php echo $gumbi; // phpcs:ignore ?></div><?php endif; ?>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}

	public static function kartica_tecaja( $c ) {
		$meta = self::meta_tecaja( $c );
		ob_start();
		?>
		<a class="aka-course" href="<?php echo esc_url( AKA_Linkovi::tecaj( $c['slug'] ) ); ?>" data-razina="<?php echo esc_attr( sanitize_title( $c['razina'] ) ); ?>">
			<div class="aka-thumb aka-t<?php echo (int) $c['thumb']; ?>"><?php echo AKA_Podaci::ikona( $c['ikona'] ); // phpcs:ignore ?></div>
			<div class="aka-course-body">
				<span class="aka-level<?php echo $c['level'] > 1 ? ' aka-l' . (int) $c['level'] : ''; ?>"><?php echo esc_html( $c['razina'] ); ?></span>
				<h3><?php echo esc_html( $c['naslov'] ); ?></h3>
				<p><?php echo esc_html( $c['opis'] ); ?></p>
				<?php echo $meta; // phpcs:ignore ?>
			</div>
		</a>
		<?php
		return ob_get_clean();
	}

	public static function meta_tecaja( $c ) {
		return '<div class="aka-meta"><span>' . AKA_Podaci::ikona( 'sat' ) . (int) $c['sati'] . ' sati</span><span>' . AKA_Podaci::ikona( 'stupci' ) . (int) $c['lekcija'] . ' lekcija</span><span>' . AKA_Podaci::ikona( 'cert' ) . 'Certifikat</span></div>';
	}

	public static function inicijali( $ime ) {
		$out = '';
		foreach ( preg_split( '/\s+/', trim( $ime ) ) as $dio ) {
			$out .= mb_substr( $dio, 0, 1 );
		}
		return mb_strtoupper( mb_substr( $out, 0, 2 ) );
	}

	/**
	 * Kartica mentora. $slug → sidro (#slug) na stranici Mentori; $puno → popis tečajeva mentora;
	 * $link → cijela kartica vodi na mentora na stranici Mentori.
	 */
	public static function kartica_mentora( $m, $slug = '', $puno = false, $link = false ) {
		$tag  = $link && $slug ? 'a' : 'div';
		$attr = $link && $slug ? ' href="' . esc_url( AKA_Linkovi::stranica( 'mentori' ) . '#' . $slug ) . '"' : '';
		$attr .= $puno && $slug ? ' id="' . esc_attr( $slug ) . '"' : '';
		ob_start();
		?>
		<<?php echo $tag; // phpcs:ignore ?> class="aka-mentor<?php echo $link ? ' is-link' : ''; ?>"<?php echo $attr; // phpcs:ignore ?>>
			<span class="aka-avatar aka-av<?php echo (int) $m['grad']; ?>"><?php echo esc_html( self::inicijali( $m['ime'] ) ); ?></span>
			<h3><?php echo esc_html( $m['ime'] ); ?></h3>
			<span class="aka-role"><?php echo esc_html( $m['uloga'] ); ?></span>
			<p><?php echo esc_html( $m['opis'] ); ?></p>
			<?php
			if ( $puno && $slug ) :
				$tecajevi = wp_list_filter( AKA_Podaci::tecajevi(), array( 'mentor' => $slug ) );
				if ( $tecajevi ) :
					?>
				<div class="aka-mtecaj"><small>Tečajevi</small><?php foreach ( $tecajevi as $ts => $t ) : ?><a href="<?php echo esc_url( AKA_Linkovi::tecaj( $ts ) ); ?>"><?php echo esc_html( $t['naslov'] ); ?> →</a><?php endforeach; ?></div>
					<?php
				endif;
			endif;
			?>
		</<?php echo $tag; // phpcs:ignore ?>>
		<?php
		return ob_get_clean();
	}

	/** Mreža mentora. */
	public static function mentori( $puno = false ) {
		$out = '';
		foreach ( AKA_Podaci::mentori() as $slug => $m ) {
			$out .= self::kartica_mentora( $m, $slug, $puno, ! $puno );
		}
		return '<div class="aka-grid aka-g4">' . $out . '</div>';
	}

	/** Pitanja kao <details> – rade bez JavaScripta. */
	public static function pitanja( $lista, $naslov = 'Česta [[pitanja]]', $vise = true ) {
		ob_start();
		?>
		<section class="aka-sec aka-faq">
			<div class="aka-wrap aka-narrow">
				<p class="aka-kicker">Pitanja i odgovori</p>
				<h2><?php echo self::naslov( $naslov ); // phpcs:ignore ?></h2>
				<div class="aka-acc">
					<?php foreach ( $lista as $i => $p ) : ?>
						<details<?php echo 0 === $i ? ' open' : ''; ?>><summary><?php echo esc_html( $p[0] ); ?></summary><p><?php echo esc_html( $p[1] ); ?></p></details>
					<?php endforeach; ?>
				</div>
				<?php if ( $vise && count( $lista ) < count( AKA_Podaci::pitanja() ) ) : ?>
					<p class="aka-c aka-more"><?php echo self::gumb( 'Sva pitanja', '/cesta-pitanja/', 'ghost' ); // phpcs:ignore ?></p>
				<?php endif; ?>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}

	/** Traka s pozivom na akciju na dnu stranica. */
	public static function cta( $naslov = 'Spreman za [[prvi korak?]]', $tekst = 'Odaberi tečaj i započni već danas – učiš svojim tempom, a mentori su tu kad zapne.', $gumb = 'Pogledaj programe', $url = '/programi/' ) {
		ob_start();
		?>
		<section class="aka-sec">
			<div class="aka-wrap">
				<div class="aka-cta">
					<div>
						<h2><?php echo self::naslov( $naslov ); // phpcs:ignore ?></h2>
						<p><?php echo esc_html( $tekst ); ?></p>
					</div>
					<?php echo self::gumb( $gumb, $url ); // phpcs:ignore ?>
				</div>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}
}
