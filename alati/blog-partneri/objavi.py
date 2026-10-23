import os, json, base64, urllib.request, sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import objave as o
U = os.environ['DS_PROBA_URL'].rstrip('/') + '/wp-json/wp/v2/posts'
H = {'Authorization': 'Basic ' + base64.b64encode(f"{os.environ['DS_PROBA_WP_USER']}:{os.environ['DS_PROBA_WP_APP_PASSWORD']}".encode()).decode(), 'Content-Type': 'application/json'}
def req(url, data=None, method='GET'):
    for k in range(4):
        try:
            r = urllib.request.Request(url, data=json.dumps(data).encode() if data else None, headers=H, method=method)
            return json.load(urllib.request.urlopen(r, timeout=60))
        except urllib.error.HTTPError as e:
            print('HTTP', e.code, e.read()[:300]); raise
        except Exception as e:
            print('ponovo', e)
    raise SystemExit('neuspjeh')
for p in o.OBJAVE:
    kat = p['kat'] if isinstance(p['kat'], list) else [p['kat']]
    r = req(U, {'title': p['naslov'], 'slug': p['slug'], 'status': 'publish', 'categories': kat, 'featured_media': p['slika'],
                'excerpt': p['sazetak'], 'content': p['tekst'] + o.proizvodi(p['ids'])}, 'POST')
    print(r['id'], r['link'])
for pid, d in o.POSTOJECE.items():
    c = req(f'{U}/{pid}?context=edit')['content']['raw']
    if '[products ids=' not in c:
        c = c.rstrip() + '\n' + o.proizvodi(d['ids'])
    r = req(f'{U}/{pid}', {'content': c, 'categories': d['kat']}, 'POST')
    print(r['id'], r['link'], r['categories'])
