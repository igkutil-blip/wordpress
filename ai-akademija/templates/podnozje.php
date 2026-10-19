<?php defined( 'ABSPATH' ) || exit; ?>
<?php $k = AKA_Podaci::kontakt(); ?>
<div class="aka aka-footer">
  <div class="aka-wrap">
    <div class="aka-fgrid">
      <div>
        <img src="<?php echo esc_url( AKA_URL . 'assets/slike/ai-akademija-logo.png' ); ?>" alt="AI Akademija" width="200" height="51">
        <p>Praktična AI znanja za stvarne rezultate. Online tečajevi za početnike i profesionalce.</p>
      </div>
      <div><h4><a class="aka-fh" href="<?php echo esc_url( AKA_Linkovi::stranica( 'programi' ) ); ?>">Programi</a></h4><?php foreach ( array_slice( AKA_Podaci::tecajevi(), 0, 5, true ) as $s => $t ) : ?><a href="<?php echo esc_url( AKA_Linkovi::tecaj( $s ) ); ?>"><?php echo esc_html( $t['naslov'] ); ?></a><?php endforeach; ?></div>
      <div><h4>Akademija</h4>
        <?php foreach ( array( 'O nama' => 'o-nama', 'Mentori' => 'mentori', 'Za tvrtke' => 'tvrtke', 'Cijene' => 'cijene', 'Blog' => 'blog', 'Česta pitanja' => 'pitanja' ) as $n => $kl ) : $u = AKA_Linkovi::stranica( $kl ); if ( $u ) : ?><a href="<?php echo esc_url( $u ); ?>"><?php echo esc_html( $n ); ?></a><?php endif; endforeach; ?></div>
      <div><h4>Kontakt</h4>
        <a href="mailto:<?php echo esc_attr( $k['email'] ); ?>"><?php echo esc_html( $k['email'] ); ?></a>
        <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $k['telefon'] ) ); ?>"><?php echo esc_html( $k['telefon'] ); ?></a>
        <span><?php echo esc_html( $k['adresa'] ); ?></span>
      </div>
    </div>
    <div class="aka-fbottom">
      <span>© <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( $k['tvrtka'] ); ?></span>
      <span><a href="<?php echo esc_url( AKA_Linkovi::stranica( 'uvjeti' ) ); ?>">Uvjeti korištenja</a><?php if ( AKA_Linkovi::stranica( 'privatnost' ) ) : ?><a href="<?php echo esc_url( AKA_Linkovi::stranica( 'privatnost' ) ); ?>">Pravila privatnosti</a><?php endif; ?></span>
    </div>
  </div>
</div>
