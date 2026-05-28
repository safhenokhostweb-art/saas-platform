{{--
  x-core-ui::checkbox — Canonical checkbox with optional label.
  Props: name, value, label, checked, disabled, error
--}}
@props([
    'name'    => null,
    'value'   => '1',
    'label'   => null,
    'checked' => false,
    'disabled'=> false,
    'error'   => null,
])

@php
$id = 'cb-' . ($name ?? uniqid());
@endphp

<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="inline-flex items-center gap-2.5 cursor-pointer {{ $disabled ? 'opacity-50 cursor-not-allowed' : '' }}">
        <input
            type="checkbox"
            id="{{ $id }}"
            name="{{ $name }}"
            value="{{ $value }}"
            @if($checked) checked @endif
            @if($disabled) disabled @endif
            {{ $attributes->except(['class', 'name', 'value', 'label', 'checked', 'disabled', 'error']) }}
            class="w-4 h-4 rounded border-[var(--ui-border)] text-[var(--ui-indigo)] focus:ring-[var(--ui-indigo-ring)] transition"
        />
        @if($label)
            <span class="text-sm text-[var(--ui-text-secondary)]">{{ $label }}</span>
        @endif
        @if($slot->isNotEmpty())
            <span class="text-sm text-[var(--ui-text-secondary)]">{{ $slot }}</span>
        @endif
    </label>
    @if($error)
        <p class="mt-1 text-xs text-[var(--ui-danger)]">{{ $error }}</p>
    @endif
</div>
