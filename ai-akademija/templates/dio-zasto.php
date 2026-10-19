<?php defined( 'ABSPATH' ) || exit; ?>
<section class="aka-sec aka-soft">
  <div class="aka-wrap">
    <p class="aka-kicker">Zašto AI Akademija</p>
    <h2>Manje teorije, <span class="aka-grad-text">više rezultata.</span></h2>
    <div class="aka-grid aka-g4">
      <?php foreach ( array(
        array( 'meta', 'Praktično', 'Svaka lekcija završava zadatkom koji odmah primijeniš u poslu ili životu.' ),
        array( 'ljudi', 'Podrška mentora', 'Pitanja postavljaš izravno mentorima i zajednici polaznika.' ),
        array( 'munja', 'Uvijek aktualno', 'AI se brzo mijenja – tečajeve osvježavamo čim izađu novi alati.' ),
        array( 'stit', 'Sigurno i odgovorno', 'Naučit ćeš što smiješ dijeliti s AI-jem i kako provjeriti odgovore.' ),
      ) as $f ) : ?>
        <div class="aka-feat"><span class="aka-ico"><?php echo AKA_Podaci::ikona( $f[0] ); // phpcs:ignore ?></span><h3><?php echo esc_html( $f[1] ); ?></h3><p><?php echo esc_html( $f[2] ); ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
