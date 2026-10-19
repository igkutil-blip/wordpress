<?php defined( 'ABSPATH' ) || exit; ?>
<div class="aka">
  <?php echo AKA_UI::hero( 'Mentori', 'Ljudi koji te [[vode.]]', 'Praktičari koji AI koriste svaki dan – u nastavi, poslu i kreativnim projektima.' ); // phpcs:ignore ?>
  <section class="aka-sec aka-pt0">
    <div class="aka-wrap">
      <?php echo AKA_UI::mentori( true ); // phpcs:ignore ?>
    </div>
  </section>
  <?php echo AKA_UI::cta( 'Želiš [[predavati]] s nama?', 'Tražimo praktičare koji znaju jednostavno objasniti AI. Javi nam se.', 'Pošalji prijavu', '/kontakt/?predmet=mentor' ); // phpcs:ignore ?>
</div>
