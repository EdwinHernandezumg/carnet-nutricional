# Tipografía del Carnet Digital

Tipografía que usa el sistema Carnet Digital de Monitoreo Nutricional Infantil,
tomada de `public/css/estilos.css` y de las vistas de impresión.

## Fuente principal

```css
font-family: 'Segoe UI', Arial, sans-serif;
```

Es una **fuente del sistema**: el navegador usa la que ya trae el equipo, sin
descargar nada. Por eso cada equipo la muestra así:

| Equipo | Fuente que se ve |
|---|---|
| Computadora con Windows | Segoe UI |
| iPhone y Mac | Arial |
| Teléfono Android | Roboto, la fuente del sistema de Android |

**Por qué una fuente del sistema y no una de Google Fonts u otro servicio:**

- La página carga más rápido y no depende de un servicio externo, igual que
  los íconos, que van incrustados.
- Funciona igual con una conexión lenta.
- Las tres fuentes son muy legibles en pantallas pequeñas, y el sistema se usa
  sobre todo desde el teléfono.

## Escala de tamaños

El tamaño base es de **17 px**, un poco más grande que el habitual (16 px),
para leer con comodidad en el teléfono. El interlineado del texto es de 1.6.
Los demás tamaños son proporcionales a esa base (1 rem = 17 px).

| Elemento | Tamaño | Grosor |
|---|---|---|
| Nombre "Carnet Digital" en la pantalla de ingreso | 2.6 rem (44 px) | 800, extra negrita |
| Cifras de las tarjetas de Seguimiento | 2.4 rem (41 px) | 700, negrita |
| Título de la tarjeta de ingreso | 1.9 rem (32 px) | 700, negrita |
| Título de cada pantalla | 1.85 rem (31 px) | 700, negrita |
| Código de ingreso (6 números) | 1.7 rem (29 px) | normal, con números del mismo ancho |
| Nombre del sistema en la barra superior | 1.4 rem (24 px) | 700, negrita |
| Subtítulo de sección | 1.35 rem (23 px) | 600, seminegrita |
| Botones | 1.05 rem (18 px) | 600, seminegrita |
| Lema "Monitoreo Nutricional Infantil" | 1.05 rem (18 px) | 600, seminegrita |
| Texto normal | 1 rem (17 px) | 400, normal |
| Etiquetas de los campos y encabezados de tabla | 0.98 rem (17 px) | 600, seminegrita |
| Texto chico (notas y ayudas) | 0.92 rem (16 px) | 400, normal |
| Menú inferior del teléfono | 0.72 rem (12 px) | 600, seminegrita |

Los títulos grandes llevan un espaciado entre letras levemente negativo
(de -0.3 a -1 px), para que se vean compactos.

## Colores del texto

| Uso | Color |
|---|---|
| Títulos y la palabra "Carnet" | Azul marino `#14304a` |
| La palabra "Digital", botones y acentos | Verde azulado (teal) `#1d9e75` |
| Lema y enlaces | Teal oscuro `#0f6e56` |
| Texto normal | Gris oscuro `#2b3947` |
| Notas y textos secundarios | Gris suave `#6b7b8b` |

## Documentos para imprimir

Las páginas que se imprimen usan **Arial** (`Arial, Helvetica, sans-serif`),
porque es la fuente más común en impresoras y en los formatos del Ministerio:

- SIGSA 3 del día;
- Carnet para la familia;
- Reporte de indicadores para imprimir (PDF);
- Lista de seguimiento para imprimir.

## Logo

El logo (`docs/logo/`) usa la misma fuente principal, Segoe UI:

- "Carnet Digital" en negrita (700), con "Carnet" en azul marino y "Digital"
  en teal;
- el lema "Monitoreo Nutricional Infantil" en seminegrita (600), en teal
  oscuro.

En las versiones para fondos oscuros, "Carnet" va en blanco, "Digital" en
verde claro `#4fd1a5` y el lema en verde pálido `#cfeee2`.
