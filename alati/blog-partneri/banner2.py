import os, sys
sys.path.insert(0, '.')
import objave as o
from PIL import Image, ImageChops, ImageOps
KREM, TAMNA = (247, 243, 235), (41, 35, 31)
W, H = 1600, 900
def vrsta(im):
    k = [im.getpixel(xy) for xy in [(3, 3), (im.width - 4, 3), (3, im.height - 4), (im.width - 4, im.height - 4)]]
    if all(min(c) > 235 for c in k): return 'w'
    if all(max(c) < 30 for c in k): return 'k'
    return 'o'
def izrez(im, ref, prag):
    d = ImageChops.difference(im, Image.new('RGB', im.size, ref)).convert('L').point(lambda v: 255 if v > prag else 0)
    return im.crop(d.getbbox() or (0, 0) + im.size)
for p in o.OBJAVE:
    sl = [Image.open(f'izvor/{i}.img').convert('RGB') for i in p['ids'][:4] if os.path.exists(f'izvor/{i}.img') and os.path.getsize(f'izvor/{i}.img') > 2000]
    vr = [vrsta(i) for i in sl]
    n = len(sl)
    if len(set(vr)) == 1 and vr[0] in 'wk':
        bg = KREM if vr[0] == 'w' else TAMNA
        plat = Image.new('RGB', (W, H), bg)
        sk = [izrez(i, (255,) * 3 if vr[0] == 'w' else (0, 0, 0), 22 if vr[0] == 'w' else 35) for i in sl]
        vis = 720 if n <= 2 else 680
        sk = [i.resize((round(i.width * vis / i.height), vis), Image.LANCZOS) for i in sk]
        gap = 80
        while sum(i.width for i in sk) + gap * (n - 1) > W - 200:
            sk = [i.resize((round(i.width * .9), round(i.height * .9)), Image.LANCZOS) for i in sk]
        x = (W - sum(i.width for i in sk) - gap * (n - 1)) // 2
        for i in sk:
            y = (H - i.height) // 2 + 10
            reg = plat.crop((x, y, x + i.width, y + i.height))
            plat.paste(ImageChops.multiply(reg, i) if vr[0] == 'w' else ImageChops.lighter(reg, i), (x, y)); x += i.width + gap
    else:  # različite pozadine: jednake kvadratne pločice
        plat = Image.new('RGB', (W, H), KREM)
        t = min(700, (W - 160 - 40 * (n - 1)) // n)
        x = (W - n * t - 40 * (n - 1)) // 2
        for i in sl:
            if i.width > i.height * 1.02 or i.height > i.width * 1.02:
                i = izrez(i, (255,) * 3, 8) if vrsta(i) == 'o' else i  # bijele trake uz rub van
            s = max(i.size); kv = Image.new('RGB', (s, s), i.getpixel((2, i.height // 2)))
            kv.paste(i, ((s - i.width) // 2, (s - i.height) // 2))
            plat.paste(kv.resize((t, t), Image.LANCZOS), (x, (H - t) // 2)); x += t + 40
    plat.save(f"banner/{p['slug']}.jpg", quality=85, optimize=True, progressive=True)
    print(p['slug'], n, vr)
