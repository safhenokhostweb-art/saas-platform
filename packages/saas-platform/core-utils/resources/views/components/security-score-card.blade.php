{{--
  core-ui::security-score-card
  Displays the security score with animated ring, grade, and trend.
  Props: $score (int 0-100), $grade (string), $label, $trend (up|down|stable|null), $href
--}}
@props([
    'score'  => 0,
    'grade'  => 'F',
    'label'  => 'Security Score',
    'trend'  => null,
    'href'   => null,
    'size'   => 'md',   // sm | md | lg
])
@php
    $color = match(true) {
        $score >= 90 => ['ring' => '#22c55e', 'text' => 'text-green-600',  'bg' => 'bg-green-50',  'badge' => 'bg-green-100 text-green-800'],
        $score >= 70 => ['ring' => '#3b82f6', 'text' => 'text-blue-600',   'bg' => 'bg-blue-50',   'badge' => 'bg-blue-100 text-blue-800'],
        $score >= 50 => ['ring' => '#f59e0b', 'text' => 'text-yellow-600', 'bg' => 'bg-yellow-50', 'badge' => 'bg-yellow-100 text-yellow-800'],
        default      => ['ring' => '#ef4444', 'text' => 'text-red-600',    'bg' => 'bg-red-50',    'badge' => 'bg-red-100 text-red-800'],
    };
    $ringSize  = match($size) { 'sm' => 80, 'lg' => 140, default => 110 };
    $strokeW   = 8;
    $r         = ($ringSize / 2) - $strokeW;
    $circ      = 2 * M_PI * $r;
    $dash      = ($score / 100) * $circ;
    $fontSize  = match($size) { 'sm' => 'text-xl', 'lg' => 'text-4xl', default => 'text-3xl' };
@endphp

<div
    class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex flex-col items-center gap-3
           {{ $href ? 'hover:shadow-md transition-all duration-200 cursor-pointer' : '' }}"
    @if($href) onclick="window.location='{{ $href }}'" @endif
    x-data="{ visible: false }"
    x-intersect="visible = true"
    role="img"
    :aria-label="'{{ $label }}: {{ $score }} out of 100'"
>
    {{-- Score Ring --}}
    <div class="relative flex items-center justify-center" style="width:{{ $ringSize }}px;height:{{ $ringSize }}px">
        <svg width="{{ $ringSize }}" height="{{ $ringSize }}" class="-rotate-90" aria-hidden="true">
            {{-- Track --}}
            <circle cx="{{ $ringSize/2 }}" cy="{{ $ringSize/2 }}" r="{{ $r }}"
                fill="none" stroke="#e5e7eb" stroke-width="{{ $strokeW }}"/>
            {{-- Score arc --}}
            <circle
                cx="{{ $ringSize/2 }}" cy="{{ $ringSize/2 }}" r="{{ $r }}"
                fill="none"
                stroke="{{ $color['ring'] }}"
                stroke-width="{{ $strokeW }}"
                stroke-linecap="round"
                stroke-dasharray="{{ $circ }}"
                :stroke-dashoffset="visible ? {{ $circ - $dash }} : {{ $circ }}"
                style="transition: stroke-dashoffset 1s cubic-bezier(.4,0,.2,1)"
            />
        </svg>
        <div class="absolute inset-0 flex flex-col items-center justify-center">
            <span class="{{ $fontSize }} font-bold {{ $color['text'] }}">{{ $score }}</span>
            <span class="text-[10px] text-gray-400 leading-none mt-0.5">/ 100</span>
        </div>
    </div>

    {{-- Grade Badge --}}
    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold {{ $color['badge'] }}">
        {{ $grade }}
    </span>

    {{-- Label --}}
    <p class="text-sm font-medium text-gray-600 text-center">{{ $label }}</p>

    {{-- Trend --}}
    @if($trend)
    <div class="flex items-center gap-1">
        @if($trend === 'up')
            <svg class="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
            </svg>
            <span class="text-xs text-green-600">بهبود</span>
        @elseif($trend === 'down')
            <svg class="w-3.5 h-3.5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
            </svg>
            <span class="text-xs text-red-600">کاهش</span>
        @else
            <span class="text-xs text-gray-400">ثابت</span>
        @endif
    </div>
    @endif
</div>
