@props([
    'rules' => [],  // array of validation rule strings
])
@if(!empty($rules))
<div {{ $attributes->merge(['class' => 'flex flex-wrap gap-1 mt-1']) }}>
    @foreach($rules as $rule)
    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-amber-50 border border-amber-200 text-amber-700 text-[10px] font-mono">
        <svg class="w-2.5 h-2.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/>
        </svg>
        {{ $rule }}
    </span>
    @endforeach
</div>
@endif
