# Regenera esquema.json (diccionario de datos del manual técnico) desde
# sql/esquema.sql. Uso, desde esta carpeta:
#   python esquema_json.py ..\..\..\sql\esquema.sql esquema.json
import json
import re
import sys

origen, destino = sys.argv[1], sys.argv[2]
sql = open(origen, encoding='utf-8').read()

esquema = {}
for tabla, cuerpo in re.findall(r'CREATE TABLE `(\w+)` \((.*?)\n\) ENGINE', sql, re.S):
    info = {'cols': [], 'pk': None, 'fk': {}}
    for linea in cuerpo.split('\n'):
        linea = linea.strip().rstrip(',')
        columna = re.match(r'`(\w+)` (\S+(?:\([^)]*\))?)\s*(.*)', linea)
        if columna:
            nombre, tipo, resto = columna.groups()
            info['cols'].append({'n': nombre, 'tipo': tipo, 'nulo': 'NOT NULL' not in resto, 'resto': resto})
            continue
        clave = re.match(r'PRIMARY KEY \(`(\w+)`\)', linea)
        if clave:
            info['pk'] = clave.group(1)
        foranea = re.search(r'FOREIGN KEY \(`(\w+)`\) REFERENCES `(\w+)`', linea)
        if foranea:
            info['fk'][foranea.group(1)] = foranea.group(2)
    esquema[tabla] = info

json.dump(esquema, open(destino, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
print(len(esquema), 'tablas:', ', '.join(esquema))
