# Sistema de Internacionalización (i18n) - TravelMap

## 🌍 Resumen

TravelMap ahora soporta múltiples idiomas con un sistema completo de internacionalización (i18n). Los usuarios pueden seleccionar su idioma preferido y los administradores pueden configurar el idioma por defecto del sitio.

## ✨ Características

- ✅ Soporte multiidioma completo (PHP y JavaScript)
- ✅ Idioma por defecto configurable desde el panel de administración
- ✅ Selector de idioma para usuarios en el frontend
- ✅ Persistencia de preferencia de idioma en localStorage
- ✅ Archivos de traducción independientes y fáciles de editar
- ✅ Detección automática del idioma del navegador
- ✅ Documentación completa para contribuyentes

## 🗂️ Estructura

```
TravelMap/
├── lang/                          # Archivos de traducción
│   ├── en.json                    # Inglés (idioma por defecto)
│   └── es.json                    # Español
├── src/helpers/
│   └── Language.php               # Sistema i18n para PHP
├── assets/js/
│   └── i18n.js                    # Sistema i18n para JavaScript
└── docs/
    └── I18N.md                    # Documentación completa
```

## 🚀 Uso Rápido

### Para Usuarios

1. Abrir el mapa público (index.php)
2. Abrir el panel lateral "Mis Viajes"
3. En el footer del panel, seleccionar el idioma preferido
4. La página se recargará con el idioma seleccionado
5. La preferencia se guarda automáticamente en localStorage

### Para Administradores

1. Ir a **Panel de Administración** → **Configuración**
2. En la sección "Configuración General", seleccionar el **Idioma por Defecto**
3. Guardar cambios
4. Los nuevos visitantes verán el sitio en este idioma por defecto

## 💻 Para Desarrolladores

### PHP

```php
// Usar traducciones en archivos PHP
echo __('app.title');           // "Travel Map - My Travels..."
echo __('navigation.home');     // "Home"
echo __('map.my_trips');        // "My Trips"

// Con valor por defecto
echo __('custom.key', 'Default Text');
```

### JavaScript

```javascript
// Usar traducciones en JavaScript
const title = __('map.my_trips');      // "My Trips"
const search = __('map.search_button'); // "Search"

// Con valor por defecto
const text = __('custom.key', 'Default Text');
```

### Agregar Nueva Traducción

1. Editar `lang/en.json`:
```json
{
  "section": {
    "new_key": "English Text"
  }
}
```

2. Editar `lang/es.json`:
```json
{
  "section": {
    "new_key": "Texto en Español"
  }
}
```

3. Usar en el código:
```php
<?= __('section.new_key') ?>
```

## 🌐 Idiomas Soportados

- 🇬🇧 **Inglés (en)** - Idioma por defecto
- 🇪🇸 **Español (es)**

## 📖 Documentación Completa

Ver [docs/I18N.md](docs/I18N.md) para:
- Cómo agregar un nuevo idioma
- Estructura detallada de archivos
- Mejores prácticas
- Guía de contribución
- Ejemplos completos

## 🔧 Configuración Técnica

### Detección de Idioma (Orden de Prioridad)

**PHP (Backend):**
1. Parámetro GET `?lang=en`
2. Cookie `travelmap_lang`
3. Configuración en base de datos
4. Idioma del navegador
5. Inglés (por defecto)

**JavaScript (Frontend):**
1. localStorage `travelmap_lang`
2. Cookie `travelmap_lang`
3. Idioma del navegador
4. Inglés (por defecto)

### Persistencia

- **localStorage**: `travelmap_lang` - Preferencia del usuario
- **Cookie**: `travelmap_lang` - Sincronización PHP/JS (365 días)
- **Base de datos**: `settings.default_language` - Idioma por defecto del sitio

## 🤝 Contribuir

¿Quieres agregar un nuevo idioma o mejorar las traducciones existentes?

1. Fork el proyecto
2. Crea un archivo `lang/XX.json` (donde XX es el código del idioma)
3. Traduce todas las cadenas
4. Actualiza los archivos mencionados en [docs/I18N.md](docs/I18N.md)
5. Crea un Pull Request

### Idiomas que nos encantaría agregar:

- 🇫🇷 Francés
- 🇩🇪 Alemán
- 🇮🇹 Italiano
- 🇵🇹 Portugués
- 🇯🇵 Japonés
- 🇨🇳 Chino

## 📝 Migrando Código Existente

Si tienes código antiguo con texto hardcodeado:

**❌ Antes:**
```php
<button>Guardar</button>
```

**✅ Después:**
```php
<button><?= __('common.save') ?></button>
```

**❌ Antes (JS):**
```javascript
alert('Error al guardar');
```

**✅ Después (JS):**
```javascript
alert(__('messages.error_saving'));
```

## 🐛 Solución de Problemas

### El idioma no cambia

1. Verificar que los archivos JSON existen en `lang/`
2. Verificar permisos de lectura
3. Limpiar cache del navegador y localStorage
4. Verificar la consola del navegador para errores

### Traducciones faltantes

1. Verificar que la clave existe en el archivo JSON
2. Usar notación de punto correcta: `section.subsection.key`
3. Verificar que el archivo JSON tiene sintaxis válida

### El selector de idioma no aparece

1. Verificar que se cargó `assets/js/i18n.js`
2. Verificar que el elemento `#languageSelector` existe en el HTML
3. Verificar la consola del navegador para errores de JavaScript

## 📊 Estado del Proyecto

- [x] Sistema i18n para PHP
- [x] Sistema i18n para JavaScript  
- [x] Archivos de idioma (EN, ES)
- [x] Selector de idioma en frontend
- [x] Configuración de idioma en admin
- [x] Persistencia en localStorage
- [x] Documentación completa
- [ ] Migrar todas las páginas del admin
- [ ] Agregar más idiomas
- [ ] Tests automatizados para traducciones

## 📄 Licencia

Este sistema de i18n es parte de TravelMap y se distribuye bajo la misma licencia del proyecto.

---

**¿Preguntas?** Abre un issue en GitHub o consulta [docs/I18N.md](docs/I18N.md) para más detalles.
