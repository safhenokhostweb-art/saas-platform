@php
$config = match($status ?? '') {
    'open'        => ['bg-yellow-50 text-yellow-700 border-yellow-200', 'bg-yellow-500', 'باز'],
    'in_progress' => ['bg-blue-50 text-blue-700 border-blue-200',       'bg-blue-500',   'در حال بررسی'],
    'closed'      => ['bg-gray-50 text-gray-600 border-gray-200',       null,            'بسته'],
    default       => ['bg-gray-50 text-gray-600 border-gray-200',       null,            $status ?? ''],
};
[$cls, $dot, $label] = $config;
@endphp
<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border {{ $cls }}">
    @if($dot)
        <span class="w-1.5 h-1.5 rounded-full {{ $dot }} animate-pulse inline-block shrink-0"></span>
    @else
        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
    @endif
    {{ $label }}
</span>
