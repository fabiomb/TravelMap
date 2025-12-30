<?php
/**
 * Modelo: Point (Point of Interest)
 * 
 * Gestiona las operaciones CRUD para puntos de interés
 */

class Point {
    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Obtener todos los puntos de interés
     * 
     * @param int|null $trip_id Filtrar por viaje (opcional)
     * @param string $order_by Campo por el que ordenar
     * @return array Lista de puntos
     */
    public function getAll($trip_id = null, $order_by = 'visit_date DESC, created_at DESC') {
        try {
            if ($trip_id) {
                $stmt = $this->db->prepare("
                    SELECT p.*, t.title as trip_title, t.color_hex as trip_color
                    FROM points_of_interest p
                    LEFT JOIN trips t ON p.trip_id = t.id
                    WHERE p.trip_id = ?
                    ORDER BY {$order_by}
                ");
                $stmt->execute([$trip_id]);
            } else {
                $stmt = $this->db->prepare("
                    SELECT p.*, t.title as trip_title, t.color_hex as trip_color
                    FROM points_of_interest p
                    LEFT JOIN trips t ON p.trip_id = t.id
                    ORDER BY {$order_by}
                ");
                $stmt->execute();
            }
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('Error al obtener puntos: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener un punto por ID
     * 
     * @param int $id ID del punto
     * @return array|null Datos del punto o null si no existe
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare('
                SELECT p.*, t.title as trip_title 
                FROM points_of_interest p
                LEFT JOIN trips t ON p.trip_id = t.id
                WHERE p.id = ?
            ');
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log('Error al obtener punto: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Crear un nuevo punto de interés
     * 
     * @param array $data Datos del punto
     * @return int|false ID del punto creado o false si falla
     */
    public function create($data) {
        try {
            $stmt = $this->db->prepare('
                INSERT INTO points_of_interest 
                (trip_id, title, description, type, icon, image_path, latitude, longitude, visit_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            
            $result = $stmt->execute([
                $data['trip_id'],
                $data['title'],
                $data['description'] ?? null,
                $data['type'],
                $data['icon'] ?? 'default',
                $data['image_path'] ?? null,
                $data['latitude'],
                $data['longitude'],
                $data['visit_date'] ?? null
            ]);

            return $result ? $this->db->lastInsertId() : false;
        } catch (PDOException $e) {
            error_log('Error al crear punto: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualizar un punto existente
     * 
     * @param int $id ID del punto
     * @param array $data Datos a actualizar
     * @return bool True si se actualizó correctamente
     */
    public function update($id, $data) {
        try {
            $stmt = $this->db->prepare('
                UPDATE points_of_interest 
                SET trip_id = ?, title = ?, description = ?, type = ?, 
                    icon = ?, image_path = ?, latitude = ?, longitude = ?, visit_date = ?
                WHERE id = ?
            ');
            
            return $stmt->execute([
                $data['trip_id'],
                $data['title'],
                $data['description'] ?? null,
                $data['type'],
                $data['icon'] ?? 'default',
                $data['image_path'] ?? null,
                $data['latitude'],
                $data['longitude'],
                $data['visit_date'] ?? null,
                $id
            ]);
        } catch (PDOException $e) {
            error_log('Error al actualizar punto: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Eliminar un punto de interés
     * 
     * @param int $id ID del punto
     * @return bool True si se eliminó correctamente
     */
    public function delete($id) {
        try {
            // Obtener la imagen antes de eliminar para borrar el archivo
            $point = $this->getById($id);
            
            $stmt = $this->db->prepare('DELETE FROM points_of_interest WHERE id = ?');
            $result = $stmt->execute([$id]);
            
            // Si se eliminó correctamente y tenía imagen, borrar el archivo
            if ($result && $point && !empty($point['image_path'])) {
                $file_path = ROOT_PATH . '/' . $point['image_path'];
                if (file_exists($file_path)) {
                    @unlink($file_path);
                }
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log('Error al eliminar punto: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Validar datos de punto de interés
     * 
     * @param array $data Datos a validar
     * @return array Array de errores (vacío si todo es válido)
     */
    public function validate($data) {
        $errors = [];

        // Trip ID requerido
        if (empty($data['trip_id']) || !is_numeric($data['trip_id'])) {
            $errors['trip_id'] = 'Debe seleccionar un viaje';
        }

        // Título requerido
        if (empty($data['title']) || trim($data['title']) === '') {
            $errors['title'] = 'El título es obligatorio';
        } elseif (strlen($data['title']) > 200) {
            $errors['title'] = 'El título no puede exceder 200 caracteres';
        }

        // Tipo requerido
        if (empty($data['type']) || !in_array($data['type'], ['stay', 'visit', 'food'])) {
            $errors['type'] = 'Debe seleccionar un tipo válido (Alojamiento, Visita, Comida)';
        }

        // Latitud requerida y válida
        if (empty($data['latitude']) && $data['latitude'] !== '0') {
            $errors['latitude'] = 'La latitud es obligatoria';
        } elseif (!is_numeric($data['latitude']) || $data['latitude'] < -90 || $data['latitude'] > 90) {
            $errors['latitude'] = 'La latitud debe estar entre -90 y 90';
        }

        // Longitud requerida y válida
        if (empty($data['longitude']) && $data['longitude'] !== '0') {
            $errors['longitude'] = 'La longitud es obligatoria';
        } elseif (!is_numeric($data['longitude']) || $data['longitude'] < -180 || $data['longitude'] > 180) {
            $errors['longitude'] = 'La longitud debe estar entre -180 y 180';
        }

        // Validar fecha si se proporciona
        if (!empty($data['visit_date'])) {
            $date = DateTime::createFromFormat('Y-m-d', $data['visit_date']);
            if (!$date || $date->format('Y-m-d') !== $data['visit_date']) {
                $errors['visit_date'] = 'La fecha debe estar en formato YYYY-MM-DD';
            }
        }

        return $errors;
    }

    /**
     * Obtener tipos de puntos disponibles
     * 
     * @return array Array asociativo de tipos
     */
    public static function getTypes() {
        return [
            'stay' => 'Alojamiento',
            'visit' => 'Punto de Visita',
            'food' => 'Restaurante/Comida'
        ];
    }

    /**
     * Obtener iconos disponibles por tipo
     * 
     * @param string $type Tipo de punto
     * @return string Nombre del icono
     */
    public static function getIconByType($type) {
        $icons = [
            'stay' => 'hotel',
            'visit' => 'camera',
            'food' => 'restaurant'
        ];
        return $icons[$type] ?? 'default';
    }

    /**
     * Obtener icono SVG por tipo
     * 
     * @param string $type Tipo de punto
     * @param int $size Tamaño del icono (default 20)
     * @return string SVG HTML
     */
    public static function getSvgIcon($type, $size = 20) {
        $icons = [
            'stay' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4V20C3 20.9428 3 21.4142 3.29289 21.7071C3.58579 22 4.05719 22 5 22H19C19.9428 22 20.4142 22 20.7071 21.7071C21 21.4142 21 20.9428 21 20V4"/><path d="M10.5 8V9.5M10.5 11V9.5M13.5 8V9.5M13.5 11V9.5M10.5 9.5H13.5"/><path d="M14 22L14 17.9999C14 16.8954 13.1046 15.9999 12 15.9999C10.8954 15.9999 10 16.8954 10 17.9999V22"/><path d="M2 4H8C8.6399 2.82727 10.1897 2 12 2C13.8103 2 15.3601 2.82727 16 4H22"/><path d="M6 8H7M6 12H7M6 16H7"/><path d="M17 8H18M17 12H18M17 16H18"/></svg>',
            'visit' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8.31253 4.7812L7.6885 4.36517V4.36517L8.31253 4.7812ZM7.5 6V6.75C7.75076 6.75 7.98494 6.62467 8.12404 6.41603L7.5 6ZM2.17224 8.83886L1.45453 8.62115L2.17224 8.83886ZM4.83886 6.17224L4.62115 5.45453H4.62115L4.83886 6.17224ZM3.46243 20.092L3.93822 19.5123L3.93822 19.5123L3.46243 20.092ZM2.90796 19.5376L3.48772 19.0618L3.48772 19.0618L2.90796 19.5376ZM21.092 19.5376L20.5123 19.0618L20.5123 19.0618L21.092 19.5376ZM20.5376 20.092L20.0618 19.5123L20.0618 19.5123L20.5376 20.092ZM14.0195 3.89791C14.3847 4.09336 14.8392 3.95575 15.0346 3.59054C15.2301 3.22534 15.0924 2.77084 14.7272 2.57539L14.0195 3.89791ZM22.5455 8.62115C22.4252 8.22477 22.0064 8.00092 21.61 8.12116C21.2137 8.2414 20.9898 8.6602 21.1101 9.05658L22.5455 8.62115ZM21.25 11.5V13.5H22.75V11.5H21.25ZM14.5 20.25H9.5V21.75H14.5V20.25ZM2.75 13.5V11.5H1.25V13.5H2.75ZM12.3593 2.25H11.6407V3.75H12.3593V2.25ZM7.6885 4.36517L6.87596 5.58397L8.12404 6.41603L8.93657 5.19722L7.6885 4.36517ZM11.6407 2.25C11.1305 2.25 10.6969 2.24925 10.3369 2.28282C9.96142 2.31783 9.61234 2.39366 9.27276 2.57539L9.98055 3.89791C10.0831 3.84299 10.2171 3.80049 10.4762 3.77634C10.7506 3.75075 11.1031 3.75 11.6407 3.75V2.25ZM8.93657 5.19722C9.23482 4.74985 9.43093 4.45704 9.60448 4.24286C9.76825 4.04074 9.87794 3.95282 9.98055 3.89791L9.27276 2.57539C8.93318 2.75713 8.67645 3.00553 8.43904 3.29853C8.2114 3.57947 7.97154 3.94062 7.6885 4.36517L8.93657 5.19722ZM2.75 11.5C2.75 10.0499 2.75814 9.49107 2.88994 9.05657L1.45453 8.62115C1.24186 9.32224 1.25 10.159 1.25 11.5H2.75ZM7.5 5.25C6.159 5.25 5.32224 5.24186 4.62115 5.45453L5.05657 6.88994C5.49107 6.75814 6.04987 6.75 7.5 6.75V5.25ZM2.88994 9.05657C3.20503 8.01787 4.01787 7.20503 5.05657 6.88994L4.62115 5.45453C3.10304 5.91505 1.91505 7.10304 1.45453 8.62115L2.88994 9.05657ZM9.5 20.25C7.83789 20.25 6.65724 20.2488 5.75133 20.1417C4.86197 20.0366 4.33563 19.8384 3.93822 19.5123L2.98663 20.6718C3.69558 21.2536 4.54428 21.5095 5.57525 21.6313C6.58966 21.7512 7.87463 21.75 9.5 21.75V20.25ZM1.25 13.5C1.25 15.1254 1.24877 16.4103 1.36868 17.4248C1.49054 18.4557 1.74638 19.3044 2.3282 20.0134L3.48772 19.0618C3.16158 18.6644 2.96343 18.138 2.85831 17.2487C2.75123 16.3428 2.75 15.1621 2.75 13.5H1.25ZM3.93822 19.5123C3.77366 19.3772 3.62277 19.2263 3.48772 19.0618L2.3282 20.0134C2.52558 20.2539 2.74612 20.4744 2.98663 20.6718L3.93822 19.5123ZM21.25 13.5C21.25 15.1621 21.2488 16.3428 21.1417 17.2487C21.0366 18.138 20.8384 18.6644 20.5123 19.0618L21.6718 20.0134C22.2536 19.3044 22.5095 18.4557 22.6313 17.4248C22.7512 16.4103 22.75 15.1254 22.75 13.5H21.25ZM14.5 21.75C16.1254 21.75 17.4103 21.7512 18.4248 21.6313C19.4557 21.5095 20.3044 21.2536 21.0134 20.6718L20.0618 19.5123C19.6644 19.8384 19.138 20.0366 18.2487 20.1417C17.3428 20.2488 16.1621 20.25 14.5 20.25V21.75ZM20.5123 19.0618C20.3772 19.2263 20.2263 19.3772 20.0618 19.5123L21.0134 20.6718C21.2539 20.4744 21.4744 20.2539 21.6718 20.0134L20.5123 19.0618ZM12.3593 3.75C12.8969 3.75 13.2494 3.75075 13.5238 3.77634C13.7829 3.80049 13.9169 3.84299 14.0195 3.89791L14.7272 2.57539C14.3877 2.39366 14.0386 2.31783 13.6631 2.28282C13.3031 2.24925 12.8695 2.25 12.3593 2.25V3.75ZM22.75 11.5C22.75 10.159 22.7581 9.32224 22.5455 8.62115L21.1101 9.05658C21.2419 9.49107 21.25 10.0499 21.25 11.5H22.75Z" fill="currentColor" stroke="none"/><circle cx="12" cy="13" r="4" stroke="currentColor" fill="none"/><path d="M17.9737 3.02148C17.9795 2.99284 18.0205 2.99284 18.0263 3.02148C18.3302 4.50808 19.4919 5.66984 20.9785 5.97368C21.0072 5.97954 21.0072 6.02046 20.9785 6.02632C19.4919 6.33016 18.3302 7.49192 18.0263 8.97852C18.0205 9.00716 17.9795 9.00716 17.9737 8.97852C17.6698 7.49192 16.5081 6.33016 15.0215 6.02632C14.9928 6.02046 14.9928 5.97954 15.0215 5.97368C16.5081 5.66984 17.6698 4.50808 17.9737 3.02148Z" fill="currentColor" stroke="none"/></svg>',
            'food' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M21 17C18.2386 17 16 14.7614 16 12C16 9.23858 18.2386 7 21 7"/><path d="M21 21C16.0294 21 12 16.9706 12 12C12 7.02944 16.0294 3 21 3"/><path d="M6 3L6 8M6 21L6 11"/><path d="M3.5 8H8.5"/><path d="M9 3L9 7.35224C9 12.216 3 12.2159 3 7.35207L3 3"/></svg>'
        ];
        return $icons[$type] ?? $icons['visit'];
    }
}
