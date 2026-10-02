<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-zinc-100 sm:text-2xl">
                    Community Portal
                </h1>
                <p class="text-xs text-zinc-400">
                    Sarak &middot; Community Workspace
                </p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-3 py-1 text-xs font-medium text-emerald-400">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                    {{ auth()->user()->role instanceof \App\Enums\UserRole ? auth()->user()->role->label() : ucfirst(auth()->user()->role ?? 'Resident') }}
                </span>
                <a href="{{ url('/') }}" class="btn btn-ghost !px-3.5 !py-1.5 text-xs">
                    <i class="ph ph-map-trifold"></i> Open Map
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Welcome Banner -->
            <div class="glass panel-12 p-6 sm:p-8 tint-shadow border border-white/10 relative overflow-hidden">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6 relative z-10">
                    <div class="space-y-2">
                        <div class="inline-flex items-center gap-2 text-xs font-mono text-emerald-400">
                            <i class="ph ph-user-circle text-base"></i>
                            <span>{{ auth()->user()->email }}</span>
                        </div>
                        <h2 class="text-2xl font-bold tracking-tight text-white">
                            Welcome back, {{ auth()->user()->name }}!
                        </h2>
                        <p class="text-sm text-zinc-400 max-w-2xl">
                            You are signed in to Sarak. Contribute places, verify municipal infrastructure, and navigate town portals.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ url('/') }}" class="btn btn-primary !px-5 !py-2.5 text-sm font-semibold shadow-lg shadow-emerald-500/20">
                            <i class="ph ph-map-pin text-base"></i> Explore Map
                        </a>
                        <a href="{{ url('/?submit=1') }}" class="btn btn-ghost !px-4 !py-2.5 text-sm font-medium">
                            <i class="ph ph-plus-circle text-base"></i> Submit Place
                        </a>
                        @if (auth()->user()->isAdmin())
                            <a href="{{ url('/admin') }}" class="btn btn-ghost border border-emerald-500/40 text-emerald-400 hover:bg-emerald-500/10 hover:text-emerald-300 !px-4 !py-2.5 text-sm font-medium">
                                <i class="ph ph-shield-check text-base"></i> Admin Console
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Portal Navigation Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <!-- Card 1: Interactive Map -->
                <div class="glass panel-12 p-6 tint-shadow border border-white/10 flex flex-col justify-between group hover:border-emerald-400/30 transition-all">
                    <div>
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-400/10 text-emerald-400 mb-4 group-hover:scale-105 transition-transform">
                            <i class="ph ph-map-trifold text-2xl"></i>
                        </div>
                        <h3 class="text-base font-semibold text-white mb-1.5">Sarak Map Portal</h3>
                        <p class="text-xs text-zinc-400 leading-relaxed mb-4">
                            Interactive wayfinding map with dark/light raster tiles, category filters, live hours indicators, and boundary geometry across 31 barangays.
                        </p>
                    </div>
                    <a href="{{ url('/') }}" class="inline-flex items-center gap-2 text-xs font-semibold text-emerald-400 hover:text-emerald-300">
                        Launch Public Map <i class="ph ph-arrow-right"></i>
                    </a>
                </div>

                <!-- Card 2: Contributor Flow -->
                <div class="glass panel-12 p-6 tint-shadow border border-white/10 flex flex-col justify-between group hover:border-emerald-400/30 transition-all">
                    <div>
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-sky-400/10 text-sky-400 mb-4 group-hover:scale-105 transition-transform">
                            <i class="ph ph-plus-circle text-2xl"></i>
                        </div>
                        <h3 class="text-base font-semibold text-white mb-1.5">Add a Place</h3>
                        <p class="text-xs text-zinc-400 leading-relaxed mb-4">
                            Submit a fast food chain, hospital, clinic, pharmacy, grocery, or coffee shop. Submissions land in the moderation queue for review and auto-snap to barangays.
                        </p>
                    </div>
                    <a href="{{ url('/?submit=1') }}" class="inline-flex items-center gap-2 text-xs font-semibold text-sky-400 hover:text-sky-300">
                        Submit a Location <i class="ph ph-arrow-right"></i>
                    </a>
                </div>

                @if (auth()->user()->isAdmin())
                    <!-- Card 3: Admin Console (For Staff) -->
                    <div class="glass panel-12 p-6 tint-shadow border border-emerald-400/20 bg-emerald-950/10 flex flex-col justify-between group hover:border-emerald-400/40 transition-all">
                        <div>
                            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-400/20 text-emerald-300 mb-4 group-hover:scale-105 transition-transform">
                                <i class="ph ph-shield-check text-2xl"></i>
                            </div>
                            <h3 class="text-base font-semibold text-white mb-1.5">Moderation & Admin</h3>
                            <p class="text-xs text-zinc-400 leading-relaxed mb-4">
                                Full moderation queue, place editor, boundary polygon viewer, Overpass/Nominatim batch imports, and category-barangay coverage analytics.
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <a href="{{ url('/admin/queue') }}" class="text-xs font-semibold text-emerald-400 hover:text-emerald-300">
                                Review Queue <i class="ph ph-tray"></i>
                            </a>
                            <span class="text-zinc-600">&middot;</span>
                            <a href="{{ url('/admin') }}" class="text-xs font-semibold text-zinc-300 hover:text-white">
                                Dashboard <i class="ph ph-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                @else
                    <!-- Card 3: Profile Settings (For Resident) -->
                    <div class="glass panel-12 p-6 tint-shadow border border-white/10 flex flex-col justify-between group hover:border-zinc-700 transition-all">
                        <div>
                            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-purple-400/10 text-purple-400 mb-4 group-hover:scale-105 transition-transform">
                                <i class="ph ph-gear text-2xl"></i>
                            </div>
                            <h3 class="text-base font-semibold text-white mb-1.5">Account & Security</h3>
                            <p class="text-xs text-zinc-400 leading-relaxed mb-4">
                                Manage your contributor account credentials, profile name, registered email address, and security password.
                            </p>
                        </div>
                        <a href="{{ route('profile.edit') }}" class="inline-flex items-center gap-2 text-xs font-semibold text-purple-400 hover:text-purple-300">
                            Edit Profile <i class="ph ph-arrow-right"></i>
                        </a>
                    </div>
                @endif
            </div>

            <!-- Community Contributor Privacy Card -->
            <div
                x-data="{
                    censored: {{ auth()->user()->censor_contributor_name !== false ? 'true' : 'false' }},
                    saving: false,
                    savedMessage: '',
                    async togglePrivacy() {
                        this.saving = true;
                        this.savedMessage = '';
                        try {
                            const res = await fetch('{{ route('profile.contributor-privacy') }}', {
                                method: 'PATCH',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({ censor_contributor_name: !this.censored })
                            });
                            const data = await res.json();
                            if (res.ok) {
                                this.censored = data.censor_contributor_name;
                                this.savedMessage = 'Privacy setting updated successfully.';
                                setTimeout(() => this.savedMessage = '', 3500);
                            } else {
                                alert(data.message || 'Failed to update privacy preference');
                            }
                        } catch (e) {
                            alert('Network error updating privacy preference');
                        } finally {
                            this.saving = false;
                        }
                    }
                }"
                class="glass panel-12 p-6 tint-shadow border border-white/10"
            >
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-400/10 text-emerald-400">
                                <i class="ph ph-shield-check text-lg"></i>
                            </span>
                            <h3 class="text-sm font-bold text-white">Community Contributor Privacy</h3>
                            <span class="badge text-[10px] font-mono uppercase" :class="censored ? 'badge-zinc' : 'badge-green'" x-text="censored ? 'Censored (Default)' : 'Public Username'"></span>
                        </div>
                        <p class="text-xs text-zinc-400 max-w-xl">
                            By default, contributor usernames are censored in the public Community Contributors leaderboard (e.g., <span class="font-mono text-zinc-300">{{ \App\Services\KarmaService::maskName(auth()->user()->name) }}</span>). You can toggle this setting to display your username (<span class="font-mono text-zinc-300">{{ auth()->user()->name }}</span>).
                        </p>
                        <template x-if="savedMessage">
                            <p class="text-xs text-emerald-400 font-medium pt-1" x-text="savedMessage"></p>
                        </template>
                    </div>

                    <div class="flex items-center gap-3 shrink-0">
                        <button
                            type="button"
                            @click="togglePrivacy()"
                            :disabled="saving"
                            class="btn text-xs font-semibold px-4 py-2 transition-all cursor-pointer"
                            :class="censored ? 'btn-ghost border border-white/10 hover:border-emerald-500/40 text-zinc-200' : 'btn-primary shadow-lg shadow-emerald-500/20'"
                        >
                            <span x-show="!saving" class="flex items-center gap-1.5">
                                <i :class="censored ? 'ph ph-eye text-sm' : 'ph ph-eye-slash text-sm'"></i>
                                <span x-text="censored ? 'Show Username' : 'Mask My Username'"></span>
                            </span>
                            <span x-show="saving" class="flex items-center gap-1.5">
                                <i class="ph ph-spinner animate-spin text-sm"></i>
                                <span>Saving...</span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Town Infrastructure Statistics Summary -->
            <div class="glass panel-12 p-6 tint-shadow border border-white/10">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <i class="ph ph-chart-polar text-emerald-400"></i> Bangued Infrastructure Snapshot
                    </h3>
                    <span class="text-xs text-zinc-500 font-mono">Live Database</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="rounded-xl border border-white/5 bg-zinc-900/40 p-4">
                        <div class="text-xs text-zinc-500 mb-1">Approved Places</div>
                        <div class="text-2xl font-bold text-white font-mono">
                            {{ \App\Models\Place::where('status', 'approved')->count() }}
                        </div>
                    </div>
                    <div class="rounded-xl border border-white/5 bg-zinc-900/40 p-4">
                        <div class="text-xs text-zinc-500 mb-1">Barangay Boundaries</div>
                        <div class="text-2xl font-bold text-white font-mono">
                            {{ \App\Models\BoundaryArea::count() }}
                        </div>
                    </div>
                    <div class="rounded-xl border border-white/5 bg-zinc-900/40 p-4">
                        <div class="text-xs text-zinc-500 mb-1">Active Categories</div>
                        <div class="text-2xl font-bold text-emerald-400 font-mono">
                            {{ \App\Models\Category::where('is_active', true)->count() }}
                        </div>
                    </div>
                    <div class="rounded-xl border border-white/5 bg-zinc-900/40 p-4">
                        <div class="text-xs text-zinc-500 mb-1">Pending Moderation</div>
                        <div class="text-2xl font-bold text-amber-400 font-mono">
                            {{ \App\Models\ModQueue::where('status', 'pending')->count() }}
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
