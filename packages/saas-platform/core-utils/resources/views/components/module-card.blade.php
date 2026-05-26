@props([
    'slug'        => '',
    'name'        => '',
    'version'     => '',
    'description' => '',
    'status'      => 'active',   // active | inactive | beta | deprecated
    'icon'        => 'squares-2x2',
    'route'       => null,
    'actions'     => [],
])
@php
    $statusClasses = match($status) {
        'active'     => 'bg-green-100 text-green-800',
        'inactive'   => 'bg-gray-100 text-gray-500',
        'beta'       => 'bg-yellow-100 text-yellow-800',
        'deprecated' => 'bg-red-100 text-red-700',
        default      => 'bg-gray-100 text-gray-500',
    };
    $statusLabel = match($status) {
        'active'     => 'فعال',
        'inactive'   => 'غیرفعال',
        'beta'       => 'آزمایشی',
        'deprecated' => 'منسوخ',
        default      => $status,
    };
@endphp
<div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 flex flex-col gap-3 hover:shadow-md transition-shadow">
    <div class="flex items-start justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-gray-800">{{ $name }}</h3>
                @if($version)
                    <p class="text-xs text-gray-400 mt-0.5">v{{ $version }}</p>
                @endif
            </div>
        </div>
        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClasses }}">
            {{ $statusLabel }}
        </span>
    </div>

    @if($description)
        <p class="text-xs text-gray-500 leading-relaxed">{{ $description }}</p>
    @endif

    {{ $slot }}

    @if($route || count($actions) > 0)
        <div class="flex items-center gap-2 pt-1 border-t border-gray-50">
            @if($route)
                <a href="{{ route($route) }}"
                   class="text-xs bg-blue-600 hover:bg-blue-700 text-white font-medium px-3 py-1.5 rounded-lg transition">
                    مدیریت
                </a>
            @endif
            @foreach($actions as $action)
                <a href="{{ $action['url'] ?? '#' }}"
                   class="text-xs text-gray-600 hover:text-gray-800 font-medium px-3 py-1.5 rounded-lg hover:bg-gray-50 transition">
                    {{ $action['label'] ?? '' }}
                </a>
            @endforeach
        </div>
    @endif
</div>
