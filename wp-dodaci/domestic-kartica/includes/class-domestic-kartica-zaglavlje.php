<?php
/**
 * Zaglavlje: gornja traka (dostava) s kratkim tekstom za mobitel, traka kategorija (računalo i mobitel),
 * izbornik na mobitelu (logo, kategorije, poveznice iz gornje trake, dostava). Samo izgled – adrese iste.
 */

defined( 'ABSPATH' ) || exit;

final class Domestic_Kartica_Zaglavlje {

	const KAMION = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 6.5h11v9h-11zM13.5 9.5h4l3 3.2v2.8h-7"/><circle cx="6.5" cy="17" r="1.8"/><circle cx="16.5" cy="17" r="1.8"/></svg>';

	public static function init(): void {
		add_filter( 'theme_mod_topbar_left', array( __CLASS__, 'traka' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'skripta' ), 30 );
	}

	private static function ukljuceno(): bool {
		$s = Domestic_Kartica::postavke();
		return ! is_admin() && 'yes' === $s['ukljuceno'] && 'yes' === $s['zaglavlje'];
	}

	/** Isti tekst kao u Flatsomeu (računalo), a na mobitelu kratki redak iz postavke `traka_kratka`. */
	public static function traka( $html ) {
		if ( ! self::ukljuceno() || '' === trim( wp_strip_all_tags( (string) $html ) ) ) {
			return $html;
		}
		$dugi   = trim( wp_strip_all_tags( (string) $html ) );
		$kratki = trim( (string) Domestic_Kartica::postavke()['traka_kratka'] );
		return '<span class="dsk-traka">' . self::KAMION . '<span class="dsk-traka-duga">' . esc_html( $dugi ) . '</span>'
			. '<span class="dsk-traka-kratka">' . esc_html( '' !== $kratki ? $kratki : $dugi ) . '</span></span>';
	}

	public static function skripta(): void {
		if ( ! self::ukljuceno() ) {
			return;
		}
		wp_register_script( 'domestic-zaglavlje', false, array(), DSK_VER, true );
		wp_enqueue_script( 'domestic-zaglavlje' );
		$kratki = (string) Domestic_Kartica::postavke()['traka_kratka'];
		wp_add_inline_script(
			'domestic-zaglavlje',
			'window.dskZaglavlje=' . wp_json_encode( array( 'dostava' => $kratki, 'pocetna' => home_url( '/' ) ) ) . ';' . self::JS
		);
	}

	/* Izbornik na mobitelu: logo gore, poveznice iz gornje trake i dostava dolje; trenutna kategorija označena. */
	const JS = <<<'JS'
(function(){
  var d=window.dskZaglavlje||{};
  function slozi(){
    var m=document.querySelector('#main-menu .sidebar-menu'); if(!m||m.dataset.dsk) return; m.dataset.dsk='1';
    var logo=document.querySelector('#logo img.header_logo,#logo img');
    var vrh=document.createElement('div'); vrh.className='dsk-izb-vrh';
    if(logo){ vrh.innerHTML='<a href="'+(d.pocetna||'/')+'"><img src="'+logo.currentSrc+'" alt="'+(logo.alt||'')+'"></a>'; }
    m.insertBefore(vrh,m.firstChild);
    var dno=document.createElement('div'); dno.className='dsk-izb-dno';
    var h='';
    document.querySelectorAll('#top-bar .top-bar-nav > li.menu-item > a').forEach(function(a){ h+='<a href="'+a.href+'">'+a.textContent.trim()+'</a>'; });
    if(h) dno.innerHTML='<nav class="dsk-izb-veze">'+h+'</nav>';
    if(d.dostava) dno.innerHTML+='<p class="dsk-izb-dostava"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 6.5h11v9h-11zM13.5 9.5h4l3 3.2v2.8h-7"/><circle cx="6.5" cy="17" r="1.8"/><circle cx="16.5" cy="17" r="1.8"/></svg>'+d.dostava.replace(/</g,'&lt;')+'</p>';
    m.appendChild(dno);
    var put=location.pathname;
    m.querySelectorAll('.nav-sidebar > li.menu-item > a').forEach(function(a){ try{ if(put.indexOf(new URL(a.href).pathname)===0 && new URL(a.href).pathname!=='/') a.parentNode.classList.add('dsk-sad'); }catch(e){} });
  }
  /* traka kategorija: trenutna kategorija označena */
  function traka(){
    var n=document.querySelector('#wide-nav .header-bottom-nav.nav-left'); if(!n) return;
    var put=location.pathname, on=null;
    n.querySelectorAll('li.menu-item > a').forEach(function(a){ try{ var p=new URL(a.href).pathname; if(p!=='/' && put.indexOf(p)===0){ a.parentNode.classList.add('dsk-sad'); on=a.parentNode; } }catch(e){} });
  }
  if(document.readyState!=='loading'){slozi();traka();} else document.addEventListener('DOMContentLoaded',function(){slozi();traka();});
})();
JS;
}
