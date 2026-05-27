{{--
  Core-UI Stat Card — Phase 9.2
  Usage:
    <x-core-ui::stat-card label="کل ارسال‌ها" :value="1423" icon="arrow-up" trend="+12%" color="indigo" />
  Props: label, value, icon (optional), trend (optional string), color, :loading (bool)
--}}
@props([
    'label'   => '',
    'value'   => 0,
    'icon'    => null,
    'trend'   => null,
    'color'   => 'indigo',
    'loading' => false,
])

@php
$ringClass = match($color) {
    'emerald' => 'ring-emerald-100',
    'amber'   => 'ring-amber-100',
    'red'     => 'ring-red-100',
    'purple'  => 'ring-purple-100',
    default   => 'ring-indigo-100',
};
$iconBg = match($color) {
    'emerald' => 'bg-emerald-50 text-emerald-600',
    'amber'   => 'bg-amber-50 text-amber-600',
    'red'     => 'bg-red-50 text-red-600',
    'purple'  => 'bg-purple-50 text-purple-600',
    default   => 'bg-indigo-50 text-indigo-600',
};
@endphp

<div class="bg-white rounded-2xl border border-slate-200 ring-1 {{ $ringClass }} p-5 shadow-sm">
  @if($loading)
    <div class="animate-pulse">
      <div class="h-3 bg-slate-200 rounded w-1/2 mb-3"></div>
      <div class="h-6 bg-slate-200 rounded w-1/3"></div>
    </div>
  @else
    <div class="flex items-start justify-between">
      <div>
        <p class="text-xs font-medium text-slate-500 mb-1">{{ $label }}</p>
        <p class="text-2xl font-bold text-slate-800">{{ number_format((float)$value) }}</p>
        @if($trend)
          <p class="text-xs font-medium mt-1 {{ str_starts_with($trend, '+') ? 'text-emerald-600' : 'text-red-600' }}">
            {{ $trend }}
          </p>
        @endif
      </div>
      @if($icon)
        <div class="w-9 h-9 rounded-xl flex items-center justify-center {{ $iconBg }}">
          @include('forms::admin.forms._partials.field-icon', ['type' => $icon])
        </div>
      @endif
    </div>
  @endif
</div>
