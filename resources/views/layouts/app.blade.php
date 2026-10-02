@php
    $header ??= null;

    $canAdmin = false;
    if (auth()->check()) {
        $user = auth()->user();
        $canAdmin = method_exists($user, 'isAdmin') ? $user->isAdmin() : (($user->role ?? null) === 'admin');
    }
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#09090b">
        <meta name="description" content="Sarak &middot; Discover and pin live community places across Abra.">

        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="icon" type="image/svg+xml" href="/icons/sarak-mark.svg">
        <link rel="apple-touch-icon" href="/icons/sarak-mark.svg">

        <title>Sarak</title>

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

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased">
        <div class="flex min-h-screen flex-col bg-zinc-950">
            <nav x-data class="relative h-14 shrink-0 border-b border-white/10 bg-zinc-950/80 backdrop-blur z-[60]">
                <div class="mx-auto flex h-full max-w-[1600px] items-center gap-4 px-4 sm:px-5">
                    <a href="{{ url('/') }}" class="flex shrink-0 items-center gap-2.5" aria-label="Sarak home">
                        <x-brand-logo class="h-8 w-8" />
                        <span class="text-base font-bold tracking-tight text-zinc-100">Sarak</span>
                    </a>

                    <div class="ml-1 sm:ml-2 flex items-center gap-1">
                        <a href="{{ url('/') }}" class="{{ request()->is('/') ? 'text-zinc-100 bg-white/5' : 'text-zinc-400 hover:text-zinc-100' }} rounded-full px-2.5 sm:px-3.5 py-1 sm:py-1.5 text-xs sm:text-sm font-medium transition-colors">
                            Map
                        </a>
                        <button type="button" @click="window.dispatchEvent(new CustomEvent('open-contributors-modal'))" class="text-zinc-400 hover:text-zinc-100 rounded-full px-2.5 sm:px-3.5 py-1 sm:py-1.5 text-xs sm:text-sm font-medium transition-colors cursor-pointer">
                            Contributors
                        </button>
                        <button type="button" @click="window.dispatchEvent(new CustomEvent('open-about-modal'))" class="text-zinc-400 hover:text-zinc-100 rounded-full px-2.5 sm:px-3.5 py-1 sm:py-1.5 text-xs sm:text-sm font-medium transition-colors cursor-pointer">
                            About
                        </button>
                        @if ($canAdmin)
                            <a href="{{ url('/admin') }}" class="{{ request()->is('admin*') ? 'text-emerald-400 bg-white/5' : 'text-zinc-400 hover:text-emerald-300' }} rounded-full px-2.5 sm:px-3.5 py-1 sm:py-1.5 text-xs sm:text-sm font-medium transition-colors">
                                Admin
                            </a>
                        @endif
                    </div>

                    <div class="ml-auto flex items-center gap-2">
                        <button
                            type="button"
                            class="flex h-9 w-9 items-center justify-center rounded-full border border-white/10 bg-zinc-900/60 text-zinc-300 transition-colors hover:text-white"
                            x-data="themeToggle()"
                            @click="toggle()"
                            :aria-label="theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme'">
                            <i class="ph text-base" :class="theme === 'dark' ? 'ph-sun' : 'ph-moon'"></i>
                        </button>
                        @auth
                            <x-dropdown align="right" width="48" contentClasses="py-1 bg-zinc-900 border border-white/10">
                                <x-slot name="trigger">
                                    <button type="button" class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-medium text-zinc-300 transition-colors hover:bg-white/5 hover:text-white">
                                        @if (Auth::user()->avatar_url)
                                            <img src="{{ Auth::user()->avatar_url }}" alt="{{ Auth::user()->name }}" class="h-6 w-6 rounded-full object-cover ring-1 ring-emerald-400/40" />
                                        @else
                                            <span class="flex h-6 w-6 items-center justify-center rounded-full border border-emerald-400/30 bg-emerald-400/15 text-xs font-semibold text-emerald-400">
                                                {{ strtoupper(Str::substr(Auth::user()->name, 0, 1)) }}
                                            </span>
                                        @endif
                                        <span class="hidden max-w-[10rem] truncate sm:block">{{ Auth::user()->name }}</span>
                                        <i class="ph ph-caret-down text-xs text-zinc-500"></i>
                                    </button>
                                </x-slot>

                                <x-slot name="content">
                                    <x-dropdown-link :href="url('/')" class="text-zinc-300 hover:bg-white/5 hover:text-white">
                                        <i class="ph ph-map-trifold"></i> Live Map
                                    </x-dropdown-link>
                                    @if ($canAdmin)
                                        <x-dropdown-link :href="route('admin.dashboard')" class="text-emerald-400 hover:bg-white/5 hover:text-emerald-300 font-medium">
                                            <i class="ph ph-squares-four"></i> Admin Console
                                        </x-dropdown-link>
                                    @else
                                        <x-dropdown-link :href="route('dashboard')" class="text-zinc-300 hover:bg-white/5 hover:text-white">
                                            <i class="ph ph-squares-four"></i> Dashboard
                                        </x-dropdown-link>
                                    @endif
                                    <x-dropdown-link :href="route('profile.edit')" class="text-zinc-300 hover:bg-white/5 hover:text-white">
                                        <i class="ph ph-user-circle"></i> Profile
                                    </x-dropdown-link>

                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <x-dropdown-link :href="route('logout')" class="text-zinc-300 hover:bg-white/5 hover:text-white" onclick="event.preventDefault(); this.closest('form').submit();">
                                            <i class="ph ph-sign-out"></i> Log Out
                                        </x-dropdown-link>
                                    </form>
                                </x-slot>
                            </x-dropdown>
                        @else
                            <a href="{{ route('login') }}" class="rounded-full px-3 sm:px-3.5 py-1.5 text-xs sm:text-sm font-medium text-zinc-300 hover:text-zinc-100 transition-colors">
                                Log in
                            </a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="btn btn-ghost !px-3 sm:!px-4 !py-1.5 text-xs sm:text-sm">
                                    Register
                                </a>
                            @endif
                        @endauth
                    </div>
                </div>
            </nav>

            @isset($header)
                <header class="shrink-0 border-b border-white/10 bg-zinc-950/70 backdrop-blur">
                    <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main class="flex-1">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>