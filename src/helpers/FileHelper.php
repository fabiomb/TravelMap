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
            
            // Aplicar redimensionamiento y compresión si está configurado
            try {
                // Cargar configuraciones desde la base de datos
                require_once ROOT_PATH . '/config/db.php';
                require_once ROOT_PATH . '/src/models/Settings.php';
                
                $conn = getDB();
                $settingsModel = new Settings($conn);
                
                $max_width = (int)$settingsModel->get('image_max_width', 1920);
                $max_height = (int)$settingsModel->get('image_max_height', 1080);
                $quality = (int)$settingsModel->get('image_quality', 85);
                
                // Aplicar redimensionamiento y compresión
                if (!self::resizeImage($file_path, $file_path, $max_width, $max_height, $quality)) {
                    error_log('Advertencia: No se pudo redimensionar la imagen, pero se guardó el archivo original');
                }
            } catch (Exception $e) {
                // Si hay error al cargar configuración o procesar, continuar con archivo original
                error_log('Advertencia: Error al procesar imagen: ' . $e->getMessage());
            }
            
            // Generar thumbnail
            $thumbnail_path = null;
            try {
                $thumbs_folder = $destination_folder . '/thumbs';
                $full_thumbs_folder = ROOT_PATH . '/' . $thumbs_folder;
                
                // Crear carpeta de thumbnails si no existe
                if (!is_dir($full_thumbs_folder)) {
                    mkdir($full_thumbs_folder, 0755, true);
                }
                
                $thumb_file_path = $full_thumbs_folder . '/' . $unique_name;
                $thumb_relative_path = $thumbs_folder . '/' . $unique_name;
                
                // Obtener configuración de thumbnail
                $thumb_width = (int)$settingsModel->get('thumbnail_max_width', 400);
                $thumb_height = (int)$settingsModel->get('thumbnail_max_height', 300);
                $thumb_quality = (int)$settingsModel->get('thumbnail_quality', 80);
                
                if (self::createThumbnail($file_path, $thumb_file_path, $thumb_width, $thumb_height, $thumb_quality)) {
                    $thumbnail_path = $thumb_relative_path;
                }
            } catch (Exception $e) {
                error_log('Advertencia: Error al crear thumbnail: ' . $e->getMessage());
            }
            
            return [
                'success' => true,
                'path' => $relative_path,
                'thumbnail_path' => $thumbnail_path,
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
        $result = false;
        
        if (file_exists($full_path)) {
            $result = @unlink($full_path);
        }
        
        // También intentar eliminar el thumbnail si existe
        $thumb_path = self::getThumbnailPath($file_path);
        if ($thumb_path) {
            $full_thumb_path = ROOT_PATH . '/' . $thumb_path;
            if (file_exists($full_thumb_path)) {
                @unlink($full_thumb_path);
            }
        }
        
        return $result;
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
     * Redimensionar y comprimir imagen
     * 
     * @param string $source_path Ruta de la imagen original
     * @param string $dest_path Ruta de destino (puede ser la misma que origen)
     * @param int $max_width Ancho máximo
     * @param int $max_height Alto máximo
     * @param int $quality Calidad JPEG (0-100)
     * @return bool True si se procesó correctamente
     */
    public static function resizeImage($source_path, $dest_path, $max_width = 1920, $max_height = 1080, $quality = 85) {
        // Verificar que la extensión GD esté disponible
        if (!extension_loaded('gd')) {
            error_log('Error: La extensión GD no está disponible');
            return false;
        }
        
        // Verificar que el archivo existe
        if (!file_exists($source_path)) {
            error_log('Error: Archivo fuente no existe: ' . $source_path);
            return false;
        }
        
        // Obtener información de la imagen
        $image_info = @getimagesize($source_path);
        if ($image_info === false) {
            error_log('Error: No se pudo obtener información de la imagen');
            return false;
        }
        
        list($orig_width, $orig_height, $image_type) = $image_info;
        
        // Si la imagen ya es más pequeña que los límites, solo aplicar compresión si es necesario
        if ($orig_width <= $max_width && $orig_height <= $max_height) {
            // Solo comprimir si es JPEG y la calidad es diferente a la original
            if ($image_type === IMAGETYPE_JPEG && $quality < 100) {
                return self::compressJpeg($source_path, $dest_path, $quality);
            }
            // Si no necesita procesamiento, copiar si son archivos diferentes
            if ($source_path !== $dest_path) {
                return copy($source_path, $dest_path);
            }
            return true;
        }
        
        // Calcular nuevas dimensiones manteniendo la proporción
        $ratio = min($max_width / $orig_width, $max_height / $orig_height);
        $new_width = round($orig_width * $ratio);
        $new_height = round($orig_height * $ratio);
        
        // Cargar imagen original según el tipo
        $source_image = null;
        switch ($image_type) {
            case IMAGETYPE_JPEG:
                $source_image = @imagecreatefromjpeg($source_path);
                break;
            case IMAGETYPE_PNG:
                $source_image = @imagecreatefrompng($source_path);
                break;
            case IMAGETYPE_GIF:
                $source_image = @imagecreatefromgif($source_path);
                break;
            default:
                error_log('Error: Tipo de imagen no soportado: ' . $image_type);
                return false;
        }
        
        if ($source_image === false) {
            error_log('Error: No se pudo cargar la imagen desde: ' . $source_path);
            return false;
        }
        
        // Corregir orientación EXIF si es una imagen JPEG
        if ($image_type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($source_path);
            if ($exif !== false && isset($exif['Orientation'])) {
                $source_image = self::fixImageOrientation($source_image, $exif['Orientation']);
                
                // Actualizar dimensiones si la imagen fue rotada 90° o 270°
                if (in_array($exif['Orientation'], [5, 6, 7, 8])) {
                    // La imagen fue rotada 90° o 270°, intercambiar dimensiones
                    $temp = $orig_width;
                    $orig_width = $orig_height;
                    $orig_height = $temp;
                    
                    // Recalcular nuevas dimensiones con las dimensiones corregidas
                    $ratio = min($max_width / $orig_width, $max_height / $orig_height);
                    $new_width = round($orig_width * $ratio);
                    $new_height = round($orig_height * $ratio);
                }
            }
        }
        
        // Crear imagen de destino
        $dest_image = imagecreatetruecolor($new_width, $new_height);
        
        if ($dest_image === false) {
            imagedestroy($source_image);
            error_log('Error: No se pudo crear la imagen de destino');
            return false;
        }
        
        // Preservar transparencia para PNG
        if ($image_type === IMAGETYPE_PNG) {
            imagealphablending($dest_image, false);
            imagesavealpha($dest_image, true);
            $transparent = imagecolorallocatealpha($dest_image, 0, 0, 0, 127);
            imagefilledrectangle($dest_image, 0, 0, $new_width, $new_height, $transparent);
        }
        
        // Redimensionar imagen
        $result = imagecopyresampled(
            $dest_image, 
            $source_image, 
            0, 0, 0, 0, 
            $new_width, $new_height, 
            $orig_width, $orig_height
        );
        
        if ($result === false) {
            imagedestroy($source_image);
            imagedestroy($dest_image);
            error_log('Error: No se pudo redimensionar la imagen');
            return false;
        }
        
        // Guardar imagen según el tipo
        $save_result = false;
        switch ($image_type) {
            case IMAGETYPE_JPEG:
                $save_result = imagejpeg($dest_image, $dest_path, $quality);
                break;
            case IMAGETYPE_PNG:
                // PNG: calidad va de 0 (sin compresión) a 9 (máxima compresión)
                // Convertir calidad JPEG (0-100) a escala PNG (0-9)
                $png_quality = 9 - round(($quality / 100) * 9);
                $save_result = imagepng($dest_image, $dest_path, $png_quality);
                break;
            case IMAGETYPE_GIF:
                $save_result = imagegif($dest_image, $dest_path);
                break;
        }
        
        // Liberar memoria
        imagedestroy($source_image);
        imagedestroy($dest_image);
        
        if ($save_result === false) {
            error_log('Error: No se pudo guardar la imagen procesada');
            return false;
        }
        
        return true;
    }
    
    /**
     * Crear thumbnail de una imagen
     * 
     * @param string $source_path Ruta de la imagen original
     * @param string $dest_path Ruta de destino del thumbnail
     * @param int $max_width Ancho máximo del thumbnail
     * @param int $max_height Alto máximo del thumbnail
     * @param int $quality Calidad JPEG (0-100)
     * @return bool True si se creó correctamente
     */
    public static function createThumbnail($source_path, $dest_path, $max_width = 400, $max_height = 300, $quality = 80) {
        // Verificar que la extensión GD esté disponible
        if (!extension_loaded('gd')) {
            error_log('Error: La extensión GD no está disponible para crear thumbnail');
            return false;
        }
        
        // Verificar que el archivo existe
        if (!file_exists($source_path)) {
            error_log('Error: Archivo fuente no existe para thumbnail: ' . $source_path);
            return false;
        }
        
        // Obtener información de la imagen
        $image_info = @getimagesize($source_path);
        if ($image_info === false) {
            error_log('Error: No se pudo obtener información de la imagen para thumbnail');
            return false;
        }
        
        list($orig_width, $orig_height, $image_type) = $image_info;
        
        // Calcular nuevas dimensiones manteniendo la proporción
        $ratio = min($max_width / $orig_width, $max_height / $orig_height);
        
        // Si la imagen es más pequeña que el thumbnail, usar dimensiones originales
        if ($ratio >= 1) {
            $new_width = $orig_width;
            $new_height = $orig_height;
        } else {
            $new_width = round($orig_width * $ratio);
            $new_height = round($orig_height * $ratio);
        }
        
        // Cargar imagen original según el tipo
        $source_image = null;
        switch ($image_type) {
            case IMAGETYPE_JPEG:
                $source_image = @imagecreatefromjpeg($source_path);
                break;
            case IMAGETYPE_PNG:
                $source_image = @imagecreatefrompng($source_path);
                break;
            case IMAGETYPE_GIF:
                $source_image = @imagecreatefromgif($source_path);
                break;
            default:
                error_log('Error: Tipo de imagen no soportado para thumbnail: ' . $image_type);
                return false;
        }
        
        if ($source_image === false) {
            error_log('Error: No se pudo cargar la imagen para thumbnail');
            return false;
        }
        
        // Crear imagen de destino
        $dest_image = imagecreatetruecolor($new_width, $new_height);
        
        if ($dest_image === false) {
            imagedestroy($source_image);
            error_log('Error: No se pudo crear la imagen de destino para thumbnail');
            return false;
        }
        
        // Preservar transparencia para PNG
        if ($image_type === IMAGETYPE_PNG) {
            imagealphablending($dest_image, false);
            imagesavealpha($dest_image, true);
            $transparent = imagecolorallocatealpha($dest_image, 0, 0, 0, 127);
            imagefilledrectangle($dest_image, 0, 0, $new_width, $new_height, $transparent);
        }
        
        // Redimensionar imagen
        $result = imagecopyresampled(
            $dest_image, 
            $source_image, 
            0, 0, 0, 0, 
            $new_width, $new_height, 
            $orig_width, $orig_height
        );
        
        if ($result === false) {
            imagedestroy($source_image);
            imagedestroy($dest_image);
            error_log('Error: No se pudo redimensionar para thumbnail');
            return false;
        }
        
        // Guardar thumbnail como JPEG para mejor compresión (excepto PNG con transparencia)
        $save_result = false;
        switch ($image_type) {
            case IMAGETYPE_JPEG:
            case IMAGETYPE_GIF:
                $save_result = imagejpeg($dest_image, $dest_path, $quality);
                break;
            case IMAGETYPE_PNG:
                // PNG: calidad va de 0 a 9
                $png_quality = 9 - round(($quality / 100) * 9);
                $save_result = imagepng($dest_image, $dest_path, $png_quality);
                break;
        }
        
        // Liberar memoria
        imagedestroy($source_image);
        imagedestroy($dest_image);
        
        if ($save_result === false) {
            error_log('Error: No se pudo guardar el thumbnail');
            return false;
        }
        
        // Establecer permisos
        chmod($dest_path, 0644);
        
        return true;
    }
    
    /**
     * Obtener la ruta del thumbnail a partir de la ruta de la imagen
     * 
     * @param string $image_path Ruta de la imagen original
     * @return string|null Ruta del thumbnail o null si no existe
     */
    public static function getThumbnailPath($image_path) {
        if (empty($image_path)) {
            return null;
        }
        
        // Construir ruta del thumbnail
        $path_parts = pathinfo($image_path);
        $thumb_path = $path_parts['dirname'] . '/thumbs/' . $path_parts['basename'];
        
        // Verificar si existe el thumbnail
        $full_thumb_path = ROOT_PATH . '/' . $thumb_path;
        if (file_exists($full_thumb_path)) {
            return $thumb_path;
        }
        
        return null;
    }
    
    /**
     * Comprimir imagen JPEG sin redimensionar
     * 
     * @param string $source_path Ruta de la imagen original
     * @param string $dest_path Ruta de destino
     * @param int $quality Calidad (0-100)
     * @return bool True si se comprimió correctamente
     */
    private static function compressJpeg($source_path, $dest_path, $quality = 85) {
        if (!extension_loaded('gd')) {
            return false;
        }
        
        $source_image = @imagecreatefromjpeg($source_path);
        if ($source_image === false) {
            return false;
        }
        
        $result = imagejpeg($source_image, $dest_path, $quality);
        imagedestroy($source_image);
        
        return $result;
    }
    
    /**
     * Corrige la orientación de una imagen basándose en los datos EXIF
     * 
     * @param resource $image Recurso de imagen GD
     * @param int $orientation Valor de orientación EXIF
     * @return resource Recurso de imagen corregido
     */
    private static function fixImageOrientation($image, $orientation) {
        if (!is_resource($image) && !($image instanceof \GdImage)) {
            return $image;
        }
        
        switch ($orientation) {
            case 2:
                // Volteo horizontal
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 3:
                // Rotación 180°
                $image = imagerotate($image, 180, 0);
                break;
            case 4:
                // Volteo vertical
                imageflip($image, IMG_FLIP_VERTICAL);
                break;
            case 5:
                // Volteo vertical + rotación 90° anti-horario
                imageflip($image, IMG_FLIP_VERTICAL);
                $image = imagerotate($image, -90, 0);
                break;
            case 6:
                // Rotación 90° anti-horario (o 270° horario)
                $image = imagerotate($image, -90, 0);
                break;
            case 7:
                // Volteo horizontal + rotación 90° anti-horario
                imageflip($image, IMG_FLIP_HORIZONTAL);
                $image = imagerotate($image, -90, 0);
                break;
            case 8:
                // Rotación 90° horario (o 270° anti-horario)
                $image = imagerotate($image, 90, 0);
                break;
        }
        
        return $image;
    }
}
