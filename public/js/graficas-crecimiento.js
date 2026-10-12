/**
 * Dibuja las gráficas de crecimiento de la OMS con Chart.js.
 * Cada gráfica muestra: curva -2 DE, mediana, +2 DE (referencia OMS)
 * y el recorrido del niño (puntos unidos).
 *
 * Espera la variable global `datosGraficas`, con la forma:
 *   {
 *     peso_edad:  { titulo, eje_x, eje_y, referencia:[{x,menos2,mediana,mas2}], nino:[{x,y,fecha}] },
 *     talla_edad: {...},
 *     peso_talla: {...}
 *   }
 *
 * Pensado primero para el teléfono:
 *   - De entrada se muestra solo el tramo de la curva donde están las
 *     mediciones del niño (con margen), para que sus puntos no queden
 *     amontonados al inicio de una curva de 0 a 5 años. El botón
 *     "Curva completa" muestra toda la tabla de la OMS.
 *   - "Ampliar" abre una gráfica a pantalla completa. Se cierra con su botón,
 *     con la tecla Escape o con el botón de atrás del teléfono.
 */
(function () {
    'use strict';

    const COLOR_LIMITE  = '#d9534f';  // -2 y +2 (rojo suave)
    const COLOR_MEDIANA = '#1d9e75';  // mediana (teal)
    const COLOR_NINO    = '#14304a';  // recorrido del niño (azul marino)

    // Margen alrededor de las mediciones y ancho mínimo del tramo mostrado:
    // en meses para las gráficas por edad y en cm para la de peso para la talla.
    const TRAMO = {
        edad:  { margen: 2, minimo: 8 },
        talla: { margen: 3, minimo: 12 },
    };

    const LIENZOS = {
        peso_edad:  'grafica_peso_edad',
        talla_edad: 'grafica_talla_edad',
        peso_talla: 'grafica_peso_talla',
    };

    const graficas = {};          // Gráficas dibujadas, por indicador.
    let vista = 'nino';           // 'nino' o 'completa'.

    /** Tramo [desde, hasta] del eje X que se muestra. */
    function tramoVisible(datos, esTalla) {
        const xs = datos.referencia.map(p => p.x);
        const inicio = xs[0];
        const fin = xs[xs.length - 1];
        if (vista === 'completa' || datos.nino.length === 0) {
            return [inicio, fin];
        }
        const regla = esTalla ? TRAMO.talla : TRAMO.edad;
        const puntosX = datos.nino.map(p => p.x);
        let desde = Math.min(...puntosX) - regla.margen;
        let hasta = Math.max(...puntosX) + regla.margen;
        // Ancho mínimo, repartido a ambos lados de las mediciones.
        if (hasta - desde < regla.minimo) {
            const falta = (regla.minimo - (hasta - desde)) / 2;
            desde -= falta;
            hasta += falta;
        }
        // Sin salirse de la tabla de la OMS.
        if (desde < inicio) { hasta += inicio - desde; desde = inicio; }
        if (hasta > fin)    { desde -= hasta - fin;    hasta = fin; }
        return [Math.max(desde, inicio), Math.min(hasta, fin)];
    }

    function construir(indicador, datos) {
        const canvas = document.getElementById(LIENZOS[indicador]);
        if (!canvas || !datos || !datos.referencia || datos.referencia.length === 0) return;

        const esTalla = indicador === 'peso_talla';
        const [desde, hasta] = tramoVisible(datos, esTalla);

        // Solo los puntos de referencia del tramo visible: así el eje Y se
        // ajusta a ese tramo y la curva se ve con detalle.
        const ref = datos.referencia.filter(p => p.x >= desde - 1 && p.x <= hasta + 1);
        const unidad = datos.eje_y.indexOf('kg') !== -1 ? ' kg' : ' cm';

        if (graficas[indicador]) {
            graficas[indicador].destroy();
        }

        graficas[indicador] = new Chart(canvas, {
            type: 'line',
            data: {
                datasets: [
                    {
                        label: '+2 DE',
                        data: ref.map(p => ({ x: p.x, y: p.mas2 })),
                        borderColor: COLOR_LIMITE,
                        borderWidth: 1.5,
                        borderDash: [6, 4],
                        pointRadius: 0,
                        tension: 0.3,
                    },
                    {
                        label: 'Mediana',
                        data: ref.map(p => ({ x: p.x, y: p.mediana })),
                        borderColor: COLOR_MEDIANA,
                        borderWidth: 2,
                        pointRadius: 0,
                        tension: 0.3,
                    },
                    {
                        label: '-2 DE',
                        data: ref.map(p => ({ x: p.x, y: p.menos2 })),
                        borderColor: COLOR_LIMITE,
                        borderWidth: 1.5,
                        borderDash: [6, 4],
                        pointRadius: 0,
                        tension: 0.3,
                    },
                    {
                        label: 'Niño',
                        data: datos.nino.map(p => ({ x: p.x, y: p.y, fecha: p.fecha })),
                        borderColor: COLOR_NINO,
                        backgroundColor: COLOR_NINO,
                        borderWidth: 2.5,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        pointHitRadius: 14,   // fácil de tocar con el dedo
                        showLine: true,
                        tension: 0,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,   // el alto lo da el CSS (.lienzo-grafica)
                animation: false,
                interaction: { mode: 'nearest', intersect: false },
                scales: {
                    x: {
                        type: 'linear',
                        min: Math.floor(desde),
                        max: Math.ceil(hasta),
                        title: { display: true, text: datos.eje_x },
                    },
                    y: {
                        title: { display: true, text: datos.eje_y },
                        grace: '4%',
                    },
                },
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 18, font: { size: 12 } } },
                    tooltip: {
                        callbacks: {
                            title: (items) => {
                                const punto = items[0].raw;
                                return punto.fecha
                                    ? 'Control del ' + punto.fecha
                                    : datos.eje_x + ': ' + items[0].parsed.x;
                            },
                            label: (item) => item.dataset.label + ': ' + item.parsed.y + unidad,
                        },
                    },
                },
            },
        });
    }

    function dibujarTodas() {
        Object.keys(LIENZOS).forEach(function (indicador) {
            if (datosGraficas[indicador]) construir(indicador, datosGraficas[indicador]);
        });
    }

    // ---------- Edad del niño / curva completa ----------
    function prepararSelectorDeVista() {
        const botones = document.querySelectorAll('.vista-graficas button');
        botones.forEach(function (boton) {
            boton.addEventListener('click', function () {
                vista = boton.dataset.vista;
                botones.forEach(function (b) {
                    const activo = b === boton;
                    b.classList.toggle('activa', activo);
                    b.setAttribute('aria-pressed', activo ? 'true' : 'false');
                });
                dibujarTodas();
            });
        });
    }

    // ---------- Ampliar a pantalla completa ----------
    let ampliada = null;   // Tarjeta abierta en pantalla completa.

    function abrir(tarjeta) {
        ampliada = tarjeta;
        tarjeta.classList.add('ampliada');
        document.body.classList.add('con-grafica-ampliada');
        const boton = tarjeta.querySelector('[data-ampliar]');
        boton.querySelector('[data-texto]').textContent = 'Cerrar';
        boton.setAttribute('aria-expanded', 'true');
        // Una entrada en el historial: el botón de atrás del teléfono cierra la gráfica.
        history.pushState({ graficaAmpliada: true }, '');
    }

    function cerrar(desdeHistorial) {
        if (!ampliada) return;
        const tarjeta = ampliada;
        ampliada = null;
        tarjeta.classList.remove('ampliada');
        document.body.classList.remove('con-grafica-ampliada');
        const boton = tarjeta.querySelector('[data-ampliar]');
        boton.querySelector('[data-texto]').textContent = 'Ampliar';
        boton.setAttribute('aria-expanded', 'false');
        boton.focus();
        if (!desdeHistorial && history.state && history.state.graficaAmpliada) {
            history.back();
        }
    }

    function prepararAmpliar() {
        document.querySelectorAll('[data-ampliar]').forEach(function (boton) {
            boton.setAttribute('aria-expanded', 'false');
            boton.addEventListener('click', function () {
                const tarjeta = boton.closest('.tarjeta-grafica');
                if (ampliada === tarjeta) {
                    cerrar(false);
                } else {
                    cerrar(false);
                    abrir(tarjeta);
                }
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') cerrar(false);
        });
        window.addEventListener('popstate', function () {
            cerrar(true);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof datosGraficas === 'undefined') return;
        dibujarTodas();
        prepararSelectorDeVista();
        prepararAmpliar();
    });
})();
