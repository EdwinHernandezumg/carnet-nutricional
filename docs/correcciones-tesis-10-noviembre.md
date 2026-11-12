# Correcciones de la tesis, 10 de noviembre de 2026

Revisión de los capítulos IV, V y VI, las conclusiones y las recomendaciones,
contra el sistema real y la guía de la universidad (22-08-2025).

Archivos, en `OneDrive\Documentos\Universidad\PG1 y 2\`:

- `PG ... - con control de cambios 10-nov.docx`: cada corrección marcada para
  revisarla en Word (Revisar → Aceptar todos).
- `PG ... - corregida 10-nov.docx`: los cambios ya aceptados, con el índice
  general, el de tablas y el de figuras actualizados. 191 páginas.
- `PG ... - corregida 10-nov.pdf`: la misma versión en PDF.

El original de `originales\` no se modificó.

## Formato

- Ningún párrafo de los capítulos IV a VI, conclusiones ni recomendaciones
  queda con menos de 6 ni con más de 10 líneas; ninguno se corta entre páginas.
  Medido con Word, párrafo por párrafo.
- Todos los párrafos del cuerpo tienen "Conservar líneas juntas" (Formato de
  párrafo → Líneas y saltos de página): si un cambio futuro los empuja, Word
  pasa el párrafo completo a la página siguiente.
- Los títulos de tablas y figuras se mantienen con su tabla o figura.
- CONCLUSIONES, GLOSARIO y ANEXOS empiezan en página nueva.
- El párrafo de 5.9.4 tenía el estilo de título de figura (letra pequeña):
  ahora tiene el formato del cuerpo.
- La guía pide de 3 a 5 líneas por conclusión y recomendación (página de
  recomendaciones generales); se siguieron las 6 a 10 líneas que pidió el
  desarrollador. Confirmar con el asesor si hay duda.

## Contenido corregido (lo que no coincidía con el sistema)

1. **6.10.2 decía que hubo pruebas con expedientes reales de 2025 y sesiones de
   usabilidad con "particular satisfacción".** No ocurrió. Se reescribió con lo
   real: visita del 16 de octubre (dos errores hallados y corregidos),
   capacitación del 21, encuesta SUS de 8 personas (promedio 68.1; Tabla 33),
   sugerencias atendidas (aplicación instalable) y cumplimiento de
   requerimientos (11 cumplidos y 3 parciales; Tabla 34).
2. **Clasificación del estado nutricional.** La tesis ponía "cerca del umbral"
   entre -2 y -3 y desnutrición bajo -3. El sistema clasifica por indicador
   según la OMS (Tabla 4) y alerta desnutrición bajo -2 y "cerca del umbral"
   entre -1 y -2. Corregido en 5.6.3, 5.9.4, 6.1, 6.7.3, 6.8.1 y el código.
3. **Código del motor (6.7.4)**: ahora es el real (`calcularZ` con
   interpolación y aplicación restringida, `clasificarPesoTalla`).
4. **Tecnologías que no se usan**: TCPDF, PhpSpreadsheet, DataTables, AES-256
   con OpenSSL y PHPMailer. Reemplazadas por lo real: impresión del navegador,
   generador propio de Excel, Chart.js, resúmenes SHA-256 y cliente SMTP
   propio (texto y Tabla 10).
5. **Motor de cálculo**: edad exacta en días, longitud o talla según la edad
   (no según cómo se midió) con la conversión de 0.7 cm, tablas de 0 a 5 años
   (6.6, 6.7).
6. **Tablas de la base de datos**: decía 13; son 19. El diccionario de datos
   se regeneró desde la base real (Tablas 14 a 32, cinco nuevas: rol,
   catalogo_vacuna, catalogo_suplemento, codigo_acceso, dispositivo_confianza).
   La Figura 13 se aclara como modelo inicial.
7. **Autenticación multifactor (RF-13)**: descrita como funciona (código de 6
   dígitos en dispositivo nuevo y siempre al administrador, 10 minutos, 5
   intentos, 90 días de confianza; recuperación por enlace de 30 minutos).
8. **Administrador "sin acceso a datos clínicos"**: no registra ni modifica
   datos clínicos, pero sí consulta reportes. Corregido.
9. **6.11 en tiempo futuro**: ahora en pasado con lo real (Hostinger Premium
   el 15 de octubre, producción el 22, ambiente de demostración, aplicación
   instalable, revisión de seguridad de PHP, capacitación del 21, visitas del
   29 de octubre y 5 de noviembre).
10. **Otros**: 12 → 14 requerimientos; SIGSA 3 es registro diario, no reporte
    mensual; Singleton por solicitud, no por sesión; método `obtener`, no
    `obtenerInstancia`; errores al archivo de registro, no a la bitácora;
    "Figura 8" → "Figura 5"; "Vision" → "Visión"; "stock" → "stack";
    "Concepcion"; SVM en lugar de "ZTE" (3.4.1); Tabla 4 con filas mal
    rotuladas (talla para la edad y peso para la talla); "y muchos más" y
    "y demás tablas" (coloquiales); 6.12 actualizado.

## Conclusiones y recomendaciones

- Siete conclusiones de 8 a 9 líneas, una por objetivo, con datos reales.
  Se reconoce con honestidad que el tiempo de registro no se midió (la
  planilla no se llenó) y que el impacto queda en etapa inicial.
- Cinco recomendaciones de 7 a 8 líneas, sin "Se recomienda" (lo prohíbe la guía), dirigidas a
  actores distintos: jefatura del puesto, personal, DDRISST, programadores y
  universidad.

## Agregados

- Tablas 33 (resultados SUS) y 34 (cumplimiento de requerimientos).
- Bibliografía: Bangor, Kortum y Miller (2009), Brooke (1996), Sauro (2011).
- Glosario: "Aplicación web progresiva" y "Escala de Usabilidad del Sistema".
- Anexo: las 8 encuestas escaneadas.

## Versión final (11 de noviembre de 2026)

La versión de 191 páginas se descartó por exceso de páginas y huecos. Las
correcciones se aplicaron sobre el archivo de trabajo de `originales\`,
párrafo por párrafo y medidas en Word, para conservar las líneas de cada
página. Resultado: 177 páginas. Cambios respecto a lo descrito arriba:

- El diccionario de datos no se regeneró (sumaba unas 8 páginas): 5.7.1 y
  5.7.2 aclaran que muestra el modelo inicial y que el esquema final tiene
  19 tablas. Las tablas nuevas son la 28 (SUS) y la 29 (requerimientos).
- Las encuestas escaneadas no se agregaron como anexo.
- Conclusiones en 2 hojas exactas; recomendaciones en 1 hoja (cuatro, sin
  "Se recomienda"); glosario en 3 hojas (se quitó RBAC, sin uso en el texto).
- CONCLUSIONES, RECOMENDACIONES y ANEXOS llevan "salto de página antes" en
  lugar de saltos manuales; las fuentes de la bibliografía, "conservar
  líneas juntas".

## Verificado el 11 de noviembre

- La constancia de entrega quedó firmada: el texto de 4.2.2 y 6.11.3 se
  mantiene.
- Costo real de Hostinger: 41.88 USD por 12 meses con dominio. Tablas 11 y
  12 y el texto de 4.5, a 7.70 quetzales por dólar: Q 322.48 al año, unos
  Q 27 al mes.
- El asesor es "MA. Ing." (como en la portada).
- Encuesta 2, afirmación 6, tiene dos marcas (1 y 3); se tomó el 3 (52.5
  puntos). Con el 1 serían 57.5 y el promedio 68.8. Decidido por el
  desarrollador el 11 de noviembre: queda con el 3 (promedio 68.1).
- Índice general actualizado por el desarrollador el 11 de noviembre. La
  tesis queda lista para imprimir y entregar en digital.
