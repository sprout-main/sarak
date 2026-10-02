import { api, notify, fmtDateTime, summarizePayload } from './api.js';

export function adminImport() {
    return {
        sources: [
            { id: 'overpass', label: 'Overpass (OSM)', hint: 'Raw Overpass QL query' },
            { id: 'official_list', label: 'Official list', hint: 'CSV rows with headers' },
            { id: 'nominatim', label: 'Nominatim', hint: 'Search terms, one per line' },
            { id: 'manual', label: 'Manual (CSV)', hint: 'category,name,lat,lng[,address,phone]' },
        ],
        source: 'overpass',
        payload: '',
        running: false,
        runId: null,
        pollTimer: null,
        pollTicks: 0,
        candidates: [],
        seenIds: new Set(),
        history: [],
        loadingHistory: true,
        historyError: '',

        fmt: fmtDateTime,

        async init() {
            await this.loadHistory();
        },

        onFile(event) {
            const file = event.target.files && event.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = () => {
                this.payload = reader.result;
            };
            reader.readAsText(file);
        },

        async run() {
            if (!this.payload.trim()) {
                notify('Provide a payload', 'error');
                return;
            }
            if (this.running) return;
            this.running = true;
            this.runId = null;
            this.candidates = [];
            this.seenIds = new Set();
            this.pollTicks = 0;
            try {
                const r = await api.post('/api/admin/import', { source: this.source, payload: this.payload });
                this.runId = r.data?.run_id || null;
                notify(r.data?.message || 'Import queued');
                this.startPolling();
            } catch (e) {
                notify(e.response?.data?.message || 'Import failed', 'error');
            } finally {
                this.running = false;
            }
        },

        startPolling() {
            this.stopPolling();
            this.pollTimer = setInterval(() => this.tick(), 5000);
        },

        stopPolling() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        },

        async tick() {
            this.pollTicks++;
            try {
                const r = await api.get('/api/admin/queue?status=pending');
                const list = r.data || [];
                list.forEach((item) => {
                    if (!this.seenIds.has(item.id)) {
                        this.seenIds.add(item.id);
                        this.candidates.unshift(item);
                    }
                });
                if (this.candidates.length) {
                    this.stopPolling();
                    notify(this.candidates.length + ' new candidate' + (this.candidates.length === 1 ? '' : 's') + ' parked in the queue');
                }
            } catch (e) {
            }
            if (this.pollTicks >= 12) this.stopPolling();
        },

        async loadHistory() {
            this.loadingHistory = true;
            this.historyError = '';
            try {
                const r = await api.get('/api/admin/imports');
                this.history = r.data || [];
            } catch (e) {
                if (e.response && e.response.status === 404) {
                    this.historyError = 'Import history endpoint not available yet';
                } else {
                    this.historyError = e.response?.data?.message || 'Failed to load history';
                }
            } finally {
                this.loadingHistory = false;
            }
        },

        summaryRows(run) {
            return summarizePayload(run.batch_summary, 8);
        },

        runStatusBadge(status) {
            const map = { running: 'badge-amber', done: 'badge-green', failed: 'badge-red' };
            return map[status] || 'badge-zinc';
        },

        srcBadge(source) {
            const map = {
                overpass: 'badge-sky',
                nominatim: 'badge-violet',
                official_list: 'badge-amber',
                manual: 'badge-zinc',
                google_ref: 'badge-sky',
                community: 'badge-green',
            };
            return map[source] || 'badge-zinc';
        },
    };
}