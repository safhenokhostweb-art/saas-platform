@props([
    'status' => 'active',
    'map'    => [],    // custom override: ['key' => ['label' => '...', 'class' => '...']]
])
@php
    $defaults = [
        'active'      => ['label' => 'فعال',      'class' => 'bg-green-100 text-green-800'],
        'inactive'    => ['label' => 'غیرفعال',  'class' => 'bg-gray-100 text-gray-500'],
        'beta'        => ['label' => 'آزمایشی',  'class' => 'bg-yellow-100 text-yellow-800'],
        'deprecated'  => ['label' => 'منسوخ',    'class' => 'bg-red-100 text-red-700'],
        'pending'     => ['label' => 'در انتظار','class' => 'bg-orange-100 text-orange-700'],
        'open'        => ['label' => 'باز',       'class' => 'bg-yellow-100 text-yellow-800'],
        'in_progress' => ['label' => 'در حال بررسی', 'class' => 'bg-blue-100 text-blue-800'],
        'closed'      => ['label' => 'بسته',     'class' => 'bg-gray-100 text-gray-600'],
        'installed'   => ['label' => 'نصب‌شده',  'class' => 'bg-green-100 text-green-800'],
        'updated'     => ['label' => 'به‌روز',   'class' => 'bg-blue-100 text-blue-700'],
        'failed'      => ['label' => 'خطا',       'class' => 'bg-red-100 text-red-700'],
    ];
    $resolved = array_merge($defaults, $map);
    $cfg      = $resolved[$status] ?? ['label' => $status, 'class' => 'bg-gray-100 text-gray-500'];
@endphp
<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $cfg['class'] }}">
    {{ $cfg['label'] }}
</span>
