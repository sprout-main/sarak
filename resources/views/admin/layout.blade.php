<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') | Sarak</title>
    <link rel="icon" type="image/svg+xml" href="/icons/sarak-mark.svg">
    <script>
        (function () {
            var theme = localStorage.getItem('atlas-theme') === 'light' ? 'light' : 'dark';
            document.documentElement.dataset.theme = theme;
        })();
    </script>
    <!-- Phosphor Icons Web Fonts -->
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/bold/style.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/fill/style.css">

    @vite(['resources/css/admin.css', 'resources/js/admin/app.js'])
    @yield('head')
</head>
<body class="h-full antialiased">
    <div x-data="adminToast()" x-init="init()">
        @if (session()->has('flash'))
            @php
                $flash = session('flash');
                $flashMessage = is_array($flash) ? ($flash['message'] ?? '') : $flash;
                $flashType = is_array($flash) ? ($flash['type'] ?? 'success') : 'success';
            @endphp
            <div id="admin-flash" data-message="{{ $flashMessage }}" data-type="{{ $flashType }}" class="hidden"></div>
        @endif

        <div x-show="show" x-cloak x-transition.opacity.duration.200ms x-bind:class="type === 'error' ? 'error' : ''" class="admin-toast">
            <div class="flex items-start gap-2">
                <i x-bind:class="type === 'error' ? 'ph ph-x-circle' : 'ph ph-check-circle'" x-bind:style="type === 'error' ? 'color:var(--danger)' : 'color:var(--success)'" class="mt-0.5 text-base"></i>
                <div x-text="message" class="flex-1 text-sm leading-5 pr-2"></div>
                <button type="button" x-on:click="show = false" class="-mt-0.5 text-zinc-500 transition-opacity hover:opacity-80"><i class="ph ph-x"></i></button>
            </div>
        </div>

        <div class="flex min-h-screen">
            <aside class="sticky top-0 flex h-screen w-60 shrink-0 flex-col border-r border-white/10 z-30" style="background-color:var(--surface);">
                <div class="flex shrink-0 items-center gap-2.5 px-4 py-4" style="border-bottom:1px solid var(--line);">
                    <x-brand-logo class="h-8 w-8" />
                    <div class="min-w-0">
                        <div class="text-sm font-semibold leading-4 text-zinc-100">Sarak</div>
                        <div class="font-mono text-[10px] uppercase tracking-widest" style="color:var(--success);">Admin console</div>
                    </div>
                </div>

                <div class="shrink-0 px-3 pt-3 pb-1">
                    <button type="button" @click="window.dispatchEvent(new CustomEvent('open-admin-submit-place'))" class="flex w-full items-center justify-center gap-2 rounded-lg py-2 px-3 text-xs font-semibold !bg-emerald-500 hover:!bg-emerald-400 !text-zinc-950 shadow-md shadow-emerald-500/20 transition-all cursor-pointer">
                        <i class="ph ph-plus-circle text-base"></i>
                        <span>Add a Place</span>
                    </button>
                </div>

                <nav class="flex-1 min-h-0 space-y-0.5 overflow-y-auto px-3 py-3">
                    @php
                        $adminPath = request()->path();
                        $navItems = [
                            ['Dashboard', '/admin', 'ph-squares-four'],
                            ['Queue', '/admin/queue', 'ph-tray'],
                            ['Places', '/admin/places', 'ph-map-pin'],
                            ['Accounts', '/admin/users', 'ph-users'],
                            ['Reports', '/admin/reports', 'ph-flag'],
                            ['Import', '/admin/import', 'ph-download-simple'],
                            ['Boundaries', '/admin/boundaries', 'ph-bounding-box'],
                            ['Analytics', '/admin/analytics', 'ph-chart-bar'],
                            ['Audit Logs', '/admin/audit-logs', 'ph-clock-counter-clockwise'],
                        ];
                    @endphp
                    @foreach ($navItems as [$navLabel, $navHref, $navIcon])
                        @php
                            $navKey = ltrim($navHref, '/');
                            $navActive = $adminPath === $navKey || str_starts_with($adminPath, $navKey . '/');
                        @endphp
                        <a href="{{ $navHref }}" class="group flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition-opacity hover:opacity-90 {{ $navActive ? 'bg-white/5 text-white' : 'text-zinc-400 hover:text-zinc-100' }}">
                            <i class="{{ $navIcon }} text-base {{ $navActive ? 'text-[var(--success)]' : 'text-zinc-500 group-hover:text-zinc-300' }}"></i>
                            <span>{{ $navLabel }}</span>
                        </a>
                    @endforeach
                </nav>

                <div class="shrink-0 space-y-1 border-t px-3 py-3" style="border-color:var(--line);">
                    <a href="/" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-zinc-400 transition-opacity hover:text-zinc-100 hover:opacity-90">
                        <i class="ph ph-map-trifold text-base"></i> Back to map
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-zinc-400 transition-opacity hover:text-zinc-100 hover:opacity-90">
                            <i class="ph ph-sign-out text-base"></i> Logout
                        </button>
                    </form>
                </div>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="sticky top-0 z-20 flex items-center justify-between gap-4 border-b border-white/10 px-6 py-3.5" style="background-color:var(--surface);">
                    <div class="min-w-0">
                        <h1 class="truncate text-base font-semibold text-white">@yield('title', 'Admin')</h1>
                        <p class="truncate text-xs text-zinc-500">@yield('subtitle', '')</p>
                    </div>
                    <div class="flex flex-shrink-0 items-center gap-3">
                        <button type="button" @click="window.dispatchEvent(new CustomEvent('open-admin-submit-place'))" class="btn btn-sm btn-ghost border border-emerald-500/30 text-emerald-400 hover:bg-emerald-500/10 cursor-pointer">
                            <i class="ph ph-plus-circle text-base"></i> <span class="hidden sm:inline">Add a Place</span>
                        </button>
                        <button
                            type="button"
                            class="flex h-8 w-8 items-center justify-center rounded-full border border-white/10 bg-white/5 text-zinc-300 transition-colors hover:text-white"
                            x-data="themeToggle()"
                            @click="toggle()"
                            :aria-label="theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme'">
                            <i class="ph text-base" :class="theme === 'dark' ? 'ph-sun' : 'ph-moon'"></i>
                        </button>
                        <a href="/" class="btn btn-ghost btn-sm"><i class="ph ph-map-pin"></i> Map</a>
                        <div class="h-4 w-px bg-white/10 hidden sm:block"></div>
                        <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 rounded-lg p-1 transition-opacity hover:opacity-80" title="Edit Profile">
                            @if(auth()->user()->avatar_url)
                                <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }}" class="h-7 w-7 rounded-full object-cover ring-1 ring-emerald-500/40" />
                            @else
                                <span class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold" style="background-color:var(--accent-soft);color:var(--success);">
                                    {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                                </span>
                            @endif
                            <div class="hidden sm:block text-left">
                                @php
                                    $roleVal = auth()->user()->role instanceof \App\Enums\UserRole ? auth()->user()->role->value : auth()->user()->role;
                                    $nameVal = auth()->user()->name;
                                    $isSame = strcasecmp(trim($nameVal), trim((string)$roleVal)) === 0;
                                @endphp
                                <div class="text-xs font-medium text-zinc-100 leading-tight">
                                    {{ $nameVal }}
                                </div>
                                @if (!$isSame)
                                    <div class="badge badge-zinc font-mono uppercase text-[9px] mt-0.5">
                                        {{ $roleVal }}
                                    </div>
                                @endif
                            </div>
                        </a>
                    </div>
                </header>

                <main class="flex-1 px-6 py-6">
                    @yield('content')
                </main>
            </div>
        </div>

        <x-admin.submit-modal />
    </div>
</body>
</html>