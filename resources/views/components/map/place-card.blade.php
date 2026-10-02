@props(['skeleton' => false, 'withButton' => true])

@if ($skeleton)
    <div class="flex flex-col gap-3">
        <div class="flex items-center gap-2">
            <div class="skeleton h-5 w-16 rounded-full"></div>
            <div class="skeleton h-5 w-20 rounded-full"></div>
        </div>
        <div class="skeleton h-6 w-3/4"></div>
        <div class="skeleton h-4 w-1/2"></div>
        <div class="skeleton h-4 w-2/3"></div>
        @if ($withButton)
            <div class="skeleton mt-1 h-9 w-full rounded-full"></div>
        @endif
    </div>
@else
    {{ $slot }}
@endif