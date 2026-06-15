{{--
  x-core-ui::input — Canonical text input.
  Props:
    label       : string
    name        : string
    type        : text | email | password | number | tel | url | search (default: text)
    placeholder : string
    value       : string
    required    : bool
    disabled    : bool
    error       : string — error message
    hint        : string — helper text
    icon        : slot — leading icon slot
--}}
@props([
    'label'       => null,
    'name'        => null,
    'type'        => 'text',
    'placeholder' => '',
    'value'       => null,
    'required'    => false,
    'disabled'    => false,
    'error'       => null,
    'hint'        => null,
    'ariaLabel'   => null,
    'role'        => null,
])

@php
$inputId = $name ? 'input-' . $name : 'input-' . uniqid();
$baseInput = 'block w-full rounded-(--ui-radius-lg) border px-3 py-2 text-sm text-(--ui-text-primary) placeholder-(--ui-text-subtle) transition focus-visible:outline-none focus-visible:ring-2 disabled:cursor-not-allowed disabled:opacity-60 bg-(--ui-surface)';
$stateInput = $error
    ? 'border-(--ui-danger) focus-visible:border-(--ui-danger) focus-visible:ring-red-200'
    : 'border-(--ui-border) focus-visible:border-(--ui-indigo) focus-visible:ring-(--ui-indigo-ring)';
@endphp

<div {{ $attributes->only('class') }}>
    @if($label)
        <label for="{{ $inputId }}" class="block text-sm font-medium text-(--ui-text-secondary) mb-1.5">
            {{ $label }}
            @if($required) <span class="text-(--ui-danger) mr-0.5">*</span> @endif
        </label>
    @endif

    <input
        id="{{ $inputId }}"
        type="{{ $type }}"
        name="{{ $name }}"
        value="{{ old($name, $value) }}"
        placeholder="{{ $placeholder }}"
        @if($ariaLabel) aria-label="{{ $ariaLabel }}" @endif
        @if($role) role="{{ $role }}" @endif
        @if($required) required @endif
        @if($disabled) disabled @endif
        {{ $attributes->except(['class', 'label', 'name', 'type', 'placeholder', 'value', 'required', 'disabled', 'error', 'hint', 'ariaLabel', 'role'])->class([$baseInput, $stateInput]) }}
    />

    @if($error)
        <p class="mt-1 text-xs text-(--ui-danger)">{{ $error }}</p>
    @elseif($hint)
        <p class="mt-1 text-xs text-(--ui-text-subtle)">{{ $hint }}</p>
    @endif
</div>
