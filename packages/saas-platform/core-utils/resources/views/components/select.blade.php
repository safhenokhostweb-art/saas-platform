{{--
  x-core-ui::select — Canonical select input.
  Props: label, name, required, disabled, error, hint, placeholder
  Slot: <option> elements
--}}
@props([
    'label'       => null,
    'name'        => null,
    'required'    => false,
    'disabled'    => false,
    'error'       => null,
    'hint'        => null,
    'placeholder' => null,
])

@php
$selectId = $name ? 'select-' . $name : 'select-' . uniqid();
$base = 'block w-full rounded-(--ui-radius-lg) border px-3 py-2 text-sm text-(--ui-text-primary) bg-(--ui-surface) transition focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:opacity-60 appearance-none pr-10';
$state = $error
    ? 'border-(--ui-danger) focus:border-(--ui-danger) focus:ring-red-200'
    : 'border-(--ui-border) focus:border-(--ui-indigo) focus:ring-(--ui-indigo-ring)';
@endphp

<div {{ $attributes->only('class') }}>
    @if($label)
        <label for="{{ $selectId }}" class="block text-sm font-medium text-(--ui-text-secondary) mb-1.5">
            {{ $label }}
            @if($required) <span class="text-(--ui-danger) mr-0.5">*</span> @endif
        </label>
    @endif

    <div class="relative">
        <select
            id="{{ $selectId }}"
            name="{{ $name }}"
            @if($required) required @endif
            @if($disabled) disabled @endif
            {{ $attributes->except(['class', 'label', 'name', 'required', 'disabled', 'error', 'hint', 'placeholder'])->class([$base, $state]) }}
        >
            @if($placeholder)
                <option value="">{{ $placeholder }}</option>
            @endif
            {{ $slot }}
        </select>
        <div class="pointer-events-none absolute inset-y-0 left-3 flex items-center">
            <svg class="w-4 h-4 text-(--ui-text-subtle)" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
            </svg>
        </div>
    </div>

    @if($error)
        <p class="mt-1 text-xs text-(--ui-danger)">{{ $error }}</p>
    @elseif($hint)
        <p class="mt-1 text-xs text-(--ui-text-subtle)">{{ $hint }}</p>
    @endif
</div>
