<?php
/**
 * Listado de usuarios (área de administración).
 * Variables: $usuarios, $mensaje (opcional), $temporal y $usuarioTemporal (opcionales)
 */
$titulo = 'Gestión de usuarios';
require __DIR__ . '/../layouts/encabezado.php';

// Mensaje con los datos de ingreso, listo para copiar o enviar por WhatsApp.
$mensajeIngreso = '';
if (!empty($temporal)) {
    $mensajeIngreso = APP_NOMBRE . "\n"
        . 'Dirección: ' . APP_URL . "\n"
        . (!empty($usuarioTemporal) ? 'Usuario: ' . $usuarioTemporal . "\n" : '')
        . 'Contraseña temporal: ' . $temporal . "\n"
        . 'Al ingresar, el sistema le pedirá cambiarla por una contraseña suya.';
}
?>

<h1 class="titulo-pagina">Gestión de usuarios</h1>

<?php if (!empty($mensaje)): ?>
    <div class="mensaje-ok"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>

<?php if (!empty($temporal)): ?>
    <div class="caja-temporal">
        <?php if (!empty($usuarioTemporal)): ?>
            <p class="dato-temporal"><span>Usuario:</span>
                <code class="pass-temporal"><?= htmlspecialchars($usuarioTemporal) ?></code></p>
        <?php endif; ?>
        <p class="dato-temporal"><span>Contraseña temporal:</span>
            <code class="pass-temporal"><?= htmlspecialchars($temporal) ?></code></p>
        <div class="acciones-temporal">
            <button type="button" class="btn-secundario" id="copiar-ingreso"
                    data-texto="<?= htmlspecialchars($mensajeIngreso) ?>">
                <?= Icono::ver('copiar', 18) ?> <span>Copiar datos de ingreso</span>
            </button>
            <a class="btn-whatsapp" target="_blank" rel="noopener"
               href="https://wa.me/?text=<?= rawurlencode($mensajeIngreso) ?>">
                <?= Icono::ver('whatsapp', 18) ?> Enviar por WhatsApp
            </a>
        </div>
        <p>Envíela solo a la persona dueña de la cuenta. Se le pedirá cambiarla al ingresar.
           Esta contraseña no se volverá a mostrar.</p>
    </div>
    <script>
        // Copia la dirección, el usuario y la contraseña temporal, para pegarlos
        // en un mensaje o en un documento.
        (function () {
            var boton = document.getElementById('copiar-ingreso');
            var etiqueta = boton.querySelector('span');
            function avisar(texto) {
                etiqueta.textContent = texto;
                setTimeout(function () { etiqueta.textContent = 'Copiar datos de ingreso'; }, 2500);
            }
            function copiarSinPortapapeles(texto) {
                var area = document.createElement('textarea');
                area.value = texto;
                area.setAttribute('readonly', '');
                area.style.position = 'fixed';
                area.style.opacity = '0';
                document.body.appendChild(area);
                area.select();
                var listo = false;
                try { listo = document.execCommand('copy'); } catch (e) { listo = false; }
                document.body.removeChild(area);
                avisar(listo ? 'Copiado' : 'No se pudo copiar');
            }
            boton.addEventListener('click', function () {
                var texto = boton.dataset.texto;
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(texto).then(function () { avisar('Copiado'); },
                        function () { copiarSinPortapapeles(texto); });
                } else {
                    copiarSinPortapapeles(texto);
                }
            });
        })();
    </script>
<?php endif; ?>

<div class="barra-acciones">
    <a href="<?= APP_URL ?>/index.php?accion=usuario_nuevo" class="btn-primario">Nuevo usuario</a>
    <!-- El catálogo de suplementos también lo mantiene el administrador -->
    <a href="<?= APP_URL ?>/index.php?accion=suplementos_catalogo" class="btn-secundario">
        <?= Icono::ver('suplemento', 20) ?> Catálogo de suplementos
    </a>
</div>

<table class="tabla tabla-tarjetas">
    <thead>
        <tr>
            <th>Nombre</th>
            <th>Usuario</th>
            <th>Correo</th>
            <th>Rol</th>
            <th>Sector</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($usuarios as $u): ?>
            <tr>
                <td class="celda-titulo"><?= htmlspecialchars($u['nombre_completo']) ?></td>
                <td data-label="Usuario"><?= htmlspecialchars($u['usuario']) ?></td>
                <td data-label="Correo" class="celda-correo"><?= Formato::dato($u['correo'], 'Sin correo') ?></td>
                <td data-label="Rol"><?= htmlspecialchars(ucfirst($u['rol'])) ?></td>
                <td data-label="Sector">
                    <?php if ($u['sector_letra']): ?>
                        T<?= htmlspecialchars($u['territorio_numero']) ?> · <?= htmlspecialchars($u['sector_letra']) ?>
                    <?php elseif ($u['rol'] === 'enfermeria'): ?>
                        Apoyo (todos los sectores)
                    <?php else: ?>
                        <?= Formato::sinDato('Sin sector') ?>
                    <?php endif; ?>
                </td>
                <td data-label="Estado">
                    <span class="etiqueta etiqueta-<?= $u['activo'] ? 'activo' : 'egresado' ?>">
                        <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
                    </span>
                    <?php if ($u['debe_cambiar_pass']): ?>
                        <span class="etiqueta etiqueta-aviso">Contraseña temporal</span>
                    <?php endif; ?>
                </td>
                <td class="acciones-fila">
                    <!-- Corregir nombre, usuario, rol o sector -->
                    <a href="<?= APP_URL ?>/index.php?accion=usuario_editar&id=<?= (int)$u['id_usuario'] ?>"
                       class="enlace-boton">Editar</a>

                    <!-- Restablecer contraseña -->
                    <form method="post" action="<?= APP_URL ?>/index.php?accion=usuario_restablecer"
                          onsubmit="return confirm('¿Generar una contraseña temporal para este usuario?');"
                          style="display:inline;">
                        <input type="hidden" name="id" value="<?= (int)$u['id_usuario'] ?>">
                        <button type="submit" class="enlace-boton">Restablecer contraseña</button>
                    </form>

                    <!-- Baja / reactivar -->
                    <form method="post" action="<?= APP_URL ?>/index.php?accion=usuario_estado"
                          style="display:inline;">
                        <input type="hidden" name="id" value="<?= (int)$u['id_usuario'] ?>">
                        <input type="hidden" name="activo" value="<?= $u['activo'] ? '0' : '1' ?>">
                        <button type="submit" class="enlace-boton">
                            <?= $u['activo'] ? 'Dar de baja' : 'Reactivar' ?>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require __DIR__ . '/../layouts/pie.php'; ?>
