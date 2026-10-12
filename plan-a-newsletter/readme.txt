=== Plan A newsletter ===
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later

Slanje newslettera članovima i sudionicima izleta iz sučelja weba.

== Postavljanje ==

1. Otvori mailbox newsletter@srd-plan-a.hr na hostingu (na istoj domeni kao web).
2. Instaliraj i aktiviraj dodatak. U izborniku se pojavi „Newsletter”.
3. Newsletter → Postavke: provjeri adresu i ime pošiljatelja, broj mailova na sat
   (zadano 40) i logo (prazno = logo teme).
4. Pošalji probni mail sebi i provjeri da ne završi u spamu.

== Kako radi ==

* Newsletter → Novi newsletter: naslov, tekst ({ime} = ime primatelja), po želji nadolazeći
  izleti na kraju (ili na mjestu {izleti}).
* Grupe: svi potvrđeni članovi, članovi s plaćenom članarinom, sudionici jednog termina
  izleta, kupci izleta iz zadnje 2 godine koji nisu članovi i ručno upisane adrese.
* Isti e-mail dobiva samo jedan mail. Odjavljeni se preskaču (osim kod obavijesti
  sudionicima izleta na koji su prijavljeni).
* Slanje ide polako: najviše N mailova na sat (Postavke), ravnomjerno svakih 5 minuta.
  Može se pauzirati, nastaviti ili prekinuti. Na kraju stiže izvješće onome tko je poslao.
* Može se zakazati za određeni dan i sat.
* Odjava: link u svakom mailu i odjava jednim klikom u Gmailu i Apple Mailu. Odjava je
  zajednička sa siječanjskim e-mailom za članarinu (dodatak Plan A članstvo).
* Pišu i šalju svi koji mogu pisati objave na webu; postavke samo administrator.
* Ne prati se tko je otvorio mail ni na što je kliknuo. Popis primatelja ne izlazi sa stranice.

== Promjene ==

= 1.1.0 =
* Newsletter i izvješće o slanju u zajedničkom Plan A izgledu (zaglavlje s logom, kartica, podnožje s odjavom).

= 1.0.0 =
* Prva verzija.
