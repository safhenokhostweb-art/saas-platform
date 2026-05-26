@php
$config = match($priority ?? '') {
    'high'   => ['bg-red-50 text-red-700 border-red-200',   'bg-red-500',  'بالا'],
    'normal' => ['bg-blue-50 text-blue-600 border-blue-200','bg-blue-500', 'عادی'],
    'low'    => ['bg-gray-50 text-gray-500 border-gray-200','bg-gray-400', 'کم'],
    default  => ['bg-gray-50 text-gray-500 border-gray-200','bg-gray-400', $priority ?? ''],
};
[$cls, $dot, $label] = $config;
@endphp
<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border {{ $cls }}">
    <span class="w-1.5 h-1.5 rounded-full {{ $dot }} inline-block shrink-0"
          style="animation: pulse-dot 2s ease-in-out infinite"></span>
    {{ $label }}
</span>
<style>
@keyframes pulse-dot{0%,100%{opacity:1;transform:scale(1)}50%{opacity:0.3;transform:scale(1.5)}}
</style>
