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

    // Orden que quedaría si el usuario soltara acá. Se calcula sobre el DOM
    // (que NO se toca durante el arrastre) y sólo se aplica de verdad cuando
    // el servidor responde success. Se limpia en dragend.
    let pendingOrder = null;

    // Confirmación PESIMISTA del reorder: mientras hay un request de orden en
    // vuelo la grilla no acepta otro arrastre (los items dejan de ser
    // draggable, igual que las subidas se serializan con isUploading) y el DOM
    // conserva el orden previo al arrastre. Así no puede haber dos reorders
    // solapados, no hace falta revertir nada si falla, y el DOM nunca puede
    // mostrar un orden que el servidor no haya aceptado. Los rounds 1 a 3 de
    // esta tarea intentaron reconciliar el camino optimista (revert, token de
    // generación, último orden confirmado) y cada arreglo destapó otro
    // entrelazado: el problema no era un guard faltante sino el diseño.
    let isReordering = false;

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

    function sameOrder(a, b) {
        return a.length === b.length && a.every(function (id, index) { return id === b[index]; });
    }

    /**
     * Marca la grilla como ocupada mientras viaja un reorder: los items dejan
     * de ser arrastrables (bloquea el gesto en el origen) y la clase is-busy
     * da el feedback visual de "esperando al servidor".
     */
    function setGridBusy(busy) {
        grid.classList.toggle('is-busy', busy);
        grid.querySelectorAll('.poi-gallery-item').forEach(function (item) {
            item.draggable = !busy;
        });
    }

    function clearDropHint() {
        grid.querySelectorAll('.poi-gallery-item').forEach(function (item) {
            item.classList.remove('drop-before', 'drop-after');
        });
    }

    /**
     * Reordena los nodos del DOM según la lista de ids CONFIRMADA por el
     * servidor. Las imágenes que no estén en la lista (subidas mientras el
     * request viajaba) van al final, en su orden relativo previo: es
     * exactamente la regla de PoiImage::reorder(), que a los ids que no
     * vinieron en la lista les asigna sort_order al final. Así el DOM y la
     * base quedan iguales aunque una subida se cruce con un reorder.
     */
    function applyOrder(order) {
        const confirmed = {};
        order.forEach(function (id) { confirmed[id] = true; });

        order.forEach(function (id) {
            const item = grid.querySelector('.poi-gallery-item[data-image-id="' + id + '"]');
            if (item) { grid.appendChild(item); }
        });

        Array.from(grid.querySelectorAll('.poi-gallery-item')).forEach(function (item) {
            if (!confirmed[item.dataset.imageId]) { grid.appendChild(item); }
        });

        refreshState();
    }

    /**
     * Calcula el orden resultante de mover draggedId junto a targetId, sin
     * tocar el DOM. El DOM se mantiene en el orden previo al arrastre hasta
     * que el servidor confirme.
     */
    function orderWithMove(draggedId, targetId, insertAfter) {
        const order = currentOrder().filter(function (id) { return id !== draggedId; });
        const targetIndex = order.indexOf(targetId);
        if (targetIndex === -1) { return currentOrder(); }

        order.splice(insertAfter ? targetIndex + 1 : targetIndex, 0, draggedId);
        return order;
    }

    function saveOrder(order) {
        const formData = new FormData();
        formData.append('action', 'reorder');
        formData.append('poi_id', poiId);
        order.forEach(function (id) { formData.append('image_ids[]', id); });
        return requestApi(formData);
    }

    /**
     * Manda el orden al servidor y sólo lo aplica al DOM si responde success.
     *
     * Mientras el request está en vuelo la grilla queda bloqueada, así que no
     * puede haber un segundo reorder en paralelo. Si falla (respuesta de error
     * o red caída) el DOM nunca se movió: no hay nada que revertir, sólo se
     * avisa. El desbloqueo va en el último .then() de la cadena, que corre
     * tanto si hubo éxito como si el .catch() ya manejó el rechazo — si no,
     * la grilla quedaría trabada hasta recargar la página.
     */
    function commitOrder(order) {
        if (isReordering) return;

        isReordering = true;
        setGridBusy(true);

        saveOrder(order)
            .then(function (result) {
                if (result.data.success) {
                    applyOrder(order);
                    return;
                }
                alert(__('points.gallery_reorder_error') + ': ' + (result.data.error || ''));
            })
            .catch(function () {
                alert(__('points.gallery_reorder_error'));
            })
            .then(function () {
                isReordering = false;
                setGridBusy(false);
            });
    }

    // ── Alta ─────────────────────────────────────────────────────────────────

    function buildItem(image) {
        const item = document.createElement('div');
        item.className = 'poi-gallery-item';
        // Si justo hay un reorder en vuelo, la foto nueva nace no arrastrable;
        // setGridBusy(false) la habilita cuando el reorder termina.
        item.draggable = !isReordering;
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
            pendingOrder = null;
            item.classList.add('is-dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        item.addEventListener('dragover', function (e) {
            // preventDefault siempre: sin esto un archivo arrastrado desde el
            // escritorio sobre un item haría que el navegador lo abra.
            e.preventDefault();
            if (!draggedItem || draggedItem === item) return;

            const box = item.getBoundingClientRect();
            const insertAfter = (e.clientX - box.left) > (box.width / 2);

            clearDropHint();
            item.classList.add(insertAfter ? 'drop-after' : 'drop-before');
            pendingOrder = orderWithMove(draggedItem.dataset.imageId, item.dataset.imageId, insertAfter);
        });

        // Se confirma en drop, no en dragend: si el usuario suelta fuera de la
        // grilla el drop no dispara y el arrastre se cancela sin mandar nada.
        item.addEventListener('drop', function (e) {
            e.preventDefault();
            if (!draggedItem || !pendingOrder) return;
            if (sameOrder(pendingOrder, currentOrder())) return;

            commitOrder(pendingOrder);
        });

        item.addEventListener('dragend', function () {
            item.classList.remove('is-dragging');
            clearDropHint();
            draggedItem = null;
            pendingOrder = null;
        });
    }

    grid.querySelectorAll('.poi-gallery-item').forEach(wireItem);
    refreshState();
})();
