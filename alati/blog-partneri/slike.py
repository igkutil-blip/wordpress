import os, json, base64, urllib.request, sys, time
sys.path.insert(0, '.')
import objave as o
B = os.environ['DS_PROBA_URL'].rstrip('/') + '/wp-json/wp/v2/'
A = 'Basic ' + base64.b64encode(f"{os.environ['DS_PROBA_WP_USER']}:{os.environ['DS_PROBA_WP_APP_PASSWORD']}".encode()).decode()
def req(u, data, ct):
    for k in range(6):
        try:
            r = urllib.request.Request(B + u, data=data, method='POST', headers={'Authorization': A, 'Content-Type': ct, **({'Content-Disposition': f'attachment; filename="{u2}"'} if ct == 'image/jpeg' else {})})
            return json.load(urllib.request.urlopen(r, timeout=120))
        except urllib.error.HTTPError as e:
            print(e.code, e.read()[:200]); raise
        except Exception as e:
            print('ponovo', e); time.sleep(3 + 2 * k)
ids = {p['slug']: None for p in o.OBJAVE}
posts = json.load(urllib.request.urlopen(urllib.request.Request(B + 'posts?per_page=50&_fields=id,slug', headers={'Authorization': A})))
pid = {p['slug']: p['id'] for p in posts}
for p in o.OBJAVE:
    u2 = f"blog-{p['slug']}.jpg"
    m = req('media', open(f"banner/{p['slug']}.jpg", 'rb').read(), 'image/jpeg')
    req(f"media/{m['id']}", json.dumps({'alt_text': p['naslov'].split(' – ')[0] + ' – vina i proizvodi u ponudi Domestic Spiritsa', 'title': p['naslov']}).encode(), 'application/json')
    req(f"posts/{pid[p['slug']]}", json.dumps({'featured_media': m['id']}).encode(), 'application/json')
    print(p['slug'], m['id'], m['source_url'])
