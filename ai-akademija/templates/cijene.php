<?php defined( 'ABSPATH' ) || exit; ?>
<div class="aka">
  <?php echo AKA_UI::hero( 'Cijene', 'Jasne cijene, [[bez iznenađenja.]]', 'Plati jedan tečaj ili uzmi sve – i otkaži kad želiš.' ); // phpcs:ignore ?>
  <?php include AKA_DIR . 'templates/dio-cijene.php'; ?>
  <?php echo AKA_UI::pitanja( AKA_Podaci::pitanja() ); // phpcs:ignore ?>
</div>
