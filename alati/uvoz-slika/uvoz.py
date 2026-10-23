# Uvoz paketa slika za jedan proizvod na probu (pravila: CLAUDE.md „Slike proizvoda – uvoz” i identitet/PRAVILA-ZA-SLIKE.txt).
#   python3 -I alati/uvoz-slika/uvoz.py <postavke.json>
# Postavke (JSON): proizvod, glavna (ID stare glavne slike), mapa (raspakirani paket), ambijent, studio, posluzivanje (nazivi datoteka),
#   alt {datoteka: alt}, alt_glavna (samo ako je stari alt prazan), naziv (kratki naziv za naslove slika), oznaka (za nazive ikona), ostale (ID-ovi koji ostaju iza u galeriji),
#   ikone [{ime, ikona, naziv, kolicina, opis, alt}] – redom s kartice posluživanja, izlaz (mapa za obrađene slike).
# Ambijentalna ide u staru glavnu sliku na istoj adresi i u istom formatu (PNG → 256 boja + paleta=1), studio/posluživanje/ikone su nove.
# Sve slike se ponovno spremaju (bez metapodataka); tekstovi iz paketa se ne učitavaju.
import os, sys, json, base64, urllib.request, time, uuid
from PIL import Image, ImageChops, ImageDraw, ImageFilter

P = json.load(open(sys.argv[1], encoding='utf-8'))
B = os.environ['DS_PROBA_URL'].rstrip('/') + '/wp-json/'
A = 'Basic ' + base64.b64encode(f"{os.environ['DS_PROBA_WP_USER']}:{os.environ['DS_PROBA_WP_APP_PASSWORD']}".encode()).decode()
OUT = P['izlaz']
os.makedirs(OUT, exist_ok=True)


def req(u, data=None, ct='application/json', extra=None, method=None):
    h = {'Authorization': A}
    if data is not None:
        h['Content-Type'] = ct
    h.update(extra or {})
    for k in range(6):
        try:
            return json.load(urllib.request.urlopen(urllib.request.Request(B + u, data, h, method=method or ('POST' if data is not None else 'GET')), timeout=180))
        except urllib.error.HTTPError as e:
            print(e.code, e.read()[:300])
            raise
        except Exception as e:
            print('ponovo', e)
            time.sleep(3 + 2 * k)
    raise SystemExit('neuspjeh: ' + u)


def multipart(polja, datoteka, mime):
    b = uuid.uuid4().hex
    t = b''
    for k, v in polja.items():
        t += f'--{b}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode()
    t += f'--{b}\r\nContent-Disposition: form-data; name="datoteka"; filename="{os.path.basename(datoteka)}"\r\nContent-Type: {mime}\r\n\r\n'.encode() + open(datoteka, 'rb').read() + f'\r\n--{b}--\r\n'.encode()
    return t, 'multipart/form-data; boundary=' + b


def nova(f, alt, naslov, mime):
    m = req('wp/v2/media', open(f, 'rb').read(), mime, {'Content-Disposition': f'attachment; filename="{os.path.basename(f)}"'})
    req(f"wp/v2/media/{m['id']}", json.dumps({'alt_text': alt, 'title': naslov}).encode())
    print(m['id'], m['source_url'])
    return m['id']


def krugovi(k, bg):
    """Središta i polumjer krugova ikona na kartici (red kroz sredinu krugova, 3 najšira odsječka)."""
    redovi = []
    for y in range(540, 740, 2):
        seg, s, p = [], None, None
        for x in range(k.width):
            if sum(abs(a - b) for a, b in zip(k.getpixel((x, y)), bg)) > 8:
                if s is None:
                    s = x
                elif x != p + 1:
                    seg.append((s, p))
                    s = x
                p = x
        if s is not None:
            seg.append((s, p))
        seg = sorted(sorted(seg, key=lambda g: g[1] - g[0])[-3:])
        if len(seg) == 3 and all(g[1] - g[0] > 250 for g in seg):
            redovi.append((sum(g[1] - g[0] for g in seg), y, seg))
    if not redovi:
        raise SystemExit('Krugovi ikona nisu nađeni – provjeriti karticu ručno.')
    # vodoravno: najširi red daje x-sredine i polumjer; okomito: sredina = gornji rub kruga + polumjer (donji rub zna imati sjenu/tanjur)
    seg = max(redovi, key=lambda r: r[0])[2]
    r = max(g[1] - g[0] for g in seg) // 2
    cx = (seg[0][0] + seg[0][1]) // 2
    # od 420 px: iznad je crta ispod naslova kartice
    vrh = next(y for y in range(420, 900) if sum(1 for x in range(cx - r, cx + r, 3) if sum(abs(a - b) for a, b in zip(k.getpixel((x, y)), bg)) > 5) > 6)
    y = vrh + r
    return [((g[0] + g[1]) // 2, y) for g in seg], max(g[1] - g[0] for g in seg) // 2


M = P['mapa']
stara = req(f"wp/v2/media/{P['glavna']}?_fields=source_url")['source_url'].split('?')[0]
ext = stara.rsplit('.', 1)[1].lower()
osnova = os.path.basename(stara)
amb = Image.open(os.path.join(M, P['ambijent'])).convert('RGB')
if ext == 'png':
    amb.quantize(256, method=Image.Quantize.MEDIANCUT, dither=Image.Dither.FLOYDSTEINBERG).save(os.path.join(OUT, osnova), optimize=True)
else:
    amb.save(os.path.join(OUT, osnova), quality=85, optimize=True, progressive=True)
for n in (P['studio'], P['posluzivanje']):
    Image.open(os.path.join(M, n)).convert('RGB').save(os.path.join(OUT, n), quality=85, optimize=True, progressive=True)

# ikone: puni krug + ono što viri izvan kruga (tanjur), ostalo prozirno; 280 px
k = Image.open(os.path.join(M, P['posluzivanje'])).convert('RGB')
bg = k.getpixel((50, 628))
sredista, r = krugovi(k, bg)
r -= 1
S = 392
print('krugovi', sredista, 'r', r)
for (x, y), ik in zip(sredista, P['ikone']):
    c = k.crop((x - S // 2, y - S // 2, x + S // 2, y + S // 2))
    van = ImageChops.difference(c, Image.new('RGB', c.size, bg)).convert('L').point(lambda v: 0 if v < 6 else (255 if v > 16 else int((v - 6) * 255 / 10)))
    m = Image.new('L', (S * 4, S * 4), 0)
    ImageDraw.Draw(m).ellipse((S * 2 - r * 4, S * 2 - r * 4, S * 2 + r * 4, S * 2 + r * 4), fill=255)
    al = ImageChops.lighter(m.resize((S, S), Image.LANCZOS), van.filter(ImageFilter.GaussianBlur(.5)))
    c = c.convert('RGBA')
    c.putalpha(al)
    c.resize((280, 280), Image.LANCZOS).save(os.path.join(OUT, f"posluzivanje-ikona-{P['oznaka']}-{ik['ime']}.png"), optimize=True)

if '--bez-ucitavanja' in sys.argv:
    raise SystemExit('Pripremljeno u ' + OUT)

t, ct = multipart({'paleta': '1'} if ext == 'png' else {}, os.path.join(OUT, osnova), 'image/png' if ext == 'png' else 'image/jpeg')
print(P['glavna'], str(req(f"domestic-pomocnik/v1/medij/{P['glavna']}", t, ct))[:160])
# stari alt glavne slike ostaje; samo prazan se popunjava (alt_glavna)
if P.get('alt_glavna') and not req(f"wp/v2/media/{P['glavna']}?context=edit&_fields=alt_text")['alt_text'].strip():
    req(f"wp/v2/media/{P['glavna']}", json.dumps({'alt_text': P['alt_glavna']}).encode())
    print('alt glavne popunjen')
st = nova(os.path.join(OUT, P['studio']), P['alt'][P['studio']], P['naziv'] + ' – studijska', 'image/jpeg')
po = nova(os.path.join(OUT, P['posluzivanje']), P['alt'][P['posluzivanje']], P['naziv'] + ' – kako poslužiti', 'image/jpeg')
pos = []
for ik in P['ikone']:
    sid = nova(os.path.join(OUT, f"posluzivanje-ikona-{P['oznaka']}-{ik['ime']}.png"), ik['alt'], P['naziv'].split(' 0,')[0] + ' – ' + ik['naziv'].lower(), 'image/png')
    pos.append({'ikona': ik['ikona'], 'naziv': ik['naziv'], 'kolicina': ik['kolicina'], 'opis': ik['opis'], 'slika': sid})
slike = [{'id': P['glavna']}, {'id': st}, {'id': po}] + [{'id': i} for i in P.get('ostale', [])]
r = req(f"wc/v3/products/{P['proizvod']}", json.dumps({'images': slike, 'meta_data': [{'key': '_dsp_posluzivanje', 'value': pos}]}).encode(), method='PUT')
print('galerija', [i['id'] for i in r['images']])
