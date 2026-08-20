/**
 * Dibuja las gráficas de crecimiento de la OMS con Chart.js.
 * Cada gráfica muestra: curva -2 DE, mediana, +2 DE (referencia OMS)
 * y el recorrido del niño (puntos unidos).
 *
 * Espera la variable global `datosGraficas`, con la forma:
 *   {
 *     peso_edad:  { eje_x, eje_y, referencia:[{x,menos2,mediana,mas2}], nino:[{x,y}] },
 *     talla_edad: {...},
 *     peso_talla: {...}
 *   }
 */
(function () {
    'use strict';

    const COLOR_LIMITE   = '#d9534f';  // -2 y +2 (rojo suave)
    const COLOR_MEDIANA  = '#1d9e75';  // mediana (teal)
    const COLOR_NINO     = '#14304a';  // recorrido del niño (azul marino)

    function construir(idCanvas, datos) {
        const canvas = document.getElementById(idCanvas);
        if (!canvas || !datos || !datos.referencia) return;

        // Ejes X (edad o talla) tomados de la referencia.
        const etiquetasX = datos.referencia.map(p => p.x);

        // Series de referencia.
        const serieMenos2  = datos.referencia.map(p => p.menos2);
        const serieMediana = datos.referencia.map(p => p.mediana);
        const serieMas2    = datos.referencia.map(p => p.mas2);

        // Puntos del niño: Chart.js los ubica por coordenada {x, y}.
        const puntosNino = datos.nino.map(p => ({ x: p.x, y: p.y }));

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: etiquetasX,
                datasets: [
                    {
                        label: '+2 DE',
                        data: serieMas2,
                        borderColor: COLOR_LIMITE,
                        borderWidth: 1,
                        borderDash: [5, 4],
                        pointRadius: 0,
                        fill: false,
                        tension: 0.3,
                    },
                    {
                        label: 'Mediana',
                        data: serieMediana,
                        borderColor: COLOR_MEDIANA,
                        borderWidth: 1.5,
                        pointRadius: 0,
                        fill: false,
                        tension: 0.3,
                    },
                    {
                        label: '−2 DE',
                        data: serieMenos2,
                        borderColor: COLOR_LIMITE,
                        borderWidth: 1,
                        borderDash: [5, 4],
                        pointRadius: 0,
                        fill: false,
                        tension: 0.3,
                    },
                    {
                        label: 'Niño',
                        data: puntosNino,
                        borderColor: COLOR_NINO,
                        backgroundColor: COLOR_NINO,
                        borderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        fill: false,
                        showLine: true,
                        tension: 0,
                        parsing: false, // usamos {x,y} directos
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                interaction: { mode: 'nearest', intersect: false },
                scales: {
                    x: {
                        type: 'linear',
                        title: { display: true, text: datos.eje_x },
                        min: etiquetasX[0],
                        max: etiquetasX[etiquetasX.length - 1],
                    },
                    y: {
                        title: { display: true, text: datos.eje_y },
                        beginAtZero: false,
                    },
                },
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 18, font: { size: 11 } } },
                    tooltip: {
                        callbacks: {
                            title: (items) => datos.eje_x + ': ' + items[0].parsed.x,
                            label: (item) => item.dataset.label + ': ' + item.parsed.y,
                        },
                    },
                },
            },
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof datosGraficas === 'undefined') return;
        if (datosGraficas.peso_edad)  construir('grafica_peso_edad',  datosGraficas.peso_edad);
        if (datosGraficas.talla_edad) construir('grafica_talla_edad', datosGraficas.talla_edad);
        if (datosGraficas.peso_talla) construir('grafica_peso_talla', datosGraficas.peso_talla);
    });
})();
