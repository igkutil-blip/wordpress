# Skupni paket s više proizvoda (npr. „Vina – serija 1”): jedna mapa po proizvodu, alt.txt u obliku
#   <datoteka>\nALT: <tekst>   +   „IZVOR …” s adresom /proizvod/<slug>/   +   „… preporuka posluživanja: <temperatura>; <jelo>”
# Svaka mapa → zaseban paket za domestic-slike (datoteka = alt, STAVKA retci) → analiza → uvoz u proizvod iz adrese (slug).
# Podmape (izvorne-fotografije) i datoteke izvan mapa se ne šalju; napomene koje spominju alat/izvor obrade se ne prenose.
#   python3 -I alati/uvoz-slika/serija.py serija.zip [--case case.json] [--samo-analiza] [--ziva]
#   case.json = {"<mapa>": "Čaša za bijelo vino.", …}  (redak „Izbor čaše” s kartice)
#               ili {"<mapa>": ["naziv | količina | opis | ikona", …]} = sve stavke za posebnu karticu (npr. Varenik „Za kuhanje”)
#   Kartica = slika s „posluzivanje” ili „uporaba” u nazivu; ostale nove slike iz mape idu u galeriju (podmape ne).
#   --slug mapa=slug  (kad u alt.txt nema adrese proizvoda; može više puta)
import os, re, sys, io, json, uuid, base64, time, zipfile, urllib.request, urllib.error, urllib.parse

ziva = '--ziva' in sys.argv
P = 'DS_' if ziva else 'DS_PROBA_'
BASE = os.environ[P + 'URL'].rstrip('/') + '/wp-json/'
AUTH = 'Basic ' + base64.b64encode(f"{os.environ[P + 'WP_USER']}:{os.environ[P + 'WP_APP_PASSWORD']}".encode()).decode()
ALAT = re.compile(r'imagegen|chatgpt|openai|dall|midjourney|firefly|gemini|stable diffusion|\bai\b|umjetn|alat|prompt', re.I)
case = {}
if '--case' in sys.argv:
    case = json.load(open(sys.argv[sys.argv.index('--case') + 1], encoding='utf-8'))


def req(u, data=None, ct='application/json', method=None):
    h = {'Authorization': AUTH}
    if data is not None:
        h['Content-Type'] = ct
    for i in range(5):
        try:
            return json.load(urllib.request.urlopen(urllib.request.Request(BASE + u, data, h, method=method or ('POST' if data is not None else 'GET')), timeout=300))
        except urllib.error.HTTPError as e:
            raise RuntimeError(f'{e.code} {e.read()[:300].decode(errors="replace")}')
        except Exception as e:
            if i == 4:
                raise RuntimeError(f'mreža: {e}')
            time.sleep(3 * (i + 1))


def citaj_alt(txt):
    alt, napomene, slug, zadnja = {}, [], '', None
    redovi = [r.strip() for r in txt.splitlines()]
    for i, r in enumerate(redovi):
        if re.search(r'\.(jpe?g|png)$', r, re.I):
            zadnja = r
        elif r.upper().startswith('ALT:') and zadnja:
            alt[zadnja] = r[4:].strip()
            zadnja = None
        elif not slug and re.search(r'domesticspirits\.hr/proizvod/', r):
            slug = re.search(r'/proizvod/([^/\s]+)/?', r).group(1)  # prva adresa našeg shopa (ne proizvođačeva)
        elif re.match(r'^(Kartica|Napomena)\s*:', r, re.I) and not ALAT.search(r):
            napomene.append(r)
    return alt, napomene, slug


rucno = dict(sys.argv[i + 1].split('=', 1) for i, a in enumerate(sys.argv) if a == '--slug')
z = zipfile.ZipFile(sys.argv[1])
mape = sorted({n.split('/')[0] for n in z.namelist() if '/' in n})
ok = 0
for mapa in mape:
    try:
        alt, napomene, slug = citaj_alt(z.read(mapa + '/alt.txt').decode('utf-8'))
        slug = rucno.get(mapa, slug)
        pr = req('wc/v3/products?status=any&slug=' + urllib.parse.quote(slug)) if slug else []
        if not pr:
            print(f'STOP     {mapa}: proizvod nije pronađen (slug „{slug}”)')
            continue
        p = pr[0]
        slike = [n for n in z.namelist() if n.startswith(mapa + '/') and n.count('/') == 1 and re.search(r'\.(jpe?g|png)$', n, re.I)]
        posl = next((n for n in slike if re.search(r'posluzivanje|uporaba', n, re.I)), None)
        stavke = []
        m = re.search(r'preporuka posluživanja:\s*([^;]+);\s*(.+)$', alt.get(os.path.basename(posl or ''), ''), re.I)
        if m:
            temp, jelo = m.group(1).strip(), m.group(2).strip()
            stavke.append(f'STAVKA = Temperatura serviranja | {temp} | | casa')
            stavke.append(f'STAVKA = Poslužite uz | | {jelo} | tanjur')
        if isinstance(case.get(mapa), list):
            stavke = [f'STAVKA = {s}' for s in case[mapa]]
        elif case.get(mapa):
            stavke.append(f"STAVKA = Izbor čaše | | {case[mapa]} | casa")
        b = io.BytesIO()
        with zipfile.ZipFile(b, 'w', zipfile.ZIP_DEFLATED) as nz:
            ime = re.sub(r'[\\/:*?"<>|]', '', p['name'])
            ime_d = lambda n: re.sub(r'uporaba', 'posluzivanje', os.path.basename(n), flags=re.I)  # dodatak prepoznaje karticu po „posluzivanje”
            for n in slike:
                nz.writestr(ime + '/' + ime_d(n), z.read(n))
            red = [f'{ime_d(n)} = {alt.get(os.path.basename(n), "")}' for n in slike] + stavke + [f'NAPOMENA-{i} = {t}' for i, t in enumerate(napomene)]
            nz.writestr(ime + '/alt.txt', '\n'.join(red) + '\n')
        g = uuid.uuid4().hex
        tijelo = f'--{g}\r\nContent-Disposition: form-data; name="zip"; filename="{mapa}.zip"\r\nContent-Type: application/zip\r\n\r\n'.encode() + b.getvalue() + f'\r\n--{g}--\r\n'.encode()
        a = req('domestic-slike/v1/paket', tijelo, 'multipart/form-data; boundary=' + g)
        stop = [t for v, t in a.get('upozorenja', []) if v == 'stop' and ('Glavna slika' in t or 'ambijentalne' in t or 'teksta' in t)]
        if stop or '--samo-analiza' in sys.argv:
            req('domestic-slike/v1/paket/' + a['token'], method='DELETE')
            print(('STOP     ' if stop else 'ANALIZA  ') + f"{p['name']} ({p['id']}, {p['status']}) · stavke {len(a['stavke'])}" + ''.join(f'\n         ! {t}' for t in stop))
            continue
        r = req('domestic-slike/v1/paket/' + a['token'], json.dumps({'proizvod': p['id'], 'dodatne': True, 'bez_ilustracija': True}).encode())
        ok += 1
        print(f"OK       {p['name']} ({p['id']}{', skriven' if p['status'] == 'private' else ''}) · glavna {r['glavna']} · nove {[x for x in (r.get('studio'), r.get('posl')) if x]} · stavke {len(r.get('posluzivanje', []))}", flush=True)
    except (RuntimeError, KeyError) as e:
        print(f'GREŠKA   {mapa}: {e}')
print(f'— uvezeno {ok} od {len(mape)}')
