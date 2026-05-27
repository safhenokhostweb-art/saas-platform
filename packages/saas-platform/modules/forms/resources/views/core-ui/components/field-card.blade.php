{{--
    Builder field card wrapper — used in form builder canvas.
    Wraps each field in the draggable list.
    Props are passed as Alpine.js expressions from the parent builder.
--}}
@props([
    'selected' => false,
])
<div {{ $attributes->merge([
    'class' => 'field-card bg-white border border-slate-200 rounded-xl p-3 cursor-pointer transition'
        . ($selected ? ' selected' : '')
]) }}>
    {{ $slot }}
</div>
