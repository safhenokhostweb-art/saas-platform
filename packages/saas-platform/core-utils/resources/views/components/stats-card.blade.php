@props([
    'label'   => '',
    'value'   => '0',
    'icon'    => null,
    'color'   => 'blue',    // blue | green | yellow | red | gray | purple
    'trend'   => null,      // null | 'up' | 'down'
    'change'  => null,      // e.g. '+12%'
    'href'    => null,
])
@php
    $colorMap = [
        'blue'   => ['bg' => 'bg-blue-50',   'icon' => 'text-blue-600',   'val' => 'text-blue-700'],
        'green'  => ['bg' => 'bg-green-50',  'icon' => 'text-green-600',  'val' => 'text-green-700'],
        'yellow' => ['bg' => 'bg-yellow-50', 'icon' => 'text-yellow-600', 'val' => 'text-yellow-700'],
        'red'    => ['bg' => 'bg-red-50',    'icon' => 'text-red-600',    'val' => 'text-red-700'],
        'gray'   => ['bg' => 'bg-gray-50',   'icon' => 'text-gray-500',   'val' => 'text-gray-700'],
        'purple' => ['bg' => 'bg-purple-50', 'icon' => 'text-purple-600', 'val' => 'text-purple-700'],
    ];
    $c = $colorMap[$color] ?? $colorMap['blue'];
@endphp
<div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 {{ $href ? 'hover:shadow-md transition-shadow cursor-pointer' : '' }}"
     @if($href) onclick="window.location='{{ $href }}'" @endif>
    <div class="flex items-center justify-between mb-3">
        <p class="text-xs font-medium text-gray-500">{{ $label }}</p>
        @if($icon)
            <div class="w-8 h-8 rounded-lg {{ $c['bg'] }} flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 {{ $c['icon'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    {!! $icon !!}
                </svg>
            </div>
        @endif
    </div>
    <p class="text-2xl font-bold {{ $c['val'] }}">{{ $value }}</p>
    @if($change)
        <div class="flex items-center gap-1 mt-1">
            @if($trend === 'up')
                <svg class="w-3 h-3 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                </svg>
            @elseif($trend === 'down')
                <svg class="w-3 h-3 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                </svg>
            @endif
            <span class="text-xs {{ $trend === 'up' ? 'text-green-600' : ($trend === 'down' ? 'text-red-600' : 'text-gray-500') }}">
                {{ $change }}
            </span>
        </div>
    @endif
</div>
