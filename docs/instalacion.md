# Instalación del Carnet Digital en un servidor

Guía para instalar el sistema en un hosting con una base de datos nueva.
Probada en Hostinger (plan Premium) el 15 de octubre de 2026, con el dominio
carnetchivarreto.com. Los nombres de los menús son los del hPanel de Hostinger;
en otro hosting cambian un poco, pero los pasos son los mismos.

## Lo que se necesita

- Hosting con **PHP 8.1 o más reciente** (el sistema se desarrolló con PHP 8.2)
  y las extensiones **PDO MySQL** y **mbstring**. En Hostinger se revisan en
  Avanzado → Configuración de PHP; PDO MySQL aparece como `nd_pdo_mysql`.
- Un dominio con certificado SSL (https). Hostinger lo activa solo; se revisa
  en Seguridad → SSL.
- **Acceso SSH** (la consola del servidor). En Hostinger: Avanzado → Acceso SSH
  → Activar. Desde Windows se entra con PowerShell, que ya trae SSH:
  ```
  ssh -p PUERTO USUARIO@IP
  ```
  El panel muestra el comando completo. Si el hosting no tiene SSH, ver
  "Sin consola" al final de cada paso.

Hostinger cierra la conexión SSH tras un rato sin uso, y la ventana se queda
congelada sin avisar. Para salir: Enter, `~` y `.`; o cerrar la ventana. Luego
se vuelve a entrar.

## 1. Crear la base de datos

En Bases de datos → Administración, crear una base y su usuario. Hostinger
antepone un prefijo (por ejemplo `u123456789_`); basta escribir la parte final,
por ejemplo `carnet`. Generar la contraseña con el botón del panel y guardarla.

La contraseña **no debe llevar comilla simple `'` ni barra invertida `\`**: va
dentro de comillas simples en `config.php` y esos caracteres rompen la línea.

No crear tablas ni importar nada: el instalador necesita la base vacía.

## 2. Traer el código con Git

En la consola del servidor:

```
cd ~/domains/su-dominio.com
git clone https://github.com/EdwinHernandezumg/carnet-nutricional.git
```

El proyecto completo queda **fuera** de la carpeta pública. En el paso 5,
`public_html` pasa a ser un enlace a `carnet-nutricional/public`:

```
domains/su-dominio.com/
├── carnet-nutricional/      el proyecto: config, src, views, sql, scripts, public
├── public_html              enlace a carnet-nutricional/public
└── public_html_original/    la página de bienvenida del hosting, guardada
```

Así solo `public/` se ve desde el navegador, y cada actualización es un
`git pull` (ver "Actualizar el sistema"). `config/config.php` y `CLAUDE.md`
están en `.gitignore`: nunca llegan al servidor. El archivo
`DO_NOT_UPLOAD_HERE` que pone Hostinger solo avisa que esa carpeta no se ve
desde la web; es justo lo que se busca.

**Sin consola:** subir el CONTENIDO de `public/` (index.php, .htaccess, css/,
js/, assets/) a `public_html`, y `config/`, `src/`, `views/`, `sql/` y
`scripts/` un nivel arriba, junto a `public_html`. El `index.php` busca
`../src`, `../config` y `../views`, así que funciona sin cambiar el código.

## 3. Configurar

Crear `carnet-nutricional/config/config.php` con el **Administrador de
archivos** del panel (Archivos → Administrador de archivos), no con la consola,
para que la contraseña no quede a la vista en la pantalla. Contenido completo,
cambiando los datos de la base, la contraseña y el dominio:

```php
<?php
/**
 * Configuración del servidor. No se guarda en Git (está en .gitignore) porque
 * lleva la contraseña de la base de datos.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'u123456789_carnet');
define('DB_USER', 'u123456789_carnet');
define('DB_PASS', 'ESCRIBA_AQUI_LA_CONTRASENA');
define('DB_CHARSET', 'utf8mb4');

define('APP_NOMBRE', 'Carnet Digital de Monitoreo Nutricional Infantil');
define('APP_URL', 'https://su-dominio.com');
define('APP_ENTORNO', 'produccion'); // desarrollo, produccion

date_default_timezone_set('America/Guatemala');
```

- La primera línea es exactamente `<?php`, sin nada antes.
- `APP_URL` va sin `www`, sin barra al final y sin `/public`.
- `APP_ENTORNO` en `produccion`: no muestra errores técnicos en pantalla y los
  guarda en `carnet-nutricional/errores_php.log`, fuera de la carpeta pública.

Revisarlo desde la consola, sin mostrar la contraseña:

```
cd ~/domains/su-dominio.com/carnet-nutricional
php -l config/config.php
php -r 'require "config/config.php"; try { new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS); echo "Conexion correcta\n"; } catch (PDOException $e) { echo "Error: ", $e->getMessage(), "\n"; }'
```

Debe decir `No syntax errors detected` y `Conexion correcta`.

## 4. Crear las tablas y el administrador

Desde la carpeta `carnet-nutricional`:

```
php scripts/instalar.php
```

El script:

1. Revisa que la base esté vacía. Si ya tiene tablas, no hace nada, para no
   borrar datos.
2. Crea las 17 tablas y carga los datos iniciales y las tablas de la OMS.
3. Verifica que todo quedó cargado: 3 roles, 29 parajes, 30 dosis de vacunas y
   728 filas de la OMS.
4. Pide el usuario y el nombre del primer administrador. Conviene no usar
   `admin`: es lo primero que prueba quien ataca un sitio público.
5. **En Hostinger no pide contraseña: genera una temporal** (por ejemplo
   `KM4821tz`) y la muestra. Hostinger deshabilita las funciones que permiten
   ocultar lo que se escribe en la consola, y una contraseña escrita a la vista
   podía guardarse distinta de la que se creía (al corregir con Retroceso, la
   consola enviaba otra cosa). La temporal se usa una sola vez: el sistema
   obliga a cambiarla en el primer ingreso, desde el navegador.

Esperar a ver `Instalación terminada` y la contraseña temporal, anotarla, y
solo entonces escribir `clear` para borrar la pantalla.

Si el administrador no se creó (por ejemplo, la conexión se cortó a la mitad),
no hace falta reinstalar: `php scripts/crear_administrador.php` lo crea.

**Sin consola:** en phpMyAdmin, elegir la base y en la pestaña **Importar**
importar, **en este orden**, `sql/esquema.sql`, `sql/datos_iniciales.sql` y
`sql/oms_lms.sql`. Para el administrador, en la computadora (con XAMPP):

```
php scripts/crear_administrador.php --sql
```

Pide los datos y muestra una sentencia SQL para pegar en la pestaña **SQL** de
phpMyAdmin. Lleva la contraseña cifrada, nunca la contraseña escrita.

## 5. Publicar el sitio

Se hace al final, para que nadie vea el sitio a medias:

```
cd ~/domains/su-dominio.com
mv public_html public_html_original
ln -s carnet-nutricional/public public_html
ls -la
```

Debe aparecer `public_html -> carnet-nutricional/public`. Para volver atrás:
`rm public_html` y `mv public_html_original public_html`.

## 6. Comprobar

1. Abrir `https://su-dominio.com`: debe aparecer la pantalla de ingreso.
2. Ingresar con el administrador y la contraseña temporal: debe pedir cambiarla
   y luego abrir en Gestión de usuarios.
3. `http://su-dominio.com` y `www.su-dominio.com` deben pasar solos a
   `https://su-dominio.com`. La sesión se guarda para una sola dirección; por
   eso `public/.htaccess` manda la dirección con www a la dirección sin www.
4. Estas direcciones deben dar "no encontrado" (404) o "prohibido" (403), nunca
   mostrar su contenido: `/config/config.php`, `/sql/esquema.sql`,
   `/scripts/instalar.php`, `/.git/config`, `/error_log`, `/css/`.
5. Revisarlo también en el teléfono.
6. Crear las cuentas de las enfermeras desde Gestión de usuarios, cada una con
   su sector.

## Actualizar el sistema

En la computadora, guardar el cambio en Git y subirlo (`git push`). En el
servidor:

```
cd ~/domains/su-dominio.com/carnet-nutricional
git pull
```

El cambio se ve en el sitio de inmediato. Si el cambio trae un archivo nuevo en
`sql/migraciones/`, ejecutarlo en la base del servidor (pestaña SQL de
phpMyAdmin) después del `git pull`.

## Si algo falla

- **Un script de la consola se detiene sin mostrar nada.** La consola de
  Hostinger oculta los errores de PHP (y los guarda en un archivo `error_log`
  en la carpeta desde donde se ejecutó). Para verlos, ejecutar el script así:
  ```
  php -d display_errors=1 scripts/crear_administrador.php
  ```
- **La página muestra un error o queda en blanco.** El detalle está en
  `carnet-nutricional/errores_php.log`. No se ve desde el navegador.

## Si el administrador pierde la contraseña

- **Con consola:** `php scripts/crear_administrador.php`, escribir el usuario
  del administrador y aceptar. En Hostinger le genera una contraseña temporal
  que debe cambiar al ingresar. También lo reactiva si estaba desactivado.
  Queda en la bitácora. Con `--temporal` se pide siempre una temporal, en
  cualquier servidor.
- **Sin consola:** crear otro administrador con
  `php scripts/crear_administrador.php --sql` (con otro nombre de usuario) y,
  desde ese, restablecer la contraseña del primero en Gestión de usuarios.

## Para el desarrollador: mantener los archivos SQL al día

La base local es la fuente de verdad. Cada vez que cambie la estructura de una
tabla o un catálogo (vacunas, suplementos, parajes), volver a generar los tres
archivos de instalación:

```
php scripts/exportar_esquema.php
```

La carpeta `sql/migraciones/` guarda los cambios hechos a una base que ya
existía (por ejemplo, el cambio al esquema de vacunación 2026). Una instalación
nueva no los necesita: `esquema.sql` y `datos_iniciales.sql` ya los incluyen.
Una base ya instalada, como la del servidor, sí: se aplican después del
`git pull`.
