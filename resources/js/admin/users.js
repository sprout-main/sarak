import { api, notify, fmtDateTime } from './api.js';

export function adminUsers() {
    return {
        users: [],
        meta: { current_page: 1, last_page: 1, total: 0 },
        loading: true,
        error: '',
        q: '',
        role: '',
        page: 1,
        busyId: null,
        roleModalUser: null,
        selectedRole: 'user',
        searchTimeout: null,

        fmt: fmtDateTime,

        async init() {
            await this.loadUsers();
        },

        async loadUsers() {
            this.loading = true;
            this.error = '';
            try {
                const params = new URLSearchParams();
                if (this.q.trim()) params.append('q', this.q.trim());
                if (this.role) params.append('role', this.role);
                params.append('page', this.page);

                const res = await api.get('/api/admin/users?' + params.toString());
                this.users = res.data || [];
                this.meta = res.meta || { current_page: 1, last_page: 1, total: 0 };
            } catch (e) {
                this.error = e.response?.data?.message || 'Failed to load user accounts';
            } finally {
                this.loading = false;
            }
        },

        onSearch() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.page = 1;
                this.loadUsers();
            }, 300);
        },

        async setPage(p) {
            if (p < 1 || p > this.meta.last_page) return;
            this.page = p;
            await this.loadUsers();
        },

        openRoleModal(user) {
            if (user.can_edit_role === false) {
                notify(user.is_head_admin ? 'The head administrator role cannot be altered.' : 'Only the head administrator can alter user roles.', 'error');
                return;
            }
            this.roleModalUser = user;
            this.selectedRole = user.role;
        },

        closeRoleModal() {
            this.roleModalUser = null;
        },

        async saveRole() {
            if (!this.roleModalUser) return;
            this.busyId = this.roleModalUser.id;
            try {
                const res = await api.patch(`/api/admin/users/${this.roleModalUser.id}/role`, {
                    role: this.selectedRole,
                });
                notify(res.data?.message || 'Role updated');
                this.closeRoleModal();
                await this.loadUsers();
            } catch (e) {
                notify(e.response?.data?.message || 'Failed to update role', 'error');
            } finally {
                this.busyId = null;
            }
        },

        async deleteUser(user) {
            if (user.is_current_user) {
                notify('You cannot delete your own account.', 'error');
                return;
            }
            if (user.is_head_admin) {
                notify('The head administrator account cannot be deleted.', 'error');
                return;
            }
            if (!confirm(`Are you sure you want to permanently delete the account "${user.name}" (${user.email})? This action cannot be undone.`)) {
                return;
            }
            this.busyId = user.id;
            try {
                const res = await api.delete(`/api/admin/users/${user.id}`);
                notify(res.data?.message || 'User account deleted');
                await this.loadUsers();
            } catch (e) {
                notify(e.response?.data?.message || 'Failed to delete user', 'error');
            } finally {
                this.busyId = null;
            }
        },
    };
}
