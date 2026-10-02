@props([
    'class' => 'h-8 w-8',
])

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128" fill="none" aria-hidden="true" {{ $attributes->merge(['class' => $class . ' transition-transform duration-200 shrink-0']) }}>
    <defs>
        <linearGradient id="brandGradDark" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#4ade80"/>
            <stop offset="100%" stop-color="#10b981"/>
        </linearGradient>
        <linearGradient id="brandGradLight" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#15803d"/>
            <stop offset="100%" stop-color="#16a34a"/>
        </linearGradient>
    </defs>

    <!-- Squircle base adaptive to light/dark themes -->
    <rect x="8" y="8" width="112" height="112" rx="30" class="brand-logo-bg fill-zinc-900 stroke-white/10 dark:fill-zinc-900 dark:stroke-white/10" stroke-width="2"/>

    <!-- Concentric Spatial Pulse Rings -->
    <circle cx="64" cy="56" r="38" class="brand-logo-ring stroke-emerald-400/30" stroke-width="1.5" stroke-dasharray="3 3"/>
    <circle cx="64" cy="56" r="26" class="brand-logo-ring stroke-emerald-400/30" stroke-width="1.5"/>

    <!-- Sarak Dynamic Crossroads Wayfinding Pin -->
    <path d="M64 22C46.3 22 32 36.3 32 54c0 24.5 26.5 48.5 32 54 5.5-5.5 32-29.5 32-54 0-17.7-14.3-32-32-32z" class="brand-logo-pin stroke-emerald-400" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"/>

    <!-- Inner Diamond Crossroads Beacon -->
    <g transform="translate(64 54)">
        <path d="M0 -15L4.5 -4.5L15 0L4.5 4.5L0 15L-4.5 4.5L-15 0L-4.5 -4.5Z" class="brand-logo-beacon fill-emerald-400"/>
        <circle cx="0" cy="0" r="3.5" class="brand-logo-dot fill-white"/>
    </g>
</svg>
