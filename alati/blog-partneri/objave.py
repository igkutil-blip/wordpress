# Objave o partnerima Domestic Spiritsa (vinarije i domaći proizvođači) – izvor: opisi proizvoda u shopu + javni izvori
VIN_KONT, VIN_DALM, VIN_SLAV, VIN_ISTRA, BLOG, RAKIJE = 207, 208, 209, 304, 228, 305


def proizvodi(ids):
    return '\n<h2>U našoj ponudi</h2>\n[products ids="' + ','.join(str(i) for i in ids) + '" orderby="post__in" columns="4"]\n'


OBJAVE = [
    {
        'slug': 'vinarija-pavlomir', 'naslov': 'Pavlomir – žlahtina s obronaka Vinodola',
        'kat': [VIN_ISTRA], 'slika': 4663, 'ids': [4662, 4660, 4655, 4709],
        'sazetak': 'Vinska kuća Pavlomir iz Novog Vinodolskog i njezina žlahtina, ponos Hrvatskog primorja.',
        'tekst': '''<p>Iznad Novog Vinodolskog, na sunčanim obroncima Vinodolske doline, nalazi se vinska kuća <strong>Pavlomir</strong>. Imanje se prostire na oko 40 hektara, od čega je 31 hektar pod vinogradima, a vodi ga obitelj Palinkaš.</p>
<h2>Žlahtina – ponos imanja</h2>
<p>Najvažnija sorta Pavlomira je <strong>žlahtina</strong>, autohtona bijela sorta hrvatskog primorja. Svježa je, lagana i pitka, s mirisima jabuke, vinogradarske breskve i mediteranskog bilja te ugodnim slankastim završetkom koji podsjeća na blizinu mora. Bijela vina čine najveći dio proizvodnje, a žlahtina je ujedno i najtraženije vino kuće.</p>
<blockquote>Žlahtina Grand Cru osvojila je zlatne medalje na ocjenjivanjima Sabatina (Hrvatska) i Concours Mondial de Bruxelles.</blockquote>
<h2>Vina koja izdvajamo</h2>
<ul>
<li><strong>Žlahtina Grand Cru</strong> – bogatija, s notama citrusa, breskve i cvijeta akacije te dugim završetkom s notom badema.</li>
<li><strong>Žlahtina</strong> – lagana i svježa, idealna uz plodove mora, salate i piletinu na mediteranski način.</li>
<li><strong>Graševina Palinkaš</strong> – puna i sočna, s medenim notama, dinjom i zrelom breskvom.</li>
<li><strong>Rosé</strong> – živ i voćan, s mirisima grejpa, naranče, maline i jagode.</li>
</ul>''',
    },
    {
        'slug': 'vinarija-frankovic', 'naslov': 'Franković – vina s položaja Santa Lucia kod Buja',
        'kat': [VIN_ISTRA], 'slika': 4410, 'ids': [779, 3075, 2808],
        'sazetak': 'Obiteljsko gospodarstvo Franković iz Buja: malvazija, momjanski muškat i pjenušac Stella.',
        'tekst': '''<p>Obiteljsko poljoprivredno gospodarstvo <strong>Josipa Frankovića</strong> nalazi se u sjeverozapadnoj Istri, kod Buja. Vinogradi su na položaju <strong>Santa Lucia</strong>, u kraju u kojem se vinova loza uzgaja stoljećima, a na njemu rađaju malvazija i muškat koji Istru čine prepoznatljivom.</p>
<h2>Malvazija s nagradama</h2>
<p>Franković malvazija karakteristične je zlatnožute boje, s mirisima narančine kore, breskve i ananasa te klasičnom notom bagremova cvijeta. Svježa je i vrlo ukusna – idealna uz ribu, plodove mora i tjesteninu.</p>
<blockquote>Malvazija Franković osvojila je zlato na Vinistri u Poreču 2017. i proglašena je ATP vinom 2017. u Umagu.</blockquote>
<h2>Muškat i pjenušac</h2>
<ul>
<li><strong>Muškat momjanski Luna Blanca</strong> – desertno vino blijedo žute boje, s aromama grožđa, limete i zrelog žutog voća. Savršeno uz deserte, a može ga i zamijeniti.</li>
<li><strong>Stella</strong> – pjenušavo bijelo vino živog, svježeg karaktera i skladnog perlanja, za aperitiv, hladna predjela i sireve.</li>
</ul>
<p>Vinarija je osvajala priznanja i na međunarodnoj razini, među ostalim i na natjecanju Decanter World Wine Awards.</p>''',
    },
    {
        'slug': 'vinarija-petrac', 'naslov': 'Petrač – Bordeaux iz Hrvatskog zagorja',
        'kat': [VIN_KONT], 'slika': 4109, 'ids': [1543, 3079],
        'sazetak': 'Vinarija Petrač iz Hrvatskog zagorja i njezina Karizma, mješavina cabernet sauvignona i merlota.',
        'tekst': '''<p>Vinarija <strong>Petrač</strong> dolazi iz Hrvatskog zagorja, iz podregije Zagorje-Međimurje. Njezin položaj Hršak Breg cjeloviti je vinograd od desetak hektara, na brežuljcima koji vinima daju svježinu i eleganciju.</p>
<h2>Karizma – vodeće vino kuće</h2>
<p><strong>Karizma</strong> je vrhunsko crveno vino u stilu Bordeauxa: <strong>70 % cabernet sauvignona i 30 % merlota</strong>. Duga maceracija cabernet sauvignona daje joj čvrstu taninsku strukturu i punoću, a kraće macerirani merlot voćnost. U mirisu dominiraju crni ribiz, borovnica i džem od šljiva.</p>
<blockquote>Karizma 2016. ocijenjena je s 90 bodova u vodiču Falstaff.</blockquote>
<p>Uz pašticadu, divljač i zrele meke sireve Karizma pokazuje svoje najbolje lice.</p>
<h2>Graševina</h2>
<p><strong>Petrač Graševina</strong> nježne je slamnatožute boje, s voćnim aromama i notama livadnog cvijeća. Fermentira na niskim temperaturama u inoks posudama, pa je svježa, elegantna i jednostavna – lijepa uz bijelo meso i ribu.</p>''',
    },
    {
        'slug': 'vinarija-trdenic', 'naslov': 'Trdenić – škrlet iz srca Moslavine',
        'kat': [VIN_KONT], 'slika': 2236, 'ids': [1583, 1572],
        'sazetak': 'Obiteljska vinarija Trdenić iz Moslavine i rijetka autohtona sorta škrlet.',
        'tekst': '''<p>Obiteljska vinarija <strong>Trdenić</strong> nalazi se u Moslavini, u selu Trnovka kod Volodera. Vode je braća Tomislav i Krešimir, a ubraja se među najveće vinarije u Moslavini.</p>
<h2>Škrlet – rijetka moslavačka sorta</h2>
<p>Ponos vinarije je <strong>škrlet</strong>, rijetka autohtona sorta koja se u Hrvatskoj uzgaja na manje od 100 hektara vinograda. Trdenić ga radi u nekoliko stilova, a <strong>Škrlet Private Collection</strong> u mirisu otkriva zelenu lubenicu, bijelo cvijeće, bijele jagode, breskvu i ananas. Na okus je svilenkast, srednjih kiselina i izražene mineralnosti.</p>
<p>Uz bijelu ribu, carpaccio, plodove mora, rižoto i sushi škrlet je odličan izbor.</p>
<h2>Pinot sivi Private Collection</h2>
<p>Sortno prepoznatljiv pinot sivi, nježnih cvjetnih i voćnih mirisa jabuke i breskve, odličan uz tjesteninu, rižote i pečeno svijetlo meso.</p>
<blockquote>Pinot sivi Private Collection 2014. osvojio je srebrnu medalju na nacionalnom ocjenjivanju vina „Vina Croatia”.</blockquote>''',
    },
    {
        'slug': 'dubrovacki-podrumi', 'naslov': 'Dubrovački podrumi – vina Konavala od 19. stoljeća',
        'kat': [VIN_DALM], 'slika': 4078, 'ids': [1507, 1092],
        'sazetak': 'Dubrovački podrumi iz Grude u Konavlima – vinska tradicija koja traje od 19. stoljeća.',
        'tekst': '''<p><strong>Dubrovački podrumi</strong> vodeći su proizvođač vina u Konavlima, na samom jugu Hrvatske. Njihova povijest počinje u drugoj polovici 19. stoljeća, a vinarija u Grudi izgrađena je 1963. godine.</p>
<h2>Obnovljeni vinogradi Konavala</h2>
<p>Devedesetih godina vinarija je teško stradala u ratu. Nakon toga obnovljeni su zapušteni i uništeni vinogradi, a danas Dubrovački podrumi imaju oko 35 hektara vlastitih vinograda te surađuju s brojnim konavoskim vinogradarima. Vinarija je opremljena modernom opremom za stabilizaciju, filtraciju i punjenje.</p>
<h2>Crna vina iz Konavoskog vinogorja</h2>
<ul>
<li><strong>Cabernet Sauvignon</strong> – intenzivne tamnocrvene boje, izraženih sortnih karakteristika i bogatog mirisa. Odličan uz meso s laganim začinima, biftek, pa i dalmatinsku pašticadu.</li>
<li><strong>Merlot</strong> – sorta koja se odlično udomaćila u konavoskom vinogorju. Zrelo crvene boje, mirisa zrelih višanja i vanilije, pun i sočan – uz jela s roštilja.</li>
</ul>
<p>Preporučena temperatura serviranja oba vina je 16 – 18 °C.</p>''',
    },
    {
        'slug': 'vinarija-kurtalj', 'naslov': 'Kurtalj – pjenušci s Plešivice',
        'kat': [VIN_KONT], 'slika': 1059, 'ids': [1058, 1075],
        'sazetak': 'Obiteljsko gospodarstvo Kurtalj s Plešivice, poznato po pjenušcima proizvedenim klasičnom metodom.',
        'tekst': '''<p>Na Plešivici, kod Jastrebarskog, obitelj <strong>Kurtalj</strong> živi uz vino više od tri stoljeća. Drago Kurtalj vinom se bavi cijeli život, a gospodarstvo je danas poznato prije svega po <strong>pjenušcima</strong> proizvedenim klasičnom metodom.</p>
<h2>Sorte Plešivice</h2>
<p>Vinogradi su najviše posađeni zelenim silvancem, ali i graševinom, rajnskim rizlingom i sivim pinotom, a od crnih sorti crnim pinotom i portugiscem – sortama koje na Plešivici daju najbolje rezultate. Za pjenušce Kurtalj koristi chardonnay i müller-thurgau za bijele te portugizac za rosé.</p>
<h2>DRI i Rosé</h2>
<ul>
<li><strong>DRI pjenušac</strong> – nazvan po djeci Doni, Roku i Ivanu. Zlatnožut, s aromama marelice, citrusa i korice kruha. Svjež i harmoničan – za aperitiv, uz paštetu i narezak.</li>
<li><strong>Rosé pjenušac</strong> – od portugisca, svjež i voćan, idealan za slavlja.</li>
</ul>
<blockquote>Kurtalj pjenušac proglašen je najboljim pjenušcem izbora „Sommelier Hrvatska” 2016.</blockquote>''',
    },
    {
        'slug': 'vinarija-miksa', 'naslov': 'Mikša – škrlet od 1936.',
        'kat': [VIN_KONT], 'slika': 3344, 'ids': [3343],
        'sazetak': 'Obitelj Mikša iz Moslavine prvi je vinograd škrleta posadila 1936. godine.',
        'tekst': '''<p>Vinogradarstvo u obitelji <strong>Mikša</strong> seže u 1936. godinu, kada je posađen njihov prvi vinograd – i to upravo <strong>škrleta</strong>, autohtone moslavačke sorte. Vinarija se nalazi u Moslavini, u vinogorju Voloder-Ivanić Grad.</p>
<h2>Škrlet</h2>
<p>Mikšin škrlet ima nježne voćne arome s notama jabuke te travnate i herbalne tonove. Okus je nježan i svjež, pa se lijepo slaže s ribom i plodovima mora, jelima od bijelog mesa, hladnim salatama s tjesteninom te gustim i krem juhama.</p>
<blockquote>Najveće priznanje vinarije je srebrna diploma prestižnog natjecanja IWC u Londonu, 2013. i 2014. godine.</blockquote>
<p>Mikša je jedan od moslavačkih proizvođača koji su udružili snage kako bi škrlet postao prepoznatljiv simbol svog kraja.</p>''',
    },
    {
        'slug': 'vinarija-antunovic', 'naslov': 'Antunović – chardonnay iz Erdutskog vinogorja',
        'kat': [VIN_SLAV], 'slika': 1593, 'ids': [1594],
        'sazetak': 'Obiteljska vinarija Antunović iz Dalja, u vinogorju Erdut, i njezin Chardonnay Premium sur lie.',
        'tekst': '''<p>Obiteljska vinarija <strong>Antunović</strong> iz Dalja, koju vodi Jasna Antunović Turk, nalazi se u istočnoj Hrvatskoj, u vinogorju <strong>Erdut</strong> u Hrvatskom Podunavlju. Vinogradi uz Dunav daju puna, bogata bijela vina, koja čine najveći dio njezine ponude.</p>
<h2>Chardonnay Premium sur lie</h2>
<p>Suho, nefiltrirano vrhunsko vino izuzetne ekstraktnosti i snage. Puno je voćnog chardonnay karaktera, uz lagani dodir hrasta i tanina zahvaljujući kratkom odležavanju u bačvi. Vino je odležalo na finom talogu (sur lie), što mu daje punoću i dugovječnost.</p>
<blockquote>Limitirana serija – proizvedeno je samo 6 500 boca.</blockquote>
<p>Preporučujemo ga uz ribu na žaru i bijelo meso, na temperaturi od 11 do 13 °C.</p>''',
    },
    {
        'slug': 'opg-cukovic-kupinovo-vino', 'naslov': 'Čuković – kupinovo vino s Moslavačke gore',
        'kat': [VIN_KONT], 'slika': 4175, 'ids': [1062],
        'sazetak': 'OPG Čuković i desertno voćno vino od kupina s obronaka Moslavačke gore.',
        'tekst': '''<p>Na pitomim obroncima Moslavačke gore obitelj <strong>Čuković</strong> uzgaja kupine od kojih nastaje <strong>kupinovo vino Čuk</strong> – autentično desertno voćno vino.</p>
<h2>Kupinovo vino Čuk</h2>
<p>Slatki i sočni plodovi kupine pretočeni su u vino bogate arome i punog okusa. Odlično se slaže s finim sirevima, jelima od tamnog mesa i divljači te slasticama, a može se poslužiti i kao aromatičan aperitiv.</p>
<p>Poslužite ga na temperaturi od 10 do 12 °C. Kupinovo vino Čuk jedno je od najprodavanijih vina u našoj ponudi.</p>''',
    },
    {
        'slug': 'patrik-rakije-likeri-gin', 'naslov': 'Patrik – rakije, likeri i gin s Plešivice',
        'kat': [RAKIJE], 'slika': 3780, 'ids': [3776, 1950, 2758, 1533, 3084, 1942, 3525, 1537, 2565, 3745, 1630, 1641],
        'sazetak': 'Patrik s Plešivice: voćne rakije, viljamovka sa zlatnim listićima, likeri i gin.',
        'tekst': '''<p><strong>Patrik</strong> je obrt s Plešivice, iz bregovitog dijela zapadne kontinentalne Hrvatske, koji izrađuje voćne rakije, likere i gin. Rakije se rade od voća, na tradicionalan način, a likeri maceracijom prirodnih sastojaka u voćnoj rakiji.</p>
<h2>Viljamovka sa zlatnim listićima</h2>
<p>Najpoznatiji proizvod je <strong>viljamovka s listićima jestivog zlata</strong> – voćna rakija od kruške viljamovke harmoničnog okusa i izraženog mirisa kruške. Doslovno zlato u boci, i omiljen poklon za drage osobe i poslovne partnere.</p>
<h2>Rakije, likeri i gin</h2>
<ul>
<li><strong>Rakije</strong> – viljamovka, šljivovica, travarica i loza muškatna, idealne kao digestiv.</li>
<li><strong>Likeri</strong> – limoncello od kora citrusa maceriranih u voćnoj rakiji te liker od borovnice.</li>
<li><strong>Hawk's Gin</strong> – gin snažnog karaktera s borovicom, kardamomom, korijanderom, anđelikom, cimetom, koricom naranče i limuna te bademom. Navy Strength verzija ima 59 % vol.</li>
<li><strong>Mix pakiranja</strong> – četiri male boce rakija ili likera za kušanje i poklon.</li>
</ul>''',
    },
    {
        'slug': 'promex-domaci-likeri', 'naslov': 'Promex – domaći likeri iz Čavla',
        'kat': [RAKIJE], 'slika': 1114, 'ids': [1113, 1115, 1107, 1105],
        'sazetak': 'Promex iz Čavla: pelin, medica, borovnica i brusnica po tradicionalnim recepturama.',
        'tekst': '''<p><strong>Promex</strong> iz Čavla kod Rijeke, s područja Grobnišćine, izrađuje domaće likere po tradicionalnim recepturama obitelji Mravinac.</p>
<h2>Pelin – najstariji hrvatski liker</h2>
<p>Gorkom okusu pelina dodaju se ljekovite biljke čija je mješavina obiteljska tajna. Pelin, biljka koja se često nalazi upravo na području Grobnišćine, temelj je maceracije. Poslužite ga rashlađenog, kao aperitiv ili digestiv, a možete probati i koktel s pelinom.</p>
<h2>Medica, borovnica i brusnica</h2>
<ul>
<li><strong>Medica</strong> – tradicionalno primorsko i istarsko piće, slatko i pitko, za aperitiv i uz slastice.</li>
<li><strong>Borovnica</strong> – od plodova samonikle borovnice iz Gorskog kotara, slatka i osvježavajuća.</li>
<li><strong>Brusnica</strong> – snažnog okusa i lijepe crvene boje, odlična uz jela od divljači.</li>
</ul>''',
    },
    {
        'slug': 'gavran-ravens-23-gin', 'naslov': 'Gavran – Raven’s 23, hrvatski London Dry Gin',
        'kat': [RAKIJE], 'slika': 3406, 'ids': [3405],
        'sazetak': 'Gavran destilerija i Raven’s 23 – ručno rađeni hrvatski London Dry Gin.',
        'tekst': '''<p><strong>Raven’s 23</strong> ručno je rađeni hrvatski <strong>London Dry Gin</strong> destilerije <strong>Gavran</strong>. Nastao je kao spoj modernog i tradicionalnog, ruralnog i urbanog – inspiriran hrvatskom prirodom i orijentalnim pričama, obogaćen domaćim sastojcima i egzotičnim aromama.</p>
<h2>Kako ga poslužiti</h2>
<p>Najbolji je hladan, na 4 – 6 °C, uz tonik i začine ili u koktelima. Idealan je kao aperitiv i uvod u gozbu koji potiče apetit.</p>
<blockquote>Gavranov Raven’s London Dry Gin ocijenjen je s 91 bodom na natjecanju IWSC u Londonu.</blockquote>''',
    },
    {
        'slug': 'good-spirit-likeri-medimurje', 'naslov': 'Good Spirit – likeri iz bregovitog Međimurja',
        'kat': [RAKIJE], 'slika': 3129, 'ids': [1519],
        'sazetak': 'Good Spirit: likeri iz male međimurske manufakture, samo od prirodnih sastojaka.',
        'tekst': '''<p><strong>Good Spirit</strong> likeri proizvode se u bregovitom dijelu Međimurja, u maloj manufakturi, isključivo od prirodnih sastojaka – bez aditiva, boja i kemijskih dodataka. Priča se temelji na dugoj tradiciji proizvodnje rakije u gornjem Međimurju, poznatoj kao žgajarenje.</p>
<p>Likeri nastaju od vinskog destilata koji se oplemenjuje prirodnim sastojcima, a odlikuju se netipičnim okusima, finim mirisom, lijepom bojom i bistrinom.</p>
<h2>Kova – liker od kave</h2>
<p>Dobiva se maceracijom <strong>100 % arabica kave</strong>. Spaja vrhunsku alkoholnu bazu s glatkim okusom kave – za aperitiv ili uz slastice.</p>''',
    },
    {
        'slug': 'gothic-vodka-golden-steam', 'naslov': 'Gothic Vodka – hrvatska premium votka',
        'kat': [RAKIJE], 'slika': 3130, 'ids': [2732],
        'sazetak': 'Gothic Vodka Premium destilerije Golden Steam Craft Distillery.',
        'tekst': '''<p><strong>Gothic Vodka Premium</strong> proizvod je destilerije <strong>Golden Steam Craft Distillery</strong>, predstavljena kao prva hrvatska vrhunska premium votka po pristupačnoj cijeni.</p>
<p>Svi sastojci su isključivo prirodni, i upravo u njima leži tajna njezina specifičnog okusa i mirisa – spoj stečenog znanja i obiteljske tradicije.</p>
<h2>Kako je poslužiti</h2>
<p>Dobro ohlađenu, kao digestiv ili kao bazu za koktele. Votka ima 40 % vol. i puni se u posebne staklene boce od 0,70 l.</p>''',
    },
    {
        'slug': 'misna-vina-dakovo-vinovica', 'naslov': 'Misna vina i Vinovica iz Đakova',
        'kat': [VIN_SLAV, RAKIJE], 'slika': 3168, 'ids': [3159, 3167, 1532, 846, 818],
        'sazetak': 'Misna vina iz Đakovačkog vinogorja i Vinovica, prirodna rakija od vina.',
        'tekst': '''<p>Iz <strong>Đakovačkog vinogorja</strong> u Slavoniji dolaze <strong>misna vina</strong> – vina sa stoljetnom tradicijom, uz koja nastaje i <strong>Vinovica</strong>, prirodna rakija od vina.</p>
<h2>Vinovica</h2>
<p>Prirodna rakija proizvedena od misnih vina Đakovačkog vinogorja. Skladna je, glatka i elegantna – idealna kao digestiv, dobro ohlađena, ili kao poklon u posebnom pakiranju.</p>
<h2>Misna vina</h2>
<p>U ponudi su traminac, chardonnay i cabernet sauvignon-franc iz Đakovačkog vinogorja.</p>''',
    },
    {
        'slug': 'pivovara-slavonica-pakrac', 'naslov': 'Slavonica – craft pivo iz Pakraca',
        'kat': [RAKIJE], 'slika': 3971, 'ids': [3970, 3967, 3964],
        'sazetak': 'Mini pivovara Slavonica iz Pakraca i njezina craft piva Grof, Grofica, Carica i Templar.',
        'tekst': '''<p><strong>Slavonica</strong> je mala privatna pivovara u <strong>Pakracu</strong> koja proizvodi craft pivo. U ponudi ima piva Grof, Grofica, Carica i Templar.</p>
<h2>Grof i Grofica</h2>
<p>Prvijenac <strong>Grof</strong> ime je dobio po grofu Juliju Jankoviću (1820. – 1904.), koji je imao velik utjecaj na razvoj Pakraca. Po stilu je amber ale umjerene jačine od 5,5 % vol., blage gorčine, punog okusa i zagasite crvenkasto-jantarne boje.</p>
<h2>Poklon paketi</h2>
<p>U ponudi su poklon paketi s tri, četiri ili šest različitih piva Slavonice – od amber alea, american pale alea i india pale alea do stouta i NEIPA-e. Kod posluživanja pazite na temperaturu, način točenja i čašu.</p>''',
    },
]

POSTOJECE = {  # postojeće objave: tekst ostaje, dodaju se proizvodi i kategorija regije
    2710: {'kat': [BLOG, VIN_ISTRA], 'ids': [1538, 1550, 3513, 2652, 2654, 1544]},
    2525: {'kat': [BLOG, VIN_ISTRA], 'ids': [867, 2795]},
    2012: {'kat': [VIN_KONT], 'ids': [843, 1623, 2051, 1613, 858, 3177]},
    1183: {'kat': [VIN_DALM], 'ids': [1479, 1612, 809, 1607, 1601, 1606, 3068, 3072, 1618]},
}
