export function fileProblem(file) {
    if (/\.(heic|heif)$/i.test(file.name) || /image\/hei[cf]/i.test(file.type)) {
        return 'HEIC photos are not supported. Export this photo as JPEG and choose it again.';
    }
    if (!file.size) return 'This file is empty. Choose another file.';
    if (file.size > 10 * 1024 * 1024) return 'This file exceeds 10 MB. Choose a smaller file.';
    if (!/\.(jpe?g|png|pdf)$/i.test(file.name) || (file.type && !['image/jpeg', 'image/png', 'application/pdf'].includes(file.type))) {
        return 'Choose a JPEG, PNG or PDF file.';
    }
    return null;
}

export function uploadError(status, body) {
    if (status === 422) return Object.values(body?.errors ?? {}).flat().join(' ') || 'The file was rejected. Check the file type, size and tag.';
    if (status === 401 || status === 419) return 'Your session expired. Sign in again, then check the job before uploading.';
    if (status === 403) return 'Upload access changed. Refresh the job to check its assignment and status.';
    if (status === 409) return 'This upload reference has changed or its saved media was removed. Refresh the job before uploading again.';
    if (status === 413) return 'The server rejected the file size. Choose a smaller file.';
    if (status === 429) return 'Too many uploads. Wait a minute, then retry.';
    return 'Upload not confirmed. Check your connection and retry here using the same selected file.';
}

export function initializeUploads(scope = document) {
    scope.querySelectorAll('[data-upload-picker]').forEach((picker) => {
        const form = picker.closest('form');
        const input = picker.querySelector('input[type="file"]');
        const list = picker.querySelector('[data-file-list]');
        const summary = picker.querySelector('[data-upload-status]');
        const max = Number(picker.dataset.maxFiles);
        const asynchronous = form.hasAttribute('data-async-media') && typeof XMLHttpRequest !== 'undefined' && !!crypto.randomUUID;
        const uploadGroup = asynchronous ? crypto.randomUUID() : null;
        let items = [];
        let busy = false;
        const buttons = [...form.querySelectorAll('button[type="submit"]')];
        const tag = form.querySelector('[name="tag"]');

        if (asynchronous) {
            form.dataset.asyncReady = 'true';
            input.required = false;
        }
        const revoke = (item) => { if (item.preview) URL.revokeObjectURL(item.preview); };
        const nativeSelection = () => {
            const transfer = new DataTransfer();
            items.forEach((item) => transfer.items.add(item.file));
            input.files = transfer.files;
        };
        const render = () => {
            list.replaceChildren();
            items.forEach((item) => {
                const row = document.createElement('div');
                row.className = 'flex flex-wrap items-start gap-3 rounded-md border-2 border-ink bg-white p-3';
                if (item.preview) {
                    const image = document.createElement('img');
                    image.src = item.preview;
                    image.alt = `Selected image: ${item.file.name}`;
                    image.className = 'size-16 shrink-0 rounded border border-stone-300 object-contain';
                    image.addEventListener('error', () => { image.hidden = true; });
                    row.append(image);
                }
                const details = document.createElement('div');
                details.className = 'min-w-0 flex-1';
                const name = document.createElement('p');
                name.className = 'break-all text-sm font-bold';
                name.textContent = item.file.name;
                const size = document.createElement('p');
                size.className = 'text-xs text-stone-600';
                const fileSize = item.file.size < 1024 * 1024 ? `${(item.file.size / 1024).toFixed(1)} KB` : `${(item.file.size / 1024 / 1024).toFixed(2)} MB`;
                size.textContent = `${fileSize}${item.tag ? ` · ${item.tag}` : ''}`;
                const status = document.createElement('p');
                status.className = 'mt-2 break-words text-sm';
                status.textContent = item.message;
                item.statusElement = status;
                details.append(name, size, status);
                if (asynchronous) {
                    const progress = document.createElement('progress');
                    progress.max = 100;
                    progress.value = item.progress ?? 0;
                    progress.setAttribute('aria-label', `Upload progress for ${item.file.name}`);
                    progress.className = 'mt-2 h-2 w-full accent-orange-600';
                    item.progressElement = progress;
                    details.append(progress);
                }
                row.append(details);
                if (!busy && item.state !== 'saved' && item.state !== 'uncertain' && (asynchronous || typeof DataTransfer !== 'undefined')) {
                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'min-h-11 shrink-0 px-2 text-sm font-bold underline';
                    remove.textContent = 'Remove';
                    remove.setAttribute('aria-label', `Remove ${item.file.name}`);
                    remove.addEventListener('click', () => {
                        revoke(item);
                        items = items.filter((candidate) => candidate !== item);
                        if (!asynchronous) nativeSelection();
                        summary.textContent = `${items.length} file(s) in this selection${asynchronous ? `; ${items.filter((candidate) => candidate.state === 'saved').length} confirmed saved` : ''}.`;
                        render();
                        input.focus();
                    });
                    row.append(remove);
                }
                list.append(row);
            });
        };
        input.addEventListener('change', () => {
            const selected = Array.from(input.files);
            if (!asynchronous) { items.forEach(revoke); items = []; }
            if (items.length + selected.length > max) {
                summary.textContent = `Choose at most ${max} files. Remove a file or refresh after completed uploads to start a new selection.`;
                if (asynchronous) input.value = '';
                else input.setCustomValidity(`Choose at most ${max} files.`);
                return;
            }
            input.setCustomValidity('');
            for (const file of selected) {
                const problem = fileProblem(file);
                items.push({file, id: asynchronous ? crypto.randomUUID() : null, state: problem ? 'invalid' : 'ready', message: problem || 'Ready to upload.', preview: !problem && ['image/jpeg', 'image/png'].includes(file.type) ? URL.createObjectURL(file) : null});
            }
            if (asynchronous) input.value = '';
            summary.textContent = 'Files remain on this device until you submit. Server validation also checks each file.';
            render();
        });

        const send = (item) => new Promise((resolve) => {
            const data = new FormData(form);
            data.delete(input.name);
            data.set('tag', item.tag);
            data.set('upload_id', item.id);
            data.set('upload_group', uploadGroup);
            data.append(input.name, item.file);
            const xhr = new XMLHttpRequest();
            xhr.open('POST', form.action);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.timeout = 120000;
            xhr.upload.onprogress = (event) => {
                if (!event.lengthComputable) return;
                item.progress = Math.round(event.loaded / event.total * 100);
                item.progressElement.value = item.progress;
                item.statusElement.textContent = item.progress === 100 ? 'Sent. Waiting for the server to confirm…' : `Sending ${item.progress}%…`;
            };
            const fail = (status = 0, body = null) => {
                item.state = [401, 403, 409, 419, 422, 413].includes(status) ? 'rejected' : 'uncertain';
                item.message = uploadError(status, body);
                resolve();
            };
            xhr.onload = () => {
                let body;
                try { body = JSON.parse(xhr.responseText); } catch { fail(xhr.status); return; }
                if (xhr.status === 200 && Array.isArray(body.media_ids) && body.media_ids.length === 1) {
                    item.state = 'saved'; item.message = 'Saved privately.'; item.progress = 100;
                    resolve();
                } else fail(xhr.status, body);
            };
            xhr.onerror = () => fail();
            xhr.ontimeout = () => fail();
            xhr.onabort = () => fail();
            xhr.send(data);
        });

        form.addEventListener('submit', async (event) => {
            if (!asynchronous) {
                const problem = items.find((item) => item.state === 'invalid');
                if (problem) { event.preventDefault(); summary.textContent = problem.message; input.focus(); }
                return;
            }
            event.preventDefault();
            if (busy) return;
            const remaining = items.filter((item) => ['ready', 'uncertain'].includes(item.state));
            if (!remaining.length) { summary.textContent = 'Choose a valid file to upload, or remove rejected files and choose replacements.'; return; }
            busy = true;
            form.setAttribute('aria-busy', 'true');
            input.disabled = true;
            buttons.forEach((button) => { button.disabled = true; });
            remaining.forEach((item) => { item.tag ??= tag.value; });
            tag.disabled = true;
            summary.textContent = 'Uploading files one at a time. Keep this page open until each result is confirmed.';
            render();
            try {
                for (const item of remaining) {
                    item.state = 'sending';
                    try { await send(item); } catch { item.state = 'uncertain'; item.message = uploadError(0); }
                    render();
                    if (item.state === 'uncertain') break;
                }
            } finally {
                busy = false;
                input.disabled = false;
                tag.disabled = false;
                buttons.forEach((button) => { button.disabled = false; });
                form.removeAttribute('aria-busy');
                const saved = items.filter((item) => item.state === 'saved').length;
                summary.textContent = `${saved} of ${items.length} files confirmed saved. ${items.some((item) => item.state === 'uncertain') ? 'Use Upload / retry remaining to safely retry unconfirmed files with their original tag.' : 'Saved files will not be sent again. Remove rejected files before choosing replacements.'}`;
                form.querySelector('[data-refresh-media]').hidden = saved === 0;
                render();
            }
        }, {capture: true});
        window.addEventListener('beforeunload', (event) => {
            if (busy || (asynchronous && items.some((item) => ['ready', 'uncertain'].includes(item.state)))) {
                event.preventDefault(); event.returnValue = '';
            }
        });
        window.addEventListener('pagehide', (event) => {
            if (!event.persisted) items.forEach(revoke);
        });
    });
}
