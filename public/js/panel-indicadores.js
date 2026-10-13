/**
 * Panel de indicadores: gráficas interactivas y lista de niños.
 *
 * Espera la variable global `datosPanel` (ver views/reportes/panel.php):
 * resumen (tarjetas, por_nivel, por_sector, por_paraje, por_condicion),
 * tendencia, ninos, etiquetas y direcciones.
 *
 * Interacción:
 *   - Tocar un sector o un paraje recarga el panel filtrado por ese lugar
 *     (el filtro queda en la dirección, así también aplica a las exportaciones).
 *   - Tocar una tarjeta, una porción de la condición más grave o una barra de
 *     condición muestra abajo la lista de esos niños, con enlace a su historial.
 *
 * Con la clase "modo-impresion" en <body> (vista para PDF) dibuja las mismas
 * gráficas sin interacción, con más resolución, y abre el diálogo de impresión.
 */
(function () {
    'use strict';

    const COLORES = {
        desnutricion_aguda:   '#a4231a',
        desnutricion_cronica: '#d9534f',
        riesgo:               '#e8873a',
        sobrepeso:            '#378add',
        cerca_umbral:         '#e0a800',
        normal:               '#1d9e75',
        sin_control:          '#aab6c2',
        desnutricion:         '#d9534f',   // desnutrición o bajo peso, agrupados
    };
    const COLOR_CONDICION = {
        aguda: '#a4231a', cronica: '#d9534f', bajo_peso: '#e8873a', sobrepeso: '#378add', cerca: '#e0a800',
    };
    // Orden de gravedad para la lista: primero los casos más graves.
    const GRAVEDAD = ['desnutricion_aguda', 'desnutricion_cronica', 'riesgo', 'sobrepeso',
                      'cerca_umbral', 'normal', 'sin_control'];

    const impresion = document.body.classList.contains('modo-impresion');

    // ---------- Utilidades ----------

    /** Dirección del panel con los filtros actuales, cambiando algunos. */
    function urlCon(cambios) {
        const p = Object.assign({}, datosPanel.parametros, cambios);
        const qs = Object.keys(p)
            .filter(k => p[k] !== '' && p[k] !== null && p[k] !== undefined)
            .map(k => encodeURIComponent(k) + '=' + encodeURIComponent(p[k]))
            .join('&');
        return datosPanel.urlPanel + '&' + qs;
    }

    /** Opciones comunes de Chart.js para pantalla o para impresión. */
    function opcionesBase(extra) {
        const base = {
            responsive: !impresion,
            maintainAspectRatio: false,
            animation: false,
            devicePixelRatio: impresion ? 2 : undefined,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 14, font: { size: 12 } } } },
        };
        if (!impresion) {
            base.onHover = (evento, elementos) => {
                evento.native.target.style.cursor = elementos.length ? 'pointer' : 'default';
            };
        }
        return Object.assign(base, extra);
    }

    function lienzo(id) {
        return document.getElementById(id);
    }

    // ---------- Gráficas ----------

    function graficaSector() {
        const sectores = datosPanel.resumen.por_sector;
        const canvas = lienzo('grafica_sector');
        if (!canvas || sectores.length === 0) return;
        const serie = (nombre, color, valor) => ({
            label: nombre, backgroundColor: color, data: sectores.map(valor),
        });
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: sectores.map(s => s.nombre),
                datasets: [
                    serie('Normal', COLORES.normal, s => s.normal),
                    serie('Cerca del umbral', COLORES.cerca_umbral, s => s.cerca_umbral),
                    serie('Desnutrición o bajo peso', COLORES.desnutricion,
                          s => s.desnutricion_aguda + s.desnutricion_cronica + s.riesgo),
                    serie('Sobrepeso', COLORES.sobrepeso, s => s.sobrepeso),
                    serie('Sin control', COLORES.sin_control, s => s.sin_control),
                ],
            },
            options: opcionesBase({
                indexAxis: 'y',
                scales: { x: { stacked: true, ticks: { precision: 0 } }, y: { stacked: true } },
                onClick: impresion ? undefined : (e, elementos) => {
                    if (!elementos.length) return;
                    const sector = sectores[elementos[0].index];
                    window.location.href = urlCon({ id_sector: sector.id_sector, id_paraje: '' });
                },
            }),
        });
    }

    function graficaParaje() {
        const parajes = datosPanel.resumen.por_paraje.slice(0, 10);
        const canvas = lienzo('grafica_paraje');
        if (!canvas) return;
        if (parajes.length === 0) {
            mensajeSinDatos(canvas, 'Ningún paraje tiene niños con alerta en este período.');
            return;
        }
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: parajes.map(p => p.nombre),
                datasets: [{
                    label: 'Niños con alerta',
                    data: parajes.map(p => p.con_alerta),
                    backgroundColor: '#d85a30',
                }],
            },
            options: opcionesBase({
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: { x: { ticks: { precision: 0 } } },
                onClick: impresion ? undefined : (e, elementos) => {
                    if (!elementos.length) return;
                    const paraje = parajes[elementos[0].index];
                    if (paraje.id_paraje) {
                        window.location.href = urlCon({ id_paraje: paraje.id_paraje, id_sector: paraje.id_sector });
                    }
                },
            }),
        });
    }

    function graficaNivel() {
        const canvas = lienzo('grafica_nivel');
        if (!canvas) return;
        const claves = GRAVEDAD.filter(k => datosPanel.resumen.por_nivel[k] > 0);
        if (claves.length === 0) {
            mensajeSinDatos(canvas, 'No hay niños con estos filtros.');
            return;
        }
        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: claves.map(k => datosPanel.etiquetasNivel[k]),
                datasets: [{
                    data: claves.map(k => datosPanel.resumen.por_nivel[k]),
                    backgroundColor: claves.map(k => COLORES[k]),
                    borderWidth: 2,
                }],
            },
            options: opcionesBase({
                cutout: '55%',
                plugins: { legend: { position: impresion ? 'right' : 'bottom', labels: { boxWidth: 14, font: { size: 12 } } } },
                onClick: impresion ? undefined : (e, elementos) => {
                    if (elementos.length) mostrarLista('nivel:' + claves[elementos[0].index], true);
                },
            }),
        });
    }

    function graficaCondicion() {
        const canvas = lienzo('grafica_condicion');
        if (!canvas) return;
        const claves = Object.keys(datosPanel.condiciones);
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: claves.map(k => datosPanel.condiciones[k]),
                datasets: [{
                    label: 'Niños',
                    data: claves.map(k => datosPanel.resumen.por_condicion[k]),
                    backgroundColor: claves.map(k => COLOR_CONDICION[k]),
                }],
            },
            options: opcionesBase({
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: { x: { ticks: { precision: 0 } } },
                onClick: impresion ? undefined : (e, elementos) => {
                    if (elementos.length) mostrarLista('cond:' + claves[elementos[0].index], true);
                },
            }),
        });
    }

    function graficaTendencia() {
        const canvas = lienzo('grafica_tendencia');
        if (!canvas) return;
        const meses = datosPanel.tendencia;
        const pct = (parte, total) => total ? Math.round(parte * 1000 / total) / 10 : null;
        const linea = (nombre, color, valor) => ({
            label: nombre, borderColor: color, backgroundColor: color,
            data: meses.map(m => pct(valor(m), m.evaluados)),
            spanGaps: true, tension: 0.25, pointRadius: 4, borderWidth: 2.5,
        });
        new Chart(canvas, {
            type: 'line',
            data: {
                labels: meses.map(m => m.etiqueta),
                datasets: [
                    linea('Desnutrición o bajo peso', COLORES.desnutricion, m => m.desnutricion),
                    linea('Cerca del umbral', COLORES.cerca_umbral, m => m.cerca),
                    linea('Sobrepeso', COLORES.sobrepeso, m => m.sobrepeso),
                ],
            },
            options: opcionesBase({
                scales: { y: { beginAtZero: true, suggestedMax: 50, ticks: { callback: v => v + ' %' } } },
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 14, font: { size: 12 } } },
                    tooltip: {
                        callbacks: {
                            label: (item) => {
                                const m = meses[item.dataIndex];
                                return item.dataset.label + ': ' + item.parsed.y + ' % (de ' + m.evaluados + ' medidos)';
                            },
                        },
                    },
                },
            }),
        });
    }

    /** Texto en lugar de la gráfica cuando no hay datos que mostrar. */
    function mensajeSinDatos(canvas, texto) {
        const p = document.createElement('p');
        p.className = 'vacio';
        p.textContent = texto;
        canvas.parentNode.replaceWith(p);
    }

    // ---------- Lista de niños detrás de la cifra ----------

    const NOMBRES_LISTA = {
        alerta:    'Niños con alguna alerta',
        todos:     'Todos los niños activos',
        evaluados: 'Niños evaluados en el período',
        atrasados: 'Niños con el control atrasado',
        'cond:aguda':     'Niños con desnutrición aguda',
        'cond:cronica':   'Niños con desnutrición crónica',
        'cond:bajo_peso': 'Niños con bajo peso',
        'cond:sobrepeso': 'Niños con sobrepeso',
        'cond:cerca':     'Niños cerca del umbral',
        // Tarjetas de estado nutricional: condición más grave de cada niño.
        'nivel:desnutricion': 'Niños con desnutrición, aguda o crónica',
        'nivel:cerca_umbral': 'Niños cerca del umbral (condición más grave)',
        'nivel:riesgo':       'Niños con riesgo, bajo peso (condición más grave)',
        'nivel:sobrepeso':    'Niños con sobrepeso (condición más grave)',
    };

    function filtroDeLista(clave) {
        if (clave === 'todos')     return () => true;
        if (clave === 'evaluados') return n => n.nivel !== 'sin_control';
        if (clave === 'atrasados') return n => n.atrasado === true;
        if (clave === 'nivel:desnutricion') return n => n.nivel === 'desnutricion_aguda' || n.nivel === 'desnutricion_cronica';
        if (clave.startsWith('cond:'))  { const c = clave.slice(5); return n => n.condiciones[c]; }
        if (clave.startsWith('nivel:')) { const v = clave.slice(6); return n => n.nivel === v; }
        return n => n.nivel !== 'normal' && n.nivel !== 'sin_control';
    }

    function tituloDeLista(clave) {
        if (NOMBRES_LISTA[clave]) return NOMBRES_LISTA[clave];
        if (clave.startsWith('cond:'))  return 'Niños con ' + datosPanel.condiciones[clave.slice(5)].toLowerCase();
        if (clave.startsWith('nivel:')) return 'Niños: ' + datosPanel.etiquetasNivel[clave.slice(6)];
        return NOMBRES_LISTA.alerta;
    }

    /** Crea un elemento con texto (nunca HTML: así un nombre no puede inyectar código). */
    function elemento(etiqueta, texto, clase, atributos) {
        const el = document.createElement(etiqueta);
        if (texto !== undefined && texto !== null) el.textContent = texto;
        if (clase) el.className = clase;
        Object.entries(atributos || {}).forEach(([k, v]) => el.setAttribute(k, v));
        return el;
    }

    function mostrarLista(clave, desplazar) {
        const ninos = datosPanel.ninos.filter(filtroDeLista(clave))
            .sort((a, b) => GRAVEDAD.indexOf(a.nivel) - GRAVEDAD.indexOf(b.nivel) || a.nombre.localeCompare(b.nombre));

        document.getElementById('detalle-titulo').textContent = tituloDeLista(clave) + ' (' + ninos.length + ')';
        document.getElementById('detalle-quitar').hidden = (clave === 'alerta');

        const contenedor = document.getElementById('detalle-lista');
        contenedor.replaceChildren();
        if (ninos.length === 0) {
            contenedor.appendChild(elemento('p', 'No hay niños en este grupo.', 'vacio'));
        } else {
            const tabla = elemento('table', null, 'tabla tabla-tarjetas');
            const cabecera = elemento('tr');
            ['Niño', 'Sector', 'Paraje', 'Edad', 'Último control', 'Condición', ''].forEach(t => cabecera.appendChild(elemento('th', t)));
            tabla.appendChild(elemento('thead')).appendChild(cabecera);
            const cuerpo = tabla.appendChild(elemento('tbody'));
            ninos.forEach(n => {
                const fila = elemento('tr');
                fila.appendChild(elemento('td', n.nombre, 'celda-titulo'));
                fila.appendChild(elemento('td', n.sector, null, { 'data-label': 'Sector' }));
                fila.appendChild(elemento('td', n.paraje || 'Sin paraje', null, { 'data-label': 'Paraje' }));
                fila.appendChild(elemento('td', n.edad, null, { 'data-label': 'Edad' }));
                fila.appendChild(elemento('td', n.control || 'Sin control', null, { 'data-label': 'Último control' }));
                const condicion = elemento('td', null, null, { 'data-label': 'Condición' });
                const etiqueta = elemento('span', datosPanel.etiquetasNivel[n.nivel], 'etiqueta etiqueta-nivel');
                etiqueta.style.setProperty('--color-nivel', COLORES[n.nivel]);
                condicion.appendChild(etiqueta);
                if (n.atrasado === true) condicion.appendChild(elemento('span', 'Control atrasado', 'etiqueta etiqueta-aviso'));
                fila.appendChild(condicion);
                const acciones = elemento('td', null, 'acciones-fila');
                acciones.appendChild(elemento('a', 'Ver historial', null, { href: datosPanel.urlHistorial + Number(n.id) }));
                fila.appendChild(acciones);
                cuerpo.appendChild(fila);
            });
            const marco = elemento('div', null, 'tabla-scroll');
            marco.appendChild(tabla);
            contenedor.appendChild(marco);
        }
        if (desplazar) {
            document.getElementById('detalle-panel').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    // ---------- Filtros del formulario ----------

    function prepararFiltros() {
        // Solo los parajes del sector elegido, como en el registro de niños.
        const sector = document.getElementById('filtro-sector');
        const paraje = document.getElementById('filtro-paraje');
        if (sector && paraje) {
            const opciones = Array.from(paraje.options);
            const filtrar = () => {
                const elegido = paraje.value;
                paraje.replaceChildren(...opciones.filter(o => o.value === '' || !sector.value || o.dataset.sector === sector.value));
                paraje.value = opciones.some(o => o.value === elegido && (!sector.value || o.dataset.sector === sector.value)) ? elegido : '';
            };
            sector.addEventListener('change', filtrar);
            filtrar();
        }
        // Al cambiar una fecha, el período pasa a "Elegir fechas".
        const periodo = document.getElementById('filtro-periodo');
        ['filtro-desde', 'filtro-hasta'].forEach(id => {
            const campo = document.getElementById(id);
            if (campo && periodo) campo.addEventListener('change', () => { periodo.value = ''; });
        });
        // En la computadora los filtros se muestran abiertos; en el teléfono, plegados.
        const panel = document.getElementById('panel-filtros');
        if (panel && window.matchMedia('(min-width: 701px)').matches) panel.open = true;
    }

    // ---------- Inicio ----------

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof datosPanel === 'undefined') return;
        graficaSector();
        graficaParaje();
        graficaNivel();
        graficaCondicion();
        graficaTendencia();

        if (impresion) {
            // Vista para PDF: abre el diálogo de impresión ("Guardar como PDF").
            if (!/[?&]sin_imprimir=1/.test(window.location.search)) {
                setTimeout(() => window.print(), 600);
            }
            return;
        }

        prepararFiltros();
        document.querySelectorAll('[data-lista]').forEach(boton => {
            boton.addEventListener('click', () => mostrarLista(boton.dataset.lista, true));
        });
        document.getElementById('detalle-quitar').addEventListener('click', () => mostrarLista('alerta', false));
        mostrarLista('alerta', false);
    });
})();
