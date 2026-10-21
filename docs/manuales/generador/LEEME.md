# Generador de los manuales

Scripts que arman los manuales técnico y de usuario (Word) y los materiales de
la prueba con el personal, con la librería `docx` de Node.js. Las capturas del
manual de usuario se toman del sistema local con Microsoft Edge sin ventana
(`puppeteer-core`). No forman parte del sistema: solo producen los documentos.

## Requisitos

- Node.js, Python con Pillow, Microsoft Word (para actualizar el índice y pasar
  a PDF) y Microsoft Edge.
- XAMPP encendido, con la base local y los usuarios de prueba de `CLAUDE.md`.
- Una sola vez, en esta carpeta: `npm install`

## Pasos (desde esta carpeta, en PowerShell)

1. Capturas del sistema local:
   `node capturas.js capturas`
   (crea un usuario de prueba local para mostrar la contraseña temporal).
2. Recorte de las capturas:
   `python recortar.py capturas capturas-doc`
3. Manuales:
   `node manual_tecnico.js ..\manual-tecnico.docx`
   `node manual_usuario.js ..\manual-usuario.docx capturas-doc`
   Materiales de la capacitación del 21 de octubre y de la entrega del 22 (agenda,
   guía rápida, planilla de tiempos, lista de asistencia, constancia y acta):
   `node capacitacion.js ..\..\visita\materiales-capacitacion-21-octubre.docx`
4. Índice y PDF de cada uno:
   `.\actualizar.ps1 -Docx (Resolve-Path ..\manual-usuario.docx) -Pdf C:\ruta\manual-usuario.pdf`
   y copiar el PDF junto al Word.

Las carpetas `capturas`, `capturas-doc` y `node_modules` no se guardan en Git (`.gitignore`). Si cambia
una tabla de la base, regenerar `esquema.json` (diccionario de datos) desde
`sql/esquema.sql`. El logo de la portada es `logo-umg.png`.
