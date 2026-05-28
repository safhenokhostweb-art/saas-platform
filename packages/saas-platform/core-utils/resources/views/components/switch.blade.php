{{--
  x-core-ui::switch — Canonical toggle switch.
  Props:
    name      : string — input name attribute
    label     : string — visible label
    hint      : string — helper text below label
    checked   : bool   — initial checked state
    disabled  : bool
    size      : sm | md | lg (default: md)
    onchange  : string — Alpine/JS expression for x-on:change
--}}
@props([
    'name'     => null,
    'label'    => null,
    'hint'     => null,
    'checked'  => false,
    'disabled' => false,
    'size'     => 'md',
])

@php
$switchId = $name ? 'switch-' . $name : 'switch-' . uniqid();

$sizes = [
    'sm' => ['track' => 'w-8 h-4',   'thumb' => 'w-3 h-3',   'translate' => 'translate-x-4',  'label' => 'text-sm'],
    'md' => ['track' => 'w-10 h-5',  'thumb' => 'w-4 h-4',   'translate' => 'translate-x-5',  'label' => 'text-sm'],
    'lg' => ['track' => 'w-12 h-6',  'thumb' => 'w-5 h-5',   'translate' => 'translate-x-6',  'label' => 'text-base'],
];
$s = $sizes[$size] ?? $sizes['md'];
@endphp

<div {{ $attributes->only('class') }}
     x-data="{ checked: {{ $checked ? 'true' : 'false' }} }"
     class="flex items-start gap-3">

    {{-- Switch track --}}
    <button
        type="button"
        role="switch"
        id="{{ $switchId }}"
        :aria-checked="checked.toString()"
        @click="{{ $disabled ? '' : 'checked = !checked' }}"
        @keydown.space.prevent="{{ $disabled ? '' : 'checked = !checked' }}"
        @keydown.enter.prevent="{{ $disabled ? '' : 'checked = !checked' }}"
        :class="checked
            ? 'bg-[var(--ui-indigo)]'
            : 'bg-[var(--ui-border-strong)]'"
        class="{{ $s['track'] }} relative inline-flex shrink-0 cursor-pointer rounded-full border-2 border-transparent
               transition-colors duration-[var(--ui-duration-moderate)] ease-[var(--ui-ease-default)]
               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--ui-focus-ring-color)] focus-visible:ring-offset-2
               {{ $disabled ? 'opacity-50 cursor-not-allowed pointer-events-none' : '' }}"
        {{ $disabled ? 'aria-disabled=true' : '' }}
    >
        {{-- Thumb --}}
        <span
            :class="checked ? '{{ $s['translate'] }}' : 'translate-x-0'"
            class="{{ $s['thumb'] }} pointer-events-none inline-block rounded-full bg-white shadow-[var(--ui-shadow-sm)]
                   ring-0 transition-transform duration-[var(--ui-duration-moderate)] ease-[var(--ui-ease-spring)]"
        ></span>
    </button>

    {{-- Hidden input for form submission --}}
    @if($name)
        <input type="hidden" name="{{ $name }}" :value="checked ? '1' : '0'" />
    @endif

    {{-- Label area --}}
    @if($label || $hint)
        <div class="flex flex-col">
            @if($label)
                <label for="{{ $switchId }}"
                       class="{{ $s['label'] }} font-medium text-[var(--ui-text-primary)] cursor-pointer select-none
                              {{ $disabled ? 'opacity-50' : '' }}"
                       @click="{{ $disabled ? '' : 'checked = !checked' }}">
                    {{ $label }}
                </label>
            @endif
            @if($hint)
                <span class="text-xs text-[var(--ui-text-muted)] mt-0.5">{{ $hint }}</span>
            @endif
        </div>
    @endif
</div>
