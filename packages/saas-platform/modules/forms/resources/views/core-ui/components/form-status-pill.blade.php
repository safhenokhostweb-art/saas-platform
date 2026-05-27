@props([
    'active' => false,
    'size'   => 'sm',  // sm | md
])
@php
    $sizeClasses = $size === 'md'
        ? 'px-3 py-1 text-sm'
        : 'px-2 py-0.5 text-xs';
@endphp
<span {{ $attributes->merge([
    'class' => 'inline-flex items-center gap-1.5 rounded-full font-medium ' . $sizeClasses
        . ($active ? ' bg-emerald-100 text-emerald-700' : ' bg-slate-100 text-slate-500'),
]) }}>
    <span class="w-1.5 h-1.5 rounded-full {{ $active ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400' }}"></span>
    {{ $active ? 'فعال' : 'غیرفعال' }}
</span>
