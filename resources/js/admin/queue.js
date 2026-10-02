import { api, notify, fmtDateTime, summarizePayload, diffRows } from './api.js';

export function adminQueue() {
    return {
        status: 'pending',
        counts: { pending: null, approved: null, rejected: null },
        items: [],
        loading: true,
        error: '',
        rejectId: null,
        rejectNote: '',
        mergeId: null,
        mergeTarget: '',
        busyId: null,
        openDiffId: null,

        fmt: fmtDateTime,
        catIcon(key) {
            return typeof window.catIcon === 'function' ? window.catIcon(key) : 'ph-map-pin';
        },

        async init() {
            await this.loadAll();
        },

        async loadAll() {
            await Promise.all([this.loadCounts(), this.loadItems()]);
        },

        async loadCounts() {
            const statuses = ['pending', 'approved', 'rejected'];
            const results = await Promise.all(
                statuses.map((s) => api.get('/api/admin/queue?status=' + s).catch(() => ({ data: [] })))
            );
            statuses.forEach((s, i) => {
                this.counts[s] = (results[i].data || []).length;
            });
        },

        async loadItems() {
            this.loading = true;
            this.error = '';
            try {
                const r = await api.get('/api/admin/queue?status=' + this.status);
                this.items = r.data || [];
            } catch (e) {
                this.error = e.response?.data?.message || 'Failed to load queue';
            } finally {
                this.loading = false;
            }
        },

        async setStatus(s) {
            this.status = s;
            await this.loadItems();
        },

        payloadRows(payload) {
            return summarizePayload(payload);
        },

        diffFor(item) {
            if (!item.diff) return [];
            return diffRows(item.diff.before, item.diff.after);
        },

        badgeClass(kind) {
            const map = {
                create: 'badge-green',
                update: 'badge-sky',
                move: 'badge-violet',
                photo: 'badge-amber',
                delete: 'badge-red',
                pending: 'badge-amber',
                approved: 'badge-green',
                rejected: 'badge-red',
            };
            return map[kind] || 'badge-zinc';
        },

        toggleDiff(item) {
            this.openDiffId = this.openDiffId === item.id ? null : item.id;
        },

        async approve(item) {
            if (this.busyId !== null || item.status !== 'pending') return;
            this.busyId = item.id;
            try {
                const r = await api.post('/api/admin/queue/' + item.id + '/approve');
                notify(r.data?.message || 'Approved');
                await this.loadAll();
            } catch (e) {
                notify(e.response?.data?.message || 'Approval failed', 'error');
            } finally {
                this.busyId = null;
            }
        },

        async deleteItem(item) {
            if (this.busyId !== null) return;
            if (!confirm(`Are you sure you want to permanently delete queue item #${item.id}? This cannot be undone.`)) {
                return;
            }
            this.busyId = item.id;
            try {
                const r = await api.delete('/api/admin/queue/' + item.id);
                notify(r.data?.message || 'Queue item deleted');
                await this.loadAll();
            } catch (e) {
                notify(e.response?.data?.message || 'Delete failed', 'error');
            } finally {
                this.busyId = null;
            }
        },

        openReject(item) {
            if (item.status !== 'pending') return;
            this.rejectId = item.id;
            this.rejectNote = '';
            this.$nextTick(() => {
                const el = this.$refs.rejectNote;
                if (el) el.focus();
            });
        },

        closeReject() {
            this.rejectId = null;
            this.rejectNote = '';
        },

        async confirmReject() {
            const note = String(this.rejectNote || '').trim();
            if (!note) {
                notify('A rejection note is required', 'error');
                return;
            }
            this.busyId = this.rejectId;
            try {
                const r = await api.post('/api/admin/queue/' + this.rejectId + '/reject', { note });
                notify(r.data?.message || 'Rejected');
                this.closeReject();
                await this.loadAll();
            } catch (e) {
                notify(e.response?.data?.message || 'Reject failed', 'error');
            } finally {
                this.busyId = null;
            }
        },

        openMerge(item) {
            if (item.status !== 'pending') return;
            this.mergeId = item.id;
            this.mergeTarget = '';
            this.$nextTick(() => {
                const el = this.$refs.mergeTarget;
                if (el) el.focus();
            });
        },

        closeMerge() {
            this.mergeId = null;
            this.mergeTarget = '';
        },

        async confirmMerge() {
            const into = parseInt(this.mergeTarget, 10);
            if (!into) {
                notify('Enter a target place id', 'error');
                return;
            }
            this.busyId = this.mergeId;
            try {
                const r = await api.post('/api/admin/queue/' + this.mergeId + '/merge', { into_place_id: into });
                notify('Merged into place ' + (r.data?.place_id ?? into));
                this.closeMerge();
                await this.loadAll();
            } catch (e) {
                notify(e.response?.data?.message || 'Merge failed', 'error');
            } finally {
                this.busyId = null;
            }
        },

        rowKey(event) {
            if (event.target.closest('input, textarea, select, button, a')) return;
            const tr = event.target.closest('tr[data-id]');
            if (!tr) return;
            const item = this.items.find((i) => i.id === Number(tr.getAttribute('data-id')));
            if (!item || item.status !== 'pending') return;
            const key = String(event.key || '').toLowerCase();
            if (key === 'a') {
                event.preventDefault();
                this.approve(item);
            } else if (key === 'r') {
                event.preventDefault();
                this.openReject(item);
            }
        },
    };
}