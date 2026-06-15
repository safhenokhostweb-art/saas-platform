{{--
  x-core-ui::alert — Canonical alert / notification banner.
  Props:
    variant     : success | danger | warning | info (default: info)
    title       : string — optional bold title
    dismissible : bool — show close button
    icon        : bool — show variant icon (default: true)
--}}
@props([
    'variant'     => 'info',
    'title'       => null,
    'dismissible' => false,
    'icon'        => true,
])

@php
$variants = [
    'success' => ['bg' => 'bg-(--ui-success-light)', 'border' => 'border-(--ui-success-border)', 'text' => 'text-(--ui-success-text)', 'icon_color' => 'text-(--ui-success)'],
    'danger'  => ['bg' => 'bg-(--ui-danger-light)',  'border' => 'border-(--ui-danger-border)',  'text' => 'text-(--ui-danger-text)',  'icon_color' => 'text-(--ui-danger)'],
    'warning' => ['bg' => 'bg-(--ui-warning-light)', 'border' => 'border-(--ui-warning-border)', 'text' => 'text-(--ui-warning-text)', 'icon_color' => 'text-(--ui-warning)'],
    'info'    => ['bg' => 'bg-(--ui-info-light)',    'border' => 'border-(--ui-info-border)',    'text' => 'text-(--ui-info-text)',    'icon_color' => 'text-(--ui-info)'],
];

$icons = [
    'success' => '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>',
    'danger'  => '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/>',
    'warning' => '<path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>',
    'info'    => '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd"/>',
];

$v = $variants[$variant] ?? $variants['info'];
@endphp

<div x-data="{ visible: true }"
     x-show="visible"
     x-transition:leave="transition ease-(--ui-ease-default) duration-(--ui-duration-normal)"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     role="alert"
     {{ $attributes->class(["flex gap-3 rounded-(--ui-radius-xl) border p-4 {$v['bg']} {$v['border']} {$v['text']}"]) }}>

    @if($icon)
        <svg class="w-5 h-5 shrink-0 mt-0.5 {{ $v['icon_color'] }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            {!! $icons[$variant] ?? $icons['info'] !!}
        </svg>
    @endif

    <div class="flex-1 min-w-0">
        @if($title)
            <p class="text-sm font-semibold mb-1">{{ $title }}</p>
        @endif
        <div class="text-sm">{{ $slot }}</div>
    </div>

    @if($dismissible)
        <button type="button"
                @click="visible = false"
                class="shrink-0 -mt-0.5 -mr-1 rounded-(--ui-radius-md) p-1
                       hover:bg-black/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-current
                       transition-colors duration-(--ui-duration-fast)"
                aria-label="بستن هشدار">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
            </svg>
        </button>
    @endif
</div>
