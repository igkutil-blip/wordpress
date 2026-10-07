=== Plan A poklon bon ===
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Prodaja poklon bonova za izlete (WooCommerce): stranica za kupnju, PDF bon s QR kodom
nakon uplate, korištenje kao kupon u košarici i popis bonova u administraciji.

== Opis ==

* Stranica za kupnju: shortcode [plan-a-poklon-bon] – iznosi kao kartice i "Drugi iznos",
  "Za koga je bon", "Od koga", "Poruka primatelju" (brojač znakova), pregled bona uživo,
  gumb "Dodaj u košaricu". Iznos i podaci provjeravaju se na poslužitelju.
* Proizvod: dodatak sam izrađuje skriveni virtualni proizvod "Poklon bon" (bez dostave i
  zalihe, nije u trgovini, pretrazi ni sitemapu). Stavka: "Poklon bon 90,00 € za Ana".
* Izdavanje: tek kad narudžba prijeđe u "U obradi" ili "Završeno" (kod uplatnice kad
  administrator označi uplatu), nikad na "Na čekanju". Kod PLANA-XXXX-XXXX (bez 0, O, 1, I, L),
  WooCommerce kupon (fiksni popust na košaricu, jednokratan, rok valjanosti), PDF (A5) i PNG
  e-mailom kupcu; preuzimanje na završnoj stranici, u "Moj račun" i u administraciji. Bon se
  ne izdaje dvaput za istu narudžbu.
* Korištenje: polje za kod u košarici i na naplati; poveznica iz QR koda /izleti/?bon=KOD
  pamti kod i primjenjuje ga kad je izlet u košarici. Bon se ne može iskoristiti za kupnju
  novog bona, a nijedan kupon ne umanjuje cijenu bona. Ostatak bona (postavka) postaje novi bon.
* Zaštita: najviše 10 neuspjelih unosa koda u 15 minuta po IP adresi; PDF datoteke u
  zaštićenoj mapi s nasumičnim nazivima, preuzimanje samo uz ključ narudžbe ili za administratora.

== Uključene biblioteke ==

* chillerlan/php-qrcode 4.4.2 i chillerlan/php-settings-container 2.1.6 (MIT), u mapi lib/,
  nazivni prostor promijenjen u PlanAPoklonBon\Vendor da se ne sudari s drugim dodacima.
* Font Lato (SIL Open Font License 1.1), assets/fonts/.
* PDF izrađuje sam dodatak (jedna stranica A5 s ugrađenom slikom bona od 300 dpi), bez
  vanjskih servisa.

== Uklanjanje ==

Deaktivacija ne briše ništa. Brisanje dodatka uklanja samo postavke; izdani bonovi (kuponi),
PDF datoteke i skriveni proizvod ostaju jer su dio poslovne evidencije.
