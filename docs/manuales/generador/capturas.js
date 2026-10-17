// Toma las capturas del manual de usuario desde el sistema local (XAMPP),
// con los usuarios de prueba de CLAUDE.md y niños ficticios.
const puppeteer = require('puppeteer-core');
const fs = require('fs');
const path = require('path');

const BASE = 'http://localhost/carnet-nutricional/public/index.php';
const SALIDA = process.argv[2];
const NINO = 14; // niño de prueba con dos controles y alerta "cerca del umbral"
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

async function entrar(pagina, usuario, clave) {
  await pagina.goto(BASE, { waitUntil: 'networkidle0' });
  await pagina.type('#usuario', usuario);
  await pagina.type('#contrasena', clave);
  await Promise.all([pagina.waitForNavigation({ waitUntil: 'networkidle0' }), pagina.click('button[type=submit]')]);
}

(async () => {
  const navegador = await puppeteer.launch({ executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', headless: 'new', args: ['--lang=es-GT'] });
  try {
    // ---- Enfermería en el teléfono
    const ctxE = await navegador.createBrowserContext();
    const tel = await ctxE.newPage();
    await tel.setViewport(TELEFONO);
    await captura(tel, 't01-ingreso', '', { espera: 900 });
    await entrar(tel, 'enfermera', 'Soyenfermera2026');
    await captura(tel, 't02-seguimiento', '?accion=panel_alertas', { completa: true });
    await captura(tel, 't03-ninos', '?accion=ninos', { completa: true });
    await tel.goto(BASE + '?accion=nino_nuevo', { waitUntil: 'networkidle0' });
    await tel.type('#fecha_nacimiento', '25022024');
    await tel.$eval('#fecha_nacimiento', e => e.blur());
    await captura(tel, 't04-registrar-nino', null, { completa: true, maxAlto: 2000 });
    await captura(tel, 't05-nino-registrado', `?accion=nino_registrado&id=${NINO}`, { completa: true });
    await captura(tel, 't06-medicion', `?accion=medicion_nueva&id_nino=${NINO}`, { completa: true });
    await captura(tel, 't07-historial', `?accion=nino_historial&id=${NINO}`, { completa: true, maxAlto: 2200 });
    await captura(tel, 't08-graficas', `?accion=nino_graficas&id=${NINO}`, { completa: true, espera: 1800, maxAlto: 1800 });
    await captura(tel, 't09-vacuna', `?accion=vacuna_nueva&id_nino=${NINO}`, { completa: true });
    await captura(tel, 't10-suplemento', `?accion=suplemento_nuevo&id_nino=${NINO}`, { completa: true });
    await captura(tel, 't11-cita', `?accion=cita_nueva&id_nino=${NINO}`, { completa: true });
    await captura(tel, 't12-citas', '?accion=citas_calendario', { completa: true });
    await captura(tel, 't13-reportes', '?accion=reportes', { completa: true, espera: 1800, maxAlto: 1800 });
    await tel.goto(BASE + '?accion=cambiar_pass', { waitUntil: 'networkidle0' });
    await tel.type('#nueva', 'Chivarreto2026');
    await tel.type('#repetir', 'Chivarreto2026');
    await tel.click('.boton-ver[data-para="repetir"]');
    await tel.$eval('#repetir', e => e.blur());
    await captura(tel, 't14-cambiar-pass', null, { completa: true });

    // ---- Enfermería en la computadora
    const pc = await ctxE.newPage();
    await pc.setViewport(PC);
    await captura(pc, 'c01-seguimiento', '?accion=panel_alertas', {});
    await captura(pc, 'c02-reportes', '?accion=reportes', { espera: 1800, completa: true, maxAlto: 1300 });
    await captura(pc, 'c03-carnet-familia', `?accion=carnet_familia&id=${NINO}`, { espera: 1000, completa: true, maxAlto: 1300 });
    await captura(pc, 'c04-sigsa3', '?accion=sigsa3', { espera: 1000 });
    await ctxE.close();

    // ---- Administrador
    const ctxA = await navegador.createBrowserContext();
    const adm = await ctxA.newPage();
    await adm.setViewport(TELEFONO);
    await entrar(adm, 'admin', 'Admin2026');
    await captura(adm, 't16-usuario-nuevo', '?accion=usuario_nuevo', { completa: true });
    // Crea una cuenta de prueba local para mostrar la caja de la contraseña temporal.
    await adm.type('#nombre_completo', 'Rosa Elena Tzul');
    await adm.type('#usuario', 'rtzul' + Date.now().toString().slice(-3));
    await adm.select('#id_rol', '2');
    await adm.select('#id_sector', await adm.$eval('#id_sector option:nth-child(2)', o => o.value));
    await Promise.all([adm.waitForNavigation({ waitUntil: 'networkidle0' }), adm.$eval('form.formulario', f => f.requestSubmit())]);
    await captura(adm, 't15-usuarios', null, { completa: true, maxAlto: 1000 });
    await ctxA.close();
  } finally {
    await navegador.close();
  }
})().catch(e => { console.error(e); process.exit(1); });
