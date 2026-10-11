<?php
/**
 * Iconos del sistema, incrustados como SVG.
 *
 * Se incluyen dentro del proyecto en lugar de cargarse desde un servicio
 * externo. De esta forma el sistema funciona aunque falle la conexion a
 * internet y no depende de que un proveedor externo siga disponible, lo
 * cual es apropiado para un puesto de salud rural.
 *
 * Uso en una vista:
 *   <?= Icono::ver('ninos') ?>
 *   <?= Icono::ver('guardar', 28) ?>   (tamano en pixeles)
 */

class Icono
{
    /** Tamano por omision, en pixeles. */
    const TAMANO = 22;

    /**
     * Devuelve el codigo SVG del icono solicitado.
     * Si el nombre no existe, devuelve cadena vacia (no rompe la vista).
     */
    public static function ver(string $nombre, int $tamano = self::TAMANO): string
    {
        $trazos = self::trazos();
        if (!isset($trazos[$nombre])) {
            return '';
        }

        return '<svg class="icono" width="' . $tamano . '" height="' . $tamano . '"'
             . ' viewBox="0 0 24 24" fill="none" stroke="currentColor"'
             . ' stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"'
             . ' aria-hidden="true" focusable="false">'
             . $trazos[$nombre]
             . '</svg>';
    }

    /**
     * Trazos SVG de cada icono. Dibujos propios de lineas simples,
     * coherentes entre si (mismo grosor y estilo).
     */
    private static function trazos(): array
    {
        return [
            // Navegacion principal
            'ninos' => '<circle cx="9" cy="7" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17.5" cy="9.5" r="2"/><path d="M15.5 20c0-2.2 1.3-4 3.5-4s3.5 1.8 3.5 4"/>',
            'seguimiento' => '<path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M7 15l3.5-4 3 3L20 7"/><circle cx="20" cy="7" r="1.6"/>',
            'citas' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/><path d="M9 15l2 2 4-4"/>',
            'reportes' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h4"/>',
            'usuarios' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.9 3.1-7 7-7s7 3.1 7 7"/>',

            // Acciones
            'agregar' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
            'guardar' => '<path d="M5 12l5 5L19 7"/>',
            'editar' => '<path d="M4 20h4l10-10a2.1 2.1 0 0 0-3-3L5 17z"/><path d="M14.5 6.5l3 3"/>',
            'ojo' => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
            'buscar' => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
            'filtrar' => '<path d="M3 5h18l-7 8v6l-4 2v-8z"/>',
            'volver' => '<path d="M15 5l-7 7 7 7"/>',
            'avanzar' => '<path d="M9 5l7 7-7 7"/>',
            'lista' => '<path d="M9 6h11M9 12h11M9 18h11"/><path d="M4.5 6h.01M4.5 12h.01M4.5 18h.01"/>',
            'ordenar' => '<path d="M8 9l4-4 4 4"/><path d="M8 15l4 4 4-4"/>',
            'orden-asc' => '<path d="M12 19V5"/><path d="M6 11l6-6 6 6"/>',
            'orden-desc' => '<path d="M12 5v14"/><path d="M6 13l6 6 6-6"/>',
            'salir' => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 17l-5-5 5-5"/><path d="M5 12h11"/>',
            'cancelar' => '<circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/>',
            'imprimir' => '<path d="M7 9V3h10v6"/><rect x="4" y="9" width="16" height="7" rx="2"/><path d="M7 14h10v7H7z"/>',
            'descargar' => '<path d="M12 4v11"/><path d="M8 11l4 4 4-4"/><path d="M4 19h16"/>',
            'whatsapp' => '<path d="M3.5 20.5l1.3-4.3A8 8 0 1 1 8 19.3z"/><path d="M8.8 9.2c.3 1.6 2.4 3.7 4 4l1-1.1 1.7.8-.3 1.4c-1.9.5-5.6-2.6-6.4-5.3l1.3-.6z"/>',

            // Modulos del carnet
            'medicion' => '<path d="M4 14l10-10 6 6-10 10z"/><path d="M8 10l1.5 1.5M11 7l1.5 1.5M5 13l1.5 1.5"/><path d="M4 20h4"/>',
            'grafica' => '<path d="M3 3v16a2 2 0 0 0 2 2h16"/><rect x="7" y="12" width="3" height="6"/><rect x="12" y="8" width="3" height="10"/><rect x="17" y="5" width="3" height="13"/>',
            'vacuna' => '<path d="M17 3l4 4"/><path d="M19.5 5.5L14 11l-1 4-4 1-5.5 5.5"/><path d="M9 12l3 3"/><path d="M12 8l4 4"/>',
            'suplemento' => '<rect x="3" y="9" width="12" height="6" rx="3" transform="rotate(-45 9 12)"/><path d="M9 9l6 6"/>',
            'historial' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',

            // Campos de formulario (se muestran dentro del recuadro, a la izquierda)
            'persona' => '<circle cx="12" cy="7.5" r="3.5"/><path d="M5 21c0-3.9 3.1-7 7-7s7 3.1 7 7"/>',
            'calendario' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/><path d="M7.5 14h.01M12 14h.01M16.5 14h.01M7.5 17.5h.01M12 17.5h.01"/>',
            'sexo' => '<circle cx="10" cy="13" r="5"/><path d="M13.5 9.5L20 3"/><path d="M15.5 3H20v4.5"/><path d="M10 18v4M8 20h4"/>',
            'documento' => '<rect x="2.5" y="5" width="19" height="14" rx="2"/><circle cx="8.5" cy="11" r="2"/><path d="M5.5 16c.5-1.4 1.6-2.2 3-2.2s2.5.8 3 2.2"/><path d="M14 10h4.5M14 13.5h3"/>',
            'idioma' => '<path d="M4 4h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H9l-5 4V5a1 1 0 0 1 1-1z"/><path d="M8 8.5h8M8 11.5h5"/>',
            'mapa' => '<path d="M3 6.5l6-2.5 6 2.5 6-2.5v13.5l-6 2.5-6-2.5-6 2.5z"/><path d="M9 4v13.5M15 6.5V20"/>',
            'ubicacion' => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
            'casa' => '<path d="M3 11l9-7 9 7"/><path d="M5 9.5V20h14V9.5"/><path d="M10 20v-5h4v5"/>',
            'telefono' => '<rect x="7" y="2.5" width="10" height="19" rx="2"/><path d="M11 18h2"/>',

            // Estados y avisos
            'alerta' => '<path d="M12 4l9 16H3z"/><path d="M12 10v4M12 17h.01"/>',
            'correcto' => '<circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-5"/>',
            'informacion' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
            'candado' => '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
        ];
    }
}
