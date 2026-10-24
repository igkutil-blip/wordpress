# Uklanja metapodatke (EXIF, XMP, C2PA, komentari, PNG tekstovi) iz SVIH slika u biblioteci – bez ponovnog sažimanja, iste adrese.
#   python3 -I alati/uvoz-slika/ocisti.py [--ziva]
import os, sys, json, base64, time, urllib.request
P = 'DS_' if '--ziva' in sys.argv else 'DS_PROBA_'
B = os.environ[P + 'URL'].rstrip('/') + '/wp-json/domestic-slike/v1/ocisti'
A = 'Basic ' + base64.b64encode(f"{os.environ[P + 'WP_USER']}:{os.environ[P + 'WP_APP_PASSWORD']}".encode()).decode()
s, uk, dat, oc, sl = 1, None, 0, 0, 0
while uk is None or s <= uk:
    for pokusaj in range(5):
        try:
            r = json.load(urllib.request.urlopen(urllib.request.Request(B, json.dumps({'stranica': s, 'po': 40}).encode(), {'Authorization': A, 'Content-Type': 'application/json'}), timeout=300))
            break
        except Exception as e:
            if pokusaj == 4:
                raise SystemExit(f'stranica {s}: {e}')
            time.sleep(4 * (pokusaj + 1))
    uk = r['ukupno_stranica']; sl += r['slika']; dat += r['datoteka']; oc += r['ocisceno']
    print(f"{s}/{uk}: slika {r['slika']}, datoteka {r['datoteka']}, očišćeno {r['ocisceno']}", flush=True)
    s += 1
print(f'— slika {sl}, datoteka {dat}, očišćeno {oc}')
