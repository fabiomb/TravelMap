# 🚀 Quick Start - Sistema Multi-Idioma TravelMap

## ⚡ Inicio Rápido en 3 Pasos

### Paso 1: Ejecutar Migración (Solo Primera Vez)

Navega a:
```
http://localhost/TravelMap/install/migrate_language.php
```

Verás una pantalla de confirmación. Si todo está OK, continúa.

### Paso 2: Configurar Idioma Por Defecto (Opcional)

1. Ve a: `http://localhost/TravelMap/admin/`
2. Login con tus credenciales
3. Click en "Configuración"
4. Selecciona "Idioma por Defecto": English o Español
5. Click "Guardar Configuración"

### Paso 3: ¡Probar!

1. Ve a: `http://localhost/TravelMap/`
2. Abre el panel lateral (botón "Mis Viajes")
3. Scroll al final del panel
4. Cambia el idioma en el selector
5. La página se recarga en el nuevo idioma ✨

---

## 🎯 Para Usuarios

### Cambiar Idioma

```
Panel Lateral → Scroll Abajo → Selector de Idioma → Seleccionar
```

La página se recarga automáticamente. Tu preferencia se guarda.

---

## 🔧 Para Administradores

### Configurar Idioma Default

```
Admin → Configuración → Idioma por Defecto → Guardar
```

Los nuevos visitantes verán el sitio en este idioma.

---

## 💻 Para Desarrolladores

### Usar Traducciones

**PHP:**
```php
<?= __('map.my_trips') ?>
<?= __('common.save') ?>
```

**JavaScript:**
```javascript
const text = __('map.routes');
alert(__('messages.saved_success'));
```

### Agregar Traducción

1. Editar `lang/en.json` y `lang/es.json`
2. Agregar clave: `"section.new_key": "Text"`
3. Usar: `__('section.new_key')`

---

## 📚 Más Información

- **Documentación Completa**: [docs/I18N.md](I18N.md)
- **Guía de Instalación**: [../install/MULTILANGUAGE_INSTALLATION.md](../install/MULTILANGUAGE_INSTALLATION.md)
- **Resumen Técnico**: [docs/IMPLEMENTATION_SUMMARY.md](IMPLEMENTATION_SUMMARY.md)

---

## ❓ Problemas Comunes

### El idioma no cambia
- Limpiar cache del navegador
- Limpiar localStorage: `localStorage.clear()`
- Verificar que los archivos JSON existen en `lang/`

### Traducciones no aparecen
- Verificar sintaxis JSON: `php -r "json_decode(file_get_contents('lang/en.json'));"`
- Verificar que la clave existe en el archivo JSON

### Selector de idioma no aparece
- Verificar que `assets/js/i18n.js` se carga
- Revisar consola del navegador para errores

---

## ✅ Checklist

- [ ] Migración ejecutada
- [ ] Selector de idioma visible
- [ ] Cambio de idioma funciona
- [ ] Preferencia se guarda
- [ ] Configuración de admin actualizada

---

**¿Listo? ¡Disfruta TravelMap en tu idioma!** 🌍
