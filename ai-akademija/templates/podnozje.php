<?php defined( 'ABSPATH' ) || exit; ?>
<?php $k = AKA_Podaci::kontakt(); ?>
<div class="aka aka-footer">
  <div class="aka-wrap">
    <div class="aka-fgrid">
      <div>
        <img src="<?php echo esc_url( AKA_URL . 'assets/slike/ai-akademija-logo.png' ); ?>" alt="AI Akademija" width="200" height="51">
        <p>Praktična AI znanja za stvarne rezultate. Online tečajevi za početnike i profesionalce.</p>
      </div>
      <div><h4>Programi</h4><?php foreach ( array_slice( AKA_Podaci::tecajevi(), 0, 5, true ) as $s => $t ) : ?><a href="<?php echo esc_url( AKA_UI::url( '/programi/' . $s . '/' ) ); ?>"><?php echo esc_html( $t['naslov'] ); ?></a><?php endforeach; ?></div>
      <div><h4>Akademija</h4>
        <?php foreach ( array( 'O nama' => '/o-nama/', 'Mentori' => '/mentori/', 'Za tvrtke' => '/za-tvrtke/', 'Cijene' => '/cijene/', 'Blog' => '/blog/', 'Česta pitanja' => '/cesta-pitanja/' ) as $n => $u ) : ?><a href="<?php echo esc_url( AKA_UI::url( $u ) ); ?>"><?php echo esc_html( $n ); ?></a><?php endforeach; ?></div>
      <div><h4>Kontakt</h4>
        <a href="mailto:<?php echo esc_attr( $k['email'] ); ?>"><?php echo esc_html( $k['email'] ); ?></a>
        <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $k['telefon'] ) ); ?>"><?php echo esc_html( $k['telefon'] ); ?></a>
        <span><?php echo esc_html( $k['adresa'] ); ?></span>
      </div>
    </div>
    <div class="aka-fbottom">
      <span>© <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( $k['tvrtka'] ); ?></span>
      <span><a href="<?php echo esc_url( AKA_UI::url( '/uvjeti-koristenja/' ) ); ?>">Uvjeti korištenja</a><a href="<?php echo esc_url( get_privacy_policy_url() ? get_privacy_policy_url() : AKA_UI::url( '/pravila-privatnosti/' ) ); ?>">Pravila privatnosti</a></span>
    </div>
  </div>
</div>
