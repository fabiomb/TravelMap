# Parámetros de URL para TravelMap

Esta funcionalidad permite controlar el estado inicial del mapa mediante parámetros en la URL. Los parámetros se pasan a través del query string en la URL del index.php.

## Funcionalidad de Compartir Enlace

En el menú lateral del mapa público encontrarás un botón **"Compartir Enlace"** que genera automáticamente una URL con los parámetros actuales del mapa:

- ✅ Posición actual del mapa (center)
- ✅ Nivel de zoom actual
- ✅ Viajes seleccionados visibles
- ✅ Estado de rutas (mostrar/ocultar)
- ✅ Estado de puntos de interés (mostrar/ocultar)
- ✅ Estado de vuelos (mostrar/ocultar)

Al hacer clic en el botón, el enlace se copia automáticamente al portapapeles y puedes compartirlo con quien quieras.

## Parámetros Disponibles

### 1. **center** - Posición de Centrado
Define el punto central del mapa al cargar.

- **Formato**: `center=latitud,longitud`
- **Ejemplo**: `?center=40.4168,-3.7038` (Madrid, España)
- **Valores**: Dos números decimales separados por coma (latitud, longitud)
- **Rango**: 
  - Latitud: -90 a 90
  - Longitud: -180 a 180

### 2. **zoom** - Nivel de Zoom
Define el nivel de zoom inicial del mapa.

- **Formato**: `zoom=nivel`
- **Ejemplo**: `?zoom=10`
- **Valores**: Número entre 1 (mundo completo) y 18 (máximo detalle)

### 3. **trips** - Viajes a Mostrar
Define qué viajes deben estar visibles al cargar el mapa.

- **Formato**: `trips=id1,id2,id3`
- **Ejemplo**: `?trips=1,5,8`
- **Valores**: Lista de IDs de viajes separados por comas
- **Nota**: Solo se mostrarán los viajes especificados, el resto estará oculto

### 4. **routes** - Mostrar Rutas
Controla si las rutas deben estar visibles.

- **Formato**: `routes=1` o `routes=0` (también acepta `true`/`false`)
- **Ejemplo**: `?routes=1` (mostrar rutas)
- **Valores**: 
  - `1` o `true` = Mostrar rutas
  - `0` o `false` = Ocultar rutas

### 5. **points** - Mostrar Puntos de Interés
Controla si los puntos de interés deben estar visibles.

- **Formato**: `points=1` o `points=0` (también acepta `true`/`false`)
- **Ejemplo**: `?points=0` (ocultar puntos)
- **Valores**: 
  - `1` o `true` = Mostrar puntos
  - `0` o `false` = Ocultar puntos

### 6. **flights** - Mostrar Vuelos
Controla si las rutas de vuelos deben estar visibles.

- **Formato**: `flights=1` o `flights=0` (también acepta `true`/`false`)
- **Ejemplo**: `?flights=1` (mostrar vuelos)
- **Valores**: 
  - `1` o `true` = Mostrar vuelos
  - `0` o `false` = Ocultar vuelos

## Ejemplos de Uso

### Ejemplo 1: Vista de Madrid con zoom específico
```
http://tudominio.com/index.php?center=40.4168,-3.7038&zoom=12
```
Centra el mapa en Madrid con un nivel de zoom de 12.

### Ejemplo 2: Mostrar solo viajes específicos
```
http://tudominio.com/index.php?trips=1,3,5
```
Muestra únicamente los viajes con IDs 1, 3 y 5.

### Ejemplo 3: Vista completa con configuración personalizada
```
http://tudominio.com/index.php?center=40.4168,-3.7038&zoom=10&trips=1,2&routes=1&points=1&flights=0
```
Centra en Madrid con zoom 10, muestra los viajes 1 y 2, con rutas y puntos visibles pero sin vuelos.

### Ejemplo 4: Solo mostrar rutas sin puntos ni vuelos
```
http://tudominio.com/index.php?trips=1,2,3&routes=1&points=0&flights=0
```
Muestra los viajes 1, 2 y 3 con solo las rutas visibles.

### Ejemplo 5: Vista global de todos los vuelos
```
http://tudominio.com/index.php?zoom=2&routes=0&points=0&flights=1
```
Muestra una vista global (zoom 2) con solo las rutas de vuelos visibles.

### Ejemplo 6: Centrar en Buenos Aires sin zoom específico
```
http://tudominio.com/index.php?center=-34.6037,-58.3816
```
Centra el mapa en Buenos Aires, manteniendo el zoom automático según el contenido.

### Ejemplo 7: Zoom específico sin centro
```
http://tudominio.com/index.php?zoom=5&trips=10
```
Muestra el viaje 10 con un nivel de zoom de 5, pero centra automáticamente según el contenido del viaje.

## Notas Importantes

1. **Prioridad de parámetros**: Los parámetros de URL tienen prioridad sobre las preferencias guardadas en localStorage.

2. **Combinación de parámetros**: Puedes combinar cualquier parámetro usando `&` como separador.

3. **Valores por defecto**: Si no se especifica un parámetro:
   - **center/zoom**: Se ajustan automáticamente al contenido visible
   - **trips**: Se muestran todos los viajes (o los guardados en preferencias)
   - **routes/points/flights**: Se usan las preferencias guardadas del usuario

4. **Validación**: Los parámetros inválidos se ignoran silenciosamente:
   - IDs de viajes que no existen
   - Coordenadas fuera de rango
   - Niveles de zoom fuera del rango 1-18

5. **Codificación de URL**: Si usas estos parámetros programáticamente, asegúrate de codificar correctamente los valores usando `encodeURIComponent()`.

## Casos de Uso

### Para compartir ubicaciones específicas
Perfecto para compartir un enlace que lleve directamente a una ubicación y configuración específica del mapa.

### Para embeber en otras páginas
Puedes usar iframes con URLs parametrizadas para mostrar vistas específicas de tus viajes.

### Para presentaciones
Prepara diferentes URLs con distintas configuraciones para mostrar diferentes aspectos de tus viajes durante presentaciones.

### Para bookmarks personalizados
Guarda enlaces favoritos con diferentes configuraciones para acceder rápidamente a vistas específicas.

## Implementación Técnica

Los parámetros son procesados por JavaScript al cargar la página:
- Se parsean mediante `URLSearchParams`
- Tienen prioridad sobre las preferencias guardadas en localStorage
- Se aplican después de cargar los datos del servidor pero antes de renderizar el mapa
- Son compatibles con ambas versiones del mapa (MapLibre GL y Leaflet)

## Cómo Usar el Botón de Compartir Enlace

1. **Navega en el mapa** hasta la posición que deseas compartir
2. **Ajusta el zoom** al nivel deseado
3. **Selecciona los viajes** que quieres que se muestren usando las casillas en el panel lateral
4. **Activa/desactiva** las opciones de rutas, puntos de interés y vuelos según tus preferencias
5. **Haz clic en el botón "Compartir Enlace"** en el footer del panel lateral
6. El enlace se copia automáticamente al portapapeles
7. **Pega el enlace** donde quieras compartirlo (email, chat, redes sociales, etc.)

El enlace generado capturará exactamente la vista y configuración que tienes en pantalla, para que quien lo abra vea exactamente lo mismo que tú.
