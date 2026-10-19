<?php
/** Jedan tečaj. Varijabla: $c (AKA_Podaci::tecaj). */
defined( 'ABSPATH' ) || exit;
$mentori = AKA_Podaci::mentori();
$m = isset( $mentori[ $c['mentor'] ] ) ? $mentori[ $c['mentor'] ] : null;
?>
<div class="aka">
  <section class="aka-phero aka-course-hero">
    <div class="aka-wrap aka-split">
      <div>
        <a class="aka-back" href="<?php echo esc_url( AKA_UI::url( '/programi/' ) ); ?>">← Svi programi</a>
        <span class="aka-level<?php echo $c['level'] > 1 ? ' aka-l' . (int) $c['level'] : ''; ?>"><?php echo esc_html( $c['razina'] ); ?></span>
        <h1><?php echo esc_html( $c['naslov'] ); ?></h1>
        <p class="aka-lead"><?php echo esc_html( $c['opis'] ); ?> Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor.</p>
        <?php echo AKA_UI::meta_tecaja( $c ); // phpcs:ignore ?>
      </div>
      <div class="aka-buy">
        <div class="aka-thumb aka-t<?php echo (int) $c['thumb']; ?>"><?php echo AKA_Podaci::ikona( $c['ikona'] ); // phpcs:ignore ?></div>
        <div class="aka-amount"><b><?php echo (int) $c['cijena']; ?> €</b><span>jednokratno, s PDV-om</span></div>
        <?php echo AKA_UI::gumb( 'Upiši tečaj', '/kontakt/?tecaj=' . $c['slug'] ); // phpcs:ignore ?>
        <ul class="aka-check"><li><?php echo AKA_Podaci::ikona( 'kvacica' ); // phpcs:ignore ?>Pristup 12 mjeseci</li><li><?php echo AKA_Podaci::ikona( 'kvacica' ); // phpcs:ignore ?>Vježbe i materijali</li><li><?php echo AKA_Podaci::ikona( 'kvacica' ); // phpcs:ignore ?>Certifikat</li></ul>
      </div>
    </div>
  </section>
  <section class="aka-sec aka-pt0">
    <div class="aka-wrap aka-split aka-split-r">
      <div>
        <h2>Što ćeš <span class="aka-grad-text">naučiti</span></h2>
        <ul class="aka-check aka-check-lg"><?php foreach ( $c['ishodi'] as $s ) : ?><li><?php echo AKA_Podaci::ikona( 'kvacica' ); // phpcs:ignore ?><?php echo esc_html( $s ); ?></li><?php endforeach; ?></ul>
        <h2 class="aka-mt">Sadržaj tečaja</h2>
        <div class="aka-acc">
          <?php foreach ( $c['moduli'] as $i => $mod ) : ?>
            <details<?php echo 0 === $i ? ' open' : ''; ?>><summary><span class="aka-num"><?php echo (int) $i + 1; ?></span><?php echo esc_html( $mod[0] ); ?><small><?php echo count( $mod[1] ); ?> lekcije</small></summary>
              <ol><?php foreach ( $mod[1] as $l ) : ?><li><?php echo esc_html( $l ); ?></li><?php endforeach; ?></ol></details>
          <?php endforeach; ?>
        </div>
      </div>
      <aside>
        <?php if ( $m ) : ?><p class="aka-kicker">Tvoj mentor</p><?php echo AKA_UI::kartica_mentora( $m, $c['mentor'], false, true ); // phpcs:ignore ?><?php endif; ?>
        <div class="aka-box"><h3>Za koga je tečaj?</h3><p>Lorem ipsum dolor sit amet – za sve koji žele brzo i praktično naučiti koristiti AI, bez tehničkog predznanja.</p></div>
      </aside>
    </div>
  </section>
  <section class="aka-sec aka-soft">
    <div class="aka-wrap">
      <div class="aka-head"><div><p class="aka-kicker">Nastavi učiti</p><h2>Ostali <span class="aka-grad-text">tečajevi</span></h2></div><?php echo AKA_UI::gumb( 'Svi programi', '/programi/', 'ghost' ); // phpcs:ignore ?></div>
      <div class="aka-grid aka-g3 aka-courses"><?php foreach ( array_slice( array_diff( array_keys( AKA_Podaci::tecajevi() ), array( $c['slug'] ) ), 0, 3 ) as $os ) { echo AKA_UI::kartica_tecaja( AKA_Podaci::tecaj( $os ) ); } // phpcs:ignore ?></div>
    </div>
  </section>
  <?php echo AKA_UI::pitanja( array_slice( AKA_Podaci::pitanja(), 0, 4 ) ); // phpcs:ignore ?>
  <?php echo AKA_UI::cta( 'Kreni s tečajem [[' . $c['naslov'] . ']]', 'Lekcije su dostupne odmah nakon upisa.', 'Upiši tečaj', '/kontakt/?tecaj=' . $c['slug'] ); // phpcs:ignore ?>
</div>
