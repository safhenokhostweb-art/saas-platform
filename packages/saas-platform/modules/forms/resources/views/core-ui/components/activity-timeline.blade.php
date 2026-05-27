@props([
    'activities' => [],  // iterable of objects with: action_type, label(), actorName(), old_value, new_value, created_at, iconColor(), metadata
    'compact'    => false,
])

<div {{ $attributes->merge(['class' => 'relative']) }}>
    @forelse($activities as $activity)
    @php
        $color  = method_exists($activity, 'iconColor') ? $activity->iconColor() : 'gray';
        $label  = method_exists($activity, 'label')     ? $activity->label()     : ($activity->action_type ?? '');
        $actor  = method_exists($activity, 'actorName') ? $activity->actorName() : 'سیستم';
        $dotMap = [
            'indigo'  => 'bg-indigo-500 ring-indigo-100',
            'blue'    => 'bg-blue-500 ring-blue-100',
            'green'   => 'bg-green-500 ring-green-100',
            'emerald' => 'bg-emerald-500 ring-emerald-100',
            'amber'   => 'bg-amber-500 ring-amber-100',
            'red'     => 'bg-red-500 ring-red-100',
            'purple'  => 'bg-purple-500 ring-purple-100',
            'teal'    => 'bg-teal-500 ring-teal-100',
            'orange'  => 'bg-orange-500 ring-orange-100',
            'gray'    => 'bg-gray-400 ring-gray-100',
            'slate'   => 'bg-slate-400 ring-slate-100',
            'yellow'  => 'bg-yellow-500 ring-yellow-100',
        ];
        $dotClass = $dotMap[$color] ?? $dotMap['gray'];
    @endphp
    <div class="relative flex gap-3 {{ $compact ? 'pb-3' : 'pb-5' }}">

        {{-- Connector line --}}
        @if(! $loop->last)
        <div class="absolute right-[9px] top-5 bottom-0 w-px bg-gray-100"></div>
        @endif

        {{-- Dot --}}
        <div class="relative z-10 mt-0.5 flex-shrink-0">
            <span class="flex h-5 w-5 items-center justify-center rounded-full ring-4 {{ $dotClass }}">
            </span>
        </div>

        {{-- Content --}}
        <div class="{{ $compact ? '' : 'flex-1 min-w-0' }}">
            <p class="text-{{ $compact ? 'xs' : 'sm' }} font-semibold text-gray-800 leading-snug">
                {{ $label }}
                @if($activity->old_value && $activity->new_value)
                <span class="text-gray-400 font-normal">
                    <span class="line-through text-gray-400">{{ $activity->old_value }}</span>
                    <svg class="inline w-3 h-3 mx-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5-5 5M6 12h12"/></svg>
                    <span class="font-semibold text-gray-700">{{ $activity->new_value }}</span>
                </span>
                @elseif($activity->new_value)
                <span class="text-gray-500 font-normal">: {{ $activity->new_value }}</span>
                @endif
            </p>
            @if(isset($activity->metadata['excerpt']))
            <p class="mt-0.5 text-xs text-gray-500 line-clamp-1">{{ $activity->metadata['excerpt'] }}</p>
            @endif
            <div class="mt-0.5 flex items-center gap-2 text-xs text-gray-400">
                <span class="font-medium text-gray-500">{{ $actor }}</span>
                <span>·</span>
                <time datetime="{{ $activity->created_at?->toIso8601String() }}">
                    {{ $activity->created_at ? \App\Helpers\Jalali::formatFa($activity->created_at, 'Y/m/d H:i') : '' }}
                </time>
            </div>
        </div>

    </div>
    @empty
    <div class="text-center py-8 text-gray-400">
        <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <p class="text-xs">فعالیتی ثبت نشده</p>
    </div>
    @endforelse
</div>
