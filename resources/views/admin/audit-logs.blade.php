@extends('admin.layout')

@section('title', 'Audit Logs')
@section('subtitle', 'Immutable chronological record of administrative operations, spatial shifts, and system mutations')

@section('content')
<div x-data="adminAuditLogs()" x-init="init()" class="mx-auto max-w-7xl space-y-4">
    <!-- Filter and Search Toolbar -->
    <div class="panel">
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
            <div class="flex flex-wrap items-center gap-2.5 flex-1 min-w-[280px]">
                <div class="relative min-w-[200px] max-w-sm flex-1">
                    <input
                        type="search"
                        x-model="q"
                        x-on:input="onSearch()"
                        class="input !py-1.5 text-xs"
                        placeholder="Search model, action, actor, or payload..."
                    />
                </div>

                <!-- Model Filter -->
                <div class="w-36 shrink-0">
                    <select class="select !w-full !py-1.5 text-xs" x-model="model" x-on:change="onFilterChange()">
                        <option value="">All models</option>
                        <template x-for="m in filters.models" :key="m">
                            <option :value="m" x-text="m"></option>
                        </template>
                    </select>
                </div>

                <!-- Action Filter -->
                <div class="w-36 shrink-0">
                    <select class="select !w-full !py-1.5 text-xs" x-model="action" x-on:change="onFilterChange()">
                        <option value="">All actions</option>
                        <template x-for="a in filters.actions" :key="a">
                            <option :value="a" x-text="a"></option>
                        </template>
                    </select>
                </div>

                <!-- Reset Filter Button -->
                <template x-if="q || model || action">
                    <button type="button" class="btn btn-ghost btn-sm !py-1 text-xs" x-on:click="resetFilters()">
                        <i class="ph ph-x"></i> Clear
                    </button>
                </template>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <span class="mono text-xs text-zinc-400 bg-zinc-800/60 border border-white/5 rounded-lg px-2.5 py-1.5 whitespace-nowrap" x-text="meta.total + ' audit ' + (meta.total === 1 ? 'event' : 'events')"></span>
                <button type="button" class="btn btn-ghost btn-sm whitespace-nowrap" x-on:click="loadLogs()">
                    <i class="ph ph-arrows-clockwise"></i> Refresh
                </button>
            </div>
        </div>
    </div>

    <!-- Error State -->
    <template x-if="error !== ''">
        <div class="panel px-4 py-4 text-sm" style="background:rgba(251,113,133,0.06);border-color:rgba(251,113,133,0.3);color:var(--rose);" x-text="error"></div>
    </template>

    <!-- Table Panel -->
    <div class="panel" x-show="error === ''">
        <div class="table-wrap">
            <table class="dense-table">
                <thead>
                    <tr>
                        <th style="width: 3.5rem;">ID</th>
                        <th style="min-width: 140px;">Timestamp</th>
                        <th style="min-width: 160px;">Actor</th>
                        <th>Action</th>
                        <th>Target Model</th>
                        <th style="min-width: 220px;">Change Summary</th>
                        <th style="width: 6rem; text-align: right;">Details</th>
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
                    <tr x-show="!loading && !logs.length">
                        <td colspan="7" class="px-4 py-12 text-center text-sm text-zinc-500">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <i class="ph ph-clock-counter-clockwise text-3xl text-zinc-600"></i>
                                <span>No audit log records match your filter criteria.</span>
                            </div>
                        </td>
                    </tr>

                    <!-- Audit Log Rows -->
                    <template x-for="log in logs" :key="log.id">
                        <tr>
                            <td class="mono text-xs text-zinc-500" x-text="'#' + log.id"></td>
                            <td>
                                <span class="mono text-xs text-zinc-400" x-text="fmt(log.created_at)"></span>
                            </td>
                            <td>
                                <template x-if="log.actor">
                                    <div class="flex items-center gap-2">
                                        <template x-if="log.actor.avatar_url">
                                            <img :src="log.actor.avatar_url" :alt="log.actor.name" class="h-6 w-6 rounded-full object-cover ring-1 ring-white/10" />
                                        </template>
                                        <template x-if="!log.actor.avatar_url">
                                            <span class="flex h-6 w-6 items-center justify-center rounded-full text-[10px] font-semibold" style="background-color:var(--accent-soft);color:var(--success);" x-text="log.actor.name.charAt(0).toUpperCase()"></span>
                                        </template>
                                        <div class="min-w-0">
                                            <div class="text-xs font-medium text-zinc-200 truncate" x-text="log.actor.name"></div>
                                            <div class="mono text-[10px] text-zinc-500 truncate" x-text="log.actor.email"></div>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="!log.actor">
                                    <div class="flex items-center gap-1.5 text-zinc-500 italic text-xs">
                                        <i class="ph ph-robot text-sm"></i>
                                        <span x-text="log.actor_id ? 'User #' + log.actor_id : 'System / Automated'"></span>
                                    </div>
                                </template>
                            </td>
                            <td>
                                <span
                                    class="badge font-mono uppercase text-[10px] tracking-wider font-semibold"
                                    :class="actionBadge(log.action)"
                                    x-text="log.action"
                                ></span>
                            </td>
                            <td>
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="badge font-mono text-[10px]"
                                        :class="modelBadge(log.model)"
                                        x-text="log.model"
                                    ></span>
                                    <span class="mono text-xs text-zinc-400" x-text="'#' + log.model_id"></span>
                                </div>
                            </td>
                            <td>
                                <span class="mono text-xs text-zinc-300 truncate block max-w-sm" x-text="formatSummary(log.diff)"></span>
                            </td>
                            <td class="text-right">
                                <button
                                    type="button"
                                    class="btn btn-ghost btn-xs text-zinc-400 hover:text-white"
                                    x-on:click="openLogModal(log)"
                                    title="View full log payload"
                                >
                                    <i class="ph ph-file-code"></i> Inspect
                                </button>
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

    <!-- Log Detail & Diff Popup Modal -->
    <div
        x-show="activeLog !== null"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-on:keydown.escape.window="closeLogModal()"
    >
        <!-- Modal Backdrop -->
        <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" x-on:click="closeLogModal()"></div>

        <!-- Popup Modal Card -->
        <div
            class="relative z-10 w-full max-w-2xl panel shadow-2xl overflow-hidden flex flex-col max-h-[88vh] my-auto"
            style="background:var(--surface);"
            role="dialog"
            aria-modal="true"
            aria-labelledby="log-modal-title"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
        >
            <div class="modal-head flex items-center justify-between shrink-0 px-5 py-3.5 border-b border-white/10">
                <div class="flex items-center gap-2.5">
                    <span class="mono text-xs text-zinc-500" x-text="'#' + (activeLog ? activeLog.id : '')"></span>
                    <h3 id="log-modal-title" class="text-sm font-bold text-zinc-100 flex items-center gap-2">
                        Audit Log Event
                    </h3>
                    <template x-if="activeLog">
                        <span class="badge font-mono uppercase text-[10px]" :class="actionBadge(activeLog.action)" x-text="activeLog.action"></span>
                    </template>
                </div>
                <button type="button" class="btn btn-ghost btn-xs text-zinc-400 hover:text-white" x-on:click="closeLogModal()">
                    <i class="ph ph-x text-base"></i>
                </button>
            </div>

            <div class="modal-body overflow-y-auto space-y-4 p-5 text-xs flex-1 min-h-0" x-show="activeLog">
                <!-- Meta Info Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3 rounded-xl bg-white/5 border border-white/5">
                    <div>
                        <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-semibold">Model</div>
                        <div class="font-mono text-zinc-200 mt-0.5" x-text="activeLog?.model"></div>
                    </div>
                    <div>
                        <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-semibold">Entity ID</div>
                        <div class="font-mono text-zinc-200 mt-0.5" x-text="'#' + activeLog?.model_id"></div>
                    </div>
                    <div>
                        <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-semibold">Actor</div>
                        <div class="text-zinc-200 mt-0.5 truncate font-medium" x-text="activeLog?.actor?.name || (activeLog?.actor_id ? 'User #' + activeLog.actor_id : 'System')"></div>
                    </div>
                    <div>
                        <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-semibold">Logged At</div>
                        <div class="mono text-zinc-200 mt-0.5" x-text="fmt(activeLog?.created_at)"></div>
                    </div>
                </div>

                <!-- Structured Before/After Delta View (if present) -->
                <template x-if="activeLog && activeLog.diff && activeLog.diff.before && activeLog.diff.after">
                    <div class="space-y-2">
                        <div class="text-xs font-semibold text-zinc-300">State Delta (Before & After)</div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div class="p-3 rounded-lg bg-rose-950/20 border border-rose-500/20">
                                <div class="text-[10px] font-mono uppercase tracking-wider text-rose-400 font-semibold mb-1 flex items-center gap-1">
                                    <i class="ph ph-minus-circle"></i> Before
                                </div>
                                <pre class="mono text-[11px] text-rose-200 overflow-x-auto whitespace-pre-wrap leading-relaxed" x-text="JSON.stringify(activeLog.diff.before, null, 2)"></pre>
                            </div>
                            <div class="p-3 rounded-lg bg-emerald-950/20 border border-emerald-500/20">
                                <div class="text-[10px] font-mono uppercase tracking-wider text-emerald-400 font-semibold mb-1 flex items-center gap-1">
                                    <i class="ph ph-plus-circle"></i> After
                                </div>
                                <pre class="mono text-[11px] text-emerald-200 overflow-x-auto whitespace-pre-wrap leading-relaxed" x-text="JSON.stringify(activeLog.diff.after, null, 2)"></pre>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Raw Diff JSON -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-zinc-300">Payload Attributes (JSON)</span>
                        <button
                            type="button"
                            class="btn btn-ghost btn-xs text-zinc-400 hover:text-white"
                            x-on:click="copyJson(activeLog ? activeLog.diff : {})"
                        >
                            <i class="ph" :class="copied ? 'ph-check text-emerald-400' : 'ph-copy'"></i>
                            <span x-text="copied ? 'Copied' : 'Copy JSON'"></span>
                        </button>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-zinc-950 p-3 overflow-hidden">
                        <pre class="mono text-xs text-emerald-300/90 overflow-x-auto max-h-64 whitespace-pre-wrap leading-relaxed" x-text="activeLog && activeLog.diff ? JSON.stringify(activeLog.diff, null, 2) : 'null'"></pre>
                    </div>
                </div>
            </div>

            <div class="modal-footer flex items-center justify-end gap-2 px-5 py-3 border-t border-white/10 shrink-0" style="background:var(--surface);">
                <button type="button" class="btn btn-ghost btn-sm" x-on:click="closeLogModal()">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection
