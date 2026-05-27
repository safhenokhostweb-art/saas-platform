@props([
    'lines' => 3,
    'showHeader' => true,
])
<div {{ $attributes->merge(['class' => 'bg-white rounded-2xl border border-slate-200 p-5 animate-pulse']) }}>
    @if($showHeader)
    <div class="flex items-start justify-between mb-4">
        <div class="space-y-2 flex-1 mr-3">
            <div class="h-4 bg-slate-200 rounded-lg w-3/4"></div>
            <div class="h-3 bg-slate-100 rounded w-1/3"></div>
        </div>
        <div class="h-5 w-14 bg-slate-100 rounded-full"></div>
    </div>
    @endif

    @for($i = 0; $i < $lines; $i++)
    <div class="h-3 bg-slate-100 rounded mb-2 {{ $i === $lines - 1 ? 'w-2/3' : 'w-full' }}"></div>
    @endfor

    <div class="mt-4 h-9 bg-indigo-50 rounded-xl"></div>

    <div class="mt-4 pt-3 border-t border-slate-100 flex items-center gap-3">
        <div class="h-7 w-7 bg-slate-100 rounded-lg"></div>
        <div class="h-7 w-7 bg-slate-100 rounded-lg"></div>
        <div class="h-7 w-7 bg-slate-100 rounded-lg"></div>
    </div>
</div>
