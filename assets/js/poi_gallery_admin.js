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

    function requestApi(formData) {
        return fetch(ENDPOINT, { method: 'POST', body: formData, credentials: 'same-origin' })
            .then(function (response) { return response.json(); });
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

    function saveOrder() {
        const formData = new FormData();
        formData.append('action', 'reorder');
        formData.append('poi_id', poiId);
        currentOrder().forEach(function (id) { formData.append('image_ids[]', id); });
        return requestApi(formData);
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
            .then(function (response) {
                if (!response.success) {
                    alert(__('points.gallery_upload_error') + ': ' + (response.error || ''));
                    return;
                }
                grid.appendChild(buildItem(response.image));
                refreshState();
            })
            .catch(function () {
                // Error de red u otra falla no controlada: avisar y seguir con
                // el resto de la cola en vez de dejar la barra de progreso trabada.
                alert(__('points.gallery_upload_error'));
            });
    }

    // Secuencial: un archivo por request
    function uploadMany(files) {
        const pending = Array.from(files);
        if (pending.length === 0) return;

        progressWrapper.classList.remove('d-none');
        const total = pending.length;
        let done = 0;

        function next() {
            if (pending.length === 0) {
                progressWrapper.classList.add('d-none');
                progressBar.style.width = '0%';
                return;
            }
            return uploadOne(pending.shift()).then(function () {
                done++;
                progressBar.style.width = Math.round((done / total) * 100) + '%';
                return next();
            });
        }

        next();
    }

    selectButton.addEventListener('click', function (e) { e.stopPropagation(); fileInput.click(); });
    dropZone.addEventListener('click', function () { fileInput.click(); });
    fileInput.addEventListener('change', function () { uploadMany(this.files); fileInput.value = ''; });

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function (eventName) {
        dropZone.addEventListener(eventName, function (e) { e.preventDefault(); e.stopPropagation(); });
    });
    dropZone.addEventListener('drop', function (e) { uploadMany(e.dataTransfer.files); });

    // ── Borrado y caption ────────────────────────────────────────────────────

    function wireItem(item) {
        item.querySelector('.poi-gallery-delete').addEventListener('click', function () {
            if (!confirm(__('points.gallery_delete_confirm'))) return;

            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('image_id', item.dataset.imageId);

            requestApi(formData).then(function (response) {
                if (response.success) { item.remove(); refreshState(); }
            });
        });

        item.querySelector('.poi-gallery-caption').addEventListener('blur', function () {
            const formData = new FormData();
            formData.append('action', 'caption');
            formData.append('image_id', item.dataset.imageId);
            formData.append('caption', this.value);
            requestApi(formData);
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
            saveOrder();
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
