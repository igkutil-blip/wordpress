# Redoslijed na popisu priča: vinarije prve (noviji datum), zatim rakije, likeri i pivo. Adrese se ne mijenjaju (/%postname%/).
import os, json, base64, urllib.request, sys, time, datetime
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import objave as o
B = os.environ['DS_PROBA_URL'].rstrip('/') + '/wp-json/wp/v2/'
H = {'Authorization': 'Basic ' + base64.b64encode(f"{os.environ['DS_PROBA_WP_USER']}:{os.environ['DS_PROBA_WP_APP_PASSWORD']}".encode()).decode(), 'Content-Type': 'application/json'}
def req(u, d=None):
    for k in range(6):
        try:
            return json.load(urllib.request.urlopen(urllib.request.Request(B + u, json.dumps(d).encode() if d else None, H, method='POST' if d else 'GET'), timeout=60))
        except urllib.error.HTTPError as e:
            print(e.code, e.read()[:200]); raise
        except Exception:
            time.sleep(2 + 2 * k)
ids = {p['slug']: p['id'] for p in req('posts?per_page=50&_fields=id,slug')}
t = datetime.datetime(2026, 10, 23, 12, 0)
for i, p in enumerate(o.OBJAVE):
    r = req(f"posts/{ids[p['slug']]}", {'date': (t - datetime.timedelta(minutes=10 * i)).isoformat()})
    print(r['slug'], r['date'], r['link'])
req('categories/304', {'description': 'Malvazija, muškat, žlahtina i istarska crna vina – vinari Istre i Hrvatskog primorja s kojima surađujemo.'})
