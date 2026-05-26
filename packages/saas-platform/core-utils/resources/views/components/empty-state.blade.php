@props([
    'title'       => 'موردی یافت نشد.',
    'description' => '',
    'actionLabel' => null,
    'actionHref'  => null,
    'icon'        => 'inbox',  // inbox | search | document
])
@php
    $iconPath = match($icon) {
        'search'   => 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
        'document' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        default    => 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4',
    };
@endphp
<div class="flex flex-col items-center justify-center py-16 text-center">
    <div class="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center mb-4">
        <svg class="w-7 h-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $iconPath }}"/>
        </svg>
    </div>
    <h3 class="text-sm font-semibold text-gray-700 mb-1">{{ $title }}</h3>
    @if($description)
        <p class="text-xs text-gray-400 max-w-xs">{{ $description }}</p>
    @endif
    @if($actionLabel && $actionHref)
        <a href="{{ $actionHref }}"
           class="mt-4 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition">
            {{ $actionLabel }}
        </a>
    @endif
    {{ $slot }}
</div>
