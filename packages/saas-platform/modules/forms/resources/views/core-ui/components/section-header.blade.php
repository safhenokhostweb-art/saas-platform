@props([
    'label'       => '',
    'description' => null,
    'border'      => true,
])
<div {{ $attributes->merge(['class' => 'mb-4' . ($border ? ' pb-3 border-b border-slate-100' : '')]) }}>
    <h4 class="text-sm font-semibold text-slate-700">{{ $label }}</h4>
    @if($description)
    <p class="text-xs text-slate-400 mt-0.5">{{ $description }}</p>
    @endif
</div>
