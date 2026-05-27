{{--
  Core-UI Modal — Phase 9.2
  Usage:
    <x-core-ui::modal wire:model="showModal" title="عنوان">
      ... content ...
      <x-slot name="footer">
        <button>تأیید</button>
      </x-slot>
    </x-core-ui::modal>

  Alpine control: pass x-show state from parent, or use the open/close pattern.
  Props: title, size (sm|md|lg|xl), :show (boolean expression)
--}}
@props([
    'title'   => '',
    'size'    => 'md',
    'show'    => null,
])

@php
$sizeClasses = match($size) {
    'sm'  => 'max-w-sm',
    'lg'  => 'max-w-2xl',
    'xl'  => 'max-w-4xl',
    default => 'max-w-lg',
};
@endphp

<div
  x-show="{{ $show ?? '$store.modal.open' }}"
  x-transition:enter="transition ease-out duration-200"
  x-transition:enter-start="opacity-0"
  x-transition:enter-end="opacity-100"
  x-transition:leave="transition ease-in duration-150"
  x-transition:leave-start="opacity-100"
  x-transition:leave-end="opacity-0"
  class="fixed inset-0 z-[200] flex items-center justify-center p-4"
  role="dialog"
  aria-modal="true"
  @if($title) aria-labelledby="modal-title" @endif
  style="display:none"
>
  {{-- Backdrop --}}
  <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="{{ $show ? ($show . ' = false') : '' }}" aria-hidden="true"></div>

  {{-- Panel --}}
  <div
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 scale-95"
    x-transition:enter-end="opacity-100 scale-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 scale-100"
    x-transition:leave-end="opacity-0 scale-95"
    class="relative w-full {{ $sizeClasses }} bg-white rounded-2xl shadow-xl ring-1 ring-black/5 z-10"
  >
    {{-- Header --}}
    @if($title)
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
      <h3 id="modal-title" class="font-semibold text-slate-800 text-base">{{ $title }}</h3>
      <button
        type="button"
        @click="{{ $show ?? '' }} = false"
        class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:outline-none"
        aria-label="بستن"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>
    @endif

    {{-- Body --}}
    <div class="px-6 py-5">
      {{ $slot }}
    </div>

    {{-- Footer --}}
    @if(isset($footer))
    <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-slate-50 rounded-b-2xl">
      {{ $footer }}
    </div>
    @endif
  </div>
</div>
