// Componente Alpine para el listado y modal de detalles
export function processList() {
    return {
        open: false,
        loading: false,
        error: '',
        current: null,     // { id, name }
        detail: null,      // GET /api/processes/{id}
        insights: null,    // GET /api/processes/{id}/insights

        init() {},

        async openFor(proc) {
            this.current = proc;
            this.open = true;
            this.loading = true;
            this.error = '';
            this.detail = null;
            this.insights = null;
            try {
                const [dRes, iRes] = await Promise.all([
                    fetch(`/api/processes/${proc.id}`, { headers: { 'Accept': 'application/json' } }),
                    fetch(`/api/processes/${proc.id}/insights`, { headers: { 'Accept': 'application/json' } }),
                ]);

                let d = null, i = null;
                try { d = await dRes.json(); } catch { d = null; }
                try { i = await iRes.json(); } catch { i = null; }

                // normaliza detalle para modal (con campos frecuentes)
                if (d && typeof d === 'object') {
                    this.detail = {
                        id: d.id,
                        name: d.name,
                        status: d.status,
                        total_images: d.total_images,
                        avg_latency_ms: d.avg_latency_ms,
                        avg_confidence: d.avg_confidence,
                        p95_latency_ms: d.p95_latency_ms,
                        max_latency_ms: d.max_latency_ms,
                        last_request: d.last_request || null,
                        models: (d.models || []).sort((a,b) => (a.order_index||0) - (b.order_index||0)),
                    };
                } else {
                    this.error = `HTTP ${dRes.status}`;
                }

                this.insights = (i && typeof i === 'object') ? i : null;
            } catch (e) {
                this.error = (e && e.message) ? e.message : 'Load error';
            } finally {
                this.loading = false;
            }
        },

        close() {
            this.open = false;
        },

        fmt(s) {
            if (!s) return '—';
            try {
                const d = new Date(s);
                return isNaN(d.getTime()) ? s : d.toLocaleString();
            } catch { return s; }
        }
    };
}

// Registra en window para Alpine (si no lo haces en app.js)
window.processList = processList;
