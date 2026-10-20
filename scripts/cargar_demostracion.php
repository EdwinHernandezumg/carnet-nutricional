<?php
/**
 * Carga datos ficticios para el ambiente de demostración
 * (pruebas.carnetchivarreto.com): 42 niños en los dos territorios, con sus
 * controles de crecimiento del último año, vacunas, suplementos y citas.
 *
 *   php scripts/cargar_demostracion.php
 *   php scripts/cargar_demostracion.php --base=otra_base    (para probar)
 *
 * Cada niño sigue un perfil, para que en la demostración se vean todas las
 * partes del sistema: crecimiento normal, cerca del umbral, desnutrición aguda
 * y crónica, sobrepeso y un niño que se recupera. Las mediciones se guardan
 * con Medicion::crear: las puntuaciones Z y las alertas las calcula el motor,
 * igual que con una medición real. Los registros quedan a nombre de una
 * enfermera ficticia por sector, cuya contraseña nadie conoce.
 *
 * Los nombres se arman al azar con nombres y apellidos comunes de la región;
 * no corresponden a ninguna persona. El azar usa una semilla fija: la carga
 * sale igual cada vez (cambian solo las fechas, que van según el día de hoy).
 *
 * Protecciones: no corre con APP_ENTORNO en 'produccion' y solo trabaja en una
 * base sin niños, para no mezclar datos ficticios con datos reales. Todo se
 * guarda en una sola transacción: si algo falla, no queda nada a medias.
 */

require __DIR__ . '/consola.php';
soloConsola();

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/core/Database.php';
require __DIR__ . '/../src/core/Bitacora.php';
require __DIR__ . '/../src/core/Catalogo.php';
require __DIR__ . '/../src/calculo/Calculo.php';
require __DIR__ . '/../src/alertas/Alerta.php';
require __DIR__ . '/../src/mediciones/Medicion.php';
require __DIR__ . '/../src/ninos/Nino.php';
require __DIR__ . '/../src/carnet/Vacuna.php';
require __DIR__ . '/../src/carnet/Suplementacion.php';

if (APP_ENTORNO === 'produccion') {
    exit("Este script no corre en producción: los datos ficticios se mezclarían con los reales.\n");
}
$base = opcion('base');
if ($base !== null) {
    Database::usarBase($base);
}
$db = Database::obtener();
$nombreBase = $db->query('SELECT DATABASE()')->fetchColumn();

$hay = (int)$db->query('SELECT COUNT(*) FROM nino')->fetchColumn();
if ($hay > 0) {
    exit("La base '$nombreBase' ya tiene $hay niños. Los datos de demostración solo se cargan en una base\n"
       . "recién instalada, para no mezclarlos con otros datos.\n");
}

echo "\nCargando datos de demostración en la base '$nombreBase'...\n";
mt_srand(2026);
$hoy = date('Y-m-d');

// ---------- Utilidades del azar y de las fechas ----------

function azar(array $lista)
{
    return $lista[mt_rand(0, count($lista) - 1)];
}

/** Verdadero con probabilidad $p (de 0 a 1). */
function probable(float $p): bool
{
    return mt_rand() / mt_getrandmax() < $p;
}

/** Número al azar con distribución normal (método de Box y Muller). */
function normal(float $media, float $desviacion): float
{
    $u = max(1e-9, mt_rand() / mt_getrandmax());
    $v = mt_rand() / mt_getrandmax();
    return $media + $desviacion * sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
}

function sumarDias(string $fecha, int $dias): string
{
    return (new DateTime($fecha))->modify(($dias >= 0 ? '+' : '') . $dias . ' days')->format('Y-m-d');
}

function sumarMeses(string $fecha, int $meses): string
{
    return (new DateTime($fecha))->modify('+' . $meses . ' months')->format('Y-m-d');
}

// ---------- Personal ficticio: una enfermera por sector ----------

$sectores = $db->query(
    "SELECT s.id_sector, s.letra, t.numero AS territorio, t.id_puesto
     FROM sector s JOIN territorio t ON t.id_territorio = s.id_territorio
     ORDER BY t.numero, s.letra"
)->fetchAll();
$idRolEnfermeria = (int)$db->query("SELECT id_rol FROM rol WHERE nombre = 'enfermeria'")->fetchColumn();

$personal = [
    ['Lucía Tzul Coyoy', 'demo.ltzul'], ['Rosa Ixcoy Tax', 'demo.rixcoy'],
    ['Elena Chanchavac Puac', 'demo.echanchavac'], ['Marta Coyoy Sapón', 'demo.mcoyoy'],
    ['Ana Puac Xiloj', 'demo.apuac'], ['Juana Tax Baten', 'demo.jtax'],
    ['Sara Tumax Ajanel', 'demo.stumax'],
];

$db->beginTransaction();
try {
    $enfermeraDelSector = [];
    foreach ($sectores as $i => $s) {
        [$nombre, $usuario] = $personal[$i % count($personal)];
        $existe = $db->prepare('SELECT id_usuario FROM usuario WHERE usuario = :u');
        $existe->execute([':u' => $usuario]);
        $id = $existe->fetchColumn();
        if (!$id) {
            // Contraseña al azar que nadie conoce: la cuenta solo firma los registros.
            $db->prepare(
                "INSERT INTO usuario (id_rol, id_puesto, id_sector, nombre_completo, usuario, contrasena_hash, debe_cambiar_pass)
                 VALUES (:rol, :puesto, :sector, :nombre, :usuario, :hash, 1)"
            )->execute([
                ':rol' => $idRolEnfermeria, ':puesto' => $s['id_puesto'], ':sector' => $s['id_sector'],
                ':nombre' => $nombre, ':usuario' => $usuario,
                ':hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
            ]);
            $id = $db->lastInsertId();
        }
        $enfermeraDelSector[(int)$s['id_sector']] = (int)$id;
    }

    $parajesPorSector = [];
    foreach ($db->query('SELECT id_paraje, id_sector FROM paraje')->fetchAll() as $p) {
        $parajesPorSector[(int)$p['id_sector']][] = (int)$p['id_paraje'];
    }

    // ---------- Nombres ficticios ----------

    $nombresNino = ['José', 'Juan', 'Luis', 'Carlos', 'Mario', 'Diego', 'Pedro', 'Santiago', 'Mateo',
                    'Daniel', 'Ángel', 'Kevin', 'Edwin', 'Selvin', 'Josué', 'Elmer', 'Fernando', 'Gerson'];
    $nombresNina = ['María', 'Ana', 'Rosa', 'Juana', 'Sandra', 'Andrea', 'Lucía', 'Sofía', 'Valeria',
                    'Ximena', 'Dulce', 'Heidy', 'Yesenia', 'Glendy', 'Marleny', 'Ingrid', 'Paola', 'Abigail'];
    $apellidos = ['Tzul', 'Ixcoy', 'Coyoy', 'Puac', 'Tax', 'Chanchavac', 'Vásquez', 'Ajanel', 'Chaj', 'Cuc',
                  'Tzoc', 'Xiloj', 'Pú', 'Sapón', 'Baten', 'Tumax', 'Rosales', 'Gómez', 'Ajtún', 'Pacheco',
                  'Ixchop', 'Lacán', 'Tacam', 'Poncio'];

    // ---------- Perfiles de crecimiento ----------
    // Puntuación Z de la talla para la edad (zT) y del peso para la longitud o
    // la talla (zPT), al primer control y al último del año. Entre uno y otro
    // cambian poco a poco, con una pequeña variación en cada control.
    $perfiles = [
        'normal'    => fn() => [$t = normal(-0.4, 0.4), $t, $p = normal(0.2, 0.4), $p],
        'talla_baja' => fn() => [-0.8, -1.5, normal(0.1, 0.3), normal(0.1, 0.3)],   // cerca del umbral
        'adelgaza'  => fn() => [normal(-0.5, 0.3), normal(-0.5, 0.3), -0.2, -1.6],  // cerca del umbral
        'aguda'     => fn() => [normal(-0.9, 0.3), normal(-1.0, 0.3), -0.7, -2.5],  // desnutrición aguda
        'cronica'   => fn() => [-2.3, -2.6, normal(0.2, 0.3), normal(0.1, 0.3)],    // desnutrición crónica
        'sobrepeso' => fn() => [normal(0.2, 0.3), normal(0.2, 0.3), 1.8, 2.5],
        'recupera'  => fn() => [normal(-1.0, 0.3), normal(-0.9, 0.3), -2.4, -0.7],  // mejora con el seguimiento
    ];
    $listaPerfiles = array_merge(array_fill(0, 23, 'normal'), array_fill(0, 4, 'talla_baja'),
        array_fill(0, 4, 'adelgaza'), array_fill(0, 3, 'aguda'), array_fill(0, 4, 'cronica'),
        array_fill(0, 2, 'sobrepeso'), array_fill(0, 2, 'recupera'));
    shuffle($listaPerfiles);

    // Edades en meses: más niños pequeños, que son los que se controlan cada mes.
    $edades = [1, 3, 4, 6, 7, 9, 10, 12, 14, 15, 17, 19, 20, 22, 23, 25, 28, 31, 35, 38, 44, 50, 55, 58, 66];

    $catalogoVacunas = Vacuna::catalogo();
    $anteriorQueCubre = [];   // dosis del esquema anterior por id de la dosis vigente que cubre
    foreach ($catalogoVacunas as $c) {
        if (!$c['vigente'] && $c['equivale_a'] !== null) {
            $anteriorQueCubre[(int)$c['equivale_a']] = $c;
        }
    }
    // Hasta esta fecha, las dosis ficticias se ponen con el esquema anterior
    // (carnet de 2018), como en los carnets de los niños mayores.
    $cambioDeEsquema = '2025-01-01';

    $totales = ['niños' => 0, 'mediciones' => 0, 'vacunas' => 0, 'suplementos' => 0, 'citas' => 0];
    $carnetPorTerritorio = [];
    $citasHoy = 0;

    $insertarCita = $db->prepare(
        "INSERT INTO cita (id_nino, id_usuario, fecha_programada, estado, fecha_asistencia, motivo, aviso_enviado)
         VALUES (:nino, :usuario, :fecha, :estado, :asistencia, :motivo, :aviso)"
    );

    foreach ($sectores as $s) {
        $idSector = (int)$s['id_sector'];
        $idEnfermera = $enfermeraDelSector[$idSector];

        for ($n = 0; $n < 6; $n++) {
            // ---------- El niño ----------
            $sexo = probable(0.5) ? 'M' : 'F';
            $nombres = $sexo === 'M' ? $nombresNino : $nombresNina;
            $nombre = azar($nombres) . (probable(0.4) ? ' ' . azar($nombres) : '');
            $apellidoPadre = azar($apellidos);
            $apellidoMadre = azar($apellidos);
            $edadMeses = azar($edades);
            $nacimiento = sumarDias((new DateTime($hoy))->modify("-$edadMeses months")->format('Y-m-d'), -mt_rand(0, 27));
            $esMaya = probable(0.9);
            $territorio = (int)$s['territorio'];
            $carnetPorTerritorio[$territorio] = ($carnetPorTerritorio[$territorio] ?? 0) + 1;

            $idNino = Nino::crear([
                'id_sector'        => $idSector,
                'id_paraje'        => azar($parajesPorSector[$idSector] ?? [0]) ?: null,
                'cui'              => null,
                'numero_carnet'    => sprintf('T%d-%03d', $territorio, $carnetPorTerritorio[$territorio]),
                'nombres'          => $nombre,
                'apellidos'        => "$apellidoPadre $apellidoMadre",
                'fecha_nacimiento' => $nacimiento,
                'sexo'             => $sexo,
                'pueblo'           => $esMaya ? Catalogo::PUEBLO_MAYA : 1,
                'comunidad_linguistica' => $esMaya ? 11 : null,   // 11 = K'iche'
                'nombre_madre'     => azar($nombresNina) . " $apellidoMadre " . azar($apellidos),
                'nombre_padre'     => probable(0.6) ? azar($nombresNino) . " $apellidoPadre " . azar($apellidos) : null,
                'telefono'         => probable(0.7) ? azar(['3', '4', '5']) . str_pad((string)mt_rand(0, 9999999), 7, '0', STR_PAD_LEFT) : null,
                'direccion'        => null,
            ], $idEnfermera);
            $totales['niños']++;

            // Fecha de registro: el comienzo de su historia en el sistema.
            $inicio = max(sumarDias($nacimiento, 3), sumarDias($hoy, -365));
            $db->prepare('UPDATE nino SET fecha_registro = :f WHERE id_nino = :id')
               ->execute([':f' => $inicio . ' 09:00:00', ':id' => $idNino]);

            // ---------- Controles de crecimiento del último año ----------
            // Frecuencia de la normativa: cada mes antes de los 2 años, cada 3
            // meses de 2 a 3 años y cada 6 meses de 3 a 5 años. A algunos niños
            // les falta el último control, para que aparezcan como atrasados.
            [$zt0, $zt1, $zp0, $zp1] = $perfiles[$listaPerfiles[$totales['niños'] - 1]]();
            $atrasado = probable(0.15);
            $fechas = [];
            $fecha = max(sumarDias($nacimiento, 30), sumarDias($hoy, -365));
            while ($fecha <= $hoy) {
                $meses = Nino::edadEnMeses($nacimiento, $fecha);
                if ($meses >= 60) {
                    break;
                }
                $fechas[] = $fecha;
                $paso = $meses < 24 ? 1 : ($meses < 36 ? 3 : 6);
                $fecha = sumarDias(sumarMeses($fecha, $paso), mt_rand(-4, 4));
            }
            $proximoControl = $fecha;   // la siguiente fecha que le tocaba
            if ($atrasado && count($fechas) > 2) {
                // No vino a su último control: su cita queda vencida.
                $proximoControl = array_pop($fechas);
            }

            $tallaAnterior = 0;
            $total = count($fechas);
            foreach ($fechas as $i => $fechaToma) {
                $avance = $total > 1 ? $i / ($total - 1) : 1;
                $edadExacta = (strtotime($fechaToma) - strtotime($nacimiento)) / 86400 / 30.4375;
                $menorDe2 = (strtotime($fechaToma) - strtotime($nacimiento)) / 86400 < 731;

                $zt = $zt0 + ($zt1 - $zt0) * $avance + normal(0, 0.08);
                $talla = Calculo::valorParaZ('talla_edad', $sexo, $edadExacta, $zt);
                $talla = round(max($talla, $tallaAnterior + 0.1), 1);
                $tallaAnterior = $talla;

                $zp = $zp0 + ($zp1 - $zp0) * $avance + normal(0, 0.12);
                $peso = Calculo::valorParaZ($menorDe2 ? 'peso_longitud' : 'peso_talla', $sexo, $talla, $zp)
                     ?? Calculo::valorParaZ('peso_edad', $sexo, $edadExacta, $zp);

                Medicion::crear([
                    'id_nino'       => $idNino,
                    'fecha_toma'    => $fechaToma,
                    'peso_kg'       => round($peso, 1),
                    'talla_cm'      => $talla,
                    'tipo_talla'    => $menorDe2 ? 'longitud' : 'talla',
                    'edad_meses'    => Nino::edadEnMeses($nacimiento, $fechaToma),
                    'observaciones' => '',
                ], $idEnfermera);
                $totales['mediciones']++;

                // Suplemento nutricional lipídico en el control, de 6 a 23 meses.
                $mesesEnControl = Nino::edadEnMeses($nacimiento, $fechaToma);
                if ($mesesEnControl >= 6 && $mesesEnControl <= 23 && $fechaToma >= sumarDias($hoy, -180) && probable(0.8)) {
                    Suplementacion::crear(['id_nino' => $idNino, 'tipo' => 'Suplemento nutricional lipídico (SNL)',
                        'fecha_entrega' => $fechaToma, 'cantidad' => '30 sobres'], $idEnfermera);
                    $totales['suplementos']++;
                }

                // Cita cumplida para los controles de los últimos tres meses.
                if ($fechaToma >= sumarDias($hoy, -90) && probable(0.6)) {
                    $insertarCita->execute([':nino' => $idNino, ':usuario' => $idEnfermera,
                        ':fecha' => sumarDias($fechaToma, -mt_rand(0, 2)), ':estado' => 'cumplida',
                        ':asistencia' => $fechaToma, ':motivo' => 'Control de crecimiento', ':aviso' => 1]);
                    $totales['citas']++;
                }
            }

            // ---------- Vacunas ----------
            foreach ($catalogoVacunas as $c) {
                if (!$c['vigente'] || $c['edad_meses'] === null) {
                    continue;
                }
                $tocaba = sumarMeses($nacimiento, (int)$c['edad_meses']);
                if ($tocaba > $hoy || !probable((int)$c['edad_meses'] === 0 ? 0.95 : 0.88)) {
                    continue;   // todavía no le toca, o quedó pendiente
                }
                $aplicada = min($hoy, sumarDias($tocaba, (int)$c['edad_meses'] === 0 ? mt_rand(0, 1) : mt_rand(0, 21)));
                // Antes del cambio de esquema se puso la dosis del carnet de 2018.
                $dosis = ($aplicada < $cambioDeEsquema && isset($anteriorQueCubre[(int)$c['id_catalogo_vacuna']]))
                    ? $anteriorQueCubre[(int)$c['id_catalogo_vacuna']] : $c;
                Vacuna::crear([
                    'id_nino'          => $idNino,
                    'nombre_vacuna'    => $dosis['nombre'],
                    'dosis'            => $dosis['dosis'],
                    'fecha_aplicacion' => $aplicada,
                    'lote'             => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $dosis['nombre']), 0, 3)) . '-' . mt_rand(1000, 9999),
                    'lugar_vacunacion' => probable(0.85) ? 1 : azar([2, 4]),
                ], $idEnfermera);
                $totales['vacunas']++;
            }

            // ---------- Vitamina A y desparasitante, cada 6 meses ----------
            for ($m = 6; $m <= 54; $m += 6) {
                $tocaba = sumarDias(sumarMeses($nacimiento, $m), mt_rand(0, 10));
                if ($tocaba > $hoy || $tocaba < sumarDias($hoy, -365)) {
                    continue;
                }
                if (probable(0.85)) {
                    Suplementacion::crear(['id_nino' => $idNino, 'tipo' => 'Vitamina A',
                        'fecha_entrega' => $tocaba, 'cantidad' => '1 cápsula'], $idEnfermera);
                    $totales['suplementos']++;
                }
                if ($m >= 12 && probable(0.7)) {
                    Suplementacion::crear(['id_nino' => $idNino, 'tipo' => 'Desparasitante (albendazol)',
                        'fecha_entrega' => $tocaba, 'cantidad' => '1 tableta'], $idEnfermera);
                    $totales['suplementos']++;
                }
            }

            // ---------- Próxima cita ----------
            if ($edadMeses < 60) {
                if (!$atrasado && $proximoControl < $hoy) {
                    $proximoControl = sumarDias($hoy, mt_rand(1, 25));
                }
                // Unas cuantas para hoy, para que el día de la demostración haya citas.
                if (!$atrasado && $citasHoy < 5 && probable(0.2)) {
                    $proximoControl = $hoy;
                    $citasHoy++;
                }
                $insertarCita->execute([':nino' => $idNino, ':usuario' => $idEnfermera,
                    ':fecha' => $proximoControl, ':estado' => 'programada', ':asistencia' => null,
                    ':motivo' => 'Control de crecimiento', ':aviso' => 0]);
                $totales['citas']++;
            }
            // Alguna inasistencia reciente.
            if (probable(0.15)) {
                $insertarCita->execute([':nino' => $idNino, ':usuario' => $idEnfermera,
                    ':fecha' => sumarDias($hoy, -mt_rand(10, 50)), ':estado' => 'inasistencia', ':asistencia' => null,
                    ':motivo' => azar(['Vacunación', 'Control de crecimiento']), ':aviso' => 1]);
                $totales['citas']++;
            }
        }
    }

    $idPrimera = reset($enfermeraDelSector);
    registrarEnBitacora($db, $idPrimera, 'crear', $idPrimera,
        'Carga de datos de demostración (ficticios): ' . $totales['niños'] . ' niños');
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    echo "\nNo se cargó nada: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Listo:\n";
foreach ($totales as $que => $cuantos) {
    echo str_pad("  $que", 16) . $cuantos . "\n";
}
echo "\nLos registros quedan a nombre de 7 enfermeras ficticias (una por sector), sin contraseña conocida.\n"
   . "Las cuentas para entrar se crean desde Usuarios, con el administrador.\n";
