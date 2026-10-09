=== Plan A jedrenje ===
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.5.0
License: GPLv2 or later

Rezervacija tjednog jedrenja za ekipu (subota–subota) u terminu koji kupac bira.

== Kako radi ==

1. Shortcode [plan-a-jedrenje] prikazuje rezervacijski blok: brod, polazak, ekipa, cijena
   "od … za cijeli brod", uključeno (kvačice) i nije uključeno, kalendar tjedana, sažetak
   odabranog tjedna, obrazac za zahtjev i gumb "Predloži ekipi".
2. Kalendar: samo tjedni sezone (ova i sljedeća godina), bez prošlih. Slobodno (plavi obrub),
   Na upitu (narančasto, još se može poslati zahtjev), Zauzeto (sivo, ne može se odabrati).
   Cijela sezona vidi se odjednom: na računalu svi mjeseci u jednom redu, na mobitelu po dva.
3. Cijena je uvijek za cijeli brod: ručna cijena tjedna > cijena razdoblja (subota ukrcaja) >
   osnovna cijena. Uz cijenu se može prikazati precrtana redovna cijena. Ispod cijene:
   "Za X osoba to je Y € po osobi".
4. Zahtjev (bez plaćanja): tjedan postaje "Na upitu", kupac dobiva e-mail "Zahtjev je zaprimljen",
   administrator e-mail s poveznicom.
5. Administracija → Jedrenje → Zahtjevi → "Brod je slobodan, pošalji uplatnicu": izrađuje se
   WooCommerce narudžba (virman, "Na čekanju") na iznos akontacije (30 %), pa kupac dobiva
   e-mail s uplatnicom i 2D kodom (dodatak Hub3) i rokom uplate (5 dana). Ako je do ukrcaja
   manje od 30 dana, traži se cijeli iznos. "Odbij" šalje kupcu poruku.
6. Rezervacije → "Uplata akontacije je stigla" (ili narudžba u WooCommerceu prebačena u
   "U obradi"): tjedan postaje zauzet, kupac dobiva potvrdu s rokom za ostatak.
7. "Pošalji uplatnicu za ostatak" izrađuje drugu narudžbu na ostatak; "Uplata ostatka je stigla"
   zatvara rezervaciju ("Sve plaćeno").
8. Poklon bon: e-mail s uplatnicom ima poveznicu "Plati preko košarice". Uplata je tada u
   košarici (Plan A košarica), kupac upiše kod s bona u polje za kod i plati preko naplate;
   narudžba administratora za taj dio se sama otkazuje.
9. Podsjetnik administratoru jednom dnevno: akontacija nije uplaćena u roku (tjedan se NE
   oslobađa sam – "Otkaži rezervaciju i oslobodi tjedan"), rok za ostatak za 7 dana, ostatak kasni.

Zaštita: cijena, broj osoba, ruta i stanje tjedna provjeravaju se na poslužitelju; tjedan se
zaključava pri promjeni stanja; za tjedan s poslanom uplatnicom ne može se poslati druga; nonce,
prava pristupa (manage_woocommerce), ograničenje zahtjeva po IP adresi, skriveno polje protiv botova.

== Predmemorija (SpeedyCache) ==

Stanja tjedana i nonce uvijek se dohvaćaju svježe preko /wp-admin/admin-ajax.php, pa blok radi
i na stranici iz predmemorije. Ipak izuzmi:
* stranicu s rezervacijom, npr. /jedrenje/
* adrese s parametrom paj_plati (poveznica za plaćanje preko košarice)
* /wp-admin/admin-ajax.php (obično nikad nije u predmemoriji)
Košarica, naplata i "Moj račun" već moraju biti izuzeti zbog WooCommercea.

== Promjene ==

= 1.5.0 =
* Jedrenje u popisu izleta i planu izleta (treba Plan A izleti 1.20.0 ili noviji). Izlet jedrenja
  iz WpTravellyja (Postavke → Popis i plan izleta) ostaje kartica u popisu, ali s terminima i cijenom
  iz kalendara jedrenja ("od 5.600 € za cijeli brod"), a kartica, plan i "Predloži ekipi" vode na
  stranicu s rezervacijom. U planu izleta jedrenje je jedan redak za cijelu sezonu.
* Popis i plan se osvježe čim se promijeni tjedan, cijena ili rezervacija.

= 1.4.1 =
* U uvodu svaka ruta u svom retku: podebljano "Ruta A:", pa otoci (Šolta, Brač, Hvar, Vis).

= 1.4.0 =
* Slajder: fotografije se više ne režu – cijela slika na zamućenoj pozadini iste slike (na mobitelu
  kvadrat), a naziv programa i gumb "Odaberi tjedan" su ispod fotografija.
* Prijelazni tjedni 26. 6. – 3. 7. 2027. i 11. – 18. 9. 2027. po 6.000 € (ručna cijena tjedna;
  mijenja se u Jedrenje → Kalendar).

= 1.3.0 =
* Tekstovi s izleta "7 dana jedrenja po srednjoj i južnoj Dalmaciji": rute A i B s programom po
  danima, sadržaji (planinarenje, biciklizam, gastronomija i vino, more i opuštanje), nije uključeno.
* Sadržaji se biraju kvačicama (može više ili nijedan).
* Novi dio "Tko vas vodi" i "Važno znati" te kontakt s telefonom (uređuje se u Postavkama).
* Stari zadani tekstovi se sami zamjenjuju novima; tekstovi koje si uredio ostaju.

= 1.2.0 =
* Kalendar prikazuje cijelu sezonu odjednom, bez listanja: svi mjeseci jedan uz drugi (na mobitelu
  po dva), tjedni kao kompaktne kartice "15.–22. 5." s cijenom.
* Koncepti tjedna (Jedrenje i kupanje, planinarenje s licenciranim diplomiranim planinskim vodičem,
  biciklizam, gastronomija, kombinirano) s opisima; kupac bira koncept u zahtjevu, a vidi se u
  e-mailovima i administraciji.
* Rute s opisom i planom po danima (Ruta A, Ruta B, Dogovor sa skiperom); uređuju se u Postavkama
  (blok po ruti: naziv, kratki opis, dani).
* U uvodu kratko: koji koncepti i rute postoje.

= 1.1.0 =
* Slajder s fotografijama na vrhu rezervacijskog bloka: istaknuta slika i galerija izleta jedrenja
  iz WpTravellyja (automatski izlet s "jedrenje" u nazivu ili odabrani u Postavke → Fotografije).
  Slike se same izmjenjuju i lagano približavaju, listaju se prstom, strelicama i točkama, a dodirom
  se otvaraju preko cijelog zaslona. Preko slika naziv programa i gumb "Odaberi tjedan".
* Postavka "Najmanje dana do ukrcaja" (zadano 7): bliži tjedni se ne nude.
* Shortcode [plan-a-jedrenje-slike] (atribut izlet="ID") za slajder bilo gdje na stranici.

= 1.0.1 =
* Sezona zadano traje do 15. listopada: zadnji iskrcaj je prva subota od 15. 10. (2027.: 16. 10.).
* Početni cjenik 2027.: Špica od subote 26. 6. do 15. 9. (zadnji ukrcaj 11. 9.) 6.400 € za cijeli brod
  (100 € po osobi više), Posezona od 16. 9. do 15. 10. 5.600 €; ostali tjedni osnovna cijena 5.600 €.
  Upisuje se samo ako cjenik još nije uređen.

= 1.0.0 =
* Prva inačica.
