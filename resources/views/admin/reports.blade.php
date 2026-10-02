@extends('admin.layout')

@section('title', 'Feedback & Reports')
@section('subtitle', 'Review user reported inaccuracies, duplicate places, and permanent closures')

@section('content')
<div x-data="adminReports()" x-init="init()" class="mx-auto max-w-7xl space-y-4">
    <!-- Filter and Search Toolbar -->
    <div class="panel">
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex flex-wrap gap-1 rounded-full p-1" style="border:1px solid var(--line);">
                    <button type="button"
                        x-on:click="setStatus('pending')"
                        x-bind:class="status === 'pending' ? 'text-[#052e16]' : 'text-zinc-400 hover:text-zinc-200'"
                        x-bind:style="status === 'pending' ? 'background:#4ade80;' : ''"
                        class="rounded-full px-3.5 py-1 text-xs font-semibold transition-all">
                        Pending <span class="ml-1 font-mono" x-text="counts.pending"></span>
                    </button>
                    <button type="button"
                        x-on:click="setStatus('resolved')"
                        x-bind:class="status === 'resolved' ? 'text-[#052e16]' : 'text-zinc-400 hover:text-zinc-200'"
                        x-bind:style="status === 'resolved' ? 'background:#4ade80;' : ''"
                        class="rounded-full px-3.5 py-1 text-xs font-semibold transition-all">
                        Resolved <span class="ml-1 font-mono" x-text="counts.resolved"></span>
                    </button>
                    <button type="button"
                        x-on:click="setStatus('dismissed')"
                        x-bind:class="status === 'dismissed' ? 'text-[#052e16]' : 'text-zinc-400 hover:text-zinc-200'"
                        x-bind:style="status === 'dismissed' ? 'background:#4ade80;' : ''"
                        class="rounded-full px-3.5 py-1 text-xs font-semibold transition-all">
                        Dismissed <span class="ml-1 font-mono" x-text="counts.dismissed"></span>
                    </button>
                    <button type="button"
                        x-on:click="setStatus('all')"
                        x-bind:class="status === 'all' ? 'text-[#052e16]' : 'text-zinc-400 hover:text-zinc-200'"
                        x-bind:style="status === 'all' ? 'background:#4ade80;' : ''"
                        class="rounded-full px-3.5 py-1 text-xs font-semibold transition-all">
                        All <span class="ml-1 font-mono" x-text="counts.total"></span>
                    </button>
                </div>

                <div class="w-64 min-w-[200px]">
                    <input
                        type="search"
                        x-model="q"
                        x-on:input="onSearch()"
                        class="input !py-1 text-xs"
                        placeholder="Search place, issue or details..."
                    />
                </div>
            </div>

            <div class="flex items-center gap-2">
                <span class="mono text-xs text-zinc-400" x-text="meta.total + ' total reports'"></span>
                <button type="button" class="btn btn-ghost btn-sm !py-1 text-xs" x-on:click="loadReports()">
                    <i class="ph ph-arrows-clockwise"></i> Refresh
                </button>
            </div>
        </div>
    </div>

    <!-- Error State -->
    <template x-if="error !== ''">
        <div class="panel px-4 py-4 text-sm" style="background:rgba(251,113,133,0.06);border-color:rgba(251,113,133,0.3);color:var(--rose);" x-text="error"></div>
    </template>

    <!-- Reports Table Panel -->
    <div class="panel" x-show="error === ''">
        <div class="table-wrap">
            <table class="dense-table">
                <thead>
                    <tr>
                        <th style="width: 4rem;">ID</th>
                        <th>Status</th>
                        <th>Issue Type</th>
                        <th>Place</th>
                        <th>Reporter</th>
                        <th style="min-width: 220px;">Details / Feedback</th>
                        <th>Submitted</th>
                        <th style="min-width: 140px;">Admin Notes</th>
                        <th style="min-width: 150px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Loading skeleton -->
                    <tr x-show="loading">
                        <td colspan="9" class="px-0 py-1">
                            <div class="flex flex-col gap-2 p-4">
                                <div class="skel h-4 w-full"></div>
                                <div class="skel h-4 w-3/4"></div>
                                <div class="skel h-4 w-1/2"></div>
                            </div>
                        </td>
                    </tr>

                    <!-- Empty state -->
                    <tr x-show="!loading && !reports.length && error === ''">
                        <td colspan="9" class="px-4 py-12 text-center text-sm text-zinc-500">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <i class="ph ph-flag text-3xl text-zinc-600"></i>
                                <span>No feedback reports found for this view.</span>
                            </div>
                        </td>
                    </tr>

                    <!-- Report Rows -->
                    <template x-for="r in reports" :key="r.id">
                        <tr x-bind:class="busyId === r.id ? 'opacity-50' : ''">
                            <td class="mono text-xs text-zinc-500" x-text="'#' + r.id"></td>
                            <td>
                                <span class="badge uppercase tracking-wider text-[10px] font-semibold"
                                    x-bind:class="badgeClass(r.status)"
                                    x-text="r.status"></span>
                            </td>
                            <td>
                                <span class="inline-flex items-center gap-1.5 font-medium text-xs text-zinc-200">
                                    <span x-text="issueLabel(r.issue_type)"></span>
                                </span>
                            </td>
                            <td>
                                <template x-if="r.place">
                                    <div class="min-w-0">
                                        <a x-bind:href="'/?place=' + r.place.id" target="_blank" class="font-semibold text-xs text-emerald-400 hover:underline flex items-center gap-1">
                                            <span x-text="r.place.name"></span>
                                            <i class="ph ph-arrow-square-out text-[11px]"></i>
                                        </a>
                                        <div class="text-[11px] text-zinc-400 truncate max-w-[200px]" x-text="r.place.address || '-'"></div>
                                    </div>
                                </template>
                                <template x-if="!r.place">
                                    <span class="text-xs text-zinc-500 italic">Deleted place</span>
                                </template>
                            </td>
                            <td>
                                <template x-if="r.reporter">
                                    <div>
                                        <div class="text-xs font-medium text-zinc-200" x-text="r.reporter.name"></div>
                                        <div class="text-[10px] text-zinc-500" x-text="r.reporter.email"></div>
                                    </div>
                                </template>
                                <template x-if="!r.reporter">
                                    <span class="badge text-[10px] bg-zinc-800 text-zinc-400 border-zinc-700">Guest Visitor</span>
                                </template>
                            </td>
                            <td>
                                <div class="text-xs text-zinc-300 max-w-[260px] whitespace-pre-wrap leading-relaxed" x-text="r.details || 'No additional details provided.'"></div>
                            </td>
                            <td>
                                <span class="mono text-[11px] text-zinc-400" x-text="fmt(r.created_at)"></span>
                            </td>
                            <td>
                                <template x-if="r.admin_notes">
                                    <div class="text-[11px] text-zinc-300 italic" x-text="r.admin_notes"></div>
                                </template>
                                <template x-if="!r.admin_notes">
                                    <span class="text-[11px] text-zinc-600">-</span>
                                </template>
                                <template x-if="r.reviewer">
                                    <div class="text-[10px] text-zinc-500 mt-0.5">by <span x-text="r.reviewer.name"></span></div>
                                </template>
                            </td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <template x-if="r.status === 'pending'">
                                        <button
                                            type="button"
                                            class="btn !bg-emerald-500/15 !border-emerald-500/30 text-emerald-300 hover:!bg-emerald-500/25 !px-2 !py-1 text-xs"
                                            x-on:click="quickUpdate(r, 'resolved')"
                                            title="Mark as Resolved">
                                            <i class="ph ph-check"></i>
                                        </button>
                                    </template>
                                    <template x-if="r.status === 'pending'">
                                        <button
                                            type="button"
                                            class="btn !bg-zinc-800 !border-zinc-700 text-zinc-400 hover:text-zinc-200 !px-2 !py-1 text-xs"
                                            x-on:click="quickUpdate(r, 'dismissed')"
                                            title="Dismiss Report">
                                            <i class="ph ph-x"></i>
                                        </button>
                                    </template>
                                    <button
                                        type="button"
                                        class="btn btn-ghost !px-2 !py-1 text-xs text-zinc-400 hover:text-zinc-100"
                                        x-on:click="openActionModal(r, r.status === 'pending' ? 'resolved' : r.status)"
                                        title="Edit Review & Notes">
                                        <i class="ph ph-note-pencil"></i>
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-ghost !px-2 !py-1 text-xs text-rose-400 hover:!bg-rose-500/10"
                                        x-on:click="deleteReport(r)"
                                        title="Delete Report">
                                        <i class="ph ph-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between border-t border-white/5 px-4 py-3 text-xs text-zinc-400" x-show="meta.last_page > 1">
            <span>Page <span class="font-mono text-zinc-200" x-text="meta.current_page"></span> of <span class="font-mono text-zinc-200" x-text="meta.last_page"></span></span>
            <div class="flex gap-1">
                <button
                    type="button"
                    class="btn btn-ghost btn-sm"
                    x-bind:disabled="meta.current_page <= 1"
                    x-on:click="setPage(meta.current_page - 1)">
                    Previous
                </button>
                <button
                    type="button"
                    class="btn btn-ghost btn-sm"
                    x-bind:disabled="meta.current_page >= meta.last_page"
                    x-on:click="setPage(meta.current_page + 1)">
                    Next
                </button>
            </div>
        </div>
    </div>

    <!-- Review & Action Modal -->
    <div
        x-show="activeReport !== null"
        x-cloak
        class="dlg-backdrop"
        x-on:click.self="closeActionModal()"
        x-on:keydown.escape.window="closeActionModal()">
        <div class="dlg-card max-w-md p-6" role="dialog" aria-modal="true" aria-labelledby="report-modal-title">
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-white/10">
                <h3 id="report-modal-title" class="text-sm font-bold text-zinc-100 flex items-center gap-2">
                    <i class="ph ph-flag text-amber-400"></i>
                    Update Report #<span x-text="activeReport ? activeReport.id : ''"></span>
                </h3>
                <button type="button" class="icon-btn" x-on:click="closeActionModal()">
                    <i class="ph ph-x"></i>
                </button>
            </div>

            <div class="space-y-4 text-xs" x-show="activeReport">
                <div>
                    <label class="block text-zinc-400 font-semibold mb-1">Place</label>
                    <div class="font-medium text-zinc-100" x-text="activeReport?.place?.name || 'Deleted place'"></div>
                </div>

                <div>
                    <label class="block text-zinc-400 font-semibold mb-1">Issue Reported</label>
                    <div class="font-medium text-amber-300" x-text="issueLabel(activeReport?.issue_type)"></div>
                    <div class="mt-1 text-zinc-300 bg-zinc-900/60 p-2.5 rounded-lg border border-white/5" x-text="activeReport?.details || 'No details provided.'"></div>
                </div>

                <div>
                    <label class="block text-zinc-400 font-semibold mb-1.5" for="report-status-select">Status</label>
                    <select id="report-status-select" class="select w-full" x-model="updateStatusValue">
                        <option value="pending">Pending</option>
                        <option value="resolved">Resolved</option>
                        <option value="dismissed">Dismissed</option>
                    </select>
                </div>

                <div>
                    <label class="block text-zinc-400 font-semibold mb-1.5" for="report-admin-notes">Internal Admin Notes</label>
                    <textarea
                        id="report-admin-notes"
                        x-model="adminNotes"
                        rows="3"
                        class="w-full rounded-xl border border-white/10 bg-zinc-900/80 px-3 py-2 text-xs text-zinc-100 placeholder-zinc-500 focus:border-emerald-500/60 focus:outline-none focus:ring-1 focus:ring-emerald-500/60"
                        placeholder="Resolution notes or remarks..."
                    ></textarea>
                </div>
            </div>

            <div class="mt-6 flex items-center justify-end gap-2 pt-3 border-t border-white/10">
                <button type="button" class="btn btn-ghost btn-sm" x-on:click="closeActionModal()">Cancel</button>
                <button
                    type="button"
                    class="btn btn-accent btn-sm"
                    x-bind:disabled="busyId !== null"
                    x-on:click="submitStatusUpdate()">
                    <span x-show="busyId === null">Save Changes</span>
                    <span x-show="busyId !== null">Saving...</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
