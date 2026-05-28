{{--
  x-core-ui::textarea — Canonical textarea input.
  Props: label, name, rows, required, disabled, error, hint, placeholder, value
--}}
@props([
    'label'       => null,
    'name'        => null,
    'rows'        => 4,
    'required'    => false,
    'disabled'    => false,
    'error'       => null,
    'hint'        => null,
    'placeholder' => '',
    'value'       => null,
])

@php
$id = $name ? 'ta-' . $name : 'ta-' . uniqid();
$base = 'block w-full rounded-[var(--ui-radius-lg)] border px-3 py-2 text-sm text-[var(--ui-text-primary)] placeholder-[var(--ui-text-subtle)] bg-[var(--ui-surface)] transition focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:opacity-60 resize-y';
$state = $error
    ? 'border-[var(--ui-danger)] focus:border-[var(--ui-danger)] focus:ring-red-200'
    : 'border-[var(--ui-border)] focus:border-[var(--ui-indigo)] focus:ring-[var(--ui-indigo-ring)]';
@endphp

<div {{ $attributes->only('class') }}>
    @if($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-[var(--ui-text-secondary)] mb-1.5">
            {{ $label }}
            @if($required) <span class="text-[var(--ui-danger)] mr-0.5">*</span> @endif
        </label>
    @endif

    <textarea
        id="{{ $id }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        placeholder="{{ $placeholder }}"
        @if($required) required @endif
        @if($disabled) disabled @endif
        {{ $attributes->except(['class', 'label', 'name', 'rows', 'required', 'disabled', 'error', 'hint', 'placeholder', 'value'])->class([$base, $state]) }}
    >{{ old($name, $value) }}</textarea>

    @if($error)
        <p class="mt-1 text-xs text-[var(--ui-danger)]">{{ $error }}</p>
    @elseif($hint)
        <p class="mt-1 text-xs text-[var(--ui-text-subtle)]">{{ $hint }}</p>
    @endif
</div>
