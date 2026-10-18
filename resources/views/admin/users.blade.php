@extends('admin.layout')

@section('title', 'Accounts')
@section('subtitle', 'Manage registered users, permissions, and administrative access')

@section('content')
<div x-data="adminUsers()" x-init="init()" class="mx-auto max-w-7xl space-y-4">
    <!-- Header / Filter Panel -->
    <div class="panel">
        <div class="flex flex-wrap items-center gap-3 px-4 py-3">
            <div class="relative min-w-[220px] max-w-sm flex-1">
                <input
                    type="search"
                    x-model="q"
                    x-on:input="onSearch()"
                    class="input"
                    placeholder="Search username or email address"
                />
            </div>
            <div class="w-36 shrink-0">
                <select class="select !w-full" x-model="role" x-on:change="setPage(1)">
                    <option value="">All roles</option>
                    <option value="admin">Admin</option>
                    <option value="user">User</option>
                </select>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <span class="mono text-xs text-zinc-400 bg-zinc-800/60 border border-white/5 rounded-lg px-2.5 py-1.5 whitespace-nowrap" x-text="meta.total + ' registered ' + (meta.total === 1 ? 'account' : 'accounts')"></span>
                <button type="button" class="btn btn-ghost btn-sm whitespace-nowrap" x-on:click="loadUsers()">
                    <i class="ph ph-arrows-clockwise"></i> Refresh
                </button>
            </div>
        </div>
    </div>

    <!-- Error State -->
    <template x-if="error !== ''">
        <div class="panel px-4 py-4 text-sm" style="background:rgba(251,113,133,0.06);border-color:rgba(251,113,133,0.3);color:var(--rose);" x-text="error"></div>
    </template>

    <!-- Users Table Panel -->
    <div class="panel" x-show="error === ''">
        <div class="table-wrap">
            <table class="dense-table">
                <thead>
                    <tr>
                        <th style="width: 4rem;">ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Karma</th>
                        <th>Joined</th>
                        <th style="min-width: 140px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Loading skeleton -->
                    <tr x-show="loading">
                        <td colspan="7" class="px-0 py-1">
                            <div class="flex flex-col gap-2 p-4">
                                <div class="skel h-4 w-full"></div>
                                <div class="skel h-4 w-3/4"></div>
                                <div class="skel h-4 w-1/2"></div>
                            </div>
                        </td>
                    </tr>

                    <!-- Empty state -->
                    <tr x-show="!loading && !users.length">
                        <td colspan="7" class="px-4 py-12 text-center text-sm text-zinc-500">
                            No accounts match your criteria.
                        </td>
                    </tr>

                    <!-- User rows -->
                    <template x-for="user in users" :key="user.id">
                        <tr x-bind:class="busyId === user.id ? 'opacity-50 pointer-events-none' : ''">
                            <td class="mono text-zinc-500" x-text="'#' + user.id"></td>
                            <td>
                                <div class="flex items-center gap-2.5">
                                    <template x-if="user.avatar_url">
                                        <img :src="user.avatar_url" :alt="user.name" class="h-7 w-7 rounded-full object-cover ring-1 ring-white/10" />
                                    </template>
                                    <template x-if="!user.avatar_url">
                                        <span class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold" style="background-color:var(--accent-soft);color:var(--success);" x-text="user.name.charAt(0).toUpperCase()"></span>
                                    </template>
                                    <div>
                                        <div class="font-medium text-zinc-100 flex items-center gap-1.5">
                                            <span x-text="user.name"></span>
                                            <template x-if="user.is_head_admin">
                                                <span class="badge font-mono uppercase text-[9px] bg-amber-500/20 text-amber-300 border border-amber-500/30">Head Admin</span>
                                            </template>
                                            <template x-if="user.is_current_user">
                                                <span class="badge badge-green text-[9px] font-mono uppercase">You</span>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="mono text-xs text-zinc-300" x-text="user.email"></span>
                            </td>
                            <td>
                                <span
                                    class="badge font-mono uppercase text-[10px]"
                                    :class="user.role === 'admin' ? 'badge-green' : 'badge-zinc'"
                                    x-text="user.role"
                                ></span>
                            </td>
                            <td>
                                <div class="flex items-center gap-1.5">
                                    <i class="ph ph-sparkle text-amber-400 text-xs"></i>
                                    <span class="mono text-xs text-zinc-300" x-text="user.karma_points"></span>
                                </div>
                            </td>
                            <td>
                                <span class="mono text-xs text-zinc-500" x-text="fmt(user.created_at)"></span>
                            </td>
                            <td style="text-align: right;">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button
                                        type="button"
                                        class="btn btn-xs btn-ghost disabled:opacity-30 disabled:pointer-events-none"
                                        :disabled="!user.can_edit_role"
                                        x-on:click="openRoleModal(user)"
                                        :title="user.is_head_admin ? 'Head administrator role cannot be altered' : (!user.can_edit_role ? 'Only the head administrator can alter user roles' : 'Change user permissions/role')"
                                    >
                                        <i class="ph ph-shield-check"></i> Role
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-xs btn-ghost !text-rose-400 hover:!text-rose-300 hover:!bg-rose-500/10 border-rose-500/20 disabled:opacity-30 disabled:pointer-events-none"
                                        :disabled="!user.can_delete"
                                        x-on:click="deleteUser(user)"
                                        :title="user.is_head_admin ? 'Head administrator account cannot be deleted' : (user.is_current_user ? 'Cannot delete self' : 'Permanently delete account')"
                                    >
                                        <i class="ph ph-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        <div class="flex items-center justify-between border-t px-4 py-3 text-xs text-zinc-400" style="border-color:var(--line);">
            <div>
                Showing page <span class="mono font-semibold text-zinc-200" x-text="meta.current_page"></span> of <span class="mono font-semibold text-zinc-200" x-text="meta.last_page"></span>
            </div>
            <div class="flex items-center gap-1.5">
                <button
                    type="button"
                    class="btn btn-xs btn-ghost"
                    :disabled="meta.current_page <= 1"
                    x-on:click="setPage(meta.current_page - 1)"
                >
                    <i class="ph ph-caret-left"></i> Previous
                </button>
                <button
                    type="button"
                    class="btn btn-xs btn-ghost"
                    :disabled="meta.current_page >= meta.last_page"
                    x-on:click="setPage(meta.current_page + 1)"
                >
                    Next <i class="ph ph-caret-right"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Role Edit Modal -->
    <div
        x-show="roleModalUser !== null"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
    >
        <div class="modal-backdrop" x-on:click="closeRoleModal()"></div>
        <div class="modal-panel w-full max-w-sm panel" style="background:var(--surface);">
            <div class="modal-head">
                <div class="text-sm font-semibold text-white">Change Account Role</div>
                <button type="button" class="btn btn-ghost btn-xs" x-on:click="closeRoleModal()">
                    <i class="ph ph-x"></i>
                </button>
            </div>
            <div class="modal-body space-y-4">
                <div class="flex items-center gap-2.5 p-2 rounded-lg bg-white/5">
                    <template x-if="roleModalUser && roleModalUser.avatar_url">
                        <img :src="roleModalUser.avatar_url" class="h-8 w-8 rounded-full object-cover" />
                    </template>
                    <template x-if="roleModalUser && !roleModalUser.avatar_url">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold" style="background-color:var(--accent-soft);color:var(--success);" x-text="roleModalUser ? roleModalUser.name.charAt(0).toUpperCase() : ''"></span>
                    </template>
                    <div class="min-w-0">
                        <div class="font-medium text-xs text-zinc-100 truncate" x-text="roleModalUser ? roleModalUser.name : ''"></div>
                        <div class="mono text-[11px] text-zinc-400 truncate" x-text="roleModalUser ? roleModalUser.email : ''"></div>
                    </div>
                </div>

                <div>
                    <label class="field-label">Assigned Role</label>
                    <select class="select" x-model="selectedRole">
                        <option value="user">User (Standard Contributor)</option>
                        <option value="admin">Admin (Full System Access)</option>
                    </select>
                    <p class="text-[11px] text-zinc-500 mt-1.5">
                        Administrators have unrestricted access to moderate submissions, edit all places, manage boundaries, and configure system accounts.
                    </p>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t" style="border-color:var(--line);">
                    <button type="button" class="btn btn-ghost btn-sm" x-on:click="closeRoleModal()">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-accent btn-sm" x-on:click="saveRole()" :disabled="busyId !== null">
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
