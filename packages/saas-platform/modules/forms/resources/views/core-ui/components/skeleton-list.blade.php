@props([
    'rows'    => 5,
    'columns' => 4,
    'avatar'  => false,
])

<div {{ $attributes->merge(['class' => 'space-y-2']) }} aria-busy="true" aria-label="در حال بارگذاری...">
    @for($i = 0; $i < $rows; $i++)
    <div class="flex items-center gap-3 p-3 rounded-xl bg-white/60 border border-gray-100">
        @if($avatar)
        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-gray-200 to-gray-100 animate-pulse shrink-0"></div>
        @endif
        <div class="flex-1 grid gap-2" style="grid-template-columns: repeat({{ $columns }}, 1fr)">
            @for($j = 0; $j < $columns; $j++)
            <div class="h-3.5 rounded-full animate-pulse"
                 style="background:linear-gradient(90deg,#f0f0f0 25%,#e8e8e8 50%,#f0f0f0 75%);background-size:200% 100%;animation:skeleton-wave 1.4s ease infinite;animation-delay:{{ $j * 0.1 }}s;opacity:{{ 1 - ($j * 0.15) }}"></div>
            @endfor
        </div>
    </div>
    @endfor
</div>
<style>
@keyframes skeleton-wave{0%{background-position:200% 0}100%{background-position:-200% 0}}
</style>
