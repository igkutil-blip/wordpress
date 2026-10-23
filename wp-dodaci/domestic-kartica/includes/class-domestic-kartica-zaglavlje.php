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
		add_action( 'wp_footer', array( __CLASS__, 'donja_traka' ), 5 );
		add_filter( 'woocommerce_add_to_cart_fragments', array( __CLASS__, 'fragment' ) );
		add_filter( 'body_class', array( __CLASS__, 'klasa_dno' ) );
	}

	private static function dno_ukljuceno(): bool {
		return self::ukljuceno() && 'yes' === Domestic_Kartica::postavke()['donja_traka'] && ! ( function_exists( 'is_checkout' ) && is_checkout() );
	}

	public static function klasa_dno( array $c ): array {
		if ( self::dno_ukljuceno() ) {
			$c[] = 'dsk-ima-dno';
		}
		return $c;
	}

	const DNO_IKONE = array(
		'pocetna' => '<path d="M3.5 11 12 4l8.5 7"/><path d="M5.5 9.5V20h4.5v-5.5h4V20h4.5V9.5"/>',
		'ponuda'  => '<rect x="4" y="4" width="6.5" height="6.5" rx="1"/><rect x="13.5" y="4" width="6.5" height="6.5" rx="1"/><rect x="4" y="13.5" width="6.5" height="6.5" rx="1"/><rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1"/>',
		'pokloni' => '<path d="M4 10h16v3H4zM5.5 13h13v7.5h-13zM12 10v10.5"/><path d="M12 10c-2-3.5-5.5-3.5-5.5-1.5S9.5 10 12 10Zm0 0c2-3.5 5.5-3.5 5.5-1.5S14.5 10 12 10Z"/>',
		'trazi'   => '<circle cx="10.5" cy="10.5" r="6"/><path d="m15 15 5 5"/>',
		'kosarica'=> '<path d="M5 8h14l-1.2 12H6.2L5 8Z"/><path d="M9 10V6.5a3 3 0 0 1 6 0V10"/>',
	);

	private static function broj(): int {
		return function_exists( 'WC' ) && WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0;
	}

	private static function broj_html(): string {
		$n = self::broj();
		return '<span class="dsk-dno-broj" data-n="' . $n . '">' . $n . '</span>';
	}

	/** Broj u košarici se osvježava s WooCommerceovim fragmentima (dodavanje bez ponovnog učitavanja). */
	public static function fragment( $f ) {
		if ( self::ukljuceno() ) {
			$f['span.dsk-dno-broj'] = self::broj_html();
		}
		return $f;
	}

	/** Mobitel: stalna krem traka na dnu s najvažnijim mjestima. */
	public static function donja_traka(): void {
		if ( ! self::dno_ukljuceno() ) {
			return;
		}
		$shop   = wc_get_page_permalink( 'shop' );
		$poklon = get_terms( array( 'taxonomy' => 'product_cat', 'search' => 'poklon', 'hide_empty' => true, 'parent' => 0, 'number' => 1 ) );
		$poklon = ! is_wp_error( $poklon ) && $poklon ? $poklon[0] : null;
		$sad    = is_front_page() ? 'pocetna' : ( is_cart() ? 'kosarica' : ( $poklon && is_tax( 'product_cat', $poklon->term_id ) ? 'pokloni' : ( is_shop() || is_product_taxonomy() || is_product() ? 'ponuda' : '' ) ) );
		$stavke = array(
			'pocetna'  => array( home_url( '/' ), 'Početna' ),
			'ponuda'   => array( $shop, 'Ponuda' ),
			'pokloni'  => $poklon ? array( get_term_link( $poklon ), 'Pokloni' ) : null,
			'trazi'    => array( add_query_arg( array( 's' => '', 'post_type' => 'product' ), home_url( '/' ) ), 'Traži' ),
			'kosarica' => array( wc_get_cart_url(), 'Košarica' ),
		);
		$h = '';
		foreach ( array_filter( $stavke ) as $k => $v ) {
			$h .= '<a href="' . esc_url( $v[0] ) . '" class="dsk-dno-' . $k . ( $sad === $k ? ' dsk-sad' : '' ) . '"' . ( $sad === $k ? ' aria-current="page"' : '' ) . '>'
				. '<svg viewBox="0 0 24 24" aria-hidden="true">' . self::DNO_IKONE[ $k ] . '</svg>' . ( 'kosarica' === $k ? self::broj_html() : '' )
				. '<span>' . esc_html( $v[1] ) . '</span></a>';
		}
		echo '<nav class="dsk-dno dsk-dno-' . count( array_filter( $stavke ) ) . '" aria-label="Brzi izbornik">' . $h . '</nav>'; // phpcs:ignore WordPress.Security.EscapeOutput
		// pretraga iz trake: vlastiti panel na vrhu (prijedlozi iz trazilica.js se spajaju sami na input[name="s"])
		echo '<div class="dsk-dno-panel" id="dsk-dno-panel" hidden><form role="search" method="get" class="searchform dsk-trazi" action="' . esc_url( home_url( '/' ) ) . '">'
			. '<label class="screen-reader-text" for="dsk-dno-s">Pretraži proizvode</label>'
			. '<svg viewBox="0 0 24 24" aria-hidden="true">' . self::DNO_IKONE['trazi'] . '</svg>'
			. '<input type="search" id="dsk-dno-s" name="s" placeholder="Pretraži likere, vina, rakije…" autocomplete="off"><input type="hidden" name="post_type" value="product">'
			. '<button type="button" class="dsk-dno-zatvori" aria-label="Zatvori pretragu">×</button></form></div>';
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
			'window.dskZaglavlje=' . wp_json_encode( array( 'dostava' => $kratki, 'pocetna' => home_url( '/' ), 'trgovina' => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '' ) ) . ';' . self::JS
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
    /* ikone za mrežu na mobitelu (prema adresi kategorije) */
    var IK={
      vin:'<path d="M8 3h8l-.6 5.2a3.5 3.5 0 0 1-6.8 0L8 3Z"/><path d="M8.3 6h7.4M12 11.6V20m-3.5 0h7"/>',
      vinar:'<circle cx="9" cy="10" r="2"/><circle cx="13" cy="10" r="2"/><circle cx="11" cy="13.4" r="2"/><circle cx="15" cy="13.4" r="2"/><circle cx="13" cy="16.8" r="2"/><path d="M11 8c0-2 1-3.5 3-4.5M12.5 5.5c1.5-.8 3-.5 4 .5"/>',
      liker:'<path d="M10 2.5h4v3l1.2 2.3c.5 1 .8 2 .8 3.1V20a1.5 1.5 0 0 1-1.5 1.5h-5A1.5 1.5 0 0 1 8 20v-9.1c0-1.1.3-2.1.8-3.1L10 5.5v-3Z"/><path d="M8 12.5h8v5H8z"/>',
      pivo:'<path d="M6 7h9v12.5a1.5 1.5 0 0 1-1.5 1.5h-6A1.5 1.5 0 0 1 6 19.5V7Z"/><path d="M15 9.5h2.2a1.8 1.8 0 0 1 1.8 1.8v3.4a1.8 1.8 0 0 1-1.8 1.8H15M6 7a2.5 2.5 0 0 1 2.5-3.2A3 3 0 0 1 13 3.6 2.4 2.4 0 0 1 15 7M9 11v6.5m3-6.5v6.5"/>',
      horeca:'<path d="M3.5 17h17M5 17a7 7 0 0 1 14 0M12 8V6.5m-1.5 0h3M2.5 20h19"/>',
      sok:'<path d="M7 7h10l-1.3 13.2a1 1 0 0 1-1 .8H9.3a1 1 0 0 1-1-.8L7 7Z"/><path d="M7.6 12.5h8.8M13 7l2-4.5h2"/>',
      poklon:'<path d="M4 10h16v3H4zM5.5 13h13v7.5h-13zM12 10v10.5"/><path d="M12 10c-2-3.5-5.5-3.5-5.5-1.5S9.5 10 12 10Zm0 0c2-3.5 5.5-3.5 5.5-1.5S14.5 10 12 10Z"/>',
      sve:'<rect x="4" y="4" width="6.5" height="6.5" rx="1"/><rect x="13.5" y="4" width="6.5" height="6.5" rx="1"/><rect x="4" y="13.5" width="6.5" height="6.5" rx="1"/><rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1"/>'
    };
    function kljuc(p){ return /vinarij/.test(p)?'vinar':/vin/.test(p)?'vin':/pivo|pive/.test(p)?'pivo':/horeca/.test(p)?'horeca':/sok/.test(p)?'sok':/poklon/.test(p)?'poklon':/likeri|rakij|alkohol|liker/.test(p)?'liker':'sve'; }
    function ikona(a){ if(a.querySelector('.dsk-kat-ik')) return; var p=''; try{ p=new URL(a.href).pathname; }catch(e){} a.insertAdjacentHTML('afterbegin','<svg class="dsk-kat-ik" viewBox="0 0 24 24" aria-hidden="true">'+IK[kljuc(p)]+'</svg>'); }
    n.querySelectorAll('li.menu-item > a').forEach(function(a){ ikona(a); try{ var p=new URL(a.href).pathname; if(p!=='/' && put.indexOf(p)===0){ a.parentNode.classList.add('dsk-sad'); on=a.parentNode; } }catch(e){} });
    /* „Sve” – samo na mobitelu, da mreža 4 × 2 bude puna */
    if(d.trgovina && !n.querySelector('.dsk-kat-sve')){
      var li=document.createElement('li'); li.className='menu-item dsk-kat-sve';
      li.innerHTML='<a href="'+d.trgovina+'" class="nav-top-link"><svg class="dsk-kat-ik" viewBox="0 0 24 24" aria-hidden="true">'+IK.sve+'</svg>Sve</a>';
      try{ if(put.indexOf(new URL(d.trgovina).pathname)===0) li.classList.add('dsk-sad'); }catch(e){}
      n.appendChild(li);
    }
  }
  /* traka na dnu: Traži = panel pretrage na vrhu, Košarica = Flatsomeova bočna košarica (ako postoji) */
  function dno(){
    var t=document.querySelector('.dsk-dno-trazi'), k=document.querySelector('.dsk-dno-kosarica'), pn=document.getElementById('dsk-dno-panel');
    function zatvori(){ if(pn){ pn.hidden=true; t&&t.classList.remove('dsk-otvoreno'); } }
    if(t&&pn){
      t.addEventListener('click',function(e){
        e.preventDefault(); e.stopPropagation();
        if(!pn.hidden){ zatvori(); return; }
        pn.hidden=false; t.classList.add('dsk-otvoreno');
        var i=pn.querySelector('input[name="s"]'); if(i){ i.focus(); }
      });
      pn.querySelector('.dsk-dno-zatvori').addEventListener('click',zatvori);
      document.addEventListener('keydown',function(e){ if(e.key==='Escape') zatvori(); });
      document.addEventListener('click',function(e){ if(!pn.hidden && !pn.contains(e.target) && !t.contains(e.target)) zatvori(); });
    }
    if(k) k.addEventListener('click',function(e){
      var c=document.querySelector('#masthead .show-for-medium .header-cart-link.off-canvas-toggle');
      if(c){ e.preventDefault(); c.click(); }
    });
  }
  if(document.readyState!=='loading'){slozi();traka();dno();} else document.addEventListener('DOMContentLoaded',function(){slozi();traka();dno();});
})();
JS;
}
