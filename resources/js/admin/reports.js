import { api, notify, fmtDateTime } from './api.js';

export function adminReports() {
    return {
        reports: [],
        meta: { current_page: 1, last_page: 1, total: 0 },
        counts: { pending: 0, resolved: 0, dismissed: 0, total: 0 },
        loading: true,
        error: '',
        status: 'pending',
        q: '',
        page: 1,
        busyId: null,
        activeReport: null,
        adminNotes: '',
        updateStatusValue: 'resolved',
        searchTimeout: null,

        fmt: fmtDateTime,

        async init() {
            await this.loadReports();
        },

        async loadReports() {
            this.loading = true;
            this.error = '';
            try {
                const params = new URLSearchParams();
                if (this.status) params.append('status', this.status);
                if (this.q.trim()) params.append('q', this.q.trim());
                params.append('page', this.page);

                const res = await api.get('/api/admin/reports?' + params.toString());
                this.reports = res.data || [];
                this.meta = res.meta || { current_page: 1, last_page: 1, total: 0 };
                this.counts = res.counts || { pending: 0, resolved: 0, dismissed: 0, total: 0 };
            } catch (e) {
                this.error = e.response?.data?.message || 'Failed to load place feedback reports';
            } finally {
                this.loading = false;
            }
        },

        setStatus(s) {
            this.status = s;
            this.page = 1;
            this.loadReports();
        },

        onSearch() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.page = 1;
                this.loadReports();
            }, 300);
        },

        async setPage(p) {
            if (p < 1 || p > this.meta.last_page) return;
            this.page = p;
            await this.loadReports();
        },

        openActionModal(report, nextStatus = 'resolved') {
            this.activeReport = report;
            this.updateStatusValue = nextStatus;
            this.adminNotes = report.admin_notes || '';
        },

        closeActionModal() {
            this.activeReport = null;
            this.adminNotes = '';
        },

        async submitStatusUpdate() {
            if (!this.activeReport) return;
            this.busyId = this.activeReport.id;
            try {
                const res = await api.patch(`/api/admin/reports/${this.activeReport.id}/status`, {
                    status: this.updateStatusValue,
                    admin_notes: this.adminNotes.trim() || null,
                });
                notify(res.data?.message || 'Report status updated successfully.');
                this.closeActionModal();
                await this.loadReports();
            } catch (e) {
                notify(e.response?.data?.message || 'Failed to update report status.', 'error');
            } finally {
                this.busyId = null;
            }
        },

        async quickUpdate(report, nextStatus) {
            this.busyId = report.id;
            try {
                const res = await api.patch(`/api/admin/reports/${report.id}/status`, {
                    status: nextStatus,
                });
                notify(res.data?.message || `Report marked as ${nextStatus}.`);
                await this.loadReports();
            } catch (e) {
                notify(e.response?.data?.message || 'Failed to update status.', 'error');
            } finally {
                this.busyId = null;
            }
        },

        async deleteReport(report) {
            if (!confirm(`Are you sure you want to permanently delete report #${report.id}?`)) {
                return;
            }
            this.busyId = report.id;
            try {
                const res = await api.delete(`/api/admin/reports/${report.id}`);
                notify(res.data?.message || 'Report deleted successfully.');
                await this.loadReports();
            } catch (e) {
                notify(e.response?.data?.message || 'Failed to delete report.', 'error');
            } finally {
                this.busyId = null;
            }
        },

        issueLabel(type) {
            const map = {
                wrong_info: 'Wrong Info',
                closed_permanently: 'Closed Permanently',
                wrong_location: 'Wrong Location',
                duplicate: 'Duplicate Place',
                inappropriate: 'Inappropriate Content',
                other: 'Other Feedback',
            };
            return map[type] || type || 'Report';
        },

        badgeClass(status) {
            switch (status) {
                case 'pending':
                    return 'bg-amber-500/15 text-amber-300 border-amber-500/30';
                case 'resolved':
                    return 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30';
                case 'dismissed':
                    return 'bg-zinc-700/50 text-zinc-400 border-zinc-600/40';
                default:
                    return 'bg-zinc-800 text-zinc-300 border-zinc-700';
            }
        },
    };
}
