{{--
  x-core-ui::button — Canonical button component.
  Props:
    variant   : primary | secondary | danger | success | ghost | outline (default: primary)
    size      : sm | md | lg (default: md)
    type      : button | submit | reset (default: button)
    disabled  : bool (default: false)
    href      : string — renders <a> instead of <button>
    icon      : bool — icon-only button (square, no padding adjustment)
    loading   : bool — show spinner and disable
    ariaLabel : string — accessible label for icon-only buttons
    class     : extra classes appended to the root element
--}}
@props([
    'variant'   => 'primary',
    'size'      => 'md',
    'type'      => 'button',
    'disabled'  => false,
    'href'      => null,
    'icon'      => false,
    'loading'   => false,
    'ariaLabel' => null,
])

@php
$base = 'inline-flex items-center justify-center gap-2 font-semibold rounded-xl border transition focus-visible:outline-none focus-visible:ring-2';

$sizes = [
    'sm' => $icon ? 'p-1.5 text-xs'   : 'px-3 py-1.5 text-xs',
    'md' => $icon ? 'p-2.5 text-sm'   : 'px-4 py-2 text-sm',
    'lg' => $icon ? 'p-3.5 text-base' : 'px-6 py-2.5 text-base',
];

$variants = [
    'primary'   => 'bg-[var(--ui-indigo)] hover:bg-[var(--ui-indigo-hover)] text-white border-transparent shadow-[var(--ui-shadow-indigo)] focus-visible:ring-[var(--ui-focus-ring-color)]',
    'secondary' => 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200 focus-visible:ring-slate-400',
    'danger'    => 'bg-[var(--ui-danger)] hover:bg-[var(--ui-danger-hover)] text-white border-transparent shadow-sm focus-visible:ring-red-400',
    'success'   => 'bg-[var(--ui-success)] hover:bg-[var(--ui-success-hover)] text-white border-transparent shadow-sm focus-visible:ring-green-400',
    'ghost'     => 'bg-transparent hover:bg-slate-100 text-slate-600 border-transparent focus-visible:ring-slate-400',
    'outline'   => 'bg-transparent hover:bg-[var(--ui-indigo-light)] text-[var(--ui-indigo)] border-[var(--ui-indigo)] focus-visible:ring-[var(--ui-focus-ring-color)]',
    'gradient'  => 'bg-gradient-to-r from-indigo-600 to-purple-600 hover:opacity-90 active:scale-95 text-white border-transparent shadow-[var(--ui-shadow-indigo)] focus-visible:ring-indigo-400',
];

$disabledCls = ($disabled || $loading) ? 'opacity-50 cursor-not-allowed pointer-events-none' : 'cursor-pointer';
$cls = implode(' ', array_filter([$base, $sizes[$size] ?? $sizes['md'], $variants[$variant] ?? $variants['primary'], $disabledCls]));
@endphp

@if($href)
<a href="{{ $href }}" role="button" {{ $attributes->class([$cls]) }}
   @if($disabled) aria-disabled="true" @endif
   @if($ariaLabel) aria-label="{{ $ariaLabel }}" @endif>
    @if($loading)
        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
    @endif
    {{ $slot }}
</a>
@else
<button type="{{ $type }}" {{ $attributes->class([$cls]) }}
        @if($disabled || $loading) disabled @endif
        @if($ariaLabel) aria-label="{{ $ariaLabel }}" @endif>
    @if($loading)
        <svg class="animate-spin w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
    @endif
    {{ $slot }}
</button>
@endif
