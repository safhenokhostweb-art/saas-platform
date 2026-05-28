{{--
  x-core-ui::toolbar — Canonical page toolbar with search/filter left and actions right.
  Props:
    sticky : bool — stick to top on scroll (default: false)
    border : bool — show bottom border (default: true)
--}}
@props([
    'sticky' => false,
    'border' => true,
])

<div {{ $attributes->class([
    'flex flex-col sm:flex-row sm:items-center gap-3 py-3',
    $sticky ? 'sticky top-0 z-[var(--ui-z-sticky)] bg-[var(--ui-bg)] px-0 -mx-0' : '',
    $border ? 'border-b border-[var(--ui-border)] mb-4' : 'mb-4',
]) }}>

    {{-- Left / Search area --}}
    @if(isset($search))
        <div class="flex-1 min-w-0">
            {{ $search }}
        </div>
    @else
        <div class="flex-1 min-w-0">
            {{ $slot }}
        </div>
    @endif

    {{-- Right / Actions area --}}
    @if(isset($actions))
        <div class="flex items-center gap-2 shrink-0">
            {{ $actions }}
        </div>
    @endif
</div>
