# Proširenja objava o partnerima (23.10.2026.) – dodaje se iza postojećeg teksta, ispred „U našoj ponudi”.
# Izvor: opisi proizvoda u shopu (gastro preporuke, temperature, podrijetlo) + opće poznato o vinogorjima.


def stol(redovi):
    """Tablica „Za stolom”: (vino, uz što, temperatura)."""
    r = ''.join(f'<tr><th>{a}</th><td>{b}</td><td>{c}</td></tr>' for a, b, c in redovi)
    return ('<h2>Za stolom</h2>\n<table class="dsb-stol"><thead><tr><th>Vino</th><th>Uz što</th><th>Temperatura</th></tr></thead>'
            f'<tbody>{r}</tbody></table>\n')


def stol_pica(redovi):
    r = ''.join(f'<tr><th>{a}</th><td>{b}</td><td>{c}</td></tr>' for a, b, c in redovi)
    return ('<h2>Kako poslužiti</h2>\n<table class="dsb-stol"><thead><tr><th>Piće</th><th>Prilika</th><th>Temperatura</th></tr></thead>'
            f'<tbody>{r}</tbody></table>\n')


def savjet(t):
    return f'<p class="dsb-savjet"><strong>Naš savjet:</strong> {t}</p>\n'


DODACI = {
    'vinarija-pavlomir': '''<h2>Vinodol – vinogradi s pogledom na more</h2>
<p>Vinodolska dolina proteže se usporedno s obalom, između Crikvenice i Novog Vinodolskog. Zaštićena je brdima od hladnih kontinentalnih vjetrova, a otvorena prema moru, pa ljeta donose puno sunca, a bura i maestral provjetravaju vinograde. Upravo takvi uvjeti žlahtini daju svježinu, a vinima Pavlomira onu blago slanu, „morsku” crtu po kojoj ih se lako prepozna.</p>
<p>Žlahtina je sorta koju većina ljubitelja vina veže uz otok Krk, no u Vinodolu ima dugu tradiciju. Pavlomir je pokazao koliko ozbiljna ta sorta može biti: od lagane, pitke žlahtine za svaki dan do bogatije Grand Cru verzije koja podnosi i ozbiljniju kuhinju.</p>
''' + stol([
        ('Žlahtina Grand Cru', 'škampi, jakobove kapice, piletina na mediteranski način', '12 – 14 °C'),
        ('Žlahtina', 'plodovi mora, salate, lagana ljetna jela', '12 – 14 °C'),
        ('Graševina Palinkaš', 'bijelo meso, riba s maslinovim uljem', '12 – 14 °C'),
        ('Rosé', 'aperitiv, pršut i sir, voćne salate', '12 – 14 °C'),
    ]) + savjet('za ljetni ručak uz more uzmite jednu Žlahtinu i jedan Rosé – prvi ide uz predjelo i ribu, drugi uz desert na terasi.'),

    'vinarija-frankovic': '''<h2>Bujština – vinski sjever Istre</h2>
<p>Buje i okolni brežuljci dio su sjeverozapadne Istre, gdje se crvena zemlja (terra rossa) izmjenjuje s laporom, a vinogradi gledaju prema Tršćanskom zaljevu. Tu je dom istarske malvazije i <strong>momjanskog muškata</strong>, jednog od najpoznatijih hrvatskih desertnih vina, koje je ime dobilo po obližnjem Momjanu.</p>
<p>Položaj Santa Lucia, na kojem Franković uzgaja lozu, daje vina jasne sortne arome i živih kiselina – malvaziju svježu i mirisnu, a muškat raskošan, ali ne težak.</p>
''' + stol([
        ('Malvazija', 'riba, plodovi mora, istarske fuži i tjestenine', '10 – 12 °C'),
        ('Muškat Luna Blanca', 'deserti od žutog voća, kolači, umjesto deserta', '8 °C'),
        ('Stella Brut', 'aperitiv, hladna predjela, sirevi, salate', '8 °C'),
    ]) + savjet('Muškat Luna Blanca u boci od 0,50 l lijep je mali poklon – dovoljno za četvero uz desert.'),

    'vinarija-petrac': '''<h2>Zagorje – brežuljci, klijeti i hladnije noći</h2>
<p>Hrvatsko zagorje poznato je po bijelim vinima, ali Petrač dokazuje da se na južnim padinama zagorskih brežuljaka mogu raditi i ozbiljna crvena vina. Vinogorje Zabok ima tople dane i svježe noći, pa grožđe sporo dozrijeva i zadržava kiseline – to Karizmi daje svježinu unatoč punoći.</p>
<p>Karizma je mješavina u bordoškom stilu: cabernet sauvignon daje strukturu i tanine, a merlot mekoću i voće. Takvo vino voli dekantiranje – otvorite bocu sat vremena prije večere i nagradit će vas.</p>
''' + stol([
        ('Karizma', 'pašticada, divljač, zreli meki sirevi', '18 °C'),
        ('Graševina', 'bijelo meso, riba, zagorski štrukli', '12 – 14 °C'),
    ]) + savjet('Karizmu poslužite u velikoj čaši za crno vino, a Graševinu dobro ohlađenu kao uvod u ručak.'),

    'vinarija-trdenic': '''<h2>Moslavina – kraj škrleta</h2>
<p>Moslavina se smjestila između Zagreba i Slavonije, na obroncima Moslavačke gore. To je jedini kraj na svijetu u kojem se škrlet uzgaja u većoj mjeri – sorta je ondje stoljećima, a lokalno se zove i ovnek žuti. Škrlet daje lagana, svježa vina niže alkoholne jakosti, savršena za svakodnevno uživanje.</p>
<p>Trdenić radi škrlet u nekoliko stilova, a <strong>Private Collection</strong> je njihova birana linija – vina od najboljeg grožđa, s više punoće i mineralnosti.</p>
''' + stol([
        ('Škrlet Private Collection', 'bijela riba, carpaccio, rižoto, sushi', '8 – 10 °C'),
        ('Pinot sivi Private Collection', 'tjestenina, rižoto, pečeno svijetlo meso', '8 – 10 °C'),
    ]) + savjet('škrlet je odličan „prvi susret” s moslavačkim vinima – usporedite Trdenića, Mikšu i Lagenu i otkrijte koliko lica ima ista sorta.'),

    'dubrovacki-podrumi': '''<h2>Konavle – najjužniji vinski kraj Hrvatske</h2>
<p>Konavoska dolina, između Cavtata i crnogorske granice, plodno je polje okruženo brdima. Blaga klima, puno sunca i blizina mora oduvijek su pogodovali vinogradima, a uz domaći plavac mali i malvasiju dubrovačku ovdje su se odlično udomaćile i svjetske sorte – cabernet sauvignon i merlot.</p>
<p>Vina Dubrovačkih podruma su pristupačna i pouzdana: dobra za obiteljski ručak, roštilj s prijateljima ili restoransku kartu, gdje se traži stalna kvaliteta iz godine u godinu.</p>
''' + stol([
        ('Cabernet Sauvignon', 'biftek, meso s laganim začinima, pašticada', '16 – 18 °C'),
        ('Merlot', 'jela s roštilja, mirodijska jela, tjestenine s mesom', '16 – 18 °C'),
    ]) + savjet('crno vino ljeti malo rashladite – 16 °C je bolje od sobne temperature od 26 °C.'),

    'vinarija-kurtalj': '''<h2>Plešivica – zagrebačko vinsko gorje</h2>
<p>Plešivica, iznad Jastrebarskog i Samobora, nadomak je Zagrebu i jedno od najpoznatijih hrvatskih područja za <strong>pjenušce</strong>. Hladnije noći i vapnenačko tlo daju grožđu svježe kiseline, a upravo su kiseline temelj dobrog pjenušca. Plešivička vinska cesta s klijetima i kušaonicama omiljeni je izlet Zagrepčana.</p>
<p>Kurtalj pjenušce radi <strong>klasičnom metodom</strong> – drugo vrenje odvija se u boci, kao kod šampanjca, a vino zatim mjesecima odležava na kvascima. Otud fini mjehurići i note korice kruha.</p>
''' + stol([
        ('DRI pjenušac', 'aperitiv, guščja pašteta, narezak, mesna jela', '10 °C'),
        ('Rosé pjenušac', 'slastice od malina i jagoda, sladoled, rudarska greblica', '10 °C'),
    ]) + savjet('pjenušac otvarajte polako, držeći čep i okrećući bocu – tiho „šššš” umjesto pucnja čuva mjehuriće.'),

    'vinarija-miksa': '''<h2>Vinogorje Voloder-Ivanić Grad</h2>
<p>Voloder je srce moslavačkog vinogradarstva: brežuljci na obroncima Moslavačke gore, s klijetima i obiteljskim vinogradima koji se nasljeđuju generacijama. Škrlet je postao zaštitni znak kraja.</p>
<p>Mikšin škrlet tipičan je primjer sorte: lagan, živahan i vrlo pitak, s niskim alkoholom. Vino je to za dugi ručak, ljetnu večer ili čašu nakon posla.</p>
''' + stol([
        ('Škrlet', 'riba i morski specijaliteti, bijelo meso, hladne salate s tjesteninom, krem juhe', '10 – 12 °C'),
    ]) + savjet('škrlet je odličan izbor kad ne znate tko će što jesti – slaže se gotovo sa svime što nije teško i ljuto.'),

    'vinarija-antunovic': '''<h2>Erdut – vinogradi iznad Dunava</h2>
<p>Erdutsko vinogorje leži na lesnim obroncima uz Dunav, na krajnjem istoku Hrvatske. Les zadržava vlagu, a rijeka ublažava ljetne vrućine, pa ovdje rastu puna, aromatična bijela vina – graševina, chardonnay i traminac.</p>
<p><strong>Sur lie</strong> znači da je vino nakon vrenja odležalo na finom talogu kvasaca. Takvo vino dobiva kremastu teksturu, punoću i duže trajanje – pa ga se ne treba bojati čuvati i nekoliko godina.</p>
''' + stol([
        ('Chardonnay Premium sur lie', 'riba na žaru, bijelo meso, kremasti rižoto, fiš paprikaš', '11 – 13 °C'),
    ]) + savjet('ne poslužujte ga preledenog – na 11 – 13 °C otvaraju se voće i lagana nota hrasta.'),

    'opg-cukovic-kupinovo-vino': '''<h2>Što je kupinovo vino?</h2>
<p>Kupinovo vino je <strong>voćno vino</strong>: nastaje vrenjem soka zrelih kupina, slično kao vino od grožđa. Ima tamnu, gotovo crnu boju, puni voćni miris i ugodnu slatkoću, pa se tradicionalno pije kao desertno vino ili aperitiv. U kontinentalnoj Hrvatskoj dugo se radilo kod kuće, a danas ga uz domaće recepture rade i obiteljska gospodarstva poput Čukovića.</p>
<p>Obronci Moslavačke gore, s puno sunca i svježim noćima, idealni su za kupine – plodovi su slatki, ali zadržavaju svježinu.</p>
''' + stol([
        ('Kupinovo vino Čuk', 'fini sirevi, tamno meso i divljač, slastice od čokolade i šumskog voća, aperitiv', '10 – 12 °C'),
    ]) + savjet('probajte ga uz plavi sir ili čokoladni kolač – kombinacija koju gosti pamte.'),

    'patrik-rakije-likeri-gin': '''<h2>Plešivica i Jastrebarsko – kraj rakije i dobrog društva</h2>
<p>Bregoviti kraj oko Jastrebarskog poznat je po voćnjacima i vinogradima, a rakija se ovdje peče generacijama. Patrik nastavlja tu tradiciju, ali s modernim dizajnom boca i proizvodima kao što je gin – ime <strong>Hawk’s Gin</strong> inspirirano je imenom grada Jastrebarskog.</p>
<h2>Za poklon</h2>
<p>Patrik je jedan od naših najtraženijih proizvođača za poklone: viljamovka sa zlatnim listićima u limitiranom pakiranju, male boce od 0,20 l za kušanje i <strong>mix paketi</strong> od četiri bočice (travarica, viljamovka, šljivovica, komovica ili medovača, višnja, rogač i borovnica) – idealni kad ne znate što netko voli.</p>
''' + stol_pica([
        ('Viljamovka, šljivovica, loza', 'digestiv nakon ručka, u dobrom društvu', 'dobro ohlađeno, 4 – 5 °C'),
        ('Travarica', 'aperitiv uz pršut, sir i masline', 'dobro ohlađeno'),
        ('Limoncello, borovnica', 'uz slastice, u koktelima, ljetno osvježenje', 'dobro ohlađeno'),
        ('Hawk’s Gin i Navy Strength', 'gin tonic, kokteli, aperitiv', '4 – 6 °C'),
    ]) + savjet('Navy Strength ima 59 % vol. – u gin tonicu stavite malo manje gina i više leda, okus će i dalje biti snažan.'),

    'promex-domaci-likeri': '''<h2>Grobnišćina – primorska tradicija likera</h2>
<p>Čavle i Grobničko polje, u zaleđu Rijeke, kraj su u kojem se kuhinja Primorja susreće s Gorskim kotarom. Otuda i Promexova paleta: <strong>pelin</strong> i <strong>medica</strong> kao primorska klasika, a <strong>borovnica</strong> i <strong>brusnica</strong> kao okusi šuma iznad Rijeke.</p>
<p>Pelin je kroz povijest bio i „narodni lijek” – gorki biljni liker koji se pije prije jela za apetit ili nakon jela za probavu. Medica je, s druge strane, slatka i nježna, omiljena uz kavu i kolače.</p>
''' + stol_pica([
        ('Pelin', 'aperitiv ili digestiv, koktel s pelinom', 'rashlađeno'),
        ('Medica', 'aperitiv, uz slastice i kavu', 'po želji'),
        ('Borovnica', 'aperitiv, uz deserte', 'ohlađeno'),
        ('Brusnica', 'uz jela od divljači, aperitiv', 'u rashlađenoj čaši'),
    ]) + savjet('boce od 1 l praktične su za obiteljska slavlja i ugostitelje – jedna boca dovoljna je za tridesetak čašica.'),

    'gavran-ravens-23-gin': '''<h2>Što je London Dry Gin?</h2>
<p><strong>London Dry</strong> nije oznaka podrijetla, nego stila: gin kojem se svi biljni sastojci dodaju tijekom destilacije, bez naknadnog dodavanja šećera i umjetnih aroma. Rezultat je suh, čist i aromatičan gin – temelj klasičnog gin tonica i koktela kao što su Martini i Negroni.</p>
<p>Raven’s 23 uz obaveznu borovicu donosi spoj domaćih biljaka i egzotičnih začina, pa je zanimljiv i onima koji gin piju čist, s kockom leda.</p>
''' + stol_pica([
        ('Gin tonic', '1 dio gina, 3 dijela tonika, puno leda, kora limuna ili naranče', '4 – 6 °C'),
        ('Negroni', 'gin, crveni vermut i bitter u jednakim omjerima, kora naranče', 'na ledu'),
        ('Čist', 'u maloj čaši s jednom velikom kockom leda', '4 – 6 °C'),
    ]) + savjet('za gin tonic koristite što više leda – led se sporije topi i piće ne postane vodenasto.'),

    'good-spirit-likeri-medimurje': '''<h2>Žgajarenje – međimurska tradicija</h2>
<p>U gornjem Međimurju, među vinogradima i klijetima, pečenje rakije zove se <strong>žgajarenje</strong>, a stari kotlovi za rakiju dio su gotovo svakog gospodarstva. Good Spirit iz te tradicije uzima vinski destilat kao bazu i oplemenjuje ga prirodnim sastojcima – bez boja, aroma i kemijskih dodataka.</p>
<p><strong>Kova</strong> je liker za ljubitelje kave: 100 % arabica daje mu pun, glatki okus bez gorčine. Pije se ohlađen, uz desert, ali i u kavi ili preko sladoleda od vanilije.</p>
''' + stol_pica([
        ('Kova', 'aperitiv, uz slastice, preko sladoleda (affogato), u kavi', 'po želji, najbolje ohlađen'),
    ]) + savjet('Kova i kuglica sladoleda od vanilije – desert za dvije minute.'),

    'gothic-vodka-golden-steam': '''<h2>Kakva je dobra votka?</h2>
<p>Dobra votka prepoznaje se po čistoći: bez oštrine u nosu, glatka na nepcu i s čistim završetkom. Upravo je to cilj Golden Steama – votka od prirodnih sastojaka, pristupačne cijene, koja se može piti čista ili biti dobra baza za koktele.</p>
<p>Gothic Vodka puni se u prepoznatljive boce s gotičkim motivom, pa je i zanimljiv poklon za ljubitelje žestokih pića.</p>
''' + stol_pica([
        ('Čista', 'digestiv, u maloj čaši', 'dobro ohlađeno, iz zamrzivača'),
        ('Kokteli', 'Moscow Mule, Bloody Mary, Espresso Martini', 'na ledu'),
    ]) + savjet('votku čuvajte u zamrzivaču – neće se smrznuti, a postat će gušća i glađa.'),

    'misna-vina-dakovo-vinovica': '''<h2>Đakovačko vinogorje</h2>
<p>Đakovo je poznato po katedrali, lipicancima i <strong>vinskim podrumima</strong> koji stoljećima opskrbljuju vino za bogoslužje. Misno vino mora biti prirodno, od čistog grožđa, bez dodataka – pa su ta vina po definiciji jednostavna, čista i iskrena. Vinogradi na blagim slavonskim brežuljcima daju bijela vina punog okusa i crna vina mekih tanina.</p>
<p><strong>Vinovica</strong> je rakija dobivena destilacijom vina, a ne komine – zato je glatka i elegantna, s blagom voćnom aromom. Odličan je digestiv i lijep poklon u posebnom pakiranju.</p>
''' + stol([
        ('Traminac', 'začinjena jela, azijska kuhinja, sirevi, deserti', '10 – 12 °C'),
        ('Chardonnay', 'riba, bijelo meso, kremasti umaci', '10 – 12 °C'),
        ('Cabernet sauvignon-franc', 'pečeno meso, kulen, slavonska jela', '16 – 18 °C'),
        ('Vinovica', 'digestiv, poklon', 'dobro ohlađeno'),
    ]),

    'pivovara-slavonica-pakrac': '''<h2>Pakrac i Janković</h2>
<p>Pakrac, gradić u zapadnoj Slavoniji, dugo je bio posjed grofova Janković, koji su ostavili traga u izgradnji i razvoju grada – otud i imena piva <strong>Grof</strong> i <strong>Grofica</strong>. Slavonica ih je pretočila u craft piva koja spajaju lokalnu priču i moderne pivske stilove.</p>
<h2>Stilovi piva u poklon paketima</h2>
<ul>
<li><strong>Amber ale</strong> – jantarne boje, slatkasta karamelna nota i blaga gorčina.</li>
<li><strong>American pale ale</strong> – osvježavajući, s mirisom citrusa i tropskog voća od američkog hmelja.</li>
<li><strong>India pale ale (IPA)</strong> – izraženije gorčine i jače aromatičan.</li>
<li><strong>Stout</strong> – tamno pivo s notama kave i čokolade.</li>
<li><strong>NEIPA</strong> – mutna, sočna, voćna IPA s blagom gorčinom.</li>
</ul>
''' + stol_pica([
        ('Lager, pale ale', 'roštilj, burgeri, ljetno osvježenje', '6 – 8 °C'),
        ('Amber ale, IPA', 'pečeno meso, začinjena jela, sirevi', '8 – 10 °C'),
        ('Stout', 'čokoladni deserti, dimljeno meso', '10 – 12 °C'),
    ]) + savjet('craft pivo točite u čašu, a ne pijte iz boce – tako se otvore mirisi hmelja i slada.'),
}

# Postojeće objave: kratki „Za stolom” dodatak (tekst objave ostaje)
DODACI_POSTOJECE = {
    2710: stol([
        ('Malvazija istarska', 'tjestenine i rižoti s plodovima mora, riblji specijaliteti', '10 – 12 °C'),
        ('Malvazija sur lie', 'jakobove kapice, škampi, tartufi', '12 – 14 °C'),
        ('Chardonnay sur lie', 'škampi, jastog, vrganji, teletina – i crveno meso', '12 °C'),
        ('Pinot bijeli', 'polpete od hobotnice, gregada, rižoto s vrganjima', '10 – 12 °C'),
        ('Grande Cuvée', 'crveno meso sa žara, divljač, tvrdi zreli sirevi', '16 – 18 °C'),
        ('Muškat bijeli', 'slatki deserti', '12 °C'),
    ]),
    2525: stol([
        ('Malvazija istarska', 'šparoge, istarske fuži s tartufima, školjke, riba', '10 – 12 °C'),
        ('Cabernet Franc', 'crveno meso, pačja prsa, gulaši, žgvacet', '16 – 18 °C'),
    ]),
    2012: stol([
        ('Škrlet', 'ljetne salate, škripavac s kruškama, riba, bijelo meso', '8 – 10 °C'),
        ('Škrlet Iconic', 'škripavac s kruškama, kremaste juhe, plodovi mora', '8 – 10 °C'),
        ('Rosé Iconic', 'slatko-slane kombinacije, pršut, carpaccio, rižoto', '8 – 10 °C'),
        ('Cabernet Sauvignon', 'plavi sirevi, pečeni odojak, slastice od šumskog voća', '14 – 16 °C'),
        ('Coral Wine', 'riblja jela, bijelo meso, pikantna jela', '8 – 10 °C'),
    ]),
    1183: stol([
        ('Pošip', 'školjke, rakovi, pikantniji sirevi, bijelo meso', '12 °C'),
        ('Grk', 'riba, plodovi mora, piletina, hladna predjela', '12 – 14 °C'),
        ('Rosé', 'salate, tjestenine, pršut, carpaccio', '12 °C'),
        ('Plavac mali', 'brudet, pašticada, divljač', '18 °C'),
        ('Grand Cru', 'crveno meso, divljač, tamna čokolada, plava riba', '19 °C'),
        ('Varenik', 'u brudet, pašticadu, rižote, preko sladoleda', '–'),
    ]),
}
