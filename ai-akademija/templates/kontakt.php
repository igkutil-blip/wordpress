<?php defined( 'ABSPATH' ) || exit; ?>
<?php $k = AKA_Podaci::kontakt(); ?>
<div class="aka">
  <?php echo AKA_UI::hero( 'Kontakt', 'Javi nam [[se.]]', 'Imaš pitanje o tečaju, plaćanju ili edukaciji za tvrtku? Odgovaramo u roku od jednog radnog dana.' ); // phpcs:ignore ?>
  <section class="aka-sec aka-pt0">
    <div class="aka-wrap aka-split">
      <div class="aka-contact">
        <a href="mailto:<?php echo esc_attr( $k['email'] ); ?>"><span class="aka-ico"><?php echo AKA_Podaci::ikona( 'mail' ); // phpcs:ignore ?></span><span><small>E-mail</small><?php echo esc_html( $k['email'] ); ?></span></a>
        <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $k['telefon'] ) ); ?>"><span class="aka-ico"><?php echo AKA_Podaci::ikona( 'telefon' ); // phpcs:ignore ?></span><span><small>Telefon</small><?php echo esc_html( $k['telefon'] ); ?></span></a>
        <div><span class="aka-ico"><?php echo AKA_Podaci::ikona( 'mjesto' ); // phpcs:ignore ?></span><span><small>Adresa</small><?php echo esc_html( $k['adresa'] ); ?></span></div>
        <div><span class="aka-ico"><?php echo AKA_Podaci::ikona( 'sat' ); // phpcs:ignore ?></span><span><small>Radno vrijeme</small><?php echo esc_html( $k['radno'] ); ?></span></div>
        <p class="aka-note"><?php echo esc_html( $k['tvrtka'] . ' · OIB ' . $k['oib'] ); ?></p>
      </div>
      <?php echo AI_Akademija::obrazac( array() ); // phpcs:ignore ?>
    </div>
  </section>
  <?php echo AKA_UI::pitanja( AKA_Podaci::pitanja() ); // phpcs:ignore ?>
</div>
