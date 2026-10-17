# Recorta las capturas para el manual de usuario y anota su tamaño.
# Las del teléfono se dejan con lo principal de la pantalla, para que quepan
# dos secciones por página.
#   python recortar.py <carpeta de capturas> <carpeta de salida>
import glob
import json
import os
import sys

from PIL import Image

origen, salida = sys.argv[1], sys.argv[2]
os.makedirs(salida, exist_ok=True)
# Alto máximo en píxeles (las capturas del teléfono están a escala 2).
especial = {'t08-graficas': 1350, 't07-historial': 1250, 't13-reportes': 1250, 't05-nino-registrado': 1250}
tamanos = {}
for archivo in sorted(glob.glob(os.path.join(origen, '*.png'))):
    nombre = os.path.basename(archivo)[:-4]
    imagen = Image.open(archivo)
    ancho, alto = imagen.size
    maximo = especial.get(nombre, 1150) if nombre.startswith('t') else 900
    if alto > maximo:
        imagen = imagen.crop((0, 0, ancho, maximo))
    imagen.save(os.path.join(salida, nombre + '.png'), optimize=True)
    tamanos[nombre] = imagen.size
with open(os.path.join(salida, 'tamanos.json'), 'w') as f:
    json.dump(tamanos, f, indent=1)
print(len(tamanos), 'capturas recortadas')
