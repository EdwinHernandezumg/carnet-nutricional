# Genera la voz de cada frase del guion con edge-tts (es-GT-MartaNeural) y la
# pasa a WAV de 24 kHz mono. Solo regenera las frases cuyo texto cambió.
import asyncio, hashlib, json, os, subprocess, sys, wave
import edge_tts, imageio_ffmpeg

AQUI = os.path.dirname(os.path.abspath(__file__))
VOZ = 'es-GT-MartaNeural'
VELOCIDAD = '-4%'
FFMPEG = imageio_ffmpeg.get_ffmpeg_exe()
CARPETA = os.path.join(AQUI, 'voz')
os.makedirs(CARPETA, exist_ok=True)

guion = json.load(open(os.path.join(AQUI, 'guion.json'), encoding='utf-8'))
duraciones = {}

async def generar(frase):
    texto = frase.get('voz', frase['texto'])
    firma = hashlib.sha1((VOZ + VELOCIDAD + texto).encode()).hexdigest()
    wav = os.path.join(CARPETA, frase['id'] + '.wav')
    marca = os.path.join(CARPETA, frase['id'] + '.firma')
    if not (os.path.exists(wav) and os.path.exists(marca) and open(marca).read() == firma):
        mp3 = os.path.join(CARPETA, frase['id'] + '.mp3')
        await edge_tts.Communicate(texto, VOZ, rate=VELOCIDAD).save(mp3)
        subprocess.run([FFMPEG, '-y', '-loglevel', 'error', '-i', mp3, '-ar', '24000', '-ac', '1', '-sample_fmt', 's16', wav], check=True)
        open(marca, 'w').write(firma)
        print('generada', frase['id'])
    with wave.open(wav) as w:
        duraciones[frase['id']] = round(w.getnframes() / w.getframerate(), 3)

async def principal():
    for seccion in guion:
        for frase in seccion['frases']:
            await generar(frase)

asyncio.run(principal())
json.dump(duraciones, open(os.path.join(AQUI, 'duraciones.json'), 'w'), indent=1)
print(len(duraciones), 'frases,', round(sum(duraciones.values()), 1), 'segundos de voz')
