<?php
/**
 * Migration 025: Trip Is Private
 *
 * Agrega la columna is_private a la tabla trips.
 * - 0 → viaje visible públicamente según su estado (comportamiento normal)
 * - 1 → viaje privado: solo visible para el usuario administrador logueado,
 *        independientemente de su status u otras configuraciones
 */
class Migration_025_trip_is_private
{
    public static function id(): string
    {
        return '025_trip_is_private';
    }

    public static function description(): string
    {
        return 'Agregar is_private a la tabla trips';
    }

    public static function check(PDO $db): bool
    {
        $stmt = $db->query("SHOW COLUMNS FROM trips LIKE 'is_private'");
        return (bool) $stmt->fetchColumn();
    }

    public static function up(PDO $db): void
    {
        $db->exec("
            ALTER TABLE trips
                ADD COLUMN is_private TINYINT(1) NOT NULL DEFAULT 0 AFTER status
        ");
    }
}
