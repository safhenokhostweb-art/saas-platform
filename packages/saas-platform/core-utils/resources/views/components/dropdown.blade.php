{{--
  x-core-ui::dropdown — Canonical dropdown menu (Alpine.js powered).
  Usage:
    <x-core-ui::dropdown>
      <x-slot name="trigger">
        <x-core-ui::button>Actions</x-core-ui::button>
      </x-slot>
      <x-core-ui::dropdown-item href="/edit">Edit</x-core-ui::dropdown-item>
      <x-core-ui::dropdown-item variant="danger" @click="confirmDelete()">Delete</x-core-ui::dropdown-item>
    </x-core-ui::dropdown>

  Props:
    align  : right | left (default: right)
    width  : string Tailwind width class (default: w-48)
--}}
@props([
    'align' => 'right',
    'width' => 'w-48',
])

@php
$alignCls = $align === 'left' ? 'right-0' : 'left-0';
@endphp

<div class="relative inline-block" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
    {{-- Trigger --}}
    <div @click="open = !open" class="cursor-pointer">
        {{ $trigger }}
    </div>

    {{-- Panel --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-(--ui-duration-fast)"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-(--ui-duration-fast)"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="{{ $alignCls }} {{ $width }} absolute top-full mt-1.5 z-(--ui-z-dropdown) origin-top-right bg-(--ui-surface) border border-(--ui-border) rounded-(--ui-radius-xl) shadow-lg-ui overflow-hidden py-1"
        style="display:none;"
        @click="open = false"
    >
        {{ $slot }}
    </div>
</div>
