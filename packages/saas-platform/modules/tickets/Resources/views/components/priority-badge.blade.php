@php
$classes = match($priority ?? '') {
    'high'   => 'bg-red-100 text-red-800',
    'normal' => 'bg-blue-100 text-blue-700',
    'low'    => 'bg-gray-100 text-gray-500',
    default  => 'bg-gray-100 text-gray-500',
};
$label = match($priority ?? '') {
    'high'   => 'فوری',
    'normal' => 'معمولی',
    'low'    => 'کم',
    default  => $priority ?? '',
};
@endphp
<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $classes }}">
    {{ $label }}
</span>
