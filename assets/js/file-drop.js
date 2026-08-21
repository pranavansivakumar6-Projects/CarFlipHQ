(function () {
    const inputs = Array.from(document.querySelectorAll('input[type="file"]')).filter((input) => {
        const accept = (input.getAttribute('accept') || '').toLowerCase();
        return accept.includes('image') || accept.includes('pdf');
    });

    if (!inputs.length || typeof DataTransfer === 'undefined') {
        return;
    }

    const setInputFile = (input, file) => {
        if (!file) return false;
        const accept = (input.getAttribute('accept') || '').toLowerCase();
        const isImage = file.type.startsWith('image/');
        const isPdf = file.type === 'application/pdf';
        if (accept.includes('pdf') && !isImage && !isPdf) return false;
        if (!accept.includes('pdf') && !isImage) return false;

        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    };

    const updateStatus = (input, text) => {
        const zone = input.closest('.file-drop-zone');
        const status = zone ? zone.querySelector('[data-file-drop-status]') : null;
        if (status) status.textContent = text;
    };

    inputs.forEach((input) => {
        if (input.closest('.file-drop-zone')) return;

        const zone = document.createElement('div');
        zone.className = 'file-drop-zone';
        input.parentNode.insertBefore(zone, input);
        zone.appendChild(input);

        const hint = document.createElement('div');
        hint.className = 'file-drop-hint';
        hint.innerHTML = '<strong>Drop image here</strong><span>or paste from clipboard, camera, screenshot, AVIF, JPG, PNG, WebP, GIF' +
            ((input.getAttribute('accept') || '').includes('pdf') ? ', PDF' : '') + '</span>';
        zone.appendChild(hint);

        const status = document.createElement('div');
        status.className = 'file-drop-status';
        status.setAttribute('data-file-drop-status', '');
        zone.appendChild(status);

        input.addEventListener('change', () => {
            updateStatus(input, input.files && input.files[0] ? `Selected: ${input.files[0].name}` : '');
        });

        ['dragenter', 'dragover'].forEach((eventName) => {
            zone.addEventListener(eventName, (event) => {
                event.preventDefault();
                zone.classList.add('is-dragging');
            });
        });

        ['dragleave', 'drop'].forEach((eventName) => {
            zone.addEventListener(eventName, () => zone.classList.remove('is-dragging'));
        });

        zone.addEventListener('drop', (event) => {
            event.preventDefault();
            const file = event.dataTransfer && event.dataTransfer.files ? event.dataTransfer.files[0] : null;
            if (!setInputFile(input, file)) {
                updateStatus(input, 'That file type is not supported here.');
            }
        });
    });

    document.addEventListener('paste', (event) => {
        const activeForm = document.activeElement ? document.activeElement.closest('form') : null;
        const candidateInputs = activeForm
            ? inputs.filter((input) => input.form === activeForm)
            : inputs.filter((input) => input.offsetParent !== null);
        const input = candidateInputs.find((candidate) => !candidate.disabled);
        if (!input) return;

        const file = Array.from(event.clipboardData ? event.clipboardData.files : []).find((item) => item.type.startsWith('image/'));
        if (file && setInputFile(input, file)) {
            event.preventDefault();
            updateStatus(input, `Pasted: ${file.name || 'clipboard image'}`);
        }
    });
})();
