import { api } from './api.js';

function heatBg(count, max) {
    if (!count || count <= 0) return 'rgba(255, 255, 255, 0.03)';
    const t = Math.min(1, count / (max > 0 ? max : 1));
    return 'rgba(74, 222, 128, ' + (0.1 + t * 0.8).toFixed(3) + ')';
}

export function adminAnalytics() {
    return {
        coverage: null,
        pending: null,
        error: '',
        loading: true,
        maxAction: 1,

        async init() {
            await this.reload();
        },

        async reload() {
            this.loading = true;
            this.error = '';
            try {
                const [coverage, pending] = await Promise.all([
                    api.get('/api/admin/analytics/coverage'),
                    api.get('/api/admin/analytics/pending'),
                ]);
                this.coverage = coverage || {};
                this.pending = pending || {};
                const list = this.actions();
                this.maxAction = Math.max(1, ...list.map((a) => a.count));
            } catch (e) {
                this.error = e.response?.data?.message || 'Failed to load analytics';
            } finally {
                this.loading = false;
            }
        },

        barangays() {
            if (!this.coverage || !Array.isArray(this.coverage.matrix)) return [];
            const seen = new Map();
            (this.coverage.matrix || []).forEach((row) => {
                (row.barangays || []).forEach((b) => {
                    if (!seen.has(b.id)) seen.set(b.id, { id: b.id, name: b.name });
                });
            });
            return [...seen.values()];
        },

        maxCell() {
            let m = 1;
            if (!this.coverage || !Array.isArray(this.coverage.matrix)) return m;
            (this.coverage.matrix || []).forEach((row) => {
                (row.barangays || []).forEach((b) => {
                    if (b.count > m) m = b.count;
                });
            });
            return m;
        },

        countFor(categoryId, barangayId) {
            if (!this.coverage || !Array.isArray(this.coverage.matrix)) return 0;
            const row = this.coverage.matrix.find((r) => r.category && r.category.id == categoryId);
            if (!row) return 0;
            const b = (row.barangays || []).find((x) => x.id == barangayId);
            return b ? b.count : 0;
        },

        cellBg(count) {
            return heatBg(count, this.maxCell());
        },

        rowTotal(categoryId) {
            if (!this.coverage || !Array.isArray(this.coverage.totals)) return 0;
            const t = this.coverage.totals.find((x) => x.category_id == categoryId);
            return t ? t.approved : 0;
        },

        totals() {
            if (!this.coverage || !Array.isArray(this.coverage.totals)) return [];
            return this.coverage.totals;
        },

        approvedTotal() {
            let sum = 0;
            this.totals().forEach((t) => {
                sum += Number(t.approved || 0);
            });
            return sum;
        },

        categoryShare(categoryId) {
            const total = this.approvedTotal();
            if (!total) return '0%';
            const count = this.rowTotal(categoryId);
            return Math.round((count / total) * 100) + '%';
        },

        categoryLabel(id) {
            if (!this.coverage || !Array.isArray(this.coverage.matrix)) return '#' + id;
            const row = this.coverage.matrix.find((r) => r.category && r.category.id == id);
            return row ? row.category.label : '#' + id;
        },

        categoryColor(id) {
            if (!this.coverage || !Array.isArray(this.coverage.matrix)) return '#4ade80';
            const row = this.coverage.matrix.find((r) => r.category && r.category.id == id);
            return row && row.category && row.category.color ? row.category.color : '#4ade80';
        },

        categoryIcon(id) {
            if (!this.coverage || !Array.isArray(this.coverage.matrix)) return 'ph-map-pin';
            const row = this.coverage.matrix.find((r) => r.category && r.category.id == id);
            const key = row && row.category ? (row.category.icon_key || row.category.slug) : '';
            return typeof window.catIcon === 'function' ? window.catIcon(key) : 'ph-map-pin';
        },

        maxPending() {
            let m = 1;
            this.totals().forEach((t) => {
                if (t.pending > m) m = t.pending;
            });
            return m;
        },

        pendingBg(categoryId) {
            const t = this.totals().find((x) => x.category_id == categoryId);
            return heatBg(t ? t.pending : 0, this.maxPending());
        },

        emptySpots() {
            if (!this.coverage || !Array.isArray(this.coverage.empty_spots)) return [];
            return this.coverage.empty_spots.map((s) => ({
                category_id: s.category_id,
                barangay_id: s.barangay_id,
                barangay_name: s.barangay_name,
                category: this.categoryLabel(s.category_id),
                color: this.categoryColor(s.category_id),
            }));
        },

        actions() {
            const by = (this.pending && this.pending.by_action) || {};
            return ['create', 'update', 'move', 'photo', 'delete'].map((a) => ({ key: a, count: by[a] || 0 }));
        },

        pendingTotal() {
            return this.pending ? this.pending.total || 0 : 0;
        },

        busiestAction() {
            let best = null;
            for (const a of this.actions()) {
                if (!best || a.count > best.count) best = a;
            }
            return best || { key: 'none', count: 0 };
        },
    };
}