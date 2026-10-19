<?php defined( 'ABSPATH' ) || exit; ?>
<section class="aka-sec">
  <div class="aka-wrap">
    <p class="aka-kicker aka-c">Cijene</p>
    <h2 class="aka-c">Odaberi način <span class="aka-grad-text">učenja.</span></h2>
    <div class="aka-grid aka-g3 aka-prices">
      <?php foreach ( AKA_Podaci::paketi() as $p ) : ?>
        <div class="aka-price<?php echo $p['istaknut'] ? ' is-top' : ''; ?>">
          <?php if ( $p['istaknut'] ) : ?><span class="aka-pill">Najpopularnije</span><?php endif; ?>
          <h3><?php echo esc_html( $p['naziv'] ); ?></h3>
          <div class="aka-amount"><b><?php echo esc_html( $p['cijena'] ); ?></b><span><?php echo esc_html( $p['nap'] ); ?></span></div>
          <ul class="aka-check"><?php foreach ( $p['stavke'] as $s ) : ?><li><?php echo AKA_Podaci::ikona( 'kvacica' ); // phpcs:ignore ?><?php echo esc_html( $s ); ?></li><?php endforeach; ?></ul>
          <?php echo AKA_UI::gumb( $p['gumb'], $p['url'], $p['istaknut'] ? 'grad' : 'ghost' ); // phpcs:ignore ?>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="aka-note aka-c">Cijene su primjer – lorem ipsum. Sve cijene uključuju PDV.</p>
  </div>
</section>
