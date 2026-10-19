<?php defined( 'ABSPATH' ) || exit; ?>
<div class="aka">
  <?php echo AKA_UI::hero( 'Programi', 'Svi [[tečajevi]]', 'Od prvih koraka s AI-jem do automatizacije posla. Odaberi razinu i kreni.' ); // phpcs:ignore ?>
  <section class="aka-sec aka-pt0">
    <div class="aka-wrap">
      <div class="aka-filter" role="group" aria-label="Razina">
        <button type="button" class="is-on" data-aka-filter="">Svi</button>
        <?php foreach ( array_unique( wp_list_pluck( AKA_Podaci::tecajevi(), 'razina' ) ) as $r ) : ?>
          <button type="button" data-aka-filter="<?php echo esc_attr( sanitize_title( $r ) ); ?>"><?php echo esc_html( $r ); ?></button>
        <?php endforeach; ?>
      </div>
      <div class="aka-grid aka-g3 aka-courses">
        <?php foreach ( array_keys( AKA_Podaci::tecajevi() ) as $slug ) { echo AKA_UI::kartica_tecaja( AKA_Podaci::tecaj( $slug ) ); } // phpcs:ignore ?>
      </div>
    </div>
  </section>
  <?php include AKA_DIR . 'templates/dio-koraci.php'; ?>
  <?php echo AKA_UI::cta( 'Ne znaš odakle [[početi?]]', 'Javi nam se i preporučit ćemo ti tečaj prema tvom poslu i predznanju.', 'Pitaj nas', '/kontakt/' ); // phpcs:ignore ?>
</div>
