{{--
  x-core-ui::stat-card — Single stat display card.
  Props:
    value   : string — the stat number/value
    label   : string — descriptive label
    trend   : string — optional trend string (+12% or -3%)
    variant : success | danger | warning | info | neutral (default: neutral)
    loading : bool
--}}
@props([
    'value'   => '—',
    'label'   => '',
    'trend'   => null,
    'variant' => 'neutral',
    'loading' => false,
])

@php
$trendPositive = $trend && str_starts_with(ltrim($trend), '+');
$trendNegative = $trend && str_starts_with(ltrim($trend), '-');
@endphp

<div {{ $attributes->class(['ui-card p-5 flex flex-col gap-2']) }}>
    @if($loading)
        <div class="ui-skeleton h-7 w-16 rounded-lg mb-1"></div>
        <div class="ui-skeleton h-3 w-24 rounded-full"></div>
    @else
        <div class="flex items-baseline justify-between gap-2">
            <span class="text-2xl font-bold text-(--ui-text-primary) leading-none">{{ $value }}</span>
            @if($trend)
                <span class="inline-flex items-center gap-0.5 text-xs font-semibold
                             {{ $trendPositive ? 'text-(--ui-success)' : ($trendNegative ? 'text-(--ui-danger)' : 'text-(--ui-text-muted)') }}">
                    @if($trendPositive)
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18"/>
                        </svg>
                    @elseif($trendNegative)
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3"/>
                        </svg>
                    @endif
                    {{ $trend }}
                </span>
            @endif
        </div>
        <p class="text-xs text-(--ui-text-muted)">{{ $label }}</p>
    @endif
</div>
