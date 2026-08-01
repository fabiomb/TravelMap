/**
 * Lightbox con galería para el mapa general.
 *
 * Recorre sólo las imágenes del POI abierto: index.php muestra todos los
 * viajes a la vez y encadenar puntos sin relación no aporta contexto.
 * Compartido por los dos renderers (MapLibre y Leaflet).
 */
(function () {
    var currentGallery = [];
    var currentIndex = 0;

    function el(id) { return document.getElementById(id); }

    function render() {
        var image = currentGallery[currentIndex];
        if (!image) return;

        el('lightboxImage').src = image.url;
        el('lightboxImage').alt = image.caption || '';

        var caption = el('lightboxCaption');
        caption.textContent = image.caption || '';
        caption.style.display = image.caption ? 'block' : 'none';

        var hasMultiple = currentGallery.length > 1;
        el('lightboxPrev').style.display = hasMultiple ? 'block' : 'none';
        el('lightboxNext').style.display = hasMultiple ? 'block' : 'none';

        var counter = el('lightboxCounter');
        if (hasMultiple) {
            var template = (typeof window.__ === 'function')
                ? window.__('map.gallery_counter') : '{current} / {total}';
            counter.textContent = template
                .replace('{current}', currentIndex + 1)
                .replace('{total}', currentGallery.length);
            counter.style.display = 'block';
        } else {
            counter.style.display = 'none';
        }
    }

    /**
     * Abre el lightbox en la galería de un POI.
     */
    window.openPoiGallery = function (poiId, index) {
        var images = (window.__poiGalleries || {})[poiId];
        if (!images || !images.length) return;

        currentGallery = images;
        currentIndex = index || 0;

        render();
        el('imageLightbox').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };

    /**
     * Avanza dentro de la galería, con vuelta circular.
     */
    window.changeImage = function (step) {
        if (currentGallery.length < 2) return;
        var total = currentGallery.length;
        currentIndex = ((currentIndex + step) % total + total) % total;
        render();
    };

    // Las imágenes de ruta usan openLightbox() y no tienen galería:
    // hay que limpiar el estado para que no queden flechas de la vez anterior.
    var openStandalone = window.openLightbox;
    window.openLightbox = function (url, alt) {
        currentGallery = [];
        currentIndex = 0;
        el('lightboxPrev').style.display = 'none';
        el('lightboxNext').style.display = 'none';
        el('lightboxCounter').style.display = 'none';
        el('lightboxCaption').style.display = 'none';
        if (typeof openStandalone === 'function') openStandalone(url, alt);
    };

    // Cierre por backdrop. Antes vivía duplicado en los dos renderers y cerraba
    // ante CUALQUIER click: con las flechas adentro, eso rompía la navegación.
    var lightbox = el('imageLightbox');
    if (lightbox) {
        lightbox.addEventListener('click', function (e) {
            if (e.target === lightbox) window.closeLightbox();
        });
    }

    document.addEventListener('keydown', function (e) {
        var box = el('imageLightbox');
        if (!box || box.style.display !== 'flex') return;
        if (e.key === 'ArrowLeft') window.changeImage(-1);
        if (e.key === 'ArrowRight') window.changeImage(1);
    });
})();
