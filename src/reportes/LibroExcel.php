<?php
/**
 * Genera libros de Excel (.xlsx) en PHP puro, sin librerías externas.
 *
 * Un archivo .xlsx es un ZIP que contiene varios archivos XML (el formato
 * Office Open XML). Esta clase escribe esos XML (libro, hojas y estilos) y los
 * empaqueta en un ZIP sin compresión, que Excel, LibreOffice y Google Sheets
 * abren sin problema. Así el sistema exporta a Excel en cualquier hosting,
 * aunque no tenga instalada la extensión ZIP de PHP, y sin depender de
 * librerías de terceros (decisión de diseño: reportes sin librerías externas).
 *
 * Uso:
 *   $libro = new LibroExcel();
 *   $libro->agregarHoja('Resumen', [['Indicador', 'Valor'], ['Niños activos', 184]], 1);
 *   $libro->descargar('reporte_nutricional');
 */

class LibroExcel
{
    /** Hojas: [nombre, filas, cantidad de filas de encabezado en negrita]. */
    private array $hojas = [];

    /**
     * Agrega una hoja. $filas es un arreglo de filas; cada fila, un arreglo de
     * valores (texto o número). Las primeras $filasNegrita filas van en negrita.
     */
    public function agregarHoja(string $nombre, array $filas, int $filasNegrita = 1): void
    {
        // Excel no admite estos caracteres en el nombre de una hoja, ni más de 31.
        $nombre = mb_substr(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $nombre), 0, 31);
        $this->hojas[] = [$nombre, $filas, $filasNegrita];
    }

    /** Envía el libro al navegador para descargarlo y termina. */
    public function descargar(string $nombreArchivo): void
    {
        $contenido = $this->generar();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.xlsx"');
        header('Content-Length: ' . strlen($contenido));
        header('Cache-Control: no-store');
        echo $contenido;
        exit;
    }

    /** Contenido binario del archivo .xlsx. */
    public function generar(): string
    {
        $archivos = [
            '[Content_Types].xml'        => $this->tiposDeContenido(),
            '_rels/.rels'                => self::XML . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '</Relationships>',
            'xl/workbook.xml'            => $this->libro(),
            'xl/_rels/workbook.xml.rels' => $this->relacionesDelLibro(),
            'xl/styles.xml'              => self::ESTILOS,
        ];
        foreach ($this->hojas as $i => [, $filas, $negrita]) {
            $archivos['xl/worksheets/sheet' . ($i + 1) . '.xml'] = $this->hoja($filas, $negrita);
        }
        return self::zip($archivos);
    }

    // ---------- Los XML del formato Office Open XML ----------

    private const XML = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";

    /** Dos estilos: 0 normal y 1 en negrita. */
    private const ESTILOS = self::XML
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
        . '</styleSheet>';

    private function tiposDeContenido(): string
    {
        $hojas = '';
        foreach ($this->hojas as $i => $h) {
            $hojas .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" '
                . 'ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return self::XML . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $hojas . '</Types>';
    }

    private function libro(): string
    {
        $hojas = '';
        foreach ($this->hojas as $i => [$nombre]) {
            $hojas .= '<sheet name="' . self::texto($nombre) . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
        }
        return self::XML . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $hojas . '</sheets></workbook>';
    }

    private function relacionesDelLibro(): string
    {
        $rel = '';
        foreach ($this->hojas as $i => $h) {
            $rel .= '<Relationship Id="rId' . ($i + 1) . '" '
                . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" '
                . 'Target="worksheets/sheet' . ($i + 1) . '.xml"/>';
        }
        $rel .= '<Relationship Id="rId' . (count($this->hojas) + 1) . '" '
            . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        return self::XML . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $rel . '</Relationships>';
    }

    /** Una hoja: ancho de columnas según el contenido y una celda por valor. */
    private function hoja(array $filas, int $filasNegrita): string
    {
        // Ancho de cada columna: el texto más largo, entre 8 y 60 caracteres.
        $anchos = [];
        foreach ($filas as $fila) {
            foreach (array_values($fila) as $c => $valor) {
                $anchos[$c] = max($anchos[$c] ?? 8, min(60, mb_strlen((string) $valor) + 2));
            }
        }
        $columnas = '';
        foreach ($anchos as $c => $ancho) {
            $columnas .= '<col min="' . ($c + 1) . '" max="' . ($c + 1) . '" width="' . $ancho . '" customWidth="1"/>';
        }

        $datos = '';
        foreach (array_values($filas) as $f => $fila) {
            $estilo = $f < $filasNegrita ? ' s="1"' : '';
            $celdas = '';
            foreach (array_values($fila) as $c => $valor) {
                if ($valor === null || $valor === '') {
                    continue;
                }
                $ref = self::columna($c) . ($f + 1);
                if (is_int($valor) || is_float($valor)) {
                    $celdas .= '<c r="' . $ref . '"' . $estilo . '><v>' . $valor . '</v></c>';
                } else {
                    $celdas .= '<c r="' . $ref . '" t="inlineStr"' . $estilo . '><is><t xml:space="preserve">'
                        . self::texto((string) $valor) . '</t></is></c>';
                }
            }
            $datos .= '<row r="' . ($f + 1) . '">' . $celdas . '</row>';
        }

        return self::XML . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . ($columnas !== '' ? '<cols>' . $columnas . '</cols>' : '')
            . '<sheetData>' . $datos . '</sheetData></worksheet>';
    }

    /** Letra de columna de Excel: 0 => A, 25 => Z, 26 => AA. */
    private static function columna(int $indice): string
    {
        $letras = '';
        for ($n = $indice + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letras = chr(65 + ($n - 1) % 26) . $letras;
        }
        return $letras;
    }

    /** Texto seguro para XML: escapa símbolos y quita caracteres de control. */
    private static function texto(string $valor): string
    {
        $valor = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $valor);
        return htmlspecialchars($valor, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    // ---------- Empaquetado ZIP (sin compresión) ----------

    /**
     * Arma un ZIP con los archivos dados ([ruta => contenido]), guardados sin
     * comprimir. Cada archivo lleva su encabezado local; al final va el
     * directorio central con la posición de cada uno (formato ZIP de PKWARE).
     */
    private static function zip(array $archivos): string
    {
        $ahora = getdate();
        $hora  = ($ahora['hours'] << 11) | ($ahora['minutes'] << 5) | intdiv($ahora['seconds'], 2);
        $fecha = (($ahora['year'] - 1980) << 9) | ($ahora['mon'] << 5) | $ahora['mday'];

        $datos = '';
        $directorio = '';
        foreach ($archivos as $ruta => $contenido) {
            $crc = crc32($contenido);
            $largo = strlen($contenido);
            $posicion = strlen($datos);

            // Encabezado local: firma, versión 2.0, nombres en UTF-8, sin compresión.
            $datos .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 0, $hora, $fecha,
                    $crc, $largo, $largo, strlen($ruta), 0)
                . $ruta . $contenido;

            // Entrada del directorio central, con la posición del encabezado local.
            $directorio .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 0, $hora, $fecha,
                    $crc, $largo, $largo, strlen($ruta), 0, 0, 0, 0, 0, $posicion)
                . $ruta;
        }

        // Fin del directorio central: cuántos archivos hay y dónde empieza.
        $fin = pack('VvvvvVVv', 0x06054b50, 0, 0, count($archivos), count($archivos),
            strlen($directorio), strlen($datos), 0);

        return $datos . $directorio . $fin;
    }
}
