# Sistema Multi-Idioma TravelMap - Instalación

## 🎯 Resumen de Cambios

Se ha implementado un sistema completo de internacionalización (i18n) en TravelMap con las siguientes características:

### ✅ Implementado

1. **Sistema i18n PHP** (`src/helpers/Language.php`)
   - Clase Language con patrón Singleton
   - Función helper `__($key, $default)` para traducciones
   - Detección automática de idioma (GET, Cookie, BD, navegador)
   - Cache de traducciones

2. **Sistema i18n JavaScript** (`assets/js/i18n.js`)
   - Carga asíncrona de traducciones
   - Función helper `__($key, $defaultValue)`
   - Sincronización con localStorage y cookies
   - Detección de idioma del navegador

3. **Archivos de Traducciones**
   - `lang/en.json` - Inglés (idioma por defecto)
   - `lang/es.json` - Español
   - Estructura jerárquica organizada por secciones
   - +200 cadenas de texto traducidas

4. **Configuración del Sistema**
   - Campo `default_language` en configuración (admin)
   - Selector de idioma en el frontend
   - Persistencia en localStorage
   - Sincronización PHP/JavaScript vía cookies

5. **Interfaz de Usuario**
   - Selector de idioma en panel lateral del mapa
   - Configuración de idioma por defecto en panel admin
   - index.php completamente traducido
   - Atributo lang dinámico en HTML

6. **Documentación**
   - `docs/I18N.md` - Documentación completa para desarrolladores
   - `docs/I18N_README.md` - Guía rápida de uso
   - Instrucciones para agregar nuevos idiomas
   - Mejores prácticas y ejemplos

## 📦 Archivos Creados

```
TravelMap/
├── lang/
│   ├── en.json                          # Traducciones inglés
│   └── es.json                          # Traducciones español
├── src/helpers/
│   └── Language.php                     # Sistema i18n PHP
├── assets/js/
│   └── i18n.js                          # Sistema i18n JavaScript
├── docs/
│   ├── I18N.md                          # Documentación completa
│   └── I18N_README.md                   # Guía rápida
└── install/
    ├── migration_language.sql            # Script SQL de migración
    ├── migrate_language.php              # Script PHP de migración
    └── MULTILANGUAGE_INSTALLATION.md     # Este archivo
```

## 📝 Archivos Modificados

- `config/config.php` - Carga automática del sistema i18n
- `index.php` - Traducciones, selector de idioma, atributo lang
- `admin/settings.php` - Campo de idioma por defecto
- `assets/js/public_map.js` - Handler para cambio de idioma

## 🚀 Instalación

### Paso 1: Ejecutar la Migración de Base de Datos

Opción A - Script PHP (Recomendado):
```
Navegar a: http://localhost/TravelMap/install/migrate_language.php
```

Opción B - SQL Manual:
```bash
mysql -u username -p database_name < install/migration_language.sql
```

### Paso 2: Verificar la Instalación

1. Ir a `http://localhost/TravelMap/`
2. Abrir el panel lateral "Mis Viajes" / "My Trips"
3. En el footer del panel, verificar que aparece el selector de idioma
4. Cambiar el idioma y verificar que la página se recarga

### Paso 3: Configurar Idioma por Defecto (Opcional)

1. Ir al Panel de Administración
2. Configuración → Configuración General
3. Seleccionar "Idioma por Defecto"
4. Guardar cambios

## 🧪 Testing

### Test 1: Cambio de Idioma en Frontend

1. Abrir el mapa público
2. Abrir panel lateral
3. Cambiar idioma a "Español"
4. Verificar que los textos cambian:
   - "My Trips" → "Mis Viajes"
   - "Routes" → "Rutas"
   - "Points" → "Puntos"
   - "Flights" → "Vuelos"

### Test 2: Persistencia

1. Cambiar idioma a Español
2. Cerrar y reabrir el navegador
3. Verificar que el idioma sigue siendo Español
4. Verificar localStorage: `localStorage.getItem('travelmap_lang')`

### Test 3: Configuración Admin

1. Ir a Admin → Configuración
2. Cambiar "Idioma por Defecto" a "Español"
3. Abrir el mapa en modo incógnito (sin cookies)
4. Verificar que el idioma por defecto es Español

### Test 4: Detección de Navegador

1. Limpiar localStorage y cookies
2. Configurar navegador en español (es)
3. Abrir el mapa
4. Verificar que detecta el idioma español

## 🔍 Verificación de Archivos

Verificar que existen los siguientes archivos:

```bash
# Verificar archivos de traducción
ls -la lang/
# Debe mostrar: en.json, es.json

# Verificar helper PHP
ls -la src/helpers/Language.php

# Verificar sistema JS
ls -la assets/js/i18n.js

# Verificar documentación
ls -la docs/I18N*.md
```

## 🐛 Solución de Problemas

### Error: "Translation file not found"

**Causa**: Los archivos JSON no están en la ubicación correcta

**Solución**:
```bash
# Verificar que existen
ls -la lang/en.json
ls -la lang/es.json

# Verificar permisos de lectura
chmod 644 lang/*.json
```

### Error: El idioma no cambia

**Causa**: localStorage bloqueado o cookies deshabilitadas

**Solución**:
1. Verificar que las cookies están habilitadas
2. Limpiar localStorage: `localStorage.clear()`
3. Recargar la página

### Error: "Language class not found"

**Causa**: No se cargó el helper de Language

**Solución**:
Verificar que `config/config.php` incluye:
```php
require_once SRC_PATH . '/helpers/Language.php';
$lang = Language::getInstance();
```

### Error: Traducciones no aparecen

**Causa**: Sintaxis JSON inválida

**Solución**:
Validar JSON:
```bash
# En Linux/Mac
python -m json.tool lang/en.json
python -m json.tool lang/es.json

# En Windows con PHP
php -r "json_decode(file_get_contents('lang/en.json'));"
```

## 📊 Estado de Traducción

### ✅ Completamente Traducido
- `index.php` - Mapa público
- Selector de idioma
- Controles del mapa (Rutas, Puntos, Vuelos)
- Archivos de configuración

### 🚧 Pendiente de Traducción
- Panel de administración (trips.php, points.php, users.php, etc.)
- Formularios de edición
- Mensajes de error/éxito
- Tooltips y ayudas

## 🎨 Personalización

### Agregar un Nuevo Idioma

1. Copiar archivo de traducción:
```bash
cp lang/en.json lang/fr.json
```

2. Traducir todas las cadenas en `lang/fr.json`

3. Actualizar `src/helpers/Language.php`:
```php
private $availableLanguages = ['en', 'es', 'fr'];
```

4. Actualizar `assets/js/i18n.js`:
```javascript
availableLanguages: ['en', 'es', 'fr']
```

5. Agregar al selector en `index.php`:
```php
<option value="fr">🇫🇷 Français</option>
```

Ver `docs/I18N.md` para instrucciones completas.

## 📚 Recursos Adicionales

- **Documentación Completa**: `docs/I18N.md`
- **Guía Rápida**: `docs/I18N_README.md`
- **Ejemplo de Uso**: Ver `index.php` líneas con `__('key')`
- **Estructura JSON**: Revisar `lang/en.json`

## ✅ Checklist Post-Instalación

- [ ] Script de migración ejecutado correctamente
- [ ] Selector de idioma visible en el frontend
- [ ] Cambio de idioma funciona correctamente
- [ ] localStorage guarda la preferencia
- [ ] Configuración de idioma por defecto en admin
- [ ] Documentación revisada
- [ ] Tests básicos completados

## 🤝 Contribuir

Para agregar traducciones o nuevos idiomas:

1. Crear/editar archivo en `lang/XX.json`
2. Seguir la estructura existente
3. Mantener coherencia en las traducciones
4. Probar todos los cambios
5. Actualizar documentación si es necesario

## 📞 Soporte

Si encuentras problemas:

1. Revisar esta documentación
2. Consultar `docs/I18N.md`
3. Verificar logs de PHP y consola del navegador
4. Abrir un issue en GitHub con detalles del error

---

**Fecha de implementación**: Diciembre 2025  
**Versión**: 1.0  
**Idiomas disponibles**: Inglés (en), Español (es)  
**Estado**: ✅ Producción Ready
