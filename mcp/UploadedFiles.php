<?php
/**
 * MCP Uploaded Files
 *
 * Temporary server-side file storage for MCP flows that should not move binary
 * payloads through JSON-RPC. Clients upload by multipart HTTP, then pass a small
 * token to MCP tools.
 */

final class McpUploadedFiles
{
    private const PHOTO_PREFIX = 'photo_';
    private const PHOTO_DIR = 'uploads/mcp_uploads/photos';

    public static function savePhotoUpload(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Error de subida: ' . (int)($file['error'] ?? UPLOAD_ERR_NO_FILE)];
        }

        $maxSize = defined('MAX_UPLOAD_SIZE') ? MAX_UPLOAD_SIZE : 8 * 1024 * 1024;
        if (($file['size'] ?? 0) > $maxSize) {
            $maxMb = round($maxSize / 1024 / 1024, 1);
            return ['success' => false, 'error' => "La imagen supera {$maxMb} MB"];
        }

        $originalName = basename((string)($file['name'] ?? 'photo.jpg'));
        if ($originalName === '' || strpbrk($originalName, "\0\r\n") !== false) {
            return ['success' => false, 'error' => 'Nombre de archivo inválido'];
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExt = defined('ALLOWED_IMAGE_EXTENSIONS') ? ALLOWED_IMAGE_EXTENSIONS : ['jpg', 'jpeg', 'png'];
        if (!in_array($ext, $allowedExt, true)) {
            return ['success' => false, 'error' => 'Extensión no permitida. Solo JPG, JPEG y PNG'];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return ['success' => false, 'error' => 'No se pudo inicializar finfo'];
        }
        $mimeReal = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowedMime = defined('ALLOWED_IMAGE_TYPES') ? ALLOWED_IMAGE_TYPES : ['image/jpeg', 'image/jpg', 'image/png'];
        if (!in_array($mimeReal, $allowedMime, true)) {
            return ['success' => false, 'error' => 'Tipo de imagen no permitido'];
        }

        $bytes = file_get_contents($file['tmp_name']);
        $gdRes = $bytes !== false ? @imagecreatefromstring($bytes) : false;
        if ($gdRes === false) {
            return ['success' => false, 'error' => 'El archivo no es una imagen válida'];
        }
        imagedestroy($gdRes);

        $token = self::PHOTO_PREFIX . bin2hex(random_bytes(16));
        $dir = self::photoDir($token);
        if (!is_dir(dirname($dir))) {
            mkdir(dirname($dir), 0750, true);
        }
        self::writeDenyHtaccess(dirname(dirname($dir)));
        if (!mkdir($dir, 0750, true)) {
            return ['success' => false, 'error' => 'No se pudo crear la carpeta temporal'];
        }

        $tempFilename = 'original.' . $ext;
        $path = $dir . '/' . $tempFilename;
        if (!move_uploaded_file($file['tmp_name'], $path)) {
            self::deleteDir($dir);
            return ['success' => false, 'error' => 'No se pudo guardar el archivo temporal'];
        }
        chmod($path, 0640);

        $exif = ExifExtractor::readFromFile($path, $originalName);
        $metadata = [
            'token' => $token,
            'kind' => 'photo',
            'original_name' => $originalName,
            'temp_filename' => $tempFilename,
            'mime' => $mimeReal,
            'size_bytes' => (int)$file['size'],
            'created_at' => time(),
            'exif' => $exif,
        ];
        file_put_contents($dir . '/metadata.json', json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return ['success' => true] + self::publicPhotoInfo($metadata);
    }

    public static function getPhoto(string $token): ?array
    {
        if (!self::isValidPhotoToken($token)) {
            return null;
        }
        $dir = self::photoDir($token);
        $metaPath = $dir . '/metadata.json';
        if (!is_file($metaPath)) {
            return null;
        }
        $metadata = json_decode((string)file_get_contents($metaPath), true);
        if (!is_array($metadata)) {
            return null;
        }
        $filePath = realpath($dir . '/' . ($metadata['temp_filename'] ?? ''));
        $realDir = realpath($dir);
        if (!$filePath || !$realDir || !str_starts_with(str_replace('\\', '/', $filePath), str_replace('\\', '/', $realDir) . '/')) {
            return null;
        }
        $metadata['path'] = $filePath;
        return $metadata;
    }

    public static function consumePhoto(string $token): array
    {
        $photo = self::getPhoto($token);
        if ($photo === null) {
            return ['success' => false, 'error' => 'photo_token inválido o expirado'];
        }

        $result = FileHelper::saveImageFromPath($photo['path'], $photo['original_name']);
        if (!$result['success']) {
            return $result;
        }

        self::deletePhoto($token);

        return $result + [
            'exif' => $photo['exif'] ?? null,
            'original_name' => $photo['original_name'],
        ];
    }

    public static function deletePhoto(string $token): bool
    {
        if (!self::isValidPhotoToken($token)) {
            return false;
        }
        return self::deleteDir(self::photoDir($token));
    }

    public static function publicPhotoInfo(array $metadata): array
    {
        $exif = $metadata['exif'] ?? [];
        $info = [
            'photo_token' => $metadata['token'],
            'original_name' => $metadata['original_name'],
            'mime' => $metadata['mime'],
            'size_bytes' => $metadata['size_bytes'],
            'has_gps' => (bool)($exif['has_gps'] ?? false),
            'has_date' => (bool)($exif['has_date'] ?? false),
            'latitude' => $exif['latitude'] ?? null,
            'longitude' => $exif['longitude'] ?? null,
            'visit_date' => $exif['date'] ?? null,
            'date_source' => $exif['date_source'] ?? null,
        ];
        if (defined('BASE_URL')) {
            $info['preview_url'] = BASE_URL . '/' . self::PHOTO_DIR . '/' . $metadata['token'] . '/' . $metadata['temp_filename'];
        }
        return $info;
    }

    private static function isValidPhotoToken(string $token): bool
    {
        return (bool)preg_match('/^photo_[a-f0-9]{32}$/', $token);
    }

    private static function photoDir(string $token): string
    {
        return ROOT_PATH . '/' . self::PHOTO_DIR . '/' . $token;
    }

    private static function writeDenyHtaccess(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $path = $dir . '/.htaccess';
        if (!file_exists($path)) {
            file_put_contents($path, "Options -ExecCGI\nAddHandler cgi-script .php .pl .py .rb\n<FilesMatch \"\\.php$\">\n    Deny from all\n</FilesMatch>\n");
        }
    }

    private static function deleteDir(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }
        foreach (glob($dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        return @rmdir($dir);
    }
}
