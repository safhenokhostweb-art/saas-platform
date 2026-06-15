{{--
  x-core-ui::navigation-item — Canonical sidebar navigation link.
  Renders identically in saas-platform and customer-site.
  Visual differences (active, disabled) are controlled via props/capabilities only.

  Props:
    href      : string — link target
    active    : bool — active state (default: false)
    icon      : slot — leading icon (16×16 SVG)
    badge     : string|int — optional badge count
    disabled  : bool
    external  : bool — opens in new tab
--}}
@props([
    'href'     => '#',
    'active'   => false,
    'badge'    => null,
    'disabled' => false,
    'external' => false,
])

@php
$base    = 'group flex items-center gap-3 px-3 py-2 rounded-(--ui-radius-lg) text-sm font-medium transition-all duration-(--ui-duration-fast) select-none';
$active  = $active
    ? 'bg-(--ui-sidebar-item-active) text-(--ui-sidebar-text-active) shadow-sm'
    : 'text-(--ui-sidebar-text) hover:bg-(--ui-sidebar-item-hover) hover:text-(--ui-sidebar-text-active)';
$dis     = $disabled ? 'opacity-40 cursor-not-allowed pointer-events-none' : '';
@endphp

<a
    href="{{ $disabled ? '#' : $href }}"
    {{ $external ? 'target=_blank rel=noopener' : '' }}
    {{ $attributes->class([$base, $active, $dis]) }}
    @if($active) aria-current="page" @endif
>
    @if(isset($icon))
        <span class="shrink-0 w-(--ui-icon-md) h-(--ui-icon-md) transition-transform duration-(--ui-duration-fast) group-hover:scale-110">
            {{ $icon }}
        </span>
    @endif

    <span class="flex-1 truncate">{{ $slot }}</span>

    @if($badge)
        <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full text-[10px] font-bold bg-(--ui-indigo) text-white leading-none">
            {{ $badge > 99 ? '99+' : $badge }}
        </span>
    @endif
</a>
