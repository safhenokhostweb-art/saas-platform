@props([
    'status'  => 'online', // online | offline | busy | away
    'label'   => null,
    'pulse'   => true,
    'size'    => 'sm', // xs | sm | md
])
@php
$colors = [
    'online'  => ['dot' => 'bg-emerald-500', 'ring' => 'bg-emerald-400', 'text' => 'text-emerald-700', 'bg' => 'bg-emerald-50 border-emerald-200'],
    'offline' => ['dot' => 'bg-gray-400',    'ring' => 'bg-gray-300',    'text' => 'text-gray-500',    'bg' => 'bg-gray-50 border-gray-200'],
    'busy'    => ['dot' => 'bg-red-500',      'ring' => 'bg-red-400',     'text' => 'text-red-700',     'bg' => 'bg-red-50 border-red-200'],
    'away'    => ['dot' => 'bg-amber-500',    'ring' => 'bg-amber-400',   'text' => 'text-amber-700',   'bg' => 'bg-amber-50 border-amber-200'],
];
$c       = $colors[$status] ?? $colors['offline'];
$sizes   = ['xs' => 'w-1.5 h-1.5', 'sm' => 'w-2 h-2', 'md' => 'w-2.5 h-2.5'];
$dotSize = $sizes[$size] ?? $sizes['sm'];
$labels  = ['online' => 'آنلاین', 'offline' => 'آفلاین', 'busy' => 'مشغول', 'away' => 'دور'];
$display = $label ?? $labels[$status] ?? $status;
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full border text-xs font-semibold {$c['bg']} {$c['text']}"]) }}>
    <span class="relative flex {{ $dotSize }} items-center justify-center">
        @if($pulse && $status === 'online')
        <span class="animate-ping absolute inline-flex h-full w-full rounded-full {{ $c['ring'] }} opacity-50"></span>
        @endif
        <span class="relative inline-flex rounded-full {{ $dotSize }} {{ $c['dot'] }}"></span>
    </span>
    {{ $display }}
</span>
