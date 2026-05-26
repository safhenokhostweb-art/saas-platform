@props([
    'actions' => [],   // [{label, href, method, class, confirm}]
    'size'    => 'sm', // sm | md
])
@php
    $btnBase = $size === 'sm'
        ? 'inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium transition'
        : 'inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium transition';
@endphp
<div class="flex flex-wrap items-center gap-2">
    @foreach($actions as $action)
        @php
            $btnClass = match($action['style'] ?? 'secondary') {
                'primary'  => 'bg-blue-600 hover:bg-blue-700 text-white',
                'danger'   => 'bg-red-50 hover:bg-red-100 text-red-700',
                'success'  => 'bg-green-50 hover:bg-green-100 text-green-700',
                'warning'  => 'bg-yellow-50 hover:bg-yellow-100 text-yellow-700',
                default    => 'bg-gray-50 hover:bg-gray-100 text-gray-700',
            };
            $confirm = $action['confirm'] ?? null;
            $method  = strtoupper($action['method'] ?? 'GET');
        @endphp

        @if($method === 'GET')
            <a href="{{ $action['href'] }}"
               class="{{ $btnBase }} {{ $btnClass }}"
               @if($confirm) onclick="return confirm('{{ $confirm }}')" @endif>
                @if(!empty($action['icon']))
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        {!! $action['icon'] !!}
                    </svg>
                @endif
                {{ $action['label'] }}
            </a>
        @else
            <form method="POST" action="{{ $action['href'] }}" class="inline">
                @csrf
                @if($method !== 'POST')
                    @method($method)
                @endif
                <button type="submit"
                        class="{{ $btnBase }} {{ $btnClass }}"
                        @if($confirm) onclick="return confirm('{{ $confirm }}')" @endif>
                    @if(!empty($action['icon']))
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            {!! $action['icon'] !!}
                        </svg>
                    @endif
                    {{ $action['label'] }}
                </button>
            </form>
        @endif
    @endforeach

    {{ $slot }}
</div>
