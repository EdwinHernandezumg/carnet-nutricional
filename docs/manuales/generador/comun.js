// Piezas comunes de los manuales: estilos, portada, títulos, párrafos, tablas,
// listas, bloques de código, índice y pie de página.
const fs = require('fs');
const path = require('path');
const {
  Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell, WidthType,
  AlignmentType, BorderStyle, ShadingType, LevelFormat, HeadingLevel,
  TableOfContents, Footer, Header, PageNumber, PageBreak, VerticalAlign, ImageRun,
} = require('docx');

// Todo el texto va en color automático (negro): sin colores propios.
const AZUL = undefined;
const TEAL = undefined;
const GRIS = 'F2F4F7';
const FUENTE = 'Arial';
const CARTA = { width: 12240, height: 15840 };
const MARGENES = { top: 1440, bottom: 1300, left: 1440, right: 1440 };
const ANCHO = CARTA.width - MARGENES.left - MARGENES.right; // 9360

const borde = { style: BorderStyle.SINGLE, size: 4, color: 'A0A7B1' };
const bordes = { top: borde, bottom: borde, left: borde, right: borde };

// Texto con marcas simples: **negrita** y `código`.
function runs(texto, op = {}) {
  // Signo menos y guiones dobles que no se cortan al final de la línea.
  texto = String(texto).replace(/(^|[\s(±])-(\d)/g, '$1−$2').replace(/--/g, '‑‑');
  const partes = String(texto).split(/(\*\*[^*]+\*\*|`[^`]+`)/g).filter(x => x !== '');
  return partes.map(x => {
    if (x.startsWith('**')) return new TextRun({ text: x.slice(2, -2), bold: true, font: FUENTE, size: op.size, color: op.color });
    if (x.startsWith('`')) return new TextRun({ text: x.slice(1, -1), font: 'Consolas', size: (op.size || 22) - 2 });
    return new TextRun({ text: x, font: FUENTE, size: op.size, color: op.color, bold: op.bold, italics: op.italics });
  });
}
const P = (texto, op = {}) => new Paragraph({ children: runs(texto, op), alignment: op.align ?? AlignmentType.JUSTIFIED, spacing: { after: op.after ?? 140, before: op.before ?? 0, line: 300 }, keepNext: op.keepNext });
const H1 = (texto, nuevaPagina = false) => new Paragraph({ heading: HeadingLevel.HEADING_1, children: [new TextRun({ text: texto })], pageBreakBefore: nuevaPagina });
const H2 = (texto) => new Paragraph({ heading: HeadingLevel.HEADING_2, children: [new TextRun({ text: texto })] });
const H3 = (texto) => new Paragraph({ heading: HeadingLevel.HEADING_3, children: [new TextRun({ text: texto })] });
const vineta = (texto, nivel = 0) => new Paragraph({ children: runs(texto), numbering: { reference: 'vinetas', level: nivel }, spacing: { after: 70, line: 290 } });
const numerado = (texto, ref = 'pasos') => new Paragraph({ children: runs(texto), numbering: { reference: ref, level: 0 }, spacing: { after: 80, line: 290 } });
const saltoPagina = () => new Paragraph({ children: [new PageBreak()] });

function codigo(lineas) {
  return (Array.isArray(lineas) ? lineas : [lineas]).map((l, i, a) => new Paragraph({
    children: [new TextRun({ text: l === '' ? ' ' : l, font: 'Consolas', size: 18 })],
    shading: { fill: 'EEF1F5', type: ShadingType.CLEAR, color: 'auto' },
    spacing: { after: i === a.length - 1 ? 160 : 0, line: 260 },
    indent: { left: 200, right: 200 },
    border: i === 0 ? { top: { style: BorderStyle.SINGLE, size: 4, color: 'D0D5DD', space: 4 } } : (i === a.length - 1 ? { bottom: { style: BorderStyle.SINGLE, size: 4, color: 'D0D5DD', space: 4 } } : undefined),
  }));
}

// Tabla con el estilo "Tabla normal 1" de Word (encabezado y primera columna
// en negrita, filas alternas en gris, bordes grises). Sin formato propio en las
// celdas, para que mande el estilo. Encabezados, filas y anchos en DXA.
function tabla(encabezados, filas, anchos, op = {}) {
  const total = anchos.reduce((a, b) => a + b, 0);
  const celda = (texto, ancho) => new TableCell({
    width: { size: ancho, type: WidthType.DXA },
    margins: { top: 60, bottom: 60, left: 100, right: 100 }, verticalAlign: VerticalAlign.CENTER,
    children: String(texto).split('\n').map(l => new Paragraph({ children: runs(l, { size: op.size || 19 }), spacing: { after: 0, line: 260 } })),
  });
  return [new Table({
    style: ESTILO_TABLA, width: { size: total, type: WidthType.DXA }, columnWidths: anchos,
    rows: [
      new TableRow({ tableHeader: true, children: encabezados.map((e, i) => celda(e, anchos[i])) }),
      ...filas.map(f => new TableRow({ cantSplit: true, children: f.map((v, i) => celda(v, anchos[i])) })),
    ],
  }), ...(op.titulo ? [new Paragraph({ children: runs(op.titulo, { size: 18, italics: true }), alignment: AlignmentType.CENTER, spacing: { before: 60, after: 200 } })] : [new Paragraph({ spacing: { after: 120 }, children: [] })])];
}

function nota(texto, tipo = 'info') {
  const b = { style: BorderStyle.SINGLE, size: 6, color: 'auto' };
  return [new Table({
    width: { size: ANCHO, type: WidthType.DXA }, columnWidths: [ANCHO],
    rows: [new TableRow({ children: [new TableCell({
      width: { size: ANCHO, type: WidthType.DXA }, borders: { top: b, bottom: b, left: { style: BorderStyle.SINGLE, size: 24, color: 'auto' }, right: b },
      margins: { top: 100, bottom: 100, left: 180, right: 180 },
      children: [new Paragraph({ children: runs(texto, { size: 20 }), spacing: { after: 0, line: 280 } })],
    })] })],
  }), new Paragraph({ spacing: { after: 120 }, children: [] })];
}

function imagen(archivo, anchoPx, altoPx, pie) {
  const datos = fs.readFileSync(archivo);
  return [
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { before: 80, after: 40 }, keepNext: true, children: [new ImageRun({ type: 'png', data: datos, transformation: { width: anchoPx, height: altoPx } })] }),
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 160 }, children: runs(pie, { size: 18, italics: true }) }),
  ];
}

// Captura a la izquierda y su explicación a la derecha, sin bordes, para no
// dejar media página en blanco junto a una imagen angosta de teléfono.
function imagenConTexto(archivo, anchoPx, altoPx, pie, textos) {
  const sin = { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' };
  const sinBordes = { top: sin, bottom: sin, left: sin, right: sin };
  const anchoImagen = Math.round(anchoPx * 15) + 300; // 1 px = 15 DXA, más margen
  const datos = fs.readFileSync(archivo);
  return [new Table({
    width: { size: ANCHO, type: WidthType.DXA }, columnWidths: [anchoImagen, ANCHO - anchoImagen],
    borders: { top: sin, bottom: sin, left: sin, right: sin, insideHorizontal: sin, insideVertical: sin },
    rows: [new TableRow({ cantSplit: true, children: [
      new TableCell({ width: { size: anchoImagen, type: WidthType.DXA }, borders: sinBordes, margins: { top: 60, bottom: 60, left: 0, right: 200 }, children: [
        new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 40 }, children: [new ImageRun({ type: 'png', data: datos, transformation: { width: anchoPx, height: altoPx } })] }),
        new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 0 }, children: runs(pie, { size: 17, italics: true }) }),
      ] }),
      new TableCell({ width: { size: ANCHO - anchoImagen, type: WidthType.DXA }, borders: sinBordes, margins: { top: 60, bottom: 60, left: 120, right: 0 }, children: textos }),
    ] })],
  }), new Paragraph({ spacing: { after: 80 }, children: [] })];
}

// Portada con los datos de la portada de la tesis.
function portada(tipoManual, version, fecha) {
  const vacios = (n, tam, despues) => Array.from({ length: n }, () => new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: despues }, children: [new TextRun({ text: '', font: FUENTE, size: tam })] }));
  const centro = (texto, op = {}) => new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: op.after ?? 120, before: op.before ?? 0 }, children: [new TextRun({ text: texto, font: FUENTE, size: op.size || 24, bold: op.bold, color: op.color, allCaps: op.caps })] });
  return [
    centro('UNIVERSIDAD MARIANO GÁLVEZ DE GUATEMALA', { size: 28, bold: true, color: AZUL, after: 60 }),
    centro('FACULTAD DE INGENIERÍA EN SISTEMAS DE INFORMACIÓN', { size: 22, bold: true, color: AZUL, after: 360 }),
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 420 }, children: [new ImageRun({ type: 'png', data: fs.readFileSync(path.join(__dirname, 'logo-umg.png')), transformation: { width: 170, height: 170 } })] }),
    centro(tipoManual, { size: 52, bold: true, color: TEAL, after: 360 }),
    centro('DESARROLLO DE UN SISTEMA INTELIGENTE DE MONITOREO NUTRICIONAL INFANTIL MEDIANTE CARNET DIGITAL EN PUESTOS DE SALUD RURALES', { size: 24, bold: true, after: 300 }),
    centro('Carnet Digital de Monitoreo Nutricional Infantil', { size: 24, after: 60 }),
    centro('Puesto de Salud de la aldea Chivarreto, San Francisco El Alto, Totonicapán', { size: 20, after: 60 }),
    centro(`Versión ${version}`, { size: 20, after: 1100 }),
    // Renglones vacíos como los dejó el desarrollador en Word (17 de octubre de
    // 2026), para repartir la portada en toda la hoja.
    ...vacios(1, 20, 40),
    centro('Elaborado por:', { size: 20, after: 40 }),
    centro('WILSON EDWIN HERNÁNDEZ MALDONADO', { size: 24, bold: true, after: 40 }),
    centro('Carné 1490-16-14973', { size: 20, after: 300 }),
    ...vacios(4, 20, 40),
    centro('Asesor:', { size: 20, after: 40 }),
    centro('MA. ING. LUIS ARMANDO TEZÓ CAYAX', { size: 22, bold: true, after: 700 }),
    ...vacios(3, 24, 120),
    centro(`GUATEMALA, ${fecha.toUpperCase()}.`, { size: 22, bold: true }),
  ];
}

function indice() {
  return [
    new Paragraph({ children: [new TextRun({ text: 'Índice', font: FUENTE, size: 32, bold: true, color: AZUL })], spacing: { after: 200 } }),
    new TableOfContents('Índice', { hyperlink: true, headingStyleRange: '1-2' }),
  ];
}

function documento({ titulo, nombreCorto, portadaHijos, cuerpo }) {
  const pie = new Footer({ children: [new Paragraph({ alignment: AlignmentType.CENTER, children: [
    new TextRun({ children: [PageNumber.CURRENT], font: FUENTE, size: 20 }),
  ] })] });
  return new Document({
    creator: 'Wilson Edwin Hernández Maldonado',
    title: titulo,
    description: titulo,
    features: { updateFields: true },
    styles: {
      default: { document: { run: { font: FUENTE, size: 22 } } },
      paragraphStyles: [
        { id: 'Heading1', name: 'Heading 1', basedOn: 'Normal', next: 'Normal', quickFormat: true, run: { size: 32, bold: true, color: AZUL, font: FUENTE }, paragraph: { spacing: { before: 320, after: 160 }, outlineLevel: 0, keepNext: true } },
        { id: 'Heading2', name: 'Heading 2', basedOn: 'Normal', next: 'Normal', quickFormat: true, run: { size: 26, bold: true, color: TEAL, font: FUENTE }, paragraph: { spacing: { before: 240, after: 120 }, outlineLevel: 1, keepNext: true } },
        { id: 'Heading3', name: 'Heading 3', basedOn: 'Normal', next: 'Normal', quickFormat: true, run: { size: 23, bold: true, color: AZUL, font: FUENTE }, paragraph: { spacing: { before: 200, after: 100 }, outlineLevel: 2, keepNext: true } },
      ],
    },
    numbering: { config: [
      { reference: 'vinetas', levels: [
        { level: 0, format: LevelFormat.BULLET, text: '•', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 500, hanging: 280 } } } },
        { level: 1, format: LevelFormat.BULLET, text: '–', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 950, hanging: 280 } } } },
      ] },
      ...['pasos', 'pasos1', 'pasos2', 'pasos3', 'pasos4', 'pasos5', 'pasos6', 'pasos7', 'pasos8', 'pasos9', 'pasos10', 'pasos11', 'pasos12'].map(ref => ({ reference: ref, levels: [{ level: 0, format: LevelFormat.DECIMAL, text: '%1.', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 500, hanging: 320 } }, run: { bold: true, color: TEAL } } }] })),
    ] },
    sections: [
      { properties: { page: { size: CARTA, margin: MARGENES } }, children: portadaHijos },
      { properties: { page: { size: CARTA, margin: MARGENES, pageNumbers: { start: 1 } } }, footers: { default: pie }, children: cuerpo },
    ],
  });
}

const ESTILO_TABLA = 'Tablanormal1';
const LOOK_TABLA = '<w:tblLook w:val="04A0" w:firstRow="1" w:lastRow="0" w:firstColumn="1" w:lastColumn="0" w:noHBand="0" w:noVBand="1"/>';

function guardar(doc, salida) {
  const JSZip = require('jszip');
  const estilo = fs.readFileSync(path.join(__dirname, 'estilo-tabla-normal-1.xml'), 'utf8');
  return Packer.toBuffer(doc).then(buf => JSZip.loadAsync(buf)).then(async zip => {
    const estilos = await zip.file('word/styles.xml').async('string');
    if (!estilos.includes(`w:styleId="${ESTILO_TABLA}"`)) {
      zip.file('word/styles.xml', estilos.replace('</w:styles>', estilo + '</w:styles>'));
    }
    // La librería pone bordes directos negros a toda tabla; en las que usan el
    // estilo se quitan, para que se vean los bordes grises del estilo.
    const documento = await zip.file('word/document.xml').async('string');
    zip.file('word/document.xml', documento.replace(/<w:tblPr>(?:(?!<\/w:tblPr>).)*<\/w:tblPr>/gs, tblPr => {
      if (!tblPr.includes(`w:tblStyle w:val="${ESTILO_TABLA}"`)) return tblPr;
      return tblPr.replace(/<w:tblBorders>.*?<\/w:tblBorders>/s, '').replace('</w:tblPr>', LOOK_TABLA + '</w:tblPr>');
    }));
    return zip.generateAsync({ type: 'nodebuffer', compression: 'DEFLATE' });
  }).then(buf => {
    fs.mkdirSync(path.dirname(salida), { recursive: true });
    fs.writeFileSync(salida, buf);
    console.log('Escrito:', salida, buf.length, 'bytes');
  });
}

module.exports = { P, H1, H2, H3, vineta, numerado, saltoPagina, codigo, tabla, nota, imagen, imagenConTexto, portada, indice, documento, guardar, runs, ANCHO, AZUL, TEAL };
