{{--
  x-core-ui::content-container — Canonical page content wrapper.
  Enforces max-width, padding, and responsive spacing.
  Props:
    narrow : bool — use narrow max-width for forms/detail pages
    flush  : bool — no padding (use when child components handle their own padding)
--}}
@props([
    'narrow' => false,
    'flush'  => false,
])

<div {{ $attributes->class([
    'mx-auto w-full',
    $narrow ? 'max-w-(--ui-content-max-width-narrow)' : 'max-w-(--ui-content-max-width)',
    $flush  ? '' : 'px-4 sm:px-6 py-6',
]) }}>
    {{ $slot }}
</div>
