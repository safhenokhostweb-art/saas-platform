{{--
  Core-UI Badge — Phase 9.2
  Usage: <x-core-ui::badge color="indigo" size="sm">متن</x-core-ui::badge>
  Props: color (indigo|emerald|amber|red|slate|purple), size (xs|sm|md), :dot (bool)
--}}
@props([
    'color' => 'slate',
    'size'  => 'sm',
    'dot'   => false,
])

@php
$colorClass = match($color) {
    'indigo'  => 'bg-indigo-50 text-indigo-700 border-indigo-200',
    'emerald' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'amber'   => 'bg-amber-50 text-amber-700 border-amber-200',
    'red'     => 'bg-red-50 text-red-700 border-red-200',
    'purple'  => 'bg-purple-50 text-purple-700 border-purple-200',
    'blue'    => 'bg-blue-50 text-blue-700 border-blue-200',
    default   => 'bg-slate-50 text-slate-600 border-slate-200',
};
$sizeClass = match($size) {
    'xs' => 'px-1.5 py-0.5 text-[11px]',
    'md' => 'px-3 py-1 text-sm',
    default => 'px-2 py-0.5 text-xs',
};
$dotColor = match($color) {
    'indigo'  => 'bg-indigo-500',
    'emerald' => 'bg-emerald-500',
    'amber'   => 'bg-amber-500',
    'red'     => 'bg-red-500',
    'purple'  => 'bg-purple-500',
    default   => 'bg-slate-400',
};
@endphp

<span class="inline-flex items-center gap-1 {{ $sizeClass }} {{ $colorClass }} font-medium rounded-full border {{ $attributes->get('class') }}">
  @if($dot)
    <span class="w-1.5 h-1.5 rounded-full {{ $dotColor }}"></span>
  @endif
  {{ $slot }}
</span>
