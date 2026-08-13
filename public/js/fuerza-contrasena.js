/**
 * Indicador de fuerza de contraseña.
 *
 * Uso en una vista:
 *   <input type="password" id="mi_pass" ...>
 *   <div class="medidor-fuerza" data-para="mi_pass"></div>
 *   <script src="<?= APP_URL ?>/js/fuerza-contrasena.js"></script>
 *
 * Requisito MÍNIMO obligatorio (se valida también en el servidor):
 *   - al menos 8 caracteres
 *   - al menos una letra y un número
 * Lo demás (mayúsculas, símbolos) SUMA fuerza pero no es obligatorio.
 */
(function () {
    'use strict';

    function evaluar(pass) {
        var puntos = 0;
        if (pass.length >= 8)  puntos++;
        if (pass.length >= 12) puntos++;
        if (/[a-z]/.test(pass) && /[A-Z]/.test(pass)) puntos++;
        if (/\d/.test(pass)) puntos++;
        if (/[^A-Za-z0-9]/.test(pass)) puntos++;

        // Cumple el mínimo obligatorio?
        var cumpleMinimo = pass.length >= 8 && /[A-Za-z]/.test(pass) && /\d/.test(pass);

        var nivel, etiqueta, color, ancho;
        if (pass.length === 0) {
            nivel = 0; etiqueta = ''; color = '#ddd'; ancho = '0%';
        } else if (!cumpleMinimo) {
            nivel = 1; etiqueta = 'No cumple el mínimo (8+, letras y números)'; color = '#d9534f'; ancho = '25%';
        } else if (puntos <= 2) {
            nivel = 2; etiqueta = 'Débil'; color = '#e0a800'; ancho = '45%';
        } else if (puntos === 3) {
            nivel = 3; etiqueta = 'Media'; color = '#f0ad4e'; ancho = '65%';
        } else if (puntos === 4) {
            nivel = 4; etiqueta = 'Fuerte'; color = '#5cb85c'; ancho = '85%';
        } else {
            nivel = 5; etiqueta = 'Muy fuerte'; color = '#2e7d32'; ancho = '100%';
        }
        return { etiqueta: etiqueta, color: color, ancho: ancho, cumpleMinimo: cumpleMinimo };
    }

    function iniciar(medidor) {
        var idInput = medidor.getAttribute('data-para');
        var input = document.getElementById(idInput);
        if (!input) return;

        // Construye la barra y el texto
        medidor.innerHTML =
            '<div class="barra-fuerza"><div class="barra-fuerza-relleno"></div></div>' +
            '<div class="texto-fuerza"></div>' +
            '<ul class="reglas-fuerza">' +
              '<li data-regla="largo">Al menos 8 caracteres</li>' +
              '<li data-regla="letranum">Letras y números</li>' +
              '<li data-regla="mayus">Mayúsculas y minúsculas (sugerido)</li>' +
              '<li data-regla="simbolo">Un símbolo (sugerido)</li>' +
            '</ul>';

        var relleno = medidor.querySelector('.barra-fuerza-relleno');
        var texto   = medidor.querySelector('.texto-fuerza');
        var rLargo   = medidor.querySelector('[data-regla="largo"]');
        var rLetnum  = medidor.querySelector('[data-regla="letranum"]');
        var rMayus   = medidor.querySelector('[data-regla="mayus"]');
        var rSimbolo = medidor.querySelector('[data-regla="simbolo"]');

        input.addEventListener('input', function () {
            var pass = input.value;
            var r = evaluar(pass);
            relleno.style.width = r.ancho;
            relleno.style.background = r.color;
            texto.textContent = r.etiqueta;
            texto.style.color = r.color;

            marcar(rLargo,   pass.length >= 8);
            marcar(rLetnum,  /[A-Za-z]/.test(pass) && /\d/.test(pass));
            marcar(rMayus,   /[a-z]/.test(pass) && /[A-Z]/.test(pass));
            marcar(rSimbolo, /[^A-Za-z0-9]/.test(pass));
        });
    }

    function marcar(el, ok) {
        if (!el) return;
        el.classList.toggle('regla-ok', ok);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var medidores = document.querySelectorAll('.medidor-fuerza');
        Array.prototype.forEach.call(medidores, iniciar);
    });
})();
