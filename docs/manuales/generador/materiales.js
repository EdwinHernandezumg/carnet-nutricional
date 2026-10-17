// Genera docs/visita/materiales-prueba-16-octubre.docx: guía rápida, planilla,
// encuesta SUS y autorización del puesto para la visita del 16 de octubre de 2026.
const fs = require('fs');
const path = require('path');
const {
  Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell, WidthType,
  AlignmentType, BorderStyle, ShadingType, PageOrientation, LevelFormat,
  VerticalAlign, HeightRule,
} = require('docx');

const SALIDA = process.argv[2];
const AZUL = '1F3A5F';
const TEAL = '1A8C8C';
const GRIS = 'F2F4F7';
const FUENTE = 'Arial';

const borde = { style: BorderStyle.SINGLE, size: 4, color: '808080' };
const bordes = { top: borde, bottom: borde, left: borde, right: borde };
const sinBorde = { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' };

function t(texto, op = {}) {
  return new TextRun({ text: texto, font: FUENTE, size: op.size || 21, bold: op.bold, italics: op.italics, color: op.color });
}
function p(partes, op = {}) {
  const hijos = (Array.isArray(partes) ? partes : [partes]).map(x => typeof x === 'string' ? t(x, op) : x);
  return new Paragraph({ children: hijos, alignment: op.align, spacing: { before: op.before ?? 0, after: op.after ?? 80, line: op.line }, numbering: op.numbering, indent: op.indent, keepNext: op.keepNext, border: op.border });
}
function titulo(texto, sub) {
  const r = [p([t(texto, { size: 30, bold: true, color: AZUL })], { after: 40 })];
  if (sub) r.push(p([t(sub, { size: 19, color: '555555' })], { after: 120, border: { bottom: { style: BorderStyle.SINGLE, size: 8, color: TEAL, space: 4 } } }));
  return r;
}
function seccion(texto) {
  return p([t(texto, { size: 23, bold: true, color: TEAL })], { before: 140, after: 60, keepNext: true });
}
function celda(contenido, ancho, op = {}) {
  const parrafos = (Array.isArray(contenido) ? contenido : [contenido]).map(c =>
    c instanceof Paragraph ? c : p([t(String(c), { size: op.size || 18, bold: op.bold, color: op.color })], { align: op.align, after: 0 }));
  return new TableCell({
    children: parrafos, width: { size: ancho, type: WidthType.DXA }, borders: op.borders || bordes,
    shading: op.fondo ? { fill: op.fondo, type: ShadingType.CLEAR, color: 'auto' } : undefined,
    verticalAlign: op.valign || VerticalAlign.CENTER, margins: { top: 50, bottom: 50, left: 80, right: 80 },
    columnSpan: op.span,
  });
}
// Recuadro de una sola celda con fondo, para avisos.
function recuadro(parrafos, ancho, fondo, colorBorde) {
  const b = { style: BorderStyle.SINGLE, size: 8, color: colorBorde };
  return new Table({
    width: { size: ancho, type: WidthType.DXA }, columnWidths: [ancho],
    rows: [new TableRow({ children: [new TableCell({
      children: parrafos, width: { size: ancho, type: WidthType.DXA },
      borders: { top: b, bottom: b, left: b, right: b },
      shading: { fill: fondo, type: ShadingType.CLEAR, color: 'auto' },
      margins: { top: 100, bottom: 100, left: 160, right: 160 },
    })] })],
  });
}
const linea = (texto, op = {}) => p([t(texto, op)], { after: op.after ?? 60 });
const espacio = (n = 60) => p([t('')], { after: n });

const CARTA = { width: 12240, height: 15840 };
const margenes = { top: 1000, bottom: 900, left: 1080, right: 1080 };
const ANCHO = CARTA.width - margenes.left - margenes.right; // 10080

const numeracion = {
  config: [
    { reference: 'pasos', levels: [{ level: 0, format: LevelFormat.DECIMAL, text: '%1.', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 400, hanging: 300 } }, run: { font: FUENTE, bold: true, color: TEAL } } }] },
    { reference: 'vinetas', levels: [{ level: 0, format: LevelFormat.BULLET, text: '•', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 400, hanging: 260 } } } }] },
    { reference: 'condiciones', levels: [{ level: 0, format: LevelFormat.LOWER_LETTER, text: '%1)', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 500, hanging: 320 } } } }] },
  ],
};
const paso = (partes) => p(partes, { numbering: { reference: 'pasos', level: 0 }, after: 70 });
const vineta = (partes) => p(partes, { numbering: { reference: 'vinetas', level: 0 }, after: 40 });
const b = (texto) => t(texto, { bold: true });

// ---------------------------------------------------------------- Guía rápida
const guia = [
  ...titulo('Guía rápida para probar el Carnet Digital', 'Puesto de Salud de Chivarreto  ·  Prueba del 16 de octubre de 2026  ·  Una hoja por persona'),
  seccion('Para entrar'),
  new Table({
    width: { size: ANCHO, type: WidthType.DXA }, columnWidths: [2600, 7480],
    rows: [
      ['Dirección', 'carnetchivarreto.com   (en Chrome, desde el teléfono)'],
      ['Usuario', ''],
      ['Contraseña temporal', ''],
    ].map(([a, v]) => new TableRow({ height: { value: 480, rule: HeightRule.ATLEAST }, children: [
      celda(a, 2600, { bold: true, fondo: GRIS, size: 20 }), celda(v, 7480, { size: 20 })] })),
  }),
  p([t('La primera vez, el sistema le pide cambiar la contraseña por una suya: mínimo 8 caracteres, con letras y números. Anótela en un lugar seguro.', { size: 19 })], { before: 60, after: 100 }),
  recuadro([
    p([t('Muy importante: los niños se registran con nombres inventados', { bold: true, color: '9A3412', size: 22 })], { after: 60 }),
    vineta([t('No escriba el nombre real del niño ni de la madre, ni el CUI, ni el teléfono, ni la dirección.')]),
    vineta([t('Sí copie del carnet o del cuaderno, tal como están: '), b('sexo, fecha de nacimiento, fecha de la toma, peso y talla'), t('. Con eso el sistema calcula el estado nutricional.')]),
    vineta([t('Es un sistema de prueba: al terminar la validación, estos datos se borran.')]),
  ], ANCHO, 'FFF4E5', 'E8A33D'),
  seccion('Qué hacer con cada niño'),
  paso([t('Toque '), b('Registrar'), t(' en la barra de abajo. Llene los datos con un nombre inventado y la fecha de nacimiento real. Elija el sector y el paraje. Toque '), b('Guardar'), t('.')]),
  paso([t('En la pantalla siguiente toque '), b('Registrar medición'), t('. Escriba la fecha de la toma, el peso en kilos, la talla en centímetros y cómo se midió: acostado o de pie. Toque '), b('Guardar medición'), t('.')]),
  paso([t('Mire el resultado: la clasificación y, si aparece, la alerta. Abra también las gráficas del niño.')]),
  paso([t('Anote en la '), b('planilla'), t(': lo que dice el carnet, lo que dice el sistema y cuánto tardó.')]),
  paso([t('Si le da tiempo, pruebe también: '), b('Registrar vacuna'), t(', '), b('Programar cita'), t(', la pantalla '), b('Seguimiento'), t(' (los niños en riesgo) y el '), b('Carnet para la familia'), t(' desde el historial del niño.')]),
  seccion('Cómo medir el tiempo'),
  p([t('Con el cronómetro del teléfono: empiece al tocar '), b('Registrar'), t(' y termine al tocar '), b('Guardar medición'), t('. Anote los minutos en la planilla.')]),
  seccion('Si algo falla o no se entiende'),
  p([t('Anótelo en la columna '), b('Observaciones'), t(' de la planilla: eso también es un resultado valioso. Puede llamar o escribir a Wilson Hernández al teléfono: ______________________.')]),
  seccion('Al terminar'),
  p([t('Llene la '), b('encuesta'), t(' de una hoja. No lleva su nombre. Muchas gracias por su ayuda.')]),
];

// -------------------------------------------------------------------- Planilla
const CARTA_H = { width: CARTA.width, height: CARTA.height };
const margenesH = { top: 720, bottom: 720, left: 720, right: 720 };
const ANCHO_H = CARTA.height - margenesH.left - margenesH.right; // 14400
const colsPlanilla = [
  ['No.', 400], ['Nombre inventado', 1500], ['Sexo\nM / F', 600], ['Fecha de\nnacimiento', 1200],
  ['Fecha de\nla toma', 1200], ['Peso\n(kg)', 800], ['Talla\n(cm)', 800], ['Acostado o\nde pie', 1000],
  ['Lo que dice el\ncarnet o cuaderno', 1800], ['Lo que dice\nel sistema', 1800], ['¿Igual?\nSí / No', 700],
  ['Tiempo\n(min)', 800], ['Observaciones', 1800],
];
const encabezadoCelda = (texto, ancho) => celda(texto.split('\n').map(l => p([t(l, { size: 16, bold: true, color: 'FFFFFF' })], { align: AlignmentType.CENTER, after: 0 })), ancho, { fondo: AZUL });
const planilla = [
  p([t('Planilla de prueba del Carnet Digital', { size: 28, bold: true, color: AZUL }), t('    Puesto de Salud de Chivarreto  ·  16 de octubre de 2026', { size: 19, color: '555555' })], { after: 60 }),
  p([t('Enfermera o enfermero: ____________________________________     Sector: ________     Hoja ____ de ____', { size: 19 })], { after: 60 }),
  p([t('Una fila por cada niño que registre. ', { size: 17 }), t('Lo que dice el carnet', { size: 17, bold: true }), t(': el estado que marca el carnet, la gráfica o el cuaderno (por ejemplo: normal, bajo peso, desnutrición). ', { size: 17 }), t('Lo que dice el sistema', { size: 17, bold: true }), t(': la clasificación o alerta que mostró. Nombres inventados; las medidas, tal como están en el carnet.', { size: 17 })], { after: 100 }),
  new Table({
    width: { size: ANCHO_H, type: WidthType.DXA }, columnWidths: colsPlanilla.map(c => c[1]),
    rows: [
      new TableRow({ tableHeader: true, children: colsPlanilla.map(([n, a]) => encabezadoCelda(n, a)) }),
      ...Array.from({ length: 12 }, (_, i) => new TableRow({ height: { value: 640, rule: HeightRule.EXACT }, children:
        colsPlanilla.map(([, a], j) => celda(j === 0 ? String(i + 1) : '', a, { align: AlignmentType.CENTER, size: 18, color: '808080' })) })),
    ],
  }),
];

// --------------------------------------------------------------------- Encuesta
const afirmaciones = [
  'Me gustaría usar este sistema con frecuencia.',
  'El sistema me pareció más complicado de lo necesario.',
  'El sistema fue fácil de usar.',
  'Necesitaría la ayuda de una persona experta para poder usar este sistema.',
  'Las distintas partes del sistema funcionan bien juntas.',
  'El sistema tiene demasiadas cosas que no son coherentes entre sí.',
  'Creo que la mayoría de las personas aprendería a usar este sistema muy rápido.',
  'El sistema me pareció muy difícil o pesado de usar.',
  'Me sentí con confianza al usar el sistema.',
  'Tuve que aprender muchas cosas antes de poder usar este sistema.',
];
const colsSus = [450, 6830, 560, 560, 560, 560, 560];
const encuesta = [
  ...titulo('Encuesta: ¿qué tan fácil fue usar el sistema?', 'Carnet Digital de Monitoreo Nutricional Infantil  ·  Puesto de Salud de Chivarreto  ·  16 de octubre de 2026'),
  p([t('No escriba su nombre. Marque con una X un solo número en cada fila.', { size: 19 })], { after: 40 }),
  p([t('1 = totalmente en desacuerdo        5 = totalmente de acuerdo', { size: 19, bold: true })], { after: 80 }),
  p([t('Puesto:   ☐ Enfermera(o)   ☐ Auxiliar de enfermería   ☐ Nutricionista   ☐ Otro: ______________', { size: 19 })], { after: 100 }),
  new Table({
    width: { size: ANCHO, type: WidthType.DXA }, columnWidths: colsSus,
    rows: [
      new TableRow({ tableHeader: true, children: [encabezadoCelda('', colsSus[0]), encabezadoCelda('Afirmación', colsSus[1]), ...[1, 2, 3, 4, 5].map((n, k) => encabezadoCelda(String(n), colsSus[k + 2]))] }),
      ...afirmaciones.map((a, i) => new TableRow({ height: { value: 470, rule: HeightRule.ATLEAST }, children: [
        celda(String(i + 1), colsSus[0], { align: AlignmentType.CENTER, size: 19, bold: true, fondo: i % 2 ? undefined : GRIS }),
        celda(a, colsSus[1], { size: 19, fondo: i % 2 ? undefined : GRIS }),
        ...[1, 2, 3, 4, 5].map((n, k) => celda('', colsSus[k + 2], { fondo: i % 2 ? undefined : GRIS })),
      ] })),
    ],
  }),
  p([t('Comparado con el carnet y el cuaderno en papel, registrar a un niño en el sistema fue:', { size: 19, bold: true })], { before: 160, after: 60 }),
  p([t('☐ Más rápido        ☐ Igual        ☐ Más lento', { size: 19 })], { after: 100 }),
  ...['¿Qué fue lo más útil del sistema?', '¿Qué fue lo más difícil o lo que no se entendió?', '¿Qué le cambiaría o le agregaría?'].flatMap(q => [
    p([t(q, { size: 19, bold: true })], { before: 80, after: 40 }),
    p([t('______________________________________________________________________________________', { size: 19, color: '999999' })], { after: 40 }),
    p([t('______________________________________________________________________________________', { size: 19, color: '999999' })], { after: 40 }),
  ]),
  p([t('Escala de usabilidad del sistema (SUS), Brooke, 1996, en versión en español adaptada.', { size: 15, italics: true, color: '777777' })], { before: 120, after: 0 }),
];

// ------------------------------------------------------------------ Autorización
const condicion = (texto) => p([t(texto)], { numbering: { reference: 'condiciones', level: 0 }, after: 80, line: 300 });
const autorizacion = [
  ...titulo('Autorización para la prueba del sistema', 'Carnet Digital de Monitoreo Nutricional Infantil'),
  p([t('Yo, _____________________________________________, en mi calidad de _______________________________ del Puesto de Salud de la aldea Chivarreto, San Francisco El Alto, Totonicapán, autorizo a Wilson Edwin Hernández Maldonado, carné 1490-16-14973, estudiante de Ingeniería en Sistemas de Información de la Universidad Mariano Gálvez de Guatemala, a realizar el 16 de octubre de 2026 una prueba del sistema Carnet Digital de Monitoreo Nutricional Infantil con el personal del puesto, como parte de su trabajo de graduación, en las condiciones siguientes:')], { before: 120, after: 140, line: 320 }),
  condicion('Solo se usan la fecha de nacimiento, el sexo, la fecha de la toma y las medidas de peso y talla anotadas en carnets o cuadernos del puesto. No se registran nombres, CUI, teléfonos ni direcciones de los niños ni de sus familias: los nombres son inventados.'),
  condicion('No se toman fotografías de carnets, cuadernos ni documentos con datos de los niños.'),
  condicion('Los datos se ingresan en un ambiente de prueba del sistema y se eliminan al terminar la validación.'),
  condicion('Los resultados se usan únicamente para el trabajo de graduación y se presentan de forma agregada, sin identificar a ningún niño.'),
  condicion('El personal participa de forma voluntaria y la encuesta de opinión es anónima.'),
  espacio(500),
  new Table({
    width: { size: ANCHO, type: WidthType.DXA }, columnWidths: [4840, 400, 4840],
    rows: [new TableRow({ children: [
      celda([p([t('')], { after: 700 }), p([t('Firma', { size: 19 })], { align: AlignmentType.CENTER, border: { top: { style: BorderStyle.SINGLE, size: 6, color: '000000', space: 4 } }, after: 40 }), p([t('Nombre: _____________________________', { size: 19 })], { after: 60 }), p([t('Cargo: ______________________________', { size: 19 })], { after: 60 }), p([t('Fecha: ______________________________', { size: 19 })], { after: 0 })], 4840, { borders: { top: sinBorde, bottom: sinBorde, left: sinBorde, right: sinBorde } }),
      celda('', 400, { borders: { top: sinBorde, bottom: sinBorde, left: sinBorde, right: sinBorde } }),
      celda([p([t('Sello del puesto', { size: 19, color: '999999' })], { align: AlignmentType.CENTER, after: 0 })], 4840, { valign: VerticalAlign.CENTER }),
    ] })],
  }),
  espacio(500),
  p([t('Estudiante: Wilson Edwin Hernández Maldonado          Firma: _________________________', { size: 19 })], { after: 0 }),
];

const propiedades = (horizontal) => ({
  page: horizontal
    ? { size: { width: CARTA_H.width, height: CARTA_H.height, orientation: PageOrientation.LANDSCAPE }, margin: margenesH }
    : { size: CARTA, margin: margenes },
});

const doc = new Document({
  creator: 'Wilson Edwin Hernández Maldonado',
  title: 'Materiales para la prueba del Carnet Digital, 16 de octubre de 2026',
  styles: { default: { document: { run: { font: FUENTE, size: 21 } } } },
  numbering: numeracion,
  sections: [
    { properties: propiedades(false), children: guia },
    { properties: propiedades(true), children: planilla },
    { properties: propiedades(false), children: encuesta },
    { properties: propiedades(false), children: autorizacion },
  ],
});

Packer.toBuffer(doc).then(buf => {
  fs.mkdirSync(path.dirname(SALIDA), { recursive: true });
  fs.writeFileSync(SALIDA, buf);
  console.log('Escrito:', SALIDA, buf.length, 'bytes');
});
