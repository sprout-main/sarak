@props(['label' => '', 'icon' => '', 'bind' => '', 'accented' => false])

<div {{ $attributes->merge(['class' => 'stat-pill' . ($accented ? ' accented' : '')]) }}>
    @if ($icon)
        <i class="ph ph-{{ $icon }} text-sm"></i>
    @endif
    @if ($label)
        <span class="hidden text-xs sm:inline">{{ $label }}</span>
    @endif
    <strong x-text="{{ $bind }}"></strong>
</div>