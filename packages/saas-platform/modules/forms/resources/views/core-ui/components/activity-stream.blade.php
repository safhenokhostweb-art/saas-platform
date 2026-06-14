@props([
    'activities' => [],  // array of activity items
    'compact'    => false,
    'showDate'   => true,
    'maxItems'   => 50,
])

<div {{ $attributes->merge(['class' => 'flow-root']) }}>
    @if(count($activities) === 0)
    <div class="text-center py-8">
        <svg class="w-10 h-10 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
        </svg>
        <p class="text-sm text-gray-400">هیچ فعالیتی ثبت نشده</p>
    </div>
    @else
    <ul class="-mb-8" role="list">
        @foreach(array_slice($activities, 0, $maxItems) as $i => $activity)
        @php
            $isLast   = $i === min(count($activities), $maxItems) - 1;
            $typeMap  = [
                'created'  => ['color' => 'bg-indigo-100 text-indigo-600', 'ring' => 'ring-indigo-200'],
                'replied'  => ['color' => 'bg-emerald-100 text-emerald-600', 'ring' => 'ring-emerald-200'],
                'assigned' => ['color' => 'bg-blue-100 text-blue-600', 'ring' => 'ring-blue-200'],
                'status'   => ['color' => 'bg-amber-100 text-amber-600', 'ring' => 'ring-amber-200'],
                'closed'   => ['color' => 'bg-gray-100 text-gray-500', 'ring' => 'ring-gray-200'],
                'note'     => ['color' => 'bg-yellow-100 text-yellow-600', 'ring' => 'ring-yellow-200'],
                'default'  => ['color' => 'bg-gray-100 text-gray-400', 'ring' => 'ring-gray-100'],
            ];
            $meta = $typeMap[$activity['type'] ?? 'default'] ?? $typeMap['default'];
            $initials = mb_substr($activity['user_name'] ?? 'U', 0, 1);
        @endphp
        <li>
            <div class="relative {{ $isLast ? '' : 'pb-8' }}">
                @if(! $isLast)
                <span class="absolute right-4 top-4 -mr-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                @endif
                <div class="relative flex items-start space-x-3 rtl:space-x-reverse">
                    <div class="relative">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full {{ $meta['color'] }} ring-2 {{ $meta['ring'] }} text-xs font-bold">
                            {{ $initials }}
                        </span>
                    </div>
                    <div class="min-w-0 flex-1 {{ $compact ? 'pt-0.5' : 'pt-1.5' }}">
                        <div class="flex items-baseline justify-between gap-2">
                            <p class="text-sm text-gray-800">
                                <span class="font-semibold">{{ $activity['user_name'] ?? 'سیستم' }}</span>
                                <span class="text-gray-500 font-normal mr-1">{{ $activity['description'] ?? '' }}</span>
                            </p>
                            @if($showDate && isset($activity['created_at']))
                            <time class="text-xs text-gray-400 whitespace-nowrap shrink-0"
                                  datetime="{{ $activity['created_at'] }}"
                                  title="{{ $activity['created_at'] }}">
                                {{ \Carbon\Carbon::parse($activity['created_at'])->diffForHumans() }}
                            </time>
                            @endif
                        </div>
                        @if(isset($activity['content']) && !$compact)
                        <div class="mt-1 text-sm text-gray-600 bg-gray-50 rounded-lg px-3 py-2 border border-gray-100">
                            {{ $activity['content'] }}
                        </div>
                        @endif
                        @if(isset($activity['meta']) && !empty($activity['meta']))
                        <div class="mt-1 flex flex-wrap gap-1">
                            @foreach($activity['meta'] as $k => $v)
                            <span class="inline-flex items-center gap-1 text-xs text-gray-500 bg-gray-100 rounded px-1.5 py-0.5">
                                <span class="font-medium">{{ $k }}:</span> {{ $v }}
                            </span>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </li>
        @endforeach
    </ul>
    @endif
</div>
