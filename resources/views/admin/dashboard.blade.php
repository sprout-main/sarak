@extends('admin.layout')

@section('title', 'Dashboard')
@section('subtitle', 'Live spatial data at a glance')

@section('content')
<div x-data="adminDashboard()" x-init="init()" class="mx-auto max-w-7xl space-y-5">
    <!-- Contributor Hero & Workspace Banner -->
    <div class="panel admin-welcome-banner p-6 relative overflow-hidden" style="border-color:var(--line);">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-5 relative z-10">
            <div class="space-y-1.5">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 dark:bg-emerald-400"></span>
                        Admin Console
                    </span>
                </div>
                <h2 class="text-xl sm:text-2xl font-bold tracking-tight" style="color:var(--text);">
                    Welcome back, {{ auth()->user()->name }}
                </h2>
                <p class="text-xs max-w-2xl leading-relaxed" style="color:var(--text-mid);">
                    Sarak unified administration. Verify community submissions, audit spatial boundaries, and manage live infrastructure.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <button type="button" @click="window.dispatchEvent(new CustomEvent('open-admin-submit-place'))" class="btn btn-sm !bg-emerald-500 hover:!bg-emerald-400 !text-zinc-950 font-semibold shadow-md shadow-emerald-500/20 cursor-pointer">
                    <i class="ph ph-plus-circle text-base"></i> Submit Place
                </button>
                <a href="{{ url('/') }}" class="btn btn-sm btn-ghost">
                    <i class="ph ph-map-trifold text-base"></i> Open Map
                </a>
                <a href="{{ url('/admin/queue') }}" class="btn btn-sm btn-ghost border border-amber-500/30 text-amber-400 hover:bg-amber-500/10">
                    <i class="ph ph-tray text-base"></i> Review Queue
                </a>
            </div>
        </div>
    </div>

    <template x-if="error !== ''">
        <div class="panel px-4 py-6 text-sm" style="background:rgba(251,113,133,0.06);border-color:rgba(251,113,133,0.3);color:var(--rose);" x-text="error"></div>
    </template>

    <div x-show="error === ''" class="space-y-5">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <template x-for="k in kpis()" :key="k.label">
                <div class="panel px-5 py-4">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg" x-bind:style="'background:' + k.bg + ';color:' + k.color">
                            <i class="ph text-base" x-bind:class="k.icon"></i>
                        </span>
                        <div class="min-w-0">
                            <div class="text-[11px] uppercase tracking-wider text-zinc-500" x-text="k.label"></div>
                            <div class="mt-0.5 mono text-2xl font-semibold leading-6 text-white">
                                <span x-show="k.value !== null" x-text="k.value"></span>
                                <span x-show="k.value === null" class="skel inline-block h-6 w-16 align-bottom"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
            <div class="panel">
                <div class="modal-head">
                    <span class="text-sm font-semibold text-white">Pending queue heat</span>
                    <a href="/admin/queue" class="btn btn-xs btn-ghost">Open queue <i class="ph ph-arrow-right"></i></a>
                </div>
                <div class="p-4">
                    <div class="space-y-3" x-show="byAction">
                        <template x-for="a in actionsList()" :key="a.key">
                            <div class="flex items-center gap-3">
                                <span class="mono w-16 flex-shrink-0 text-[11px] uppercase tracking-wider text-zinc-500" x-text="a.key"></span>
                                <div class="bar flex-1">
                                    <div class="bar-fill" x-bind:style="'width:' + (a.count / maxAction * 100) + '%'"></div>
                                </div>
                                <span class="mono w-8 flex-shrink-0 text-right text-sm" style="color:var(--success);" x-text="a.count"></span>
                            </div>
                        </template>
                        <div class="flex items-center gap-3 pt-1" style="border-top:1px solid var(--line);">
                            <span class="mono w-16 flex-shrink-0 text-[11px] uppercase tracking-wider text-zinc-500">Total</span>
                            <div class="flex-1 text-sm font-medium text-zinc-200">
                                <span x-show="pendingTotal === null" class="skel inline-block h-3.5 w-24"></span>
                                <span x-show="pendingTotal !== null" x-text="pendingTotal + ' waiting'"></span>
                            </div>
                        </div>
                    </div>
                    <div x-show="byAction === null">
                        <div class="space-y-3">
                            <div class="skel h-3.5 w-full"></div>
                            <div class="skel h-3.5 w-5/6"></div>
                            <div class="skel h-3.5 w-2/3"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="modal-head">
                    <span class="text-sm font-semibold text-white">Quick actions</span>
                </div>
                <div class="grid grid-cols-1 gap-2 p-4 sm:grid-cols-2">
                    <button type="button" @click="window.dispatchEvent(new CustomEvent('open-admin-submit-place'))" class="flex items-center gap-3 rounded-lg border border-emerald-500/30 bg-emerald-500/5 px-4 py-3 text-sm font-medium text-emerald-400 transition-colors hover:bg-emerald-500/10 text-left cursor-pointer">
                        <i class="ph ph-plus-circle text-base"></i> Submit a place
                    </button>
                    <a href="/admin/queue" class="flex items-center gap-3 rounded-lg border border-white/10 px-4 py-3 text-sm font-medium text-zinc-200 transition-opacity hover:opacity-90">
                        <i class="ph ph-tray text-base" style="color:var(--warn);"></i> Review queue
                    </a>
                    <a href="/admin/places" class="flex items-center gap-3 rounded-lg border border-white/10 px-4 py-3 text-sm font-medium text-zinc-200 transition-opacity hover:opacity-90">
                        <i class="ph ph-map-pin text-base" style="color:var(--success);"></i> Manage places
                    </a>
                    <a href="/admin/import" class="flex items-center gap-3 rounded-lg border border-white/10 px-4 py-3 text-sm font-medium text-zinc-200 transition-opacity hover:opacity-90">
                        <i class="ph ph-download-simple text-base" style="color:var(--info);"></i> Run an import
                    </a>
                    <a href="/admin/analytics" class="flex items-center gap-3 rounded-lg border border-white/10 px-4 py-3 text-sm font-medium text-zinc-200 transition-opacity hover:opacity-90">
                        <i class="ph ph-chart-bar text-base" style="color:var(--violet);"></i> Coverage analytics
                    </a>
                    <a href="/" class="flex items-center gap-3 rounded-lg border border-white/10 px-4 py-3 text-sm font-medium text-zinc-200 transition-opacity hover:opacity-90">
                        <i class="ph ph-map-trifold text-base" style="color:var(--danger);"></i> View live map
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection