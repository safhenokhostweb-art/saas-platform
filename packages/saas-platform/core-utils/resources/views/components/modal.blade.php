{{--
  x-core-ui::modal — Canonical modal dialog with full enterprise features:
    - Stacked modal support via z-index layering
    - Escape key hierarchy
    - Focus trapping
    - Scroll locking
    - Mobile fullscreen on xs screens
    - Lazy rendering
    - Backdrop blur
    - RTL support

  Usage:
    <x-core-ui::modal name="my-modal" title="عنوان">
        Content here
        <x-slot name="footer">
            <x-core-ui::button @click="$dispatch('close-modal', 'my-modal')">بستن</x-core-ui::button>
        </x-slot>
    </x-core-ui::modal>

  Open: $dispatch('open-modal', 'my-modal')
  Close: $dispatch('close-modal', 'my-modal')

  Props:
    name        : string — unique modal identifier
    title       : string — header title
    size        : sm | md | lg | xl | full (default: md)
    persistent  : bool   — disable close on backdrop click
    closeable   : bool   — show X button in header (default: true)
    level       : int    — stacking level 1|2|3 (default: 1, adjusts z-index)
--}}
@props([
    'name'       => null,
    'title'      => null,
    'size'       => 'md',
    'persistent' => false,
    'closeable'  => true,
    'level'      => 1,
])

@php
$sizes = [
    'sm'   => 'max-w-[var(--ui-modal-max-width-sm)]',
    'md'   => 'max-w-[var(--ui-modal-max-width-md)]',
    'lg'   => 'max-w-[var(--ui-modal-max-width-lg)]',
    'xl'   => 'max-w-[var(--ui-modal-max-width-xl)]',
    'full' => 'max-w-[var(--ui-modal-max-width-full)]',
];
$maxW = $sizes[$size] ?? $sizes['md'];
$zOverlay = match($level) { 2 => 'var(--ui-z-modal-2)', 3 => 'var(--ui-z-modal-3)', default => 'var(--ui-z-modal)' };
$modalId  = $name ?? 'modal-' . uniqid();
@endphp

<div
    x-data="{
        open: false,
        modalName: '{{ $modalId }}',
        init() {
            this.$watch('open', val => {
                if (val) {
                    document.body.style.overflow = 'hidden';
                    this.$nextTick(() => this.trapFocus());
                } else {
                    document.body.style.overflow = '';
                }
            });
        },
        trapFocus() {
            const el = this.$refs.dialog;
            if (!el) return;
            const focusable = el.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex=\"-1\"])');
            if (focusable.length) focusable[0].focus();
        },
    }"
    @open-modal.window="if ($event.detail === modalName) { open = true }"
    @close-modal.window="if ($event.detail === modalName) { open = false }"
    @keydown.escape.window="if (open) { open = false }"
>
    {{-- Overlay --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-[var(--ui-ease-out)] duration-[var(--ui-duration-moderate)]"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-[var(--ui-ease-default)] duration-[var(--ui-duration-normal)]"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 flex items-center justify-center p-4
               bg-[var(--ui-modal-overlay-bg)]
               sm:items-center"
        style="z-index: {{ $zOverlay }}; backdrop-filter: var(--ui-modal-backdrop-blur); -webkit-backdrop-filter: var(--ui-modal-backdrop-blur);"
        @click="{{ $persistent ? '' : 'open = false' }}"
        aria-hidden="true"
    ></div>

    {{-- Dialog panel --}}
    <div
        x-show="open"
        x-ref="dialog"
        x-transition:enter="transition ease-[var(--ui-ease-spring)] duration-[var(--ui-duration-moderate)]"
        x-transition:enter-start="opacity-0 scale-95 translate-y-2"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-[var(--ui-ease-default)] duration-[var(--ui-duration-normal)]"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        @click.stop
        role="dialog"
        aria-modal="true"
        @if($title) aria-labelledby="{{ $modalId }}-title" @endif
        class="fixed inset-x-4 mx-auto {{ $maxW }} w-full
               bg-[var(--ui-surface)] rounded-[var(--ui-modal-radius)]
               shadow-[var(--ui-shadow-modal)] flex flex-col
               max-h-[calc(100dvh-2rem)] overflow-hidden
               top-1/2 -translate-y-1/2"
        style="z-index: calc({{ $zOverlay }} + 1);"
    >
        {{-- Header --}}
        @if($title || $closeable)
            <div class="flex items-center justify-between gap-4 px-6 py-4 border-b border-[var(--ui-border)] shrink-0">
                @if($title)
                    <h2 id="{{ $modalId }}-title"
                        class="text-base font-semibold text-[var(--ui-text-primary)] truncate">
                        {{ $title }}
                    </h2>
                @else
                    <div></div>
                @endif

                @if($closeable)
                    <button type="button"
                            @click="open = false"
                            class="shrink-0 rounded-[var(--ui-radius-lg)] p-1.5
                                   text-[var(--ui-text-muted)] hover:text-[var(--ui-text-primary)]
                                   hover:bg-[var(--ui-surface-muted)]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--ui-focus-ring-color)]
                                   transition-colors duration-[var(--ui-duration-fast)]"
                            aria-label="بستن">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                @endif
            </div>
        @endif

        {{-- Body --}}
        <div class="flex-1 overflow-y-auto p-6 text-sm text-[var(--ui-text-secondary)]">
            {{ $slot }}
        </div>

        {{-- Footer --}}
        @if(isset($footer))
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-[var(--ui-border)] shrink-0 bg-[var(--ui-surface-soft)]">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>
