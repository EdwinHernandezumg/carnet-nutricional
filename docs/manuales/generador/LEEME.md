# Generador de los manuales

Scripts que arman los manuales técnico y de usuario (Word) y los materiales de
la prueba con el personal, con la librería `docx` de Node.js. Las capturas del
manual de usuario se toman del sistema local con Microsoft Edge sin ventana
(`puppeteer-core`). No forman parte del sistema: solo producen los documentos.

## Requisitos

- Node.js, Python con Pillow, Microsoft Word (para actualizar el índice y pasar
  a PDF) y Microsoft Edge.
- XAMPP encendido.
- Una sola vez, en esta carpeta: `npm install`

## Preparar las capturas (versión 1.1)

Las capturas se toman con los niños ficticios de la demostración, no con la
base de desarrollo, y con un servidor de correo de prueba que no envía nada,
para mostrar la pantalla del código de ingreso.

1. Base de demostración local (una sola vez; ver `docs/instalacion.md`): crear
   la base `demo_local`, instalarla con `php scripts/instalar.php --base=demo_local`
   y cargar los datos con `php scripts/cargar_demostracion.php --base=demo_local`.
   Crear ahí las cuentas locales que usa `capturas.js`: `admin` (al instalar,
   o después con `php scripts/crear_administrador.php --base=demo_local`) y `enfermera` (rol
   enfermería, con sector) desde la pantalla de Usuarios. En el primer ingreso
   de cada una, ponerle la contraseña que aparece en `capturas.js`.
2. Darle a la cuenta `enfermera` el correo `enfermera@carnet.test`, para que el
   sistema le pida el código. `.test` es un dominio reservado: nunca llega a
   nadie.
3. En `config/config.php`, mientras se toman las capturas (anotar los valores
   de antes para devolverlos):
   - `DB_NAME` en `'demo_local'`
   - `SMTP_HOST` en `'127.0.0.1'`, `SMTP_PUERTO` en `2525`, y cualquier texto
     en `SMTP_USUARIO` y `SMTP_CONTRASENA` (el servidor de prueba acepta todo).
4. En otra ventana de PowerShell, en esta carpeta, encender el servidor de
   correo de prueba y dejarlo abierto:
   `php servidor_correo_prueba.php`
   Guarda cada mensaje en `buzon.jsonl` (no se sube a Git).

Los números de los niños que se muestran están al inicio de `capturas.js`
(`NINO`, `NINO_PEQUENO` y `NINO_ANULAR`): si se carga otra demostración,
revisarlos.

## Pasos (desde esta carpeta, en PowerShell)

1. Capturas del sistema local:
   `node capturas.js capturas buzon.jsonl`
   Crea en `demo_local` un usuario de prueba (para mostrar la contraseña
   temporal) y anula una medición del niño `NINO_ANULAR`; para repetir las
   capturas desde cero, volver a cargar la demostración.
2. Apagar el servidor de correo (Ctrl+C) y **devolver `config.php`** a la base
   y al correo de antes.
3. Recorte de las capturas:
   `python recortar.py capturas capturas-doc`
4. Manuales:
   `node manual_tecnico.js ..\manual-tecnico.docx`
   `node manual_usuario.js ..\manual-usuario.docx capturas-doc`
   Materiales de la capacitación del 21 de octubre y de la entrega del 22 (agenda,
   guía rápida, planilla de tiempos, lista de asistencia, constancia y acta):
   `node capacitacion.js ..\..\visita\materiales-capacitacion-21-octubre.docx`
5. Índice y PDF de cada uno:
   `.\actualizar.ps1 -Docx (Resolve-Path ..\manual-usuario.docx).Path -Pdf C:\xampp\htdocs\carnet-nutricional\docs\manuales\manual-usuario.pdf`
   (y lo mismo con `manual-tecnico`).

## Video de capacitación (carpeta `video`)

Video vertical para el teléfono, de unos 5 minutos y medio, con voz
artificial (`es-GT-MartaNeural`), subtítulos y un círculo en cada toque:
`docs/manuales/video-capacitacion.mp4`. Se graba solo, con los niños
ficticios de `demo_local`, a 1080 x 1920.

Requisitos además de los de arriba: `pip install edge-tts imageio-ffmpeg` (la
voz se genera en línea; ffmpeg viene dentro del paquete) y Git Bash para
`preparar_base.sh`.

1. Voz: `python video\voz.py`. Solo vuelve a generar las frases que cambiaron
   en `guion.json`. En `voz` va cómo se pronuncia cuando difiere del
   subtítulo (por ejemplo "carné", para que no suene en inglés).
2. Copiar `video\estudio.html` a `C:\xampp\htdocs\estudio-video\index.html`:
   es la página con el teléfono, el título y los subtítulos, y debe estar en
   localhost, junto al sistema.
3. `bash video/preparar_base.sh`: copia `demo_local` en `video_local` y deja a
   la enfermera ficticia del video con correo. Repetirlo antes de cada
   grabación.
4. `config.php` apuntando a `video_local` y al servidor de correo de prueba,
   como en "Preparar las capturas", con `php servidor_correo_prueba.php`
   encendido.
5. `node video\grabar.js` (unos 6 minutos; si una acción falla, la anota con
   FALLO y sigue).
6. `python video\armar.py video-1080.mp4 1080` junta los cuadros con la voz
   (el MP4 queda en la carpeta `video`). Devolver `config.php` y copiar el MP4
   a `docs\manuales\video-capacitacion.mp4`.

## Diccionario de datos

Si cambia una tabla de la base, regenerar primero `sql/esquema.sql` con
`php scripts/exportar_esquema.php` y después `esquema.json`, que usa el manual
técnico:

`python esquema_json.py ..\..\..\sql\esquema.sql esquema.json`

Las descripciones de cada columna están en `manual_tecnico.js` (`desc`); una
columna nueva sin descripción sale con la celda en blanco. Una tabla nueva hay
que agregarla también a `ordenTablas`, en el mismo archivo.

Las carpetas `capturas`, `capturas-doc` y `node_modules` y el archivo
`buzon.jsonl` no se guardan en Git (`.gitignore`). El logo de la portada es
`logo-umg.png`.
