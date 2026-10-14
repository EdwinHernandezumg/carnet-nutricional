<?php
/**
 * Catálogos para poblar los menús desplegables de los formularios.
 */

class Catalogo
{
    /**
     * Pueblo, con los códigos del SIGSA 3 y del cuaderno SIGSA 5a (MSPAS).
     * En la base se guarda el código: así los formatos impresos llevan el
     * número que piden, y en pantalla se muestra el nombre.
     */
    const PUEBLOS = [
        1 => 'Mestizo o ladino',
        2 => 'Maya',
        3 => 'Garífuna',
        4 => 'Xinca',
        5 => 'Otro',
        6 => 'No indica',
    ];

    /** Solo el pueblo maya lleva comunidad lingüística. */
    const PUEBLO_MAYA = 2;

    /** Comunidad lingüística, con los códigos del SIGSA 3 y 5a. */
    const COMUNIDADES_LINGUISTICAS = [
        1  => "Achi'",
        2  => 'Akateka',
        3  => 'Awakateka',
        4  => "Ch'orti'",
        5  => 'Chalchiteka',
        6  => 'Chuj',
        7  => "Itza'",
        8  => 'Ixil',
        9  => 'Jakalteka',
        10 => 'Kaqchikel',
        11 => "K'iche'",
        12 => 'Mam',
        13 => 'Mopan',
        14 => 'Poqomam',
        15 => "Poqomchi'",
        16 => "Q'anjob'al",
        17 => "Q'eqchi'",
        18 => 'Sakapulteka',
        19 => 'Sipakapensa',
        20 => 'Tektiteka',
        21 => "Tz'utujil",
        22 => 'Uspanteka',
        23 => 'No indica',
    ];

    /** Sectores con su territorio, para el selector. */
    public static function sectores(): array
    {
        $db = Database::obtener();
        $sql = "SELECT s.id_sector, s.letra, t.numero AS territorio
                FROM sector s
                JOIN territorio t ON t.id_territorio = s.id_territorio
                ORDER BY t.numero, s.letra";
        return $db->query($sql)->fetchAll();
    }

    /** Parajes con su sector, para el selector dependiente. */
    public static function parajes(): array
    {
        $db = Database::obtener();
        $sql = "SELECT id_paraje, id_sector, nombre
                FROM paraje
                WHERE activo = 1
                ORDER BY nombre";
        return $db->query($sql)->fetchAll();
    }

      public static function roles(): array
    {
        $db = Database::obtener();
        return $db->query("SELECT id_rol, nombre FROM rol ORDER BY id_rol")->fetchAll();
    }
}
