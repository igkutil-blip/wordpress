=== Plan A poklon bon ===
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.1.4
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

== Novo u 1.1.4 ==

* Polje za kod u košarici i na naplati prikazuje se i kad je u WooCommerceu isključeno
  "Omogući korištenje kodova kupona" (dodatak kupone uvijek uključuje; bez njih se bon ne može
  iskoristiti). Jasniji natpisi: "Imaš poklon bon ili kod za popust?", "Kod s bona", "Iskoristi".

== Novo u 1.1.3 ==

* Gumb "Daruj poklon bon" i u praznoj košarici, ispod "Pogledaj izlete" (kuka
  plan_a_kosarica_empty_actions iz dodatka Plan A košarica 1.2.1 ili novijeg).

== Novo u 1.1.2 ==

* Košarica: uz "Nastavi s odabirom izleta" gumb "Daruj poklon bon" (vodi na stranicu bona;
  prikazuje se kad stranica sa shortcodeom [plan-a-poklon-bon] postoji).

== Novo u 1.1.1 ==

* Kartica "Izdani bonovi": povijest korištenja vidljiva ispod svakog bona (datum, narudžba s
  poveznicom, iskorišteni iznos, novi kod s ostatkom ili "propao"), i za bonove iskorištene
  prije inačice 1.1.0.

== Novo u 1.1.0 ==

* Novi dizajn bona kao ulaznica (omjer 2:1): fotografija ili ilustracija grebena, iznos i imena,
  otkidni dio s QR kodom i kodom bona. Isti dizajn u pregledu na stranici, sličici u košarici,
  naplati, završnoj stranici i e-mailu, u PDF-u (A5, s uputama i izdavateljem) i PNG-u (1600 × 800).
* "Uredi bon" u košarici: povratak na stranicu bona s upisanim podacima; spremanje zamjenjuje stavku.
* Izbornik "Poklon bonovi" s karticama Čekaju uplatu (gumb "Uplata je stigla, pošalji bon",
  crvena oznaka broja), Izdani bonovi (pregled, PDF, ponovno slanje, povijest korištenja, CSV),
  Ručno izdavanje i Postavke; okvir "Poklon bon" na stranici narudžbe.
* E-mail administratoru "Novi poklon bon čeka uplatu".
* Povijest korištenja bona; ostatak se obrađuje i kad je iznos za uplatu 0,00 €.
* Polje za kod prikazuje dodatak za košaricu (kodovi su obični WooCommerce kuponi).

== Uključene biblioteke ==

* chillerlan/php-qrcode 4.4.2 i chillerlan/php-settings-container 2.1.6 (MIT), u mapi lib/,
  nazivni prostor promijenjen u PlanAPoklonBon\Vendor da se ne sudari s drugim dodacima.
* Font Lato (SIL Open Font License 1.1), assets/fonts/.
* PDF izrađuje sam dodatak (jedna stranica A5 s ugrađenom slikom bona od 300 dpi), bez
  vanjskih servisa.

== Uklanjanje ==

Deaktivacija ne briše ništa. Brisanje dodatka uklanja samo postavke; izdani bonovi (kuponi),
PDF datoteke i skriveni proizvod ostaju jer su dio poslovne evidencije.
