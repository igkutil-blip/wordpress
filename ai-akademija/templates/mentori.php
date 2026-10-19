<?php defined( 'ABSPATH' ) || exit; ?>
<div class="aka">
  <?php echo AKA_UI::hero( 'Mentori', 'Ljudi koji te [[vode.]]', 'Praktičari koji AI koriste svaki dan – u nastavi, poslu i kreativnim projektima.' ); // phpcs:ignore ?>
  <section class="aka-sec aka-pt0">
    <div class="aka-wrap">
      <div class="aka-grid aka-g4"><?php foreach ( AKA_Podaci::mentori() as $m ) { echo AKA_UI::kartica_mentora( $m ); } // phpcs:ignore ?></div>
    </div>
  </section>
  <?php echo AKA_UI::cta( 'Želiš [[predavati]] s nama?', 'Tražimo praktičare koji znaju jednostavno objasniti AI. Javi nam se.', 'Pošalji prijavu', '/kontakt/?predmet=mentor' ); // phpcs:ignore ?>
</div>
