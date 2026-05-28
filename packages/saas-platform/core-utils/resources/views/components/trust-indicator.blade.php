{{--
  core-ui::trust-indicator
  Shows trust level with visual indicator bar and label.
  Props: $score (int), $level (trusted|verified|conditional|untrusted), $dimensions (array)
--}}
@props([
    'score'      => 0,
    'level'      => 'untrusted',
    'dimensions' => [],
    'compact'    => false,
])
@php
    $levelConfig = match($level) {
        'trusted'     => ['color' => 'green',  'icon' => '🛡️', 'label' => 'Trusted',     'bar' => 'bg-green-500'],
        'verified'    => ['color' => 'blue',   'icon' => '✓',  'label' => 'Verified',    'bar' => 'bg-blue-500'],
        'conditional' => ['color' => 'yellow', 'icon' => '⚠',  'label' => 'Conditional', 'bar' => 'bg-yellow-500'],
        default       => ['color' => 'red',    'icon' => '✗',  'label' => 'Untrusted',   'bar' => 'bg-red-500'],
    };
    $textColor = "text-{$levelConfig['color']}-700";
    $bgColor   = "bg-{$levelConfig['color']}-50";
    $borderColor = "border-{$levelConfig['color']}-200";
@endphp

<div class="bg-white rounded-xl border {{ $borderColor }} p-4" role="meter" aria-valuenow="{{ $score }}" aria-valuemin="0" aria-valuemax="100" aria-label="Trust score: {{ $score }}/100">
    <div class="flex items-center justify-between mb-2">
        <div class="flex items-center gap-2">
            <span class="text-lg" aria-hidden="true">{{ $levelConfig['icon'] }}</span>
            <span class="text-sm font-semibold {{ $textColor }}">{{ $levelConfig['label'] }}</span>
        </div>
        <span class="text-xl font-bold {{ $textColor }}">{{ $score }}<span class="text-xs font-normal text-gray-400">/100</span></span>
    </div>

    {{-- Trust bar --}}
    <div class="h-2 bg-gray-100 rounded-full overflow-hidden mb-3"
         x-data="{ w: 0 }" x-init="setTimeout(() => w = {{ $score }}, 100)">
        <div :style="'width:' + w + '%'"
             class="h-full {{ $levelConfig['bar'] }} rounded-full transition-all duration-1000 ease-out"></div>
    </div>

    @if(! $compact && ! empty($dimensions))
    <div class="space-y-1.5 mt-3">
        @foreach($dimensions as $dim)
        @php
            $dimScore = $dim['score'] ?? 0;
            $dimColor = $dimScore >= 80 ? 'bg-green-400' : ($dimScore >= 60 ? 'bg-yellow-400' : 'bg-red-400');
        @endphp
        <div class="flex items-center gap-2">
            <span class="text-xs text-gray-500 w-32 shrink-0 truncate">
                {{ str_replace('_', ' ', ucwords($dim['dimension'] ?? '', '_')) }}
            </span>
            <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full {{ $dimColor }} rounded-full" style="width: {{ $dimScore }}%"></div>
            </div>
            <span class="text-xs font-medium text-gray-600 w-8 text-right">{{ $dimScore }}</span>
        </div>
        @endforeach
    </div>
    @endif
</div>
