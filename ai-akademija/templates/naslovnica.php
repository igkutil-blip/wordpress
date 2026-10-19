<?php
/** Naslovnica – HTML. Varijable: $logo, $foto. */
defined( 'ABSPATH' ) || exit;
?>
<div class="aka">
  <section class="aka-hero">
    <div class="aka-wrap">
      <div class="aka-hero-text">
        <span class="aka-badge">Znanje koje otvara mogućnosti</span>
        <h1>Praktična AI znanja za <span class="aka-grad-text">stvarne rezultate.</span></h1>
        <p class="aka-lead">Online tečajevi za početnike i profesionalce — nauči koristiti AI alate u poslu i svakodnevnom životu uz praktične primjere, jasne lekcije i podršku mentora.</p>
        <div class="aka-actions">
          <a class="aka-btn aka-btn-grad" href="<?php echo esc_url( AKA_UI::url( '/programi/' ) ); ?>">Započni učenje
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
          <a class="aka-btn aka-btn-ghost" href="#programi"><span class="aka-play"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span>Pogledaj programe</a>
        </div>
        <div class="aka-stats">
          <div class="aka-stat"><span class="aka-stat-ico"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.2"/><path d="M3 19c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><circle cx="17" cy="9" r="2.5"/><path d="M16 13.6c2.8.2 5 2.2 5 5.4"/></svg></span><div><b>12.000+</b><span>polaznika</span></div></div>
          <div class="aka-stat"><span class="aka-stat-ico"><svg class="aka-fill" viewBox="0 0 24 24"><path d="M12 2.8l2.8 5.8 6.3.9-4.6 4.4 1.1 6.3L12 17.2l-5.6 3 1.1-6.3L2.9 9.5l6.3-.9z"/></svg></span><div><b>4.9/5</b><span>ocjena</span></div></div>
          <div class="aka-stat"><span class="aka-stat-ico"><svg viewBox="0 0 24 24"><path d="M3 7.5A1.5 1.5 0 0 1 4.5 6H9l2 2h8.5A1.5 1.5 0 0 1 21 9.5v8a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 17.5z"/></svg></span><div><b>Praktični</b><span>projekti</span></div></div>
          <div class="aka-stat"><span class="aka-stat-ico"><svg viewBox="0 0 24 24"><circle cx="12" cy="9" r="5.5"/><path d="M8.5 13.5L7 21l5-2.5 5 2.5-1.5-7.5"/></svg></span><div><b>Certifikat</b><span>za svaki program</span></div></div>
        </div>
      </div>

      <div class="aka-visual<?php echo $foto ? ' aka-has-foto' : ''; ?>" aria-hidden="true">
        <span class="aka-glow"></span>
        <span class="aka-orbit"></span>
        <span class="aka-orbit aka-orbit-2"></span>
        <img class="aka-logo" src="<?php echo esc_url( $logo ); ?>" alt="" width="512" height="512">
        <?php if ( $foto ) : ?><img class="aka-photo" src="<?php echo esc_url( $foto ); ?>" alt=""><?php endif; ?>
        <div class="aka-fcard aka-fc1">
          <span class="aka-fcard-ico" style="background:#EAF0FF"><svg viewBox="0 0 24 24" style="stroke:#1F5BFF"><path d="M2 5.5C4.5 4.5 8 4.5 12 6.5v13c-4-2-7.5-2-10-1z"/><path d="M22 5.5c-2.5-1-6-1-10 1v13c4-2 7.5-2 10-1z"/></svg></span>
          <div><b>AI Osnove</b><span class="aka-bar"><i style="width:62%"></i></span></div>
        </div>
        <div class="aka-fcard aka-fc2">
          <span class="aka-fcard-ico" style="background:#E3FAF6"><svg viewBox="0 0 24 24" style="stroke:#0E9AA8"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20.5l1.4-4.9A8 8 0 1 1 21 12z"/><path d="M8.5 12h.01M12 12h.01M15.5 12h.01"/></svg></span>
          <div><b>Prompting</b><span class="aka-bar"><i style="width:48%"></i></span></div>
        </div>
        <div class="aka-fcard aka-fc3">
          <span class="aka-fcard-ico" style="background:#E3FAF6"><svg viewBox="0 0 24 24" style="stroke:#0E9AA8"><path d="M5 20v-5M10 20v-9M15 20v-12M20 20V4"/></svg></span>
          <div><b>AI alati za posao</b><span class="aka-bar"><i style="width:74%"></i></span></div>
        </div>
        <div class="aka-info">
          <div><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg>Početnik</div>
          <div><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>12 sati</div>
          <div><svg viewBox="0 0 24 24"><path d="M14 3H6.5A1.5 1.5 0 0 0 5 4.5v15A1.5 1.5 0 0 0 6.5 21h11a1.5 1.5 0 0 0 1.5-1.5V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/></svg>Certifikat</div>
        </div>
      </div>
    </div>
  </section>

  <section class="aka-prog" id="programi">
    <div class="aka-wrap">
      <div class="aka-head">
        <div>
          <p class="aka-kicker">Popularni programi</p>
          <h2>Praktični tečajevi za <span class="aka-grad-text">stvarne rezultate.</span></h2>
        </div>
        <div class="aka-head-r">
          <p>Nauči koristiti najpopularnije AI alate kroz jasne lekcije, konkretne primjere i praktične projekte.</p>
          <div class="aka-arrows">
            <button type="button" class="aka-arrow" data-aka-dir="-1" aria-label="Prethodni"><svg viewBox="0 0 24 24"><path d="M19 12H5M11 6l-6 6 6 6"/></svg></button>
            <button type="button" class="aka-arrow" data-aka-dir="1" aria-label="Sljedeći"><svg viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
          </div>
        </div>
      </div>

      <div class="aka-track">
        <?php foreach ( array_keys( AKA_Podaci::tecajevi() ) as $slug ) { echo AKA_UI::kartica_tecaja( AKA_Podaci::tecaj( $slug ) ); } // phpcs:ignore ?>
      </div>
    </div>
  </section>
  <?php include AKA_DIR . 'templates/dio-koraci.php'; ?>
  <?php include AKA_DIR . 'templates/dio-zasto.php'; ?>
  <section class="aka-sec">
    <div class="aka-wrap">
      <div class="aka-head"><div><p class="aka-kicker">Mentori</p><h2>Uči od ljudi koji AI <span class="aka-grad-text">koriste svaki dan.</span></h2></div>
        <?php echo AKA_UI::gumb( 'Svi mentori', '/mentori/', 'ghost' ); // phpcs:ignore ?></div>
      <div class="aka-grid aka-g4"><?php foreach ( AKA_Podaci::mentori() as $m ) { echo AKA_UI::kartica_mentora( $m ); } // phpcs:ignore ?></div>
    </div>
  </section>
  <?php include AKA_DIR . 'templates/dio-cijene.php'; ?>
  <?php echo AKA_UI::pitanja( array_slice( AKA_Podaci::pitanja(), 0, 4 ) ); // phpcs:ignore ?>
  <?php echo AKA_UI::cta(); // phpcs:ignore ?>
</div>
