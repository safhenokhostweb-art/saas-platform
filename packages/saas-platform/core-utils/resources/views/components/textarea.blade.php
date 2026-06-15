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
$base = 'block w-full rounded-(--ui-radius-lg) border px-3 py-2 text-sm text-(--ui-text-primary) placeholder-(--ui-text-subtle) bg-(--ui-surface) transition focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:opacity-60 resize-y';
$state = $error
    ? 'border-(--ui-danger) focus:border-(--ui-danger) focus:ring-red-200'
    : 'border-(--ui-border) focus:border-(--ui-indigo) focus:ring-(--ui-indigo-ring)';
@endphp

<div {{ $attributes->only('class') }}>
    @if($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-(--ui-text-secondary) mb-1.5">
            {{ $label }}
            @if($required) <span class="text-(--ui-danger) mr-0.5">*</span> @endif
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
        <p class="mt-1 text-xs text-(--ui-danger)">{{ $error }}</p>
    @elseif($hint)
        <p class="mt-1 text-xs text-(--ui-text-subtle)">{{ $hint }}</p>
    @endif
</div>
