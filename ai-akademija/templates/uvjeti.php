<?php defined( 'ABSPATH' ) || exit; ?>
<div class="aka">
  <?php echo AKA_UI::hero( 'Pravno', 'Uvjeti [[korištenja]]', 'Primjer teksta – zamijeniti pravim uvjetima prije objave (preporuka: pregled pravnika).' ); // phpcs:ignore ?>
  <section class="aka-sec aka-pt0">
    <div class="aka-wrap aka-narrow aka-prose">
      <?php foreach ( array( '1. Opće odredbe', '2. Registracija i korisnički račun', '3. Kupnja tečaja i plaćanje', '4. Pristup sadržaju', '5. Pravo na odustanak', '6. Autorska prava', '7. Odgovornost', '8. Završne odredbe' ) as $h ) : ?>
        <h2><?php echo esc_html( $h ); ?></h2>
        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
      <?php endforeach; ?>
    </div>
  </section>
</div>
