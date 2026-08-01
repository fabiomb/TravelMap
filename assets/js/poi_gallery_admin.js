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

    let draggedItem = null;

    // Token de generación para reorders: cada llamado a confirmOrder() toma
    // el número siguiente. A diferencia de las subidas (que se serializan con
    // isUploading), un reorder en vuelo NO bloquea el siguiente drag — el
    // usuario puede seguir reordenando de inmediato. Dos contadores, dos
    // criterios distintos (no es el mismo chequeo repetido dos veces):
    //
    // - REVERT (falla): sólo el intento MÁS NUEVO puede revertir el DOM
    //   (myGeneration === orderGeneration, chequeado en confirmOrder). Si uno
    //   más nuevo ya arrancó, ese manda: revertir acá interrumpiría en el
    //   aire un drag que el usuario todavía puede estar completando.
    // - CONFIRMACIÓN (éxito): CUALQUIER éxito cuenta, pero sólo avanza
    //   lastConfirmedOrder si es más nuevo que la última confirmación ya
    //   aplicada (myGeneration > lastConfirmedGeneration), nunca al revés.
    //   Esto importa en una cadena de 3+ drags solapados donde el del medio
    //   confirma con éxito pero el primero y el último fallan: si la
    //   confirmación exigiera "ser el más nuevo AHORA" (mismo criterio que el
    //   revert), el éxito confirmado del medio se descartaría apenas arranca
    //   el tercer drag, y el revert final del tercero caería hasta el estado
    //   original, tirando por la borda un cambio que el servidor sí tiene
    //   guardado. Ver la traza de 3 drags en el reporte de la Task 5, Round 3.
    let orderGeneration       = 0;
    let lastConfirmedGeneration = 0;

    // Último orden que el SERVIDOR confirmó — no "lo que mostraba el DOM
    // cuando arrancó este drag". Un snapshot local del DOM puede contener el
    // movimiento optimista de un drag anterior que nunca se confirmó, así
    // que revertir a un snapshot local puede terminar mostrando un orden que
    // el servidor nunca tuvo (ver Round 3 en el reporte de la Task 5). Se
    // inicializa con el orden que renderizó PHP al cargar la página, que sí
    // viene de la base (poi_images.sort_order), y sólo avanza cuando el
    // servidor responde success a un reorder más nuevo que el último
    // confirmado (ver confirmOrder).
    let lastConfirmedOrder = null;

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

    /**
     * Envía el orden actual del DOM. Devuelve también ese mismo orden
     * (`submittedOrder`), tomado en este instante — no el orden que el DOM
     * tenga cuando la respuesta llegue, que puede haber cambiado por otro
     * drag mientras este request estaba en vuelo.
     */
    function saveOrder() {
        const submittedOrder = currentOrder();
        const formData = new FormData();
        formData.append('action', 'reorder');
        formData.append('poi_id', poiId);
        submittedOrder.forEach(function (id) { formData.append('image_ids[]', id); });
        return requestApi(formData).then(function (result) {
            return { result: result, submittedOrder: submittedOrder };
        });
    }

    /**
     * Confirma contra el servidor el reorder ya aplicado visualmente al soltar.
     *
     * - Si el servidor confirma éxito Y este intento es más nuevo que la
     *   última confirmación ya aplicada, `submittedOrder` pasa a ser el nuevo
     *   `lastConfirmedOrder` — un orden que el servidor realmente tiene, sin
     *   retroceder nunca a una confirmación más vieja que llegue tarde.
     * - Si el servidor no lo confirma (falla o red caída) Y este intento
     *   sigue siendo el más nuevo en curso, revierte el DOM a
     *   `lastConfirmedOrder` — nunca a un snapshot local del DOM: ese
     *   snapshot podría incluir el movimiento optimista de un drag anterior
     *   que nunca se confirmó, y revertir a él mostraría un orden que el
     *   servidor nunca tuvo. Si ya arrancó un intento más nuevo, no se
     *   revierte: ese intento más nuevo es quien decide, con su propio éxito
     *   o fallo, qué pasa al final.
     */
    function confirmOrder() {
        const myGeneration = ++orderGeneration;

        return saveOrder()
            .then(function (outcome) {
                if (outcome.result.data.success) {
                    if (myGeneration > lastConfirmedGeneration) {
                        lastConfirmedOrder = outcome.submittedOrder;
                        lastConfirmedGeneration = myGeneration;
                    }
                    return;
                }
                if (myGeneration === orderGeneration) {
                    applyOrder(lastConfirmedOrder);
                    alert(__('points.gallery_reorder_error') + ': ' + (outcome.result.data.error || ''));
                }
            })
            .catch(function () {
                if (myGeneration === orderGeneration) {
                    applyOrder(lastConfirmedOrder);
                    alert(__('points.gallery_reorder_error'));
                }
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
            item.classList.add('is-dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        item.addEventListener('dragend', function () {
            item.classList.remove('is-dragging');
            draggedItem = null;
            refreshState();
            confirmOrder();
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
    // El orden renderizado por PHP viene de poi_images.sort_order: es la
    // línea de base real contra la que revertir hasta el primer reorder.
    lastConfirmedOrder = currentOrder();
})();
