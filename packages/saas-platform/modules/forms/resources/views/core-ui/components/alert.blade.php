{{--
  Core-UI Alert — Phase 9.2
  Usage:
    <x-core-ui::alert type="info" :dismissible="true">
      پیام اطلاعاتی
    </x-core-ui::alert>
  Props: type (info|success|warning|error), :dismissible (bool), :icon (bool)
--}}
@props([
    'type'        => 'info',
    'dismissible' => false,
    'icon'        => true,
])

@php
$classes = match($type) {
    'success' => 'bg-emerald-50 border-emerald-200 text-emerald-800',
    'warning' => 'bg-amber-50 border-amber-200 text-amber-800',
    'error'   => 'bg-red-50 border-red-200 text-red-800',
    default   => 'bg-indigo-50 border-indigo-200 text-indigo-800',
};
$iconColor = match($type) {
    'success' => 'text-emerald-600',
    'warning' => 'text-amber-600',
    'error'   => 'text-red-600',
    default   => 'text-indigo-600',
};
@endphp

<div
  @if($dismissible) x-data="{ show: true }" x-show="show" @endif
  class="flex items-start gap-3 rounded-xl border p-4 {{ $classes }}"
  role="alert"
>
  @if($icon)
  <span class="shrink-0 {{ $iconColor }}" aria-hidden="true">
    @if($type === 'success')
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
    @elseif($type === 'warning')
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
    @elseif($type === 'error')
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
    @else
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
    @endif
  </span>
  @endif

  <div class="flex-1 text-sm min-w-0">{{ $slot }}</div>

  @if($dismissible)
  <button
    type="button"
    @click="show = false"
    class="shrink-0 opacity-60 hover:opacity-100 transition focus-visible:ring-2 focus-visible:ring-current focus-visible:outline-none rounded"
    aria-label="بستن"
  >
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
    </svg>
  </button>
  @endif
</div>
