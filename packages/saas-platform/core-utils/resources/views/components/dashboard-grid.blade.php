{{--
  x-core-ui::dashboard-grid — Canonical responsive dashboard grid layout.
  Props:
    cols : int — max columns (default: 3)
--}}
@props([
    'cols' => 3,
])

@php
$colClass = match((int)$cols) {
    1 => 'grid-cols-1',
    2 => 'grid-cols-1 sm:grid-cols-2',
    4 => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
    default => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
};
@endphp

<div {{ $attributes->class(["grid {$colClass} gap-(--ui-card-gap)"]) }}>
    {{ $slot }}
</div>
