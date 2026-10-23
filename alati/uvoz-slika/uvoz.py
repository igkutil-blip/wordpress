# Uvoz paketa slika preko dodatka domestic-slike (sva obrada je u dodatku: Domestic → Slike proizvoda).
#   python3 -I alati/uvoz-slika/uvoz.py paket.zip [--samo-analiza] [--postavke izmjene.json]
# Bez --samo-analiza: analiza pa uvoz s prijedlozima dodatka (proizvod, stara adresa glavne slike, ikone, „Kako poslužiti”).
# izmjene.json (neobavezno) = polja za uvoz koja se mijenjaju: proizvod, cilj, ikone_izvor, posluzivanje[], zadrzi[], dodatne, napomene.
# Okolina: DS_PROBA_URL, DS_PROBA_WP_USER, DS_PROBA_WP_APP_PASSWORD (ili DS_URL/DS_WP_* za živu, uz --ziva).
import os, sys, json, base64, uuid, urllib.request

ziva = '--ziva' in sys.argv
P = 'DS_' if ziva else 'DS_PROBA_'
B = os.environ[P + 'URL'].rstrip('/') + '/wp-json/domestic-slike/v1/'
A = 'Basic ' + base64.b64encode(f"{os.environ[P + 'WP_USER']}:{os.environ[P + 'WP_APP_PASSWORD']}".encode()).decode()


def req(u, data=None, ct='application/json', method=None):
    h = {'Authorization': A}
    if data is not None:
        h['Content-Type'] = ct
    try:
        return json.load(urllib.request.urlopen(urllib.request.Request(B + u, data, h, method=method or ('POST' if data is not None else 'GET')), timeout=300))
    except urllib.error.HTTPError as e:
        raise SystemExit(f'{e.code} {e.read()[:400].decode(errors="replace")}')


zip_put = sys.argv[1]
b = uuid.uuid4().hex
tijelo = f'--{b}\r\nContent-Disposition: form-data; name="zip"; filename="{os.path.basename(zip_put)}"\r\nContent-Type: application/zip\r\n\r\n'.encode() + open(zip_put, 'rb').read() + f'\r\n--{b}--\r\n'.encode()
a = req('paket', tijelo, 'multipart/form-data; boundary=' + b)
print('proizvod:', [(k['id'], k['naziv'], k['bodovi'], 'glavna', k['glavna']) for k in a['kandidati']])
print('slike:', [s['naziv'] for s in a['slike']], 'nije učitano:', a['ostalo'])
print('ikone:', a['ikone'], 'iste kao proizvod:', a['izvor_ikona'])
for s in a['stavke']:
    print('  ', s['naziv'], '|', s['kolicina'], '|', s['opis'], '|', s['ikona'])
print('napomene:', len(a['napomene']))
if '--samo-analiza' in sys.argv:
    print('token', a['token'], '(priprema čeka u Domestic → Slike proizvoda)')
    raise SystemExit
izmjene = {}
if '--postavke' in sys.argv:
    izmjene = json.load(open(sys.argv[sys.argv.index('--postavke') + 1], encoding='utf-8'))
r = req('paket/' + a['token'], json.dumps(izmjene).encode())
print('uvezeno:', json.dumps(r, ensure_ascii=False))
