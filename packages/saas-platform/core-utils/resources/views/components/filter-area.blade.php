{{--
  x-core-ui::filter-area — Canonical collapsible filter panel.
  Props:
    open       : bool   — start expanded (default: false)
    label      : string — filter toggle button label
    count      : int    — active filter count (shows badge)
    collapsible: bool   — show expand/collapse toggle (default: true)
--}}
@props([
    'open'        => false,
    'label'       => 'فیلترها',
    'count'       => 0,
    'collapsible' => true,
])

<div {{ $attributes->class(['mb-4']) }}
     x-data="{ open: {{ $open ? 'true' : 'false' }} }">

    @if($collapsible)
        <button type="button"
                @click="open = !open"
                class="flex items-center gap-2 text-sm font-medium text-(--ui-text-secondary)
                       hover:text-(--ui-text-primary) transition-colors duration-(--ui-duration-fast)
                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-(--ui-focus-ring-color) rounded-lg px-1 py-0.5"
                :aria-expanded="open">
            <svg class="w-4 h-4 transition-transform duration-(--ui-duration-normal)"
                 :class="open ? 'rotate-180' : ''"
                 fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
            </svg>
            {{ $label }}
            @if($count > 0)
                <x-core-ui::badge variant="primary" size="sm">{{ $count }}</x-core-ui::badge>
            @endif
        </button>

        <div x-show="open"
             x-collapse
             x-transition:enter="transition ease-(--ui-ease-out) duration-(--ui-duration-moderate)"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-(--ui-ease-default) duration-(--ui-duration-fast)"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div class="pt-3 pb-2">
                {{ $slot }}
            </div>
        </div>
    @else
        {{ $slot }}
    @endif
</div>
