{{--
  Core-UI Toolbar — Phase 9.2
  A sticky action toolbar for list pages.
  Usage:
    <x-core-ui::toolbar :sticky="true">
      <x-slot name="left">
        <input type="search" ...>
      </x-slot>
      <x-slot name="right">
        <button>...</button>
      </x-slot>
    </x-core-ui::toolbar>
  Props: :sticky (bool)
--}}
@props(['sticky' => false])

<div class="flex flex-wrap items-center gap-3 px-0 py-3 {{ $sticky ? 'sticky top-0 z-10 bg-white border-b border-slate-100 -mx-6 px-6 mb-4' : 'mb-4' }}">
  @if(isset($left))
    <div class="flex items-center gap-3 flex-1 min-w-0">{{ $left }}</div>
  @endif
  @if(isset($right))
    <div class="flex items-center gap-2 shrink-0">{{ $right }}</div>
  @endif
</div>
