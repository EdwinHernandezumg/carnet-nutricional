// Genera docs/visita/materiales-capacitacion-21-octubre.docx: agenda de la
// capacitación, guía rápida, planilla de tiempos, lista de asistencia,
// constancia de recepción de materiales y acta de entrega del sistema.
// Capacitación del 21 de octubre y entrega del 22 de octubre de 2026.
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
const sinBordes = { top: sinBorde, bottom: sinBorde, left: sinBorde, right: sinBorde };

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
  return p([t(texto, { size: 22, bold: true, color: TEAL })], { before: 110, after: 50, keepNext: true });
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
      margins: { top: 90, bottom: 90, left: 160, right: 160 },
    })] })],
  });
}
const espacio = (n = 60) => p([t('')], { after: n });

const CARTA = { width: 12240, height: 15840 };
const margenes = { top: 900, bottom: 800, left: 1000, right: 1000 };
const ANCHO = CARTA.width - margenes.left - margenes.right; // 10240
const margenesH = { top: 720, bottom: 720, left: 720, right: 720 };
const ANCHO_H = CARTA.height - margenesH.left - margenesH.right; // 14400

const numeracion = {
  config: [
    { reference: 'pasos', levels: [{ level: 0, format: LevelFormat.DECIMAL, text: '%1.', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 400, hanging: 300 } }, run: { font: FUENTE, bold: true, color: TEAL } } }] },
    { reference: 'vinetas', levels: [{ level: 0, format: LevelFormat.BULLET, text: '•', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 400, hanging: 260 } } } }] },
    { reference: 'acta', levels: [{ level: 0, format: LevelFormat.DECIMAL, text: '%1.', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 500, hanging: 340 } }, run: { font: FUENTE, bold: true } } }] },
  ],
};
const paso = (partes, op = {}) => p(partes, { numbering: { reference: 'pasos', level: 0 }, after: op.after ?? 50, ...op });
const vineta = (partes, op = {}) => p(partes, { numbering: { reference: 'vinetas', level: 0 }, after: op.after ?? 30, ...op });
const b = (texto, op = {}) => t(texto, { bold: true, ...op });
const encabezadoCelda = (texto, ancho, size = 17) => celda(texto.split('\n').map(l => p([t(l, { size, bold: true, color: 'FFFFFF' })], { align: AlignmentType.CENTER, after: 0 })), ancho, { fondo: AZUL });

// Tabla de dos columnas sin bordes (pares de "etiqueta: valor" o columnas de texto).
function dosColumnas(izquierda, derecha, anchoIzq) {
  return new Table({
    width: { size: ANCHO, type: WidthType.DXA }, columnWidths: [anchoIzq, ANCHO - anchoIzq],
    rows: [new TableRow({ children: [
      new TableCell({ children: izquierda, width: { size: anchoIzq, type: WidthType.DXA }, borders: sinBordes, margins: { right: 200 } }),
      new TableCell({ children: derecha, width: { size: ANCHO - anchoIzq, type: WidthType.DXA }, borders: sinBordes, margins: { left: 200 } }),
    ] })],
  });
}

// ------------------------------------------------------------------- Agenda
const bloques = [
  ['5 min', 'Bienvenida', 'Para qué sirve el sistema: detectar a tiempo a los niños que se acercan a la desnutrición, que en la gráfica de papel no se distinguen. Hoy se practica con niños inventados; el jueves se borran y empieza el trabajo real.'],
  ['10 min', 'Entrar desde el teléfono', 'Cada persona entra con su usuario en carnetchivarreto.com desde Chrome. Se registra su correo en Usuarios y escribe el código que le llega (una sola vez en ese teléfono). Se guarda la contraseña en Chrome para entrar con la huella.'],
  ['20 min', 'El trabajo de cada día', 'Buscar al niño por nombre o número de carnet. Registrar un niño inventado. Registrar su medición y ver la clasificación. Registrar varias vacunas a la vez con su lote. Entregar un suplemento. Programar la cita y avisar por WhatsApp.'],
  ['5 min', 'Seguimiento y alertas', 'Qué significa cada color: desnutrición, cerca del umbral, riesgo y sobrepeso. Las gráficas de crecimiento del niño.'],
  ['5 min', 'Corregir un error', 'Corregir y anular un registro con su motivo, como la línea roja del carnet. En un mes anterior se pide la referencia del conocimiento.'],
  ['15 min', 'Práctica con tiempo', 'Cada persona registra un niño inventado en papel y en el sistema, y anota los dos tiempos en la planilla. Al terminar, la encuesta.'],
  ['5 min', 'Preguntas', 'Dudas, sugerencias y entrega de la guía rápida. Recordar: si olvida la contraseña, "¿Olvidó su contraseña?" en la pantalla de ingreso.'],
];
const agenda = [
  ...titulo('Capacitación en el uso del Carnet Digital', 'Puesto de Salud de Chivarreto  ·  21 de octubre de 2026  ·  Duración: 1 hora, aproximadamente'),
  p([b('Objetivo: '), t('que cada persona del personal de enfermería pueda registrar, consultar y corregir la información nutricional y de vacunación de un niño desde su teléfono, y sepa qué hacer si olvida su contraseña.')], { after: 100, line: 290 }),
  new Table({
    width: { size: ANCHO, type: WidthType.DXA }, columnWidths: [1100, 2300, 6840],
    rows: [
      new TableRow({ tableHeader: true, children: [encabezadoCelda('Tiempo', 1100), encabezadoCelda('Tema', 2300), encabezadoCelda('Qué se hace', 6840)] }),
      ...bloques.map(([min, tema, que], i) => new TableRow({ children: [
        celda(min, 1100, { align: AlignmentType.CENTER, size: 18, bold: true, fondo: i % 2 ? undefined : GRIS }),
        celda(tema, 2300, { size: 18, bold: true, fondo: i % 2 ? undefined : GRIS }),
        celda(que, 6840, { size: 18, fondo: i % 2 ? undefined : GRIS }),
      ] })),
    ],
  }),
  seccion('Antes de empezar (lista del capacitador)'),
  vineta([t('Guías rápidas impresas, una por persona, y planillas y encuestas.')]),
  vineta([t('Lista de asistencia para que cada persona escriba su correo y firme; con el correo se completa su usuario en el sistema.')]),
  vineta([t('Teléfono propio con datos o wifi, cargado; la computadora del puesto encendida.')]),
  vineta([t('Pedir permiso antes de tomar fotos de la capacitación. No fotografiar carnets ni cuadernos con datos de niños.')]),
  vineta([t('Usar solo niños inventados: el jueves 22, antes de la entrega, esos datos se borran.')]),
  seccion('Evidencia para el trabajo de graduación'),
  vineta([t('Lista de asistencia firmada y fotos de la sesión (con permiso).')]),
  vineta([t('Planillas de tiempo (papel contra sistema) y encuestas de usabilidad (SUS), llenas y anónimas.')]),
  vineta([t('Preguntas y sugerencias del personal, anotadas al final de la sesión.')]),
];

// ------------------------------------------------------------- Guía rápida
const guia1 = [
  ...titulo('Guía rápida del Carnet Digital', 'Para el personal de enfermería  ·  Puesto de Salud de Chivarreto  ·  Versión del 21 de octubre de 2026'),
  seccion('1. Entrar al sistema'),
  new Table({
    width: { size: ANCHO, type: WidthType.DXA }, columnWidths: [2600, 7640],
    rows: [
      ['Dirección', 'carnetchivarreto.com   (en Chrome, desde el teléfono)'],
      ['Mi usuario', ''],
      ['Contraseña temporal', ''],
    ].map(([a, v]) => new TableRow({ height: { value: 440, rule: HeightRule.ATLEAST }, children: [
      celda(a, 2600, { bold: true, fondo: GRIS, size: 19 }), celda(v, 7640, { size: 19 })] })),
  }),
  paso([t('La primera vez, el sistema pide '), b('cambiar la contraseña'), t(' por una suya: mínimo 8 caracteres, con letras y números. Toque '), b('Guardar y continuar'), t('.')], { before: 80 }),
  paso([t('Si entra desde un teléfono nuevo, le llega un '), b('código de 6 números a su correo'), t(' (también en el asunto del correo). Escríbalo y toque '), b('Confirmar'), t('. Deje marcada la casilla '), b('"No volver a pedirlo en este dispositivo por 90 días"'), t(' si el teléfono es suyo.')]),
  paso([t('Cuando Chrome pregunte si quiere '), b('guardar la contraseña'), t(', toque Guardar. La próxima vez, Chrome la llena sola con su huella.')]),
  paso([t('Si no usa el sistema por '), b('20 minutos'), t(', se cierra solo para proteger los datos. Vuelva a entrar.')]),
  paso([t('¿Olvidó su contraseña? En la pantalla de ingreso toque '), b('"¿Olvidó su contraseña?"'), t(', escriba su usuario y abra el enlace que le llega al correo.')]),
  seccion('2. La barra de abajo'),
  new Table({
    width: { size: ANCHO, type: WidthType.DXA }, columnWidths: [1700, 8540],
    rows: [
      ['Niños', 'Buscar a un niño por su nombre o por su número de carnet. Es la pantalla de inicio.'],
      ['Seguimiento', 'Los niños con alerta en su último control, del más grave al menos grave.'],
      ['Registrar', 'Registrar a un niño nuevo (menor de 7 años).'],
      ['Citas', 'El calendario de citas y la lista de las próximas.'],
      ['Reportes', 'Indicadores del puesto, el SIGSA 3 del día y las exportaciones a PDF, Excel y CSV.'],
    ].map(([a, v], i) => new TableRow({ children: [
      celda(a, 1700, { bold: true, size: 18, fondo: i % 2 ? undefined : GRIS }), celda(v, 8540, { size: 18, fondo: i % 2 ? undefined : GRIS })] })),
  }),
  seccion('3. Los colores de las alertas'),
  new Table({
    width: { size: ANCHO, type: WidthType.DXA }, columnWidths: [2600, 7640],
    rows: [
      ['Desnutrición', 'FDECEA', 'A4231A', 'Aguda (peso bajo para su talla) o crónica (talla baja para su edad). Atender primero.'],
      ['Riesgo (bajo peso)', 'FDEBD9', '9A3412', 'Peso bajo para su edad.'],
      ['Cerca del umbral', 'FFF3CD', '7A5900', 'Todavía no es desnutrición, pero se está acercando. Es lo que en la gráfica de papel no se ve.'],
      ['Sobrepeso', 'EEF7F3', '0F6E56', 'Peso alto para su talla.'],
    ].map(([a, fondo, color, v]) => new TableRow({ children: [
      celda(a, 2600, { bold: true, size: 18, fondo, color }), celda(v, 7640, { size: 18 })] })),
  }),
];

const guia2 = [
  seccion('4. Con un niño que llegó al puesto'),
  paso([t('Toque '), b('Niños'), t(' y búsquelo por nombre o número de carnet. Si no está, toque '), b('Registrar'), t(', llene sus datos y toque '), b('Registrar niño'), t('.')]),
  paso([t('En su historial, toque '), b('Nueva medición'), t(': fecha de la toma, peso en kilos, talla en centímetros y si se midió acostado o de pie. Toque '), b('Guardar medición'), t('. El sistema muestra la clasificación y la alerta, si la hay.')]),
  paso([t('Para las vacunas, toque '), b('Registrar vacunas'), t('. Ponga la fecha y el lugar una sola vez, marque las dosis que se pusieron y escriba el lote de cada una. Toque '), b('Guardar'), t('.')]),
  paso([t('Para un suplemento, toque '), b('Registrar suplemento'), t(', elíjalo de la lista y escriba la cantidad entregada.')]),
  paso([t('Para la próxima visita, toque '), b('Programar cita'), t('. El sistema avisa si ese día ya hay muchas citas. Con '), b('Avisar por WhatsApp'), t(' le manda el recordatorio a la madre.')]),
  seccion('5. Las vacunas, en colores'),
  p([t('En el historial, '), b('"Ver esquema de vacunación completo"'), t(' muestra cada dosis del esquema 2026:')], { after: 40 }),
  vineta([b('Aplicada (verde azulado): '), t('ya está registrada.')]),
  vineta([b('Pendiente (naranja): '), t('por su edad ya le tocaba y no está registrada. Revise el carnet o póngasela.')]),
  vineta([b('Próxima (gris): '), t('todavía no le toca por su edad.')]),
  vineta([b('Cubierta (azul): '), t('se la cubre una dosis del carnet de 2018 (por ejemplo, Pentavalente en lugar de Hexavalente). No se repite; revise el carnet.')]),
  seccion('6. Si se equivocó'),
  p([t('Nada se borra. En el historial, junto al registro, toque '), b('Corregir'), t(' (vacuna, suplemento o cita) o '), b('Anular'), t(' (cualquier registro, también una medición). Escriba el motivo. Lo anulado queda tachado, como la línea roja del carnet, y deja de contar en las alertas y los reportes.')], { line: 280 }),
  recuadro([
    p([b('Regla del mes, como en SIGSA Web: ', { size: 19 }), t('durante el mes de la atención basta el motivo. Si la atención es de un mes anterior, se pide además la referencia del conocimiento.', { size: 19 })], { after: 0 }),
  ], ANCHO, 'EEF7F3', TEAL),
  seccion('7. Otras cosas útiles'),
  vineta([b('Ver gráficas de crecimiento: '), t('el recorrido del niño frente a las líneas de la OMS.')]),
  vineta([b('Carnet para la familia: '), t('copia del carnet en dos hojas, para imprimir o enviar como PDF por WhatsApp. Lleva firma y sello del puesto.')]),
  vineta([b('SIGSA 3 del día '), t('(en Reportes): la hoja del registro diario ya llena con los niños atendidos.')]),
  vineta([b('Para practicar: '), t('pruebas.carnetchivarreto.com tiene niños inventados (franja amarilla arriba). Pida su cuenta al administrador y pruebe sin miedo.')]),
  espacio(40),
  p([t('Dudas o problemas: Wilson Hernández, teléfono ______________________.   Manual completo y video: se envían por WhatsApp.', { size: 18, italics: true, color: '555555' })], { after: 0 }),
];

// ----------------------------------------------------------------- Planilla
const colsPlanilla = [
  ['No.', 500], ['Nombre inventado\ndel niño', 2200], ['Qué se registró\n(datos, medición, vacunas...)', 3200],
  ['Tiempo en\npapel (min)', 1300], ['Tiempo en el\nsistema (min)', 1300], ['¿Le fue más\nfácil? Sí / No', 1300], ['Observaciones', 4600],
];
const planilla = [
  p([t('Planilla de tiempos: papel contra sistema', { size: 28, bold: true, color: AZUL }), t('    Puesto de Salud de Chivarreto  ·  21 de octubre de 2026', { size: 19, color: '555555' })], { after: 60 }),
  p([t('Enfermera o enfermero: ____________________________________     Sector: ________     Hoja ____ de ____', { size: 19 })], { after: 60 }),
  p([t('Registre al mismo niño inventado primero en el carnet y el cuaderno (papel) y después en el sistema. Mida cada uno con el cronómetro del teléfono: en papel, desde que empieza a escribir hasta que termina; en el sistema, desde que toca Registrar hasta que guarda la medición o las vacunas.', { size: 17 })], { after: 100 }),
  new Table({
    width: { size: ANCHO_H, type: WidthType.DXA }, columnWidths: colsPlanilla.map(c => c[1]),
    rows: [
      new TableRow({ tableHeader: true, children: colsPlanilla.map(([n, a]) => encabezadoCelda(n, a)) }),
      ...Array.from({ length: 10 }, (_, i) => new TableRow({ height: { value: 640, rule: HeightRule.EXACT }, children:
        colsPlanilla.map(([, a], j) => celda(j === 0 ? String(i + 1) : '', a, { align: AlignmentType.CENTER, size: 18, color: '808080' })) })),
    ],
  }),
];

// ------------------------------------------------------- Lista de asistencia
const colsAsistencia = [
  ['No.', 500], ['Nombre completo', 3600], ['Cargo', 1900], ['Sector', 1100],
  ['Correo electrónico\n(para su usuario)', 3400], ['¿Acepta salir\nen fotos?', 1200], ['Firma', 2700],
];
const asistencia = [
  p([t('Lista de asistencia: capacitación en el uso del Carnet Digital', { size: 26, bold: true, color: AZUL })], { after: 40 }),
  p([t('Puesto de Salud de la aldea Chivarreto, San Francisco El Alto, Totonicapán   ·   Fecha: 21 de octubre de 2026   ·   Hora: ________   ·   Capacitador: Wilson Edwin Hernández Maldonado', { size: 18, color: '555555' })], { after: 60 }),
  p([t('El correo se usa solo para que el sistema le envíe el código de ingreso y el enlace para recuperar la contraseña. Las fotos de la capacitación se usan únicamente en el trabajo de graduación.', { size: 16, italics: true, color: '555555' })], { after: 80 }),
  new Table({
    width: { size: ANCHO_H, type: WidthType.DXA }, columnWidths: colsAsistencia.map(c => c[1]),
    rows: [
      new TableRow({ tableHeader: true, children: colsAsistencia.map(([n, a]) => encabezadoCelda(n, a, 16)) }),
      ...Array.from({ length: 16 }, (_, i) => new TableRow({ height: { value: 500, rule: HeightRule.EXACT }, children:
        colsAsistencia.map(([, a], j) => celda(j === 0 ? String(i + 1) : (j === 5 ? 'Sí    No' : ''), a, { align: AlignmentType.CENTER, size: 17, color: '808080' })) })),
    ],
  }),
];

// --------------------------------------------------- Constancia de recepción
const colsRecepcion = [
  ['No.', 500], ['Nombre completo', 3600], ['Cargo', 2000],
  ['Material recibido\n(marque con X)', 4300], ['Fecha', 1300], ['Firma', 2700],
];
const recepcion = [
  p([t('Constancia de recepción de materiales del Carnet Digital', { size: 26, bold: true, color: AZUL })], { after: 40 }),
  p([t('Puesto de Salud de la aldea Chivarreto, San Francisco El Alto, Totonicapán   ·   Entregó: Wilson Edwin Hernández Maldonado, estudiante de Ingeniería en Sistemas, Universidad Mariano Gálvez de Guatemala', { size: 18, color: '555555' })], { after: 100 }),
  new Table({
    width: { size: ANCHO_H, type: WidthType.DXA }, columnWidths: colsRecepcion.map(c => c[1]),
    rows: [
      new TableRow({ tableHeader: true, children: colsRecepcion.map(([n, a]) => encabezadoCelda(n, a, 16)) }),
      ...Array.from({ length: 15 }, (_, i) => new TableRow({ height: { value: 560, rule: HeightRule.EXACT }, children:
        colsRecepcion.map(([, a], j) => celda(j === 0 ? String(i + 1) : (j === 3 ? '☐ Guía rápida   ☐ Manual impreso   ☐ PDF y video' : ''), a, { align: j === 3 ? AlignmentType.LEFT : AlignmentType.CENTER, size: 16, color: j === 3 ? '333333' : '808080' })) })),
    ],
  }),
];

// --------------------------------------------------------- Acta de entrega
const itemActa = (partes) => p(partes, { numbering: { reference: 'acta', level: 0 }, after: 70, line: 290 });
const firma = (rotulo, lineas) => [
  p([t('')], { after: 650 }),
  p([t(rotulo, { size: 19, bold: true })], { align: AlignmentType.CENTER, border: { top: { style: BorderStyle.SINGLE, size: 6, color: '000000', space: 4 } }, after: 40 }),
  ...lineas.map(l => p([t(l, { size: 18 })], { align: AlignmentType.CENTER, after: 20 })),
];
const acta = [
  ...titulo('Acta de entrega del sistema Carnet Digital de Monitoreo Nutricional Infantil', 'Puesto de Salud de la aldea Chivarreto, San Francisco El Alto, Totonicapán'),
  p([t('En la aldea Chivarreto, municipio de San Francisco El Alto, departamento de Totonicapán, el 22 de octubre de 2026, se reúnen Wilson Edwin Hernández Maldonado, carné 1490-16-14973, estudiante de Ingeniería en Sistemas de Información de la Universidad Mariano Gálvez de Guatemala, y _____________________________________, ________________________________ del Puesto de Salud de Chivarreto, para hacer constar la entrega del sistema desarrollado como trabajo de graduación, en los términos siguientes:')], { after: 120, line: 300 }),
  itemActa([b('Sistema entregado. '), t('Carnet Digital de Monitoreo Nutricional Infantil, en funcionamiento en la dirección https://carnetchivarreto.com. Registra a los niños menores de 7 años, sus controles de crecimiento con la puntuación Z según los patrones de la OMS, las alertas tempranas de desnutrición, la vacunación según el esquema 2026, la suplementación y las citas; permite corregir y anular registros con su motivo; imprime el carnet para la familia y la hoja del SIGSA 3; y presenta el panel de indicadores con exportación a PDF, Excel y CSV.')]),
  itemActa([b('Ambiente de demostración. '), t('https://pruebas.carnetchivarreto.com, con datos ficticios, para práctica del personal y demostración. No contiene datos reales.')]),
  itemActa([b('Datos. '), t('Antes de esta entrega se borraron los datos inventados durante la capacitación. Desde esta fecha, la información que se registre corresponde al puesto de salud; el estudiante no la usará fuera del trabajo de graduación, y en este solo de forma agregada, sin identificar a ningún niño.')]),
  itemActa([b('Cuentas de usuario. '), t('Se crearon las cuentas del personal según sus roles (administración, enfermería y consulta). Cada persona definió su propia contraseña; nadie más la conoce. Las cuentas las administra: ________________________________________.')]),
  itemActa([b('Capacitación. '), t('Se capacitó al personal el 21 de octubre de 2026, según la lista de asistencia adjunta.')]),
  itemActa([b('Documentación. '), t('Se entregan el manual de usuario y el manual técnico (versión 1.1), la guía rápida y un video de uso, en forma impresa y digital.')]),
  itemActa([b('Alojamiento y dominio. '), t('El servicio de alojamiento y el dominio carnetchivarreto.com están pagados por 12 meses desde octubre de 2026. La renovación posterior queda a cargo de: ________________________________________.')]),
  itemActa([b('Soporte. '), t('El estudiante dará soporte técnico al puesto, a solicitud de este, hasta: ______________________. El acceso técnico al servidor se usará solo para mantenimiento.')]),
  p([t('Observaciones: ____________________________________________________________________________________', { size: 19 })], { before: 100, after: 60 }),
  p([t('____________________________________________________________________________________________', { size: 19 })], { after: 100 }),
  p([t('Para constancia, firman los que en ella intervienen.')], { after: 40 }),
  new Table({
    width: { size: ANCHO, type: WidthType.DXA }, columnWidths: [3300, 3640, 3300],
    rows: [new TableRow({ children: [
      new TableCell({ children: firma('Entrega', ['Wilson Edwin Hernández Maldonado', 'Estudiante, carné 1490-16-14973']), width: { size: 3300, type: WidthType.DXA }, borders: sinBordes, margins: { left: 100, right: 100 } }),
      new TableCell({ children: firma('Recibe', ['Nombre: ______________________', 'Cargo: _______________________']), width: { size: 3640, type: WidthType.DXA }, borders: sinBordes, margins: { left: 100, right: 100 } }),
      new TableCell({ children: [p([t('')], { after: 300 }), p([t('Sello del puesto', { size: 18, color: '999999' })], { align: AlignmentType.CENTER, after: 0 })], width: { size: 3300, type: WidthType.DXA }, borders: bordes, verticalAlign: VerticalAlign.CENTER }),
    ] })],
  }),
];

const propiedades = (horizontal) => ({
  page: horizontal
    ? { size: { width: CARTA.width, height: CARTA.height, orientation: PageOrientation.LANDSCAPE }, margin: margenesH }
    : { size: CARTA, margin: margenes },
});

const doc = new Document({
  creator: 'Wilson Edwin Hernández Maldonado',
  title: 'Materiales para la capacitación y la entrega del Carnet Digital',
  styles: { default: { document: { run: { font: FUENTE, size: 21 } } } },
  numbering: numeracion,
  sections: [
    { properties: propiedades(false), children: agenda },
    { properties: propiedades(false), children: guia1 },
    { properties: propiedades(false), children: guia2 },
    { properties: propiedades(true), children: planilla },
    { properties: propiedades(true), children: asistencia },
    { properties: propiedades(true), children: recepcion },
    { properties: propiedades(false), children: acta },
  ],
});

Packer.toBuffer(doc).then(buf => {
  fs.mkdirSync(path.dirname(SALIDA), { recursive: true });
  fs.writeFileSync(SALIDA, buf);
  console.log('Escrito:', SALIDA, buf.length, 'bytes');
});
