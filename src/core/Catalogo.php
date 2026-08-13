<?php
/**
 * Catálogos para poblar los menús desplegables de los formularios.
 */

class Catalogo
{
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
