/**
 * Galería de imágenes del formulario de POI.
 *
 * Subida, borrado, reordenamiento y captions, todo inmediato contra
 * api/poi_images.php. Se sube de a un archivo por request para no chocar
 * contra post_max_size.
 */
(function () {
    const root = document.getElementById('poi-gallery');
    if (!root) return;

    const poiId            = root.dataset.poiId;
    const grid              = document.getElementById('poiGalleryGrid');
    const dropZone          = document.getElementById('poiGalleryDrop');
    const fileInput         = document.getElementById('poiGalleryInput');
    const selectButton      = document.getElementById('poiGallerySelect');
    const emptyMessage      = document.getElementById('poiGalleryEmpty');
    const progressWrapper   = document.getElementById('poiGalleryProgress');
    const progressBar       = progressWrapper.querySelector('.progress-bar');

    const ENDPOINT = BASE_URL + '/api/poi_images.php';

    let draggedItem     = null;
    let orderBeforeDrag = null;

    // Token de generación para reorders: cada llamado a confirmOrder() toma
    // el número siguiente. A diferencia de las subidas (que se serializan con
    // isUploading), un reorder en vuelo NO bloquea el siguiente drag — el
    // usuario puede seguir reordenando de inmediato. Por eso, si dos reorders
    // quedan en vuelo a la vez y el más viejo falla después de que el más
    // nuevo ya tuvo éxito, revertir con el snapshot del viejo pisaría en
    // silencio un estado que el servidor ya confirmó. La regla: un intento
    // sólo puede revertir el DOM si sigue siendo el más nuevo cuando su
    // respuesta llega (ver revertIfCurrent en confirmOrder).
    let orderGeneration = 0;

    // Cola única de subida: si el usuario suelta una segunda tanda mientras la
    // primera sigue en curso, se acumula acá en vez de arrancar una segunda
    // recursión independiente que pisaría la misma barra de progreso.
    let uploadQueue = [];
    let queueTotal  = 0;
    let queueDone   = 0;
    let isUploading = false;

    // Porcentaje mostrado en la barra: sólo puede crecer. Si entra una tanda
    // nueva mientras otra está a mitad de camino, queueTotal crece y el
    // cálculo crudo (queueDone / queueTotal) puede bajar; clampeamos para que
    // la barra nunca retroceda visualmente (cosmético, no afecta el resultado
    // final: igual llega a 100% y se esconde).
    let displayedPercent = 0;

    /**
     * Devuelve {status, data}: el status HTTP se necesita para distinguir,
     * por ejemplo, un 404 (la fila ya no existe) de un 500 real. Si la
     * respuesta no es JSON válido (por ejemplo, una redirección a login),
     * la promesa se rechaza y el llamador la maneja con su propio .catch().
     */
    function requestApi(formData) {
        return fetch(ENDPOINT, { method: 'POST', body: formData, credentials: 'same-origin' })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { status: response.status, data: data };
                });
            });
    }

    // ── Estado visual ────────────────────────────────────────────────────────

    function refreshState() {
        const items = grid.querySelectorAll('.poi-gallery-item');
        items.forEach(function (item, index) {
            item.classList.toggle('is-cover', index === 0);
        });
        emptyMessage.classList.toggle('d-none', items.length > 0);
    }

    function currentOrder() {
        return Array.from(grid.querySelectorAll('.poi-gallery-item'))
                    .map(function (el) { return el.dataset.imageId; });
    }

    /**
     * Reordena los nodos del DOM según la lista de ids recibida.
     * Se usa para revertir un reorder que el servidor no confirmó.
     */
    function applyOrder(order) {
        order.forEach(function (id) {
            const item = grid.querySelector('.poi-gallery-item[data-image-id="' + id + '"]');
            if (item) { grid.appendChild(item); }
        });
        refreshState();
    }

    function saveOrder() {
        const formData = new FormData();
        formData.append('action', 'reorder');
        formData.append('poi_id', poiId);
        currentOrder().forEach(function (id) { formData.append('image_ids[]', id); });
        return requestApi(formData);
    }

    /**
     * Confirma contra el servidor el reorder ya aplicado visualmente al soltar.
     * Si el servidor no lo confirma (falla o red caída), revierte el DOM al
     * orden previo a ESTE drag — pero sólo si ningún reorder más nuevo arrancó
     * mientras este request estaba en vuelo (ver comentario de orderGeneration
     * arriba). Si ya hay uno más nuevo, ese es quien manda: revertir acá
     * pisaría en silencio un estado que el servidor ya confirmó.
     */
    function confirmOrder(previousOrder) {
        const myGeneration = ++orderGeneration;

        function revertIfStillCurrent(message) {
            if (myGeneration !== orderGeneration) return;
            applyOrder(previousOrder);
            alert(message);
        }

        return saveOrder()
            .then(function (result) {
                if (!result.data.success) {
                    revertIfStillCurrent(__('points.gallery_reorder_error') + ': ' + (result.data.error || ''));
                }
            })
            .catch(function () {
                revertIfStillCurrent(__('points.gallery_reorder_error'));
            });
    }

    // ── Alta ─────────────────────────────────────────────────────────────────

    function buildItem(image) {
        const item = document.createElement('div');
        item.className = 'poi-gallery-item';
        item.draggable = true;
        item.dataset.imageId = image.id;

        const img = document.createElement('img');
        img.src = image.thumbnail_url || image.url;
        item.appendChild(img);

        const badge = document.createElement('span');
        badge.className = 'poi-gallery-cover-badge';
        badge.textContent = __('points.gallery_cover');
        item.appendChild(badge);

        const caption = document.createElement('input');
        caption.type = 'text';
        caption.className = 'poi-gallery-caption form-control form-control-sm';
        caption.placeholder = __('points.gallery_caption_placeholder');
        caption.value = image.caption || '';
        item.appendChild(caption);

        const deleteButton = document.createElement('button');
        deleteButton.type = 'button';
        deleteButton.className = 'btn btn-sm btn-outline-danger poi-gallery-delete';
        deleteButton.textContent = __('common.remove');
        item.appendChild(deleteButton);

        wireItem(item);
        return item;
    }

    function uploadOne(file) {
        const formData = new FormData();
        formData.append('action', 'upload');
        formData.append('poi_id', poiId);
        formData.append('image', file);

        return requestApi(formData)
            .then(function (result) {
                if (!result.data.success) {
                    alert(__('points.gallery_upload_error') + ': ' + (result.data.error || ''));
                    return;
                }
                grid.appendChild(buildItem(result.data.image));
                refreshState();
            })
            .catch(function () {
                // Error de red u otra falla no controlada: avisar y seguir con
                // el resto de la cola en vez de dejar la barra de progreso trabada.
                alert(__('points.gallery_upload_error'));
            });
    }

    // Encola archivos nuevos en la tanda en curso (si la hay) en vez de
    // arrancar una segunda recursión que compita por la misma barra.
    function enqueueUploads(files) {
        const incoming = Array.from(files);
        if (incoming.length === 0) return;

        uploadQueue = uploadQueue.concat(incoming);
        queueTotal += incoming.length;

        if (isUploading) return;

        isUploading = true;
        progressWrapper.classList.remove('d-none');
        processQueue();
    }

    // Secuencial: un archivo por request
    function processQueue() {
        if (uploadQueue.length === 0) {
            isUploading = false;
            queueTotal = 0;
            queueDone = 0;
            displayedPercent = 0;
            progressWrapper.classList.add('d-none');
            progressBar.style.width = '0%';
            return;
        }

        return uploadOne(uploadQueue.shift()).then(function () {
            queueDone++;
            const rawPercent = Math.round((queueDone / queueTotal) * 100);
            displayedPercent = Math.max(displayedPercent, rawPercent);
            progressBar.style.width = displayedPercent + '%';
            return processQueue();
        });
    }

    selectButton.addEventListener('click', function (e) { e.stopPropagation(); fileInput.click(); });
    dropZone.addEventListener('click', function () { fileInput.click(); });
    fileInput.addEventListener('change', function () { enqueueUploads(this.files); fileInput.value = ''; });

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function (eventName) {
        dropZone.addEventListener(eventName, function (e) { e.preventDefault(); e.stopPropagation(); });
    });
    dropZone.addEventListener('drop', function (e) { enqueueUploads(e.dataTransfer.files); });

    // ── Borrado y caption ────────────────────────────────────────────────────

    function deleteItem(item) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('image_id', item.dataset.imageId);

        return requestApi(formData)
            .then(function (result) {
                // 404 = la fila ya no existe (por ejemplo, doble clic sobre el
                // botón de borrar): desde la perspectiva del usuario es un éxito,
                // así que sacamos el elemento igual.
                if (result.data.success || result.status === 404) {
                    item.remove();
                    refreshState();
                    return;
                }
                alert(__('points.gallery_delete_error') + ': ' + (result.data.error || ''));
            })
            .catch(function () {
                alert(__('points.gallery_delete_error'));
            });
    }

    function saveCaption(item, value) {
        const formData = new FormData();
        formData.append('action', 'caption');
        formData.append('image_id', item.dataset.imageId);
        formData.append('caption', value);

        return requestApi(formData)
            .then(function (result) {
                if (!result.data.success) {
                    alert(__('points.gallery_caption_error') + ': ' + (result.data.error || ''));
                }
            })
            .catch(function () {
                alert(__('points.gallery_caption_error'));
            });
    }

    function wireItem(item) {
        item.querySelector('.poi-gallery-delete').addEventListener('click', function () {
            if (!confirm(__('points.gallery_delete_confirm'))) return;
            deleteItem(item);
        });

        item.querySelector('.poi-gallery-caption').addEventListener('blur', function () {
            saveCaption(item, this.value);
        });

        item.addEventListener('dragstart', function (e) {
            draggedItem = item;
            orderBeforeDrag = currentOrder();
            item.classList.add('is-dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        item.addEventListener('dragend', function () {
            item.classList.remove('is-dragging');
            draggedItem = null;
            refreshState();
            confirmOrder(orderBeforeDrag);
        });

        item.addEventListener('dragover', function (e) {
            e.preventDefault();
            if (!draggedItem || draggedItem === item) return;

            const box = item.getBoundingClientRect();
            const insertAfter = (e.clientX - box.left) > (box.width / 2);
            grid.insertBefore(draggedItem, insertAfter ? item.nextSibling : item);
        });
    }

    grid.querySelectorAll('.poi-gallery-item').forEach(wireItem);
    refreshState();
})();
