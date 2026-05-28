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
$base = 'rounded-[var(--ui-admin-radius)] border border-[var(--ui-border)]';
$surface = $glass
    ? 'bg-[var(--ui-glass-bg)] backdrop-blur-[var(--ui-glass-blur)] border-[var(--ui-glass-border)]'
    : 'bg-[var(--ui-surface)]';
$shadow  = 'shadow-[var(--ui-shadow-card)]';
$hoverCls = $hover ? 'transition-all duration-[var(--ui-duration-moderate)] hover:-translate-y-0.5 hover:shadow-[var(--ui-shadow-card-hover)]' : '';
$cls = implode(' ', array_filter([$base, $surface, $shadow, $hoverCls, $pads[$padding] ?? $pads['md']]));
@endphp

<div {{ $attributes->class([$cls]) }}>
    {{ $slot }}
</div>
