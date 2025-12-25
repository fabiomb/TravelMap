# IMPORTANTE: Descarga de Librerías Locales

Para que la aplicación funcione correctamente, debes descargar las siguientes librerías y colocarlas en las carpetas indicadas:

## 📦 Bootstrap 5

**Descargar de:** https://getbootstrap.com/docs/5.3/getting-started/download/

**Versión recomendada:** 5.3.x

**Archivos necesarios:**
- `bootstrap.min.css` → Colocar en: `assets/vendor/bootstrap/css/`
- `bootstrap.bundle.min.js` → Colocar en: `assets/vendor/bootstrap/js/`

**Estructura final:**
```
assets/vendor/bootstrap/
├── css/
│   └── bootstrap.min.css
└── js/
    └── bootstrap.bundle.min.js
```

## 📦 jQuery

**Descargar de:** https://jquery.com/download/

**Versión recomendada:** 3.7.x (versión comprimida/minified)

**Archivo necesario:**
- `jquery.min.js` → Colocar en: `assets/vendor/jquery/`

**Estructura final:**
```
assets/vendor/jquery/
└── jquery.min.js
```

## 📦 Leaflet.js (Para Fase 4)

**Descargar de:** https://leafletjs.com/download.html

**Versión recomendada:** 1.9.x

**Archivos necesarios:**
- `leaflet.css` → Colocar en: `assets/vendor/leaflet/css/`
- `leaflet.js` → Colocar en: `assets/vendor/leaflet/js/`
- Carpeta `images` (con los iconos) → Colocar en: `assets/vendor/leaflet/css/images/`

**Plugins adicionales (para fases posteriores):**
- Leaflet.draw
- Leaflet.markercluster
- Leaflet.polylineDecorator

## 🚀 Pasos Rápidos

1. Descarga Bootstrap 5 (compiled CSS and JS)
2. Extrae y copia:
   - `bootstrap.min.css` a `assets/vendor/bootstrap/css/`
   - `bootstrap.bundle.min.js` a `assets/vendor/bootstrap/js/`

3. Descarga jQuery (compressed, production)
4. Renombra a `jquery.min.js` y copia a `assets/vendor/jquery/`

5. (Opcional para ahora) Descarga Leaflet.js
6. Extrae y copia los archivos a `assets/vendor/leaflet/`

## ⚠️ Nota Importante

Sin estas librerías, la aplicación no funcionará correctamente ya que el layout depende de Bootstrap y el JavaScript usa jQuery. Asegúrate de descargarlas antes de intentar acceder al panel de administración.

## ✅ Verificación

Puedes verificar que los archivos estén correctamente instalados accediendo a:
- `http://localhost/TravelMap/assets/vendor/bootstrap/css/bootstrap.min.css`
- `http://localhost/TravelMap/assets/vendor/bootstrap/js/bootstrap.bundle.min.js`
- `http://localhost/TravelMap/assets/vendor/jquery/jquery.min.js`

Si ves el código fuente de las librerías, ¡están instaladas correctamente!
