// Toma las capturas del manual de usuario desde el sistema local (XAMPP), con
// la base de demostración (niños ficticios de scripts/cargar_demostracion.php)
// y los usuarios de prueba de CLAUDE.md.
//
//   node capturas.js <carpeta de salida> <buzón del servidor de correo de prueba>
//
// Antes: config.php apuntando a la base de demostración y a un servidor SMTP de
// prueba que guarde los mensajes en el archivo del buzón (una línea JSON por
// mensaje), y la cuenta "enfermera" con correo, para mostrar el código de
// ingreso. Ver LEEME.md.
const puppeteer = require('puppeteer-core');
const fs = require('fs');
const path = require('path');

const BASE = 'http://localhost/carnet-nutricional/public/index.php';
const SALIDA = process.argv[2];
const BUZON = process.argv[3];
const NINO = 6;          // 2 años 7 meses, desnutrición aguda, vacunas del carnet de 2018
const NINO_PEQUENO = 3;  // 6 meses, cerca del umbral, con dosis pendientes
const NINO_ANULAR = 2;   // 1 año 10 meses, con controles de meses anteriores
fs.mkdirSync(SALIDA, { recursive: true });

const TELEFONO = { width: 375, height: 812, deviceScaleFactor: 2, isMobile: true, hasTouch: true };
const PC = { width: 1280, height: 800, deviceScaleFactor: 1 };
const espera = (ms) => new Promise(r => setTimeout(r, ms));

async function captura(pagina, nombre, url, op = {}) {
  if (url !== null) await pagina.goto(BASE + url, { waitUntil: 'networkidle0' });
  await espera(op.espera || 600);
  if (op.antes) await pagina.evaluate(op.antes);
  const alto = await pagina.evaluate(() => document.documentElement.scrollHeight);
  const vista = pagina.viewport();
  const maxAlto = op.maxAlto || (vista.width < 500 ? 1400 : 1600);
  const archivo = path.join(SALIDA, nombre + '.png');
  const altoFinal = Math.min(alto, op.completa ? maxAlto : vista.height);
  // La barra de menú del teléfono es fija abajo: se agranda la ventana al alto
  // de la captura para que quede al pie de la imagen y no en medio.
  if (op.completa && altoFinal > vista.height) {
    await pagina.setViewport({ ...vista, height: altoFinal });
    await espera(op.espera || 600);
  }
  await pagina.screenshot({ path: archivo, clip: { x: 0, y: 0, width: vista.width, height: altoFinal } });
  if (op.completa && altoFinal > vista.height) await pagina.setViewport(vista);
  console.log('captura', nombre, altoFinal);
}

// Captura de una sola parte de la pantalla.
async function capturaElemento(pagina, nombre, selector) {
  const elemento = await pagina.$(selector);
  await elemento.scrollIntoView();
  await espera(400);
  await elemento.screenshot({ path: path.join(SALIDA, nombre + '.png') });
  console.log('captura', nombre, selector);
}

const enviar = (pagina, selector = 'form') => Promise.all([
  pagina.waitForNavigation({ waitUntil: 'networkidle0' }), pagina.$eval(selector, f => f.requestSubmit())]);

async function entrar(pagina, usuario, clave) {
  await pagina.goto(BASE, { waitUntil: 'networkidle0' });
  await pagina.type('#usuario', usuario);
  await pagina.type('#contrasena', clave);
  await enviar(pagina);
}

// Código de ingreso del último correo enviado a esa dirección.
function codigoDelBuzon(correo) {
  const correos = fs.readFileSync(BUZON, 'utf8').trim().split('\n').map(l => JSON.parse(l));
  return correos.filter(c => c.para === correo).pop().asunto.slice(-6);
}

(async () => {
  const navegador = await puppeteer.launch({ executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', headless: 'new', args: ['--lang=es-GT'] });
  try {
    // ---- Enfermería en el teléfono
    const ctxE = await navegador.createBrowserContext();
    const tel = await ctxE.newPage();
    await tel.setViewport(TELEFONO);
    tel.on('dialog', d => d.accept());
    await captura(tel, 't01-ingreso', '', { espera: 900 });
    await captura(tel, 't18-recuperar', '?accion=recuperar', { completa: true });
    await entrar(tel, 'enfermera', 'Soyenfermera2026');
    await captura(tel, 't17-codigo', null, { completa: true });
    await tel.type('#codigo', codigoDelBuzon('enfermera@carnet.test'));
    await enviar(tel);

    await captura(tel, 't02-seguimiento', '?accion=panel_alertas', { completa: true });
    await captura(tel, 't03-ninos', '?accion=ninos', { completa: true });
    await tel.goto(BASE + '?accion=nino_nuevo', { waitUntil: 'networkidle0' });
    await tel.type('#fecha_nacimiento', '25022024');
    await tel.$eval('#fecha_nacimiento', e => e.blur());
    await tel.type('#numero_carnet', 'T8-050');
    await captura(tel, 't04-registrar-nino', null, { completa: true, maxAlto: 2000 });
    await captura(tel, 't05-nino-registrado', `?accion=nino_registrado&id=${NINO_PEQUENO}`, { completa: true });
    await captura(tel, 't06-medicion', `?accion=medicion_nueva&id_nino=${NINO_PEQUENO}`, { completa: true });
    await captura(tel, 't07-historial', `?accion=nino_historial&id=${NINO}`, { completa: true, maxAlto: 2200 });
    await tel.evaluate(() => { document.querySelector('details.bloque-esquema').open = true; });
    await capturaElemento(tel, 't21-esquema', 'details.bloque-esquema');
    await captura(tel, 't08-graficas', `?accion=nino_graficas&id=${NINO}`, { completa: true, espera: 1800, maxAlto: 1800 });

    // Vacunas: dos dosis pendientes marcadas, con su lote.
    await tel.goto(BASE + `?accion=vacuna_nueva&id_nino=${NINO_PEQUENO}`, { waitUntil: 'networkidle0' });
    const dosis = await tel.$$eval('fieldset .dosis-item input[type=checkbox]', cs => cs.slice(0, 2).map(c => c.name));
    for (const nombre of dosis) {
      await tel.click(`input[name="${nombre}"]`);
      await tel.type('#' + nombre.replace('dosis_', 'lote_'), 'L' + nombre.replace('dosis_', '') + '-2026');
    }
    await captura(tel, 't09-vacuna', null, { completa: true, maxAlto: 2000 });

    await tel.goto(BASE + `?accion=suplemento_nuevo&id_nino=${NINO_PEQUENO}`, { waitUntil: 'networkidle0' });
    await tel.select('#tipo_sup', 'Suplemento nutricional lipídico (SNL)');
    await captura(tel, 't10-suplemento', null, { completa: true });
    await captura(tel, 't11-cita', `?accion=cita_nueva&id_nino=${NINO_PEQUENO}`, { completa: true });
    await captura(tel, 't12-citas', '?accion=citas_calendario', { completa: true });
    await captura(tel, 't13-reportes', '?accion=reportes', { completa: true, espera: 1800, maxAlto: 1800 });

    // ---- Enfermería en la computadora (antes de anular, para no cambiar los datos)
    const pc = await ctxE.newPage();
    await pc.setViewport(PC);
    await captura(pc, 'c01-seguimiento', '?accion=panel_alertas', {});
    await captura(pc, 'c02-reportes', '?accion=reportes', { espera: 1800, completa: true, maxAlto: 1300 });
    await captura(pc, 'c03-carnet-familia', `?accion=carnet_familia&id=${NINO}`, { espera: 1000, completa: true, maxAlto: 1300 });
    await captura(pc, 'c04-sigsa3', '?accion=sigsa3&mes_completo=1', { espera: 1000 });
    await pc.close();

    // Anular una medición de un mes anterior: pide el motivo y la referencia del conocimiento.
    await tel.goto(BASE + `?accion=nino_historial&id=${NINO_ANULAR}`, { waitUntil: 'networkidle0' });
    const enlaces = await tel.$$eval('a.enlace-anular', as => as.map(a => a.getAttribute('href')).filter(h => h.includes('tipo=medicion')));
    await tel.goto(new URL(enlaces[2], BASE).href, { waitUntil: 'networkidle0' });
    await tel.type('#motivo_cambio', 'Se anotó el peso de otro niño');
    if (await tel.$('#referencia')) await tel.type('#referencia', 'Conocimiento 01-2026');
    await captura(tel, 't19-anular', null, { completa: true });
    await enviar(tel, 'form.formulario');
    await capturaElemento(tel, 't20-anulado', 'tr.fila-anulada');

    await tel.goto(BASE + '?accion=cambiar_pass', { waitUntil: 'networkidle0' });
    await tel.type('#nueva', 'Chivarreto2026');
    await tel.type('#repetir', 'Chivarreto2026');
    await tel.click('.boton-ver[data-para="repetir"]');
    await tel.$eval('#repetir', e => e.blur());
    await captura(tel, 't14-cambiar-pass', null, { completa: true });
    await ctxE.close();

    // ---- Administrador
    const ctxA = await navegador.createBrowserContext();
    const adm = await ctxA.newPage();
    await adm.setViewport(TELEFONO);
    await entrar(adm, 'admin', 'Admin2026');
    await adm.goto(BASE + '?accion=usuario_nuevo', { waitUntil: 'networkidle0' });
    await adm.type('#nombre_completo', 'Rosa Elena Tzul');
    await adm.type('#correo', 'rosa.tzul@example.com');
    await adm.type('#usuario', 'rtzul' + Date.now().toString().slice(-3));
    await adm.select('#id_rol', '2');
    await adm.select('#id_sector', await adm.$eval('#id_sector option:nth-child(2)', o => o.value));
    await captura(adm, 't16-usuario-nuevo', null, { completa: true });
    // Crea la cuenta de prueba local para mostrar la caja de la contraseña temporal.
    await enviar(adm, 'form.formulario');
    await captura(adm, 't15-usuarios', null, { completa: true, maxAlto: 1000 });
    await captura(adm, 't22-catalogo-suplementos', '?accion=suplementos_catalogo', { completa: true, maxAlto: 1500 });
    await ctxA.close();
  } finally {
    await navegador.close();
  }
})().catch(e => { console.error(e); process.exit(1); });
