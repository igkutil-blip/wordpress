<?php defined( 'ABSPATH' ) || exit; ?>
<div class="aka">
  <?php echo AKA_UI::hero( 'O nama', 'Učimo ljude da AI [[rade za njih.]]', 'AI Akademija nastala je s jednim ciljem: da umjetna inteligencija bude jednostavna, korisna i dostupna svima – ne samo programerima.' ); // phpcs:ignore ?>
  <section class="aka-sec aka-pt0">
    <div class="aka-wrap aka-split">
      <div>
        <h2>Naša <span class="aka-grad-text">priča</span></h2>
        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer nec odio. Praesent libero. Sed cursus ante dapibus diam. Sed nisi. Nulla quis sem at nibh elementum imperdiet.</p>
        <p>Duis sagittis ipsum. Praesent mauris. Fusce nec tellus sed augue semper porta. Mauris massa. Vestibulum lacinia arcu eget nulla. Class aptent taciti sociosqu ad litora torquent per conubia nostra.</p>
      </div>
      <div class="aka-numbers">
        <div><b>2026.</b><span>godina osnivanja</span></div>
        <div><b>5</b><span>programa</span></div>
        <div><b>4</b><span>mentora</span></div>
        <div><b>100 %</b><span>online</span></div>
      </div>
    </div>
  </section>
  <section class="aka-sec aka-soft">
    <div class="aka-wrap">
      <p class="aka-kicker">Vrijednosti</p>
      <h2>U što <span class="aka-grad-text">vjerujemo.</span></h2>
      <div class="aka-grid aka-g3">
        <div class="aka-feat"><span class="aka-ico"><?php echo AKA_Podaci::ikona( 'meta' ); // phpcs:ignore ?></span><h3>Jednostavnost</h3><p>Složene stvari objašnjavamo jednostavnim jezikom, bez žargona.</p></div>
        <div class="aka-feat"><span class="aka-ico"><?php echo AKA_Podaci::ikona( 'munja' ); // phpcs:ignore ?></span><h3>Praksa ispred teorije</h3><p>Svaka lekcija ima zadatak koji odmah koristiš.</p></div>
        <div class="aka-feat"><span class="aka-ico"><?php echo AKA_Podaci::ikona( 'stit' ); // phpcs:ignore ?></span><h3>Odgovornost</h3><p>Učimo i kako AI koristiti sigurno, pošteno i provjereno.</p></div>
      </div>
    </div>
  </section>
  <section class="aka-sec">
    <div class="aka-wrap">
      <div class="aka-head"><div><p class="aka-kicker">Tim</p><h2>Upoznaj <span class="aka-grad-text">mentore.</span></h2></div><?php echo AKA_UI::gumb( 'Svi mentori', '/mentori/', 'ghost' ); // phpcs:ignore ?></div>
      <?php echo AKA_UI::mentori(); // phpcs:ignore ?>
    </div>
  </section>
  <?php echo AKA_UI::cta(); // phpcs:ignore ?>
</div>
