{{--
  core-ui::patch-status-badge
  Inline badge for patch status with channel icon and severity color.
  Props: $status (pending|installed|rolling_back|failed), $channel, $severity, $count
--}}
@props([
    'status'   => 'pending',
    'channel'  => 'stability',
    'severity' => 'medium',
    'count'    => null,
    'label'    => null,
    'pulse'    => false,
])
@php
    $sevColor = match($severity) {
        'critical' => 'bg-red-100 text-red-800 border-red-200',
        'high'     => 'bg-orange-100 text-orange-800 border-orange-200',
        'medium'   => 'bg-yellow-100 text-yellow-800 border-yellow-200',
        'low'      => 'bg-green-100 text-green-800 border-green-200',
        default    => 'bg-gray-100 text-gray-700 border-gray-200',
    };
    $statusIcon = match($status) {
        'installed'    => '✓',
        'rolling_back' => '↩',
        'failed'       => '✗',
        default        => '⏳',
    };
    $chanIcon = match($channel) {
        'security', 'critical', 'hotfix' => '🔒',
        'stability', 'fix', 'bugfix'     => '🔧',
        'feature', 'premium'             => '✨',
        default                          => '📦',
    };
    $displayLabel = $label ?? ucfirst($severity) . ' ' . ucfirst($channel);
@endphp

<span
    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium border {{ $sevColor }}"
    role="status"
    aria-label="{{ $displayLabel }}: {{ $status }}"
>
    @if($pulse && $status === 'pending')
    <span class="relative flex h-2 w-2">
        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-current opacity-75"></span>
        <span class="relative inline-flex h-2 w-2 rounded-full bg-current"></span>
    </span>
    @else
    <span aria-hidden="true">{{ $statusIcon }}</span>
    @endif

    <span aria-hidden="true">{{ $chanIcon }}</span>
    {{ $displayLabel }}

    @if($count !== null)
    <span class="ml-0.5 font-bold">({{ $count }})</span>
    @endif
</span>
