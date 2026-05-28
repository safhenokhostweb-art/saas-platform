{{--
  x-core-ui::sidebar-group — Canonical collapsible sidebar section.
  Groups navigation items under a labelled section.

  Props:
    label       : string — section heading
    collapsible : bool — enable collapse toggle (default: false)
    collapsed   : bool — initially collapsed (default: false)
    icon        : slot — optional section icon
--}}
@props([
    'label'       => null,
    'collapsible' => false,
    'collapsed'   => false,
])

@php
$groupId = 'sidebar-group-' . md5($label ?? uniqid());
@endphp

@if($collapsible)
<div x-data="{ open: {{ $collapsed ? 'false' : 'true' }} }">
    @if($label)
    <button
        type="button"
        @click="open = !open"
        class="w-full flex items-center justify-between px-3 py-1.5 text-[10px] font-bold uppercase tracking-widest text-[var(--ui-sidebar-text)] opacity-60 hover:opacity-90 transition select-none mb-1"
    >
        <span class="flex items-center gap-2">
            @if(isset($icon))<span class="w-3.5 h-3.5">{{ $icon }}</span>@endif
            {{ $label }}
        </span>
        <svg class="w-3 h-3 transition-transform duration-150" :class="open ? '' : '-rotate-90'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
        </svg>
    </button>
    @endif
    <div x-show="open" x-collapse class="space-y-0.5">
        {{ $slot }}
    </div>
</div>
@else
<div class="mb-4">
    @if($label)
    <p class="px-3 py-1.5 text-[10px] font-bold uppercase tracking-widest text-[var(--ui-sidebar-text)] opacity-50 mb-1 select-none">
        @if(isset($icon))<span class="inline-block w-3.5 h-3.5 mr-1.5 align-middle">{{ $icon }}</span>@endif
        {{ $label }}
    </p>
    @endif
    <div class="space-y-0.5">
        {{ $slot }}
    </div>
</div>
@endif
