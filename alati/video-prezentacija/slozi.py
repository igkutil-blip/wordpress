# Slaže video iz scena: Ken Burns na mirnim scenama, listanje stranice, pretapanje, glazba
import subprocess, os, sys
V = os.path.dirname(os.path.abspath(__file__)) + '/'
FPS = 30
X = 0.7  # trajanje pretapanja

SCENE = [  # (ime, trajanje, vrsta)
    ('01-naslov', 5.5, 'zoom'),
    ('02-kategorija', 7.5, 'zoom'),
    ('03-proizvod', 7.5, 'zoom'),
    ('04-listanje', 10.5, 'listanje'),
    ('04b-glava', 7.5, 'zoom'),
    ('04c-galerija', 6.5, 'zoom'),
    ('05-foto1', 6.5, 'zoom'),
    ('06-foto2', 6.5, 'zoom'),
    ('06b-foto3', 6.5, 'zoom'),
    ('07-posluzivanje', 6.5, 'zoom'),
    ('08-zapremnine', 6.5, 'zoom'),
    ('09-glava', 5.5, 'zoom'),
    ('10-mobitel', 7.5, 'zoom'),
    ('11-dijeljenje', 6.5, 'zoom'),
    ('12-brojke', 7.0, 'zoom'),
    ('13-kraj', 6.5, 'zoom'),
]


def run(a):
    r = subprocess.run(a, capture_output=True, text=True)
    if r.returncode:
        print(r.stderr[-2000:])
        sys.exit(1)


os.makedirs(V + 'seg', exist_ok=True)
for ime, d, vrsta in SCENE:
    izlaz = V + 'seg/' + ime + '.mp4'
    n = int(round(d * FPS))
    if vrsta == 'zoom' and os.path.exists(izlaz):
        continue
    if vrsta == 'zoom':
        vf = (f"scale=3840:2160:flags=lanczos,zoompan=z='1+0.035*on/{n}':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)'"
              f":d={n}:s=1920x1080:fps={FPS},format=yuv420p")
        run(['ffmpeg', '-y', '-loop', '1', '-i', V + 'scene/' + ime + '.png', '-vf', vf, '-frames:v', str(n),
             '-c:v', 'libx264', '-preset', 'slow', '-crf', '18', '-r', str(FPS), izlaz])
    else:
        y = "if(lt(t,1.3),0,1340*(0.5-0.5*cos(PI*min(1,(t-1.3)/8.2))))"
        fc = (f"[0]crop=1920:1080:0:'{y}',setsar=1[a];"
              f"[1]format=rgba,fade=in:st=0.9:d=0.6:alpha=1,fade=out:st={d - 1.6}:d=0.6:alpha=1[b];[a][b]overlay=0:0,format=yuv420p")
        run(['ffmpeg', '-y', '-loop', '1', '-framerate', str(FPS), '-i', V + 'snimke/ter-nova-cijela.png',
             '-loop', '1', '-framerate', str(FPS), '-i', V + 'scene/natpis.png', '-filter_complex', fc,
             '-frames:v', str(n), '-c:v', 'libx264', '-preset', 'slow', '-crf', '18', '-r', str(FPS), izlaz])
    print('segment', ime, d)

# pretapanje redom
ulazi, fc, zadnji, pomak = [], [], '[0:v]', 0.0
for i, (ime, d, _) in enumerate(SCENE):
    ulazi += ['-i', V + 'seg/' + ime + '.mp4']
for i in range(1, len(SCENE)):
    pomak += SCENE[i - 1][1] - X
    oznaka = f'[v{i}]'
    fc.append(f"{zadnji}[{i}:v]xfade=transition=fade:duration={X}:offset={pomak:.3f}{oznaka}")
    zadnji = oznaka
ukupno = sum(d for _, d, _ in SCENE) - X * (len(SCENE) - 1)
fc.append(f"{zadnji}fade=out:st={ukupno - 1.0:.3f}:d=1.0,format=yuv420p[v]")
run(['python3', '-I', V + 'glazba.py', V + 'glazba.wav', f'{ukupno:.2f}'])
run(['ffmpeg', '-y', *ulazi, '-i', V + 'glazba.wav', '-filter_complex', ';'.join(fc), '-map', '[v]', '-map', f'{len(SCENE)}:a',
     '-c:v', 'libx264', '-preset', 'slow', '-crf', '18', '-profile:v', 'high', '-pix_fmt', 'yuv420p', '-r', str(FPS),
     '-c:a', 'aac', '-b:a', '192k', '-movflags', '+faststart', '-shortest', '-map_metadata', '-1',
     '-metadata', 'title=Domestic Spirits – Likeri i rakije, prije i sada', V + 'likeri-i-rakije-prije-i-sada.mp4'])
print('gotovo', round(ukupno, 1), 's')
