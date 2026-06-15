{{--
  x-core-ui::card — Canonical card container.
  Props:
    padding  : none | sm | md | lg (default: md)
    hover    : bool — enable hover lift effect (default: false)
    glass    : bool — glass morphism surface (default: false)
    class    : extra classes
--}}
@props([
    'padding' => 'md',
    'hover'   => false,
    'glass'   => false,
])

@php
$pads = ['none' => '', 'sm' => 'p-4', 'md' => 'p-5', 'lg' => 'p-6 md:p-8'];
$base = 'rounded-(--ui-admin-radius) border border-(--ui-border)';
$surface = $glass
    ? 'bg-(--ui-glass-bg) backdrop-blur-(--ui-glass-blur) border-(--ui-glass-border)'
    : 'bg-(--ui-surface)';
$shadow  = 'shadow-card';
$hoverCls = $hover ? 'transition-all duration-(--ui-duration-moderate) hover:-translate-y-0.5 hover:shadow-card-hover' : '';
$cls = implode(' ', array_filter([$base, $surface, $shadow, $hoverCls, $pads[$padding] ?? $pads['md']]));
@endphp

<div {{ $attributes->class([$cls]) }}>
    {{ $slot }}
</div>
