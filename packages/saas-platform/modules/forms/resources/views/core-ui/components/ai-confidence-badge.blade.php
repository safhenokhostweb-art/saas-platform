{{--
  AI Confidence Badge — shows a provider's confidence score for a suggestion.
  Usage:
    <x-core-ui::ai-confidence-badge :score="0.87" />
    <x-core-ui::ai-confidence-badge :score="$result['confidence']" source="openai" />
--}}
@props([
    'score'   => 1.0,   // 0.0 to 1.0
    'source'  => null,  // 'ai', 'rule_based', 'fallback', 'openai', 'claude', 'cache'
    'compact' => false,
])

@php
    $pct     = (int) round($score * 100);
    $level   = match(true) {
        $score >= 0.85 => ['label' => 'بالا',    'color' => 'emerald'],
        $score >= 0.6  => ['label' => 'متوسط',   'color' => 'amber'],
        $score >= 0.3  => ['label' => 'پایین',   'color' => 'orange'],
        default        => ['label' => 'قانونی',  'color' => 'gray'],
    };
    $isFallback = in_array($source, ['rule_based', 'fallback', 'empty_fallback'], true);
    $colorMap = [
        'emerald' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'amber'   => 'bg-amber-50 text-amber-700 border-amber-200',
        'orange'  => 'bg-orange-50 text-orange-700 border-orange-200',
        'gray'    => 'bg-gray-50 text-gray-500 border-gray-200',
    ];
    $colorClass = $colorMap[$level['color']] ?? $colorMap['gray'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 border rounded-full font-medium {$colorClass}" . ($compact ? ' px-1.5 py-0.5 text-xs' : ' px-2 py-0.5 text-xs')]) }}
      title="اطمینان هوش مصنوعی: {{ $pct }}٪">

    @if($isFallback)
    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
    </svg>
    <span>قانون‌محور</span>
    @else
    {{-- Confidence arc indicator --}}
    <svg class="w-3 h-3 shrink-0" viewBox="0 0 24 24" fill="none">
        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" stroke-opacity="0.25"/>
        <circle cx="12" cy="12" r="9"
                stroke="currentColor"
                stroke-width="2"
                stroke-dasharray="{{ round(2 * 3.14159 * 9, 1) }}"
                stroke-dashoffset="{{ round((1 - $score) * 2 * 3.14159 * 9, 1) }}"
                stroke-linecap="round"
                transform="rotate(-90 12 12)"/>
    </svg>
    @if(!$compact)
    <span>{{ $level['label'] }}</span>
    @endif
    <span>{{ $pct }}٪</span>
    @endif
</span>
