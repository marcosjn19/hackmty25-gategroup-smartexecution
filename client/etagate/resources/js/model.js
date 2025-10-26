// resources/js/model.js

function modelCard(opts = {}) {
    const id = opts.id || (self.crypto?.randomUUID?.() || String(Date.now()));
    const uploadUrl = opts.uploadUrl || '';
    const maxBytes = 10 * 1024 * 1024; // 10MB
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';

    return {
        id,
        open: false,
        positive: true,
        isDragging: false,
        queue: [], // [{file, status, progress, error}]

        init() {
            window.addEventListener('card:open', (ev) => {
                if (ev.detail !== this.id) this.open = false;
            });
        },
        openToggle() {
            window.dispatchEvent(new CustomEvent('card:open', { detail: this.id }));
            this.open = !this.open;
        },

        // Helpers
        bytesHuman(n) { return (n / 1024 / 1024).toFixed(1) + ' MB'; },
        summaryBytes() {
            return this.queue.filter(i => i.status !== 'error')
                .reduce((a, i) => a + (i.file?.size || 0), 0);
        },
        overall() {
            const valid = this.queue.filter(i => i.status !== 'error');
            if (!valid.length) return 0;
            const sum = valid.reduce((a, i) => a + (i.progress || 0), 0);
            return Math.round(sum / valid.length);
        },
        clear() {
            this.queue = [];
            if (this.$refs?.fileInput) this.$refs.fileInput.value = null;
        },

        // Files
        add(list) {
            const arr = [...list].map(f => ({ file: f, status: 'queued', progress: 0, error: '' }));
            arr.forEach(i => {
                if (i.file.size > maxBytes) {
                    i.status = 'error';
                    i.error = 'Max 10MB';
                }
            });
            this.queue = arr;
        },

        // Uploads
        async uploadAll(e) {
            if (e?.preventDefault) e.preventDefault();
            if (!uploadUrl) return;

            for (const item of this.queue) {
                if (item.status === 'error') continue;
                console.log('[POST]', uploadUrl, { name: item.file.name, size: item.file.size, type: this.positive ? 'positive' : 'negative' });
                await this.uploadOne(item, uploadUrl, token);
            }

            if (this.queue.every(i => i.status === 'done' || i.status === 'error')) {
                this.clear();
                this.open = false;
            }
        },

        uploadOne(item, url, token) {
            return new Promise((resolve) => {
                item.status = 'uploading';
                item.progress = 0;

                const xhr = new XMLHttpRequest();
                xhr.open('POST', url, true);
                // xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                // if (token) xhr.setRequestHeader('X-CSRF-TOKEN', token);
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.responseType = 'json';

                xhr.upload.onprogress = (e) => {
                    if (e.lengthComputable) item.progress = Math.round((e.loaded * 100) / e.total);
                };

                xhr.onload = () => {
                    const ok = xhr.status >= 200 && xhr.status < 300;
                    if (ok && (xhr.response?.ok ?? true)) {
                        item.status = 'done';
                        item.progress = 100;
                    } else {
                        item.status = 'error';
                        let msg = 'HTTP ' + xhr.status;
                        try {
                            const resp = xhr.response ?? JSON.parse(xhr.responseText);
                            if (resp?.errors) {
                                const k = Object.keys(resp.errors)[0];
                                msg = resp.errors[k]?.[0] || resp.message || msg;
                            } else {
                                msg = resp?.error || resp?.message || msg;
                            }
                        } catch { }
                        item.error = msg;
                    }
                    resolve();
                };

                xhr.onerror = () => { item.status = 'error'; item.error = 'Network error'; resolve(); };

                // IMPORTANTE: el backend espera el CAMPO 'file'
                const fd = new FormData();
                fd.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
                fd.append('type', this.positive ? 'positive' : 'negative');
                fd.append('image', item.file, item.file.name);
                xhr.send(fd);
            });
        },
    };
}

window.modelCard = modelCard;
export default modelCard;
