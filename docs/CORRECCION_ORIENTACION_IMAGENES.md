# Corrección de Orientación de Imágenes

## Problema Identificado

Las fotografías tomadas con dispositivos móviles en orientación vertical se mostraban horizontalmente en los popups del mapa público. Esto ocurría porque:

1. **Metadatos EXIF**: Los dispositivos móviles no rotan físicamente la imagen, sino que guardan la orientación en los metadatos EXIF.
2. **Pérdida de metadatos**: Al redimensionar/comprimir la imagen con GD, se perdían los metadatos EXIF.
3. **Sin corrección**: El código original no leía ni aplicaba la orientación EXIF antes de procesar.

## Solución Implementada

Se agregó procesamiento automático de orientación EXIF en `FileHelper.php`:

### Cambios en `src/helpers/FileHelper.php`

#### 1. Lectura y aplicación de orientación EXIF
En el método `resizeImage()`, después de cargar la imagen:

```php
// Corregir orientación EXIF si es una imagen JPEG
if ($image_type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
    $exif = @exif_read_data($source_path);
    if ($exif !== false && isset($exif['Orientation'])) {
        $source_image = self::fixImageOrientation($source_image, $exif['Orientation']);
        
        // Actualizar dimensiones si la imagen fue rotada 90° o 270°
        if (in_array($exif['Orientation'], [5, 6, 7, 8])) {
            // Intercambiar dimensiones
            $temp = $orig_width;
            $orig_width = $orig_height;
            $orig_height = $temp;
            
            // Recalcular nuevas dimensiones
            $ratio = min($max_width / $orig_width, $max_height / $orig_height);
            $new_width = round($orig_width * $ratio);
            $new_height = round($orig_height * $ratio);
        }
    }
}
```

#### 2. Nuevo método `fixImageOrientation()`
Aplica las transformaciones necesarias según el valor EXIF:

```php
private static function fixImageOrientation($image, $orientation) {
    switch ($orientation) {
        case 2: // Volteo horizontal
            imageflip($image, IMG_FLIP_HORIZONTAL);
            break;
        case 3: // Rotación 180°
            $image = imagerotate($image, 180, 0);
            break;
        case 4: // Volteo vertical
            imageflip($image, IMG_FLIP_VERTICAL);
            break;
        case 5: // Volteo vertical + rotación 90° anti-horario
            imageflip($image, IMG_FLIP_VERTICAL);
            $image = imagerotate($image, -90, 0);
            break;
        case 6: // Rotación 90° anti-horario
            $image = imagerotate($image, -90, 0);
            break;
        case 7: // Volteo horizontal + rotación 90° anti-horario
            imageflip($image, IMG_FLIP_HORIZONTAL);
            $image = imagerotate($image, -90, 0);
            break;
        case 8: // Rotación 90° horario
            $image = imagerotate($image, 90, 0);
            break;
    }
    return $image;
}
```

## Valores de Orientación EXIF

| Valor | Descripción | Transformación |
|-------|-------------|----------------|
| 1 | Normal (sin cambios) | Ninguna |
| 2 | Espejo horizontal | Volteo horizontal |
| 3 | Rotada 180° | Rotación 180° |
| 4 | Espejo vertical | Volteo vertical |
| 5 | Espejo vertical + rotada 90° CCW | Volteo vertical + Rotación -90° |
| 6 | Rotada 90° CCW | Rotación -90° |
| 7 | Espejo horizontal + rotada 90° CCW | Volteo horizontal + Rotación -90° |
| 8 | Rotada 90° CW | Rotación 90° |

*CCW = Counter-Clockwise (anti-horario), CW = Clockwise (horario)*

## Requisitos

- **Extensión PHP EXIF**: Debe estar habilitada en `php.ini`
  ```ini
  extension=exif
  ```
- **Extensión PHP GD**: Ya requerida para el procesamiento de imágenes
  ```ini
  extension=gd
  ```

## Verificación

Para verificar que las extensiones están habilitadas:

```bash
php -m | grep -i exif
php -m | grep -i gd
```

O en Windows PowerShell:
```powershell
php -m | Select-String -Pattern "exif"
php -m | Select-String -Pattern "gd"
```

## Comportamiento

### Para imágenes nuevas
- Al subir una imagen, se leerá automáticamente la orientación EXIF
- La imagen se rotará físicamente según corresponda
- Se guardarán las dimensiones correctas
- Los metadatos EXIF no se preservan en la imagen procesada (ya no son necesarios)

### Para imágenes existentes
- Las imágenes ya procesadas mantendrán su orientación actual
- Para corregirlas, será necesario volver a subirlas desde el formulario de edición
- Al reemplazar la imagen, se aplicará la corrección automáticamente

## Notas Técnicas

1. **Intercambio de dimensiones**: Cuando la orientación es 5, 6, 7 u 8 (rotaciones de 90° o 270°), es necesario intercambiar ancho y alto ya que la imagen cambia de orientación.

2. **Recálculo de proporciones**: Después de corregir la orientación, se recalculan las nuevas dimensiones respetando las proporciones originales corregidas.

3. **Compatibilidad**: El código verifica que `exif_read_data()` exista antes de intentar leer los datos EXIF, evitando errores si la extensión no está disponible.

4. **Manejo de errores**: Se usa el operador `@` para suprimir warnings de EXIF en imágenes que no tienen metadatos.

## Fecha de Implementación

29 de diciembre de 2025
