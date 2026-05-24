(function () {
    const cfg      = window.McpApiKeyCfg;
    const keyInput  = document.getElementById('mcp-apikey-value');
    const keyHints  = document.querySelectorAll('.mcp-apikey-hint');
    const toggleBtn = document.getElementById('mcp-apikey-toggle');
    const copyBtn   = document.getElementById('mcp-apikey-copy');
    const genBtn    = document.getElementById('mcp-apikey-gen');

    const eyeOpen  = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/></svg>';
    const eyeSlash = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-eye-slash" viewBox="0 0 16 16"><path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7 7 0 0 0-2.79.588l.77.771A6 6 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755q-.247.248-.517.486z"/><path d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829zm-2.943 1.299.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 0 2.829 2.829z"/><path d="M3.35 5.47q-.27.24-.518.487A13 13 0 0 0 1.172 8l.195.288c.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c.716 0 1.39-.133 2.02-.36l.77.772A7 7 0 0 1 8 13.5C3 13.5 0 8 0 8s.939-1.721 2.641-3.238l.708.709zm10.296 8.884-12-12 .708-.708 12 12z"/></svg>';

    function setKey(key) {
        keyInput.value       = key || '';
        keyInput.placeholder = key ? '' : 'No generada aún';
        keyHints.forEach(el => el.textContent = key || '…');
        genBtn.textContent   = key ? 'Regenerar API Key' : 'Generar API Key';
    }

    fetch(cfg.apiUrl)
        .then(r => r.json())
        .then(d => { if (d.success) setKey(d.api_key); })
        .catch(() => { keyInput.placeholder = 'Error al cargar'; });

    toggleBtn.addEventListener('click', function () {
        const isPassword    = keyInput.type === 'password';
        keyInput.type       = isPassword ? 'text' : 'password';
        toggleBtn.innerHTML = isPassword ? eyeSlash : eyeOpen;
    });

    copyBtn.addEventListener('click', function () {
        if (!keyInput.value) return;
        const orig = copyBtn.innerHTML;
        const showCheck = () => {
            copyBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-check" viewBox="0 0 16 16"><path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 0 1 1.06-1.06l2.094 2.093 3.473-4.425z"/></svg>';
            setTimeout(() => { copyBtn.innerHTML = orig; }, 1500);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(keyInput.value).then(showCheck);
        } else {
            keyInput.select();
            document.execCommand('copy');
            showCheck();
        }
    });

    document.getElementById('mcp-client-select').addEventListener('change', function () {
        document.querySelectorAll('.mcp-client-block').forEach(el => el.classList.add('d-none'));
        document.querySelector('.mcp-client-block[data-client="' + this.value + '"]').classList.remove('d-none');
    });

    genBtn.addEventListener('click', function () {
        if (keyInput.value && !confirm('¿Regenerar la API Key? La clave actual quedará invalidada.')) return;
        genBtn.disabled = true;
        fetch(cfg.apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': cfg.csrfToken },
            body: JSON.stringify({ csrf_token: cfg.csrfToken }),
        })
            .then(r => r.json())
            .then(d => { if (d.success) { setKey(d.api_key); keyInput.type = 'text'; toggleBtn.innerHTML = eyeSlash; } })
            .finally(() => { genBtn.disabled = false; });
    });
})();
