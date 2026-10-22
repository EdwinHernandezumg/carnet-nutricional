// Graba el video de capacitación: abre el estudio (teléfono, título y
// subtítulos), recorre el sistema local frase por frase y guarda los cuadros
// de la pantalla con su tiempo. armar.py junta los cuadros con la voz.
//
//   node grabar.js [hasta_id]     (hasta_id: detenerse después de esa frase)
const puppeteer = require('puppeteer-core');
const fs = require('fs');
const path = require('path');

const AQUI = __dirname;
const ESTUDIO = 'http://localhost/estudio-video/index.html';
const BASE = 'http://localhost/carnet-nutricional/public/index.php';
const BUZON = path.join(AQUI, '..', 'buzon.jsonl');
const USUARIO = 'ltzul', CLAVE = 'VideoPrueba2026', CORREO = 'lucia.tzul@example.com';
const NINO = 3;
const HASTA = process.argv[2] || null;
const PAUSA = 0.5;  // segundos de silencio entre frases

const guion = JSON.parse(fs.readFileSync(path.join(AQUI, 'guion.json'), 'utf8'));
const duracion = JSON.parse(fs.readFileSync(path.join(AQUI, 'duraciones.json'), 'utf8'));
const frases = {};
guion.forEach(s => s.frases.forEach(f => { frases[f.id] = f; }));

const CUADROS = path.join(AQUI, 'cuadros');
fs.rmSync(CUADROS, { recursive: true, force: true });
fs.mkdirSync(CUADROS);

const espera = ms => new Promise(r => setTimeout(r, ms));
let t0 = 0;
const ahora = () => (Date.now() - t0) / 1000;
const cuadros = [];
const marcas = [];
let page, app;

class Fin extends Error {}

// ---------------------------------------------------------------- estudio
async function seccion(nombre) {
  await page.evaluate(n => window.estudio.titulo(n), nombre);
  await espera(700);
}

// Muestra el subtítulo, marca dónde empieza la voz y hace la acción mientras
// se oye. Espera a que termine la frase antes de seguir.
async function decir(id, accion) {
  const f = frases[id];
  await page.evaluate(t => window.estudio.subtitulo(t), f.texto);
  marcas.push({ id, t: ahora() + 0.25 });
  const inicio = Date.now();
  if (accion) {
    try { await accion(); } catch (e) { console.log('FALLO', id, e.message.split(/\r?\n/)[0]); }
  }
  const falta = duracion[id] + PAUSA + 0.25 - (Date.now() - inicio) / 1000;
  if (falta > 0) await espera(falta * 1000);
  console.log('frase', id, ahora().toFixed(1));
  if (HASTA === id) throw new Fin();
}

// ---------------------------------------------------------------- teléfono
async function ir(url) {
  await app.goto(BASE + url, { waitUntil: 'networkidle0' });
  await espera(300);
}

async function elemento(selector, texto, dentroDe) {
  const h = await app.evaluateHandle((sel, txt, dentro) => {
    let raiz = document;
    if (dentro) raiz = [...document.querySelectorAll(dentro.sel)].find(e => e.textContent.includes(dentro.txt)) || document;
    return [...raiz.querySelectorAll(sel)].find(e => e.getClientRects().length && getComputedStyle(e).visibility !== 'hidden'
      && (!txt || e.textContent.replace(/\s+/g, ' ').includes(txt))) || null;
  }, selector, texto || null, dentroDe || null);
  const el = h.asElement();
  if (!el) throw new Error(`No se encontró ${selector} ${texto || ''}`);
  return el;
}

// Lleva el elemento a la vista con desplazamiento suave, si hace falta.
async function aLaVista(el, bloque = 'center') {
  const fuera = await el.evaluate(e => {
    const r = e.getBoundingClientRect();
    return r.top < 70 || r.bottom > window.innerHeight - 80;
  });
  if (fuera) {
    await el.evaluate((e, b) => e.scrollIntoView({ behavior: 'smooth', block: b }), bloque);
    await espera(900);
  }
}

async function desplazar(y) {
  await app.evaluate(y => window.scrollTo({ top: y, behavior: 'smooth' }), y);
  await espera(900);
}

async function desplazarPoco(dy, pasos = 1) {
  for (let i = 0; i < pasos; i++) {
    await app.evaluate(dy => window.scrollBy({ top: dy, behavior: 'smooth' }), dy);
    await espera(900);
  }
}

// Círculo verde donde se toca, para que se vea el toque.
async function marcarToque(el) {
  await aLaVista(el);
  const c = await el.evaluate(e => { const r = e.getBoundingClientRect(); return { x: r.left + r.width / 2, y: r.top + r.height / 2 }; });
  await app.evaluate(({ x, y }) => {
    const d = document.createElement('div');
    d.style.cssText = `position:fixed;left:${x - 24}px;top:${y - 24}px;width:48px;height:48px;border-radius:50%;
      background:rgba(29,158,117,.32);border:3px solid #1d9e75;z-index:99999;pointer-events:none;
      transform:scale(.5);transition:transform .25s ease-out, opacity .45s;`;
    document.body.appendChild(d);
    requestAnimationFrame(() => requestAnimationFrame(() => { d.style.transform = 'scale(1)'; }));
    setTimeout(() => { d.style.opacity = '0'; }, 650);
    setTimeout(() => d.remove(), 1200);
  }, c);
  await espera(380);
}

async function tocar(el, navega = false) {
  await marcarToque(el);
  if (navega) {
    await Promise.all([app.waitForNavigation({ waitUntil: 'networkidle0' }), el.evaluate(e => e.click())]);
    await espera(400);
  } else {
    await el.evaluate(e => {
      const campo = ['INPUT', 'SELECT', 'TEXTAREA'].includes(e.tagName);
      if (campo) e.focus();
      if (!(e.tagName === 'INPUT' && e.type === 'date')) e.click();
    });
    await espera(250);
  }
}

async function escribir(el, texto) {
  await tocar(el);
  await el.type(texto, { delay: 95 });
  await espera(250);
}

// Recuadro amarillo que resalta una parte de la pantalla.
async function resaltar(el, ms = 2600) {
  await aLaVista(el);
  await el.evaluate((e, ms) => {
    const antes = e.style.boxShadow, transicion = e.style.transition;
    e.style.transition = 'box-shadow .3s';
    e.style.boxShadow = '0 0 0 4px #f5b400, 0 0 18px rgba(245,180,0,.55)';
    setTimeout(() => { e.style.boxShadow = antes; setTimeout(() => { e.style.transition = transicion; }, 400); }, ms);
  }, ms);
}

function codigoDelBuzon() {
  const correos = fs.readFileSync(BUZON, 'utf8').trim().split('\n').map(l => JSON.parse(l));
  return correos.filter(c => c.para === CORREO).pop().asunto.slice(-6);
}

// ---------------------------------------------------------------- guion
async function grabar() {
  // Portada
  await page.evaluate(() => window.estudio.portada(true));
  await decir('i1');
  await decir('i2');

  // 1. Entrar
  await page.evaluate(() => window.estudio.portada(false));
  await espera(500);
  await seccion('1. Entrar al sistema');
  await decir('a1');
  await decir('a2', async () => {
    // Edge agrega su propio botón para ver la contraseña; el Chrome del teléfono no.
    await app.addStyleTag({ content: 'input::-ms-reveal, input::-ms-clear { display: none !important; }' });
    await escribir(await elemento('#usuario'), USUARIO);
    await escribir(await elemento('#contrasena'), CLAVE);
    await tocar(await elemento('button[type=submit]'), true);
  });
  await decir('a3', async () => { await espera(1200); await resaltar(await elemento('.mensaje-ok'), 3000).catch(() => {}); });
  await decir('a4', async () => {
    await escribir(await elemento('#codigo'), codigoDelBuzon());
    await espera(600);
    await resaltar(await elemento('label', 'No volver a pedirlo'), 3500);
  });
  await decir('a5', async () => { await tocar(await elemento('button[type=submit]', 'Confirmar'), true); });
  await decir('a6');

  // 2. Buscar
  await seccion('2. Buscar a un niño');
  await decir('b1', async () => { await espera(1500); await resaltar(await elemento('input[name=texto]'), 3500); });
  await decir('b2', async () => {
    await escribir(await elemento('input[name=texto]'), 'Ixchop');
    await tocar(await elemento('button[type=submit]', 'Buscar'), true);
  });
  await decir('b3', async () => {
    await espera(600);
    await tocar(await elemento('a[href*="nino_historial"]', 'Ver', { sel: 'tr', txt: 'Ixchop Lacán' }), true);
  });
  await decir('b4', async () => { await espera(1800); await resaltar(await elemento('.aviso-alerta'), 4500); });
  await decir('b5');

  // 3. Medición
  await seccion('3. Registrar una medición');
  await decir('c1', async () => {
    const boton = await elemento('a[href*="medicion_nueva"]');
    await espera(600);
    await aLaVista(boton);
    await espera(2600);
    await tocar(boton, true);
  });
  await decir('c2', async () => {
    await resaltar(await elemento('#fecha_toma'), 2000);
    await espera(1500);
    await escribir(await elemento('#peso_kg'), '7.45');
    await escribir(await elemento('#talla_cm'), '65.2');
  });
  await decir('c3', async () => {
    await resaltar(await elemento('#tipo_talla'), 3000);
    await espera(2600);
    await tocar(await elemento('button[type=submit]', 'Guardar'), true);
  });
  await decir('c4', async () => { await espera(800); await resaltar(await elemento('.aviso-alerta'), 5000); });
  await decir('c5', async () => {
    const tabla = await elemento('h2', 'Mediciones registradas');
    await tabla.evaluate(e => e.scrollIntoView({ behavior: 'smooth', block: 'start' }));
    await espera(1200);
  });

  // 4. Gráficas
  await seccion('4. Gráficas de crecimiento');
  await decir('d1', async () => {
    await desplazar(0);
    await espera(400);
    await tocar(await elemento('a[href*="nino_graficas"]'), true);
    await espera(1500);
  });
  await decir('d2', async () => {
    const lienzos = await app.$$('canvas');
    await lienzos[0].evaluate(e => e.scrollIntoView({ behavior: 'smooth', block: 'center' }));
    await espera(1000);
  });
  await decir('d3', async () => {
    const lienzos = await app.$$('canvas');
    if (lienzos[1]) { await lienzos[1].evaluate(e => e.scrollIntoView({ behavior: 'smooth', block: 'center' })); await espera(1000); }
  });
  await decir('d4', async () => { await tocar(await elemento('a', 'Volver al historial'), true); });

  // 5. Vacunas
  await seccion('5. Vacunas');
  await decir('e1', async () => {
    const boton = await elemento('a[href*="vacuna_nueva"]');
    await espera(500);
    await aLaVista(boton);
    await espera(2400);
    await tocar(boton, true);
  });
  await decir('e2', async () => {
    await resaltar(await elemento('#fecha_aplicacion'), 1800);
    await espera(1900);
    await resaltar(await elemento('#lugar_vacunacion'), 1800);
    await espera(1900);
    const primera = await elemento('fieldset .dosis-item');
    await primera.evaluate(e => e.scrollIntoView({ behavior: 'smooth', block: 'start' }));
    await espera(1000);
  });
  await decir('e3', async () => {
    const nombres = await app.$$eval('fieldset .dosis-item input[type=checkbox]', cs => cs.filter(c => c.getClientRects().length).slice(0, 2).map(c => c.name));
    for (const nombre of nombres) {
      await tocar(await elemento(`input[name="${nombre}"]`));
      await escribir(await elemento('#' + nombre.replace('dosis_', 'lote_')), 'L' + nombre.replace('dosis_', '') + '-26');
    }
  });
  await decir('e4', async () => { await tocar(await elemento('button[type=submit]', 'Guardar'), true); });
  await decir('e5', async () => {
    const det = await elemento('details.bloque-esquema summary');
    await aLaVista(det);
    await tocar(det);
    await espera(500);
  });
  await decir('e6', async () => {
    const leyenda = await app.evaluateHandle(() => {
      const d = document.querySelector('details.bloque-esquema');
      return [...d.querySelectorAll('ul, div, p')].filter(e => e.textContent.includes('Aplicada') && e.textContent.includes('Pendiente'))
        .sort((a, b) => a.textContent.length - b.textContent.length)[0] || null;
    });
    if (leyenda.asElement()) { await resaltar(leyenda.asElement(), 3500); await espera(3000); }
    await desplazarPoco(330, 6);
  });

  // 6. Suplementos y citas
  await seccion('6. Suplementos y citas');
  await decir('f1', async () => {
    await tocar(await elemento('a[href*="suplemento_nuevo"]'), true);
    await tocar(await elemento('#tipo_sup'));
    await app.select('#tipo_sup', 'Suplemento nutricional lipídico (SNL)');
    await app.evaluate(() => document.activeElement.blur());
    await espera(700);
    await escribir(await elemento('#cantidad'), '30');
  });
  await decir('f2', async () => { await tocar(await elemento('button[type=submit]', 'Guardar'), true); });
  await decir('f3', async () => {
    await tocar(await elemento('a[href*="cita_nueva"]'), true);
    // En el teléfono la fecha se elige en un calendario; aquí se pone directo.
    const fecha = await elemento('#fecha_programada');
    await marcarToque(fecha);
    await fecha.evaluate(e => {
      e.value = '2026-11-20';
      e.dispatchEvent(new Event('input', { bubbles: true }));
      e.dispatchEvent(new Event('change', { bubbles: true }));
    });
    await espera(900);
    await resaltar(await elemento('#motivo'), 1500);
    await espera(1300);
    await tocar(await elemento('button[type=submit]', 'Guardar'), true);
  });
  await decir('f4', async () => {
    await tocar(await elemento('.barra-nav a[href*="citas_calendario"]'), true);
    await espera(800);
    await desplazar(0);
    await resaltar(await elemento('.cal-leyenda'), 2400);
    await espera(2200);
    await resaltar(await elemento('.calendario'), 2600);
  });
  await decir('f5', async () => {
    const boton = await elemento('a', 'Avisar por WhatsApp');
    await aLaVista(boton);
    await espera(600);
    await marcarToque(boton);
    await resaltar(boton, 2500);
  });

  // 7. Corregir o anular
  await seccion('7. Corregir o anular');
  await decir('g1', async () => {
    await ir(`?accion=nino_historial&id=${NINO}`);
    const fila = await elemento('a.enlace-anular[href*="tipo=vacuna"]');
    await aLaVista(fila);
    await espera(500);
    await resaltar(await fila.evaluateHandle(a => a.closest('td')).then(h => h.asElement()), 4000);
  });
  await decir('g2', async () => {
    await tocar(await elemento('a.enlace-anular[href*="tipo=vacuna"]'), true);
    await escribir(await elemento('#motivo_cambio'), 'Se marcó la dosis por error');
    const ref = await app.$('#referencia');
    if (ref && await ref.evaluate(e => e.getClientRects().length > 0)) await escribir(ref, 'Conocimiento 01-2026');
    await tocar(await elemento('form.formulario button[type=submit]'), true);
  });
  await decir('g3', async () => {
    await espera(500);
    const fila = await elemento('tr.fila-anulada', null);
    await aLaVista(fila);
    await resaltar(fila, 5000);
  });
  await decir('g4');

  // 8. Seguimiento
  await seccion('8. Seguimiento');
  await decir('h1', async () => { await tocar(await elemento('.barra-nav a[href*="panel_alertas"]'), true); });
  await decir('h2', async () => {
    await resaltar(await elemento('.tarjeta-r.tr-roja'), 3500);
  });
  await decir('h3', async () => {
    await resaltar(await elemento('.tarjeta-r.tr-amarilla'), 3500);
  });
  await decir('h4', async () => { await desplazarPoco(420, 2); });

  // 9. Reportes y documentos
  await seccion('9. Reportes y documentos');
  await decir('k1', async () => {
    await tocar(await elemento('.barra-nav a[href*="reportes"]'), true);
    await espera(1200);
    await resaltar(await elemento('a', 'Excel'), 3000).catch(() => {});
  });
  await decir('k2', async () => {
    // El enlace abre otra pestaña: se marca el toque y se abre dentro del teléfono.
    const enlace = await elemento('a[href*="sigsa3"]');
    await marcarToque(enlace);
    await app.goto(await enlace.evaluate(a => a.href), { waitUntil: 'networkidle0' });
    await espera(800);
    await desplazarPoco(260, 1);
  });
  await decir('k3', async () => {
    await ir(`?accion=carnet_familia&id=${NINO}`);
    await espera(600);
    await desplazarPoco(300, 3);
  });

  // Cierre
  await page.evaluate(() => window.estudio.portada(true,
    'Para practicar:<br><b style="color:#4fd1a5">pruebas.carnetchivarreto.com</b>',
    'Dudas: guía rápida, manual<br>o el administrador del sistema',
    'Gracias por su trabajo'));
  await espera(600);
  await decir('z1');
  await decir('z2');
  await decir('z3');
  await espera(1500);
}

(async () => {
  const navegador = await puppeteer.launch({
    executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
    headless: 'new', args: ['--lang=es-GT', '--hide-scrollbars'],
  });
  let error = null;
  try {
    const ctx = await navegador.createBrowserContext();
    page = await ctx.newPage();
    page.on('dialog', d => d.accept());
    await page.setViewport({ width: 1080, height: 1920, deviceScaleFactor: 1, isMobile: true, hasTouch: true });
    await page.goto(ESTUDIO, { waitUntil: 'networkidle0' });
    app = await (await page.$('#app')).contentFrame();
    await ir('');

    const cdp = await page.createCDPSession();
    cdp.on('Page.screencastFrame', ({ data, sessionId }) => {
      const archivo = 'c' + String(cuadros.length).padStart(5, '0') + '.jpg';
      cuadros.push({ archivo, t: ahora() });
      fs.writeFileSync(path.join(CUADROS, archivo), Buffer.from(data, 'base64'));
      cdp.send('Page.screencastFrameAck', { sessionId }).catch(() => {});
    });
    t0 = Date.now();
    await cdp.send('Page.startScreencast', { format: 'jpeg', quality: 92, maxWidth: 1080, maxHeight: 1920, everyNthFrame: 1 });
    // Un cambio mínimo para que llegue el primer cuadro.
    await page.evaluate(() => window.estudio.subtitulo(''));
    await espera(800);
    try {
      await grabar();
    } catch (e) {
      if (!(e instanceof Fin)) throw e;
    }
    await page.evaluate(() => window.estudio.subtitulo(' '));
    await espera(400);
    await cdp.send('Page.stopScreencast');
  } catch (e) {
    error = e;
    await page.screenshot({ path: path.join(AQUI, 'error.png') }).catch(() => {});
  } finally {
    fs.writeFileSync(path.join(AQUI, 'tiempos.json'), JSON.stringify({ fin: ahora(), cuadros, marcas }, null, 1));
    await navegador.close();
  }
  console.log('cuadros', cuadros.length, 'marcas', marcas.length, 'duración', ahora().toFixed(1));
  if (error) { console.error(error); process.exit(1); }
})();
