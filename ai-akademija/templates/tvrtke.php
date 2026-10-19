<?php defined( 'ABSPATH' ) || exit; ?>
<div class="aka">
  <?php echo AKA_UI::hero( 'Za tvrtke', 'AI edukacija za [[cijeli tim.]]', 'Program prilagođen vašem poslu, alatima i pravilima – online, uživo ili kombinirano.', AKA_UI::gumb( 'Zatraži ponudu', '#upit' ) . AKA_UI::gumb( 'Pogledaj programe', '/programi/', 'ghost' ) ); // phpcs:ignore ?>
  <section class="aka-sec aka-pt0">
    <div class="aka-wrap">
      <div class="aka-grid aka-g3">
        <?php foreach ( array(
          array( 'meta', 'Prilagođeno poslu', 'Primjeri iz vaše branše i vaših procesa, ne općenite teorije.' ),
          array( 'ljudi', 'Radionice uživo', 'Mentor dolazi k vama ili radimo online – u grupama do 20 ljudi.' ),
          array( 'stupci', 'Mjerljiv napredak', 'Pratite tko je što završio i koliko vremena tim štedi.' ),
          array( 'stit', 'Sigurnost podataka', 'Pravila korištenja AI-ja usklađena s GDPR-om i politikom tvrtke.' ),
          array( 'cert', 'Certifikati', 'Svaki polaznik dobiva certifikat AI Akademije.' ),
          array( 'srce', 'Podrška nakon tečaja', 'Tri mjeseca pitanja i odgovora za cijeli tim.' ),
        ) as $f ) : ?>
          <div class="aka-feat"><span class="aka-ico"><?php echo AKA_Podaci::ikona( $f[0] ); // phpcs:ignore ?></span><h3><?php echo esc_html( $f[1] ); ?></h3><p><?php echo esc_html( $f[2] ); ?></p></div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <section class="aka-sec aka-soft">
    <div class="aka-wrap">
      <p class="aka-kicker">Kako radimo</p>
      <h2>Od upita do <span class="aka-grad-text">educiranog tima.</span></h2>
      <div class="aka-grid aka-g4 aka-steps">
        <div class="aka-step"><b>1</b><h3>Razgovor</h3><p>Kratki poziv: što tim radi i gdje AI može pomoći.</p></div>
        <div class="aka-step"><b>2</b><h3>Prijedlog</h3><p>Program, termini i ponuda u roku od 3 radna dana.</p></div>
        <div class="aka-step"><b>3</b><h3>Edukacija</h3><p>Radionice i online lekcije prema dogovoru.</p></div>
        <div class="aka-step"><b>4</b><h3>Praćenje</h3><p>Izvještaj o napretku i podrška nakon tečaja.</p></div>
      </div>
    </div>
  </section>
  <section class="aka-sec" id="upit">
    <div class="aka-wrap aka-split">
      <div><p class="aka-kicker">Upit</p><h2>Zatražite <span class="aka-grad-text">ponudu.</span></h2><p class="aka-lead">Javit ćemo se u roku od jednog radnog dana. Lorem ipsum dolor sit amet.</p></div>
      <?php echo AI_Akademija::obrazac( array( 'predmet' => 'Upit za tvrtke', 'tvrtka' => true ) ); // phpcs:ignore ?>
    </div>
  </section>
</div>
