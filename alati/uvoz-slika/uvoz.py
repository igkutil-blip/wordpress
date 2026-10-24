# Uvoz paketa slika preko dodatka domestic-slike (sva obrada je u dodatku: Domestic → Slike proizvoda).
#   python3 -I alati/uvoz-slika/uvoz.py paket1.zip [paket2.zip …] [--samo-analiza] [--postavke izmjene.json] [--detalji] [--prisili] [--ziva]
# Za svaki zip: analiza → ako nema upozorenja „stop”, uvoz s prijedlozima dodatka → jedan redak izvještaja.
# Paket s upozorenjem „stop” (zajednička glavna slika, nesiguran proizvod, nema ambijentalne…) se NE uvozi – priprema se briše
# (osim uz --prisili ili --postavke, kad je odluka već donesena).
# izmjene.json (samo za jedan zip) = polja za uvoz: proizvod, cilj, ikone_izvor, posluzivanje[], zadrzi[], dodatne, napomene.
# Okolina: DS_PROBA_URL, DS_PROBA_WP_USER, DS_PROBA_WP_APP_PASSWORD (ili DS_URL/DS_WP_* za živu, uz --ziva).
import os, sys, json, base64, uuid, time, urllib.request, urllib.error

arg = [a for a in sys.argv[1:] if not a.startswith('--')]
zast = {a for a in sys.argv[1:] if a.startswith('--')}
P = 'DS_' if '--ziva' in zast else 'DS_PROBA_'
B = os.environ[P + 'URL'].rstrip('/') + '/wp-json/domestic-slike/v1/'
A = 'Basic ' + base64.b64encode(f"{os.environ[P + 'WP_USER']}:{os.environ[P + 'WP_APP_PASSWORD']}".encode()).decode()
izmjene = {}
if '--postavke' in sys.argv:
    put = sys.argv[sys.argv.index('--postavke') + 1]
    izmjene = json.load(open(put, encoding='utf-8'))
    arg.remove(put)


def req(u, data=None, ct='application/json', method=None):
    h = {'Authorization': A}
    if data is not None:
        h['Content-Type'] = ct
    for pokusaj in range(5):
        try:
            return json.load(urllib.request.urlopen(urllib.request.Request(B + u, data, h, method=method or ('POST' if data is not None else 'GET')), timeout=300))
        except urllib.error.HTTPError as e:
            raise RuntimeError(f'{e.code} {e.read()[:300].decode(errors="replace")}')
        except (urllib.error.URLError, ConnectionError, TimeoutError) as e:
            if pokusaj == 4:
                raise RuntimeError(f'mreža: {e}')
            time.sleep(3 * (pokusaj + 1))


def jedan(zip_put):
    b = uuid.uuid4().hex
    tijelo = (f'--{b}\r\nContent-Disposition: form-data; name="zip"; filename="{os.path.basename(zip_put)}"\r\nContent-Type: application/zip\r\n\r\n'.encode()
              + open(zip_put, 'rb').read() + f'\r\n--{b}--\r\n'.encode())
    a = req('paket', tijelo, 'multipart/form-data; boundary=' + b)
    k = a['kandidati'][0] if a['kandidati'] else {'id': 0, 'naziv': '?'}
    up = a.get('upozorenja', [])
    stop = [t for v, t in up if v == 'stop']
    info = [t for v, t in up if v == 'info']
    if '--detalji' in zast or '--samo-analiza' in zast:
        print('  proizvod:', [(x['id'], x['naziv'], x['bodovi'], 'glavna', x['glavna']) for x in a['kandidati']])
        print('  slike:', [s['naziv'] for s in a['slike']], 'nije učitano:', a['ostalo'])
        print('  ikone:', [i.get('ista') or 'nova' for i in a['ikone']], 'iste kao proizvod:', a['izvor_ikona'])
        for s in a['stavke']:
            print('    ', s['naziv'], '|', s['kolicina'], '|', s['opis'], '|', s['ikona'])
    if '--samo-analiza' in zast or (stop and not izmjene and '--prisili' not in zast):
        req('paket/' + a['token'], method='DELETE')
        oznaka = 'ANALIZA' if '--samo-analiza' in zast else 'STOP'
        return f"{oznaka:8} {k['naziv']} ({k['id']})" + ''.join(f'\n         ! {t}' for t in stop) + ''.join(f'\n         · {t}' for t in info)
    r = req('paket/' + a['token'], json.dumps(izmjene).encode())
    iste = sum(1 for i in a['ikone'] if i.get('ista')) if not a['izvor_ikona'] else len(r.get('posluzivanje', []))
    return (f"OK       {k['naziv']} ({r['proizvod']}) · glavna {r['glavna']} · nove {[x for x in (r.get('studio'), r.get('posl')) if x]}"
            f" · zamijenjene {r.get('zamijenjene', [])} · ilustracije {len(r.get('posluzivanje', []))} (iste {iste})"
            + ''.join(f'\n         · {t}' for t in info))


ok = 0
for z in arg:
    try:
        red = jedan(z)
        ok += red.startswith('OK')
    except RuntimeError as e:
        red = f'GREŠKA   {os.path.basename(z)}: {e}'
    print(red, flush=True)
print(f'— uvezeno {ok} od {len(arg)}')
