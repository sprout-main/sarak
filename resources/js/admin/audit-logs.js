import { api, notify, fmtDateTime } from './api.js';

export function adminAuditLogs() {
    return {
        logs: [],
        meta: { current_page: 1, last_page: 1, total: 0 },
        filters: { models: [], actions: [] },
        loading: true,
        error: '',
        q: '',
        model: '',
        action: '',
        page: 1,
        activeLog: null,
        searchTimeout: null,
        copied: false,

        fmt: fmtDateTime,

        async init() {
            await this.loadLogs();
        },

        async loadLogs() {
            this.loading = true;
            this.error = '';
            try {
                const params = new URLSearchParams();
                if (this.q.trim()) params.append('q', this.q.trim());
                if (this.model) params.append('model', this.model);
                if (this.action) params.append('action', this.action);
                params.append('page', this.page);

                const res = await api.get('/api/admin/audit-logs?' + params.toString());
                this.logs = res.data || [];
                this.meta = res.meta || { current_page: 1, last_page: 1, total: 0 };
                if (res.filters) {
                    this.filters = res.filters;
                }
            } catch (e) {
                this.error = e.response?.data?.message || 'Failed to load audit logs';
            } finally {
                this.loading = false;
            }
        },

        onSearch() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.page = 1;
                this.loadLogs();
            }, 300);
        },

        async setPage(p) {
            if (p < 1 || p > this.meta.last_page) return;
            this.page = p;
            await this.loadLogs();
        },

        onFilterChange() {
            this.page = 1;
            this.loadLogs();
        },

        resetFilters() {
            this.q = '';
            this.model = '';
            this.action = '';
            this.page = 1;
            this.loadLogs();
        },

        openLogModal(log) {
            this.activeLog = log;
            this.copied = false;
        },

        closeLogModal() {
            this.activeLog = null;
            this.copied = false;
        },

        async copyJson(data) {
            try {
                await navigator.clipboard.writeText(JSON.stringify(data, null, 2));
                this.copied = true;
                notify('Copied JSON payload to clipboard');
                setTimeout(() => {
                    this.copied = false;
                }, 2000);
            } catch {
                notify('Could not copy to clipboard', 'error');
            }
        },

        actionBadge(action) {
            switch (action) {
                case 'create':
                    return 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30';
                case 'update':
                case 'move':
                case 'mass_move':
                    return 'bg-amber-500/15 text-amber-300 border border-amber-500/30';
                case 'delete':
                case 'reject':
                case 'discard':
                    return 'bg-rose-500/15 text-rose-300 border border-rose-500/30';
                case 'role_change':
                    return 'bg-purple-500/15 text-purple-300 border border-purple-500/30';
                case 'status_update':
                    return 'bg-sky-500/15 text-sky-300 border border-sky-500/30';
                case 'set_cover':
                case 'photo':
                    return 'bg-indigo-500/15 text-indigo-300 border border-indigo-500/30';
                default:
                    return 'bg-zinc-800 text-zinc-300 border border-white/10';
            }
        },

        modelBadge(model) {
            switch (model) {
                case 'place':
                    return 'bg-emerald-950/60 text-emerald-300 border border-emerald-500/20';
                case 'user':
                    return 'bg-violet-950/60 text-violet-300 border border-violet-500/20';
                case 'mod_queue':
                    return 'bg-amber-950/60 text-amber-300 border border-amber-500/20';
                case 'place_photo':
                    return 'bg-cyan-950/60 text-cyan-300 border border-cyan-500/20';
                case 'place_report':
                    return 'bg-orange-950/60 text-orange-300 border border-orange-500/20';
                default:
                    return 'bg-zinc-800/80 text-zinc-300 border border-zinc-700/60';
            }
        },

        formatSummary(diff) {
            if (!diff || typeof diff !== 'object') {
                return 'No diff payload';
            }
            if (diff.before && diff.after) {
                const keys = Object.keys(diff.after);
                if (keys.length === 1) {
                    return `Changed ${keys[0]}`;
                }
                return `Updated ${keys.length} fields (${keys.slice(0, 3).join(', ')}${keys.length > 3 ? '...' : ''})`;
            }
            if (diff.name) {
                return `Name: "${diff.name}"`;
            }
            if (diff.note) {
                return `Note: "${diff.note}"`;
            }
            const keys = Object.keys(diff);
            if (keys.length === 0) return 'Empty diff';
            return `${keys.slice(0, 3).map(k => `${k}: ${typeof diff[k] === 'object' ? '...' : diff[k]}`).join(', ')}${keys.length > 3 ? '...' : ''}`;
        },
    };
}
