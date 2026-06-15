{{--
  x-core-ui::radio-card — Selectable card with radio button semantics.
  Props: name, value, label, description, checked, disabled
--}}
@props([
    'name'        => null,
    'value'       => null,
    'label'       => '',
    'description' => null,
    'checked'     => false,
    'disabled'    => false,
])

@php
$id = 'rc-' . $name . '-' . md5($value ?? uniqid());
@endphp

<label for="{{ $id }}" class="relative flex items-start gap-3 p-4 rounded-(--ui-radius-xl) border cursor-pointer transition-all
    {{ $disabled ? 'opacity-50 cursor-not-allowed' : 'hover:border-(--ui-indigo) hover:bg-(--ui-indigo-light)' }}
    {{ $checked ? 'border-(--ui-indigo) bg-(--ui-indigo-light) ring-1 ring-(--ui-indigo)' : 'border-(--ui-border) bg-(--ui-surface)' }}
">
    <input
        type="radio"
        id="{{ $id }}"
        name="{{ $name }}"
        value="{{ $value }}"
        @if($checked) checked @endif
        @if($disabled) disabled @endif
        class="mt-0.5 w-4 h-4 text-(--ui-indigo) border-(--ui-border) focus:ring-(--ui-indigo-ring) shrink-0"
        {{ $attributes }}
    />
    <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-(--ui-text-primary) leading-tight">{{ $label }}</p>
        @if($description)
            <p class="mt-0.5 text-xs text-(--ui-text-muted)">{{ $description }}</p>
        @endif
        @if($slot->isNotEmpty())
            <div class="mt-2">{{ $slot }}</div>
        @endif
    </div>
</label>
