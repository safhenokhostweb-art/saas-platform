{{--
  x-core-ui::dropdown-item — Row inside a dropdown.
  Props: href, variant (default | danger | warning)
--}}
@props([
    'href'    => null,
    'variant' => 'default',
])

@php
$variants = [
    'default' => 'text-[var(--ui-text-secondary)] hover:bg-slate-50 hover:text-[var(--ui-text-primary)]',
    'danger'  => 'text-[var(--ui-danger)] hover:bg-[var(--ui-danger-bg)]',
    'warning' => 'text-[var(--ui-warning)] hover:bg-[var(--ui-warning-bg)]',
];
$base = 'flex items-center gap-2.5 w-full px-4 py-2 text-sm font-medium transition text-right';
$cls  = $base . ' ' . ($variants[$variant] ?? $variants['default']);
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->class([$cls]) }}>{{ $slot }}</a>
@else
    <button type="button" {{ $attributes->class([$cls]) }}>{{ $slot }}</button>
@endif
