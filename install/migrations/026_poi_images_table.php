<?php
/**
 * Migration 026: Tabla poi_images
 *
 * Agrega galería de imágenes por punto de interés. La columna
 * points_of_interest.image_path se conserva como espejo de la portada
 * (la imagen de menor sort_order) para no romper a los consumidores
 * existentes: MCP, importador EXIF, save_poi y backups.
 */
class Migration_026_poi_images_table
{
    public static function id(): string
    {
        return '026_poi_images_table';
    }

    public static function description(): string
    {
        return 'Tabla poi_images: galería de imágenes por punto de interés';
    }

    public static function check(PDO $db): bool
    {
        $stmt = $db->query("SHOW TABLES LIKE 'poi_images'");
        return (bool) $stmt->fetchColumn();
    }

    public static function up(PDO $db): void
    {
        // 1. Crear la tabla
        $db->exec("
            CREATE TABLE IF NOT EXISTS poi_images (
                id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                poi_id     INT UNSIGNED NOT NULL,
                image_path VARCHAR(255) NOT NULL,
                caption    VARCHAR(255) DEFAULT NULL,
                sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (poi_id) REFERENCES points_of_interest(id) ON DELETE CASCADE,
                INDEX idx_poi_sort (poi_id, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 2. Backfill: cada POI con imagen actual pasa a ser su portada
        $db->exec("
            INSERT INTO poi_images (poi_id, image_path, sort_order)
            SELECT id, image_path, 0
            FROM points_of_interest
            WHERE image_path IS NOT NULL AND image_path <> ''
        ");
    }
}
