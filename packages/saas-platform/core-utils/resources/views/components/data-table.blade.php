@props([
    'headers'  => [],    // ['label' => string, 'class' => string]
    'empty'    => 'موردی یافت نشد.',
    'striped'  => false,
    'compact'  => false,
])
@php
    $rowPad = $compact ? 'px-4 py-2.5' : 'px-4 py-3';
@endphp
<div class="overflow-hidden rounded-xl border border-gray-100 shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-right">
            @if(count($headers) > 0)
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        @foreach($headers as $header)
                            <th class="{{ $rowPad }} text-xs font-semibold text-gray-500 {{ $header['class'] ?? '' }}">
                                {{ is_string($header) ? $header : ($header['label'] ?? '') }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
            @endif
            <tbody class="divide-y divide-gray-50 bg-white">
                {{ $slot }}
            </tbody>
        </table>
    </div>

    @if(isset($empty_state))
        {{ $empty_state }}
    @endif
</div>
