# Upisuje proširene tekstove (objave.py + dodaci.py) na probu.
import os, json, base64, urllib.request, sys, time
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import objave as o, dodaci as d
U = os.environ['DS_PROBA_URL'].rstrip('/') + '/wp-json/wp/v2/posts'
H = {'Authorization': 'Basic ' + base64.b64encode(f"{os.environ['DS_PROBA_WP_USER']}:{os.environ['DS_PROBA_WP_APP_PASSWORD']}".encode()).decode(), 'Content-Type': 'application/json'}
def req(url, data=None):
    for k in range(6):
        try:
            r = urllib.request.Request(url, data=json.dumps(data).encode() if data else None, headers=H, method='POST' if data else 'GET')
            return json.load(urllib.request.urlopen(r, timeout=90))
        except urllib.error.HTTPError as e:
            print('HTTP', e.code, e.read()[:300]); raise
        except Exception as e:
            print('ponovo', e); time.sleep(2 + 2 * k)
ids = {p['slug']: p['id'] for p in req(U + '?per_page=50&_fields=id,slug')}
for p in o.OBJAVE:
    r = req(f"{U}/{ids[p['slug']]}", {'content': p['tekst'] + '\n' + d.DODACI[p['slug']] + o.proizvodi(p['ids'])})
    print(r['id'], p['slug'])
for pid, dod in d.DODACI_POSTOJECE.items():
    c = req(f'{U}/{pid}?context=edit')['content']['raw']
    if 'dsb-stol' not in c:
        c = c.replace('<h2>U našoj ponudi</h2>', dod + '<h2>U našoj ponudi</h2>', 1)
        req(f'{U}/{pid}', {'content': c})
    print(pid, 'dsb-stol' in c)
