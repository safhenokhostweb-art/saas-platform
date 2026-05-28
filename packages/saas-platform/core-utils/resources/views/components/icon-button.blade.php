{{--
  x-core-ui::icon-button — Square icon-only button.
  Props: variant, size, type, disabled, href, loading, label (aria-label)
--}}
@props([
    'variant' => 'ghost',
    'size'    => 'md',
    'type'    => 'button',
    'disabled'=> false,
    'href'    => null,
    'loading' => false,
    'label'   => '',
])

@php
$sizes = ['sm' => 'p-1.5', 'md' => 'p-2', 'lg' => 'p-2.5'];
$variants = [
    'ghost'   => 'text-[var(--ui-text-subtle)] hover:bg-slate-100 hover:text-[var(--ui-text-secondary)]',
    'primary' => 'bg-[var(--ui-indigo)] text-white hover:bg-[var(--ui-indigo-hover)]',
    'danger'  => 'text-[var(--ui-danger)] hover:bg-[var(--ui-danger-bg)]',
];
$base = 'inline-flex items-center justify-center rounded-[var(--ui-radius-lg)] transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--ui-focus-ring-color)]';
$dis  = ($disabled || $loading) ? 'opacity-50 cursor-not-allowed pointer-events-none' : '';
$cls  = implode(' ', array_filter([$base, $sizes[$size] ?? $sizes['md'], $variants[$variant] ?? $variants['ghost'], $dis]));
@endphp

@if($href)
<a href="{{ $href }}" aria-label="{{ $label }}" {{ $attributes->class([$cls]) }}>
    {{ $slot }}
</a>
@else
<button type="{{ $type }}" aria-label="{{ $label }}" {{ $attributes->class([$cls]) }} @if($disabled||$loading) disabled @endif>
    @if($loading)
        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
    @else
        {{ $slot }}
    @endif
</button>
@endif
