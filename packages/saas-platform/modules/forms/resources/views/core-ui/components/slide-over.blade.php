@props([
    'title'    => '',
    'size'     => 'md',  // sm | md | lg | xl
    'show'     => false,
    'position' => 'right', // right | left
])
@php
$widths = ['sm' => 'max-w-sm', 'md' => 'max-w-md', 'lg' => 'max-w-lg', 'xl' => 'max-w-xl'];
$width  = $widths[$size] ?? $widths['md'];
$enter  = $position === 'left' ? '-translate-x-full' : 'translate-x-full';
@endphp

<div x-data="{ open: @js($show) }"
     x-on:open-slide-over.window="$event.detail?.id === $el.id && (open = true)"
     x-on:close-slide-over.window="open = false"
     x-on:keydown.escape.window="open = false"
     {{ $attributes->except(['title', 'size', 'show', 'position']) }}>

    {{ $slot }}

    {{-- Overlay --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm z-40"
         @click="open = false"
         style="display:none"
         aria-hidden="true"></div>

    {{-- Panel --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="{{ $enter }} opacity-0"
         x-transition:enter-end="translate-x-0 opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-x-0 opacity-100"
         x-transition:leave-end="{{ $enter }} opacity-0"
         class="fixed top-0 {{ $position }}-0 h-full {{ $width }} w-full bg-white shadow-2xl z-50 flex flex-col"
         role="dialog" aria-modal="true" aria-labelledby="slide-over-title"
         style="display:none">

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 bg-gradient-to-r from-white to-gray-50/50 shrink-0">
            <h2 id="slide-over-title" class="text-base font-bold text-gray-900">{{ $title }}</h2>
            <button @click="open = false"
                    class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors"
                    aria-label="بستن">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Body --}}
        <div class="flex-1 overflow-y-auto p-5 scrollbar-thin">
            @if(isset($body))
                {{ $body }}
            @endif
        </div>

        {{-- Footer --}}
        @if(isset($footer))
        <div class="shrink-0 px-5 py-4 border-t border-gray-100 bg-gray-50/50">
            {{ $footer }}
        </div>
        @endif

    </div>
</div>
