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
    'success' => 'bg-[var(--ui-success-bg)] text-[var(--ui-success-text)] border-[var(--ui-success-border)]',
    'danger'  => 'bg-[var(--ui-danger-bg)]  text-[var(--ui-danger-text)]  border-[var(--ui-danger-border)]',
    'warning' => 'bg-[var(--ui-warning-bg)] text-[var(--ui-warning-text)] border-[var(--ui-warning-border)]',
    'info'    => 'bg-[var(--ui-info-bg)]    text-[var(--ui-info-text)]    border-[var(--ui-info-border)]',
    'neutral' => 'bg-[var(--ui-neutral-bg)] text-[var(--ui-neutral-text)] border-[var(--ui-neutral-border)]',
    'primary' => 'bg-[var(--ui-indigo-light)] text-[var(--ui-indigo)] border-[var(--ui-indigo)]',
    'purple'  => 'bg-[var(--ui-purple-light)] text-[var(--ui-purple)] border-purple-200',
];

$dotColors = [
    'success' => 'bg-[var(--ui-success)]',
    'danger'  => 'bg-[var(--ui-danger)]',
    'warning' => 'bg-[var(--ui-warning)]',
    'info'    => 'bg-[var(--ui-info)]',
    'neutral' => 'bg-[var(--ui-neutral)]',
    'primary' => 'bg-[var(--ui-indigo)]',
    'purple'  => 'bg-[var(--ui-purple)]',
];

$sizes = [
    'sm' => 'px-1.5 py-0.5 text-[0.625rem] gap-1',
    'md' => 'px-2.5 py-1 text-xs gap-1.5',
];

$radius = $pill ? 'rounded-[var(--ui-radius-full)]' : 'rounded-[var(--ui-radius-md)]';
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
