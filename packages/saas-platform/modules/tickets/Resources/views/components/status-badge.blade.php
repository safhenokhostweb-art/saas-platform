@php
$classes = match($status ?? '') {
    'open'        => 'bg-yellow-100 text-yellow-800',
    'in_progress' => 'bg-blue-100 text-blue-800',
    'closed'      => 'bg-gray-100 text-gray-600',
    default       => 'bg-gray-100 text-gray-600',
};
$label = match($status ?? '') {
    'open'        => 'باز',
    'in_progress' => 'در حال بررسی',
    'closed'      => 'بسته',
    default       => $status ?? '',
};
@endphp
<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $classes }}">
    {{ $label }}
</span>
