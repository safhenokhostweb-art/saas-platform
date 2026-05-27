{{--
  Core-UI Confirm Modal — Phase 9.2
  Replaces native confirm() with an accessible Alpine-powered dialog.

  Usage:
    <x-core-ui::confirm-modal
      :show="showDeleteConfirm"
      title="حذف فرم"
      message="آیا مطمئن هستید؟ این عملیات غیرقابل بازگشت است."
      confirm-label="بله، حذف شود"
      cancel-label="انصراف"
      variant="danger"
      @confirmed="deleteItem"
    />

  Props: title, message, confirmLabel, cancelLabel, variant (danger|warning|info)
  Events: @confirmed, @cancelled
--}}
@props([
    'title'        => 'تأیید عملیات',
    'message'      => 'آیا از انجام این عملیات مطمئن هستید؟',
    'confirmLabel' => 'تأیید',
    'cancelLabel'  => 'انصراف',
    'variant'      => 'danger',
    'show'         => 'showConfirmModal',
])

@php
$confirmBtnClass = match($variant) {
    'warning' => 'bg-amber-600 hover:bg-amber-700 text-white',
    'info'    => 'bg-indigo-600 hover:bg-indigo-700 text-white',
    default   => 'bg-red-600 hover:bg-red-700 text-white',
};
$iconColor = match($variant) {
    'warning' => 'text-amber-600',
    'info'    => 'text-indigo-600',
    default   => 'text-red-600',
};
@endphp

<div
  x-show="{{ $show }}"
  x-transition:enter="transition ease-out duration-150"
  x-transition:enter-start="opacity-0"
  x-transition:enter-end="opacity-100"
  x-transition:leave="transition ease-in duration-100"
  x-transition:leave-start="opacity-100"
  x-transition:leave-end="opacity-0"
  @keydown.escape.window="{{ $show }} = false"
  class="fixed inset-0 z-[200] flex items-center justify-center p-4"
  role="alertdialog"
  aria-modal="true"
  aria-labelledby="confirm-title"
  aria-describedby="confirm-desc"
  style="display:none"
>
  {{-- Backdrop --}}
  <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="{{ $show }} = false" aria-hidden="true"></div>

  {{-- Panel --}}
  <div
    x-transition:enter="transition ease-out duration-150"
    x-transition:enter-start="opacity-0 scale-95"
    x-transition:enter-end="opacity-100 scale-100"
    class="relative w-full max-w-sm bg-white rounded-2xl shadow-xl ring-1 ring-black/5 z-10 p-6"
  >
    <div class="flex items-start gap-4">
      {{-- Icon --}}
      <div class="shrink-0 w-10 h-10 rounded-full flex items-center justify-center
        @if($variant === 'danger') bg-red-50
        @elseif($variant === 'warning') bg-amber-50
        @else bg-indigo-50 @endif">
        @if($variant === 'danger')
        <svg class="w-5 h-5 {{ $iconColor }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
        </svg>
        @elseif($variant === 'warning')
        <svg class="w-5 h-5 {{ $iconColor }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0zm-9 3.75h.008v.008H12v-.008z"/>
        </svg>
        @else
        <svg class="w-5 h-5 {{ $iconColor }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0zm-9-3.75h.008v.008H12V8.25z"/>
        </svg>
        @endif
      </div>

      {{-- Text --}}
      <div class="flex-1 min-w-0">
        <h3 id="confirm-title" class="font-semibold text-slate-800 mb-1">{{ $title }}</h3>
        <p id="confirm-desc" class="text-sm text-slate-500">{{ $message }}</p>
      </div>
    </div>

    {{-- Actions --}}
    <div class="flex items-center justify-end gap-3 mt-6">
      <button
        type="button"
        @click="{{ $show }} = false; $dispatch('cancelled')"
        class="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:outline-none"
      >
        {{ $cancelLabel }}
      </button>
      <button
        type="button"
        @click="{{ $show }} = false; $dispatch('confirmed')"
        class="px-4 py-2 text-sm font-medium {{ $confirmBtnClass }} rounded-xl transition active:scale-95 focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
        :class="{{ $variant === 'danger' ? '\'focus-visible:ring-red-500\'' : '\'focus-visible:ring-indigo-500\'' }}"
      >
        {{ $confirmLabel }}
      </button>
    </div>
  </div>
</div>
