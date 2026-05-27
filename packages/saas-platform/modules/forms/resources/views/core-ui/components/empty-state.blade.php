@props([
    'icon'        => null,
    'title'       => '',
    'description' => '',
    'size'        => 'md',   // sm | md | lg
])
@php
    $paddings = ['sm' => 'p-10', 'md' => 'p-16', 'lg' => 'p-20'];
    $iconSizes = ['sm' => 'w-12 h-12', 'md' => 'w-16 h-16', 'lg' => 'w-20 h-20'];
    $innerSizes = ['sm' => 'w-6 h-6', 'md' => 'w-8 h-8', 'lg' => 'w-10 h-10'];
    $pad = $paddings[$size] ?? $paddings['md'];
    $iconSz = $iconSizes[$size] ?? $iconSizes['md'];
    $innerSz = $innerSizes[$size] ?? $innerSizes['md'];
@endphp
<div {{ $attributes->merge(['class' => 'text-center ' . $pad]) }}>
    @if($icon)
    <div class="{{ $iconSz }} mx-auto mb-5 rounded-2xl bg-indigo-50 flex items-center justify-center">
        <svg class="{{ $innerSz }} text-indigo-300" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
            {!! $icon !!}
        </svg>
    </div>
    @endif

    @if($title)
    <h3 class="text-lg font-bold text-slate-700 mb-2">{{ $title }}</h3>
    @endif

    @if($description)
    <p class="text-slate-400 text-sm mb-6">{{ $description }}</p>
    @endif

    @if($slot->isNotEmpty())
    <div class="flex items-center justify-center gap-3">
        {{ $slot }}
    </div>
    @endif
</div>
