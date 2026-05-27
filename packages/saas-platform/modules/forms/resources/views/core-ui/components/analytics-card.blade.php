@props([
    'label'  => '',
    'value'  => '0',
    'trend'  => null,     // null | 'up' | 'down' | 'neutral'
    'color'  => 'indigo', // indigo | emerald | amber | slate | purple
    'size'   => 'md',     // sm | md
])
@php
    $colorMap = [
        'indigo'  => 'text-indigo-600',
        'emerald' => 'text-emerald-600',
        'amber'   => 'text-amber-500',
        'slate'   => 'text-slate-700',
        'purple'  => 'text-purple-600',
    ];
    $textColor = $colorMap[$color] ?? $colorMap['indigo'];
    $valueSize = $size === 'sm' ? 'text-xl' : 'text-2xl';
@endphp
<div {{ $attributes->merge(['class' => 'bg-white rounded-xl border border-slate-200 p-4 text-center hover:border-indigo-200 transition']) }}>
    <p class="{{ $valueSize }} font-bold {{ $textColor }}">{{ $value }}</p>
    <p class="text-xs text-slate-500 mt-0.5">{{ $label }}</p>
    @if($trend)
    <div class="flex items-center justify-center gap-1 mt-1">
        @if($trend === 'up')
            <svg class="w-3 h-3 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5"/></svg>
            <span class="text-[10px] text-emerald-600">{{ $slot }}</span>
        @elseif($trend === 'down')
            <svg class="w-3 h-3 text-red-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
            <span class="text-[10px] text-red-500">{{ $slot }}</span>
        @else
            <span class="text-[10px] text-slate-400">{{ $slot }}</span>
        @endif
    </div>
    @endif
</div>
