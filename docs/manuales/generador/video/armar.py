# Junta los cuadros grabados (tiempos.json) con la voz de cada frase y saca el
# MP4. Uso: python armar.py salida.mp4 [ancho]   (ancho 1080 o 720)
import json, os, subprocess, sys, wave
import imageio_ffmpeg

AQUI = os.path.dirname(os.path.abspath(__file__))
FFMPEG = imageio_ffmpeg.get_ffmpeg_exe()
salida = os.path.join(AQUI, sys.argv[1])
ancho = int(sys.argv[2]) if len(sys.argv) > 2 else 1080
alto = ancho * 16 // 9

tiempos = json.load(open(os.path.join(AQUI, 'tiempos.json'), encoding='utf-8'))
cuadros, marcas, fin = tiempos['cuadros'], tiempos['marcas'], tiempos['fin']

# Imagen: lista para el concat de ffmpeg, cada cuadro hasta que llega el siguiente.
lista = os.path.join(AQUI, 'cuadros.txt')
with open(lista, 'w', encoding='utf-8') as f:
    for i, c in enumerate(cuadros):
        siguiente = cuadros[i + 1]['t'] if i + 1 < len(cuadros) else fin
        f.write(f"file 'cuadros/{c['archivo']}'\nduration {max(siguiente - c['t'], 0.001):.3f}\n")
    f.write(f"file 'cuadros/{cuadros[-1]['archivo']}'\n")

# Sonido: silencio del largo del video y cada frase en su marca de tiempo.
TASA = 24000
total = int((fin + 0.5) * TASA)
pista = bytearray(total * 2)
for m in marcas:
    with wave.open(os.path.join(AQUI, 'voz', m['id'] + '.wav')) as w:
        datos = w.readframes(w.getnframes())
    inicio = int(m['t'] * TASA) * 2
    pista[inicio:inicio + len(datos)] = datos[:len(pista) - inicio]
audio = os.path.join(AQUI, 'voz_completa.wav')
with wave.open(audio, 'wb') as w:
    w.setnchannels(1); w.setsampwidth(2); w.setframerate(TASA)
    w.writeframes(bytes(pista))

# Los cuadros JPEG vienen en rango completo; los teléfonos esperan el rango estándar.
filtro = f'fps=30,scale={ancho}:{alto}:flags=lanczos:in_range=pc:out_range=tv,format=yuv420p'
subprocess.run([FFMPEG, '-y', '-loglevel', 'error', '-f', 'concat', '-safe', '0', '-i', lista, '-i', audio,
                '-vf', filtro, '-c:v', 'libx264', '-preset', 'slow', '-crf', '24' if ancho >= 1080 else '26',
                '-pix_fmt', 'yuv420p', '-color_range', 'tv', '-colorspace', 'bt709', '-color_primaries', 'bt709', '-color_trc', 'bt709', '-c:a', 'aac', '-b:a', '96k', '-ar', '48000', '-movflags', '+faststart',
                '-shortest', salida], check=True, cwd=AQUI)
print(salida, round(os.path.getsize(salida) / 1e6, 1), 'MB,', round(fin, 1), 's')
