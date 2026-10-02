import { api, notify, fmtDateTime } from './api.js';

export const BANGUD_OVERPASS_QUERY =
    '[out:json][timeout:90];\n' +
    'area["name"="Bangued"]["boundary"="administrative"]->.a;\n' +
    'relation["boundary"="administrative"]["name"](area.a);\n' +
    'out geom;';

export function adminBoundaries() {
    return {
        items: [],
        loading: true,
        error: '',
        importing: false,
        query: BANGUD_OVERPASS_QUERY,

        fmt: fmtDateTime,

        async init() {
            await this.reload();
        },

        async reload() {
            this.loading = true;
            this.error = '';
            try {
                const r = await api.get('/api/admin/boundaries');
                this.items = r.data || [];
            } catch (e) {
                if (e.response && e.response.status === 404) {
                    const br = await api.get('/api/barangays').catch(() => ({ data: [] }));
                    this.items = (br.data || []).map((b) => ({
                        id: b.id,
                        name: b.name,
                        type: b.type,
                        psgc: b.psgc,
                        boundary_source: '',
                        imported_at: null,
                        place_count: b.place_count || 0,
                    }));
                } else {
                    this.error = e.response?.data?.message || 'Failed to load boundaries';
                }
            } finally {
                this.loading = false;
            }
        },

        async reimport() {
            if (this.importing) return;
            this.importing = true;
            try {
                const r = await api.post('/api/admin/import', { source: 'overpass', payload: this.query });
                notify(r.data?.message || 'Barangay import queued');
            } catch (e) {
                notify(e.response?.data?.message || 'Import failed', 'error');
            } finally {
                this.importing = false;
            }
        },

        typeBadge(type) {
            return type === 'municipality' ? 'badge-sky' : 'badge-zinc';
        },
    };
}