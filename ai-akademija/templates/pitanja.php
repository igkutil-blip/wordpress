<?php defined( 'ABSPATH' ) || exit; ?>
<div class="aka">
  <?php echo AKA_UI::hero( 'Pomoć', 'Česta [[pitanja]]', 'Ne nalaziš odgovor? Piši nam na kontakt stranici.' ); // phpcs:ignore ?>
  <?php echo AKA_UI::pitanja( AKA_Podaci::pitanja(), 'Sve što te [[zanima]]', false ); // phpcs:ignore ?>
  <?php echo AKA_UI::cta( 'Još uvijek imaš [[pitanje?]]', 'Odgovaramo u roku od jednog radnog dana.', 'Kontaktiraj nas', '/kontakt/' ); // phpcs:ignore ?>
</div>
