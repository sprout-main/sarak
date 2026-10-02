<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#09090b">
        <meta name="description" content="Sign in to Sarak.">

        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="icon" type="image/svg+xml" href="/icons/sarak-mark.svg">

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
        <div class="flex min-h-screen flex-col items-center justify-center bg-zinc-950 px-4 py-10 sm:py-14">
            <style>
                .auth-shell .text-gray-700,
                .auth-shell .text-gray-600,
                .auth-shell .text-gray-500 {
                    color: var(--atlas-text-2);
                }

                .auth-shell .text-gray-900,
                .auth-shell .text-indigo-600 {
                    color: var(--atlas-text);
                }

                .auth-shell .border-gray-300 {
                    border-color: var(--atlas-border);
                }

                .auth-shell .bg-white {
                    background: transparent;
                }

                .auth-shell input,
                .auth-shell select,
                .auth-shell textarea {
                    background: var(--atlas-bg-raise);
                    color: var(--atlas-text);
                }

                .auth-shell input::placeholder,
                .auth-shell textarea::placeholder {
                    color: var(--atlas-text-3);
                }

                .auth-shell button.bg-gray-800 {
                    background: var(--atlas-accent);
                    color: #09090b;
                }

                .auth-shell button.bg-gray-800:hover {
                    background: var(--atlas-accent-hover);
                }
            </style>

            <div class="auth-shell flex w-full max-w-md flex-col gap-6">
                <a href="/" class="flex items-center justify-center gap-2.5">
                    <x-brand-logo class="h-10 w-10" />
                    <span class="text-lg font-bold tracking-tight text-zinc-100">Sarak</span>
                </a>

                <div class="glass panel-12 tint-shadow p-6 sm:p-8">
                    {{ $slot }}
                </div>

                <p class="text-center text-xs text-zinc-500">
                    Sarak &middot; Community Map
                </p>
            </div>
        </div>
    </body>
</html>