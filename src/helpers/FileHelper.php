<?php
/**
 * Helper: FileHelper
 * 
 * Gestiona la subida y validación de archivos
 */

class FileHelper {
    
    /**
     * Subir una imagen al servidor
     * 
     * @param array $file Archivo de $_FILES
     * @param string $destination_folder Carpeta destino relativa a ROOT_PATH
     * @param array $allowed_types Tipos MIME permitidos
     * @param int $max_size Tamaño máximo en bytes
     * @return array Array con 'success', 'path' (si éxito) o 'error' (si falla)
     */
    public static function uploadImage($file, $destination_folder = 'uploads/points', $allowed_types = null, $max_size = null) {
        // Valores por defecto
        if ($allowed_types === null) {
            $allowed_types = ALLOWED_IMAGE_TYPES;
        }
        if ($max_size === null) {
            $max_size = MAX_UPLOAD_SIZE;
        }

        // Validar que se haya subido un archivo
        if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['success' => false, 'error' => 'No se seleccionó ningún archivo'];
        }

        // Verificar errores de subida
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => self::getUploadErrorMessage($file['error'])];
        }

        // Validar tamaño
        if ($file['size'] > $max_size) {
            $max_mb = round($max_size / 1024 / 1024, 2);
            return ['success' => false, 'error' => "El archivo excede el tamaño máximo permitido ({$max_mb} MB)"];
        }

        // Validar tipo MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime_type, $allowed_types)) {
            return ['success' => false, 'error' => 'Tipo de archivo no permitido. Solo se permiten imágenes JPG, JPEG y PNG'];
        }

        // Validar extensión
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ALLOWED_IMAGE_EXTENSIONS)) {
            return ['success' => false, 'error' => 'Extensión de archivo no válida'];
        }

        // Generar nombre único
        $unique_name = self::generateUniqueFileName($extension);

        // Crear carpeta si no existe
        $full_destination = ROOT_PATH . '/' . $destination_folder;
        if (!is_dir($full_destination)) {
            if (!mkdir($full_destination, 0755, true)) {
                return ['success' => false, 'error' => 'No se pudo crear el directorio de destino'];
            }
        }

        // Ruta completa del archivo
        $file_path = $full_destination . '/' . $unique_name;
        $relative_path = $destination_folder . '/' . $unique_name;

        // Mover archivo
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            // Establecer permisos
            chmod($file_path, 0644);
            
            return [
                'success' => true,
                'path' => $relative_path,
                'filename' => $unique_name
            ];
        } else {
            return ['success' => false, 'error' => 'Error al mover el archivo al destino'];
        }
    }

    /**
     * Eliminar un archivo del servidor
     * 
     * @param string $file_path Ruta relativa del archivo
     * @return bool True si se eliminó correctamente
     */
    public static function deleteFile($file_path) {
        if (empty($file_path)) {
            return false;
        }

        $full_path = ROOT_PATH . '/' . $file_path;
        
        if (file_exists($full_path)) {
            return @unlink($full_path);
        }
        
        return false;
    }

    /**
     * Generar un nombre único para archivo
     * 
     * @param string $extension Extensión del archivo
     * @return string Nombre único
     */
    private static function generateUniqueFileName($extension) {
        return uniqid('img_', true) . '_' . time() . '.' . $extension;
    }

    /**
     * Obtener mensaje de error de subida
     * 
     * @param int $error_code Código de error de PHP
     * @return string Mensaje de error
     */
    private static function getUploadErrorMessage($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'El archivo excede el tamaño máximo permitido';
            case UPLOAD_ERR_PARTIAL:
                return 'El archivo se subió parcialmente';
            case UPLOAD_ERR_NO_FILE:
                return 'No se subió ningún archivo';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Falta la carpeta temporal';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Error al escribir el archivo en disco';
            case UPLOAD_ERR_EXTENSION:
                return 'Una extensión de PHP detuvo la subida';
            default:
                return 'Error desconocido al subir el archivo';
        }
    }

    /**
     * Validar imagen antes de subir
     * 
     * @param array $file Archivo de $_FILES
     * @return array Array con 'valid' (bool) y 'errors' (array)
     */
    public static function validateImage($file) {
        $errors = [];

        if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['valid' => true, 'errors' => []]; // No es obligatorio
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = self::getUploadErrorMessage($file['error']);
        }

        if ($file['size'] > MAX_UPLOAD_SIZE) {
            $max_mb = round(MAX_UPLOAD_SIZE / 1024 / 1024, 2);
            $errors[] = "El archivo excede {$max_mb} MB";
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ALLOWED_IMAGE_EXTENSIONS)) {
            $errors[] = 'Solo se permiten archivos JPG, JPEG y PNG';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Redimensionar imagen (opcional, para futuras mejoras)
     * 
     * @param string $source_path Ruta de la imagen original
     * @param string $dest_path Ruta de destino
     * @param int $max_width Ancho máximo
     * @param int $max_height Alto máximo
     * @return bool True si se redimensionó correctamente
     */
    public static function resizeImage($source_path, $dest_path, $max_width = 1200, $max_height = 800) {
        // Esta función se puede implementar en el futuro si se necesita
        // Por ahora, solo retornamos true
        return true;
    }
}
