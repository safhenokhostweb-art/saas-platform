{{--
  x-core-ui::badge — Canonical status/category badge.
  Props:
    variant : success | danger | warning | info | neutral | primary | purple (default: neutral)
    size    : sm | md (default: md)
    dot     : bool — show colored dot before label
    pill    : bool — force pill/full-radius style
--}}
@props([
    'variant' => 'neutral',
    'size'    => 'md',
    'dot'     => false,
    'pill'    => true,
])

@php
$variants = [
    'success' => 'bg-(--ui-success-bg) text-(--ui-success-text) border-(--ui-success-border)',
    'danger'  => 'bg-(--ui-danger-bg)  text-(--ui-danger-text)  border-(--ui-danger-border)',
    'warning' => 'bg-(--ui-warning-bg) text-(--ui-warning-text) border-(--ui-warning-border)',
    'info'    => 'bg-(--ui-info-bg)    text-(--ui-info-text)    border-(--ui-info-border)',
    'neutral' => 'bg-(--ui-neutral-bg) text-(--ui-neutral-text) border-(--ui-neutral-border)',
    'primary' => 'bg-(--ui-indigo-light) text-(--ui-indigo) border-(--ui-indigo)',
    'purple'  => 'bg-(--ui-purple-light) text-(--ui-purple) border-purple-200',
];

$dotColors = [
    'success' => 'bg-(--ui-success)',
    'danger'  => 'bg-(--ui-danger)',
    'warning' => 'bg-(--ui-warning)',
    'info'    => 'bg-(--ui-info)',
    'neutral' => 'bg-(--ui-neutral)',
    'primary' => 'bg-(--ui-indigo)',
    'purple'  => 'bg-(--ui-purple)',
];

$sizes = [
    'sm' => 'px-1.5 py-0.5 text-[0.625rem] gap-1',
    'md' => 'px-2.5 py-1 text-xs gap-1.5',
];

$radius = $pill ? 'rounded-(--ui-radius-full)' : 'rounded-(--ui-radius-md)';
$cls = implode(' ', [
    'inline-flex items-center font-semibold border whitespace-nowrap',
    $radius,
    $variants[$variant] ?? $variants['neutral'],
    $sizes[$size] ?? $sizes['md'],
]);
@endphp

<span {{ $attributes->class([$cls]) }}>
    @if($dot)
        <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $dotColors[$variant] ?? $dotColors['neutral'] }}"></span>
    @endif
    {{ $slot }}
</span>
