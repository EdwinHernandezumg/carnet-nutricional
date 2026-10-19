/**
 * Filtro de lo que se escribe en ciertos campos, según su atributo data-filtro:
 *   nombre    letras (con tildes y eñe), espacios, apóstrofo, punto y guion
 *   palabras  lo mismo que nombre, y además coma
 *   digitos   solo números (teléfono, CUI)
 *   usuario   letras sin tilde, números, punto, guion y guion bajo
 *   fecha     números y la barra de día/mes/año
 *   codigo    letras sin tilde, números, guion y barra (número de carnet)
 *
 * Si se escribe o se pega un carácter no permitido, simplemente no aparece.
 * Es solo una comodidad: el servidor vuelve a validar todo
 * (src/core/Validacion.php), porque el navegador se puede saltar.
 */
(function () {
    var noPermitidos = {
        nombre:   /[^\p{L}\s'.\-]/gu,
        palabras: /[^\p{L}\s'.,\-]/gu,
        digitos:  /\D/g,
        usuario:  /[^A-Za-z0-9._\-]/g,
        fecha:    /[^\d\/]/g,
        codigo:   /[^A-Za-z0-9\/\-]/g
    };

    document.addEventListener('input', function (evento) {
        var campo = evento.target;
        var filtro = campo.dataset ? noPermitidos[campo.dataset.filtro] : null;
        if (!filtro) {
            return;
        }
        var limpio = campo.value.replace(filtro, '');
        if (limpio === campo.value) {
            return;
        }
        // Conserva la posición del cursor después de quitar lo no permitido.
        var posicion = campo.selectionStart - (campo.value.length - limpio.length);
        campo.value = limpio;
        try {
            campo.setSelectionRange(posicion, posicion);
        } catch (e) {
            // Algunos tipos de campo no admiten mover el cursor; no pasa nada.
        }
    });
})();
